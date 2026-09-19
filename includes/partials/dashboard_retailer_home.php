<?php
/**
 * Retailer home dashboard (CUSTOMER type). Expects dashboard_helpers loaded.
 * Optional: $rdBounds from dashboard-retailer.php (date range filter).
 */
$rdDateFrom = isset($rdBounds['start']) ? (string) $rdBounds['start'] : '';
$rdDateTo = isset($rdBounds['end']) ? (string) $rdBounds['end'] : '';
$rd = auragold_retailer_dashboard_kpis($rdDateFrom, $rdDateTo);
$rx = auragold_retailer_dashboard_extras();
$labelsJson = json_encode($rd['chart_labels'], JSON_UNESCAPED_UNICODE);
$valuesJson = json_encode($rd['chart_values'], JSON_UNESCAPED_UNICODE);

$filterDateFrom = (string) ($rd['date_from'] ?? date('Y-m-d'));
$filterDateTo = (string) ($rd['date_to'] ?? date('Y-m-d'));
$rdIsToday = !empty($rd['is_today']);
$rdIsSingleDay = !empty($rd['is_single_day']);
$periodSalesLabel = $rdIsToday ? 'Today\'s Sales' : ($rdIsSingleDay ? 'Sales' : 'Period Sales');
$periodPurchaseLabel = $rdIsToday ? 'Today\'s Purchase' : ($rdIsSingleDay ? 'Purchase' : 'Period Purchase');
$periodOrdersLabel = $rdIsToday ? 'Today\'s Orders' : ($rdIsSingleDay ? 'Orders' : 'Period Orders');
$periodBankLabel = 'Bank Balance';
$periodCardLabel = 'Card Balance';
$chartBadge = $rdIsSingleDay
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
$cashTotalSub = 'Total Cash = ' . auragold_fmt_money($rd['balance_cash']);
$bankTotalSub = 'Total Bank = ' . auragold_fmt_money($rd['balance_bank']);
$cardTotalSub = 'Total Card = ' . auragold_fmt_money($rd['balance_card']);
?>
<style>
.retail-dash {
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

.rd-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.rd-top-copy {
    min-width: 180px;
}
.rd-top-copy .rd-greeting {
    font-size: 12px;
    color: var(--rd-gold-deep);
    font-weight: 600;
    letter-spacing: 0.02em;
}
.rd-top-copy h1 {
    margin: 1px 0 0;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--rd-ink);
    line-height: 1.2;
}
.rd-top-copy .rd-date {
    margin-top: 2px;
    font-size: 12px;
    color: var(--rd-muted);
}
.rd-top-mid {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 auto;
    justify-content: flex-end;
}
.rd-top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.rd-btn {
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
.rd-btn:hover { text-decoration: none; transform: translateY(-1px); }
.rd-btn-primary {
    background: var(--rd-ink);
    color: #fff;
    box-shadow: 0 4px 12px rgba(26, 35, 50, 0.16);
}
.rd-btn-primary:hover { color: #fff; background: #243044; }
.rd-btn-ghost {
    background: var(--rd-surface);
    color: var(--rd-ink);
    border-color: var(--rd-line);
}
.rd-btn-ghost:hover { color: var(--rd-ink); background: #faf9f6; }

.rd-toolbar {
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
.rd-toolbar label {
    display: none;
}
.rd-toolbar input[type="date"] {
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
.rd-toolbar .rd-btn-apply {
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
.rd-toolbar .rd-btn-reset {
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
.rd-toolbar .rd-btn-reset:hover { color: var(--rd-ink); text-decoration: none; }

.rd-alert {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    background: #fff8eb;
    border: 1px solid #f0e0b8;
    color: #6b5320;
    font-size: 12px;
}

.rd-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}
.rd-metric {
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
.rd-metric:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.rd-metric .icon {
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
.rd-metric .meta { min-width: 0; }
.rd-metric .lbl {
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-muted);
}
.rd-metric .val {
    margin-top: 2px;
    font-size: 1.25rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    color: var(--rd-ink);
    line-height: 1.15;
}

.rd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.rd-kpi {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.rd-kpi:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.rd-kpi .icon {
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
.rd-kpi .lbl {
    font-size: 11px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    line-height: 1.3;
}
.rd-kpi .num {
    margin-top: 4px;
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--rd-ink);
    line-height: 1.2;
    letter-spacing: -0.02em;
}
.rd-kpi .sub {
    margin-top: 4px;
    font-size: 11px;
    color: var(--rd-muted);
}
.rd-kpi .sub strong { color: var(--rd-ink-soft); font-weight: 650; }
.rd-kpi .link {
    margin-top: 6px;
    font-size: 11px;
}
.rd-kpi .link a {
    color: var(--rd-gold-deep);
    font-weight: 600;
    text-decoration: none;
}
.rd-kpi .link a:hover { text-decoration: underline; }

.rd-panel {
    border: 2px solid transparent;
    border-radius: 14px;
    box-shadow: var(--rd-shadow);
    padding: 14px 16px;
    height: 100%;
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.rd-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.rd-panel-head h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rd-ink);
    letter-spacing: -0.01em;
}
.rd-badge {
    font-size: 11px;
    font-weight: 650;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--rd-canvas);
    color: var(--rd-muted);
    border: 1px solid var(--rd-line);
    text-decoration: none;
}
.rd-badge:hover { color: var(--rd-ink); text-decoration: none; }
.rd-badge-gold {
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
    border-color: #e6d7b0;
}
.rd-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}

.rd-market {
    color: #fff;
    border: 2px solid transparent;
    background:
        linear-gradient(165deg, #1a2332 0%, #243044 55%, #2c3a4f 100%) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.rd-market .rd-panel-head h2 { color: #fff; }
.rd-market .rd-badge {
    background: rgba(255,255,255,0.08);
    color: #e8d5a8;
    border-color: rgba(255,255,255,0.12);
}
.rd-rate-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 12px;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
}
.rd-rate-row .karat {
    font-weight: 700;
    font-size: 14px;
    color: #e8d5a8;
}
.rd-rate-row .rate {
    font-weight: 750;
    font-size: 17px;
    color: #fff;
    letter-spacing: -0.02em;
}
.rd-rate-row .rate.empty {
    color: rgba(255,255,255,0.35);
    font-weight: 600;
}
.rd-market-foot {
    margin-top: 12px;
    font-size: 11px;
    color: rgba(255,255,255,0.55);
}
.rd-market-foot a { color: #e8d5a8; }

.rd-table {
    width: 100%;
    font-size: 13px;
    margin: 0;
}
.rd-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    border-bottom: 1px solid var(--rd-line);
    padding: 8px 10px;
    background: transparent;
}
.rd-table tbody td {
    padding: 11px 10px;
    border-bottom: 1px solid #f3f1ec;
    vertical-align: middle;
    color: var(--rd-ink-soft);
}
.rd-table tbody tr:last-child td { border-bottom: 0; }
.rd-table tbody tr:hover td { background: #faf9f6; }
.rd-table .amt {
    font-weight: 700;
    color: var(--rd-ink);
    text-align: right;
    white-space: nowrap;
}
.rd-table .link-cell a {
    color: var(--rd-ink);
    font-weight: 650;
    text-decoration: none;
}
.rd-table .link-cell a:hover { color: var(--rd-gold-deep); }
.rd-status {
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
.rd-empty {
    text-align: center;
    padding: 28px 16px;
    color: var(--rd-muted);
    font-size: 13px;
}
.rd-empty a { color: var(--rd-gold-deep); font-weight: 600; }
.rd-foot {
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
.rd-foot a { color: var(--rd-muted); text-decoration: none; }
.rd-foot a:hover { color: var(--rd-gold-deep); }

@media (max-width: 1399.98px) {
    .rd-kpi-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1199.98px) {
    .rd-top-mid { justify-content: flex-start; width: 100%; order: 3; }
    .rd-toolbar { width: 100%; }
}
@media (max-width: 991.98px) {
    .rd-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .rd-metrics { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
    .rd-top-copy h1 { font-size: 1.2rem; }
    .rd-top-actions { width: 100%; }
    .rd-top-actions .rd-btn { flex: 1; justify-content: center; }
    .rd-toolbar input[type="date"] { width: 100%; flex: 1 1 120px; }
    .rd-kpi-grid { grid-template-columns: 1fr; }
    .rd-chart-wrap { height: 220px; }
}
</style>

<div class="retail-dash">

<div class="rd-top">
    <div class="rd-top-copy">
        <div class="rd-greeting"><?php echo htmlspecialchars($greeting); ?></div>
        <h1>Retailer Dashboard</h1>
        <div class="rd-date"><?php echo htmlspecialchars($todayLabel); ?></div>
    </div>
    <div class="rd-top-mid">
        <form class="rd-toolbar" method="get" action="dashboard-retailer.php" id="rdDateFilterForm">
            <label for="rd_date_from">From date</label>
            <input type="date" name="date_from" id="rd_date_from" title="From date" value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8'); ?>" required>
            <label for="rd_date_to">To date</label>
            <input type="date" name="date_to" id="rd_date_to" title="To date" value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8'); ?>" required>
            <button type="submit" class="rd-btn-apply">Apply</button>
            <a href="dashboard-retailer.php" class="rd-btn-reset">Today</a>
        </form>
        <div class="rd-top-actions">
            <a class="rd-btn rd-btn-primary" href="pos-sale-invoice.php"><i class="feather icon-shopping-cart"></i> Open POS</a>
            <a class="rd-btn rd-btn-ghost" href="dashboards-hub.php"><i class="feather icon-grid"></i> All dashboards</a>
        </div>
    </div>
</div>

<?php if ((int) ($rd['retailer_type_id'] ?? 0) <= 0): ?>
    <div class="rd-alert">Customer type <code>CUSTOMER</code> not found in masters. KPIs use all customers until the type exists.</div>
<?php endif; ?>

<div class="rd-metrics">
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-calendar"></i></div>
        <div class="meta">
            <div class="lbl">This week sales</div>
            <div class="val"><?php echo auragold_fmt_money($rx['sales_week']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-bar-chart-2"></i></div>
        <div class="meta">
            <div class="lbl">This month sales</div>
            <div class="val"><?php echo auragold_fmt_money($rx['sales_month']); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-users"></i></div>
        <div class="meta">
            <div class="lbl">Retail customers</div>
            <div class="val"><?php echo number_format((int) $rx['customers_count']); ?></div>
        </div>
    </div>
</div>

<div class="rd-kpi-grid">
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-trending-up"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodSalesLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($rd['sales_today']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-download"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodPurchaseLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($rd['purchase_today']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-package"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodOrdersLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo number_format((int) $rd['orders_today']); ?></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-pocket"></i></div>
        <div class="lbl">Cash In Hand</div>
        <div class="num"><?php echo auragold_fmt_money($rd['cash_today']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($cashTotalSub, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($url_ledger_all('Cash')); ?>">View ledger</a></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-briefcase"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodBankLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($rd['bank_today']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($bankTotalSub, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($url_ledger_all('Bank Account')); ?>">View ledger</a></div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-credit-card"></i></div>
        <div class="lbl"><?php echo htmlspecialchars($periodCardLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="num"><?php echo auragold_fmt_money($rd['card_today']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($cardTotalSub, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="link"><a href="<?php echo htmlspecialchars($url_ledger_all('Card')); ?>">View ledger</a></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Sales overview</h2>
                <span class="rd-badge rd-badge-gold"><?php echo htmlspecialchars($chartBadge, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="rd-chart-wrap">
                <canvas id="retailSalesChart"></canvas>
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
                $rate = auragold_market_rate($rd['market'][$key] ?? null);
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
            <?php if (!empty($rx['recent_invoices'])): ?>
            <div class="table-responsive">
                <table class="rd-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rx['recent_invoices'] as $inv): ?>
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
            <div class="rd-empty">No sale invoices yet. <a href="sale-invoice.php">Create one</a></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Pending orders</h2>
                <a href="sale-order.php" class="rd-badge">View all</a>
            </div>
            <?php if (!empty($rx['pending_orders'])): ?>
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
                    <?php foreach ($rx['pending_orders'] as $ord): ?>
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

<div class="rd-foot">
    <span>Gold Matrix · Retailer dashboard</span>
    <span><a href="account-ledger.php">Account ledger</a> · <a href="dashboard-gold-rates.php">Gold rates</a></span>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('retailSalesChart');
    if (!ctx || typeof Chart === 'undefined') return;
    var labels = <?php echo $labelsJson; ?>;
    var values = <?php echo $valuesJson; ?>;
    var gold = '#b8954a';
    var ink = '#1a2332';
    var grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, 'rgba(184, 149, 74, 0.28)');
    grad.addColorStop(1, 'rgba(184, 149, 74, 0.02)');
    var retailChart = new Chart(ctx, {
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
        if (retailChart) retailChart.resize();
    });
})();
</script>
