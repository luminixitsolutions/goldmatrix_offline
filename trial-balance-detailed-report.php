<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fs_ledger_groups.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

/**
 * Normalize date from d-m-Y or Y-m-d to Y-m-d for SQL (matches account ledger filters).
 */
function tbd_normalize_sql_date($s) {
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    return $s;
}

/** JewelSteps-style: absolute value + Dr|Cr (positive = Dr, negative = Cr) */
function tbd_fmt_signed($signed) {
    $signed = (float) $signed;
    if (abs($signed) < 0.0000001) {
        return '0.00';
    }
    $suf = $signed >= 0 ? 'Dr' : 'Cr';
    return number_format(abs($signed), 2, '.', '') . $suf;
}

/**
 * Map a ledger name to grouped TB line (same as trial-balance.php).
 *
 * @param array<string,int> $customer_sundry_map
 * @param array<string,int> $customer_sundry_lower
 */
function tbd_map_ledger_to_group($ledger_name, array $customer_sundry_map, array $customer_sundry_lower) {
    $n = trim((string) $ledger_name);
    if ($n === '') {
        return 'Current Assets';
    }
    $lower = strtolower($n);

    if ($lower === 'profit and loss opening' || strcasecmp($n, 'Profit and Loss Opening') === 0) {
        return 'Profit and Loss Opening';
    }
    if ($lower === 'profit and loss' || strcasecmp($n, 'Profit and Loss') === 0) {
        return 'Profit and Loss';
    }

    if ($n === 'Sales Account' || $n === 'Making Sale Account') {
        return 'Sales Account';
    }
    if ($n === 'Purchase Account' || $n === 'Making Purchase Account') {
        return 'Purchase Account';
    }

    if (stripos($lower, 'expense') !== false || $lower === 'expenses' || $lower === 'indirect expenses') {
        return 'Indirect Expenses';
    }

    if ($n === 'Cash' || $n === 'Bank Account') {
        return 'Current Assets';
    }

    if (in_array($n, ['Tax Ledger', 'Hedging Account', 'Discount Received', 'Manufacturing Account', 'Sundry Debtors'], true)) {
        return 'Current Assets';
    }
    if ($n === 'Sundry Creditors') {
        return 'Current Liabilities';
    }

    $sid = null;
    if (isset($customer_sundry_map[$n])) {
        $sid = (int) $customer_sundry_map[$n];
    } elseif (isset($customer_sundry_lower[strtolower($n)])) {
        $sid = (int) $customer_sundry_lower[strtolower($n)];
    }
    if ($sid !== null) {
        if ($sid === 2) {
            return 'Current Liabilities';
        }
        if ($sid === 1 || $sid === 29) {
            return 'Current Assets';
        }
    }

    return 'Current Assets';
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
    $from_date = tbd_normalize_sql_date(date('Y-m-d', strtotime($fyStart)));
    $to_date = tbd_normalize_sql_date(date('Y-m-d', strtotime($fyEnd)));
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
        $from_date = tbd_normalize_sql_date($parts[0]);
        $to_date = tbd_normalize_sql_date($parts[1]);
        if ($from_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $from_date = '';
        }
        if ($to_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $to_date = '';
        }
    }
    $display_range = $date_range_get;
}

$tbd_groups = [];
$tb_total_debit = 0.0;
$tb_total_credit = 0.0;
$tb_ledger_error = '';

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

$tbd_br_sql = fs_customer_ledger_branch_and_sql($conn);

$tb_group_order = [
    'Current Liabilities',
    'Current Assets',
    'Sales Account',
    'Purchase Account',
    'Indirect Expenses',
    'Profit and Loss',
    'Profit and Loss Opening',
];

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

    $groups = [];
    $children_by_group = [];
    foreach ($tb_group_order as $g) {
        $groups[$g] = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
        $children_by_group[$g] = [];
    }

    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $tbd_br_sql . " ORDER BY customer_name ASC"
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
                WHERE customer_name = '$cust_esc' AND status = 1" . $tbd_br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $tbd_br_sql . " AND transaction_type = 'opening'
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
            FROM tbl_customer_ledger WHERE $period_where" . $tbd_br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;
        if (abs($opening_amt) < 0.0000001 && $td < 0.0000001 && $tc < 0.0000001 && abs($closing_amt) < 0.0000001) {
            continue;
        }

        $tb_total_debit += $td;
        $tb_total_credit += $tc;

        $g = tbd_map_ledger_to_group($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if (!isset($groups[$g])) {
            $groups[$g] = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
            $children_by_group[$g] = [];
        }
        $groups[$g]['opening'] += $opening_amt;
        $groups[$g]['debit'] += $td;
        $groups[$g]['credit'] += $tc;

        $children_by_group[$g][] = [
            'particulars' => $customer_name,
            'opening' => tbd_fmt_signed($opening_amt),
            'debit' => number_format($td, 2, '.', ''),
            'credit' => number_format($tc, 2, '.', ''),
            'balance' => tbd_fmt_signed($closing_amt),
        ];
    }

    $sr = 0;
    foreach ($tb_group_order as $g) {
        $o = $groups[$g]['opening'];
        $d = $groups[$g]['debit'];
        $c = $groups[$g]['credit'];
        $cl = $o + $d - $c;
        $sr++;
        $ch = $children_by_group[$g];
        usort($ch, function ($a, $b) {
            return strcasecmp($a['particulars'] ?? '', $b['particulars'] ?? '');
        });
        $tbd_groups[] = [
            'sr' => $sr,
            'particulars' => $g,
            'opening' => tbd_fmt_signed($o),
            'debit' => number_format($d, 2, '.', ''),
            'credit' => number_format($c, 2, '.', ''),
            'balance' => tbd_fmt_signed($cl),
            'children' => $ch,
        ];
    }
} else {
    $tb_ledger_error = 'Ledger table not found. Save transactions to build trial balance.';
}

$tb_total_debit_s = number_format($tb_total_debit, 2, '.', '');
$tb_total_credit_s = number_format($tb_total_credit, 2, '.', '');

$DASHBOARD_PAGE_TITLE = 'Trial Balance Detailed Report';
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
    .tb-toolbar-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    .tb-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }
    .tb-toolbar .form-control.tb-date-range {
        max-width: 260px;
        border: 1px solid rgba(201, 162, 39, 0.45);
        border-radius: 8px;
        font-size: 13px;
    }
    .tb-toolbar .input-group-text { border-color: rgba(201, 162, 39, 0.45) !important; }
    .btn-tb-outline {
        border: 1px solid var(--tb-gold-mid) !important;
        color: var(--tb-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        padding: 0.4rem 1rem;
    }
    .btn-tb-outline:hover { background: #fffbf0 !important; border-color: var(--tb-gold) !important; }
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
    .tb-table-wrap {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .tb-table-wrap table { margin-bottom: 0; font-size: 14px; }
    .tb-table-wrap .table.tb-table-main thead th {
        background: linear-gradient(180deg, var(--tb-navy) 0%, var(--tb-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-color: rgba(255,255,255,.12);
        border-bottom: 2px solid var(--tb-gold-dark) !important;
        white-space: nowrap;
        padding: 12px 14px;
    }
    .tb-table-wrap tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-color: #eef0f3;
    }
    .tb-table-wrap tbody tr.tbd-parent td { background: #fafafa; font-weight: 600; }
    .tb-table-wrap tbody tr.tbd-parent:hover td { background: #fff9ec !important; }
    .tb-table-wrap tbody tr.tbd-child td { background: #ffffff; }
    .tb-table-wrap tbody tr.tbd-child:hover td { background: #fff9ec !important; }
    .tb-table-wrap tbody tr.tbd-child td.tbd-part { padding-left: 2.25rem; font-size: 13px; color: #334155; }
    .tbd-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin-right: 8px;
        border-radius: 50%;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: var(--tb-navy);
        font-size: 10px;
        line-height: 1;
        cursor: pointer;
        vertical-align: middle;
    }
    .tbd-toggle:hover { border-color: var(--tb-gold-mid); color: var(--tb-gold-dark); }
    details.tbd-det { display: inline-block; width: 100%; vertical-align: middle; margin: 0; }
    details.tbd-det > summary {
        list-style: none;
        display: flex;
        align-items: center;
        width: 100%;
    }
    details.tbd-det > summary::-webkit-details-marker { display: none; }
    details.tbd-det:not([open]) .tbd-toggle::before { content: '▶'; }
    details.tbd-det[open] .tbd-toggle::before { content: '▼'; }
    .tb-table-wrap tfoot td {
        font-weight: 700;
        background: #fdf2f7;
        border-top: 2px solid rgba(201, 162, 39, 0.35);
        padding: 12px 14px;
    }
    .tb-num { text-align: right; font-variant-numeric: tabular-nums; }
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
?>
<div class="tb-wrap">
    <div class="tb-toolbar-header">
        <h1 class="tb-page-title mb-0">Trial Balance Detailed Report</h1>
        <div class="tb-toolbar-actions">
            <form method="get" action="trial-balance-detailed-report.php" class="tb-toolbar mb-0">
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                    <input type="text" class="form-control tb-date-range border-start-0" name="date_range" id="tbdDateRange" value="<?php echo htmlspecialchars($display_range); ?>" placeholder="DD-MM-YYYY - DD-MM-YYYY (empty + Apply = all dates)" aria-label="Date range">
                </div>
                <button type="submit" class="btn btn-tb-primary">Apply</button>
                <button type="button" class="btn btn-tb-outline" id="tbdClear">Clear</button>
            </form>
            <details class="tb-export-dd" data-fs-root="#tbdMainColReorder" data-fs-file="trial-balance-detailed" data-fs-title="Trial Balance (Detailed)">
                <summary class="btn btn-tb-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </div>
    </div>

    <?php if ($tb_ledger_error !== ''): ?>
    <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($tb_ledger_error); ?></div>
    <?php endif; ?>

    <div class="tb-table-wrap">
        <div class="table-responsive">
            <table id="tbdMainColReorder" class="table mb-0 tb-table-main acr-col-table">
                <thead>
                    <tr>
                        <th style="width:72px;">Sr. No.</th>
                        <th>Particulars</th>
                        <th class="tb-num">Opening</th>
                        <th class="tb-num">Debit</th>
                        <th class="tb-num">Credit</th>
                        <th class="tb-num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tbd_groups)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4"><?php echo $ledger_table_ok ? 'No ledger balances in this period.' : ''; ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tbd_groups as $g): ?>
                    <tr class="tbd-parent">
                        <td><?php echo (int) $g['sr']; ?></td>
                        <td>
                            <?php if (!empty($g['children'])): ?>
                            <details class="tbd-det" open>
                                <summary>
                                    <span class="tbd-toggle" aria-hidden="true"></span>
                                    <?php echo htmlspecialchars($g['particulars']); ?>
                                </summary>
                            </details>
                            <?php else: ?>
                            <?php echo htmlspecialchars($g['particulars']); ?>
                            <?php endif; ?>
                        </td>
                        <td class="tb-num"><?php echo htmlspecialchars($g['opening']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($g['debit']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($g['credit']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($g['balance']); ?></td>
                    </tr>
                    <?php if (!empty($g['children'])): ?>
                        <?php foreach ($g['children'] as $ch): ?>
                    <tr class="tbd-child">
                        <td></td>
                        <td class="tbd-part"><?php echo htmlspecialchars($ch['particulars']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($ch['opening']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($ch['debit']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($ch['credit']); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($ch['balance']); ?></td>
                    </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td>Total:</td>
                        <td class="tb-num"></td>
                        <td class="tb-num"><?php echo htmlspecialchars($tb_total_debit_s); ?></td>
                        <td class="tb-num"><?php echo htmlspecialchars($tb_total_credit_s); ?></td>
                        <td class="tb-num"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<script>
(function () {
    document.getElementById('tbdClear').addEventListener('click', function () {
        window.location.href = 'trial-balance-detailed-report.php';
    });
    document.querySelectorAll('details.tbd-det').forEach(function (det) {
        det.addEventListener('toggle', function () {
            var sr = det.closest('tr');
            if (!sr || !sr.nextElementSibling) return;
            var show = det.open;
            var row = sr.nextElementSibling;
            while (row && row.classList.contains('tbd-child')) {
                row.style.display = show ? '' : 'none';
                row = row.nextElementSibling;
            }
        });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.AuragoldColReorder) {
        AuragoldColReorder.init('#tbdMainColReorder', { storageKey: 'auragold_colorder_trial_balance_detailed', fixedFirst: true });
    }
});
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
