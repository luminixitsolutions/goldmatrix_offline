<?php
/**
 * After a jobwork invoice is saved: post finished barcodes from tbl_jobwork_order_items
 * into tbl_stock_journal + tbl_stock (purchase) so they appear in stock / stock history.
 *
 * Rows are tagged with comment: auragold_jwi|jwi_id={invoice_id}|joi_id={item_id}|
 * Re-save removes prior rows for that invoice and re-inserts.
 */

if (!function_exists('auragold_jobwork_invoice_remove_stock_for_invoice')) {

    function auragold_jobwork_invoice_remove_stock_for_invoice(mysqli $conn, int $jobwork_invoice_id): void
    {
        $jid = (int) $jobwork_invoice_id;
        if ($jid <= 0) {
            return;
        }
        $like = 'auragold_jwi|jwi_id=' . $jid . '|%';
        $like_esc = mysqli_real_escape_string($conn, $like);
        $q = @mysqli_query($conn, "SELECT id FROM tbl_stock_journal WHERE status = 'active' AND comment LIKE '$like_esc'");
        $ids = [];
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $ids[] = (int) ($r['id'] ?? 0);
            }
            mysqli_free_result($q);
        }
        if (empty($ids)) {
            return;
        }
        $in = implode(',', array_map('intval', $ids));

        $has_ref = false;
        $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        if ($rc && mysqli_num_rows($rc) >= 2) {
            $has_ref = true;
        }
        if ($rc) {
            mysqli_free_result($rc);
        }

        if ($has_ref) {
            @mysqli_query($conn, "DELETE FROM tbl_stock WHERE reference_type = 'stock_journal' AND reference_id IN ($in)");
        }

        $t_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
        if ($t_inward && mysqli_num_rows($t_inward) > 0) {
            mysqli_free_result($t_inward);
            @mysqli_query($conn, "DELETE FROM tbl_inward_stock WHERE stock_journal_id IN ($in)");
        } elseif ($t_inward) {
            mysqli_free_result($t_inward);
        }

        @mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE id IN ($in)");
    }

    /**
     * When JWO line weights are zero, copy from tbl_sale_order_items (same as save-jobwork-order.php).
     *
     * @param array<string,mixed> $item
     */
    function auragold_jobwork_invoice_merge_item_weights_from_sale_order(mysqli $conn, int $sale_order_id, array &$item): void
    {
        $sid = (int) $sale_order_id;
        if ($sid < 1) {
            return;
        }
        $g = (float) ($item['gross_weight'] ?? 0);
        $f = (float) ($item['final_weight'] ?? 0);
        $n = (float) ($item['net_weight'] ?? 0);
        if (($g > 0.0000001) || ($f > 0.0000001) || ($n > 0.0000001)) {
            return;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_items'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return;
        }
        mysqli_free_result($chk);

        $pid = (int) ($item['product_id'] ?? 0);
        $cid = isset($item['product_characteristic_id']) ? (int) $item['product_characteristic_id'] : 0;
        $barcode = isset($item['barcode']) ? trim((string) $item['barcode']) : '';
        $barcode_esc = mysqli_real_escape_string($conn, $barcode);

        $row = null;
        if ($pid > 0 && $cid > 0 && function_exists('getRecord')) {
            $row = getRecord('SELECT * FROM tbl_sale_order_items WHERE order_id = ' . $sid . ' AND product_id = ' . $pid
                . ' AND product_characteristic_id = ' . $cid . ' ORDER BY id ASC LIMIT 1');
        }
        if (!$row && $pid > 0 && $barcode !== '' && function_exists('getRecord')) {
            $row = getRecord('SELECT * FROM tbl_sale_order_items WHERE order_id = ' . $sid . ' AND product_id = ' . $pid
                . " AND TRIM(IFNULL(barcode,'')) = '" . $barcode_esc . "' ORDER BY id ASC LIMIT 1");
        }
        if (!$row && $pid > 0 && function_exists('getRecord')) {
            $row = getRecord('SELECT * FROM tbl_sale_order_items WHERE order_id = ' . $sid . ' AND product_id = ' . $pid . ' ORDER BY id ASC LIMIT 1');
        }
        if (!$row || !is_array($row)) {
            return;
        }

        $sg = isset($row['gross_weight']) ? (float) $row['gross_weight'] : 0.0;
        if ($sg > 0.0000001) {
            $item['gross_weight'] = $sg;
        }
        if (isset($row['less_weight']) && (float) $row['less_weight'] > 0.0000001) {
            $item['less_weight'] = (float) $row['less_weight'];
        }
        if (isset($row['net_weight']) && (float) $row['net_weight'] > 0.0000001) {
            $item['net_weight'] = (float) $row['net_weight'];
        }
        if (isset($row['final_weight']) && (float) $row['final_weight'] > 0.0000001) {
            $item['final_weight'] = (float) $row['final_weight'];
        }
        $pp = null;
        if (isset($row['pure_weight'])) {
            $pp = (float) $row['pure_weight'];
        } elseif (isset($row['purity_weight'])) {
            $pp = (float) $row['purity_weight'];
        }
        if ($pp !== null && $pp > 0.0000001) {
            $item['pure_weight'] = $pp;
            $item['purity_weight'] = $pp;
        }
        if (isset($row['purity']) && (float) $row['purity'] > 0.0000001) {
            $item['purity'] = (float) $row['purity'];
        }
    }

    /**
     * @param array<int,string> $posted_barcodes_out
     * @throws RuntimeException
     */
    function auragold_jobwork_invoice_apply_stock_in(
        mysqli $conn,
        int $jobwork_invoice_id,
        int $jobwork_order_id,
        string $invoice_no,
        int $repair_jobwork_order_id = 0,
        array &$posted_barcodes_out = []
    ): void {
        $jwi_id = (int) $jobwork_invoice_id;
        $jwo_id = (int) $jobwork_order_id;
        $rjwo_id = (int) $repair_jobwork_order_id;
        if ($jwi_id <= 0 || ($jwo_id <= 0 && $rjwo_id <= 0)) {
            return;
        }

        $invoice_no_esc = mysqli_real_escape_string($conn, $invoice_no);
        $journal_date_esc = mysqli_real_escape_string($conn, date('Y-m-d'));

        $has_reference_cols = false;
        $ref_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        if ($ref_cols && mysqli_num_rows($ref_cols) >= 2) {
            $has_reference_cols = true;
        }
        if ($ref_cols) {
            mysqli_free_result($ref_cols);
        }

        $has_stock_journal_id = false;
        $sjid_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
        if ($sjid_col && mysqli_num_rows($sjid_col) > 0) {
            $has_stock_journal_id = true;
        }
        if ($sjid_col) {
            mysqli_free_result($sjid_col);
        }

        auragold_jobwork_invoice_remove_stock_for_invoice($conn, $jwi_id);

        $sale_order_id = 0;
        $items = [];
        if ($jwo_id > 0) {
            $jwo = getRecord("SELECT id, sale_order_id, sale_order_no, jobwork_no FROM tbl_jobwork_orders WHERE id = $jwo_id LIMIT 1");
            if (!$jwo) {
                throw new RuntimeException('Job work order not found for stock posting');
            }
            $sale_order_id = isset($jwo['sale_order_id']) ? (int) $jwo['sale_order_id'] : 0;
            $items = getList("SELECT * FROM tbl_jobwork_order_items WHERE jobwork_order_id = $jwo_id ORDER BY id ASC");
        } else {
            $t_rj = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_jobwork_order_items'");
            if (!$t_rj || mysqli_num_rows($t_rj) === 0) {
                if ($t_rj) {
                    mysqli_free_result($t_rj);
                }
                return;
            }
            mysqli_free_result($t_rj);
            $items = getList("SELECT * FROM tbl_repair_jobwork_order_items WHERE repair_jobwork_order_id = $rjwo_id ORDER BY id ASC");
        }
        if (!is_array($items) || empty($items)) {
            return;
        }

        if ($sale_order_id > 0) {
            foreach ($items as $ik => $_row) {
                if (is_array($items[$ik])) {
                    auragold_jobwork_invoice_merge_item_weights_from_sale_order($conn, $sale_order_id, $items[$ik]);
                }
            }
        }

        $user_id = 0;
        if (isset($_SESSION['Admin']['id'])) {
            $user_id = (int) $_SESSION['Admin']['id'];
        } elseif (isset($_SESSION['user_id'])) {
            $user_id = (int) $_SESSION['user_id'];
        }

        foreach ($items as $item) {
            $joi_id = (int) ($item['id'] ?? 0);
            $product_id = (int) ($item['product_id'] ?? 0);
            $barcode = isset($item['barcode']) ? trim((string) $item['barcode']) : '';
            if ($product_id <= 0 || $barcode === '') {
                continue;
            }

            $characteristic_id = isset($item['product_characteristic_id']) ? (int) $item['product_characteristic_id'] : 0;
            $product_name = isset($item['product_name']) ? trim((string) $item['product_name']) : '';
            if ($product_name === '' && $product_id > 0) {
                $pn = getRecord("SELECT name FROM tbl_products WHERE id = $product_id AND status = 1 LIMIT 1");
                if ($pn && !empty($pn['name'])) {
                    $product_name = trim((string) $pn['name']);
                }
            }
            $product_name_esc = mysqli_real_escape_string($conn, $product_name);

            $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 0;
            $gross_weight = isset($item['gross_weight']) ? (float) $item['gross_weight'] : 0;
            $less_weight = isset($item['less_weight']) ? (float) $item['less_weight'] : 0;
            $net_weight = isset($item['net_weight']) ? (float) $item['net_weight'] : 0;
            $final_weight = isset($item['final_weight']) ? (float) $item['final_weight'] : 0;
            $purity = isset($item['purity']) ? (float) $item['purity'] : 0;
            $pure_weight = isset($item['pure_weight']) ? (float) $item['pure_weight'] : 0;
            $rate = isset($item['rate']) ? (float) $item['rate'] : 0;
            $amount = isset($item['amount']) ? (float) $item['amount'] : 0;
            $making_amount = isset($item['making_amount']) ? (float) $item['making_amount'] : 0;
            $tax_amount = isset($item['tax_amount']) ? (float) $item['tax_amount'] : 0;
            $net_amount = isset($item['net_amount']) ? (float) $item['net_amount'] : 0;
            $net_amt_with_tax = isset($item['net_amt_with_tax']) ? (float) $item['net_amt_with_tax'] : 0;
            $design_no = isset($item['design_no']) ? trim((string) $item['design_no']) : '';

            if ($quantity <= 0) {
                $quantity = 1;
            }
            $stock_weight = $gross_weight > 0 ? $gross_weight : ($final_weight > 0 ? $final_weight : ($net_weight > 0 ? $net_weight : 0));
            if ($stock_weight <= 0 && $pure_weight > 0) {
                $stock_weight = $pure_weight;
            }
            if ($stock_weight <= 0) {
                continue;
            }
            if ($net_weight <= 0) {
                $net_weight = $stock_weight - $less_weight;
            }
            $purity_weight = ($net_weight > 0 && $purity > 0) ? ($net_weight * $purity / 100) : 0;
            if ($pure_weight <= 0 && $purity_weight > 0) {
                $pure_weight = $purity_weight;
            }
            if ($final_weight <= 0) {
                $final_weight = $net_weight > 0 ? $net_weight : $stock_weight;
            }

            $metal_id = 0;
            $branch_id = 0;
            $stock_purity = $purity;
            if ($characteristic_id > 0) {
                $char_details = getRecord("SELECT branch_id, metal_id, opening_purity FROM tbl_product_characteristics WHERE id = $characteristic_id AND status = 1");
                if ($char_details) {
                    $branch_id = (int) $char_details['branch_id'];
                    $metal_id = (int) $char_details['metal_id'];
                    if ($stock_purity <= 0) {
                        $stock_purity = (float) ($char_details['opening_purity'] ?? 0);
                    }
                }
            }
            if ($metal_id <= 0 && $product_id > 0) {
                $default_metal = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 ORDER BY id DESC LIMIT 1");
                $metal_id = $default_metal ? (int) $default_metal['metal_id'] : 0;
            }
            if ($branch_id <= 0) {
                $branch_id = 1;
            }
            if ($metal_id <= 0) {
                $metal_id = 1;
            }
            if ($stock_purity <= 0) {
                $stock_purity = 100.0;
            }

            $metal_type = '';
            if ($metal_id > 0) {
                $metal_info = getRecord("SELECT system_name, display_name FROM tbl_metal WHERE id = $metal_id");
                if ($metal_info) {
                    $raw = $metal_info['system_name'] ?? strtolower((string) ($metal_info['display_name'] ?? ''));
                    $raw = trim((string) $raw);
                    if ($raw !== '') {
                        $metal_type = $raw;
                    }
                }
            }
            $metal_type_esc = $metal_type !== '' ? "'" . mysqli_real_escape_string($conn, $metal_type) . "'" : 'NULL';

            $category_esc = 'NULL';
            if ($product_id > 0) {
                $pcat = getRecord("SELECT c.name FROM tbl_products p LEFT JOIN tbl_categories c ON c.id = p.category_id WHERE p.id = $product_id LIMIT 1");
                if ($pcat && !empty($pcat['name'])) {
                    $category_esc = "'" . mysqli_real_escape_string($conn, (string) $pcat['name']) . "'";
                }
            }

            $sj_no = 'JWI' . $jwi_id . 'I' . $joi_id;
            if (strlen($sj_no) > 45) {
                $sj_no = 'J' . $jwi_id . 'x' . $joi_id;
            }
            $sj_no_esc = mysqli_real_escape_string($conn, $sj_no);

            $comment_raw = 'auragold_jwi|jwi_id=' . $jwi_id . '|joi_id=' . $joi_id . '|';
            $comment_esc = mysqli_real_escape_string($conn, $comment_raw);

            $barcode_esc = mysqli_real_escape_string($conn, $barcode);
            $design_esc = $design_no !== '' ? "'" . mysqli_real_escape_string($conn, $design_no) . "'" : 'NULL';

            $voucher_esc = mysqli_real_escape_string($conn, 'Jobwork Invoice');

            $stock_value = $net_amt_with_tax > 0 ? $net_amt_with_tax : ($net_amount > 0 ? $net_amount : $amount);

            $journal_sql = "
            INSERT INTO tbl_stock_journal (
                sj_invoice_no,
                item_id,
                invoice_id,
                invoice_no,
                sj_date,
                barcode,
                code,
                product_id,
                product_characteristic_id,
                product_name,
                metal_id,
                metal_type,
                quantity,
                karat,
                gross_weight,
                less_weight,
                net_weight,
                purity,
                purity_weight,
                pure_weight,
                final_weight,
                rate,
                amount,
                making_amount,
                tax_amount,
                net_amount,
                net_amt_with_tax,
                rfid_code,
                voucher_type,
                design_no,
                huid_no,
                category,
                calculation,
                location,
                pkt_wt,
                pkt_less_wt,
                requested_purity,
                requested,
                gold_loss_1,
                gold_loss_2,
                setting_charge,
                wastage_per,
                wastage_wt,
                alloy_wt,
                metal_value,
                metal_cost,
                discount_type,
                discount_per,
                discount_amount,
                discount,
                making_type,
                making_rate,
                making_cost,
                minimum_price,
                stone_charge_type,
                stone_weight,
                stone_rate,
                stone_amount,
                stone_cost,
                diamond_amount,
                purchase_amount,
                sale_amount,
                other_charge_type,
                other_weight,
                other_rate,
                other_info,
                other_amount,
                hallmark_amount,
                hallmark_rate,
                reverse,
                group_name,
                comment,
                status,
                created_by,
                created_at
            ) VALUES (
                '$sj_no_esc',
                NULL,
                NULL,
                '$invoice_no_esc',
                '$journal_date_esc',
                '$barcode_esc',
                NULL,
                $product_id,
                " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
                '$product_name_esc',
                " . ($metal_id ? $metal_id : 'NULL') . ",
                $metal_type_esc,
                $quantity,
                NULL,
                $gross_weight,
                $less_weight,
                $net_weight,
                $purity,
                $purity_weight,
                $pure_weight,
                $final_weight,
                $rate,
                $amount,
                $making_amount,
                $tax_amount,
                $net_amount,
                $net_amt_with_tax,
                NULL,
                '$voucher_esc',
                $design_esc,
                NULL,
                $category_esc,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                '$comment_esc',
                'active',
                $user_id,
                NOW()
            )
            ";

            if (!mysqli_query($conn, $journal_sql)) {
                throw new RuntimeException('Stock journal insert failed: ' . mysqli_error($conn));
            }
            $journal_id = (int) mysqli_insert_id($conn);

            $ref_cols_sql = $has_reference_cols ? ', reference_id, reference_type' : '';
            $ref_vals_sql = $has_reference_cols ? ", $journal_id, 'stock_journal'" : '';
            $sjid_col_sql = $has_stock_journal_id ? ', stock_journal_id' : '';
            $sjid_val_sql = $has_stock_journal_id ? ", $journal_id" : '';

            $barcode_sql = "'" . $barcode_esc . "'";
            $inward_stock_sql = "
                INSERT INTO tbl_stock (
                    product_id,
                    product_characteristic_id,
                    barcode,
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
                    $ref_cols_sql
                    $sjid_col_sql
                ) VALUES (
                    $product_id,
                    " . ($characteristic_id ? $characteristic_id : 'NULL') . ",
                    $barcode_sql,
                    $branch_id,
                    $metal_id,
                    $stock_weight,
                    $stock_purity,
                    $quantity,
                    " . ($final_weight > 0 ? $final_weight : $stock_weight) . ",
                    $rate,
                    $stock_value,
                    $stock_weight,
                    $quantity,
                    'purchase',
                    '$journal_date_esc',
                    NOW()
                    $ref_vals_sql
                    $sjid_val_sql
                )
            ";

            if (!mysqli_query($conn, $inward_stock_sql)) {
                throw new RuntimeException('Stock insert failed: ' . mysqli_error($conn));
            }

            $t_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
            if ($t_inward && mysqli_num_rows($t_inward) > 0) {
                mysqli_free_result($t_inward);
                $inward_val_sql = "
                    INSERT INTO tbl_inward_stock (
                        stock_journal_id, product_id, product_characteristic_id, barcode_no,
                        branch_id, metal_id, qty, weight, rate, value, stock_type, transaction_date, created_at
                    ) VALUES (
                        $journal_id,
                        $product_id,
                        " . ($characteristic_id ? $characteristic_id : 'NULL') . ",
                        $barcode_sql,
                        $branch_id,
                        " . ($metal_id ? $metal_id : 'NULL') . ",
                        $quantity,
                        $stock_weight,
                        $rate,
                        $stock_value,
                        'purchase',
                        '$journal_date_esc',
                        NOW()
                    )
                ";
                if (!mysqli_query($conn, $inward_val_sql)) {
                    throw new RuntimeException('tbl_inward_stock insert failed: ' . mysqli_error($conn));
                }
            } elseif ($t_inward) {
                mysqli_free_result($t_inward);
            }

            if ($characteristic_id > 0) {
                $has_qty = false;
                $has_wt = false;
                $upd_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics WHERE Field IN ('opening_qty','opening_weight')");
                if ($upd_cols) {
                    while ($c = mysqli_fetch_assoc($upd_cols)) {
                        if (($c['Field'] ?? '') === 'opening_qty') {
                            $has_qty = true;
                        }
                        if (($c['Field'] ?? '') === 'opening_weight') {
                            $has_wt = true;
                        }
                    }
                    mysqli_free_result($upd_cols);
                }
                if ($has_qty || $has_wt) {
                    $set_parts = [];
                    if ($has_qty) {
                        $set_parts[] = 'opening_qty = COALESCE(opening_qty, 0) + ' . $quantity;
                    }
                    if ($has_wt) {
                        $set_parts[] = 'opening_weight = COALESCE(opening_weight, 0) + ' . $stock_weight;
                    }
                    $upd_sql = 'UPDATE tbl_product_characteristics SET ' . implode(', ', $set_parts) . " WHERE id = $characteristic_id";
                    if (!mysqli_query($conn, $upd_sql)) {
                        throw new RuntimeException('Product characteristic update failed: ' . mysqli_error($conn));
                    }
                }
            }

            if ($barcode !== '') {
                $posted_barcodes_out[] = $barcode;
            }
        }
    }

    /**
     * Jobwork invoice complete: return issued diamonds/stones to stock (material receive against issue lines,
     * then clear sale-order gem issue rows). Metal finished goods use auragold_jobwork_invoice_apply_stock_in().
     *
     * @throws RuntimeException
     */
    function auragold_jobwork_invoice_apply_gem_stock_in(
        mysqli $conn,
        int $jobwork_invoice_id,
        string $invoice_no,
        int $sale_order_id
    ): void {
        $jwi_id = (int) $jobwork_invoice_id;
        if ($jwi_id <= 0) {
            return;
        }

        require_once __DIR__ . '/auragold_voucher_pending_diamond_stone.php';
        require_once __DIR__ . '/auragold_voucher_diamond_stock.php';
        require_once __DIR__ . '/auragold_voucher_stone_stock.php';
        require_once __DIR__ . '/auragold_material_issue_rows_for_sale_order.php';

        $invoice_no = trim($invoice_no);
        $doc_date = date('Y-m-d');
        $tx_ok = true;
        $tx_err = '';

        $pending_diamond = auragold_voucher_parse_pending_diamond_lines_from_post();
        $pending_stone = auragold_voucher_parse_pending_stone_lines_from_post();

        $diamond_receive = [];
        foreach ($pending_diamond as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $src_id = (int) ($ln['source_issue_id'] ?? 0);
            $wt = isset($ln['weight']) ? (float) $ln['weight'] : 0.0;
            if ($src_id < 1 || $wt <= 0.0000001) {
                continue;
            }
            $diamond_receive[] = [
                'source_issue_id' => $src_id,
                'weight' => $wt,
                'qty' => isset($ln['qty']) ? (float) $ln['qty'] : 0.0,
                'barcode' => isset($ln['barcode']) ? trim((string) $ln['barcode']) : '',
                'product_name' => isset($ln['product_name']) ? trim((string) $ln['product_name']) : '',
                'diamond_category' => isset($ln['diamond_category']) ? trim((string) $ln['diamond_category']) : '',
            ];
        }

        $stone_receive = [];
        foreach ($pending_stone as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $src_id = (int) ($ln['source_issue_id'] ?? 0);
            $wt = isset($ln['weight']) ? (float) $ln['weight'] : 0.0;
            if ($src_id < 1 || $wt <= 0.0000001) {
                continue;
            }
            $stone_receive[] = [
                'source_issue_id' => $src_id,
                'weight' => $wt,
                'qty' => isset($ln['qty']) ? (float) $ln['qty'] : 0.0,
                'barcode' => isset($ln['barcode']) ? trim((string) $ln['barcode']) : '',
                'product_name' => isset($ln['product_name']) ? trim((string) $ln['product_name']) : '',
                'stone_category' => isset($ln['stone_category']) ? trim((string) $ln['stone_category']) : '',
            ];
        }

        $soid = (int) $sale_order_id;
        if ($soid > 0) {
            $mi_diamonds = auragold_material_issue_list_diamond_rows_for_sale_order($conn, $soid);
            foreach ($mi_diamonds as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $bal_wt = (float) ($row['balance_weight'] ?? 0);
                if ($bal_wt <= 0.0000001) {
                    continue;
                }
                $issue_line_id = (int) ($row['issue_line_id'] ?? $row['id'] ?? 0);
                if ($issue_line_id < 1) {
                    continue;
                }
                $already = false;
                foreach ($diamond_receive as $ex) {
                    if ((int) ($ex['source_issue_id'] ?? 0) === $issue_line_id) {
                        $already = true;
                        break;
                    }
                }
                if ($already) {
                    continue;
                }
                $diamond_receive[] = [
                    'source_issue_id' => $issue_line_id,
                    'weight' => $bal_wt,
                    'qty' => (float) ($row['balance_qty'] ?? 0),
                    'barcode' => trim((string) ($row['barcode'] ?? '')),
                    'product_name' => trim((string) ($row['product_name'] ?? '')),
                    'diamond_category' => trim((string) ($row['diamond_category'] ?? 'Diamonds')),
                ];
            }

            $mi_stones = auragold_material_issue_list_stone_rows_for_sale_order($conn, $soid);
            foreach ($mi_stones as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $bal_wt = (float) ($row['balance_weight'] ?? 0);
                if ($bal_wt <= 0.0000001) {
                    continue;
                }
                $issue_line_id = (int) ($row['issue_line_id'] ?? $row['id'] ?? 0);
                if ($issue_line_id < 1) {
                    continue;
                }
                $already = false;
                foreach ($stone_receive as $ex) {
                    if ((int) ($ex['source_issue_id'] ?? 0) === $issue_line_id) {
                        $already = true;
                        break;
                    }
                }
                if ($already) {
                    continue;
                }
                $stone_receive[] = [
                    'source_issue_id' => $issue_line_id,
                    'weight' => $bal_wt,
                    'qty' => (float) ($row['balance_qty'] ?? 0),
                    'barcode' => trim((string) ($row['barcode'] ?? '')),
                    'product_name' => trim((string) ($row['product_name'] ?? '')),
                    'stone_category' => trim((string) ($row['stone_category'] ?? 'Stones')),
                ];
            }
        }

        if ($diamond_receive !== []) {
            auragold_voucher_apply_diamond_receive_allocations(
                $conn,
                $jwi_id,
                $diamond_receive,
                $invoice_no,
                $doc_date,
                $tx_ok,
                $tx_err,
                'jobwork_invoice'
            );
            if (!$tx_ok) {
                throw new RuntimeException($tx_err !== '' ? $tx_err : 'Diamond stock receive failed on jobwork invoice.');
            }
        }

        if ($stone_receive !== []) {
            auragold_voucher_apply_stone_receive_allocations(
                $conn,
                $jwi_id,
                $stone_receive,
                $invoice_no,
                $doc_date,
                $tx_ok,
                $tx_err,
                'jobwork_invoice'
            );
            if (!$tx_ok) {
                throw new RuntimeException($tx_err !== '' ? $tx_err : 'Stone stock receive failed on jobwork invoice.');
            }
        }

        if ($soid > 0) {
            auragold_jobwork_invoice_restore_sale_order_gem_issues($conn, $soid, $tx_ok, $tx_err);
            if (!$tx_ok) {
                throw new RuntimeException($tx_err !== '' ? $tx_err : 'Could not restore sale order gem stock on jobwork invoice.');
            }
        }
    }

    /**
     * Reverse sale-order diamond/stone allocations (return weight to inward stock).
     */
    function auragold_jobwork_invoice_restore_sale_order_gem_issues(
        mysqli $conn,
        int $sale_order_id,
        bool &$tx_ok,
        string &$tx_err
    ): void {
        $soid = (int) $sale_order_id;
        if (!$tx_ok || $soid <= 0) {
            return;
        }
        require_once __DIR__ . '/auragold_sale_order_diamond_stock.php';
        require_once __DIR__ . '/auragold_sale_order_stone_stock.php';
        require_once __DIR__ . '/auragold_voucher_diamond_stock.php';
        require_once __DIR__ . '/auragold_voucher_stone_stock.php';

        auragold_sale_order_ensure_diamond_issue_table($conn);
        if (function_exists('auragold_sale_order_ensure_stone_issue_table')) {
            auragold_sale_order_ensure_stone_issue_table($conn);
        }

        $d_rows = getList('SELECT id FROM tbl_sale_order_diamond_stock_issue WHERE order_id = ' . $soid . ' ORDER BY id ASC');
        if (is_array($d_rows)) {
            foreach ($d_rows as $rec) {
                $issue_id = (int) ($rec['id'] ?? 0);
                if ($issue_id < 1) {
                    continue;
                }
                auragold_voucher_remove_diamond_issue($conn, 'sale_order', $soid, $issue_id, $tx_ok, $tx_err);
                if (!$tx_ok) {
                    return;
                }
            }
        }

        $t_stone = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_stone_stock_issue'");
        if ($t_stone && mysqli_num_rows($t_stone) > 0) {
            mysqli_free_result($t_stone);
            $s_rows = getList(
                'SELECT id, stock_id, barcode, weight, qty FROM tbl_sale_order_stone_stock_issue WHERE order_id = '
                . $soid . ' ORDER BY id ASC'
            );
            if (is_array($s_rows)) {
                auragold_voucher_ensure_stone_issue_table($conn);
                foreach ($s_rows as $rec) {
                    $issue_id = (int) ($rec['id'] ?? 0);
                    $sid = (int) ($rec['stock_id'] ?? 0);
                    $wt = (float) ($rec['weight'] ?? 0);
                    $qt = (float) ($rec['qty'] ?? 0);
                    $bc = trim((string) ($rec['barcode'] ?? ''));
                    if ($issue_id < 1) {
                        continue;
                    }
                    if ($sid > 0 && $wt > 0.0000001) {
                        auragold_voucher_restore_inward_diamond_stock_after_removal($conn, $sid, $wt, $qt, $bc, $tx_ok, $tx_err);
                        if (!$tx_ok) {
                            return;
                        }
                    }
                    if (!@mysqli_query($conn, 'DELETE FROM tbl_sale_order_stone_stock_issue WHERE id = ' . $issue_id . ' LIMIT 1')) {
                        $tx_ok = false;
                        $tx_err = 'Could not clear sale order stone allocation: ' . mysqli_error($conn);

                        return;
                    }
                    @mysqli_query(
                        $conn,
                        "DELETE FROM tbl_voucher_stone_stock_issue WHERE voucher_kind = 'sale_order' AND voucher_id = "
                        . $soid . ' AND id = ' . $issue_id . ' LIMIT 1'
                    );
                    @mysqli_query(
                        $conn,
                        "DELETE FROM tbl_voucher_stone_stock_issue WHERE voucher_kind = 'sale_order' AND voucher_id = "
                        . $soid . ' AND stock_id = ' . $sid . ' LIMIT 1'
                    );
                }
            }
        } elseif ($t_stone) {
            mysqli_free_result($t_stone);
        }
    }
}
