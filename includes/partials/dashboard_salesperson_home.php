<?php
/**
 * Salesperson home dashboard. Expects dashboard_helpers loaded.
 * Entry sets $sd, $sp; optional $spBounds from dashboard-sales-person.php.
 */
if (!isset($sd) || !is_array($sd)) {
    $sd = auragold_salesperson_dashboard_data($sp ?? 'ALL', '', '');
}
$selSp = isset($sp) ? (string) $sp : 'ALL';
$filterDateFrom = (string) ($sd['date_from'] ?? ($spBounds['start'] ?? date('Y-m-d')));
$filterDateTo = (string) ($sd['date_to'] ?? ($spBounds['end'] ?? date('Y-m-d')));
$sx = auragold_salesperson_dashboard_extras($selSp, $filterDateFrom, $filterDateTo);
$k = $sd['kpi'];
$labelsJson = json_encode($sd['chart_labels'] ?? [], JSON_UNESCAPED_UNICODE);
$valuesJson = json_encode($sd['chart_values'] ?? [], JSON_UNESCAPED_UNICODE);
$opts = $sd['salesperson_options'] ?? [];
$isToday = !empty($sd['is_today']);
$isSingleDay = !empty($sd['is_single_day']);
$periodSalesLabel = $isToday ? 'Today\'s Sales' : ($isSingleDay ? 'Sales' : 'Period Sales');
$chartBadge = $isSingleDay
    ? date('d M Y', strtotime($filterDateFrom))
    : date('d M', strtotime($filterDateFrom)) . ' – ' . date('d M Y', strtotime($filterDateTo));
$spDisplay = strtoupper($selSp) === 'ALL' ? 'All sales team' : $selSp;

if (!function_exists('sp_fmt')) {
    function sp_fmt($n) {
        return number_format((float) $n, 2);
    }
}
if (!function_exists('sp_esc')) {
    function sp_esc($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

$greetingHour = (int) date('G');
if ($greetingHour < 12) {
    $greeting = 'Good morning';
} elseif ($greetingHour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
$todayLabel = date('l, d M Y');
$dateQs = 'date_from=' . rawurlencode($filterDateFrom) . '&date_to=' . rawurlencode($filterDateTo);
$todaySpHref = 'dashboard-sales-person.php?sp=' . rawurlencode($selSp);
?>
<style>
.sp-dash {
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

.sp-dash .rd-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.sp-dash .rd-top-copy {
    min-width: 180px;
}
.sp-dash .rd-top-copy .rd-greeting {
    font-size: 12px;
    color: var(--rd-gold-deep);
    font-weight: 600;
    letter-spacing: 0.02em;
}
.sp-dash .rd-top-copy h1 {
    margin: 1px 0 0;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--rd-ink);
    line-height: 1.2;
}
.sp-dash .rd-top-copy .rd-sub {
    margin-top: 2px;
    font-size: 12px;
    color: var(--rd-muted);
}
.sp-dash .rd-top-copy .rd-date {
    margin-top: 2px;
    font-size: 12px;
    color: var(--rd-muted);
}
.sp-dash .rd-top-mid {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 auto;
    justify-content: flex-end;
}
.sp-dash .rd-top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.sp-dash .rd-btn {
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
.sp-dash .rd-btn:hover { text-decoration: none; transform: translateY(-1px); }
.sp-dash .rd-btn-primary {
    background: var(--rd-ink);
    color: #fff;
    box-shadow: 0 4px 12px rgba(26, 35, 50, 0.16);
}
.sp-dash .rd-btn-primary:hover { color: #fff; background: #243044; }
.sp-dash .rd-btn-ghost {
    background: var(--rd-surface);
    color: var(--rd-ink);
    border-color: var(--rd-line);
}
.sp-dash .rd-btn-ghost:hover { color: var(--rd-ink); background: #faf9f6; }

.sp-dash .rd-toolbar {
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
.sp-dash .rd-toolbar label {
    display: none;
}
.sp-dash .rd-toolbar select,
.sp-dash .rd-toolbar input[type="date"] {
    height: 32px;
    border: 1px solid var(--rd-line);
    border-radius: 7px;
    padding: 0 8px;
    font-size: 12px;
    color: var(--rd-ink);
    background: #faf9f6;
}
.sp-dash .rd-toolbar select {
    min-width: 140px;
    max-width: 180px;
    font-weight: 600;
}
.sp-dash .rd-toolbar input[type="date"] {
    width: 132px;
    min-width: 0;
}
.sp-dash .rd-toolbar .rd-btn-apply {
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
.sp-dash .rd-toolbar .rd-btn-reset {
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
.sp-dash .rd-toolbar .rd-btn-reset:hover { color: var(--rd-ink); text-decoration: none; }

.sp-dash .rd-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}
.sp-dash .rd-metric {
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
.sp-dash .rd-metric:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.sp-dash .rd-metric .icon {
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
.sp-dash .rd-metric .meta { min-width: 0; }
.sp-dash .rd-metric .lbl {
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-muted);
}
.sp-dash .rd-metric .val {
    margin-top: 2px;
    font-size: 1.25rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    color: var(--rd-ink);
    line-height: 1.15;
}

.sp-dash .rd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.sp-dash .rd-kpi {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.sp-dash .rd-kpi:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.sp-dash .rd-kpi .icon {
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
.sp-dash .rd-kpi .lbl {
    font-size: 11px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    line-height: 1.3;
}
.sp-dash .rd-kpi .num {
    margin-top: 4px;
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--rd-ink);
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.sp-dash .rd-panel {
    border: 2px solid transparent;
    border-radius: 14px;
    box-shadow: var(--rd-shadow);
    padding: 14px 16px;
    height: 100%;
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.sp-dash .rd-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.sp-dash .rd-panel-head h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rd-ink);
    letter-spacing: -0.01em;
}
.sp-dash .rd-badge {
    font-size: 11px;
    font-weight: 650;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--rd-canvas);
    color: var(--rd-muted);
    border: 1px solid var(--rd-line);
    text-decoration: none;
}
.sp-dash .rd-badge:hover { color: var(--rd-ink); text-decoration: none; }
.sp-dash .rd-badge-gold {
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
    border-color: #e6d7b0;
}
.sp-dash .rd-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}

.sp-dash .rd-table {
    width: 100%;
    font-size: 13px;
    margin: 0;
}
.sp-dash .rd-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    border-bottom: 1px solid var(--rd-line);
    padding: 8px 10px;
    background: transparent;
}
.sp-dash .rd-table tbody td {
    padding: 11px 10px;
    border-bottom: 1px solid #f3f1ec;
    vertical-align: middle;
    color: var(--rd-ink-soft);
}
.sp-dash .rd-table tbody tr:last-child td { border-bottom: 0; }
.sp-dash .rd-table tbody tr:hover td { background: #faf9f6; }
.sp-dash .rd-table .amt {
    font-weight: 700;
    color: var(--rd-ink);
    text-align: right;
    white-space: nowrap;
}
.sp-dash .rd-table .link-cell a {
    color: var(--rd-ink);
    font-weight: 650;
    text-decoration: none;
}
.sp-dash .rd-table .link-cell a:hover { color: var(--rd-gold-deep); }
.sp-dash .rd-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: var(--rd-canvas);
    color: var(--rd-muted);
}
.sp-dash .rd-rank--1 {
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
}
.sp-dash .rd-empty {
    text-align: center;
    padding: 28px 16px;
    color: var(--rd-muted);
    font-size: 13px;
}
.sp-dash .rd-empty a { color: var(--rd-gold-deep); font-weight: 600; }
.sp-dash .rd-foot {
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
.sp-dash .rd-foot a { color: var(--rd-muted); text-decoration: none; }
.sp-dash .rd-foot a:hover { color: var(--rd-gold-deep); }

@media (max-width: 1399.98px) {
    .sp-dash .rd-kpi-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1199.98px) {
    .sp-dash .rd-top-mid { justify-content: flex-start; width: 100%; order: 3; }
    .sp-dash .rd-toolbar { width: 100%; }
}
@media (max-width: 991.98px) {
    .sp-dash .rd-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .sp-dash .rd-metrics { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
    .sp-dash .rd-top-copy h1 { font-size: 1.2rem; }
    .sp-dash .rd-top-actions { width: 100%; }
    .sp-dash .rd-top-actions .rd-btn { flex: 1; justify-content: center; }
    .sp-dash .rd-toolbar select,
    .sp-dash .rd-toolbar input[type="date"] { width: 100%; flex: 1 1 120px; max-width: none; }
    .sp-dash .rd-kpi-grid { grid-template-columns: 1fr; }
    .sp-dash .rd-chart-wrap { height: 220px; }
}
</style>

<div class="sp-dash">

<div class="rd-top">
    <div class="rd-top-copy">
        <div class="rd-greeting"><?php echo sp_esc($greeting); ?></div>
        <h1>Salesperson Dashboard</h1>
        <div class="rd-sub">Viewing <?php echo sp_esc($spDisplay); ?></div>
        <div class="rd-date"><?php echo sp_esc($todayLabel); ?></div>
    </div>
    <div class="rd-top-mid">
        <form class="rd-toolbar" method="get" action="dashboard-sales-person.php" id="spDashForm">
            <label for="spSel">Sales person</label>
            <select name="sp" id="spSel" title="Sales person">
                <option value="ALL"<?php echo strtoupper($selSp) === 'ALL' ? ' selected' : ''; ?>>All team</option>
                <?php foreach ($opts as $name): ?>
                    <option value="<?php echo sp_esc($name); ?>"<?php echo $selSp === $name ? ' selected' : ''; ?>>
                        <?php echo sp_esc($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="sp_date_from">From date</label>
            <input type="date" name="date_from" id="sp_date_from" title="From date" value="<?php echo sp_esc($filterDateFrom); ?>" required>
            <label for="sp_date_to">To date</label>
            <input type="date" name="date_to" id="sp_date_to" title="To date" value="<?php echo sp_esc($filterDateTo); ?>" required>
            <button type="submit" class="rd-btn-apply">Apply</button>
            <a href="<?php echo sp_esc($todaySpHref); ?>" class="rd-btn-reset">Today</a>
        </form>
        <div class="rd-top-actions">
            <a class="rd-btn rd-btn-primary" href="pos-sale-invoice.php"><i class="feather icon-shopping-cart"></i> Open POS</a>
            <a class="rd-btn rd-btn-ghost" href="dashboards-hub.php"><i class="feather icon-grid"></i> All dashboards</a>
        </div>
    </div>
</div>

<div class="rd-metrics">
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-trending-up"></i></div>
        <div class="meta">
            <div class="lbl"><?php echo sp_esc($periodSalesLabel); ?></div>
            <div class="val"><?php echo sp_fmt($k['total_sales']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-file-text"></i></div>
        <div class="meta">
            <div class="lbl">Avg. invoice value</div>
            <div class="val"><?php echo sp_fmt($sx['avg_ticket']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-users"></i></div>
        <div class="meta">
            <div class="lbl">Active sales team</div>
            <div class="val"><?php echo number_format((int) $sx['team_count']); ?></div>
        </div>
    </div>
</div>

<div class="rd-kpi-grid">
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-trending-up"></i></div>
        <div class="lbl"><?php echo sp_esc($periodSalesLabel); ?></div>
        <div class="num"><?php echo sp_fmt($k['total_sales']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-layers"></i></div>
        <div class="lbl">Total making</div>
        <div class="num"><?php echo sp_fmt($k['total_making']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-file-text"></i></div>
        <div class="lbl">Invoices</div>
        <div class="num"><?php echo number_format((int) $k['total_invoices']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-calendar"></i></div>
        <div class="lbl">Today&rsquo;s sales</div>
        <div class="num"><?php echo sp_fmt($k['today_sales']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-package"></i></div>
        <div class="lbl">Today&rsquo;s making</div>
        <div class="num"><?php echo sp_fmt($k['today_making']); ?></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Sales overview</h2>
                <span class="rd-badge rd-badge-gold"><?php echo sp_esc($chartBadge); ?></span>
            </div>
            <div class="rd-chart-wrap">
                <canvas id="spSalesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Top performers</h2>
                <span class="rd-badge rd-badge-gold">Leaderboard</span>
            </div>
            <?php if (!empty($sd['top_performers'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th class="text-right">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    foreach ($sd['top_performers'] as $row):
                        $rankClass = $i === 1 ? ' rd-rank--1' : '';
                        $spName = (string) ($row['name'] ?? '');
                        $spLink = 'dashboard-sales-person.php?sp=' . rawurlencode($spName) . '&' . $dateQs;
                    ?>
                        <tr>
                            <td><span class="rd-rank<?php echo sp_esc($rankClass); ?>"><?php echo $i++; ?></span></td>
                            <td class="link-cell"><a href="<?php echo sp_esc($spLink); ?>"><?php echo sp_esc($spName); ?></a></td>
                            <td class="amt"><?php echo sp_fmt($row['amount'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No sales data for this period.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Need attention</h2>
                <span class="rd-badge">Lowest sales</span>
            </div>
            <?php if (!empty($sd['weak_performers'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th class="text-right">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $j = 1;
                    foreach ($sd['weak_performers'] as $row):
                        $spName = (string) ($row['name'] ?? '');
                        $spLink = 'dashboard-sales-person.php?sp=' . rawurlencode($spName) . '&' . $dateQs;
                    ?>
                        <tr>
                            <td><span class="rd-rank"><?php echo $j++; ?></span></td>
                            <td class="link-cell"><a href="<?php echo sp_esc($spLink); ?>"><?php echo sp_esc($spName); ?></a></td>
                            <td class="amt"><?php echo sp_fmt($row['amount'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No performers to compare yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Recent sale invoices</h2>
                <a href="sale-invoice.php" class="rd-badge">View all</a>
            </div>
            <?php if (!empty($sx['recent_invoices'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Sales person</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sx['recent_invoices'] as $inv): ?>
                        <tr>
                            <td class="link-cell"><a href="sale-invoice.php?id=<?php echo (int) ($inv['id'] ?? 0); ?>"><?php echo sp_esc($inv['invoice_no'] ?? '#' . ($inv['id'] ?? '')); ?></a></td>
                            <td><?php echo sp_esc($inv['customer_name'] ?? '—'); ?></td>
                            <td><?php echo sp_esc($inv['sales_person'] ?? '—'); ?></td>
                            <td><?php echo sp_esc($inv['invoice_date'] ?? ''); ?></td>
                            <td class="amt"><?php
                                $amt = function_exists('auragold_dashboard_invoice_display_amount')
                                    ? auragold_dashboard_invoice_display_amount($inv)
                                    : (float) ($inv['grand_total'] ?? 0);
                                echo sp_fmt($amt);
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No invoices in this period. <a href="sale-invoice.php">Create sale invoice</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="rd-foot">
    <span>Gold Matrix · Salesperson dashboard · <?php echo sp_esc($spDisplay); ?></span>
    <span><a href="sale-invoice.php">Sale invoices</a> · <a href="pos-sale-invoice.php">POS</a></span>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('spSalesChart');
    if (!ctx || typeof Chart === 'undefined') return;
    var labels = <?php echo $labelsJson; ?>;
    var values = <?php echo $valuesJson; ?>;
    var gold = '#b8954a';
    var ink = '#1a2332';
    var grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, 'rgba(184, 149, 74, 0.28)');
    grad.addColorStop(1, 'rgba(184, 149, 74, 0.02)');
    var spChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales',
                data: values,
                borderColor: gold,
                backgroundColor: grad,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: gold,
                pointBorderWidth: 2,
                borderWidth: 2.25
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: ink,
                    titleColor: '#e8d5a8',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(c) {
                            return 'Sales: ' + (c.parsed.y != null ? Number(c.parsed.y).toFixed(2) : '0.00');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(26,35,50,0.06)' },
                    ticks: { color: '#6b7280', font: { size: 11 } },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#6b7280', font: { size: 11 }, maxRotation: 0 },
                    border: { display: false }
                }
            }
        }
    });
    window.addEventListener('resize', function() {
        if (spChart) spChart.resize();
    });
})();
</script>
