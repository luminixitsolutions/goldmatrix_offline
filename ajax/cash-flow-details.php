<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/fs_ledger_groups.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$cf_allowed_buckets = [
    'Capital Account' => true,
    'Loans (Liability)' => true,
    'Current Assets' => true,
    'Branch / Divisions' => true,
    'Suspense A/C' => true,
    'Sales Account' => true,
    'Direct Income' => true,
    'Indirect Income' => true,
    'Current Liabilities' => true,
    'Fixed Assets' => true,
    'Investments' => true,
    'Misc. Expenses (ASSET)' => true,
    'Purchase Account' => true,
    'Direct Expenses' => true,
    'Indirect Expenses' => true,
];

$bucket = isset($_GET['bucket']) ? trim((string) $_GET['bucket']) : '';
if ($bucket === '' || !isset($cf_allowed_buckets[$bucket])) {
    echo json_encode(['ok' => false, 'message' => 'Invalid group', 'date_text' => '']);
    exit;
}

$date_range_get = isset($_GET['date_range']) ? trim((string) $_GET['date_range']) : '';
$from_date = '';
$to_date = '';
if ($date_range_get !== '') {
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

$tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
$ledger_ok = $tchk && mysqli_num_rows($tchk) > 0;
if ($tchk) {
    mysqli_free_result($tchk);
}

$date_text = $date_range_get !== '' ? $date_range_get : 'All dates';
$ledger_report_base = 'accountledger-report.php';

if (!$ledger_ok) {
    echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
    exit;
}

$raw = fs_list_ledgers_for_cash_flow_bucket($conn, $bucket, $from_date, $to_date, $tb_hidden_sql);
$rows = [];
foreach ($raw as $r) {
    $name = $r['name'];
    $esc = rawurlencode($name);
    $rows[] = [
        'name' => $name,
        'opening' => fs_balance_fmt_signed($r['opening']),
        'debit' => number_format((float) $r['debit'], 2, '.', ''),
        'credit' => number_format((float) $r['credit'], 2, '.', ''),
        'balance' => fs_balance_fmt_signed($r['closing']),
        'ledger_url' => $ledger_report_base . '?ledger_name=' . $esc,
    ];
}

echo json_encode([
    'ok' => true,
    'title' => 'Account Groups',
    'subtitle' => $bucket,
    'date_text' => $date_text,
    'mode' => 'ledger',
    'rows' => $rows,
]);
