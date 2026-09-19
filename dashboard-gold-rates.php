<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/dashboard_helpers.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$rates = auragold_dashboard_gold_metal_rates_from_sales(25);
$carats = auragold_dashboard_carat_master();
$DASHBOARD_PAGE_TITLE = 'Gold rates dashboard';
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <div class="dash-page-title">Gold rates (from sales)</div>
        <div class="dash-page-sub">Latest activity uses <strong>metal rate</strong> on sale lines for <strong>Gold</strong> (<code>tbl_product_characteristics.metal_id = 1</code>). There is no separate daily rate table in this schema.</div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="dashboards-hub.php"><i class="feather icon-grid me-1"></i> All dashboards</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="dash-table-wrap">
            <div class="px-3 py-2 border-bottom bg-light"><strong>Metal rate by carat</strong> (from sale invoice lines)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Carat</th>
                            <th class="text-end">Avg rate</th>
                            <th class="text-end">Min</th>
                            <th class="text-end">Max</th>
                            <th>Last invoice date</th>
                            <th class="text-end">Lines</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rates)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No gold lines with metal rate recorded yet.</td></tr>
                    <?php else: foreach ($rates as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row['carat'] ?? '')); ?></td>
                            <td class="text-end"><?php echo number_format((float) ($row['avg_metal_rate'] ?? 0), 2); ?></td>
                            <td class="text-end"><?php echo number_format((float) ($row['min_metal_rate'] ?? 0), 2); ?></td>
                            <td class="text-end"><?php echo number_format((float) ($row['max_metal_rate'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['last_invoice_date'] ?? '')); ?></td>
                            <td class="text-end"><?php echo number_format((int) ($row['line_count'] ?? 0)); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="dash-table-wrap">
            <div class="px-3 py-2 border-bottom bg-light"><strong>Carat master</strong> (<code>tbl_carat</code>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th class="text-end">Purity</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php if (empty($carats)): ?>
                        <tr><td colspan="3" class="text-muted text-center py-4">No active carat rows</td></tr>
                    <?php else: foreach ($carats as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars((string) ($row['purity'] ?? '')); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars((string) ($row['description'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
