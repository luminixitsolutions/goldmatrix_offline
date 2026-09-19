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
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    
    // Get form data
    $fixing_id = isset($_POST['fixing_id']) ? (int)$_POST['fixing_id'] : 0;
    $invoice_no = esc($_POST['invoice_no'] ?? $_POST['order_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $ref_no = esc($_POST['ref_no'] ?? '');
    $customer_name = esc($_POST['customer_name'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways_id = isset($_POST['layaways_id']) ? (int)$_POST['layaways_id'] : 0;
    $layaways = isset($_POST['layaways']) ? 1 : 0;
    $against = esc($_POST['against'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'USD');
    $currency_rate = isset($_POST['currency_rate']) ? (float)$_POST['currency_rate'] : 1.00;
    $goz = isset($_POST['goz']) ? (float)$_POST['goz'] : 0.00;
    $invoice_date = esc($_POST['invoice_date'] ?? $_POST['order_date'] ?? date('Y-m-d'));
    $fixing_date = esc($_POST['fixing_date'] ?? date('Y-m-d'));
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $previous_balance = isset($_POST['previous_balance']) ? (float)$_POST['previous_balance'] : 0.00;
    $previous_gold = isset($_POST['previous_gold']) ? (float)$_POST['previous_gold'] : 0.000;
    $previous_silver = isset($_POST['previous_silver']) ? (float)$_POST['previous_silver'] : 0.000;
    $subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.00;
    $additional_amt = isset($_POST['additional_amt']) ? (float)$_POST['additional_amt'] : 0.00;
    $net_total = isset($_POST['net_total']) ? (float)$_POST['net_total'] : 0.00;
    $discount_amt = isset($_POST['discount_amt']) ? (float)$_POST['discount_amt'] : 0.00;
    $grand_total = isset($_POST['grand_total']) ? (float)$_POST['grand_total'] : 0.00;
    $advance_payment = isset($_POST['advance_payment']) ? (float)$_POST['advance_payment'] : 0.00;
    $paid_amt = isset($_POST['paid_amt']) ? (float)$_POST['paid_amt'] : 0.00;
    $balance_amt = isset($_POST['balance_amt']) ? (float)$_POST['balance_amt'] : 0.00;
    $total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0.00;
    $comment = esc($_POST['comment'] ?? '');
    
    // Generate invoice number if not provided
    if (empty($invoice_no)) {
        $last_invoice = getRecord("SELECT invoice_no FROM tbl_sale_fixing_direct ORDER BY id DESC LIMIT 1");
        if ($last_invoice && $last_invoice['invoice_no']) {
            $last_num = (int)str_replace('SFD-', '', $last_invoice['invoice_no']);
            $invoice_no = 'SFD-' . ($last_num + 1);
        } else {
            $invoice_no = 'SFD-1';
        }
    }
    
    // Calculate totals from items
    $total_gross_wt = 0;
    $total_purity_wt = 0;
    $items = [];
    
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            $metal_id = isset($item['metal_id']) ? (int)$item['metal_id'] : 0;
            $gross_wt = isset($item['gross_wt']) ? (float)$item['gross_wt'] : 0;
            $purity_wt = isset($item['purity_wt']) ? (float)$item['purity_wt'] : 0;
            $rate = isset($item['rate']) ? (float)$item['rate'] : 0;
            $amount = isset($item['amount']) ? (float)$item['amount'] : 0;
            $purity = isset($item['purity']) ? (float)$item['purity'] : 1.00;
            
            if ($metal_id > 0) {
                $items[] = [
                    'metal_id' => $metal_id,
                    'gross_wt' => $gross_wt,
                    'purity_wt' => $purity_wt,
                    'rate' => $rate,
                    'amount' => $amount,
                    'purity' => $purity
                ];
                
                $total_gross_wt += $gross_wt;
                $total_purity_wt += $purity_wt;
            }
        }
    }
    
    // Validation
    if (empty($customer_name)) {
        throw new Exception("Customer name is required");
    }
    
    if ($fixing_id > 0) {
        // Update existing fixing
        $sql = "
            UPDATE tbl_sale_fixing_direct SET
                invoice_no = '$invoice_no',
                customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                ref_no = '$ref_no',
                customer_name = '$customer_name',
                sales_person = '$sales_person',
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ",
                layaways_id = " . ($layaways_id > 0 ? $layaways_id : 'NULL') . ",
                layaways = $layaways,
                against = " . ($against ? "'$against'" : "NULL") . ",
                against_of = " . ($against_of ? "'$against_of'" : "NULL") . ",
                currency = '$currency',
                currency_rate = $currency_rate,
                goz = $goz,
                invoice_date = '$invoice_date',
                fixing_date = '$fixing_date',
                fixing_type = '$fixing_type',
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                total_gross_wt = $total_gross_wt,
                total_purity_wt = $total_purity_wt,
                subtotal = $subtotal,
                additional_amt = $additional_amt,
                net_total = $net_total,
                discount_amt = $discount_amt,
                grand_total = $grand_total,
                advance_payment = $advance_payment,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                total_amount = $total_amount,
                comment = " . ($comment ? "'$comment'" : "NULL") . ",
                updated_at = NOW()
            WHERE id = $fixing_id
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Update failed: " . mysqli_error($conn));
        }
        
        // Delete existing items
        $delete_items = "DELETE FROM tbl_sale_fixing_direct_items WHERE fixing_id = $fixing_id";
        mysqli_query($conn, $delete_items);
    } else {
        // Insert new fixing
        $sql = "
            INSERT INTO tbl_sale_fixing_direct (
                invoice_no, customer_id, ref_no, customer_name, sales_person, due_date, layaways_id, layaways,
                against, against_of, currency, currency_rate, goz,
                invoice_date, fixing_date, fixing_type, previous_balance, previous_gold, previous_silver,
                total_gross_wt, total_purity_wt, subtotal, additional_amt, net_total, discount_amt,
                grand_total, advance_payment, paid_amt, balance_amt, total_amount, comment,
                status, created_by, created_at
            ) VALUES (
                '$invoice_no',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$ref_no', '$customer_name', '$sales_person', " . ($due_date ? "'$due_date'" : "NULL") . ",
                " . ($layaways_id > 0 ? $layaways_id : 'NULL') . ", $layaways,
                " . ($against ? "'$against'" : "NULL") . ", " . ($against_of ? "'$against_of'" : "NULL") . ",
                '$currency', $currency_rate, $goz,
                '$invoice_date', '$fixing_date', '$fixing_type', $previous_balance, $previous_gold, $previous_silver,
                $total_gross_wt, $total_purity_wt, $subtotal, $additional_amt, $net_total, $discount_amt,
                $grand_total, $advance_payment, $paid_amt, $balance_amt, $total_amount,
                " . ($comment ? "'$comment'" : "NULL") . ",
                'draft', $user_id, NOW()
            )
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Insert failed: " . mysqli_error($conn));
        }
        
        $fixing_id = mysqli_insert_id($conn);
    }
    
    // Insert items
    foreach ($items as $item) {
        $item_sql = "
            INSERT INTO tbl_sale_fixing_direct_items (
                fixing_id, metal_id, gross_wt, purity_wt, rate, amount, purity, created_at
            ) VALUES (
                $fixing_id, {$item['metal_id']}, {$item['gross_wt']}, {$item['purity_wt']}, 
                {$item['rate']}, {$item['amount']}, {$item['purity']}, NOW()
            )
        ";
        
        if (!mysqli_query($conn, $item_sql)) {
            throw new Exception("Item insert failed: " . mysqli_error($conn));
        }
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Sale Fixing Direct Invoice saved successfully',
        'fixing_id' => $fixing_id,
        'invoice_no' => $invoice_no
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
