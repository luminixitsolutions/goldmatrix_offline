<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_ensure_payment_mode_ledger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';

    $has_pv_branch = auragold_ensure_table_branch_id_column($conn, 'tbl_payment_vouchers');
    $hdr_branch    = auragold_transaction_header_branch_id();
    $eff_branch    = auragold_effective_branch_id();
    $pv_dup_sql    = ($has_pv_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';

    $voucher_id = isset($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : 0;
    $payment_voucher_existed = ($voucher_id > 0);
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
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_payment_vouchers') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
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
    $metal_exchange_barcodes_out = [];

    $pv_money_types = ['cash', 'bank', 'cheque', 'upi', 'card', 'scrap'];
    $pv_company_types = ['cash', 'bank', 'cheque', 'upi', 'card', 'metal'];
    $sum_money_from_items = 0.0;
    $party_against_display = '';
    $party_against_parts = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            $pt = strtolower(trim($it['payment_type'] ?? ''));
            $a = (float)($it['amount'] ?? 0);
            if (in_array($pt, $pv_money_types, true)) {
                $sum_money_from_items += $a;
            }
            if ($a > 0 && in_array($pt, $pv_company_types, true)) {
                $d = auragold_payment_mode_default_ledger_name($pt, trim((string) ($it['deposit_into'] ?? '')));
                // Company ledger is Credited → show Cr on party against
                $party_against_parts[] = $d . '(' . number_format($a, 2) . 'Cr)';
            }
        }
    }
    if (!empty($party_against_parts)) {
        $party_against_display = implode(', ', $party_against_parts);
    }
    if ($total_amount <= 0 && $sum_money_from_items > 0) {
        $total_amount = $sum_money_from_items;
    }

    // Metal id(s) for gold/silver: compute ledger totals from items so deduction is always correct
    $gold_metal_ids = [];
    $silver_metal_ids = [];
    $metal_name_by_id = [];
    foreach (getList("SELECT id, LOWER(COALESCE(display_name, system_name, '')) as n FROM tbl_metal") as $m) {
        $id = (int)$m['id'];
        $n = $m['n'] ?? '';
        $metal_name_by_id[$id] = $n;
        if (strpos($n, 'gold') !== false) {
            $gold_metal_ids[] = $id;
        } elseif (strpos($n, 'silver') !== false) {
            $silver_metal_ids[] = $id;
        }
    }
    $total_gold_pure = 0.000;
    $total_gold_from_items = 0.000;  // gold weight for ledger (Metal Exchange + Scrap)
    $total_silver_from_items = 0.000;
    // Payment types that carry gold/silver weight into customer ledger (Account Ledger report)
    $pv_weight_payment_types = ['metal', 'm. exch.', 'metal exchange', 'metal_exchange', 'scrap'];

    /**
     * Classify metal line weight as gold / silver from metal_id or metal name text.
     * @return array{0:float,1:float} [gold_wt, silver_wt]
     */
    $pv_classify_metal_wt = static function (int $metal_id, float $wt, string $metal_name_hint = '') use ($gold_metal_ids, $silver_metal_ids, $metal_name_by_id): array {
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
        // Metal exchange line with weight but unresolved type → gold (jewellery ERP default)
        return [$wt, 0.0];
    };

    // Validation
    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }

    if (empty($voucher_no)) {
        throw new Exception('Voucher number is required');
    }

    $pv_row_branch_id = 0;
    if ($voucher_id > 0 && $has_pv_branch) {
        $pv_br = getRecord("SELECT branch_id FROM tbl_payment_vouchers WHERE id = $voucher_id LIMIT 1");
        $pv_row_branch_id = (int) ($pv_br['branch_id'] ?? 0);
        auragold_branch_require_document_access($conn, 'tbl_payment_vouchers', $voucher_id);
    }

    // Check if voucher number already exists (for new vouchers)
    if ($voucher_id == 0) {
        $existing = getRecord("SELECT id FROM tbl_payment_vouchers WHERE voucher_no = '$voucher_no'$pv_dup_sql" . auragold_doc_series_active_sql($conn, 'tbl_payment_vouchers'));
        if ($existing) {
            throw new Exception('Voucher number already exists');
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_payment_vouchers', 'voucher_no']], $voucher_no);
        }
    }

    if ($voucher_id > 0) {
        // Update existing voucher
        $update_query = "
            UPDATE tbl_payment_vouchers SET
                customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                customer_name = '$customer_name',
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
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
                " . ($has_pv_branch && $eff_branch > 0 && $pv_row_branch_id === 0 ? ', branch_id = ' . (int) $eff_branch : '') . ",
                updated_at = NOW()
            WHERE id = $voucher_id
        ";

        if (!mysqli_query($conn, $update_query)) {
            throw new Exception('Error updating voucher: ' . mysqli_error($conn));
        }

        // Delete existing items
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=pv|hid=" . (int) $voucher_id . "|%'");
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=pv_out|rid=" . (int) $voucher_id . "|%'");
        auragold_metal_exchange_delete_stock_for_reference($conn, 'payment_voucher', (int) $voucher_id);
        mysqli_query($conn, "DELETE FROM tbl_payment_voucher_items WHERE voucher_id = $voucher_id");
    } else {
        // Insert new voucher
        $insert_query = "
            INSERT INTO tbl_payment_vouchers (
                voucher_no, customer_id, customer_name, ref_no, voucher_type, against,
                sales_person, against_of, currency$fx_insert_col_sql, voucher_date, due_date, layaways_id,
                fixing_type, previous_balance, previous_gold, previous_silver,
                total_amount, total_gold, total_silver, comment, status, created_by,
                " . ($has_pv_branch ? 'branch_id, ' : '') . "created_at
            ) VALUES (
                '$voucher_no',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$customer_name',
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
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
                " . ($has_pv_branch ? ((int) $hdr_branch > 0 ? (int) $hdr_branch : 'NULL') . ', ' : '') . "
                NOW()
            )
        ";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception('Error inserting voucher: ' . mysqli_error($conn));
        }

        $voucher_id = mysqli_insert_id($conn);
    }

    if (is_array($items)) {
        foreach ($items as $__pvi) {
            if (!is_array($__pvi)) {
                continue;
            }
            $__mrg = auragold_payment_merge_stored_details($__pvi);
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mrg)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mrg);
        }
    }
    $___pv_me_has_ref = auragold_metal_exchange_document_init($conn, $payment_voucher_existed, (int) $voucher_id, 'payment_voucher_metal_exchange');
    $___pv_out_has_ref = auragold_prepare_tbl_stock_reference_columns($conn);

    $pv_stock_branch = 0;
    if ($has_pv_branch && $voucher_id > 0) {
        $pv_br_st = getRecord("SELECT branch_id FROM tbl_payment_vouchers WHERE id = $voucher_id LIMIT 1");
        $pv_stock_branch = (int) ($pv_br_st['branch_id'] ?? 0);
    }
    if ($pv_stock_branch <= 0 && $eff_branch > 0) {
        $pv_stock_branch = (int) $eff_branch;
    }
    if ($pv_stock_branch <= 0) {
        $pv_stock_branch = auragold_metal_exchange_default_branch_id();
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
            $rate = isset($item['rate']) ? (float)$item['rate'] : 0.00;
            $amount = isset($item['amount']) ? (float)$item['amount'] : 0.00;
            $item_code = esc($item['item_code'] ?? '');
            $barcode_no = esc($item['barcode_no'] ?? '');
            $card_no = esc($item['card_no'] ?? '');
            $previous_balance_amount = isset($item['previous_balance_amount']) ? (float)$item['previous_balance_amount'] : 0.00;

            $__pv_keep_line = auragold_should_persist_payment_row_with_metal_exchange($conn, $item)
                || strlen(trim((string) ($item['payment_type'] ?? ''))) > 0;
            if (!$__pv_keep_line) {
                continue;
            }

            // Accumulate metal totals from Metal exchange + Scrap for ledger (Account Ledger weight columns)
            $ptype_raw = strtolower(trim((string) ($item['payment_type'] ?? '')));
            if (in_array($ptype_raw, $pv_weight_payment_types, true)) {
                $wt = $purity_wt > 0 ? $purity_wt : $weight;
                $metal_hint = trim((string) ($item['metal'] ?? $item['metal_display'] ?? $item['product'] ?? ''));
                [$gAdd, $sAdd] = $pv_classify_metal_wt($metal_id, $wt, $metal_hint);
                if ($gAdd > 0) {
                    $total_gold_pure += $gAdd;
                    $total_gold_from_items += $gAdd;
                }
                if ($sAdd > 0) {
                    $total_silver_from_items += $sAdd;
                }
            }
            
            // Build INSERT query with only existing columns
            // Note: transfer_from, item_code, barcode_no, card_no, rate may not exist in table
            // Adjust based on your actual table structure
            $item_query = "
                INSERT INTO tbl_payment_voucher_items (
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
            $pv_line_id = (int) mysqli_insert_id($conn);
            $pv_dt = trim((string) ($_POST['voucher_date'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pv_dt)) {
                $pv_dt = date('Y-m-d');
            }
            if (auragold_payment_is_metal_exchange_outward($item)) {
                auragold_post_payment_voucher_metal_outward_to_stock(
                    $conn,
                    (int) $voucher_id,
                    trim((string) ($_POST['voucher_no'] ?? '')),
                    $pv_dt,
                    $item,
                    $pv_line_id,
                    is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                    $pv_stock_branch,
                    $___pv_out_has_ref
                );
            } else {
                require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
                $pw = $purity_wt > 0 ? $purity_wt : $weight;
                auragold_stock_history_audit_for_document_barcode_line($conn, 'Payment Voucher', trim((string) ($_POST['voucher_no'] ?? '')), $pv_dt, 'PV', (int) $voucher_id, $pv_line_id, 'pv', [
                    'barcode' => trim((string) ($item['barcode_no'] ?? $item['barcode'] ?? '')),
                    'product_id' => $product_id,
                    'metal_id' => $metal_id,
                    'quantity' => $quantity,
                    'gross_weight' => $weight,
                    'less_weight' => 0,
                    'net_weight' => $pw,
                    'purity_weight' => $purity_wt,
                    'pure_weight' => $pw,
                    'final_weight' => $pw,
                    'purity' => 0,
                    'rate' => $rate,
                    'amount' => $amount,
                    'tax_amount' => 0,
                    'net_amount' => $amount,
                    'net_amt_with_tax' => $amount,
                    'category' => trim((string) ($item['diamond_category'] ?? '')),
                ]);
            }
        }
        // Keep voucher header in sync with items so total_gold/total_silver match ledger
        // Recompute from items (source of truth) — same approach as receipt voucher.
        $sum_gold = 0.000;
        $sum_silver = 0.000;
        $sum_gold_pure = 0.000;
        foreach ($items as $it) {
            $ptype = strtolower(trim((string) ($it['payment_type'] ?? '')));
            if (!in_array($ptype, $pv_weight_payment_types, true)) {
                continue;
            }
            $mid = isset($it['metal_id']) ? (int) $it['metal_id'] : 0;
            $pwt = isset($it['purity_wt']) ? (float) $it['purity_wt'] : (isset($it['purity_weight']) ? (float) $it['purity_weight'] : 0.000);
            $gwt = isset($it['weight']) ? (float) $it['weight'] : 0.000;
            $wt = $pwt > 0 ? $pwt : $gwt;
            $hint = trim((string) ($it['metal'] ?? $it['metal_display'] ?? $it['product'] ?? ''));
            [$gAdd, $sAdd] = $pv_classify_metal_wt($mid, $wt, $hint);
            if ($gAdd > 0) {
                $sum_gold += $gAdd;
                $sum_gold_pure += $gAdd;
            }
            if ($sAdd > 0) {
                $sum_silver += $sAdd;
            }
        }
        if ($sum_gold > 0 || $sum_silver > 0) {
            $total_gold_from_items = $sum_gold;
            $total_silver_from_items = $sum_silver;
            $total_gold_pure = $sum_gold_pure;
            $total_gold = $sum_gold;
            $total_silver = $sum_silver;
            mysqli_query($conn, "UPDATE tbl_payment_vouchers SET total_gold = " . (float)$total_gold . ", total_silver = " . (float)$total_silver . " WHERE id = $voucher_id");
        } elseif ($total_gold_from_items > 0 || $total_silver_from_items > 0) {
            $vg = ($total_gold_from_items > 0) ? $total_gold_from_items : $total_gold;
            $vs = ($total_silver_from_items > 0) ? $total_silver_from_items : $total_silver;
            mysqli_query($conn, "UPDATE tbl_payment_vouchers SET total_gold = " . (float)$vg . ", total_silver = " . (float)$vs . " WHERE id = $voucher_id");
        }
    }

    // Scrap payment lines: create Old Jewelry Scrap invoice (OJB-*) per line; customer ledger against_invoice shows OJB no.
    $t_ojb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
    $t_ojb_i = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
    $t_ojb_p = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_payments'");
    if ($t_ojb && mysqli_num_rows($t_ojb) > 0 && $t_ojb_i && mysqli_num_rows($t_ojb_i) > 0 && is_array($items) && count($items) > 0) {
        mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE comment LIKE '%[[PV_LINK_ID:" . (int)$voucher_id . "]]%'");
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
            $ojb_comment = mysqli_real_escape_string($conn, 'Auto from Payment Voucher ' . $voucher_no . ' [[PV_LINK_ID:' . (int)$voucher_id . ']]');
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
                $pv_no_esc = mysqli_real_escape_string($conn, $voucher_no);
                $ins_pm = "
                    INSERT INTO tbl_old_jewelry_scrap_invoice_payments (
                        invoice_id, payment_type, deposit_into, transaction_no, amount
                    ) VALUES (
                        $ojb_id, 'Payment Voucher', NULL, '$pv_no_esc', $sam
                    )
                ";
                if (!mysqli_query($conn, $ins_pm)) {
                    throw new Exception('Scrap invoice payment row failed: ' . mysqli_error($conn));
                }
            }

            $scrap_invoice_numbers[] = $ojb_no;
            $party_against_display .= ($party_against_display !== '' ? ', ' : '') . $ojb_no . '(' . number_format($sam, 2) . 'Dr)';
        }
    }

    $pv_branch_for_ledger = 0;
    if ($has_pv_branch) {
        $pvb = getRecord("SELECT branch_id FROM tbl_payment_vouchers WHERE id = $voucher_id LIMIT 1");
        $pv_branch_for_ledger = (int) ($pvb['branch_id'] ?? 0);
    }
    if ($pv_branch_for_ledger <= 0 && $eff_branch > 0) {
        $pv_branch_for_ledger = (int) $eff_branch;
    }
    auragold_ensure_customer_ledger_branch_column($conn);
    $ledger_has_branch_col = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = ($ledger_has_branch_col && $pv_branch_for_ledger > 0)
        ? ', ' . (int) $pv_branch_for_ledger
        : ($ledger_has_branch_col ? ', NULL' : '');
    $ledger_br_scope = auragold_customer_ledger_branch_scope_sql($conn, $pv_branch_for_ledger);

    // Post to customer ledger so payment voucher shows in customer ledger and affects previous balance
    // IMPORTANT: When updating a voucher, delete its old ledger entry FIRST so "last balance" is the
    // balance before this voucher (e.g. 2000). Then new_balance = 2000 - new_total (e.g. 0) = 2000.
    // Otherwise we'd read last_balance = 1500 and compute 1500 - 0 = 1500 (wrong).
    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_type = 'payment_voucher' AND transaction_id = $voucher_id AND status = 1
    ");

    $has_gold_pure_cols = false;
    $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
    if ($gpc && mysqli_num_rows($gpc) > 0) {
        $has_gold_pure_cols = true;
    }
    $gold_pure_select = $has_gold_pure_cols ? ", balance_gold_pure" : "";

    $ledger_customer_id = $customer_id > 0 ? $customer_id : 0;
    $last_balance = null;
    if ($ledger_customer_id > 0) {
        $last_balance = getRecord("
            SELECT balance_amount, balance_gold, balance_silver $gold_pure_select
            FROM tbl_customer_ledger
            WHERE customer_id = $ledger_customer_id AND status = 1
            $ledger_br_scope
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
    }
    if (!$last_balance && !empty($customer_name)) {
        $last_balance = getRecord("
            SELECT balance_amount, balance_gold, balance_silver $gold_pure_select
            FROM tbl_customer_ledger
            WHERE customer_name = '$customer_name' AND status = 1
            $ledger_br_scope
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        if (!$last_balance) {
            $last_balance = getRecord("
                SELECT balance_amount, balance_gold, balance_silver
                FROM tbl_customer_balance
                WHERE customer_name = '$customer_name' LIMIT 1
            ");
        }
    }
    $prev_amt = (float)($last_balance['balance_amount'] ?? 0);
    $prev_gold = (float)($last_balance['balance_gold'] ?? 0);
    $prev_silver = (float)($last_balance['balance_silver'] ?? 0);
    $prev_gold_pure = $has_gold_pure_cols ? (float)($last_balance['balance_gold_pure'] ?? 0) : 0;
    // Metal exchange / scrap: post weight on party Debit side (gold / silver).
    $ledger_metal_gold = ($total_gold_from_items > 0) ? $total_gold_from_items : $total_gold;
    $ledger_metal_silver = ($total_silver_from_items > 0) ? $total_silver_from_items : $total_silver;
    // Payment Voucher: money + metal on party Debit; company Cash/Bank/Metal Credit.
    // balance_amount follows CL = opening + Dr − Cr → Debit increases running balance.
    $new_balance_amt = $prev_amt + $total_amount;
    $new_balance_gold = $prev_gold + $ledger_metal_gold;
    $new_balance_silver = $prev_silver + $ledger_metal_silver;
    $new_balance_gold_pure = $prev_gold_pure + $total_gold_pure;

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : null);
    $ref_sql = $ref_no ? "'$ref_no'" : 'NULL';
    $desc = "Payment Voucher: $voucher_no";
    // Tag Hedging when Fixing Type is Hedging, or when metal weight is posted (Account Ledger metal columns).
    $fixing_type_norm = trim((string) ($_POST['fixing_type'] ?? $fixing_type ?? 'Standard'));
    if (strcasecmp($fixing_type_norm, 'Hedging') === 0 || $ledger_metal_gold > 0 || $ledger_metal_silver > 0) {
        $desc .= " (Hedging)";
    }
    $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
    $ledger_has_against_cols = ($has_against && mysqli_num_rows($has_against) > 0);
    $against_cols = $ledger_has_against_cols ? ", against_ledger, against_invoice_no" : "";
    $party_against_inv = !empty($scrap_invoice_numbers)
        ? implode(', ', $scrap_invoice_numbers)
        : ($ref_no !== '' ? $ref_no : $voucher_no);
    if ($ledger_has_against_cols) {
        if ($party_against_display !== '') {
            $against_vals = ", '" . mysqli_real_escape_string($conn, $party_against_display) . "', '" . mysqli_real_escape_string($conn, $party_against_inv) . "'";
        } else {
            $against_vals = ", NULL, NULL";
        }
    } else {
        $against_vals = "";
    }
    $gold_pure_cols = $has_gold_pure_cols ? ", debit_gold_pure, credit_gold_pure, balance_gold_pure" : "";
    $gold_pure_vals = $has_gold_pure_cols ? ", " . (float)$total_gold_pure . ", 0, " . (float)$new_balance_gold_pure : "";

    $desc_esc_led = mysqli_real_escape_string($conn, $desc);
    $ledger_sql = "
        INSERT INTO tbl_customer_ledger (
            customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount,
            debit_gold, credit_gold, debit_silver, credit_silver,
            balance_amount, balance_gold, balance_silver
            $gold_pure_cols
            , description, reference_no, status, created_by, created_at
            $against_cols
        ) VALUES (
            $ledger_customer_id" . $ledger_branch_sql_val . ",
            '$customer_name',
            'payment_voucher',
            $voucher_id,
            '$voucher_no',
            '$voucher_date',
            $total_amount,
            0,
            " . (float)$ledger_metal_gold . ",
            0,
            " . (float)$ledger_metal_silver . ",
            0,
            $new_balance_amt,
            $new_balance_gold,
            $new_balance_silver
            $gold_pure_vals
            , '$desc_esc_led',
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

    // Cash / Bank / Metal / company ledgers: Credit payment mode (double-entry vs party Debit)
    $ledger_has_against = $ledger_has_against_cols;
    $against_inv_esc = mysqli_real_escape_string($conn, $ref_no !== '' ? $ref_no : $voucher_no);
    $voucher_no_db = mysqli_real_escape_string($conn, $voucher_no);
    $voucher_date_db = mysqli_real_escape_string($conn, $voucher_date);

    $company_post_items = [];
    if (is_array($items) && count($items) > 0) {
        foreach ($items as $item) {
            $pt = strtolower(trim($item['payment_type'] ?? ''));
            if (!in_array($pt, $pv_company_types, true)) {
                continue;
            }
            $line_amt = (float) ($item['amount'] ?? 0);
            $dep_raw = auragold_payment_mode_default_ledger_name($pt, trim((string) ($item['deposit_into'] ?? '')));
            $line_gold = 0.0;
            $line_silver = 0.0;
            if (in_array($pt, $pv_weight_payment_types, true)) {
                $mid = isset($item['metal_id']) ? (int) $item['metal_id'] : 0;
                $pwt = isset($item['purity_wt']) ? (float) $item['purity_wt'] : (isset($item['purity_weight']) ? (float) $item['purity_weight'] : 0.0);
                $wt = $pwt > 0 ? $pwt : (float) ($item['weight'] ?? 0);
                $hint = trim((string) ($item['metal'] ?? $item['metal_display'] ?? $item['product'] ?? ''));
                if ($wt > 0) {
                    [$line_gold, $line_silver] = $pv_classify_metal_wt($mid, $wt, $hint);
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

        $ensured = auragold_ensure_payment_mode_ledger($conn, $dep_raw, $pt, $pv_branch_for_ledger);
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
        // Credit decreases company running CL
        $cash_new_balance = $cash_prev_balance - $line_amt;
        $cash_new_gold = $cash_prev_gold - $line_gold;
        $cash_new_silver = $cash_prev_silver - $line_silver;
        $sl_ledger = mysqli_real_escape_string(
            $conn,
            accountledger_against_party_payment_label($customer_name, $pt, $line_amt > 0 ? $line_amt : max($line_gold, $line_silver), 'Dr')
        );
        $cash_desc_esc = mysqli_real_escape_string($conn, "Payment mode ({$pt}) for {$customer_name} (Payment Voucher {$voucher_no})");

        if ($ledger_has_against) {
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
                    'payment_voucher',
                    $voucher_id,
                    '$voucher_no_db',
                    '$voucher_date_db',
                    0,
                    $line_amt,
                    0,
                    " . (float) $line_gold . ",
                    0,
                    " . (float) $line_silver . ",
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
                    'payment_voucher',
                    $voucher_id,
                    '$voucher_no_db',
                    '$voucher_date_db',
                    0,
                    $line_amt,
                    0,
                    " . (float) $line_gold . ",
                    0,
                    " . (float) $line_silver . ",
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

    // Keep summary balance table in sync with party debit
    $has_balance_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_balance'");
    if ($has_balance_table && mysqli_num_rows($has_balance_table) > 0) {
        $up = "INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_transaction_date, last_updated)
              VALUES (" . ($ledger_customer_id ? $ledger_customer_id : 0) . ", '$customer_name', $new_balance_amt, $new_balance_gold, $new_balance_silver, '$voucher_date', NOW())
              ON DUPLICATE KEY UPDATE balance_amount = $new_balance_amt, balance_gold = $new_balance_gold, balance_silver = $new_balance_silver, last_transaction_date = '$voucher_date', last_updated = NOW()";
        @mysqli_query($conn, $up);
    }

    if ((int) $voucher_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'payment_voucher', (int) $voucher_id, $voucher_no_db, $voucher_date_db);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Payment Voucher',
        'verb' => $payment_voucher_existed ? 'updated' : 'created',
        'number' => $voucher_no,
        'party' => $customer_name,
        'doc_date' => $voucher_date,
        'due_date' => $due_date,
        'ref_id' => (int) $voucher_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Payment voucher saved successfully',
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
