<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/fs_ledger_groups.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

/** Same as trial-balance.php — for period filters on tbl_customer_ledger. */
function auragold_coa_normalize_sql_date($s) {
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

/** Positive = Dr, negative = Cr — same convention as trial balance. */
function auragold_coa_fmt_signed($signed) {
    $signed = (float) $signed;
    if (abs($signed) < 0.0000001) {
        return '0.00';
    }
    $suf = $signed >= 0 ? 'Dr' : 'Cr';

    return number_format(abs($signed), 2, '.', '') . $suf;
}

/**
 * Folder path (under Primary) for a ledger name — matches Chart of Account labels (Tally-style).
 * Uses tbl_customers.sundry_debtors_id like trial-balance.php (1=debtors, 2=creditors, 29=bank).
 *
 * @param array<string,int> $customer_sundry_map
 * @param array<string,int> $customer_sundry_lower
 * @return list<string>
 */
function auragold_coa_ledger_folders(string $name, array $customer_sundry_map, array $customer_sundry_lower): array {
    $n = trim($name);
    if ($n === '') {
        return ['Current Assets', 'Sundry Debtors'];
    }
    $lower = strtolower($n);

    if ($lower === 'profit and loss opening' || strcasecmp($n, 'Profit and Loss Opening') === 0) {
        return ['Profit and Loss Opening'];
    }
    if ($lower === 'profit and loss' || strcasecmp($n, 'Profit and Loss') === 0) {
        return ['Profit and Loss'];
    }

    if ($n === 'Sales Account' || $n === 'Making Sale Account' || $n === 'Making Sales Account') {
        return ['Sales Account'];
    }
    if ($n === 'Purchase Account' || $n === 'Making Purchase Account') {
        return ['Purchase Account'];
    }

    if (stripos($lower, 'direct income') !== false) {
        return ['Direct Income'];
    }
    if (stripos($lower, 'indirect income') !== false) {
        return ['Indirect Income'];
    }
    if (stripos($lower, 'direct expense') !== false || stripos($lower, 'direct exp') !== false) {
        return ['Direct Expenses'];
    }
    if (stripos($lower, 'expense') !== false || $lower === 'expenses' || $lower === 'indirect expenses') {
        return ['Indirect Expenses'];
    }

    if (stripos($lower, 'inter branch') !== false || stripos($lower, 'branch account') !== false) {
        return ['Branch /Divisions'];
    }
    if (stripos($lower, 'suspense') !== false) {
        return ['Suspense A/C'];
    }
    if (stripos($lower, 'misc') !== false && stripos($lower, 'asset') !== false) {
        return ['Misc.Expenses (ASSET)'];
    }

    if (stripos($lower, 'fixed asset') !== false || $lower === 'fixed assets') {
        return ['Fixed Assets'];
    }
    if (stripos($lower, 'investment') !== false && stripos($lower, 'loan') === false) {
        return ['Investments'];
    }

    if (
        $lower === 'capital'
        || stripos($lower, 'reserve') !== false
        || stripos($lower, 'surplus') !== false
        || stripos($lower, 'share capital') !== false
    ) {
        if (stripos($lower, 'reserve') !== false || stripos($lower, 'surplus') !== false) {
            return ['Capital Account', 'Reservers &Surplus'];
        }

        return ['Capital Account'];
    }

    if (
        preg_match('/\b(loan|loans|od|o\\.d\\.|overdraft)\\b/i', $n)
        || stripos($n, 'Secured') !== false
        || stripos($n, 'Unsecured') !== false
        || stripos($lower, 'bank od') !== false
    ) {
        return ['Loans (Liability)'];
    }

    if (
        $n === 'Tax Ledger'
        || stripos($lower, 'gst') !== false
        || stripos($lower, 'tds') !== false
        || (stripos($lower, 'tax') !== false && stripos($lower, 'sundry') === false)
        || (stripos($lower, 'duty') !== false && stripos($lower, 'duties') === false)
    ) {
        return ['Current Liabilities', 'Duties& Taxes'];
    }

    if (stripos($lower, 'provision') !== false) {
        return ['Current Liabilities', 'Provisions'];
    }

    if (
        stripos($lower, 'advance against fund') !== false
        || (stripos($lower, 'advance') !== false && stripos($lower, 'fund') !== false && stripos($lower, 'installment') !== false)
    ) {
        return ['Current Liabilities', 'Advance Against Fund Installment'];
    }

    $sid = null;
    if (isset($customer_sundry_map[$n])) {
        $sid = (int) $customer_sundry_map[$n];
    } elseif (isset($customer_sundry_lower[strtolower($n)])) {
        $sid = (int) $customer_sundry_lower[strtolower($n)];
    }

    if ($n === 'Sundry Creditors' || $sid === 2) {
        return ['Current Liabilities', 'Sundry Creditors'];
    }

    if ($sid === 29) {
        return ['Current Assets', 'Bank Accounts'];
    }

    if ($n === 'Cash') {
        return ['Current Assets', 'Cash-in-Hand'];
    }
    if ($n === 'Bank Account') {
        return ['Current Assets', 'Bank Accounts'];
    }
    if (in_array($n, ['Hedging Account', 'Discount Received', 'Manufacturing Account'], true)) {
        return ['Current Assets', 'Other Current Assets'];
    }

    if ($n === 'Opening Stock' || (stripos($lower, 'stock') !== false && stripos($lower, 'journal') === false)) {
        return ['Current Assets', 'Stock-in-Hand'];
    }
    if (stripos($lower, 'deposit') !== false && stripos($lower, 'advance') === false) {
        return ['Current Assets', 'Deposits(Assets)'];
    }
    if (
        stripos($lower, 'advance') !== false
        && stripos($lower, 'liabilit') === false
        && stripos($lower, 'fund') === false
    ) {
        return ['Current Assets', 'Loans & Advances(Asset)'];
    }

    if ($n === 'Sundry Debtors' || $sid === 1) {
        return ['Current Assets', 'Sundry Debtors'];
    }

    return ['Current Assets', 'Sundry Debtors'];
}

/**
 * @param list<array{label:string,children:array}|string> $children
 * @param array<string,int> $orderRank
 */
function auragold_coa_sort_level(array $children, array $orderRank): array {
    usort($children, function ($a, $b) use ($orderRank) {
        $la = is_string($a) ? $a : (string) ($a['label'] ?? '');
        $lb = is_string($b) ? $b : (string) ($b['label'] ?? '');
        $ra = $orderRank[$la] ?? 500;
        $rb = $orderRank[$lb] ?? 500;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strcasecmp($la, $lb);
    });
    foreach ($children as &$node) {
        if (is_array($node) && isset($node['children']) && is_array($node['children'])) {
            $node['children'] = auragold_coa_sort_level($node['children'], $orderRank);
        }
    }
    unset($node);

    return $children;
}

/**
 * @param list<array{label:string,children:array}|string> $roots
 */
function auragold_coa_merge_leaf(array &$roots, array $folders, string $leaf): void {
    if ($folders === []) {
        $roots[] = $leaf;

        return;
    }
    $head = $folders[0];
    $rest = array_slice($folders, 1);
    $idx = null;
    foreach ($roots as $i => $node) {
        if (is_array($node) && isset($node['label']) && $node['label'] === $head) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        $roots[] = ['label' => $head, 'children' => []];
        $idx = count($roots) - 1;
    }
    if (!isset($roots[$idx]['children']) || !is_array($roots[$idx]['children'])) {
        $roots[$idx]['children'] = [];
    }
    auragold_coa_merge_leaf($roots[$idx]['children'], $rest, $leaf);
}

/**
 * Full Chart of Accounts group tree (JewelSteps-style labels). Shown even when empty — amounts roll up as 0.00.
 * Ledgers from tbl_customer_ledger are merged into these folders by auragold_coa_ledger_folders().
 *
 * @return list<array{label:string,children:array}>
 */
function auragold_coa_skeleton_children(): array {
    return [
        [
            'label' => 'Capital Account',
            'children' => [
                ['label' => 'Reservers &Surplus', 'children' => []],
            ],
        ],
        ['label' => 'Loans (Liability)', 'children' => []],
        [
            'label' => 'Current Liabilities',
            'children' => [
                ['label' => 'Duties& Taxes', 'children' => []],
                ['label' => 'Provisions', 'children' => []],
                ['label' => 'Sundry Creditors', 'children' => []],
                ['label' => 'Advance Against Fund Installment', 'children' => []],
            ],
        ],
        ['label' => 'Fixed Assets', 'children' => []],
        ['label' => 'Investments', 'children' => []],
        [
            'label' => 'Current Assets',
            'children' => [
                ['label' => 'Stock-in-Hand', 'children' => []],
                ['label' => 'Deposits(Assets)', 'children' => []],
                ['label' => 'Loans & Advances(Asset)', 'children' => []],
                ['label' => 'Sundry Debtors', 'children' => []],
                ['label' => 'Cash-in-Hand', 'children' => []],
                ['label' => 'Bank Accounts', 'children' => []],
                ['label' => 'Other Current Assets', 'children' => []],
            ],
        ],
        ['label' => 'Branch /Divisions', 'children' => []],
        ['label' => 'Misc.Expenses (ASSET)', 'children' => []],
        ['label' => 'Suspense A/C', 'children' => []],
        ['label' => 'Sales Account', 'children' => []],
        ['label' => 'Purchase Account', 'children' => []],
        ['label' => 'Direct Income', 'children' => []],
        ['label' => 'Direct Expenses', 'children' => []],
        ['label' => 'Indirect Income', 'children' => []],
        ['label' => 'Indirect Expenses', 'children' => []],
        ['label' => 'Profit and Loss', 'children' => []],
        ['label' => 'Profit and Loss Opening', 'children' => []],
    ];
}

$coa_global_order = [
    'Capital Account' => 10,
    'Reservers &Surplus' => 11,
    'Loans (Liability)' => 20,
    'Current Liabilities' => 30,
    'Duties& Taxes' => 31,
    'Provisions' => 32,
    'Sundry Creditors' => 33,
    'Advance Against Fund Installment' => 34,
    'Fixed Assets' => 40,
    'Investments' => 50,
    'Current Assets' => 60,
    'Stock-in-Hand' => 61,
    'Deposits(Assets)' => 62,
    'Loans & Advances(Asset)' => 63,
    'Sundry Debtors' => 64,
    'Cash-in-Hand' => 65,
    'Bank Accounts' => 66,
    'Other Current Assets' => 67,
    'Branch /Divisions' => 68,
    'Misc.Expenses (ASSET)' => 69,
    'Suspense A/C' => 70,
    'Sales Account' => 71,
    'Purchase Account' => 72,
    'Direct Income' => 73,
    'Direct Expenses' => 74,
    'Indirect Income' => 75,
    'Indirect Expenses' => 76,
    'Profit and Loss' => 100,
    'Profit and Loss Opening' => 110,
];

$coa_tree = [];
$coa_ledger_error = '';
$coa_ledger_amounts = [];
$coa_total_debit = 0.0;
$coa_total_credit = 0.0;

$coa_hidden = ['Purchase Fixing Account'];
$coa_hidden_sql = '';
if (!empty($coa_hidden) && isset($conn)) {
    $h = [];
    foreach ($coa_hidden as $hn) {
        $h[] = "'" . mysqli_real_escape_string($conn, $hn) . "'";
    }
    $coa_hidden_sql = ' AND customer_name NOT IN (' . implode(',', $h) . ')';
}

$coa_br_sql = isset($conn) && $conn instanceof mysqli ? fs_customer_ledger_branch_and_sql($conn) : '';

$ledger_table_ok = false;
if (isset($conn)) {
    $tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
    if ($tchk && mysqli_num_rows($tchk) > 0) {
        $ledger_table_ok = true;
    }
    if ($tchk) {
        mysqli_free_result($tchk);
    }
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
    $from_date = auragold_coa_normalize_sql_date(date('Y-m-d', strtotime($fyStart)));
    $to_date = auragold_coa_normalize_sql_date(date('Y-m-d', strtotime($fyEnd)));
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
        $from_date = auragold_coa_normalize_sql_date($parts[0]);
        $to_date = auragold_coa_normalize_sql_date($parts[1]);
        if ($from_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $from_date = '';
        }
        if ($to_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $to_date = '';
        }
    }
    $display_range = $date_range_get;
}

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
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1 AND TRIM(IFNULL(customer_name,'')) != ''"
        . $coa_hidden_sql
        . $coa_br_sql
        . ' ORDER BY customer_name ASC'
    );

    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? (string) $nr['customer_name'] : '';
        if (trim($customer_name) === '') {
            continue;
        }
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $coa_br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $coa_br_sql . " AND transaction_type = 'opening'
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
            FROM tbl_customer_ledger WHERE $period_where" . $coa_br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;

        $coa_ledger_amounts[$customer_name] = [
            'o' => $opening_amt,
            'd' => $td,
            'c' => $tc,
            'cl' => $closing_amt,
        ];
        $coa_total_debit += $td;
        $coa_total_credit += $tc;
    }

    $primary_children = auragold_coa_skeleton_children();
    foreach ($names_list as $nr) {
        $ledger_name = trim((string) ($nr['customer_name'] ?? ''));
        if ($ledger_name === '') {
            continue;
        }
        $folders = auragold_coa_ledger_folders($ledger_name, $customer_sundry_map, $customer_sundry_lower);
        auragold_coa_merge_leaf($primary_children, $folders, $ledger_name);
    }

    $primary_children = auragold_coa_sort_level($primary_children, $coa_global_order);

    $coa_tree = [
        [
            'label' => 'Primary',
            'highlight' => true,
            'children' => $primary_children,
        ],
    ];
} else {
    $coa_ledger_error = 'Ledger data not found. Chart of accounts will appear after transactions create ledger entries.';
    $coa_tree = [
        [
            'label' => 'Primary',
            'highlight' => true,
            'children' => auragold_coa_skeleton_children(),
        ],
    ];
}

/**
 * Attach opening / movement / closing from tbl_customer_ledger and roll up group totals.
 *
 * @param mixed $node
 * @param array<string,array{o:float,d:float,c:float,cl:float}> $amounts
 * @return array{leaf:bool,label:string,o:float,d:float,c:float,cl:float,highlight?:bool,children?:array}
 */
function auragold_coa_enrich_node($node, array $amounts): array {
    if (is_string($node)) {
        $a = $amounts[$node] ?? ['o' => 0.0, 'd' => 0.0, 'c' => 0.0, 'cl' => 0.0];

        return [
            'leaf' => true,
            'label' => $node,
            'o' => (float) $a['o'],
            'd' => (float) $a['d'],
            'c' => (float) $a['c'],
            'cl' => (float) $a['cl'],
        ];
    }
    if (!is_array($node)) {
        return ['leaf' => true, 'label' => '', 'o' => 0.0, 'd' => 0.0, 'c' => 0.0, 'cl' => 0.0];
    }
    $children = [];
    $to = 0.0;
    $td = 0.0;
    $tc = 0.0;
    $tcl = 0.0;
    foreach ($node['children'] ?? [] as $c) {
        $ec = auragold_coa_enrich_node($c, $amounts);
        $children[] = $ec;
        $to += $ec['o'];
        $td += $ec['d'];
        $tc += $ec['c'];
        $tcl += $ec['cl'];
    }

    return [
        'leaf' => false,
        'label' => (string) ($node['label'] ?? ''),
        'highlight' => !empty($node['highlight']),
        'children' => $children,
        'o' => $to,
        'd' => $td,
        'c' => $tc,
        'cl' => $tcl,
    ];
}

$coa_tree_enriched = [];
foreach ($coa_tree as $root) {
    $coa_tree_enriched[] = auragold_coa_enrich_node($root, $coa_ledger_amounts);
}
$coa_tree = $coa_tree_enriched;

/**
 * @param array{leaf:bool,label:string,o:float,d:float,c:float,cl:float,highlight?:bool,children?:array} $node
 */
function auragold_coa_render_amount_cells(array $node): void {
    $o = $node['o'] ?? 0.0;
    $d = $node['d'] ?? 0.0;
    $c = $node['c'] ?? 0.0;
    $cl = $node['cl'] ?? 0.0;
    echo '<div class="coa-nums">';
    echo '<span class="coa-num" data-coa-col="o">' . htmlspecialchars(auragold_coa_fmt_signed($o)) . '</span>';
    echo '<span class="coa-num" data-coa-col="d">' . htmlspecialchars(number_format($d, 2, '.', '')) . '</span>';
    echo '<span class="coa-num" data-coa-col="c">' . htmlspecialchars(number_format($c, 2, '.', '')) . '</span>';
    echo '<span class="coa-num" data-coa-col="cl">' . htmlspecialchars(auragold_coa_fmt_signed($cl)) . '</span>';
    echo '</div>';
}

/**
 * @param array{leaf:bool,label:string,o:float,d:float,c:float,cl:float,highlight?:bool,children?:array} $node
 */
function auragold_coa_render_node(array $node, int $level): void {
    $label = (string) ($node['label'] ?? '');
    $highlight = !empty($node['highlight']);
    $children = $node['children'] ?? [];
    $isLeaf = !empty($node['leaf']);

    if ($isLeaf) {
        $pad = 14 + $level * 18;
        $cls = 'coa-row coa-leaf' . ($highlight ? ' coa-highlight' : '');
        echo '<div class="' . $cls . '">';
        echo '<span class="coa-name" style="padding-left:' . (int) $pad . 'px">' . htmlspecialchars($label) . '</span>';
        auragold_coa_render_amount_cells($node);
        echo '</div>';

        return;
    }

    $sumPad = 18 + $level * 18;
    $sumCls = 'coa-row coa-summary' . ($highlight ? ' coa-highlight' : '');
    echo '<details class="coa-details" open><summary class="' . $sumCls . '">';
    echo '<span class="coa-name" style="padding-left:' . (int) $sumPad . 'px">' . htmlspecialchars($label) . '</span>';
    auragold_coa_render_amount_cells($node);
    echo '</summary><div class="coa-inner">';
    foreach ($children as $c) {
        if (is_array($c)) {
            auragold_coa_render_node($c, $level + 1);
        }
    }
    echo '</div></details>';
}

$DASHBOARD_PAGE_TITLE = 'Chart Of Account';
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
    .btn-tb-icon {
        border: 1px solid rgba(201, 162, 39, 0.45) !important;
        color: var(--tb-gold-dark) !important;
        background: #fff !important;
        border-radius: 8px;
        padding: 0.35rem 0.5rem;
        line-height: 1;
    }
    .btn-tb-icon:hover { background: #fffbf0 !important; }
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
    .coa-panel {
        background: #fff;
        border-radius: 12px;
        border: 1px solid rgba(201, 162, 39, 0.25);
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(17, 41, 75, 0.08);
    }
    .coa-panel .coa-head {
        background: linear-gradient(180deg, var(--tb-navy) 0%, var(--tb-navy-deep) 100%);
        font-weight: 700;
        color: #ffffff !important;
        border-bottom: 2px solid var(--tb-gold-dark);
        padding: 12px 14px;
        font-size: 14px;
    }
    .coa-head-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .coa-head-row .coa-h-name {
        flex: 1;
        min-width: 0;
    }
    .coa-head-row .coa-h-nums {
        display: grid;
        grid-template-columns: 100px 100px 100px 110px;
        flex-shrink: 0;
        text-align: right;
        font-weight: 700;
        font-size: 13px;
        gap: 0;
    }
    .coa-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px 10px 0;
        min-height: 42px;
        border-bottom: 1px solid #eef0f3;
    }
    .coa-row .coa-name {
        flex: 1;
        min-width: 0;
    }
    .coa-nums {
        display: grid;
        grid-template-columns: 100px 100px 100px 110px;
        flex-shrink: 0;
        gap: 0;
    }
    .coa-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-size: 13px;
        color: #334155;
    }
    .coa-scroll {
        max-height: min(72vh, 720px);
        overflow-y: auto;
        font-size: 14px;
        color: #1e293b;
    }
    .coa-scroll::-webkit-scrollbar { width: 8px; }
    .coa-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .coa-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%);
        border-radius: 4px;
    }
    .coa-body { padding: 0; }
    .coa-details { border-bottom: 1px solid #eef0f3; }
    .coa-details > summary {
        list-style: none;
        cursor: pointer;
        user-select: none;
        min-height: 42px;
    }
    .coa-details > summary::-webkit-details-marker { display: none; }
    .coa-summary.coa-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .coa-summary .coa-name {
        position: relative;
    }
    .coa-summary .coa-name::before {
        content: '';
        position: absolute;
        left: 4px;
        top: 50%;
        margin-top: -3px;
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 6px solid #2563eb;
        transition: transform 0.15s ease;
    }
    .coa-details:not([open]) > .coa-summary .coa-name::before {
        transform: rotate(-90deg);
    }
    .coa-inner { padding-bottom: 4px; }
    .coa-inner > .coa-details:last-child { border-bottom: none; }
    .coa-leaf {
        border-bottom: 1px solid #eef0f3;
    }
    .coa-leaf:last-child { border-bottom: 1px solid #eef0f3; }
    .coa-foot {
        border-top: 2px solid rgba(201, 162, 39, 0.35);
        background: #fdf2f7;
        padding: 12px 14px 12px 0;
        margin-top: 0;
    }
    .coa-highlight {
        background: linear-gradient(90deg, #eef2ff 0%, #f8fafc 100%) !important;
    }
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
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h1 class="tb-page-title mb-0">Chart Of Account</h1>
        <form method="get" action="chart-of-account.php" class="tb-toolbar d-flex flex-wrap align-items-center gap-2 mb-0">
            <div class="input-group input-group-sm" style="width: auto;">
                <span class="input-group-text bg-white border-end-0"><i class="feather icon-calendar" style="color:#a67c1a;"></i></span>
                <input type="text" class="form-control tb-date-range border-start-0" name="date_range" id="coaDateRange" value="<?php echo htmlspecialchars($display_range); ?>" placeholder="DD-MM-YYYY - DD-MM-YYYY" aria-label="Date range" title="Leave empty and Apply for all dates">
            </div>
            <button type="submit" class="btn btn-tb-primary">Apply</button>
            <button type="button" class="btn btn-tb-icon" id="coaRefresh" title="Reset to current financial year" aria-label="Reset date range"><i class="feather icon-refresh-cw"></i></button>
            <button type="button" class="btn btn-tb-outline" id="coaClear">Clear</button>
            <details class="tb-export-dd" data-fs-mode="chart-of-account" data-fs-root=".coa-panel" data-fs-file="chart-of-account" data-fs-title="Chart of Account">
                <summary class="btn btn-tb-primary">Export <i class="feather icon-chevron-down" style="font-size:14px;vertical-align:middle;"></i></summary>
                <div class="tb-export-menu">
                    <a href="#" class="fs-export-xls">Excel</a>
                    <a href="#" class="fs-export-pdf">PDF</a>
                </div>
            </details>
        </form>
    </div>

    <?php if ($coa_ledger_error !== ''): ?>
    <div class="alert alert-warning py-2 mb-3"><?php echo htmlspecialchars($coa_ledger_error); ?></div>
    <?php endif; ?>

    <div class="coa-panel">
        <div class="coa-head coa-head-row">
            <div class="coa-h-name">Account Group</div>
            <div class="coa-h-nums">
                <span data-coa-col="o">Opening</span>
                <span data-coa-col="d">Debit</span>
                <span data-coa-col="c">Credit</span>
                <span data-coa-col="cl">Balance</span>
            </div>
        </div>
        <div class="coa-scroll coa-body">
            <?php
            foreach ($coa_tree as $root) {
                auragold_coa_render_node($root, 0);
            }
            ?>
        </div>
        <?php if ($ledger_table_ok): ?>
        <div class="coa-foot coa-row">
            <span class="coa-name" style="font-weight:700;">Total</span>
            <div class="coa-nums" style="font-weight:700;">
                <span class="coa-num" data-coa-col="o"></span>
                <span class="coa-num" data-coa-col="d"><?php echo htmlspecialchars(number_format($coa_total_debit, 2, '.', '')); ?></span>
                <span class="coa-num" data-coa-col="c"><?php echo htmlspecialchars(number_format($coa_total_credit, 2, '.', '')); ?></span>
                <span class="coa-num" data-coa-col="cl"></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var inp = document.getElementById('coaDateRange');
    var def = <?php echo json_encode($default_range); ?>;
    function resetRange() {
        if (inp) inp.value = def;
    }
    document.getElementById('coaRefresh').addEventListener('click', resetRange);
    document.getElementById('coaClear').addEventListener('click', function () {
        window.location.href = 'chart-of-account.php';
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-coa-col-reorder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.AuragoldCoaColReorder) {
        AuragoldCoaColReorder.init({ storageKey: 'auragold_colorder_chart_of_account', headerSelector: '.coa-h-nums', fixedFirst: true });
    }
});
</script>
<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
