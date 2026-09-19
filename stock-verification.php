<?php
/**
 * Stock Verification — available barcode stock, metal-wise.
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/barcode_management_fetch.php';
if (!function_exists('auragold_nav_show_php_href')) {
    require_once __DIR__ . '/includes/auragold_sidebar_nav_permissions.php';
}

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'all';
$sv_page = max(1, (int) ($_GET['page'] ?? 1));
$sv_per_page = max(10, min(200, (int) ($_GET['per_page'] ?? 20)));
$sv_search = trim((string) ($_GET['q'] ?? ''));

$sv_metals = auragold_barcode_management_metals($conn);
$sv_active_tab = 'all';
if ($tab !== '' && $tab !== 'all') {
    foreach ($sv_metals as $m) {
        if (($m['slug'] ?? '') === $tab || (string) ($m['id'] ?? '') === $tab) {
            $sv_active_tab = (string) $m['slug'];
            break;
        }
    }
}

$load_error = '';
$rows = [];

$sv_query_base = static function (array $overrides = []) use ($sv_active_tab, $sv_search, $sv_per_page): array {
    $q = ['tab' => $sv_active_tab !== '' ? $sv_active_tab : 'all'];
    if ($sv_search !== '') {
        $q['q'] = $sv_search;
    }
    if ($sv_per_page !== 20) {
        $q['per_page'] = $sv_per_page;
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return $q;
};

$page_title = (function_exists('auragold_t') ? auragold_t('inv.physical_stock', 'Barcode Re-Print') : 'Barcode Re-Print')
    . ' — ' . auragold_app_name();
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        :root { --sv-navy: #11294b; --sv-gold: #c9a227; }
        .sv-wrap { padding: 16px 20px 28px; }
        .sv-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .sv-title { font-size: 1.2rem; font-weight: 700; color: var(--sv-navy); margin: 0 0 4px; }
        .sv-sub { font-size: 0.85rem; color: #64748b; margin: 0; }
        .sv-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .sv-tabs a {
            padding: 7px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;
            color: var(--sv-navy); font-size: 0.82rem; font-weight: 600; text-decoration: none;
        }
        .sv-tabs a:hover { background: #fdf8f0; border-color: var(--sv-gold); }
        .sv-tabs a.active { background: var(--sv-navy); color: #fff; border-color: var(--sv-navy); }
        .sv-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .sv-stat {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 14px;
            font-size: 0.8rem; color: #475569;
        }
        .sv-stat strong { color: var(--sv-navy); font-size: 1rem; margin-right: 4px; }
        .sv-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px; }
        .sv-toolbar input {
            border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 0.85rem;
            min-width: 240px;
        }
        .sv-bulk-print {
            display: none; padding: 7px 16px; border: none; border-radius: 6px;
            background: var(--sv-navy); color: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer;
        }
        .sv-bulk-print.is-visible { display: inline-flex; align-items: center; gap: 6px; }
        .sv-bulk-print:hover { opacity: 0.92; }
        .sv-bulk-print:disabled { opacity: 0.55; cursor: not-allowed; }
        .sv-bulk-delete {
            display: none; padding: 7px 16px; border: none; border-radius: 6px;
            background: #dc2626; color: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer;
        }
        .sv-bulk-delete.is-visible { display: inline-flex; align-items: center; gap: 6px; }
        .sv-bulk-delete:hover { opacity: 0.92; }
        .sv-bulk-delete:disabled { opacity: 0.55; cursor: not-allowed; }
        .sv-check { width: 16px; height: 16px; cursor: pointer; accent-color: var(--sv-navy); }
        .sv-table th.sv-check-col,
        .sv-table td.sv-check-col { width: 36px; text-align: center; }
        .sv-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(17, 41, 75, 0.06); overflow: hidden;
        }
        .sv-scroll { overflow: auto; max-height: calc(100vh - 280px); }
        .sv-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; white-space: nowrap; }
        .sv-table thead th {
            position: sticky; top: 0; z-index: 2; background: var(--sv-navy); color: #fff;
            padding: 9px 8px; font-weight: 700;
        }
        .sv-table tbody td { padding: 7px 8px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
        .sv-table tbody tr:nth-child(even) { background: #fcfdff; }
        .sv-table tbody tr.sv-hidden { display: none; }
        .sv-empty { padding: 32px; text-align: center; color: #94a3b8; }
        .sv-alert { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #991b1b; margin-bottom: 12px; }
        .sv-print {
            display: inline-block; padding: 4px 10px; border-radius: 6px; background: var(--sv-navy);
            color: #fff !important; font-size: 0.75rem; font-weight: 600; text-decoration: none;
        }
        .sv-print:hover { opacity: 0.9; color: #fff !important; }
        .sv-metal { font-weight: 700; color: var(--sv-navy); }
        .sv-pagination {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 10px; padding: 12px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc;
            font-size: 0.82rem; color: #475569;
        }
        .sv-pagination-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .sv-pagination-nav a, .sv-pagination-nav span {
            display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px;
            padding: 0 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            color: var(--sv-navy); text-decoration: none; font-weight: 600;
        }
        .sv-pagination-nav a:hover { background: #fdf8f0; border-color: var(--sv-gold); }
        .sv-pagination-nav span.current { background: var(--sv-navy); color: #fff; border-color: var(--sv-navy); }
        .sv-pagination-nav span.disabled { opacity: 0.45; pointer-events: none; }
        .sv-pagination-meta select {
            border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 8px; font-size: 0.82rem;
        }
        .sv-search-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .sv-search-form button {
            padding: 7px 14px; border: 1px solid var(--sv-navy); border-radius: 6px;
            background: var(--sv-navy); color: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer;
        }
        .sv-search-form button[type="button"] {
            background: #fff; color: var(--sv-navy);
        }
        .sv-loading-row td { text-align: center; padding: 28px; color: #64748b; }
        .sv-stats.is-loading .sv-stat strong::after { content: '…'; margin-left: 2px; }
        .sv-selected-totals {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            font-size: 0.8rem; color: #475569;
        }
        .sv-selected-total {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 6px 12px; white-space: nowrap;
        }
        .sv-selected-total strong { color: var(--sv-navy); font-size: 0.9rem; margin-right: 4px; }
    </style>
</head>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div class="layout-container">
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;">
                    <?php include __DIR__ . '/sidebar.php'; ?>

                    <div class="sv-wrap">
                        <div class="sv-head">
                            <div>
                                <h1 class="sv-title"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.physical_stock', 'Barcode Re-Print'), ENT_QUOTES, 'UTF-8') : 'Barcode Re-Print'; ?></h1>
                                <p class="sv-sub"><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('inv.physical_stock_lead', 'All available barcode stock, grouped by metal.'), ENT_QUOTES, 'UTF-8') : 'All available barcode stock, grouped by metal.'; ?></p>
                            </div>
                        </div>

                        <div class="sv-tabs" role="tablist" aria-label="Metal tabs">
                            <a href="<?php echo htmlspecialchars('stock-verification.php?' . http_build_query($sv_query_base(['tab' => 'all', 'page' => null])), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $sv_active_tab === 'all' ? 'active' : ''; ?>">All Metals</a>
                            <?php foreach ($sv_metals as $m): ?>
                                <?php
                                $mslug = (string) ($m['slug'] ?? '');
                                $mname = (string) ($m['name'] ?? '');
                                $mhref = 'stock-verification.php?' . http_build_query($sv_query_base(['tab' => $mslug, 'page' => null]));
                                ?>
                                <a href="<?php echo htmlspecialchars($mhref, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $sv_active_tab === $mslug ? 'active' : ''; ?>"><?php echo htmlspecialchars($mname, ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endforeach; ?>
                        </div>

                        <div class="sv-stats is-loading" id="svStats">
                            <div class="sv-stat"><strong id="svStatTotal">—</strong> Available barcodes</div>
                            <div class="sv-stat"><strong id="svStatQty">—</strong> Total qty</div>
                            <div class="sv-stat"><strong id="svStatWt">—</strong> Total wt</div>
                            <div id="svStatMetals"></div>
                        </div>

                        <?php if ($load_error !== ''): ?>
                            <div class="sv-alert"><?php echo $load_error; ?></div>
                        <?php endif; ?>

                        <div class="sv-toolbar">
                            <form class="sv-search-form" method="get" action="stock-verification.php" id="svSearchForm">
                                <input type="hidden" name="tab" id="svTabInput" value="<?php echo htmlspecialchars($sv_active_tab !== '' ? $sv_active_tab : 'all', ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="per_page" id="svPerPageInput" value="<?php echo (int) $sv_per_page; ?>">
                                <input type="search" name="q" id="svSearch" value="<?php echo htmlspecialchars($sv_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search barcode, product, metal, invoice…" aria-label="Search available barcodes">
                                <button type="submit">Search</button>
                                <?php if ($sv_search !== ''): ?>
                                    <button type="button" id="svSearchClear">Clear</button>
                                <?php endif; ?>
                            </form>
                            <button type="button" class="sv-bulk-print" id="svBulkPrint" hidden aria-hidden="true">Print selected (<span id="svBulkPrintCount">0</span>)</button>
                            <button type="button" class="sv-bulk-delete" id="svBulkDelete" hidden aria-hidden="true">Delete selected (<span id="svBulkDeleteCount">0</span>)</button>
                            <div class="sv-selected-totals" id="svSelectedTotals">
                                <div class="sv-selected-total"><strong id="svSelGrossWt">0.000</strong> Sel. Gross Wt</div>
                                <div class="sv-selected-total"><strong id="svSelNetWt">0.000</strong> Sel. Net Wt</div>
                                <div class="sv-selected-total"><strong id="svSelBalQty">0.00</strong> Sel. Bal Qty</div>
                                <div class="sv-selected-total"><strong id="svSelBalWt">0.000</strong> Sel. Bal Wt</div>
                            </div>
                        </div>

                        <div class="sv-card">
                            <div class="sv-scroll">
                                <table class="sv-table" id="svTable">
                                    <thead>
                                        <tr>
                                            <th class="sv-check-col"><input type="checkbox" class="sv-check" id="svSelectAll" title="Select all visible rows" aria-label="Select all visible rows"></th>
                                            <th>Metal</th>
                                            <th>Barcode No</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Branch</th>
                                            <th>Gross Wt</th>
                                            <th>Net Wt</th>
                                            <th>Bal Qty</th>
                                            <th>Bal Wt</th>
                                            <th>Carat</th>
                                            <th>HUID No.</th>
                                            <th>Location</th>
                                            <th>Voucher Type</th>
                                            <th>Invoice No.</th>
                                            <th>Print</th>
                                        </tr>
                                    </thead>
                                    <tbody id="svTableBody">
                                        <tr class="sv-loading-row"><td colspan="16">Loading barcodes…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="sv-pagination" id="svPagination">
                                <div class="sv-pagination-meta" id="svPaginationMeta">Loading…</div>
                                <div class="sv-pagination-nav" id="svPaginationNav" aria-label="Pagination"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var state = {
        tab: <?php echo json_encode($sv_active_tab !== '' ? $sv_active_tab : 'all'); ?>,
        page: <?php echo (int) $sv_page; ?>,
        perPage: <?php echo (int) $sv_per_page; ?>,
        q: <?php echo json_encode($sv_search); ?>,
        loading: false
    };

    var searchForm = document.getElementById('svSearchForm');
    var searchEl = document.getElementById('svSearch');
    var searchClear = document.getElementById('svSearchClear');
    var tabInput = document.getElementById('svTabInput');
    var perPageInput = document.getElementById('svPerPageInput');
    var tbody = document.getElementById('svTableBody');
    var paginationMeta = document.getElementById('svPaginationMeta');
    var paginationNav = document.getElementById('svPaginationNav');
    var table = document.getElementById('svTable');
    var selectAllEl = document.getElementById('svSelectAll');
    var bulkPrintBtn = document.getElementById('svBulkPrint');
    var bulkPrintCountEl = document.getElementById('svBulkPrintCount');
    var bulkDeleteBtn = document.getElementById('svBulkDelete');
    var bulkDeleteCountEl = document.getElementById('svBulkDeleteCount');
    var selGrossWtEl = document.getElementById('svSelGrossWt');
    var selNetWtEl = document.getElementById('svSelNetWt');
    var selBalQtyEl = document.getElementById('svSelBalQty');
    var selBalWtEl = document.getElementById('svSelBalWt');
    var statsEl = document.getElementById('svStats');
    var statTotal = document.getElementById('svStatTotal');
    var statQty = document.getElementById('svStatQty');
    var statWt = document.getElementById('svStatWt');
    var statMetals = document.getElementById('svStatMetals');

    function rowChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('#svTable tbody .sv-row-check'));
    }

    function visibleRowChecks() {
        return rowChecks();
    }

    function selectedBarcodes() {
        var codes = [];
        rowChecks().forEach(function (cb) {
            if (cb.checked) {
                var tr = cb.closest('tr');
                var bc = String(cb.value || (tr && tr.getAttribute('data-sv-barcode')) || '').trim();
                if (bc) codes.push(bc);
            }
        });
        return codes;
    }

    function parseNum(val) {
        var n = parseFloat(val);
        return isNaN(n) ? 0 : n;
    }

    function selectedTotals() {
        var gross = 0;
        var net = 0;
        var balQty = 0;
        var balWt = 0;
        rowChecks().forEach(function (cb) {
            if (!cb.checked) return;
            var tr = cb.closest('tr');
            if (!tr) return;
            gross += parseNum(tr.getAttribute('data-sv-gross-wt'));
            net += parseNum(tr.getAttribute('data-sv-net-wt'));
            balQty += parseNum(tr.getAttribute('data-sv-bal-qty'));
            balWt += parseNum(tr.getAttribute('data-sv-bal-wt'));
        });
        return { gross: gross, net: net, balQty: balQty, balWt: balWt };
    }

    function syncBulkUi() {
        var selected = selectedBarcodes();
        var count = selected.length;
        var totals = selectedTotals();
        if (bulkPrintCountEl) bulkPrintCountEl.textContent = String(count);
        if (bulkDeleteCountEl) bulkDeleteCountEl.textContent = String(count);
        if (selGrossWtEl) selGrossWtEl.textContent = totals.gross.toFixed(3);
        if (selNetWtEl) selNetWtEl.textContent = totals.net.toFixed(3);
        if (selBalQtyEl) selBalQtyEl.textContent = totals.balQty.toFixed(2);
        if (selBalWtEl) selBalWtEl.textContent = totals.balWt.toFixed(3);
        if (bulkPrintBtn) {
            var show = count > 0;
            bulkPrintBtn.hidden = !show;
            bulkPrintBtn.setAttribute('aria-hidden', show ? 'false' : 'true');
            bulkPrintBtn.classList.toggle('is-visible', show);
            bulkPrintBtn.disabled = count === 0;
        }
        if (bulkDeleteBtn) {
            var showDel = count > 0;
            bulkDeleteBtn.hidden = !showDel;
            bulkDeleteBtn.setAttribute('aria-hidden', showDel ? 'false' : 'true');
            bulkDeleteBtn.classList.toggle('is-visible', showDel);
            bulkDeleteBtn.disabled = count === 0;
        }
        if (selectAllEl) {
            var visible = visibleRowChecks();
            if (!visible.length) {
                selectAllEl.checked = false;
                selectAllEl.indeterminate = false;
                return;
            }
            var checkedVisible = visible.filter(function (cb) { return cb.checked; }).length;
            selectAllEl.checked = checkedVisible === visible.length;
            selectAllEl.indeterminate = checkedVisible > 0 && checkedVisible < visible.length;
        }
    }

    function buildApiUrl(extra) {
        var params = new URLSearchParams();
        params.set('tab', state.tab || 'all');
        params.set('page', String(state.page));
        params.set('per_page', String(state.perPage));
        if (state.q) params.set('q', state.q);
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                params.set(k, String(extra[k]));
            });
        }
        return 'ajax/get-stock-verification-rows.php?' + params.toString();
    }

    function pushUrl() {
        var params = new URLSearchParams();
        params.set('tab', state.tab || 'all');
        if (state.page > 1) params.set('page', String(state.page));
        if (state.perPage !== 20) params.set('per_page', String(state.perPage));
        if (state.q) params.set('q', state.q);
        var qs = params.toString();
        var url = 'stock-verification.php' + (qs ? ('?' + qs) : '');
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', url);
        }
    }

    function renderPagination(pg) {
        if (!paginationMeta || !paginationNav) return;
        var cur = pg.page || 1;
        var totalPages = Math.max(1, pg.total_pages || 1);
        var total = pg.total || 0;
        var per = pg.per_page || state.perPage;
        var from = total > 0 ? pg.range_from : 0;
        var to = total > 0 ? pg.range_to : 0;

        paginationMeta.innerHTML = '';
        if (total > 0) {
            paginationMeta.appendChild(document.createTextNode('Showing ' + from + '–' + to + ' of ' + total + ' '));
        } else {
            paginationMeta.appendChild(document.createTextNode('No rows '));
        }
        var label = document.createElement('label');
        label.setAttribute('for', 'svPerPage');
        label.style.marginLeft = '10px';
        label.textContent = 'Rows:';
        paginationMeta.appendChild(label);
        var select = document.createElement('select');
        select.id = 'svPerPage';
        select.setAttribute('aria-label', 'Rows per page');
        [20, 50, 100].forEach(function (opt) {
            var o = document.createElement('option');
            o.value = String(opt);
            o.textContent = String(opt);
            if (opt === per) o.selected = true;
            select.appendChild(o);
        });
        select.addEventListener('change', function () {
            state.perPage = parseInt(select.value, 10) || 20;
            state.page = 1;
            if (perPageInput) perPageInput.value = String(state.perPage);
            loadRows(false);
        });
        paginationMeta.appendChild(select);

        paginationNav.innerHTML = '';
        function addLink(text, pageNum, disabled, current) {
            if (disabled) {
                var sp = document.createElement('span');
                sp.className = 'disabled';
                sp.textContent = text;
                paginationNav.appendChild(sp);
                return;
            }
            if (current) {
                var curEl = document.createElement('span');
                curEl.className = 'current';
                curEl.textContent = text;
                paginationNav.appendChild(curEl);
                return;
            }
            var a = document.createElement('a');
            a.href = '#';
            a.textContent = text;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                state.page = pageNum;
                loadRows(false);
            });
            paginationNav.appendChild(a);
        }

        addLink('Prev', cur - 1, cur <= 1, false);
        var win = 2;
        var start = Math.max(1, cur - win);
        var end = Math.min(totalPages, cur + win);
        if (start > 1) {
            addLink('1', 1, false, cur === 1);
            if (start > 2) addLink('…', 0, true, false);
        }
        for (var p = start; p <= end; p++) {
            addLink(String(p), p, false, p === cur);
        }
        if (end < totalPages) {
            if (end < totalPages - 1) addLink('…', 0, true, false);
            addLink(String(totalPages), totalPages, false, cur === totalPages);
        }
        addLink('Next', cur + 1, cur >= totalPages, false);
    }

    function renderSummary(summary) {
        if (!summary) return;
        if (statTotal) statTotal.textContent = String(summary.total_count || 0);
        if (statQty) statQty.textContent = Number(summary.qty_sum || 0).toFixed(2);
        if (statWt) statWt.textContent = Number(summary.wt_sum || 0).toFixed(3);
        if (statMetals) {
            statMetals.innerHTML = '';
            var metals = summary.metal_counts || {};
            Object.keys(metals).forEach(function (name) {
                var div = document.createElement('div');
                div.className = 'sv-stat';
                div.innerHTML = '<strong>' + metals[name] + '</strong> ' + name;
                statMetals.appendChild(div);
            });
        }
        if (statsEl) statsEl.classList.remove('is-loading');
    }

    function loadSummary() {
        fetch(buildApiUrl({ summary: 1 }), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success' && data.summary) {
                    renderSummary(data.summary);
                }
            })
            .catch(function () {});
    }

    function loadRows(updateSummary) {
        if (state.loading) return;
        state.loading = true;
        if (tbody) {
            tbody.innerHTML = '<tr class="sv-loading-row"><td colspan="16">Loading barcodes…</td></tr>';
        }
        if (selectAllEl) {
            selectAllEl.checked = false;
            selectAllEl.indeterminate = false;
        }
        syncBulkUi();
        pushUrl();

        fetch(buildApiUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                state.loading = false;
                if (data.status !== 'success') {
                    if (tbody) tbody.innerHTML = '<tr><td colspan="16" class="sv-empty">' + (data.message || 'Could not load data') + '</td></tr>';
                    return;
                }
                if (tbody) tbody.innerHTML = data.rows_html || '';
                renderPagination(data.pagination || {});
                syncBulkUi();
                if (updateSummary !== false) {
                    loadSummary();
                }
            })
            .catch(function (err) {
                state.loading = false;
                if (tbody) tbody.innerHTML = '<tr><td colspan="16" class="sv-empty">' + (err && err.message ? err.message : 'Load failed') + '</td></tr>';
            });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            state.q = searchEl ? searchEl.value.trim() : '';
            state.page = 1;
            if (tabInput) state.tab = tabInput.value || 'all';
            loadRows(true);
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchEl) searchEl.value = '';
            state.q = '';
            state.page = 1;
            loadRows(true);
        });
    }

    if (selectAllEl) {
        selectAllEl.addEventListener('change', function () {
            var check = !!selectAllEl.checked;
            visibleRowChecks().forEach(function (cb) {
                cb.checked = check;
            });
            syncBulkUi();
        });
    }

    if (table) {
        table.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('sv-row-check')) {
                syncBulkUi();
            }
        });
    }

    if (bulkPrintBtn) {
        bulkPrintBtn.addEventListener('click', function () {
            var codes = selectedBarcodes();
            if (!codes.length) return;
            window.open('barcode-print.php?barcodes=' + encodeURIComponent(codes.join(',')), '_blank', 'noopener');
        });
    }

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function () {
            var codes = selectedBarcodes();
            if (!codes.length) return;
            if (!confirm('Delete ' + codes.length + ' selected barcode(s) from stock? This removes stock journal entries and cannot be undone.\n\n' + codes.slice(0, 8).join(', ') + (codes.length > 8 ? '…' : ''))) {
                return;
            }
            bulkDeleteBtn.disabled = true;
            var origText = bulkDeleteBtn.textContent;
            bulkDeleteBtn.textContent = 'Deleting…';
            fetch('ajax/delete-stock-verification-barcodes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ barcodes: codes })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        alert(data.message || 'Deleted successfully');
                        loadRows(true);
                        return;
                    }
                    alert(data.message || 'Delete failed');
                    bulkDeleteBtn.disabled = false;
                    bulkDeleteBtn.textContent = origText;
                    syncBulkUi();
                })
                .catch(function (err) {
                    alert(err && err.message ? err.message : 'Delete request failed');
                    bulkDeleteBtn.disabled = false;
                    bulkDeleteBtn.textContent = origText;
                    syncBulkUi();
                });
        });
    }

    loadRows(true);
})();
</script>
</body>
</html>
