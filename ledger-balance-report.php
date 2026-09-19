<?php 
session_start();
require_once 'config.php';

// Get filters
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$date_range = isset($_GET['date_range']) ? esc($_GET['date_range']) : '';
$from_date = isset($_GET['from_date']) ? esc($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? esc($_GET['to_date']) : '';

// Parse date range if provided
if (!empty($date_range)) {
    $dates = explode(' - ', $date_range);
    if (count($dates) == 2) {
        $from_date = trim($dates[0]);
        $to_date = trim($dates[1]);
    }
} else {
    // Default to current financial year (April to March)
    $current_year = date('Y');
    $current_month = date('m');
    if ($current_month >= 4) {
        $from_date = $current_year . '-04-01';
        $to_date = ($current_year + 1) . '-03-31';
    } else {
        $from_date = ($current_year - 1) . '-04-01';
        $to_date = $current_year . '-03-31';
    }
}

// Format dates for display
$from_date_display = !empty($from_date) ? date('d-m-Y', strtotime($from_date)) : '';
$to_date_display = !empty($to_date) ? date('d-m-Y', strtotime($to_date)) : '';
$date_range_display = $from_date_display . ' - ' . $to_date_display;

// Pagination (defaults; actual page size resolved in AJAX)
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$raw_per_page = isset($_GET['per_page']) ? trim((string) $_GET['per_page']) : '10';
if ($raw_per_page === 'all' || $raw_per_page === '-1') {
    $lbr_ui_per_page = 'all';
} else {
    $lbr_ui_per_page = (string) max(1, (int) $raw_per_page);
}

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';

$lbl_ledger_bal = function_exists('auragold_t') ? auragold_t('rep.ledger_balance') : 'Ledger Balance Report';
?>

<div class="layout-container ledger-balance-report-page">
    <div class="main-content">
        <div class="page-container">
            <h1 class="sr-only"><?php echo htmlspecialchars((string) $lbl_ledger_bal, ENT_QUOTES, 'UTF-8'); ?></h1>
            <!-- Page Header -->
            <div class="page-header-bar">
                <div class="header-left">
                    <div class="search-box">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search" value="<?php echo htmlspecialchars((string) $search, ENT_QUOTES, 'UTF-8'); ?>" onkeyup="handleSearch(event)">
                        <i class="feather icon-search search-icon"></i>
                    </div>
                    <label class="lbr-customers-only">
                        <input type="checkbox" id="customersOnly" checked /> Customers only
                    </label>
                </div>
                <div class="header-right">
                    <span class="lbr-page-title"><?php echo htmlspecialchars((string) $lbl_ledger_bal, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="date-range-display" onclick="openDatePicker()">
                        <i class="feather icon-calendar"></i>
                        <span id="dateRangeText"><?php echo htmlspecialchars((string) $date_range_display, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <button type="button" class="btn-icon" onclick="loadLedgerBalanceData()" title="Refresh">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <button type="button" class="btn-icon" onclick="shareViaChat()" title="Chat">
                        <i class="feather icon-message-circle"></i>
                    </button>
                    <button type="button" class="btn-icon" onclick="shareViaEmail()" title="Email">
                        <i class="feather icon-mail"></i>
                    </button>
                    <button type="button" class="btn-icon" onclick="shareViaWhatsApp()" title="WhatsApp">
                        <i class="feather icon-message-square"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn-primary" onclick="event.stopPropagation(); const m = this.nextElementSibling; m.classList.toggle('show');">
                            <i class="feather icon-download"></i> Export
                            <i class="feather icon-chevron-down" style="margin-left: 4px;"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); exportToExcel()">Export to Excel</a>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); exportToPDF()">Export to PDF</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="ledgerBalanceTable" class="table">
                            <thead>
                                <tr>
                                    <th class="lbr-th-fixed" data-col="select" data-lbr-min="40" style="width: 44px; min-width: 40px;">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        <span class="lbr-col-resizer" aria-hidden="true"></span>
                                    </th>
                                    <th class="lbr-th-reorder" data-col="ledger" data-lbr-min="120" style="min-width: 120px;">
                                        <span class="lbr-th-label">Ledger</span>
                                        <span class="lbr-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span>
                                        <span class="lbr-col-resizer" aria-hidden="true"></span>
                                    </th>
                                    <th class="lbr-th-reorder" data-col="ledger_type" data-lbr-min="100" style="min-width: 100px;">
                                        <span class="lbr-th-label">Ledger Type</span>
                                        <span class="lbr-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span>
                                        <span class="lbr-col-resizer" aria-hidden="true"></span>
                                    </th>
                                    <th class="lbr-th-reorder" data-col="balance_amount" data-lbr-min="110" style="min-width: 110px; text-align: right;">
                                        <span class="lbr-th-label">Balance Amount</span>
                                        <span class="lbr-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span>
                                        <span class="lbr-col-resizer" aria-hidden="true"></span>
                                    </th>
                                    <th class="lbr-th-reorder" data-col="balance_wt" data-lbr-min="108" style="min-width: 108px; text-align: right;">
                                        <span class="lbr-th-label">Balance Wt</span>
                                        <button type="button" class="th-gear-btn" onclick="event.stopPropagation(); openColumnsModal();" title="Column layout" aria-label="Column layout"><i class="feather icon-settings"></i></button>
                                        <span class="lbr-th-drag" title="Drag to reorder column"><i class="feather icon-move" aria-hidden="true"></i></span>
                                        <span class="lbr-col-resizer" aria-hidden="true"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <div style="color: #64748b;">Loading data...</div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="tableFooter" style="display: none;">
                                <tr class="total-row">
                                    <td data-col="select"></td>
                                    <td data-col="ledger" style="font-weight: 600; text-align: right;">Total</td>
                                    <td data-col="ledger_type"></td>
                                    <td id="totalBalanceAmount" data-col="balance_amount" style="text-align: right; font-weight: 600;"></td>
                                    <td id="totalBalance" data-col="balance_wt" style="text-align: right; font-weight: 600;"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="pagination-container">
                        <div class="pagination-left">
                            <span id="paginationInfo" style="font-size: 11px; color: #64748b;">Showing 0 to 0 of 0 entries</span>
                            <label class="per-page-wrap">
                                <span>Show</span>
                                <select id="perPageSelect" class="per-page-select" onchange="onPerPageChange()">
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="all">Show All Items</option>
                                </select>
                                <span>items</span>
                            </label>
                        </div>
                        <div class="pagination" id="pagination">
                            <!-- Pagination will be generated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Column layout help / reset -->
<div id="lbrColumnsModal" class="filter-modal">
    <div class="filter-modal-content" style="max-width: 440px;">
        <div class="filter-modal-header">
            <h5>Column layout</h5>
            <button type="button" class="filter-modal-close" onclick="closeLbrColumnsModal()">&times;</button>
        </div>
        <div class="filter-modal-body">
            <p style="margin: 0 0 12px; font-size: 12px; color: #475569; line-height: 1.5;">
                Reorder columns by dragging the <i class="feather icon-move" style="vertical-align: middle;"></i> icon on each header.
                Resize a column by dragging the right edge of its header. Layout is saved in this browser.
            </p>
            <div class="filter-modal-footer" style="margin-top: 0; padding-top: 0; border-top: none;">
                <button type="button" class="btn-apply" onclick="lbrResetColumnLayout(); closeLbrColumnsModal();">Reset to default</button>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Picker Modal -->
<div id="datePickerModal" class="filter-modal">
    <div class="filter-modal-content" style="max-width: 500px;">
        <div class="filter-modal-header">
            <h5>Select Date Range</h5>
            <button class="filter-modal-close" onclick="closeDatePicker()">&times;</button>
        </div>
        <div class="filter-modal-body">
            <div class="filter-form-group">
                <label>From Date</label>
                <input type="date" id="fromDate" value="<?php echo $from_date; ?>" class="form-control">
            </div>
            <div class="filter-form-group" style="margin-top: 10px;">
                <label>To Date</label>
                <input type="date" id="toDate" value="<?php echo $to_date; ?>" class="form-control">
            </div>
            <div class="filter-modal-footer">
                <button type="button" class="btn-apply" onclick="applyDateRange()">Apply</button>
                <button type="button" class="btn-clear" onclick="closeDatePicker()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>
<script src="assets/libs/sortablejs/sortable.js"></script>

<style>
/* Layout Container */
.layout-container {
    padding: 20px;
    width: 100%;
    box-sizing: border-box;
    background: #f4f6fb;
    min-height: calc(100vh - 60px);
}

.main-content {
    width: 100%;
    max-width: 100%;
}

.page-container {
    width: 100%;
    max-width: 100%;
    padding: 0;
    background: #f4f6fb;
}

.page-header-bar {
    background: #fff;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.search-box {
    position: relative;
    width: 300px;
}

.search-input {
    width: 100%;
    padding: 8px 35px 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11px;
    height: 36px;
}

.search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.header-right {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: nowrap;
}

.date-range-display {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    background: #f8fafc;
    cursor: pointer;
    font-size: 11px;
    color: #334155;
    transition: all 0.2s;
}

.date-range-display:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.btn-icon {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 11px;
    height: 36px;
    width: 36px;
}

.btn-icon:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.btn-primary {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 6px;
    height: 36px;
}

.btn-primary:hover {
    background: #4a2d6c;
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-top: 5px;
    min-width: 150px;
    z-index: 100;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    color: #1e293b;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-item:hover {
    background: #f8fafc;
    color: #1e293b;
}

.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 10px;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
}

.card-body {
    padding: 15px;
}

.table-responsive {
    overflow-x: auto;
    overflow-y: auto;
    width: 100%;
    max-height: calc(100vh - 350px);
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table th {
    background: #f8fafc;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    font-size: 12px;
    white-space: nowrap;
}

.table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 12px;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.table tbody tr.total-row {
    font-weight: 600;
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
}

.table tbody tr.total-row td {
    color: #1e293b;
}

.table tfoot tr.total-row {
    font-weight: 600;
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
}

.table tfoot tr.total-row td {
    color: #1e293b;
}

#ledgerBalanceTable {
    table-layout: fixed;
    width: 100%;
}

#ledgerBalanceTable thead th {
    position: relative;
    vertical-align: middle;
    user-select: none;
    box-sizing: border-box;
}

#ledgerBalanceTable thead th.lbr-th-reorder {
    padding-right: 16px;
}

#ledgerBalanceTable thead th .lbr-th-drag {
    display: inline-flex;
    align-items: center;
    margin-left: 6px;
    cursor: grab;
    color: #a68a4a;
    line-height: 0;
    vertical-align: middle;
}

#ledgerBalanceTable thead th .lbr-th-drag .feather {
    width: 15px;
    height: 15px;
}

#ledgerBalanceTable thead th .lbr-th-drag:active {
    cursor: grabbing;
}

#ledgerBalanceTable thead th .lbr-col-resizer {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    cursor: col-resize;
    z-index: 3;
    background: linear-gradient(90deg, transparent, rgba(15, 23, 42, 0.06));
}

#ledgerBalanceTable thead th .lbr-col-resizer:hover {
    background: rgba(15, 23, 42, 0.12);
}

#ledgerBalanceTable thead th.lbr-sortable-ghost {
    opacity: 0.45;
}

#ledgerBalanceTable thead th.lbr-sortable-chosen {
    background: #eef2f6 !important;
}

#ledgerBalanceTable thead th.lbr-col-resizing {
    user-select: none;
}

.negative-balance {
    color: #ef4444;
    font-weight: 600;
}

.pagination-container {
    padding: 12px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-left {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
}

.per-page-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #64748b;
}

.per-page-select {
    height: 28px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11px;
    min-width: 128px;
    background: #fff;
}

.lbr-customers-only {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #334155;
    white-space: nowrap;
    margin: 0;
}

.lbr-page-title {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    margin-right: 6px;
    white-space: nowrap;
}

.th-gear-btn {
    background: none;
    border: none;
    padding: 2px 4px;
    margin-left: 4px;
    cursor: pointer;
    color: #64748b;
    vertical-align: middle;
}

.th-gear-btn:hover {
    color: #334155;
}

.pagination {
    display: flex;
    gap: 4px;
    align-items: center;
}

.pagination .page-link {
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    color: #334155;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 11px;
}

.pagination .page-link:hover:not(.disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination .page-link.active {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.filter-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.filter-modal.active {
    display: flex;
}

.filter-modal-content {
    background: #fff;
    border-radius: 6px;
    padding: 0;
    width: 90%;
    max-width: 600px;
    max-height: 85vh;
    overflow: auto;
}

.filter-modal-header {
    background: #11294b;
    color: #fff;
    padding: 12px 15px;
    border-radius: 6px 6px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filter-modal-header h5 {
    margin: 0;
    color: #fff;
    font-weight: 600;
    font-size: 12px;
}

.filter-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #fff;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-modal-close:hover {
    color: #f0f0f0;
}

.filter-modal-body {
    padding: 15px;
}

.filter-form-group {
    display: flex;
    flex-direction: column;
}

.filter-form-group label {
    margin-bottom: 6px;
    font-weight: 500;
    color: #334155;
    font-size: 12px;
}

.filter-form-group input,
.filter-form-group select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11px;
    width: 100%;
    height: 38px;
}

.filter-modal-footer {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.btn-apply {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
    font-size: 11px;
}

.btn-apply:hover {
    background: #4a2d6c;
}

.btn-clear {
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
    font-size: 11px;
}

.btn-clear:hover {
    background: #dc2626;
}
</style>

<script>
let currentPage = <?php echo (int) $page; ?>;
let currentPerPage = <?php echo json_encode($lbr_ui_per_page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
let currentSearch = <?php echo json_encode((string) $search, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
let currentFromDate = <?php echo json_encode((string) $from_date); ?>;
let currentToDate = <?php echo json_encode((string) $to_date); ?>;

var LBR_TABLE_COL_KEYS = ['select', 'ledger', 'ledger_type', 'balance_amount', 'balance_wt'];
var LBR_ORDER_KEY = 'auragold_ledger_balance_report_col_order';
var LBR_WIDTHS_KEY = 'auragold_ledger_balance_report_col_widths';
var lbrColumnSortableInstance = null;

function lbrGetLedgerTable() {
    return document.getElementById('ledgerBalanceTable');
}

function lbrGetOrderFromThead() {
    var table = lbrGetLedgerTable();
    if (!table) return [];
    return [].map.call(table.querySelectorAll('thead th[data-col]'), function (th) {
        return th.getAttribute('data-col');
    });
}

function lbrSyncBodyColumnOrder(table, order) {
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.cells.length === 1) return;
        var byCol = {};
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            byCol[td.getAttribute('data-col')] = td;
        });
        order.forEach(function (k) {
            if (byCol[k]) tr.appendChild(byCol[k]);
        });
    });
}

function lbrSyncFooterColumnOrder(table, order) {
    var footRow = table.querySelector('tfoot tr');
    if (!footRow || footRow.cells.length === 1) return;
    var byCol = {};
    footRow.querySelectorAll('td[data-col]').forEach(function (td) {
        byCol[td.getAttribute('data-col')] = td;
    });
    order.forEach(function (k) {
        if (byCol[k]) footRow.appendChild(byCol[k]);
    });
}

function lbrApplyOrderArray(order) {
    var table = lbrGetLedgerTable();
    if (!table || !order || order.length !== LBR_TABLE_COL_KEYS.length) return;
    var need = {};
    LBR_TABLE_COL_KEYS.forEach(function (k) { need[k] = 0; });
    order.forEach(function (k) {
        if (Object.prototype.hasOwnProperty.call(need, k)) need[k]++;
    });
    if (!LBR_TABLE_COL_KEYS.every(function (k) { return need[k] === 1; })) return;
    if (order[0] !== 'select') return;
    var theadRow = table.querySelector('thead tr');
    if (!theadRow) return;
    var thByCol = {};
    theadRow.querySelectorAll('th[data-col]').forEach(function (th) {
        thByCol[th.getAttribute('data-col')] = th;
    });
    order.forEach(function (k) {
        if (thByCol[k]) theadRow.appendChild(thByCol[k]);
    });
    lbrSyncBodyColumnOrder(table, order);
    lbrSyncFooterColumnOrder(table, order);
}

function lbrSyncDataRowsToTheadOrder() {
    var table = lbrGetLedgerTable();
    if (!table) return;
    var ord = lbrGetOrderFromThead();
    if (ord.length !== LBR_TABLE_COL_KEYS.length) return;
    lbrSyncBodyColumnOrder(table, ord);
    lbrSyncFooterColumnOrder(table, ord);
}

function lbrTryLoadOrder() {
    try {
        var j = localStorage.getItem(LBR_ORDER_KEY);
        if (!j) return;
        lbrApplyOrderArray(JSON.parse(j));
    } catch (e) {}
}

function lbrSaveOrder() {
    try {
        localStorage.setItem(LBR_ORDER_KEY, JSON.stringify(lbrGetOrderFromThead()));
    } catch (e) {}
}

function lbrThMinWidthFloor(th) {
    var a = th.getAttribute('data-lbr-min');
    if (a != null && a !== '') {
        var f = parseInt(a, 10);
        if (!isNaN(f) && f >= 40) return f;
    }
    return 40;
}

function lbrApplyWidths(table, w) {
    if (!w || typeof w !== 'object' || !table) return;
    table.querySelectorAll('thead th[data-col]').forEach(function (th) {
        var k = th.getAttribute('data-col');
        if (k && w[k] != null) {
            var floor = lbrThMinWidthFloor(th);
            var px = Math.max(floor, parseInt(w[k], 10) || 0);
            th.style.width = px + 'px';
            th.style.minWidth = px + 'px';
        }
    });
}

function lbrTryLoadWidths() {
    var table = lbrGetLedgerTable();
    if (!table) return;
    try {
        var j = localStorage.getItem(LBR_WIDTHS_KEY);
        if (j) lbrApplyWidths(table, JSON.parse(j));
    } catch (e) {}
}

function lbrSaveWidths() {
    var table = lbrGetLedgerTable();
    if (!table) return;
    var w = {};
    table.querySelectorAll('thead th[data-col]').forEach(function (th) {
        var k = th.getAttribute('data-col');
        if (k) w[k] = Math.round(th.getBoundingClientRect().width);
    });
    try {
        localStorage.setItem(LBR_WIDTHS_KEY, JSON.stringify(w));
    } catch (e) {}
}

function initLbrTableColumnsAndResize() {
    var table = lbrGetLedgerTable();
    if (!table) return;
    var theadRow = table.querySelector('thead tr');
    if (!theadRow) return;

    var lastGoodOrder = lbrGetOrderFromThead().slice();

    if (lbrColumnSortableInstance && typeof lbrColumnSortableInstance.destroy === 'function') {
        try { lbrColumnSortableInstance.destroy(); } catch (e2) {}
        lbrColumnSortableInstance = null;
    }

    lbrTryLoadOrder();
    lbrTryLoadWidths();
    lastGoodOrder = lbrGetOrderFromThead().slice();

    if (typeof Sortable !== 'undefined') {
        lbrColumnSortableInstance = Sortable.create(theadRow, {
            animation: 150,
            handle: '.lbr-th-drag',
            draggable: 'th.lbr-th-reorder',
            ghostClass: 'lbr-sortable-ghost',
            chosenClass: 'lbr-sortable-chosen',
            onEnd: function () {
                var ord = lbrGetOrderFromThead();
                if (ord[0] !== 'select') {
                    lbrApplyOrderArray(lastGoodOrder);
                    return;
                }
                lbrSyncBodyColumnOrder(table, ord);
                lbrSyncFooterColumnOrder(table, ord);
                lbrSaveOrder();
                lastGoodOrder = ord.slice();
            }
        });
    }

    table.querySelectorAll('thead th .lbr-col-resizer').forEach(function (handle) {
        if (handle.getAttribute('data-lbr-resize-bound') === '1') return;
        handle.setAttribute('data-lbr-resize-bound', '1');
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var th = handle.closest('th');
            if (!th) return;
            var startX = e.clientX;
            var startW = th.getBoundingClientRect().width;
            var minW = lbrThMinWidthFloor(th);
            function onMove(e2) {
                var dx = e2.clientX - startX;
                var nw = Math.max(minW, Math.round(startW + dx));
                th.style.width = nw + 'px';
                th.style.minWidth = nw + 'px';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.cursor = '';
                th.classList.remove('lbr-col-resizing');
                lbrSaveWidths();
            }
            th.classList.add('lbr-col-resizing');
            document.body.style.cursor = 'col-resize';
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    });
}

function lbrResetColumnLayout() {
    try {
        localStorage.removeItem(LBR_ORDER_KEY);
        localStorage.removeItem(LBR_WIDTHS_KEY);
    } catch (e) {}
    lbrApplyOrderArray(LBR_TABLE_COL_KEYS.slice());
    var table = lbrGetLedgerTable();
    if (!table) return;
    table.querySelectorAll('thead th[data-col]').forEach(function (th) {
        var k = th.getAttribute('data-col');
        var mn = th.getAttribute('data-lbr-min');
        th.style.width = '';
        th.style.minWidth = '';
        if (k === 'select') {
            th.style.width = '44px';
            th.style.minWidth = (mn || '40') + 'px';
        } else if (mn) {
            th.style.minWidth = mn + 'px';
        }
    });
    lbrSyncDataRowsToTheadOrder();
}

function syncPerPageSelectFromState() {
    const sel = document.getElementById('perPageSelect');
    if (!sel) return;
    const v = currentPerPage === 'all' ? 'all' : String(currentPerPage);
    if ([...sel.options].some(o => o.value === v)) {
        sel.value = v;
    }
}

$(document).ready(function() {
    syncPerPageSelectFromState();
    $('#customersOnly').on('change', function() {
        currentPage = 1;
        loadLedgerBalanceData();
    });
    initLbrTableColumnsAndResize();
    loadLedgerBalanceData();
});

function onPerPageChange() {
    const sel = document.getElementById('perPageSelect');
    currentPerPage = sel && sel.value === 'all' ? 'all' : (sel ? parseInt(sel.value, 10) : 10);
    if (!Number.isFinite(currentPerPage)) currentPerPage = 10;
    currentPage = 1;
    loadLedgerBalanceData();
}

function loadLedgerBalanceData() {
    const params = new URLSearchParams();
    params.set('page', currentPage);
    params.set('per_page', currentPerPage === 'all' ? 'all' : currentPerPage);
    if (currentSearch) params.set('search', currentSearch);
    if (currentFromDate) params.set('from_date', currentFromDate);
    if (currentToDate) params.set('to_date', currentToDate);
    params.set('customers_only', document.getElementById('customersOnly') && document.getElementById('customersOnly').checked ? '1' : '0');

    $('#tableBody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #64748b;">Loading data...</div></td></tr>');
    
    fetch('ajax/get-ledger-balance-report.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderTable(data.data);
                renderPagination(data.pagination);
                renderTotals(data.totals);
            } else {
                $('#tableBody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #ef4444;">Error: ' + (data.message || 'Failed to load data') + '</div></td></tr>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            $('#tableBody').html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #ef4444;">Error loading data</div></td></tr>');
        });
}

function renderTable(data) {
    const tbody = $('#tableBody');
    tbody.empty();
    
    if (!data || data.length === 0) {
        tbody.html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div style="color: #64748b;">No Rows To Show</div></td></tr>');
        $('#tableFooter').hide();
        return;
    }
    
    $('#tableFooter').show();
    
    data.forEach(row => {
        const tr = $('<tr>');
        tr.append($('<td>').attr('data-col', 'select').html('<input type="checkbox" class="row-checkbox" data-id="' + row.id + '">'));
        tr.append($('<td>').attr('data-col', 'ledger').text(row.ledger_name));
        tr.append($('<td>').attr('data-col', 'ledger_type').text(row.ledger_type));

        const balanceAmount = parseFloat(row.balance_amount) || 0;
        const balanceAmountCell = $('<td>').attr('data-col', 'balance_amount').text(formatNumber(balanceAmount)).css('text-align', 'right');
        if (balanceAmount < 0) {
            balanceAmountCell.addClass('negative-balance');
        }
        tr.append(balanceAmountCell);

        const balance = parseFloat(row.balance) || 0;
        tr.append($('<td>').attr('data-col', 'balance_wt').text(formatWeight(balance)).css('text-align', 'right'));

        tbody.append(tr);
    });

    lbrSyncDataRowsToTheadOrder();
}

function renderTotals(totals) {
    if (!totals) return;
    
    const totalBalanceAmount = parseFloat(totals.total_balance_amount) || 0;
    const totalBalanceAmountCell = $('#totalBalanceAmount');
    totalBalanceAmountCell.text(formatNumber(totalBalanceAmount));
    if (totalBalanceAmount < 0) {
        totalBalanceAmountCell.addClass('negative-balance');
    } else {
        totalBalanceAmountCell.removeClass('negative-balance');
    }
    
    const totalBalance = parseFloat(totals.total_balance) || 0;
    $('#totalBalance').text(formatWeight(totalBalance));
}

function renderPagination(pagination) {
    const paginationEl = $('#pagination');
    const infoEl = $('#paginationInfo');
    
    if (!pagination) {
        paginationEl.empty();
        infoEl.text('Showing 0 to 0 of 0 entries');
        return;
    }
    
    const { current_page, per_page, total, total_pages } = pagination;
    const start = total > 0 ? ((current_page - 1) * per_page) + 1 : 0;
    const end = Math.min(current_page * per_page, total);
    
    infoEl.text(`Showing ${start} to ${end} of ${total} entries`);
    
    paginationEl.empty();
    
    if (total_pages <= 1) return;
    
    // First page
    if (current_page > 1) {
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(1); return false;">&lt;&lt;</a>`);
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(${current_page - 1}); return false;">&lt;</a>`);
    }
    
    // Page numbers
    const startPage = Math.max(1, current_page - 2);
    const endPage = Math.min(total_pages, current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === current_page ? 'active' : '';
        paginationEl.append(`<a href="#" class="page-link ${activeClass}" onclick="goToPage(${i}); return false;">${i}</a>`);
    }
    
    // Last page
    if (current_page < total_pages) {
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(${current_page + 1}); return false;">&gt;</a>`);
        paginationEl.append(`<a href="#" class="page-link" onclick="goToPage(${total_pages}); return false;">&gt;&gt;</a>`);
    }
}

function goToPage(page) {
    currentPage = page;
    loadLedgerBalanceData();
}

function handleSearch(event) {
    if (event.key === 'Enter') {
        currentSearch = event.target.value;
        currentPage = 1;
        loadLedgerBalanceData();
    }
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
}

function openDatePicker() {
    document.getElementById('datePickerModal').classList.add('active');
}

function closeDatePicker() {
    document.getElementById('datePickerModal').classList.remove('active');
}

function applyDateRange() {
    currentFromDate = document.getElementById('fromDate').value;
    currentToDate = document.getElementById('toDate').value;
    
    if (currentFromDate && currentToDate) {
        const fromDateObj = new Date(currentFromDate);
        const toDateObj = new Date(currentToDate);
        const fromDateDisplay = fromDateObj.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const toDateDisplay = toDateObj.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
        document.getElementById('dateRangeText').textContent = fromDateDisplay + ' - ' + toDateDisplay;
    }
    
    currentPage = 1;
    loadLedgerBalanceData();
    closeDatePicker();
}

function shareViaChat() {
    alert('Chat share functionality will be implemented soon');
}

function shareViaEmail() {
    alert('Email share functionality will be implemented soon');
}

function shareViaWhatsApp() {
    alert('WhatsApp share functionality will be implemented soon');
}

function exportToExcel() {
    const params = new URLSearchParams();
    if (currentSearch) {
        params.set('search', currentSearch);
    }
    if (currentFromDate) {
        params.set('from_date', currentFromDate);
    }
    if (currentToDate) {
        params.set('to_date', currentToDate);
    }
    params.set('customers_only', document.getElementById('customersOnly') && document.getElementById('customersOnly').checked ? '1' : '0');
    window.location.href = 'ajax/export-ledger-balance-report-excel.php?' + params.toString();
}

function exportToPDF() {
    alert('PDF export functionality will be implemented soon');
}

function openColumnsModal() {
    document.getElementById('lbrColumnsModal').classList.add('active');
}

function closeLbrColumnsModal() {
    document.getElementById('lbrColumnsModal').classList.remove('active');
}

function formatNumber(num) {
    return parseFloat(num || 0).toFixed(2);
}

function formatWeight(wt) {
    return parseFloat(wt || 0).toFixed(3);
}

// Close modals when clicking outside
document.getElementById('datePickerModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDatePicker();
    }
});

document.getElementById('lbrColumnsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLbrColumnsModal();
    }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const menu = dropdown.querySelector('.dropdown-menu');
        if (menu) {
            menu.classList.remove('show');
        }
    });
});
</script>
