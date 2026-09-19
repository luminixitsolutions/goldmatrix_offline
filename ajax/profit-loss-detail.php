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

$fs_stk_br = function_exists('auragold_tbl_stock_branch_and_sql') ? auragold_tbl_stock_branch_and_sql($conn, 's') : '';
$fs_stk_br_plain = function_exists('auragold_tbl_stock_branch_and_sql') ? auragold_tbl_stock_branch_and_sql($conn, '') : '';

$ledger_report_base = 'accountledger-report.php';

/** Particulars: product item name with barcode (fallback: description / barcode). */
function pl_closing_stock_particulars_label(array $row) {
    $bc = trim((string) ($row['barcode'] ?? ''));
    $name = trim((string) ($row['product_name'] ?? ''));
    $alt = trim((string) ($row['product_alt'] ?? ''));
    $art = trim((string) ($row['product_article'] ?? ''));
    $desc = trim((string) ($row['line_desc'] ?? ''));

    $item = $name !== '' ? $name : '';
    if ($item === '' && $alt !== '') {
        $item = $alt;
    }
    if ($item === '' && $art !== '') {
        $item = $art;
    }

    if ($item !== '' && $bc !== '') {
        return $item . ' | Barcode: ' . $bc;
    }
    if ($item !== '') {
        return $item;
    }
    if ($desc !== '' && $bc !== '') {
        return $desc . ' | Barcode: ' . $bc;
    }
    if ($desc !== '') {
        return $desc;
    }

    return $bc !== '' ? $bc : '—';
}

function pl_detail_ledger_rows_json(array $raw_rows) {
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

switch ($key) {
    case 'opening_stock':
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Opening Stock',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => [
                ['label' => 'Note', 'value' => 'Opening stock is not read from stock valuation in this report. Post opening stock to ledgers if required.'],
            ],
        ]);
        exit;

    case 'purchase_accounts':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_pnl_bucket($conn, 'purchase', $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Purchase Accounts',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => pl_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'direct_expenses':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_pnl_bucket($conn, 'direct_expense', $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Direct Expenses',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => pl_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'indirect_expenses':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_pnl_bucket($conn, 'indirect_expense', $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Indirect Expenses',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => pl_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'sales_accounts':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_pnl_bucket($conn, 'sales', $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Sales Accounts',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => pl_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'direct_incomes':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_pnl_bucket($conn, 'direct_income', $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Direct Incomes',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => pl_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'indirect_incomes':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_pnl_bucket($conn, 'indirect_income', $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Indirect Incomes',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => pl_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'closing_stock':
        $ts = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if (!$ts || mysqli_num_rows($ts) === 0) {
            if ($ts) {
                mysqli_free_result($ts);
            }
            echo json_encode(['ok' => false, 'message' => 'Stock table not found', 'date_text' => $date_text]);
            exit;
        }
        mysqli_free_result($ts);

        $has_desc = false;
        $hc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'description'");
        if ($hc && mysqli_num_rows($hc) > 0) {
            $has_desc = true;
        }
        if ($hc) {
            mysqli_free_result($hc);
        }
        $has_products_join = false;
        $tpr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_products'");
        $cpid = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'product_id'");
        if ($tpr && mysqli_num_rows($tpr) > 0 && $cpid && mysqli_num_rows($cpid) > 0) {
            $has_products_join = true;
        }
        if ($tpr) {
            mysqli_free_result($tpr);
        }
        if ($cpid) {
            mysqli_free_result($cpid);
        }

        $line_desc_sql = $has_desc
            ? "COALESCE(NULLIF(TRIM(s.description),''), '') AS line_desc"
            : "'' AS line_desc";
        $prod_select = "TRIM(COALESCE(p.name,'')) AS product_name, TRIM(COALESCE(p.article,'')) AS product_article, '' AS product_alt";
        $hca = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_products LIKE 'alternate_name'");
        if ($hca && mysqli_num_rows($hca) > 0) {
            $prod_select = "TRIM(COALESCE(p.name,'')) AS product_name, TRIM(COALESCE(p.article,'')) AS product_article, TRIM(COALESCE(p.alternate_name,'')) AS product_alt";
        }
        if ($hca) {
            mysqli_free_result($hca);
        }

        if ($has_products_join) {
            $sql = "SELECT s.barcode,
                $prod_select,
                $line_desc_sql,
                COALESCE(s.current_weight,0) AS cw, COALESCE(s.current_qty,0) AS cq, COALESCE(s.value,0) AS val
            FROM tbl_stock s
            LEFT JOIN tbl_products p ON p.id = s.product_id
            WHERE s.status = 1
            $fs_stk_br
            AND (IFNULL(s.current_weight,0) > 0.00001 OR IFNULL(s.current_qty,0) > 0.00001)
            ORDER BY COALESCE(NULLIF(p.name,''), s.barcode) ASC, s.barcode ASC";
        } else {
            $sql = "SELECT s.barcode,
                '' AS product_name, '' AS product_alt, '' AS product_article,
                $line_desc_sql,
                COALESCE(s.current_weight,0) AS cw, COALESCE(s.current_qty,0) AS cq, COALESCE(s.value,0) AS val
            FROM tbl_stock s
            WHERE s.status = 1
            $fs_stk_br
            AND (IFNULL(s.current_weight,0) > 0.00001 OR IFNULL(s.current_qty,0) > 0.00001)
            ORDER BY s.barcode ASC";
        }
        $list = getList($sql);
        $stock_rows = [];
        foreach ($list as $row) {
            $stock_rows[] = [
                'name' => pl_closing_stock_particulars_label($row),
                'weight' => number_format((float) ($row['cw'] ?? 0), 3, '.', ''),
                'qty' => number_format((float) ($row['cq'] ?? 0), 2, '.', ''),
                'value' => number_format((float) ($row['val'] ?? 0), 2, '.', ''),
            ];
        }
        echo json_encode([
            'ok' => true,
            'title' => 'Closing Stock',
            'subtitle' => 'Product, barcode, weight, qty, value',
            'date_text' => $date_text,
            'mode' => 'stock',
            'rows' => $stock_rows,
        ]);
        exit;

    case 'pl_trading_total':
        $pnl = fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql);
        $closing_stock = 0.0;
        $tst = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if ($tst && mysqli_num_rows($tst) > 0) {
            $stk = getRecord(
                "SELECT COALESCE(SUM(value), 0) AS v FROM tbl_stock WHERE status = 1
                 AND (IFNULL(current_weight,0) > 0.00001 OR IFNULL(current_qty,0) > 0.00001)" . $fs_stk_br_plain
            );
            if ($stk && isset($stk['v'])) {
                $closing_stock = (float) $stk['v'];
            }
        }
        if ($tst) {
            mysqli_free_result($tst);
        }
        $opening_stock = 0.0;
        $sales_net = (float) ($pnl['sales_net'] ?? 0);
        $purchase_net = (float) ($pnl['purchase_net'] ?? 0);
        $direct_expense_net = (float) ($pnl['direct_expense_net'] ?? 0);
        $direct_income_net = (float) ($pnl['direct_income_net'] ?? 0);
        $total_income = $closing_stock + $sales_net + $direct_income_net;
        $total_trading_expense = $opening_stock + $purchase_net + $direct_expense_net;
        $gross_profit = $total_income - $total_trading_expense;
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        $rows = [
            ['label' => 'Closing stock + Sales + Direct incomes (income side)', 'value' => $fmtn($total_income)],
            ['label' => 'Opening stock + Purchases + Direct expenses (expense side)', 'value' => $fmtn($total_trading_expense)],
            ['label' => 'Gross profit / (loss)', 'value' => $fmtn($gross_profit)],
        ];
        echo json_encode([
            'ok' => true,
            'title' => 'Trading account',
            'subtitle' => 'Total (both columns)',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => $rows,
        ]);
        exit;

    case 'pl_gross_carry':
        $pnl = fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql);
        $closing_stock = 0.0;
        $tst = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if ($tst && mysqli_num_rows($tst) > 0) {
            $stk = getRecord(
                "SELECT COALESCE(SUM(value), 0) AS v FROM tbl_stock WHERE status = 1
                 AND (IFNULL(current_weight,0) > 0.00001 OR IFNULL(current_qty,0) > 0.00001)" . $fs_stk_br_plain
            );
            if ($stk && isset($stk['v'])) {
                $closing_stock = (float) $stk['v'];
            }
        }
        if ($tst) {
            mysqli_free_result($tst);
        }
        $opening_stock = 0.0;
        $sales_net = (float) ($pnl['sales_net'] ?? 0);
        $purchase_net = (float) ($pnl['purchase_net'] ?? 0);
        $direct_expense_net = (float) ($pnl['direct_expense_net'] ?? 0);
        $direct_income_net = (float) ($pnl['direct_income_net'] ?? 0);
        $total_income = $closing_stock + $sales_net + $direct_income_net;
        $total_trading_expense = $opening_stock + $purchase_net + $direct_expense_net;
        $gross_profit = $total_income - $total_trading_expense;
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => $gross_profit >= 0 ? 'Gross profit (carried down)' : 'Gross loss (brought down)',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => [
                ['label' => 'Gross result for period', 'value' => $fmtn(abs($gross_profit))],
            ],
        ]);
        exit;

    case 'pl_net_line':
        $pnl = fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql);
        $closing_stock = 0.0;
        $tst = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if ($tst && mysqli_num_rows($tst) > 0) {
            $stk = getRecord(
                "SELECT COALESCE(SUM(value), 0) AS v FROM tbl_stock WHERE status = 1
                 AND (IFNULL(current_weight,0) > 0.00001 OR IFNULL(current_qty,0) > 0.00001)" . $fs_stk_br_plain
            );
            if ($stk && isset($stk['v'])) {
                $closing_stock = (float) $stk['v'];
            }
        }
        if ($tst) {
            mysqli_free_result($tst);
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
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        echo json_encode([
            'ok' => true,
            'title' => 'Profit and Loss',
            'subtitle' => 'Net profit / net loss',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => [
                ['label' => 'Gross trading result', 'value' => $fmtn($gross_profit)],
                ['label' => 'Plus indirect incomes', 'value' => $fmtn($indirect_income_net)],
                ['label' => 'Less indirect expenses', 'value' => $fmtn($indirect_expense_net)],
                ['label' => 'Net result', 'value' => $fmtn($net_result)],
            ],
        ]);
        exit;

    case 'pl_grand_total':
        $pnl = fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql);
        $closing_stock = 0.0;
        $tst = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if ($tst && mysqli_num_rows($tst) > 0) {
            $stk = getRecord(
                "SELECT COALESCE(SUM(value), 0) AS v FROM tbl_stock WHERE status = 1
                 AND (IFNULL(current_weight,0) > 0.00001 OR IFNULL(current_qty,0) > 0.00001)" . $fs_stk_br_plain
            );
            if ($stk && isset($stk['v'])) {
                $closing_stock = (float) $stk['v'];
            }
        }
        if ($tst) {
            mysqli_free_result($tst);
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
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        $grand_exp_lower = ($gross_profit >= 0 ? $gross_profit : abs($gross_profit)) + $indirect_expense_net;
        $grand_inc_lower = $indirect_income_net + abs($net_result);
        echo json_encode([
            'ok' => true,
            'title' => 'Profit and Loss',
            'subtitle' => 'Grand total',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => [
                ['label' => 'Expense side (gross carry + indirect expenses)', 'value' => $fmtn($grand_exp_lower)],
                ['label' => 'Income side (indirect incomes + net result)', 'value' => $fmtn($grand_inc_lower)],
                ['label' => 'Net result (profit / loss)', 'value' => $fmtn(abs($net_result)) . ($net_is_profit ? ' (profit)' : ' (loss)')],
            ],
        ]);
        exit;

    default:
        echo json_encode(['ok' => false, 'message' => 'Invalid key', 'date_text' => $date_text]);
        exit;
}
