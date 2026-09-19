<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/auragold_sale_analysis_data.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

/** Column order and labels — populated from sale invoice lines (see auragold_sale_analysis_fetch_rows). */
$sa_fields = [
    'ledger_name' => 'Ledger Name',
    'party' => 'Party',
    'sales_person' => 'Sales Person',
    'invoice_no' => 'Invoice No.',
    'branch' => 'Branch',
    'date' => 'Date',
    'barcode' => 'Barcode',
    'pcs' => 'Pcs',
    'category' => 'Category',
    'product' => 'Product',
    'gross_wt' => 'Gross Wt',
    'final_wt' => 'Final Wt',
    'metal_amt' => 'Metal Amt.',
    'making_amt' => 'Making Amt.',
    'amount' => 'Amount',
    'sales_amt' => 'Sales Amt.',
    'tax_amount' => 'Tax Amount',
    'making_cost' => 'Making Cost',
    'cost_price' => 'Cost Price',
    'profit' => 'Profit',
    'grand_total' => 'Grand Total',
    'discount' => 'Discount',
    'cash' => 'Cash',
    'bank' => 'Bank',
    'transaction_name' => 'Transaction Name',
    'cheque' => 'Cheque',
    'upi' => 'Upi',
    'card' => 'Card',
    'metal_exch_amt' => 'Metal Exch. Amt',
    'metal_exch_wt' => 'Metal Exch. Wt',
    'old_jew_amt' => 'Old Jew. Amt',
    'old_jew_wt' => 'Old Jew. Wt',
    'huid_no' => 'HUID No.',
    'balance_amt' => 'Balance Amt.',
    'comment' => 'Comment',
    'currency' => 'Currency',
    'layaways_status' => 'Layaways Status',
    'advance_payment' => 'Advance Payment',
    'round_off' => 'Round OFF Value',
    'from_prev_balance' => 'From Previous Balance Amount',
    'return_amount' => 'Return Amount',
    'additional_amount' => 'Additional Amount',
    'customer_advance' => 'Customer Advance Amount',
    'fund_transfer' => 'Fund Transfer Amount',
    'sale_order_advance' => 'Sale Order Advance Payment',
    'article' => 'Article',
    'national_id' => 'National Id',
    'mobile_no' => 'Mobile No.',
];

$sa_col_keys = array_keys($sa_fields);
$sa_num_col_keys = [
    'gross_wt', 'final_wt', 'metal_amt', 'making_amt', 'amount', 'sales_amt', 'tax_amount',
    'making_cost', 'cost_price', 'profit', 'grand_total', 'discount', 'cash', 'bank',
    'cheque', 'upi', 'card', 'metal_exch_amt', 'metal_exch_wt', 'old_jew_amt', 'old_jew_wt',
    'balance_amt', 'advance_payment', 'round_off', 'from_prev_balance', 'return_amount',
    'additional_amount', 'customer_advance', 'fund_transfer', 'sale_order_advance', 'pcs',
];

if (!function_exists('auragold_um_branch_picker_groups')) {
    require_once __DIR__ . '/includes/user_management_schema.php';
}

$sa_filters = auragold_sale_analysis_parse_filters();
$sa_range = [
    'from_ymd' => (string) $sa_filters['date_from'],
    'to_ymd' => (string) $sa_filters['date_to'],
    'from_dmY' => date('d-m-Y', strtotime((string) $sa_filters['date_from'])),
    'to_dmY' => date('d-m-Y', strtotime((string) $sa_filters['date_to'])),
];
$sa_range['label'] = $sa_range['from_dmY'] . ' - ' . $sa_range['to_dmY'];
$default_range = $sa_range['label'];

$sa_filter_opts = isset($conn) && $conn instanceof mysqli
    ? auragold_sale_analysis_filter_options($conn)
    : ['branch_groups' => [], 'metals' => [], 'products' => [], 'categories' => [], 'karats' => [], 'articles' => [], 'currencies' => [], 'persons' => [], 'ledgers' => []];
$sa_adv_filter_count = auragold_sale_analysis_filter_count($sa_filters);

/** @var array<int, array<string, string>> */
$sa_rows = [];
global $conn;
if (isset($conn) && $conn instanceof mysqli) {
    $sa_rows = auragold_sale_analysis_fetch_rows($conn, $sa_range['from_ymd'], $sa_range['to_ymd']);
    $sa_rows = auragold_sale_analysis_apply_filters($sa_rows, $sa_filters);
}

$sa_per_page = 10;
$row_count = count($sa_rows);
$sa_show_from = $row_count > 0 ? 1 : 0;
$sa_show_to = min($sa_per_page, $row_count);
$sa_total_pages = $row_count > 0 ? (int) ceil($row_count / $sa_per_page) : 0;

$DASHBOARD_PAGE_TITLE = 'Sale Reports';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .sa-wrap {
        max-width: 100%;
        --sa-gold: #c9a227;
        --sa-gold-mid: #b8941f;
        --sa-gold-dark: #8b6914;
        --sa-navy: #11294b;
        --sa-navy-deep: #0c1f38;
    }
    .sa-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--sa-gold-mid) 45%, var(--sa-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .sa-page-title { color: var(--sa-gold-dark); -webkit-text-fill-color: var(--sa-gold-dark); }
    }
    .sa-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 1rem;
    }
    .sa-subnav a {
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
    .sa-subnav a:hover { background: #fffbf0; border-color: var(--sa-gold-mid); color: var(--sa-gold-dark); }
    .sa-subnav a.sa-subnav-active {
        background: linear-gradient(180deg, #5b4b9a 0%, #4338ca 100%);
        border-color: #3730a3;
        color: #fff !important;
    }
    .sa-toolbar .form-control.sa-date-range {
        max-width: 260px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .sa-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-sa-outline {
        border: 1px solid var(--sa-gold-mid) !important;
        color: var(--sa-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 0.85rem;
    }
    .btn-sa-outline:hover { background: #fffbf0 !important; border-color: var(--sa-gold) !important; }
    .btn-sa-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--sa-gold-mid) 55%, var(--sa-gold-dark) 100%) !important;
        border: 1px solid var(--sa-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
        text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    .btn-sa-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .sa-badge {
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
    .sa-filter-wrap { position: relative; display: inline-block; }
    .sa-table-outer {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: visible;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .sa-table-scroll {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }
    .sa-table-main {
        margin-bottom: 0;
        font-size: 13px;
        min-width: max-content;
        table-layout: fixed;
        width: max(100%, 2400px);
    }
    .sa-table-main thead th {
        position: relative;
        top: auto;
        z-index: 2;
        background: linear-gradient(180deg, var(--sa-navy) 0%, var(--sa-navy-deep) 100%) !important;
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-right: 1px solid rgba(255, 255, 255, 0.18);
        border-bottom: 2px solid var(--sa-gold-dark) !important;
        white-space: nowrap;
        padding: 10px 14px 10px 10px;
        vertical-align: middle;
        min-width: 72px;
    }
    .sa-table-main thead th:last-child { border-right: none; }
    .sa-col-head { padding-right: 14px; cursor: grab; }
    .sa-col-head-inner {
        vertical-align: middle;
        user-select: none;
        touch-action: none;
    }
    .sa-col-head:active { cursor: grabbing; }
    .sa-col-head.sa-col-dragging { opacity: 0.55; }
    .sa-col-head.sa-col-drop-target {
        box-shadow: inset 0 0 0 2px rgba(201, 162, 98, 0.85);
    }
    .sa-col-resizer {
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
    .sa-col-resizer:hover { background: rgba(232, 197, 71, 0.65); }
    .sa-col-hidden { display: none !important; }
    .sa-table-scroll.sa-col-scroll-dragging { overflow-x: hidden !important; cursor: grabbing !important; }
    .sa-table-main tbody td {
        padding: 8px 12px;
        vertical-align: middle;
        border-color: #eef0f3;
        border-right: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .sa-table-main tbody td:last-child { border-right: none; }
    .sa-table-main tbody tr:nth-child(even) td { background: #fafbfc; }
    .sa-table-main tbody tr:hover td { background: #fff9ec !important; }
    .sa-num { text-align: right; font-variant-numeric: tabular-nums; }
    .sa-footer-bar {
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
    .sa-pager { display: flex; align-items: center; gap: 6px; }
    .sa-pager button {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        color: #64748b;
    }
    .sa-pager button:disabled { opacity: 0.45; cursor: not-allowed; }
    .sa-page-label {
        min-width: 52px;
        text-align: center;
        font-size: 12px;
        color: #64748b;
        padding: 0 4px;
        user-select: none;
    }
    .sa-export-dd { position: relative; display: inline-block; }
    .sa-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .sa-export-dd > summary::-webkit-details-marker { display: none; }
    .sa-export-menu {
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
    .sa-export-menu a {
        display: block;
        padding: 8px 14px;
        color: #374151;
        text-decoration: none;
        font-size: 13px;
    }
    .sa-export-menu a:hover { background: #fffbf0; color: var(--sa-gold-dark); }
    .sa-col-dropdown {
        min-width: 260px;
        max-height: 360px;
        overflow-y: auto;
        padding: 8px 0;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(17, 41, 75, 0.12);
    }
    .sa-col-dropdown .dropdown-header {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #11294b;
        padding: 8px 16px 4px;
    }
    .sa-col-check-label {
        display: flex;
        align-items: center;
        padding: 7px 16px;
        font-size: 0.875rem;
        color: #334155;
        cursor: pointer;
        margin-bottom: 0;
    }
    .sa-col-check-label:hover { background: #f8fafc; }
    .sa-col-cb { margin-right: 10px; accent-color: #11294b; }
    .sa-adv-modal .modal-content { border: none; border-radius: 12px; overflow: visible; box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2); }
    .sa-adv-modal .modal-dialog { max-width: 960px; width: calc(100vw - 32px); }
    .sa-adv-modal .mp-ms-panel { z-index: 1060; }
    .sa-adv-modal .modal-header.sa-adv-modal-header {
        flex-direction: column; align-items: stretch; padding: 16px 44px 14px 20px;
        background: linear-gradient(135deg, #11294b 0%, #1a3d66 100%); border-bottom: 3px solid #c9a962;
    }
    .sa-adv-modal .modal-title { width: 100%; text-align: center; font-weight: 700; font-size: 1.05rem; color: #fff; margin: 0;
        display: flex; align-items: center; justify-content: center; gap: 10px; }
    .sa-adv-modal .modal-sub { text-align: center; font-size: 0.78rem; color: rgba(255,255,255,.78); margin: 8px 0 0; }
    .sa-adv-modal .close { position: absolute; right: 14px; top: 18px; opacity: .85; color: #fff; text-shadow: none; }
    .sa-adv-modal .close:hover { opacity: 1; color: #c9a962; }
    .sa-adv-modal .modal-body { padding: 20px 22px 12px; background: #fafbfc; overflow: visible; }
    .sa-adv-modal .modal-footer-adv {
        display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;
        padding: 16px 22px 22px; border: none; background: #fff; border-top: 1px solid #e2e8f0;
    }
    .sa-adv-modal .btn-adv-apply {
        background: #fff; color: #11294b; border: 2px solid #11294b; font-weight: 600;
        padding: 8px 26px; border-radius: 8px;
    }
    .sa-adv-modal .btn-adv-clear {
        background: #fff; color: #b45309; border: 2px solid #f5c2a7; font-weight: 600;
        padding: 8px 26px; border-radius: 8px;
    }
    .sa-adv-section { grid-column: 1 / -1; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: #64748b; margin: 8px 0 4px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
    .sa-adv-modal .filter-grid { margin-top: 0; }
    .sa-adv-modal .mp-ms-group + .mp-ms-group {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #e2e8f0;
    }
    .sa-adv-modal .mp-ms-opt-main { font-weight: 600; color: #11294b; }
    .sa-adv-modal .mp-ms-opt-sub { padding-left: 28px; font-size: 12px; color: #475569; }
    .sa-adv-modal .mp-ms-opt-sub span::before { content: "↳ "; color: #94a3b8; font-weight: 400; }
</style>
HTML;

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="sa-wrap">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <h1 class="sa-page-title mb-0">Sale Reports</h1>
        <div class="sa-toolbar d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control sa-date-range border-start-0" id="saDateRange" value="<?php echo htmlspecialchars($default_range); ?>" readonly aria-label="Date range">
            </div>
            <div class="sa-filter-wrap" title="Advance filter">
                <button type="button" class="btn btn-sa-outline position-relative" id="saFilter" aria-label="Filter" data-toggle="modal" data-target="#saAdvFilterModal">
                    <i class="feather icon-filter"></i>
                    <?php if ($sa_adv_filter_count > 0): ?>
                    <span class="sa-badge"><?php echo (int) $sa_adv_filter_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <button type="button" class="btn btn-sa-outline" id="saRefresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
            <div class="dropdown">
                <button type="button" class="btn btn-sa-outline" id="saColSettingsBtn" title="Show / hide columns" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="feather icon-settings"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right sa-col-dropdown" onclick="event.stopPropagation();">
                    <div class="dropdown-header">Show columns</div>
                    <?php foreach ($sa_fields as $key => $label): ?>
                    <label class="sa-col-check-label">
                        <input type="checkbox" class="sa-col-cb" data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" checked>
                        <?php echo htmlspecialchars($label); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <details class="sa-export-dd" data-fs-root="#saMainTable" data-fs-file="sale-analysis" data-fs-title="Sale Reports">
                <summary class="btn btn-sa-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="sa-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <nav class="sa-subnav" aria-label="Financial statement analysis">
        <a href="sale-analysis.php" class="sa-subnav-active">Sale Reports</a>
        <a href="gold-silver-financial-analysis.php">Gold Silver Analysis</a>
        <a href="diamond-stone-financial-analysis.php">Diamond &amp; Stone Analysis</a>
        <a href="imitation-watches-financial-analysis.php">Imitation Or Watches</a>
        <a href="salesperson-performance.php">Salesperson Performance</a>
        <a href="vendor-report.php">Vendor Report</a>
    </nav>

    <div class="sa-table-outer">
        <div class="sa-table-scroll">
            <table id="saMainTable" class="table sa-table-main">
                <thead>
                    <tr id="saMainHeadRow">
                        <?php foreach ($sa_fields as $key => $label): ?>
                        <?php $sa_is_num = in_array($key, $sa_num_col_keys, true); ?>
                        <th class="sa-col-head<?php echo $sa_is_num ? ' sa-num' : ''; ?>" data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" title="Drag to reorder column">
                            <span class="sa-col-head-inner"><?php echo htmlspecialchars($label); ?></span>
                            <span class="sa-col-resizer" title="Drag to resize column"></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($row_count === 0): ?>
                    <tr class="sa-empty-row">
                        <td colspan="<?php echo count($sa_fields); ?>" class="text-center text-muted py-4">No sale invoice or sale quotation lines found for this date range.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($sa_rows as $sa_row_i => $row): ?>
                    <tr class="sa-data-row"<?php echo $sa_row_i >= $sa_per_page ? ' style="display:none"' : ''; ?>>
                        <?php foreach (array_keys($sa_fields) as $key): ?>
                        <?php
                        $val = isset($row[$key]) ? $row[$key] : '';
                        $is_num = in_array($key, $sa_num_col_keys, true);
                        ?>
                        <td data-col="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $is_num ? 'sa-num' : ''; ?>"><?php echo htmlspecialchars($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sa-footer-bar">
            <span id="saPaginationInfo">Showing <strong><?php echo (int) $sa_show_from; ?></strong> to <strong><?php echo (int) $sa_show_to; ?></strong> of <strong><?php echo (int) $row_count; ?></strong> entries</span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 small" for="saPageSize">Show</label>
                <select class="form-control form-control-sm" id="saPageSize" style="width:auto; min-width:72px;" aria-label="Page size">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="sa-pager">
                <button type="button" id="saPageFirst" aria-label="First"<?php echo ($row_count === 0 || $sa_total_pages <= 1) ? ' disabled' : ''; ?>>«</button>
                <button type="button" id="saPagePrev" aria-label="Previous"<?php echo ($row_count === 0 || $sa_total_pages <= 1) ? ' disabled' : ''; ?>>‹</button>
                <span class="sa-page-label" id="saPageLabel"><?php echo $row_count > 0 ? '1 / ' . (int) $sa_total_pages : '0 / 0'; ?></span>
                <button type="button" id="saPageNext" aria-label="Next"<?php echo ($row_count === 0 || $sa_total_pages <= 1) ? ' disabled' : ''; ?>>›</button>
                <button type="button" id="saPageLast" aria-label="Last"<?php echo ($row_count === 0 || $sa_total_pages <= 1) ? ' disabled' : ''; ?>>»</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sa-adv-modal" id="saAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="saAdvFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header sa-adv-modal-header position-relative">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="saAdvFilterModalLabel"><i class="feather icon-filter"></i> Advance Filter</h5>
                <p class="modal-sub mb-0">Filter sale invoices and quotations by date, branch, party, product and more.</p>
            </div>
            <form method="get" action="sale-analysis.php" id="saAdvFilterForm">
                <div class="modal-body">
                    <div class="filter-grid">
                        <div class="sa-adv-section filter-field-full">Date range</div>
                        <div class="filter-field">
                            <label for="sa_date_from">From Date</label>
                            <input type="date" class="form-control" id="sa_date_from" name="date_from" value="<?php echo htmlspecialchars((string) $sa_filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sa_date_to">To Date</label>
                            <input type="date" class="form-control" id="sa_date_to" name="date_to" value="<?php echo htmlspecialchars((string) $sa_filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="sa-adv-section filter-field-full">Branch &amp; voucher</div>
                        <div class="filter-field">
                            <label class="filter-field-label">Branch</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Branches">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Branches</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach (($sa_filter_opts['branch_groups'] ?? []) as $sa_branch_group):
                                            $sa_main = $sa_branch_group['main'] ?? [];
                                            $sa_subs = $sa_branch_group['subs'] ?? [];
                                            $sa_main_id = (int) ($sa_main['id'] ?? 0);
                                            $sa_main_name = trim((string) ($sa_main['name'] ?? ''));
                                            if ($sa_main_id <= 0 || $sa_main_name === '') {
                                                continue;
                                            }
                                            ?>
                                        <div class="mp-ms-group">
                                            <label class="mp-ms-opt mp-ms-opt-main">
                                                <input type="checkbox" name="adv_branch[]" value="<?php echo $sa_main_id; ?>" <?php echo in_array($sa_main_id, (array) $sa_filters['branch_ids'], true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($sa_main_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                            <?php foreach ($sa_subs as $sa_sub):
                                                $sa_sub_id = (int) ($sa_sub['id'] ?? 0);
                                                $sa_sub_name = trim((string) ($sa_sub['name'] ?? ''));
                                                if ($sa_sub_id <= 0 || $sa_sub_name === '') {
                                                    continue;
                                                }
                                                ?>
                                            <label class="mp-ms-opt mp-ms-opt-sub">
                                                <input type="checkbox" name="adv_branch[]" value="<?php echo $sa_sub_id; ?>" <?php echo in_array($sa_sub_id, (array) $sa_filters['branch_ids'], true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($sa_sub_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_voucher_type">Voucher Type</label>
                            <select class="form-control" id="sa_adv_voucher_type" name="adv_voucher_type">
                                <option value="" <?php echo ($sa_filters['voucher_type'] ?? '') === '' ? 'selected' : ''; ?>>All (Invoice &amp; Quotation)</option>
                                <option value="si" <?php echo ($sa_filters['voucher_type'] ?? '') === 'si' ? 'selected' : ''; ?>>Sale Invoice</option>
                                <option value="pos" <?php echo ($sa_filters['voucher_type'] ?? '') === 'pos' ? 'selected' : ''; ?>>POS Sale Invoice</option>
                                <option value="sq" <?php echo ($sa_filters['voucher_type'] ?? '') === 'sq' ? 'selected' : ''; ?>>Sale Quotation</option>
                            </select>
                        </div>

                        <div class="sa-adv-section filter-field-full">Party &amp; person</div>
                        <div class="filter-field">
                            <label for="sa_adv_ledger_name">Ledger Name</label>
                            <select class="form-control" id="sa_adv_ledger_name" name="adv_ledger_name">
                                <option value="">Select Ledger</option>
                                <?php foreach (($sa_filter_opts['ledgers'] ?? []) as $led):
                                    $ln = trim((string) ($led['name'] ?? ''));
                                    if ($ln === '') continue;
                                ?>
                                <option value="<?php echo htmlspecialchars($ln, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($ln, (string) ($sa_filters['ledger_name'] ?? '')) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($ln); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_account_no">Account No</label>
                            <input type="text" class="form-control" id="sa_adv_account_no" name="adv_account_no" value="<?php echo htmlspecialchars((string) ($sa_filters['account_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="National ID / Mobile / Invoice">
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_sales_person">Sales Person</label>
                            <select class="form-control" id="sa_adv_sales_person" name="adv_sales_person">
                                <option value="">Select Sales Person</option>
                                <?php foreach (($sa_filter_opts['persons'] ?? []) as $per):
                                    $pn = trim((string) ($per['sales_person'] ?? ''));
                                    if ($pn === '') continue;
                                ?>
                                <option value="<?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($pn, (string) ($sa_filters['sales_person'] ?? '')) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($pn); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sa-adv-section filter-field-full">Product attributes</div>
                        <div class="filter-field">
                            <label class="filter-field-label">Metal Type</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Metals">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Metals</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach (($sa_filter_opts['metals'] ?? []) as $mt): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_metal[]" value="<?php echo (int) ($mt['id'] ?? 0); ?>" <?php echo in_array((int) ($mt['id'] ?? 0), (array) $sa_filters['metal_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($mt['name'] ?? '')); ?></span></label>
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
                                        <?php foreach (($sa_filter_opts['products'] ?? []) as $fp): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_product[]" value="<?php echo (int) ($fp['id'] ?? 0); ?>" <?php echo in_array((int) ($fp['id'] ?? 0), (array) $sa_filters['product_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($fp['name'] ?? '')); ?></span></label>
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
                                        <?php foreach (($sa_filter_opts['categories'] ?? []) as $cat): ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_category[]" value="<?php echo (int) ($cat['id'] ?? 0); ?>" <?php echo in_array((int) ($cat['id'] ?? 0), (array) $sa_filters['category_ids'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars((string) ($cat['name'] ?? '')); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Karat</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Karats">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Karats</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach (($sa_filter_opts['karats'] ?? []) as $kr):
                                            $kn = trim((string) ($kr['name'] ?? ''));
                                            if ($kn === '') continue;
                                        ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_karat[]" value="<?php echo htmlspecialchars($kn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($kn, (array) $sa_filters['karats'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($kn); ?></span></label>
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
                                        <?php foreach (($sa_filter_opts['articles'] ?? []) as $fa):
                                            $art = trim((string) ($fa['article'] ?? ''));
                                            if ($art === '') continue;
                                        ?>
                                        <label class="mp-ms-opt"><input type="checkbox" name="adv_article[]" value="<?php echo htmlspecialchars($art, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($art, (array) $sa_filters['articles'], true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($art); ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sa-adv-section filter-field-full">Amounts &amp; identifiers</div>
                        <div class="filter-field">
                            <label for="sa_adv_currency">Currency</label>
                            <select class="form-control" id="sa_adv_currency" name="adv_currency">
                                <option value="">Select Currency</option>
                                <?php foreach (($sa_filter_opts['currencies'] ?? []) as $cur):
                                    $cv = trim((string) ($cur['currency'] ?? ''));
                                    if ($cv === '') continue;
                                ?>
                                <option value="<?php echo htmlspecialchars($cv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strcasecmp($cv, (string) ($sa_filters['currency'] ?? '')) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($cv); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_above_amount">Above Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="sa_adv_above_amount" name="adv_above_amount" value="<?php echo $sa_filters['above_amount'] !== null ? htmlspecialchars((string) $sa_filters['above_amount'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_barcode">Barcode No</label>
                            <input type="text" class="form-control" id="sa_adv_barcode" name="adv_barcode" value="<?php echo htmlspecialchars((string) ($sa_filters['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_invoice_no">Invoice No.</label>
                            <input type="text" class="form-control" id="sa_adv_invoice_no" name="adv_invoice_no" value="<?php echo htmlspecialchars((string) ($sa_filters['invoice_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_gross_wt">Gross Wt</label>
                            <input type="number" step="0.001" min="0" class="form-control" id="sa_adv_gross_wt" name="adv_gross_wt" value="<?php echo $sa_filters['gross_wt'] !== null ? htmlspecialchars((string) $sa_filters['gross_wt'], ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Min gross wt">
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_ledger_type">Ledger Type</label>
                            <select class="form-control" id="sa_adv_ledger_type" name="adv_ledger_type">
                                <option value="" <?php echo ($sa_filters['ledger_type'] ?? '') === '' ? 'selected' : ''; ?>>Select</option>
                                <option value="customer" <?php echo ($sa_filters['ledger_type'] ?? '') === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                <option value="metal" <?php echo ($sa_filters['ledger_type'] ?? '') === 'metal' ? 'selected' : ''; ?>>Metal</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_comment">Comment</label>
                            <input type="text" class="form-control" id="sa_adv_comment" name="adv_comment" value="<?php echo htmlspecialchars((string) ($sa_filters['comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="filter-field">
                            <label for="sa_adv_layaways_status">Layaways Status</label>
                            <select class="form-control" id="sa_adv_layaways_status" name="adv_layaways_status">
                                <option value="" <?php echo ($sa_filters['layaways_status'] ?? '') === '' ? 'selected' : ''; ?>>Select</option>
                                <option value="active" <?php echo ($sa_filters['layaways_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="none" <?php echo ($sa_filters['layaways_status'] ?? '') === 'none' ? 'selected' : ''; ?>>Not Layaways</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-adv">
                    <button type="submit" class="btn btn-adv-apply">Apply Filter</button>
                    <a href="sale-analysis.php" class="btn btn-adv-clear">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var inp = document.getElementById('saDateRange');
    var def = <?php echo json_encode($default_range); ?>;
    document.getElementById('saRefresh').addEventListener('click', function () {
        var v = (inp && inp.value) ? String(inp.value).trim() : def;
        var parts = v.split(/\s+\-\s+/);
        if (parts.length === 2 && parts[0].trim() !== '' && parts[1].trim() !== '') {
            window.location.href = 'sale-analysis.php?sa_from=' + encodeURIComponent(parts[0].trim()) + '&sa_to=' + encodeURIComponent(parts[1].trim());
            return;
        }
        window.location.reload();
    });

    function saMpMsUpdateLabel(wrap) {
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

    function saInitMpMultiSelect(root) {
        (root || document).querySelectorAll('#saAdvFilterModal [data-mp-ms]').forEach(function (wrap) {
            if (wrap._saMpMsInit) return;
            wrap._saMpMsInit = true;
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
                saMpMsUpdateLabel(wrap);
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasOpen = panel.classList.contains('is-open');
                document.querySelectorAll('#saAdvFilterModal .mp-ms-panel.is-open').forEach(function (p) { p.classList.remove('is-open'); });
                document.querySelectorAll('#saAdvFilterModal .mp-ms-btn').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
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

    if (!document._saMpMsDocClick) {
        document._saMpMsDocClick = true;
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#saAdvFilterModal .mp-ms')) return;
            document.querySelectorAll('#saAdvFilterModal .mp-ms-panel.is-open').forEach(function (p) { p.classList.remove('is-open'); });
            document.querySelectorAll('#saAdvFilterModal .mp-ms-btn').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        saInitMpMultiSelect(document);
        if (window.jQuery) {
            jQuery('#saAdvFilterModal').on('shown.bs.modal', function () {
                saInitMpMultiSelect(document.getElementById('saAdvFilterModal'));
            });
        }
    });
})();
</script>
<script>
function saInitTableTools() {
    var table = document.getElementById('saMainTable');
    var headRow = document.getElementById('saMainHeadRow');
    if (!table || !headRow) return;

    var SA_COL_STORAGE_KEY = 'auragold_sa_cols_v2';
    var SA_COL_DEFAULT_ORDER = <?php echo json_encode($sa_col_keys, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function getHeadOrder() {
        var out = [];
        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            out.push(th.getAttribute('data-col'));
        });
        return out;
    }

    function normalizeStoredOrder(arr) {
        if (!arr || !Array.isArray(arr) || arr.length !== SA_COL_DEFAULT_ORDER.length) return null;
        var set = {};
        for (var i = 0; i < arr.length; i++) set[arr[i]] = true;
        for (var j = 0; j < SA_COL_DEFAULT_ORDER.length; j++) {
            if (!set[SA_COL_DEFAULT_ORDER[j]]) return null;
        }
        return arr.slice();
    }

    function toggleColHidden(key, hide) {
        table.querySelectorAll('[data-col="' + key + '"]').forEach(function (el) {
            el.classList.toggle('sa-col-hidden', !!hide);
        });
        var cb = document.querySelector('.sa-col-cb[data-col="' + key + '"]');
        if (cb) cb.checked = !hide;
    }

    function countVisibleCols() {
        return getHeadOrder().filter(function (k) {
            var th = headRow.querySelector('th[data-col="' + k + '"]');
            return th && !th.classList.contains('sa-col-hidden');
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
    }

    function syncEmptyColspan() {
        var empty = table.querySelector('tbody td[colspan]');
        if (!empty) return;
        var n = headRow.querySelectorAll('th[data-col]:not(.sa-col-hidden)').length;
        empty.colSpan = Math.max(1, n);
    }

    function saveColState() {
        var hidden = {};
        getHeadOrder().forEach(function (k) {
            var th = headRow.querySelector('th[data-col="' + k + '"]');
            if (th && th.classList.contains('sa-col-hidden')) hidden[k] = true;
        });
        var widths = {};
        getHeadOrder().forEach(function (k) {
            var th = headRow.querySelector('th[data-col="' + k + '"]');
            if (th && th.style && th.style.width) widths[k] = th.style.width;
        });
        try {
            localStorage.setItem(SA_COL_STORAGE_KEY, JSON.stringify({
                order: getHeadOrder(),
                hidden: hidden,
                widths: widths
            }));
        } catch (e) {}
        // legacy keys cleanup optional — keep col vis dropdown in sync
        try {
            var vis = {};
            getHeadOrder().forEach(function (k) {
                vis[k] = !hidden[k];
            });
            localStorage.setItem('auragold_sa_colvis_v1', JSON.stringify(vis));
        } catch (e2) {}
    }

    function loadColState() {
        var state = {};
        try {
            var raw = localStorage.getItem(SA_COL_STORAGE_KEY);
            if (raw) state = JSON.parse(raw) || {};
        } catch (e) {}

        var ord = normalizeStoredOrder(state.order);
        if (ord) reorderColumns(ord);

        if (state.hidden && typeof state.hidden === 'object') {
            SA_COL_DEFAULT_ORDER.forEach(function (k) {
                if (state.hidden[k]) toggleColHidden(k, true);
            });
        } else {
            // migrate from legacy colvis-only storage
            try {
                var legacy = localStorage.getItem('auragold_sa_colvis_v1');
                if (legacy) {
                    var o = JSON.parse(legacy) || {};
                    SA_COL_DEFAULT_ORDER.forEach(function (k) {
                        toggleColHidden(k, o[k] === false);
                    });
                }
            } catch (eL) {}
        }

        if (countVisibleCols() === 0) {
            SA_COL_DEFAULT_ORDER.forEach(function (k) { toggleColHidden(k, false); });
        }

        if (state.widths && typeof state.widths === 'object') {
            Object.keys(state.widths).forEach(function (k) {
                var th = headRow.querySelector('th[data-col="' + k + '"]');
                if (th && state.widths[k]) th.style.width = state.widths[k];
            });
        }

        syncEmptyColspan();
    }

    function thFromPoint(clientX, clientY) {
        var el = document.elementFromPoint(clientX, clientY);
        if (!el || !el.closest) return null;
        return el.closest('#saMainHeadRow th[data-col]');
    }

    function bindColumnDragResize() {
        headRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var handle = th.querySelector('.sa-col-head-inner');
            if (handle && !handle._saBound) {
                handle._saBound = true;
                handle.addEventListener('pointerdown', function (e) {
                    if (e.button !== 0) return;
                    var dragFromKey = th.getAttribute('data-col');
                    if (!dragFromKey || th.classList.contains('sa-col-hidden')) return;
                    e.preventDefault();
                    e.stopPropagation();
                    th.classList.add('sa-col-dragging');
                    var scrollEl = table.closest('.sa-table-scroll');
                    if (scrollEl) scrollEl.classList.add('sa-col-scroll-dragging');
                    try { handle.setPointerCapture(e.pointerId); } catch (errCap) {}

                    function clearDropHighlights() {
                        headRow.querySelectorAll('.sa-col-drop-target').forEach(function (x) {
                            x.classList.remove('sa-col-drop-target');
                        });
                    }

                    function onMove(ev) {
                        clearDropHighlights();
                        var over = thFromPoint(ev.clientX, ev.clientY);
                        if (over && over.getAttribute('data-col') !== dragFromKey && !over.classList.contains('sa-col-hidden')) {
                            over.classList.add('sa-col-drop-target');
                        }
                    }

                    function onEnd(ev) {
                        th.classList.remove('sa-col-dragging');
                        if (scrollEl) scrollEl.classList.remove('sa-col-scroll-dragging');
                        clearDropHighlights();
                        try { handle.releasePointerCapture(ev.pointerId); } catch (errRel) {}
                        handle.removeEventListener('pointermove', onMove);
                        handle.removeEventListener('pointerup', onEnd);
                        handle.removeEventListener('pointercancel', onEnd);

                        var over = thFromPoint(ev.clientX, ev.clientY);
                        var toKey = over && over.getAttribute('data-col');
                        if (!toKey || toKey === dragFromKey || over.classList.contains('sa-col-hidden')) return;
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

            var resizer = th.querySelector('.sa-col-resizer');
            if (resizer && !resizer._saBound) {
                resizer._saBound = true;
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

    document.querySelectorAll('.sa-col-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var key = this.getAttribute('data-col');
            if (!key) return;
            if (!this.checked) {
                if (countVisibleCols() === 1) {
                    var th = headRow.querySelector('th[data-col="' + key + '"]');
                    if (th && !th.classList.contains('sa-col-hidden')) {
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

    document.querySelectorAll('.sa-col-check-label').forEach(function (lbl) {
        lbl.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    loadColState();
    bindColumnDragResize();
    saInitPagination();
}

function saInitPagination() {
    var table = document.getElementById('saMainTable');
    if (!table) return;

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.sa-data-row'));
    var infoEl = document.getElementById('saPaginationInfo');
    var pageSizeEl = document.getElementById('saPageSize');
    var pageLabel = document.getElementById('saPageLabel');
    var btnFirst = document.getElementById('saPageFirst');
    var btnPrev = document.getElementById('saPagePrev');
    var btnNext = document.getElementById('saPageNext');
    var btnLast = document.getElementById('saPageLast');

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
    document.addEventListener('DOMContentLoaded', saInitTableTools);
} else {
    saInitTableTools();
}
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
