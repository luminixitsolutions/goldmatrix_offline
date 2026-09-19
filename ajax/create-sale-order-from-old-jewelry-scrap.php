<?php
/**
 * Creates a draft tbl_sale_orders + tbl_sale_order_items from selected Old Jewellery scrap invoice lines
 * so jobwork-order.php?sale_order_id= can open for refinery / manufacturing.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_oj_scrap_sale_order_bridge.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$scrap_invoice_id = isset($_POST['scrap_invoice_id']) ? (int) $_POST['scrap_invoice_id'] : 0;
$raw_ids = isset($_POST['scrap_item_ids']) ? trim((string) $_POST['scrap_item_ids']) : '';

if ($scrap_invoice_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid scrap invoice']);
    exit;
}

$ids = [];
if ($raw_ids !== '') {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw_ids)))));
}
if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Select at least one row']);
    exit;
}

$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
if (!$t || mysqli_num_rows($t) === 0) {
    if ($t) {
        mysqli_free_result($t);
    }
    echo json_encode(['status' => 'error', 'message' => 'Scrap tables not found']);
    exit;
}
mysqli_free_result($t);

$inv = getRecord('SELECT * FROM tbl_old_jewelry_scrap_invoices WHERE id = ' . $scrap_invoice_id . ' LIMIT 1');
if (!$inv) {
    echo json_encode(['status' => 'error', 'message' => 'Scrap invoice not found']);
    exit;
}

$inv_no_trim = trim((string) ($inv['invoice_no'] ?? ''));
if ($inv_no_trim !== '') {
    $tjwo_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
    if ($tjwo_chk && mysqli_num_rows($tjwo_chk) > 0) {
        mysqli_free_result($tjwo_chk);
        $marker = 'Job work / refinery from Old Jewellery scrap';
        $needle_esc = mysqli_real_escape_string($conn, $marker);
        $against_esc = mysqli_real_escape_string($conn, $inv_no_trim);
        $dup = getRecord(
            'SELECT j.id FROM tbl_jobwork_orders j '
            . 'INNER JOIN tbl_sale_orders so ON so.id = j.sale_order_id '
            . "WHERE so.comment LIKE '%{$needle_esc}%' AND TRIM(IFNULL(so.against_of,'')) = '{$against_esc}' LIMIT 1"
        );
        if ($dup && !empty($dup['id'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'A refinery job work order already exists for invoice ' . $inv_no_trim,
            ]);
            exit;
        }
    } elseif ($tjwo_chk) {
        mysqli_free_result($tjwo_chk);
    }
}

$id_list = implode(',', $ids);
$items = getList(
    'SELECT * FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = ' . $scrap_invoice_id
    . ' AND id IN (' . $id_list . ') AND IFNULL(status,1) = 1'
);
if (!is_array($items) || count($items) !== count($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'One or more lines are missing or do not belong to this invoice']);
    exit;
}

$ph = getRecord('SELECT id FROM tbl_products WHERE status = 1 ORDER BY id ASC LIMIT 1');
$product_id = (int) ($ph['id'] ?? 0);
if ($product_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'No active product in master — add a product first']);
    exit;
}
$ch = getRecord('SELECT id FROM tbl_product_characteristics WHERE product_id = ' . $product_id . ' AND status = 1 ORDER BY id ASC LIMIT 1');
$characteristic_id = ($ch && !empty($ch['id'])) ? (int) $ch['id'] : null;

$oi_stone_weight = false;
$oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_items LIKE 'stone_weight'");
if ($oc && mysqli_num_rows($oc) > 0) {
    $oi_stone_weight = true;
}
if ($oc) {
    mysqli_free_result($oc);
}

$order_no = function_exists('getNextSaleOrderNo') ? getNextSaleOrderNo($conn) : ('SO-SCRAP-' . $scrap_invoice_id . '-' . time());
$customer_id = isset($inv['customer_id']) ? (int) $inv['customer_id'] : 0;
$customer_name = mysqli_real_escape_string($conn, (string) ($inv['customer_name'] ?? ''));
$against_of = mysqli_real_escape_string($conn, (string) ($inv['invoice_no'] ?? ''));
$currency = mysqli_real_escape_string($conn, (string) ($inv['currency'] ?? 'INR'));
$ref_no_raw = (string) ($inv['ref_no'] ?? '');
$ref_no = $ref_no_raw !== '' ? "'" . mysqli_real_escape_string($conn, $ref_no_raw) . "'" : 'NULL';
$sales_person = isset($inv['sales_person']) && (string) $inv['sales_person'] !== ''
    ? "'" . mysqli_real_escape_string($conn, (string) $inv['sales_person']) . "'" : 'NULL';
$order_date = !empty($inv['invoice_date']) ? mysqli_real_escape_string($conn, (string) $inv['invoice_date']) : date('Y-m-d');
$due_date = !empty($inv['due_date']) ? "'" . mysqli_real_escape_string($conn, (string) $inv['due_date']) . "'" : 'NULL';
$fixing_type = mysqli_real_escape_string($conn, (string) ($inv['fixing_type'] ?? 'Standard'));
$item_ids_note = implode(',', $ids);
$comment = mysqli_real_escape_string(
    $conn,
    'Job work / refinery from Old Jewellery scrap ' . ($inv['invoice_no'] ?? '') . ' — lines: ' . $item_ids_note
);

$user_id = isset($_SESSION['Admin']) ? (int) $_SESSION['Admin'] : 0;

$oj_payments = getList(
    'SELECT * FROM tbl_old_jewelry_scrap_invoice_payments WHERE invoice_id = ' . (int) $scrap_invoice_id . ' AND IFNULL(status,1) = 1 ORDER BY id ASC'
);
if (!is_array($oj_payments)) {
    $oj_payments = [];
}

$has_scrap_metal_payment = false;
$oj_pay_sum = 0.0;
foreach ($oj_payments as $op) {
    $oj_pay_sum += (float) ($op['amount'] ?? 0);
    if ((float) ($op['amount'] ?? 0) <= 0) {
        continue;
    }
    if (auragold_oj_scrap_payment_row_is_scrap_metal($op)) {
        $has_scrap_metal_payment = true;
    }
}

$sum_net = 0.0;
foreach ($items as $it) {
    $sum_net += (float) ($it['net_amt'] ?? $it['amount'] ?? 0);
}

if ($has_scrap_metal_payment) {
    $subtotal = (float) ($inv['subtotal'] ?? 0);
    if ($subtotal <= 0) {
        $subtotal = (float) ($inv['net_total'] ?? 0);
    }
    if ($subtotal <= 0) {
        $subtotal = (float) ($inv['grand_total'] ?? 0);
    }
    if ($subtotal <= 0 && $oj_pay_sum > 0) {
        $subtotal = $oj_pay_sum;
    }
    if ($subtotal <= 0) {
        $subtotal = (float) $sum_net;
    }
    $grand_total = (float) ($inv['grand_total'] ?? 0);
    if ($grand_total <= 0) {
        $grand_total = (float) ($inv['net_total'] ?? $subtotal);
    }
    if ($grand_total <= 0) {
        $grand_total = $subtotal;
    }
} else {
    $subtotal = (float) $sum_net;
    $grand_total = $subtotal;
}

mysqli_begin_transaction($conn);

try {
    $sql_so = "
        INSERT INTO tbl_sale_orders (
            order_no, customer_id, customer_name, against_of, currency, ref_no, sales_person,
            order_date, due_date, layaways_id, fixing_type,
            previous_balance, previous_gold, previous_silver,
            subtotal, additional_amt, net_total, reward_points, coupon_code, coupon_discount,
            discount_amt, redeem_points, grand_total, advance_payment, metal_amt, round_off, paid_amt, balance_amt,
            group_name, comment, status, created_by, created_at
        ) VALUES (
            '" . mysqli_real_escape_string($conn, $order_no) . "',
            " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
            '$customer_name',
            '$against_of',
            '$currency',
            $ref_no,
            $sales_person,
            '$order_date',
            $due_date,
            NULL,
            '$fixing_type',
            0, 0, 0,
            $subtotal, 0, $subtotal, 0, NULL, 0,
            0, 0, $grand_total, 0, 0, 0, 0, $grand_total,
            NULL,
            '$comment',
            'draft',
            " . ($user_id > 0 ? $user_id : 'NULL') . ",
            NOW()
        )
    ";
    if (!mysqli_query($conn, $sql_so)) {
        throw new Exception('Sale order insert failed: ' . mysqli_error($conn));
    }
    $order_id = (int) mysqli_insert_id($conn);
    if ($order_id < 1) {
        throw new Exception('Sale order ID missing');
    }

    if ($has_scrap_metal_payment && count($items) === 1) {
        $w_modal = auragold_oj_scrap_sum_modal_weights_from_ojb_payments($conn, $scrap_invoice_id);
        if ($w_modal !== null) {
            $it0 = &$items[0];
            auragold_oj_scrap_apply_modal_weights_to_ojb_item_shape_line($it0, $w_modal);
        }
    }

    foreach ($items as $it) {
        $desc = (string) ($it['description'] ?? 'Scrap refinery');
        $product_name = mysqli_real_escape_string($conn, $desc !== '' ? $desc : 'Scrap refinery');
        $barcode = !empty($it['barcode']) ? "'" . mysqli_real_escape_string($conn, (string) $it['barcode']) . "'" : 'NULL';
        $qty = (float) ($it['quantity'] ?? 1);
        $gross = (float) ($it['gross_wt'] ?? 0);
        $less = (float) ($it['less_wt'] ?? 0);
        $net = (float) ($it['net_wt'] ?? 0);
        $final = (float) ($it['final_wt'] ?? $net);
        $pure = (float) ($it['pure_wt'] ?? 0);
        $purity = (float) ($it['purity'] ?? 0);
        $purity_wt = ($net > 0 && $purity > 0) ? ($purity > 1 ? $net * ($purity / 100) : $net * $purity) : $pure;
        $rate = (float) ($it['rate'] ?? 0);
        $making = (float) ($it['making'] ?? 0);
        $tax = (float) ($it['tax'] ?? 0);
        $amount = (float) ($it['amount'] ?? 0);
        $net_amt = (float) ($it['net_amt'] ?? $amount);
        $net_amt_tax = $net_amt + $tax;
        $diamond_wt = (float) ($it['diamond_wt'] ?? 0);
        $gemstone_wt = (float) ($it['gemstone_wt'] ?? 0);
        $stone_wt = $diamond_wt + $gemstone_wt;

        $cols = 'order_id, product_id, product_characteristic_id, barcode, product_name, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, design_no, status, created_at';
        $cid_sql = $characteristic_id !== null ? (string) (int) $characteristic_id : 'NULL';
        $vals = $order_id . ', ' . $product_id . ', ' . $cid_sql . ', ' . $barcode . ", '" . $product_name . "', NULL, "
            . $qty . ', ' . $gross . ', ' . $less . ', ' . $purity . ', ' . $purity_wt . ', ' . $final . ', ' . $net . ', ' . $pure . ', '
            . $rate . ', ' . $making . ', ' . $amount . ', ' . $tax . ', ' . $net_amt . ', ' . $net_amt_tax . ", NULL, 1, NOW()";

        if ($oi_stone_weight) {
            $cols .= ', stone_weight';
            $vals .= ', ' . $stone_wt;
        }

        $item_sql = 'INSERT INTO tbl_sale_order_items (' . $cols . ') VALUES (' . $vals . ')';
        if (!mysqli_query($conn, $item_sql)) {
            throw new Exception('Sale order line insert failed: ' . mysqli_error($conn));
        }
    }

    if ($has_scrap_metal_payment) {
        $sop_pd_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'payment_details'");
        $sop_has_pd = ($sop_pd_chk && mysqli_num_rows($sop_pd_chk) > 0);
        if ($sop_pd_chk) {
            mysqli_free_result($sop_pd_chk);
        }
        if (!$sop_has_pd) {
            @mysqli_query($conn, "ALTER TABLE tbl_sale_order_payments ADD COLUMN payment_details TEXT NULL COMMENT 'JSON: scrap modal fields, etc.'");
            $sop_pd_chk2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'payment_details'");
            $sop_has_pd = ($sop_pd_chk2 && mysqli_num_rows($sop_pd_chk2) > 0);
            if ($sop_pd_chk2) {
                mysqli_free_result($sop_pd_chk2);
            }
        }
        $sop_prev = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'previous_balance_amount'");
        $sop_has_prev = ($sop_prev && mysqli_num_rows($sop_prev) > 0);
        if ($sop_prev) {
            mysqli_free_result($sop_prev);
        }

        foreach ($oj_payments as $op) {
            $amt = (float) ($op['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            $pt_raw = trim((string) ($op['payment_type'] ?? ''));
            $dep_raw_pay = strtolower(trim((string) ($op['deposit_into'] ?? '')));
            if ($pt_raw === '') {
                $pt_raw = ($dep_raw_pay === 'scrap') ? 'Scrap' : 'Cash';
            }
            $pt_esc = mysqli_real_escape_string($conn, $pt_raw);
            $dep_raw = trim((string) ($op['deposit_into'] ?? ''));
            $dep_sql = $dep_raw !== '' ? "'" . mysqli_real_escape_string($conn, $dep_raw) . "'" : 'NULL';
            $tn_raw = trim((string) ($op['transaction_no'] ?? ''));
            $tn_sql = $tn_raw !== '' ? "'" . mysqli_real_escape_string($conn, $tn_raw) . "'" : 'NULL';
            $chq = $op['cheque_date'] ?? null;
            $chq_sql = (!empty($chq) && (string) $chq !== '') ? "'" . mysqli_real_escape_string($conn, (string) $chq) . "'" : 'NULL';
            $pur_raw = trim((string) ($op['purity_carat'] ?? ''));
            $pur_sql = $pur_raw !== '' ? "'" . mysqli_real_escape_string($conn, $pur_raw) . "'" : 'NULL';
            $dia_raw = trim((string) ($op['diamond_category'] ?? ''));
            $dia_sql = $dia_raw !== '' ? "'" . mysqli_real_escape_string($conn, $dia_raw) . "'" : 'NULL';
            $qty_pay = (float) ($op['quantity'] ?? 0);
            $pd_raw = isset($op['payment_details']) ? (string) $op['payment_details'] : '';
            $pd_sql = ($sop_has_pd && $pd_raw !== '') ? ("'" . mysqli_real_escape_string($conn, $pd_raw) . "'") : 'NULL';

            if ($sop_has_pd && $sop_has_prev) {
                $pay_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity, payment_details, status, created_at) VALUES ("
                    . (int) $order_id . ", '$pt_esc', $dep_sql, $tn_sql, $chq_sql, $pur_sql, $amt, 0, $dia_sql, $qty_pay, $pd_sql, 1, NOW())";
            } elseif ($sop_has_pd) {
                $pay_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, diamond_category, quantity, payment_details, status, created_at) VALUES ("
                    . (int) $order_id . ", '$pt_esc', $dep_sql, $tn_sql, $chq_sql, $pur_sql, $amt, $dia_sql, $qty_pay, $pd_sql, 1, NOW())";
            } elseif ($sop_has_prev) {
                $pay_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity, status, created_at) VALUES ("
                    . (int) $order_id . ", '$pt_esc', $dep_sql, $tn_sql, $chq_sql, $pur_sql, $amt, 0, $dia_sql, $qty_pay, 1, NOW())";
            } else {
                $pay_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, diamond_category, quantity, status, created_at) VALUES ("
                    . (int) $order_id . ", '$pt_esc', $dep_sql, $tn_sql, $chq_sql, $pur_sql, $amt, $dia_sql, $qty_pay, 1, NOW())";
            }
            if (!mysqli_query($conn, $pay_sql)) {
                throw new Exception('Sale order payment insert failed: ' . mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'sale_order_id' => $order_id,
    'order_no' => $order_no,
    'message' => 'Sale order created for job work',
]);
