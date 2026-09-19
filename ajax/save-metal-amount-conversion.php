<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/ensure_metal_amount_conversion.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$direction = strtolower(trim((string)($_POST['direction'] ?? '')));
if (!in_array($direction, ['metal_to_amount', 'amount_to_metal'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid direction']);
    exit;
}

$customer_id = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
$customer_name = isset($_POST['customer_name']) ? esc($_POST['customer_name']) : '';
$metal_type = strtolower(trim((string)($_POST['metal_type'] ?? 'gold')));
$rate = isset($_POST['rate']) ? (float) $_POST['rate'] : 0.0;
$trans_date = isset($_POST['trans_date']) ? trim((string) $_POST['trans_date']) : '';
$trans_date = str_replace('T', ' ', $trans_date);
if ($trans_date === '') {
    $trans_date = date('Y-m-d H:i:s');
}
$trans_date = esc($trans_date);
$comment = isset($_POST['comment']) ? esc($_POST['comment']) : '';

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$product_characteristic_id = isset($_POST['product_characteristic_id']) ? (int) $_POST['product_characteristic_id'] : 0;
$against_item_label = '';
if ($product_id > 0) {
    $pr = getRecord('SELECT name, alternate_name, article FROM tbl_products WHERE id = ' . (int) $product_id . ' LIMIT 1');
    if ($pr) {
        $against_item_label = trim((string) ($pr['name'] ?? ''));
        if ($against_item_label === '' && !empty($pr['alternate_name'])) {
            $against_item_label = trim((string) $pr['alternate_name']);
        }
        if (!empty($pr['article'])) {
            $against_item_label = trim($against_item_label . ' · ' . (string) $pr['article']);
        }
        if (strlen($against_item_label) > 255) {
            $against_item_label = substr($against_item_label, 0, 255);
        }
    }
    if ($product_characteristic_id > 0) {
        $pc = getRecord('SELECT id FROM tbl_product_characteristics WHERE id = ' . (int) $product_characteristic_id . ' AND product_id = ' . (int) $product_id . ' LIMIT 1');
        if (!$pc) {
            $product_characteristic_id = 0;
        }
    }
} else {
    $product_characteristic_id = 0;
}

$metal_in = isset($_POST['metal_weight']) ? (float) $_POST['metal_weight'] : 0.0; // amount_to_metal: from amount path
$amount_in = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;           // metal_to_amount: from amount path

$allowed = ['gold', 'silver', 'diamond', 'platinum'];
if (!in_array($metal_type, $allowed, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Select a valid metal (gold, silver, diamond, or platinum) for balance posting.']);
    exit;
}

if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Select a customer']);
    exit;
}
$cust = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . (int) $customer_id . ' AND status = 1 LIMIT 1');
if (!$cust) {
    echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
    exit;
}
$customer_name = esc($cust['name'] ?? $customer_name);

$eff_branch = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($eff_branch <= 0 && !empty($_SESSION['branch_id'])) {
    $eff_branch = (int) $_SESSION['branch_id'];
}

auragold_ensure_metal_amount_conversion_table($conn);
if (function_exists('auragold_ensure_mac_against_item_columns')) {
    auragold_ensure_mac_against_item_columns($conn);
}
if (in_array($metal_type, ['diamond', 'platinum', 'gold', 'silver'], true)) {
    auragold_ensure_ledger_diamond_columns($conn);
    auragold_ensure_ledger_platinum_columns($conn);
}
auragold_ensure_customer_ledger_branch_column($conn);

$ledger_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
$ledger_scope = function_exists('auragold_customer_ledger_branch_scope_sql') ? auragold_customer_ledger_branch_scope_sql($conn, $eff_branch) : '';

$use_gold_pure = false;
$gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
if ($gpc && mysqli_num_rows($gpc) > 0) {
    $use_gold_pure = true;
}
if ($gpc) {
    mysqli_free_result($gpc);
}

$has_against   = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'against_ledger');
$ledger_has_d  = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_diamond');
$ledger_has_p  = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_platinum');

if ($rate <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Rate must be greater than zero']);
    exit;
}

$weight = 0.0;
$amount = 0.0;
if ($direction === 'metal_to_amount') {
    $weight = $metal_in;
    if ($weight <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Enter metal weight to convert']);
        exit;
    }
    $amount = $weight * $rate;
} else {
    $amount = $amount_in;
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Enter amount to convert']);
        exit;
    }
    $weight = $amount / $rate;
}

$weight = round($weight, 4);
$amount = round($amount, 2);

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : null);

$prev_cols = 'balance_amount, balance_gold, balance_silver';
if ($use_gold_pure) {
    $prev_cols .= ', balance_gold_pure';
}
if ($ledger_has_d) {
    $prev_cols .= ', balance_diamond';
}
if ($ledger_has_p) {
    $prev_cols .= ', balance_platinum';
}

$last = getRecord("
    SELECT $prev_cols
    FROM tbl_customer_ledger
    WHERE status = 1 AND customer_id = " . (int) $customer_id . "
    $ledger_scope
    ORDER BY id DESC
    LIMIT 1
");
if (!$last) {
    $last = getRecord("
        SELECT $prev_cols
        FROM tbl_customer_ledger
        WHERE status = 1 AND LOWER(TRIM(customer_name)) = LOWER('" . mysqli_real_escape_string($conn, $customer_name) . "')
        $ledger_scope
        ORDER BY id DESC
        LIMIT 1
    ");
}
$prev_row = is_array($last) ? $last : [];
$prev_amt = (float) ($prev_row['balance_amount'] ?? 0);
$prev_g = (float) ($prev_row['balance_gold'] ?? 0);
$prev_s = (float) ($prev_row['balance_silver'] ?? 0);
$prev_gp = $use_gold_pure ? (float) ($prev_row['balance_gold_pure'] ?? 0) : 0.0;
$prev_d = $ledger_has_d ? (float) ($prev_row['balance_diamond'] ?? 0) : 0.0;
$prev_p = $ledger_has_p ? (float) ($prev_row['balance_platinum'] ?? 0) : 0.0;

$eps_amt = 0.01;
$eps_metal = 0.0001;
/**
 * Signed running balance: negative = customer has credit (metal/amount in their favour in this app).
 * Available to use for MTA/ATM = abs(negative) or positive balance (legacy “positive = has” books).
 */
$macAvail = function (float $signed): float {
    if ($signed < 0) {
        return (float) (-$signed);
    }
    return $signed;
};

if ($direction === 'metal_to_amount') {
    if ($metal_type === 'gold') {
        if ($macAvail($prev_g) < $weight - $eps_metal) {
            echo json_encode(['status' => 'error', 'code' => 'insufficient_metal', 'message' => 'Insufficient gold balance for this conversion. Add metal to the account or reduce the weight.']);
            exit;
        }
        if ($use_gold_pure && $macAvail($prev_gp) < $weight - $eps_metal) {
            echo json_encode(['status' => 'error', 'code' => 'insufficient_metal', 'message' => 'Insufficient gold (pure) balance for this conversion.']);
            exit;
        }
    } elseif ($metal_type === 'silver' && $macAvail($prev_s) < $weight - $eps_metal) {
        echo json_encode(['status' => 'error', 'code' => 'insufficient_metal', 'message' => 'Insufficient silver balance for this conversion. Add metal to the account or reduce the weight.']);
        exit;
    } elseif ($metal_type === 'diamond' && $ledger_has_d && $macAvail($prev_d) < $weight - $eps_metal) {
        echo json_encode(['status' => 'error', 'code' => 'insufficient_metal', 'message' => 'Insufficient diamond balance for this conversion. Add carat/weight to the account or reduce the amount.']);
        exit;
    } elseif ($metal_type === 'platinum' && $ledger_has_p && $macAvail($prev_p) < $weight - $eps_metal) {
        echo json_encode(['status' => 'error', 'code' => 'insufficient_metal', 'message' => 'Insufficient platinum balance for this conversion. Add metal to the account or reduce the weight.']);
        exit;
    }
} else {
    if ($macAvail($prev_amt) < $amount - $eps_amt) {
        echo json_encode(['status' => 'error', 'code' => 'insufficient_amount', 'message' => 'Insufficient amount balance. The customer’s amount balance is not enough to buy metal.']);
        exit;
    }
}

$new_amt = $prev_amt;
$new_g = $prev_g;
$new_s = $prev_s;
$new_gp = $prev_gp;
$new_d = $prev_d;
$new_p = $prev_p;

$debit_g = 0.0;
$credit_g = 0.0;
$debit_gp = 0.0;
$credit_gp = 0.0;
$debit_s = 0.0;
$credit_s = 0.0;
$debit_d = 0.0;
$credit_d = 0.0;
$debit_p = 0.0;
$credit_p = 0.0;
$debit_amt = 0.0;
$credit_amt = 0.0;
$trans_label = 'Metal to Amount';
$trans_type = 'metal_to_amount';

if ($direction === 'metal_to_amount') {
    // Metal leaves customer; rupee value is credited to the customer’s *amount* balance in this system
    // (account ledger running CL uses +debit - credit, same as other hedging: increase amount = debit).
    if ($metal_type === 'gold') {
        $debit_g = $weight;
        $debit_gp = $weight;
        $new_g = $prev_g - $debit_g;
        $new_gp = $use_gold_pure ? ($prev_gp - $debit_gp) : $new_g;
    } elseif ($metal_type === 'silver') {
        $debit_s = $weight;
        $new_s = $prev_s - $debit_s;
    } elseif ($metal_type === 'platinum') {
        if (!$ledger_has_p) {
            echo json_encode(['status' => 'error', 'message' => 'Ledger platinum columns are missing. Open the app once to apply schema, or contact support.']);
            exit;
        }
        $debit_p = $weight;
        $new_p = $prev_p - $debit_p;
    } elseif ($metal_type === 'diamond') {
        if (!$ledger_has_d) {
            echo json_encode(['status' => 'error', 'message' => 'Ledger diamond columns are missing. Run the app update or contact support.']);
            exit;
        }
        $debit_d = $weight;
        $new_d = $prev_d - $debit_d;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unsupported metal type.']);
        exit;
    }
    $debit_amt = $amount;
    $credit_amt = 0.0;
    $new_amt = $prev_amt + $amount;
} else {
    $trans_label = 'Amount to Metal';
    $trans_type = 'amount_to_metal';
    // Like Payment Voucher: give metal, credit (money) reduces as we pay in metal
    if ($metal_type === 'gold') {
        $credit_g = $weight;
        $credit_gp = $weight;
        $new_g = $prev_g + $credit_g;
        $new_gp = $use_gold_pure ? ($prev_gp + $credit_gp) : $new_g;
    } elseif ($metal_type === 'silver') {
        $credit_s = $weight;
        $new_s = $prev_s + $credit_s;
    } elseif ($metal_type === 'platinum') {
        if (!$ledger_has_p) {
            echo json_encode(['status' => 'error', 'message' => 'Ledger platinum columns are missing.']);
            exit;
        }
        $credit_p = $weight;
        $new_p = $prev_p + $credit_p;
    } elseif ($metal_type === 'diamond') {
        if (!$ledger_has_d) {
            echo json_encode(['status' => 'error', 'message' => 'Ledger diamond columns are missing.']);
            exit;
        }
        $credit_d = $weight;
        $new_d = $prev_d + $credit_d;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unsupported metal type.']);
        exit;
    }
    $credit_amt = $amount;
    $new_amt = $prev_amt - $amount;
}

$td = $trans_date;
if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $trans_date)) {
    $td = $trans_date . ' ' . date('H:i:s');
}

$desc = $trans_label . " (Hedging): " . strtoupper($metal_type) . ' ' . $weight;
if ($against_item_label !== '') {
    $desc .= ' | Against: ' . $against_item_label;
}
if ($comment !== '') {
    $desc .= ' | ' . $comment;
}
$desc_esc = mysqli_real_escape_string($conn, $desc);
$cust_esc = mysqli_real_escape_string($conn, $customer_name);
$ref_sql = 'NULL';

$against_part = $has_against ? ', against_ledger, against_invoice_no' : '';
$against_val = '';

$has_product_cols = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'product_id');
$product_sql = $has_product_cols ? ', product_id, product_characteristic_id, against_item_label' : '';
$product_vals = $has_product_cols
    ? ', ' . ($product_id > 0 ? (int) $product_id : 'NULL')
      . ', ' . ($product_characteristic_id > 0 ? (int) $product_characteristic_id : 'NULL')
      . ', ' . ($against_item_label !== '' ? "'" . mysqli_real_escape_string($conn, $against_item_label) . "'" : 'NULL')
    : '';

mysqli_begin_transaction($conn);
try {
    $branch_sql = ($ledger_has_branch && $eff_branch > 0) ? ', branch_id' : '';
    $branch_val = ($ledger_has_branch && $eff_branch > 0) ? ', ' . (int) $eff_branch : ($ledger_has_branch ? ', NULL' : '');

    $ins = "
        INSERT INTO tbl_metal_amount_conversions
        (branch_id, customer_id, customer_name, direction, metal_type, metal_weight, rate, amount, trans_date, trans_no, comment" . $product_sql . ", status, created_by, created_at)
        VALUES (
            " . ($eff_branch > 0 ? (int) $eff_branch : 'NULL') . ",
            " . (int) $customer_id . ",
            '" . $cust_esc . "',
            '" . mysqli_real_escape_string($conn, $direction) . "',
            '" . mysqli_real_escape_string($conn, $metal_type) . "',
            " . (float) $weight . ",
            " . (float) $rate . ",
            " . (float) $amount . ",
            '" . esc($td) . "',
            NULL,
            " . ($comment !== '' ? "'" . mysqli_real_escape_string($conn, $comment) . "'" : 'NULL') . $product_vals . ",
            1,
            " . ($user_id ? (int) $user_id : 'NULL') . ",
            NOW()
        )
    ";
    if (!mysqli_query($conn, $ins)) {
        throw new Exception(mysqli_error($conn));
    }
    $new_id = (int) mysqli_insert_id($conn);
    $p = $direction === 'metal_to_amount' ? 'MTA' : 'ATM';
    $tno = $p . '-' . $new_id;
    mysqli_query($conn, "UPDATE tbl_metal_amount_conversions SET trans_no = '" . esc($tno) . "' WHERE id = " . (int) $new_id);

    if ($has_against) {
        $wfmt = number_format((float) $weight, 4, '.', '');
        $against_ledger_txt = $trans_label . ' — ' . strtoupper($metal_type) . ' ' . $wfmt
            . ' @ ' . number_format((float) $rate, 2, '.', '') . ' = ' . number_format((float) $amount, 2, '.', '');
        if (strlen($against_ledger_txt) > 255) {
            $against_ledger_txt = $trans_label . ' · ' . $tno;
        }
        $ag_le = mysqli_real_escape_string($conn, $against_ledger_txt);
        $ag_in = mysqli_real_escape_string($conn, $tno);
        $against_val = ", '" . $ag_le . "', '" . $ag_in . "'";
    }

    if ($use_gold_pure) {
        $d_sql  = $ledger_has_d ? ', debit_diamond, credit_diamond' : '';
        $p_sql  = $ledger_has_p ? ', debit_platinum, credit_platinum' : '';
        $bd_sql = $ledger_has_d ? ', balance_diamond' : '';
        $bp_sql = $ledger_has_p ? ', balance_platinum' : '';
        $d_mval = $ledger_has_d ? (float) $debit_d . ', ' . (float) $credit_d : '';
        $p_mval = $ledger_has_p ? (float) $debit_p . ', ' . (float) $credit_p : '';
        $d_bval = $ledger_has_d ? (float) $new_d : '';
        $p_bval = $ledger_has_p ? (float) $new_p : '';
        $sql_dp = $ledger_has_d ? ', ' . $d_mval : '';
        $sql_pp = $ledger_has_p ? ', ' . $p_mval : '';
        $sql_bd = $ledger_has_d ? ', ' . $d_bval : '';
        $sql_bp = $ledger_has_p ? ', ' . $p_bval : '';
        $q = "
            INSERT INTO tbl_customer_ledger (
                customer_id" . $branch_sql . ", customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver" . $d_sql . $p_sql . ",
                balance_amount, balance_gold, balance_silver, balance_gold_pure" . $bd_sql . $bp_sql . ",
                description, reference_no, status, created_by, created_at" . $against_part . "
            ) VALUES (
                " . (int) $customer_id . $branch_val . ",
                '" . $cust_esc . "',
                '" . esc($trans_type) . "',
                " . (int) $new_id . ",
                '" . esc($tno) . "',
                '" . esc(substr($td, 0, 10)) . "',
                " . (float) $debit_amt . ",
                " . (float) $credit_amt . ",
                " . (float) $debit_g . ",
                " . (float) $credit_g . ",
                " . (float) $debit_gp . ",
                " . (float) $credit_gp . ",
                " . (float) $debit_s . ",
                " . (float) $credit_s . $sql_dp . $sql_pp . ",
                " . (float) $new_amt . ",
                " . (float) $new_g . ",
                " . (float) $new_s . ",
                " . (float) $new_gp . $sql_bd . $sql_bp . ",
                '" . $desc_esc . "',
                " . $ref_sql . ",
                1,
                " . ($user_id ? (int) $user_id : 'NULL') . ",
                NOW()
                " . $against_val . "
            )
        ";
    } elseif ($ledger_has_d || $ledger_has_p) {
        $d_sql  = $ledger_has_d ? ', debit_diamond, credit_diamond' : '';
        $p_sql  = $ledger_has_p ? ', debit_platinum, credit_platinum' : '';
        $bd_sql = $ledger_has_d ? ', balance_diamond' : '';
        $bp_sql = $ledger_has_p ? ', balance_platinum' : '';
        $sql_dp = $ledger_has_d ? ', ' . (float) $debit_d . ', ' . (float) $credit_d : '';
        $sql_pp = $ledger_has_p ? ', ' . (float) $debit_p . ', ' . (float) $credit_p : '';
        $sql_bd = $ledger_has_d ? ', ' . (float) $new_d : '';
        $sql_bp = $ledger_has_p ? ', ' . (float) $new_p : '';
        $q = "
            INSERT INTO tbl_customer_ledger (
                customer_id" . $branch_sql . ", customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, debit_silver, credit_silver" . $d_sql . $p_sql . ",
                balance_amount, balance_gold, balance_silver" . $bd_sql . $bp_sql . ",
                description, reference_no, status, created_by, created_at" . $against_part . "
            ) VALUES (
                " . (int) $customer_id . $branch_val . ",
                '" . $cust_esc . "',
                '" . esc($trans_type) . "',
                " . (int) $new_id . ",
                '" . esc($tno) . "',
                '" . esc(substr($td, 0, 10)) . "',
                " . (float) $debit_amt . ",
                " . (float) $credit_amt . ",
                " . (float) $debit_g . ",
                " . (float) $credit_g . ",
                " . (float) $debit_s . ",
                " . (float) $credit_s . $sql_dp . $sql_pp . ",
                " . (float) $new_amt . ",
                " . (float) $new_g . ",
                " . (float) $new_s . $sql_bd . $sql_bp . ",
                '" . $desc_esc . "',
                " . $ref_sql . ",
                1,
                " . ($user_id ? (int) $user_id : 'NULL') . ",
                NOW()
                " . $against_val . "
            )
        ";
    } else {
        $q = "
            INSERT INTO tbl_customer_ledger (
                customer_id" . $branch_sql . ", customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, debit_silver, credit_silver,
                balance_amount, balance_gold, balance_silver,
                description, reference_no, status, created_by, created_at" . $against_part . "
            ) VALUES (
                " . (int) $customer_id . $branch_val . ",
                '" . $cust_esc . "',
                '" . esc($trans_type) . "',
                " . (int) $new_id . ",
                '" . esc($tno) . "',
                '" . esc(substr($td, 0, 10)) . "',
                " . (float) $debit_amt . ",
                " . (float) $credit_amt . ",
                " . (float) $debit_g . ",
                " . (float) $credit_g . ",
                " . (float) $debit_s . ",
                " . (float) $credit_s . ",
                " . (float) $new_amt . ",
                " . (float) $new_g . ",
                " . (float) $new_s . ",
                '" . $desc_esc . "',
                " . $ref_sql . ",
                1,
                " . ($user_id ? (int) $user_id : 'NULL') . ",
                NOW()
                " . $against_val . "
            )
        ";
    }

    if (!mysqli_query($conn, $q)) {
        throw new Exception('Ledger: ' . mysqli_error($conn));
    }
    mysqli_commit($conn);
    echo json_encode([
        'status'  => 'success',
        'message' => 'Saved ' . $tno,
        'id'      => $new_id,
        'trans_no' => $tno,
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
