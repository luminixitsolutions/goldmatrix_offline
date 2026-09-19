<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

$from_date = isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = date('Y-m-d');
}

$lbl_title = function_exists('auragold_t') ? auragold_t('rep.barcode_scan', 'Barcode Scan Report') : 'Barcode Scan Report';

$AURAGOLD_REPORT_PAGE = true;
include __DIR__ . '/header-script.php';
include __DIR__ . '/sidebar.php';
?>

<div class="layout-container ageing-report-page barcode-scan-report-page">
    <div class="main-content">
        <div class="page-container">
            <h1 class="sr-only"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></h1>

            <div class="ageing-shell">
                <div class="ageing-shell-top">
                    <div class="ageing-tabs-row">
                        <div class="search-box-inline field-grow bsr-search-wrap">
                            <label class="sr-only" for="bsrSearch">Search</label>
                            <input type="search" id="bsrSearch" class="form-control-sm" placeholder="Barcode, product, user…" autocomplete="off">
                            <i class="feather icon-search"></i>
                        </div>
                        <div class="toolbar-actions ageing-tabs-row__actions">
                            <button type="button" class="btn-icon-tight" id="bsrBtnRefresh" title="Refresh">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                            <div class="ageing-export-dd">
                                <button type="button" class="btn-ageing-primary" id="bsrBtnExportToggle">
                                    Export
                                    <i class="feather icon-chevron-down"></i>
                                </button>
                                <div class="ageing-export-menu" id="bsrExportMenu" role="menu" hidden>
                                    <button type="button" class="ageing-export-item" id="bsrExportXlsx" role="menuitem">
                                        <span class="ageing-export-ico ageing-export-ico--excel" aria-hidden="true"><i class="fas fa-file-excel"></i></span>
                                        <span class="ageing-export-txt">Excel (.xlsx)</span>
                                    </button>
                                    <button type="button" class="ageing-export-item" id="bsrExportPdf" role="menuitem">
                                        <span class="ageing-export-ico ageing-export-ico--pdf" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
                                        <span class="ageing-export-txt">PDF</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ageing-toolbar bsr-date-toolbar">
                    <div class="toolbar-inner">
                        <div class="field-group">
                            <label class="field-label" for="bsrFromDate">From date</label>
                            <input type="date" id="bsrFromDate" class="form-control-sm" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="bsrToDate">To date</label>
                            <input type="date" id="bsrToDate" class="form-control-sm" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="bsrStatus">Status</label>
                            <select id="bsrStatus" class="form-control-sm">
                                <option value="">All</option>
                                <option value="matched">Matched</option>
                                <option value="unknown">Unknown</option>
                            </select>
                        </div>
                        <div class="field-group bsr-title-group">
                            <span class="bsr-page-title"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bsr-summary-row" id="bsrSummaryRow">
                    <div class="bsr-stat-card">
                        <span class="bsr-stat-label">Total scans</span>
                        <strong class="bsr-stat-val" id="bsrStatTotal">0</strong>
                    </div>
                    <div class="bsr-stat-card bsr-stat-card--matched">
                        <span class="bsr-stat-label">Matched</span>
                        <strong class="bsr-stat-val" id="bsrStatMatched">0</strong>
                    </div>
                    <div class="bsr-stat-card bsr-stat-card--unknown">
                        <span class="bsr-stat-label">Unknown</span>
                        <strong class="bsr-stat-val" id="bsrStatUnknown">0</strong>
                    </div>
                    <div class="bsr-stat-card">
                        <span class="bsr-stat-label">Total qty</span>
                        <strong class="bsr-stat-val" id="bsrStatQty">0</strong>
                    </div>
                    <div class="bsr-stat-card">
                        <span class="bsr-stat-label">Gross wt</span>
                        <strong class="bsr-stat-val" id="bsrStatGross">0</strong>
                    </div>
                    <div class="bsr-stat-card">
                        <span class="bsr-stat-label">Net wt</span>
                        <strong class="bsr-stat-val" id="bsrStatNet">0</strong>
                    </div>
                    <div class="bsr-stat-card">
                        <span class="bsr-stat-label">Final wt</span>
                        <strong class="bsr-stat-val" id="bsrStatFinal">0</strong>
                    </div>
                </div>

                <div class="ageing-panel">
                    <div class="bsr-period-line" id="bsrPeriodLine">Loading…</div>
                    <div class="table-responsive ageing-table-wrap">
                        <table class="table ageing-table" id="bsrTable">
                            <thead>
                                <tr>
                                    <th class="th-num" style="min-width:42px">Sr</th>
                                    <th style="min-width:92px">Scan Date</th>
                                    <th style="min-width:78px">Scan Time</th>
                                    <th style="min-width:78px">Status</th>
                                    <th style="min-width:96px">Scan Code</th>
                                    <th style="min-width:96px">Barcode</th>
                                    <th style="min-width:96px">RFID Code</th>
                                    <th style="min-width:120px">Product</th>
                                    <th style="min-width:90px">Article</th>
                                    <th style="min-width:72px">Metal</th>
                                    <th style="min-width:72px">Branch</th>
                                    <th style="min-width:80px">Location</th>
                                    <th class="th-num" style="min-width:52px">Qty</th>
                                    <th class="th-num" style="min-width:72px">Gross Wt</th>
                                    <th class="th-num" style="min-width:72px">Net Wt</th>
                                    <th class="th-num" style="min-width:72px">Final Wt</th>
                                    <th style="min-width:110px">Voucher Type</th>
                                    <th style="min-width:96px">Invoice No.</th>
                                    <th style="min-width:96px">Scanned By</th>
                                </tr>
                            </thead>
                            <tbody id="bsrBody">
                                <tr class="empty-row">
                                    <td colspan="19" class="empty-msg">Loading…</td>
                                </tr>
                            </tbody>
                            <tfoot id="bsrFoot" hidden>
                                <tr class="bsr-footer-total">
                                    <td colspan="12" class="text-right"><strong>Total</strong></td>
                                    <td class="th-num" id="bsrFootQty">0</td>
                                    <td class="th-num" id="bsrFootGross">0</td>
                                    <td class="th-num" id="bsrFootNet">0</td>
                                    <td class="th-num" id="bsrFootFinal">0</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="pagination-container ageing-pagination">
                        <div>
                            <span id="bsrPaginationInfo" class="pagination-info">Showing 0 scan record(s)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>

<style>
.barcode-scan-report-page {
    --ageing-navy: #11294b;
    --ageing-navy-dark: #0c1d36;
    --ageing-gold: #c9a227;
    --ageing-border: #c5cddf;
    --ageing-bg: #eef1f6;
}
.barcode-scan-report-page.layout-container {
    padding: 10px clamp(6px, 0.9vw, 14px) 20px;
    width: 100%;
    max-width: 100vw;
    margin: 0;
    box-sizing: border-box;
    background: var(--ageing-bg);
    min-height: calc(100vh - 60px);
}
.barcode-scan-report-page .main-content,
.barcode-scan-report-page .page-container { width: 100%; max-width: 100%; margin: 0; padding: 0; }
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
}
.ageing-tabs-row .toolbar-actions { margin-left: auto; align-items: center; }
.toolbar-actions { display: flex; align-items: center; gap: 10px; }
.btn-icon-tight {
    width: 38px; height: 38px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid #c9d4e3; border-radius: 8px; background: #fff; cursor: pointer;
    color: #475569;
}
.search-box-inline { position: relative; flex: 1; min-width: 200px; }
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
.ageing-export-menu { position: absolute; top: 100%; right: 0; margin-top: 6px; min-width: 196px; background: #fff;
    border: 1px solid var(--ageing-border); border-radius: 10px; box-shadow: 0 12px 32px rgba(17, 41, 75, 0.14); z-index: 1050; padding: 8px 0; }
.ageing-export-menu:not([hidden]) { display: block; }
.ageing-export-item {
    display: flex; align-items: center; gap: 12px; width: 100%; margin: 0; border: 0; background: transparent;
    padding: 11px 16px; font-size: 13px; font-weight: 500; color: #334155; cursor: pointer; text-align: left; font-family: inherit;
}
.ageing-export-item:hover { background: #f1f5f9; }
.ageing-export-ico--excel { color: #217346; }
.ageing-export-ico--pdf { color: #c62828; }
.barcode-scan-report-page .bsr-title-group { margin-left: auto; align-self: flex-end; }
.barcode-scan-report-page .bsr-page-title { font-size: 1.05rem; font-weight: 700; color: var(--ageing-navy); }
.bsr-summary-row {
    display: flex; flex-wrap: wrap; gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--ageing-border);
    background: linear-gradient(180deg, #fafbfd 0%, #f5f7fb 100%);
}
.bsr-stat-card {
    flex: 1 1 110px;
    min-width: 110px;
    background: #fff;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    padding: 10px 12px;
    box-shadow: 0 1px 2px rgba(17, 41, 75, 0.04);
}
.bsr-stat-card--matched { border-left: 3px solid #22c55e; }
.bsr-stat-card--unknown { border-left: 3px solid #ef4444; }
.bsr-stat-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; margin-bottom: 4px; }
.bsr-stat-val { font-size: 1.15rem; color: var(--ageing-navy); }
.barcode-scan-report-page .ageing-panel { min-width: 0; padding: 0 0 12px; }
.bsr-period-line { padding: 10px 18px 0; font-size: 12px; color: #64748b; font-weight: 600; }
.ageing-table-wrap {
    display: block; max-height: calc(100vh - 380px); overflow: auto; min-width: 0; width: 100%; background: #fff;
    margin: 8px 0 0; padding: 0 12px;
    box-sizing: border-box;
}
.ageing-table { width: 100%; border-collapse: collapse; margin: 0; font-size: 12px; white-space: nowrap; }
.ageing-table thead th {
    position: sticky; top: 0; z-index: 2; background: var(--ageing-navy); padding: 11px 12px;
    text-align: left; font-weight: 600; color: #fff; border-bottom: none;
}
.ageing-table .th-num { text-align: right; }
.ageing-table tbody td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; color: #475569; background: #fff; }
.ageing-table tbody tr:nth-child(even) td { background: #fbfcfe; }
.ageing-table tbody .empty-msg { text-align: center; color: #94a3b8; padding: 52px 16px !important; white-space: normal; }
.ageing-table tfoot td {
    padding: 10px 12px; font-weight: 700; background: #e8eef5 !important;
    border-top: 2px solid rgba(17, 41, 75, 0.2); color: var(--ageing-navy);
}
.bsr-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.bsr-badge--matched { background: #dcfce7; color: #166534; }
.bsr-badge--unknown { background: #fee2e2; color: #991b1b; }
.pagination-container.ageing-pagination {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
    padding: 12px 16px; border-top: 1px solid var(--ageing-border); background: #fafbfc;
}
.pagination-info { font-size: 12px; color: #64748b; }
</style>

<script>
(function () {
    var fromEl = document.getElementById('bsrFromDate');
    var toEl = document.getElementById('bsrToDate');
    var statusEl = document.getElementById('bsrStatus');
    var searchEl = document.getElementById('bsrSearch');
    var bodyEl = document.getElementById('bsrBody');
    var footEl = document.getElementById('bsrFoot');
    var periodEl = document.getElementById('bsrPeriodLine');
    var infoEl = document.getElementById('bsrPaginationInfo');
    var refreshBtn = document.getElementById('bsrBtnRefresh');
    var exportToggle = document.getElementById('bsrBtnExportToggle');
    var exportMenu = document.getElementById('bsrExportMenu');
    var exportXlsx = document.getElementById('bsrExportXlsx');
    var exportPdf = document.getElementById('bsrExportPdf');
    var searchTimer = null;

    function esc(s) {
        if (s == null || s === '') return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtNum(v, dec) {
        if (v == null || v === '') return '0';
        var n = parseFloat(v);
        if (isNaN(n)) return esc(v);
        if (dec == null) dec = 4;
        var f = n.toFixed(dec);
        return f.replace(/\.?0+$/, function (m) { return m === '.' ? '' : m; }) || '0';
    }

    function fmtDisplayDate(iso) {
        if (!iso) return '';
        var p = String(iso).split('-');
        if (p.length !== 3) return iso;
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function getQueryParams() {
        var qs = new URLSearchParams();
        if (fromEl && fromEl.value) qs.set('from_date', fromEl.value);
        if (toEl && toEl.value) qs.set('to_date', toEl.value);
        if (statusEl && statusEl.value) qs.set('status', statusEl.value);
        if (searchEl && searchEl.value.trim()) qs.set('q', searchEl.value.trim());
        return qs;
    }

    function updateSummary(summary, fromDate, toDate, rowCount) {
        summary = summary || {};
        document.getElementById('bsrStatTotal').textContent = summary.total_records != null ? summary.total_records : rowCount;
        document.getElementById('bsrStatMatched').textContent = summary.matched_count != null ? summary.matched_count : 0;
        document.getElementById('bsrStatUnknown').textContent = summary.unknown_count != null ? summary.unknown_count : 0;
        document.getElementById('bsrStatQty').textContent = fmtNum(summary.total_qty);
        document.getElementById('bsrStatGross').textContent = fmtNum(summary.total_gross_wt);
        document.getElementById('bsrStatNet').textContent = fmtNum(summary.total_net_wt);
        document.getElementById('bsrStatFinal').textContent = fmtNum(summary.total_final_wt);

        document.getElementById('bsrFootQty').textContent = fmtNum(summary.total_qty);
        document.getElementById('bsrFootGross').textContent = fmtNum(summary.total_gross_wt);
        document.getElementById('bsrFootNet').textContent = fmtNum(summary.total_net_wt);
        document.getElementById('bsrFootFinal').textContent = fmtNum(summary.total_final_wt);

        if (periodEl) {
            periodEl.textContent = rowCount + ' scan record(s) from ' + fmtDisplayDate(fromDate) + ' to ' + fmtDisplayDate(toDate);
        }
        if (infoEl) {
            infoEl.textContent = 'Showing ' + rowCount + ' scan record(s)';
        }
    }

    function loadReport() {
        if (!bodyEl) return;
        bodyEl.innerHTML = '<tr class="empty-row"><td colspan="19" class="empty-msg">Loading…</td></tr>';
        if (footEl) footEl.hidden = true;

        fetch('ajax/list-barcode-scan-report.php?' + getQueryParams().toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    bodyEl.innerHTML = '<tr class="empty-row"><td colspan="19" class="empty-msg">' + esc((data && data.message) || 'Failed to load') + '</td></tr>';
                    updateSummary({}, '', '', 0);
                    return;
                }
                var rows = data.rows || [];
                updateSummary(data.summary || {}, data.from_date, data.to_date, rows.length);

                if (!rows.length) {
                    bodyEl.innerHTML = '<tr class="empty-row"><td colspan="19" class="empty-msg">No scan records for selected filters.</td></tr>';
                    return;
                }

                if (footEl) footEl.hidden = false;
                bodyEl.innerHTML = rows.map(function (r) {
                    var st = String(r.scan_status || '').toLowerCase();
                    var badge = st === 'unknown'
                        ? '<span class="bsr-badge bsr-badge--unknown">Unknown</span>'
                        : '<span class="bsr-badge bsr-badge--matched">Matched</span>';
                    return '<tr>'
                        + '<td class="th-num">' + esc(r.sr) + '</td>'
                        + '<td>' + esc(r.scan_date) + '</td>'
                        + '<td>' + esc(r.scan_time) + '</td>'
                        + '<td>' + badge + '</td>'
                        + '<td><strong>' + esc(r.scan_code) + '</strong></td>'
                        + '<td>' + esc(r.barcode) + '</td>'
                        + '<td>' + esc(r.rfid_code) + '</td>'
                        + '<td>' + esc(r.product_name) + '</td>'
                        + '<td>' + esc(r.article) + '</td>'
                        + '<td>' + esc(r.metal_name) + '</td>'
                        + '<td>' + esc(r.branch_name) + '</td>'
                        + '<td>' + esc(r.location) + '</td>'
                        + '<td class="th-num">' + fmtNum(r.qty) + '</td>'
                        + '<td class="th-num">' + fmtNum(r.gross_wt) + '</td>'
                        + '<td class="th-num">' + fmtNum(r.net_wt) + '</td>'
                        + '<td class="th-num">' + fmtNum(r.final_wt) + '</td>'
                        + '<td>' + esc(r.voucher_type) + '</td>'
                        + '<td>' + esc(r.invoice_no) + '</td>'
                        + '<td>' + esc(r.user_name) + '</td>'
                        + '</tr>';
                }).join('');
            })
            .catch(function () {
                bodyEl.innerHTML = '<tr class="empty-row"><td colspan="19" class="empty-msg">Network error</td></tr>';
                updateSummary({}, '', '', 0);
            });
    }

    function closeExportMenu() {
        if (exportMenu) exportMenu.hidden = true;
    }

    if (exportToggle && exportMenu) {
        exportToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            exportMenu.hidden = !exportMenu.hidden;
        });
        document.addEventListener('click', closeExportMenu);
        exportMenu.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    if (exportXlsx) {
        exportXlsx.addEventListener('click', function () {
            closeExportMenu();
            window.location.href = 'ajax/export-barcode-scan-report-excel.php?' + getQueryParams().toString();
        });
    }
    if (exportPdf) {
        exportPdf.addEventListener('click', function () {
            closeExportMenu();
            window.location.href = 'ajax/export-barcode-scan-report-pdf.php?' + getQueryParams().toString();
        });
    }

    if (refreshBtn) refreshBtn.addEventListener('click', loadReport);
    if (fromEl) fromEl.addEventListener('change', loadReport);
    if (toEl) toEl.addEventListener('change', loadReport);
    if (statusEl) statusEl.addEventListener('change', loadReport);
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadReport, 350);
        });
    }

    loadReport();
})();
</script>
</body>
</html>
