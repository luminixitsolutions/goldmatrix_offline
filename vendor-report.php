<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/auragold_vendor_report_data.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$vr_fields = [
    'product_name'  => 'Product Name',
    'vendor_name'   => 'Vendor Name',
    'metal_name'    => 'Metal',
    'article'       => 'Article',
    'category'      => 'Category',
    'branch'        => 'Branch',
    'opening_wt'    => 'Total Opening Wt',
    'opening_qty'   => 'Opening Qty',
    'sale_wt'       => 'Total Sale Wt',
    'sale_qty'      => 'Total Sale Qty',
    'purchase_wt'   => 'Total Purchase Wt',
    'purchase_qty'  => 'Total Purchase Qty',
    'balance_wt'    => 'Balance Stock Wt',
    'balance_qty'   => 'Balance Stock Qty',
];

$vr_normalize_date_param = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        return ($d && $d->format('Y-m-d') === $raw) ? $d->format('d-m-Y') : '';
    }
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $raw)) {
        $d = DateTimeImmutable::createFromFormat('d-m-Y', $raw);
        return ($d && $d->format('d-m-Y') === $raw) ? $raw : '';
    }
    return '';
};

$default_range_label = '';
$vr_from_param = $vr_normalize_date_param((string) ($_GET['vr_from'] ?? ''));
$vr_to_param   = $vr_normalize_date_param((string) ($_GET['vr_to'] ?? ''));
if ($vr_from_param !== '' && $vr_to_param !== '') {
    $default_range_label = $vr_from_param . ' - ' . $vr_to_param;
} else {
    $todayVr = new DateTimeImmutable('today');
    $yVr     = (int) $todayVr->format('Y');
    $mVr     = (int) $todayVr->format('n');
    $fyStart = $mVr >= 4 ? $yVr : ($yVr - 1);
    $default_range_label = sprintf('01-04-%d - 31-03-%d', $fyStart, $fyStart + 1);
}
$vr_range = auragold_sale_analysis_parse_range($default_range_label);
$default_range = $vr_range['label'];

$filter_vendor  = max(0, (int) ($_GET['vr_vendor'] ?? 0));
$filter_product = max(0, (int) ($_GET['vr_product'] ?? 0));
$filter_metal   = max(0, (int) ($_GET['vr_metal'] ?? 0));
$filter_search  = trim((string) ($_GET['vr_search'] ?? ''));

/** @var array{vendors:array,products:array,metals:array} */
$vr_lists = ['vendors' => [], 'products' => [], 'metals' => []];
/** @var array<int, array<string,string>> */
$vr_rows = [];
/** @var array<string,string> */
$vr_totals = [];

global $conn;
if (isset($conn) && $conn instanceof mysqli) {
    $vr_lists = auragold_vendor_report_filter_lists($conn);
    $vr_rows = auragold_vendor_report_fetch_rows($conn, [
        'vendor_id'  => $filter_vendor,
        'product_id' => $filter_product,
        'metal_id'   => $filter_metal,
        'search'     => $filter_search,
        'from_ymd'   => $vr_range['from_ymd'],
        'to_ymd'     => $vr_range['to_ymd'],
    ]);
    if ($vr_rows !== []) {
        $vr_totals = auragold_vendor_report_totals($vr_rows);
    }
}

$row_count = count($vr_rows);
$vr_show_from = $row_count > 0 ? 1 : 0;
$active_filter_count = 0;
if ($filter_vendor > 0) {
    $active_filter_count++;
}
if ($filter_product > 0) {
    $active_filter_count++;
}
if ($filter_metal > 0) {
    $active_filter_count++;
}
if ($filter_search !== '') {
    $active_filter_count++;
}

$num_cols = [
    'opening_wt', 'opening_qty', 'sale_wt', 'sale_qty', 'purchase_wt', 'purchase_qty', 'balance_wt', 'balance_qty',
];

$DASHBOARD_PAGE_TITLE = 'Vendor Report';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .vr-wrap {
        max-width: 100%;
        --vr-gold: #c9a227;
        --vr-gold-mid: #b8941f;
        --vr-gold-dark: #8b6914;
        --vr-navy: #11294b;
        --vr-navy-deep: #0c1f38;
    }
    .vr-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--vr-gold-mid) 45%, var(--vr-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .vr-page-title { color: var(--vr-gold-dark); -webkit-text-fill-color: var(--vr-gold-dark); }
    }
    .vr-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 1rem;
    }
    .vr-subnav a {
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
    .vr-subnav a:hover { background: #fffbf0; border-color: var(--vr-gold-mid); color: var(--vr-gold-dark); }
    .vr-subnav a.vr-subnav-active {
        background: linear-gradient(180deg, #5b4b9a 0%, #4338ca 100%);
        border-color: #3730a3;
        color: #fff !important;
    }
    .btn-vr-outline {
        border: 1px solid var(--vr-gold-mid) !important;
        color: var(--vr-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 0.85rem;
    }
    .btn-vr-outline:hover { background: #fffbf0 !important; border-color: var(--vr-gold) !important; }
    .btn-vr-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--vr-gold-mid) 100%) !important;
        border: 1px solid var(--vr-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 0.85rem;
    }
    .vr-range-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.35rem 0.75rem;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        background: #fff;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        max-width: 100%;
    }
    .vr-range-chip i { color: #a67c1a; }
    .vr-adv-modal .modal-content {
        border: none;
        border-radius: 12px;
        overflow: visible;
        box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2);
    }
    .vr-adv-modal .modal-dialog { max-width: 720px; width: calc(100vw - 32px); }
    .vr-adv-modal .modal-header.vr-adv-modal-header {
        flex-direction: column;
        align-items: stretch;
        padding: 16px 44px 14px 20px;
        background: linear-gradient(135deg, #11294b 0%, #1a3d66 100%);
        border-bottom: 3px solid #c9a962;
    }
    .vr-adv-modal .modal-title {
        width: 100%;
        text-align: center;
        font-weight: 700;
        font-size: 1.05rem;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .vr-adv-modal .modal-sub {
        text-align: center;
        font-size: 0.78rem;
        color: rgba(255,255,255,.78);
        margin: 8px 0 0;
    }
    .vr-adv-modal .close {
        position: absolute;
        right: 14px;
        top: 18px;
        opacity: .85;
        color: #fff;
        text-shadow: none;
    }
    .vr-adv-modal .close:hover { opacity: 1; color: #c9a962; }
    .vr-adv-modal .modal-body { padding: 20px 22px 12px; background: #fafbfc; overflow: visible; }
    .vr-adv-modal .modal-footer-adv {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px 22px 22px;
        border: none;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }
    .vr-adv-modal .btn-adv-apply {
        background: #fff;
        color: #11294b;
        border: 2px solid #11294b;
        font-weight: 600;
        padding: 8px 26px;
        border-radius: 8px;
    }
    .vr-adv-modal .btn-adv-clear {
        background: #fff;
        color: #b45309;
        border: 2px solid #f5c2a7;
        font-weight: 600;
        padding: 8px 26px;
        border-radius: 8px;
    }
    .vr-adv-section {
        grid-column: 1 / -1;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin: 8px 0 4px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .vr-adv-modal .filter-grid { margin-top: 0; }
    .vr-adv-modal .filter-field .select2-container { width: 100% !important; }
    .vr-adv-modal .select2-container--default .select2-selection--single {
        height: 34px;
        min-height: 34px;
        border: 1px solid #cfd6e6;
        border-radius: 6px;
        font-size: 13px;
    }
    .vr-adv-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px;
        padding-left: 10px;
        color: #334155;
    }
    .vr-adv-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px;
    }
    .select2-container.vr-filter-select2-dropdown {
        z-index: 10950 !important;
    }
    .select2-dropdown.vr-filter-select2-dropdown {
        z-index: 10950 !important;
        font-size: 13px;
    }
    .vr-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
    }
    .vr-table-outer {
        background: #fff;
        border: 1px solid rgba(17, 41, 75, 0.1);
        border-radius: 12px;
        overflow: visible;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }
    .vr-table-scroll { overflow-x: auto; max-height: calc(100vh - 340px); -webkit-overflow-scrolling: touch; }
    .vr-table-main { margin-bottom: 0; font-size: 13px; min-width: max-content; table-layout: auto; }
    table.vr-table-main.acr-col-table thead th {
        position: relative !important;
        top: auto !important;
        z-index: 2;
        background: linear-gradient(180deg, var(--vr-navy) 0%, var(--vr-navy-deep) 100%) !important;
        color: #ffffff !important;
        font-weight: 700;
        border-color: rgba(255, 255, 255, 0.12);
        border-bottom: 2px solid var(--vr-gold-dark) !important;
        white-space: nowrap;
        padding: 10px 14px 10px 10px;
        vertical-align: middle;
        min-width: 96px;
    }
    table.vr-table-main.acr-col-table thead th .vr-th-label,
    table.vr-table-main.acr-col-table thead th .acr-th-inner {
        color: #ffffff !important;
    }
    table.vr-table-main.acr-col-table thead th .acr-th-resize {
        background: linear-gradient(90deg, transparent, rgba(232, 197, 71, 0.45)) !important;
    }
    table.vr-table-main.acr-col-table thead th .acr-th-resize:hover {
        background: rgba(232, 197, 71, 0.75) !important;
    }
    .vr-table-main tbody td {
        padding: 0.45rem 0.65rem;
        vertical-align: middle;
        border-color: rgba(226, 232, 240, 0.9) !important;
    }
    .vr-table-main tbody tr:nth-child(even) { background: #fafbfd; }
    .vr-table-main tbody tr:hover { background: #fffbf0; }
    .vr-table-main tbody tr.vr-total-row {
        background: #fef9e7 !important;
        font-weight: 700;
        border-top: 2px solid var(--vr-gold-mid);
    }
    .vr-num { text-align: right; font-variant-numeric: tabular-nums; }
    .vr-footer-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 0.65rem 1rem;
        border-top: 1px solid rgba(226, 232, 240, 0.9);
        font-size: 12px;
        color: #64748b;
    }
    .vr-export-dd { position: relative; display: inline-block; }
    .vr-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .vr-export-dd > summary::-webkit-details-marker { display: none; }
    .vr-export-menu {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 4px;
        min-width: 140px;
        padding: 6px 0;
        background: #fff;
        border: 1px solid rgba(17, 41, 75, 0.12);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        z-index: 20;
    }
    .vr-export-menu a {
        display: block;
        padding: 8px 14px;
        color: #374151;
        text-decoration: none;
        font-size: 13px;
    }
    .vr-export-menu a:hover { background: #fffbf0; color: var(--vr-gold-dark); }
    .vr-hint {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 0.75rem;
    }
</style>
HTML;

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<link rel="stylesheet" href="assets/libs/select2/select2.css">
<div class="vr-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <h1 class="vr-page-title mb-0">Vendor Report</h1>
        <div class="vr-toolbar d-flex flex-wrap align-items-center gap-2">
            <span class="vr-range-chip" title="Active date range">
                <i class="feather icon-calendar"></i>
                <span><?php echo htmlspecialchars($default_range); ?></span>
            </span>
            <button type="button" class="btn btn-vr-outline position-relative" id="vrToggleFilter" aria-label="Open filters" data-toggle="modal" data-target="#vrFilterModal">
                <i class="feather icon-filter"></i>
                <?php if ($active_filter_count > 0): ?>
                <span class="vr-badge"><?php echo (int) $active_filter_count; ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="btn btn-vr-outline" id="vrRefresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
            <details class="vr-export-dd" data-fs-root="#vrMainTable" data-fs-file="vendor-report" data-fs-title="Vendor Report">
                <summary class="btn btn-vr-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="vr-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <nav class="vr-subnav" aria-label="Financial statement analysis">
        <a href="sale-analysis.php">Sale Reports</a>
        <a href="gold-silver-financial-analysis.php">Gold Silver Analysis</a>
        <a href="diamond-stone-financial-analysis.php">Diamond &amp; Stone Analysis</a>
        <a href="imitation-watches-financial-analysis.php">Imitation Or Watches</a>
        <a href="salesperson-performance.php">Salesperson Performance</a>
        <a href="vendor-report.php" class="vr-subnav-active">Vendor Report</a>
    </nav>

    <p class="vr-hint mb-2">
        Products assigned to a vendor, broken down by metal. Sale and purchase columns use the selected date range; opening and balance stock are current branch totals.
    </p>

    <div class="vr-table-outer">
        <div class="vr-table-scroll">
            <table id="vrMainTable" class="table vr-table-main acr-col-table">
                <thead>
                    <tr>
                        <?php foreach ($vr_fields as $key => $label): ?>
                        <th data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-acr-min="96">
                            <span class="vr-th-label"><?php echo htmlspecialchars($label); ?></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($row_count === 0): ?>
                    <tr>
                        <td colspan="<?php echo count($vr_fields); ?>" class="text-center text-muted py-4">
                            No vendor-assigned products found for the selected filters.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($vr_rows as $row): ?>
                    <tr>
                        <?php foreach (array_keys($vr_fields) as $key): ?>
                        <?php $val = isset($row[$key]) ? $row[$key] : ''; ?>
                        <td data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo in_array($key, $num_cols, true) ? 'vr-num' : ''; ?>"><?php echo htmlspecialchars($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($vr_totals !== []): ?>
                    <tr class="vr-total-row">
                        <?php foreach (array_keys($vr_fields) as $key): ?>
                        <?php $val = isset($vr_totals[$key]) ? $vr_totals[$key] : ''; ?>
                        <td data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo in_array($key, $num_cols, true) ? 'vr-num' : ''; ?>"><?php echo htmlspecialchars($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="vr-footer-bar">
            <span>Showing <strong><?php echo (int) $vr_show_from; ?></strong> to <strong><?php echo (int) $row_count; ?></strong> of <strong><?php echo (int) $row_count; ?></strong> entries</span>
        </div>
    </div>
</div>

<div class="modal fade vr-adv-modal" id="vrFilterModal" tabindex="-1" role="dialog" aria-labelledby="vrFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header vr-adv-modal-header position-relative">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="vrFilterModalLabel"><i class="feather icon-filter"></i> Advance Filter</h5>
                <p class="modal-sub mb-0">Filter by date range, vendor, product and metal.</p>
            </div>
            <form method="get" action="vendor-report.php" id="vrFilterForm">
                <div class="modal-body">
                    <div class="filter-grid">
                        <div class="vr-adv-section filter-field-full">Date range</div>
                        <div class="filter-field">
                            <label for="vrDateFrom">From Date</label>
                            <input type="date" class="form-control" id="vrDateFrom" name="vr_from" value="<?php echo htmlspecialchars($vr_range['from_ymd']); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="vrDateTo">To Date</label>
                            <input type="date" class="form-control" id="vrDateTo" name="vr_to" value="<?php echo htmlspecialchars($vr_range['to_ymd']); ?>">
                        </div>

                        <div class="vr-adv-section filter-field-full">Filters</div>
                        <div class="filter-field">
                            <label for="vrVendor">Vendor</label>
                            <select class="form-control vr-filter-select" id="vrVendor" name="vr_vendor">
                                <option value="0">All Vendors</option>
                                <?php foreach ($vr_lists['vendors'] as $v): ?>
                                <option value="<?php echo (int) $v['id']; ?>"<?php echo $filter_vendor === (int) $v['id'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="vrProduct">Product</label>
                            <select class="form-control vr-filter-select" id="vrProduct" name="vr_product">
                                <option value="0">All Products</option>
                                <?php foreach ($vr_lists['products'] as $p): ?>
                                <?php
                                $plabel = (string) $p['name'];
                                if (!empty($p['article'])) {
                                    $plabel .= ' (' . $p['article'] . ')';
                                }
                                ?>
                                <option value="<?php echo (int) $p['id']; ?>"<?php echo $filter_product === (int) $p['id'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($plabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="vrMetal">Metal</label>
                            <select class="form-control vr-filter-select" id="vrMetal" name="vr_metal">
                                <option value="0">All Metals</option>
                                <?php foreach ($vr_lists['metals'] as $m): ?>
                                <option value="<?php echo (int) $m['id']; ?>"<?php echo $filter_metal === (int) $m['id'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $m['display_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="vrSearch">Search</label>
                            <input type="text" class="form-control" id="vrSearch" name="vr_search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Product / vendor / metal…">
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-adv">
                    <button type="submit" class="btn btn-adv-apply">Apply Filter</button>
                    <a href="vendor-report.php" class="btn btn-adv-clear">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var refreshBtn = document.getElementById('vrRefresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            window.location.reload();
        });
    }
})();
</script>
<script src="assets/libs/select2/select2.js"></script>
<script src="assets/js/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script>
function vrInitPageTools() {
    try {
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            var $modal = jQuery('#vrFilterModal');
            var select2Common = {
                width: '100%',
                allowClear: true,
                minimumResultsForSearch: 0,
                dropdownParent: $modal,
                dropdownCssClass: 'vr-filter-select2-dropdown',
                containerCssClass: 'vr-filter-select2-dropdown',
                language: {
                    noResults: function () { return 'No match found'; },
                    searching: function () { return 'Searching…'; }
                }
            };
            jQuery('#vrVendor').select2(jQuery.extend({}, select2Common, { placeholder: 'All Vendors' }));
            jQuery('#vrProduct').select2(jQuery.extend({}, select2Common, { placeholder: 'All Products' }));
            jQuery('#vrMetal').select2(jQuery.extend({}, select2Common, { placeholder: 'All Metals' }));
        }
    } catch (e) {}

    if (typeof Sortable === 'undefined') {
        console.warn('Vendor Report: Sortable library not loaded — column drag disabled.');
    }
    if (window.AuragoldColReorder) {
        AuragoldColReorder.init('#vrMainTable', {
            storageKey: 'auragold_colorder_vendor_report_v2',
            widthsStorageKey: 'auragold_colorder_vendor_report_v2_widths',
            fixedFirst: true,
            minWidth: 96
        });
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', vrInitPageTools);
} else {
    vrInitPageTools();
}
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
