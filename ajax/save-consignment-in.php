<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Master data (accept consignment_* keys and purchase-style order_* / order_date from shared UI)
    $consignment_id = isset($_POST['consignment_id']) ? (int)$_POST['consignment_id'] : 0;
    if ($consignment_id <= 0 && isset($_POST['order_id'])) {
        $consignment_id = (int)$_POST['order_id'];
    }
    $ci_was_update = ($consignment_id > 0);
    $consignment_no = isset($_POST['consignment_no']) ? esc($_POST['consignment_no']) : '';
    if ($consignment_no === '' && isset($_POST['order_no'])) {
        $consignment_no = esc($_POST['order_no']);
    }
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = isset($_POST['customer_name']) ? esc($_POST['customer_name']) : '';
    $consignment_date = isset($_POST['consignment_date']) ? esc($_POST['consignment_date']) : date('Y-m-d');
    if (isset($_POST['order_date']) && trim((string)$_POST['order_date']) !== '') {
        $consignment_date = esc($_POST['order_date']);
    }
    $ref_no = isset($_POST['ref_no']) ? esc($_POST['ref_no']) : '';
    $against_of = isset($_POST['against_of']) ? esc($_POST['against_of']) : '';
    $currency = isset($_POST['currency']) ? esc($_POST['currency']) : 'AED';
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_consignment_in') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $fixing_type = isset($_POST['fixing_type']) ? esc($_POST['fixing_type']) : 'Standard';
    $sales_person = isset($_POST['sales_person']) ? esc($_POST['sales_person']) : '';
    $previous_balance = isset($_POST['previous_balance']) ? (float)$_POST['previous_balance'] : 0;
    $previous_gold = isset($_POST['previous_gold']) ? (float)$_POST['previous_gold'] : 0;
    $previous_silver = isset($_POST['previous_silver']) ? (float)$_POST['previous_silver'] : 0;
    $gross_total = isset($_POST['gross_total']) ? (float)$_POST['gross_total'] : 0;
    if ($gross_total <= 0 && isset($_POST['subtotal'])) {
        $gross_total = (float)$_POST['subtotal'];
    }
    if ($gross_total <= 0 && isset($_POST['net_total'])) {
        $gross_total = (float)$_POST['net_total'];
    }
    $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
    if ($discount_amount <= 0 && isset($_POST['discount_amt'])) {
        $discount_amount = (float)$_POST['discount_amt'];
    }
    $tax_amount = isset($_POST['tax_amount']) ? (float)$_POST['tax_amount'] : 0;
    $grand_total = isset($_POST['grand_total']) ? (float)$_POST['grand_total'] : 0;
    $comment = isset($_POST['comment']) ? esc($_POST['comment']) : '';
    $created_by = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1);

    $ci_cfg = function_exists('getConsignmentInBillSeriesConfig') ? getConsignmentInBillSeriesConfig($conn) : ['prefix' => 'CI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    
    // Reference to consignment out (if coming from memo flow)
    $consignment_out_id = isset($_POST['consignment_out_id']) ? (int)$_POST['consignment_out_id'] : 0;
    
    // Items JSON
    $items_json = isset($_POST['items']) ? $_POST['items'] : '[]';
    $items = json_decode($items_json, true);
    if (!is_array($items)) {
        $items = [];
    }

    $payments_ci = isset($_POST['payments']) ? $_POST['payments'] : '[]';
    if (is_string($payments_ci)) {
        $payments_ci = json_decode($payments_ci, true);
    }
    if (!is_array($payments_ci)) {
        $payments_ci = [];
    }
    $metal_exchange_barcodes_out = [];

    if ($tax_amount <= 0.000001 && !empty($items)) {
        foreach ($items as $cit) {
            $tax_amount += (float)($cit['tax'] ?? $cit['tax_amount'] ?? 0);
        }
    }

    // Validation
    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }
    
    if (empty($items)) {
        throw new Exception('At least one item is required');
    }

    // Calculate totals from items
    $total_quantity = 0;
    $total_gross_weight = 0;
    $total_net_weight = 0;
    $total_pure_weight = 0;
    
    foreach ($items as $item) {
        $total_quantity += (int)($item['quantity'] ?? 1);
        $total_gross_weight += (float)($item['gross_weight'] ?? 0);
        $total_net_weight += (float)($item['net_weight'] ?? 0);
        $total_pure_weight += (float)($item['pure_weight'] ?? $item['purity_weight'] ?? 0);
    }

    // Check tbl_stock structure
    $tbl_stock_has_barcode = false;
    $bc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
    if ($bc && mysqli_num_rows($bc) > 0) {
        $tbl_stock_has_barcode = true;
        mysqli_free_result($bc);
    }

    // Generate consignment number if new (Bill Series: getNextConsignmentInNo; legacy CI-1, CI-2)
    if ($consignment_id == 0) {
        $consignment_no = trim((string)$consignment_no);
        if ($consignment_no !== '') {
            $cn_esc = mysqli_real_escape_string($conn, $consignment_no);
            $existing = getRecord("SELECT id FROM tbl_consignment_in WHERE consignment_no = '$cn_esc'" . auragold_doc_series_active_sql($conn, 'tbl_consignment_in'));
            if ($existing) {
                throw new Exception('Consignment number already exists: ' . $consignment_no);
            }
        } else {
            $consignment_no = function_exists('getNextConsignmentInNo') ? getNextConsignmentInNo($conn) : 'CI-1';
        }
        $tries = 0;
        $ci_active = auragold_doc_series_active_sql($conn, 'tbl_consignment_in');
        while ($tries < 20) {
            $cn_chk = mysqli_real_escape_string($conn, $consignment_no);
            $exists = getRecord("SELECT id FROM tbl_consignment_in WHERE consignment_no = '$cn_chk'" . $ci_active);
            if (!$exists) {
                break;
            }
            $consignment_no = function_exists('bumpConsignmentInNo') ? bumpConsignmentInNo($conn, $consignment_no, $ci_cfg) : ('CI-' . (string)(time() % 1000000));
            $tries++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_consignment_in', 'consignment_no']], $consignment_no);
        }
        
        $insert_master = "
            INSERT INTO tbl_consignment_in (
                consignment_no, consignment_out_id, customer_id, customer_name, consignment_date, ref_no, against_of,
                currency$fx_insert_col_sql, fixing_type, sales_person, previous_balance, previous_gold, previous_silver,
                gross_total, discount_amount, tax_amount, grand_total, 
                total_quantity, total_gross_weight, total_net_weight, total_pure_weight,
                comment, status, created_by, created_at
            ) VALUES (
                '$consignment_no',
                " . ($consignment_out_id > 0 ? $consignment_out_id : 'NULL') . ",
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$customer_name',
                '$consignment_date',
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                " . ($against_of ? "'$against_of'" : 'NULL') . ",
                '$currency'$fx_insert_val_sql,
                '$fixing_type',
                " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                $previous_balance,
                $previous_gold,
                $previous_silver,
                $gross_total,
                $discount_amount,
                $tax_amount,
                $grand_total,
                $total_quantity,
                $total_gross_weight,
                $total_net_weight,
                $total_pure_weight,
                " . ($comment ? "'$comment'" : 'NULL') . ",
                'active',
                $created_by,
                NOW()
            )
        ";
        
        if (!mysqli_query($conn, $insert_master)) {
            throw new Exception('Error creating consignment: ' . mysqli_error($conn));
        }
        
        $consignment_id = mysqli_insert_id($conn);
        
    } else {
        // Update existing record
        $update_master = "
            UPDATE tbl_consignment_in SET
                customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                customer_name = '$customer_name',
                consignment_date = '$consignment_date',
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                currency = '$currency', $fx_update_sql
                fixing_type = '$fixing_type',
                sales_person = " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                gross_total = $gross_total,
                discount_amount = $discount_amount,
                tax_amount = $tax_amount,
                grand_total = $grand_total,
                total_quantity = $total_quantity,
                total_gross_weight = $total_gross_weight,
                total_net_weight = $total_net_weight,
                total_pure_weight = $total_pure_weight,
                comment = " . ($comment ? "'$comment'" : 'NULL') . ",
                updated_at = NOW()
            WHERE id = $consignment_id
        ";
        
        if (!mysqli_query($conn, $update_master)) {
            throw new Exception('Error updating consignment: ' . mysqli_error($conn));
        }
        
        // Reverse stock for old items before deleting (undo the inward)
        if ($tbl_stock_has_barcode) {
            $old_items = getList("SELECT * FROM tbl_consignment_in_items WHERE consignment_id = $consignment_id");
            foreach ($old_items as $old_item) {
                $old_barcode = $old_item['barcode'] ?? '';
                $old_qty = (int)($old_item['quantity'] ?? 1);
                $old_weight = (float)($old_item['net_weight'] ?? 0);
                
                if ($old_barcode !== '') {
                    $old_barcode_esc = mysqli_real_escape_string($conn, $old_barcode);
                    mysqli_query($conn, "UPDATE tbl_stock SET current_qty = GREATEST(0, current_qty - $old_qty), current_weight = GREATEST(0, current_weight - $old_weight) WHERE barcode = '$old_barcode_esc'");
                }
            }
        }
        
        // Delete old items
        mysqli_query($conn, "DELETE FROM tbl_consignment_in_items WHERE consignment_id = $consignment_id");
    }

    // Insert items and add stock (Stock In / Inward)
    foreach ($items as $item) {
        $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
        $characteristic_id = isset($item['product_characteristic_id']) ? (int)$item['product_characteristic_id'] : 0;
        if (!$characteristic_id) {
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : 0;
        }
        $barcode = isset($item['barcode']) ? esc($item['barcode']) : '';
        $product_name = isset($item['product_name']) ? esc($item['product_name']) : '';
        $design_no = isset($item['design_no']) ? esc($item['design_no']) : '';
        $huid_no = isset($item['huid_no']) ? esc($item['huid_no']) : '';
        $category = isset($item['category']) ? esc($item['category']) : '';
        $calculation_mode = isset($item['calculation_mode']) ? esc($item['calculation_mode']) : '';
        if ($calculation_mode === '' && isset($item['calculation_type'])) {
            $calculation_mode = esc($item['calculation_type']);
        }
        $location = isset($item['location']) ? esc($item['location']) : '';
        $metal_id = isset($item['metal_id']) ? (int)$item['metal_id'] : 0;
        $carat = isset($item['carat']) ? esc($item['carat']) : '';
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $gross_weight = isset($item['gross_weight']) ? (float)$item['gross_weight'] : 0;
        $less_weight = isset($item['less_weight']) ? (float)$item['less_weight'] : 0;
        $net_weight = isset($item['net_weight']) ? (float)$item['net_weight'] : 0;
        $purity = isset($item['purity']) ? (float)$item['purity'] : 0;
        $purity_weight = isset($item['purity_weight']) ? (float)$item['purity_weight'] : 0;
        $wastage_percent = isset($item['wastage_percent']) ? (float)$item['wastage_percent'] : 0;
        $wastage_weight = isset($item['wastage_weight']) ? (float)$item['wastage_weight'] : 0;
        $final_weight = isset($item['final_weight']) ? (float)$item['final_weight'] : 0;
        $pure_weight = isset($item['pure_weight']) ? (float)$item['pure_weight'] : 0;
        $rate = isset($item['rate']) ? (float)$item['rate'] : 0;
        $metal_value = isset($item['metal_value']) ? (float)$item['metal_value'] : 0;
        $amount = isset($item['amount']) ? (float)$item['amount'] : 0;
        $making_type = isset($item['making_type']) ? esc($item['making_type']) : '';
        $making_rate = isset($item['making_rate']) ? (float)$item['making_rate'] : 0;
        $making_amount = isset($item['making_amount']) ? (float)$item['making_amount'] : 0;
        $stone_weight = isset($item['stone_weight']) ? (float)$item['stone_weight'] : 0;
        $stone_rate = isset($item['stone_rate']) ? (float)$item['stone_rate'] : 0;
        $stone_amount = isset($item['stone_amount']) ? (float)$item['stone_amount'] : 0;
        $diamond_amount = isset($item['diamond_amount']) ? (float)$item['diamond_amount'] : 0;
        $other_amount = isset($item['other_amount']) ? (float)$item['other_amount'] : 0;
        $discount_percent = isset($item['discount_percent']) ? (float)$item['discount_percent'] : 0;
        $item_discount_amount = isset($item['discount_amount']) ? (float)$item['discount_amount'] : 0;
        $tax_percent = isset($item['tax_percent']) ? (float)$item['tax_percent'] : 0;
        $item_tax_amount = isset($item['tax_amount']) ? (float)$item['tax_amount'] : 0;
        $net_amount = isset($item['net_amount']) ? (float)$item['net_amount'] : 0;
        $net_amt_with_tax = isset($item['net_amt_with_tax']) ? (float)$item['net_amt_with_tax'] : 0;
        
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_consignment_in_items', $item);
        $insert_item = "
            INSERT INTO tbl_consignment_in_items (
                consignment_id, product_id, product_characteristic_id, barcode, product_name,
                design_no, huid_no, category, calculation_mode, location, metal_id, carat,
                quantity, gross_weight, less_weight, net_weight, purity, purity_weight,
                wastage_percent, wastage_weight, final_weight, pure_weight, rate, metal_value, amount,
                making_type, making_rate, making_amount, stone_weight, stone_rate, stone_amount,
                diamond_amount, other_amount, discount_percent, discount_amount, tax_percent, tax_amount,
                net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at
            ) VALUES (
                $consignment_id,
                " . ($product_id > 0 ? $product_id : 'NULL') . ",
                " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
                " . ($barcode ? "'$barcode'" : 'NULL') . ",
                " . ($product_name ? "'$product_name'" : 'NULL') . ",
                " . ($design_no ? "'$design_no'" : 'NULL') . ",
                " . ($huid_no ? "'$huid_no'" : 'NULL') . ",
                " . ($category ? "'$category'" : 'NULL') . ",
                " . ($calculation_mode ? "'$calculation_mode'" : 'NULL') . ",
                " . ($location ? "'$location'" : 'NULL') . ",
                " . ($metal_id > 0 ? $metal_id : 'NULL') . ",
                " . ($carat ? "'$carat'" : 'NULL') . ",
                $quantity,
                $gross_weight,
                $less_weight,
                $net_weight,
                $purity,
                $purity_weight,
                $wastage_percent,
                $wastage_weight,
                $final_weight,
                $pure_weight,
                $rate,
                $metal_value,
                $amount,
                " . ($making_type ? "'$making_type'" : 'NULL') . ",
                $making_rate,
                $making_amount,
                $stone_weight,
                $stone_rate,
                $stone_amount,
                $diamond_amount,
                $other_amount,
                $discount_percent,
                $item_discount_amount,
                $tax_percent,
                $item_tax_amount,
                $net_amount,
                $net_amt_with_tax{$ef_parts['values']},
                1,
                NOW()
            )
        ";
        
        if (!mysqli_query($conn, $insert_item)) {
            throw new Exception('Error inserting item: ' . mysqli_error($conn));
        }
        
        // Add stock (Stock In / Inward)
        if ($barcode !== '' && $tbl_stock_has_barcode) {
            $barcode_esc = mysqli_real_escape_string($conn, $barcode);
            $add_weight = $net_weight > 0 ? $net_weight : $gross_weight;
            
            // Check if stock record exists for this barcode
            $existing_stock = getRecord("SELECT id FROM tbl_stock WHERE barcode = '$barcode_esc' AND status = 1 ORDER BY id DESC LIMIT 1");
            
            if ($existing_stock) {
                // Update existing stock: increase current_qty and current_weight
                $stock_update = "UPDATE tbl_stock SET 
                    current_qty = current_qty + $quantity, 
                    current_weight = current_weight + $add_weight 
                    WHERE barcode = '$barcode_esc' AND status = 1";
                mysqli_query($conn, $stock_update);
            } else {
                // Create new stock entry for this barcode
                $has_reference_cols = false;
                $ref_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
                if ($ref_check && mysqli_num_rows($ref_check) >= 2) {
                    $has_reference_cols = true;
                    mysqli_free_result($ref_check);
                }
                
                $stock_value = $add_weight * $rate;
                
                if ($has_reference_cols) {
                    $inward_sql = "
                        INSERT INTO tbl_stock (
                            product_id, product_characteristic_id, barcode, branch_id, metal_id,
                            opening_weight, opening_purity, opening_qty, final_weight, rate, value,
                            current_weight, current_qty, stock_type, transaction_date, status, created_at,
                            reference_id, reference_type
                        ) VALUES (
                            " . ($product_id > 0 ? $product_id : 'NULL') . ",
                            " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
                            '$barcode_esc',
                            1,
                            " . ($metal_id > 0 ? $metal_id : '1') . ",
                            $add_weight,
                            $purity,
                            $quantity,
                            $add_weight,
                            $rate,
                            $stock_value,
                            $add_weight,
                            $quantity,
                            'inward',
                            '$consignment_date',
                            1,
                            NOW(),
                            $consignment_id,
                            'consignment_in'
                        )
                    ";
                } else {
                    $inward_sql = "
                        INSERT INTO tbl_stock (
                            product_id, product_characteristic_id, barcode, branch_id, metal_id,
                            opening_weight, opening_purity, opening_qty, final_weight, rate, value,
                            current_weight, current_qty, stock_type, transaction_date, status, created_at
                        ) VALUES (
                            " . ($product_id > 0 ? $product_id : 'NULL') . ",
                            " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
                            '$barcode_esc',
                            1,
                            " . ($metal_id > 0 ? $metal_id : '1') . ",
                            $add_weight,
                            $purity,
                            $quantity,
                            $add_weight,
                            $rate,
                            $stock_value,
                            $add_weight,
                            $quantity,
                            'inward',
                            '$consignment_date',
                            1,
                            NOW()
                        )
                    ";
                }
                @mysqli_query($conn, $inward_sql);
            }

            // Do not INSERT into tbl_stock_journal here: schema has no transaction_type/transaction_id; FKs tie
            // invoice_id/item_id to purchase tables only. tbl_stock above records the inward movement.
            
        } elseif ($characteristic_id > 0) {
            // Fallback: add to product_characteristics if no barcode
            @mysqli_query($conn, "UPDATE tbl_product_characteristics SET quantity = quantity + $quantity WHERE id = $characteristic_id");
        }
    }

    if (!empty($payments_ci)) {
        foreach ($payments_ci as $__pci) {
            if (!is_array($__pci)) {
                continue;
            }
            $__mci = auragold_payment_merge_stored_details($__pci);
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mci)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mci);
        }
        $___cin_me_has_ref = auragold_metal_exchange_document_init($conn, $ci_was_update, (int) $consignment_id, 'consignment_in_metal_exchange');
        $__ci_plain_no = trim((string) $consignment_no);
        $__ci_dt = substr(trim((string) $consignment_date), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__ci_dt)) {
            $__ci_dt = date('Y-m-d');
        }
        foreach ($payments_ci as $pay_seq => $payment) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                continue;
            }
            $___pm_ci = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'consignment_in_metal_exchange',
                (int) $consignment_id,
                $__ci_plain_no,
                $__ci_dt,
                $___pm_ci,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___cin_me_has_ref,
                'Consignment In — Metal Exchange',
                'cin_me',
                'CIN-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Consignment In',
        'verb' => $ci_was_update ? 'updated' : 'created',
        'number' => $consignment_no,
        'party' => $customer_name,
        'doc_date' => $consignment_date,
        'due_date' => '',
        'ref_id' => (int) $consignment_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Consignment In saved successfully',
        'consignment_id' => $consignment_id,
        'invoice_id' => $consignment_id,
        'order_id' => $consignment_id,
        'consignment_no' => $consignment_no,
        'invoice_no' => $consignment_no,
        'order_no' => $consignment_no,
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
