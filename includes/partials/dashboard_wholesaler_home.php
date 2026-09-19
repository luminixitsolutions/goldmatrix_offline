<?php
/**
 * Wholesaler home dashboard (WHOLESALER type). Expects dashboard_helpers loaded.
 * Optional: $wdBounds from dashboard-wholesaler.php (date range filter).
 */
$wdDateFrom = isset($wdBounds['start']) ? (string) $wdBounds['start'] : '';
$wdDateTo = isset($wdBounds['end']) ? (string) $wdBounds['end'] : '';
$wd = auragold_wholesaler_dashboard_kpis($wdDateFrom, $wdDateTo);
$wx = auragold_wholesaler_dashboard_extras();
$labelsJson = json_encode($wd['chart_labels'], JSON_UNESCAPED_UNICODE);
$valuesJson = json_encode($wd['chart_values'], JSON_UNESCAPED_UNICODE);

$filterDateFrom = (string) ($wd['date_from'] ?? date('Y-m-d'));
$filterDateTo = (string) ($wd['date_to'] ?? date('Y-m-d'));
$wdIsToday = !empty($wd['is_today']);
$wdIsSingleDay = !empty($wd['is_single_day']);
$periodSalesLabel = $wdIsToday ? 'Today\'s Sales' : ($wdIsSingleDay ? 'Sales' : 'Period Sales');
$periodPurchaseLabel = $wdIsToday ? 'Today\'s Purchase' : ($wdIsSingleDay ? 'Purchase' : 'Period Purchase');
$periodOrdersLabel = $wdIsToday ? 'Today\'s Orders' : ($wdIsSingleDay ? 'Orders' : 'Period Orders');
$periodBankLabel = 'Bank Balance';
$periodCardLabel = 'Card Balance';
$chartBadge = $wdIsSingleDay
    ? date('d M Y', strtotime($filterDateFrom))
    : date('d M', strtotime($filterDateFrom)) . ' – ' . date('d M Y', strtotime($filterDateTo));

$url_ledger_all = static function (string $ledgerName): string {
    return 'accountledger-report.php?tab=all&ledger_account=' . rawurlencode($ledgerName) . '&ledger_name=' . rawurlencode($ledgerName);
};

if (!function_exists('auragold_fmt_money')) {
    function auragold_fmt_money($n) {
        return number_format((float) $n, 2);
    }
}

if (!function_exists('auragold_market_rate')) {
    function auragold_market_rate($row) {
        if (!$row || !is_array($row)) {
            return '—';
        }
        $v = $row['avg_metal_rate'] ?? $row['max_metal_rate'] ?? null;
        if ($v === null || $v === '') {
            return '—';
        }
        return number_format((float) $v, 2);
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
$cashTotalSub = 'Total Cash = ' . auragold_fmt_money($wd['balance_cash']);
$bankTotalSub = 'Total Bank = ' . auragold_fmt_money($wd['balance_bank']);
$cardTotalSub = 'Total Card = ' . auragold_fmt_money($wd['balance_card']);
?>
<style>
.wholesale-dash {
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

.wholesale-dash .rd-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.wholesale-dash .rd-top-copy { min-width: 180px; }
.wholesale-dash .rd-top-copy .rd-greeting {
    font-size: 12px;
    color: var(--rd-gold-deep);
    font-weight: 600;
    letter-spacing: 0.02em;
}
.wholesale-dash .rd-top-copy h1 {
    margin: 1px 0 0;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--rd-ink);
    line-height: 1.2;
}
.wholesale-dash .rd-top-copy .rd-date {
    margin-top: 2px;
    font-size: 12px;
    color: var(--rd-muted);
}
.wholesale-dash .rd-top-mid {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 auto;
    justify-content: flex-end;
}
.wholesale-dash .rd-top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.wholesale-dash .rd-btn {
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
.wholesale-dash .rd-btn:hover { text-decoration: none; transform: translateY(-1px); }
.wholesale-dash .rd-btn-primary {
    background: var(--rd-ink);
    color: #fff;
    box-shadow: 0 4px 12px rgba(26, 35, 50, 0.16);
}
.wholesale-dash .rd-btn-primary:hover { color: #fff; background: #243044; }
.wholesale-dash .rd-btn-ghost {
    background: var(--rd-surface);
    color: var(--rd-ink);
    border-color: var(--rd-line);
}
.wholesale-dash .rd-btn-ghost:hover { color: var(--rd-ink); background: #faf9f6; }

.wholesale-dash .rd-toolbar {
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
.wholesale-dash .rd-toolbar label { display: none; }
.wholesale-dash .rd-toolbar input[type="date"] {
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
.wholesale-dash .rd-toolbar .rd-btn-apply {
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
.wholesale-dash .rd-toolbar .rd-btn-reset {
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
.wholesale-dash .rd-toolbar .rd-btn-reset:hover { color: var(--rd-ink); text-decoration: none; }

.wholesale-dash .rd-alert {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    background: #fff8eb;
    border: 1px solid #f0e0b8;
    color: #6b5320;
    font-size: 12px;
}

.wholesale-dash .rd-metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}
.wholesale-dash .rd-metric {
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
.wholesale-dash .rd-metric .icon {
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
.wholesale-dash .rd-metric .meta { min-width: 0; }
.wholesale-dash .rd-metric .lbl {
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-muted);
}
.wholesale-dash .rd-metric .val {
    margin-top: 2px;
    font-size: 1.2rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    color: var(--rd-ink);
    line-height: 1.15;
}

.wholesale-dash .rd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.wholesale-dash .rd-kpi {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.wholesale-dash .rd-kpi:hover,
.wholesale-dash .rd-metric:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.wholesale-dash .rd-kpi .icon {
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
.wholesale-dash .rd-kpi .lbl {
    font-size: 11px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    line-height: 1.3;
}
.wholesale-dash .rd-kpi .num {
    margin-top: 4px;
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--rd-ink);
    line-height: 1.2;
    letter-spacing: -0.02em;
}
.wholesale-dash .rd-kpi .sub {
    margin-top: 4px;
    font-size: 11px;
    color: var(--rd-muted);
}
.wholesale-dash .rd-kpi .sub strong { color: var(--rd-ink-soft); font-weight: 650; }
.wholesale-dash .rd-kpi .link { margin-top: 6px; font-size: 11px; }
.wholesale-dash .rd-kpi .link a {
    color: var(--rd-gold-deep);
    font-weight: 600;
    text-decoration: none;
}
.wholesale-dash .rd-kpi .link a:hover { text-decoration: underline; }

.wholesale-dash .rd-panel {
    border: 2px solid transparent;
    border-radius: 14px;
    box-shadow: var(--rd-shadow);
    padding: 14px 16px;
    height: 100%;
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.wholesale-dash .rd-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.wholesale-dash .rd-panel-head h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rd-ink);
    letter-spacing: -0.01em;
}
.wholesale-dash .rd-badge {
    font-size: 11px;
    font-weight: 650;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--rd-canvas);
    color: var(--rd-muted);
    border: 1px solid var(--rd-line);
    text-decoration: none;
}
.wholesale-dash .rd-badge:hover { color: var(--rd-ink); text-decoration: none; }
.wholesale-dash .rd-badge-gold {
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
    border-color: #e6d7b0;
}
.wholesale-dash .rd-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}

.wholesale-dash .rd-market {
    background: linear-gradient(165deg, #1a2332 0%, #243044 55%, #2c3a4f 100%);
    color: #fff;
    border: 2px solid transparent;
    background:
        linear-gradient(165deg, #1a2332 0%, #243044 55%, #2c3a4f 100%) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.wholesale-dash .rd-market .rd-panel-head h2 { color: #fff; }
.wholesale-dash .rd-market .rd-badge {
    background: rgba(255,255,255,0.08);
    color: #e8d5a8;
    border-color: rgba(255,255,255,0.12);
}
.wholesale-dash .rd-rate-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 12px;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
}
.wholesale-dash .rd-rate-row .karat {
    font-weight: 700;
    font-size: 14px;
    color: #e8d5a8;
}
.wholesale-dash .rd-rate-row .rate {
    font-weight: 750;
    font-size: 17px;
    color: #fff;
    letter-spacing: -0.02em;
}
.wholesale-dash .rd-rate-row .rate.empty {
    color: rgba(255,255,255,0.35);
    font-weight: 600;
}
.wholesale-dash .rd-market-foot {
    margin-top: 12px;
    font-size: 11px;
    color: rgba(255,255,255,0.55);
}
.wholesale-dash .rd-market-foot a { color: #e8d5a8; }

.wholesale-dash .rd-table {
    width: 100%;
    font-size: 13px;
    margin: 0;
}
.wholesale-dash .rd-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    border-bottom: 1px solid var(--rd-line);
    padding: 8px 10px;
    background: transparent;
}
.wholesale-dash .rd-table tbody td {
    padding: 11px 10px;
    border-bottom: 1px solid #f3f1ec;
    vertical-align: middle;
    color: var(--rd-ink-soft);
}
.wholesale-dash .rd-table tbody tr:last-child td { border-bottom: 0; }
.wholesale-dash .rd-table tbody tr:hover td { background: #faf9f6; }
.wholesale-dash .rd-table .amt {
    font-weight: 700;
    color: var(--rd-ink);
    text-align: right;
    white-space: nowrap;
}
.wholesale-dash .rd-table .link-cell a {
    color: var(--rd-ink);
    font-weight: 650;
    text-decoration: none;
}
.wholesale-dash .rd-table .link-cell a:hover { color: var(--rd-gold-deep); }
.wholesale-dash .rd-status {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
}
.wholesale-dash .rd-empty {
    text-align: center;
    padding: 28px 16px;
    color: var(--rd-muted);
    font-size: 13px;
}
.wholesale-dash .rd-empty a { color: var(--rd-gold-deep); font-weight: 600; }
.wholesale-dash .rd-foot {
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
.wholesale-dash .rd-foot a { color: var(--rd-muted); text-decoration: none; }
.wholesale-dash .rd-foot a:hover { color: var(--rd-gold-deep); }

@media (max-width: 1399.98px) {
    .wholesale-dash .rd-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    .wholesale-dash .rd-metrics { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 1199.98px) {
    .wholesale-dash .rd-top-mid { justify-content: flex-start; width: 100%; order: 3; }
    .wholesale-dash .rd-toolbar { width: 100%; }
}
@media (max-width: 991.98px) {
    .wholesale-dash .rd-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .wholesale-dash .rd-metrics { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
    .wholesale-dash .rd-top-copy h1 { font-size: 1.2rem; }
    .wholesale-dash .rd-top-actions { width: 100%; }
    .wholesale-dash .rd-top-actions .rd-btn { flex: 1; justify-content: center; }
    .wholesale-dash .rd-toolbar input[type="date"] { width: 100%; flex: 1 1 120px; }
    .wholesale-dash .rd-kpi-grid { grid-template-columns: 1fr; }
    .wholesale-dash .rd-chart-wrap { height: 220px; }
}
</style>

<div class="wholesale-dash">

<div class="rd-top">
    <div class="rd-top-copy">
        <div class="rd-greeting"><?php echo htmlspecialchars($greeting); ?></div>
        <h1>Wholesaler Dashboard</h1>
        <div class="rd-date"><?php echo htmlspecialchars($todayLabel); ?></div>
    </div>
    <div class="rd-top-mid">
        <form class="rd-toolbar" method="get" action="dashboard-wholesaler.php" id="wdDateFilterForm">
            <label for="wd_date_from">From date</label>
            <input type="date" name="date_from" id="wd_date_from" title="From date" value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8'); ?>" required>
            <label for="wd_date_to">To date</label>
            <input type="date" name="date_to" id="wd_date_to" title="To date" value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8'); ?>" required>
            <button type="submit" class="rd-btn-apply">Apply</button>
            <a href="dashboard-wholesaler.php" class="rd-btn-reset">Today</a>
        </form>
        <div class="rd-top-actions">
            <a class="rd-btn rd-btn-primary" href="purchase-invoice.php"><i class="feather icon-download"></i> New Purchase</a>
            <a class="rd-btn rd-btn-ghost" href="dashboards-hub.php"><i class="feather icon-grid"></i> All dashboards</a>
        </div>
    </div>
</div>

<?php if ((int) ($wd['customer_type_id'] ?? 0) <= 0): ?>
    <div class="rd-alert">Customer type <code>WHOLESALER</code> not found in masters. KPIs use all customers until the type exists.</div>
<?php endif; ?>

<div class="rd-metrics">
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-calendar"></i></div>
        <div class="meta">
            <div class="lbl">This week sales</div>
            <div class="val"><?php echo auragold_fmt_money($wx['sales_week']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-bar-chart-2"></i></div>
        <div class="meta">
            <div class="lbl">This month sales</div>
            <div class="val"><?php echo auragold_fmt_money($wx['sales_month']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-download"></i></div>
        <div class="meta">
            <div class="lbl">This month purchases</div>
            <div class="val"><?php echo auragold_fmt_money($wx['purchases_month']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-users"></i></div>
        <div class="meta">
            <div class="lbl">Wholesaler partners</div>
            <div class="val"><?php echo number_format((int) $wx['customers_count']); ?></div>
        </div>
    </div>
</div>

<div class="rd-kpi-grid">
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-trending-up"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodSalesLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($wd['sales_today']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-download"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodPurchaseLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($wd['purchase_today']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-package"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodOrdersLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo number_format((int) $wd['orders_today']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-pocket"></i></div>
        <div class="lbl">Cash In Hand</div>
        <div class="num"><?php echo auragold_fmt_money($wd['cash_today']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($cashTotalSub, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($url_ledger_all('Cash')); ?>">View ledger</a></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-briefcase"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodBankLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($wd['bank_today']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($bankTotalSub, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($url_ledger_all('Bank Account')); ?>">View ledger</a></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-credit-card"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodCardLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($wd['card_today']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($cardTotalSub, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($url_ledger_all('Card')); ?>">View ledger</a></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Wholesale sales overview</h2>
                <span class="rd-badge rd-badge-gold"><?php echo htmlspecialchars($chartBadge, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="rd-chart-wrap">
                <canvas id="wholesaleSalesChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="rd-panel rd-market">
            <div class="rd-panel-head">
                <h2>Gold rates</h2>
                <span class="rd-badge">Live avg</span>
            </div>
            <?php
            $karats = ['18k' => '18K', '21k' => '21K', '22k' => '22K', '24k' => '24K'];
            foreach ($karats as $key => $label):
                $rate = auragold_market_rate($wd['market'][$key] ?? null);
                $isEmpty = ($rate === '—');
            ?>
            <div class="rd-rate-row">
                <span class="karat"><?php echo htmlspecialchars($label); ?></span>
                <span class="rate<?php echo $isEmpty ? ' empty' : ''; ?>"><?php echo $isEmpty ? '—' : htmlspecialchars($rate); ?></span>
            </div>
            <?php endforeach; ?>
            <p class="rd-market-foot mb-0">Rates from dashboard sheet / settings. <a href="dashboard.php">Update rates</a> · <a href="dashboard-gold-rates.php">Full rates</a></p>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Recent sale invoices</h2>
                <a href="sale-invoice.php" class="rd-badge">View all</a>
            </div>
            <?php if (!empty($wx['recent_invoices'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Wholesaler</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wx['recent_invoices'] as $inv): ?>
                        <tr>
                            <td class="link-cell"><a href="sale-invoice.php?id=<?php echo (int) ($inv['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($inv['invoice_no'] ?? '#' . ($inv['id'] ?? ''))); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($inv['customer_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($inv['invoice_date'] ?? '')); ?></td>
                            <td class="amt"><?php echo auragold_fmt_money(auragold_dashboard_invoice_display_amount($inv)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No wholesaler sale invoices yet. <a href="sale-invoice.php">Create one</a></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Pending orders</h2>
                <a href="sale-order.php" class="rd-badge">View all</a>
            </div>
            <?php if (!empty($wx['pending_orders'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wx['pending_orders'] as $ord): ?>
                        <tr>
                            <td class="link-cell"><a href="sale-order.php?id=<?php echo (int) ($ord['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($ord['order_no'] ?? '#' . ($ord['id'] ?? ''))); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($ord['customer_name'] ?? '—')); ?></td>
                            <td><span class="rd-status"><?php echo htmlspecialchars((string) ($ord['status'] ?? 'Open')); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No pending orders. <a href="sale-order.php">New order</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Recent purchase invoices</h2>
                <a href="purchase-invoice.php" class="rd-badge">View all</a>
            </div>
            <?php if (!empty($wx['recent_purchases'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wx['recent_purchases'] as $pur): ?>
                        <tr>
                            <td class="link-cell"><a href="purchase-invoice.php?id=<?php echo (int) ($pur['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($pur['invoice_no'] ?? '#' . ($pur['id'] ?? ''))); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($pur['supplier_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($pur['invoice_date'] ?? '')); ?></td>
                            <td class="amt"><?php echo auragold_fmt_money($pur['grand_total'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No purchase invoices yet. <a href="purchase-invoice.php">Record a purchase</a></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Active consignments</h2>
                <a href="consignment-out.php" class="rd-badge">View all</a>
            </div>
            <?php if (!empty($wx['active_consignments'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>Memo no.</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wx['active_consignments'] as $co): ?>
                        <tr>
                            <td class="link-cell"><a href="consignment-out.php?id=<?php echo (int) ($co['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($co['consignment_no'] ?? '#' . ($co['id'] ?? ''))); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($co['customer_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($co['consignment_date'] ?? '')); ?></td>
                            <td class="amt"><?php echo auragold_fmt_money($co['grand_total'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="rd-empty">No active consignments. <a href="consignment-out.php">Create memo out</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="rd-foot">
    <span>Gold Matrix · Wholesaler dashboard</span>
    <span><a href="account-ledger.php">Account ledger</a> · <a href="consignment-out-report.php">Memo items</a> · <a href="dashboard-gold-rates.php">Gold rates</a></span>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('wholesaleSalesChart');
    if (!ctx || typeof Chart === 'undefined') return;
    var labels = <?php echo $labelsJson; ?>;
    var values = <?php echo $valuesJson; ?>;
    var gold = '#b8954a';
    var ink = '#1a2332';
    var grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, 'rgba(184, 149, 74, 0.28)');
    grad.addColorStop(1, 'rgba(184, 149, 74, 0.02)');
    var wholesaleChart = new Chart(ctx, {
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
                    cornerRadius: 8
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
                    ticks: { color: '#6b7280', font: { size: 11 } },
                    border: { display: false }
                }
            }
        }
    });
    window.addEventListener('resize', function() {
        if (wholesaleChart) wholesaleChart.resize();
    });
})();
</script>
