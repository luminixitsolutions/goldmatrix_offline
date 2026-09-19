<?php
/**
 * Expects: $auragold_segment_code (CUSTOMER | WHOLESALER | JOB_WORKER), $DASHBOARD_PAGE_TITLE
 */
$code = isset($auragold_segment_code) ? trim((string) $auragold_segment_code) : '';
$typeId = auragold_customer_type_id_by_code($code);
$label = auragold_customer_type_label($code);
$sum = auragold_dashboard_segment_summary($typeId);
$recent = auragold_dashboard_segment_recent_invoices($typeId, 15);
$top = auragold_dashboard_segment_top_customers($typeId, 10);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <div class="dash-page-title"><?php echo htmlspecialchars($DASHBOARD_PAGE_TITLE ?? 'Segment'); ?></div>
        <div class="dash-page-sub">Sales and customers linked to master type <strong><?php echo htmlspecialchars($label); ?></strong>
            (code <?php echo htmlspecialchars($code); ?><?php echo $typeId ? ', id ' . (int) $typeId : ''; ?>).</div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="dashboards-hub.php"><i class="feather icon-grid me-1"></i> All dashboards</a>
</div>

<?php if ($typeId <= 0): ?>
    <div class="alert alert-warning">Customer type code was not found in <code>tbl_customer_types</code>. Add or enable it under masters.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dash-stat-card">
            <div class="lbl">Customers (type)</div>
            <div class="val"><?php echo number_format((int) $sum['customer_count']); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dash-stat-card">
            <div class="lbl">With sales</div>
            <div class="val"><?php echo number_format((int) $sum['customers_with_sales']); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dash-stat-card">
            <div class="lbl">Sale invoices</div>
            <div class="val"><?php echo number_format((int) $sum['invoice_count']); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dash-stat-card">
            <div class="lbl">Sale total</div>
            <div class="val"><?php echo number_format($sum['sale_total'], 2); ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="dash-table-wrap">
            <div class="px-3 py-2 border-bottom bg-light"><strong>Top customers</strong> by sale total</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Customer</th><th class="text-end">Invoices</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php if (empty($top)): ?>
                        <tr><td colspan="3" class="text-muted text-center py-4">No data</td></tr>
                    <?php else: foreach ($top as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                            <td class="text-end"><?php echo number_format((int) ($row['inv_count'] ?? 0)); ?></td>
                            <td class="text-end"><?php echo number_format((float) ($row['sale_total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="dash-table-wrap">
            <div class="px-3 py-2 border-bottom bg-light"><strong>Recent sale invoices</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="4" class="text-muted text-center py-4">No invoices</td></tr>
                    <?php else: foreach ($recent as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row['invoice_no'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['invoice_date'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['customer_name'] ?? '')); ?></td>
                            <td class="text-end"><?php echo number_format((float) ($row['grand_total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
