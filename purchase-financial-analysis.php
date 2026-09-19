<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_purchase_financial_analysis_data.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$pa_fields = [
    'invoice_no' => 'Invoice No.',
    'branch' => 'Branch',
    'date' => 'Date',
    'barcode' => 'Barcode',
    'product' => 'Product',
    'location' => 'Location',
    'gross_wt' => 'Gross Wt',
    'final_wt' => 'Final Wt',
    'pcs' => 'Pcs',
    'stone_wt' => 'Stone Wt',
    'metal_amt' => 'Metal Amt.',
    'making_amt' => 'Making Amt.',
    'stone_amt' => 'Stone Amt.',
    'purchase_amt' => 'Purchase Amt.',
    'ledger_name' => 'Ledger Name',
    'grand_total' => 'Grand Total',
    'discount' => 'Discount',
    'cash' => 'Cash',
    'bank' => 'Bank',
    'cheque' => 'Cheque',
    'upi' => 'Upi',
    'round_off' => 'Round OFF Value',
    'card' => 'Card',
    'metal_exch_amt' => 'Metal Exch. Amt',
    'metal_exch_wt' => 'Metal Exch. Wt',
    'old_jew_amt' => 'Old Jew. Amt',
    'old_jew_wt' => 'Old Jew. Wt',
    'balance_amt' => 'Balance Amt.',
    'comment' => 'Comment',
    'currency' => 'Currency',
    'category' => 'Category',
    'article' => 'Article',
    'national_id' => 'National Id',
    'mobile_no' => 'Mobile No.',
];

$pa_filters = auragold_purchase_financial_parse_filters();
$pa_dates = [
    'from' => (string) $pa_filters['date_from'],
    'to' => (string) $pa_filters['date_to'],
    'label' => auragold_purchase_financial_resolve_dates()['label'],
];
$eff_pa_br = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
$pa_filter_opts = isset($conn) && $conn instanceof mysqli
    ? auragold_purchase_financial_filter_options($conn)
    : ['branch_groups' => [], 'branches' => [], 'metals' => [], 'products' => [], 'categories' => [], 'suppliers' => [], 'articles' => [], 'currencies' => [], 'persons' => []];
$pa_adv_filter_count = auragold_purchase_financial_filter_count($pa_filters);
/** @var array<int, array<string, string>> */
$pa_rows = isset($conn) && $conn instanceof mysqli
    ? auragold_fetch_purchase_financial_analysis_rows($conn, $eff_pa_br, $pa_filters)
    : [];

$pa_numeric_keys = [
    'gross_wt', 'final_wt', 'pcs', 'stone_wt', 'metal_amt', 'making_amt', 'stone_amt', 'purchase_amt',
    'grand_total', 'discount', 'cash', 'bank', 'cheque', 'upi', 'round_off', 'card',
    'metal_exch_amt', 'metal_exch_wt', 'old_jew_amt', 'old_jew_wt', 'balance_amt',
];

$pa_totals = [];
foreach ($pa_numeric_keys as $k) {
    $pa_totals[$k] = 0.0;
}
foreach ($pa_rows as $row) {
    foreach ($pa_numeric_keys as $k) {
        if (!isset($row[$k]) || $row[$k] === '') {
            continue;
        }
        $pa_totals[$k] += (float) $row[$k];
    }
}

function pa_format_total(string $key, float $v): string {
    if (in_array($key, ['gross_wt', 'final_wt', 'stone_wt', 'metal_exch_wt', 'old_jew_wt'], true)) {
        return number_format($v, 3, '.', '');
    }
    if ($key === 'pcs') {
        return (string) (int) round($v);
    }
    if ($key === 'round_off') {
        return number_format($v, 2, '.', '');
    }
    return number_format($v, 2, '.', '');
}

$row_count = count($pa_rows);
$pa_col_keys = array_keys($pa_fields);
$pa_per_page = 10;
$pa_show_from = $row_count > 0 ? 1 : 0;
$pa_show_to = min($pa_per_page, $row_count);
$pa_total_pages = $row_count > 0 ? (int) ceil($row_count / $pa_per_page) : 0;
$default_range = $pa_dates['label'];
$pa_date_from_qs = htmlspecialchars($pa_dates['from'], ENT_QUOTES, 'UTF-8');
$pa_date_to_qs = htmlspecialchars($pa_dates['to'], ENT_QUOTES, 'UTF-8');

$DASHBOARD_PAGE_TITLE = 'Purchase Reports';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .pa-wrap {
        max-width: 100%;
        --pa-gold: #c9a227;
        --pa-gold-mid: #b8941f;
        --pa-gold-dark: #8b6914;
        --pa-navy: #11294b;
        --pa-navy-deep: #0c1f38;
    }
    .pa-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--pa-gold-mid) 45%, var(--pa-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .pa-page-title { color: var(--pa-gold-dark); -webkit-text-fill-color: var(--pa-gold-dark); }
    }
    .pa-toolbar .form-control.pa-date-range {
        max-width: 260px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .pa-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-pa-outline {
        border: 1px solid var(--pa-gold-mid) !important;
        color: var(--pa-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 0.85rem;
    }
    .btn-pa-outline:hover { background: #fffbf0 !important; border-color: var(--pa-gold) !important; }
    .btn-pa-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--pa-gold-mid) 55%, var(--pa-gold-dark) 100%) !important;
        border: 1px solid var(--pa-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
        text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    .btn-pa-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .pa-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        font-size: 10px;
        font-weight: 700;
        line-height: 18px;
        color: #fff;
        background: #dc2626;
        border-radius: 999px;
    }
    .pa-filter-wrap { position: relative; display: inline-block; }
    .pa-table-outer {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .pa-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .pa-table-main {
        margin-bottom: 0;
        font-size: 13px;
        min-width: max-content;
        table-layout: fixed;
        width: max(100%, 2200px);
    }
    .pa-table-main thead th {
        position: relative;
        background: linear-gradient(180deg, var(--pa-navy) 0%, var(--pa-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-right: 1px solid rgba(255, 255, 255, 0.18);
        border-bottom: 2px solid var(--pa-gold-dark) !important;
        white-space: nowrap;
        padding: 10px 14px 10px 10px;
        vertical-align: middle;
        min-width: 72px;
    }
    .pa-table-main thead th:last-child { border-right: none; }
    .pa-col-head { padding-right: 14px; cursor: grab; }
    .pa-col-head-inner {
        vertical-align: middle;
        user-select: none;
        touch-action: none;
    }
    .pa-col-head:active { cursor: grabbing; }
    .pa-col-head.pa-col-dragging { opacity: 0.55; }
    .pa-col-head.pa-col-drop-target {
        box-shadow: inset 0 0 0 2px rgba(201, 162, 98, 0.85);
    }
    .pa-col-resizer {
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 8px;
        cursor: col-resize;
        z-index: 8;
        user-select: none;
        touch-action: none;
        background: linear-gradient(90deg, transparent, rgba(232, 197, 71, 0.35));
    }
    .pa-col-resizer:hover { background: rgba(232, 197, 71, 0.65); }
    .pa-col-hidden { display: none !important; }
    .pa-table-scroll.pa-col-scroll-dragging { overflow-x: hidden !important; cursor: grabbing !important; }
    .pa-table-main tbody td {
        padding: 8px 12px;
        vertical-align: middle;
        border-color: #eef0f3;
        border-right: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .pa-table-main tbody td:last-child { border-right: none; }
    .pa-table-main tbody tr:nth-child(even) td { background: #fafbfc; }
    .pa-table-main tbody tr:hover td { background: #fff9ec !important; }
    .pa-table-main tfoot td {
        padding: 10px 12px;
        font-weight: 700;
        background: #e0f2fe !important;
        color: #0c4a6e;
        border-color: #bae6fd;
        border-right: 1px solid #bae6fd;
        white-space: nowrap;
    }
    .pa-table-main tfoot td:last-child { border-right: none; }
    .pa-num { text-align: right; font-variant-numeric: tabular-nums; }
    .pa-footer-bar {
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
    .pa-pager { display: flex; align-items: center; gap: 6px; }
    .pa-pager button {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        color: #64748b;
    }
    .pa-pager button:disabled { opacity: 0.45; cursor: not-allowed; }
    .pa-page-label {
        min-width: 52px;
        text-align: center;
        font-size: 12px;
        color: #64748b;
        padding: 0 4px;
        user-select: none;
    }
    .pa-export-dd { position: relative; display: inline-block; }
    .pa-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .pa-export-dd > summary::-webkit-details-marker { display: none; }
    .pa-export-menu {
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
    .pa-export-menu a {
        display: block;
        padding: 8px 14px;
        color: #374151;
        text-decoration: none;
        font-size: 13px;
    }
    .pa-export-menu a:hover { background: #fffbf0; color: var(--pa-gold-dark); }
    .pa-product-link { color: #2563eb; font-weight: 600; }
    .pa-col-dropdown {
        min-width: 260px;
        max-height: 360px;
        overflow-y: auto;
        padding: 8px 0;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(17, 41, 75, 0.12);
    }
    .pa-col-dropdown .dropdown-header {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #11294b;
        padding: 8px 16px 4px;
    }
    .pa-col-check-label {
        display: flex;
        align-items: center;
        padding: 7px 16px;
        font-size: 0.875rem;
        color: #334155;
        cursor: pointer;
        margin-bottom: 0;
    }
    .pa-col-check-label:hover { background: #f8fafc; }
    .pa-col-cb { margin-right: 10px; accent-color: #11294b; }
    .pa-adv-modal .modal-content { border: none; border-radius: 12px; overflow: visible; box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2); }
    .pa-adv-modal .modal-dialog { max-width: 960px; width: calc(100vw - 32px); }
    .pa-adv-modal .mp-ms-panel { z-index: 1060; }
    .pa-adv-modal .modal-header.pa-adv-modal-header {
        flex-direction: column; align-items: stretch; padding: 16px 44px 14px 20px;
        background: linear-gradient(135deg, #11294b 0%, #1a3d66 100%); border-bottom: 3px solid #c9a962;
    }
    .pa-adv-modal .modal-title { width: 100%; text-align: center; font-weight: 700; font-size: 1.05rem; color: #fff; margin: 0;
        display: flex; align-items: center; justify-content: center; gap: 10px; }
    .pa-adv-modal .modal-sub { text-align: center; font-size: 0.78rem; color: rgba(255,255,255,.78); margin: 8px 0 0; }
    .pa-adv-modal .close { position: absolute; right: 14px; top: 18px; opacity: .85; color: #fff; text-shadow: none; }
    .pa-adv-modal .close:hover { opacity: 1; color: #c9a962; }
    .pa-adv-modal .modal-body { padding: 20px 22px 12px; background: #fafbfc; overflow: visible; }
    .pa-adv-modal .modal-footer-adv {
        display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;
        padding: 16px 22px 22px; border: none; background: #fff; border-top: 1px solid #e2e8f0;
    }
    .pa-adv-modal .btn-adv-apply {
        background: #fff; color: #11294b; border: 2px solid #11294b; font-weight: 600;
        padding: 8px 26px; border-radius: 8px;
    }
    .pa-adv-modal .btn-adv-clear {
        background: #fff; color: #b45309; border: 2px solid #f5c2a7; font-weight: 600;
        padding: 8px 26px; border-radius: 8px;
    }
    .pa-adv-section { grid-column: 1 / -1; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: #64748b; margin: 8px 0 4px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
    .pa-adv-modal .filter-grid { margin-top: 0; }
    .pa-adv-modal .mp-ms-group + .mp-ms-group {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #e2e8f0;
    }
    .pa-adv-modal .mp-ms-opt-main {
        font-weight: 600;
        color: #11294b;
    }
    .pa-adv-modal .mp-ms-opt-sub {
        padding-left: 28px;
        font-size: 12px;
        color: #475569;
    }
    .pa-adv-modal .mp-ms-opt-sub span::before {
        content: "↳ ";
        color: #94a3b8;
        font-weight: 400;
    }
</style>
HTML;

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="pa-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <h1 class="pa-page-title mb-0">Purchase Reports</h1>
        <div class="pa-toolbar d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control pa-date-range border-start-0" id="paDateRange" value="<?php echo htmlspecialchars($default_range); ?>" readonly aria-label="Date range">
            </div>
            <div class="pa-filter-wrap" title="Advance filter">
                <button type="button" class="btn btn-pa-outline position-relative" id="paFilter" aria-label="Filter" data-toggle="modal" data-target="#paAdvFilterModal">
                    <i class="feather icon-filter"></i>
                    <?php if ($pa_adv_filter_count > 0): ?>
                    <span class="pa-badge"><?php echo (int) $pa_adv_filter_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <button type="button" class="btn btn-pa-outline" id="paRefresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
            <div class="dropdown">
                <button type="button" class="btn btn-pa-outline" id="paColSettingsBtn" title="Show / hide columns" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="feather icon-settings"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right pa-col-dropdown" onclick="event.stopPropagation();">
                    <div class="dropdown-header">Show columns</div>
                    <?php foreach ($pa_fields as $key => $label): ?>
                    <label class="pa-col-check-label">
                        <input type="checkbox" class="pa-col-cb" data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" checked>
                        <?php echo htmlspecialchars($label); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <details class="pa-export-dd" data-fs-root="#paMainTable" data-fs-file="purchase-financial-analysis" data-fs-title="Purchase Financial Analysis">
                <summary class="btn btn-pa-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="pa-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <div class="pa-table-outer">
        <div class="pa-table-scroll">
            <table id="paMainTable" class="table pa-table-main">
                <thead>
                    <tr id="paMainHeadRow">
                        <?php foreach ($pa_fields as $key => $label): ?>
                        <?php $pa_is_num = in_array($key, $pa_numeric_keys, true); ?>
                        <th class="pa-col-head<?php echo $pa_is_num ? ' pa-num' : ''; ?>" data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" title="Drag to reorder column">
                            <span class="pa-col-head-inner"><?php echo htmlspecialchars($label); ?></span>
                            <span class="pa-col-resizer" title="Drag to resize column"></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($row_count === 0): ?>
                    <tr class="pa-empty-row">
                        <td colspan="<?php echo (int) count($pa_fields); ?>" class="text-center text-muted py-4">No purchase invoice or quotation lines found for this branch and date range.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($pa_rows as $pa_row_i => $row): ?>
                    <tr class="pa-data-row"<?php echo $pa_row_i >= $pa_per_page ? ' style="display:none"' : ''; ?>>
                        <?php foreach (array_keys($pa_fields) as $key): ?>
                        <?php
                        $val = isset($row[$key]) ? $row[$key] : '';
                        $is_num = in_array($key, $pa_numeric_keys, true);
                        $is_product = ($key === 'product');
                        ?>
                        <td data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $is_num ? 'pa-num' : ''; ?><?php echo $is_product ? ' pa-product-link' : ''; ?>"><?php echo htmlspecialchars($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <?php foreach (array_keys($pa_fields) as $key): ?>
                        <?php
                        $dck = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                        if ($key === 'product') {
                            echo '<td data-col="' . $dck . '"><strong>Total</strong></td>';
                        } elseif (in_array($key, $pa_numeric_keys, true)) {
                            echo '<td data-col="' . $dck . '" class="pa-num">' . htmlspecialchars(pa_format_total($key, $pa_totals[$key])) . '</td>';
                        } elseif (in_array($key, ['invoice_no', 'branch', 'date', 'barcode', 'location'], true)) {
                            echo '<td data-col="' . $dck . '"></td>';
                        } else {
                            echo '<td data-col="' . $dck . '">—</td>';
                        }
                        ?>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="pa-footer-bar">
            <span id="paPaginationInfo">Showing <strong><?php echo (int) $pa_show_from; ?></strong> to <strong><?php echo (int) $pa_show_to; ?></strong> of <strong><?php echo (int) $row_count; ?></strong> entries</span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 small" for="paPageSize">Show</label>
                <select class="form-control form-control-sm" id="paPageSize" style="width:auto; min-width:72px;" aria-label="Page size">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="pa-pager">
                <button type="button" id="paPageFirst" aria-label="First"<?php echo ($row_count === 0 || $pa_total_pages <= 1) ? ' disabled' : ''; ?>>«</button>
                <button type="button" id="paPagePrev" aria-label="Previous"<?php echo ($row_count === 0 || $pa_total_pages <= 1) ? ' disabled' : ''; ?>>‹</button>
                <span class="pa-page-label" id="paPageLabel"><?php echo $row_count > 0 ? '1 / ' . (int) $pa_total_pages : '0 / 0'; ?></span>
                <button type="button" id="paPageNext" aria-label="Next"<?php echo ($row_count === 0 || $pa_total_pages <= 1) ? ' disabled' : ''; ?>>›</button>
                <button type="button" id="paPageLast" aria-label="Last"<?php echo ($row_count === 0 || $pa_total_pages <= 1) ? ' disabled' : ''; ?>>»</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade pa-adv-modal" id="paAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="paAdvFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header pa-adv-modal-header position-relative">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="paAdvFilterModalLabel"><i class="feather icon-filter"></i> Advance Filter</h5>
                <p class="modal-sub mb-0">Filter purchase invoices and quotations by date, branch, supplier, product and more.</p>
            </div>
            <form method="get" action="purchase-financial-analysis.php" id="paAdvFilterForm">
                <div class="modal-body">
                    <div class="filter-grid">
                        <div class="pa-adv-section filter-field-full">Date range</div>
                        <div class="filter-field">
                            <label for="pa_date_from">From Date</label>
                            <input type="date" class="form-control" id="pa_date_from" name="date_from" value="<?php echo htmlspecialchars($pa_dates['from'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="pa_date_to">To Date</label>
                            <input type="date" class="form-control" id="pa_date_to" name="date_to" value="<?php echo htmlspecialchars($pa_dates['to'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="pa-adv-section filter-field-full">Branch &amp; voucher</div>
                        <div class="filter-field">
                            <label class="filter-field-label">Branch</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Branches">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Branches</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach (($pa_filter_opts['branch_groups'] ?? []) as $pa_branch_group):
                                            $pa_main = $pa_branch_group['main'] ?? [];
                                            $pa_subs = $pa_branch_group['subs'] ?? [];
                                            $pa_main_id = (int) ($pa_main['id'] ?? 0);
                                            $pa_main_name = trim((string) ($pa_main['name'] ?? ''));
                                            if ($pa_main_id <= 0 || $pa_main_name === '') {
                                                continue;
                                            }
                                            ?>
                                        <div class="mp-ms-group">
                                            <label class="mp-ms-opt mp-ms-opt-main">
                                                <input type="checkbox" name="adv_branch[]" value="<?php echo $pa_main_id; ?>" <?php echo in_array($pa_main_id, (array) $pa_filters['branch_ids'], true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($pa_main_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                            <?php foreach ($pa_subs as $pa_sub):
                                                $pa_sub_id = (int) ($pa_sub['id'] ?? 0);
                                                $pa_sub_name = trim((string) ($pa_sub['name'] ?? ''));
                                                if ($pa_sub_id <= 0 || $pa_sub_name === '') {
                                                    continue;
                                                }
                                                ?>
                                            <label class="mp-ms-opt mp-ms-opt-sub">
                                                <input type="checkbox" name="adv_branch[]" value="<?php echo $pa_sub_id; ?>" <?php echo in_array($pa_sub_id, (array) $pa_filters['branch_ids'], true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($pa_sub_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_voucher_type">Voucher Type</label>
                            <select class="form-control" id="pa_adv_voucher_type" name="adv_voucher_type">
                                <option value="" <?php echo ($pa_filters['voucher_type'] ?? '') === '' ? 'selected' : ''; ?>>Purchase Invoice &amp; Quotation</option>
                                <option value="pi" <?php echo ($pa_filters['voucher_type'] ?? '') === 'pi' ? 'selected' : ''; ?>>Purchase Invoice only</option>
                                <option value="pq" <?php echo ($pa_filters['voucher_type'] ?? '') === 'pq' ? 'selected' : ''; ?>>Purchase Quotation only</option>
                            </select>
                        </div>
                        <div class="pa-adv-section filter-field-full">Supplier &amp; person</div>
                        <div class="filter-field">
                            <label class="filter-field-label">Ledger Name</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Suppliers">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Suppliers</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($pa_filter_opts['suppliers'] as $sup): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_supplier[]" value="<?php echo (int) ($sup['id'] ?? 0); ?>" <?php echo in_array((int) ($sup['id'] ?? 0), (array) $pa_filters['supplier_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($sup['name'] ?? '')); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_purchase_person">Purchase Person</label>
                            <select class="form-control" id="pa_adv_purchase_person" name="adv_purchase_person">
                                <option value="">Select Purchase Person</option>
                                <?php foreach ($pa_filter_opts['persons'] as $per):
                                    $pn = trim((string) ($per['purchase_person'] ?? ''));
                                    if ($pn === '') continue;
                                ?>
                                <option value="<?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($pn, (string) ($pa_filters['purchase_person'] ?? '')) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($pn); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_account_no">Account No.</label>
                            <input type="text" class="form-control" id="pa_adv_account_no" name="adv_account_no" value="<?php echo htmlspecialchars((string) ($pa_filters['account_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="pa-adv-section filter-field-full">Product attributes</div>
                        <div class="filter-field">
                            <label class="filter-field-label">Metal Type</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Metals">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Metals</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($pa_filter_opts['metals'] as $mt): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_metal[]" value="<?php echo (int) ($mt['id'] ?? 0); ?>" <?php echo in_array((int) ($mt['id'] ?? 0), (array) $pa_filters['metal_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($mt['name'] ?? '')); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Product</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Products">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Products</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($pa_filter_opts['products'] as $fp): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_product[]" value="<?php echo (int) ($fp['id'] ?? 0); ?>" <?php echo in_array((int) ($fp['id'] ?? 0), (array) $pa_filters['product_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($fp['name'] ?? '')); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Category</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Categories">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Categories</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($pa_filter_opts['categories'] as $cat): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_category[]" value="<?php echo (int) ($cat['id'] ?? 0); ?>" <?php echo in_array((int) ($cat['id'] ?? 0), (array) $pa_filters['category_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($cat['name'] ?? '')); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Article</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Articles">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Articles</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($pa_filter_opts['articles'] as $fa):
                                            $art = trim((string) ($fa['article'] ?? ''));
                                            if ($art === '') continue;
                                        ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_article[]" value="<?php echo htmlspecialchars($art, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($art, (array) $pa_filters['articles'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($art); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pa-adv-section filter-field-full">Amounts &amp; identifiers</div>
                        <div class="filter-field">
                            <label for="pa_adv_currency">Currency</label>
                            <select class="form-control" id="pa_adv_currency" name="adv_currency">
                                <option value="">Select Currency</option>
                                <?php foreach ($pa_filter_opts['currencies'] as $cur):
                                    $cv = trim((string) ($cur['currency'] ?? ''));
                                    if ($cv === '') continue;
                                ?>
                                <option value="<?php echo htmlspecialchars($cv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($cv, (string) ($pa_filters['currency'] ?? '')) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($cv); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_above_amount">Above Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="pa_adv_above_amount" name="adv_above_amount" value="<?php echo $pa_filters['above_amount'] !== null ? htmlspecialchars((string) $pa_filters['above_amount'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_barcode">Barcode No.</label>
                            <input type="text" class="form-control" id="pa_adv_barcode" name="adv_barcode" value="<?php echo htmlspecialchars((string) ($pa_filters['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_invoice_no">Invoice No.</label>
                            <input type="text" class="form-control" id="pa_adv_invoice_no" name="adv_invoice_no" value="<?php echo htmlspecialchars((string) ($pa_filters['invoice_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="pa_adv_gross_wt">Gross Wt (min)</label>
                            <input type="number" step="0.001" min="0" class="form-control" id="pa_adv_gross_wt" name="adv_gross_wt" value="<?php echo $pa_filters['gross_wt'] !== null ? htmlspecialchars((string) $pa_filters['gross_wt'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="filter-field filter-field-full">
                            <label for="pa_adv_comment">Comment</label>
                            <input type="text" class="form-control" id="pa_adv_comment" name="adv_comment" value="<?php echo htmlspecialchars((string) ($pa_filters['comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-adv">
                    <button type="submit" class="btn btn-adv-apply">Apply Filter</button>
                    <a href="purchase-financial-analysis.php" class="btn btn-adv-clear">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    document.getElementById('paRefresh').addEventListener('click', function () {
        window.location.reload();
    });

    function paMpMsUpdateLabel(wrap) {
        var btn = wrap.querySelector('.mp-ms-btn');
        var list = wrap.querySelector('.mp-ms-list');
        if (!btn || !list) return;
        var ph = wrap.getAttribute('data-mp-label') || 'Select';
        var opts = list.querySelectorAll('input[type="checkbox"]');
        var checked = list.querySelectorAll('input[type="checkbox"]:checked');
        if (checked.length === 0) btn.textContent = ph;
        else if (opts.length && checked.length === opts.length) btn.textContent = ph + ' (all)';
        else btn.textContent = ph + ' (' + checked.length + ')';
    }

    function paInitMpMultiSelect(root) {
        (root || document).querySelectorAll('#paAdvFilterModal [data-mp-ms]').forEach(function (wrap) {
            if (wrap._paMpMsInit) return;
            wrap._paMpMsInit = true;
            var btn = wrap.querySelector('.mp-ms-btn');
            var panel = wrap.querySelector('.mp-ms-panel');
            var search = wrap.querySelector('.mp-ms-search');
            var list = wrap.querySelector('.mp-ms-list');
            var allCb = wrap.querySelector('.mp-ms-check-all');
            function syncAll() {
                var opts = list.querySelectorAll('input[type="checkbox"]');
                var checked = list.querySelectorAll('input[type="checkbox"]:checked');
                if (allCb) {
                    allCb.indeterminate = checked.length > 0 && checked.length < opts.length;
                    allCb.checked = opts.length > 0 && checked.length === opts.length;
                }
                paMpMsUpdateLabel(wrap);
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasOpen = panel.classList.contains('is-open');
                document.querySelectorAll('#paAdvFilterModal .mp-ms-panel.is-open').forEach(function (p) { p.classList.remove('is-open'); });
                document.querySelectorAll('#paAdvFilterModal .mp-ms-btn').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
                if (!wasOpen) {
                    panel.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
            if (allCb) {
                allCb.addEventListener('change', function () {
                    var v = allCb.checked;
                    list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                        if (lab.style.display === 'none') return;
                        var cb = lab.querySelector('input[type="checkbox"]');
                        if (cb) cb.checked = v;
                    });
                    syncAll();
                });
            }
            list.addEventListener('change', function (e) {
                if (e.target && e.target.type === 'checkbox' && e.target !== allCb) syncAll();
            });
            if (search) {
                search.addEventListener('input', function () {
                    var q = (search.value || '').toLowerCase().trim();
                    list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                        lab.style.display = !q || (lab.textContent || '').toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }
            syncAll();
        });
    }

    if (!document._paMpMsDocClick) {
        document._paMpMsDocClick = true;
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#paAdvFilterModal .mp-ms')) return;
            document.querySelectorAll('#paAdvFilterModal .mp-ms-panel.is-open').forEach(function (p) { p.classList.remove('is-open'); });
            document.querySelectorAll('#paAdvFilterModal .mp-ms-btn').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        paInitMpMultiSelect(document);
        if (window.jQuery) {
            jQuery('#paAdvFilterModal').on('shown.bs.modal', function () {
                paInitMpMultiSelect(document.getElementById('paAdvFilterModal'));
            });
        }
    });
})();
</script>
<script>
function paInitTableTools() {
    var table = document.getElementById('paMainTable');
    var headRow = document.getElementById('paMainHeadRow');
    if (!table || !headRow) return;

    var PA_COL_STORAGE_KEY = 'auragold_pa_cols_v2';
    var PA_COL_DEFAULT_ORDER = <?php echo json_encode($pa_col_keys, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function getHeadOrder() {
        var out = [];
        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            out.push(th.getAttribute('data-col'));
        });
        return out;
    }

    function normalizeStoredOrder(arr) {
        if (!arr || !Array.isArray(arr) || arr.length !== PA_COL_DEFAULT_ORDER.length) return null;
        var set = {};
        for (var i = 0; i < arr.length; i++) set[arr[i]] = true;
        for (var j = 0; j < PA_COL_DEFAULT_ORDER.length; j++) {
            if (!set[PA_COL_DEFAULT_ORDER[j]]) return null;
        }
        return arr.slice();
    }

    function toggleColHidden(key, hide) {
        table.querySelectorAll('[data-col="' + key + '"]').forEach(function (el) {
            el.classList.toggle('pa-col-hidden', !!hide);
        });
        var cb = document.querySelector('.pa-col-cb[data-col="' + key + '"]');
        if (cb) cb.checked = !hide;
    }

    function countVisibleCols() {
        return getHeadOrder().filter(function (k) {
            var th = headRow.querySelector('th[data-col="' + k + '"]');
            return th && !th.classList.contains('pa-col-hidden');
        }).length;
    }

    function reorderHeaders(order) {
        var map = {};
        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            map[th.getAttribute('data-col')] = th;
        });
        var frag = document.createDocumentFragment();
        order.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        headRow.appendChild(frag);
    }

    function reorderCellsInRow(tr, order) {
        if (tr.querySelector('td[colspan]')) return;
        var map = {};
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            map[td.getAttribute('data-col')] = td;
        });
        if (!Object.keys(map).length) return;
        var frag = document.createDocumentFragment();
        order.forEach(function (k) {
            if (map[k]) frag.appendChild(map[k]);
        });
        tr.appendChild(frag);
    }

    function reorderColumns(order) {
        reorderHeaders(order);
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            reorderCellsInRow(tr, order);
        });
        table.querySelectorAll('tfoot tr').forEach(function (tr) {
            reorderCellsInRow(tr, order);
        });
    }

    function syncEmptyColspan() {
        var empty = table.querySelector('tbody td[colspan]');
        if (!empty) return;
        var n = headRow.querySelectorAll('th[data-col]:not(.pa-col-hidden)').length;
        empty.colSpan = Math.max(1, n);
    }

    function saveColState() {
        var hidden = {};
        getHeadOrder().forEach(function (k) {
            var th = headRow.querySelector('th[data-col="' + k + '"]');
            if (th && th.classList.contains('pa-col-hidden')) hidden[k] = true;
        });
        var widths = {};
        getHeadOrder().forEach(function (k) {
            var th = headRow.querySelector('th[data-col="' + k + '"]');
            if (th && th.style && th.style.width) widths[k] = th.style.width;
        });
        try {
            localStorage.setItem(PA_COL_STORAGE_KEY, JSON.stringify({
                order: getHeadOrder(),
                hidden: hidden,
                widths: widths
            }));
        } catch (e) {}
        try {
            var vis = {};
            getHeadOrder().forEach(function (k) {
                vis[k] = !hidden[k];
            });
            localStorage.setItem('auragold_pa_colvis_v1', JSON.stringify(vis));
        } catch (e2) {}
    }

    function loadColState() {
        var state = {};
        try {
            var raw = localStorage.getItem(PA_COL_STORAGE_KEY);
            if (raw) state = JSON.parse(raw) || {};
        } catch (e) {}

        var ord = normalizeStoredOrder(state.order);
        if (!ord) {
            try {
                var legacyOrder = localStorage.getItem('auragold_colorder_purchase_financial');
                if (legacyOrder) ord = normalizeStoredOrder(JSON.parse(legacyOrder));
            } catch (eO) {}
        }
        if (ord) reorderColumns(ord);

        if (state.hidden && typeof state.hidden === 'object') {
            PA_COL_DEFAULT_ORDER.forEach(function (k) {
                if (state.hidden[k]) toggleColHidden(k, true);
            });
        } else {
            try {
                var legacy = localStorage.getItem('auragold_pa_colvis_v1');
                if (legacy) {
                    var o = JSON.parse(legacy) || {};
                    PA_COL_DEFAULT_ORDER.forEach(function (k) {
                        toggleColHidden(k, o[k] === false);
                    });
                }
            } catch (eL) {}
        }

        if (countVisibleCols() === 0) {
            PA_COL_DEFAULT_ORDER.forEach(function (k) { toggleColHidden(k, false); });
        }

        var widths = state.widths;
        if (!widths || typeof widths !== 'object') {
            try {
                var legacyWidths = localStorage.getItem('auragold_colorder_purchase_financial_widths');
                if (legacyWidths) widths = JSON.parse(legacyWidths) || {};
            } catch (eW) {}
        }
        if (widths && typeof widths === 'object') {
            Object.keys(widths).forEach(function (k) {
                var th = headRow.querySelector('th[data-col="' + k + '"]');
                if (th && widths[k]) th.style.width = widths[k];
            });
        }

        syncEmptyColspan();
    }

    function thFromPoint(clientX, clientY) {
        var el = document.elementFromPoint(clientX, clientY);
        if (!el || !el.closest) return null;
        return el.closest('#paMainHeadRow th[data-col]');
    }

    function bindColumnDragResize() {
        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var handle = th.querySelector('.pa-col-head-inner');
            if (handle && !handle._paBound) {
                handle._paBound = true;
                handle.addEventListener('pointerdown', function (e) {
                    if (e.button !== 0) return;
                    var dragFromKey = th.getAttribute('data-col');
                    if (!dragFromKey || th.classList.contains('pa-col-hidden')) return;
                    e.preventDefault();
                    e.stopPropagation();
                    th.classList.add('pa-col-dragging');
                    var scrollEl = table.closest('.pa-table-scroll');
                    if (scrollEl) scrollEl.classList.add('pa-col-scroll-dragging');
                    try { handle.setPointerCapture(e.pointerId); } catch (errCap) {}

                    function clearDropHighlights() {
                        headRow.querySelectorAll('.pa-col-drop-target').forEach(function (x) {
                            x.classList.remove('pa-col-drop-target');
                        });
                    }

                    function onMove(ev) {
                        clearDropHighlights();
                        var over = thFromPoint(ev.clientX, ev.clientY);
                        if (over && over.getAttribute('data-col') !== dragFromKey && !over.classList.contains('pa-col-hidden')) {
                            over.classList.add('pa-col-drop-target');
                        }
                    }

                    function onEnd(ev) {
                        th.classList.remove('pa-col-dragging');
                        if (scrollEl) scrollEl.classList.remove('pa-col-scroll-dragging');
                        clearDropHighlights();
                        try { handle.releasePointerCapture(ev.pointerId); } catch (errRel) {}
                        handle.removeEventListener('pointermove', onMove);
                        handle.removeEventListener('pointerup', onEnd);
                        handle.removeEventListener('pointercancel', onEnd);

                        var over = thFromPoint(ev.clientX, ev.clientY);
                        var toKey = over && over.getAttribute('data-col');
                        if (!toKey || toKey === dragFromKey || over.classList.contains('pa-col-hidden')) return;
                        var order = getHeadOrder().slice();
                        var i = order.indexOf(dragFromKey);
                        var j = order.indexOf(toKey);
                        if (i < 0 || j < 0) return;
                        order.splice(i, 1);
                        order.splice(j, 0, dragFromKey);
                        reorderColumns(order);
                        saveColState();
                        syncEmptyColspan();
                    }

                    handle.addEventListener('pointermove', onMove);
                    handle.addEventListener('pointerup', onEnd);
                    handle.addEventListener('pointercancel', onEnd);
                });
            }

            var resizer = th.querySelector('.pa-col-resizer');
            if (resizer && !resizer._paBound) {
                resizer._paBound = true;
                resizer.addEventListener('mousedown', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var startX = e.clientX;
                    var startW = th.getBoundingClientRect().width;
                    function onMoveR(ev) {
                        var w = Math.max(48, startW + (ev.clientX - startX));
                        th.style.width = w + 'px';
                    }
                    function onUpR() {
                        document.removeEventListener('mousemove', onMoveR);
                        document.removeEventListener('mouseup', onUpR);
                        saveColState();
                    }
                    document.addEventListener('mousemove', onMoveR);
                    document.addEventListener('mouseup', onUpR);
                });
            }
        });
    }

    document.querySelectorAll('.pa-col-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var key = this.getAttribute('data-col');
            if (!key) return;
            if (!this.checked) {
                if (countVisibleCols() === 1) {
                    var th = headRow.querySelector('th[data-col="' + key + '"]');
                    if (th && !th.classList.contains('pa-col-hidden')) {
                        this.checked = true;
                        return;
                    }
                }
                toggleColHidden(key, true);
            } else {
                toggleColHidden(key, false);
            }
            syncEmptyColspan();
            saveColState();
        });
    });

    document.querySelectorAll('.pa-col-check-label').forEach(function (lbl) {
        lbl.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    loadColState();
    bindColumnDragResize();
    paInitPagination();
}

function paInitPagination() {
    var table = document.getElementById('paMainTable');
    if (!table) return;

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.pa-data-row'));
    var infoEl = document.getElementById('paPaginationInfo');
    var pageSizeEl = document.getElementById('paPageSize');
    var pageLabel = document.getElementById('paPageLabel');
    var btnFirst = document.getElementById('paPageFirst');
    var btnPrev = document.getElementById('paPagePrev');
    var btnNext = document.getElementById('paPageNext');
    var btnLast = document.getElementById('paPageLast');

    var page = 1;
    var pageSize = pageSizeEl ? (parseInt(pageSizeEl.value, 10) || 10) : 10;

    function totalPages() {
        if (!rows.length) return 0;
        return Math.max(1, Math.ceil(rows.length / pageSize));
    }

    function applyPage() {
        var total = rows.length;
        var pages = totalPages();

        if (pages === 0) {
            page = 1;
        } else {
            if (page > pages) page = pages;
            if (page < 1) page = 1;
        }

        var start = total ? (page - 1) * pageSize : 0;
        var end = start + pageSize;

        rows.forEach(function (tr, i) {
            tr.style.display = (i >= start && i < end) ? '' : 'none';
        });

        var from = total ? start + 1 : 0;
        var to = total ? Math.min(end, total) : 0;

        if (infoEl) {
            infoEl.innerHTML = 'Showing <strong>' + from + '</strong> to <strong>' + to + '</strong> of <strong>' + total + '</strong> entries';
        }
        if (pageLabel) {
            pageLabel.textContent = total ? (page + ' / ' + pages) : '0 / 0';
        }

        var atFirst = pages <= 1 || page <= 1;
        var atLast = pages <= 1 || page >= pages;
        if (btnFirst) btnFirst.disabled = atFirst;
        if (btnPrev) btnPrev.disabled = atFirst;
        if (btnNext) btnNext.disabled = atLast;
        if (btnLast) btnLast.disabled = atLast;
    }

    if (pageSizeEl) {
        pageSizeEl.addEventListener('change', function () {
            pageSize = parseInt(pageSizeEl.value, 10) || 10;
            page = 1;
            applyPage();
        });
    }
    if (btnFirst) {
        btnFirst.addEventListener('click', function () {
            page = 1;
            applyPage();
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            page -= 1;
            applyPage();
        });
    }
    if (btnNext) {
        btnNext.addEventListener('click', function () {
            page += 1;
            applyPage();
        });
    }
    if (btnLast) {
        btnLast.addEventListener('click', function () {
            page = totalPages();
            applyPage();
        });
    }

    applyPage();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', paInitTableTools);
} else {
    paInitTableTools();
}
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
