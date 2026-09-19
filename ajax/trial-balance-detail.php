<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/fs_ledger_groups.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
$date_range_get = isset($_GET['date_range']) ? trim((string) $_GET['date_range']) : '';
$date_from_get = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$date_to_get = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';

$from_date = '';
$to_date = '';
if ($date_from_get !== '' && $date_to_get !== '') {
    $from_date = fs_normalize_sql_date($date_from_get);
    $to_date = fs_normalize_sql_date($date_to_get);
    if ($from_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
        $from_date = '';
    }
    if ($to_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        $to_date = '';
    }
    if ($from_date === '' || $to_date === '') {
        $from_date = '';
        $to_date = '';
    }
}
if ($from_date === '' && $to_date === '' && $date_range_get !== '') {
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

$ledger_report_base = 'accountledger-report.php';

function tb_detail_ledger_rows_json(array $raw_rows) {
    global $ledger_report_base;
    $out = [];
    foreach ($raw_rows as $r) {
        $name = $r['name'];
        $esc = rawurlencode($name);
        $out[] = [
            'name' => $name,
            'opening' => fs_balance_fmt_signed($r['opening']),
            'debit' => number_format((float) $r['debit'], 2, '.', ''),
            'credit' => number_format((float) $r['credit'], 2, '.', ''),
            'balance' => fs_balance_fmt_signed($r['closing']),
            'ledger_url' => $ledger_report_base . '?ledger_name=' . $esc,
        ];
    }
    return $out;
}

$tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
$ledger_ok = $tchk && mysqli_num_rows($tchk) > 0;
if ($tchk) {
    mysqli_free_result($tchk);
}

$date_text = 'All dates';
if ($from_date !== '' && $to_date !== '') {
    $t1 = strtotime($from_date);
    $t2 = strtotime($to_date);
    if ($t1 !== false && $t2 !== false) {
        $date_text = date('d-m-Y', $t1) . ' - ' . date('d-m-Y', $t2);
    }
} elseif ($date_range_get !== '') {
    $date_text = $date_range_get;
}

$group_by_key = [
    'current_liabilities' => ['label' => 'Current Liabilities', 'groups' => ['Current Liabilities']],
    'current_assets' => ['label' => 'Current Assets', 'groups' => ['Current Assets']],
    'sales_account' => ['label' => 'Sales Account', 'groups' => ['Sales Account']],
    'purchase_account' => ['label' => 'Purchase Account', 'groups' => ['Purchase Account']],
    'indirect_expenses' => ['label' => 'Indirect Expenses', 'groups' => ['Indirect Expenses']],
    'profit_and_loss' => ['label' => 'Profit and Loss', 'groups' => ['Profit and Loss']],
    'profit_loss_opening' => ['label' => 'Profit and Loss Opening', 'groups' => ['Profit and Loss Opening']],
];

if (!isset($group_by_key[$key])) {
    echo json_encode(['ok' => false, 'message' => 'Unknown group', 'date_text' => $date_text]);
    exit;
}

if (!$ledger_ok) {
    echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
    exit;
}

$meta = $group_by_key[$key];
$raw = fs_list_ledgers_for_tb_groups($conn, $meta['groups'], $from_date, $to_date, $tb_hidden_sql);

echo json_encode([
    'ok' => true,
    'title' => 'Trial Balance — Ledgers',
    'subtitle' => $meta['label'],
    'date_text' => $date_text,
    'mode' => 'ledger',
    'rows' => tb_detail_ledger_rows_json($raw),
]);
