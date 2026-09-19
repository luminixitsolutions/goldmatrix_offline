<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/dashboard_helpers.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$rSum = auragold_dashboard_segment_summary(auragold_customer_type_id_by_code('CUSTOMER'));
$wSum = auragold_dashboard_segment_summary(auragold_customer_type_id_by_code('WHOLESALER'));
$mSum = auragold_dashboard_segment_summary(auragold_customer_type_id_by_code('JOB_WORKER'));
$spRows = auragold_dashboard_sales_by_salesperson(5);
$spTotal = 0.0;
foreach ($spRows as $r) {
    $spTotal += (float) ($r['sale_total'] ?? 0);
}
$goldRateRows = auragold_dashboard_gold_metal_rates_from_sales(20);
$st = auragold_dashboard_stock_overview();

$DASHBOARD_PAGE_TITLE = 'Dashboards hub';
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="mb-3">
    <div class="dash-page-title">Dashboards</div>
    <div class="dash-page-sub">Quick links to segment and operational views. Data comes from your branch database (same as other screens).</div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-xl-4">
        <div class="dash-stat-card h-100 d-flex flex-column">
            <div class="lbl">Retailer (Customer type)</div>
            <div class="val"><?php echo number_format((int) $rSum['invoice_count']); ?> inv · <?php echo number_format($rSum['sale_total'], 2); ?></div>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="dashboard-retailer.php">Open</a>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="dash-stat-card h-100 d-flex flex-column">
            <div class="lbl">Wholesaler</div>
            <div class="val"><?php echo number_format((int) $wSum['invoice_count']); ?> inv · <?php echo number_format($wSum['sale_total'], 2); ?></div>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="dashboard-wholesaler.php">Open</a>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="dash-stat-card h-100 d-flex flex-column">
            <div class="lbl">Manufacturing / Job worker</div>
            <div class="val"><?php echo number_format((int) $mSum['invoice_count']); ?> inv · <?php echo number_format($mSum['sale_total'], 2); ?></div>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="dashboard-manufacturing.php">Open</a>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="dash-stat-card h-100 d-flex flex-column">
            <div class="lbl">Sales person</div>
            <div class="val"><?php echo number_format($spTotal, 2); ?> (top 5 sum)</div>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="dashboard-sales-person.php">Open</a>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="dash-stat-card h-100 d-flex flex-column">
            <div class="lbl">Gold rates</div>
            <div class="val"><?php echo count($goldRateRows) ? count($goldRateRows) . ' carat groups' : '—'; ?></div>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="dashboard-gold-rates.php">Open</a>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="dash-stat-card h-100 d-flex flex-column">
            <div class="lbl">Stock</div>
            <div class="val"><?php echo number_format((float) ($st['totals']['value'] ?? 0), 2); ?> value</div>
            <a class="btn btn-sm btn-primary mt-auto align-self-start" href="dashboard-stock.php">Open</a>
        </div>
    </div>
</div>

<div class="mt-4">
    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php"><i class="feather icon-home me-1"></i> Main dashboard (rates UI)</a>
</div>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
