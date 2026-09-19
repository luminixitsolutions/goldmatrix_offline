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

$fs_stk_br = function_exists('auragold_tbl_stock_branch_and_sql') ? auragold_tbl_stock_branch_and_sql($conn, 's') : '';
$fs_stk_br_plain = function_exists('auragold_tbl_stock_branch_and_sql') ? auragold_tbl_stock_branch_and_sql($conn, '') : '';

$ledger_report_base = 'accountledger-report.php';

function bs_closing_stock_particulars_label(array $row) {
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

function bs_detail_ledger_rows_json(array $raw_rows) {
    global $conn, $ledger_report_base;
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

$date_text = $date_range_get !== '' ? $date_range_get : 'All dates';

switch ($key) {
    case 'current_liabilities':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_tb_groups($conn, ['Current Liabilities'], $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Current Liabilities',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => bs_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'current_assets':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_tb_groups($conn, ['Current Assets'], $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Current Assets',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => bs_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'profit_loss_opening':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $raw = fs_list_ledgers_for_tb_groups($conn, ['Profit and Loss Opening'], $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Profit And Loss (Opening)',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => bs_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'profit_loss_current':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $groups = ['Sales Account', 'Purchase Account', 'Indirect Expenses', 'Profit and Loss'];
        $raw = fs_list_ledgers_for_tb_groups($conn, $groups, $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Current Period (P&L ledgers)',
            'date_text' => $date_text,
            'mode' => 'ledger',
            'rows' => bs_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'profit_loss_account':
        if (!$ledger_ok) {
            echo json_encode(['ok' => false, 'message' => 'Ledger not available', 'date_text' => $date_text]);
            exit;
        }
        $openRows = fs_list_ledgers_for_tb_groups($conn, ['Profit and Loss Opening'], $from_date, $to_date, $tb_hidden_sql);
        $curGroups = ['Sales Account', 'Purchase Account', 'Indirect Expenses', 'Profit and Loss'];
        $curRows = fs_list_ledgers_for_tb_groups($conn, $curGroups, $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Account Groups',
            'subtitle' => 'Profit And Loss Account',
            'date_text' => $date_text,
            'mode' => 'sections',
            'sections' => [
                [
                    'label' => 'Profit And Loss (Opening)',
                    'rows' => bs_detail_ledger_rows_json($openRows),
                ],
                [
                    'label' => 'Current Period',
                    'rows' => bs_detail_ledger_rows_json($curRows),
                ],
            ],
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
                '' AS product_name, '' AS product_article, '' AS product_alt,
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
                'name' => bs_closing_stock_particulars_label($row),
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

    case 'difference':
        $computed = fs_compute_ledger_groups($conn, $from_date, $to_date, $tb_hidden_sql);
        $groups = $computed['groups'];
        $C = static function ($k) use ($groups) {
            return isset($groups[$k]['closing']) ? (float) $groups[$k]['closing'] : 0.0;
        };
        $liab_current_liabilities = -1 * $C('Current Liabilities');
        $pl_opening_display = -1 * $C('Profit and Loss Opening');
        $pl_current_display = -1 * (
            $C('Sales Account') + $C('Purchase Account') + $C('Indirect Expenses') + $C('Profit and Loss')
        );
        $profit_loss_parent = $pl_opening_display + $pl_current_display;
        $total_balance_sheet = $liab_current_liabilities;
        $asset_current_assets = $C('Current Assets');
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
        $asset_difference = $total_balance_sheet - $asset_current_assets - $closing_stock - $profit_loss_parent;
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        echo json_encode([
            'ok' => true,
            'title' => 'Difference',
            'subtitle' => 'Balancing figure (same formula as balance sheet)',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => [
                ['label' => 'Total (Current Liabilities column)', 'value' => $fmtn($total_balance_sheet)],
                ['label' => 'Less: Current Assets', 'value' => $fmtn($asset_current_assets)],
                ['label' => 'Less: Closing Stock', 'value' => $fmtn($closing_stock)],
                ['label' => 'Less: Profit And Loss Account', 'value' => $fmtn($profit_loss_parent)],
                ['label' => 'Difference', 'value' => $fmtn($asset_difference)],
            ],
        ]);
        exit;

    case 'total_liability':
        $computed = fs_compute_ledger_groups($conn, $from_date, $to_date, $tb_hidden_sql);
        if (!$computed['ok']) {
            echo json_encode(['ok' => false, 'message' => 'Ledger data not available', 'date_text' => $date_text]);
            exit;
        }
        $groups = $computed['groups'];
        $C = static function ($k) use ($groups) {
            return isset($groups[$k]['closing']) ? (float) $groups[$k]['closing'] : 0.0;
        };
        $total_balance_sheet = -1 * $C('Current Liabilities');
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        $explain_rows = [
            ['label' => 'This total equals Current Liabilities (liability column)', 'value' => $fmtn($total_balance_sheet)],
        ];
        if (!$ledger_ok) {
            echo json_encode([
                'ok' => true,
                'title' => 'Liability — Total',
                'subtitle' => 'Summary',
                'date_text' => $date_text,
                'mode' => 'explain',
                'rows' => $explain_rows,
            ]);
            exit;
        }
        $raw = fs_list_ledgers_for_tb_groups($conn, ['Current Liabilities'], $from_date, $to_date, $tb_hidden_sql);
        echo json_encode([
            'ok' => true,
            'title' => 'Liability — Total',
            'subtitle' => 'Summary and ledger breakdown',
            'date_text' => $date_text,
            'mode' => 'explain_then_ledger',
            'explain_rows' => $explain_rows,
            'ledger_caption' => 'Ledgers — Current Liabilities',
            'rows' => bs_detail_ledger_rows_json($raw),
        ]);
        exit;

    case 'total_assets':
        $computed = fs_compute_ledger_groups($conn, $from_date, $to_date, $tb_hidden_sql);
        if (!$computed['ok']) {
            echo json_encode(['ok' => false, 'message' => 'Ledger data not available', 'date_text' => $date_text]);
            exit;
        }
        $groups = $computed['groups'];
        $C = static function ($k) use ($groups) {
            return isset($groups[$k]['closing']) ? (float) $groups[$k]['closing'] : 0.0;
        };
        $liab_current_liabilities = -1 * $C('Current Liabilities');
        $pl_opening_display = -1 * $C('Profit and Loss Opening');
        $pl_current_display = -1 * (
            $C('Sales Account') + $C('Purchase Account') + $C('Indirect Expenses') + $C('Profit and Loss')
        );
        $profit_loss_parent = $pl_opening_display + $pl_current_display;
        $total_balance_sheet = $liab_current_liabilities;
        $asset_current_assets = $C('Current Assets');
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
        $asset_difference = $total_balance_sheet - $asset_current_assets - $closing_stock - $profit_loss_parent;
        $total_assets = $asset_current_assets + $closing_stock + $profit_loss_parent + $asset_difference;
        $fmtn = static function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        echo json_encode([
            'ok' => true,
            'title' => 'Assets — Total',
            'subtitle' => 'How this column total is built',
            'date_text' => $date_text,
            'mode' => 'explain',
            'rows' => [
                ['label' => 'Current Assets', 'value' => $fmtn($asset_current_assets)],
                ['label' => 'Closing Stock', 'value' => $fmtn($closing_stock)],
                ['label' => 'Profit And Loss Account', 'value' => $fmtn($profit_loss_parent)],
                ['label' => 'Difference', 'value' => $fmtn($asset_difference)],
                ['label' => 'Total (Assets)', 'value' => $fmtn($total_assets)],
            ],
        ]);
        exit;

    default:
        echo json_encode(['ok' => false, 'message' => 'Invalid key', 'date_text' => $date_text]);
        exit;
}
