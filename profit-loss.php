<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fs_ledger_groups.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$DASHBOARD_PAGE_TITLE = 'Profit and Loss';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .pl-wrap {
        max-width: 100%;
        --pl-navy: #11294b;
        --pl-navy-deep: #0c1f38;
        --pl-border: rgba(17, 41, 75, 0.12);
        --pl-muted: #64748b;
    }
    .pl-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        color: var(--pl-navy-deep);
    }
    /* Toolbar gaps: assets/css/fs-financial-toolbar.css */
    .pl-toolbar .form-control.pl-date-range {
        max-width: 280px;
        border: 1px solid rgba(17, 41, 75, 0.18);
        border-radius: 8px;
        font-size: 13px;
    }
    .pl-toolbar .input-group-text { border-color: rgba(17, 41, 75, 0.18) !important; }
    .pl-toolbar .pl-export-dd > summary {
        margin: 0;
    }
    .btn-pl-outline {
        border: 1px solid rgba(17, 41, 75, 0.22) !important;
        color: var(--pl-navy-deep) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
    }
    .btn-pl-outline:hover { background: #f8fafc !important; }
    .btn-pl-primary {
        background: linear-gradient(180deg, #5b4b9a 0%, #4a3d7a 100%) !important;
        border: 1px solid #3d3366 !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
    }
    .btn-pl-primary:hover { filter: brightness(1.06); color: #fff !important; }
    .btn-pl-icon {
        border: 1px solid rgba(17, 41, 75, 0.18) !important;
        color: var(--pl-navy-deep) !important;
        background: #fff !important;
        border-radius: 8px;
        padding: 0.35rem 0.55rem;
        line-height: 1;
    }
    .pl-two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        align-items: stretch;
        width: 100%;
        background: #fff;
        border: 1px solid var(--pl-border);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(17, 41, 75, 0.06);
    }
    .pl-col {
        min-width: 0;
        background: #f8f9fb;
        display: flex;
        flex-direction: column;
    }
    .pl-col:first-child { border-right: 1px solid var(--pl-border); }
    @media (max-width: 991.98px) {
        .pl-two-col { grid-template-columns: 1fr; }
        .pl-col:first-child { border-right: none; border-bottom: 1px solid var(--pl-border); }
    }
    .pl-panel-table { margin-bottom: 0; font-size: 14px; width: 100%; }
    .pl-panel-table thead th {
        background: linear-gradient(180deg, #eef1f6 0%, #e8ebf2 100%);
        font-weight: 700;
        color: var(--pl-navy-deep) !important;
        border-bottom: 1px solid var(--pl-border) !important;
        padding: 12px 14px;
    }
    .pl-panel-table tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-color: rgba(0,0,0,.06);
        color: #1e293b;
        background: #fafbfc;
    }
    .pl-panel-table tbody tr.pl-row:hover td { background: #f1f5f9 !important; }
    .pl-panel-table tbody tr.pl-section-total td {
        font-weight: 700;
        background: #fff !important;
        border-top: 2px solid rgba(17, 41, 75, 0.15) !important;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
    }
    .pl-panel-table tbody tr.pl-grand-total td {
        font-weight: 700;
        background: #fff !important;
        border-top: 2px solid var(--pl-navy) !important;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
    }
    .pl-panel-table tbody tr.pl-net-profit td { font-weight: 700; background: #f0fdf4 !important; }
    .pl-panel-table tbody tr.pl-net-loss td { font-weight: 700; background: #fff1f2 !important; }
    .pl-panel-table tbody tr.pl-bottom-rule td {
        padding: 6px 0 0 !important;
        border: none !important;
        background: transparent !important;
        border-top: 2px solid var(--pl-navy) !important;
    }
    .pl-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    a.pl-amt-link {
        color: #2563eb;
        text-decoration: none;
        cursor: pointer;
        border-bottom: 1px dotted rgba(37, 99, 235, 0.45);
    }
    a.pl-amt-link:hover { color: #1d4ed8; border-bottom-color: #1d4ed8; }
    .pl-export-dd { position: relative; display: inline-block; }
    .pl-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .pl-export-dd > summary::-webkit-details-marker { display: none; }
    .pl-export-menu {
        position: absolute; right: 0; top: 100%; margin-top: 4px; min-width: 140px;
        padding: 6px 0; background: #fff; border: 1px solid var(--pl-border);
        border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,.1); z-index: 20;
    }
    .pl-export-menu a {
        display: block; padding: 8px 14px; color: #374151; text-decoration: none; font-size: 13px;
    }
    .pl-export-menu a:hover { background: #f1f5f9; color: var(--pl-navy-deep); }
    #plGroupModal .modal-header {
        background: #fff;
        color: var(--pl-navy-deep);
        border-bottom: 1px solid var(--pl-border);
    }
    #plGroupModal .modal-title { width: 100%; text-align: center; font-weight: 700; }
    #plGroupModal .modal-header .close { color: var(--pl-navy-deep); opacity: 0.75; }
    #plGroupModal .pl-modal-sub { font-size: 13px; color: var(--pl-muted); margin-bottom: 10px; }
    #plGroupModal .table thead th {
        background: #e8f0fe;
        font-weight: 600;
        font-size: 13px;
        border-bottom: 2px solid #c7d7f0;
    }
    #plGroupModal .table td { font-size: 13px; vertical-align: middle; }
    #plGroupModal .pl-m-num { text-align: right; font-variant-numeric: tabular-nums; }
    .pl-modal-datebar {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.65rem 1rem;
        padding-bottom: 0.75rem; border-bottom: 1px solid var(--pl-border);
    }
    .pl-modal-date-field label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: var(--pl-muted);
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .pl-modal-date-field .form-control {
        max-width: 168px;
        font-size: 13px;
        border-color: rgba(17, 41, 75, 0.18);
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

$pl_date_range_attr = htmlspecialchars($display_range, ENT_QUOTES, 'UTF-8');
$pl_default_range_attr = htmlspecialchars($default_range, ENT_QUOTES, 'UTF-8');

$tb_hidden_ledgers = ['Purchase Fixing Account'];
$tb_hidden_sql = '';
if (!empty($tb_hidden_ledgers)) {
    $h = [];
    foreach ($tb_hidden_ledgers as $hn) {
        $h[] = "'" . mysqli_real_escape_string($conn, $hn) . "'";
    }
    $tb_hidden_sql = ' AND customer_name NOT IN (' . implode(',', $h) . ')';
}

$pnl = fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql);
$ledger_ok = $pnl['ok'];

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

$opening_stock = 0.0;

$sales_net = (float) ($pnl['sales_net'] ?? 0);
$purchase_net = (float) ($pnl['purchase_net'] ?? 0);
$direct_expense_net = (float) ($pnl['direct_expense_net'] ?? 0);
$indirect_expense_net = (float) ($pnl['indirect_expense_net'] ?? 0);
$direct_income_net = (float) ($pnl['direct_income_net'] ?? 0);
$indirect_income_net = (float) ($pnl['indirect_income_net'] ?? 0);

$total_income = $closing_stock + $sales_net + $direct_income_net;
$total_trading_expense = $opening_stock + $purchase_net + $direct_expense_net;
$gross_profit = $total_income - $total_trading_expense;
$gross_is_profit = $gross_profit >= -0.0000001;

$net_result = $gross_profit + $indirect_income_net - $indirect_expense_net;
$net_is_profit = $net_result >= -0.0000001;

$grand_exp_lower = ($gross_is_profit ? $gross_profit : abs($gross_profit)) + $indirect_expense_net;
$grand_inc_lower = $indirect_income_net + abs($net_result);

$fmt = static function ($v) {
    return number_format((float) $v, 2, '.', '');
};

if ($gross_is_profit) {
    $pl_expenses = [
        ['type' => 'row', 'label' => 'Opening Stock', 'value' => $fmt($opening_stock), 'detail' => 'opening_stock'],
        ['type' => 'row', 'label' => 'Purchase Accounts', 'value' => $fmt($purchase_net), 'detail' => 'purchase_accounts'],
        ['type' => 'row', 'label' => 'Direct Expenses', 'value' => $fmt($direct_expense_net), 'detail' => 'direct_expenses'],
        ['type' => 'row', 'label' => 'Gross Profit c/d', 'value' => $fmt($gross_profit), 'detail' => 'pl_gross_carry'],
        ['type' => 'section_total', 'label' => 'Total', 'value' => $fmt($total_income), 'detail' => 'pl_trading_total'],
        ['type' => 'row', 'label' => 'Gross Profit b/d', 'value' => $fmt($gross_profit), 'detail' => 'pl_gross_carry'],
        ['type' => 'row', 'label' => 'Indirect Expenses', 'value' => $fmt($indirect_expense_net), 'detail' => 'indirect_expenses'],
        ['type' => 'grand_total', 'label' => 'Grand Total', 'value' => $fmt($grand_exp_lower), 'detail' => 'pl_grand_total'],
        ['type' => 'bottom_rule'],
    ];
    $pl_income = [
        ['type' => 'row', 'label' => 'Closing Stock', 'value' => $fmt($closing_stock), 'detail' => 'closing_stock'],
        ['type' => 'row', 'label' => 'Sales Accounts', 'value' => $fmt($sales_net), 'detail' => 'sales_accounts'],
        ['type' => 'row', 'label' => 'Direct Incomes', 'value' => $fmt($direct_income_net), 'detail' => 'direct_incomes'],
        ['type' => 'section_total', 'label' => 'Total', 'value' => $fmt($total_income), 'detail' => 'pl_trading_total'],
        ['type' => 'row', 'label' => 'Indirect Incomes', 'value' => $fmt($indirect_income_net), 'detail' => 'indirect_incomes'],
        ['type' => $net_is_profit ? 'net_profit' : 'net_loss', 'label' => $net_is_profit ? 'Net Profit' : 'Net Loss', 'value' => $fmt(abs($net_result)), 'detail' => 'pl_net_line'],
        ['type' => 'grand_total', 'label' => 'Grand Total', 'value' => $fmt($grand_inc_lower), 'detail' => 'pl_grand_total'],
        ['type' => 'bottom_rule'],
    ];
} else {
    $gross_loss = abs($gross_profit);
    $pl_expenses = [
        ['type' => 'row', 'label' => 'Opening Stock', 'value' => $fmt($opening_stock), 'detail' => 'opening_stock'],
        ['type' => 'row', 'label' => 'Purchase Accounts', 'value' => $fmt($purchase_net), 'detail' => 'purchase_accounts'],
        ['type' => 'row', 'label' => 'Direct Expenses', 'value' => $fmt($direct_expense_net), 'detail' => 'direct_expenses'],
        ['type' => 'section_total', 'label' => 'Total', 'value' => $fmt($total_trading_expense), 'detail' => 'pl_trading_total'],
        ['type' => 'row', 'label' => 'Gross Loss b/d', 'value' => $fmt($gross_loss), 'detail' => 'pl_gross_carry'],
        ['type' => 'row', 'label' => 'Indirect Expenses', 'value' => $fmt($indirect_expense_net), 'detail' => 'indirect_expenses'],
        ['type' => 'grand_total', 'label' => 'Grand Total', 'value' => $fmt($grand_exp_lower), 'detail' => 'pl_grand_total'],
        ['type' => 'bottom_rule'],
    ];
    $pl_income = [
        ['type' => 'row', 'label' => 'Closing Stock', 'value' => $fmt($closing_stock), 'detail' => 'closing_stock'],
        ['type' => 'row', 'label' => 'Sales Accounts', 'value' => $fmt($sales_net), 'detail' => 'sales_accounts'],
        ['type' => 'row', 'label' => 'Direct Incomes', 'value' => $fmt($direct_income_net), 'detail' => 'direct_incomes'],
        ['type' => 'row', 'label' => 'Gross Loss b/d', 'value' => $fmt($gross_loss), 'detail' => 'pl_gross_carry'],
        ['type' => 'section_total', 'label' => 'Total', 'value' => $fmt($total_trading_expense), 'detail' => 'pl_trading_total'],
        ['type' => 'row', 'label' => 'Indirect Incomes', 'value' => $fmt($indirect_income_net), 'detail' => 'indirect_incomes'],
        ['type' => $net_is_profit ? 'net_profit' : 'net_loss', 'label' => $net_is_profit ? 'Net Profit' : 'Net Loss', 'value' => $fmt(abs($net_result)), 'detail' => 'pl_net_line'],
        ['type' => 'grand_total', 'label' => 'Grand Total', 'value' => $fmt($grand_inc_lower), 'detail' => 'pl_grand_total'],
        ['type' => 'bottom_rule'],
    ];
}

$pl_error = '';
if (!$ledger_ok) {
    $pl_error = 'Ledger table not found. Post transactions to build profit and loss.';
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function auragold_pl_render_amount_cell(array $r): void
{
    $val = htmlspecialchars($r['value'] ?? '');
    $d = isset($r['detail']) ? trim((string) $r['detail']) : '';
    if ($d !== '') {
        echo '<a href="#" class="pl-amt-link" data-pl-detail="' . htmlspecialchars($d) . '">' . $val . '</a>';
    } else {
        echo '<span class="pl-num">' . $val . '</span>';
    }
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function auragold_pl_render_rows(array $rows): void
{
    foreach ($rows as $r) {
        $t = $r['type'] ?? 'row';
        if ($t === 'bottom_rule') {
            echo '<tr class="pl-bottom-rule"><td colspan="2"></td></tr>';
            continue;
        }
        $lab = htmlspecialchars($r['label'] ?? '');
        if ($t === 'section_total') {
            echo '<tr class="pl-section-total"><td>' . $lab . '</td><td class="pl-num">';
            auragold_pl_render_amount_cell($r);
            echo '</td></tr>';
            continue;
        }
        if ($t === 'grand_total') {
            echo '<tr class="pl-grand-total"><td>' . $lab . '</td><td class="pl-num">';
            auragold_pl_render_amount_cell($r);
            echo '</td></tr>';
            continue;
        }
        if ($t === 'net_profit') {
            echo '<tr class="pl-net-profit"><td>' . $lab . '</td><td class="pl-num">';
            auragold_pl_render_amount_cell($r);
            echo '</td></tr>';
            continue;
        }
        if ($t === 'net_loss') {
            echo '<tr class="pl-net-loss"><td>' . $lab . '</td><td class="pl-num">';
            auragold_pl_render_amount_cell($r);
            echo '</td></tr>';
            continue;
        }
        echo '<tr class="pl-row"><td>' . $lab . '</td><td class="pl-num">';
        auragold_pl_render_amount_cell($r);
        echo '</td></tr>';
    }
}
?>
<div class="pl-wrap" id="plRoot" data-date-range="<?php echo $pl_date_range_attr; ?>" data-default-range="<?php echo $pl_default_range_attr; ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h1 class="pl-page-title mb-0">Profit and Loss</h1>
        <div class="pl-toolbar d-flex flex-wrap align-items-center">
            <form method="get" action="profit-loss.php" class="d-flex flex-wrap align-items-center mb-0">
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#64748b;"></i></span>
                    <input type="text" class="form-control pl-date-range border-start-0" name="date_range" id="plDateRange"
                        value="<?php echo htmlspecialchars($display_range); ?>"
                        placeholder="DD-MM-YYYY - DD-MM-YYYY (empty + Apply = all dates)" aria-label="Date range">
                </div>
                <button type="submit" class="btn btn-pl-primary">Apply</button>
                <button type="button" class="btn btn-pl-outline" id="plClear">Clear</button>
            </form>
            <details class="pl-export-dd" data-fs-root=".pl-two-col" data-fs-file="profit-loss" data-fs-title="Profit and Loss">
                <summary class="btn btn-pl-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="pl-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
            <button type="button" class="btn btn-pl-icon" title="Sort" aria-label="Sort"><i class="feather icon-bar-chart-2"></i></button>
            <button type="button" class="btn btn-pl-icon" title="List view" aria-label="List view"><i class="feather icon-list"></i></button>
        </div>
    </div>

    <?php if ($pl_error !== ''): ?>
    <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($pl_error); ?></div>
    <?php endif; ?>

    <div class="pl-two-col">
        <div class="pl-col">
            <div class="table-responsive">
                <table class="table pl-panel-table mb-0">
                    <thead>
                        <tr>
                            <th>Expenses</th>
                            <th class="pl-num" style="width:38%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php auragold_pl_render_rows($pl_expenses); ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pl-col">
            <div class="table-responsive">
                <table class="table pl-panel-table mb-0">
                    <thead>
                        <tr>
                            <th>Income</th>
                            <th class="pl-num" style="width:38%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php auragold_pl_render_rows($pl_income); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="plGroupModal" tabindex="-1" role="dialog" aria-labelledby="plGroupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="plGroupModalTitle">Account Groups</h5>
                <button type="button" class="close position-absolute" style="right:1rem;top:50%;transform:translateY(-50%);" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="pl-modal-datebar" id="plModalDateBar">
                    <span class="small font-weight-bold text-muted text-uppercase mb-1" style="letter-spacing:.04em;">Period</span>
                    <div class="pl-modal-date-field">
                        <label for="plModalDateFrom">From date</label>
                        <input type="date" class="form-control form-control-sm" id="plModalDateFrom" autocomplete="off" aria-label="From date">
                    </div>
                    <div class="pl-modal-date-field">
                        <label for="plModalDateTo">To date</label>
                        <input type="date" class="form-control form-control-sm" id="plModalDateTo" autocomplete="off" aria-label="To date">
                    </div>
                    <button type="button" class="btn btn-sm btn-pl-primary" id="plModalDateApply">Apply</button>
                    <button type="button" class="btn btn-sm btn-pl-outline" id="plModalDateFY" title="Use current financial year">Financial year</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="plModalDateClear" title="All dates (no range filter)">All dates</button>
                </div>
                <div class="pl-modal-sub font-weight-bold text-dark" id="plModalSub"></div>
                <div id="plModalLoading" class="text-muted py-4 text-center" style="display:none;">Loading…</div>
                <div id="plModalError" class="alert alert-warning py-2" style="display:none;"></div>
                <div id="plModalBody"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var el = document.getElementById('plClear');
    if (el) el.addEventListener('click', function () { window.location.href = 'profit-loss.php'; });

    var $modal = $('#plGroupModal');
    var $title = $('#plGroupModalTitle');
    var $sub = $('#plModalSub');
    var $body = $('#plModalBody');
    var $load = $('#plModalLoading');
    var $err = $('#plModalError');
    var $inpFrom = $('#plModalDateFrom');
    var $inpTo = $('#plModalDateTo');
    var plModalDetailKey = null;

    function plDdMmYyyyToIso(s) {
        s = (s || '').trim();
        var m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(s);
        if (!m) {
            return '';
        }
        return m[3] + '-' + m[2] + '-' + m[1];
    }

    function plSyncModalDatesFromRangeStr(rangeStr) {
        rangeStr = (rangeStr || '').trim();
        if (rangeStr.indexOf(' - ') === -1) {
            $inpFrom.val('');
            $inpTo.val('');
            return;
        }
        var parts = rangeStr.split(' - ');
        $inpFrom.val(plDdMmYyyyToIso(parts[0].trim()));
        $inpTo.val(plDdMmYyyyToIso(parts[1].trim()));
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderLedgerTable(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>' +
            '<th>Name</th><th class="pl-m-num">Opening Balance</th><th class="pl-m-num">Debit</th><th class="pl-m-num">Credit</th><th class="pl-m-num">Balance</th></tr></thead><tbody>';
        if (!rows || !rows.length) {
            h += '<tr><td colspan="5" class="text-center text-muted py-3">No ledgers in this group for the selected period.</td></tr>';
        } else {
            rows.forEach(function (r) {
                var link = r.ledger_url ? '<a href="' + esc(r.ledger_url) + '">' + esc(r.name) + '</a>' : esc(r.name);
                h += '<tr><td>' + link + '</td><td class="pl-m-num">' + esc(r.opening) + '</td><td class="pl-m-num">' + esc(r.debit) +
                    '</td><td class="pl-m-num">' + esc(r.credit) + '</td><td class="pl-m-num">' + esc(r.balance) + '</td></tr>';
            });
        }
        h += '</tbody></table></div>';
        return h;
    }

    function renderStockTable(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>' +
            '<th>Particulars</th><th class="pl-m-num">Weight</th><th class="pl-m-num">Qty</th><th class="pl-m-num">Value</th></tr></thead><tbody>';
        if (!rows || !rows.length) {
            h += '<tr><td colspan="4" class="text-center text-muted py-3">No stock lines with quantity or weight.</td></tr>';
        } else {
            rows.forEach(function (r) {
                h += '<tr><td>' + esc(r.name) + '</td><td class="pl-m-num">' + esc(r.weight) + '</td><td class="pl-m-num">' + esc(r.qty) +
                    '</td><td class="pl-m-num">' + esc(r.value) + '</td></tr>';
            });
        }
        h += '</tbody></table></div>';
        return h;
    }

    function renderExplain(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>';
        (rows || []).forEach(function (r) {
            h += '<tr><td>' + esc(r.label) + '</td><td class="pl-m-num font-weight-bold">' + esc(r.value) + '</td></tr>';
        });
        h += '</tbody></table></div>';
        return h;
    }

    function plModalRenderResponse(res) {
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
            var html2 = '';
            res.sections.forEach(function (sec) {
                html2 += '<h6 class="mt-3 mb-2 font-weight-bold">' + esc(sec.label) + '</h6>';
                html2 += renderLedgerTable(sec.rows);
            });
            $body.html(html2);
        } else {
            $err.text('Unknown response.').show();
        }
    }

    function plModalFetchDetail() {
        if (!plModalDetailKey) {
            return;
        }
        var df = ($inpFrom.val() || '').trim();
        var dt = ($inpTo.val() || '').trim();
        var payload = { key: plModalDetailKey };
        if (df !== '' && dt !== '') {
            payload.date_from = df;
            payload.date_to = dt;
        }
        $err.hide().text('');
        $body.empty();
        $load.show();
        $.getJSON('ajax/profit-loss-detail.php', payload)
            .done(plModalRenderResponse)
            .fail(function () {
                $load.hide();
                $err.text('Request failed. Check your connection and try again.').show();
            });
    }

    $(document).on('click', 'a.pl-amt-link', function (e) {
        e.preventDefault();
        var key = $(this).data('pl-detail');
        if (!key) return;
        plModalDetailKey = key;
        var pageDr = ($('#plRoot').attr('data-date-range')) || '';
        plSyncModalDatesFromRangeStr(pageDr);
        $err.hide().text('');
        $body.empty();
        $title.text('Account Groups');
        $sub.text('');
        $modal.modal('show');
    });

    $modal.on('shown.bs.modal', function () {
        plModalFetchDetail();
    });

    $('#plModalDateApply').on('click', function () {
        var df = ($inpFrom.val() || '').trim();
        var dt = ($inpTo.val() || '').trim();
        if ((df !== '' && dt === '') || (df === '' && dt !== '')) {
            $err.text('Please choose both From date and To date, or clear both for all dates.').show();
            return;
        }
        plModalFetchDetail();
    });

    $('#plModalDateFY').on('click', function () {
        var fy = $('#plRoot').attr('data-default-range') || '';
        plSyncModalDatesFromRangeStr(fy);
        plModalFetchDetail();
    });

    $('#plModalDateClear').on('click', function () {
        $inpFrom.val('');
        $inpTo.val('');
        plModalFetchDetail();
    });
})();
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
