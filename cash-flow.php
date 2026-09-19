<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fs_ledger_groups.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

function auragold_cf_fmt_num($v) {
    return number_format((float) $v, 2, '.', '');
}

/** Debit column: show magnitude so the total matches the sum of line amounts (JewelSteps-style). */
function auragold_cf_fmt_debit_col($v) {
    return number_format(abs((float) $v), 2, '.', '');
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

$cf_left_labels = [
    'Capital Account',
    'Loans (Liability)',
    'Current Assets',
    'Branch / Divisions',
    'Suspense A/C',
    'Sales Account',
    'Direct Income',
    'Indirect Income',
];
$cf_right_labels = [
    'Current Liabilities',
    'Fixed Assets',
    'Investments',
    'Misc. Expenses (ASSET)',
    'Purchase Account',
    'Direct Expenses',
    'Indirect Expenses',
];

$cf_buckets = [];
foreach (array_merge($cf_left_labels, $cf_right_labels) as $lbl) {
    $cf_buckets[$lbl] = 0.0;
}

$cf_ledger_error = '';
$ledger_table_ok = false;
$tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
if ($tchk && mysqli_num_rows($tchk) > 0) {
    $ledger_table_ok = true;
}
if ($tchk) {
    mysqli_free_result($tchk);
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

$cf_br_sql = fs_customer_ledger_branch_and_sql($conn);

if ($ledger_table_ok) {
    $customer_sundry_map = [];
    $customer_sundry_lower = [];
    $tcust = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customers'");
    if ($tcust && mysqli_num_rows($tcust) > 0) {
        $cm = getList("SELECT name, sundry_debtors_id FROM tbl_customers WHERE status = 1");
        foreach ($cm as $row) {
            $nm = trim($row['name'] ?? '');
            if ($nm === '') {
                continue;
            }
            $sid = (int) ($row['sundry_debtors_id'] ?? 0);
            if (!isset($customer_sundry_map[$nm])) {
                $customer_sundry_map[$nm] = $sid;
            }
            $lk = strtolower($nm);
            if (!isset($customer_sundry_lower[$lk])) {
                $customer_sundry_lower[$lk] = $sid;
            }
        }
    }
    if ($tcust) {
        mysqli_free_result($tcust);
    }

    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $cf_br_sql . " ORDER BY customer_name ASC"
    );
    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? $nr['customer_name'] : '';
        if ($customer_name === '') {
            continue;
        }
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $cf_br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $cf_br_sql . " AND transaction_type = 'opening'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_row) {
                $ob = (float) ($opening_row['balance_amount'] ?? 0);
                if ($ob != 0.0) {
                    $opening_amt = $ob;
                } else {
                    $dr = (float) ($opening_row['debit_amount'] ?? 0);
                    $cr = (float) ($opening_row['credit_amount'] ?? 0);
                    $opening_amt = $dr > 0 ? $dr : -$cr;
                }
            }
        }

        $period_where = "customer_name = '$cust_esc' AND status = 1 AND COALESCE(transaction_type,'') != 'opening'";
        if ($from_date !== '') {
            $period_where .= " AND transaction_date >= '$from_date'";
        }
        if ($to_date !== '') {
            $period_where .= " AND transaction_date <= '$to_date'";
        }
        $psum = getRecord(
            "SELECT COALESCE(SUM(debit_amount),0) AS td, COALESCE(SUM(credit_amount),0) AS tc
            FROM tbl_customer_ledger WHERE $period_where" . $cf_br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;

        $bucket = fs_map_ledger_to_cash_flow_bucket($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if (!isset($cf_buckets[$bucket])) {
            $cf_buckets[$bucket] = 0.0;
        }
        $cf_buckets[$bucket] += $closing_amt;
    }
} else {
    $cf_ledger_error = 'Ledger table not found. Save transactions to build cash flow.';
}

$sum_left = 0.0;
foreach ($cf_left_labels as $lbl) {
    $sum_left += abs((float) ($cf_buckets[$lbl] ?? 0));
}
$sum_right = 0.0;
foreach ($cf_right_labels as $lbl) {
    $sum_right += (float) ($cf_buckets[$lbl] ?? 0);
}

$sum_left_fmt = auragold_cf_fmt_num($sum_left);
$cf_left = [
    ['type' => 'top_total', 'label' => 'Total', 'value' => $sum_left_fmt],
    ['type' => 'sep'],
];
$push_left_detail_rows = static function () use (&$cf_left, $cf_left_labels, $cf_buckets) {
    foreach ($cf_left_labels as $li => $lbl) {
        $zebra = ($li % 2 === 1) ? ' cf-row-zebra' : '';
        $cf_left[] = [
            'type' => 'row',
            'label' => $lbl,
            'value' => auragold_cf_fmt_debit_col($cf_buckets[$lbl] ?? 0),
            'tr_class' => 'cf-row' . $zebra,
            'bucket' => $lbl,
        ];
    }
};
$push_left_detail_rows();
$cf_left[] = ['type' => 'sep'];
$cf_left[] = ['type' => 'section_total', 'label' => 'Total', 'value' => $sum_left_fmt];
$cf_left[] = ['type' => 'sep'];
$push_left_detail_rows();
$cf_left[] = ['type' => 'sep'];
$cf_left[] = ['type' => 'section_total', 'label' => 'Total', 'value' => $sum_left_fmt];

$cf_right = [];
foreach ($cf_right_labels as $ri => $lbl) {
    $zebra = ($ri % 2 === 1) ? ' cf-row-zebra' : '';
    $cf_right[] = [
        'type' => 'row',
        'label' => $lbl,
        'value' => auragold_cf_fmt_num($cf_buckets[$lbl] ?? 0),
        'tr_class' => 'cf-row' . $zebra,
        'bucket' => $lbl,
    ];
}
$cf_right[] = ['type' => 'sep'];
$cf_right[] = ['type' => 'section_total', 'label' => 'Total', 'value' => auragold_cf_fmt_num($sum_right)];

$cf_page_from_iso = ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) ? $from_date : '';
$cf_page_to_iso = ($to_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) ? $to_date : '';
$cf_fy_start_iso = date('Y-m-d', strtotime($fyStart));
$cf_fy_end_iso = date('Y-m-d', strtotime($fyEnd));
$cf_date_range_attr = htmlspecialchars($display_range, ENT_QUOTES, 'UTF-8');
$cf_default_range_attr = htmlspecialchars($default_range, ENT_QUOTES, 'UTF-8');

$DASHBOARD_PAGE_TITLE = 'Cash Flow';
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
    .cf-col-right {
        border-left: 1px solid rgba(201, 162, 39, 0.35);
    }
    @media (max-width: 991.98px) {
        .cf-col-right { border-left: none; border-top: 1px solid rgba(201, 162, 39, 0.3); padding-top: 1rem; margin-top: 0.5rem; }
    }
    .bs-panel {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
        height: 100%;
    }
    .cf-scroll {
        max-height: min(70vh, 640px);
        overflow-y: auto;
    }
    .cf-scroll::-webkit-scrollbar { width: 8px; }
    .cf-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .cf-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%);
        border-radius: 4px;
    }
    .bs-panel .table { margin-bottom: 0; font-size: 14px; }
    /* Navy bar + white text (match top nav); override style.css safely */
    .tb-wrap .bs-panel .table thead th {
        background: linear-gradient(180deg, var(--tb-navy) 0%, var(--tb-navy-deep) 100%) !important;
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
        border-bottom: 2px solid var(--tb-gold-dark) !important;
        padding: 12px 14px;
        position: sticky;
        top: 0;
        z-index: 2;
        white-space: nowrap;
        font-size: 13px;
    }
    .bs-panel tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-color: #eef0f3;
        color: #1e293b;
    }
    .bs-panel tbody tr.cf-row td { background: #ffffff; }
    .bs-panel tbody tr.cf-row-zebra td { background: #f4f5f7; }
    .bs-panel tbody tr.cf-row:hover td { background: #fff9ec !important; }
    .bs-panel tbody tr.cf-top-total td {
        font-weight: 700;
        background: #f8fafc !important;
        border-bottom: 2px solid #64748b !important;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
    }
    .bs-panel tbody tr.cf-section-total td {
        font-weight: 700;
        background: #f1f5f9 !important;
        border-top: 2px solid #64748b !important;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
    }
    a.cf-amt-link {
        color: #2563eb;
        text-decoration: none;
        cursor: pointer;
        border-bottom: 1px dotted rgba(37, 99, 235, 0.45);
    }
    a.cf-amt-link:hover { color: #1d4ed8; text-decoration: none; border-bottom-color: #1d4ed8; }
    #cfGroupModal .modal-header {
        background: #fff;
        color: #1e293b;
        border-bottom: 3px solid #7c3aed;
        box-shadow: 0 1px 0 rgba(124, 58, 237, 0.15);
    }
    #cfGroupModal .modal-header .close { color: #64748b; opacity: 0.9; text-shadow: none; }
    #cfGroupModal .cf-modal-sub { font-size: 13px; color: #64748b; margin-bottom: 10px; }
    #cfGroupModal .table thead th {
        background: #f1f5f9;
        font-weight: 600;
        font-size: 13px;
        border-bottom: 2px solid #e2e8f0;
    }
    #cfGroupModal .table td { font-size: 13px; vertical-align: middle; }
    #cfGroupModal .cf-m-num { text-align: right; font-variant-numeric: tabular-nums; }
    .cf-modal-datebar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(17, 41, 75, 0.1);
    }
    .cf-modal-date-range {
        display: inline-flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.4rem 0.5rem;
        flex: 1 1 280px;
        min-width: 0;
    }
    .cf-modal-date-range .cf-modal-date-label {
        flex-shrink: 0;
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        white-space: nowrap;
    }
    .cf-modal-date-range .cf-modal-date-sep {
        flex-shrink: 0;
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        text-transform: lowercase;
        padding: 0 0.15rem;
    }
    .cf-modal-datebar input[type="date"] {
        max-width: none;
        width: 10.75rem;
        min-width: 9rem;
        flex: 0 0 auto;
        font-size: 13px;
        border-color: rgba(201, 162, 39, 0.45);
        border-radius: 6px;
    }
    .cf-modal-date-actions {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.5rem;
    }
    .cf-sep td {
        padding: 0 !important;
        height: 0;
        border: none !important;
        background: transparent !important;
    }
    .cf-sep div {
        margin: 10px 14px;
        border-top: 1px dashed #94a3b8;
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

$DASHBOARD_FS_PAGE = true;
require __DIR__ . '/includes/dashboard_shell_top.php';

function auragold_cf_render_rows(array $rows): void
{
    foreach ($rows as $r) {
        $t = $r['type'] ?? 'row';
        if ($t === 'sep') {
            echo '<tr class="cf-sep"><td colspan="2"><div></div></td></tr>';
            continue;
        }
        $lab = htmlspecialchars($r['label'] ?? '');
        $val = htmlspecialchars($r['value'] ?? '');
        if ($t === 'top_total') {
            echo '<tr class="cf-top-total"><td>' . $lab . '</td><td class="bs-num">' . $val . '</td></tr>';
            continue;
        }
        if ($t === 'section_total') {
            echo '<tr class="cf-section-total"><td>' . $lab . '</td><td class="bs-num">' . $val . '</td></tr>';
            continue;
        }
        $trc = htmlspecialchars($r['tr_class'] ?? 'cf-row');
        $bucket = isset($r['bucket']) ? (string) $r['bucket'] : '';
        if ($bucket !== '') {
            $besc = htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8');
            $valCell = '<a href="#" class="cf-amt-link" data-cf-bucket="' . $besc . '">' . $val . '</a>';
        } else {
            $valCell = $val;
        }
        echo '<tr class="' . $trc . '"><td>' . $lab . '</td><td class="bs-num">' . $valCell . '</td></tr>';
    }
}
?>
<div class="tb-wrap" id="cfRoot"
    data-date-range="<?php echo $cf_date_range_attr; ?>"
    data-default-range="<?php echo $cf_default_range_attr; ?>"
    data-from-iso="<?php echo htmlspecialchars($cf_page_from_iso, ENT_QUOTES, 'UTF-8'); ?>"
    data-to-iso="<?php echo htmlspecialchars($cf_page_to_iso, ENT_QUOTES, 'UTF-8'); ?>"
    data-fy-start-iso="<?php echo htmlspecialchars($cf_fy_start_iso, ENT_QUOTES, 'UTF-8'); ?>"
    data-fy-end-iso="<?php echo htmlspecialchars($cf_fy_end_iso, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h1 class="tb-page-title mb-0">Cash Flow</h1>
        <div class="tb-toolbar d-flex flex-wrap align-items-center gap-2">
            <form method="get" action="cash-flow.php" class="d-flex flex-wrap align-items-center gap-2 mb-0">
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                    <input type="text" class="form-control tb-date-range border-start-0" name="date_range" id="cfDateRange" value="<?php echo htmlspecialchars($display_range); ?>" placeholder="DD-MM-YYYY - DD-MM-YYYY (empty + Apply = all dates)" aria-label="Date range">
                </div>
                <button type="submit" class="btn btn-tb-primary">Apply</button>
                <button type="button" class="btn btn-tb-outline" id="cfClear">Clear</button>
            </form>
            <details class="tb-export-dd" data-fs-root="#cfRoot" data-fs-file="cash-flow" data-fs-title="Cash Flow">
                <summary class="btn btn-tb-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <?php if ($cf_ledger_error !== ''): ?>
    <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($cf_ledger_error); ?></div>
    <?php endif; ?>

    <div class="row g-0 align-items-stretch">
        <div class="col-lg-6">
            <div class="bs-panel">
                <div class="table-responsive cf-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="bs-num" style="width:38%;">Debit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php auragold_cf_render_rows($cf_left); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6 cf-col-right ps-lg-3">
            <div class="bs-panel">
                <div class="table-responsive cf-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="bs-num" style="width:38%;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php auragold_cf_render_rows($cf_right); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cfGroupModal" tabindex="-1" role="dialog" aria-labelledby="cfGroupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cfGroupModalTitle">Account Groups</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="cf-modal-datebar" id="cfModalDateBar">
                    <span class="small font-weight-bold text-muted text-uppercase" style="letter-spacing:.04em;">Total</span>
                    <div class="cf-modal-date-range">
                        <label class="cf-modal-date-label" for="cfModalFromDate">Date</label>
                        <input type="date" class="form-control form-control-sm" id="cfModalFromDate" name="modal_from_date" autocomplete="off" aria-label="From date">
                        <span class="cf-modal-date-sep" aria-hidden="true">to</span>
                        <input type="date" class="form-control form-control-sm" id="cfModalToDate" name="modal_to_date" autocomplete="off" aria-label="To date">
                    </div>
                    <div class="cf-modal-date-actions">
                        <button type="button" class="btn btn-sm btn-tb-primary" id="cfModalDateApply">Apply</button>
                        <button type="button" class="btn btn-sm btn-tb-outline" id="cfModalDateFY" title="Use current financial year">Financial year</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cfModalDateClear" type="button" title="All dates (no range filter)">All dates</button>
                    </div>
                </div>
                <p class="small text-muted mb-0 pb-2 border-bottom">Adjust dates and Apply to refresh. Leave both empty and Apply for all dates.</p>
                <div class="cf-modal-sub" id="cfModalSub"></div>
                <div id="cfModalLoading" class="text-muted py-4 text-center" style="display:none;">Loading…</div>
                <div id="cfModalError" class="alert alert-warning py-2" style="display:none;"></div>
                <div id="cfModalBody"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    document.getElementById('cfClear').addEventListener('click', function () {
        window.location.href = 'cash-flow.php';
    });

    var $modal = $('#cfGroupModal');
    var $title = $('#cfGroupModalTitle');
    var $sub = $('#cfModalSub');
    var $body = $('#cfModalBody');
    var $load = $('#cfModalLoading');
    var $err = $('#cfModalError');
    var $inpFrom = $('#cfModalFromDate');
    var $inpTo = $('#cfModalToDate');
    var cfModalBucket = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderLedgerTable(rows) {
        var h = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>' +
            '<th>Name</th><th class="cf-m-num">Opening Balance</th><th class="cf-m-num">Debit</th><th class="cf-m-num">Credit</th><th class="cf-m-num">Balance</th></tr></thead><tbody>';
        if (!rows || !rows.length) {
            h += '<tr><td colspan="5" class="text-center text-muted py-4">No Rows To Show</td></tr>';
        } else {
            rows.forEach(function (r) {
                var link = r.ledger_url ? '<a href="' + esc(r.ledger_url) + '">' + esc(r.name) + '</a>' : esc(r.name);
                h += '<tr><td>' + link + '</td><td class="cf-m-num">' + esc(r.opening) + '</td><td class="cf-m-num">' + esc(r.debit) +
                    '</td><td class="cf-m-num">' + esc(r.credit) + '</td><td class="cf-m-num">' + esc(r.balance) + '</td></tr>';
            });
        }
        h += '</tbody></table></div>';
        return h;
    }

    function cfIsoToDmY(iso) {
        var p = (iso || '').trim().split('-');
        if (p.length !== 3) return '';
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function cfModalGetDateRangeParam() {
        var f = ($inpFrom.val() || '').trim();
        var t = ($inpTo.val() || '').trim();
        if (!f && !t) return '';
        if (!f || !t) return '__incomplete__';
        return cfIsoToDmY(f) + ' - ' + cfIsoToDmY(t);
    }

    function cfModalSyncDatesFromPage() {
        var $r = $('#cfRoot');
        $inpFrom.val($r.attr('data-from-iso') || '');
        $inpTo.val($r.attr('data-to-iso') || '');
    }

    function cfModalRenderResponse(res) {
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
        } else {
            $err.text('Unknown response.').show();
        }
    }

    function cfModalFetchDetail() {
        if (!cfModalBucket) return;
        var dr = cfModalGetDateRangeParam();
        if (dr === '__incomplete__') {
            $load.hide();
            $err.text('Select both From and To dates, or clear both and use All dates.').show();
            return;
        }
        $err.hide().text('');
        $body.empty();
        $load.show();
        $.getJSON('ajax/cash-flow-details.php', { bucket: cfModalBucket, date_range: dr })
            .done(cfModalRenderResponse)
            .fail(function () {
                $load.hide();
                $err.text('Request failed. Check your connection and try again.').show();
            });
    }

    $(document).on('click', 'a.cf-amt-link', function (e) {
        e.preventDefault();
        var b = $(this).data('cf-bucket');
        if (!b) return;
        cfModalBucket = b;
        cfModalSyncDatesFromPage();
        $err.hide().text('');
        $body.empty();
        $title.text('Account Groups');
        $sub.text('');
        $modal.modal('show');
    });

    $modal.on('shown.bs.modal', function () {
        cfModalFetchDetail();
    });

    $('#cfModalDateApply').on('click', function () {
        cfModalFetchDetail();
    });

    $('#cfModalDateFY').on('click', function () {
        var $r = $('#cfRoot');
        $inpFrom.val($r.attr('data-fy-start-iso') || '');
        $inpTo.val($r.attr('data-fy-end-iso') || '');
        cfModalFetchDetail();
    });

    $('#cfModalDateClear').on('click', function () {
        $inpFrom.val('');
        $inpTo.val('');
        cfModalFetchDetail();
    });
})();
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
