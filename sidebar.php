<?php
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/login_financial_years_helper.php';
require_once __DIR__ . '/includes/auragold_sidebar_nav_permissions.php';
require_once __DIR__ . '/includes/auragold_mobile_menu_settings.php';
require_once __DIR__ . '/includes/auragold_user_menu_preferences.php';
require_once __DIR__ . '/includes/auragold_employee_management_menu.php';
require_once __DIR__ . '/includes/auragold_gst_reports_catalog.php';
require_once __DIR__ . '/includes/branch_profile_schema.php';
require_once __DIR__ . '/includes/branch_working_context.php';
if (is_file(__DIR__ . '/includes/auragold_api_shop_connection.php')) {
    require_once __DIR__ . '/includes/auragold_api_shop_connection.php';
}
if (is_file(__DIR__ . '/includes/auragold_access_token.php')) {
    require_once __DIR__ . '/includes/auragold_access_token.php';
}
if (!defined('AURAGOLD_DASHBOARD_SHELL')) {
    require_once __DIR__ . '/includes/brand_page_loader.php';
    if (auragold_brand_page_loader_should_show()) {
        echo auragold_brand_page_loader_after_body_html();
    }
}
$auragold_nav_admin_role = !empty($_SESSION['Admin']) && auragold_session_is_admin_login_type();
$auragold_nav_basename = basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
$auragold_user_menu_style = auragold_get_user_menu_style();
$auragold_nav_collapse_hide = function_exists('auragold_t') ? auragold_t('nav.collapse_hide') : 'Hide menu';
$auragold_nav_collapse_show = function_exists('auragold_t') ? auragold_t('nav.collapse_show') : 'Show menu';
if ($auragold_nav_collapse_hide === 'nav.collapse_hide') {
    $auragold_nav_collapse_hide = 'Hide menu';
}
if ($auragold_nav_collapse_show === 'nav.collapse_show') {
    $auragold_nav_collapse_show = 'Show menu';
}

/** Client-side inactivity timer (ms); 0 = disabled. Matches AURAGOLD_IDLE_LOGOUT_SECONDS when set. */
$auragold_client_idle_ms = 0;
if (defined('AURAGOLD_IDLE_LOGOUT_SECONDS') && (int) AURAGOLD_IDLE_LOGOUT_SECONDS > 0
    && (int) ($_SESSION['user_id'] ?? 0) > 0) {
    $auragold_client_idle_ms = (int) AURAGOLD_IDLE_LOGOUT_SECONDS * 1000;
}

/** Top nav active state: each script basename belongs to at most one section (stock transfer lives under Inventory; Tax Return under Financial Statement). */
$auragold_dashboards_pages = [
    'dashboard-retailer.php',
    'dashboard-wholesaler.php',
    'dashboard-manufacturing.php',
    'dashboard-sales-person.php',
    'dashboard.php',
    'dashboard-stock.php',
];
$auragold_utilities_pages = [
    'product-opening.php',
    'account-ledger.php',
    'customer-details-import.php',
    'delete-customer-entries.php',
    'metal-to-amount.php',
    'amount-to-metal.php',
    'bank-reconciliation.php',
];
$auragold_transaction_pages = [
    'sale-invoice.php',
    'draft-sale-invoice.php',
    'pos-sale-invoice.php',
    'sale-quotations.php',
    'sale-return.php',
    'purchase-invoice.php',
    'purchase-quotation.php',
    'purchase-return.php',
    'payment-voucher.php',
    'receipt-voucher.php',
    'advance-payment.php',
    'cheque-entry.php',
    'material-issue.php',
    'material-receive.php',
    'old-jewelry-scrap-invoice.php',
    'old-jewellery.php',
    'stock-journal.php',
    'credit-note.php',
    'debit-note.php',
    'contra-voucher.php',
    'journal-voucher.php',
    'expense-invoice.php',
    'income-invoice.php',
    'repair-invoice.php',
    'investment-fund.php',
    'installment-report.php',
];
$auragold_inventory_pages = [
    'stock-transfer.php',
    'stock-transfer-history.php',
    'stock-receive-history.php',
    'gold-and-silver.php',
    'barcode-management.php',
    'gold-silver-analysis.php',
    'platinum-analysis.php',
    'platinum-stock.php',
    'diamond-stone-analysis.php',
    'diamond-stock.php',
    'diamond-and-stones.php',
    'jewelry-catalogue.php',
    'imitation-and-watches.php',
    'imitation-analysis.php',
    'stock-history.php',
    'stock-history-ledger.php',
    'rfid-barcode-scan.php',
];
$auragold_orders_pages = [
    'sale-order.php',
    'repair-order.php',
    'sale-order-process.php',
    'repair-order-process.php',
    'purchase-order.php',
    'jobwork-order.php',
    'jobwork-order-print.php',
    'manufacturing-outsource.php',
    'job-work-order-manufacturing.php',
];
$auragold_manufacturer_pages = [
    'department.php',
    'department-report.php',
    'outsource-department-report.php',
    'manufacturing-process.php',
    'closing-report.php',
    'loss-tracking.php',
    'jobwork-queue.php',
    'job-card-print.php',
];
$auragold_financial_statement_pages = [
    'trial-balance.php',
    'balance-sheet.php',
    'profit-loss.php',
    'cash-flow.php',
    'fund-flow.php',
    'chart-of-account.php',
    'tax-return.php',
    'sale-analysis.php',
    'purchase-financial-analysis.php',
    'trial-balance-detailed-report.php',
];
$auragold_report_pages = [
    'transaction-report.php',
    'accountledger-report.php',
    'day-report.php',
    'ageing-report.php',
    'ledger-balance-report.php',
    'Ledger-Balance-Report.php',
    'kyc-report.php',
    'reward-point-report.php',
    'barcode-scan-report.php',
];
$auragold_gst_report_pages = [
    'gst-reports.php',
    'gst-report.php',
];
$auragold_employee_management_pages = [
    'employee-dashboard.php',
    'employee-attendance.php',
    'employee-advance.php',
    'employee-advance-request.php',
    'employee-incentive.php',
    'expense-category.php',
    'employee-expenses.php',
    'employee-reports.php',
    'employee-salary-payroll.php',
    'assign-inventory-to-sales-team.php',
    'assign-inventory.php',
    'unassign-inventory.php',
    'assign-inventory-items.php',
];
$auragold_settings_pages = [
    'set-software.php',
    'language-settings.php',
    'mail-settings.php',
    'voucher-type.php',
    'invoice-print-settings.php',
    'ewaybill-api-settings.php',
    'ewaybill-authentication.php',
    'user-management.php',
    'department-management.php',
    'designation-management.php',
    'masters.php',
    'exchange-rate.php',
    'accounting-masters.php',
    'credit-card.php',
    'role-management.php',
    'permission-management.php',
    'whitelist-management.php',
    'blocklist-management.php',
    'crm.php',
];

$auragold_dashboards_nav_active = in_array($auragold_nav_basename, $auragold_dashboards_pages, true);
$auragold_utilities_nav_active = in_array($auragold_nav_basename, $auragold_utilities_pages, true);
$auragold_transaction_nav_active = in_array($auragold_nav_basename, $auragold_transaction_pages, true);
$auragold_inventory_nav_active = in_array($auragold_nav_basename, $auragold_inventory_pages, true);
$auragold_orders_nav_active = in_array($auragold_nav_basename, $auragold_orders_pages, true);
$auragold_manufacturer_nav_active = in_array($auragold_nav_basename, $auragold_manufacturer_pages, true);
$auragold_financial_statement_nav_active = in_array($auragold_nav_basename, $auragold_financial_statement_pages, true);
$auragold_report_nav_active = in_array($auragold_nav_basename, $auragold_report_pages, true);
$auragold_gst_report_nav_active = in_array($auragold_nav_basename, $auragold_gst_report_pages, true);
$auragold_employee_management_nav_active = in_array($auragold_nav_basename, $auragold_employee_management_pages, true);
$auragold_settings_nav_active = in_array($auragold_nav_basename, $auragold_settings_pages, true);

/**
 * External CRM auto-login (opens in a new tab).
 */
$auragold_crm_external_url = 'https://crmsoftware.goldmatrixsoftware.com/';
$auragold_crm_shop_email = '';
$auragold_crm_access_token = '';
if (function_exists('auragold_bootstrap_session_shop_access_token')) {
    $auragold_crm_access_token = auragold_bootstrap_session_shop_access_token();
}
if ($auragold_crm_access_token === '') {
    $auragold_crm_access_token = trim((string) ($_SESSION['shop_access_token'] ?? $_SESSION['Admin']['shop_access_token'] ?? ''));
}
$auragold_crm_bid = 0;
if (function_exists('auragold_my_profile_target_branch_id')) {
    $auragold_crm_bid = (int) auragold_my_profile_target_branch_id();
}
if ($auragold_crm_bid <= 0) {
    $auragold_crm_bid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
}
if ($auragold_crm_bid > 0 && function_exists('getRecordMaster')) {
    $auragold_crm_br = @getRecordMaster(
        'SELECT email, main_branch_id FROM tbl_branches WHERE id = ' . $auragold_crm_bid . ' LIMIT 1'
    );
    if (is_array($auragold_crm_br)) {
        $auragold_crm_shop_email = trim((string) ($auragold_crm_br['email'] ?? ''));
        if ($auragold_crm_access_token === '' && function_exists('auragold_ensure_shop_access_token')) {
            $auragold_crm_main_bid = (int) ($auragold_crm_br['main_branch_id'] ?? 0);
            global $conn_master;
            $auragold_crm_access_token = auragold_ensure_shop_access_token(
                $conn_master,
                $auragold_crm_main_bid > 0 ? $auragold_crm_main_bid : $auragold_crm_bid
            );
        }
    }
}
if ($auragold_crm_shop_email === '') {
    $auragold_crm_shop_email = trim((string) ($_SESSION['Admin']['EmailId'] ?? $_SESSION['Admin']['email'] ?? ''));
}
if ($auragold_crm_shop_email !== '' && $auragold_crm_access_token !== '') {
    $auragold_crm_external_url = 'https://crmsoftware.goldmatrixsoftware.com/autologin/'
        . rawurlencode($auragold_crm_shop_email) . '/'
        . rawurlencode($auragold_crm_access_token);
}

/** Resolve tbl_branches row from central registry (config.php already hydrates session context). */
if (empty($GLOBALS['auragold_active_branch_row'])
    && function_exists('auragold_ensure_operational_session_context')) {
    auragold_ensure_operational_session_context();
}

$auragold_header_db_name = function_exists('auragold_resolve_active_operational_db_name')
    ? auragold_resolve_active_operational_db_name()
    : '';
$auragold_header_db_title = '';
$auragold_header_conn_alert = '';
$auragold_ops_conn_ok       = false;

global $conn;
if (isset($conn) && $conn instanceof mysqli) {
    $auragold_ops_conn_ok = (@mysqli_query($conn, 'SELECT 1') !== false);
}

$auragold_header_branch_row = function_exists('auragold_resolve_active_branch_row')
    ? auragold_resolve_active_branch_row()
    : null;

/** Top-left mark: working-branch shop logo (my-profile / tbl_branches.logo_path). Top-right avatar: user photo, else shop logo, else default. */
$auragold_header_avatar_src    = 'assets/img/avatars/1.png';
$auragold_header_avatar_style  = 'cursor: pointer;';
$auragold_header_shop_logo_url = '';
$auragold_header_shop_name     = '';
if (is_array($auragold_header_branch_row)) {
    $auragold_header_shop_name = trim((string) ($auragold_header_branch_row['name'] ?? ''));
    $hlp = trim((string) ($auragold_header_branch_row['logo_path'] ?? ''));
    if ($hlp !== '' && is_file(__DIR__ . '/' . $hlp)) {
        $auragold_header_shop_logo_url = $hlp . '?v=' . (int) @filemtime(__DIR__ . '/' . $hlp);
    }
}
if (function_exists('getRecord')) {
    global $conn_master, $conn;
    $auragold_user_db = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    if (!empty($auragold_user_db)) {
        require_once __DIR__ . '/includes/branch_profile_schema.php';
        auragold_ensure_tbl_users_profile_photo_column($auragold_user_db);
        $auragold_avatar_from_user = false;
        $hb_uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($hb_uid > 0) {
            $uav = @getRecord('SELECT profile_photo FROM tbl_users WHERE id = ' . $hb_uid . ' LIMIT 1');
            if (is_array($uav)) {
                $upp = trim((string) ($uav['profile_photo'] ?? ''));
                if ($upp !== '' && is_file(__DIR__ . '/' . $upp)) {
                    $auragold_header_avatar_src = $upp . '?v=' . (int) @filemtime(__DIR__ . '/' . $upp);
                    $auragold_avatar_from_user  = true;
                }
            }
        }
        if (!$auragold_avatar_from_user && $auragold_header_shop_logo_url !== '') {
            $auragold_header_avatar_src = $auragold_header_shop_logo_url;
        }
    }
}

$wbn = $auragold_header_shop_name !== '' ? $auragold_header_shop_name : trim((string) ($_SESSION['working_branch_name'] ?? ''));
if ($auragold_header_db_name !== '') {
    $auragold_header_db_title = $wbn !== ''
        ? 'Branch: ' . $wbn . ' — MySQL database: ' . $auragold_header_db_name
        : 'Connected MySQL database: ' . $auragold_header_db_name;
}

/** Short FY label (e.g. 2025-26) — same as header pill; server enforces non-empty when FY is required. */
$auragold_fy_short_label = function_exists('auragold_header_financial_year_short_label')
    ? auragold_header_financial_year_short_label()
    : auragold_session_financial_year_short_label();
$auragold_header_branch_pill = $wbn;
if ($auragold_header_branch_pill === '') {
    $auragold_header_branch_pill = $auragold_header_shop_name;
}
if ($auragold_header_branch_pill === '' && function_exists('auragold_t')) {
    $auragold_header_branch_pill = auragold_t('user.main_branch');
} elseif ($auragold_header_branch_pill === '') {
    $auragold_header_branch_pill = 'Main Branch';
}
$auragold_header_identity_title = 'GoldMatrix';
if (defined('COMPANY_NAME') && trim((string) constant('COMPANY_NAME')) !== '') {
    $auragold_header_identity_title = trim((string) constant('COMPANY_NAME'));
} elseif (isset($Proj_Title) && trim((string) $Proj_Title) !== '') {
    $auragold_header_identity_title = trim((string) $Proj_Title);
}
if ($auragold_header_shop_name !== '') {
    $auragold_header_identity_title = $auragold_header_shop_name;
} elseif ($wbn !== '') {
    $auragold_header_identity_title = $wbn;
}

if (!empty($_SESSION['Admin'])) {
    if (!$auragold_ops_conn_ok) {
        $auragold_header_conn_alert = 'AuraGold: Operational database connection is missing or not responding. Sign in again or check that MySQL is running.';
    } elseif ($auragold_header_db_name === '') {
        $auragold_header_conn_alert = 'AuraGold: The active database name could not be determined. Sign in again or contact support.';
    }
}
?>
<script>(function(){var s=<?php echo json_encode($auragold_user_menu_style, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;if(document.body){document.body.classList.add('auragold-menu-'+s);}else{document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('auragold-menu-'+s);});}})();</script>
<?php if (function_exists('auragold_timezone_js_globals')) { echo auragold_timezone_js_globals(); } ?>
<script>
(function () {
  if (!window.AURAGOLD_TZ || !window.AURAGOLD_TZ.today) return;
  window.auragoldBranchToday = function () { return String(window.AURAGOLD_TZ.today || ''); };
  window.auragoldBranchNow = function () { return String(window.AURAGOLD_TZ.now || ''); };
})();
</script>
<style id="auragold-mobile-hide-footer">
@media (max-width: 991.98px) {
    nav.layout-footer,
    .layout-footer.footer,
    .layout-footer {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        min-height: 0 !important;
        overflow: hidden !important;
        pointer-events: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
}
</style>
<div class="auragold-nav-backdrop" id="auragoldNavBackdrop" aria-hidden="true"></div>
<!-- Company Header -->
                    <div class="company-header">
                        <button type="button" class="auragold-mobile-menu-btn d-lg-none" id="auragoldMobileMenuBtn" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mobile.open_menu'), ENT_QUOTES, 'UTF-8') : 'Open menu'; ?>" aria-expanded="false" aria-controls="auragoldTopNav">
                            <i class="feather icon-menu" aria-hidden="true"></i>
                        </button>
                        <div class="company-header-start">
                        <div class="company-header-left">
                            <button type="button" class="auragold-top-nav-collapse-tab" id="auragoldTopNavCollapseTab" title="<?php echo htmlspecialchars($auragold_nav_collapse_hide, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="true" aria-controls="auragoldTopNav" data-auragold-title-show="<?php echo htmlspecialchars($auragold_nav_collapse_show, ENT_QUOTES, 'UTF-8'); ?>" data-auragold-title-hide="<?php echo htmlspecialchars($auragold_nav_collapse_hide, ENT_QUOTES, 'UTF-8'); ?>"><i class="feather icon-menu" aria-hidden="true"></i></button>
                            <div class="auragold-header-identity">
                                <div class="auragold-header-identity__mark<?php echo $auragold_header_shop_logo_url !== '' ? ' auragold-header-identity__mark--has-logo' : ''; ?>"<?php echo $auragold_header_shop_logo_url !== '' ? ' role="img" aria-label="' . htmlspecialchars(function_exists('auragold_t') ? auragold_t('user.shop_logo') : 'Shop logo', ENT_QUOTES, 'UTF-8') . '"' : ' aria-hidden="true"'; ?>>
                                    <?php if ($auragold_header_shop_logo_url !== ''): ?>
                                    <img class="auragold-header-identity__mark-img" src="<?php echo htmlspecialchars($auragold_header_shop_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="36" height="36" loading="lazy" decoding="async" onerror="this.style.visibility='hidden';if(this.nextElementSibling)this.nextElementSibling.classList.remove('d-none');">
                                    <i class="feather icon-layers d-none" aria-hidden="true"></i>
                                    <?php else: ?>
                                    <i class="feather icon-layers" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="auragold-header-identity__text">
                                    <div class="auragold-header-identity__title"><?php echo htmlspecialchars($auragold_header_identity_title, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="auragold-header-identity__pill" title="<?php
                                    $___pill_tip = ($auragold_fy_short_label !== '' ? 'FY: ' . $auragold_fy_short_label : '') . ($auragold_fy_short_label !== '' && $auragold_header_branch_pill !== '' ? ' | ' : '') . $auragold_header_branch_pill;
                                    echo htmlspecialchars($___pill_tip, ENT_QUOTES, 'UTF-8');
                                    ?>">
                                        <?php if ($auragold_fy_short_label !== ''): ?>
                                            <span class="auragold-header-identity__pill-fy"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.fy'), ENT_QUOTES, 'UTF-8') : 'FY'; ?>: <?php echo htmlspecialchars($auragold_fy_short_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($auragold_fy_short_label !== '' && $auragold_header_branch_pill !== ''): ?>
                                            <span class="auragold-header-identity__pill-sep" aria-hidden="true">|</span>
                                        <?php endif; ?>
                                        <?php if ($auragold_header_branch_pill !== ''): ?>
                                            <span class="auragold-header-identity__pill-branch"><?php echo htmlspecialchars($auragold_header_branch_pill, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="company-header-search d-none d-lg-flex" id="auragoldMenuSearchWrap">
                            <div class="auragold-menu-search" role="search">
                                <i class="feather icon-search auragold-menu-search__icon" aria-hidden="true"></i>
                                <input type="search"
                                    class="auragold-menu-search__input"
                                    id="auragoldMenuSearchInput"
                                    name="menu-search"
                                    placeholder="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.search_menu'), ENT_QUOTES, 'UTF-8') : 'Search menu…'; ?>"
                                    autocomplete="search"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    data-lpignore="true"
                                    data-form-type="other"
                                    aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.search_menu'), ENT_QUOTES, 'UTF-8') : 'Search menu'; ?>"
                                    aria-controls="auragoldMenuSearchResults"
                                    aria-expanded="false"
                                    aria-autocomplete="list">
                                <div class="auragold-menu-search-results" id="auragoldMenuSearchResults" role="listbox" style="display:none;"></div>
                            </div>
                        </div>
                        </div>
                        <div class="company-header-center">
                            <div class="company-info-logo">
                                <img class="auragold-header-logo" src="logott.png" alt="GoldMatrix">
                            </div>
                        </div>
                        <div class="user-info">
                            <?php if (!empty($_SESSION['Admin'])): ?>
                                <?php if ($auragold_ops_conn_ok && $auragold_header_db_name !== ''): ?>
                            <span class="auragold-header-db-name notranslate" translate="no" title="<?php echo htmlspecialchars($auragold_header_db_title, ENT_QUOTES, 'UTF-8'); ?>"><span class="auragold-header-db-label"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.db_label'), ENT_QUOTES, 'UTF-8') : 'DB'; ?></span><?php echo htmlspecialchars($auragold_header_db_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php elseif (!$auragold_ops_conn_ok): ?>
                            <span class="auragold-header-db-name auragold-header-db-name--error" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.db_no_connection'), ENT_QUOTES, 'UTF-8') : 'No operational database connection'; ?>"><span class="auragold-header-db-label"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.db_label'), ENT_QUOTES, 'UTF-8') : 'DB'; ?></span>—</span>
                                <?php else: ?>
                            <span class="auragold-header-db-name auragold-header-db-name--warn" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.db_unknown'), ENT_QUOTES, 'UTF-8') : 'Database name unknown'; ?>"><span class="auragold-header-db-label"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.db_label'), ENT_QUOTES, 'UTF-8') : 'DB'; ?></span>?</span>
                                <?php endif; ?>
                            <?php endif; ?>
                                <div id="auragoldGtSlotDesktop" class="auragold-gt-slot auragold-gt-slot--desktop">
                                    <div id="google_translate_element" class="auragold-google-translate skiptranslate notranslate" translate="no" title="Translate page" aria-label="Translate page"></div>
                                </div>
                                <button type="button" class="btn-icon d-none d-lg-flex" id="fullscreenBtn" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.fullscreen'), ENT_QUOTES, 'UTF-8') : 'Fullscreen (Use F11 for persistent fullscreen across pages)'; ?>" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.fullscreen'), ENT_QUOTES, 'UTF-8') : 'Fullscreen'; ?>"><i class="feather icon-maximize-2" aria-hidden="true"></i></button>
                                <div class="auragold-notif-dropdown" id="auragoldNotifWrap">
                                    <button type="button" class="btn-icon d-flex auragold-header-notif-btn position-relative" id="auragoldNotifToggle" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.notifications'), ENT_QUOTES, 'UTF-8') : 'Notifications'; ?>" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.notifications'), ENT_QUOTES, 'UTF-8') : 'Notifications'; ?>" aria-expanded="false" aria-haspopup="true">
                                        <i class="feather icon-bell" aria-hidden="true"></i>
                                        <span class="badge-notification auragold-notif-badge--empty" id="auragoldNotifBadge" style="display: none;"></span>
                                    </button>
                                    <div class="auragold-notif-menu" id="auragoldNotifMenu" aria-labelledby="auragoldNotifToggle" role="menu">
                                        <div class="auragold-notif-menu-header">
                                            <span class="auragold-notif-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.notifications'), ENT_QUOTES, 'UTF-8') : 'Notifications'; ?></span>
                                        </div>
                                        <div id="auragoldNotifList" class="auragold-notif-list"></div>
                                        <div class="auragold-notif-footer d-flex justify-content-between align-items-center border-top px-3 py-2">
                                            <button type="button" class="btn btn-link btn-sm auragold-notif-footer-btn p-0" id="auragoldNotifMarkAll">Mark all as Read</button>
                                            <a class="auragold-notif-footer-btn small" href="notifications.php">View All</a>
                                        </div>
                                    </div>
                                </div>
                            <div class="dropdown user-dropdown" id="userDropdown">
                                <ul class="dropdown-menu user-dropdown-menu" id="userDropdownMenu">
                                    <?php
                                    /** Person name for UI: Fname + Lname from tbl_users (session Admin), never branch name. */
                                    $auragold_person_full_name = '';
                                    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
                                        $ad = $_SESSION['Admin'];
                                        $fn = isset($ad['Fname']) ? trim((string) $ad['Fname']) : (isset($ad['fname']) ? trim((string) $ad['fname']) : '');
                                        $ln = isset($ad['Lname']) ? trim((string) $ad['Lname']) : (isset($ad['lname']) ? trim((string) $ad['lname']) : '');
                                        $auragold_person_full_name = trim($fn . ' ' . $ln);
                                    }
                                    if ($auragold_person_full_name === '' && !empty($_SESSION['name'])) {
                                        $auragold_person_full_name = trim((string) $_SESSION['name']);
                                    }
                                    if ($auragold_person_full_name === '' && !empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
                                        $ad = $_SESSION['Admin'];
                                        $u  = $ad['Username'] ?? $ad['username'] ?? '';
                                        $auragold_person_full_name = trim((string) $u);
                                    }
                                    if ($auragold_person_full_name === '') {
                                        $auragold_person_full_name = function_exists('auragold_t') ? auragold_t('user.fallback_name') : 'User';
                                    }

                                    $auragold_working_db = '';
                                    if (!empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
                                        $auragold_working_db = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
                                    }
                                    $auragold_active_mysql_db = ($auragold_working_db !== '')
                                        ? $auragold_working_db
                                        : (defined('DB_NAME') ? (string) DB_NAME : '');

                                    /** Dropdown title: branch name from tbl_branches (registry). Sub-branch by id; else first main row. */
                                    $auragold_dropdown_branch_title = '';
                                    if (function_exists('auragold_resolve_active_branch_row')) {
                                        $activeBrRow = auragold_resolve_active_branch_row();
                                        if (is_array($activeBrRow)) {
                                            $auragold_dropdown_branch_title = trim((string) ($activeBrRow['name'] ?? ''));
                                        }
                                    }
                                    if ($auragold_dropdown_branch_title === '' && function_exists('getRecordMaster')) {
                                        $auragold_bid = (int) ($_SESSION['auragold_login_branch_id'] ?? 0);
                                        if ($auragold_bid <= 0) {
                                            $auragold_bid = (int) ($_SESSION['working_branch_id'] ?? 0);
                                        }
                                        if ($auragold_bid <= 0) {
                                            $auragold_bid = (int) ($_SESSION['branch_id'] ?? 0);
                                        }
                                        if ($auragold_bid > 0) {
                                            $br = @getRecordMaster(
                                                'SELECT name FROM tbl_branches WHERE id = ' . $auragold_bid . ' LIMIT 1'
                                            );
                                            if (is_array($br)) {
                                                $auragold_dropdown_branch_title = trim((string) ($br['name'] ?? ''));
                                            }
                                        }
                                        if ($auragold_dropdown_branch_title === '') {
                                            $mainBr = @getRecordMaster(
                                                'SELECT name FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1'
                                            );
                                            if (is_array($mainBr)) {
                                                $auragold_dropdown_branch_title = trim((string) ($mainBr['name'] ?? ''));
                                            }
                                        }
                                    }
                                    if ($auragold_dropdown_branch_title === '' && !empty($_SESSION['working_branch_name'])) {
                                        $auragold_dropdown_branch_title = trim((string) $_SESSION['working_branch_name']);
                                    }
if ($auragold_dropdown_branch_title === '') {
        $auragold_dropdown_branch_title = function_exists('auragold_t') ? auragold_t('user.main_branch') : 'Main Branch';
    }

                                    $auragold_header_display_name = $auragold_person_full_name;
                                    ?>
                                    <li class="user-dropdown-menu-header">
                                        <div class="udh-title"><?php echo htmlspecialchars($auragold_dropdown_branch_title); ?></div>
                                        <?php if ($auragold_fy_short_label !== ''): ?>
                                            <div class="udh-sub"><span class="udh-label"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.fy'), ENT_QUOTES, 'UTF-8') : 'FY'; ?></span> : <?php echo htmlspecialchars($auragold_fy_short_label); ?></div>
                                        <?php endif; ?>
                                        <div class="udh-sub"><span class="udh-label"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.username'), ENT_QUOTES, 'UTF-8') : 'Username'; ?></span><?php echo htmlspecialchars($auragold_person_full_name); ?></div>
                                        <?php if ($auragold_active_mysql_db !== ''): ?>
                                            <!-- <div class="udh-sub" title="Stock and transactions use this MySQL database (from tbl_branches for the selected branch, or main row for default). Registry is usually <?php echo defined('AURAGOLD_REGISTRY_DB') ? htmlspecialchars((string) AURAGOLD_REGISTRY_DB) : 'auragold'; ?>.">
                                                <span class="udh-label">MySQL DB</span><?php echo htmlspecialchars($auragold_active_mysql_db); ?>
                                            </div> -->
                                        <?php endif; ?>
                                    </li>
                                    <li><a class="dropdown-item" href="my-profile.php"><i class="feather icon-user"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.my_profile'), ENT_QUOTES, 'UTF-8') : 'My Profile'; ?></a></li>
                                    <li><a class="dropdown-item" href="change-password.php"><i class="feather icon-lock"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.change_password'), ENT_QUOTES, 'UTF-8') : 'Change Password'; ?></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="feather icon-bell"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.notifications'), ENT_QUOTES, 'UTF-8') : 'Notifications'; ?></a></li>
                                    <?php
                                    $auragold_profile_switch_branches = function_exists('auragold_profile_dropdown_switchable_branches')
                                        ? auragold_profile_dropdown_switchable_branches()
                                        : [];
                                    if (!is_array($auragold_profile_switch_branches)) {
                                        $auragold_profile_switch_branches = [];
                                    }
                                    ?>
                                    <?php if ($auragold_profile_switch_branches !== []): ?>
                                    <li class="dropdown-divider"></li>
                                    <li class="user-dropdown-branch-label" aria-hidden="true">
                                        <span><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.branch'), ENT_QUOTES, 'UTF-8') : 'Branch'; ?></span>
                                    </li>
                                    <?php foreach ($auragold_profile_switch_branches as $swBr): ?>
                                    <li>
                                        <button type="button"
                                            class="dropdown-item user-dropdown-branch-item<?php echo !empty($swBr['is_current']) ? ' is-current' : ''; ?><?php echo !empty($swBr['is_main']) ? ' is-main' : ' is-sub'; ?>"
                                            data-branch-id="<?php echo (int) ($swBr['id'] ?? 0); ?>"
                                            data-branch-name="<?php echo htmlspecialchars((string) ($swBr['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo !empty($swBr['is_current']) ? ' aria-current="true"' : ''; ?>>
                                            <span class="udb-avatar" aria-hidden="true"><?php echo htmlspecialchars((string) ($swBr['initials'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($swBr['is_current'])): ?><span class="udb-dot"></span><?php endif; ?></span>
                                            <span class="udb-name"><?php echo htmlspecialchars((string) ($swBr['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </button>
                                    </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <a class="dropdown-item user-dropdown-manage-branches" href="branches.php">
                                            <i class="feather icon-layers"></i>
                                            <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.manage_branches'), ENT_QUOTES, 'UTF-8') : 'Manage Branches'; ?>
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li><a class="dropdown-item" href="branches.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.branch'), ENT_QUOTES, 'UTF-8') : 'Branch'; ?></a></li>
                                    <?php endif; ?>
                                    <!-- <li><a class="dropdown-item" href="#"><i class="feather icon-settings"></i> Account Settings</a></li> -->
                                    <!-- <li class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#"><i class="feather icon-shield"></i> Security</a></li>
                                    
                                    <li><a class="dropdown-item" href="#"><i class="feather icon-help-circle"></i> Help & Support</a></li> -->
                                    <li class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="feather icon-log-out"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('user.logout'), ENT_QUOTES, 'UTF-8') : 'Logout'; ?></a></li>
                                </ul>
                                <img id="userAvatarToggle" src="<?php echo htmlspecialchars($auragold_header_avatar_src); ?>" alt="" class="d-block rounded-circle auragold-header-avatar" style="<?php echo htmlspecialchars($auragold_header_avatar_style); ?>" title="<?php echo htmlspecialchars($auragold_header_display_name ?? 'User'); ?>" onerror="this.onerror=null;this.src='assets/img/avatars/1.png';">
                            </div>
                        </div>
                    </div>

                    <!-- Top Navigation Tabs (mobile: drawer; desktop: horizontal strip or vertical sidebar) -->
                    <div class="auragold-top-nav-wrap" id="auragoldTopNavWrap">
                    <nav class="top-navbar" id="auragoldTopNav" role="navigation" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.aria_main'), ENT_QUOTES, 'UTF-8') : 'Main'; ?>">
                        <div class="auragold-mobile-nav-topbar d-lg-none">
                            <span class="auragold-mobile-nav-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mobile.title_menu'), ENT_QUOTES, 'UTF-8') : 'Menu'; ?></span>
                            <button type="button" class="auragold-mobile-nav-close" id="auragoldMobileNavClose" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mobile.close_menu'), ENT_QUOTES, 'UTF-8') : 'Close menu'; ?>">
                                <i class="feather icon-x" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="auragoldGtSlotMobile" class="auragold-gt-slot auragold-gt-slot--mobile d-lg-none" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('set.language'), ENT_QUOTES, 'UTF-8') : 'Language'; ?>"></div>
                        <script>(function(){try{var e=document.getElementById('google_translate_element'),m=document.getElementById('auragoldGtSlotMobile'),d=document.getElementById('auragoldGtSlotDesktop');if(e&&m&&d&&window.matchMedia('(max-width:991.98px)').matches&&e.parentElement!==m){m.appendChild(e);}}catch(x){}})();</script>
                        <div class="d-flex justify-content-between align-items-center top-navbar-inner">
                        <ul class="nav nav-tabs border-0">
                            <?php if (function_exists('auragold_nav_sidebar_entirely_hidden_for_session') && auragold_nav_sidebar_entirely_hidden_for_session()): ?>
                            <li class="nav-item">
                                <a class="nav-link<?php echo $auragold_nav_basename === 'dashboard.php' ? ' active' : ''; ?>" href="dashboard.php"><i class="feather icon-grid"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboard'), ENT_QUOTES, 'UTF-8') : 'Dashboard'; ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php echo $auragold_nav_basename === 'permission-management.php' ? ' active' : ''; ?>" href="permission-management.php"><i class="feather icon-lock"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.permissions'), ENT_QUOTES, 'UTF-8') : 'Permissions'; ?></a>
                            </li>
                            <?php else: ?>
                            <?php if (auragold_nav_module_has_visible_link('dashboards')): ?>
                            <li class="nav-item" data-mm-module="dashboards">
                                <a class="nav-link<?php echo $auragold_dashboards_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-grid"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards'), ENT_QUOTES, 'UTF-8') : 'Dashboards'; ?></a>
                                <ul class="dropdown-menu">
                                    <?php if (auragold_nav_show_php_href('dashboard-retailer.php')): ?><li><a class="dropdown-item" href="dashboard-retailer.php"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards.retailer'), ENT_QUOTES, 'UTF-8') : 'Retailer'; ?></a></li><?php endif; ?>
                                    <?php if (auragold_nav_show_php_href('dashboard-wholesaler.php')): ?><li><a class="dropdown-item" href="dashboard-wholesaler.php"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards.wholesaler'), ENT_QUOTES, 'UTF-8') : 'Wholesaler'; ?></a></li><?php endif; ?>
                                    <?php if (auragold_nav_show_php_href('dashboard-manufacturing.php')): ?><li><a class="dropdown-item" href="dashboard-manufacturing.php"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards.mfg_jobworker'), ENT_QUOTES, 'UTF-8') : 'Manufacturing / Job worker'; ?></a></li><?php endif; ?>
                                    <?php if (auragold_nav_show_php_href('dashboard-sales-person.php')): ?><li><a class="dropdown-item" href="dashboard-sales-person.php"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards.sales_person'), ENT_QUOTES, 'UTF-8') : 'Sales person'; ?></a></li><?php endif; ?>
                                    <?php if (auragold_nav_show_php_href('dashboard.php')): ?><li><a class="dropdown-item" href="dashboard.php"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards.gold_rates'), ENT_QUOTES, 'UTF-8') : 'Gold rates'; ?></a></li><?php endif; ?>
                                    <?php if (auragold_nav_show_php_href('dashboard-stock.php')): ?><li><a class="dropdown-item" href="dashboard-stock.php"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.dashboards.stock'), ENT_QUOTES, 'UTF-8') : 'Stock'; ?></a></li><?php endif; ?>
                                </ul>
                        </li>
                            <?php endif; ?>
                        

                            <?php if (auragold_nav_module_has_visible_link('utilities')): ?>
                            <li class="nav-item" data-mm-module="utilities">
                                    <a class="nav-link<?php echo $auragold_utilities_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-box"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.utilities'), ENT_QUOTES, 'UTF-8') : 'Opening'; ?></a>
                                    <ul class="dropdown-menu utilities-submenu">
                                        <?php if (auragold_nav_show_php_href('product-opening.php')): ?><li><a class="dropdown-item" href="product-opening.php"><i class="feather icon-package"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.util.product_opening'), ENT_QUOTES, 'UTF-8') : 'Product Opening'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('account-ledger.php')): ?><li><a class="dropdown-item" href="account-ledger.php"><i class="feather icon-book"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.util.account_ledger'), ENT_QUOTES, 'UTF-8') : 'Account Ledger'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('customer-details-import.php')): ?><li><a class="dropdown-item" href="customer-details-import.php"><i class="feather icon-upload"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.util.customer_details_import'), ENT_QUOTES, 'UTF-8') : 'Customer Details Import'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('delete-customer-entries.php')): ?><li><a class="dropdown-item" href="delete-customer-entries.php"><i class="feather icon-trash-2"></i> Delete Customer Entries</a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('metal-to-amount.php')): ?><li><a class="dropdown-item" href="metal-to-amount.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.util.metal_to_amount'), ENT_QUOTES, 'UTF-8') : 'Metal to Amount'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('amount-to-metal.php')): ?><li><a class="dropdown-item" href="amount-to-metal.php"><i class="feather icon-repeat"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.util.amount_to_metal'), ENT_QUOTES, 'UTF-8') : 'Amount to Metal'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('bank-reconciliation.php')): ?><li data-mm-page="utilities.bank_reconciliation"><a class="dropdown-item" href="bank-reconciliation.php"><i class="feather icon-refresh-cw"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.util.bank_recon'), ENT_QUOTES, 'UTF-8') : 'Bank Reconciliation'; ?></a></li><?php endif; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('transaction')): ?>
                            <li class="nav-item mega-menu-item" data-mm-module="transaction">
                                    <a class="nav-link<?php echo $auragold_transaction_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.transaction'), ENT_QUOTES, 'UTF-8') : 'Operations'; ?></a>
                                    <div class="mega-menu">
                                        <div class="mega-menu-content">
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.sales_purchase'), ENT_QUOTES, 'UTF-8') : 'Sales & Purchase'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('sale-invoice.php')): ?><li><a class="dropdown-item" href="sale-invoice.php"><i class="feather icon-shopping-cart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.sale_invoice'), ENT_QUOTES, 'UTF-8') : 'Sales Invoice'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('draft-sale-invoice.php')): ?><li><a class="dropdown-item" href="draft-sale-invoice.php"><i class="feather icon-edit"></i> Draft Invoices</a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('pos-sale-invoice.php')): ?><li><a class="dropdown-item" href="pos-sale-invoice.php"><i class="feather icon-cpu"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.pos_sale_invoice'), ENT_QUOTES, 'UTF-8') : 'Quick POS Billing'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('sale-quotations.php')): ?><li><a class="dropdown-item" href="sale-quotations.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.sale_quotation'), ENT_QUOTES, 'UTF-8') : 'Sales enquiry'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('sale-return.php')): ?><li><a class="dropdown-item" href="sale-return.php"><i class="feather icon-repeat"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.sale_return'), ENT_QUOTES, 'UTF-8') : 'Sales Return'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('purchase-invoice.php')): ?><li><a class="dropdown-item" href="purchase-invoice.php"><i class="feather icon-file"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.purchase_invoice'), ENT_QUOTES, 'UTF-8') : 'Purchase Invoice'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('purchase-quotation.php')): ?><li><a class="dropdown-item" href="purchase-quotation.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.purchase_quotation'), ENT_QUOTES, 'UTF-8') : 'Purchase Inquiry'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('purchase-return.php')): ?><li><a class="dropdown-item" href="purchase-return.php"><i class="feather icon-rotate-ccw"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.purchase_return'), ENT_QUOTES, 'UTF-8') : 'Purchase Return'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.payments_receipts'), ENT_QUOTES, 'UTF-8') : 'Payments & Receipts'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('payment-voucher.php')): ?><li><a class="dropdown-item" href="payment-voucher.php"><i class="feather icon-credit-card"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.payment_voucher'), ENT_QUOTES, 'UTF-8') : 'Payment Voucher'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('receipt-voucher.php')): ?><li><a class="dropdown-item" href="receipt-voucher.php"><i class="feather icon-credit-card"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.receipt_voucher'), ENT_QUOTES, 'UTF-8') : 'Receipt Voucher'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('advance-payment.php')): ?><li><a class="dropdown-item" href="advance-payment.php"><i class="feather icon-arrow-up"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.advance_payment'), ENT_QUOTES, 'UTF-8') : 'Advance Payment'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('cheque-entry.php')): ?><li><a class="dropdown-item" href="cheque-entry.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.cheque_entry'), ENT_QUOTES, 'UTF-8') : 'Cheque Entry'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.material_management'), ENT_QUOTES, 'UTF-8') : 'Material Management'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('material-issue.php')): ?><li><a class="dropdown-item" href="material-issue.php"><i class="feather icon-share-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.material_issue'), ENT_QUOTES, 'UTF-8') : 'Material Issue'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('material-receive.php')): ?><li><a class="dropdown-item" href="material-receive.php"><i class="feather icon-inbox"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.material_receive'), ENT_QUOTES, 'UTF-8') : 'Material Receive'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('old-jewelry-scrap-invoice.php')): ?><li><a class="dropdown-item" href="old-jewelry-scrap-invoice.php"><i class="feather icon-scissors"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.old_scrap_invoice'), ENT_QUOTES, 'UTF-8') : 'Old Jewellery - Scrap Invoice'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('old-jewellery.php')): ?><li><a class="dropdown-item" href="old-jewellery.php"><i class="feather icon-trash-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.old_scrap'), ENT_QUOTES, 'UTF-8') : 'Old Jewellery - Scrap'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.accounting_journals'), ENT_QUOTES, 'UTF-8') : 'Accounting & Journals'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('stock-journal.php')): ?><li><a class="dropdown-item" href="stock-journal.php"><i class="feather icon-book"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.stock_journal'), ENT_QUOTES, 'UTF-8') : 'Stock Journal'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('credit-note.php')): ?><li><a class="dropdown-item" href="credit-note.php"><i class="feather icon-plus-circle"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.credit_note'), ENT_QUOTES, 'UTF-8') : 'Credit Note'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('debit-note.php')): ?><li><a class="dropdown-item" href="debit-note.php"><i class="feather icon-minus-circle"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.debit_note'), ENT_QUOTES, 'UTF-8') : 'Debit Note'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('contra-voucher.php')): ?><li><a class="dropdown-item" href="contra-voucher.php"><i class="feather icon-shuffle"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.contra_voucher'), ENT_QUOTES, 'UTF-8') : 'Contra Voucher'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('journal-voucher.php')): ?><li><a class="dropdown-item" href="journal-voucher.php"><i class="feather icon-edit"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.journal_voucher'), ENT_QUOTES, 'UTF-8') : 'Journal Voucher'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('expense-invoice.php')): ?><li><a class="dropdown-item" href="expense-invoice.php"><i class="feather icon-minus-square"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.expense_invoice'), ENT_QUOTES, 'UTF-8') : 'Expense Invoice'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('income-invoice.php')): ?><li><a class="dropdown-item" href="income-invoice.php"><i class="feather icon-plus-square"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.income_invoice'), ENT_QUOTES, 'UTF-8') : 'Income Invoice'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('repair-invoice.php')): ?><li><a class="dropdown-item" href="repair-invoice.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.repair_invoice'), ENT_QUOTES, 'UTF-8') : 'Repair Invoice'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('investment-fund.php')): ?><li><a class="dropdown-item" href="investment-fund.php"><i class="feather icon-briefcase"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.investment_fund'), ENT_QUOTES, 'UTF-8') : 'Investment / Layaways Fund'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('installment-report.php')): ?><li><a class="dropdown-item" href="installment-report.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('trans.installment_report'), ENT_QUOTES, 'UTF-8') : 'Installment Report'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('inventory')): ?>
                            <li class="nav-item mega-menu-item" data-mm-module="inventory">
                                    <a class="nav-link<?php echo $auragold_inventory_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-package"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.inventory'), ENT_QUOTES, 'UTF-8') : 'Stock Management'; ?></a>
                                    <div class="mega-menu inventory-mega-menu">
                                        <div class="mega-menu-content">
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.catalogue_stock'), ENT_QUOTES, 'UTF-8') : 'Catalogue & Stock'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('jewelry-catalogue.php')): ?><li><a class="dropdown-item" href="jewelry-catalogue.php"><i class="feather icon-book"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.jewellery_catalogue'), ENT_QUOTES, 'UTF-8') : 'Jewellery Catalogue'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('stock-verification.php')): ?><li data-mm-page="inventory.physical_stock"><a class="dropdown-item" href="stock-verification.php"><i class="feather icon-box"></i> Barcode Re-Print</a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.consignment_transfer'), ENT_QUOTES, 'UTF-8') : 'Consignment & Transfer'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('consignment-in.php')): ?><li><a class="dropdown-item" href="consignment-in.php"><i class="feather icon-arrow-down"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.memo_in'), ENT_QUOTES, 'UTF-8') : 'Memo / Consignment In'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('consignment-out.php')): ?><li><a class="dropdown-item" href="consignment-out.php"><i class="feather icon-arrow-up"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.memo_out'), ENT_QUOTES, 'UTF-8') : 'Memo / Consignment Out'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('consignment-out-report.php')): ?><li><a class="dropdown-item" href="consignment-out-report.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.memo_items'), ENT_QUOTES, 'UTF-8') : 'Memo / Consignment Items'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('stock-history.php')): ?><li><a class="dropdown-item" href="stock-history.php?ledger=1"><i class="feather icon-clock"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.stock_history'), ENT_QUOTES, 'UTF-8') : 'Stock History'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('stock-transfer.php')): ?><li><a class="dropdown-item" href="stock-transfer.php"><i class="feather icon-shuffle"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.stock_transfer'), ENT_QUOTES, 'UTF-8') : 'Stock Transfer'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('stock-transfer-history.php')): ?><li><a class="dropdown-item" href="stock-transfer-history.php"><i class="feather icon-list"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.stock_transfer_history'), ENT_QUOTES, 'UTF-8') : 'Stock Transfer History'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('stock-receive-history.php')): ?><li><a class="dropdown-item" href="stock-receive-history.php"><i class="feather icon-download"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.stock_receive_history'), ENT_QUOTES, 'UTF-8') : 'Stock Receive History'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.gold_plat_dia'), ENT_QUOTES, 'UTF-8') : 'Gold, Platinum & Diamond'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('gold-and-silver.php')): ?><li><a class="dropdown-item" href="gold-and-silver.php"><i class="feather icon-star"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.gold_silver'), ENT_QUOTES, 'UTF-8') : 'Gold & Silver'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('gold-silver-analysis.php')): ?><li><a class="dropdown-item" href="gold-silver-analysis.php"><i class="feather icon-bar-chart-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.gold_silver_analysis'), ENT_QUOTES, 'UTF-8') : 'Gold / Silver Analysis'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('platinum-analysis.php')): ?><li><a class="dropdown-item" href="platinum-analysis.php"><i class="feather icon-trending-up"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.platinum_analysis'), ENT_QUOTES, 'UTF-8') : 'Platinum Analysis'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('platinum-stock.php')): ?><li><a class="dropdown-item" href="platinum-stock.php"><i class="feather icon-disc"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.platinum_stock'), ENT_QUOTES, 'UTF-8') : 'Platinum Stock'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('diamond-and-stones.php')): ?><li><a class="dropdown-item" href="diamond-and-stones.php"><i class="feather icon-octagon"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.diamond_stone'), ENT_QUOTES, 'UTF-8') : 'Diamond & Stone Inventory'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('diamond-stock.php')): ?><li><a class="dropdown-item" href="diamond-stock.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.diamond_stock'), ENT_QUOTES, 'UTF-8') : 'Diamond Stock'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('diamond-stone-analysis.php')): ?><li><a class="dropdown-item" href="diamond-stone-analysis.php?tab=current-stock"><i class="feather icon-bar-chart-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.diamond_stone_analysis'), ENT_QUOTES, 'UTF-8') : 'Diamond & Stone Analysis'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="mega-menu-column">
                                                <h6 class="mega-menu-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mega.imitation_services'), ENT_QUOTES, 'UTF-8') : 'Imitation, Services & Reports'; ?></h6>
                                                <ul class="mega-menu-list">
                                                    <?php if (auragold_nav_show_php_href('imitation-and-watches.php')): ?><li><a class="dropdown-item" href="imitation-and-watches.php"><i class="feather icon-watch"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.imitation_watches_stock'), ENT_QUOTES, 'UTF-8') : 'Imitation / Watches'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('imitation-analysis.php')): ?><li><a class="dropdown-item" href="imitation-analysis.php"><i class="feather icon-pie-chart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.imitation_watches'), ENT_QUOTES, 'UTF-8') : 'Imitation / Watches Inventory'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'other_services')): ?><li data-mm-page="inventory.other_services"><a class="dropdown-item" href="#"><i class="feather icon-grid"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.other_or_services'), ENT_QUOTES, 'UTF-8') : 'Other or Services'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'other_services_analysis')): ?><li data-mm-page="inventory.other_services_analysis"><a class="dropdown-item" href="#"><i class="feather icon-activity"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.other_or_services_analysis'), ENT_QUOTES, 'UTF-8') : 'Other or Services Analysis'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('rfid-barcode-scan.php')): ?><li><a class="dropdown-item" href="rfid-barcode-scan.php"><i class="feather icon-radio"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.rfid_barcode'), ENT_QUOTES, 'UTF-8') : 'RFID / Barcode Scan'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'reset_rfid_tag')): ?><li data-mm-page="inventory.reset_rfid_tag"><a class="dropdown-item" href="#"><i class="feather icon-refresh-cw"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.reset_rfid'), ENT_QUOTES, 'UTF-8') : 'Reset RFID Tag'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_show_php_href('barcode-management.php')): ?><li><a class="dropdown-item" href="barcode-management.php"><i class="feather icon-hash"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.barcode'), ENT_QUOTES, 'UTF-8') : 'Barcode Management'; ?></a></li><?php elseif (auragold_nav_can_page_keys('inventory', 'barcode')): ?><li data-mm-page="inventory.barcode"><a class="dropdown-item" href="#"><i class="feather icon-hash"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.barcode'), ENT_QUOTES, 'UTF-8') : 'Barcode Management'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'stock_valuation')): ?><li data-mm-page="inventory.stock_valuation"><a class="dropdown-item" href="#"><i class="feather icon-pie-chart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.stock_valuation'), ENT_QUOTES, 'UTF-8') : 'Stock Valuation'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'stock_summary_report')): ?><li data-mm-page="inventory.stock_summary_report"><a class="dropdown-item" href="#"><i class="feather icon-bar-chart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.stock_summary_report'), ENT_QUOTES, 'UTF-8') : 'Stock Summary Report'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'carat_analysis_report')): ?><li><a class="dropdown-item" href="#"><i class="feather icon-bar-chart-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.carat_analysis_report'), ENT_QUOTES, 'UTF-8') : 'Carat Analysis Report'; ?></a></li><?php endif; ?>
                                                    <?php if (auragold_nav_can_page_keys('inventory', 'procurement_operations')): ?><li data-mm-page="inventory.procurement_operations"><a class="dropdown-item" href="#"><i class="feather icon-shopping-cart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.procurement_ops'), ENT_QUOTES, 'UTF-8') : 'Procurement Operations'; ?></a></li><?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('orders')): ?>
                            <li class="nav-item" data-mm-module="orders">
                                    <a class="nav-link<?php echo $auragold_orders_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-shopping-cart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.orders'), ENT_QUOTES, 'UTF-8') : 'Order Management'; ?></a>
                                    <ul class="dropdown-menu orders-submenu">
                                        <?php if (auragold_nav_show_php_href('sale-order.php')): ?><li><a class="dropdown-item" href="sale-order.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.sale_order'), ENT_QUOTES, 'UTF-8') : 'Sale Order'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('repair-order.php')): ?><li><a class="dropdown-item" href="repair-order.php"><i class="feather icon-clipboard"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.repair_order'), ENT_QUOTES, 'UTF-8') : 'Repair Order'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('sale-order-process.php')): ?><li><a class="dropdown-item" href="sale-order-process.php"><i class="feather icon-refresh-cw"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.sale_repair_process'), ENT_QUOTES, 'UTF-8') : 'Sale / Repair Order Process'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('jobwork-order.php')): ?><li><a class="dropdown-item" href="jobwork-order.php"><i class="feather icon-settings"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.jobwork_mfg'), ENT_QUOTES, 'UTF-8') : 'Jobwork Order Manufacturing'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('manufacturing-outsource.php')): ?><li><a class="dropdown-item" href="manufacturing-outsource.php"><i class="feather icon-globe"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.manufacturing_outsource'), ENT_QUOTES, 'UTF-8') : 'Manufacturing Outsource'; ?></a></li><?php endif; ?>
                                        <!-- <?php if (auragold_nav_show_php_href('job-work-order-manufacturing.php')): ?><li><a class="dropdown-item" href="job-work-order-manufacturing.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.jobwork_outsource'), ENT_QUOTES, 'UTF-8') : 'Jobwork Order Outsource'; ?></a></li><?php endif; ?> -->
                                        <?php if (auragold_nav_can_page_keys('orders', 'catalogue_quotation')): ?><li data-mm-page="orders.catalogue_quotation"><a class="dropdown-item" href="#"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.catalogue_quotation'), ENT_QUOTES, 'UTF-8') : 'Catalogue Quotation'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('orders', 'sales')): ?><li data-mm-page="orders.sales"><a class="dropdown-item" href="#"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ord.sales'), ENT_QUOTES, 'UTF-8') : 'Sales'; ?></a></li><?php endif; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (auragold_nav_module_has_visible_link('manufacturer')): ?>
                            <li class="nav-item" data-mm-module="manufacturer">
                                    <a class="nav-link<?php echo $auragold_manufacturer_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-briefcase"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.manufacturer'), ENT_QUOTES, 'UTF-8') : 'Production'; ?></a>
                                    <ul class="dropdown-menu manufacturer-submenu">
                                        <?php if (auragold_nav_show_php_href('department.php')): ?><li><a class="dropdown-item" href="department.php"><i class="feather icon-grid"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.department'), ENT_QUOTES, 'UTF-8') : 'Department'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('department-report.php')): ?><li><a class="dropdown-item" href="department-report.php"><i class="feather icon-users"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.department_report'), ENT_QUOTES, 'UTF-8') : 'Insource Department Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('outsource-department-report.php')): ?><li><a class="dropdown-item" href="outsource-department-report.php"><i class="feather icon-share-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.outsource_department_report'), ENT_QUOTES, 'UTF-8') : 'Outsource Department Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('jobwork-queue.php')): ?><li><a class="dropdown-item" href="jobwork-queue.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.jobwork_queue'), ENT_QUOTES, 'UTF-8') : 'Jobwork Queue'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('manufacturing-process.php')): ?><li><a class="dropdown-item" href="manufacturing-process.php"><i class="feather icon-refresh-cw"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.manufacturing_process'), ENT_QUOTES, 'UTF-8') : 'Manufacturing Process'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('manufacturer', 'jobwork_queue_report')): ?><li data-mm-page="manufacturer.jobwork_queue_report"><a class="dropdown-item" href="#"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.jobwork_queue_report'), ENT_QUOTES, 'UTF-8') : 'Jobwork Queue Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('loss-tracking.php')): ?><li><a class="dropdown-item" href="loss-tracking.php"><i class="feather icon-trending-down"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.loss_tracking'), ENT_QUOTES, 'UTF-8') : 'Loss Tracking'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('closing-report.php')): ?><li><a class="dropdown-item" href="closing-report.php"><i class="feather icon-x-circle"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.closing_report'), ENT_QUOTES, 'UTF-8') : 'Closing Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('manufacturer', 'worklog_report')): ?><li data-mm-page="manufacturer.worklog_report"><a class="dropdown-item" href="#"><i class="feather icon-clock"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.worklog_report'), ENT_QUOTES, 'UTF-8') : 'Worklog Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('manufacturer', 'jobcard_report')): ?><li data-mm-page="manufacturer.jobcard_report"><a class="dropdown-item" href="#"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.jobcard_report'), ENT_QUOTES, 'UTF-8') : 'Jobcard Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('job-card-print.php')): ?><li><a class="dropdown-item" href="job-card-print.php"><i class="feather icon-printer"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.jobcard_print'), ENT_QUOTES, 'UTF-8') : 'Jobcard Print'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('manufacturer', 'daily_manufacturing_summary')): ?><li data-mm-page="manufacturer.daily_manufacturing_summary"><a class="dropdown-item" href="#"><i class="feather icon-bar-chart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('mfg.daily_mfg_summary'), ENT_QUOTES, 'UTF-8') : 'Daily Manufacturing Summary'; ?></a></li><?php endif; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('financial_statement')): ?>
                            <li class="nav-item" data-mm-module="financial_statement">
                                    <a class="nav-link<?php echo $auragold_financial_statement_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-pie-chart"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.financial_statement'), ENT_QUOTES, 'UTF-8') : 'Financial Statement'; ?></a>
                                    <ul class="dropdown-menu financial-statement-submenu">
                                        <?php if (auragold_nav_show_php_href('trial-balance.php')): ?><li><a class="dropdown-item" href="trial-balance.php"><i class="feather icon-bar-chart-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.trial_balance'), ENT_QUOTES, 'UTF-8') : 'Trial Balance'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('balance-sheet.php')): ?><li><a class="dropdown-item" href="balance-sheet.php"><i class="feather icon-layers"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.balance_sheet'), ENT_QUOTES, 'UTF-8') : 'Balance Sheet'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('profit-loss.php')): ?><li><a class="dropdown-item" href="profit-loss.php"><i class="feather icon-trending-up"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.profit_loss'), ENT_QUOTES, 'UTF-8') : 'Profit Loss'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('cash-flow.php')): ?><li><a class="dropdown-item" href="cash-flow.php"><i class="feather icon-repeat"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.cash_flow'), ENT_QUOTES, 'UTF-8') : 'Cash Flow'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('fund-flow.php')): ?><li><a class="dropdown-item" href="fund-flow.php"><i class="feather icon-refresh-ccw"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.fund_flow'), ENT_QUOTES, 'UTF-8') : 'Fund Flow'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('chart-of-account.php')): ?><li><a class="dropdown-item" href="chart-of-account.php"><i class="feather icon-grid"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.chart_of_account'), ENT_QUOTES, 'UTF-8') : 'Chart Of Account'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('tax-return.php')): ?><li><a class="dropdown-item" href="tax-return.php"><i class="feather icon-percent"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.tax_return'), ENT_QUOTES, 'UTF-8') : 'Tax Return'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('sale-analysis.php')): ?><li><a class="dropdown-item" href="sale-analysis.php"><i class="feather icon-search"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.sale_analysis'), ENT_QUOTES, 'UTF-8') : 'Sale Reports'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('purchase-financial-analysis.php')): ?><li><a class="dropdown-item" href="purchase-financial-analysis.php"><i class="feather icon-search"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.purchase_analysis'), ENT_QUOTES, 'UTF-8') : 'Purchase Reports'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('trial-balance-detailed-report.php')): ?><li><a class="dropdown-item" href="trial-balance-detailed-report.php"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('fs.trial_balance_detailed'), ENT_QUOTES, 'UTF-8') : 'Trial Balance Detailed Report'; ?></a></li><?php endif; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('report')): ?>
                            <li class="nav-item" data-mm-module="report">
                                    <a class="nav-link<?php echo $auragold_report_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-file-text"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.report'), ENT_QUOTES, 'UTF-8') : 'Report Analysis'; ?></a>
                                    <ul class="dropdown-menu report-submenu">
                                        <?php if (auragold_nav_show_php_href('transaction-report.php')): ?><li><a class="dropdown-item" href="transaction-report.php"><i class="feather icon-paperclip"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.transactions'), ENT_QUOTES, 'UTF-8') : 'Transactions Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('accountledger-report.php')): ?><li><a class="dropdown-item" href="accountledger-report.php"><i class="feather icon-bar-chart-2"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.account_ledger'), ENT_QUOTES, 'UTF-8') : 'Account Ledger Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('day-report.php') && auragold_nav_can_page_keys('report', 'day_report')): ?><li><a class="dropdown-item" href="day-report.php"><i class="feather icon-search"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.day_report'), ENT_QUOTES, 'UTF-8') : 'Day Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('ageing-report.php') && auragold_nav_can_page_keys('report', 'ageing_report')): ?><li><a class="dropdown-item" href="ageing-report.php"><i class="feather icon-repeat"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.ageing'), ENT_QUOTES, 'UTF-8') : 'Ageing Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('kyc-report.php') && auragold_nav_can_page_keys('report', 'customer_kyc_report')): ?><li><a class="dropdown-item" href="kyc-report.php"><i class="feather icon-user-check"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.customer_kyc'), ENT_QUOTES, 'UTF-8') : 'Customer / KYC Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('ledger-balance-report.php') && auragold_nav_can_page_keys('report', 'ledger_balance_report')): ?><li><a class="dropdown-item" href="ledger-balance-report.php"><i class="feather icon-book"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.ledger_balance'), ENT_QUOTES, 'UTF-8') : 'Ledger Balance Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('report', 'fixing_position')): ?><li data-mm-page="report.fixing_position"><a class="dropdown-item" href="#"><i class="feather icon-alert-triangle"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.fixing_position'), ENT_QUOTES, 'UTF-8') : 'Fixing Position'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_can_page_keys('report', 'karatwise_profit_loss')): ?><li data-mm-page="report.karatwise_profit_loss"><a class="dropdown-item" href="#"><i class="feather icon-aperture"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.karatwise_pnl'), ENT_QUOTES, 'UTF-8') : 'Karatwise Profit & Loss'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('reward-point-report.php') && auragold_nav_can_page_keys('report', 'reward_point_report')): ?><li><a class="dropdown-item" href="reward-point-report.php"><i class="feather icon-percent"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.reward_point'), ENT_QUOTES, 'UTF-8') : 'Reward Point Report'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('barcode-scan-report.php')): ?><li><a class="dropdown-item" href="barcode-scan-report.php"><i class="feather icon-radio"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('rep.barcode_scan'), ENT_QUOTES, 'UTF-8') : 'Barcode Scan Report'; ?></a></li><?php endif; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('gst_report')): ?>
                            <li class="nav-item" data-mm-module="gst_report">
                                    <a class="nav-link<?php echo $auragold_gst_report_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-percent"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.gst_report'), ENT_QUOTES, 'UTF-8') : 'GST Reports'; ?></a>
                                    <ul class="dropdown-menu gst-report-submenu">
                                        <?php
                                        $auragold_gst_catalog = auragold_gst_reports_catalog();
                                        $auragold_gst_current_type = isset($_GET['type']) ? (string) $_GET['type'] : '';
                                        foreach ($auragold_gst_catalog as $gstItem):
                                            if (!auragold_nav_can_page_keys('gst_report', $gstItem['key'])) {
                                                continue;
                                            }
                                            $gstHref = auragold_gst_report_href($gstItem['key']);
                                            $gstActive = ($auragold_nav_basename === 'gst-report.php' && $auragold_gst_current_type === $gstItem['key']);
                                        ?>
                                        <li><a class="dropdown-item<?php echo $gstActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($gstHref, ENT_QUOTES, 'UTF-8'); ?>"><i class="feather <?php echo htmlspecialchars($gstItem['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($gstItem['label'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('employee_management')): ?>
                             <li class="nav-item" data-mm-module="employee_management">
                                    <a class="nav-link<?php echo $auragold_employee_management_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-users"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.employee_management'), ENT_QUOTES, 'UTF-8') : 'Employee Management'; ?></a>
                                    <ul class="dropdown-menu employee-management-submenu">
                                        <?php foreach (auragold_employee_management_menu_items() as $emNavItem):
                                            $emNavFile = (string) ($emNavItem['file'] ?? '');
                                            $emNavHref = (string) ($emNavItem['href'] ?? $emNavFile);
                                            $emNavKey = (string) ($emNavItem['key'] ?? '');
                                            $emIsPlaceholder = ($emNavHref === '' || $emNavHref === '#');
                                            if ($emIsPlaceholder) {
                                                if (!auragold_nav_can_page_keys('employee_management', $emNavKey)
                                                    && !auragold_nav_can_page_keys('utilities', $emNavKey)
                                                    && !auragold_employee_management_can_view_page($emNavKey)) {
                                                    continue;
                                                }
                                            } elseif ($emNavFile === ''
                                                || (!auragold_nav_show_php_href($emNavFile)
                                                    && !auragold_employee_management_can_view_page($emNavKey))) {
                                                continue;
                                            }
                                            $emNavActive = ($emNavFile !== '' && $auragold_nav_basename === $emNavFile);
                                            $emNavIcon = htmlspecialchars((string) ($emNavItem['icon'] ?? 'icon-circle'), ENT_QUOTES, 'UTF-8');
                                            $emNavLabel = function_exists('auragold_t')
                                                ? htmlspecialchars(auragold_t('em.' . $emNavKey), ENT_QUOTES, 'UTF-8')
                                                : htmlspecialchars((string) ($emNavItem['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            if ($emNavLabel === 'em.' . $emNavKey) {
                                                $emNavLabel = htmlspecialchars((string) ($emNavItem['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            }
                                            $emMmAttr = $emIsPlaceholder
                                                ? ' data-mm-page="employee_management.' . htmlspecialchars($emNavKey, ENT_QUOTES, 'UTF-8') . '"'
                                                : '';
                                        ?>
                                        <li<?php echo $emMmAttr; ?>><a class="dropdown-item<?php echo $emNavActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($emIsPlaceholder ? '#' : $emNavHref, ENT_QUOTES, 'UTF-8'); ?>"><i class="feather <?php echo $emNavIcon; ?>"></i> <?php echo $emNavLabel; ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                            </li> 
                            <?php endif; ?>
                            <?php if (auragold_nav_module_has_visible_link('settings') || auragold_nav_administration_dropdown_visible($auragold_nav_admin_role)): ?>
                            <li class="nav-item" data-mm-module="settings">
                                    <a class="nav-link<?php echo $auragold_settings_nav_active ? ' active' : ''; ?>" href="#"><i class="feather icon-settings"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('nav.settings'), ENT_QUOTES, 'UTF-8') : 'Settings'; ?></a>
                                    <ul class="dropdown-menu settings-submenu">
                                        <?php if (auragold_nav_show_php_href('set-software.php')): ?><li><a class="dropdown-item" href="set-software.php"><i class="feather icon-monitor"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('set.set_software'), ENT_QUOTES, 'UTF-8') : 'Set Software'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('voucher-type.php')): ?><li><a class="dropdown-item" href="voucher-type.php"><i class="feather icon-percent"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('set.voucher_type'), ENT_QUOTES, 'UTF-8') : 'Voucher Type'; ?></a></li><?php endif; ?>
                                        <!-- <?php if (auragold_nav_show_php_href('invoice-print-settings.php')): ?><li><a class="dropdown-item" href="invoice-print-settings.php"><i class="feather icon-printer"></i> Invoice print settings</a></li><?php endif; ?> -->
                                        <?php
                                        /* Language & Masters live in Set Software sidebar — omit from top Settings menu */
                                        $auragold_settings_config_links = auragold_nav_show_php_href('set-software.php')
                                            || auragold_nav_show_php_href('voucher-type.php');
                                        if (auragold_nav_administration_dropdown_visible($auragold_nav_admin_role)):
                                        ?>
                                        <?php if ($auragold_settings_config_links): ?><li class="dropdown-divider" role="separator"></li><?php endif; ?>
                                        <?php if ($auragold_nav_admin_role && auragold_nav_show_php_href('user-management.php')): ?>
                                        <li><a class="dropdown-item" href="user-management.php"><i class="feather icon-users"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('adm.user_management'), ENT_QUOTES, 'UTF-8') : 'User Management'; ?></a></li>
                                        <?php endif; ?>
                                        <!-- <?php if ($auragold_nav_admin_role && auragold_nav_show_php_href('role-management.php')): ?>
                                        <li><a class="dropdown-item" href="role-management.php"><i class="feather icon-shield"></i> Role Management</a></li>
                                        <?php endif; ?>
                                        <?php if ($auragold_nav_admin_role && auragold_nav_show_php_href('permission-management.php')): ?>
                                        <li><a class="dropdown-item" href="permission-management.php"><i class="feather icon-lock"></i> Permissions</a></li>
                                        <?php endif; ?>
                                        <?php if ($auragold_nav_admin_role && auragold_nav_show_php_href('whitelist-management.php')): ?>
                                        <li><a class="dropdown-item" href="whitelist-management.php"><i class="feather icon-check-circle"></i> Whitelist</a></li>
                                        <?php endif; ?>
                                        <?php if ($auragold_nav_admin_role && auragold_nav_show_php_href('blocklist-management.php')): ?>
                                        <li><a class="dropdown-item" href="blocklist-management.php"><i class="feather icon-slash"></i> Blocklist</a></li>
                                        <?php endif; ?> -->
                                        <?php if (auragold_nav_can_page_keys('administration', 'activity_log')): ?><li data-mm-page="administration.activity_log"><a class="dropdown-item" href="activity-log.php"><i class="feather icon-search"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('adm.activity_log'), ENT_QUOTES, 'UTF-8') : 'Activity Log'; ?></a></li><?php endif; ?>
                                        <?php if (auragold_nav_show_php_href('crm.php')): ?><li><a class="dropdown-item" href="<?php echo htmlspecialchars($auragold_crm_external_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i class="feather icon-sidebar"></i> <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('adm.crm'), ENT_QUOTES, 'UTF-8') : 'CRM'; ?></a></li><?php endif; ?>
                                        <?php endif; ?>
                                    </ul>
                            </li>
                            <?php endif; ?>
                            <?php endif; ?>
                            <!-- <li class="nav-item">
                                    <a class="nav-link" href="#"><i class="feather icon-shield"></i> Administration</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#"><i class="feather icon-users"></i> User Management</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="feather icon-shield"></i> Roles & Permissions</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="feather icon-database"></i> Database</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="feather icon-archive"></i> Backup & Restore</a></li>
                                        <li class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#"><i class="feather icon-info"></i> System Info</a></li>
                                    </ul>
                            </li> -->
                        </ul>
                            
                        </div>
                    </nav>
                    </div>

<?php
global $conn;
$auragold_mobile_menu_js = [
    'basenameMap'      => [],
    'disabledModules'  => [],
    'disabledPages'    => [],
];
if (isset($conn) && $conn instanceof mysqli) {
    $mmMap = auragold_nav_basename_permission_map();
    foreach ($mmMap as $bn => $pair) {
        $auragold_mobile_menu_js['basenameMap'][$bn] = $pair[0] . '.' . $pair[1];
    }
    $mmFilter = auragold_mobile_menu_get_js_filter_config($conn);
    $auragold_mobile_menu_js['disabledModules'] = $mmFilter['disabledModules'];
    $auragold_mobile_menu_js['disabledPages']   = $mmFilter['disabledPages'];
}
?>
<script>window.auragoldMobileMenuFilter = <?php echo json_encode($auragold_mobile_menu_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
<script src="assets/js/auragold-mobile-menu-filter.js"></script>
<script src="assets/js/auragold-menu-search.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/auragold-menu-search.js') ?: time(); ?>"></script>

<script>
// Mobile: hamburger (menu) opens the main nav in a left drawer; submenus use tap to expand
(function() {
    var mq = window.matchMedia('(max-width: 991.98px)');
    var openBtn = document.getElementById('auragoldMobileMenuBtn');
    var closeBtn = document.getElementById('auragoldMobileNavClose');
    var backdrop = document.getElementById('auragoldNavBackdrop');
    var topNav = document.getElementById('auragoldTopNav');
    if (!openBtn || !topNav) {
        return;
    }
    function isMobile() {
        return mq.matches;
    }
    function clearExpanded() {
        var items = topNav.querySelectorAll('.nav-item.is-expanded');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.remove('is-expanded');
        }
    }
    function openDrawer() {
        if (!isMobile()) {
            return;
        }
        document.body.classList.add('auragold-nav-drawer-open');
        if (backdrop) {
            backdrop.classList.add('is-visible');
            backdrop.setAttribute('aria-hidden', 'false');
        }
        openBtn.setAttribute('aria-expanded', 'true');
        if (typeof window.auragoldApplyMobileMenuFilter === 'function') {
            window.auragoldApplyMobileMenuFilter();
        }
    }
    function closeDrawer() {
        document.body.classList.remove('auragold-nav-drawer-open');
        if (backdrop) {
            backdrop.classList.remove('is-visible');
            backdrop.setAttribute('aria-hidden', 'true');
        }
        openBtn.setAttribute('aria-expanded', 'false');
        clearExpanded();
    }
    function onResize() {
        if (!isMobile()) {
            closeDrawer();
        }
    }
    openBtn.addEventListener('click', function() {
        if (!isMobile()) {
            return;
        }
        if (document.body.classList.contains('auragold-nav-drawer-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', closeDrawer);
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('auragold-nav-drawer-open')) {
            closeDrawer();
        }
    });
    window.addEventListener('resize', onResize, { passive: true });
    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', onResize);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(onResize);
    }
    topNav.addEventListener('click', function(e) {
        if (!isMobile()) {
            return;
        }
        var subLink = e.target.closest('a.nav-link[href="#"]');
        if (subLink && topNav.contains(subLink) && subLink.getAttribute('href') === '#') {
            var li = subLink.closest('li.nav-item');
            if (!li || !topNav.contains(li)) {
                return;
            }
            if (!li.querySelector('.dropdown-menu, .mega-menu')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var wasOpen = li.classList.contains('is-expanded');
            var parent = li.parentNode;
            if (parent) {
                var ch = parent.firstElementChild;
                while (ch) {
                    if (ch.classList && ch.classList.contains('nav-item')) {
                        ch.classList.remove('is-expanded');
                    }
                    ch = ch.nextElementSibling;
                }
            }
            if (!wasOpen) {
                li.classList.add('is-expanded');
                requestAnimationFrame(function() {
                    li.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                });
            }
        }
    });
    topNav.addEventListener('click', function(e) {
        if (!isMobile() || !document.body.classList.contains('auragold-nav-drawer-open')) {
            return;
        }
        var a = e.target.closest('a[href]');
        if (!a || !topNav.contains(a)) {
            return;
        }
        var h = a.getAttribute('href');
        if (!h || h === '#' || h === '#!') {
            return;
        }
        closeDrawer();
    });
})();

// Desktop: collapse main nav — vertical sidebar only (hidden in horizontal mode)
(function() {
    var mq = window.matchMedia('(min-width: 992px)');
    var wrap = document.getElementById('auragoldTopNavWrap');
    var tab = document.getElementById('auragoldTopNavCollapseTab');
    var nav = document.getElementById('auragoldTopNav');
    if (!wrap || !tab || !nav) {
        return;
    }
    var titleShow = tab.getAttribute('data-auragold-title-show') || 'Show menu';
    var titleHide = tab.getAttribute('data-auragold-title-hide') || 'Hide menu';
    var storageKey = 'auragoldTopNavCollapsed';

    function isVerticalDesktop() {
        return mq.matches && document.body.classList.contains('auragold-menu-vertical');
    }

    function apply(collapsed) {
        if (!isVerticalDesktop()) {
            collapsed = false;
        }
        document.body.classList.toggle('auragold-top-nav-collapsed', collapsed);
        tab.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        tab.title = collapsed ? titleShow : titleHide;
        nav.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
        if (isVerticalDesktop()) {
            try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (e) {}
        }
    }

    var stored = null;
    try { stored = localStorage.getItem(storageKey); } catch (e) {}
    if (stored === '1' && isVerticalDesktop()) {
        apply(true);
    } else {
        apply(false);
    }

    tab.addEventListener('click', function() {
        if (!isVerticalDesktop()) {
            return;
        }
        apply(!document.body.classList.contains('auragold-top-nav-collapsed'));
    });

    function onBreakpointChange() {
        if (!isVerticalDesktop()) {
            apply(false);
        } else {
            var s = null;
            try { s = localStorage.getItem(storageKey); } catch (e) {}
            apply(s === '1');
        }
    }
    window.addEventListener('resize', onBreakpointChange, { passive: true });
    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', onBreakpointChange);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(onBreakpointChange);
    }
})();

// Vertical desktop: accordion submenus under each item (Set Software Region style)
(function() {
    var mqDesktop = window.matchMedia('(min-width: 992px)');
    var topNav = document.getElementById('auragoldTopNav');
    if (!topNav) {
        return;
    }

    function isVerticalDesktop() {
        return mqDesktop.matches
            && document.body.classList.contains('auragold-menu-vertical')
            && !document.body.classList.contains('auragold-top-nav-collapsed');
    }

    function clearExpanded(except) {
        var items = topNav.querySelectorAll('.nav-item.is-expanded');
        for (var i = 0; i < items.length; i++) {
            if (items[i] !== except) {
                items[i].classList.remove('is-expanded');
            }
        }
    }

    topNav.addEventListener('click', function(e) {
        if (!isVerticalDesktop()) {
            return;
        }
        var subLink = e.target.closest('a.nav-link[href="#"]');
        if (!subLink || !topNav.contains(subLink)) {
            return;
        }
        var li = subLink.closest('li.nav-item');
        if (!li || !topNav.contains(li)) {
            return;
        }
        if (!li.querySelector('.dropdown-menu, .mega-menu')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var wasOpen = li.classList.contains('is-expanded');
        clearExpanded(null);
        if (!wasOpen) {
            li.classList.add('is-expanded');
            requestAnimationFrame(function() {
                li.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            });
        }
    });

    function onModeChange() {
        if (!isVerticalDesktop()) {
            clearExpanded(null);
        }
    }

    if (typeof mqDesktop.addEventListener === 'function') {
        mqDesktop.addEventListener('change', onModeChange);
    } else if (typeof mqDesktop.addListener === 'function') {
        mqDesktop.addListener(onModeChange);
    }
})();

// Fullscreen Toggle Functionality
(function() {
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    if (!fullscreenBtn) return;
    
    const fullscreenIcon = fullscreenBtn.querySelector('i');
    const FULLSCREEN_KEY = 'auragold_fullscreen_enabled';

    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || 
                  document.mozFullScreenElement || document.msFullscreenElement);
    }

    function enterFullscreen() {
        const elem = document.documentElement;
        const request = elem.requestFullscreen || elem.webkitRequestFullscreen || 
                       elem.mozRequestFullScreen || elem.msRequestFullscreen;
        if (request) {
            request.call(elem).catch(function() {});
        }
    }

    function exitFullscreen() {
        const exit = document.exitFullscreen || document.webkitExitFullscreen || 
                    document.mozCancelFullScreen || document.msExitFullscreen;
        if (exit && isFullscreen()) {
            exit.call(document);
        }
    }

    function updateIcon() {
        if (isFullscreen()) {
            fullscreenIcon.className = 'feather icon-minimize-2';
            fullscreenBtn.title = 'Exit Fullscreen';
        } else {
            fullscreenIcon.className = 'feather icon-maximize-2';
            fullscreenBtn.title = 'Fullscreen (Use F11 for persistent fullscreen)';
        }
    }

    function onFullscreenChange() {
        updateIcon();
        localStorage.setItem(FULLSCREEN_KEY, isFullscreen() ? 'true' : 'false');
    }

    // Toggle button click
    fullscreenBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!isFullscreen()) {
            enterFullscreen();
        } else {
            exitFullscreen();
        }
    });

    // Listen for fullscreen changes
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    document.addEventListener('mozfullscreenchange', onFullscreenChange);
    document.addEventListener('MSFullscreenChange', onFullscreenChange);

    // Auto-restore fullscreen on first interaction if preference is set
    if (localStorage.getItem(FULLSCREEN_KEY) === 'true' && !isFullscreen()) {
        let restored = false;
        
        function restoreOnInteraction() {
            if (!restored && localStorage.getItem(FULLSCREEN_KEY) === 'true' && !isFullscreen()) {
                restored = true;
                enterFullscreen();
            }
        }
        
        // Use capture phase and multiple event types for immediate response
        ['mousedown', 'keydown', 'touchstart', 'click'].forEach(function(evt) {
            document.addEventListener(evt, restoreOnInteraction, { capture: true, once: true });
        });
    }
    
    updateIcon();
})();

// User dropdown: show menu when clicking user avatar; close when clicking outside
(function() {
    var userDropdown = document.getElementById('userDropdown');
    var userAvatar = document.getElementById('userAvatarToggle');
    if (!userDropdown || !userAvatar) return;

    function toggleUserDropdown(e) {
        if (e) e.stopPropagation();
        userDropdown.classList.toggle('show');
    }

    function closeUserDropdown() {
        userDropdown.classList.remove('show');
    }

    userAvatar.addEventListener('click', toggleUserDropdown);

    document.addEventListener('click', function(e) {
        if (userDropdown.classList.contains('show') && !userDropdown.contains(e.target)) {
            closeUserDropdown();
        }
    });
})();

// Header notifications bell
(function() {
    var wrap = document.getElementById('auragoldNotifWrap');
    var toggle = document.getElementById('auragoldNotifToggle');
    var menu = document.getElementById('auragoldNotifMenu');
    var listEl = document.getElementById('auragoldNotifList');
    var badge = document.getElementById('auragoldNotifBadge');
    var btnMarkAll = document.getElementById('auragoldNotifMarkAll');
    if (!wrap || !toggle || !menu || !listEl || !badge || !btnMarkAll) {
        return;
    }

    var open = false;
    var feedUrl = 'ajax/notifications-feed.php';

    function renderNotifItem(it) {
        var card = document.createElement('div');
        card.className = 'auragold-notif-card' + (it.unread ? ' unread' : '');
        var head = document.createElement('div');
        head.className = 'auragold-notif-card-head';
        var h = document.createElement('h4');
        h.className = 'auragold-notif-title';
        h.textContent = it.title || 'Notification';
        var mark = document.createElement('button');
        mark.type = 'button';
        mark.className = 'auragold-notif-mark-one';
        mark.textContent = 'Mark as Read';
        mark.addEventListener('click', function(ev) {
            ev.stopPropagation();
            var fd = new FormData();
            fd.append('action', 'mark_one');
            fd.append('id', String(it.id));
            fetch(feedUrl + '?action=mark_one', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function() {
                    card.classList.remove('unread');
                    refreshBadge();
                })
                .catch(function() {});
        });
        head.appendChild(h);
        head.appendChild(mark);
        var body = document.createElement('p');
        body.className = 'auragold-notif-body';
        body.textContent = it.message || '';
        var time = document.createElement('div');
        time.className = 'auragold-notif-time';
        time.textContent = it.time_ago || '';
        card.appendChild(head);
        card.appendChild(body);
        card.appendChild(time);
        return card;
    }

    function refreshBadge() {
        fetch(feedUrl + '?action=count', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d || typeof d.unread !== 'number') return;
                var u = d.unread;
                if (u <= 0) {
                    badge.style.display = 'none';
                    badge.textContent = '';
                    badge.classList.add('auragold-notif-badge--empty');
                } else if (u > 9) {
                    badge.style.display = '';
                    badge.classList.remove('auragold-notif-badge--empty');
                    badge.textContent = '9+';
                } else {
                    badge.style.display = '';
                    badge.classList.remove('auragold-notif-badge--empty');
                    badge.textContent = String(u);
                }
            })
            .catch(function() {});
    }

    function loadPanel() {
        listEl.innerHTML = '<div class="auragold-notif-empty">Loading…</div>';
        fetch(feedUrl + '?action=list', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                listEl.innerHTML = '';
                if (!d || !d.items || d.items.length === 0) {
                    listEl.innerHTML = '<div class="auragold-notif-empty">No notifications</div>';
                    return;
                }
                d.items.forEach(function(it) {
                    listEl.appendChild(renderNotifItem(it));
                });
            })
            .catch(function() {
                listEl.innerHTML = '<div class="auragold-notif-empty">Could not load notifications</div>';
            });
    }

    function closeNotif() {
        open = false;
        toggle.setAttribute('aria-expanded', 'false');
        menu.classList.remove('show');
    }

    function togglePanel(e) {
        if (e) e.preventDefault();
        e.stopPropagation();
        open = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            menu.classList.add('show');
            loadPanel();
        } else {
            menu.classList.remove('show');
        }
    }

    toggle.addEventListener('click', togglePanel);

    btnMarkAll.addEventListener('click', function(ev) {
        ev.preventDefault();
        var fd = new FormData();
        fd.append('action', 'mark_all');
        fetch(feedUrl + '?action=mark_all', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function() {
                loadPanel();
                refreshBadge();
            })
            .catch(function() {});
    });

    document.addEventListener('click', function(e) {
        if (open && !wrap.contains(e.target)) {
            closeNotif();
        }
    });

    setTimeout(refreshBadge, 1200);
    setInterval(refreshBadge, 120000);
})();
// Log out after no keyboard/mouse/scroll activity (aligned with server session idle timeout)
(function() {
    var maxIdleMs = <?php echo (int) $auragold_client_idle_ms; ?>;
    if (!maxIdleMs) {
        return;
    }
    var tid = null;
    function go() {
        window.location.href = 'logout.php?timeout=1';
    }
    function reset() {
        if (tid) {
            clearTimeout(tid);
        }
        tid = setTimeout(go, maxIdleMs);
    }
    ['mousedown', 'keydown', 'scroll', 'touchstart', 'click', 'wheel'].forEach(function(evt) {
        document.addEventListener(evt, reset, { passive: true, capture: true });
    });
    reset();
})();

// One-shot alert when operational DB connection / name is missing (header chip shows DB — or DB ?)
(function() {
    var msg = <?php echo json_encode($auragold_header_conn_alert, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (typeof msg === 'string' && msg.length) {
        setTimeout(function() { window.alert(msg); }, 0);
    }
})();

</script>

<?php if (!empty($_SESSION['Admin'])): ?>
<div id="auragoldProfileBranchPwdOverlay" class="auragold-profile-branch-pwd-overlay" role="dialog" aria-modal="true" aria-labelledby="auragoldProfileBranchPwdTitle" hidden>
    <div class="auragold-profile-branch-pwd-dialog">
        <div class="auragold-profile-branch-pwd-dialog__head">
            <h2 id="auragoldProfileBranchPwdTitle" class="auragold-profile-branch-pwd-dialog__title">Enter branch password</h2>
            <button type="button" class="auragold-profile-branch-pwd-dialog__close" id="auragoldProfileBranchPwdClose" aria-label="Close">&times;</button>
        </div>
        <div class="auragold-profile-branch-pwd-dialog__body">
            <p class="auragold-profile-branch-pwd-dialog__label" id="auragoldProfileBranchPwdBranchLabel">Enter the password for this branch.</p>
            <label class="auragold-profile-branch-pwd-label" for="auragoldProfileBranchPwdInput">Password</label>
            <input type="password" id="auragoldProfileBranchPwdInput" class="auragold-profile-branch-pwd-input" autocomplete="current-password">
            <div id="auragoldProfileBranchPwdError" class="auragold-profile-branch-pwd-error" role="alert"></div>
        </div>
        <div class="auragold-profile-branch-pwd-dialog__foot">
            <button type="button" class="auragold-profile-branch-pwd-btn auragold-profile-branch-pwd-btn--ghost" id="auragoldProfileBranchPwdCancel">Cancel</button>
            <button type="button" class="auragold-profile-branch-pwd-btn auragold-profile-branch-pwd-btn--primary" id="auragoldProfileBranchPwdSubmit">Continue</button>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('auragoldProfileBranchPwdOverlay');
    var input = document.getElementById('auragoldProfileBranchPwdInput');
    var errEl = document.getElementById('auragoldProfileBranchPwdError');
    var labelEl = document.getElementById('auragoldProfileBranchPwdBranchLabel');
    var submitBtn = document.getElementById('auragoldProfileBranchPwdSubmit');
    var cancelBtn = document.getElementById('auragoldProfileBranchPwdCancel');
    var closeBtn = document.getElementById('auragoldProfileBranchPwdClose');
    var branchId = 0;
    var submitDefaultHtml = submitBtn ? submitBtn.innerHTML : 'Continue';

    function showErr(msg) {
        if (!errEl) return;
        if (!msg) {
            errEl.textContent = '';
            errEl.classList.remove('is-visible');
            return;
        }
        errEl.textContent = msg;
        errEl.classList.add('is-visible');
    }

    function setLoading(on) {
        if (!submitBtn) return;
        if (on) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="auragold-profile-branch-pwd-spinner" aria-hidden="true"></span> Please wait…';
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitDefaultHtml;
        }
        if (cancelBtn) cancelBtn.disabled = !!on;
        if (closeBtn) closeBtn.disabled = !!on;
        if (input) input.disabled = !!on;
    }

    function openModal(id, name) {
        if (!overlay) return;
        branchId = parseInt(id, 10) || 0;
        if (labelEl) {
            labelEl.textContent = name ? ('Branch: ' + name) : 'Enter the password for this branch.';
        }
        if (input) {
            input.value = '';
            input.disabled = false;
        }
        setLoading(false);
        showErr('');
        overlay.hidden = false;
        overlay.classList.add('is-open');
        setTimeout(function () {
            if (input) input.focus();
        }, 40);
    }

    function closeModal() {
        if (!overlay) return;
        overlay.classList.remove('is-open');
        overlay.hidden = true;
        branchId = 0;
        setLoading(false);
        showErr('');
    }

    function submitPassword() {
        if (!branchId || !input) return;
        var pw = String(input.value || '');
        if (!pw.trim()) {
            showErr('Enter the branch password');
            return;
        }
        setLoading(true);
        showErr('');
        var body = 'id=' + encodeURIComponent(String(branchId)) + '&password=' + encodeURIComponent(pw);
        fetch('ajax/verify-branch-panel-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.status === 'ok' && d.redirect) {
                    window.location.href = d.redirect;
                    return;
                }
                setLoading(false);
                showErr((d && d.message) ? d.message : 'Incorrect password');
            })
            .catch(function () {
                setLoading(false);
                showErr('Network error');
            });
    }

    document.querySelectorAll('.user-dropdown-branch-item').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (btn.classList.contains('is-current')) {
                return;
            }
            var dd = document.getElementById('userDropdown');
            if (dd) {
                dd.classList.remove('show');
            }
            var id = btn.getAttribute('data-branch-id');
            var nm = btn.getAttribute('data-branch-name') || '';
            openModal(id, nm);
        });
    });

    if (submitBtn) submitBtn.addEventListener('click', submitPassword);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitPassword();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeModal();
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
    }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/toast_flash.php'; ?>
