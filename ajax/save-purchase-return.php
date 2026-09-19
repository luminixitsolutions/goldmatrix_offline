<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/invoice_item_unique_barcode.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!function_exists('purchase_return_validate_metal_exchange_payments')) {
    /** @param array<int, array<string, mixed>> $payments */
    function purchase_return_validate_metal_exchange_payments($conn, array $payments): void
    {
        foreach ($payments as $payment) {
            $payment = auragold_payment_merge_stored_details($payment);
            if (!auragold_payment_is_metal_exchange_inward($conn, $payment)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $payment);
        }
    }
}

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    $metal_exchange_barcodes_out = [];

    $has_pr_branch = function_exists('auragold_ensure_table_branch_id_column') && auragold_ensure_table_branch_id_column($conn, 'tbl_purchase_returns');
    $hdr_branch    = function_exists('auragold_transaction_header_branch_id') ? auragold_transaction_header_branch_id() : 0;
    $eff_branch    = function_exists('auragold_effective_branch_id') ? auragold_effective_branch_id() : 0;
    $pr_dup_sql    = ($has_pr_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';
    
    // Get return data
    $return_no = esc($_POST['order_no'] ?? '');
    $supplier_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $supplier_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $against_type = esc($_POST['against_type'] ?? '');
    $against_id = isset($_POST['against_id']) ? (int)$_POST['against_id'] : 0;
    $currency = esc($_POST['currency'] ?? 'USD');
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_purchase_returns') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $return_date = trim($_POST['order_date'] ?? '') !== '' ? esc($_POST['order_date']) : date('Y-m-d');
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $ounce_rate = (float)($_POST['ounce_rate'] ?? 0);
    $unfix_dmd_gms = isset($_POST['dmd_gms_unfix']) ? 1 : 0;
    $unfix_metal = isset($_POST['metal_unfix']) ? 1 : 0;
    $unfix = isset($_POST['unfix']) ? 1 : 0;
    $credit_note = (float)($_POST['credit_note'] ?? 0);
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
    $return_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $is_update = ($return_id > 0);

    $old_return_no_snapshot = null;
    $pr_row_branch_id = 0;
    if ($is_update) {
        $___pr_prev = getRecord("SELECT return_no, branch_id FROM tbl_purchase_returns WHERE id = " . (int)$return_id . " LIMIT 1");
        if ($___pr_prev && !empty($___pr_prev['return_no'])) {
            $old_return_no_snapshot = $___pr_prev['return_no'];
        }
        if ($has_pr_branch && $___pr_prev) {
            $pr_row_branch_id = (int) ($___pr_prev['branch_id'] ?? 0);
            if (function_exists('auragold_branch_require_document_access')) {
                auragold_branch_require_document_access($conn, 'tbl_purchase_returns', $return_id);
            }
        }
    }
    
    // Validation
    if (empty($supplier_name)) {
        throw new Exception("Supplier name is required");
    }
    
    if (empty($return_no)) {
        $return_no = function_exists('getNextPurchaseReturnNo') ? esc(getNextPurchaseReturnNo($conn)) : 'PR-1';
    }

    // New return: bump until return_no is unique among active rows
    if (!$is_update) {
        $cfg = function_exists('getPurchaseReturnBillSeriesConfig') ? getPurchaseReturnBillSeriesConfig($conn) : ['prefix' => 'PR-', 'suffix' => '', 'start_count' => 1];
        $pr_active = auragold_doc_series_active_sql($conn, 'tbl_purchase_returns');
        $existing_return = getRecord("SELECT id FROM tbl_purchase_returns WHERE return_no = '$return_no'$pr_dup_sql" . $pr_active);
        $guard = 0;
        while ($existing_return && $guard < 5000) {
            $return_no = esc(function_exists('bumpPurchaseReturnNo') ? bumpPurchaseReturnNo($conn, $return_no, $cfg) : ('PR-' . ($guard + 2)));
            $existing_return = getRecord("SELECT id FROM tbl_purchase_returns WHERE return_no = '$return_no'$pr_dup_sql" . $pr_active);
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_purchase_returns', 'return_no']], $return_no);
        }
    }
    
    if ($is_update) {
        // Get current return number to check if it's changing
        $current_return = getRecord("SELECT return_no FROM tbl_purchase_returns WHERE id = $return_id");
        $current_return_no = $current_return ? $current_return['return_no'] : '';
        
        // Check if return_no is being changed and if it conflicts with another return
        if ($return_no !== $current_return_no) {
            $existing_return = getRecord("SELECT id FROM tbl_purchase_returns WHERE return_no = '$return_no' AND id != $return_id$pr_dup_sql" . auragold_doc_series_active_sql($conn, 'tbl_purchase_returns'));
            if ($existing_return) {
                throw new Exception("Return number '$return_no' already exists. Please use a different return number.");
            }
        }
        
        // Update existing return - escape strings to avoid SQL errors
        $return_no_esc = mysqli_real_escape_string($conn, $return_no);
        $supplier_name_esc = mysqli_real_escape_string($conn, $supplier_name);
        $against_of_esc = mysqli_real_escape_string($conn, $against_of);
        $ref_no_esc = mysqli_real_escape_string($conn, $ref_no);
        $sales_person_esc = mysqli_real_escape_string($conn, $sales_person);
        $due_date_esc = mysqli_real_escape_string($conn, $due_date);
        $group_name_esc = mysqli_real_escape_string($conn, $group_name);
        $comment_esc = mysqli_real_escape_string($conn, $comment);

        $return_no_update = ($return_no !== $current_return_no) ? "return_no = '$return_no_esc', " : "";
        $against_of_val = (trim($against_of) !== '') ? "'$against_of_esc'" : "NULL";
        $against_type_esc = mysqli_real_escape_string($conn, $against_type);
        $against_type_val = (trim($against_type) !== '') ? "'$against_type_esc'" : "NULL";
        $against_id_val = ($against_id > 0) ? (string)(int)$against_id : "NULL";
        $ref_no_val = (trim($ref_no) !== '') ? "'$ref_no_esc'" : "NULL";
        $sales_person_val = (trim($sales_person) !== '') ? "'$sales_person_esc'" : "NULL";
        $due_date_val = (trim($due_date) !== '') ? "'$due_date_esc'" : "NULL";
        $layaways_val = (trim($layaways) !== '' && (int)$layaways > 0) ? (int)$layaways : "NULL";
        $group_name_val = (trim($group_name) !== '') ? "'$group_name_esc'" : "NULL";
        $comment_val = (trim($comment) !== '') ? "'$comment_esc'" : "NULL";

        $sql = "UPDATE tbl_purchase_returns SET
                {$return_no_update}
                supplier_id = " . ($supplier_id > 0 ? (int)$supplier_id : 0) . ",
                supplier_name = '$supplier_name_esc',
                against_of = $against_of_val,
                against_type = $against_type_val,
                against_id = $against_id_val,
                currency = '" . mysqli_real_escape_string($conn, $currency) . "',
                $fx_update_sql
                ref_no = $ref_no_val,
                sales_person = $sales_person_val,
                return_date = '" . mysqli_real_escape_string($conn, $return_date) . "',
                due_date = $due_date_val,
                layaways_id = $layaways_val,
                fixing_type = '" . mysqli_real_escape_string($conn, $fixing_type) . "',
                ounce_rate = " . (float)$ounce_rate . ",
                unfix_dmd_gms = " . (int)$unfix_dmd_gms . ",
                unfix_metal = " . (int)$unfix_metal . ",
                unfix = " . (int)$unfix . ",
                previous_balance = " . (float)$previous_balance . ",
                previous_gold = " . (float)$previous_gold . ",
                previous_silver = " . (float)$previous_silver . ",
                subtotal = " . (float)$subtotal . ",
                additional_amt = " . (float)$additional_amt . ",
                net_total = " . (float)$net_total . ",
                discount_amt = " . (float)$discount_amt . ",
                grand_total = " . (float)$grand_total . ",
                advance_payment = " . (float)$advance_payment . ",
                metal_amt = " . (float)$metal_amt . ",
                round_off = " . (float)$round_off . ",
                credit_note = " . (float)$credit_note . ",
                paid_amt = " . (float)$paid_amt . ",
                balance_amt = " . (float)$balance_amt . ",
                group_name = $group_name_val,
                comment = $comment_val
                " . ($has_pr_branch && $eff_branch > 0 && $pr_row_branch_id === 0 ? ', branch_id = ' . (int) $eff_branch : '') . ",
                updated_at = NOW()
            WHERE id = " . (int)$return_id;

        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            // Check if it's a duplicate key error
            if (strpos($error, 'Duplicate entry') !== false) {
                throw new Exception("Return number '$return_no' already exists. Please use a different return number.");
            }
            throw new Exception("Return update failed: " . $error);
        }
        
        // Get old return items BEFORE deleting (to find matching stock records)
        $old_items = getList("SELECT id, product_id, product_characteristic_id, created_at FROM tbl_purchase_return_items WHERE return_id = $return_id");
        
        // Get return date for fallback deletion
        $return_record = getRecord("SELECT return_date FROM tbl_purchase_returns WHERE id = $return_id");
        $return_date = $return_record ? $return_record['return_date'] : '';
        
        // Delete old stock records that were created for this return
        // Match by product_id, product_characteristic_id, date, and stock_type='purchase_return'
        if (!empty($old_items)) {
            foreach ($old_items as $old_item) {
                $old_product_id = (int)$old_item['product_id'];
                $old_char_id = $old_item['product_characteristic_id'] ? (int)$old_item['product_characteristic_id'] : 'NULL';
                $old_date = date('Y-m-d', strtotime($old_item['created_at']));
                $old_timestamp = $old_item['created_at'];
                
                // Delete stock records that match this return item
                // Match by product_id, characteristic_id, date, and stock_type='purchase_return'
                // Also match by timestamp (within 5 minutes of item creation)
                if ($old_char_id === 'NULL') {
                    $delete_stock_sql = "
                        DELETE FROM tbl_stock 
                        WHERE product_id = $old_product_id 
                        AND product_characteristic_id IS NULL
                        AND DATE(created_at) = '$old_date'
                        AND stock_type = 'purchase_return'
                        AND ABS(TIMESTAMPDIFF(MINUTE, created_at, '$old_timestamp')) <= 5
                    ";
                } else {
                    $delete_stock_sql = "
                        DELETE FROM tbl_stock 
                        WHERE product_id = $old_product_id 
                        AND product_characteristic_id = $old_char_id
                        AND DATE(created_at) = '$old_date'
                        AND stock_type = 'purchase_return'
                        AND ABS(TIMESTAMPDIFF(MINUTE, created_at, '$old_timestamp')) <= 5
                    ";
                }
                mysqli_query($conn, $delete_stock_sql);
            }
        } elseif ($return_date) {
            // Fallback: If no old items found, delete stock records by return date and product_id
            $delete_stock_sql = "
                DELETE s FROM tbl_stock s
                INNER JOIN tbl_purchase_return_items pri ON (
                    s.product_id = pri.product_id 
                    AND (s.product_characteristic_id = pri.product_characteristic_id OR (s.product_characteristic_id IS NULL AND pri.product_characteristic_id IS NULL))
                    AND DATE(s.created_at) = DATE(pri.created_at)
                    AND ABS(TIMESTAMPDIFF(MINUTE, s.created_at, pri.created_at)) <= 5
                )
                WHERE pri.return_id = $return_id
                AND s.stock_type = 'purchase_return'
            ";
            mysqli_query($conn, $delete_stock_sql);
        }
        
        // Delete stock journal entries that reference these return items
        if (!empty($old_items)) {
            $old_item_ids = array_column($old_items, 'id');
            if (!empty($old_item_ids)) {
                $item_ids_str = implode(',', array_map('intval', $old_item_ids));
                mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE item_id IN ($item_ids_str)");
            }
        } else {
            // Fallback: Delete stock journal entries for all items in this return
            $all_item_ids = getList("SELECT id FROM tbl_purchase_return_items WHERE return_id = $return_id");
            if (!empty($all_item_ids)) {
                $item_ids_str = implode(',', array_map(function($item) { return (int)$item['id']; }, $all_item_ids));
                if ($item_ids_str) {
                    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE item_id IN ($item_ids_str)");
                }
            }
        }
        
        // Delete existing items and payments (after we've deleted stock journal entries)
        mysqli_query($conn, "DELETE FROM tbl_purchase_return_items WHERE return_id = $return_id");
        mysqli_query($conn, "DELETE FROM tbl_purchase_return_payments WHERE return_id = $return_id");
    } else {
        // Insert new return - build values safely to avoid SQL syntax errors
        $v_return_no = "'" . mysqli_real_escape_string($conn, $return_no) . "'";
        $v_supplier_id = $supplier_id > 0 ? (int)$supplier_id : 0;
        $v_supplier_name = "'" . mysqli_real_escape_string($conn, $supplier_name) . "'";
        $v_against_of = (trim($against_of) !== '') ? "'" . mysqli_real_escape_string($conn, $against_of) . "'" : "NULL";
        $v_against_type = (trim($against_type) !== '') ? "'" . mysqli_real_escape_string($conn, $against_type) . "'" : "NULL";
        $v_against_id = ($against_id > 0) ? (int)$against_id : "NULL";
        $v_currency = "'" . mysqli_real_escape_string($conn, $currency) . "'";
        $v_ref_no = (trim($ref_no) !== '') ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : "NULL";
        $v_sales_person = (trim($sales_person) !== '') ? "'" . mysqli_real_escape_string($conn, $sales_person) . "'" : "NULL";
        $return_date_safe = (trim($return_date) !== '') ? $return_date : date('Y-m-d');
        $v_return_date = "'" . mysqli_real_escape_string($conn, $return_date_safe) . "'";
        $v_due_date = (trim($due_date) !== '') ? "'" . mysqli_real_escape_string($conn, $due_date) . "'" : "NULL";
        $v_layaways_id = (trim($layaways) !== '' && (int)$layaways > 0) ? (int)$layaways : "NULL";
        $v_fixing_type = "'" . mysqli_real_escape_string($conn, $fixing_type) . "'";
        $v_group_name = (trim($group_name) !== '') ? "'" . mysqli_real_escape_string($conn, $group_name) . "'" : "NULL";
        $v_comment = (trim($comment) !== '') ? "'" . mysqli_real_escape_string($conn, $comment) . "'" : "NULL";

        $sql = "INSERT INTO tbl_purchase_returns (
                return_no, supplier_id, supplier_name, against_of, against_type, against_id, currency$fx_insert_col_sql, ref_no, sales_person,
                return_date, due_date, layaways_id, fixing_type, ounce_rate,
                unfix_dmd_gms, unfix_metal, unfix,
                previous_balance, previous_gold, previous_silver,
                subtotal, additional_amt, net_total, discount_amt,
                grand_total, advance_payment, metal_amt, round_off, credit_note,
                paid_amt, balance_amt, group_name, comment,
                " . ($has_pr_branch ? 'branch_id, ' : '') . "status, created_by, created_at
            ) VALUES (
                $v_return_no, $v_supplier_id, $v_supplier_name, $v_against_of, $v_against_type, $v_against_id,
                $v_currency$fx_insert_val_sql, $v_ref_no, $v_sales_person,
                $v_return_date, $v_due_date, $v_layaways_id,
                $v_fixing_type, " . (float)$ounce_rate . ",
                " . (int)$unfix_dmd_gms . ", " . (int)$unfix_metal . ", " . (int)$unfix . ",
                " . (float)$previous_balance . ", " . (float)$previous_gold . ", " . (float)$previous_silver . ",
                " . (float)$subtotal . ", " . (float)$additional_amt . ", " . (float)$net_total . ", " . (float)$discount_amt . ",
                " . (float)$grand_total . ", " . (float)$advance_payment . ", " . (float)$metal_amt . ", " . (float)$round_off . ", " . (float)$credit_note . ",
                " . (float)$paid_amt . ", " . (float)$balance_amt . ",
                $v_group_name, $v_comment,
                " . ($has_pr_branch ? ((int) $hdr_branch > 0 ? (int) $hdr_branch : 'NULL') . ', ' : '') . "'draft', " . (int)$user_id . ", NOW()
            )";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Return insert failed: " . mysqli_error($conn));
        }
        
        $return_id = mysqli_insert_id($conn);
    }
    
    // Save return items
    $items = [];
    if (isset($_POST['items'])) {
        if (is_string($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
        } else if (is_array($_POST['items'])) {
            $items = $_POST['items'];
        }
    }
    if (!empty($items) && is_array($items)) {
        $invoice_used_barcodes = [];
        foreach ($items as $item) {
            $active = isset($item['active']) ? (int)$item['active'] : 1;
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $rfid = esc($item['rfid'] ?? '');
            $voucher_type = esc($item['voucher_type'] ?? '');
            $barcode = '';
            $design_no = esc($item['design_no'] ?? '');
            $huid = esc($item['huid'] ?? '');
            $category_id = isset($item['category_id']) && $item['category_id'] ? (int)$item['category_id'] : NULL;
            $calculation_type = esc($item['calculation_type'] ?? '');
            $product_name = esc($item['product_name'] ?? '');
            $description = esc($item['description'] ?? '');
            $location_id = isset($item['location_id']) && $item['location_id'] ? (int)$item['location_id'] : NULL;
            $carat = esc($item['carat'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $pkt_wt = (float)($item['pkt_wt'] ?? 0);
            $pkt_less_wt = (float)($item['pkt_less_wt'] ?? 0);
            $requested_purity = (float)($item['requested_purity'] ?? 0);
            $requested_wt = (float)($item['requested_wt'] ?? 0);
            $gross_weight = (float)($item['gross_weight'] ?? 0);
            $less_weight = (float)($item['less_weight'] ?? 0);
            $gold_loss_wt = (float)($item['gold_loss_wt'] ?? 0);
            $gold_loss_value = (float)($item['gold_loss_value'] ?? 0);
            $setting_charge = (float)($item['setting_charge'] ?? 0);
            $net_weight = (float)($item['net_weight'] ?? 0);
            $purity = (float)($item['purity'] ?? 0);
            $purity_weight = (float)($item['purity_weight'] ?? 0);
            $pure_weight = (float)($item['pure_weight'] ?? $item['pure_wt'] ?? $purity_weight);
            $wastage_per = (float)($item['wastage_per'] ?? 0);
            $wastage_wt = (float)($item['wastage_wt'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? 0);
            $alloy_wt = (float)($item['alloy_wt'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $metal_value = (float)($item['metal_value'] ?? 0);
            $metal_cost = (float)($item['metal_cost'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $discount_type = esc($item['discount_type'] ?? '');
            $discount_per = (float)($item['discount_per'] ?? 0);
            $discount_amount = (float)($item['discount_amount'] ?? 0);
            $discount = (float)($item['discount'] ?? 0);
            $discount_type2 = esc($item['discount_type2'] ?? '');
            $discount_per2 = (float)($item['discount_per2'] ?? 0);
            $discount_amount2 = (float)($item['discount_amount2'] ?? 0);
            $discounted_amt = (float)($item['discounted_amt'] ?? 0);
            $discounted_per = (float)($item['discounted_per'] ?? 0);
            $making_type = esc($item['making_type'] ?? '');
            $making_rate = (float)($item['making_rate'] ?? 0);
            $making_discount_amt = (float)($item['making_discount_amt'] ?? 0);
            $making_amount = (float)($item['making_amount'] ?? 0);
            $making_actual_value = (float)($item['making_actual_value'] ?? 0);
            $making_cost = (float)($item['making_cost'] ?? 0);
            $min_price = (float)($item['min_price'] ?? 0);
            $minimum = (float)($item['minimum'] ?? 0);
            $stone_charge_type = esc($item['stone_charge_type'] ?? '');
            $stone_weight = (float)($item['stone_weight'] ?? 0);
            $stone_rate = (float)($item['stone_rate'] ?? 0);
            $stone_amount = (float)($item['stone_amount'] ?? 0);
            $stone_cost = (float)($item['stone_cost'] ?? 0);
            $diamond_amount = (float)($item['diamond_amount'] ?? 0);
            $purchase_amount = (float)($item['purchase_amount'] ?? 0);
            $sale_amount = (float)($item['sale_amount'] ?? 0);
            $sale_amount_with = (float)($item['sale_amount_with'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            $tax = (float)($item['tax'] ?? 0);
            $other_charge_type = esc($item['other_charge_type'] ?? '');
            $other_weight = (float)($item['other_weight'] ?? 0);
            $other_rate = (float)($item['other_rate'] ?? 0);
            $other_info = esc($item['other_info'] ?? '');
            $other_amount = (float)($item['other_amount'] ?? 0);
            $hallmark_amount = (float)($item['hallmark_amount'] ?? 0);
            $hallmark_rate = (float)($item['hallmark_rate'] ?? 0);
            $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
            $reverse = (float)($item['reverse'] ?? 0);
            
            if ($product_id > 0) {
                $barcode = esc(auragold_resolve_unique_invoice_item_barcode($conn, $item, $invoice_used_barcodes));

                // Insert return item with all fields matching table structure
                $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_purchase_return_items', $item);
                $item_sql = "
                    INSERT INTO tbl_purchase_return_items (
                        return_id, product_id, product_characteristic_id, barcode, 
                        product_name, description, carat, 
                        quantity, gross_weight, less_weight, 
                        purity, purity_weight, final_weight, 
                        net_weight, pure_weight, rate, 
                        making_amount, amount, tax_amount, 
                        net_amount, net_amt_with_tax, net_amt_weight,
                        diamond_weight, gemstone_weight, diamond_amount,
                        design_no{$ef_parts['columns']},
                        status, created_at
                    ) VALUES (
                        $return_id, $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                        " . ($barcode ? "'$barcode'" : "NULL") . ",
                        '$product_name',
                        " . ($description ? "'$description'" : "NULL") . ",
                        " . ($carat ? "'$carat'" : "NULL") . ",
                        $quantity,
                        $gross_weight, $less_weight,
                        $purity, $purity_weight,
                        $final_weight,
                        $net_weight, $pure_weight,
                        $rate,
                        $making_amount,
                        $amount,
                        $tax,
                        $net_amount, $net_amt_with_tax,
                        " . (isset($item['net_amt_weight']) ? (float)$item['net_amt_weight'] : 0) . ",
                        " . (isset($item['diamond_weight']) ? (float)$item['diamond_weight'] : 0) . ",
                        " . (isset($item['gemstone_weight']) ? (float)$item['gemstone_weight'] : 0) . ",
                        $diamond_amount,
                        " . ($design_no ? "'$design_no'" : "NULL") . "{$ef_parts['values']},
                        1, NOW()
                    )
                ";
                
                if (!mysqli_query($conn, $item_sql)) {
                    $error = mysqli_error($conn);
                    // If error is due to missing columns, provide helpful message
                    if (stripos($error, 'Unknown column') !== false) {
                        // Extract column name from error message
                        preg_match("/Unknown column ['\"]([^'\"]+)['\"]/i", $error, $matches);
                        $missing_column = $matches[1] ?? 'unknown';
                        
                        throw new Exception("Database column '$missing_column' is missing. Please run the SQL file: admin/sql/create_purchase_return_tables.sql to add all missing columns. Error: " . $error);
                    } else {
                        throw new Exception("Item insert failed: " . $error);
                    }
                }
                
                // Get the item ID that was just inserted
                $item_id = mysqli_insert_id($conn);
                
                // Add stock entry for purchase return (outward stock - negative)
                // Check if we have any weight (gross, net, or final) and product_id
                $has_weight = ($gross_weight > 0 || $net_weight > 0 || $final_weight > 0);
                
                if ($product_id > 0 && $has_weight) {
                    $branch_id = 0;
                    $metal_id = 0;
                    $stock_purity = $purity;
                    
                    // Try to get product characteristic details for branch and metal
                    if ($characteristic_id) {
                        $char_details = getRecord("
                            SELECT branch_id, metal_id, opening_purity 
                            FROM tbl_product_characteristics 
                            WHERE id = $characteristic_id AND status = 1
                        ");
                        
                        if ($char_details) {
                            $branch_id = (int)$char_details['branch_id'];
                            $metal_id = (int)$char_details['metal_id'];
                            if ($stock_purity <= 0) {
                                $stock_purity = (float)$char_details['opening_purity'];
                            }
                        }
                    }
                    
                    // If no characteristic or missing branch/metal, try to get from product
                    if ($branch_id <= 0 || $metal_id <= 0) {
                        $product_details = getRecord("
                            SELECT pc.branch_id, pc.metal_id, pc.opening_purity
                            FROM tbl_product_characteristics pc
                            WHERE pc.product_id = $product_id AND pc.status = 1
                            ORDER BY pc.id DESC
                            LIMIT 1
                        ");
                        
                        if ($product_details) {
                            if ($branch_id <= 0) {
                                $branch_id = (int)$product_details['branch_id'];
                            }
                            if ($metal_id <= 0) {
                                $metal_id = (int)$product_details['metal_id'];
                            }
                            if ($stock_purity <= 0) {
                                $stock_purity = (float)$product_details['opening_purity'];
                            }
                        }
                    }
                    
                    // Use the best available weight (prefer net_weight, then final_weight, then gross_weight)
                    // For returns, weight should be negative (outward stock)
                    $stock_weight = 0;
                    if ($net_weight > 0) {
                        $stock_weight = -$net_weight; // Negative for outward
                    } else if ($final_weight > 0) {
                        $stock_weight = -$final_weight; // Negative for outward
                    } else if ($gross_weight > 0) {
                        $stock_weight = -$gross_weight; // Negative for outward
                    }
                    
                    // Use the best available value (negative for returns)
                    $stock_value = 0;
                    if ($net_amount > 0) {
                        $stock_value = -$net_amount; // Negative for returns
                    } else if ($amount > 0) {
                        $stock_value = -$amount; // Negative for returns
                    }
                    
                    // Default values if missing
                    if ($stock_purity <= 0) {
                        $stock_purity = 100.0; // Default to 100% if not specified
                    }
                    if ($branch_id <= 0) {
                        $hbr = 0;
                        if ($return_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_purchase_returns', 'branch_id')) {
                            $hr = getRecord('SELECT branch_id FROM tbl_purchase_returns WHERE id = ' . (int) $return_id . ' LIMIT 1');
                            $hbr = (int) ($hr['branch_id'] ?? 0);
                        }
                        if ($hbr <= 0 && function_exists('auragold_transaction_header_branch_id')) {
                            $hbr = (int) auragold_transaction_header_branch_id();
                        }
                        $branch_id = $hbr > 0 ? $hbr : (($eff_branch > 0) ? $eff_branch : 0);
                        if ($branch_id <= 0 && function_exists('auragold_settings_main_branch_id')) {
                            $mid = (int) auragold_settings_main_branch_id();
                            if ($mid > 0) {
                                $branch_id = $mid;
                            }
                        }
                    }
                    if ($metal_id <= 0) {
                        // Try to get default metal from product
                        $default_metal = getRecord("
                            SELECT metal_id FROM tbl_product_characteristics 
                            WHERE product_id = $product_id AND status = 1 
                            ORDER BY id DESC LIMIT 1
                        ");
                        $metal_id = $default_metal ? (int)$default_metal['metal_id'] : 1;
                    }
                    
                    // Get the item ID that was just inserted
                    $item_id = mysqli_insert_id($conn);
                    
                    // Insert stock entry with stock_type='purchase_return' (outward stock)
                    // Note: If tbl_stock doesn't have barcode/reference columns, we'll join with purchase_return_items in queries
                    $stock_sql = "
                        INSERT INTO tbl_stock (
                            product_id,
                            product_characteristic_id,
                            branch_id,
                            metal_id,
                            opening_weight,
                            opening_purity,
                            opening_qty,
                            final_weight,
                            rate,
                            value,
                            current_weight,
                            current_qty,
                            stock_type,
                            transaction_date,
                            created_at
                        ) VALUES (
                            $product_id,
                            " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                            $branch_id,
                            $metal_id,
                            $stock_weight,
                            $stock_purity,
                            -$quantity,
                            " . ($final_weight > 0 ? -$final_weight : $stock_weight) . ",
                            $rate,
                            $stock_value,
                            $stock_weight,
                            -$quantity,
                            'purchase_return',
                            '$return_date',
                            NOW()
                        )
                    ";
                    
                    if (!mysqli_query($conn, $stock_sql)) {
                        throw new Exception("Stock insert failed: " . mysqli_error($conn) . " | SQL: " . $stock_sql);
                    }

                    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
                    $sj_no_pr = 'PR' . (int) $return_id . 'I' . (int) $item_id;
                    if (strlen($sj_no_pr) > 48) {
                        $sj_no_pr = 'Q' . (int) $return_id . 'x' . (int) $item_id;
                    }
                    auragold_stock_history_audit_insert_row($conn, [
                        'sj_invoice_no' => $sj_no_pr,
                        'item_id' => 0,
                        'invoice_id' => 0,
                        'invoice_no' => $return_no,
                        'sj_date' => $return_date,
                        'barcode' => $barcode,
                        'product_id' => $product_id,
                        'product_characteristic_id' => $characteristic_id ? (int) $characteristic_id : 0,
                        'product_name' => $product_name,
                        'metal_id' => $metal_id,
                        'metal_type' => auragold_stock_history_metal_type($conn, $metal_id),
                        'quantity' => $quantity,
                        'gross_weight' => $gross_weight,
                        'less_weight' => $less_weight,
                        'net_weight' => $net_weight,
                        'purity' => $purity,
                        'purity_weight' => $purity_weight,
                        'pure_weight' => $pure_weight,
                        'final_weight' => $final_weight,
                        'rate' => $rate,
                        'amount' => $amount,
                        'making_amount' => $making_amount,
                        'tax_amount' => $tax,
                        'net_amount' => $net_amount,
                        'net_amt_with_tax' => $net_amt_with_tax,
                        'rfid_code' => '',
                        'voucher_type' => 'Purchase Return',
                        'design_no' => $design_no,
                        'category' => '',
                        'comment' => 'auragold_doc|src=pr|rid=' . (int) $return_id . '|pri=' . (int) $item_id . '|',
                    ]);
                    
                    // Store the relationship: Update stock with reference to invoice item
                    // We'll use the created_at timestamp to match stock with invoice items in queries
                    // Alternatively, we can add a reference column if needed
                } else {
                    // Log why stock wasn't added (for debugging)
                    $reason = [];
                    if ($product_id <= 0) $reason[] = "product_id missing";
                    if (!$has_weight) $reason[] = "no weight";
                    error_log("Stock not added for item: " . implode(", ", $reason) . " | Product ID: $product_id | Gross: $gross_weight | Net: $net_weight | Final: $final_weight");
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
        purchase_return_validate_metal_exchange_payments($conn, $payments);
        $__pr_me_has_ref = auragold_metal_exchange_document_init($conn, $is_update, (int) $return_id, 'purchase_return_metal_exchange');
        $__pr_bn = trim((string) ($_POST['order_no'] ?? ''));

        foreach ($payments as $pay_seq => $payment) {
            $payment_type = esc($payment['payment_type'] ?? '');
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $transaction_no = esc($payment['transaction_no'] ?? '');
            $cheque_date = isset($payment['cheque_date']) && $payment['cheque_date'] ? esc($payment['cheque_date']) : NULL;
            $purity_carat = esc($payment['purity_carat'] ?? '');
            $current_order_amount = (float)($payment['amount'] ?? 0);
            $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
            $amount = $current_order_amount + $previous_balance_amount; // Total amount (current order + previous balance)
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $quantity = (float)($payment['quantity'] ?? 0);

            if (auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                // Try to insert with previous_balance_amount column (if it exists)
                $payment_sql = "
                        INSERT INTO tbl_purchase_return_payments (
                            return_id, payment_type, deposit_into, transaction_no,
                            cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity,
                            status, created_at
                        ) VALUES (
                            $return_id, '$payment_type',
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
                    // Check for various error messages related to missing column
                    if (stripos($error, 'previous_balance_amount') !== false ||
                                stripos($error, 'Unknown column') !== false ||
                                stripos($error, "field list") !== false) {
                        // Column doesn't exist, insert without it (will need to add column to table)
                        $payment_sql = "
                                INSERT INTO tbl_purchase_return_payments (
                                    return_id, payment_type, deposit_into, transaction_no,
                                    cheque_date, purity_carat, amount, diamond_category, quantity,
                                    status, created_at
                                ) VALUES (
                                    $return_id, '$payment_type',
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

            $pm_saved_pr = auragold_payment_merge_stored_details($payment);
            $__pr_rd = substr(trim(str_replace('\\', '', (string) $return_date)), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__pr_rd)) {
                $__pr_rd = date('Y-m-d');
            }
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'purchase_return_metal_exchange',
                (int) $return_id,
                $__pr_bn !== '' ? $__pr_bn : trim(str_replace('\\', '', (string) $return_no)),
                $__pr_rd,
                $pm_saved_pr,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $__pr_me_has_ref,
                'Purchase Return — Metal Exchange',
                'pr_me',
                'PR-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }

    $against_type_trim = trim((string)$against_type);
    $is_pur_quot = (stripos($against_type_trim, 'quotation') !== false);
    $chk_pend = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotation_items LIKE 'pending_qty'");
    $has_qq_pending = ($chk_pend && mysqli_num_rows($chk_pend) > 0);
    if ($chk_pend) {
        mysqli_free_result($chk_pend);
    }
    if ($is_pur_quot && $against_id > 0 && $has_qq_pending && !empty($items) && is_array($items) && !$is_update) {
        foreach ($items as $it) {
            $srcId = (int)($it['quotation_item_id'] ?? $it['purchase_quotation_item_id'] ?? $it['source_against_item_id'] ?? 0);
            $rqty = (float)($it['quantity'] ?? 0);
            if ($srcId <= 0 || $rqty <= 0) {
                continue;
            }
            $row = getRecord("SELECT id, quantity, pending_qty, returned_qty FROM tbl_purchase_quotation_items WHERE id = $srcId AND quotation_id = " . (int)$against_id . " LIMIT 1");
            if (!$row) {
                throw new Exception('Invalid quotation line for return.');
            }
            $pend = isset($row['pending_qty']) && $row['pending_qty'] !== null && $row['pending_qty'] !== ''
                ? (float)$row['pending_qty']
                : (float)($row['quantity'] ?? 0);
            $ret = (float)($row['returned_qty'] ?? 0);
            if ($rqty > $pend + 0.0001) {
                throw new Exception('Return quantity cannot exceed pending quantity for one or more lines.');
            }
            $newRet = $ret + $rqty;
            $newPend = max(0, $pend - $rqty);
            mysqli_query($conn, "UPDATE tbl_purchase_quotation_items SET returned_qty = $newRet, pending_qty = $newPend WHERE id = $srcId");
        }
        $hdrMk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotations LIKE 'making_amount'");
        if ($hdrMk && mysqli_num_rows($hdrMk) > 0) {
            mysqli_free_result($hdrMk);
            $mkRed = 0.0;
            foreach ($items as $it) {
                $mkRed += (float)($it['making_amount'] ?? 0);
            }
            if ($mkRed > 0.00001) {
                mysqli_query($conn, "UPDATE tbl_purchase_quotations SET making_amount = GREATEST(0, IFNULL(making_amount,0) - $mkRed) WHERE id = " . (int)$against_id);
            }
        } elseif ($hdrMk) {
            mysqli_free_result($hdrMk);
        }
    }
    
    $metal_amt_post = (float)($_POST['metal_amt'] ?? 0);

    auragold_ensure_customer_ledger_branch_column($conn);
    $pr_ledger_branch_id = 0;
    if ($return_id > 0 && $has_pr_branch) {
        $pbr = getRecord('SELECT branch_id FROM tbl_purchase_returns WHERE id = ' . (int) $return_id . ' LIMIT 1');
        $pr_ledger_branch_id = (int) ($pbr['branch_id'] ?? 0);
    }
    if ($pr_ledger_branch_id <= 0) {
        $pr_ledger_branch_id = $pr_row_branch_id > 0 ? $pr_row_branch_id : (($hdr_branch > 0) ? $hdr_branch : (($eff_branch > 0) ? $eff_branch : 0));
    }
    $ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = '';
    if ($ledger_has_branch_col) {
        $ledger_branch_sql_val = ', ' . ($pr_ledger_branch_id > 0 ? (string) (int) $pr_ledger_branch_id : 'NULL');
    }
    $ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql') ? auragold_customer_ledger_branch_scope_sql($conn, $pr_ledger_branch_id) : '';

    require __DIR__ . '/includes/purchase_return_post_ledger.inc.php';
    
    if ((int) $return_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'purchase_return', (int) $return_id, $return_no, $return_date);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Purchase Return',
        'verb' => $is_update ? 'updated' : 'created',
        'number' => $return_no,
        'party' => $supplier_name,
        'doc_date' => $return_date,
        'due_date' => $due_date,
        'ref_id' => (int) $return_id,
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Purchase return saved successfully',
        'return_id' => $return_id,
        'return_no' => $return_no,
        'invoice_id' => $return_id, // For compatibility
        'invoice_no' => $return_no, // For compatibility
        'order_id' => $return_id, // For compatibility
        'order_no' => $return_no, // For compatibility
        'new_barcodes' => $metal_exchange_barcodes_out,
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

