<?php
session_start();
require_once 'config.php';

$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$customer_type_id = isset($_GET['customer_type_id']) ? (int) $_GET['customer_type_id'] : 0;
$country_id = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$nationality_id = isset($_GET['nationality_id']) ? (int) $_GET['nationality_id'] : 0;
$has_aml = isset($_GET['has_aml']) ? esc($_GET['has_aml']) : '';

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 25;
if ($per_page < 1) {
    $per_page = 25;
}

$customer_types = getList('SELECT * FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC');
$countries = getList('SELECT * FROM tbl_countries WHERE status = 1 ORDER BY name ASC');
$nationalities = getList('SELECT * FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC');
if (!is_array($customer_types)) {
    $customer_types = [];
}
if (!is_array($countries)) {
    $countries = [];
}
if (!is_array($nationalities)) {
    $nationalities = [];
}

$column_labels = [
    'name'             => 'Name',
    'account_no'       => 'Account No',
    'first_name'       => 'First Name',
    'last_name'        => 'Last Name',
    'contact'          => 'Contact',
    'email_id'         => 'Email ID',
    'identity_no'      => 'Identity No.',
    'national_id'      => 'National ID',
    'trade_no'         => 'Trade No.',
    'special_day'      => 'Special Day',
    'dob'              => 'DOB',
    'registration'     => 'Registration',
    'customer_type'    => 'Customer Type',
    'country'          => 'Country',
    'nationality'      => 'Nationality',
    'billing_address'  => 'Billing Address',
    'state'            => 'State',
    'nominee'          => 'Nominee',
    'aml'              => 'Aml',
    'info'             => 'info',
    'action'           => 'action',
];

$default_order = array_keys($column_labels);
$default_visibility = [];
foreach ($default_order as $k) {
    $default_visibility[$k] = true;
}

$prefs = isset($_SESSION['kyc_report_prefs']) && is_array($_SESSION['kyc_report_prefs']) ? $_SESSION['kyc_report_prefs'] : [];
$visibility = array_merge($default_visibility, isset($prefs['visibility']) && is_array($prefs['visibility']) ? $prefs['visibility'] : []);
foreach ($default_order as $k) {
    if (!array_key_exists($k, $visibility)) {
        $visibility[$k] = true;
    }
}

$order = isset($prefs['order']) && is_array($prefs['order']) ? $prefs['order'] : $default_order;
$known_keys = array_flip($default_order);
$order = array_values(array_filter($order, static function ($k) use ($known_keys) {
    return isset($known_keys[$k]);
}));
foreach ($default_order as $k) {
    if (!in_array($k, $order, true)) {
        $order[] = $k;
    }
}

$widths = isset($prefs['widths']) && is_array($prefs['widths']) ? $prefs['widths'] : [];

$page_title = function_exists('auragold_t') ? auragold_t('rep.customer_kyc') : 'Customer / KYC Report';

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container kyc-report-page">
    <div class="main-content">
        <div class="page-container">
            <div class="page-header-bar kyc-toolbar">
                <form method="get" class="kyc-header-search" action="kyc-report.php">
                    <?php if ($customer_type_id) {
                        ?><input type="hidden" name="customer_type_id" value="<?php echo (int) $customer_type_id; ?>"><?php
                    } ?>
                    <?php if ($country_id) {
                        ?><input type="hidden" name="country_id" value="<?php echo (int) $country_id; ?>"><?php
                    } ?>
                    <?php if ($nationality_id) {
                        ?><input type="hidden" name="nationality_id" value="<?php echo (int) $nationality_id; ?>"><?php
                    } ?>
                    <?php if ($has_aml !== '') {
                        ?><input type="hidden" name="has_aml" value="<?php echo htmlspecialchars($has_aml, ENT_QUOTES, 'UTF-8'); ?>"><?php
                    } ?>
                    <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
                    <div class="kyc-search-wrap">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search" class="form-control kyc-search-input" autocomplete="off">
                        <button type="submit" class="kyc-search-submit" title="Search" aria-label="Search">
                            <i class="feather icon-search"></i>
                        </button>
                    </div>
                </form>
                <div class="kyc-toolbar-actions page-header-actions">
                    <button type="button" class="kyc-btn-filter" onclick="openFilterModal()" title="Filter">
                        <i class="feather icon-filter"></i>
                        <?php
                        $filter_count = 0;
                        if ($customer_type_id) {
                            ++$filter_count;
                        }
                        if ($country_id) {
                            ++$filter_count;
                        }
                        if ($nationality_id) {
                            ++$filter_count;
                        }
                        if ($has_aml !== '') {
                            ++$filter_count;
                        }
                        if ($filter_count > 0) {
                            echo '<span class="kyc-filter-badge">' . (int) $filter_count . '</span>';
                        }
                        ?>
                    </button>
                    <button type="button" class="kyc-btn-refresh" onclick="location.reload()" title="Refresh">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <button type="button" class="kyc-btn-columns" onclick="window.kycOpenColumns && window.kycOpenColumns()" title="Show / hide columns" aria-label="Column settings">
                        <i class="feather icon-settings"></i>
                    </button>
                    <div class="dropdown kyc-export-dd">
                        <button type="button" class="kyc-btn-export" title="Export" onclick="event.stopPropagation(); this.parentNode.querySelector('.dropdown-menu').classList.toggle('show');">
                            <span>Export</span>
                            <i class="feather icon-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="exportToExcel(); return false;">Export to Excel (.xlsx)</a>
                            <a class="dropdown-item" href="#" onclick="exportToPdf(); return false;">Export to PDF</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body kyc-card-body">
                    <div class="table-responsive kyc-table-wrap">
                        <table id="kycReportTable" class="table table-bordered kyc-data-table">
                            <thead>
                                <tr id="tableHeaderRow"></tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="100" class="kyc-loading-cell">Loading data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="kyc-footer-bar">
                        <span id="paginationInfo" class="kyc-footer-info">Showing 0 to 0 of 0 entries</span>
                        <div class="kyc-footer-right">
                            <label class="kyc-per-page">
                                <span>Show</span>
                                <select id="perPageSelect" class="form-control form-control-sm" onchange="changePerPage(this.value)">
                                    <?php foreach ([10, 25, 50, 100] as $n) { ?>
                                        <option value="<?php echo $n; ?>" <?php echo $per_page === $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
                                    <?php } ?>
                                </select>
                                <span>Items</span>
                            </label>
                            <div class="pagination" id="pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="filterModal" class="filter-modal">
    <div class="filter-modal-content">
        <div class="filter-modal-header">
            <h5>Advance Filter</h5>
            <button type="button" class="filter-modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="filter-modal-body">
            <form method="get" action="kyc-report.php" id="filterForm">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Customer Type</label>
                        <select name="customer_type_id" class="form-control">
                            <option value="">Select Customer Type</option>
                            <?php foreach ($customer_types as $ct) { ?>
                                <option value="<?php echo (int) $ct['id']; ?>" <?php echo $customer_type_id === (int) $ct['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['name'] ?? ''); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>Country</label>
                        <select name="country_id" class="form-control">
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country) { ?>
                                <option value="<?php echo (int) $country['id']; ?>" <?php echo $country_id === (int) $country['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($country['name'] ?? ''); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label>Nationality</label>
                        <select name="nationality_id" class="form-control">
                            <option value="">Select Nationality</option>
                            <?php foreach ($nationalities as $nationalOption) { ?>
                                <option value="<?php echo (int) $nationalOption['id']; ?>" <?php echo $nationality_id === (int) $nationalOption['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($nationalOption['name'] ?? ''); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label>AML Status</label>
                        <select name="has_aml" class="form-control">
                            <option value="">All</option>
                            <option value="1" <?php echo $has_aml === '1' ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo $has_aml === '0' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
                <div class="filter-modal-footer">
                    <button type="submit" class="btn-apply">Apply Filter</button>
                    <button type="button" class="btn-clear" onclick="window.location.href='kyc-report.php'">Clear Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="columnsModal" class="kyc-col-modal" aria-hidden="true">
    <div class="kyc-col-modal__backdrop" id="columnsModalBackdrop" role="presentation"></div>
    <div class="kyc-col-modal__panel" role="dialog" aria-modal="true" aria-labelledby="kycColModalTitle">
        <div class="kyc-col-modal__head">
            <h2 class="kyc-col-modal__title" id="kycColModalTitle">Show / hide columns</h2>
            <button type="button" class="kyc-col-modal__close" onclick="closeColumnsModal()" title="Close" aria-label="Close">&times;</button>
        </div>
        <p class="kyc-col-modal__hint">Tick columns to show. Drag the grip in the list or use the move icon on each table header to reorder. Drag the right edge of a header to resize.</p>
        <input type="text" id="columnSearch" class="form-control kyc-col-modal__search" placeholder="Search columns" autocomplete="off">
        <div id="columnsList" class="kyc-columns-list"></div>
        <div class="kyc-col-modal__actions">
            <div class="kyc-col-modal__actions-left">
                <button type="button" class="kyc-col-modal__icon-text" onclick="exportToExcel(); return false;" title="Export to Excel (.xlsx)">
                    <i class="feather icon-file-text kyc-icon-excel"></i><span>Excel (.xlsx)</span>
                </button>
                <button type="button" class="kyc-col-modal__icon-text" onclick="exportToPdf(); return false;" title="Print / PDF">
                    <i class="feather icon-file kyc-icon-pdf"></i><span>PDF</span>
                </button>
            </div>
            <div class="kyc-col-modal__actions-right">
                <button type="button" class="kyc-col-modal__btn kyc-col-modal__btn--secondary" onclick="resetColumns()">Reset columns</button>
                <button type="button" class="kyc-col-modal__btn kyc-col-modal__btn--primary" onclick="closeColumnsModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="kycLedgerModal" class="kyc-ledger-modal" aria-hidden="true">
    <div class="kyc-ledger-modal__backdrop" id="kycLedgerModalBackdrop" role="presentation"></div>
    <div class="kyc-ledger-modal__panel" role="dialog" aria-modal="true" aria-labelledby="kycLedgerModalTitle">
        <div class="kyc-ledger-modal__head">
            <h2 class="kyc-ledger-modal__title" id="kycLedgerModalTitle">KYC Ledger Details</h2>
            <button type="button" class="kyc-ledger-modal__close" onclick="window.kycCloseLedgerDetailsModal && window.kycCloseLedgerDetailsModal()" title="Close" aria-label="Close">&times;</button>
        </div>
        <div id="kycLedgerModalBody" class="kyc-ledger-modal__body">
            <div class="kyc-ledger-loading">Loading…</div>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<style>
.kyc-report-page {
    --kyc-navy: #11294b;
    --kyc-navy-dark: #0d2038;
    --kyc-gold: #c9a227;
    --kyc-gold-hover: #d4b03a;
    --kyc-gold-light: #e8c547;
}
.kyc-report-page .layout-container {
    padding: 20px clamp(16px, 3vw, 36px);
    width: 100%;
    box-sizing: border-box;
    background: #f4f6fb;
    min-height: calc(100vh - 60px);
    max-width: 100%;
}
.kyc-report-page .main-content,
.kyc-report-page .page-container {
    max-width: 100%;
    min-width: 0;
    padding-left: clamp(4px, 1.5vw, 16px);
    padding-right: clamp(4px, 1.5vw, 16px);
    box-sizing: border-box;
}
.kyc-toolbar.page-header-bar {
    background: #fff;
    padding: 12px 16px;
    border: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    flex-wrap: wrap;
}
.kyc-header-search {
    flex: 1;
    min-width: 200px;
    margin: 0;
    max-width: 640px;
}
.kyc-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.kyc-search-wrap:focus-within {
    border-color: #c4b5fd;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}
.kyc-search-input {
    border: none !important;
    box-shadow: none !important;
    height: 40px;
    font-size: 13px;
    padding: 8px 44px 8px 14px;
    border-radius: 10px !important;
    flex: 1;
    background: transparent !important;
}
.kyc-search-submit {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 32px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.kyc-search-submit:hover {
    color: #7c3aed;
    background: #f5f3ff;
}
.kyc-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.kyc-btn-filter {
    position: relative;
    width: 40px;
    height: 40px;
    border: 2px solid var(--kyc-gold);
    border-radius: 8px;
    background: var(--kyc-navy);
    color: #fff !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(17, 41, 75, 0.32);
    transition: background 0.15s, border-color 0.15s, transform 0.1s;
}
.kyc-btn-filter:hover {
    background: var(--kyc-navy-dark);
    border-color: var(--kyc-gold-hover);
}
.kyc-btn-filter .feather { width: 18px; height: 18px; stroke-width: 2.2px; }
.kyc-filter-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    background: #ef4444;
    color: #fff;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
    border: 2px solid #fff;
}
.kyc-btn-refresh {
    width: 40px;
    height: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.kyc-btn-refresh:hover {
    border-color: var(--kyc-gold);
    color: var(--kyc-navy);
    background: #faf8f1;
}
.kyc-btn-columns {
    width: 40px;
    height: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: var(--kyc-navy);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    padding: 0;
}
.kyc-btn-columns:hover {
    border-color: var(--kyc-gold);
    color: var(--kyc-navy);
    background: #faf8f1;
}
.kyc-btn-columns .feather {
    width: 18px;
    height: 18px;
}
.kyc-btn-export {
    height: 40px;
    padding: 0 14px 0 16px;
    border: 2px solid var(--kyc-gold);
    border-radius: 8px;
    background: var(--kyc-navy);
    color: #fff !important;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(17, 41, 75, 0.32);
    transition: background 0.15s, border-color 0.15s;
}
.kyc-btn-export:hover {
    background: var(--kyc-navy-dark);
    border-color: var(--kyc-gold-hover);
}
.kyc-btn-export .feather {
    color: var(--kyc-gold-light);
    width: 16px;
    height: 16px;
}
.kyc-export-dd { position: relative; }
.kyc-export-dd .dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
    min-width: 180px;
    z-index: 200;
    padding: 4px 0;
}
.kyc-export-dd .dropdown-menu.show { display: block; }
.kyc-export-dd .dropdown-item {
    display: block;
    padding: 10px 16px;
    font-size: 13px;
    color: #334155;
    text-decoration: none;
}
.kyc-export-dd .dropdown-item:hover { background: #f8fafc; color: #7c3aed; }
.kyc-report-page .kyc-card-body {
    padding: 18px clamp(14px, 2.5vw, 28px) 20px !important;
    box-sizing: border-box;
}
.kyc-report-page .card {
    overflow: hidden;
    max-width: 100%;
    min-width: 0;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.kyc-table-wrap {
    overflow-x: auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    max-height: calc(100vh - 300px);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    margin: 0;
}
/* Horizontal scroll hint when content is wider than viewport */
.kyc-table-wrap:focus {
    outline: none;
}
#kycReportTable {
    margin: 0;
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: auto;
}
#kycReportTable.kyc-data-table { border-color: #e9ecef; }
#kycReportTable.kyc-data-table td,
#kycReportTable.kyc-data-table th { border-color: #eef2f7; }
/* Navy + gold header (ageing report style); drag handle + resizer */
#kycReportTable thead th.kyc-col-head {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--kyc-navy) !important;
    font-size: 12px;
    font-weight: 600;
    color: #fff !important;
    padding: 12px 10px 12px 8px;
    line-height: 1.35;
    border-bottom: 2px solid var(--kyc-gold);
    white-space: nowrap;
    vertical-align: middle;
    min-width: 120px;
    box-sizing: border-box;
    height: auto;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
    text-align: left;
    padding-right: 12px;
}
#kycReportTable thead th.kyc-col-head.kyc-col-sortable .kyc-col-head-inner {
    cursor: pointer;
}
#kycReportTable thead th.kyc-col-head[data-column="name"] {
    min-width: 170px;
}
#kycReportTable thead th.kyc-col-head[data-column="action"] {
    min-width: 110px;
}
#kycReportTable thead th.kyc-col-head.kyc-col-sortable:hover {
    background: #1a3a63 !important;
}
.kyc-col-drag {
    display: inline-block;
    vertical-align: middle;
    margin-right: 6px;
    cursor: grab;
    opacity: 0.9;
    line-height: 0;
    user-select: none;
    touch-action: none;
    color: rgba(255, 255, 255, 0.92);
}
.kyc-col-drag:hover {
    color: var(--kyc-gold-light);
}
.kyc-col-drag:active {
    cursor: grabbing;
}
.kyc-col-head-inner {
    vertical-align: middle;
    display: inline;
}
.kyc-col-head .kyc-sort-ico {
    width: 13px;
    height: 13px;
    opacity: 0.9;
    vertical-align: middle;
    margin-left: 2px;
}
#kycReportTable thead th.kyc-col-head.kyc-col-dragging {
    opacity: 0.55;
}
#kycReportTable thead th.kyc-col-head.kyc-col-drop-target {
    box-shadow: inset 0 0 0 2px var(--kyc-gold-light);
}
.kyc-col-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 8px;
    cursor: col-resize;
    z-index: 4;
    user-select: none;
}
.kyc-col-resizer:hover {
    background: rgba(232, 197, 71, 0.25);
}
#kycReportTable tbody td {
    font-size: 12px;
    color: #334155;
    padding: 11px 14px;
    line-height: 1.35;
    border-bottom: 1px solid #eef2f7;
    white-space: nowrap;
    vertical-align: middle;
    min-width: 120px;
    max-width: 360px;
    overflow: hidden;
    text-overflow: ellipsis;
    box-sizing: border-box;
}
#kycReportTable tbody td[data-col="name"] {
    min-width: 170px;
    max-width: 420px;
}
#kycReportTable tbody td[data-col="action"] {
    min-width: 110px;
    max-width: 140px;
}
#kycReportTable tbody tr:nth-child(odd) { background: #ffffff; }
#kycReportTable tbody tr:nth-child(even) { background: #f4f7ff; }
#kycReportTable tbody tr:hover { background: #eef2ff !important; }
.kyc-loading-cell { text-align: center; padding: 40px; color: #64748b; }
.kyc-footer-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    border-top: 1px solid #e9ecef;
    background: #fff;
    font-size: 12px;
    color: #64748b;
}
.kyc-footer-info { flex-shrink: 0; }
.kyc-footer-right {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}
.kyc-per-page { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 12px; color: #64748b; }
.kyc-per-page select {
    width: auto;
    min-width: 72px;
    height: 32px;
    font-size: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0 8px;
}
.kyc-report-page .pagination { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.kyc-report-page .pagination .page-link {
    min-width: 32px;
    height: 32px;
    padding: 0 10px;
    border: 1px solid transparent;
    border-radius: 6px;
    color: #64748b;
    text-decoration: none;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
}
.kyc-report-page .pagination .page-link.page-nav {
    border-color: #e2e8f0;
    background: #fff;
    color: #475569;
}
.kyc-report-page .pagination .page-link.page-nav:hover {
    border-color: var(--kyc-gold);
    color: var(--kyc-navy);
    background: #faf8f1;
}
.kyc-report-page .pagination .page-link.page-num.active {
    background: var(--kyc-navy) !important;
    color: #fff !important;
    border: 2px solid var(--kyc-gold) !important;
    border-radius: 50%;
    min-width: 32px;
    width: 32px;
    height: 32px;
    padding: 0;
    font-weight: 600;
}
.kyc-report-page .pagination .page-link.page-num:not(.active):hover {
    background: #f1f5f9;
    color: var(--kyc-navy);
}
.action-buttons { display: flex; gap: 4px; justify-content: flex-start; }
.action-buttons .action-btn {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: var(--kyc-navy);
    border-radius: 4px;
}
.action-buttons .action-btn:hover {
    background: rgba(17, 41, 75, 0.08);
    color: var(--kyc-gold-hover);
}
.filter-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.filter-modal.active { display: flex; }
.filter-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 560px; max-height: 88vh; overflow: hidden; display: flex; flex-direction: column; }
.filter-modal-header {
    background: #11294b;
    color: #fff;
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}
.filter-modal-header h5 { margin: 0; font-size: 13px; font-weight: 600; }
.filter-modal-close { background: none; border: none; color: #fff; font-size: 22px; cursor: pointer; line-height: 1; padding: 0 4px; }
.filter-modal-body { padding: 14px; overflow: auto; }
/* —— KYC columns: centered modal (match ageing report pattern) —— */
.kyc-col-modal {
    --kyc-modal-navy: #11294b;
    --kyc-modal-gold: #c9a227;
    display: none;
    position: fixed;
    inset: 0;
    z-index: 5000;
    align-items: center;
    justify-content: center;
    padding: clamp(12px, 3vw, 24px);
    box-sizing: border-box;
}
.kyc-col-modal.active {
    display: flex !important;
}
.kyc-col-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    cursor: pointer;
}
.kyc-col-modal__panel {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 440px;
    max-height: min(92vh, 700px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 22px 56px rgba(15, 23, 42, 0.22);
    padding: 20px 22px 18px;
    overflow: hidden;
}
.kyc-col-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
    flex-shrink: 0;
}
.kyc-col-modal__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.3;
}
.kyc-col-modal__close {
    border: 0;
    background: transparent;
    font-size: 1.5rem;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
    padding: 0 4px;
    margin: -4px -4px 0 0;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
}
.kyc-col-modal__close:hover {
    color: #1e293b;
    background: #f1f5f9;
}
.kyc-col-modal__hint {
    margin: 0 0 12px;
    font-size: 12px;
    color: #64748b;
    line-height: 1.45;
    flex-shrink: 0;
}
.kyc-col-modal__search {
    margin-bottom: 12px;
    height: 38px;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.kyc-columns-list {
    flex: 1 1 auto;
    min-height: 120px;
    max-height: min(46vh, 360px);
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #e8edf2;
    border-radius: 8px;
    background: #fafbfc;
}
.kyc-column-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
    cursor: default;
    font-size: 13px;
    color: #334155;
    background: #fff;
}
.kyc-column-item:last-child {
    border-bottom: 0;
}
.kyc-column-item:hover {
    background: #f8fafc;
}
.kyc-column-item.dragging {
    opacity: 0.55;
}
.kyc-drag-handle {
    cursor: grab;
    color: #94a3b8;
    padding: 2px;
    display: flex;
    align-items: center;
}
.kyc-drag-handle:active {
    cursor: grabbing;
}
.kyc-column-item input[type="checkbox"] {
    accent-color: var(--kyc-gold);
    width: 17px;
    height: 17px;
    flex-shrink: 0;
}
.kyc-col-modal__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e8e4d9;
    flex-shrink: 0;
}
.kyc-col-modal__actions-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.kyc-col-modal__actions-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-left: auto;
}
.kyc-col-modal__icon-text {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.kyc-col-modal__icon-text:hover {
    border-color: #c4b5fd;
    background: #faf5ff;
    color: #6d28d9;
}
.kyc-col-modal__icon-text .feather {
    width: 15px;
    height: 15px;
}
.kyc-icon-excel { color: #16a34a !important; }
.kyc-icon-pdf { color: #dc2626 !important; }
.kyc-col-modal__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.2;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.08s;
    -webkit-appearance: none;
    appearance: none;
}
.kyc-col-modal__btn--secondary {
    background: #fffef8;
    color: var(--kyc-modal-navy);
    border: 1px solid rgba(17, 41, 75, 0.45);
    box-shadow: 0 1px 2px rgba(17, 41, 75, 0.06);
}
.kyc-col-modal__btn--secondary:hover {
    background: #fff;
    border-color: var(--kyc-modal-navy);
    box-shadow: 0 3px 10px rgba(17, 41, 75, 0.1);
}
.kyc-col-modal__btn--primary {
    background: var(--kyc-modal-navy);
    color: #fff !important;
    border: 2px solid var(--kyc-modal-gold);
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.35), 0 0 0 1px rgba(201, 162, 39, 0.35);
}
.kyc-col-modal__btn--primary:hover {
    background: #0d2038;
    border-color: #d4b03a;
    box-shadow: 0 4px 14px rgba(17, 41, 75, 0.4);
}
.kyc-col-modal__btn:active {
    transform: translateY(1px);
}
.filter-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.filter-form-group label { font-size: 11px; color: #475569; margin-bottom: 4px; display: block; }
.filter-form-group select { height: 32px; font-size: 12px; }
.filter-modal-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px; }
.btn-apply { background: #11294b; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-clear { background: #ef4444; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; }

/* Info column + KYC ledger details modal */
#kycReportTable tbody td[data-col="info"] {
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
}
.kyc-info-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: none;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #2563eb;
    color: #fff;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.35);
    transition: background 0.15s, transform 0.08s, box-shadow 0.15s;
    vertical-align: middle;
}
.kyc-info-btn:hover {
    background: #1d4ed8;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.45);
}
.kyc-info-btn:active {
    transform: scale(0.96);
}
.kyc-info-btn__i {
    font-size: 13px;
    font-weight: 700;
    font-style: italic;
    line-height: 1;
    font-family: Georgia, 'Times New Roman', serif;
    user-select: none;
}
.kyc-ledger-modal {
    --kyc-lm-navy: #11294b;
    --kyc-lm-gold: #c9a227;
    display: none;
    position: fixed;
    inset: 0;
    z-index: 5100;
    align-items: center;
    justify-content: center;
    padding: clamp(12px, 3vw, 24px);
    box-sizing: border-box;
}
.kyc-ledger-modal.active {
    display: flex !important;
}
.kyc-ledger-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    cursor: pointer;
}
.kyc-ledger-modal__panel {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 920px;
    max-height: min(92vh, 880px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 22px 56px rgba(15, 23, 42, 0.22);
    border: 2px solid var(--kyc-lm-navy);
    overflow: hidden;
}
.kyc-ledger-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    background: var(--kyc-lm-navy);
    color: #fff;
    flex-shrink: 0;
    border-bottom: 2px solid var(--kyc-lm-gold);
}
.kyc-ledger-modal__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #fff;
}
.kyc-ledger-modal__close {
    border: 0;
    background: transparent;
    font-size: 1.5rem;
    line-height: 1;
    color: var(--kyc-gold-light);
    cursor: pointer;
    padding: 0 4px;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
}
.kyc-ledger-modal__close:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.12);
}
.kyc-ledger-modal__body {
    padding: 18px 20px 20px;
    overflow: auto;
    flex: 1 1 auto;
    min-height: 120px;
}
.kyc-ledger-loading,
.kyc-ledger-error {
    text-align: center;
    color: #64748b;
    padding: 28px 12px;
    font-size: 14px;
}
.kyc-ledger-error {
    color: #dc2626;
}
.kyc-ledger-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--kyc-lm-navy);
    margin: 0 0 14px;
}
.kyc-ledger-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 28px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #334155;
}
@media (max-width: 640px) {
    .kyc-ledger-grid { grid-template-columns: 1fr; }
}
.kyc-ledger-grid label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 2px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.kyc-ledger-grid .kyc-ledger-val {
    margin: 0;
    word-break: break-word;
}
.kyc-ledger-flags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 28px;
    margin-bottom: 20px;
    padding: 14px 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 14px;
}
.kyc-ledger-modal__body .kyc-ledger-flag-item {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    padding: 0;
    cursor: pointer;
    font-weight: 600;
    color: #334155;
    line-height: 1.2;
    user-select: none;
}
.kyc-ledger-modal__body .kyc-ledger-flag-item input[type="checkbox"] {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    min-width: 20px;
    min-height: 20px;
    margin: 0;
    flex-shrink: 0;
    border: 2px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
    box-sizing: border-box;
    vertical-align: middle;
    position: static;
    float: none;
    cursor: pointer;
    pointer-events: auto;
    transition: border-color 0.15s, background 0.15s;
}
.kyc-ledger-modal__body .kyc-ledger-flag-item input[type="checkbox"]:checked {
    border-color: var(--kyc-lm-navy);
    background: var(--kyc-lm-navy);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23c9a227' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 8l3 3 7-7'/%3E%3C/svg%3E");
    background-size: 14px 14px;
    background-position: center;
    background-repeat: no-repeat;
}
.kyc-ledger-modal__body .kyc-ledger-flag-item input[type="checkbox"]:disabled {
    opacity: 0.65;
    cursor: wait;
    pointer-events: none;
}
.kyc-ledger-modal__body .kyc-ledger-flag-item.is-saving {
    opacity: 0.75;
}
.kyc-ledger-flag-meta {
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
}
.kyc-ledger-flag-yesno {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.kyc-ledger-flag-yesno.is-on {
    color: #15803d;
}
.kyc-ledger-flag-yesno.is-off {
    color: #64748b;
}
.kyc-ledger-docs-head {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--kyc-lm-navy);
    margin: 0 0 10px;
}
.kyc-ledger-doc-table-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: auto;
    max-height: 280px;
}
.kyc-ledger-doc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin: 0;
}
.kyc-ledger-doc-table th {
    background: var(--kyc-lm-navy);
    color: #fff;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--kyc-lm-gold);
    white-space: nowrap;
}
.kyc-ledger-doc-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
    color: #334155;
}
.kyc-ledger-doc-table tr:last-child td {
    border-bottom: 0;
}
.kyc-ledger-doc-table tr:nth-child(even) td {
    background: #fafbfc;
}
.kyc-doc-empty {
    text-align: center;
    color: #94a3b8;
    padding: 20px !important;
}
.kyc-doc-act {
    text-align: center;
    width: 52px;
}
.kyc-doc-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    color: var(--kyc-lm-navy);
    background: #fff;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.kyc-doc-link:hover {
    border-color: var(--kyc-lm-gold);
    background: #fffef8;
    color: var(--kyc-lm-navy);
}
.kyc-doc-link .feather {
    width: 18px;
    height: 18px;
}
</style>

<script>
(function () {
    const columnLabels = <?php echo json_encode($column_labels, JSON_UNESCAPED_UNICODE); ?>;
    const columnOrder = <?php echo json_encode($order, JSON_UNESCAPED_UNICODE); ?>;
    const visibility = <?php echo json_encode($visibility, JSON_UNESCAPED_UNICODE); ?>;
    const widths = <?php echo json_encode($widths, JSON_UNESCAPED_UNICODE); ?>;

    let currentPage = <?php echo (int) $page; ?>;
    let currentPerPage = <?php echo (int) $per_page; ?>;
    const __urlParams = new URLSearchParams(window.location.search);
    let sortColumn = __urlParams.get('sort') || '';
    let sortOrder = (__urlParams.get('order') || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
                          window.__kycLastRows = [];

    function prefsPayload() {
        return {
            visibility: Object.assign({}, visibility),
            order: columnOrder.slice(),
            widths: Object.assign({}, widths)
        };
    }

    function savePrefs(redirectReload) {
        const body = prefsPayload();
        fetch('ajax/save-kyc-report-columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function () {
            if (redirectReload) {
                window.location.reload();
            }
        }).catch(function () {
            if (redirectReload) {
                window.location.reload();
            }
        });
    }

    function orderedKeys() {
        return columnOrder.filter(function (k) { return columnLabels[k]; });
    }

    function getVisibleHeadKeys() {
        var keys = [];
        var tr = document.getElementById('tableHeaderRow');
        if (!tr) return keys;
        tr.querySelectorAll('th[data-column]').forEach(function (h) {
            keys.push(h.getAttribute('data-column'));
        });
        return keys;
    }

    function mergeColumnOrderAfterReorder(visibleKeys) {
        var hidden = [];
        columnOrder.forEach(function (k) {
            if (visibility[k] === false) {
                hidden.push(k);
            }
        });
        columnOrder.length = 0;
        visibleKeys.forEach(function (k) { columnOrder.push(k); });
        hidden.forEach(function (k) { columnOrder.push(k); });
    }

    function reorderKycTableColumns(visibleKeys) {
        var row = document.getElementById('tableHeaderRow');
        if (!row) return;
        var map = {};
        row.querySelectorAll('th[data-column]').forEach(function (th) {
            map[th.getAttribute('data-column')] = th;
        });
        var frag = document.createDocumentFragment();
        visibleKeys.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        row.appendChild(frag);

        document.querySelectorAll('#tableBody tr').forEach(function (tr) {
            if (tr.querySelector('.kyc-loading-cell')) return;
            var cMap = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                cMap[td.getAttribute('data-col')] = td;
            });
            var f2 = document.createDocumentFragment();
            visibleKeys.forEach(function (k) {
                if (cMap[k]) f2.appendChild(cMap[k]);
            });
            tr.appendChild(f2);
        });
    }

    function initKycColumnDragResize() {
        var headRow = document.getElementById('tableHeaderRow');
        if (!headRow) return;

        function clearDropHighlights() {
            headRow.querySelectorAll('.kyc-col-drop-target').forEach(function (x) {
                x.classList.remove('kyc-col-drop-target');
            });
        }

        function thFromPoint(clientX, clientY) {
            var el = document.elementFromPoint(clientX, clientY);
            if (!el || !el.closest) return null;
            var t = el.closest('#tableHeaderRow th[data-column]');
            return t || null;
        }

        headRow.querySelectorAll('th[data-column].kyc-col-head').forEach(function (th) {
            var dragEl = th.querySelector('.kyc-col-drag');
            if (!dragEl) return;

            dragEl.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                var dragFromKey = th.getAttribute('data-column');
                if (!dragFromKey) return;
                e.preventDefault();
                th.classList.add('kyc-col-dragging');
                try { dragEl.setPointerCapture(e.pointerId); } catch (err1) {}

                function onMove(ev) {
                    clearDropHighlights();
                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (over && over.getAttribute('data-column') !== dragFromKey) {
                        over.classList.add('kyc-col-drop-target');
                    }
                }

                function onEnd(ev) {
                    th.classList.remove('kyc-col-dragging');
                    clearDropHighlights();
                    try { dragEl.releasePointerCapture(ev.pointerId); } catch (err2) {}
                    dragEl.removeEventListener('pointermove', onMove);
                    dragEl.removeEventListener('pointerup', onEnd);
                    dragEl.removeEventListener('pointercancel', onEnd);

                    var over = thFromPoint(ev.clientX, ev.clientY);
                    var toKey = over && over.getAttribute('data-column');
                    if (!toKey || toKey === dragFromKey) return;
                    var order = getVisibleHeadKeys().slice();
                    var i = order.indexOf(dragFromKey);
                    var j = order.indexOf(toKey);
                    if (i < 0 || j < 0) return;
                    order.splice(i, 1);
                    order.splice(j, 0, dragFromKey);
                    mergeColumnOrderAfterReorder(order);
                    reorderKycTableColumns(order);
                    savePrefs(false);
                }

                dragEl.addEventListener('pointermove', onMove);
                dragEl.addEventListener('pointerup', onEnd);
                dragEl.addEventListener('pointercancel', onEnd);
            });

            var resizer = th.querySelector('.kyc-col-resizer');
            if (resizer) {
                resizer.addEventListener('mousedown', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var colKey = th.getAttribute('data-column');
                    var startX = e.clientX;
                    var startW = th.getBoundingClientRect().width;
                    function onMove(ev) {
                        var w = Math.max(120, startW + (ev.clientX - startX));
                        th.style.width = w + 'px';
                        th.style.minWidth = w + 'px';
                        if (colKey) widths[colKey] = w;
                        document.querySelectorAll('#tableBody tr td[data-col="' + colKey + '"]').forEach(function (td) {
                            td.style.width = w + 'px';
                            td.style.minWidth = w + 'px';
                        });
                    }
                    function onUp() {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        savePrefs(false);
                    }
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            }
        });
    }

    function buildHeaderRow() {
        const tr = document.getElementById('tableHeaderRow');
        tr.innerHTML = '';
        orderedKeys().forEach(function (key) {
            if (visibility[key] === false) return;
            const th = document.createElement('th');
            th.className = 'kyc-col-head';
            th.dataset.column = key;
            const label = columnLabels[key] || key;
            const drag = document.createElement('span');
            drag.className = 'kyc-col-drag';
            drag.setAttribute('title', 'Drag to reorder');
            drag.innerHTML = '<i class="feather icon-move"></i>';
            const inner = document.createElement('span');
            inner.className = 'kyc-col-head-inner';
            if (key === 'action') {
                inner.textContent = label;
            } else {
                inner.innerHTML = label + ' <i class="feather icon-chevrons-up-down kyc-sort-ico"></i>';
                th.classList.add('kyc-col-sortable');
            }
            const resizer = document.createElement('span');
            resizer.className = 'kyc-col-resizer';
            resizer.setAttribute('title', 'Resize');
            th.appendChild(drag);
            th.appendChild(inner);
            th.appendChild(resizer);
            const w = widths[key];
            if (w && parseInt(w, 10) > 0) {
                const px = Math.max(120, parseInt(w, 10));
                th.style.width = px + 'px';
                th.style.minWidth = px + 'px';
            }
            if (key !== 'action') {
                inner.addEventListener('click', function (ev) {
                    if (ev.target.closest('.kyc-col-drag') || ev.target.closest('.kyc-col-resizer')) return;
                    if (sortColumn === key) {
                        sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortColumn = key;
                        sortOrder = 'asc';
                    }
                    loadData();
                });
            }
            tr.appendChild(th);
        });
        initKycColumnDragResize();
    }

    function loadData() {
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(currentPage));
        params.set('per_page', String(currentPerPage));
        if (sortColumn) {
            params.set('sort', sortColumn);
            params.set('order', sortOrder);
        }
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '<tr><td colspan="100" class="kyc-loading-cell">Loading data...</td></tr>';
        fetch('ajax/get-kyc-report.php?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    window.__kycLastRows = data.data || [];
                    renderRows(data.data || []);
                    renderPagination(data.pagination);
                    const u = new URL(window.location.href);
                    if (sortColumn) {
                        u.searchParams.set('sort', sortColumn);
                        u.searchParams.set('order', sortOrder);
                    } else {
                        u.searchParams.delete('sort');
                        u.searchParams.delete('order');
                    }
                    u.searchParams.set('page', String(currentPage));
                    u.searchParams.set('per_page', String(currentPerPage));
                    window.history.replaceState({}, '', u);
                } else {
                    tbody.innerHTML = '<tr><td colspan="100" class="kyc-loading-cell" style="color:#ef4444;">' + (data.message || 'Error') + '</td></tr>';
                }
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="100" class="kyc-loading-cell" style="color:#ef4444;">Error loading data</td></tr>';
            });
    }

    function renderRows(rows) {
        buildHeaderRow();
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="100" class="kyc-loading-cell">No Rows To Show</td></tr>';
            return;
        }
        rows.forEach(function (row) {
            const tr = document.createElement('tr');
            orderedKeys().forEach(function (key) {
                if (visibility[key] === false) {
                    return;
                }
                const td = document.createElement('td');
                td.dataset.col = key;
                const w = widths[key];
                if (w && parseInt(w, 10) > 0) {
                    const px = Math.max(120, parseInt(w, 10));
                    td.style.width = px + 'px';
                    td.style.minWidth = px + 'px';
                }
                if (key === 'action') {
                    td.innerHTML =
                        '<div class="action-buttons">' +
                        '<button type="button" class="action-btn" title="Print" onclick="window.kycPrint(' + row.id + ')"><i class="feather icon-printer"></i></button>' +
                        '<button type="button" class="action-btn" title="Download row" onclick="window.kycDownloadRow(' + row.id + ')"><i class="feather icon-download"></i></button>' +
                        '</div>';
                } else if (key === 'info') {
                    const notes = row[key] != null ? String(row[key]) : '';
                    td.dataset.exportValue = notes.replace(/\r\n/g, ' ').replace(/\n/g, ' ').trim();
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'kyc-info-btn';
                    btn.title = 'KYC ledger details';
                    btn.setAttribute('aria-label', 'KYC details');
                    btn.innerHTML = '<span class="kyc-info-btn__i">i</span>';
                    btn.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        window.kycOpenLedgerDetails(row.id);
                    });
                    td.appendChild(btn);
                } else {
                    const cellText = row[key] != null ? String(row[key]) : '';
                    td.textContent = cellText;
                    if (cellText.length > 40) {
                        td.setAttribute('title', cellText);
                    }
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    function renderPagination(p) {
        const el = document.getElementById('pagination');
        const info = document.getElementById('paginationInfo');
        if (!p) {
            el.innerHTML = '';
            info.textContent = 'Showing 0 to 0 of 0 entries';
            return;
        }
        const total = p.total || 0;
        const cur = p.current_page || 1;
        const pp = p.per_page || currentPerPage;
        const tpages = p.total_pages || 1;
        const start = total ? (cur - 1) * pp + 1 : 0;
        const end = Math.min(cur * pp, total);
        info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + total + ' entries';
        el.innerHTML = '';
        if (tpages <= 1) {
            return;
        }
        if (cur > 1) {
            el.insertAdjacentHTML('beforeend', '<a href="#" class="page-link page-nav">&lt;&lt;</a>');
            el.lastChild.addEventListener('click', function (e) { e.preventDefault(); currentPage = 1; loadData(); });
            el.insertAdjacentHTML('beforeend', '<a href="#" class="page-link page-nav">&lt;</a>');
            el.lastChild.addEventListener('click', function (e) { e.preventDefault(); currentPage = cur - 1; loadData(); });
        }
        const s = Math.max(1, cur - 2);
        const en = Math.min(tpages, cur + 2);
        for (let i = s; i <= en; i++) {
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'page-link page-num' + (i === cur ? ' active' : '');
            a.textContent = String(i);
            a.addEventListener('click', function (e) {
                e.preventDefault();
                currentPage = i;
                loadData();
            });
            el.appendChild(a);
        }
        if (cur < tpages) {
            el.insertAdjacentHTML('beforeend', '<a href="#" class="page-link page-nav">&gt;</a>');
            el.lastChild.addEventListener('click', function (e) { e.preventDefault(); currentPage = cur + 1; loadData(); });
            el.insertAdjacentHTML('beforeend', '<a href="#" class="page-link page-nav">&gt;&gt;</a>');
            el.lastChild.addEventListener('click', function (e) { e.preventDefault(); currentPage = tpages; loadData(); });
        }
    }

    window.kycPrint = function (id) {
        window.open('kyc-form-pdf.php?id=' + encodeURIComponent(id), '_blank');
    };

    window.kycDownloadRow = function (id) {
        const row = (window.__kycLastRows || []).find(function (r) { return String(r.id) === String(id); });
        if (!row) {
            return;
        }
        const keys = orderedKeys().filter(function (k) { return k !== 'action' && visibility[k] !== false; });
        const line = keys.map(function (k) {
            let v = row[k] != null ? String(row[k]) : '';
            v = v.replace(/"/g, '""');
            if (/[",\n]/.test(v)) {
                v = '"' + v + '"';
                                        }
            return v;
        }).join(',');
        const head = keys.map(function (k) { return columnLabels[k] || k; }).join(',');
        const blob = new Blob(['\ufeff' + head + '\n' + line], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'kyc-customer-' + id + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    };

    window.changePerPage = function (v) {
        currentPerPage = parseInt(v, 10) || 25;
        currentPage = 1;
        const u = new URL(window.location.href);
        u.searchParams.set('per_page', String(currentPerPage));
        u.searchParams.set('page', '1');
        window.history.replaceState({}, '', u);
        loadData();
    };

    window.openFilterModal = function () {
        document.getElementById('filterModal').classList.add('active');
    };
    window.closeFilterModal = function () {
        document.getElementById('filterModal').classList.remove('active');
    };

    window.kycOpenColumns = function () {
        window.openColumnsModal();
    };

    function kycEscapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function kycFmtLedgerDate(s) {
        if (s == null || String(s).trim() === '') {
            return '—';
        }
        var p = String(s).slice(0, 10);
        var m = p.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (m) {
            return m[3] + '/' + m[2] + '/' + m[1];
        }
        return String(s);
    }

    function kycLedgerFlagOn(v) {
        if (v === true || v === 1) {
            return true;
        }
        if (v === false || v === 0) {
            return false;
        }
        if (v == null || v === '') {
            return false;
        }
        if (typeof v === 'string') {
            var s = v.trim().toLowerCase();
            return s === '1' || s === 'yes' || s === 'true' || s === 'y' || s === 'on';
        }
        return false;
    }

    function renderKycLedgerModalBody(c, docs) {
        var body = document.getElementById('kycLedgerModalBody');
        if (!body) {
            return;
        }
        var docCount = Array.isArray(docs) ? docs.length : 0;
        var rowsHtml = '';
        if (!docCount) {
            rowsHtml = '<tr><td colspan="5" class="kyc-doc-empty">No Rows To Show</td></tr>';
        } else {
            docs.forEach(function (d) {
                var path = (d && d.path) ? String(d.path) : '';
                var href = path ? kycEscapeHtml(path) : '#';
                rowsHtml += '<tr>' +
                    '<td>' + kycEscapeHtml(d.document_type || '') + '</td>' +
                    '<td>' + kycEscapeHtml(d.name || '') + '</td>' +
                    '<td>' + kycEscapeHtml(kycFmtLedgerDate(d.issue_date)) + '</td>' +
                    '<td>' + kycEscapeHtml(kycFmtLedgerDate(d.expiry_date)) + '</td>' +
                    '<td class="kyc-doc-act">' +
                    (path
                        ? '<a href="' + href + '" target="_blank" rel="noopener" class="kyc-doc-link" title="Open document"><i class="feather icon-external-link"></i></a>'
                        : '—') +
                    '</td>' +
                    '</tr>';
            });
        }

        var kycOn = kycLedgerFlagOn(c.kyc);
        var amlOn = kycLedgerFlagOn(c.aml);
        var kycChk = kycOn ? ' checked' : '';
        var amlChk = amlOn ? ' checked' : '';
        var customerId = c.id != null ? parseInt(String(c.id), 10) : 0;

        body.innerHTML =
            '<h3 class="kyc-ledger-name">' + kycEscapeHtml(c.name || '') + '</h3>' +
            '<div class="kyc-ledger-grid">' +
            '<div><label>Contact</label><p class="kyc-ledger-val">' + kycEscapeHtml(c.contact || '') + '</p></div>' +
            '<div><label>KYC verification date</label><p class="kyc-ledger-val">' + kycEscapeHtml(kycFmtLedgerDate(c.kyc_verification_date)) + '</p></div>' +
            '<div><label>Email</label><p class="kyc-ledger-val">' + kycEscapeHtml(c.email || '') + '</p></div>' +
            '<div><label>Address</label><p class="kyc-ledger-val">' + kycEscapeHtml(c.address || '') + '</p></div>' +
            '<div><label>DOB</label><p class="kyc-ledger-val">' + kycEscapeHtml(kycFmtLedgerDate(c.dob)) + '</p></div>' +
            '</div>' +
            '<div class="kyc-ledger-flags" data-customer-id="' + (customerId > 0 ? customerId : '') + '">' +
            '<label class="kyc-ledger-flag-item"><input type="checkbox" id="kycLedgerFlagKyc" data-flag="kyc"' + kycChk + '><span class="kyc-ledger-flag-meta"><span>KYC</span><span class="kyc-ledger-flag-yesno' + (kycOn ? ' is-on' : ' is-off') + '">(' + (kycOn ? 'Yes' : 'No') + ')</span></span></label>' +
            '<label class="kyc-ledger-flag-item"><input type="checkbox" id="kycLedgerFlagAml" data-flag="aml"' + amlChk + '><span class="kyc-ledger-flag-meta"><span>AML</span><span class="kyc-ledger-flag-yesno' + (amlOn ? ' is-on' : ' is-off') + '">(' + (amlOn ? 'Yes' : 'No') + ')</span></span></label>' +
            '</div>' +
            '<h4 class="kyc-ledger-docs-head">Document List (' + docCount + ')</h4>' +
            '<div class="kyc-ledger-doc-table-wrap">' +
            '<table class="kyc-ledger-doc-table">' +
            '<thead><tr>' +
            '<th>Document Types</th><th>Document Name</th><th>Issue Date</th><th>Expiry Date</th><th class="kyc-doc-act"> </th>' +
            '</tr></thead><tbody>' + rowsHtml + '</tbody></table></div>';

        if (window.feather && typeof feather.replace === 'function') {
            try {
                feather.replace({ elements: [body] });
            } catch (e) {}
        }

        bindKycLedgerFlagCheckboxes(customerId);
    }

    function updateKycLedgerFlagYesNo(input) {
        if (!input) return;
        var label = input.closest('.kyc-ledger-flag-item');
        var yesNo = label ? label.querySelector('.kyc-ledger-flag-yesno') : null;
        if (!yesNo) return;
        var on = !!input.checked;
        yesNo.textContent = '(' + (on ? 'Yes' : 'No') + ')';
        yesNo.classList.toggle('is-on', on);
        yesNo.classList.toggle('is-off', !on);
    }

    function syncKycReportRowFlag(customerId, field, value) {
        if (!(customerId > 0) || !field) return;
        var tbody = document.getElementById('kycTableBody');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr[data-id="' + customerId + '"], tr[data-customer-id="' + customerId + '"]');
        if (!rows.length && typeof window.kycRowsCache === 'object' && window.kycRowsCache) {
            /* update in-memory row if present */
            Object.keys(window.kycRowsCache).forEach(function (k) {
                var r = window.kycRowsCache[k];
                if (r && String(r.id) === String(customerId)) {
                    r[field] = value ? '1' : '0';
                }
            });
        }
        Array.prototype.forEach.call(rows, function (tr) {
            var cell = tr.querySelector('td[data-key="' + field + '"]');
            if (cell) {
                cell.textContent = value ? '1' : '0';
            }
        });
    }

    function bindKycLedgerFlagCheckboxes(customerId) {
        var body = document.getElementById('kycLedgerModalBody');
        if (!body || !(customerId > 0)) return;
        var inputs = body.querySelectorAll('.kyc-ledger-flag-item input[type="checkbox"][data-flag]');
        Array.prototype.forEach.call(inputs, function (input) {
            input.addEventListener('change', function () {
                var field = String(input.getAttribute('data-flag') || '');
                if (field !== 'kyc' && field !== 'aml') return;
                var nextVal = input.checked ? 1 : 0;
                var label = input.closest('.kyc-ledger-flag-item');
                updateKycLedgerFlagYesNo(input);
                input.disabled = true;
                if (label) label.classList.add('is-saving');

                var fd = new FormData();
                fd.append('customer_id', String(customerId));
                fd.append('field', field);
                fd.append('value', String(nextVal));

                fetch('ajax/update-kyc-aml-flags.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.status !== 'success') {
                            input.checked = !nextVal;
                            updateKycLedgerFlagYesNo(input);
                            alert((res && res.message) ? res.message : 'Failed to update');
                            return;
                        }
                        syncKycReportRowFlag(customerId, field, nextVal);
                    })
                    .catch(function () {
                        input.checked = !nextVal;
                        updateKycLedgerFlagYesNo(input);
                        alert('Failed to update');
                    })
                    .finally(function () {
                        input.disabled = false;
                        if (label) label.classList.remove('is-saving');
                    });
            });
        });
    }

    window.kycOpenLedgerDetails = function (customerId) {
        var modal = document.getElementById('kycLedgerModal');
        var body = document.getElementById('kycLedgerModalBody');
        if (!modal || !body) {
            return;
        }
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        modal.dataset.customerId = String(customerId || '');
        body.innerHTML = '<div class="kyc-ledger-loading">Loading…</div>';
        fetch('ajax/get-kyc-ledger-details.php?customer_id=' + encodeURIComponent(customerId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.status !== 'success' || !data.customer) {
                    body.innerHTML = '<p class="kyc-ledger-error">' + kycEscapeHtml((data && data.message) ? data.message : 'Failed to load') + '</p>';
                    return;
                }
                renderKycLedgerModalBody(data.customer, data.documents || []);
            })
            .catch(function () {
                body.innerHTML = '<p class="kyc-ledger-error">Error loading details</p>';
            });
    };

    window.kycCloseLedgerDetailsModal = function () {
        var m = document.getElementById('kycLedgerModal');
        if (m) {
            m.classList.remove('active');
            m.setAttribute('aria-hidden', 'true');
        }
    };

    let dragKey = null;
    window.openColumnsModal = function () {
        const modal = document.getElementById('columnsModal');
        const list = document.getElementById('columnsList');
        list.innerHTML = '';
        columnOrder.forEach(function (key) {
            if (!columnLabels[key]) {
                return;
            }
            const div = document.createElement('div');
            div.className = 'kyc-column-item';
            div.draggable = true;
            div.dataset.key = key;
            const vis = visibility[key] !== false;
            div.innerHTML =
                '<span class="kyc-drag-handle"><i class="feather icon-more-vertical"></i></span>' +
                '<input type="checkbox" id="col_' + key + '"' + (vis ? ' checked' : '') + '>' +
                '<label for="col_' + key + '" style="margin:0;flex:1;cursor:pointer;">' + (columnLabels[key] || key) + '</label>';
            const cb = div.querySelector('input');
            cb.addEventListener('change', function () {
                visibility[key] = cb.checked;
                savePrefs(true);
            });
            div.addEventListener('dragstart', function (e) {
                dragKey = key;
                div.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            div.addEventListener('dragend', function () {
                div.classList.remove('dragging');
                dragKey = null;
            });
            div.addEventListener('dragover', function (e) {
                e.preventDefault();
            });
            div.addEventListener('drop', function (e) {
                e.preventDefault();
                const from = dragKey;
                const to = key;
                if (!from || from === to) {
                    return;
                }
                const ia = columnOrder.indexOf(from);
                const ib = columnOrder.indexOf(to);
                if (ia < 0 || ib < 0) {
                    return;
                }
                columnOrder.splice(ia, 1);
                const newTo = ia < ib ? ib - 1 : ib;
                columnOrder.splice(newTo, 0, from);
                savePrefs(true);
            });
            list.appendChild(div);
        });
        document.getElementById('columnSearch').value = '';
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
    };

    window.closeColumnsModal = function () {
        const m = document.getElementById('columnsModal');
        m.classList.remove('active');
        m.setAttribute('aria-hidden', 'true');
    };

    window.resetColumns = function () {
        Object.keys(columnLabels).forEach(function (k) {
            visibility[k] = true;
        });
        const def = <?php echo json_encode($default_order, JSON_UNESCAPED_UNICODE); ?>;
        columnOrder.length = 0;
        def.forEach(function (k) { columnOrder.push(k); });
        Object.keys(widths).forEach(function (k) { delete widths[k]; });
        savePrefs(true);
    };

    document.getElementById('columnSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#columnsList .kyc-column-item').forEach(function (el) {
            const lab = el.querySelector('label');
            const t = lab ? lab.textContent.toLowerCase() : '';
            el.style.display = t.indexOf(q) >= 0 ? 'flex' : 'none';
        });
    });

    const columnsBackdrop = document.getElementById('columnsModalBackdrop');
    if (columnsBackdrop) {
        columnsBackdrop.addEventListener('click', function () {
            closeColumnsModal();
        });
    }

    document.getElementById('filterModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeFilterModal();
        }
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('.kyc-export-dd .dropdown-menu.show').forEach(function (m) {
            m.classList.remove('show');
        });
    });

    function getExportColumnsForDownload() {
        return orderedKeys().filter(function (k) {
            return k !== 'action' && visibility[k] !== false;
        });
    }

    window.exportToExcel = function () {
        const cols = getExportColumnsForDownload();
        if (!cols.length) {
            alert('No columns to export. Turn on at least one data column in column settings (Action is not exported).');
            return;
        }
        const u = new URL(window.location.href);
        const payload = {
            columns: cols,
            search: u.searchParams.get('search') || '',
            customer_type_id: parseInt(u.searchParams.get('customer_type_id') || '0', 10) || 0,
            country_id: parseInt(u.searchParams.get('country_id') || '0', 10) || 0,
            nationality_id: parseInt(u.searchParams.get('nationality_id') || '0', 10) || 0,
            has_aml: u.searchParams.get('has_aml') || '',
            sort: sortColumn || 'name',
            order: sortOrder || 'asc'
        };
        const basePath = window.location.pathname.replace(/[^/]+$/, '');
        const url = basePath + 'ajax/export-kyc-report-excel.php';

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) {
                const ct = (r.headers.get('Content-Type') || '').toLowerCase();
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                if (ct.indexOf('html') !== -1) {
                    throw new Error('login');
                }
                let fname = 'Customer_KYC_Report_' + new Date().toISOString().slice(0, 10).replace(/-/g, '_') + '.xlsx';
                const cd = r.headers.get('Content-Disposition');
                if (cd) {
                    const star = /filename\*=UTF-8''([^;]+)/i.exec(cd);
                    const plain = /filename="([^"]+)"/i.exec(cd);
                    const raw = star ? star[1] : (plain ? plain[1] : '');
                    if (raw) {
                        try {
                            fname = decodeURIComponent(raw.trim());
                        } catch (e) {
                            fname = raw.trim();
                        }
                    }
                }
                return r.blob().then(function (blob) {
                    return { blob: blob, fname: fname };
                });
            })
            .then(function (o) {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(o.blob);
                let name = o.fname || 'Customer_KYC_Report.xlsx';
                if (name.toLowerCase().indexOf('.xlsx') === -1) {
                    name += '.xlsx';
                }
                a.download = name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(a.href);
            })
            .catch(function () {
                alert('Could not download the Excel file. Please refresh the page and try again (make sure you are logged in).');
            });
    };

    window.exportToPdf = function () {
        window.print();
    };

    document.addEventListener('DOMContentLoaded', function () {
        buildHeaderRow();
        loadData();
        var lb = document.getElementById('kycLedgerModalBackdrop');
        if (lb) {
            lb.addEventListener('click', function () {
                window.kycCloseLedgerDetailsModal();
            });
        }
    });
})();
</script>
