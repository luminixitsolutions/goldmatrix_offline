<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_ensure_payment_mode_ledger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $has_rv_branch = auragold_ensure_table_branch_id_column($conn, 'tbl_receipt_vouchers');
    $hdr_branch    = auragold_transaction_header_branch_id();
    $eff_branch    = auragold_effective_branch_id();
    $rv_dup_sql    = ($has_rv_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';

    $voucher_id = isset($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : 0;
    $receipt_voucher_existed = ($voucher_id > 0);
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
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_receipt_vouchers') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

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
    $scrap_invoice_numbers = [];
    $scrap_ledger_segments = [];
    $metal_exchange_barcodes_out = [];

    $rv_money_types = ['cash', 'bank', 'cheque', 'upi', 'card'];
    $rv_company_types = ['cash', 'bank', 'cheque', 'upi', 'card', 'metal'];
    $sum_money_from_items = 0.0;
    $party_against_display = '';
    $party_against_parts = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            $pt = strtolower(trim($it['payment_type'] ?? ''));
            $a = (float) ($it['amount'] ?? 0);
            if (in_array($pt, $rv_money_types, true)) {
                $sum_money_from_items += $a;
            }
            if ($a > 0 && in_array($pt, $rv_company_types, true)) {
                $d = auragold_payment_mode_default_ledger_name($pt, trim((string) ($it['deposit_into'] ?? '')));
                // Company ledger is Debited → show Dr on party against
                $party_against_parts[] = $d . '(' . number_format($a, 2) . 'Dr)';
            }
        }
    }
    if (!empty($party_against_parts)) {
        $party_against_display = implode(', ', $party_against_parts);
    }
    if ($total_amount <= 0 && $sum_money_from_items > 0) {
        $total_amount = $sum_money_from_items;
    }

    // Gold/silver metal IDs for computing totals from items (receipt = add/summation)
    $gold_metal_ids = [];
    $silver_metal_ids = [];
    foreach (getList("SELECT id, LOWER(COALESCE(display_name, system_name, '')) as n FROM tbl_metal") as $m) {
        $id = (int)$m['id'];
        $n = $m['n'] ?? '';
        if (strpos($n, 'gold') !== false) {
            $gold_metal_ids[] = $id;
        } elseif (strpos($n, 'silver') !== false) {
            $silver_metal_ids[] = $id;
        }
    }
    $total_gold_pure = 0.000;
    $rv_weight_payment_types = ['metal', 'm. exch.', 'metal exchange', 'metal_exchange', 'scrap'];
    $metal_name_by_id = [];
    foreach (getList("SELECT id, LOWER(COALESCE(display_name, system_name, '')) as n FROM tbl_metal") as $m) {
        $id = (int) ($m['id'] ?? 0);
        $metal_name_by_id[$id] = (string) ($m['n'] ?? '');
    }
    $rv_classify_metal_wt = static function (int $metal_id, float $wt, string $metal_name_hint = '') use ($gold_metal_ids, $silver_metal_ids, $metal_name_by_id): array {
        if ($wt <= 0.00001) {
            return [0.0, 0.0];
        }
        if ($metal_id > 0 && in_array($metal_id, $gold_metal_ids, true)) {
            return [$wt, 0.0];
        }
        if ($metal_id > 0 && in_array($metal_id, $silver_metal_ids, true)) {
            return [0.0, $wt];
        }
        $hint = strtolower(trim($metal_name_hint));
        if ($hint === '' && $metal_id > 0) {
            $hint = (string) ($metal_name_by_id[$metal_id] ?? '');
        }
        if ($hint !== '' && strpos($hint, 'gold') !== false) {
            return [$wt, 0.0];
        }
        if ($hint !== '' && strpos($hint, 'silver') !== false) {
            return [0.0, $wt];
        }

        return [$wt, 0.0];
    };

    // Validation
    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }

    if (empty($voucher_no)) {
        throw new Exception('Voucher number is required');
    }

    $rv_row_branch_id = 0;
    if ($voucher_id > 0 && $has_rv_branch) {
        $rv_br = getRecord("SELECT branch_id FROM tbl_receipt_vouchers WHERE id = $voucher_id LIMIT 1");
        $rv_row_branch_id = (int) ($rv_br['branch_id'] ?? 0);
        auragold_branch_require_document_access($conn, 'tbl_receipt_vouchers', $voucher_id);
    }

    // Check if voucher number already exists (for new vouchers)
    if ($voucher_id == 0) {
        $existing = getRecord("SELECT id FROM tbl_receipt_vouchers WHERE voucher_no = '$voucher_no'$rv_dup_sql" . auragold_doc_series_active_sql($conn, 'tbl_receipt_vouchers'));
        if ($existing) {
            throw new Exception('Voucher number already exists');
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_receipt_vouchers', 'voucher_no']], $voucher_no);
        }
    }

    if ($voucher_id > 0) {
        // Update existing voucher
        $update_query = "
            UPDATE tbl_receipt_vouchers SET
                customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                customer_name = '$customer_name',
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                receipt_no = " . ($receipt_no ? "'$receipt_no'" : 'NULL') . ",
                voucher_type = " . ($voucher_type ? "'$voucher_type'" : 'NULL') . ",
                against = " . ($against ? "'$against'" : 'NULL') . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                currency = '$currency', $fx_update_sql
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
                comment = " . ($comment ? "'$comment'" : 'NULL') . "
                " . ($has_rv_branch && $eff_branch > 0 && $rv_row_branch_id === 0 ? ', branch_id = ' . (int) $eff_branch : '') . ",
                updated_at = NOW()
            WHERE id = $voucher_id
        ";

        if (!mysqli_query($conn, $update_query)) {
            throw new Exception('Error updating voucher: ' . mysqli_error($conn));
        }

        // Delete existing items
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=rv|hid=" . (int) $voucher_id . "|%'");
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=rv_in|rid=" . (int) $voucher_id . "|%'");
        auragold_metal_exchange_delete_stock_for_reference($conn, 'receipt_voucher', (int) $voucher_id);
        mysqli_query($conn, "DELETE FROM tbl_receipt_voucher_items WHERE voucher_id = $voucher_id");
    } else {
        // Insert new voucher
        $insert_query = "
            INSERT INTO tbl_receipt_vouchers (
                voucher_no, customer_id, customer_name, ref_no, receipt_no, voucher_type, against,
                sales_person, against_of, currency$fx_insert_col_sql, voucher_date, due_date, layaways_id,
                fixing_type, previous_balance, previous_gold, previous_silver,
                total_amount, total_gold, total_silver, comment, status, created_by,
                " . ($has_rv_branch ? 'branch_id, ' : '') . "created_at
            ) VALUES (
                '$voucher_no',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$customer_name',
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                " . ($receipt_no ? "'$receipt_no'" : 'NULL') . ",
                " . ($voucher_type ? "'$voucher_type'" : 'NULL') . ",
                " . ($against ? "'$against'" : 'NULL') . ",
                " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                " . ($against_of ? "'$against_of'" : 'NULL') . ",
                '$currency'$fx_insert_val_sql,
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
                " . ($has_rv_branch ? ((int) $hdr_branch > 0 ? (int) $hdr_branch : 'NULL') . ', ' : '') . "
                NOW()
            )
        ";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception('Error inserting voucher: ' . mysqli_error($conn));
        }

        $voucher_id = mysqli_insert_id($conn);
    }

    if (is_array($items)) {
        foreach ($items as $__rvi) {
            if (!is_array($__rvi)) {
                continue;
            }
            $__mrg = auragold_payment_merge_stored_details($__rvi);
            if (!auragold_payment_is_metal_exchange_outward($__mrg)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mrg);
        }
    }
    $___rv_me_has_ref = auragold_metal_exchange_document_init($conn, $receipt_voucher_existed, (int) $voucher_id, 'receipt_voucher_metal_exchange');
    $___rv_in_has_ref = auragold_prepare_tbl_stock_reference_columns($conn);

    $rv_stock_branch = 0;
    if ($has_rv_branch && $voucher_id > 0) {
        $rv_br_st = getRecord("SELECT branch_id FROM tbl_receipt_vouchers WHERE id = $voucher_id LIMIT 1");
        $rv_stock_branch = (int) ($rv_br_st['branch_id'] ?? 0);
    }
    if ($rv_stock_branch <= 0 && $eff_branch > 0) {
        $rv_stock_branch = (int) $eff_branch;
    }
    if ($rv_stock_branch <= 0) {
        $rv_stock_branch = auragold_metal_exchange_default_branch_id();
    }

    // Insert receipt items
    if (is_array($items) && count($items) > 0) {
        foreach ($items as $pay_seq => $item) {
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
            $purity_wt = isset($item['purity_wt']) ? (float)$item['purity_wt'] : (isset($item['purity_weight']) ? (float)$item['purity_weight'] : 0.000);

            $amount = isset($item['amount']) ? (float)$item['amount'] : 0.00;
            $previous_balance_amount = isset($item['previous_balance_amount']) ? (float)$item['previous_balance_amount'] : 0.00;

            $__rv_keep_line = auragold_should_persist_payment_row_with_metal_exchange($conn, $item)
                || strlen(trim((string) ($item['payment_type'] ?? ''))) > 0;
            if (!$__rv_keep_line) {
                continue;
            }

            $item_query = "
                INSERT INTO tbl_receipt_voucher_items (
                    voucher_id, payment_type, diamond_category, transaction_no, deposit_into,
                    product_id, cheque_date, weight, metal_id, quantity, purity_carat, purity_wt,
                    amount, previous_balance_amount,
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
                    $amount,
                    $previous_balance_amount,
                    1,
                    NOW()
                )
            ";

            if (!mysqli_query($conn, $item_query)) {
                throw new Exception('Error inserting voucher item: ' . mysqli_error($conn));
            }
            $rv_line_id = (int) mysqli_insert_id($conn);
            $rv_dt = trim((string) ($_POST['voucher_date'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rv_dt)) {
                $rv_dt = date('Y-m-d');
            }
            if (auragold_payment_is_metal_exchange_outward($item)) {
                auragold_post_receipt_voucher_metal_inward_to_stock(
                    $conn,
                    (int) $voucher_id,
                    trim((string) ($_POST['voucher_no'] ?? '')),
                    $rv_dt,
                    $item,
                    $rv_line_id,
                    is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                    $rv_stock_branch,
                    $___rv_in_has_ref
                );
            } else {
                require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
                $rw = $purity_wt > 0 ? $purity_wt : $weight;
                auragold_stock_history_audit_for_document_barcode_line($conn, 'Receipt Voucher', trim((string) ($_POST['voucher_no'] ?? '')), $rv_dt, 'RV', (int) $voucher_id, $rv_line_id, 'rv', [
                    'barcode' => trim((string) ($item['barcode_no'] ?? $item['barcode'] ?? '')),
                    'product_id' => $product_id,
                    'metal_id' => $metal_id,
                    'quantity' => $quantity,
                    'gross_weight' => $weight,
                    'less_weight' => 0,
                    'net_weight' => $rw,
                    'purity_weight' => $purity_wt,
                    'pure_weight' => $rw,
                    'final_weight' => $rw,
                    'purity' => 0,
                    'rate' => isset($item['rate']) ? (float) $item['rate'] : 0,
                    'amount' => $amount,
                    'tax_amount' => 0,
                    'net_amount' => $amount,
                    'net_amt_with_tax' => $amount,
                    'category' => trim((string) ($item['diamond_category'] ?? '')),
                ]);
            }
        }
        // Receipt voucher: compute total_amount, total_gold, total_silver from items (add/summation, not deduct)
        $sum_amt = 0.000;
        $sum_gold = 0.000;
        $sum_silver = 0.000;
        $sum_gold_pure = 0.000;
        foreach ($items as $it) {
            $sum_amt += isset($it['amount']) ? (float)$it['amount'] : 0.00;
            $ptype_raw = strtolower(trim((string) ($it['payment_type'] ?? '')));
            if (!in_array($ptype_raw, $rv_weight_payment_types, true)) {
                continue;
            }
            $mid = isset($it['metal_id']) ? (int)$it['metal_id'] : 0;
            $pwt = isset($it['purity_wt']) ? (float)$it['purity_wt'] : (isset($it['purity_weight']) ? (float)$it['purity_weight'] : 0.000);
            $gwt = isset($it['weight']) ? (float)$it['weight'] : 0.000;
            $wt = $pwt > 0 ? $pwt : $gwt;
            $hint = trim((string) ($it['metal'] ?? $it['metal_display'] ?? $it['product'] ?? ''));
            [$gAdd, $sAdd] = $rv_classify_metal_wt($mid, $wt, $hint);
            if ($gAdd > 0) {
                $sum_gold += $gAdd;
                $sum_gold_pure += $gAdd;
            }
            if ($sAdd > 0) {
                $sum_silver += $sAdd;
            }
        }
        $total_amount = $sum_amt;
        $total_gold = $sum_gold;
        $total_silver = $sum_silver;
        $total_gold_pure = $sum_gold_pure;
        // If we have gold weight but gold_pure still 0 (e.g. metal not in gold_metal_ids), use total_gold so balance_gold_pure updates
        if ($total_gold > 0 && $total_gold_pure <= 0) {
            $total_gold_pure = $total_gold;
        }
        // Update voucher header with totals from items
        mysqli_query($conn, "UPDATE tbl_receipt_vouchers SET total_amount = " . (float)$total_amount . ", total_gold = " . (float)$total_gold . ", total_silver = " . (float)$total_silver . " WHERE id = $voucher_id");
    }

    // Scrap lines: create OJB invoice per row (linked to this receipt voucher for edit/delete)
    $t_ojb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
    $t_ojb_i = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
    $t_ojb_p = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_payments'");
    if ($t_ojb && mysqli_num_rows($t_ojb) > 0 && $t_ojb_i && mysqli_num_rows($t_ojb_i) > 0 && is_array($items) && count($items) > 0) {
        mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE comment LIKE '%[[RV_LINK_ID:" . (int)$voucher_id . "]]%'");
        $last_ojb = getRecord("SELECT invoice_no FROM tbl_old_jewelry_scrap_invoices ORDER BY id DESC LIMIT 1");
        $next_ojb_num = 1;
        if ($last_ojb && !empty($last_ojb['invoice_no'])) {
            $next_ojb_num = (int)preg_replace('/[^0-9]/', '', $last_ojb['invoice_no']) + 1;
        }
        $cust_sql_id = $customer_id > 0 ? (int)$customer_id : 'NULL';
        $sales_sql = ($sales_person !== '') ? "'" . mysqli_real_escape_string($conn, $sales_person) . "'" : 'NULL';
        $created_by_sql = $created_by ? (int)$created_by : 'NULL';

        foreach ($items as $sitem) {
            if (strcasecmp(trim($sitem['payment_type'] ?? ''), 'Scrap') !== 0) {
                continue;
            }
            $sam = isset($sitem['amount']) ? (float)$sitem['amount'] : 0.00;
            if ($sam <= 0) {
                continue;
            }
            $ojb_no = 'OJB-' . $next_ojb_num;
            $next_ojb_num++;
            while (getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE invoice_no = '" . mysqli_real_escape_string($conn, $ojb_no) . "'" . auragold_doc_series_active_sql($conn, 'tbl_old_jewelry_scrap_invoices') . " LIMIT 1")) {
                $ojb_no = 'OJB-' . $next_ojb_num;
                $next_ojb_num++;
            }

            $sw = isset($sitem['weight']) ? (float)$sitem['weight'] : 0.000;
            $snet = $sw;
            $spure = isset($sitem['purity_wt']) ? (float)$sitem['purity_wt'] : (isset($sitem['purity_weight']) ? (float)$sitem['purity_weight'] : 0.000);
            $sqty = isset($sitem['quantity']) ? (float)$sitem['quantity'] : 1.00;
            $srate = isset($sitem['rate']) ? (float)$sitem['rate'] : 0.00;
            $purity_num = 0.00;
            if (!empty($sitem['purity_carat'])) {
                $purity_num = (float)preg_replace('/[^0-9.]/', '', (string)$sitem['purity_carat']);
            }
            $pid = isset($sitem['product_id']) ? (int)$sitem['product_id'] : 0;
            $desc_item = trim((string)($sitem['product'] ?? ''));
            if ($desc_item === '' && $pid > 0) {
                $pr = @getRecord("SELECT name FROM tbl_products WHERE id = $pid LIMIT 1");
                if ($pr && !empty($pr['name'])) {
                    $desc_item = $pr['name'];
                }
            }
            if ($desc_item === '') {
                $desc_item = 'Scrap';
            }
            $desc_item_esc = mysqli_real_escape_string($conn, $desc_item);
            $ojb_comment = mysqli_real_escape_string($conn, 'Auto from Receipt Voucher ' . $voucher_no . ' [[RV_LINK_ID:' . (int)$voucher_id . ']]');
            $ojb_no_esc = mysqli_real_escape_string($conn, $ojb_no);

            $ins_h = "
                INSERT INTO tbl_old_jewelry_scrap_invoices (
                    invoice_no, customer_id, customer_name, currency, invoice_date,
                    subtotal, net_total, grand_total, paid_amt, balance_amt,
                    comment, status, created_by, sales_person
                ) VALUES (
                    '$ojb_no_esc', $cust_sql_id, '$customer_name', '$currency', '$voucher_date',
                    $sam, $sam, $sam, $sam, 0.00,
                    '$ojb_comment', 'draft', $created_by_sql, $sales_sql
                )
            ";
            if (!mysqli_query($conn, $ins_h)) {
                throw new Exception('Scrap invoice header failed: ' . mysqli_error($conn));
            }
            $ojb_id = (int)mysqli_insert_id($conn);

            $ins_it = "
                INSERT INTO tbl_old_jewelry_scrap_invoice_items (
                    invoice_id, description, gross_wt, final_wt, net_wt, pure_wt,
                    amount, net_amt, quantity, purity, rate
                ) VALUES (
                    $ojb_id, '$desc_item_esc', $sw, $sw, $snet, $spure,
                    $sam, $sam, $sqty, $purity_num, $srate
                )
            ";
            if (!mysqli_query($conn, $ins_it)) {
                throw new Exception('Scrap invoice item failed: ' . mysqli_error($conn));
            }

            if ($t_ojb_p && mysqli_num_rows($t_ojb_p) > 0) {
                $rv_no_esc = mysqli_real_escape_string($conn, $voucher_no);
                $ins_pm = "
                    INSERT INTO tbl_old_jewelry_scrap_invoice_payments (
                        invoice_id, payment_type, deposit_into, transaction_no, amount
                    ) VALUES (
                        $ojb_id, 'Receipt Voucher', NULL, '$rv_no_esc', $sam
                    )
                ";
                if (!mysqli_query($conn, $ins_pm)) {
                    throw new Exception('Scrap invoice payment row failed: ' . mysqli_error($conn));
                }
            }

            $scrap_invoice_numbers[] = $ojb_no;
            $scrap_ledger_segments[] = ['ojb' => $ojb_no, 'amount' => $sam];
        }
    }

    $rv_branch_for_ledger = 0;
    if (!empty($has_rv_branch)) {
        $rvb = getRecord("SELECT branch_id FROM tbl_receipt_vouchers WHERE id = $voucher_id LIMIT 1");
        $rv_branch_for_ledger = (int) ($rvb['branch_id'] ?? 0);
    }
    if ($rv_branch_for_ledger <= 0 && $eff_branch > 0) {
        $rv_branch_for_ledger = (int) $eff_branch;
    }
    auragold_ensure_customer_ledger_branch_column($conn);
    $ledger_has_branch_col = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = ($ledger_has_branch_col && $rv_branch_for_ledger > 0)
        ? ', ' . (int) $rv_branch_for_ledger
        : ($ledger_has_branch_col ? ', NULL' : '');
    $ledger_br_scope = auragold_customer_ledger_branch_scope_sql($conn, $rv_branch_for_ledger);

    // Customer + Cash/Bank ledger rows for this voucher. Delete all lines first (party + deposit accounts).
    // IMPORTANT: When updating, remove old rows so last balance is before this voucher, then re-post.
    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_type = 'receipt_voucher' AND transaction_id = $voucher_id AND status = 1
    ");

    // Fallback: ensure gold purity total is set from gross gold when we have metal but summation didn't set purity (e.g. metal not in gold_metal_ids)
    if ($total_gold > 0 && $total_gold_pure <= 0) {
        $total_gold_pure = $total_gold;
    }
    // When no items sent (e.g. form serialization), use POST totals so ledger still gets metal/purity
    if (empty($items) && (float)($_POST['total_gold'] ?? 0) > 0 && $total_gold_pure <= 0) {
        $total_gold_pure = (float)($_POST['total_gold'] ?? 0);
    }
    if (empty($items) && (float)($_POST['total_silver'] ?? 0) > 0 && $total_silver <= 0) {
        $total_silver = (float)($_POST['total_silver'] ?? 0);
    }

    $ledger_customer_id = $customer_id > 0 ? $customer_id : 0;
    $last_balance = null;
    $has_gold_pure = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'balance_gold_pure'");
    $use_gold_pure = ($has_gold_pure && mysqli_num_rows($has_gold_pure) > 0);

    if ($ledger_customer_id > 0) {
        $cols = $use_gold_pure ? "balance_amount, balance_gold, balance_silver, balance_gold_pure" : "balance_amount, balance_gold, balance_silver";
        $last_balance = getRecord("
            SELECT $cols
            FROM tbl_customer_ledger
            WHERE customer_id = $ledger_customer_id AND status = 1
            $ledger_br_scope
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
    }
    if (!$last_balance && !empty($customer_name)) {
        $cols = $use_gold_pure ? "balance_amount, balance_gold, balance_silver, balance_gold_pure" : "balance_amount, balance_gold, balance_silver";
        $last_balance = getRecord("
            SELECT $cols
            FROM tbl_customer_ledger
            WHERE customer_name = '" . mysqli_real_escape_string($conn, $customer_name) . "' AND status = 1
            $ledger_br_scope
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        if (!$last_balance) {
            $bal = @getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_balance WHERE customer_name = '" . mysqli_real_escape_string($conn, $customer_name) . "' LIMIT 1");
            if ($bal) {
                $last_balance = $bal;
            }
        }
    }
    $prev_amt = (float)($last_balance['balance_amount'] ?? 0);
    $prev_gold = (float)($last_balance['balance_gold'] ?? 0);
    $prev_silver = (float)($last_balance['balance_silver'] ?? 0);
    $prev_gold_pure = $use_gold_pure ? (float)($last_balance['balance_gold_pure'] ?? 0) : 0.000;

    // Receipt Voucher: money + metal on party Credit; company Cash/Bank/Metal Debit.
    // balance_amount follows CL = opening + Dr − Cr → Credit decreases running balance.
    // Scrap rupee value: extra Credit line(s) vs OJB.
    $new_balance_amt_final = $prev_amt - $total_amount;
    $new_balance_gold = $prev_gold - $total_gold;
    $new_balance_silver = $prev_silver - $total_silver;
    $debit_gold = 0;
    $credit_gold = $total_gold;
    $debit_silver = 0;
    $credit_silver = $total_silver;
    $debit_gold_pure = 0;
    $credit_gold_pure = $total_gold_pure;
    $new_balance_gold_pure = $use_gold_pure ? ($prev_gold_pure - $credit_gold_pure) : 0.000;

    $sum_scrap_amt = 0.0;
    foreach ($scrap_ledger_segments as $seg) {
        $sum_scrap_amt += (float)($seg['amount'] ?? 0);
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : null);
    $ref_sql = $ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL';
    $cust_name_esc = mysqli_real_escape_string($conn, $customer_name);
    $desc = (strtolower(trim($against)) === 'against advance')
        ? "Receipt Voucher $voucher_no (Advance Adjusted - " . number_format($total_amount, 2) . ")"
        : "Receipt Voucher: $voucher_no";
    if ($total_gold > 0 || $total_silver > 0) {
        $desc .= " (Hedging)";
    }
    $desc_esc = mysqli_real_escape_string($conn, $desc);
    $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
    $ledger_has_against_cols = ($has_against && mysqli_num_rows($has_against) > 0);
    $against_cols = $ledger_has_against_cols ? ", against_ledger, against_invoice_no" : "";
    $party_against_inv = ($ref_no !== '' ? $ref_no : $voucher_no);
    if ($ledger_has_against_cols) {
        if ($party_against_display !== '') {
            $against_vals = ", '" . mysqli_real_escape_string($conn, $party_against_display) . "', '" . mysqli_real_escape_string($conn, $party_against_inv) . "'";
        } else {
            $against_vals = ", NULL, NULL";
        }
    } else {
        $against_vals = "";
    }
    $ledger_debit_credit = $use_gold_pure
        ? "debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,"
        : "debit_gold, credit_gold, debit_silver, credit_silver,";
    $ledger_balance = $use_gold_pure
        ? "balance_amount, balance_gold, balance_gold_pure, balance_silver,"
        : "balance_amount, balance_gold, balance_silver,";

    if (empty($scrap_ledger_segments) || $sum_scrap_amt <= 0) {
        $debit_amt = 0;
        $credit_amt = $total_amount;
        $ledger_debit_credit_vals = $use_gold_pure
            ? "$debit_gold, $credit_gold, $debit_gold_pure, $credit_gold_pure, $debit_silver, $credit_silver,"
            : "$debit_gold, $credit_gold, $debit_silver, $credit_silver,";
        $ledger_balance_vals = $use_gold_pure
            ? "$new_balance_amt_final, $new_balance_gold, $new_balance_gold_pure, $new_balance_silver,"
            : "$new_balance_amt_final, $new_balance_gold, $new_balance_silver,";

        $ledger_sql = "
            INSERT INTO tbl_customer_ledger (
                customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                $ledger_debit_credit
                $ledger_balance
                description, reference_no, status, created_by, created_at
                $against_cols
            ) VALUES (
                " . ($ledger_customer_id ? $ledger_customer_id : 0) . $ledger_branch_sql_val . ",
                '$cust_name_esc',
                'receipt_voucher',
                $voucher_id,
                '$voucher_no',
                '$voucher_date',
                $debit_amt,
                $credit_amt,
                $ledger_debit_credit_vals
                $ledger_balance_vals
                '$desc_esc',
                $ref_sql,
                1,
                " . ($user_id ? $user_id : 'NULL') . ",
                NOW()
                $against_vals
            )
        ";
        if (!mysqli_query($conn, $ledger_sql)) {
            throw new Exception('Customer ledger entry failed: ' . mysqli_error($conn));
        }
    } else {
        $money_credit = max(0.0, $total_amount - $sum_scrap_amt);
        $new_balance_amt_main = $prev_amt - $money_credit;
        $debit_amt = 0;
        $credit_amt = $money_credit;
        $ledger_debit_credit_vals = $use_gold_pure
            ? "$debit_gold, $credit_gold, $debit_gold_pure, $credit_gold_pure, $debit_silver, $credit_silver,"
            : "$debit_gold, $credit_gold, $debit_silver, $credit_silver,";
        $ledger_balance_vals_main = $use_gold_pure
            ? "$new_balance_amt_main, $new_balance_gold, $new_balance_gold_pure, $new_balance_silver,"
            : "$new_balance_amt_main, $new_balance_gold, $new_balance_silver,";

        $ledger_sql_main = "
            INSERT INTO tbl_customer_ledger (
                customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                $ledger_debit_credit
                $ledger_balance
                description, reference_no, status, created_by, created_at
                $against_cols
            ) VALUES (
                " . ($ledger_customer_id ? $ledger_customer_id : 0) . $ledger_branch_sql_val . ",
                '$cust_name_esc',
                'receipt_voucher',
                $voucher_id,
                '$voucher_no',
                '$voucher_date',
                $debit_amt,
                $credit_amt,
                $ledger_debit_credit_vals
                $ledger_balance_vals_main
                '$desc_esc',
                $ref_sql,
                1,
                " . ($user_id ? $user_id : 'NULL') . ",
                NOW()
                $against_vals
            )
        ";
        if (!mysqli_query($conn, $ledger_sql_main)) {
            throw new Exception('Customer ledger entry failed: ' . mysqli_error($conn));
        }

        $run_bal_amt = $new_balance_amt_main;
        foreach ($scrap_ledger_segments as $seg) {
            $samt = (float)($seg['amount'] ?? 0);
            if ($samt <= 0) {
                continue;
            }
            $run_bal_amt -= $samt;
            $ojb_no_seg = $seg['ojb'] ?? '';
            $desc_scrap_esc = mysqli_real_escape_string($conn, $desc . ' (Scrap ' . $ojb_no_seg . ')');
            if ($ledger_has_against_cols) {
                $agl = mysqli_real_escape_string($conn, $ojb_no_seg . '(' . number_format($samt, 2) . 'Dr)');
                $agi = mysqli_real_escape_string($conn, $ojb_no_seg);
                $against_vals_scrap = ", '$agl', '$agi'";
            } else {
                $against_vals_scrap = "";
            }
            $ledger_zero_metal = $use_gold_pure
                ? "0, 0, 0, 0, 0, 0,"
                : "0, 0, 0, 0,";
            $ledger_bal_scrap = $use_gold_pure
                ? "$run_bal_amt, $new_balance_gold, $new_balance_gold_pure, $new_balance_silver,"
                : "$run_bal_amt, $new_balance_gold, $new_balance_silver,";

            $ledger_sql_scrap = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    $ledger_debit_credit
                    $ledger_balance
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    " . ($ledger_customer_id ? $ledger_customer_id : 0) . $ledger_branch_sql_val . ",
                    '$cust_name_esc',
                    'receipt_voucher',
                    $voucher_id,
                    '$voucher_no',
                    '$voucher_date',
                    0,
                    $samt,
                    $ledger_zero_metal
                    $ledger_bal_scrap
                    '$desc_scrap_esc',
                    $ref_sql,
                    1,
                    " . ($user_id ? $user_id : 'NULL') . ",
                    NOW()
                    $against_vals_scrap
                )
            ";
            if (!mysqli_query($conn, $ledger_sql_scrap)) {
                throw new Exception('Customer scrap ledger entry failed: ' . mysqli_error($conn));
            }
        }
    }

    $new_balance_amt = $new_balance_amt_final;

    // Cash / Bank / Metal / company ledgers: Debit payment mode (double-entry vs party Credit)
    $against_inv_esc = mysqli_real_escape_string($conn, $ref_no !== '' ? $ref_no : $voucher_no);
    $voucher_no_db = mysqli_real_escape_string($conn, $voucher_no);
    $voucher_date_db = mysqli_real_escape_string($conn, $voucher_date);

    $company_post_items = [];
    if (is_array($items) && count($items) > 0) {
        foreach ($items as $item) {
            $pt = strtolower(trim($item['payment_type'] ?? ''));
            if (!in_array($pt, $rv_company_types, true)) {
                continue;
            }
            $line_amt = (float) ($item['amount'] ?? 0);
            $dep_raw = auragold_payment_mode_default_ledger_name($pt, trim((string) ($item['deposit_into'] ?? '')));
            $line_gold = 0.0;
            $line_silver = 0.0;
            if (in_array($pt, $rv_weight_payment_types, true)) {
                $mid = isset($item['metal_id']) ? (int) $item['metal_id'] : 0;
                $pwt = isset($item['purity_wt']) ? (float) $item['purity_wt'] : (isset($item['purity_weight']) ? (float) $item['purity_weight'] : 0.0);
                $wt = $pwt > 0 ? $pwt : (float) ($item['weight'] ?? 0);
                $hint = trim((string) ($item['metal'] ?? $item['metal_display'] ?? $item['product'] ?? ''));
                if ($wt > 0) {
                    [$line_gold, $line_silver] = $rv_classify_metal_wt($mid, $wt, $hint);
                }
            }
            if ($line_amt <= 0 && $line_gold <= 0 && $line_silver <= 0) {
                continue;
            }
            $company_post_items[] = [
                'payment_type' => $pt,
                'deposit_into' => $dep_raw,
                'amount' => $line_amt,
                'gold' => $line_gold,
                'silver' => $line_silver,
            ];
        }
    }
    // Fallback: voucher total with no item lines → debit company-name ledger
    if ($company_post_items === [] && $total_amount > 0) {
        $company_post_items[] = [
            'payment_type' => 'cash',
            'deposit_into' => auragold_payment_company_ledger_name(),
            'amount' => $total_amount,
            'gold' => 0.0,
            'silver' => 0.0,
        ];
    }

    foreach ($company_post_items as $citem) {
        $pt = (string) $citem['payment_type'];
        $line_amt = (float) $citem['amount'];
        $line_gold = (float) $citem['gold'];
        $line_silver = (float) $citem['silver'];
        $dep_raw = trim((string) $citem['deposit_into']);
        if ($dep_raw === '') {
            $dep_raw = auragold_payment_company_ledger_name();
        }

        $ensured = auragold_ensure_payment_mode_ledger($conn, $dep_raw, $pt, $rv_branch_for_ledger);
        if (!$ensured['ok']) {
            throw new Exception('Could not create company ledger "' . $dep_raw . '": ' . ($ensured['message'] ?? ''));
        }
        $dep_raw = $ensured['name'] !== '' ? $ensured['name'] : $dep_raw;
        $dep_esc = esc($dep_raw);

        $cash_balance_record = getRecord("
            SELECT balance_amount, balance_gold, balance_silver
            FROM tbl_customer_ledger
            WHERE customer_name = '$dep_esc'
            AND status = 1
            $ledger_br_scope
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        $cash_prev_balance = (float) ($cash_balance_record['balance_amount'] ?? 0);
        $cash_prev_gold = (float) ($cash_balance_record['balance_gold'] ?? 0);
        $cash_prev_silver = (float) ($cash_balance_record['balance_silver'] ?? 0);
        // Debit increases company running CL
        $cash_new_balance = $cash_prev_balance + $line_amt;
        $cash_new_gold = $cash_prev_gold + $line_gold;
        $cash_new_silver = $cash_prev_silver + $line_silver;
        $sl_ledger = mysqli_real_escape_string(
            $conn,
            accountledger_against_party_payment_label($customer_name, $pt, $line_amt > 0 ? $line_amt : max($line_gold, $line_silver), 'Cr')
        );
        $cash_desc_esc = mysqli_real_escape_string($conn, "Receipt mode ({$pt}) from {$customer_name} (Receipt Voucher {$voucher_no})");

        if ($ledger_has_against_cols) {
            $cash_ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at,
                    against_ledger, against_invoice_no
                ) VALUES (
                    0" . $ledger_branch_sql_val . ",
                    '$dep_esc',
                    'receipt_voucher',
                    $voucher_id,
                    '$voucher_no_db',
                    '$voucher_date_db',
                    $line_amt,
                    0,
                    " . (float) $line_gold . ",
                    0,
                    " . (float) $line_silver . ",
                    0,
                    $cash_new_balance,
                    $cash_new_gold,
                    $cash_new_silver,
                    '$cash_desc_esc',
                    $ref_sql,
                    1,
                    " . ($user_id ? $user_id : 'NULL') . ",
                    NOW(),
                    '$sl_ledger',
                    '$against_inv_esc'
                )
            ";
        } else {
            $cash_ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                ) VALUES (
                    0" . $ledger_branch_sql_val . ",
                    '$dep_esc',
                    'receipt_voucher',
                    $voucher_id,
                    '$voucher_no_db',
                    '$voucher_date_db',
                    $line_amt,
                    0,
                    " . (float) $line_gold . ",
                    0,
                    " . (float) $line_silver . ",
                    0,
                    $cash_new_balance,
                    $cash_new_gold,
                    $cash_new_silver,
                    '$cash_desc_esc',
                    $ref_sql,
                    1,
                    " . ($user_id ? $user_id : 'NULL') . ",
                    NOW()
                )
            ";
        }
        if (!mysqli_query($conn, $cash_ledger_sql)) {
            throw new Exception('Company ledger entry failed: ' . mysqli_error($conn));
        }
    }

    // Update tbl_customer_balance if exists
    $has_balance_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_balance'");
    if ($has_balance_table && mysqli_num_rows($has_balance_table) > 0) {
        $up = "INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_transaction_date, last_updated)
              VALUES (" . ($ledger_customer_id ? $ledger_customer_id : 0) . ", '$cust_name_esc', $new_balance_amt, $new_balance_gold, $new_balance_silver, '$voucher_date', NOW())
              ON DUPLICATE KEY UPDATE balance_amount = $new_balance_amt, balance_gold = $new_balance_gold, balance_silver = $new_balance_silver, last_transaction_date = '$voucher_date', last_updated = NOW()";
        @mysqli_query($conn, $up);
    }

    if ((int) $voucher_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'receipt_voucher', (int) $voucher_id, $voucher_no, $voucher_date);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Receipt Voucher',
        'verb' => $receipt_voucher_existed ? 'updated' : 'created',
        'number' => $voucher_no,
        'party' => $customer_name,
        'doc_date' => $voucher_date,
        'due_date' => $due_date,
        'ref_id' => (int) $voucher_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Receipt voucher saved successfully',
        'voucher_id' => $voucher_id,
        'voucher_no' => $voucher_no,
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
