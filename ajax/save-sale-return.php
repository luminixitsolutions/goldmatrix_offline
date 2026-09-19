<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/next_product_stock_barcode.php';
require_once __DIR__ . '/../includes/invoice_item_unique_barcode.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

if (!function_exists('sale_return_validate_metal_exchange_payments')) {
    /** @param array<int, array<string, mixed>> $payments */
    function sale_return_validate_metal_exchange_payments($conn, array $payments): void
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

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    $metal_exchange_barcodes_out = [];

    $has_sr_branch   = auragold_ensure_table_branch_id_column($conn, 'tbl_sale_returns');
    $hdr_branch      = auragold_transaction_header_branch_id();
    $eff_branch      = auragold_effective_branch_id();
    $sr_dup_sql      = ($has_sr_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';

    // Get return data
    $return_no = esc($_POST['order_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'USD');
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_sale_returns') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $rate = isset($_POST['rate']) ? (float)$_POST['rate'] : 1.000000;
    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $return_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $ounce_rate = (float)($_POST['ounce_rate'] ?? 0);
    $unfix_dmd_gms = isset($_POST['unfix_dmd_gms']) ? 1 : 0;
    $unfix_metal = isset($_POST['unfix_metal']) ? 1 : 0;
    $unfix = isset($_POST['unfix']) ? 1 : 0;
    $comment = esc($_POST['comment'] ?? '');
    
    // Summary values
    $previous_balance = (float)($_POST['previous_balance'] ?? 0);
    $previous_gold = (float)($_POST['previous_gold'] ?? 0);
    $previous_silver = (float)($_POST['previous_silver'] ?? 0);
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $credit_note = (float)($_POST['credit_note'] ?? 0);
    
    if (empty($return_no)) {
        $return_no = function_exists('getNextSaleReturnNo') ? esc(getNextSaleReturnNo($conn)) : 'SR-1';
    }

    if (empty($customer_name)) {
        throw new Exception("Customer name is required");
    }
    
    // Against Of: document ref (e.g. SI-1, SQ-1), type (Direct/Sale Invoice/Sale Quotation), and source id
    $against_type = esc($_POST['against_type'] ?? '');
    $against_id = isset($_POST['against_id']) ? (int)$_POST['against_id'] : 0;
    $payment_comments = isset($_POST['payment_comments']) ? $_POST['payment_comments'] : '';
    if (is_string($payment_comments) && (strpos(trim($payment_comments), '[') === 0 || trim($payment_comments) === '')) {
        $payment_comments = trim($payment_comments) === '' ? '[]' : $payment_comments;
    } else {
        $payment_comments = '[]';
    }
    
    // Check if return already exists (accept order_id for edit from form)
    $return_id = isset($_POST['return_id']) ? (int)$_POST['return_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);
    $is_new_return = ($return_id <= 0);

    $old_return_no_snapshot = null;
    $sr_row_branch_id = 0;
    if ($return_id > 0) {
        $___prev_row = getRecord("SELECT return_no, branch_id FROM tbl_sale_returns WHERE id = $return_id LIMIT 1");
        if ($___prev_row && !empty($___prev_row['return_no'])) {
            $old_return_no_snapshot = $___prev_row['return_no'];
        }
        if ($has_sr_branch && $___prev_row) {
            $sr_row_branch_id = (int) ($___prev_row['branch_id'] ?? 0);
            auragold_branch_require_document_access($conn, 'tbl_sale_returns', $return_id);
        }
    }

    // New return: bump until return_no is unique among active rows
    if ($is_new_return) {
        $cfg = function_exists('getSalesReturnBillSeriesConfig') ? getSalesReturnBillSeriesConfig($conn) : ['prefix' => 'SR-', 'suffix' => '', 'start_count' => 1];
        $sr_active = auragold_doc_series_active_sql($conn, 'tbl_sale_returns');
        $existing_ret = getRecord("SELECT id FROM tbl_sale_returns WHERE return_no = '$return_no'$sr_dup_sql" . $sr_active);
        $guard = 0;
        while ($existing_ret && $guard < 5000) {
            $return_no = esc(function_exists('bumpSaleReturnNo') ? bumpSaleReturnNo($conn, $return_no, $cfg) : ('SR-' . ($guard + 2)));
            $existing_ret = getRecord("SELECT id FROM tbl_sale_returns WHERE return_no = '$return_no'$sr_dup_sql" . $sr_active);
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_sale_returns', 'return_no']], $return_no);
        }
    }

    if ($return_id > 0) {
        // Update existing return
        $check_sql = "SELECT id FROM tbl_sale_returns WHERE id = $return_id";
        $check_result = mysqli_query($conn, $check_sql);
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            throw new Exception("Return not found");
        }
        
        // Check if return number is already used by another return
        $check_no_sql = "SELECT id FROM tbl_sale_returns WHERE return_no = '$return_no' AND id != $return_id$sr_dup_sql" . auragold_doc_series_active_sql($conn, 'tbl_sale_returns');
        $check_no_result = mysqli_query($conn, $check_no_sql);
        if ($check_no_result && mysqli_num_rows($check_no_result) > 0) {
            throw new Exception("Return number already exists");
        }
        
        // Update return
        $sql = "
            UPDATE tbl_sale_returns SET
                return_no = '$return_no',
                customer_id = " . ($customer_id > 0 ? $customer_id : "NULL") . ",
                customer_name = '$customer_name',
                against_of = " . ($against_of ? "'$against_of'" : "NULL") . ",
                currency = '$currency', $fx_update_sql
                rate = $rate,
                ref_no = " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : "NULL") . ",
                return_date = '$return_date',
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ",
                layaways_id = " . ($layaways ? (int)$layaways : "NULL") . ",
                fixing_type = '$fixing_type',
                ounce_rate = $ounce_rate,
                unfix_dmd_gms = $unfix_dmd_gms,
                unfix_metal = $unfix_metal,
                unfix = $unfix,
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                subtotal = $subtotal,
                net_total = $net_total,
                grand_total = $grand_total,
                round_off = $round_off,
                credit_note = $credit_note,
                comment = " . ($comment ? "'$comment'" : "NULL") . ",
                payment_comments = " . (strlen($payment_comments) ? "'" . mysqli_real_escape_string($conn, $payment_comments) . "'" : "NULL") . ",
                against_type = " . ($against_type ? "'" . mysqli_real_escape_string($conn, $against_type) . "'" : "NULL") . ",
                against_id = " . ($against_id > 0 ? $against_id : "NULL") . "
                " . ($has_sr_branch && $eff_branch > 0 && $sr_row_branch_id === 0 ? ', branch_id = ' . (int) $eff_branch : '') . ",
                updated_at = NOW()
            WHERE id = $return_id
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Return update failed: " . mysqli_error($conn));
        }
        
        // Delete existing items and payments
        mysqli_query($conn, "DELETE FROM tbl_sale_return_items WHERE return_id = $return_id");
        mysqli_query($conn, "DELETE FROM tbl_sale_return_payments WHERE return_id = $return_id");
    } else {
        // Insert new return (return_no already made unique above)
        $payment_comments_esc = mysqli_real_escape_string($conn, $payment_comments);
        $against_type_esc = $against_type ? "'" . mysqli_real_escape_string($conn, $against_type) . "'" : "NULL";
        $sql = "
            INSERT INTO tbl_sale_returns (
                return_no, customer_id, customer_name, against_of, against_type, against_id, currency$fx_insert_col_sql, rate, ref_no, sales_person,
                return_date, due_date, layaways_id, fixing_type, ounce_rate,
                unfix_dmd_gms, unfix_metal, unfix,
                previous_balance, previous_gold, previous_silver,
                subtotal, net_total, grand_total, round_off, credit_note,
                comment, payment_comments, status, created_by,
                " . ($has_sr_branch ? 'branch_id, ' : '') . "created_at
            ) VALUES (
                '$return_no', " . ($customer_id > 0 ? $customer_id : "NULL") . ", '$customer_name', 
                " . ($against_of ? "'$against_of'" : "NULL") . ",
                $against_type_esc, " . ($against_id > 0 ? $against_id : "NULL") . ",
                '$currency'$fx_insert_val_sql, $rate, " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                " . ($sales_person ? "'$sales_person'" : "NULL") . ",
                '$return_date', " . ($due_date ? "'$due_date'" : "NULL") . ",
                " . ($layaways ? (int)$layaways : "NULL") . ",
                '$fixing_type', $ounce_rate,
                $unfix_dmd_gms, $unfix_metal, $unfix,
                $previous_balance, $previous_gold, $previous_silver,
                $subtotal, $net_total, $grand_total, $round_off, $credit_note,
                " . ($comment ? "'$comment'" : "NULL") . ",
                " . (strlen($payment_comments) ? "'$payment_comments_esc'" : "NULL") . ",
                'draft', $user_id,
                " . ($has_sr_branch ? ((int) $hdr_branch > 0 ? (int) $hdr_branch : 'NULL') . ', ' : '') . "NOW()
            )
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Return insert failed: " . mysqli_error($conn));
        }
        
        $return_id = mysqli_insert_id($conn);
    }

    auragold_ensure_customer_ledger_branch_column($conn);
    $sr_ledger_branch_id = 0;
    if ($return_id > 0 && $has_sr_branch) {
        $srb = getRecord('SELECT branch_id FROM tbl_sale_returns WHERE id = ' . (int) $return_id . ' LIMIT 1');
        $sr_ledger_branch_id = (int) ($srb['branch_id'] ?? 0);
    }
    if ($sr_ledger_branch_id <= 0) {
        $sr_ledger_branch_id = $sr_row_branch_id > 0 ? $sr_row_branch_id : (($hdr_branch > 0) ? $hdr_branch : (($eff_branch > 0) ? $eff_branch : 0));
    }
    $ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = '';
    if ($ledger_has_branch_col) {
        $ledger_branch_sql_val = ', ' . ($sr_ledger_branch_id > 0 ? (string) (int) $sr_ledger_branch_id : 'NULL');
    }
    $ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql') ? auragold_customer_ledger_branch_scope_sql($conn, $sr_ledger_branch_id) : '';
    
    // Ensure tbl_sale_return_items has diamond_category and calculation_type (for Diamond Category & Calculation Type)
    $ri_has_diamond_category = false;
    $ri_has_calculation_type = false;
    $col_dc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_return_items LIKE 'diamond_category'");
    if ($col_dc && mysqli_num_rows($col_dc) > 0) {
        $ri_has_diamond_category = true;
        mysqli_free_result($col_dc);
    }
    $col_ct = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_return_items LIKE 'calculation_type'");
    if ($col_ct && mysqli_num_rows($col_ct) > 0) {
        $ri_has_calculation_type = true;
        mysqli_free_result($col_ct);
    }
    if (!$ri_has_diamond_category) {
        @mysqli_query($conn, "ALTER TABLE tbl_sale_return_items ADD COLUMN diamond_category VARCHAR(100) NULL DEFAULT NULL AFTER description");
        $ri_has_diamond_category = true;
    }
    if (!$ri_has_calculation_type) {
        @mysqli_query($conn, "ALTER TABLE tbl_sale_return_items ADD COLUMN calculation_type VARCHAR(100) NULL DEFAULT NULL");
        $ri_has_calculation_type = true;
    }
    $ri_has_metal_qty = false;
    $ri_has_metal_weight = false;
    $col_mq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_return_items LIKE 'metal_qty'");
    if ($col_mq && mysqli_num_rows($col_mq) > 0) { $ri_has_metal_qty = true; @mysqli_free_result($col_mq); }
    $col_mw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_return_items LIKE 'metal_weight'");
    if ($col_mw && mysqli_num_rows($col_mw) > 0) { $ri_has_metal_weight = true; @mysqli_free_result($col_mw); }
    if (!$ri_has_metal_qty) {
        @mysqli_query($conn, "ALTER TABLE tbl_sale_return_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity");
        $ri_has_metal_qty = true;
    }
    if (!$ri_has_metal_weight) {
        @mysqli_query($conn, "ALTER TABLE tbl_sale_return_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty");
        $ri_has_metal_weight = true;
    }
    if (function_exists('auragold_ensure_sale_return_item_source_against_id')) {
        auragold_ensure_sale_return_item_source_against_id($conn);
    }
    $ri_has_source_against = false;
    $col_src = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_return_items LIKE 'source_against_item_id'");
    if ($col_src && mysqli_num_rows($col_src) > 0) {
        $ri_has_source_against = true;
        mysqli_free_result($col_src);
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

    $tbl_stock_has_barcode = false;
    $sbchk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
    if ($sbchk && mysqli_num_rows($sbchk) > 0) {
        $tbl_stock_has_barcode = true;
        mysqli_free_result($sbchk);
    }

    $new_barcodes_out = [];
    $against_for_barcode = trim((string)$against_of . ' ' . (string)$against_type);
    $gen_barcode_for_return = (bool)preg_match('/sale\s+invoice|sale\s+quotation/i', $against_for_barcode);
    
    if (!empty($items) && is_array($items)) {
        $invoice_used_barcodes = [];
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $barcode = '';
            $product_name = esc($item['product_name'] ?? '');
            $description = esc($item['description'] ?? '');
            $carat = esc($item['carat'] ?? '');
            $diamond_category = esc($item['category'] ?? $item['diamond_category'] ?? '');
            $calculation_type = esc($item['calculation_type'] ?? $item['calculation'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $metal_qty = (float)($item['metal_qty'] ?? 1);
            $metal_weight = (float)($item['metal_weight'] ?? 0);
            $gross_weight = (float)($item['gross_weight'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? 0);
            $net_weight = (float)($item['net_weight'] ?? 0);
            $pure_weight = (float)($item['pure_weight'] ?? 0);
            $making = (float)($item['making'] ?? $item['making_amount'] ?? 0);
            $tax = (float)($item['tax'] ?? $item['tax_amount'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? $item['net_amt'] ?? 0);
            $net_amt_weight = (float)($item['net_amt_weight'] ?? $item['net_amt_with_tax'] ?? $item['net_amt_tax'] ?? 0);
            $diamond_weight = (float)($item['diamond_weight'] ?? 0);
            $gemstone_weight = (float)($item['gemstone_weight'] ?? 0);
            $diamond_amount = (float)($item['diamond_amount'] ?? 0);
            $source_against_item_id = (int)($item['source_against_item_id'] ?? $item['sale_invoice_item_id'] ?? 0);
            
            if ($product_id > 0) {
                $has_weight = ($gross_weight > 0 || $net_weight > 0 || $final_weight > 0);
                $raw_bc = auragold_resolve_unique_invoice_item_barcode($conn, $item, $invoice_used_barcodes);
                $barcode = esc($raw_bc);
                if ($gen_barcode_for_return && $has_weight) {
                    $new_barcodes_out[] = ['barcode' => $raw_bc, 'product_name' => $product_name];
                }

                $dc_col = $ri_has_diamond_category ? ", diamond_category" : "";
                $dc_val = $ri_has_diamond_category ? ", " . ($diamond_category ? "'" . mysqli_real_escape_string($conn, $diamond_category) . "'" : "NULL") : "";
                $ct_col = $ri_has_calculation_type ? ", calculation_type" : "";
                $ct_val = $ri_has_calculation_type ? ", " . ($calculation_type ? "'" . mysqli_real_escape_string($conn, $calculation_type) . "'" : "NULL") : "";
                $mq_col = $ri_has_metal_qty ? ", metal_qty" : "";
                $mq_val = $ri_has_metal_qty ? ", $metal_qty" : "";
                $mw_col = $ri_has_metal_weight ? ", metal_weight" : "";
                $mw_val = $ri_has_metal_weight ? ", $metal_weight" : "";
                $src_col = $ri_has_source_against ? ", source_against_item_id" : "";
                $src_val = $ri_has_source_against ? ", " . ($source_against_item_id > 0 ? $source_against_item_id : "NULL") : "";
                $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_sale_return_items', $item);
                $ef_col = $ef_parts['columns'];
                $ef_val = $ef_parts['values'];
                // Insert return item
                $item_sql = "
                    INSERT INTO tbl_sale_return_items (
                        return_id$src_col, product_id, product_characteristic_id, barcode, product_name,
                        description$dc_col, carat, quantity$mq_col$mw_col, gross_weight, final_weight,
                        net_weight, pure_weight, making_amount, tax_amount,
                        amount, net_amount, net_amt_weight,
                        diamond_weight, gemstone_weight, diamond_amount$ct_col$ef_col,
                        status, created_at
                    ) VALUES (
                        $return_id$src_val, $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                        " . ($barcode ? "'$barcode'" : "NULL") . ",
                        '$product_name',
                        " . ($description ? "'$description'" : "NULL") . "$dc_val,
                        " . ($carat ? "'$carat'" : "NULL") . ",
                        $quantity$mq_val$mw_val, $gross_weight, $final_weight,
                        $net_weight, $pure_weight, $making, $tax,
                        $amount, $net_amount, $net_amt_weight,
                        $diamond_weight, $gemstone_weight, $diamond_amount$ct_val$ef_val,
                        1, NOW()
                    )
                ";
                
                if (!mysqli_query($conn, $item_sql)) {
                    throw new Exception("Item insert failed: " . mysqli_error($conn));
                }
                
                // Get the item ID that was just inserted
                $item_id = mysqli_insert_id($conn);
                
                // Add inward stock entry (sale return increases inventory - products coming back)
                if ($product_id > 0 && $has_weight) {
                    $branch_id = 0;
                    $metal_id = 0;
                    $stock_purity = 0;
                    $rate = 0;
                    
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
                            $stock_purity = (float)$char_details['opening_purity'];
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
                    $stock_weight = 0;
                    if ($net_weight > 0) {
                        $stock_weight = $net_weight;
                    } else if ($final_weight > 0) {
                        $stock_weight = $final_weight;
                    } else if ($gross_weight > 0) {
                        $stock_weight = $gross_weight;
                    }
                    
                    // Use the best available value
                    $stock_value = 0;
                    if ($net_amount > 0) {
                        $stock_value = $net_amount;
                    } else if ($amount > 0) {
                        $stock_value = $amount;
                    }
                    
                    // Calculate rate if we have weight and value
                    if ($stock_weight > 0 && $stock_value > 0) {
                        $rate = $stock_value / $stock_weight;
                    }
                    
                    // Default values if missing
                    if ($stock_purity <= 0) {
                        $stock_purity = 100.0; // Default to 100% if not specified
                    }
                    if ($branch_id <= 0) {
                        $hbr = 0;
                        if ($return_id > 0 && !empty($has_sr_branch)) {
                            $hr = getRecord('SELECT branch_id FROM tbl_sale_returns WHERE id = ' . (int) $return_id . ' LIMIT 1');
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
                    
                    // Insert inward stock entry (sale return - products coming back into inventory)
                    $stock_cols = "product_id, product_characteristic_id";
                    $stock_vals = "$product_id, " . ($characteristic_id ? $characteristic_id : "NULL");
                    if ($tbl_stock_has_barcode) {
                        $stock_cols .= ", barcode";
                        $stock_vals .= ", " . ($barcode ? "'$barcode'" : "NULL");
                    }
                    $stock_cols .= ", branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
                    $stock_vals .= ", $branch_id, $metal_id, $stock_weight, $stock_purity, $quantity, " . ($final_weight > 0 ? $final_weight : $stock_weight) . ", $rate, $stock_value, $stock_weight, $quantity, 'sale_return', '$return_date', NOW()";
                    $stock_sql = "INSERT INTO tbl_stock ($stock_cols) VALUES ($stock_vals)";
                    
                    if (!mysqli_query($conn, $stock_sql)) {
                        throw new Exception("Inward stock insert failed: " . mysqli_error($conn) . " | SQL: " . $stock_sql);
                    }

                    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
                    $sj_no_sr = 'SR' . (int) $return_id . 'I' . (int) $item_id;
                    if (strlen($sj_no_sr) > 48) {
                        $sj_no_sr = 'R' . (int) $return_id . 'x' . (int) $item_id;
                    }
                    auragold_stock_history_audit_insert_row($conn, [
                        'sj_invoice_no' => $sj_no_sr,
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
                        'less_weight' => 0,
                        'net_weight' => $net_weight,
                        'purity' => 0,
                        'purity_weight' => $pure_weight,
                        'pure_weight' => $pure_weight,
                        'final_weight' => $final_weight,
                        'rate' => $rate,
                        'amount' => $amount,
                        'making_amount' => $making,
                        'tax_amount' => $tax,
                        'net_amount' => $net_amount,
                        'net_amt_with_tax' => $net_amt_weight,
                        'rfid_code' => '',
                        'voucher_type' => 'Sale Return',
                        'design_no' => '',
                        'category' => $diamond_category,
                        'comment' => 'auragold_doc|src=sr|rid=' . (int) $return_id . '|sri=' . (int) $item_id . '|',
                    ]);
                }
            }
        }
    }
    
    // Save return payments
    $payments = [];
    if (isset($_POST['payments'])) {
        if (is_string($_POST['payments'])) {
            $payments = json_decode($_POST['payments'], true);
        } else if (is_array($_POST['payments'])) {
            $payments = $_POST['payments'];
        }
    }
    
    if (!empty($payments) && is_array($payments)) {
        sale_return_validate_metal_exchange_payments($conn, $payments);
        $__sr_was_update_local = !$is_new_return;
        $__sr_me_has_ref = auragold_metal_exchange_document_init($conn, $__sr_was_update_local, (int) $return_id, 'sale_return_metal_exchange');

        foreach ($payments as $pay_seq => $payment) {
            $payment_type = esc($payment['payment_type'] ?? '');
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $transaction_no = esc($payment['transaction_no'] ?? '');
            $transfer_from = esc($payment['transfer_from'] ?? '');
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $product = esc($payment['product'] ?? '');
            $cheque_date = esc($payment['cheque_date'] ?? '');
            $weight = (float)($payment['weight'] ?? 0);
            $metal = esc($payment['metal'] ?? '');
            $quantity = (float)($payment['quantity'] ?? 0);
            $purity_carat = esc($payment['purity_carat'] ?? '');
            $amount = (float)($payment['amount'] ?? 0);

            if (auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                $payment_sql = "
                    INSERT INTO tbl_sale_return_payments (
                        return_id, payment_type, diamond_category, transaction_no,
                        transfer_from, deposit_into, product, cheque_date,
                        weight, metal, quantity, purity_carat, amount,
                        status, created_at
                    ) VALUES (
                        $return_id, '$payment_type',
                        " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                        " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                        " . ($transfer_from ? "'$transfer_from'" : "NULL") . ",
                        " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                        " . ($product ? "'$product'" : "NULL") . ",
                        " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                        $weight,
                        " . ($metal ? "'$metal'" : "NULL") . ",
                        $quantity,
                        " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                        $amount,
                        1, NOW()
                    )
                ";

                if (!mysqli_query($conn, $payment_sql)) {
                    throw new Exception("Payment insert failed: " . mysqli_error($conn));
                }
            }

            $pm = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'sale_return_metal_exchange',
                (int) $return_id,
                trim((string) preg_replace('/\s.+/', '', (string) $return_no)),
                substr(trim((string) $return_date), 0, 10),
                $pm,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $__sr_me_has_ref,
                'Sale Return — Metal Exchange',
                'sr_me',
                'SR-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }

    // ================== CUSTOMER LEDGER: Sales Return + optional refund Payment Voucher ==================
    $metal_amt_post = (float)($_POST['metal_amt'] ?? 0);
    if (($customer_id > 0 || $customer_name !== '') && !empty($items) && is_array($items)) {
        $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $has_against = ($has_against && mysqli_num_rows($has_against) > 0);
        $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
        $has_gold_pure_cols = ($gpc && mysqli_num_rows($gpc) > 0);

        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'sale_return' AND transaction_id = $return_id AND status = 1");

        $refs_to_clean = array_unique(array_filter([$old_return_no_snapshot, $return_no], function ($v) {
            return $v !== null && $v !== '';
        }));
        foreach ($refs_to_clean as $__ref) {
            $__re = mysqli_real_escape_string($conn, (string)$__ref);
            $pv_rows = getList("SELECT id FROM tbl_payment_vouchers WHERE ref_no = '$__re'");
            if (!is_array($pv_rows)) {
                continue;
            }
            foreach ($pv_rows as $pvr) {
                $pvid = (int)($pvr['id'] ?? 0);
                if ($pvid <= 0) {
                    continue;
                }
                mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'payment_voucher' AND transaction_id = $pvid AND status = 1");
                mysqli_query($conn, "DELETE FROM tbl_payment_voucher_items WHERE voucher_id = $pvid");
                mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE id = $pvid");
            }
        }

        $total_sales_amt = 0.0;
        $total_making_amt = 0.0;
        $total_tax_amt = 0.0;
        foreach ($items as $item) {
            $metal_val = (float)($item['metal_value'] ?? 0);
            $diamond_amt = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
            $stone_amt = (float)($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
            $making_amt = (float)($item['making_amount'] ?? $item['making'] ?? 0);
            $tax_amt = (float)($item['tax'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $item_sales = $metal_val + $diamond_amt + $stone_amt;
            if ($item_sales <= 0 && $amount > 0) {
                $item_sales = max(0, $amount - $making_amt);
            }
            $total_sales_amt += $item_sales;
            $total_making_amt += $making_amt;
            $total_tax_amt += $tax_amt;
        }
        if ($total_sales_amt <= 0) {
            $total_sales_amt = $metal_amt_post;
        }
        if ($total_tax_amt <= 0) {
            $total_tax_amt = max(0, $grand_total - $net_total);
        }
        if ($total_making_amt <= 0 && $grand_total > 0) {
            $total_making_amt = max(0, $net_total - $total_sales_amt - $total_tax_amt);
        }

        $get_ledger_balance = function ($ledger_name) use ($conn, $ledger_br_scope) {
            $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = '" . mysqli_real_escape_string($conn, $ledger_name) . "' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            return (float)($r['balance_amount'] ?? 0);
        };
        $get_making_amount_ledger_balance = function () use ($conn, $ledger_br_scope) {
            $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = 'Making Sale Account' AND status = 1 $ledger_br_scope ORDER BY id DESC LIMIT 1");
            if ($r && array_key_exists('balance_amount', $r)) {
                return (float)$r['balance_amount'];
            }
            $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = 'Making Amount Ledger' AND status = 1 $ledger_br_scope ORDER BY id DESC LIMIT 1");
            return (float)($r['balance_amount'] ?? 0);
        };

        $against_cols = $has_against ? ', against_ledger, against_invoice_no' : '';
        $return_no_sql = mysqli_real_escape_string($conn, $return_no);
        $return_date_sql = mysqli_real_escape_string($conn, $return_date);

        if ($total_sales_amt > 0.00001) {
            $prev_sales = $get_ledger_balance('Sales Account');
            $new_sales_bal = $prev_sales + $total_sales_amt;
            $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$return_no_sql'" : '';
            $sales_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Sales Account', 'sale_return', $return_id, '$return_no_sql', '$return_date_sql', $total_sales_amt, 0.00, $new_sales_bal, 'Sales Return: $return_no_sql', " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ", 1, $user_id, NOW() $against_vals)";
            if (!mysqli_query($conn, $sales_sql)) {
                throw new Exception('Sales Account ledger (sale return) failed: ' . mysqli_error($conn));
            }
        }
        if ($total_making_amt > 0.00001) {
            $prev_making = $get_making_amount_ledger_balance();
            $new_making_bal = $prev_making + $total_making_amt;
            $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$return_no_sql'" : '';
            $making_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Making Sale Account', 'sale_return', $return_id, '$return_no_sql', '$return_date_sql', $total_making_amt, 0.00, $new_making_bal, 'Making charges - Sales Return: $return_no_sql', " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ", 1, $user_id, NOW() $against_vals)";
            if (!mysqli_query($conn, $making_sql)) {
                throw new Exception('Making Sale Account ledger (sale return) failed: ' . mysqli_error($conn));
            }
        }
        if ($total_tax_amt > 0.00001) {
            $prev_tax = $get_ledger_balance('Tax Ledger');
            $new_tax_bal = $prev_tax + $total_tax_amt;
            $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$return_no_sql'" : '';
            $tax_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Tax Ledger', 'sale_return', $return_id, '$return_no_sql', '$return_date_sql', $total_tax_amt, 0.00, $new_tax_bal, 'GST/Tax - Sales Return: $return_no_sql', " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ", 1, $user_id, NOW() $against_vals)";
            if (!mysqli_query($conn, $tax_sql)) {
                throw new Exception('Tax Ledger (sale return) failed: ' . mysqli_error($conn));
            }
        }

        if ($grand_total > 0.00001) {
            $prev_balance_select = 'balance_amount, balance_gold, balance_silver';
            if ($has_gold_pure_cols) {
                $prev_balance_select .= ', balance_gold_pure';
            }
            $previous_balance_record = null;
            if ($customer_id > 0) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_ledger WHERE customer_id = $customer_id AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            if (!$previous_balance_record && $customer_name !== '') {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_ledger WHERE customer_name = '$customer_name' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? 0);
            $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? 0);
            $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? 0);
            $prev_balance_gold_pure = $has_gold_pure_cols ? (float)($previous_balance_record['balance_gold_pure'] ?? 0) : 0.0;

            $new_balance_amount = $prev_balance_amount - $grand_total;
            $against_vals_c = $has_against ? ", 'Sales Return', '$return_no_sql'" : '';
            $ledger_gold_pure_cols = '';
            $metal_vals = '0.000, 0.000';
            $balance_metal_vals = (string)$prev_balance_gold . ', ' . (string)$prev_balance_silver;
            if ($has_gold_pure_cols) {
                $ledger_gold_pure_cols = 'debit_gold_pure, credit_gold_pure, ';
                $metal_vals = '0.000, 0.000, 0.000, 0.000';
                $balance_metal_vals = (string)$prev_balance_gold . ', ' . (string)$prev_balance_gold_pure . ', ' . (string)$prev_balance_silver;
            }
            $metal_vals .= ', 0.000, 0.000';
            $ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold" . ($has_gold_pure_cols ? ', balance_gold_pure' : '') . ", balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 0) . "$ledger_branch_sql_val,
                    '$customer_name',
                    'sale_return',
                    $return_id,
                    '$return_no_sql',
                    '$return_date_sql',
                    0.00,
                    $grand_total,
                    $metal_vals,
                    $new_balance_amount,
                    $balance_metal_vals,
                    'Sales Return: $return_no_sql',
                    " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ",
                    1,
                    $user_id,
                    NOW()
                    $against_vals_c
                )
            ";
            if (!mysqli_query($conn, $ledger_sql)) {
                throw new Exception('Customer ledger (sale return) failed: ' . mysqli_error($conn));
            }
        }

        $pv_total_money = 0.0;
        if (!empty($payments) && is_array($payments)) {
            foreach ($payments as $__pv) {
                $pv_total_money += (float)($__pv['current_order_amount'] ?? ($__pv['amount'] ?? 0));
            }
        }
        if ($pv_total_money > 0.00001) {
            $last_pv = getRecord('SELECT voucher_no FROM tbl_payment_vouchers ORDER BY id DESC LIMIT 1');
            $pv_num = 1;
            if ($last_pv && !empty($last_pv['voucher_no']) && preg_match('/PV[- ]?(\d+)/i', $last_pv['voucher_no'], $m)) {
                $pv_num = (int)$m[1] + 1;
            }
            $pi_payment_voucher_no = 'PV-' . $pv_num;
            $pv_esc = mysqli_real_escape_string($conn, $pi_payment_voucher_no);
            $pv_header_sql = "
                INSERT INTO tbl_payment_vouchers (
                    voucher_no, customer_id, customer_name, ref_no, voucher_type,
                    voucher_date, total_amount, total_gold, total_silver,
                    comment, status, created_by, created_at
                ) VALUES (
                    '$pv_esc',
                    " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                    '$customer_name',
                    '$return_no_sql',
                    'Payment Voucher',
                    '$return_date_sql',
                    $pv_total_money,
                    0,
                    0,
                    'Refund against Sale Return',
                    'draft',
                    $user_id,
                    NOW()
                )
            ";
            if (!mysqli_query($conn, $pv_header_sql)) {
                throw new Exception('Payment voucher header failed: ' . mysqli_error($conn));
            }
            $pi_pv_id = (int)mysqli_insert_id($conn);

            foreach ($payments as $__p) {
                $cur = (float)($__p['current_order_amount'] ?? ($__p['amount'] ?? 0));
                if ($cur <= 0.00001) {
                    continue;
                }
                $pt = esc($__p['payment_type'] ?? 'cash');
                $dep = esc(trim((string)($__p['deposit_into'] ?? '')));
                if ($dep === '' && strtolower($pt) === 'cash') {
                    $dep = esc('Cash');
                }
                $pvi_sql = "INSERT INTO tbl_payment_voucher_items (voucher_id, payment_type, deposit_into, amount, previous_balance_amount, status, created_at) VALUES ($pi_pv_id, '$pt', " . ($dep !== '' ? "'$dep'" : 'NULL') . ", $cur, 0, 1, NOW())";
                if (!mysqli_query($conn, $pvi_sql)) {
                    throw new Exception('Payment voucher item failed: ' . mysqli_error($conn));
                }
            }

            $pay_bal_pure_sel = $has_gold_pure_cols ? ', balance_gold_pure' : '';
            $last_balance = null;
            if ($customer_id > 0) {
                $last_balance = getRecord("SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel FROM tbl_customer_ledger WHERE customer_id = $customer_id AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            if (!$last_balance && $customer_name !== '') {
                $last_balance = getRecord("SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel FROM tbl_customer_ledger WHERE customer_name = '$customer_name' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            $prev_amt = (float)($last_balance['balance_amount'] ?? 0);
            $prev_gold = (float)($last_balance['balance_gold'] ?? 0);
            $prev_silver = (float)($last_balance['balance_silver'] ?? 0);
            $prev_gold_pure = $has_gold_pure_cols ? (float)($last_balance['balance_gold_pure'] ?? 0) : 0.0;
            $new_balance_amt = $prev_amt + $pv_total_money;

            $party_against_parts = [];
            foreach ($payments as $__p) {
                $line_amt = (float)($__p['current_order_amount'] ?? ($__p['amount'] ?? 0));
                if ($line_amt <= 0.00001) {
                    continue;
                }
                $pt_raw = strtolower(trim((string)($__p['payment_type'] ?? 'cash')));
                $dep_raw = trim((string)($__p['deposit_into'] ?? ''));
                if ($dep_raw === '' && $pt_raw === 'cash') {
                    $dep_raw = 'Cash';
                }
                if ($dep_raw !== '') {
                    $dep_esc2 = esc($dep_raw);
                    $party_against_parts[] = $dep_esc2 . '(' . number_format($line_amt, 2) . 'Cr)';
                }
            }
            $party_against_display = implode(', ', $party_against_parts);
            $against_vals_pv = '';
            if ($has_against) {
                if ($party_against_display !== '') {
                    $party_against_display_esc = mysqli_real_escape_string($conn, $party_against_display);
                    $against_vals_pv = ", '$party_against_display_esc', '$return_no_sql'";
                } else {
                    $against_vals_pv = ', NULL, NULL';
                }
            }

            $desc_pv = mysqli_real_escape_string($conn, 'Payment Voucher: ' . $pi_payment_voucher_no . ' (Sales Return ' . $return_no . ')');
            $ledger_cust_id = $customer_id > 0 ? $customer_id : 0;
            if ($has_gold_pure_cols) {
                $party_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,
                        balance_amount, balance_gold, balance_gold_pure, balance_silver,
                        description, reference_no, status, created_by, created_at
                        $against_cols
                    ) VALUES (
                        $ledger_cust_id$ledger_branch_sql_val,
                        '$customer_name',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$return_date_sql',
                        $pv_total_money,
                        0,
                        0, 0, 0, 0, 0, 0,
                        $new_balance_amt,
                        $prev_gold,
                        $prev_gold_pure,
                        $prev_silver,
                        '$desc_pv',
                        " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ",
                        1,
                        $user_id,
                        NOW()
                        $against_vals_pv
                    )
                ";
            } else {
                $party_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, debit_silver, credit_silver,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at
                        $against_cols
                    ) VALUES (
                        $ledger_cust_id$ledger_branch_sql_val,
                        '$customer_name',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$return_date_sql',
                        $pv_total_money,
                        0,
                        0, 0, 0, 0,
                        $new_balance_amt,
                        $prev_gold,
                        $prev_silver,
                        '$desc_pv',
                        " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ",
                        1,
                        $user_id,
                        NOW()
                        $against_vals_pv
                    )
                ";
            }
            if (!mysqli_query($conn, $party_sql)) {
                throw new Exception('Payment voucher customer ledger failed: ' . mysqli_error($conn));
            }

            foreach ($payments as $__p) {
                $tot = (float)($__p['current_order_amount'] ?? ($__p['amount'] ?? 0));
                if ($tot <= 0.00001) {
                    continue;
                }
                $pt_raw = strtolower(trim((string)($__p['payment_type'] ?? 'cash')));
                $dep_raw = trim((string)($__p['deposit_into'] ?? ''));
                if ($dep_raw === '' && $pt_raw === 'cash') {
                    $dep_raw = 'Cash';
                }
                if ($dep_raw === '') {
                    continue;
                }
                $dep_esc = esc($dep_raw);
                $cash_balance_record = getRecord("SELECT balance_amount, balance_gold, balance_silver" . ($has_gold_pure_cols ? ', balance_gold_pure' : '') . " FROM tbl_customer_ledger WHERE customer_name = '$dep_esc' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
                $cash_prev_balance = (float)($cash_balance_record['balance_amount'] ?? 0);
                $cash_new_balance = $cash_prev_balance - $tot;
                $acc_prev_g = (float)($cash_balance_record['balance_gold'] ?? 0);
                $acc_prev_s = (float)($cash_balance_record['balance_silver'] ?? 0);
                $acc_prev_gp = $has_gold_pure_cols ? (float)($cash_balance_record['balance_gold_pure'] ?? 0) : 0.0;
                $cash_desc_esc = mysqli_real_escape_string($conn, 'Refund to ' . $customer_name . ' (Payment Voucher ' . $pi_payment_voucher_no . ')');
                $ca_line_esc = mysqli_real_escape_string($conn, function_exists('accountledger_against_party_payment_label') ? accountledger_against_party_payment_label($customer_name, $pt_raw, $tot) : ($customer_name . '(' . number_format($tot, 2) . ')'));
                if ($has_against) {
                    $cash_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, balance_gold, balance_silver,
                            description, reference_no, status, created_by, created_at,
                            against_ledger, against_invoice_no
                        ) VALUES (
                            0$ledger_branch_sql_val,
                            '$dep_esc',
                            'payment_voucher',
                            $pi_pv_id,
                            '$pv_esc',
                            '$return_date_sql',
                            0,
                            $tot,
                            $cash_new_balance,
                            $acc_prev_g,
                            $acc_prev_s,
                            '$cash_desc_esc',
                            " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ",
                            1,
                            $user_id,
                            NOW(),
                            '$ca_line_esc',
                            '$return_no_sql'
                        )
                    ";
                } else {
                    $cash_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, balance_gold, balance_silver,
                            description, reference_no, status, created_by, created_at
                        ) VALUES (
                            0$ledger_branch_sql_val,
                            '$dep_esc',
                            'payment_voucher',
                            $pi_pv_id,
                            '$pv_esc',
                            '$return_date_sql',
                            0,
                            $tot,
                            $cash_new_balance,
                            $acc_prev_g,
                            $acc_prev_s,
                            '$cash_desc_esc',
                            " . ($ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL') . ",
                            1,
                            $user_id,
                            NOW()
                        )
                    ";
                }
                if (!mysqli_query($conn, $cash_sql)) {
                    throw new Exception('Payment voucher cash/bank ledger failed: ' . mysqli_error($conn));
                }
            }
        }
    }

    if ((int) $return_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'sale_return', (int) $return_id, $return_no, $return_date);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Sale Return',
        'verb' => $is_new_return ? 'created' : 'updated',
        'number' => $return_no,
        'party' => $customer_name,
        'doc_date' => $return_date,
        'due_date' => $due_date,
        'ref_id' => (int) $return_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Sale return saved successfully',
        'return_id' => $return_id,
        'order_id' => $return_id,
        'return_no' => $return_no,
        'order_no' => $return_no,
        'new_barcodes' => array_merge((array) $new_barcodes_out, (array) $metal_exchange_barcodes_out),
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Sale Return Save Error: " . $e->getMessage());
    error_log("Sale Return Save Trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
