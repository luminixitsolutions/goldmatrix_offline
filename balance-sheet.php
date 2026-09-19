<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fs_ledger_groups.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$DASHBOARD_PAGE_TITLE = 'Balance Sheet';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .bs-wrap {
        max-width: 100%;
        width: 100%;
        --bs-gold: #c9a227;
        --bs-gold-mid: #b8941f;
        --bs-gold-dark: #8b6914;
        --bs-navy: #11294b;
        --bs-navy-deep: #0c1f38;
    }
    .bs-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--bs-gold-mid) 45%, var(--bs-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .bs-page-title { color: var(--bs-gold-dark); -webkit-text-fill-color: var(--bs-gold-dark); }
    }
    /* Toolbar gaps: assets/css/fs-financial-toolbar.css */
    .bs-toolbar-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    .bs-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }
    .bs-toolbar .form-control.bs-date-range {
        max-width: 280px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .bs-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-bs-outline {
        border: 1px solid var(--bs-gold-mid) !important;
        color: var(--bs-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
    }
    .btn-bs-outline:hover {
        background: #fffbf0 !important;
        border-color: var(--bs-gold) !important;
    }
    .btn-bs-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--bs-gold-mid) 55%, var(--bs-gold-dark) 100%) !important;
        border: 1px solid var(--bs-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
    }
    .bs-table-wrap {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
        height: 100%;
    }
    .bs-table-wrap table { margin-bottom: 0; font-size: 14px; }
    .bs-table-wrap .table.bs-table thead th {
        background: linear-gradient(180deg, var(--bs-navy) 0%, var(--bs-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-bottom: 2px solid var(--bs-gold-dark) !important;
        padding: 12px 14px;
    }
    .bs-table-wrap tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-color: #eef0f3;
    }
    .bs-table-wrap tbody tr:nth-child(odd) { background: #ffffff; }
    .bs-table-wrap tbody tr:nth-child(even) { background: #f4f5f7; }
    .bs-table-wrap tfoot td {
        font-weight: 700;
        background: #fdf2f7;
        border-top: 2px solid rgba(201, 162, 39, 0.35);
        padding: 12px 14px;
    }
    .bs-num { text-align: right; font-variant-numeric: tabular-nums; }
    .bs-sub td:first-child { padding-left: 2rem !important; font-size: 13px; color: #374151; }
    .bs-export-dd { position: relative; display: inline-block; }
    .bs-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .bs-export-dd > summary::-webkit-details-marker { display: none; }
    .bs-export-menu {
        position: absolute; right: 0; top: 100%; margin-top: 4px; min-width: 140px;
        padding: 6px 0; background: #fff; border: 1px solid rgba(201, 162, 39, 0.35);
        border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,.1); z-index: 20;
    }
    .bs-export-menu a {
        display: block; padding: 8px 14px; color: #374151; text-decoration: none; font-size: 13px;
    }
    .bs-export-menu a:hover { background: #fffbf0; color: var(--bs-gold-dark); }
    /* Side-by-side: Liability | Asset — equal column height (stretch to taller side) */
    .bs-two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        align-items: stretch;
        width: 100%;
    }
    .bs-two-col .bs-col-liability,
    .bs-two-col .bs-col-asset {
        min-width: 0;
        width: 100%;
        display: flex;
        flex-direction: column;
    }
    @media (max-width: 767.98px) {
        .bs-two-col {
            grid-template-columns: 1fr;
        }
    }
    /* JewelSteps-style statement panels */
    .bs-panel {
        background: #f8f9fb;
        border: 1px solid rgba(17, 41, 75, 0.12);
        border-radius: 10px;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(17, 41, 75, 0.06);
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 100%;
    }
    .bs-panel-h {
        text-align: center;
        font-weight: 700;
        font-size: 15px;
        color: var(--bs-navy);
        padding: 12px 14px;
        background: linear-gradient(180deg, #eef1f6 0%, #e8ebf2 100%);
        border-bottom: 1px solid rgba(17, 41, 75, 0.1);
        flex-shrink: 0;
    }
    .bs-panel-body {
        padding: 6px 0 0;
        background: #fafbfc;
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .bs-line {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 12px;
        padding: 10px 16px;
        font-size: 14px;
        border-bottom: 1px solid rgba(0,0,0,.04);
    }
    .bs-line .bs-lbl { color: #334155; flex: 1; min-width: 0; }
    .bs-line .bs-val {
        font-variant-numeric: tabular-nums;
        text-align: right;
        white-space: nowrap;
        font-weight: 500;
        color: #0f172a;
    }
    .bs-line.bs-sub .bs-lbl { padding-left: 12px; font-size: 13px; color: #64748b; }
    .bs-hr-dash {
        border: 0;
        border-top: 1px dashed rgba(17, 41, 75, 0.2);
        margin: 6px 16px;
    }
    .bs-hr-solid {
        border: 0;
        border-top: 2px solid rgba(17, 41, 75, 0.15);
        margin: 8px 0 0;
    }
    .bs-line.bs-total {
        background: #fff;
        font-weight: 700;
        padding-top: 12px;
        padding-bottom: 12px;
        border-bottom: none;
    }
    .bs-line.bs-total .bs-val { color: var(--bs-navy-deep); }
    a.bs-amt-link {
        color: #2563eb;
        text-decoration: none;
        cursor: pointer;
        border-bottom: 1px dotted rgba(37, 99, 235, 0.45);
    }
    a.bs-amt-link:hover { color: #1d4ed8; text-decoration: none; border-bottom-color: #1d4ed8; }
    .bs-line.bs-total .bs-amt-link {
        font-weight: 700;
        color: var(--bs-navy-deep);
        border-bottom-color: rgba(37, 99, 235, 0.35);
    }
    #bsGroupModal .modal-header {
        background: linear-gradient(180deg, var(--bs-navy) 0%, var(--bs-navy-deep) 100%);
        color: #fff;
        border-bottom: 2px solid var(--bs-gold-dark);
    }
    #bsGroupModal .modal-header .close { color: #fff; opacity: 0.9; text-shadow: none; }
    #bsGroupModal .bs-modal-sub { font-size: 13px; color: #64748b; margin-bottom: 10px; }
    #bsGroupModal .table thead th {
        background: #f1f5f9;
        font-weight: 600;
        font-size: 13px;
        border-bottom: 2px solid #e2e8f0;
    }
    #bsGroupModal .table td { font-size: 13px; vertical-align: middle; }
    #bsGroupModal .bs-m-num { text-align: right; font-variant-numeric: tabular-nums; }
    .bs-modal-datebar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(17, 41, 75, 0.1);
    }
    .bs-modal-datebar .input-group { border-radius: 8px; overflow: hidden; }
    .bs-modal-datebar .input-group-text {
        background: #fff;
        border-color: rgba(201, 162, 39, 0.45);
        color: #a67c1a;
    }
    .bs-modal-datebar .bs-modal-date-field {
        width: auto;
        min-width: 0;
    }
    .bs-modal-datebar input[type="date"] {
        max-width: 11.5rem;
        font-size: 13px;
        border-color: rgba(201, 162, 39, 0.45);
        border-radius: 6px;
    }
    #bsModalDateHint {
        margin-top: 0.35rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid rgba(17, 41, 75, 0.08);
    }
</style>
HTML;

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';

$tz = new DateTimeZone(function_exists('auragold_branch_timezone') ? auragold_branch_timezone() : 'Asia/Kolkata');
$now = new DateTime('now', $tz);
$y = (int) $now->format('Y');
$m = (int) $now->format('n');
if ($m >= 4) {
    $fyStart = sprintf('%d-04-01', $y);
    $fyEnd = sprintf('%d-03-31', $y + 1);
} else {
    $fyStart = sprintf('%d-04-01', $y - 1);
    $fyEnd = sprintf('%d-03-31', $y);
}
$default_range = date('d-m-Y', strtotime($fyStart)) . ' - ' . date('d-m-Y', strtotime($fyEnd));

$date_range_get = isset($_GET['date_range']) ? trim((string) $_GET['date_range']) : null;
$from_date = '';
$to_date = '';
$display_range = '';

if ($date_range_get === null) {
    $from_date = fs_normalize_sql_date(date('Y-m-d', strtotime($fyStart)));
    $to_date = fs_normalize_sql_date(date('Y-m-d', strtotime($fyEnd)));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        $from_date = '';
        $to_date = '';
    }
    $display_range = $default_range;
} elseif ($date_range_get === '') {
    $from_date = '';
    $to_date = '';
    $display_range = '';
} else {
    $parts = explode(' - ', $date_range_get, 2);
    if (count($parts) === 2) {
        $from_date = fs_normalize_sql_date($parts[0]);
        $to_date = fs_normalize_sql_date($parts[1]);
        if ($from_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $from_date = '';
        }
        if ($to_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $to_date = '';
        }
    }
    $display_range = $date_range_get;
}

$bs_page_from_iso = ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) ? $from_date : '';
$bs_page_to_iso = ($to_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) ? $to_date : '';
$bs_fy_start_iso = date('Y-m-d', strtotime($fyStart));
$bs_fy_end_iso = date('Y-m-d', strtotime($fyEnd));

$tb_hidden_ledgers = ['Purchase Fixing Account'];
$tb_hidden_sql = '';
if (!empty($tb_hidden_ledgers)) {
    $h = [];
    foreach ($tb_hidden_ledgers as $hn) {
        $h[] = "'" . mysqli_real_escape_string($conn, $hn) . "'";
    }
    $tb_hidden_sql = ' AND customer_name NOT IN (' . implode(',', $h) . ')';
}

$computed = fs_compute_ledger_groups($conn, $from_date, $to_date, $tb_hidden_sql);
$groups = $computed['groups'];
$ledger_ok = $computed['ok'];

$closing_stock = 0.0;
$ts = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
if ($ts && mysqli_num_rows($ts) > 0) {
    $stk_br = function_exists('auragold_tbl_stock_branch_and_sql') ? auragold_tbl_stock_branch_and_sql($conn, '') : '';
    $stk = getRecord(
        "SELECT COALESCE(SUM(value), 0) AS v FROM tbl_stock WHERE status = 1
         AND (IFNULL(current_weight,0) > 0.00001 OR IFNULL(current_qty,0) > 0.00001)" . $stk_br
    );
    if ($stk && isset($stk['v'])) {
        $closing_stock = (float) $stk['v'];
    }
}
if ($ts) {
    mysqli_free_result($ts);
}

$C = static function ($key) use ($groups) {
    return isset($groups[$key]['closing']) ? (float) $groups[$key]['closing'] : 0.0;
};

$liab_current_liabilities = -1 * $C('Current Liabilities');
$pl_opening_display = -1 * $C('Profit and Loss Opening');
$pl_current_display = -1 * (
    $C('Sales Account') + $C('Purchase Account') + $C('Indirect Expenses') + $C('Profit and Loss')
);
$profit_loss_parent = $pl_opening_display + $pl_current_display;
/** Column total (JewelSteps-style): liability side shows current liabilities only; assets include P&L + difference. */
$total_balance_sheet = $liab_current_liabilities;

$asset_current_assets = $C('Current Assets');
$asset_difference = $total_balance_sheet - $asset_current_assets - $closing_stock - $profit_loss_parent;
$total_assets = $asset_current_assets + $closing_stock + $profit_loss_parent + $asset_difference;

$bs_error = '';
if (!$ledger_ok) {
    $bs_error = 'Ledger table not found. Post vouchers to build the balance sheet.';
}

$fmt = static function ($v) {
    return number_format((float) $v, 2, '.', '');
};

$bs_date_range_attr = htmlspecialchars($display_range, ENT_QUOTES, 'UTF-8');
$bs_default_range_attr = htmlspecialchars($default_range, ENT_QUOTES, 'UTF-8');

?>
<div class="bs-wrap">
    <div class="bs-toolbar-header">
        <h1 class="bs-page-title mb-0">Balance Sheet</h1>
        <div class="bs-toolbar-actions">
            <form method="get" action="balance-sheet.php" class="bs-toolbar mb-0">
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                    <input type="text" class="form-control bs-date-range border-start-0" name="date_range" id="bsDateRange"
                       value="<?php echo htmlspecialchars($display_range); ?>"
                       placeholder="DD-MM-YYYY - DD-MM-YYYY (empty + Apply = all dates)" aria-label="Date range">
                </div>
                <button type="submit" class="btn btn-bs-primary">Apply</button>
                <button type="button" class="btn btn-bs-outline" id="bsClear">Clear</button>
            </form>
            <details class="bs-export-dd" data-fs-mode="balance-sheet" data-fs-root="#bsRoot" data-fs-file="balance-sheet" data-fs-title="Balance Sheet">
                <summary class="btn btn-bs-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="bs-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <?php if ($bs_error !== ''): ?>
    <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($bs_error); ?></div>
    <?php endif; ?>

    <div class="bs-two-col" id="bsRoot"
        data-date-range="<?php echo $bs_date_range_attr; ?>"
        data-default-range="<?php echo $bs_default_range_attr; ?>"
        data-from-iso="<?php echo htmlspecialchars($bs_page_from_iso, ENT_QUOTES, 'UTF-8'); ?>"
        data-to-iso="<?php echo htmlspecialchars($bs_page_to_iso, ENT_QUOTES, 'UTF-8'); ?>"
        data-fy-start-iso="<?php echo htmlspecialchars($bs_fy_start_iso, ENT_QUOTES, 'UTF-8'); ?>"
        data-fy-end-iso="<?php echo htmlspecialchars($bs_fy_end_iso, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="bs-col-liability">
            <div class="bs-panel">
                <div class="bs-panel-h">Liability</div>
                <div class="bs-panel-body">
                    <div class="bs-line">
                        <span class="bs-lbl">Current Liabilities</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="current_liabilities"><?php echo htmlspecialchars($fmt($liab_current_liabilities)); ?></a>
                    </div>
                    <hr class="bs-hr-solid">
                    <div class="bs-line bs-total">
                        <span class="bs-lbl">Total</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="total_liability"><?php echo htmlspecialchars($fmt($total_balance_sheet)); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="bs-col-asset">
            <div class="bs-panel">
                <div class="bs-panel-h">Asset</div>
                <div class="bs-panel-body">
                    <div class="bs-line">
                        <span class="bs-lbl">Current Assets</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="current_assets"><?php echo htmlspecialchars($fmt($asset_current_assets)); ?></a>
                    </div>
                    <div class="bs-line">
                        <span class="bs-lbl">Closing Stock</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="closing_stock"><?php echo htmlspecialchars($fmt($closing_stock)); ?></a>
                    </div>
                    <hr class="bs-hr-dash">
                    <div class="bs-line">
                        <span class="bs-lbl">Profit And Loss Account</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="profit_loss_account"><?php echo htmlspecialchars($fmt($profit_loss_parent)); ?></a>
                    </div>
                    <div class="bs-line bs-sub">
                        <span class="bs-lbl">Profit And Loss (Opening)</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="profit_loss_opening"><?php echo htmlspecialchars($fmt($pl_opening_display)); ?></a>
                    </div>
                    <div class="bs-line bs-sub">
                        <span class="bs-lbl">Current Period</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="profit_loss_current"><?php echo htmlspecialchars($fmt($pl_current_display)); ?></a>
                    </div>
                    <hr class="bs-hr-dash">
                    <div class="bs-line">
                        <span class="bs-lbl">Difference</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="difference"><?php echo htmlspecialchars($fmt($asset_difference)); ?></a>
                    </div>
                    <hr class="bs-hr-solid">
                    <div class="bs-line bs-total">
                        <span class="bs-lbl">Total</span>
                        <a href="#" class="bs-val bs-amt-link" data-bs-detail="total_assets"><?php echo htmlspecialchars($fmt($total_assets)); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bsGroupModal" tabindex="-1" role="dialog" aria-labelledby="bsGroupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bsGroupModalTitle">Account Groups</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="bs-modal-datebar" id="bsModalDateBar">
                    <span class="small font-weight-bold text-muted text-uppercase" style="letter-spacing:.04em;">Period</span>
                    <div class="d-flex flex-wrap align-items-center bs-modal-date-field">
                        <label class="small text-muted mb-0 mr-1" for="bsModalFromDate">From</label>
                        <input type="date" class="form-control form-control-sm" id="bsModalFromDate" name="modal_from_date" autocomplete="off">
                    </div>
                    <div class="d-flex flex-wrap align-items-center bs-modal-date-field">
                        <label class="small text-muted mb-0 mr-1" for="bsModalToDate">To</label>
                        <input type="date" class="form-control form-control-sm" id="bsModalToDate" name="modal_to_date" autocomplete="off">
                    </div>
                    <button type="button" class="btn btn-sm btn-bs-primary" id="bsModalDateApply">Apply</button>
                    <button type="button" class="btn btn-sm btn-bs-outline" id="bsModalDateFY" title="Use current financial year">Financial year</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="bsModalDateClear" type="button" title="All dates (no range filter)">All dates</button>
                </div>
                <p class="small text-muted mb-0" id="bsModalDateHint">Use the browser date picker on each field. Leave both empty and click Apply for all dates.</p>
                <div class="bs-modal-sub" id="bsModalSub"></div>
                <div id="bsModalLoading" class="text-muted py-4 text-center" style="display:none;">Loading…</div>
                <div id="bsModalError" class="alert alert-warning py-2" style="display:none;"></div>
                <div id="bsModalBody"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var el = document.getElementById('bsClear');
    if (el) el.addEventListener('click', function () { window.location.href = 'balance-sheet.php'; });

    var $modal = $('#bsGroupModal');
    var $title = $('#bsGroupModalTitle');
    var $sub = $('#bsModalSub');
    var $body = $('#bsModalBody');
    var $load = $('#bsModalLoading');
    var $err = $('#bsModalError');
    var $inpFrom = $('#bsModalFromDate');
    var $inpTo = $('#bsModalToDate');
    var bsModalDetailKey = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderLedgerTable(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>' +
            '<th>Name</th><th class="bs-m-num">Opening Balance</th><th class="bs-m-num">Debit</th><th class="bs-m-num">Credit</th><th class="bs-m-num">Balance</th></tr></thead><tbody>';
        if (!rows || !rows.length) {
            h += '<tr><td colspan="5" class="text-center text-muted py-3">No ledgers in this group for the selected period.</td></tr>';
        } else {
            rows.forEach(function (r) {
                var link = r.ledger_url ? '<a href="' + esc(r.ledger_url) + '">' + esc(r.name) + '</a>' : esc(r.name);
                h += '<tr><td>' + link + '</td><td class="bs-m-num">' + esc(r.opening) + '</td><td class="bs-m-num">' + esc(r.debit) +
                    '</td><td class="bs-m-num">' + esc(r.credit) + '</td><td class="bs-m-num">' + esc(r.balance) + '</td></tr>';
            });
        }
        h += '</tbody></table></div>';
        return h;
    }

    function renderStockTable(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>' +
            '<th>Particulars</th><th class="bs-m-num">Weight</th><th class="bs-m-num">Qty</th><th class="bs-m-num">Value</th></tr></thead><tbody>';
        if (!rows || !rows.length) {
            h += '<tr><td colspan="4" class="text-center text-muted py-3">No stock lines with quantity or weight.</td></tr>';
        } else {
            rows.forEach(function (r) {
                h += '<tr><td>' + esc(r.name) + '</td><td class="bs-m-num">' + esc(r.weight) + '</td><td class="bs-m-num">' + esc(r.qty) +
                    '</td><td class="bs-m-num">' + esc(r.value) + '</td></tr>';
            });
        }
        h += '</tbody></table></div>';
        return h;
    }

    function renderExplain(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>';
        (rows || []).forEach(function (r) {
            h += '<tr><td>' + esc(r.label) + '</td><td class="bs-m-num font-weight-bold">' + esc(r.value) + '</td></tr>';
        });
        h += '</tbody></table></div>';
        return h;
    }

    function bsIsoToDmY(iso) {
        var p = (iso || '').trim().split('-');
        if (p.length !== 3) return '';
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function bsModalGetDateRangeParam() {
        var f = ($inpFrom.val() || '').trim();
        var t = ($inpTo.val() || '').trim();
        if (!f && !t) return '';
        if (!f || !t) return '__incomplete__';
        return bsIsoToDmY(f) + ' - ' + bsIsoToDmY(t);
    }

    function bsModalSyncDatesFromPage() {
        var $r = $('#bsRoot');
        $inpFrom.val($r.attr('data-from-iso') || '');
        $inpTo.val($r.attr('data-to-iso') || '');
    }

    function bsModalRenderResponse(res) {
        $load.hide();
        if (!res || !res.ok) {
            $err.text((res && res.message) ? res.message : 'Could not load detail.').show();
            return;
        }
        $title.text(res.title || 'Account Groups');
        var subParts = [];
        if (res.subtitle) subParts.push(res.subtitle);
        if (res.date_text) subParts.push(res.date_text);
        $sub.html(subParts.map(esc).join(' · '));

        if (res.mode === 'ledger') {
            $body.html(renderLedgerTable(res.rows));
        } else if (res.mode === 'stock') {
            $body.html(renderStockTable(res.rows));
        } else if (res.mode === 'explain') {
            $body.html(renderExplain(res.rows));
        } else if (res.mode === 'explain_then_ledger') {
            var html = renderExplain(res.explain_rows || []);
            if (res.ledger_caption) {
                html += '<h6 class="mt-3 mb-2 font-weight-bold">' + esc(res.ledger_caption) + '</h6>';
            }
            html += renderLedgerTable(res.rows || []);
            $body.html(html);
        } else if (res.mode === 'sections' && res.sections) {
            var html = '';
            res.sections.forEach(function (sec) {
                html += '<h6 class="mt-3 mb-2 font-weight-bold">' + esc(sec.label) + '</h6>';
                html += renderLedgerTable(sec.rows);
            });
            $body.html(html);
        } else {
            $err.text('Unknown response.').show();
        }
    }

    function bsModalFetchDetail() {
        if (!bsModalDetailKey) {
            return;
        }
        var dr = bsModalGetDateRangeParam();
        if (dr === '__incomplete__') {
            $load.hide();
            $err.text('Select both From and To dates, or clear both and use All dates.').show();
            return;
        }
        $err.hide().text('');
        $body.empty();
        $load.show();
        $.getJSON('ajax/balance-sheet-detail.php', { key: bsModalDetailKey, date_range: dr })
            .done(bsModalRenderResponse)
            .fail(function () {
                $load.hide();
                $err.text('Request failed. Check your connection and try again.').show();
            });
    }

    $(document).on('click', 'a.bs-amt-link', function (e) {
        e.preventDefault();
        var key = $(this).data('bs-detail');
        if (!key) return;
        bsModalDetailKey = key;
        bsModalSyncDatesFromPage();
        $err.hide().text('');
        $body.empty();
        $title.text('Account Groups');
        $sub.text('');
        $modal.modal('show');
    });

    $modal.on('shown.bs.modal', function () {
        bsModalFetchDetail();
    });

    $('#bsModalDateApply').on('click', function () {
        bsModalFetchDetail();
    });

    $('#bsModalDateFY').on('click', function () {
        var $r = $('#bsRoot');
        $inpFrom.val($r.attr('data-fy-start-iso') || '');
        $inpTo.val($r.attr('data-fy-end-iso') || '');
        bsModalFetchDetail();
    });

    $('#bsModalDateClear').on('click', function () {
        $inpFrom.val('');
        $inpTo.val('');
        bsModalFetchDetail();
    });
})();
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
