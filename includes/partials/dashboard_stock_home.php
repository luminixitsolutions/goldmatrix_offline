<?php
if (!isset($stk) || !is_array($stk)) {
    $stk = auragold_stock_dashboard_jewelsteps();
}

$filterDateFrom = isset($stkBounds['start']) ? (string) $stkBounds['start'] : date('Y-m-d');
$filterDateTo = isset($stkBounds['end']) ? (string) $stkBounds['end'] : date('Y-m-d');
$sx = auragold_stock_dashboard_extras($filterDateFrom, $filterDateTo);
if (!empty($sx['date_from'])) {
    $filterDateFrom = (string) $sx['date_from'];
}
if (!empty($sx['date_to'])) {
    $filterDateTo = (string) $sx['date_to'];
}

$stkIsSingleDay = !empty($sx['is_single_day']);
$stkIsToday = !empty($sx['is_today']);
$inwardLabel = $stkIsToday ? 'Inward today' : ($stkIsSingleDay ? 'Inward' : 'Inward in period');
$outwardLabel = $stkIsToday ? 'Outward today' : ($stkIsSingleDay ? 'Outward' : 'Outward in period');
$inwardWeight = (float) ($sx['inward_weight'] ?? $k['inward_weight'] ?? 0);
$inwardQty = (float) ($sx['inward_qty'] ?? $k['inward_qty'] ?? 0);
$outwardWeight = (float) ($sx['outward_weight'] ?? $k['outward_weight'] ?? 0);
$outwardQty = (float) ($sx['outward_qty'] ?? $k['outward_qty'] ?? 0);
$chartBadge = $stkIsSingleDay
    ? date('d M Y', strtotime($filterDateFrom))
    : date('d M', strtotime($filterDateFrom)) . ' – ' . date('d M Y', strtotime($filterDateTo));

if (!function_exists('stk_stock_img_url')) {
    function stk_stock_img_url($imagesJson) {
        if ($imagesJson === null || $imagesJson === '') {
            return '';
        }
        $j = json_decode((string) $imagesJson, true);
        if (is_array($j)) {
            if (!empty($j['primary'])) {
                return (string) $j['primary'];
            }
            if (isset($j[0])) {
                return (string) $j[0];
            }
            foreach ($j as $v) {
                if (is_string($v) && $v !== '') {
                    return $v;
                }
            }
        }
        return '';
    }
}

$k = $stk['kpi'];
$metals = $k['metals'] ?? [];
$metalById = [];
foreach ($metals as $m) {
    $metalById[(int) ($m['id'] ?? 0)] = $m;
}
$metalCardIds = [1, 2, 4, 5, 6];
$metalCards = [];
foreach ($metalCardIds as $mid) {
    $metalCards[] = $metalById[$mid] ?? ['id' => $mid, 'name' => '—', 'w' => 0, 'q' => 0];
}

$mcb = $stk['metal_chart_branchwise'] ?? [];
$branchChartLabelsJson = json_encode($mcb['branch_labels'] ?? [], JSON_UNESCAPED_UNICODE);
$branchChartDatasetsJson = json_encode($mcb['datasets'] ?? [], JSON_UNESCAPED_UNICODE);

$metalPieLabels = [];
$metalPieValues = [];
foreach ($stk['metal_chart'] ?? [] as $mc) {
    $w = (float) ($mc['weight'] ?? 0);
    if ($w > 0) {
        $metalPieLabels[] = (string) ($mc['label'] ?? '—');
        $metalPieValues[] = $w;
    }
}
$metalPieLabelsJson = json_encode($metalPieLabels, JSON_UNESCAPED_UNICODE);
$metalPieValuesJson = json_encode($metalPieValues, JSON_UNESCAPED_UNICODE);

$greetingHour = (int) date('G');
if ($greetingHour < 12) {
    $greeting = 'Good morning';
} elseif ($greetingHour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
$todayLabel = date('l, d M Y');

if (!function_exists('stk_fmt_w')) {
    function stk_fmt_w($n) {
        return number_format((float) $n, 3);
    }
}
if (!function_exists('stk_fmt_q')) {
    function stk_fmt_q($n) {
        return number_format((float) $n, 2);
    }
}
if (!function_exists('stk_esc')) {
    function stk_esc($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

$metalIcons = ['icon-award', 'icon-layers', 'icon-circle', 'icon-heart', 'icon-settings'];
?>
<style>
.stk-dash {
    --rd-ink: #1a2332;
    --rd-ink-soft: #3d4a5c;
    --rd-gold: #b8954a;
    --rd-gold-soft: #f4ead4;
    --rd-gold-deep: #8a6b2e;
    --rd-surface: #ffffff;
    --rd-canvas: #f6f4f0;
    --rd-muted: #6b7280;
    --rd-line: #e8e4dc;
    --rd-shadow: 0 1px 2px rgba(26, 35, 50, 0.04), 0 8px 24px rgba(26, 35, 50, 0.06);
    font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    color: var(--rd-ink);
    max-width: 100%;
    padding: 2px 0 12px;
}

.stk-dash .rd-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.stk-dash .rd-top-copy { min-width: 180px; }
.stk-dash .rd-top-copy .rd-greeting {
    font-size: 12px;
    color: var(--rd-gold-deep);
    font-weight: 600;
    letter-spacing: 0.02em;
}
.stk-dash .rd-top-copy h1 {
    margin: 1px 0 0;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--rd-ink);
    line-height: 1.2;
}
.stk-dash .rd-top-copy .rd-date {
    margin-top: 2px;
    font-size: 12px;
    color: var(--rd-muted);
}
.stk-dash .rd-top-mid {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 auto;
    justify-content: flex-end;
}
.stk-dash .rd-top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.stk-dash .rd-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 34px;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 650;
    text-decoration: none;
    border: 1px solid transparent;
    transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    white-space: nowrap;
}
.stk-dash .rd-btn:hover { text-decoration: none; transform: translateY(-1px); }
.stk-dash .rd-btn-primary {
    background: var(--rd-ink);
    color: #fff;
    box-shadow: 0 4px 12px rgba(26, 35, 50, 0.16);
}
.stk-dash .rd-btn-primary:hover { color: #fff; background: #243044; }
.stk-dash .rd-btn-ghost {
    background: var(--rd-surface);
    color: var(--rd-ink);
    border-color: var(--rd-line);
}
.stk-dash .rd-btn-ghost:hover { color: var(--rd-ink); background: #faf9f6; }

.stk-dash .rd-toolbar {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    padding: 6px 8px;
    background: var(--rd-surface);
    border: 1px solid var(--rd-line);
    border-radius: 10px;
    box-shadow: var(--rd-shadow);
}
.stk-dash .rd-toolbar label { display: none; }
.stk-dash .rd-toolbar input[type="date"] {
    height: 32px;
    width: 132px;
    min-width: 0;
    border: 1px solid var(--rd-line);
    border-radius: 7px;
    padding: 0 8px;
    font-size: 12px;
    color: var(--rd-ink);
    background: #faf9f6;
}
.stk-dash .rd-toolbar .rd-btn-apply {
    height: 32px;
    padding: 0 12px;
    border: none;
    border-radius: 7px;
    background: var(--rd-gold);
    color: #1a160c;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
}
.stk-dash .rd-toolbar .rd-btn-reset {
    height: 32px;
    padding: 0 10px;
    border-radius: 7px;
    border: 1px solid var(--rd-line);
    background: #fff;
    color: var(--rd-ink-soft);
    font-weight: 600;
    font-size: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}
.stk-dash .rd-toolbar .rd-btn-reset:hover { color: var(--rd-ink); text-decoration: none; }

.stk-dash .rd-note {
    margin-bottom: 10px;
    font-size: 11px;
    color: var(--rd-muted);
    line-height: 1.45;
}

.stk-dash .rd-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}
.stk-dash .rd-metric {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.stk-dash .rd-metric:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.stk-dash .rd-metric .icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
}
.stk-dash .rd-metric .meta { min-width: 0; }
.stk-dash .rd-metric .lbl {
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-muted);
}
.stk-dash .rd-metric .val {
    margin-top: 2px;
    font-size: 1.25rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    color: var(--rd-ink);
    line-height: 1.15;
}

.stk-dash .rd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.stk-dash .rd-kpi {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.stk-dash .rd-kpi:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.stk-dash .rd-kpi .icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    margin-bottom: 8px;
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
}
.stk-dash .rd-kpi .lbl {
    font-size: 11px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    line-height: 1.3;
}
.stk-dash .rd-kpi .num {
    margin-top: 4px;
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--rd-ink);
    line-height: 1.2;
    letter-spacing: -0.02em;
}
.stk-dash .rd-kpi .sub {
    margin-top: 4px;
    font-size: 11px;
    color: var(--rd-muted);
}
.stk-dash .rd-kpi .sub strong { color: var(--rd-ink-soft); font-weight: 650; }

.stk-dash .stk-metal-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.stk-dash .stk-metal-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.stk-dash .stk-metal-card:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.stk-dash .stk-metal-card .nm {
    font-size: 10px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
}
.stk-dash .stk-metal-card .wt {
    margin-top: 3px;
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--rd-ink);
    letter-spacing: -0.02em;
}
.stk-dash .stk-metal-card .qt {
    margin-top: 2px;
    font-size: 10px;
    color: var(--rd-muted);
}
.stk-dash .stk-metal-card .mi {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
}

.stk-dash .rd-panel {
    border: 2px solid transparent;
    border-radius: 14px;
    box-shadow: var(--rd-shadow);
    padding: 14px 16px;
    height: 100%;
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.stk-dash .rd-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.stk-dash .rd-panel-head h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rd-ink);
    letter-spacing: -0.01em;
}
.stk-dash .rd-badge {
    font-size: 11px;
    font-weight: 650;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--rd-canvas);
    color: var(--rd-muted);
    border: 1px solid var(--rd-line);
    text-decoration: none;
}
.stk-dash .rd-badge:hover { color: var(--rd-ink); text-decoration: none; }
.stk-dash .rd-badge-gold {
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
    border-color: #e6d7b0;
}
.stk-dash .rd-panel-note {
    font-size: 11px;
    color: var(--rd-muted);
    margin: -4px 0 10px;
    line-height: 1.45;
}
.stk-dash .rd-table-scroll {
    max-height: 320px;
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid var(--rd-line);
    border-radius: 10px;
    background: #fff;
}
.stk-dash .rd-table-scroll .rd-table {
    margin: 0;
}
.stk-dash .rd-table-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #faf9f6;
    box-shadow: 0 1px 0 var(--rd-line);
}
.stk-dash .rd-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}
.stk-dash .rd-chart-wrap--sm { height: 210px; }

.stk-dash .karat-row { margin-bottom: 12px; }
.stk-dash .karat-row .kr-h {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    margin-bottom: 5px;
}
.stk-dash .karat-row .kr-title { font-weight: 650; color: var(--rd-gold-deep); }
.stk-dash .karat-row .kr-num { font-weight: 750; color: var(--rd-ink); font-size: 13px; }
.stk-dash .karat-row .progress { height: 10px; border-radius: 6px; background: var(--rd-canvas); }
.stk-dash .karat-row .progress-bar {
    background: linear-gradient(90deg, var(--rd-gold-deep), var(--rd-gold));
    border-radius: 6px;
}

.stk-dash .rd-table {
    width: 100%;
    font-size: 13px;
    margin: 0;
}
.stk-dash .rd-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    border-bottom: 1px solid var(--rd-line);
    padding: 8px 10px;
    background: transparent;
}
.stk-dash .rd-table tbody td {
    padding: 11px 10px;
    border-bottom: 1px solid #f3f1ec;
    vertical-align: middle;
    color: var(--rd-ink-soft);
}
.stk-dash .rd-table tbody tr:last-child td { border-bottom: 0; }
.stk-dash .rd-table tbody tr:hover td { background: #faf9f6; }
.stk-dash .rd-table .amt,
.stk-dash .rd-table .w-cell,
.stk-dash .rd-table .q-cell {
    font-weight: 700;
    color: var(--rd-ink);
    text-align: right;
    white-space: nowrap;
}
.stk-dash .rd-table .link-cell a {
    color: var(--rd-ink);
    font-weight: 650;
    text-decoration: none;
}
.stk-dash .rd-table .link-cell a:hover { color: var(--rd-gold-deep); }

.stk-dash .stk-thumb {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--rd-canvas);
    border: 1px solid var(--rd-line);
}
.stk-dash .stk-thumb-ph {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: var(--rd-canvas);
    border: 1px solid var(--rd-line);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--rd-muted);
    font-size: 15px;
    flex-shrink: 0;
}
.stk-dash .stk-product-cell { display: flex; align-items: center; gap: 10px; }
.stk-dash .stk-product-name { font-weight: 650; color: var(--rd-ink); }
.stk-dash .stk-line-note { font-size: 11px; color: var(--rd-muted); font-weight: 500; }

.stk-dash .rd-empty {
    text-align: center;
    padding: 28px 16px;
    color: var(--rd-muted);
    font-size: 13px;
}
.stk-dash .rd-foot {
    margin-top: 18px;
    padding-top: 12px;
    border-top: 1px solid var(--rd-line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 12px;
    color: #9aa3af;
}
.stk-dash .rd-foot a { color: var(--rd-muted); text-decoration: none; }
.stk-dash .rd-foot a:hover { color: var(--rd-gold-deep); }

@media (max-width: 1399.98px) {
    .stk-dash .stk-metal-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1199.98px) {
    .stk-dash .rd-top-mid { justify-content: flex-start; width: 100%; order: 3; }
    .stk-dash .rd-toolbar { width: 100%; }
    .stk-dash .rd-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 991.98px) {
    .stk-dash .rd-metrics { grid-template-columns: 1fr; }
    .stk-dash .stk-metal-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767.98px) {
    .stk-dash .rd-top-copy h1 { font-size: 1.2rem; }
    .stk-dash .rd-top-actions { width: 100%; }
    .stk-dash .rd-top-actions .rd-btn { flex: 1; justify-content: center; }
    .stk-dash .rd-toolbar input[type="date"] { width: 100%; flex: 1 1 120px; }
    .stk-dash .rd-kpi-grid { grid-template-columns: 1fr; }
    .stk-dash .stk-metal-grid { grid-template-columns: 1fr; }
    .stk-dash .rd-chart-wrap { height: 220px; }
}
</style>

<div class="stk-dash">

<div class="rd-top">
    <div class="rd-top-copy">
        <div class="rd-greeting"><?php echo stk_esc($greeting); ?></div>
        <h1>Stock Dashboard</h1>
        <div class="rd-date"><?php echo stk_esc($todayLabel); ?></div>
    </div>
    <div class="rd-top-mid">
        <form class="rd-toolbar" method="get" action="dashboard-stock.php" id="stkDateFilterForm">
            <label for="stk_date_from">From date</label>
            <input type="date" name="date_from" id="stk_date_from" title="From date" value="<?php echo stk_esc($filterDateFrom); ?>" required>
            <label for="stk_date_to">To date</label>
            <input type="date" name="date_to" id="stk_date_to" title="To date" value="<?php echo stk_esc($filterDateTo); ?>" required>
            <button type="submit" class="rd-btn-apply">Apply</button>
            <a href="dashboard-stock.php" class="rd-btn-reset">Today</a>
        </form>
        <div class="rd-top-actions">
            <a class="rd-btn rd-btn-primary" href="stock-journal.php"><i class="feather icon-book"></i> Stock Journal</a>
            <a class="rd-btn rd-btn-ghost" href="dashboards-hub.php"><i class="feather icon-grid"></i> All dashboards</a>
        </div>
    </div>
</div>

<p class="rd-note">On-hand stock is live; date filter applies to journal movements.</p>

<div class="rd-metrics">
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-package"></i></div>
        <div class="meta">
            <div class="lbl">Total stock value</div>
            <div class="val"><?php echo stk_fmt_q($stk['totals']['value'] ?? 0); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-box"></i></div>
        <div class="meta">
            <div class="lbl">Total weight (gm)</div>
            <div class="val"><?php echo stk_fmt_w($stk['totals']['weight'] ?? 0); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-alert-triangle"></i></div>
        <div class="meta">
            <div class="lbl">Low stock alerts</div>
            <div class="val"><?php echo number_format((int) ($sx['low_stock_count'] ?? 0)); ?></div>
        </div>
    </div>
</div>

<div class="rd-kpi-grid">
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-package"></i></div>
        <div class="lbl">Total products</div>
        <div class="num"><?php echo number_format((int) $k['total_products']); ?></div>
        <div class="sub">Qty <strong><?php echo stk_fmt_q($k['total_products_qty']); ?></strong></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-box"></i></div>
        <div class="lbl">Zero stock</div>
        <div class="num"><?php echo number_format((int) $k['zero_stock_lines']); ?></div>
        <div class="sub">Qty <strong><?php echo stk_fmt_q($k['zero_stock_qty']); ?></strong></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-log-in"></i></div>
        <div class="lbl"><?php echo stk_esc($inwardLabel); ?></div>
        <div class="num"><?php echo stk_fmt_w($inwardWeight); ?></div>
        <div class="sub">Qty <strong><?php echo stk_fmt_q($inwardQty); ?></strong></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-log-out"></i></div>
        <div class="lbl"><?php echo stk_esc($outwardLabel); ?></div>
        <div class="num"><?php echo stk_fmt_w($outwardWeight); ?></div>
        <div class="sub">Qty <strong><?php echo stk_fmt_q($outwardQty); ?></strong></div>
    </div>
</div>

<div class="stk-metal-grid">
    <?php foreach ($metalCards as $idx => $mc):
        $fi = $metalIcons[$idx] ?? 'icon-package';
    ?>
    <div class="stk-metal-card">
        <div>
            <div class="nm"><?php echo stk_esc($mc['name'] ?? '—'); ?></div>
            <div class="wt"><?php echo stk_fmt_w($mc['w'] ?? 0); ?></div>
            <div class="qt">Qty <?php echo stk_fmt_q($mc['q'] ?? 0); ?></div>
        </div>
        <div class="mi"><i class="feather <?php echo stk_esc($fi); ?>"></i></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Metal-wise stock by branch</h2>
                <span class="rd-badge rd-badge-gold"><?php echo (int) ($sx['branch_count'] ?? 0); ?> branches</span>
            </div>
            <?php if (empty($mcb['branch_labels'] ?? []) || empty($mcb['datasets'] ?? [])): ?>
            <div class="rd-empty">No stock rows for this scope.</div>
            <?php else: ?>
            <div class="rd-chart-wrap">
                <canvas id="stkMetalChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Metal mix</h2>
            </div>
            <div class="rd-chart-wrap rd-chart-wrap--sm">
                <canvas id="stkMetalPieChart"></canvas>
            </div>
            <p class="rd-panel-note mb-0">
                Gold: <strong><?php echo stk_fmt_w($sx['gold_weight']); ?></strong> gm ·
                Silver: <strong><?php echo stk_fmt_w($sx['silver_weight']); ?></strong> gm
            </p>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Karat-wise gold stock</h2>
            </div>
            <?php if (empty($stk['karatwise'])): ?>
            <div class="rd-empty">No gold karat breakdown yet.</div>
            <?php else:
                $kwList = $stk['karatwise'];
                $maxKw = 0.0001;
                foreach ($kwList as $x) {
                    $maxKw = max($maxKw, abs((float) ($x['weight'] ?? 0)));
                }
                foreach ($kwList as $kr):
                    $w = (float) ($kr['weight'] ?? 0);
                    $q = (float) ($kr['qty'] ?? 0);
                    $pct = 0;
                    if ($w > 0) {
                        $pct = min(100, (abs($w) / $maxKw) * 100);
                    } elseif ($w < 0) {
                        $pct = 100;
                    }
            ?>
            <div class="karat-row">
                <div class="kr-h">
                    <span class="kr-title"><?php echo stk_esc($kr['title'] ?? ''); ?></span>
                    <span class="kr-num"><?php echo stk_fmt_w($w); ?> / <?php echo number_format($q, 0); ?></span>
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo (float) $pct; ?>%;"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Stock by branch</h2>
            </div>
            <?php if (empty($stk['by_branch'])): ?>
            <div class="rd-empty">No branch stock data.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead><tr><th>Branch</th><th class="text-right">Weight</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($stk['by_branch'] as $br): ?>
                        <tr>
                            <td><?php echo stk_esc($br['branch_name'] ?? '—'); ?></td>
                            <td class="w-cell"><?php echo stk_fmt_w($br['sum_current_weight'] ?? 0); ?></td>
                            <td class="amt"><?php echo stk_fmt_q($br['sum_value'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Low stock items</h2>
                <span class="rd-badge rd-badge-gold"><?php echo (int) ($sx['low_stock_count'] ?? 0); ?> alerts</span>
            </div>
            <p class="rd-panel-note">Products with qty ≤ 1 or weight ≤ 0 at branch level.</p>
            <?php if (empty($stk['low_stock'])): ?>
            <div class="rd-empty">No low-stock items — inventory looks healthy.</div>
            <?php else: ?>
            <div class="rd-table-scroll">
                <table class="rd-table">
                    <thead><tr><th>Item</th><th>Branch</th><th class="text-right">Weight</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                    <?php foreach ($stk['low_stock'] as $ls):
                        $img = stk_stock_img_url($ls['images'] ?? '');
                        $lc = (int) ($ls['low_line_count'] ?? 1);
                        $pn = (string) ($ls['product_name'] ?? '');
                        $lcNote = $lc > 1 ? ' (' . $lc . ' low lines)' : '';
                    ?>
                        <tr>
                            <td>
                                <div class="stk-product-cell">
                                    <?php if ($img !== ''): ?>
                                        <img class="stk-thumb" src="<?php echo stk_esc($img); ?>" alt="">
                                    <?php else: ?>
                                        <span class="stk-thumb-ph"><i class="feather icon-image"></i></span>
                                    <?php endif; ?>
                                    <span>
                                        <span class="stk-product-name"><?php echo stk_esc($pn); ?></span>
                                        <?php if ($lcNote !== ''): ?><span class="stk-line-note"><?php echo stk_esc($lcNote); ?></span><?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo stk_esc($ls['branch_name'] ?? '—'); ?></td>
                            <td class="w-cell"><?php echo stk_fmt_w($ls['total_weight'] ?? $ls['current_weight'] ?? 0); ?></td>
                            <td class="q-cell"><?php echo stk_fmt_q($ls['total_qty'] ?? $ls['current_qty'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Recent stock movements</h2>
                <span class="rd-badge rd-badge-gold"><?php echo stk_esc($chartBadge); ?></span>
            </div>
            <?php if (empty($sx['recent_journal'])): ?>
            <div class="rd-empty">No journal entries for this period.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead><tr><th>Ref</th><th>Product</th><th>Date</th><th class="text-right">Wt</th></tr></thead>
                    <tbody>
                    <?php foreach ($sx['recent_journal'] as $sj): ?>
                        <tr>
                            <td class="link-cell"><a href="stock-journal.php"><?php echo stk_esc($sj['sj_invoice_no'] ?? '#' . ($sj['id'] ?? '')); ?></a></td>
                            <td><?php echo stk_esc($sj['product_name'] ?? $sj['barcode'] ?? '—'); ?></td>
                            <td><?php echo stk_esc($sj['sj_date'] ?? ''); ?></td>
                            <td class="w-cell"><?php echo stk_fmt_w($sj['gross_weight'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="rd-foot">
    <span>Gold Matrix · Stock dashboard · Rows: <?php echo (int) ($stk['totals']['rows'] ?? 0); ?> · Branches: <?php echo (int) ($sx['branch_count'] ?? 0); ?></span>
    <span><a href="stock-history.php">Stock history</a> · <a href="gold-silver-analysis.php">Gold / Silver analysis</a></span>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    if (typeof Chart === 'undefined') return;
    var ink = '#1a2332';
    var gold = '#b8954a';
    var goldSoft = '#e8d5a8';

    var ctxBar = document.getElementById('stkMetalChart');
    if (ctxBar) {
        var branchLabels = <?php echo $branchChartLabelsJson; ?>;
        var datasets = <?php echo $branchChartDatasetsJson; ?>;
        if (branchLabels.length && datasets.length) {
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: branchLabels,
                    datasets: datasets.map(function(ds) {
                        ds.borderWidth = 1;
                        ds.borderColor = 'rgba(255,255,255,0.6)';
                        ds.borderRadius = 4;
                        return ds;
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, color: '#6b7280' } },
                        tooltip: {
                            backgroundColor: ink,
                            titleColor: goldSoft,
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                footer: function(items) {
                                    if (!items || !items.length) return '';
                                    var sum = 0;
                                    items.forEach(function(it) { sum += parseFloat(it.parsed.y) || 0; });
                                    return 'Branch total: ' + sum.toFixed(3);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 45, font: { size: 11 }, color: '#6b7280' }, border: { display: false } },
                        y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(26,35,50,0.06)' }, ticks: { font: { size: 11 }, color: '#6b7280' }, border: { display: false } }
                    }
                }
            });
        }
    }

    var ctxPie = document.getElementById('stkMetalPieChart');
    if (ctxPie) {
        var pieLabels = <?php echo $metalPieLabelsJson; ?>;
        var pieValues = <?php echo $metalPieValuesJson; ?>;
        if (!pieLabels.length) {
            pieLabels = ['No data'];
            pieValues = [1];
        }
        var pieColors = ['#b8954a', '#1a2332', '#c9a961', '#3d4a5c', '#d4bc82', '#6b7280', '#8a6b2e', '#243044'];
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieValues,
                    backgroundColor: pieLabels[0] === 'No data' ? ['#e8e4dc'] : pieColors.slice(0, pieLabels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 }, padding: 8, color: '#6b7280' } },
                    tooltip: {
                        backgroundColor: ink,
                        titleColor: goldSoft,
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(c) {
                                return c.label + ': ' + Number(c.parsed).toFixed(3) + ' gm';
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
