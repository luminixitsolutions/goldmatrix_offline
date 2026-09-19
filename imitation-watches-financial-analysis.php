<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_sale_financial_analysis_data.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

/** Sale invoice lines: Imitation Or Watches metal for the logged-in (effective) branch. */
$eff_iwa_fin_br = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
$iwa_rows = isset($conn) && $conn instanceof mysqli
    ? auragold_fetch_financial_analysis_sale_lines($conn, $eff_iwa_fin_br, 'imitation')
    : [];

/**
 * Imitation Or Watches Analysis — Financial Statement style.
 */
$iwa_fields = [
    'branch' => 'Branch',
    'date' => 'Date',
    'ledger_name' => 'Ledger Name',
    'invoice_no' => 'Invoice No.',
    'sales_person' => 'Sales Person',
    'article' => 'Article',
    'barcode' => 'Barcode',
    'product' => 'Product',
    'category' => 'Category',
    'pcs' => 'Pcs',
    'purity' => 'Purity',
    'purity_per' => 'Purity Per',
    'gross_wt' => 'Gross Wt.',
    'stone_wt' => 'Stone Wt.',
    'net_wt' => 'Net Wt',
    'final_wt' => 'Final Wt',
    'gold_rate' => 'Rate',
    'current_gold_rate' => 'Current Rate',
    'metal_amt' => 'Metal Amt.',
    'metal_cost' => 'Metal Cost',
    'making_type' => 'Making Type',
    'making_rate' => 'Making Rate',
    'making_amt' => 'Making Amt.',
    'collected_making' => 'Collected Making',
    'collected_making_charge' => 'Collected Making Charge',
    'making_cost' => 'Making Cost',
    'making_profit' => 'Making Profit',
    'stone_charge' => 'Stone Charge',
    'stone_cost' => 'Stone Cost',
    'stone_profit' => 'Stone Profit',
    'other_charges' => 'Other Charges',
    'discount' => 'Discount',
    'discount_per' => 'DiscountPer',
    'net_amount' => 'Net Amount',
    'tax_amount' => 'Tax Amount',
    'sales_amount' => 'Sales Amount',
    'cost_price' => 'Cost Price',
    'profit' => 'Profit',
    'profit_per' => 'ProfitPer',
    'supplier_name' => 'Supplier Name',
    'barcoded_date' => 'Barcoded Date',
];

$row_count = count($iwa_rows);
$todayIwa = new DateTimeImmutable('today');
$yIwa = (int) $todayIwa->format('Y');
$mIwa = (int) $todayIwa->format('n');
$fyStartIwa = $mIwa >= 4 ? $yIwa : ($yIwa - 1);
$default_range = sprintf('01-04-%d - 31-03-%d', $fyStartIwa, $fyStartIwa + 1);

$DASHBOARD_PAGE_TITLE = 'Imitation Or Watches Analysis';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .iwa-wrap {
        max-width: 100%;
        --iwa-gold: #c9a227;
        --iwa-gold-mid: #b8941f;
        --iwa-gold-dark: #8b6914;
        --iwa-navy: #11294b;
        --iwa-navy-deep: #0c1f38;
    }
    .iwa-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--iwa-gold-mid) 45%, var(--iwa-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .iwa-page-title { color: var(--iwa-gold-dark); -webkit-text-fill-color: var(--iwa-gold-dark); }
    }
    .iwa-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 1rem;
    }
    .iwa-subnav a {
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
    .iwa-subnav a:hover { background: #fffbf0; border-color: var(--iwa-gold-mid); color: var(--iwa-gold-dark); }
    .iwa-subnav a.iwa-subnav-active {
        background: linear-gradient(180deg, #5b4b9a 0%, #4338ca 100%);
        border-color: #3730a3;
        color: #fff !important;
    }
    .iwa-toolbar .form-control.iwa-date-range {
        max-width: 260px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .iwa-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-iwa-outline {
        border: 1px solid var(--iwa-gold-mid) !important;
        color: var(--iwa-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 0.85rem;
    }
    .btn-iwa-outline:hover { background: #fffbf0 !important; border-color: var(--iwa-gold) !important; }
    .btn-iwa-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--iwa-gold-mid) 55%, var(--iwa-gold-dark) 100%) !important;
        border: 1px solid var(--iwa-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
        text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    .btn-iwa-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .iwa-filter-wrap { position: relative; display: inline-block; }
    .iwa-table-outer {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .iwa-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .iwa-table-main {
        margin-bottom: 0;
        font-size: 13px;
        min-width: max-content;
    }
    .iwa-table-main thead th {
        background: linear-gradient(180deg, var(--iwa-navy) 0%, var(--iwa-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-bottom: 2px solid var(--iwa-gold-dark) !important;
        white-space: nowrap;
        padding: 10px 12px;
        vertical-align: middle;
    }
    .iwa-table-main thead th .iwa-col-settings {
        margin-left: 6px;
        opacity: 0.9;
        cursor: default;
    }
    .iwa-table-main tbody td {
        padding: 8px 12px;
        vertical-align: middle;
        border-color: #eef0f3;
        white-space: nowrap;
    }
    .iwa-table-main tbody tr:nth-child(even) td { background: #fafbfc; }
    .iwa-table-main tbody tr:hover td { background: #fff9ec !important; }
    .iwa-num { text-align: right; font-variant-numeric: tabular-nums; }
    .iwa-footer-bar {
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
    .iwa-pager { display: flex; align-items: center; gap: 6px; }
    .iwa-pager button {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        color: #64748b;
    }
    .iwa-pager button:disabled { opacity: 0.45; cursor: not-allowed; }
    .iwa-export-dd { position: relative; display: inline-block; }
    .iwa-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .iwa-export-dd > summary::-webkit-details-marker { display: none; }
    .iwa-export-menu {
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
    .iwa-export-menu a {
        display: block;
        padding: 8px 14px;
        color: #374151;
        text-decoration: none;
        font-size: 13px;
    }
    .iwa-export-menu a:hover { background: #fffbf0; color: var(--iwa-gold-dark); }
</style>
HTML;

$iwa_numeric_keys = [
    'gross_wt', 'stone_wt', 'net_wt', 'final_wt', 'gold_rate', 'current_gold_rate',
    'metal_amt', 'metal_cost', 'making_rate', 'making_amt', 'collected_making',
    'collected_making_charge', 'making_cost', 'making_profit', 'stone_charge', 'stone_cost',
    'stone_profit', 'other_charges', 'discount', 'discount_per', 'net_amount', 'tax_amount',
    'sales_amount', 'cost_price', 'profit', 'profit_per',
];

require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="iwa-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <h1 class="iwa-page-title mb-0">Imitation Or Watches Analysis</h1>
        <div class="iwa-toolbar d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control iwa-date-range border-start-0" id="iwaDateRange" value="<?php echo htmlspecialchars($default_range); ?>" readonly aria-label="Date range">
            </div>
            <div class="iwa-filter-wrap" title="Branch-scoped data for your login; date filters can be added later.">
                <button type="button" class="btn btn-iwa-outline" id="iwaFilter" aria-label="Filter">
                    <i class="feather icon-filter"></i>
                </button>
            </div>
            <button type="button" class="btn btn-iwa-outline" id="iwaRefresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
            <details class="iwa-export-dd" data-fs-root="#iwaMainTable" data-fs-file="imitation-watches-financial-analysis" data-fs-title="Imitation Or Watches Financial Analysis">
                <summary class="btn btn-iwa-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="iwa-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <nav class="iwa-subnav" aria-label="Financial statement analysis">
        <a href="sale-analysis.php">Sale Reports</a>
        <a href="gold-silver-financial-analysis.php">Gold Silver Analysis</a>
        <a href="diamond-stone-financial-analysis.php">Diamond &amp; Stone Analysis</a>
        <a href="imitation-watches-financial-analysis.php" class="iwa-subnav-active">Imitation Or Watches</a>
        <a href="salesperson-performance.php">Salesperson Performance</a>
        <a href="vendor-report.php">Vendor Report</a>
    </nav>

    <div class="iwa-table-outer">
        <div class="iwa-table-scroll">
            <table id="iwaMainTable" class="table iwa-table-main acr-col-table">
                <thead>
                    <tr>
                        <?php
                        $iwa_col_i = 0;
                        $iwa_col_total = count($iwa_fields);
                        foreach ($iwa_fields as $key => $label):
                            $iwa_col_i++;
                        ?>
                        <th data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($label); ?>
                            <?php if ($iwa_col_i === $iwa_col_total): ?>
                            <i class="feather icon-settings iwa-col-settings" title="Column settings (placeholder)"></i>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($row_count === 0): ?>
                    <tr>
                        <td colspan="<?php echo (int) count($iwa_fields); ?>" class="text-center text-muted py-4">No sale lines found for Imitation Or Watches in this branch.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($iwa_rows as $row): ?>
                    <tr>
                        <?php foreach (array_keys($iwa_fields) as $key): ?>
                        <?php
                        $val = isset($row[$key]) ? $row[$key] : '';
                        $is_num = in_array($key, $iwa_numeric_keys, true);
                        ?>
                        <td data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $is_num ? 'iwa-num' : ''; ?>"><?php echo htmlspecialchars($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="iwa-footer-bar">
            <?php
            $iwa_show_high = $row_count > 0 ? $row_count : 0;
            ?>
            <span><?php echo $iwa_show_high > 0
                ? 'Showing <strong>1</strong> to <strong>' . $iwa_show_high . '</strong> of <strong>' . $iwa_show_high . '</strong> entries'
                : '<strong>0</strong> entries'; ?></span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 small">Show</label>
                <select class="form-control form-control-sm" style="width:auto; min-width:120px;" disabled aria-label="Page size">
                    <option>All Items</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>
            <div class="iwa-pager">
                <button type="button" disabled aria-label="First">«</button>
                <button type="button" disabled aria-label="Previous">‹</button>
                <button type="button" disabled aria-label="Next">›</button>
                <button type="button" disabled aria-label="Last">»</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    document.getElementById('iwaRefresh').addEventListener('click', function () {
        window.location.reload();
    });
    document.getElementById('iwaFilter').addEventListener('click', function () {
        alert('Data is limited to sale invoice lines for your branch (Imitation Or Watches). Filters can extend date range later.');
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.AuragoldColReorder) {
        AuragoldColReorder.init('#iwaMainTable', { storageKey: 'auragold_colorder_imitation_watches_financial', fixedFirst: true });
    }
});
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
