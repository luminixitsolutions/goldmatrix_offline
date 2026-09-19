<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fs_ledger_groups.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$DASHBOARD_PAGE_TITLE = 'Fund Flow';
$DASHBOARD_EXTRA_CSS = <<<'HTML'
<style>
    .tb-wrap {
        max-width: 100%;
        --tb-gold: #c9a227;
        --tb-gold-mid: #b8941f;
        --tb-gold-dark: #8b6914;
        --tb-navy: #11294b;
        --tb-navy-deep: #0c1f38;
    }
    .tb-page-title {
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #e8c547 0%, var(--tb-gold-mid) 45%, var(--tb-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        -webkit-text-fill-color: transparent;
    }
    @supports not (background-clip: text) {
        .tb-page-title { color: var(--tb-gold-dark); -webkit-text-fill-color: var(--tb-gold-dark); }
    }
    .tb-toolbar .form-control.tb-date-range {
        max-width: 260px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .tb-toolbar .input-group-text {
        border-color: rgba(201, 162, 39, 0.45) !important;
    }
    .btn-tb-outline {
        border: 1px solid var(--tb-gold-mid) !important;
        color: var(--tb-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
    }
    .btn-tb-outline:hover {
        background: #fffbf0 !important;
        color: var(--tb-gold-dark) !important;
        border-color: var(--tb-gold) !important;
    }
    .btn-tb-primary {
        background: linear-gradient(180deg, #d4af37 0%, var(--tb-gold-mid) 55%, var(--tb-gold-dark) 100%) !important;
        border: 1px solid var(--tb-gold-dark) !important;
        color: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
        text-shadow: 0 1px 0 rgba(0,0,0,.12);
    }
    .btn-tb-primary:hover { filter: brightness(1.05); color: #fff !important; }
    .ff-col-right {
        border-left: 1px solid rgba(201, 162, 39, 0.35);
    }
    @media (max-width: 991.98px) {
        .ff-col-right { border-left: none; border-top: 1px solid rgba(201, 162, 39, 0.3); padding-top: 1rem; margin-top: 0.5rem; }
    }
    .ff-section-title {
        font-weight: 650;
        color: var(--tb-navy);
        font-size: 0.95rem;
        margin-bottom: 10px;
        letter-spacing: 0.02em;
    }
    .bs-panel {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
        height: 100%;
    }
    .bs-panel .table { margin-bottom: 0; font-size: 14px; }
    .bs-panel thead th {
        background: linear-gradient(180deg, var(--tb-navy) 0%, var(--tb-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-bottom: 2px solid var(--tb-gold-dark) !important;
        padding: 12px 14px;
    }
    .bs-panel tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-color: #eef0f3;
        color: #1e293b;
    }
    .bs-panel tbody tr.ff-row td { background: #ffffff; }
    .bs-panel tbody tr.ff-row:hover td { background: #fff9ec !important; }
    .bs-panel tbody tr.ff-total td {
        font-weight: 700;
        background: #fdf2f7 !important;
        border-top: 2px solid var(--tb-navy) !important;
    }
    .bs-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .tb-export-dd { position: relative; display: inline-block; }
    .tb-export-dd > summary { list-style: none; cursor: pointer; user-select: none; }
    .tb-export-dd > summary::-webkit-details-marker { display: none; }
    .tb-export-menu {
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
    .tb-export-menu a {
        display: block;
        padding: 8px 14px;
        color: #374151;
        text-decoration: none;
        font-size: 13px;
    }
    .tb-export-menu a:hover { background: #fffbf0; color: var(--tb-gold-dark); }
</style>
HTML;

/** JewelSteps-style: absolute value + Dr|Cr (positive = Dr, negative = Cr) — same as trial balance */
function fund_flow_fmt_signed($signed) {
    $signed = (float) $signed;
    if (abs($signed) < 0.0000001) {
        return '0.00';
    }
    $suf = $signed >= 0 ? 'Dr' : 'Cr';
    return number_format(abs($signed), 2, '.', '') . $suf;
}

function fund_flow_fmt_amount($v) {
    return number_format((float) $v, 3, '.', '');
}

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

$tb_hidden_ledgers = ['Purchase Fixing Account'];
$tb_hidden_sql = '';
if (!empty($tb_hidden_ledgers)) {
    $h = [];
    foreach ($tb_hidden_ledgers as $hn) {
        $h[] = "'" . mysqli_real_escape_string($conn, $hn) . "'";
    }
    $tb_hidden_sql = ' AND customer_name NOT IN (' . implode(',', $h) . ')';
}

$ff_ledger_error = '';
$ff_sources = [];
$ff_applications = [['label' => 'Total', 'amount' => fund_flow_fmt_amount(0), 'total' => true]];
$ff_working_capital = [];

$groups_result = fs_compute_ledger_groups($conn, $from_date, $to_date, $tb_hidden_sql);
if (!$groups_result['ok']) {
    $ff_ledger_error = 'Ledger table not found. Post transactions to build fund flow.';
} else {
    $G = $groups_result['groups'];
    $ca_o = (float) ($G['Current Assets']['opening'] ?? 0);
    $ca_c = (float) ($G['Current Assets']['closing'] ?? 0);
    $cl_o = (float) ($G['Current Liabilities']['opening'] ?? 0);
    $cl_c = (float) ($G['Current Liabilities']['closing'] ?? 0);

    $ca_wci = $ca_c - $ca_o;
    $cl_wci = $cl_o - $cl_c;
    $wc_o = $ca_o - $cl_o;
    $wc_c = $ca_c - $cl_c;
    $wc_wci = $wc_c - $wc_o;

    $ff_working_capital = [
        [
            'particulars' => 'Current Assets',
            'opening' => fund_flow_fmt_signed($ca_o),
            'closing' => fund_flow_fmt_signed($ca_c),
            'wci' => fund_flow_fmt_amount($ca_wci),
        ],
        [
            'particulars' => 'Current Liabilities',
            'opening' => fund_flow_fmt_signed($cl_o),
            'closing' => fund_flow_fmt_signed($cl_c),
            'wci' => fund_flow_fmt_amount($cl_wci),
        ],
        [
            'particulars' => 'Working Capital',
            'opening' => fund_flow_fmt_signed($wc_o),
            'closing' => fund_flow_fmt_signed($wc_c),
            'wci' => fund_flow_fmt_amount($wc_wci),
        ],
    ];

    $pnl = fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql);
    $closing_stock = 0.0;
    $ts = null;
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
    $net_result = $gross_profit + $indirect_income_net - $indirect_expense_net;
    $net_is_profit = $net_result >= -0.0000001;
    $net_label = $net_is_profit ? 'Net Profit' : 'Net Loss';
    $net_amt = fund_flow_fmt_amount(abs($net_result));

    $ff_sources = [
        ['label' => $net_label, 'amount' => $net_amt, 'total' => false],
        ['label' => 'Total', 'amount' => $net_amt, 'total' => true],
    ];
}

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';
?>
<div class="tb-wrap">
    <?php if ($ff_ledger_error !== ''): ?>
    <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($ff_ledger_error); ?></div>
    <?php endif; ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h1 class="tb-page-title mb-0">Fund Flow</h1>
        <form method="get" action="fund-flow.php" class="tb-toolbar d-flex flex-wrap align-items-center gap-2 mb-0">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control tb-date-range border-start-0" name="date_range" id="ffDateRange" value="<?php echo htmlspecialchars($display_range); ?>" placeholder="DD-MM-YYYY - DD-MM-YYYY" aria-label="Date range">
            </div>
            <button type="submit" class="btn btn-tb-primary">Apply</button>
            <button type="button" class="btn btn-tb-outline" id="ffClear">Clear</button>
            <details class="tb-export-dd" data-fs-root="#ffExportRoot" data-fs-file="fund-flow" data-fs-title="Fund Flow">
                <summary class="btn btn-tb-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </form>
    </div>

    <div id="ffExportRoot">
    <div class="ff-section-title">Sources and Applications of Funds</div>
    <div class="row g-0 align-items-stretch mb-4">
        <div class="col-lg-6">
            <div class="bs-panel">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th class="bs-num" style="width:42%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ff_sources)): ?>
                            <tr class="ff-row"><td colspan="2" class="text-center text-muted py-3">No data</td></tr>
                            <?php else: ?>
                            <?php foreach ($ff_sources as $row): ?>
                            <tr class="<?php echo !empty($row['total']) ? 'ff-total' : 'ff-row'; ?>">
                                <td><?php echo htmlspecialchars($row['label']); ?></td>
                                <td class="bs-num"><?php echo htmlspecialchars($row['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6 ff-col-right ps-lg-3">
            <div class="bs-panel">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th class="bs-num" style="width:42%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ff_applications as $row): ?>
                            <tr class="<?php echo !empty($row['total']) ? 'ff-total' : 'ff-row'; ?>">
                                <td><?php echo htmlspecialchars($row['label']); ?></td>
                                <td class="bs-num"><?php echo htmlspecialchars($row['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="ff-section-title">Changes in Working Capital</div>
    <div class="bs-panel">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th class="bs-num">Opening Balance</th>
                        <th class="bs-num">Closing Balance</th>
                        <th class="bs-num">Working Capital Increase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ff_working_capital)): ?>
                    <tr class="ff-row"><td colspan="4" class="text-center text-muted py-3">No data</td></tr>
                    <?php else: ?>
                    <?php foreach ($ff_working_capital as $r): ?>
                    <tr class="ff-row">
                        <td><?php echo htmlspecialchars($r['particulars']); ?></td>
                        <td class="bs-num"><?php echo htmlspecialchars($r['opening']); ?></td>
                        <td class="bs-num"><?php echo htmlspecialchars($r['closing']); ?></td>
                        <td class="bs-num"><?php echo htmlspecialchars($r['wci']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>
<script>
(function () {
    document.getElementById('ffClear').addEventListener('click', function () {
        window.location.href = 'fund-flow.php';
    });
})();
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
