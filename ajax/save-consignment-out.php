<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Master data (accept consignment_* and shared UI keys order_* / order_date)
    $consignment_id = isset($_POST['consignment_id']) ? (int)$_POST['consignment_id'] : 0;
    if ($consignment_id <= 0 && isset($_POST['order_id'])) {
        $consignment_id = (int)$_POST['order_id'];
    }
    $co_was_update = ($consignment_id > 0);
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
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_consignment_out') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
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

    $co_cfg = function_exists('getConsignmentOutBillSeriesConfig') ? getConsignmentOutBillSeriesConfig($conn) : ['prefix' => 'CO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    
    // Items JSON
    $items_json = isset($_POST['items']) ? $_POST['items'] : '[]';
    $items = json_decode($items_json, true);
    if (!is_array($items)) {
        $items = [];
    }

    $payments_co = isset($_POST['payments']) ? $_POST['payments'] : '[]';
    if (is_string($payments_co)) {
        $payments_co = json_decode($payments_co, true);
    }
    if (!is_array($payments_co)) {
        $payments_co = [];
    }
    $metal_exchange_barcodes_out = [];

    // Validation
    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }
    
    if (empty($items)) {
        throw new Exception('At least one item is required');
    }

    if ($tax_amount <= 0.000001 && !empty($items)) {
        foreach ($items as $cit) {
            $tax_amount += (float)($cit['tax'] ?? $cit['tax_amount'] ?? 0);
        }
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

    // Same branch + net balance rules as gold-and-silver.php / get-product-by-barcode (inward − outward per barcode).
    $co_stock_in_types = "'opening','purchase','stock_journal','balance','sale_return'";
    $co_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if ($co_branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
        $co_branch_id = (int) $_SESSION['working_branch_id'];
    } elseif ($co_branch_id <= 0 && !empty($_SESSION['branch_id'])) {
        $co_branch_id = (int) $_SESSION['branch_id'];
    }
    if ($co_branch_id <= 0 && function_exists('getRecordMaster')) {
        $mbr_co = @getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1');
        if ($mbr_co && !empty($mbr_co['id'])) {
            $co_branch_id = (int) $mbr_co['id'];
        }
    }
    $co_branch_sql_s = '';
    $co_branch_sql = '';
    if ($co_branch_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
        $co_main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($co_main_bid > 0 && $co_branch_id === $co_main_bid) {
            $co_branch_sql_s = ' AND (s.branch_id = ' . $co_branch_id . ' OR s.branch_id IS NULL OR s.branch_id = 0)';
            $co_branch_sql = ' AND (branch_id = ' . $co_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $co_branch_sql_s = ' AND COALESCE(s.branch_id, 0) = ' . $co_branch_id;
            $co_branch_sql = ' AND COALESCE(branch_id, 0) = ' . $co_branch_id;
        }
    }

    // Validate stock availability before proceeding
    foreach ($items as $item) {
        $barcode_raw = isset($item['barcode']) ? trim((string) $item['barcode']) : '';
        $qty_required = (int) ($item['quantity'] ?? 1);
        $gross_w_req = isset($item['gross_weight']) ? (float) $item['gross_weight'] : 0;
        $net_w_req = isset($item['net_weight']) ? (float) $item['net_weight'] : 0;
        $wt_required = $net_w_req > 0 ? $net_w_req : $gross_w_req;

        if ($barcode_raw !== '' && $tbl_stock_has_barcode) {
            $barcode_esc = mysqli_real_escape_string($conn, $barcode_raw);
            $bal = getRecord("
                SELECT
                    (SUM(CASE WHEN s.stock_type IN ($co_stock_in_types) THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)
                     - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) AS bal_qty,
                    (SUM(CASE WHEN s.stock_type IN ($co_stock_in_types) THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)
                     - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) AS bal_wt
                FROM tbl_stock s
                WHERE s.status = 1 AND s.barcode = '$barcode_esc'
                $co_branch_sql_s
            ");
            $available_qty = (float) ($bal['bal_qty'] ?? 0);
            $available_wt = (float) ($bal['bal_wt'] ?? 0);

            $pooled_ok = false;
            if (($available_qty + 1e-9 < $qty_required || ($wt_required > 0 && $available_wt + 1e-9 < $wt_required))
                && $co_branch_id > 0
                && function_exists('auragold_tbl_has_column')
                && auragold_tbl_has_column($conn, 'tbl_stock', 'reference_barcodes')
            ) {
                $co_main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
                $br_pred = ($co_main_bid > 0 && $co_branch_id === $co_main_bid)
                    ? '(branch_id = ' . $co_branch_id . ' OR branch_id IS NULL OR branch_id = 0)'
                    : 'COALESCE(branch_id, 0) = ' . $co_branch_id;
                $pool_row = getRecord("
                    SELECT id FROM tbl_stock
                    WHERE status = 1 AND stock_type = 'outward' AND $br_pred
                    AND reference_barcodes IS NOT NULL AND TRIM(reference_barcodes) != ''
                    AND FIND_IN_SET(
                        '$barcode_esc',
                        REPLACE(REPLACE(REPLACE(TRIM(reference_barcodes), ' ', ''), CHAR(9), ''), CHAR(13), '')
                    ) > 0
                    AND (IFNULL(current_qty, 0) > 0 OR IFNULL(current_weight, 0) > 0)
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $pooled_ok = $pool_row && !empty($pool_row['id']);
            }

            if (!$pooled_ok && ($available_qty + 1e-9 < $qty_required || ($wt_required > 0 && $available_wt + 1e-9 < $wt_required))) {
                $show_qty = (int) floor($available_qty + 1e-9);
                $show_wt = $available_wt;
                throw new Exception(
                    "Insufficient stock for barcode: $barcode_raw. Available qty: $show_qty, weight: $show_wt; Required qty: $qty_required"
                    . ($wt_required > 0 ? ', weight: ' . $wt_required : '')
                );
            }
        }
    }

    // Generate consignment number if new (Bill Series: getNextConsignmentOutNo; legacy CO-1)
    if ($consignment_id == 0) {
        $consignment_no = trim((string)$consignment_no);
        if ($consignment_no !== '') {
            $cn_esc = mysqli_real_escape_string($conn, $consignment_no);
            $existing = getRecord("SELECT id FROM tbl_consignment_out WHERE consignment_no = '$cn_esc'" . auragold_doc_series_active_sql($conn, 'tbl_consignment_out'));
            if ($existing) {
                throw new Exception('Consignment number already exists: ' . $consignment_no);
            }
        } else {
            $consignment_no = function_exists('getNextConsignmentOutNo') ? getNextConsignmentOutNo($conn) : 'CO-1';
        }
        $tries = 0;
        $co_active = auragold_doc_series_active_sql($conn, 'tbl_consignment_out');
        while ($tries < 20) {
            $cn_chk = mysqli_real_escape_string($conn, $consignment_no);
            $exists = getRecord("SELECT id FROM tbl_consignment_out WHERE consignment_no = '$cn_chk'" . $co_active);
            if (!$exists) {
                break;
            }
            $consignment_no = function_exists('bumpConsignmentOutNo') ? bumpConsignmentOutNo($conn, $consignment_no, $co_cfg) : ('CO-' . (string)(time() % 1000000));
            $tries++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_consignment_out', 'consignment_no']], $consignment_no);
        }

        $insert_master = "
            INSERT INTO tbl_consignment_out (
                consignment_no, customer_id, customer_name, consignment_date, ref_no, against_of,
                currency$fx_insert_col_sql, fixing_type, sales_person, previous_balance, previous_gold, previous_silver,
                gross_total, discount_amount, tax_amount, grand_total, 
                total_quantity, total_gross_weight, total_net_weight, total_pure_weight,
                comment, status, created_by, created_at
            ) VALUES (
                '$consignment_no',
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
            UPDATE tbl_consignment_out SET
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
        
        // Restore stock for old items before deleting (reverse the deduction)
        if ($tbl_stock_has_barcode) {
            $old_items = getList("SELECT * FROM tbl_consignment_out_items WHERE consignment_id = $consignment_id");
            foreach ($old_items as $old_item) {
                $old_barcode = $old_item['barcode'] ?? '';
                $old_qty = (int)($old_item['quantity'] ?? 1);
                $old_weight = (float)($old_item['net_weight'] ?? 0);
                
                if ($old_barcode !== '') {
                    $old_barcode_esc = mysqli_real_escape_string($conn, $old_barcode);
                    $restore_row = getRecord("
                        SELECT id FROM tbl_stock
                        WHERE barcode = '$old_barcode_esc' AND status = 1
                        AND stock_type IN ($co_stock_in_types)
                        $co_branch_sql
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    if ($restore_row && !empty($restore_row['id'])) {
                        $rid = (int) $restore_row['id'];
                        mysqli_query($conn, "UPDATE tbl_stock SET current_qty = current_qty + $old_qty, current_weight = current_weight + $old_weight WHERE id = $rid");
                    }
                }
            }
        }

        // Remove prior Stock History ledger lines for this consignment (rebuilt from new line items)
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=co|cid=" . (int) $consignment_id . "|%'");
        
        // Delete old items
        mysqli_query($conn, "DELETE FROM tbl_consignment_out_items WHERE consignment_id = $consignment_id");
    }

    // Insert items and deduct stock
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
        
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_consignment_out_items', $item);
        $insert_item = "
            INSERT INTO tbl_consignment_out_items (
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
        $co_item_id = (int) mysqli_insert_id($conn);

        // Deduct stock (Stock Out / Outward) — one inward source row (same pattern as sale invoice)
        if ($barcode !== '' && $tbl_stock_has_barcode) {
            $barcode_esc = $barcode;
            $deduct_weight = $net_weight > 0 ? $net_weight : $gross_weight;

            // Prefer lines with live current_*; else inward rows zeroed by stock-journal merge (balance from opening_* only)
            $source_stock = getRecord("
                SELECT *
                FROM tbl_stock
                WHERE barcode = '$barcode_esc' AND status = 1
                AND stock_type IN ($co_stock_in_types)
                AND (
                    COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0
                    OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0
                )
                $co_branch_sql
                ORDER BY CASE
                    WHEN COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0 THEN 0
                    ELSE 1
                END, id DESC
                LIMIT 1
            ");
            if (!$source_stock) {
                $agg_pick = getRecord("
                    SELECT
                        MAX(CASE WHEN s.stock_type IN ($co_stock_in_types) THEN s.id END) AS pick_id
                    FROM tbl_stock s
                    WHERE s.status = 1 AND s.barcode = '$barcode_esc'
                    $co_branch_sql_s
                    GROUP BY s.barcode, s.branch_id
                    HAVING MAX(CASE WHEN s.stock_type IN ($co_stock_in_types) THEN s.id END) IS NOT NULL
                    AND (
                        (SUM(CASE WHEN s.stock_type IN ($co_stock_in_types) THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)
                         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) > 0.00001
                        OR
                        (SUM(CASE WHEN s.stock_type IN ($co_stock_in_types) THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)
                         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) > 0.00001
                    )
                    LIMIT 1
                ");
                if ($agg_pick && !empty($agg_pick['pick_id'])) {
                    $pick_id = (int) $agg_pick['pick_id'];
                    if ($pick_id > 0) {
                        $source_stock = getRecord("SELECT * FROM tbl_stock WHERE id = $pick_id AND status = 1 LIMIT 1");
                    }
                }
            }

            if ($source_stock) {
                $stock_row = $source_stock;
                $branch_id = (int)($stock_row['branch_id'] ?? 0);
                $stock_metal_id = (int)($stock_row['metal_id'] ?? $metal_id);
                $stock_purity = (float)($stock_row['opening_purity'] ?? $purity);
                $stock_rate_val = (float)($stock_row['rate'] ?? $rate);
                $stock_value = $deduct_weight * $stock_rate_val;
                
                // Check if tbl_stock has reference columns
                $has_reference_cols = false;
                $ref_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
                if ($ref_check && mysqli_num_rows($ref_check) >= 2) {
                    $has_reference_cols = true;
                    mysqli_free_result($ref_check);
                }
                
                // Create outward entry in tbl_stock
                if ($has_reference_cols) {
                    $outward_sql = "
                        INSERT INTO tbl_stock (
                            product_id, product_characteristic_id, barcode, branch_id, metal_id,
                            opening_weight, opening_purity, opening_qty, final_weight, rate, value,
                            current_weight, current_qty, stock_type, transaction_date, status, created_at,
                            reference_id, reference_type
                        ) VALUES (
                            " . ($product_id > 0 ? $product_id : 'NULL') . ",
                            " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
                            '$barcode_esc',
                            $branch_id,
                            $stock_metal_id,
                            $deduct_weight,
                            $stock_purity,
                            $quantity,
                            $deduct_weight,
                            $stock_rate_val,
                            $stock_value,
                            $deduct_weight,
                            $quantity,
                            'outward',
                            '$consignment_date',
                            1,
                            NOW(),
                            $consignment_id,
                            'consignment_out'
                        )
                    ";
                } else {
                    $outward_sql = "
                        INSERT INTO tbl_stock (
                            product_id, product_characteristic_id, barcode, branch_id, metal_id,
                            opening_weight, opening_purity, opening_qty, final_weight, rate, value,
                            current_weight, current_qty, stock_type, transaction_date, status, created_at
                        ) VALUES (
                            " . ($product_id > 0 ? $product_id : 'NULL') . ",
                            " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
                            '$barcode_esc',
                            $branch_id,
                            $stock_metal_id,
                            $deduct_weight,
                            $stock_purity,
                            $quantity,
                            $deduct_weight,
                            $stock_rate_val,
                            $stock_value,
                            $deduct_weight,
                            $quantity,
                            'outward',
                            '$consignment_date',
                            1,
                            NOW()
                        )
                    ";
                }
                @mysqli_query($conn, $outward_sql);

                $src_id = (int) $source_stock['id'];
                $prev_cq = (float) ($source_stock['current_qty'] ?? 0);
                $prev_cw = (float) ($source_stock['current_weight'] ?? 0);
                $op_q = (float) ($source_stock['opening_qty'] ?? 0);
                $op_w = (float) ($source_stock['opening_weight'] ?? 0);
                $sold_q = (float) $quantity;

                if ($prev_cq > 0 || $prev_cw > 0) {
                    if ($sold_q <= 0 && $prev_cw > 0 && $prev_cq > 0) {
                        $sold_q = $prev_cq * ($deduct_weight / $prev_cw);
                    }
                    $balance_weight = $prev_cw - $deduct_weight;
                    $new_cq = max(0, $prev_cq - $sold_q);
                    if ($balance_weight <= 0) {
                        mysqli_query($conn, "UPDATE tbl_stock SET current_weight = 0, current_qty = 0, value = 0 WHERE id = $src_id");
                    } else {
                        $new_val = $stock_rate_val * $balance_weight;
                        mysqli_query($conn, "UPDATE tbl_stock SET current_weight = $balance_weight, current_qty = $new_cq, final_weight = $balance_weight, value = $new_val WHERE id = $src_id");
                    }
                } else {
                    $new_op_q = max(0, $op_q - $sold_q);
                    $new_op_w = max(0, $op_w - $deduct_weight);
                    if ($new_op_w <= 0.00001 && $new_op_q <= 0.00001) {
                        mysqli_query($conn, "UPDATE tbl_stock SET opening_qty = 0, opening_weight = 0, final_weight = 0, value = 0 WHERE id = $src_id");
                    } else {
                        $new_val = $stock_rate_val * $new_op_w;
                        mysqli_query($conn, "UPDATE tbl_stock SET opening_qty = $new_op_q, opening_weight = $new_op_w, final_weight = $new_op_w, value = $new_val WHERE id = $src_id");
                    }
                }
            } else {
                throw new Exception('Could not locate an inward stock line to deduct for barcode: ' . $barcode_esc);
            }
        } elseif ($characteristic_id > 0) {
            @mysqli_query($conn, "UPDATE tbl_product_characteristics SET quantity = GREATEST(0, quantity - $quantity) WHERE id = $characteristic_id");
        }

        // Stock History ledger (stock-history.php?ledger=1) — same tbl_stock_journal audit as sale / PI
        if ($co_item_id > 0 && ($product_id > 0 || trim((string) ($item['barcode'] ?? '')) !== '')) {
            require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
            $sj_key_co = 'CO' . (int) $consignment_id . 'I' . $co_item_id;
            if (strlen($sj_key_co) > 48) {
                $sj_key_co = 'C' . (int) $consignment_id . 'x' . $co_item_id;
            }
            $sj_date_co = trim((string) $consignment_date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sj_date_co)) {
                $sj_date_co = date('Y-m-d');
            }
            $hist_barcode = trim((string) ($item['barcode'] ?? ''));
            $hist_product_name = trim((string) ($item['product_name'] ?? ''));
            if ($hist_product_name === '' && $product_id > 0) {
                $pn_row = getRecord('SELECT name FROM tbl_products WHERE id = ' . $product_id . ' LIMIT 1');
                if ($pn_row && isset($pn_row['name'])) {
                    $hist_product_name = trim((string) $pn_row['name']);
                }
            }
            auragold_stock_history_audit_insert_row($conn, [
                'sj_invoice_no' => $sj_key_co,
                'item_id' => 0,
                'invoice_id' => 0,
                'invoice_no' => $consignment_no,
                'sj_date' => $sj_date_co,
                'barcode' => $hist_barcode,
                'product_id' => $product_id,
                'product_characteristic_id' => $characteristic_id,
                'product_name' => $hist_product_name,
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
                'tax_amount' => $item_tax_amount,
                'net_amount' => $net_amount,
                'net_amt_with_tax' => $net_amt_with_tax,
                'rfid_code' => trim((string) ($item['rfid_code'] ?? $item['rfid'] ?? '')),
                'voucher_type' => 'Consignment Out',
                'design_no' => trim((string) ($item['design_no'] ?? '')),
                'category' => trim((string) ($item['category'] ?? '')),
                'calculation' => trim((string) ($item['calculation_mode'] ?? $item['calculation'] ?? '')),
                'location' => trim((string) ($item['location'] ?? '')),
                'comment' => 'auragold_doc|src=co|cid=' . (int) $consignment_id . '|coi=' . (int) $co_item_id . '|',
            ]);
        }
    }

    if (!empty($payments_co)) {
        foreach ($payments_co as $__pco) {
            if (!is_array($__pco)) {
                continue;
            }
            $__mco = auragold_payment_merge_stored_details($__pco);
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mco)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mco);
        }
        $___cout_me_has_ref = auragold_metal_exchange_document_init($conn, $co_was_update, (int) $consignment_id, 'consignment_out_metal_exchange');
        $__co_plain_no = trim((string) $consignment_no);
        $__co_dt = substr(trim((string) $consignment_date), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__co_dt)) {
            $__co_dt = date('Y-m-d');
        }
        foreach ($payments_co as $pay_seq => $payment) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                continue;
            }
            $___pm_co = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'consignment_out_metal_exchange',
                (int) $consignment_id,
                $__co_plain_no,
                $__co_dt,
                $___pm_co,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___cout_me_has_ref,
                'Consignment Out — Metal Exchange',
                'cout_me',
                'COU-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Consignment Out',
        'verb' => $co_was_update ? 'updated' : 'created',
        'number' => $consignment_no,
        'party' => $customer_name,
        'doc_date' => $consignment_date,
        'due_date' => '',
        'ref_id' => (int) $consignment_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Consignment Out saved successfully',
        'consignment_id' => $consignment_id,
        'consignment_no' => $consignment_no,
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
