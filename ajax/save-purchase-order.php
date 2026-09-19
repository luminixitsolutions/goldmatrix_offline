<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// Ensure purchase order tables exist (run admin/sql/create_tbl_purchase_orders.sql if missing)
$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_orders'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) mysqli_free_result($tbl);
    echo json_encode([
        'status' => 'error',
        'message' => 'Purchase order tables not found. Please run admin/sql/create_tbl_purchase_orders.sql in your database first.'
    ]);
    exit;
}
mysqli_free_result($tbl);

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    
    // Get order data
    $order_no = esc($_POST['order_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'AED');
    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $order_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $group_name = esc($_POST['group_name'] ?? '');
    $comment = esc($_POST['comment'] ?? '');
    
    // Summary values
    $previous_balance = (float)($_POST['previous_balance'] ?? 0);
    $previous_gold = (float)($_POST['previous_gold'] ?? 0);
    $previous_silver = (float)($_POST['previous_silver'] ?? 0);
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $additional_amt = (float)($_POST['additional_amt'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $reward_points = (float)($_POST['reward_points'] ?? 0);
    $coupon_code = esc($_POST['coupon_code'] ?? '');
    $coupon_discount = (float)($_POST['coupon_discount'] ?? 0);
    $discount_amt = (float)($_POST['discount_amt'] ?? 0);
    $redeem_points = (float)($_POST['redeem_points'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $metal_amt = (float)($_POST['metal_amt'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);
    
    // Check if update or insert
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $is_update = ($order_id > 0);
    
    // Validation
    if (empty($customer_name)) {
        throw new Exception("Customer name is required");
    }
    
    if (empty($order_no)) {
        // Generate order number if not provided
        $last_order = getRecord("SELECT order_no FROM tbl_purchase_orders ORDER BY id DESC LIMIT 1");
        if ($last_order && $last_order['order_no']) {
            $last_num = (int)str_replace('PO-', '', $last_order['order_no']);
            $order_no = 'PO-' . ($last_num + 1);
        } else {
            $order_no = 'PO-1';
        }
    }
    
    if ($is_update) {
        // Update existing order
        $sql = "
            UPDATE tbl_purchase_orders SET
                order_no = '$order_no',
                customer_id = " . ($customer_id > 0 ? $customer_id : "NULL") . ",
                customer_name = '$customer_name',
                against_of = " . ($against_of ? "'$against_of'" : "NULL") . ",
                currency = '$currency',
                ref_no = " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : "NULL") . ",
                order_date = '$order_date',
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ",
                layaways_id = " . ($layaways ? (int)$layaways : "NULL") . ",
                fixing_type = '$fixing_type',
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                subtotal = $subtotal,
                additional_amt = $additional_amt,
                net_total = $net_total,
                reward_points = $reward_points,
                coupon_code = " . ($coupon_code ? "'$coupon_code'" : "NULL") . ",
                coupon_discount = $coupon_discount,
                discount_amt = $discount_amt,
                redeem_points = $redeem_points,
                grand_total = $grand_total,
                advance_payment = $advance_payment,
                metal_amt = $metal_amt,
                round_off = $round_off,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                group_name = " . ($group_name ? "'$group_name'" : "NULL") . ",
                comment = " . ($comment ? "'$comment'" : "NULL") . ",
                updated_at = NOW()
            WHERE id = $order_id
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Order update failed: " . mysqli_error($conn));
        }
        
        // Delete existing items and payments
        mysqli_query($conn, "DELETE FROM tbl_purchase_order_items WHERE order_id = $order_id");
        mysqli_query($conn, "DELETE FROM tbl_purchase_order_payments WHERE order_id = $order_id");
    } else {
        // Insert new order
        $sql = "
            INSERT INTO tbl_purchase_orders (
                order_no, customer_id, customer_name, against_of, currency, ref_no, sales_person,
                order_date, due_date, layaways_id, fixing_type,
                previous_balance, previous_gold, previous_silver,
                subtotal, additional_amt, net_total, reward_points,
                coupon_code, coupon_discount, discount_amt, redeem_points,
                grand_total, advance_payment, metal_amt, round_off,
                paid_amt, balance_amt, group_name, comment,
                status, created_by, created_at
            ) VALUES (
                '$order_no', " . ($customer_id > 0 ? $customer_id : "NULL") . ", '$customer_name', " . ($against_of ? "'$against_of'" : "NULL") . ",
                '$currency', " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                " . ($sales_person ? "'$sales_person'" : "NULL") . ",
                '$order_date', " . ($due_date ? "'$due_date'" : "NULL") . ",
                " . ($layaways ? (int)$layaways : "NULL") . ",
                '$fixing_type',
                $previous_balance, $previous_gold, $previous_silver,
                $subtotal, $additional_amt, $net_total, $reward_points,
                " . ($coupon_code ? "'$coupon_code'" : "NULL") . ",
                $coupon_discount, $discount_amt, $redeem_points,
                $grand_total, $advance_payment, $metal_amt, $round_off,
                $paid_amt, $balance_amt,
                " . ($group_name ? "'$group_name'" : "NULL") . ",
                " . ($comment ? "'$comment'" : "NULL") . ",
                'draft', $user_id, NOW()
            )
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Order insert failed: " . mysqli_error($conn));
        }
        
        $order_id = mysqli_insert_id($conn);
    }
    
    // Save order items
    $items = [];
    if (isset($_POST['items'])) {
        if (is_string($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
        } else if (is_array($_POST['items'])) {
            $items = $_POST['items'];
        }
    }
    
    if (!empty($items) && is_array($items)) {
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $barcode = esc($item['barcode'] ?? '');
            $product_name = esc($item['product_name'] ?? '');
            $carat = esc($item['carat'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $gross_weight = (float)($item['gross_weight'] ?? 0);
            $less_weight = (float)($item['less_weight'] ?? 0);
            $purity = (float)($item['purity'] ?? 0);
            $purity_weight = (float)($item['purity_weight'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? 0);
            $net_weight = (float)($item['net_weight'] ?? 0);
            $pure_weight = (float)($item['pure_weight'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $making = (float)($item['making'] ?? 0);
            $making_amount = (float)($item['making_amount'] ?? 0);
            $design_no = esc($item['design_no'] ?? '');
            $tax = (float)($item['tax'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
            $stone_charges = (float)($item['stone_charges'] ?? 0);
            $stone_amount = (float)($item['stone_amount'] ?? 0);
            $other_charges = (float)($item['other_charges'] ?? 0);
            $other_amount = (float)($item['other_amount'] ?? 0);
            $diamond_value = (float)($item['diamond_value'] ?? 0);
            $diamond_amount = (float)($item['diamond_amount'] ?? 0);
            $gemstone_value = (float)($item['gemstone_value'] ?? 0);
            $metal_value = (float)($item['metal_value'] ?? 0);
            $discount = (float)($item['discount'] ?? 0);
            $purchase_amount = (float)($item['purchase_amount'] ?? 0);
            $sale_amount = (float)($item['sale_amount'] ?? 0);
            $sale_amount_with = (float)($item['sale_amount_with'] ?? 0);
            $reverse = (float)($item['reverse'] ?? 0);
            
            if ($product_id > 0) {
                // Insert with only basic columns that should exist in the table
                // If you get column errors, add the missing columns to the database table
                $item_sql = "
                    INSERT INTO tbl_purchase_order_items (
                        order_id, product_id, product_characteristic_id, barcode, product_name,
                        carat, quantity, gross_weight, less_weight, purity, purity_weight,
                        final_weight, net_weight, pure_weight, rate,
                        making_amount, amount, tax_amount, net_amount, net_amt_with_tax,
                        design_no, status, created_at
                    ) VALUES (
                        $order_id, $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                        " . ($barcode ? "'$barcode'" : "NULL") . ",
                        '$product_name',
                        " . ($carat ? "'$carat'" : "NULL") . ",
                        $quantity, $gross_weight, $less_weight, $purity, $purity_weight,
                        $final_weight, $net_weight, $pure_weight, $rate,
                        $making_amount, $amount, $tax, $net_amount, $net_amt_with_tax,
                        " . ($design_no ? "'$design_no'" : "NULL") . ",
                        1, NOW()
                    )
                ";
                
                if (!mysqli_query($conn, $item_sql)) {
                    throw new Exception("Item insert failed: " . mysqli_error($conn));
                }
            }
        }
    }
    
    // Save payments
    $payments = [];
    if (isset($_POST['payments'])) {
        if (is_string($_POST['payments'])) {
            $payments = json_decode($_POST['payments'], true);
        } else if (is_array($_POST['payments'])) {
            $payments = $_POST['payments'];
        }
    }
    
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $payment) {
            $payment_type = esc($payment['payment_type'] ?? '');
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $transaction_no = esc($payment['transaction_no'] ?? '');
            $cheque_date = isset($payment['cheque_date']) && $payment['cheque_date'] ? esc($payment['cheque_date']) : NULL;
            $purity_carat = esc($payment['purity_carat'] ?? '');
            $amount = (float)($payment['amount'] ?? 0); // Total amount (current order + previous balance)
            $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
            $current_order_amount = (float)($payment['current_order_amount'] ?? ($amount - $previous_balance_amount));
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $quantity = (float)($payment['quantity'] ?? 0);
            
            if ($amount > 0) {
                // Try to insert with previous_balance_amount column (if it exists)
                $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
                
                $payment_sql = "
                    INSERT INTO tbl_purchase_order_payments (
                        order_id, payment_type, deposit_into, transaction_no,
                        cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity,
                        status, created_at
                    ) VALUES (
                        $order_id, '$payment_type',
                        " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                        " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                        " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                        " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                        $amount,
                        $previous_balance_amount,
                        " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                        $quantity,
                        1, NOW()
                    )
                ";
                
                // If insert fails due to missing column, try without previous_balance_amount
                if (!mysqli_query($conn, $payment_sql)) {
                    $error = mysqli_error($conn);
                    if (strpos($error, 'previous_balance_amount') !== false) {
                        // Column doesn't exist, insert without it (will need to add column to table)
                        $payment_sql = "
                            INSERT INTO tbl_purchase_order_payments (
                                order_id, payment_type, deposit_into, transaction_no,
                                cheque_date, purity_carat, amount, diamond_category, quantity,
                                status, created_at
                            ) VALUES (
                                $order_id, '$payment_type',
                                " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                                " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                                " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                                " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                                $amount,
                                " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                                $quantity,
                                1, NOW()
                            )
                        ";
                        if (!mysqli_query($conn, $payment_sql)) {
                            throw new Exception("Payment insert failed: " . mysqli_error($conn));
                        }
                    } else {
                        throw new Exception("Payment insert failed: " . $error);
                    }
                }
            }
        }
    }
    
    // ================== CUSTOMER LEDGER UPDATE ==================
    // Only update ledger for new orders (not updates) to prevent duplicate entries
    // For sale orders: customer owes us money
    // Debit = grand_total (what customer owes)
    // Credit = paid_amt (what customer paid)
    
    if (!$is_update) {
    
    // Get previous balance from customer ledger
    $previous_balance_record = null;
    if ($customer_id > 0) {
        $previous_balance_record = getRecord("
            SELECT balance_amount, balance_gold, balance_silver 
            FROM tbl_customer_ledger 
            WHERE customer_id = $customer_id 
            AND status = 1 
            ORDER BY transaction_date DESC, id DESC 
            LIMIT 1
        ");
        
        // If not found in ledger, try balance table
        if (!$previous_balance_record) {
            $previous_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_balance 
                WHERE customer_id = $customer_id 
                LIMIT 1
            ");
        }
    } else if (!empty($customer_name)) {
        $previous_balance_record = getRecord("
            SELECT balance_amount, balance_gold, balance_silver 
            FROM tbl_customer_ledger 
            WHERE customer_name = '$customer_name' 
            AND status = 1 
            ORDER BY transaction_date DESC, id DESC 
            LIMIT 1
        ");
        
        // If not found in ledger, try balance table
        if (!$previous_balance_record) {
            $previous_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_balance 
                WHERE customer_name = '$customer_name' 
                LIMIT 1
            ");
        }
    }
    
    // Calculate previous balance (default to 0 if not found)
    $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? $previous_balance);
    $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? $previous_gold);
    $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? $previous_silver);
    
    // For sale orders: customer owes us (debit = what customer owes, credit = 0 for sale order entry)
    // Payments will be separate credit entries
    $ledger_debit_amount = $grand_total; // Customer owes this amount
    $ledger_credit_amount = 0;           // Sale order entry has no credit (payments are separate entries)
    
    // Calculate new running balance for sale order entry
    // Balance = Previous Balance + Order Amount (no payment deduction here, payments are separate entries)
    $new_balance_amount = $prev_balance_amount + $ledger_debit_amount;
    
    // Calculate gold and silver weights from items
    $total_gold_weight = 0;
    $total_silver_weight = 0;
    if (!empty($items) && is_array($items)) {
        foreach ($items as $item) {
            $item_pure_weight = (float)($item['pure_weight'] ?? 0);
            $item_purity = (float)($item['purity'] ?? 0);
            
            // Determine if gold or silver based on purity (typically gold is 916, 750, etc., silver is 925, etc.)
            if ($item_purity >= 750) {
                // Gold (750, 916, 999, etc.)
                $total_gold_weight += $item_pure_weight;
            } else if ($item_purity >= 500) {
                // Silver (500, 925, etc.)
                $total_silver_weight += $item_pure_weight;
            }
        }
    }
    
    $new_balance_gold = $prev_balance_gold + $total_gold_weight;
    $new_balance_silver = $prev_balance_silver + $total_silver_weight;
    
    // Insert ledger entry for sale order
    $ledger_sql = "
        INSERT INTO tbl_customer_ledger (
            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount, 
            debit_gold, credit_gold, debit_silver, credit_silver,
            balance_amount, balance_gold, balance_silver,
            description, reference_no, against_ledger, against_invoice_no,
            status, created_by, created_at
        ) VALUES (
            " . ($customer_id > 0 ? $customer_id : 0) . ",
            '$customer_name',
            'purchase_order',
            $order_id,
            '$order_no',
            '$order_date',
            $ledger_debit_amount,
            $ledger_credit_amount,
            $total_gold_weight,
            0.000,
            $total_silver_weight,
            0.000,
            $new_balance_amount,
            $new_balance_gold,
            $new_balance_silver,
            'Purchase Order: $order_no',
            " . ($ref_no ? "'$ref_no'" : "NULL") . ",
            " . ($against_of ? "'$against_of'" : "NULL") . ",
            '$order_no',
            1,
            $user_id,
            NOW()
        )
    ";
    
    if (!mysqli_query($conn, $ledger_sql)) {
        throw new Exception("Ledger entry failed: " . mysqli_error($conn));
    }
    
    // Payments: same accounting as purchase invoice — supplier debit, Cash/Bank credit (money out)
    if (!empty($payments) && is_array($payments)) {
        $order_no_esc = mysqli_real_escape_string($conn, $order_no);
        foreach ($payments as $payment) {
            $current_order_amount = (float)($payment['amount'] ?? 0);
            $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
            $payment_amount = $current_order_amount + $previous_balance_amount;
            $deposit_into = esc($payment['deposit_into'] ?? 'Cash');
            $payment_type_raw = strtolower(trim($payment['payment_type'] ?? 'cash'));

            if ($payment_amount > 0) {
                if ($previous_balance_amount > 0) {
                    $last_balance_record = getRecord("
                        SELECT balance_amount, balance_gold, balance_silver 
                        FROM tbl_customer_ledger 
                        WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . "
                        AND customer_name = '$customer_name' 
                        AND status = 1 
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                    $current_running_balance_amount = (float)($last_balance_record['balance_amount'] ?? $new_balance_amount);
                    $current_running_balance_gold = (float)($last_balance_record['balance_gold'] ?? $new_balance_gold);
                    $current_running_balance_silver = (float)($last_balance_record['balance_silver'] ?? $new_balance_silver);
                    $current_running_balance_amount -= $previous_balance_amount;

                    $prev_balance_payment_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, balance_gold, balance_silver,
                            description, against_ledger, against_invoice_no,
                            status, created_by, created_at
                        ) VALUES (
                            " . ($customer_id > 0 ? $customer_id : 0) . ",
                            '$customer_name',
                            'previous_balance_payment',
                            $order_id,
                            '$order_no_esc',
                            '$order_date',
                            0.00,
                            $previous_balance_amount,
                            $current_running_balance_amount,
                            $current_running_balance_gold,
                            $current_running_balance_silver,
                            'Payment for Previous Balance - Purchase Order: $order_no_esc',
                            '$deposit_into',
                            'Previous Balance',
                            1,
                            $user_id,
                            NOW()
                        )
                    ";
                    if (!mysqli_query($conn, $prev_balance_payment_sql)) {
                        throw new Exception("Previous balance payment ledger entry failed: " . mysqli_error($conn));
                    }
                    $new_balance_amount = $current_running_balance_amount;
                }

                if ($current_order_amount > 0) {
                    $last_balance_record = getRecord("
                        SELECT balance_amount, balance_gold, balance_silver 
                        FROM tbl_customer_ledger 
                        WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . "
                        AND customer_name = '$customer_name' 
                        AND status = 1 
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                    $current_running_balance_amount = (float)($last_balance_record['balance_amount'] ?? $new_balance_amount);
                    $current_running_balance_gold = (float)($last_balance_record['balance_gold'] ?? $new_balance_gold);
                    $current_running_balance_silver = (float)($last_balance_record['balance_silver'] ?? $new_balance_silver);
                    $current_running_balance_amount -= $current_order_amount;

                    $supplier_balance_for_display = $current_running_balance_amount;
                    $supplier_crdr = $supplier_balance_for_display >= 0 ? 'Dr' : 'Cr';
                    $supplier_against_ledger = $customer_name . '(' . number_format(abs($supplier_balance_for_display), 2) . $supplier_crdr . ')';
                    $payment_against_ledger = $deposit_into . '(' . number_format($current_order_amount, 2) . 'Cr)';

                    $payment_ledger_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, balance_gold, balance_silver,
                            description, against_ledger, against_invoice_no,
                            status, created_by, created_at
                        ) VALUES (
                            " . ($customer_id > 0 ? $customer_id : 0) . ",
                            '$customer_name',
                            'payment',
                            $order_id,
                            '$order_no_esc',
                            '$order_date',
                            $current_order_amount,
                            0.00,
                            $current_running_balance_amount,
                            $current_running_balance_gold,
                            $current_running_balance_silver,
                            'Payment for Purchase Order: $order_no_esc',
                            '" . mysqli_real_escape_string($conn, $payment_against_ledger) . "',
                            '$order_no_esc',
                            1,
                            $user_id,
                            NOW()
                        )
                    ";
                    if (!mysqli_query($conn, $payment_ledger_sql)) {
                        throw new Exception("Payment ledger entry failed: " . mysqli_error($conn));
                    }
                    $new_balance_amount = $current_running_balance_amount;
                }

                $total_payment_received = $current_order_amount + $previous_balance_amount;
                if ($total_payment_received > 0 && !empty($deposit_into)) {
                    $bank_against_ledger = mysqli_real_escape_string($conn, accountledger_against_party_payment_label($customer_name, $payment_type_raw, $total_payment_received));

                    $cash_balance_record = getRecord("
                        SELECT balance_amount 
                        FROM tbl_customer_ledger 
                        WHERE customer_name = '$deposit_into' 
                        AND status = 1 
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                    $cash_prev_balance = (float)($cash_balance_record['balance_amount'] ?? 0);
                    $cash_new_balance = $cash_prev_balance - $total_payment_received;

                    $cash_ledger_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, description, against_ledger, against_invoice_no,
                            status, created_by, created_at
                        ) VALUES (
                            0,
                            '$deposit_into',
                            'payment',
                            $order_id,
                            '$order_no_esc',
                            '$order_date',
                            0.00,
                            $total_payment_received,
                            $cash_new_balance,
                            'Payment to $customer_name for Purchase Order: $order_no_esc (Current: " . number_format($current_order_amount, 2) . ", Previous Balance: " . number_format($previous_balance_amount, 2) . ")',
                            '$bank_against_ledger',
                            '$order_no_esc',
                            1,
                            $user_id,
                            NOW()
                        )
                    ";
                    if (!mysqli_query($conn, $cash_ledger_sql)) {
                        throw new Exception("Cash ledger entry failed: " . mysqli_error($conn));
                    }
                }
            }
        }
    }
    
    // Update customer balance summary with final running balance
    $existing_balance = null;
    if ($customer_id > 0) {
        $existing_balance = getRecord("
            SELECT id FROM tbl_customer_balance 
            WHERE customer_id = $customer_id 
            LIMIT 1
        ");
    } else if (!empty($customer_name)) {
        $existing_balance = getRecord("
            SELECT id FROM tbl_customer_balance 
            WHERE customer_name = '$customer_name' 
            LIMIT 1
        ");
    }
    
    if ($existing_balance) {
        // Update existing record
        if ($customer_id > 0) {
            $balance_update_sql = "
                UPDATE tbl_customer_balance SET
                    balance_amount = $new_balance_amount,
                    balance_gold = $new_balance_gold,
                    balance_silver = $new_balance_silver,
                    last_transaction_date = '$order_date',
                    last_updated = NOW()
                WHERE customer_id = $customer_id
            ";
        } else {
            $balance_update_sql = "
                UPDATE tbl_customer_balance SET
                    balance_amount = $new_balance_amount,
                    balance_gold = $new_balance_gold,
                    balance_silver = $new_balance_silver,
                    last_transaction_date = '$order_date',
                    last_updated = NOW()
                WHERE customer_name = '$customer_name'
            ";
        }
        
        if (!mysqli_query($conn, $balance_update_sql)) {
            throw new Exception("Balance update failed: " . mysqli_error($conn));
        }
    } else {
        // Insert new record
        if ($customer_id > 0) {
            $balance_insert_sql = "
                INSERT INTO tbl_customer_balance (
                    customer_id, customer_name, balance_amount, balance_gold, balance_silver,
                    last_transaction_date
                ) VALUES (
                    $customer_id, '$customer_name', $new_balance_amount, 
                    $new_balance_gold, $new_balance_silver,
                    '$order_date'
                )
            ";
        } else {
            $balance_insert_sql = "
                INSERT INTO tbl_customer_balance (
                    customer_id, customer_name, balance_amount, balance_gold, balance_silver,
                    last_transaction_date
                ) VALUES (
                    0, '$customer_name', $new_balance_amount, 
                    $new_balance_gold, $new_balance_silver,
                    '$order_date'
                )
            ";
        }
        
        if (!mysqli_query($conn, $balance_insert_sql)) {
            throw new Exception("Balance insert failed: " . mysqli_error($conn));
        }
    }
    // ================== END CUSTOMER LEDGER UPDATE ==================
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Order saved successfully',
        'order_id' => $order_id,
        'order_no' => $order_no
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

