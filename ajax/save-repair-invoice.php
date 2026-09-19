<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_invoices'");
if (!$tbl || mysqli_num_rows($tbl) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Repair invoice tables not found. Please run admin/sql/create_repair_invoice_tables.sql']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);

    $repair_invoice_no = esc($_POST['order_no'] ?? '');
    $repair_invoice_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'AED');
    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $repair_invoice_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $group_name = esc($_POST['group_name'] ?? '');
    $comment = esc($_POST['comment'] ?? '');

    $previous_balance = (float)($_POST['previous_balance'] ?? 0);
    $previous_gold = (float)($_POST['previous_gold'] ?? 0);
    $previous_silver = (float)($_POST['previous_silver'] ?? 0);
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $additional_amt = (float)($_POST['additional_amt'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $reward_points = (float)($_POST['reward_points'] ?? 0);
    $coupon_discount = (float)($_POST['coupon_discount'] ?? 0);
    $discount_amt = (float)($_POST['discount_amt'] ?? 0);
    $redeem_points = (float)($_POST['redeem_points'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $metal_amt = (float)($_POST['metal_amt'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);

    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }

    if (empty($repair_invoice_no)) {
        $last = getRecord("SELECT repair_invoice_no FROM tbl_repair_invoices ORDER BY id DESC LIMIT 1");
        if ($last && $last['repair_invoice_no']) {
            $num = (int)preg_replace('/[^0-9]/', '', $last['repair_invoice_no']);
            $repair_invoice_no = 'RI-' . ($num + 1);
        } else {
            $repair_invoice_no = 'RI-1';
        }
    }

    $is_update = ($repair_invoice_id > 0);

    if ($is_update) {
        $cur = getRecord("SELECT repair_invoice_no FROM tbl_repair_invoices WHERE id = $repair_invoice_id");
        $cur_no = $cur ? $cur['repair_invoice_no'] : '';
        if ($repair_invoice_no !== $cur_no) {
            $ex = getRecord("SELECT id FROM tbl_repair_invoices WHERE repair_invoice_no = '$repair_invoice_no' AND id != $repair_invoice_id");
            if ($ex) throw new Exception("Repair Invoice No '$repair_invoice_no' already exists.");
        }
        mysqli_query($conn, "
            UPDATE tbl_repair_invoices SET
                repair_invoice_no = '$repair_invoice_no',
                customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                customer_name = '$customer_name',
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                currency = '$currency',
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                repair_invoice_date = '$repair_invoice_date',
                due_date = " . ($due_date ? "'$due_date'" : 'NULL') . ",
                layaways_id = " . ($layaways ? (int)$layaways : 'NULL') . ",
                fixing_type = '$fixing_type',
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                subtotal = $subtotal,
                additional_amt = $additional_amt,
                net_total = $net_total,
                reward_points = $reward_points,
                coupon_discount = $coupon_discount,
                discount_amt = $discount_amt,
                redeem_points = $redeem_points,
                grand_total = $grand_total,
                advance_payment = $advance_payment,
                metal_amt = $metal_amt,
                round_off = $round_off,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                group_name = " . ($group_name ? "'$group_name'" : 'NULL') . ",
                comment = " . ($comment ? "'$comment'" : 'NULL') . ",
                updated_at = NOW()
            WHERE id = $repair_invoice_id
        ");
        if (mysqli_error($conn)) throw new Exception('Update failed: ' . mysqli_error($conn));
        mysqli_query($conn, "DELETE FROM tbl_repair_invoice_items WHERE repair_invoice_id = $repair_invoice_id");
        mysqli_query($conn, "DELETE FROM tbl_repair_invoice_payments WHERE repair_invoice_id = $repair_invoice_id");
    } else {
        mysqli_query($conn, "
            INSERT INTO tbl_repair_invoices (
                repair_invoice_no, customer_id, customer_name, against_of, currency, ref_no, sales_person,
                repair_invoice_date, due_date, layaways_id, fixing_type,
                previous_balance, previous_gold, previous_silver,
                subtotal, additional_amt, net_total, reward_points, coupon_discount, discount_amt, redeem_points,
                grand_total, advance_payment, metal_amt, round_off, paid_amt, balance_amt,
                group_name, comment, status, created_by, created_at
            ) VALUES (
                '$repair_invoice_no',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$customer_name',
                " . ($against_of ? "'$against_of'" : 'NULL') . ",
                '$currency',
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                '$repair_invoice_date',
                " . ($due_date ? "'$due_date'" : 'NULL') . ",
                " . ($layaways ? (int)$layaways : 'NULL') . ",
                '$fixing_type',
                $previous_balance, $previous_gold, $previous_silver,
                $subtotal, $additional_amt, $net_total, $reward_points, $coupon_discount, $discount_amt, $redeem_points,
                $grand_total, $advance_payment, $metal_amt, $round_off, $paid_amt, $balance_amt,
                " . ($group_name ? "'$group_name'" : 'NULL') . ",
                " . ($comment ? "'$comment'" : 'NULL') . ",
                'draft',
                " . ($user_id ? $user_id : 'NULL') . ",
                NOW()
            )
        ");
        if (mysqli_error($conn)) throw new Exception('Insert failed: ' . mysqli_error($conn));
        $repair_invoice_id = mysqli_insert_id($conn);
    }

    $items = [];
    if (isset($_POST['items'])) {
        $items = is_string($_POST['items']) ? json_decode($_POST['items'], true) : $_POST['items'];
    }
    if (!empty($items) && is_array($items)) {
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            if ($product_id <= 0) continue;
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : null;
            $product_name = esc($item['product_name'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $gross_weight = (float)($item['gross_weight'] ?? 0);
            $less_weight = (float)($item['less_weight'] ?? 0);
            $purity = (float)($item['purity'] ?? 0);
            $purity_weight = (float)($item['purity_weight'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? 0);
            $net_weight = (float)($item['net_weight'] ?? 0);
            $pure_weight = (float)($item['pure_weight'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $making_amount = (float)($item['making_amount'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $tax = (float)($item['tax'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
            $design_no = esc($item['design_no'] ?? '');
            $location_id = isset($item['location_id']) ? (int)$item['location_id'] : null;
            $barcode = esc($item['barcode'] ?? '');
            $carat = esc($item['carat'] ?? '');

            $iq = "INSERT INTO tbl_repair_invoice_items (
                repair_invoice_id, product_id, product_characteristic_id, barcode, product_name, carat,
                quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight,
                rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, design_no, location_id, status, created_at
            ) VALUES (
                $repair_invoice_id, $product_id, " . ($characteristic_id ? $characteristic_id : 'NULL') . ",
                " . ($barcode ? "'$barcode'" : 'NULL') . ", '$product_name', " . ($carat ? "'$carat'" : 'NULL') . ",
                $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight,
                $rate, $making_amount, $amount, $tax, $net_amount, $net_amt_with_tax,
                " . ($design_no ? "'$design_no'" : 'NULL') . ", " . ($location_id ? $location_id : 'NULL') . ", 1, NOW()
            )";
            if (!mysqli_query($conn, $iq)) throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }
    }

    $payments = [];
    if (isset($_POST['payments'])) {
        $payments = is_string($_POST['payments']) ? json_decode($_POST['payments'], true) : $_POST['payments'];
    }
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $p) {
            $amount = (float)($p['amount'] ?? 0);
            if ($amount <= 0) continue;
            $payment_type = esc($p['payment_type'] ?? '');
            $deposit_into = esc($p['deposit_into'] ?? '');
            $transaction_no = esc($p['transaction_no'] ?? '');
            $cheque_date = !empty($p['cheque_date']) ? esc($p['cheque_date']) : null;
            $purity_carat = esc($p['purity_carat'] ?? '');
            $previous_balance_amount = (float)($p['previous_balance_amount'] ?? 0);
            $current_order_amount = (float)($p['current_order_amount'] ?? $amount);
            $diamond_category = esc($p['diamond_category'] ?? '');
            $quantity = (float)($p['quantity'] ?? 0);

            $pq = "INSERT INTO tbl_repair_invoice_payments (
                repair_invoice_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat,
                amount, previous_balance_amount, current_order_amount, diamond_category, quantity, status, created_at
            ) VALUES (
                $repair_invoice_id, '$payment_type',
                " . ($deposit_into ? "'$deposit_into'" : 'NULL') . ",
                " . ($transaction_no ? "'$transaction_no'" : 'NULL') . ",
                " . ($cheque_date ? "'$cheque_date'" : 'NULL') . ",
                " . ($purity_carat ? "'$purity_carat'" : 'NULL') . ",
                $amount, $previous_balance_amount, $current_order_amount,
                " . ($diamond_category ? "'$diamond_category'" : 'NULL') . ", $quantity, 1, NOW()
            )";
            if (!mysqli_query($conn, $pq)) throw new Exception('Payment insert failed: ' . mysqli_error($conn));
        }
    }

    require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';
    if (function_exists('auragold_sync_voucher_cheque_entries')) {
        auragold_sync_voucher_cheque_entries($conn, [
            'voucher_no' => $repair_invoice_no,
            'voucher_type' => 'Repair Invoice',
            'voucher_date' => $repair_invoice_date,
            'account_ledger' => $customer_name,
            'transaction_id' => (int) $repair_invoice_id,
            'user_id' => (int) $user_id,
            'payments' => isset($payments) && is_array($payments) ? $payments : [],
            'pdc_direction' => 'receivable',
        ]);
    }

    mysqli_commit($conn);
    echo json_encode([
        'status' => 'success',
        'message' => 'Repair invoice saved successfully',
        'order_id' => $repair_invoice_id,
        'order_no' => $repair_invoice_no,
        'repair_invoice_id' => $repair_invoice_id,
        'repair_invoice_no' => $repair_invoice_no
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
