<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_gst_reports_catalog.php';

if (empty($_SESSION['Admin']) && empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';

/* Map legacy report keys to the 4 filing reports */
$gst_legacy_map = [
    'b2b_sales' => ['gstr1', 'b2b'],
    'b2c_large' => ['gstr1', 'b2c_large'],
    'b2c_small' => ['gstr1', 'b2c_small'],
    'credit_note' => ['gstr1', 'credit_note'],
    'debit_note' => ['gstr1', 'debit_note'],
    'hsn_summary' => ['gstr1', 'hsn'],
    'gstr3b_summary' => ['gstr3b', ''],
    'gstr2b_recon' => ['gstr2b', ''],
    'gstr9_summary' => ['gstr9', ''],
];
if (isset($gst_legacy_map[$type])) {
    $mapped = $gst_legacy_map[$type];
    $q = ['type' => $mapped[0]];
    if ($mapped[1] !== '') {
        $q['section'] = $mapped[1];
    }
    if (!empty($_GET['from_date'])) {
        $q['from_date'] = (string) $_GET['from_date'];
    }
    if (!empty($_GET['to_date'])) {
        $q['to_date'] = (string) $_GET['to_date'];
    }
    header('Location: gst-report.php?' . http_build_query($q));
    exit;
}

if (!auragold_gst_report_is_valid($type)) {
    header('Location: gst-reports.php');
    exit;
}

$meta = auragold_gst_report_meta($type);
$lbl_title = $meta['label'] ?? 'GST Report';
$purpose = $meta['purpose'] ?? '';
$section = isset($_GET['section']) ? trim((string) $_GET['section']) : '';
$gstr1_sections = ($type === 'gstr1') ? auragold_gst_gstr1_sections() : [];
if ($type === 'gstr1' && ($section === '' || !isset($gstr1_sections[$section]))) {
    $section = 'b2b';
}

$from_date = isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : date('Y-m-t');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = date('Y-m-t');
}

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container gst-report-page ageing-report-page">
    <div class="main-content">
        <div class="page-container">
            <h1 class="sr-only"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></h1>

            <div class="ageing-shell gst-shell">
                <div class="ageing-shell-top">
                    <div class="ageing-tabs-row gst-report-top">
                        <div class="gst-report-heading">
                            <a href="gst-reports.php" class="gst-report-hub-link" title="All GST Reports">
                                <i class="feather icon-grid"></i>
                                <span>All GST Reports</span>
                            </a>
                            <div class="gst-report-title-wrap">
                                <span class="gst-report-page-title"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($purpose !== ''): ?>
                                    <span class="gst-report-purpose"><?php echo htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="toolbar-actions ageing-tabs-row__actions">
                            <button type="button" class="btn-icon-tight" id="gstBtnRefresh" title="Refresh">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                            <button type="button" class="btn-ageing-primary" id="gstBtnExportCsv">
                                <i class="feather icon-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ageing-toolbar gst-toolbar">
                    <div class="toolbar-inner">
                        <div class="field-group">
                            <label class="field-label" for="gstFromDate">From</label>
                            <input type="date" id="gstFromDate" class="form-control-sm gst-date-input" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="gstToDate">To</label>
                            <input type="date" id="gstToDate" class="form-control-sm gst-date-input" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field-group gst-apply-group">
                            <label class="field-label">&nbsp;</label>
                            <button type="button" class="btn-ageing-primary" id="gstBtnApply">Apply</button>
                        </div>
                        <div class="gst-report-note-wrap">
                            <span class="gst-report-note" id="gstReportNote"></span>
                        </div>
                    </div>
                </div>

                <?php if ($type === 'gstr1' && $gstr1_sections): ?>
                <div class="gst-section-tabs" id="gstSectionTabs" role="tablist">
                    <?php foreach ($gstr1_sections as $sec): ?>
                    <button type="button"
                        class="gst-section-tab<?php echo ($section === $sec['key']) ? ' is-active' : ''; ?>"
                        data-section="<?php echo htmlspecialchars($sec['key'], ENT_QUOTES, 'UTF-8'); ?>"
                        role="tab"
                        aria-selected="<?php echo ($section === $sec['key']) ? 'true' : 'false'; ?>">
                        <?php echo htmlspecialchars($sec['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="ageing-panel gst-panel">
                    <div class="gst-summary-bar" id="gstSummaryBar" hidden></div>
                    <div class="table-responsive ageing-table-wrap gst-table-wrap">
                        <table class="table ageing-table gst-report-table" id="gstReportTable">
                            <thead id="gstTableHead">
                                <tr><th>Loading…</th></tr>
                            </thead>
                            <tbody id="gstTableBody">
                                <tr><td class="empty-msg" style="text-align:center;padding:40px;color:#64748b;">Loading data…</td></tr>
                            </tbody>
                            <tfoot id="gstTableFoot" hidden></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Self-contained report shell (same pattern as reward-point / ageing reports) */
.gst-report-page {
    --ageing-navy: #11294b;
    --ageing-navy-dark: #0c1d36;
    --ageing-gold: #c9a227;
    --ageing-gold-hover: #d4af37;
    --ageing-gold-muted: #f5eed9;
    --ageing-border: #c5cddf;
    --ageing-bg: #eef1f6;
    --ageing-total-row: #e8eef5;
}
.gst-report-page.layout-container,
.gst-report-page.ageing-report-page.layout-container {
    padding: 10px clamp(8px, 1vw, 16px) 20px !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    box-sizing: border-box;
    background: var(--ageing-bg);
    min-height: calc(100vh - 60px);
}
.gst-report-page .main-content,
.gst-report-page .page-container {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box;
}
.gst-report-page .ageing-shell,
.gst-report-page .gst-shell {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    background: #fff;
    border: 1px solid var(--ageing-border);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.07);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.gst-report-page .ageing-shell-top {
    padding: 14px 18px 0;
    background: #fff;
    flex-shrink: 0;
}
.gst-report-page .ageing-tabs-row,
.gst-report-page .gst-report-top {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ageing-border);
    margin-bottom: 0;
}
.gst-report-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 16px;
    min-width: 0;
    flex: 1 1 auto;
}
.gst-report-hub-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #475569;
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 7px 12px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    white-space: nowrap;
    flex-shrink: 0;
}
.gst-report-hub-link:hover {
    color: #0f172a;
    border-color: var(--ageing-gold);
    text-decoration: none;
}
.gst-report-hub-link .feather { width: 15px; height: 15px; }
.gst-report-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.gst-report-page-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--ageing-navy);
    line-height: 1.25;
}
.gst-report-purpose {
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.3;
}
.gst-report-page .toolbar-actions {
    display: flex !important;
    flex-direction: row !important;
    align-items: center;
    gap: 10px;
    margin-left: auto;
    flex-shrink: 0;
}
.gst-report-page .btn-icon-tight {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #c9d4e3;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    color: #475569;
    padding: 0;
}
.gst-report-page .btn-icon-tight:hover {
    border-color: var(--ageing-gold);
    color: var(--ageing-navy);
}
.gst-report-page .btn-icon-tight .feather { width: 16px; height: 16px; }
.gst-report-page .btn-ageing-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 38px;
    padding: 0 18px;
    background: var(--ageing-navy);
    color: #fff !important;
    border: 2px solid var(--ageing-gold);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.gst-report-page .btn-ageing-primary:hover {
    background: var(--ageing-navy-dark);
    color: #fff !important;
}
.gst-report-page .btn-ageing-primary .feather { width: 15px; height: 15px; }

.gst-report-page .ageing-toolbar,
.gst-report-page .gst-toolbar {
    margin: 0;
    background: linear-gradient(180deg, #fbfcfe 0%, #f4f6fa 100%);
    border-bottom: 1px solid var(--ageing-border);
    flex-shrink: 0;
}
.gst-report-page .toolbar-inner {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 20px;
    padding: 14px 18px 16px;
    width: 100%;
    box-sizing: border-box;
}
.gst-report-page .field-group {
    display: flex !important;
    flex-direction: column !important;
    gap: 5px;
    min-width: 150px;
    margin: 0;
}
.gst-report-page .gst-apply-group {
    min-width: auto;
}
.gst-report-page .field-label {
    font-size: 11px;
    font-weight: 700;
    color: #5c6b7a;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.2;
}
.gst-report-page .form-control-sm,
.gst-report-page .gst-date-input {
    height: 38px !important;
    min-width: 160px;
    padding: 7px 12px !important;
    border: 1px solid #c9d4e3 !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    color: #1e293b;
    background: #fff !important;
    box-sizing: border-box;
}
.gst-report-note-wrap {
    flex: 1 1 220px;
    min-width: 180px;
    display: flex;
    align-items: flex-end;
    padding-bottom: 8px;
}
.gst-report-note {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.4;
}
.gst-section-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 18px;
    background: #fff;
    border-bottom: 1px solid var(--ageing-border);
    flex-shrink: 0;
}
.gst-section-tab {
    appearance: none;
    border: 1px solid #dbe3ef;
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    padding: 7px 12px;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    line-height: 1.2;
}
.gst-section-tab:hover { border-color: var(--ageing-gold); color: var(--ageing-navy); }
.gst-section-tab.is-active {
    background: var(--ageing-navy);
    border-color: var(--ageing-gold);
    color: #fff;
}

.gst-report-page .ageing-panel,
.gst-report-page .gst-panel {
    min-width: 0;
    width: 100%;
    padding: 0;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}
.gst-summary-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 16px;
    padding: 10px 18px;
    background: #fffbf3;
    border-bottom: 1px solid #f0e6cf;
    font-size: 0.82rem;
    color: #334155;
    flex-shrink: 0;
}
.gst-summary-bar[hidden] { display: none !important; }
.gst-summary-bar strong { color: #0f172a; font-weight: 700; }
.gst-summary-bar .gst-sum-sep { color: #cbd5e1; user-select: none; }

.gst-report-page .ageing-table-wrap,
.gst-report-page .gst-table-wrap {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0;
    max-height: calc(100vh - 280px);
    overflow: auto;
    background: #fff;
    -webkit-overflow-scrolling: touch;
}
.gst-report-page .ageing-table,
.gst-report-page .gst-report-table,
.gst-report-page #gstReportTable {
    width: 100% !important;
    min-width: 900px;
    border-collapse: collapse;
    margin: 0 !important;
    font-size: 12px;
    table-layout: auto;
}
.gst-report-page #gstReportTable thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--ageing-navy) !important;
    padding: 11px 12px !important;
    text-align: left;
    font-weight: 600;
    color: #fff !important;
    border-bottom: none !important;
    border-top: none !important;
    white-space: nowrap;
    vertical-align: middle;
}
.gst-report-page #gstReportTable thead th.num { text-align: right; }
.gst-report-page #gstReportTable tbody td {
    padding: 10px 12px !important;
    border-bottom: 1px solid #eef2f7 !important;
    color: #475569;
    background: #fff;
    vertical-align: middle;
}
.gst-report-page #gstReportTable tbody tr:hover td { background: #f8fafc; }
.gst-report-page #gstReportTable td.num,
.gst-report-page #gstReportTable th.num {
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.gst-report-page #gstReportTable tbody .empty-msg,
.gst-report-page #gstReportTable tbody td[colspan] {
    text-align: center !important;
    color: #94a3b8;
    padding: 52px 16px !important;
}
.gst-report-page #gstReportTable tfoot td {
    font-weight: 700;
    background: var(--ageing-total-row) !important;
    padding: 11px 12px !important;
    border-top: 1px solid var(--ageing-border);
    color: var(--ageing-navy);
    position: sticky;
    bottom: 0;
    z-index: 1;
}

@media (max-width: 768px) {
    .gst-report-page.layout-container { padding: 8px !important; }
    .gst-report-page .toolbar-inner { gap: 12px; padding: 12px; }
    .gst-report-page .form-control-sm,
    .gst-report-page .gst-date-input { min-width: 100%; width: 100%; }
    .gst-report-page .field-group { min-width: calc(50% - 8px); flex: 1 1 calc(50% - 8px); }
    .gst-report-page .gst-apply-group { flex: 0 0 auto; }
    .gst-report-note-wrap { flex-basis: 100%; padding-bottom: 0; }
    .gst-report-page .ageing-table-wrap { max-height: calc(100vh - 320px); }
}
</style>

<script>
(function () {
    var reportType = <?php echo json_encode($type, JSON_UNESCAPED_UNICODE); ?>;
    var currentSection = <?php echo json_encode($section, JSON_UNESCAPED_UNICODE); ?>;
    var lastPayload = null;

    function fmtNum(n) {
        var x = Number(n);
        if (!isFinite(x)) return '0.00';
        return x.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function isMoneyKey(k) {
        return /value|amount|tax|cgst|sgst|igst|total|taxable|payable|itc|output|docs/i.test(k) && k !== 'invoice_count' && k !== 'count';
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setActiveSectionTab(sec) {
        currentSection = sec || '';
        document.querySelectorAll('#gstSectionTabs .gst-section-tab').forEach(function (btn) {
            var on = btn.getAttribute('data-section') === currentSection;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function loadReport() {
        var from = document.getElementById('gstFromDate').value;
        var to = document.getElementById('gstToDate').value;
        var body = document.getElementById('gstTableBody');
        body.innerHTML = '<tr><td colspan="20" class="empty-msg">Loading data…</td></tr>';

        var url = 'ajax/get-gst-report.php?type=' + encodeURIComponent(reportType)
            + '&from_date=' + encodeURIComponent(from)
            + '&to_date=' + encodeURIComponent(to);
        if (reportType === 'gstr1' && currentSection) {
            url += '&section=' + encodeURIComponent(currentSection);
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    body.innerHTML = '<tr><td colspan="20" class="empty-msg" style="color:#b91c1c;">'
                        + esc((data && data.message) || 'Failed to load') + '</td></tr>';
                    return;
                }
                lastPayload = data;
                if (data.section) setActiveSectionTab(data.section);
                renderReport(data);
                var u = new URL(window.location.href);
                u.searchParams.set('type', reportType);
                u.searchParams.set('from_date', from);
                u.searchParams.set('to_date', to);
                if (reportType === 'gstr1' && currentSection) {
                    u.searchParams.set('section', currentSection);
                } else {
                    u.searchParams.delete('section');
                }
                history.replaceState({}, '', u.toString());
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="20" class="empty-msg" style="color:#b91c1c;">Network error</td></tr>';
            });
    }

    function renderReport(data) {
        var cols = data.columns || [];
        var rows = data.rows || [];
        var totals = data.totals || {};
        var noteEl = document.getElementById('gstReportNote');
        if (noteEl) noteEl.textContent = data.note || '';

        var head = document.getElementById('gstTableHead');
        var body = document.getElementById('gstTableBody');
        var foot = document.getElementById('gstTableFoot');
        var sumBar = document.getElementById('gstSummaryBar');

        if (!cols.length) {
            head.innerHTML = '<tr><th>Info</th></tr>';
            body.innerHTML = '<tr><td class="empty-msg">No columns / no data</td></tr>';
            foot.hidden = true;
            sumBar.hidden = true;
            return;
        }

        head.innerHTML = '<tr>' + cols.map(function (c) {
            var align = c.align === 'right' ? ' class="num"' : '';
            return '<th' + align + '>' + esc(c.label || c.key) + '</th>';
        }).join('') + '</tr>';

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="' + cols.length + '" class="empty-msg">No rows for this period</td></tr>';
            foot.hidden = true;
        } else {
            body.innerHTML = rows.map(function (row) {
                return '<tr>' + cols.map(function (c) {
                    var v = row[c.key];
                    var align = (c.align === 'right' || isMoneyKey(c.key)) ? ' class="num"' : '';
                    var txt = (c.align === 'right' || isMoneyKey(c.key)) && typeof v === 'number' ? fmtNum(v) : esc(v);
                    if ((c.align === 'right' || isMoneyKey(c.key)) && typeof v !== 'number' && v !== '' && v != null && !isNaN(Number(v))) {
                        txt = fmtNum(v);
                    }
                    return '<td' + align + '>' + txt + '</td>';
                }).join('') + '</tr>';
            }).join('');

            var hasFooter = false;
            var footCells = cols.map(function (c, idx) {
                if (idx === 0) return '<td>Total (' + (totals.count != null ? totals.count : rows.length) + ')</td>';
                if (totals[c.key] != null && typeof totals[c.key] === 'number' && c.key !== 'count' && c.key !== 'invoice_count') {
                    hasFooter = true;
                    return '<td class="num">' + fmtNum(totals[c.key]) + '</td>';
                }
                if (isMoneyKey(c.key) && totals[c.key] != null) {
                    hasFooter = true;
                    return '<td class="num">' + fmtNum(totals[c.key]) + '</td>';
                }
                return '<td></td>';
            }).join('');
            if (hasFooter) {
                foot.innerHTML = '<tr>' + footCells + '</tr>';
                foot.hidden = false;
            } else {
                foot.hidden = true;
            }
        }

        var chips = [];
        if (totals.output_tax != null) chips.push('Output Tax: <strong>' + fmtNum(totals.output_tax) + '</strong>');
        if (totals.itc != null) chips.push('ITC: <strong>' + fmtNum(totals.itc) + '</strong>');
        if (totals.payable != null) chips.push('Payable: <strong>' + fmtNum(totals.payable) + '</strong>');
        if (totals.tax != null && totals.output_tax == null) chips.push('Total Tax: <strong>' + fmtNum(totals.tax) + '</strong>');
        if (totals.invoice_value != null) chips.push('Invoice Value: <strong>' + fmtNum(totals.invoice_value) + '</strong>');
        if (totals.doc_value != null) chips.push('Value: <strong>' + fmtNum(totals.doc_value) + '</strong>');
        if (totals.count != null) chips.push('Rows: <strong>' + totals.count + '</strong>');
        if (chips.length) {
            sumBar.innerHTML = chips.join('<span class="gst-sum-sep">|</span>');
            sumBar.hidden = false;
        } else {
            sumBar.hidden = true;
        }
    }

    function exportCsv() {
        if (!lastPayload || !lastPayload.columns) return;
        var cols = lastPayload.columns;
        var rows = lastPayload.rows || [];
        var lines = [cols.map(function (c) { return '"' + String(c.label || c.key).replace(/"/g, '""') + '"'; }).join(',')];
        rows.forEach(function (row) {
            lines.push(cols.map(function (c) {
                var v = row[c.key];
                if (v == null) v = '';
                return '"' + String(v).replace(/"/g, '""') + '"';
            }).join(','));
        });
        var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'gst-' + reportType + '-' + document.getElementById('gstFromDate').value + '-to-' + document.getElementById('gstToDate').value + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.getElementById('gstBtnApply').addEventListener('click', loadReport);
    document.getElementById('gstBtnRefresh').addEventListener('click', loadReport);
    document.getElementById('gstBtnExportCsv').addEventListener('click', exportCsv);
    document.getElementById('gstFromDate').addEventListener('change', loadReport);
    document.getElementById('gstToDate').addEventListener('change', loadReport);

    var tabs = document.getElementById('gstSectionTabs');
    if (tabs) {
        tabs.addEventListener('click', function (e) {
            var btn = e.target.closest('.gst-section-tab');
            if (!btn) return;
            setActiveSectionTab(btn.getAttribute('data-section') || 'b2b');
            loadReport();
        });
    }

    loadReport();
})();
</script>

<?php include 'footer-script.php'; ?>
