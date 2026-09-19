<?php
/**
 * Manufacturing / jobwork home dashboard. Expects dashboard_helpers loaded.
 * Optional: $mfgBounds from dashboard-manufacturing.php (date range filter).
 */
if (!isset($mfg) || !is_array($mfg)) {
    $mfg = auragold_manufacturing_dashboard();
}
$mfgDateFrom = isset($mfgBounds['start']) ? (string) $mfgBounds['start'] : (string) ($mfg['date_from'] ?? date('Y-m-d'));
$mfgDateTo = isset($mfgBounds['end']) ? (string) $mfgBounds['end'] : (string) ($mfg['date_to'] ?? date('Y-m-d'));
$mx = auragold_manufacturing_dashboard_extras($mfgDateFrom, $mfgDateTo);
$k = $mfg['kpi'];

$wsLabels = [];
$wsValues = [];
foreach ($mfg['workstation_rows'] ?? [] as $wr) {
    $wsLabels[] = (string) ($wr['dept_label'] ?? $wr['dept_key'] ?? '—');
    $wsValues[] = (int) ($wr['order_count'] ?? 0);
}
$wsLabelsJson = json_encode($wsLabels, JSON_UNESCAPED_UNICODE);
$wsValuesJson = json_encode($wsValues, JSON_UNESCAPED_UNICODE);

$filterDateFrom = (string) ($mfg['date_from'] ?? $mfgDateFrom);
$filterDateTo = (string) ($mfg['date_to'] ?? $mfgDateTo);
$mfgIsToday = !empty($mfg['is_today']);
$mfgIsSingleDay = !empty($mfg['is_single_day']);
$chartBadge = $mfgIsSingleDay
    ? date('d M Y', strtotime($filterDateFrom))
    : date('d M', strtotime($filterDateFrom)) . ' – ' . date('d M Y', strtotime($filterDateTo));
$dueMetricLabel = $mfgIsToday ? 'Due today' : ($mfgIsSingleDay ? 'Due on date' : 'Due in period');
$completedLabel = $mfgIsToday ? 'Completed today' : ($mfgIsSingleDay ? 'Completed' : 'Completed in period');
$jobworkMetricLabel = $mfgIsToday ? 'Jobwork today' : ($mfgIsSingleDay ? 'Jobwork on date' : 'Jobwork in period');

if (!function_exists('mfg_esc')) {
    function mfg_esc($s) {
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
?>
<style>
.mfg-dash {
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

.mfg-dash .rd-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.mfg-dash .rd-top-copy {
    min-width: 180px;
}
.mfg-dash .rd-top-copy .rd-greeting {
    font-size: 12px;
    color: var(--rd-gold-deep);
    font-weight: 600;
    letter-spacing: 0.02em;
}
.mfg-dash .rd-top-copy h1 {
    margin: 1px 0 0;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--rd-ink);
    line-height: 1.2;
}
.mfg-dash .rd-top-copy .rd-date {
    margin-top: 2px;
    font-size: 12px;
    color: var(--rd-muted);
}
.mfg-dash .rd-top-mid {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1 1 auto;
    justify-content: flex-end;
}
.mfg-dash .rd-top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.mfg-dash .rd-btn {
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
.mfg-dash .rd-btn:hover { text-decoration: none; transform: translateY(-1px); }
.mfg-dash .rd-btn-primary {
    background: var(--rd-ink);
    color: #fff;
    box-shadow: 0 4px 12px rgba(26, 35, 50, 0.16);
}
.mfg-dash .rd-btn-primary:hover { color: #fff; background: #243044; }
.mfg-dash .rd-btn-ghost {
    background: var(--rd-surface);
    color: var(--rd-ink);
    border-color: var(--rd-line);
}
.mfg-dash .rd-btn-ghost:hover { color: var(--rd-ink); background: #faf9f6; }

.mfg-dash .rd-toolbar {
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
.mfg-dash .rd-toolbar label {
    display: none;
}
.mfg-dash .rd-toolbar input[type="date"] {
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
.mfg-dash .rd-toolbar .rd-btn-apply {
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
.mfg-dash .rd-toolbar .rd-btn-reset {
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
.mfg-dash .rd-toolbar .rd-btn-reset:hover { color: var(--rd-ink); text-decoration: none; }

.mfg-dash .rd-alert {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    background: #fff8eb;
    border: 1px solid #f0e0b8;
    color: #6b5320;
    font-size: 12px;
}

.mfg-dash .rd-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 10px;
}
.mfg-dash .rd-metric {
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
.mfg-dash .rd-metric:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.mfg-dash .rd-metric .icon {
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
.mfg-dash .rd-metric .meta { min-width: 0; }
.mfg-dash .rd-metric .lbl {
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-muted);
}
.mfg-dash .rd-metric .val {
    margin-top: 2px;
    font-size: 1.25rem;
    font-weight: 750;
    letter-spacing: -0.02em;
    color: var(--rd-ink);
    line-height: 1.15;
}

.mfg-dash .rd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 12px;
}
.mfg-dash .rd-kpi {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    box-shadow: var(--rd-shadow);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
    transition: transform .15s ease, background .15s ease;
}
.mfg-dash .rd-kpi:hover {
    transform: translateY(-1px);
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold-deep) 50%, #243044 50%) border-box;
}
.mfg-dash .rd-kpi .icon {
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
.mfg-dash .rd-kpi .lbl {
    font-size: 11px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    line-height: 1.3;
}
.mfg-dash .rd-kpi .num {
    margin-top: 4px;
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--rd-ink);
    line-height: 1.2;
    letter-spacing: -0.02em;
}
.mfg-dash .rd-kpi .sub {
    margin-top: 4px;
    font-size: 11px;
    color: var(--rd-muted);
}

.mfg-dash .rd-panel {
    border: 2px solid transparent;
    border-radius: 14px;
    box-shadow: var(--rd-shadow);
    padding: 14px 16px;
    height: 100%;
    display: flex;
    flex-direction: column;
    background:
        linear-gradient(var(--rd-surface), var(--rd-surface)) padding-box,
        linear-gradient(to right, var(--rd-gold) 50%, var(--rd-ink) 50%) border-box;
}
.mfg-dash .rd-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.mfg-dash .rd-panel-head h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--rd-ink);
    letter-spacing: -0.01em;
}
.mfg-dash .rd-badge {
    font-size: 11px;
    font-weight: 650;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--rd-canvas);
    color: var(--rd-muted);
    border: 1px solid var(--rd-line);
    text-decoration: none;
    white-space: nowrap;
}
.mfg-dash .rd-badge:hover { color: var(--rd-ink); text-decoration: none; }
.mfg-dash .rd-badge-gold {
    background: var(--rd-gold-soft);
    color: var(--rd-gold-deep);
    border-color: #e6d7b0;
}
.mfg-dash .rd-badge-warn {
    background: #fef3e8;
    color: #8a5a20;
    border-color: #ecd9b8;
}
.mfg-dash .rd-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}
.mfg-dash .rd-panel-body {
    flex: 1;
    min-height: 0;
}
.mfg-dash .rd-panel-foot {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--rd-line);
    font-size: 12px;
    color: var(--rd-muted);
}
.mfg-dash .rd-panel-foot strong {
    color: var(--rd-ink-soft);
    font-weight: 650;
}

.mfg-dash .rd-table {
    width: 100%;
    font-size: 13px;
    margin: 0;
}
.mfg-dash .rd-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--rd-muted);
    border-bottom: 1px solid var(--rd-line);
    padding: 8px 10px;
    background: transparent;
}
.mfg-dash .rd-table tbody td {
    padding: 11px 10px;
    border-bottom: 1px solid #f3f1ec;
    vertical-align: middle;
    color: var(--rd-ink-soft);
}
.mfg-dash .rd-table tbody tr:last-child td { border-bottom: 0; }
.mfg-dash .rd-table tbody tr:hover td { background: #faf9f6; }
.mfg-dash .rd-table .cnt {
    font-weight: 700;
    color: var(--rd-ink);
    text-align: right;
    white-space: nowrap;
}
.mfg-dash .rd-table .link-cell a {
    color: var(--rd-ink);
    font-weight: 650;
    text-decoration: none;
}
.mfg-dash .rd-table .link-cell a:hover { color: var(--rd-gold-deep); }
.mfg-dash .rd-table .due-over { color: #9a3412; font-weight: 700; }
.mfg-dash .rd-table .due-soon { color: var(--rd-gold-deep); font-weight: 600; }

.mfg-dash .rd-status {
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
.mfg-dash .rd-status-hold {
    background: #f6f0e4;
    color: #7a5c28;
}
.mfg-dash .rd-status-done {
    background: #eef3ed;
    color: #3d5c44;
}
.mfg-dash .rd-status-late {
    background: #faf0ea;
    color: #8a4520;
}

.mfg-dash .rd-empty {
    text-align: center;
    padding: 28px 16px;
    color: var(--rd-muted);
    font-size: 13px;
}
.mfg-dash .rd-empty a { color: var(--rd-gold-deep); font-weight: 600; }

.mfg-dash .rd-foot {
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
.mfg-dash .rd-foot a { color: var(--rd-muted); text-decoration: none; }
.mfg-dash .rd-foot a:hover { color: var(--rd-gold-deep); }

@media (max-width: 1199.98px) {
    .mfg-dash .rd-top-mid { justify-content: flex-start; width: 100%; order: 3; }
    .mfg-dash .rd-toolbar { width: 100%; }
    .mfg-dash .rd-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 991.98px) {
    .mfg-dash .rd-metrics { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
    .mfg-dash .rd-top-copy h1 { font-size: 1.2rem; }
    .mfg-dash .rd-top-actions { width: 100%; }
    .mfg-dash .rd-top-actions .rd-btn { flex: 1; justify-content: center; }
    .mfg-dash .rd-toolbar input[type="date"] { width: 100%; flex: 1 1 120px; }
    .mfg-dash .rd-kpi-grid { grid-template-columns: 1fr; }
    .mfg-dash .rd-chart-wrap { height: 220px; }
}
</style>

<div class="mfg-dash">

<div class="rd-top">
    <div class="rd-top-copy">
        <div class="rd-greeting"><?php echo mfg_esc($greeting); ?></div>
        <h1>Manufacturing Dashboard</h1>
        <div class="rd-date"><?php echo mfg_esc($todayLabel); ?></div>
    </div>
    <div class="rd-top-mid">
        <form class="rd-toolbar" method="get" action="dashboard-manufacturing.php" id="mfgDateFilterForm">
            <label for="mfg_date_from">From date</label>
            <input type="date" name="date_from" id="mfg_date_from" title="From date" value="<?php echo mfg_esc($filterDateFrom); ?>" required>
            <label for="mfg_date_to">To date</label>
            <input type="date" name="date_to" id="mfg_date_to" title="To date" value="<?php echo mfg_esc($filterDateTo); ?>" required>
            <button type="submit" class="rd-btn-apply">Apply</button>
            <a href="dashboard-manufacturing.php" class="rd-btn-reset">Today</a>
        </form>
        <div class="rd-top-actions">
            <a class="rd-btn rd-btn-primary" href="jobwork-order.php"><i class="feather icon-settings"></i> New Jobwork</a>
            <a class="rd-btn rd-btn-ghost" href="dashboards-hub.php"><i class="feather icon-grid"></i> All dashboards</a>
        </div>
    </div>
</div>

<?php if (empty($mfg['has_jobwork'])): ?>
    <div class="rd-alert">Jobwork orders table not found — showing sale order activity only.</div>
<?php endif; ?>

<div class="rd-metrics">
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-layers"></i></div>
        <div class="meta">
            <div class="lbl"><?php echo mfg_esc($jobworkMetricLabel); ?></div>
            <div class="val"><?php echo number_format((int) ($mfg['total_jobwork'] ?? 0)); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-shopping-cart"></i></div>
        <div class="meta">
            <div class="lbl">Pending sale orders</div>
            <div class="val"><?php echo number_format((int) ($mx['pending_sale_orders'] ?? 0)); ?></div>
        </div>
    </div>
    <div class="rd-metric">
        <div class="icon"><i class="feather icon-calendar"></i></div>
        <div class="meta">
            <div class="lbl"><?php echo mfg_esc($dueMetricLabel); ?></div>
            <div class="val"><?php echo number_format((int) ($mx['jobs_due_week'] ?? 0)); ?></div>
        </div>
    </div>
</div>

<div class="rd-kpi-grid">
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-activity"></i></div>
        <div class="lbl">In Progress</div>
        <div class="num"><?php echo number_format((int) ($k['in_progress'] ?? 0)); ?></div>
        <div class="sub">Open jobs · not overdue</div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-alert-circle"></i></div>
        <div class="lbl">Delayed</div>
        <div class="num"><?php echo number_format((int) ($k['delayed'] ?? 0)); ?></div>
        <div class="sub">Open jobs · past due</div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-pause-circle"></i></div>
        <div class="lbl">On Hold</div>
        <div class="num"><?php echo number_format((int) ($k['on_hold'] ?? 0)); ?></div>
        <div class="sub">Paused / waiting</div>
    </div>
    <div class="rd-kpi">
        <div class="icon"><i class="feather icon-clock"></i></div>
        <div class="lbl">Not Initiated</div>
        <div class="num"><?php echo number_format((int) ($k['not_initiate'] ?? 0)); ?></div>
        <div class="sub">Draft / pending start</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Orders by workstation</h2>
                <span class="rd-badge rd-badge-gold"><?php echo mfg_esc($chartBadge); ?></span>
            </div>
            <div class="rd-chart-wrap">
                <canvas id="mfgWorkstationChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Workstation load</h2>
                <span class="rd-badge">By department</span>
            </div>
            <div class="rd-panel-body">
                <?php if (!empty($mfg['workstation_rows'])): ?>
                <div class="table-responsive">
                    <table class="rd-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th class="text-right">Jobs</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mfg['workstation_rows'] as $wr): ?>
                            <tr>
                                <td><?php echo mfg_esc($wr['dept_label'] ?? $wr['dept_key'] ?? '—'); ?></td>
                                <td class="cnt"><?php echo (int) ($wr['order_count'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="rd-empty">No workstation data yet.</div>
                <?php endif; ?>
            </div>
            <div class="rd-panel-foot">
                Total jobwork: <strong><?php echo number_format((int) ($mfg['total_jobwork'] ?? 0)); ?></strong>
                · <?php echo mfg_esc($completedLabel); ?>: <strong><?php echo number_format((int) ($mx['jobs_completed_month'] ?? 0)); ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Jobs in progress</h2>
                <a href="jobwork-order.php" class="rd-badge">View all</a>
            </div>
            <div class="rd-panel-body">
                <?php if (!empty($mfg['list_in_progress'])): ?>
                <div class="table-responsive">
                    <table class="rd-table">
                        <thead>
                            <tr>
                                <th>Jobwork</th>
                                <th>Customer</th>
                                <th>Sale order</th>
                                <th>Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mfg['list_in_progress'] as $row):
                            $due = (string) ($row['due_date'] ?? '');
                            $dueClass = '';
                            if ($due !== '' && $due < date('Y-m-d')) {
                                $dueClass = 'due-over';
                            } elseif ($due !== '' && $due <= date('Y-m-d', strtotime('+3 days'))) {
                                $dueClass = 'due-soon';
                            }
                        ?>
                            <tr>
                                <td class="link-cell"><a href="jobwork-order.php?id=<?php echo (int) ($row['id'] ?? 0); ?>"><?php echo mfg_esc($row['jobwork_no'] ?? ''); ?></a></td>
                                <td><?php echo mfg_esc($row['customer_name'] ?? '—'); ?></td>
                                <td><?php echo mfg_esc($row['sale_order_no'] ?? '—'); ?></td>
                                <td class="<?php echo mfg_esc($dueClass); ?>"><?php echo mfg_esc($due ?: '—'); ?></td>
                                <td><span class="rd-status"><?php echo mfg_esc($row['status'] ?? 'Open'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="rd-empty">No jobs in progress. <a href="jobwork-order.php">Create jobwork</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Delayed jobs</h2>
                <span class="rd-badge rd-badge-warn"><?php echo number_format((int) ($k['delayed'] ?? 0)); ?> overdue</span>
            </div>
            <div class="rd-panel-body">
                <?php if (!empty($mfg['list_delayed'])): ?>
                <div class="table-responsive">
                    <table class="rd-table">
                        <thead>
                            <tr>
                                <th>Jobwork</th>
                                <th>Customer</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($mfg['list_delayed'], 0, 8) as $row): ?>
                            <tr>
                                <td class="link-cell"><a href="jobwork-order.php?id=<?php echo (int) ($row['id'] ?? 0); ?>"><?php echo mfg_esc($row['jobwork_no'] ?? ''); ?></a></td>
                                <td><?php echo mfg_esc($row['customer_name'] ?? '—'); ?></td>
                                <td class="due-over"><?php echo mfg_esc($row['due_date'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="rd-empty">No delayed jobs — all on track.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Jobs on hold</h2>
            </div>
            <div class="rd-panel-body">
                <?php if (!empty($mfg['list_on_hold'])): ?>
                <div class="table-responsive">
                    <table class="rd-table">
                        <thead>
                            <tr>
                                <th>Jobwork</th>
                                <th>Customer</th>
                                <th>Sale order</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($mfg['list_on_hold'], 0, 8) as $row): ?>
                            <tr>
                                <td class="link-cell"><a href="jobwork-order.php?id=<?php echo (int) ($row['id'] ?? 0); ?>"><?php echo mfg_esc($row['jobwork_no'] ?? ''); ?></a></td>
                                <td><?php echo mfg_esc($row['customer_name'] ?? '—'); ?></td>
                                <td><?php echo mfg_esc($row['sale_order_no'] ?? '—'); ?></td>
                                <td><?php echo mfg_esc($row['due_date'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="rd-empty">No jobs on hold.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Recent sale orders</h2>
                <a href="sale-order.php" class="rd-badge">View all</a>
            </div>
            <div class="rd-panel-body">
                <?php if (!empty($mfg['recent_sale_orders'])): ?>
                <div class="table-responsive">
                    <table class="rd-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Tag no.</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($mfg['recent_sale_orders'], 0, 8) as $row): ?>
                            <tr>
                                <td class="link-cell"><a href="sale-order.php?id=<?php echo (int) ($row['id'] ?? 0); ?>"><?php echo mfg_esc($row['order_no'] ?? ''); ?></a></td>
                                <td><?php echo mfg_esc($row['customer_name'] ?? '—'); ?></td>
                                <td><?php echo mfg_esc($row['tag_no'] ?? '') ?: '—'; ?></td>
                                <td><?php echo mfg_esc($row['order_date'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="rd-empty">No sale orders yet. <a href="sale-order.php">New order</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="rd-panel">
            <div class="rd-panel-head">
                <h2>Completed sale orders</h2>
                <span class="rd-badge rd-badge-gold"><?php echo number_format((int) ($mfg['total_sale_orders'] ?? 0)); ?> total orders</span>
            </div>
            <div class="rd-panel-body">
                <?php if (!empty($mfg['completed_orders'])): ?>
                <div class="table-responsive">
                    <table class="rd-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Tag no.</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($mfg['completed_orders'], 0, 10) as $row): ?>
                            <tr>
                                <td class="link-cell"><a href="sale-order.php?id=<?php echo (int) ($row['id'] ?? 0); ?>"><?php echo mfg_esc($row['order_no'] ?? ''); ?></a></td>
                                <td><?php echo mfg_esc($row['customer_name'] ?? '—'); ?></td>
                                <td><?php echo mfg_esc($row['tag_no'] ?? '') ?: '—'; ?></td>
                                <td><?php echo mfg_esc($row['order_date'] ?? ''); ?></td>
                                <td><span class="rd-status rd-status-done"><?php echo mfg_esc($row['status'] ?? 'Done'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="rd-empty">No completed orders yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="rd-foot">
    <span>Gold Matrix · Manufacturing dashboard · Jobwork: <?php echo number_format((int) ($mfg['total_jobwork'] ?? 0)); ?> · Sale orders: <?php echo number_format((int) ($mfg['total_sale_orders'] ?? 0)); ?></span>
    <span><a href="jobwork-queue.php">Jobwork queue</a> · <a href="manufacturing-process.php">Process</a> · <a href="sale-order.php">Sale orders</a></span>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('mfgWorkstationChart');
    if (!ctx || typeof Chart === 'undefined') return;
    var labels = <?php echo $wsLabelsJson; ?>;
    var values = <?php echo $wsValuesJson; ?>;
    if (!labels.length) {
        labels = ['No data'];
        values = [0];
    }
    var gold = '#b8954a';
    var goldLight = 'rgba(184, 149, 74, 0.72)';
    var goldPale = 'rgba(184, 149, 74, 0.18)';
    var ink = '#1a2332';
    var c2d = ctx.getContext('2d');
    var grad = c2d.createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, goldLight);
    grad.addColorStop(1, goldPale);
    var mfgChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Orders',
                data: values,
                backgroundColor: grad,
                borderColor: gold,
                borderWidth: 1.5,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: gold,
                hoverBorderColor: ink
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
                    ticks: { stepSize: 1, color: '#6b7280', font: { size: 11 } },
                    grid: { color: 'rgba(26, 35, 50, 0.06)' },
                    border: { display: false }
                },
                x: {
                    ticks: { color: '#6b7280', font: { size: 10 }, maxRotation: 45, minRotation: 0 },
                    grid: { display: false },
                    border: { display: false }
                }
            }
        }
    });
    window.addEventListener('resize', function() {
        if (mfgChart) mfgChart.resize();
    });
})();
</script>
