<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $voucher_id = isset($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : 0;
    $voucher_no = esc($_POST['voucher_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $ref_no = esc($_POST['ref_no'] ?? '');
    $receipt_no = esc($_POST['receipt_no'] ?? '');
    $voucher_type = esc($_POST['voucher_type'] ?? '');
    $against = esc($_POST['against'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'USD');
    $currency_rate = isset($_POST['currency_rate']) ? (float)$_POST['currency_rate'] : 1.0;
    $voucher_date = esc($_POST['voucher_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? null);
    $layaways_id = isset($_POST['layaways_id']) ? (int)$_POST['layaways_id'] : 0;
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $previous_balance = isset($_POST['previous_balance']) ? (float)$_POST['previous_balance'] : 0.00;
    $previous_gold = isset($_POST['previous_gold']) ? (float)$_POST['previous_gold'] : 0.000;
    $previous_silver = isset($_POST['previous_silver']) ? (float)$_POST['previous_silver'] : 0.000;
    $total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0.00;
    $total_gold = isset($_POST['total_gold']) ? (float)$_POST['total_gold'] : 0.000;
    $total_silver = isset($_POST['total_silver']) ? (float)$_POST['total_silver'] : 0.000;
    $comment = esc($_POST['comment'] ?? '');
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    $created_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Validation
    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }

    if (empty($voucher_no)) {
        throw new Exception('Voucher number is required');
    }

    // Check if voucher number already exists (for new vouchers)
    if ($voucher_id == 0) {
        $existing = getRecord("SELECT id FROM tbl_customer_advance_vouchers WHERE voucher_no = '$voucher_no'");
        if ($existing) {
            throw new Exception('Voucher number already exists');
        }
    }

    if ($voucher_id > 0) {
        // Update existing voucher
        $update_query = "
            UPDATE tbl_customer_advance_vouchers SET
                customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                customer_name = '$customer_name',
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                voucher_type = " . ($voucher_type ? "'$voucher_type'" : 'NULL') . ",
                against = " . ($against ? "'$against'" : 'NULL') . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                currency = '$currency',
                voucher_date = '$voucher_date',
                due_date = " . ($due_date ? "'$due_date'" : 'NULL') . ",
                layaways_id = " . ($layaways_id > 0 ? $layaways_id : 'NULL') . ",
                fixing_type = '$fixing_type',
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                total_amount = $total_amount,
                total_gold = $total_gold,
                total_silver = $total_silver,
                comment = " . ($comment ? "'$comment'" : 'NULL') . ",
                updated_at = NOW()
            WHERE id = $voucher_id
        ";

        if (!mysqli_query($conn, $update_query)) {
            throw new Exception('Error updating voucher: ' . mysqli_error($conn));
        }

        // Delete existing items
        mysqli_query($conn, "DELETE FROM tbl_customer_advance_voucher_items WHERE voucher_id = $voucher_id");
    } else {
        // Insert new voucher
        $insert_query = "
            INSERT INTO tbl_customer_advance_vouchers (
                voucher_no, customer_id, customer_name, ref_no, voucher_type, against,
                sales_person, against_of, currency, voucher_date, due_date, layaways_id,
                fixing_type, previous_balance, previous_gold, previous_silver,
                total_amount, total_gold, total_silver, comment, status, created_by, created_at
            ) VALUES (
                '$voucher_no',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$customer_name',
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                " . ($voucher_type ? "'$voucher_type'" : 'NULL') . ",
                " . ($against ? "'$against'" : 'NULL') . ",
                " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                " . ($against_of ? "'$against_of'" : 'NULL') . ",
                '$currency',
                '$voucher_date',
                " . ($due_date ? "'$due_date'" : 'NULL') . ",
                " . ($layaways_id > 0 ? $layaways_id : 'NULL') . ",
                '$fixing_type',
                $previous_balance,
                $previous_gold,
                $previous_silver,
                $total_amount,
                $total_gold,
                $total_silver,
                " . ($comment ? "'$comment'" : 'NULL') . ",
                'draft',
                " . ($created_by ? $created_by : 'NULL') . ",
                NOW()
            )
        ";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception('Error inserting voucher: ' . mysqli_error($conn));
        }

        $voucher_id = mysqli_insert_id($conn);
    }

    // Insert receipt items
    if (is_array($items) && count($items) > 0) {
        foreach ($items as $item) {
            $payment_type = esc($item['payment_type'] ?? '');
            $diamond_category = esc($item['diamond_category'] ?? '');
            $transaction_no = esc($item['transaction_no'] ?? '');
            $deposit_into = esc($item['deposit_into'] ?? '');
            $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $cheque_date = esc($item['cheque_date'] ?? null);
            $weight = isset($item['weight']) ? (float)$item['weight'] : 0.000;
            $metal_id = isset($item['metal_id']) ? (int)$item['metal_id'] : 0;
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0.00;
            $purity_carat = esc($item['purity_carat'] ?? '');
            $purity_wt = isset($item['purity_wt']) ? (float)$item['purity_wt'] : 0.000;

            $item_query = "
                INSERT INTO tbl_customer_advance_voucher_items (
                    voucher_id, payment_type, diamond_category, transaction_no, deposit_into,
                    product_id, cheque_date, weight, metal_id, quantity, purity_carat, purity_wt,
                    status, created_at
                ) VALUES (
                    $voucher_id,
                    " . ($payment_type ? "'$payment_type'" : 'NULL') . ",
                    " . ($diamond_category ? "'$diamond_category'" : 'NULL') . ",
                    " . ($transaction_no ? "'$transaction_no'" : 'NULL') . ",
                    " . ($deposit_into ? "'$deposit_into'" : 'NULL') . ",
                    " . ($product_id > 0 ? $product_id : 'NULL') . ",
                    " . ($cheque_date ? "'$cheque_date'" : 'NULL') . ",
                    $weight,
                    " . ($metal_id > 0 ? $metal_id : 'NULL') . ",
                    $quantity,
                    " . ($purity_carat ? "'$purity_carat'" : 'NULL') . ",
                    $purity_wt,
                    1,
                    NOW()
                )
            ";

            if (!mysqli_query($conn, $item_query)) {
                throw new Exception('Error inserting voucher item: ' . mysqli_error($conn));
            }
        }
    }

    // Update customer ledger (if needed)
    // This would typically create ledger entries for the advance payment
    // For now, we'll just save the voucher

    mysqli_commit($conn);

    echo json_encode([
        'status' => 'success',
        'message' => 'Voucher saved successfully',
        'voucher_id' => $voucher_id,
        'voucher_no' => $voucher_no
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
