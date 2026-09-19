<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

$from_date = isset($_GET['from_date']) ? trim((string) esc($_GET['from_date'])) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? trim((string) esc($_GET['to_date'])) : date('Y-m-t');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = date('Y-m-t');
}

$lbl_title = function_exists('auragold_t') ? auragold_t('rep.reward_point') : 'Reward Point Report';

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container ageing-report-page reward-point-report-page">
    <div class="main-content">
        <div class="page-container">
            <h1 class="sr-only"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></h1>

            <div class="ageing-shell">
                <div class="ageing-shell-top">
                    <div class="ageing-tabs-row">
                        <div class="search-box-inline field-grow rpr-search-wrap">
                            <label class="sr-only" for="rprSearchInput"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.search'), ENT_QUOTES, 'UTF-8') : 'Search'; ?></label>
                            <input type="search" id="rprSearchInput" class="form-control-sm" placeholder="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.search_table'), ENT_QUOTES, 'UTF-8') : 'Search…'; ?>" autocomplete="off">
                            <i class="feather icon-search"></i>
                        </div>
                        <div class="toolbar-actions ageing-tabs-row__actions">
                            <button type="button" class="btn-icon-tight" id="rprBtnRefresh" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.refresh'), ENT_QUOTES, 'UTF-8') : 'Refresh'; ?>">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                            <button type="button" class="btn-icon-tight ageing-toolbar-gear" id="rprColGearBtn" title="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.column_settings'), ENT_QUOTES, 'UTF-8') : 'Column settings'; ?>" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.column_settings'), ENT_QUOTES, 'UTF-8') : 'Column settings'; ?>">
                                <i class="feather icon-settings"></i>
                            </button>
                            <div class="ageing-export-dd">
                                <button type="button" class="btn-ageing-primary" id="rprBtnExportToggle">
                                    <?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.export'), ENT_QUOTES, 'UTF-8') : 'Export'; ?>
                                    <i class="feather icon-chevron-down"></i>
                                </button>
                                <div class="ageing-export-menu" id="rprExportMenu" role="menu" hidden>
                                    <button type="button" class="ageing-export-item" id="rprExportXlsx" role="menuitem">
                                        <span class="ageing-export-ico ageing-export-ico--excel" aria-hidden="true"><i class="fas fa-file-excel"></i></span>
                                        <span class="ageing-export-txt">Excel (.xlsx)</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ageing-toolbar rpr-date-toolbar">
                    <div class="toolbar-inner">
                        <div class="field-group">
                            <label class="field-label" for="rprFromDate"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.aging_date'), ENT_QUOTES, 'UTF-8') : 'From'; ?></label>
                            <input type="date" id="rprFromDate" class="form-control-sm" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="rprToDate">To</label>
                            <input type="date" id="rprToDate" class="form-control-sm" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group rpr-title-group">
                            <span class="rpr-page-title"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="ageing-panel">
                    <div class="table-responsive ageing-table-wrap">
                        <table class="table ageing-table ageing-table--colmgr" id="rprTable">
                            <thead>
                                <tr id="rprHeadRow">
                                    <th class="ageing-col-head th-sort" data-col="customer_name" data-sort="customer_name" style="min-width: 160px;">
                                        <span class="ageing-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">Customer Name<span class="sort-icons" aria-hidden="true"></span></span>
                                        <span class="ageing-col-resizer" title="Resize"></span>
                                    </th>
                                    <th class="ageing-col-head th-sort" data-col="invoice_no" data-sort="invoice_no" style="min-width: 120px;">
                                        <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">Invoice No.<span class="sort-icons"></span></span>
                                        <span class="ageing-col-resizer"></span>
                                    </th>
                                    <th class="ageing-col-head th-sort" data-col="invoice_date" data-sort="invoice_date" style="min-width: 110px;">
                                        <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">Date<span class="sort-icons"></span></span>
                                        <span class="ageing-col-resizer"></span>
                                    </th>
                                    <th class="ageing-col-head th-sort th-num" data-col="generated_point" data-sort="generated_point" style="min-width: 120px;">
                                        <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">GeneratedPoint.<span class="sort-icons"></span></span>
                                        <span class="ageing-col-resizer"></span>
                                    </th>
                                    <th class="ageing-col-head th-sort th-num" data-col="redeemed_point" data-sort="redeemed_point" style="min-width: 120px;">
                                        <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">RedeemedPoint.<span class="sort-icons"></span></span>
                                        <span class="ageing-col-resizer"></span>
                                    </th>
                                    <th class="ageing-col-head th-sort th-num" data-col="redeem_value" data-sort="redeem_value" style="min-width: 112px;">
                                        <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">RedeemValue.<span class="sort-icons"></span></span>
                                        <span class="ageing-col-resizer"></span>
                                    </th>
                                    <th class="ageing-col-head th-sort" data-col="account_no" data-sort="account_no" style="min-width: 100px;">
                                        <span class="ageing-col-drag"><i class="feather icon-move"></i></span>
                                        <span class="ageing-col-head-inner">Account No.<span class="sort-icons"></span></span>
                                        <span class="ageing-col-resizer"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="rprBody">
                                <tr class="empty-row">
                                    <td colspan="7" class="empty-msg" id="rprEmptyMsgCell">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-container ageing-pagination">
                        <div>
                            <span id="rprPaginationInfo" class="pagination-info">Showing 0 to 0 of 0 entries</span>
                        </div>
                        <div class="pagination-right">
                            <label class="per-page-label">
                                <select id="rprPerPage" class="form-control-sm per-page-select">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="0">Show all</option>
                                </select>
                            </label>
                            <nav class="pagination ageing-pager" id="rprPager" aria-label="Pagination">
                                <button type="button" class="page-btn" disabled data-go="first" title="First"><i class="feather icon-chevrons-left"></i></button>
                                <button type="button" class="page-btn" disabled data-go="prev" title="Previous"><i class="feather icon-chevron-left"></i></button>
                                <button type="button" class="page-btn" disabled data-go="next" title="Next"><i class="feather icon-chevron-right"></i></button>
                                <button type="button" class="page-btn" disabled data-go="last" title="Last"><i class="feather icon-chevrons-right"></i></button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ageing-col-modal" id="rprColSettingsModal" hidden>
    <div class="ageing-col-modal__backdrop" id="rprColSettingsBackdrop"></div>
    <div class="ageing-col-modal__panel" role="dialog" aria-labelledby="rprColSettingsTitle">
        <div class="ageing-col-modal__head">
            <h2 class="ageing-col-modal__title" id="rprColSettingsTitle"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_settings_title'), ENT_QUOTES, 'UTF-8') : 'Show / hide columns'; ?></h2>
            <button type="button" class="ageing-col-modal__close" id="rprColSettingsCloseX" aria-label="<?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_close'), ENT_QUOTES, 'UTF-8') : 'Close'; ?>">&times;</button>
        </div>
        <p class="ageing-col-modal__hint"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.col_settings_hint'), ENT_QUOTES, 'UTF-8') : 'Tick columns to show. Drag the grip icon on a header to reorder. Drag the right edge of a header to resize.'; ?></p>
        <ul class="ageing-col-modal__list" id="rprColSettingsList"></ul>
        <div class="ageing-col-modal__actions">
            <button type="button" class="ageing-col-modal__btn ageing-col-modal__btn--secondary" id="rprColSettingsReset"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_reset'), ENT_QUOTES, 'UTF-8') : 'Reset'; ?></button>
            <button type="button" class="ageing-col-modal__btn ageing-col-modal__btn--primary" id="rprColSettingsCloseBtn"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('ageing.columns_close'), ENT_QUOTES, 'UTF-8') : 'Close'; ?></button>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<style>
/* Layout + table + modal (aligned with ageing-report.php so this page is self-contained) */
.ageing-report-page {
    --ageing-navy: #11294b;
    --ageing-navy-dark: #0c1d36;
    --ageing-gold: #c9a227;
    --ageing-gold-hover: #d4af37;
    --ageing-gold-muted: #f5eed9;
    --ageing-border: #c5cddf;
    --ageing-bg: #eef1f6;
    --ageing-total-row: #e8eef5;
}
.ageing-report-page.layout-container {
    padding: 10px clamp(6px, 0.9vw, 14px) 20px;
    width: 100%;
    max-width: 100vw;
    margin: 0;
    box-sizing: border-box;
    background: var(--ageing-bg);
    min-height: calc(100vh - 60px);
}
.ageing-report-page .main-content,
.ageing-report-page .page-container { width: 100%; max-width: 100%; margin: 0; padding: 0; }
.ageing-shell {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    background: #fff;
    border: 1px solid var(--ageing-border);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.07);
    overflow: hidden;
}
.ageing-shell-top { padding: 14px 18px 0; background: #fff; }
.ageing-tabs-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ageing-border);
    margin-bottom: 0;
}
.ageing-tabs-row .toolbar-actions { margin-left: auto; align-items: center; align-self: center; }
.toolbar-actions { display: flex; align-items: center; gap: 10px; }
.ageing-toolbar-gear .feather { width: 18px; height: 18px; stroke-width: 2.2px; color: var(--ageing-gold); }
.ageing-toolbar-gear:hover .feather { color: var(--ageing-navy); }
.btn-icon-tight {
    width: 38px; height: 38px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid #c9d4e3; border-radius: 8px; background: #fff; cursor: pointer;
    color: #475569;
}
.search-box-inline { position: relative; }
.search-box-inline input { width: 100%; padding-right: 34px; }
.search-box-inline .feather { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8; pointer-events: none; }
.ageing-toolbar { margin: 0; background: linear-gradient(180deg, #fbfcfe 0%, #f4f6fa 100%); border-bottom: 1px solid var(--ageing-border); }
.toolbar-inner { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px 22px; padding: 16px 18px 18px; }
.field-group { display: flex; flex-direction: column; gap: 5px; min-width: 140px; }
.field-label { font-size: 11px; font-weight: 700; color: #5c6b7a; margin: 0; text-transform: uppercase; letter-spacing: 0.04em; }
.form-control-sm {
    height: 38px; padding: 7px 12px; border: 1px solid #c9d4e3; border-radius: 8px;
    font-size: 12px; color: #1e293b; background: #fff; box-sizing: border-box;
}
.btn-ageing-primary {
    display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 18px;
    background: var(--ageing-navy); color: #fff; border: 2px solid var(--ageing-gold); border-radius: 8px;
    font-size: 12px; font-weight: 600; cursor: pointer;
}
.ageing-export-dd { position: relative; }
.ageing-export-menu { position: absolute; top: 100%; left: 0; margin-top: 6px; min-width: 196px; background: #fff;
    border: 1px solid var(--ageing-border); border-radius: 10px; box-shadow: 0 12px 32px rgba(17, 41, 75, 0.14); z-index: 1050; padding: 8px 0; }
.ageing-export-menu:not([hidden]) { display: block; }
.ageing-export-item {
    display: flex; align-items: center; gap: 12px; width: 100%; margin: 0; border: 0; background: transparent;
    padding: 11px 16px; font-size: 13px; font-weight: 500; color: #334155; cursor: pointer; text-align: left; font-family: inherit;
}
.ageing-table-wrap {
    display: block; max-height: calc(100vh - 300px); overflow: auto; min-width: 0; width: 100%; background: #fff;
}
.ageing-table { width: 100%; border-collapse: collapse; margin: 0; font-size: 12px; }
.ageing-table--colmgr { table-layout: fixed; }
.ageing-table thead th {
    position: sticky; top: 0; z-index: 2; background: var(--ageing-navy); padding: 11px 12px;
    text-align: left; font-weight: 600; color: #fff; border-bottom: none; white-space: nowrap;
}
.ageing-col-head { position: relative; padding-right: 14px; vertical-align: middle; }
.ageing-col-drag { display: inline-block; vertical-align: middle; margin-right: 5px; cursor: grab; user-select: none; touch-action: none; }
.ageing-table thead .ageing-col-drag .feather { width: 15px; height: 15px; color: var(--ageing-gold); }
.ageing-col-head-inner { vertical-align: middle; }
.ageing-col-dragging { opacity: 0.55; }
.ageing-col-drop-target { box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.75); }
.ageing-col-resizer { position: absolute; right: 0; top: 0; bottom: 0; width: 8px; cursor: col-resize; z-index: 3; }
.ageing-col-hidden { display: none !important; }
.ageing-table .th-num { text-align: right; }
.ageing-table tbody td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; color: #475569; background: #fff; }
.ageing-table tbody .empty-msg { text-align: center; color: #94a3b8; padding: 52px 16px !important; }
.pagination-container.ageing-pagination {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
    padding: 12px 16px; border-top: 1px solid var(--ageing-border); background: #fafbfc;
}
.pagination-right { display: flex; align-items: center; gap: 12px; }
.per-page-select { min-width: 72px; }
.page-btn { width: 34px; height: 34px; border: 1px solid #c9d4e3; background: #fff; border-radius: 6px; cursor: pointer; }
.page-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ageing-col-modal { position: fixed; inset: 0; z-index: 4000; display: flex; align-items: center; justify-content: center; padding: 16px; }
.ageing-col-modal[hidden] { display: none !important; }
.ageing-col-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
.ageing-col-modal__panel {
    position: relative; z-index: 1; width: 100%; max-width: 400px; max-height: 90vh; overflow: auto;
    background: #fff; border-radius: 12px; box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18); padding: 18px 20px 16px;
}
.ageing-col-modal__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.ageing-col-modal__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; }
.ageing-col-modal__close { border: 0; background: transparent; font-size: 1.5rem; line-height: 1; color: #64748b; cursor: pointer; }
.ageing-col-modal__hint { font-size: 12px; color: #64748b; margin: 0 0 12px; line-height: 1.45; }
.ageing-col-modal__list { list-style: none; margin: 0 0 14px; padding: 0; }
.ageing-col-modal__list li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.ageing-col-modal__actions { display: flex; justify-content: flex-end; gap: 10px; }
.ageing-col-modal__btn { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; }
.ageing-col-modal__btn--secondary { background: #f8fafc; color: #334155; }
.ageing-col-modal__btn--primary { background: var(--ageing-navy); color: #fff; border-color: var(--ageing-navy); }

.reward-point-report-page .field-grow { flex: 1; min-width: 200px; }
.reward-point-report-page .rpr-title-group { margin-left: auto; align-self: flex-end; }
.reward-point-report-page .rpr-page-title { font-size: 1.05rem; font-weight: 700; color: #4a2d6c; }
.reward-point-report-page .ageing-panel { min-width: 0; padding: 0 0 12px; }
.reward-point-report-page #rprTable tbody td[data-col="generated_point"],
.reward-point-report-page #rprTable tbody td[data-col="redeemed_point"],
.reward-point-report-page #rprTable tbody td[data-col="redeem_value"] { text-align: right; }
.reward-point-report-page .th-sort .sort-icons::before {
    content: '↕'; display: inline-block; margin-left: 4px; font-size: 11px; opacity: 0.9; vertical-align: middle;
}
.reward-point-report-page .th-sort.is-sorted-asc .sort-icons::before { content: '↑'; }
.reward-point-report-page .th-sort.is-sorted-desc .sort-icons::before { content: '↓'; }
</style>

<script>
(function () {
    var RPR_COL_STORAGE_KEY = 'auragold_rpr_cols_v1';
    var RPR_COL_DEFAULT_ORDER = ['customer_name', 'invoice_no', 'invoice_date', 'generated_point', 'redeemed_point', 'redeem_value', 'account_no'];

    var state = {
        page: 1,
        totalPages: 1,
        total: 0,
        sort: 'invoice_date',
        order: 'desc',
        loading: false,
        rows: []
    };

    function el(id) { return document.getElementById(id); }

    function formatDisplayDate(iso) {
        if (!iso) return '';
        var p = String(iso).split('-');
        if (p.length !== 3) return iso;
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function getSearchParams() {
        var q = new URLSearchParams();
        q.set('from_date', el('rprFromDate').value || '');
        q.set('to_date', el('rprToDate').value || '');
        q.set('search', el('rprSearchInput').value.trim());
        q.set('page', String(state.page));
        var per = el('rprPerPage').value;
        q.set('per_page', per === '0' ? 'all' : per);
        q.set('sort', state.sort);
        q.set('order', state.order);
        return q;
    }

    function setSortIcons() {
        document.querySelectorAll('#rprHeadRow th.th-sort').forEach(function (th) {
            var sk = th.getAttribute('data-sort');
            th.classList.remove('is-sorted-asc', 'is-sorted-desc');
            if (sk === state.sort) {
                th.classList.add(state.order === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            }
        });
    }

    function updatePager() {
        var per = parseInt(el('rprPerPage').value, 10) || 25;
        var start = state.total === 0 ? 0 : (state.page - 1) * (per === 0 ? state.total : per) + 1;
        var end = state.total === 0 ? 0 : Math.min(state.page * (per === 0 ? state.total : per), state.total);
        if (per === 0) { start = state.total === 0 ? 0 : 1; end = state.total; }
        el('rprPaginationInfo').textContent = 'Showing ' + start + ' to ' + end + ' of ' + state.total + ' entries';

        var nav = el('rprPager');
        if (!nav) return;
        var atFirst = state.page <= 1;
        var atLast = state.page >= state.totalPages;
        nav.querySelector('[data-go="first"]').disabled = atFirst;
        nav.querySelector('[data-go="prev"]').disabled = atFirst;
        nav.querySelector('[data-go="next"]').disabled = atLast || state.total === 0;
        nav.querySelector('[data-go="last"]').disabled = atLast || state.total === 0;
    }

    function renderBody(rows) {
        state.rows = rows || [];
        var tbody = el('rprBody');
        var order = [];
        el('rprHeadRow').querySelectorAll('th[data-col]:not(.ageing-col-hidden)').forEach(function (th) {
            order.push(th.getAttribute('data-col'));
        });
        var nc = order.length || 1;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="' + nc + '" class="empty-msg" id="rprEmptyMsgCell">No Rows To Show</td></tr>';
            return;
        }
        tbody.innerHTML = '';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            order.forEach(function (k) {
                var td = document.createElement('td');
                td.setAttribute('data-col', k);
                var v = '';
                if (k === 'customer_name') v = r.customer_name || '';
                else if (k === 'invoice_no') v = r.invoice_no || '';
                else if (k === 'invoice_date') v = formatDisplayDate(r.invoice_date);
                else if (k === 'generated_point') v = String(r.generated_point != null ? r.generated_point : '');
                else if (k === 'redeemed_point') v = String(r.redeemed_point != null ? r.redeemed_point : '');
                else if (k === 'redeem_value') v = String(r.redeem_value != null ? r.redeem_value : '');
                else if (k === 'account_no') v = r.account_no || '';
                if (k === 'generated_point' || k === 'redeemed_point' || k === 'redeem_value') td.className = 'th-num';
                td.textContent = v;
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        syncEmptyColspan();
    }

    function syncEmptyColspan() {
        var cell = el('rprEmptyMsgCell');
        if (!cell) return;
        var n = document.querySelectorAll('#rprHeadRow th[data-col]:not(.ageing-col-hidden)').length;
        cell.colSpan = Math.max(1, n);
    }

    function loadData() {
        if (state.loading) return;
        state.loading = true;
        var url = 'ajax/get-reward-point-report.php?' + getSearchParams().toString();
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                state.loading = false;
                if (!j || j.status !== 'success') {
                    renderBody([]);
                    state.total = 0;
                    state.totalPages = 1;
                    updatePager();
                    return;
                }
                var p = j.pagination || {};
                state.total = parseInt(p.total, 10) || 0;
                state.totalPages = parseInt(p.total_pages, 10) || 1;
                state.page = parseInt(p.current_page, 10) || 1;
                if (j.meta && j.meta.sort) {
                    state.sort = j.meta.sort;
                    state.order = j.meta.order || 'asc';
                }
                setSortIcons();
                renderBody(j.data || []);
                updatePager();
            })
            .catch(function () {
                state.loading = false;
                renderBody([]);
            });
    }

    function getHeadOrder() {
        var out = [];
        el('rprHeadRow').querySelectorAll('th[data-col]').forEach(function (th) {
            out.push(th.getAttribute('data-col'));
        });
        return out;
    }

    function normalizeStoredOrder(arr) {
        if (!arr || !Array.isArray(arr) || arr.length !== RPR_COL_DEFAULT_ORDER.length) return null;
        var set = {};
        arr.forEach(function (k) { set[k] = true; });
        for (var i = 0; i < RPR_COL_DEFAULT_ORDER.length; i++) {
            if (!set[RPR_COL_DEFAULT_ORDER[i]]) return null;
        }
        return arr.slice();
    }

    function toggleColHidden(key, hide) {
        document.querySelectorAll('#rprTable th[data-col="' + key + '"], #rprTable td[data-col="' + key + '"]').forEach(function (node) {
            node.classList.toggle('ageing-col-hidden', !!hide);
        });
    }

    function countVisibleCols() {
        return getHeadOrder().filter(function (k) {
            var th = document.querySelector('#rprHeadRow th[data-col="' + k + '"]');
            return th && !th.classList.contains('ageing-col-hidden');
        }).length;
    }

    function reorderHeaders(order) {
        var row = el('rprHeadRow');
        var map = {};
        row.querySelectorAll('th[data-col]').forEach(function (th) {
            map[th.getAttribute('data-col')] = th;
        });
        var frag = document.createDocumentFragment();
        order.forEach(function (k) { if (map[k]) frag.appendChild(map[k]); });
        row.appendChild(frag);
    }

    function reorderCells(tr, order) {
        if (tr.classList.contains('empty-row')) return;
        var map = {};
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            map[td.getAttribute('data-col')] = td;
        });
        if (Object.keys(map).length === 0) return;
        var frag = document.createDocumentFragment();
        order.forEach(function (k) { if (map[k]) frag.appendChild(map[k]); });
        tr.appendChild(frag);
    }

    function reorderColumns(order) {
        reorderHeaders(order);
        document.querySelectorAll('#rprBody tr').forEach(function (tr) {
            reorderCells(tr, order);
        });
    }

    function saveColState() {
        var hidden = {};
        getHeadOrder().forEach(function (k) {
            var th = document.querySelector('#rprHeadRow th[data-col="' + k + '"]');
            if (th && th.classList.contains('ageing-col-hidden')) hidden[k] = true;
        });
        var widths = {};
        getHeadOrder().forEach(function (k) {
            var th = document.querySelector('#rprHeadRow th[data-col="' + k + '"]');
            if (th && th.style && th.style.width) widths[k] = th.style.width;
        });
        try {
            localStorage.setItem(RPR_COL_STORAGE_KEY, JSON.stringify({
                order: getHeadOrder(),
                hidden: hidden,
                widths: widths
            }));
        } catch (e) {}
    }

    function thPlainLabel(th) {
        var inner = th.querySelector('.ageing-col-head-inner');
        if (!inner) return (th.textContent || '').trim();
        var clone = inner.cloneNode(true);
        clone.querySelectorAll('.sort-icons').forEach(function (n) { n.remove(); });
        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function openColModal() {
        var modal = el('rprColSettingsModal');
        var list = el('rprColSettingsList');
        list.innerHTML = '';
        getHeadOrder().forEach(function (k) {
            var th = document.querySelector('#rprHeadRow th[data-col="' + k + '"]');
            if (!th) return;
            var li = document.createElement('li');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !th.classList.contains('ageing-col-hidden');
            cb.setAttribute('data-col-key', k);
            cb.addEventListener('change', function () {
                if (!cb.checked) {
                    if (countVisibleCols() === 1 && !th.classList.contains('ageing-col-hidden')) {
                        cb.checked = true; return;
                    }
                    toggleColHidden(k, true);
                } else {
                    toggleColHidden(k, false);
                }
                renderBody(state.rows);
                syncEmptyColspan();
                saveColState();
            });
            var lab = document.createElement('label');
            lab.style.cursor = 'pointer';
            lab.style.flex = '1';
            lab.textContent = thPlainLabel(th);
            lab.addEventListener('click', function (e) {
                e.preventDefault();
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            li.appendChild(cb);
            li.appendChild(lab);
            list.appendChild(li);
        });
        modal.hidden = false;
    }

    function closeColModal() {
        var m = el('rprColSettingsModal');
        if (m) m.hidden = true;
    }

    function initColumnManager() {
        var headRow = el('rprHeadRow');
        if (!headRow) return;

        var st = {};
        try {
            var raw = localStorage.getItem(RPR_COL_STORAGE_KEY);
            if (raw) st = JSON.parse(raw) || {};
        } catch (e1) { st = {}; }

        var ord = normalizeStoredOrder(st.order);
        if (ord) reorderColumns(ord);

        if (st.hidden && typeof st.hidden === 'object') {
            RPR_COL_DEFAULT_ORDER.forEach(function (k) {
                if (st.hidden[k]) toggleColHidden(k, true);
            });
        }
        if (countVisibleCols() === 0) {
            RPR_COL_DEFAULT_ORDER.forEach(function (k) { toggleColHidden(k, false); });
        }

        if (st.widths && typeof st.widths === 'object') {
            Object.keys(st.widths).forEach(function (k) {
                var th = document.querySelector('#rprHeadRow th[data-col="' + k + '"]');
                if (th && st.widths[k]) th.style.width = st.widths[k];
            });
        }

        syncEmptyColspan();

        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var handle = th.querySelector('.ageing-col-drag');
            if (!handle) return;

            function clearDrop() {
                headRow.querySelectorAll('.ageing-col-drop-target').forEach(function (x) {
                    x.classList.remove('ageing-col-drop-target');
                });
            }
            function thFromPoint(cx, cy) {
                var e = document.elementFromPoint(cx, cy);
                if (!e || !e.closest) return null;
                return e.closest('#rprHeadRow th[data-col]');
            }

            handle.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                var fromKey = th.getAttribute('data-col');
                if (!fromKey) return;
                e.preventDefault();
                th.classList.add('ageing-col-dragging');
                try { handle.setPointerCapture(e.pointerId); } catch (x) {}

                function onMove(ev) {
                    clearDrop();
                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (over && over.getAttribute('data-col') !== fromKey) {
                        over.classList.add('ageing-col-drop-target');
                    }
                }
                function onEnd(ev) {
                    th.classList.remove('ageing-col-dragging');
                    clearDrop();
                    try { handle.releasePointerCapture(ev.pointerId); } catch (x) {}
                    handle.removeEventListener('pointermove', onMove);
                    handle.removeEventListener('pointerup', onEnd);
                    handle.removeEventListener('pointercancel', onEnd);

                    var over = thFromPoint(ev.clientX, ev.clientY);
                    var toKey = over && over.getAttribute('data-col');
                    if (!toKey || toKey === fromKey) return;
                    var order = getHeadOrder().slice();
                    var i = order.indexOf(fromKey);
                    var j = order.indexOf(toKey);
                    if (i < 0 || j < 0) return;
                    order.splice(i, 1);
                    order.splice(j, 0, fromKey);
                    reorderColumns(order);
                    saveColState();
                }
                handle.addEventListener('pointermove', onMove);
                handle.addEventListener('pointerup', onEnd);
                handle.addEventListener('pointercancel', onEnd);
            });

            var resizer = th.querySelector('.ageing-col-resizer');
            if (resizer) {
                resizer.addEventListener('mousedown', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var startX = e.clientX;
                    var startW = th.getBoundingClientRect().width;
                    function onMove(ev) {
                        var w = Math.max(48, startW + (ev.clientX - startX));
                        th.style.width = w + 'px';
                    }
                    function onUp() {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        saveColState();
                    }
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            }
        });

        el('rprColGearBtn').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var em = el('rprExportMenu');
            if (em) em.hidden = true;
            openColModal();
        });
        el('rprColSettingsBackdrop').addEventListener('click', closeColModal);
        el('rprColSettingsCloseX').addEventListener('click', closeColModal);
        el('rprColSettingsCloseBtn').addEventListener('click', closeColModal);
        el('rprColSettingsReset').addEventListener('click', function () {
            try { localStorage.removeItem(RPR_COL_STORAGE_KEY); } catch (e2) {}
            reorderColumns(RPR_COL_DEFAULT_ORDER.slice());
            RPR_COL_DEFAULT_ORDER.forEach(function (k) { toggleColHidden(k, false); });
            headRow.querySelectorAll('th[data-col]').forEach(function (h) { h.style.width = ''; });
            renderBody(state.rows);
            syncEmptyColspan();
            closeColModal();
        });
    }

    function wireSort() {
        el('rprHeadRow').querySelectorAll('th.th-sort').forEach(function (th) {
            th.addEventListener('click', function (e) {
                if (e.target.closest('.ageing-col-drag') || e.target.closest('.ageing-col-resizer')) return;
                var sk = th.getAttribute('data-sort');
                if (!sk) return;
                if (state.sort === sk) {
                    state.order = state.order === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sort = sk;
                    state.order = sk === 'invoice_date' ? 'desc' : 'asc';
                }
                state.page = 1;
                setSortIcons();
                loadData();
            });
        });
    }

    function exportStyledExcel() {
        var cols = [];
        el('rprHeadRow').querySelectorAll('th[data-col]:not(.ageing-col-hidden)').forEach(function (th) {
            cols.push(th.getAttribute('data-col'));
        });
        if (cols.length === 0) {
            return;
        }
        var body = JSON.stringify({
            from_date: el('rprFromDate').value || '',
            to_date: el('rprToDate').value || '',
            search: el('rprSearchInput').value.trim(),
            sort: state.sort,
            order: state.order,
            columns: cols
        });
        fetch('ajax/export-reward-point-report-excel.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: body
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('Export failed');
                }
                var cd = r.headers.get('Content-Disposition');
                var name = 'Reward_Point_Report.xlsx';
                if (cd && cd.indexOf('filename=') !== -1) {
                    var m = cd.match(/filename="([^"]+)"/);
                    if (m) name = m[1];
                }
                return r.blob().then(function (blob) {
                    return { blob: blob, name: name };
                });
            })
            .then(function (obj) {
                var url = URL.createObjectURL(obj.blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = obj.name;
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                el('rprExportMenu').hidden = true;
            })
            .catch(function () {
                alert(typeof window.rprExportErr === 'string' ? window.rprExportErr : 'Could not export. Please try again.');
            });
    }

    el('rprBtnRefresh').addEventListener('click', function () { state.page = 1; loadData(); });
    el('rprFromDate').addEventListener('change', function () { state.page = 1; loadData(); });
    el('rprToDate').addEventListener('change', function () { state.page = 1; loadData(); });
    var searchTimer;
    el('rprSearchInput').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.page = 1; loadData(); }, 350);
    });
    el('rprPerPage').addEventListener('change', function () { state.page = 1; loadData(); });

    el('rprBtnExportToggle').addEventListener('click', function (e) {
        e.stopPropagation();
        var m = el('rprExportMenu');
        m.hidden = !m.hidden;
    });
    document.addEventListener('click', function () { var m = el('rprExportMenu'); if (m) m.hidden = true; });
    el('rprExportXlsx').addEventListener('click', function (e) {
        e.stopPropagation();
        exportStyledExcel();
    });
    el('rprPager').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-go]');
        if (!btn || btn.disabled) return;
        var go = btn.getAttribute('data-go');
        if (go === 'first') state.page = 1;
        else if (go === 'prev') state.page = Math.max(1, state.page - 1);
        else if (go === 'next') state.page = Math.min(state.totalPages, state.page + 1);
        else if (go === 'last') state.page = state.totalPages;
        loadData();
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        var m = el('rprColSettingsModal');
        if (m && !m.hidden) closeColModal();
    });

    initColumnManager();
    wireSort();
    state.sort = 'invoice_date';
    state.order = 'desc';
    setSortIcons();
    loadData();
})();
</script>
