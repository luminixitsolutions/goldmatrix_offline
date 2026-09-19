<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

/** Product category groups — each has 6 metrics (Jewelsteps-style). */
$sp_groups = [
    'gold' => 'Gold',
    'silver' => 'Silver',
    'platinum' => 'Platinum',
    'diamond_stones' => 'Diamond & Stones',
    'imitation_watches' => 'Imitation Or Watches',
    'other_services' => 'Other Or Services',
];

$sp_metrics = [
    'qty' => 'Qty',
    'gross_wt' => 'GrossWt',
    'net_wt' => 'NetWt',
    'd_ct' => 'D CT',
    'sale_amount' => 'Sale Amount',
    'gross_profit' => 'Gross Profit',
];

$sp_scheme_sub = [
    'no_of_scheme' => 'NO OF SCHEME',
    'scheme_amount' => 'SCHEME AMOUNT',
];

require_once __DIR__ . '/includes/auragold_salesperson_performance_data.php';

$default_range_label = '';
if (!empty($_GET['sp_from']) && !empty($_GET['sp_to'])) {
    $default_range_label = trim((string) $_GET['sp_from']) . ' - ' . trim((string) $_GET['sp_to']);
} else {
    $todaySp = new DateTimeImmutable('today');
    $ySp     = (int) $todaySp->format('Y');
    $mSp     = (int) $todaySp->format('n');
    $fyStart = $mSp >= 4 ? $ySp : ($ySp - 1);
    $default_range_label = sprintf('01-04-%d - 31-03-%d', $fyStart, $fyStart + 1);
}
$sp_range = auragold_sale_analysis_parse_range($default_range_label);
$default_range = $sp_range['label'];

/** @var array<int, array<string, mixed>> */
$sp_rows = [];
global $conn;
if (isset($conn) && $conn instanceof mysqli) {
    $sp_rows = auragold_salesperson_performance_fetch_rows(
        $conn,
        $sp_range['from_ymd'],
        $sp_range['to_ymd'],
        $sp_groups
    );
}

$row_count = count($sp_rows);
$sp_table_colspan = 2 + (count($sp_groups) * count($sp_metrics)) + count($sp_scheme_sub);

$sp_default_group_order = array_merge(array_keys($sp_groups), ['_scheme']);
$sp_default_metrics_json = json_encode(array_keys($sp_metrics), JSON_UNESCAPED_UNICODE);
$sp_default_scheme_json = json_encode(array_keys($sp_scheme_sub), JSON_UNESCAPED_UNICODE);
$sp_default_groups_json = json_encode($sp_default_group_order, JSON_UNESCAPED_UNICODE);

$DASHBOARD_PAGE_TITLE = 'Salesperson Performance';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .sp-wrap {
        max-width: 100%;
        --sp-gold: #c9a227;
        --sp-gold-mid: #b8941f;
        --sp-gold-dark: #8b6914;
        --sp-navy: #11294b;
        --sp-navy-deep: #0c1f38;
    }
    .sp-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--sp-gold-mid) 45%, var(--sp-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .sp-page-title { color: var(--sp-gold-dark); -webkit-text-fill-color: var(--sp-gold-dark); }
    }
    .sp-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 1rem;
    }
    .sp-subnav a {
        display: inline-block;
        padding: 0.35rem 0.9rem;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid rgba(17, 41, 75, 0.15);
        color: #334155;
        background: #fff;
    }
    .sp-subnav a:hover { background: #fffbf0; border-color: var(--sp-gold-mid); color: var(--sp-gold-dark); }
    .sp-subnav a.sp-subnav-active {
        background: linear-gradient(180deg, #5b4b9a 0%, #4338ca 100%);
        border-color: #3730a3;
        color: #fff !important;
    }
    .sp-toolbar .form-control.sp-date-range {
        max-width: 260px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .sp-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-sp-outline {
        border: 1px solid var(--sp-gold-mid) !important;
        color: var(--sp-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 0.85rem;
    }
    .btn-sp-outline:hover { background: #fffbf0 !important; border-color: var(--sp-gold) !important; }
    .btn-sp-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--sp-gold-mid) 55%, var(--sp-gold-dark) 100%) !important;
        border: 1px solid var(--sp-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
        text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    .btn-sp-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .sp-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        font-size: 10px;
        font-weight: 700;
        line-height: 18px;
        color: #fff;
        background: #dc2626;
        border-radius: 999px;
    }
    .sp-filter-wrap { position: relative; display: inline-block; }
    .sp-table-outer {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .sp-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .sp-table-main {
        margin-bottom: 0;
        font-size: 12px;
        min-width: max-content;
    }
    .sp-table-main thead th {
        background: linear-gradient(180deg, var(--sp-navy) 0%, var(--sp-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-bottom: 2px solid var(--sp-gold-dark) !important;
        white-space: nowrap;
        padding: 8px 10px;
        vertical-align: middle;
        text-align: center;
    }
    .sp-table-main thead th.sp-th-fixed {
        text-align: left;
        position: sticky;
        left: 0;
        z-index: 3;
        box-shadow: 2px 0 6px rgba(0,0,0,.08);
    }
    .sp-table-main thead tr:first-child th.sp-th-fixed {
        z-index: 4;
    }
    .sp-table-main thead th.sp-group-hdr {
        font-size: 11px;
        letter-spacing: 0.02em;
    }
    .sp-table-main thead th .sp-col-settings {
        margin-left: 4px;
        opacity: 0.9;
        cursor: default;
    }
    .sp-table-main tbody td {
        padding: 7px 10px;
        vertical-align: middle;
        border-color: #eef0f3;
        white-space: nowrap;
    }
    .sp-table-main tbody td.sp-td-name {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
        font-weight: 600;
        box-shadow: 2px 0 6px rgba(0,0,0,.06);
    }
    .sp-table-main tbody tr:nth-child(even) td.sp-td-name { background: #fafbfc; }
    .sp-table-main tbody tr:hover td { background: #fff9ec !important; }
    .sp-table-main tbody tr:hover td.sp-td-name { background: #fff3dc !important; }
    .sp-num { text-align: right; font-variant-numeric: tabular-nums; }
    .sp-footer-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 14px;
        background: #f8fafc;
        border-top: 1px solid rgba(201, 162, 39, 0.2);
        font-size: 13px;
        color: #475569;
    }
    .sp-pager { display: flex; align-items: center; gap: 6px; }
    .sp-pager button {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        color: #64748b;
    }
    .sp-pager button:disabled { opacity: 0.45; cursor: not-allowed; }
    .sp-export-dd { position: relative; display: inline-block; }
    .sp-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .sp-export-dd > summary::-webkit-details-marker { display: none; }
    .sp-export-menu {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 4px;
        min-width: 140px;
        padding: 6px 0;
        background: #fff;
        border: 1px solid rgba(201, 162, 39, 0.35);
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0,0,0,.1);
        z-index: 20;
    }
    .sp-export-menu a {
        display: block;
        padding: 8px 14px;
        color: #374151;
        text-decoration: none;
        font-size: 13px;
    }
    .sp-export-menu a:hover { background: #fffbf0; color: var(--sp-gold-dark); }
    .sp-qty-link {
        color: #2563eb;
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 2px;
        font-weight: 600;
    }
    .sp-qty-link:hover { color: #1d4ed8; }
    .sp-detail-modal {
        --sp-navy: #11294b;
        --sp-navy-deep: #0c1f38;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow-x: hidden;
        overflow-y: auto;
        outline: 0;
        z-index: 1050;
    }
    .sp-detail-modal.show {
        display: flex !important;
        align-items: flex-start;
        justify-content: center;
        padding: 88px 16px 32px;
    }
    .sp-detail-modal .modal-dialog {
        margin: 0 auto;
        max-width: 960px;
        width: 100%;
        max-height: calc(100vh - 120px);
    }
    .sp-detail-modal .modal-content {
        border: 1px solid rgba(201, 162, 39, 0.35);
        border-radius: 10px;
        overflow: hidden;
        max-height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
    }
    .sp-detail-modal .modal-body {
        overflow-y: auto;
        flex: 1 1 auto;
    }
    body.sp-modal-open { overflow: hidden; }
    #spModalBackdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 1040;
    }
    .sp-detail-modal .modal-header {
        background: #11294b !important;
        color: #ffffff !important;
        border-bottom: 2px solid #8b6914;
        padding: 14px 18px;
        flex-shrink: 0;
    }
    .sp-detail-modal .modal-title {
        font-size: 1rem;
        font-weight: 700;
        color: #ffffff !important;
    }
    .sp-detail-modal .modal-sub {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.92) !important;
        margin-top: 2px;
    }
    .sp-detail-modal .close {
        color: #fff !important;
        opacity: 0.95;
        text-shadow: none;
        position: relative;
        z-index: 2;
    }
    .sp-detail-table {
        font-size: 12px;
        margin-bottom: 0;
        width: 100%;
        table-layout: auto;
    }
    .sp-detail-modal .sp-detail-table thead th {
        background: #11294b !important;
        font-weight: 700;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff;
        white-space: nowrap;
        border-color: rgba(255, 255, 255, 0.15) !important;
        border-bottom: 2px solid #8b6914 !important;
        padding: 8px 10px;
        vertical-align: middle;
        text-align: left;
    }
    .sp-detail-modal .sp-detail-table thead th.sp-num {
        text-align: right;
    }
    .sp-detail-modal .sp-detail-table tbody td {
        vertical-align: middle;
        color: #1e293b;
        padding: 7px 10px;
        border-color: #eef0f3;
    }
    .sp-detail-modal .sp-detail-table tbody tr:nth-child(even) td {
        background: #fafbfc;
    }
    .sp-detail-modal .sp-detail-table .sp-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .sp-detail-doc-link { color: #2563eb; font-weight: 600; text-decoration: none; }
    .sp-detail-doc-link:hover { text-decoration: underline; color: #1d4ed8; }
</style>
HTML;

require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="sp-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <h1 class="sp-page-title mb-0">Salesperson Performance</h1>
        <div class="sp-toolbar d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control sp-date-range border-start-0" id="spDateRange" value="<?php echo htmlspecialchars($default_range); ?>" readonly aria-label="Date range">
            </div>
            <div class="sp-filter-wrap" title="Filters (placeholder)">
                <button type="button" class="btn btn-sp-outline position-relative" id="spFilter" aria-label="Filter">
                    <i class="feather icon-filter"></i>
                    <span class="sp-badge">2</span>
                </button>
            </div>
            <button type="button" class="btn btn-sp-outline" id="spRefresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
            <details class="sp-export-dd" data-fs-root="#spMainTable" data-fs-file="salesperson-performance" data-fs-title="Salesperson Performance">
                <summary class="btn btn-sp-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="sp-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <nav class="sp-subnav" aria-label="Financial statement analysis">
        <a href="sale-analysis.php">Sale Analysis</a>
        <a href="gold-silver-financial-analysis.php">Gold Silver Analysis</a>
        <a href="diamond-stone-financial-analysis.php">Diamond &amp; Stone Analysis</a>
        <a href="imitation-watches-financial-analysis.php">Imitation Or Watches</a>
        <a href="salesperson-performance.php" class="sp-subnav-active">Salesperson Performance</a>
        <a href="vendor-report.php">Vendor Report</a>
    </nav>

    <div class="sp-table-outer">
        <div class="sp-table-scroll">
            <table id="spMainTable" class="table sp-table-main table-bordered"
                data-sp-default-groups="<?php echo htmlspecialchars($sp_default_groups_json, ENT_QUOTES, 'UTF-8'); ?>"
                data-sp-default-metrics="<?php echo htmlspecialchars($sp_default_metrics_json, ENT_QUOTES, 'UTF-8'); ?>"
                data-sp-default-scheme-metrics="<?php echo htmlspecialchars($sp_default_scheme_json, ENT_QUOTES, 'UTF-8'); ?>">
                <thead>
                    <tr>
                        <th rowspan="2" class="sp-th-fixed" data-sp-fixed="1" data-sp-role="name">Sales Person Name</th>
                        <th rowspan="2" data-sp-fixed="1" data-sp-role="bills">No Of Bills</th>
                        <?php foreach ($sp_groups as $slug => $glabel): ?>
                        <th colspan="6" class="sp-group-hdr sp-group-top" data-sp-group="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" data-sp-cols="6">
                            <?php echo htmlspecialchars($glabel); ?> (group)
                            <span class="sp-group-drag" title="Drag to reorder groups"><i class="feather icon-move" aria-hidden="true"></i></span>
                        </th>
                        <?php endforeach; ?>
                        <th colspan="2" class="sp-group-hdr sp-group-top" data-sp-group="_scheme" data-sp-cols="2">
                            SCHEME (group)
                            <span class="sp-group-drag" title="Drag to reorder groups"><i class="feather icon-move" aria-hidden="true"></i></span>
                        </th>
                    </tr>
                    <tr>
                        <?php foreach ($sp_groups as $slug => $_glabel): ?>
                            <?php foreach ($sp_metrics as $mk => $mlabel): ?>
                        <th data-sp-group="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" data-sp-metric="<?php echo htmlspecialchars($mk, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($mlabel); ?>
                            <span class="sp-metric-drag" title="Drag to reorder columns in group (same order for all categories)"><i class="feather icon-move" aria-hidden="true"></i></span>
                        </th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php foreach ($sp_scheme_sub as $sk => $slabel): ?>
                        <th data-sp-group="_scheme" data-sp-metric="<?php echo htmlspecialchars($sk, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($slabel); ?>
                            <span class="sp-metric-drag" title="Drag to reorder scheme columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                            <?php echo $sk === 'scheme_amount' ? ' <i class="feather icon-settings sp-col-settings" title="Column settings (placeholder)"></i>' : ''; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($row_count === 0): ?>
                    <tr>
                        <td class="text-center text-muted py-4" colspan="<?php echo (int) $sp_table_colspan; ?>">No sale lines in this date range (sales + POS). Adjust the range and use Refresh, or check branch access.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($sp_rows as $row): ?>
                    <?php
                    $bills_val = (string) ($row['bills'] ?? '0');
                    $bills_clickable = is_numeric($bills_val) && (int) $bills_val > 0;
                    ?>
                    <tr>
                        <td class="sp-td-name" data-sp-fixed="1" data-sp-role="name"><?php echo htmlspecialchars($row['name']); ?></td>
                        <td class="sp-num<?php echo $bills_clickable ? ' sp-qty-link' : ''; ?>"
                            data-sp-fixed="1"
                            data-sp-role="bills"
                            <?php if ($bills_clickable): ?>
                            data-sp-salesperson="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-sp-bills="<?php echo htmlspecialchars($bills_val, ENT_QUOTES, 'UTF-8'); ?>"
                            title="Click to view bills"
                            role="button"
                            tabindex="0"
                            <?php endif; ?>><?php echo htmlspecialchars($bills_val); ?></td>
                        <?php foreach ($sp_groups as $slug => $_g): ?>
                            <?php
                            $gdata = isset($row[$slug]) && is_array($row[$slug]) ? $row[$slug] : [];
                            foreach ($sp_metrics as $mk => $_ml):
                                $cell = isset($gdata[$mk]) ? (string) $gdata[$mk] : '';
                            ?>
                        <td class="sp-num" data-sp-group="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" data-sp-metric="<?php echo htmlspecialchars($mk, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cell); ?></td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <td class="sp-num" data-sp-group="_scheme" data-sp-metric="no_of_scheme"><?php echo htmlspecialchars($row['scheme']['no_of_scheme']); ?></td>
                        <td class="sp-num" data-sp-group="_scheme" data-sp-metric="scheme_amount"><?php echo htmlspecialchars($row['scheme']['scheme_amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sp-footer-bar">
            <span><?php echo $row_count > 0 ? 'Showing <strong>1</strong> to <strong>' . (int) $row_count . '</strong> of <strong>' . (int) $row_count . '</strong> entries' : 'Showing <strong>0</strong> entries'; ?></span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 small">Show</label>
                <select class="form-control form-control-sm" style="width:auto; min-width:120px;" disabled aria-label="Page size">
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                    <option>All Items</option>
                </select>
            </div>
            <div class="sp-pager">
                <button type="button" disabled aria-label="First">«</button>
                <button type="button" disabled aria-label="Previous">‹</button>
                <button type="button" disabled aria-label="Next">›</button>
                <button type="button" disabled aria-label="Last">»</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sp-detail-modal" id="spBillsDetailModal" tabindex="-1" role="dialog" aria-labelledby="spBillsDetailModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="spBillsDetailModalTitle">Bills</h5>
                    <div class="modal-sub" id="spBillsDetailModalSub"></div>
                </div>
                <button type="button" class="close sp-modal-close" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div id="spBillsDetailLoading" class="text-muted py-4 text-center" style="display:none;">Loading…</div>
                <div id="spBillsDetailError" class="alert alert-warning m-3 py-2" style="display:none;"></div>
                <div class="table-responsive" id="spBillsDetailWrap" style="display:none;">
                    <table class="table table-sm table-bordered table-hover sp-detail-table mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Doc No</th>
                                <th>Date</th>
                                <th>Party</th>
                                <th class="sp-num">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="spBillsDetailBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="spModalBackdrop" style="display:none;" aria-hidden="true"></div>

<script>
(function () {
    var inp = document.getElementById('spDateRange');
    var def = <?php echo json_encode($default_range, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    document.getElementById('spRefresh').addEventListener('click', function () {
        var v = (inp && inp.value) ? String(inp.value).trim() : def;
        var parts = v.split(/\s+\-\s+/);
        if (parts.length === 2 && parts[0].trim() !== '' && parts[1].trim() !== '') {
            window.location.href = 'salesperson-performance.php?sp_from=' + encodeURIComponent(parts[0].trim()) + '&sp_to=' + encodeURIComponent(parts[1].trim());
            return;
        }
        window.location.reload();
    });
    document.getElementById('spFilter').addEventListener('click', function () {
        alert('Filter panel can be connected to date range, branch, and salesperson filters.');
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-salesperson-col-reorder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.AuragoldSalespersonColReorder) {
        AuragoldSalespersonColReorder.init('#spMainTable', { storageKey: 'auragold_sp_perf_columns' });
    }

    var dateRangeDef = <?php echo json_encode($default_range, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var modal = document.getElementById('spBillsDetailModal');
    var backdrop = document.getElementById('spModalBackdrop');
    var titleEl = document.getElementById('spBillsDetailModalTitle');
    var subEl = document.getElementById('spBillsDetailModalSub');
    var loadEl = document.getElementById('spBillsDetailLoading');
    var errEl = document.getElementById('spBillsDetailError');
    var wrapEl = document.getElementById('spBillsDetailWrap');
    var bodyEl = document.getElementById('spBillsDetailBody');
    var tableEl = document.getElementById('spMainTable');

    function spCurrentDateRange() {
        var inp = document.getElementById('spDateRange');
        var v = (inp && inp.value) ? String(inp.value).trim() : dateRangeDef;
        var parts = v.split(/\s+\-\s+/);
        if (parts.length === 2) {
            return { from: parts[0].trim(), to: parts[1].trim(), label: v };
        }
        return { from: '', to: '', label: v };
    }

    function spEscHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function spShowModal() {
        if (!modal || !backdrop) {
            return;
        }
        modal.classList.add('show');
        modal.style.display = 'flex';
        modal.setAttribute('aria-modal', 'true');
        modal.removeAttribute('aria-hidden');
        backdrop.style.display = 'block';
        document.body.classList.add('modal-open', 'sp-modal-open');
    }

    function spHideModal() {
        if (!modal || !backdrop) {
            return;
        }
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.removeAttribute('aria-modal');
        modal.setAttribute('aria-hidden', 'true');
        backdrop.style.display = 'none';
        document.body.classList.remove('modal-open', 'sp-modal-open');
    }

    function spRenderBillsDetail(res) {
        loadEl.style.display = 'none';
        if (!res || !res.ok) {
            errEl.textContent = (res && res.message) ? res.message : 'Could not load bills.';
            errEl.style.display = 'block';
            return;
        }
        var rows = res.rows || [];
        subEl.textContent = (res.salesperson || '') + ' · ' + (res.date_range || '');
        if (rows.length === 0) {
            errEl.textContent = 'No bills found for this sales person.';
            errEl.style.display = 'block';
            return;
        }
        var html = '';
        rows.forEach(function (r) {
            var docCell = r.url
                ? '<a href="' + spEscHtml(r.url) + '" class="sp-detail-doc-link" target="_blank" rel="noopener">' + spEscHtml(r.doc_no || '—') + '</a>'
                : spEscHtml(r.doc_no || '—');
            html += '<tr>'
                + '<td>' + spEscHtml(r.doc_type || '') + '</td>'
                + '<td>' + docCell + '</td>'
                + '<td>' + spEscHtml(r.date || '') + '</td>'
                + '<td>' + spEscHtml(r.party || '') + '</td>'
                + '<td class="sp-num">' + spEscHtml(r.amount || '') + '</td>'
                + '</tr>';
        });
        bodyEl.innerHTML = html;
        wrapEl.style.display = 'block';
    }

    function spOpenBillsDetail(salesperson, billsLabel) {
        var dr = spCurrentDateRange();
        titleEl.textContent = 'Bills (' + billsLabel + ')';
        subEl.textContent = '';
        errEl.style.display = 'none';
        errEl.textContent = '';
        wrapEl.style.display = 'none';
        bodyEl.innerHTML = '';
        loadEl.style.display = 'block';
        spShowModal();

        var url = 'ajax/salesperson-performance-bills-detail.php?salesperson='
            + encodeURIComponent(salesperson)
            + '&from=' + encodeURIComponent(dr.from)
            + '&to=' + encodeURIComponent(dr.to);

        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(spRenderBillsDetail)
            .catch(function () {
                loadEl.style.display = 'none';
                errEl.textContent = 'Request failed. Please try again.';
                errEl.style.display = 'block';
            });
    }

    function spOnBillsCellActivate(el) {
        if (!el || !el.classList || !el.classList.contains('sp-qty-link')) {
            return;
        }
        var salesperson = el.getAttribute('data-sp-salesperson') || '';
        var billsLabel = el.getAttribute('data-sp-bills') || el.textContent.trim();
        if (!salesperson) {
            return;
        }
        spOpenBillsDetail(salesperson, billsLabel);
    }

    if (tableEl) {
        tableEl.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || t.getAttribute('data-sp-role') !== 'bills' || !t.classList.contains('sp-qty-link')) {
                return;
            }
            ev.preventDefault();
            spOnBillsCellActivate(t);
        });
        tableEl.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' && ev.key !== ' ') {
                return;
            }
            var t = ev.target;
            if (!t || t.getAttribute('data-sp-role') !== 'bills' || !t.classList.contains('sp-qty-link')) {
                return;
            }
            ev.preventDefault();
            spOnBillsCellActivate(t);
        });
    }

    document.querySelectorAll('.sp-modal-close').forEach(function (btn) {
        btn.addEventListener('click', spHideModal);
    });
    if (backdrop) {
        backdrop.addEventListener('click', spHideModal);
    }
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            spHideModal();
        }
    });
});
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
