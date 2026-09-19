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

// Ensure sale quotation tables exist (run admin/sql/create_sale_quotation_tables.sql if missing)
$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotations'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) mysqli_free_result($tbl);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sale quotation tables not found. Please run admin/sql/create_sale_quotation_tables.sql in your database first.'
    ]);
    exit;
}
mysqli_free_result($tbl);

$has_sq_branch = auragold_ensure_table_branch_id_column($conn, 'tbl_sale_quotations');
$hdr_branch    = auragold_transaction_header_branch_id();
$eff_branch    = auragold_effective_branch_id();
$sq_dup_branch_sql = ($has_sq_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';

if (!function_exists('sale_quotation_delete_auto_receipt_vouchers_for_refs')) {
    function sale_quotation_delete_auto_receipt_vouchers_for_refs($conn, array $ref_numbers): void
    {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return;
        }
        mysqli_free_result($chk);
        $refs = array_values(array_unique(array_filter(array_map('trim', $ref_numbers))));
        if (empty($refs)) {
            return;
        }
        $in = [];
        foreach ($refs as $r) {
            $in[] = "'" . mysqli_real_escape_string($conn, $r) . "'";
        }
        $in_sql = implode(',', $in);
        $rows = getList("SELECT id FROM tbl_receipt_vouchers WHERE ref_no IN ($in_sql) AND voucher_type = 'Sale Quotation Payment'");
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            $vid = (int) ($row['id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }
            mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'receipt_voucher' AND transaction_id = $vid AND status = 1");
            mysqli_query($conn, "DELETE FROM tbl_receipt_voucher_items WHERE voucher_id = $vid");
            mysqli_query($conn, "DELETE FROM tbl_receipt_vouchers WHERE id = $vid");
        }
    }
}

if (!function_exists('sale_quotation_validate_metal_exchange_payments')) {
    /** @param array<int, array<string, mixed>> $payments */
    function sale_quotation_validate_metal_exchange_payments($conn, array $payments): void
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

if (!function_exists('sale_quotation_payment_is_auto_receipt_money')) {
    function sale_quotation_payment_is_auto_receipt_money(array $payment): bool
    {
        $amt = (float) ($payment['current_order_amount'] ?? $payment['amount'] ?? 0);
        if ($amt <= 0.00001) {
            return false;
        }
        $pt = strtolower(trim((string) ($payment['payment_type'] ?? 'cash')));
        if (strpos($pt, 'scrap') !== false) {
            return false;
        }
        return true;
    }
}

if (!function_exists('sale_quotation_create_auto_receipt_voucher_and_post_ledger')) {
    /**
     * One RV-x per quotation for money lines; Cash/Bank ledger debit as receipt_voucher (matches Sale Invoice flow).
     */
    function sale_quotation_create_auto_receipt_voucher_and_post_ledger(
        $conn,
        string $quotation_no,
        string $quotation_date,
        int $customer_id,
        string $customer_name,
        string $currency,
        string $sales_person,
        array $payments_money,
        int $user_id,
        ?string $ref_no,
        bool $ledger_has_branch_col = false,
        string $ledger_branch_sql_val = '',
        string $ledger_br_scope = ''
    ): void {
        $lbcol = $ledger_has_branch_col ? ', branch_id' : '';
        if (empty($payments_money)) {
            return;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return;
        }
        mysqli_free_result($chk);
        $chk2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_voucher_items'");
        if (!$chk2 || mysqli_num_rows($chk2) === 0) {
            if ($chk2) {
                mysqli_free_result($chk2);
            }
            return;
        }
        mysqli_free_result($chk2);

        $total_amount = 0.0;
        foreach ($payments_money as $p) {
            $total_amount += (float) ($p['current_order_amount'] ?? $p['amount'] ?? 0);
        }
        if ($total_amount <= 0.00001) {
            return;
        }

        $last_rv = getRecord('SELECT voucher_no FROM tbl_receipt_vouchers ORDER BY id DESC LIMIT 1');
        $rv_next = 1;
        if ($last_rv && !empty($last_rv['voucher_no']) && preg_match('/RV[- ]?(\d+)/i', (string) $last_rv['voucher_no'], $m)) {
            $rv_next = (int) $m[1] + 1;
        }
        $voucher_no = 'RV-' . $rv_next;
        $voucher_no_esc = mysqli_real_escape_string($conn, $voucher_no);
        $qdate_esc = mysqli_real_escape_string($conn, $quotation_date);
        $curr_esc = mysqli_real_escape_string($conn, $currency !== '' ? $currency : 'AED');
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);
        $sp_esc = $sales_person !== '' ? "'" . mysqli_real_escape_string($conn, $sales_person) . "'" : 'NULL';
        $ref_sql = $ref_no !== null && $ref_no !== '' ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL';
        $qno_esc = mysqli_real_escape_string($conn, $quotation_no);
        $comment_esc = mysqli_real_escape_string($conn, 'Receipt against Sale Quotation: ' . $quotation_no);
        $uid_sql = $user_id > 0 ? (string) $user_id : 'NULL';

        $ins_h = "
            INSERT INTO tbl_receipt_vouchers (
                voucher_no, customer_id, customer_name, ref_no, voucher_type, against,
                sales_person, currency, voucher_date, fixing_type,
                previous_balance, previous_gold, previous_silver,
                total_amount, total_gold, total_silver, comment, status, created_by, created_at
            ) VALUES (
                '$voucher_no_esc',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$cust_esc',
                '$qno_esc',
                'Sale Quotation Payment',
                'Sale Quotation',
                $sp_esc,
                '$curr_esc',
                '$qdate_esc',
                'Standard',
                0, 0, 0,
                $total_amount, 0, 0,
                '$comment_esc',
                'saved',
                $uid_sql,
                NOW()
            )
        ";
        if (!mysqli_query($conn, $ins_h)) {
            throw new Exception('Receipt voucher header failed: ' . mysqli_error($conn));
        }
        $voucher_id = (int) mysqli_insert_id($conn);
        if ($voucher_id <= 0) {
            throw new Exception('Receipt voucher id missing after insert');
        }

        foreach ($payments_money as $payment) {
            $pt = esc($payment['payment_type'] ?? 'cash');
            $dep = esc($payment['deposit_into'] ?? 'Cash');
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $txn_line = esc($payment['transaction_no'] ?? '');
            $chq = esc($payment['cheque_date'] ?? '');
            $amt = (float) ($payment['current_order_amount'] ?? $payment['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            $ins_i = "
                INSERT INTO tbl_receipt_voucher_items (
                    voucher_id, payment_type, diamond_category, transaction_no, deposit_into,
                    product_id, cheque_date, weight, metal_id, quantity, purity_carat, purity_wt,
                    amount, previous_balance_amount, status, created_at
                ) VALUES (
                    $voucher_id,
                    " . ($pt !== '' ? "'$pt'" : 'NULL') . ",
                    " . ($diamond_category !== '' ? "'$diamond_category'" : 'NULL') . ",
                    " . ($txn_line !== '' ? "'$txn_line'" : 'NULL') . ",
                    " . ($dep !== '' ? "'$dep'" : 'NULL') . ",
                    NULL,
                    " . ($chq !== '' ? "'$chq'" : 'NULL') . ",
                    0, NULL, 0, NULL, 0,
                    $amt, 0,
                    1, NOW()
                )
            ";
            if (!mysqli_query($conn, $ins_i)) {
                throw new Exception('Receipt voucher item failed: ' . mysqli_error($conn));
            }
        }

        $party_against_parts = [];
        foreach ($payments_money as $p) {
            $line_amt = (float) ($p['current_order_amount'] ?? $p['amount'] ?? 0);
            if ($line_amt <= 0.00001) {
                continue;
            }
            $pt = strtolower(trim((string) ($p['payment_type'] ?? 'cash')));
            $dep_raw = trim((string) ($p['deposit_into'] ?? ''));
            if ($dep_raw === '' && $pt === 'cash') {
                $dep_raw = 'Cash';
            }
            if ($dep_raw !== '') {
                $party_against_parts[] = $dep_raw . '(' . number_format($line_amt, 2) . 'Dr)';
            }
        }
        $party_against_display = implode(', ', $party_against_parts);

        $has_gold_pure = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'balance_gold_pure'");
        $use_gold_pure = ($has_gold_pure && mysqli_num_rows($has_gold_pure) > 0);
        if ($has_gold_pure) {
            mysqli_free_result($has_gold_pure);
        }

        $ledger_customer_id = $customer_id > 0 ? $customer_id : 0;
        $last_balance = null;
        if ($ledger_customer_id > 0) {
            $cols = $use_gold_pure ? 'balance_amount, balance_gold, balance_silver, balance_gold_pure' : 'balance_amount, balance_gold, balance_silver';
            $last_balance = getRecord("
                SELECT $cols FROM tbl_customer_ledger
                WHERE customer_id = $ledger_customer_id AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
        }
        if (!$last_balance && $customer_name !== '') {
            $cols = $use_gold_pure ? 'balance_amount, balance_gold, balance_silver, balance_gold_pure' : 'balance_amount, balance_gold, balance_silver';
            $last_balance = getRecord("
                SELECT $cols FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
        }
        $prev_amt = (float) ($last_balance['balance_amount'] ?? 0);
        $prev_gold = (float) ($last_balance['balance_gold'] ?? 0);
        $prev_silver = (float) ($last_balance['balance_silver'] ?? 0);
        $prev_gold_pure = $use_gold_pure ? (float) ($last_balance['balance_gold_pure'] ?? 0) : 0.0;

        $new_balance_amt_final = $prev_amt - $total_amount;
        $new_balance_gold = $prev_gold;
        $new_balance_silver = $prev_silver;
        $new_balance_gold_pure = $prev_gold_pure;

        $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $ledger_has_against = ($has_against && mysqli_num_rows($has_against) > 0);
        if ($has_against) {
            mysqli_free_result($has_against);
        }
        $against_cols = $ledger_has_against ? ', against_ledger, against_invoice_no' : '';
        $against_vals = '';
        if ($ledger_has_against) {
            $against_vals = $party_against_display !== ''
                ? ", '" . mysqli_real_escape_string($conn, $party_against_display) . "', '$qno_esc'"
                : ", NULL, '$qno_esc'";
        }

        $rv_desc_base = 'Receipt Voucher: ' . $voucher_no . ' (Sale Quotation ' . $quotation_no . ')';
        $desc_esc = mysqli_real_escape_string($conn, $rv_desc_base);

        if ($use_gold_pure) {
            $ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_gold_pure, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    $ledger_customer_id$ledger_branch_sql_val,
                    '$cust_esc',
                    'receipt_voucher',
                    $voucher_id,
                    '$voucher_no_esc',
                    '$qdate_esc',
                    0,
                    $total_amount,
                    0, 0, 0, 0, 0, 0,
                    $new_balance_amt_final, $new_balance_gold, $new_balance_gold_pure, $new_balance_silver,
                    '$desc_esc',
                    $ref_sql,
                    1,
                    $uid_sql,
                    NOW()
                    $against_vals
                )
            ";
        } else {
            $ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    $ledger_customer_id$ledger_branch_sql_val,
                    '$cust_esc',
                    'receipt_voucher',
                    $voucher_id,
                    '$voucher_no_esc',
                    '$qdate_esc',
                    0,
                    $total_amount,
                    0, 0, 0, 0,
                    $new_balance_amt_final, $new_balance_gold, $new_balance_silver,
                    '$desc_esc',
                    $ref_sql,
                    1,
                    $uid_sql,
                    NOW()
                    $against_vals
                )
            ";
        }
        if (!mysqli_query($conn, $ledger_sql)) {
            throw new Exception('Receipt voucher party ledger failed: ' . mysqli_error($conn));
        }

        foreach ($payments_money as $item) {
            $pt_raw = strtolower(trim((string) ($item['payment_type'] ?? 'cash')));
            $line_amt = (float) ($item['current_order_amount'] ?? $item['amount'] ?? 0);
            $dep_raw = trim((string) ($item['deposit_into'] ?? ''));
            if ($dep_raw === '' && $pt_raw === 'cash') {
                $dep_raw = 'Cash';
            }
            if ($line_amt <= 0.00001 || $dep_raw === '') {
                continue;
            }
            $dep_esc = esc($dep_raw);
            $cash_balance_record = getRecord("
                SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$dep_esc' AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
            $cash_prev_balance = (float) ($cash_balance_record['balance_amount'] ?? 0);
            $cash_new_balance = $cash_prev_balance + $line_amt;
            $cash_desc_esc = mysqli_real_escape_string($conn, 'Receipt from ' . $customer_name . ' (Receipt Voucher ' . $voucher_no . ')');
            $ca_line_esc = mysqli_real_escape_string($conn, function_exists('accountledger_against_party_payment_label')
                ? accountledger_against_party_payment_label($customer_name, $pt_raw, $line_amt)
                : ($customer_name . '(' . number_format($line_amt, 2) . 'Dr)'));

            if ($ledger_has_against) {
                $cash_ledger_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at,
                        against_ledger, against_invoice_no
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'receipt_voucher',
                        $voucher_id,
                        '$voucher_no_esc',
                        '$qdate_esc',
                        $line_amt,
                        0,
                        $cash_new_balance,
                        0,
                        0,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW(),
                        '$ca_line_esc',
                        '$qno_esc'
                    )
                ";
            } else {
                $cash_ledger_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'receipt_voucher',
                        $voucher_id,
                        '$voucher_no_esc',
                        '$qdate_esc',
                        $line_amt,
                        0,
                        $cash_new_balance,
                        0,
                        0,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW()
                    )
                ";
            }
            if (!mysqli_query($conn, $cash_ledger_sql)) {
                throw new Exception('Receipt voucher cash/bank ledger failed: ' . mysqli_error($conn));
            }
        }
    }
}

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    $metal_exchange_barcodes_out = [];

    // Log the request for debugging
    error_log("Sale Quotation Save Request - User ID: " . $user_id . ", POST data keys: " . implode(", ", array_keys($_POST)));
    
    // Get quotation data
    $quotation_no = esc($_POST['order_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $against_type = esc($_POST['against_type'] ?? '');
    $against_id = isset($_POST['against_id']) ? (int)$_POST['against_id'] : 0;
    $currency = esc($_POST['currency'] ?? 'AED');
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_sale_quotations') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $quotation_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $is_hedging = (strtolower($fixing_type) === 'hedging');
    $group_name = esc($_POST['group_name'] ?? '');
    $comment = esc($_POST['comment'] ?? '');
    $payment_comments_raw = isset($_POST['payment_comments']) ? $_POST['payment_comments'] : '[]';
    if (is_string($payment_comments_raw)) {
        $payment_comments_decoded = @json_decode($payment_comments_raw, true);
        $payment_comments = (is_array($payment_comments_decoded)) ? json_encode($payment_comments_decoded) : '[]';
    } else {
        $payment_comments = '[]';
    }
    $payment_comments_esc = mysqli_real_escape_string($conn, $payment_comments);
    $validity_days = isset($_POST['validity_days']) ? (int)$_POST['validity_days'] : 30;
    
    // Ensure payment_comments column exists in tbl_sale_quotations
    $has_payment_comments = false;
    $pc_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_quotations LIKE 'payment_comments'");
    if ($pc_col && mysqli_num_rows($pc_col) > 0) {
        $has_payment_comments = true;
        mysqli_free_result($pc_col);
    } else {
        if ($pc_col) mysqli_free_result($pc_col);
        @mysqli_query($conn, "ALTER TABLE tbl_sale_quotations ADD COLUMN payment_comments TEXT NULL DEFAULT NULL AFTER comment");
        $has_payment_comments = true;
    }
    
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
    $adjusted_balance_used = (float)($_POST['adjusted_balance_used'] ?? 0);
    // UI may post 0 when #summaryGrandTotal includes a currency symbol (parseFloat('₹ 1234.56') === NaN)
    if ($grand_total <= 0.00001) {
        $fallback_total = $net_total > 0.00001 ? $net_total : $subtotal;
        if ($fallback_total > 0.00001) {
            $grand_total = $fallback_total - $discount_amt + $round_off;
        }
    }
    
    // Calculate expiry date
    $expiry_date = null;
    if ($quotation_date && $validity_days > 0) {
        $expiry_date = date('Y-m-d', strtotime($quotation_date . ' + ' . $validity_days . ' days'));
    }
    
    // Check if update or insert
    $quotation_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $is_update = ($quotation_id > 0);
    $sq_row_branch_id = 0;
    if ($is_update && $has_sq_branch) {
        $own_br = getRecord("SELECT branch_id FROM tbl_sale_quotations WHERE id = $quotation_id LIMIT 1");
        $sq_row_branch_id = (int) ($own_br['branch_id'] ?? 0);
        auragold_branch_require_document_access($conn, 'tbl_sale_quotations', $quotation_id);
    }

    // Validation
    if (empty($customer_name)) {
        throw new Exception("Customer name is required");
    }
    
    // Validate user session
    if ($user_id <= 0) {
        throw new Exception("User session expired. Please login again.");
    }
    
    if (empty($quotation_no)) {
        // Bill series: voucher "Sales Quotation" in tbl_bill_series + tbl_voucher_types; else legacy SQ-1, SQ-2
        $quotation_no = function_exists('getNextSalesQuotationNo') ? esc(getNextSalesQuotationNo($conn)) : 'SQ-1';
    }

    // New quotation: bump until quotation_no is unique among active rows
    if (!$is_update) {
        $cfg = function_exists('getSalesQuotationBillSeriesConfig') ? getSalesQuotationBillSeriesConfig($conn) : ['prefix' => 'SQ-', 'suffix' => '', 'start_count' => 1];
        $sq_active = auragold_doc_series_active_sql($conn, 'tbl_sale_quotations');
        $existing_quotation = getRecord("SELECT id FROM tbl_sale_quotations WHERE quotation_no = '$quotation_no'$sq_dup_branch_sql" . $sq_active);
        $guard = 0;
        while ($existing_quotation && $guard < 5000) {
            $quotation_no = esc(function_exists('bumpSalesQuotationNo') ? bumpSalesQuotationNo($conn, $quotation_no, $cfg) : ('SQ-' . ($guard + 2)));
            $existing_quotation = getRecord("SELECT id FROM tbl_sale_quotations WHERE quotation_no = '$quotation_no'$sq_dup_branch_sql" . $sq_active);
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_sale_quotations', 'quotation_no']], $quotation_no);
        }
    }
    
    if ($is_update) {
        // Get current quotation number to check if it's changing
        $current_quotation = getRecord("SELECT quotation_no FROM tbl_sale_quotations WHERE id = $quotation_id");
        $current_quotation_no = $current_quotation ? $current_quotation['quotation_no'] : '';
        
        // Check if quotation_no is being changed and if it conflicts with another quotation
        if ($quotation_no !== $current_quotation_no) {
            $existing_quotation = getRecord("SELECT id FROM tbl_sale_quotations WHERE quotation_no = '$quotation_no' AND id != $quotation_id$sq_dup_branch_sql" . auragold_doc_series_active_sql($conn, 'tbl_sale_quotations'));
            if ($existing_quotation) {
                throw new Exception("Quotation number '$quotation_no' already exists. Please use a different quotation number.");
            }
        }
        
        // Update existing quotation
        $quotation_no_update = ($quotation_no !== $current_quotation_no) ? "quotation_no = '$quotation_no'," : "";
        
        // Build UPDATE query
        $update_fields = [];
        if ($quotation_no_update) {
            $update_fields[] = $quotation_no_update;
        }
        $update_fields[] = "customer_id = " . ($customer_id > 0 ? $customer_id : 0);
        $update_fields[] = "customer_name = '$customer_name'";
        $update_fields[] = "against_of = " . ($against_of ? "'$against_of'" : "NULL");
        $update_fields[] = "against_type = " . ($against_type ? "'$against_type'" : "NULL");
        $update_fields[] = "against_id = " . ($against_id > 0 ? $against_id : "NULL");
        $update_fields[] = "currency = '$currency'";
        if (!empty($__fx['has']) && !empty($__fx['name'])) {
            $update_fields[] = $__fx['name'] . " = " . (float)$exchange_rate;
        }
        $update_fields[] = "ref_no = " . ($ref_no ? "'$ref_no'" : "NULL");
        $update_fields[] = "sales_person = " . ($sales_person ? "'$sales_person'" : "NULL");
        $update_fields[] = "quotation_date = '$quotation_date'";
        $update_fields[] = "due_date = " . ($due_date ? "'$due_date'" : "NULL");
        $update_fields[] = "layaways_id = " . ($layaways ? (int)$layaways : "NULL");
        $update_fields[] = "fixing_type = '$fixing_type'";
        $update_fields[] = "previous_balance = $previous_balance";
        $update_fields[] = "previous_gold = $previous_gold";
        $update_fields[] = "previous_silver = $previous_silver";
        $update_fields[] = "subtotal = $subtotal";
        $update_fields[] = "additional_amt = $additional_amt";
        $update_fields[] = "net_total = $net_total";
        $update_fields[] = "reward_points = $reward_points";
        $update_fields[] = "coupon_code = " . ($coupon_code ? "'$coupon_code'" : "NULL");
        $update_fields[] = "coupon_discount = $coupon_discount";
        $update_fields[] = "discount_amt = $discount_amt";
        $update_fields[] = "redeem_points = $redeem_points";
        $update_fields[] = "grand_total = $grand_total";
        $update_fields[] = "advance_payment = $advance_payment";
        $update_fields[] = "metal_amt = $metal_amt";
        $update_fields[] = "round_off = $round_off";
        $update_fields[] = "paid_amt = $paid_amt";
        $update_fields[] = "balance_amt = $balance_amt";
        $update_fields[] = "adjusted_balance_used = $adjusted_balance_used";
        $update_fields[] = "group_name = " . ($group_name ? "'$group_name'" : "NULL");
        $update_fields[] = "comment = " . ($comment ? "'$comment'" : "NULL");
        if ($has_payment_comments) {
            $update_fields[] = "payment_comments = '" . $payment_comments_esc . "'";
        }
        $update_fields[] = "validity_days = $validity_days";
        $update_fields[] = "expiry_date = " . ($expiry_date ? "'$expiry_date'" : "NULL");
        if ($has_sq_branch && $eff_branch > 0 && $sq_row_branch_id === 0) {
            $update_fields[] = 'branch_id = ' . (int) $eff_branch;
        }
        $update_fields[] = "updated_at = NOW()";
        
        $sql = "UPDATE tbl_sale_quotations SET " . implode(", ", $update_fields) . " WHERE id = $quotation_id";
        
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            error_log("Sale Quotation Update SQL Error: " . $error);
            error_log("Sale Quotation Update SQL: " . $sql);
            if (strpos($error, 'Duplicate entry') !== false) {
                throw new Exception("Quotation number '$quotation_no' already exists. Please use a different quotation number.");
            }
            throw new Exception("Quotation update failed: " . $error);
        }
        
        // Delete existing items and payments
        mysqli_query($conn, "DELETE FROM tbl_sale_quotation_items WHERE quotation_id = $quotation_id");
        mysqli_query($conn, "DELETE FROM tbl_sale_quotation_payments WHERE quotation_id = $quotation_id");
        
        if (!empty($current_quotation_no)) {
            sale_quotation_delete_auto_receipt_vouchers_for_refs($conn, [$current_quotation_no]);
        }
        
        // Delete existing ledger entries for this quotation (so we re-create them below)
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger 
            WHERE transaction_id = $quotation_id AND status = 1 
            AND transaction_type IN ('sale_quotation', 'sale_quotation_revenue', 'quotation_payment')
        ");
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger 
            WHERE customer_name = 'Hedging Account' AND transaction_id = $quotation_id AND transaction_type = 'sale_quotation' AND status = 1
        ");
        
        // Note: Quotations don't affect stock, so we don't delete stock entries
    } else {
        // Insert new quotation
        $insert_fields = [
            "quotation_no", "customer_id", "customer_name", "against_of", "against_type", "against_id", "currency", "ref_no", "sales_person",
            "quotation_date", "due_date", "layaways_id", "fixing_type",
            "previous_balance", "previous_gold", "previous_silver",
            "subtotal", "additional_amt", "net_total", "reward_points",
            "coupon_code", "coupon_discount", "discount_amt", "redeem_points",
            "grand_total", "advance_payment", "metal_amt", "round_off",
            "paid_amt", "balance_amt", "adjusted_balance_used",
            "group_name", "comment", "status", "validity_days", "expiry_date",
            "created_by"
        ];
        if (!empty($__fx['has']) && !empty($__fx['name']) && !in_array($__fx['name'], $insert_fields, true)) {
            $curPos = array_search('currency', $insert_fields, true);
            if ($curPos === false) { $insert_fields[] = $__fx['name']; }
            else { array_splice($insert_fields, (int)$curPos + 1, 0, [$__fx['name']]); }
        }
        if ($has_sq_branch) {
            $insert_fields[] = 'branch_id';
        }
        $insert_fields[] = "created_at";
        $insert_values = [
            "'$quotation_no'",
            ($customer_id > 0 ? $customer_id : 0),
            "'$customer_name'",
            ($against_of ? "'$against_of'" : "NULL"),
            ($against_type ? "'$against_type'" : "NULL"),
            ($against_id > 0 ? $against_id : "NULL"),
            "'$currency'",
            ($ref_no ? "'$ref_no'" : "NULL"),
            ($sales_person ? "'$sales_person'" : "NULL"),
            "'$quotation_date'",
            ($due_date ? "'$due_date'" : "NULL"),
            ($layaways ? (int)$layaways : "NULL"),
            "'$fixing_type'",
            $previous_balance,
            $previous_gold,
            $previous_silver,
            $subtotal,
            $additional_amt,
            $net_total,
            $reward_points,
            ($coupon_code ? "'$coupon_code'" : "NULL"),
            $coupon_discount,
            $discount_amt,
            $redeem_points,
            $grand_total,
            $advance_payment,
            $metal_amt,
            $round_off,
            $paid_amt,
            $balance_amt,
            $adjusted_balance_used,
            ($group_name ? "'$group_name'" : "NULL"),
            ($comment ? "'$comment'" : "NULL"),
            "'draft'",
            $validity_days,
            ($expiry_date ? "'$expiry_date'" : "NULL"),
            $user_id
        ];
        if ($has_sq_branch) {
            $insert_values[] = ($hdr_branch > 0 ? (string) (int) $hdr_branch : 'NULL');
        }
        $insert_values[] = "NOW()";
        if ($has_payment_comments) {
            $insert_fields[] = "payment_comments";
            $insert_values[] = "'" . $payment_comments_esc . "'";
        }
        
        if (!empty($__fx['has']) && !empty($__fx['name'])) {
            $ratePos = array_search($__fx['name'], $insert_fields, true);
            if ($ratePos !== false && count($insert_values) < count($insert_fields)) {
                array_splice($insert_values, (int)$ratePos, 0, [(float)$exchange_rate]);
            }
        }
        $sql = "INSERT INTO tbl_sale_quotations (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $insert_values) . ")";
        
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            error_log("Sale Quotation Insert SQL Error: " . $error);
            error_log("Sale Quotation Insert SQL: " . $sql);
            throw new Exception("Quotation insert failed: " . $error);
        }
        
        $quotation_id = mysqli_insert_id($conn);
    }

    auragold_ensure_customer_ledger_branch_column($conn);
    $sq_ledger_branch_id = 0;
    if ($quotation_id > 0 && $has_sq_branch) {
        $sq_br_row = getRecord('SELECT branch_id FROM tbl_sale_quotations WHERE id = ' . (int) $quotation_id . ' LIMIT 1');
        $sq_ledger_branch_id = (int) ($sq_br_row['branch_id'] ?? 0);
    }
    if ($sq_ledger_branch_id <= 0) {
        $sq_ledger_branch_id = $sq_row_branch_id > 0 ? $sq_row_branch_id : (($hdr_branch > 0) ? $hdr_branch : (($eff_branch > 0) ? $eff_branch : 0));
    }
    $ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = '';
    if ($ledger_has_branch_col) {
        $ledger_branch_sql_val = ', ' . ($sq_ledger_branch_id > 0 ? (string) (int) $sq_ledger_branch_id : 'NULL');
    }
    $ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql') ? auragold_customer_ledger_branch_scope_sql($conn, $sq_ledger_branch_id) : '';
    
    // Check optional item columns (diamond-related and metal); add if missing
    $qi_has_diamond_category = false;
    $qi_has_metal_rate = false;
    $qi_has_calculation_type = false;
    $qi_has_metal_value = false;
    $qi_has_diamond_amount = false;
    $qi_has_stone_amount = false;
    $qi_has_stone_weight = false;
    $qi_has_metal_qty = false;
    $qi_has_metal_weight = false;
    foreach (['diamond_category', 'metal_rate', 'calculation_type', 'metal_value', 'diamond_amount', 'stone_amount', 'stone_weight', 'metal_qty', 'metal_weight'] as $col) {
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_quotation_items LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
        if ($r && mysqli_num_rows($r) > 0) {
            if ($col === 'diamond_category') $qi_has_diamond_category = true;
            if ($col === 'metal_rate') $qi_has_metal_rate = true;
            if ($col === 'calculation_type') $qi_has_calculation_type = true;
            if ($col === 'metal_value') $qi_has_metal_value = true;
            if ($col === 'diamond_amount') $qi_has_diamond_amount = true;
            if ($col === 'stone_amount') $qi_has_stone_amount = true;
            if ($col === 'stone_weight') $qi_has_stone_weight = true;
            if ($col === 'metal_qty') $qi_has_metal_qty = true;
            if ($col === 'metal_weight') $qi_has_metal_weight = true;
            mysqli_free_result($r);
        } else {
            if ($r) mysqli_free_result($r);
            if ($col === 'diamond_category') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN diamond_category VARCHAR(100) NULL DEFAULT NULL AFTER design_no"); $qi_has_diamond_category = true; }
            if ($col === 'metal_rate') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN metal_rate DECIMAL(12,4) NULL DEFAULT NULL AFTER rate"); $qi_has_metal_rate = true; }
            if ($col === 'calculation_type') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN calculation_type VARCHAR(100) NULL DEFAULT NULL"); $qi_has_calculation_type = true; }
            if ($col === 'metal_value') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN metal_value DECIMAL(15,2) NULL DEFAULT NULL"); $qi_has_metal_value = true; }
            if ($col === 'diamond_amount') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN diamond_amount DECIMAL(15,2) NULL DEFAULT NULL"); $qi_has_diamond_amount = true; }
            if ($col === 'stone_amount') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN stone_amount DECIMAL(15,2) NULL DEFAULT NULL"); $qi_has_stone_amount = true; }
            if ($col === 'stone_weight') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN stone_weight DECIMAL(10,3) NULL DEFAULT NULL"); $qi_has_stone_weight = true; }
            if ($col === 'metal_qty') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity"); $qi_has_metal_qty = true; }
            if ($col === 'metal_weight') { @mysqli_query($conn, "ALTER TABLE tbl_sale_quotation_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty"); $qi_has_metal_weight = true; }
        }
    }

    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
    
    // Save quotation items
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
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $barcode = '';
            $product_name = esc($item['product_name'] ?? '');
            $carat = esc($item['carat'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $gross_weight = (float)($item['gross_weight'] ?? $item['gross_wt'] ?? 0);
            $less_weight = (float)($item['less_weight'] ?? $item['less_wt'] ?? 0);
            $purity = (float)($item['purity'] ?? 0);
            $purity_weight = (float)($item['purity_weight'] ?? $item['pure_wt'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? $item['final_wt'] ?? 0);
            $net_weight = (float)($item['net_weight'] ?? $item['net_wt'] ?? 0);
            $pure_weight = (float)($item['pure_weight'] ?? $item['pure_wt'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $making = (float)($item['making'] ?? 0);
            $making_amount = (float)($item['making_amount'] ?? 0);
            $design_no = esc($item['design_no'] ?? '');
            $tax = (float)($item['tax'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
            $location_id = isset($item['location_id']) ? (int)$item['location_id'] : NULL;
            $diamond_category = esc($item['category'] ?? $item['diamond_category'] ?? '');
            $metal_rate = (float)($item['metal_rate'] ?? $item['rate'] ?? 0);
            $calculation_type = esc($item['calculation_type'] ?? $item['calculation'] ?? '');
            $metal_value = (float)($item['metal_value'] ?? 0);
            $diamond_amount = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
            $stone_amount = (float)($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
            $stone_weight = (float)($item['stone_weight'] ?? 0);
            $metal_qty = (float)($item['metal_qty'] ?? 1);
            $metal_weight = (float)($item['metal_weight'] ?? 0);

            if ($product_id > 0) {
                $barcode = esc(auragold_resolve_unique_invoice_item_barcode($conn, $item, $invoice_used_barcodes));
                $dc_col = $qi_has_diamond_category ? ", diamond_category" : "";
                $dc_val = $qi_has_diamond_category ? ", " . ($diamond_category ? "'" . mysqli_real_escape_string($conn, $diamond_category) . "'" : "NULL") : "";
                $mr_col = $qi_has_metal_rate ? ", metal_rate" : "";
                $mr_val = $qi_has_metal_rate ? ", $metal_rate" : "";
                $ct_col = $qi_has_calculation_type ? ", calculation_type" : "";
                $ct_val = $qi_has_calculation_type ? ", " . ($calculation_type ? "'" . mysqli_real_escape_string($conn, $calculation_type) . "'" : "NULL") : "";
                $mv_col = $qi_has_metal_value ? ", metal_value" : "";
                $mv_val = $qi_has_metal_value ? ", $metal_value" : "";
                $da_col = $qi_has_diamond_amount ? ", diamond_amount" : "";
                $da_val = $qi_has_diamond_amount ? ", $diamond_amount" : "";
                $sa_col = $qi_has_stone_amount ? ", stone_amount" : "";
                $sa_val = $qi_has_stone_amount ? ", $stone_amount" : "";
                $sw_col = $qi_has_stone_weight ? ", stone_weight" : "";
                $sw_val = $qi_has_stone_weight ? ", $stone_weight" : "";
                $mq_col = $qi_has_metal_qty ? ", metal_qty" : "";
                $mq_val = $qi_has_metal_qty ? ", $metal_qty" : "";
                $mw_col = $qi_has_metal_weight ? ", metal_weight" : "";
                $mw_val = $qi_has_metal_weight ? ", $metal_weight" : "";
                $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_sale_quotation_items', $item);
                $ef_col = $ef_parts['columns'];
                $ef_val = $ef_parts['values'];
                // Insert quotation item (all fields including diamond-related)
                $item_sql = "
                    INSERT INTO tbl_sale_quotation_items (
                        quotation_id, product_id, product_characteristic_id, barcode, product_name,
                        carat, quantity, gross_weight, less_weight, purity, purity_weight,
                        final_weight, net_weight, pure_weight, rate,
                        making_amount, amount, tax_amount, net_amount, net_amt_with_tax,
                        design_no, location_id, status, created_at $dc_col $mr_col $ct_col $mv_col $da_col $sa_col $sw_col $mq_col $mw_col$ef_col
                    ) VALUES (
                        $quotation_id, $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                        " . ($barcode ? "'$barcode'" : "NULL") . ",
                        '$product_name',
                        " . ($carat ? "'$carat'" : "NULL") . ",
                        $quantity, $gross_weight, $less_weight, $purity, $purity_weight,
                        $final_weight, $net_weight, $pure_weight, $rate,
                        $making_amount, $amount, $tax, $net_amount, $net_amt_with_tax,
                        " . ($design_no ? "'$design_no'" : "NULL") . ",
                        " . ($location_id ? $location_id : "NULL") . ",
                        1, NOW() $dc_val $mr_val $ct_val $mv_val $da_val $sa_val $sw_val $mq_val $mw_val$ef_val
                    )
                ";
                
                if (!mysqli_query($conn, $item_sql)) {
                    throw new Exception("Item insert failed: " . mysqli_error($conn));
                }

                $sq_item_id = (int) mysqli_insert_id($conn);
                $metal_id_sq = 0;
                if ($characteristic_id) {
                    $ch_sq = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = " . (int) $characteristic_id . " AND status = 1");
                    if ($ch_sq) {
                        $metal_id_sq = (int) ($ch_sq['metal_id'] ?? 0);
                    }
                }
                if ($metal_id_sq <= 0) {
                    $dm_sq = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 ORDER BY id DESC LIMIT 1");
                    if ($dm_sq) {
                        $metal_id_sq = (int) ($dm_sq['metal_id'] ?? 0);
                    }
                }
                $sj_no_sq = 'SQ' . (int) $quotation_id . 'I' . $sq_item_id;
                if (strlen($sj_no_sq) > 48) {
                    $sj_no_sq = 'Q' . (int) $quotation_id . 'x' . $sq_item_id;
                }
                if (trim((string) $barcode) !== '') {
                auragold_stock_history_audit_insert_row($conn, [
                    'sj_invoice_no' => $sj_no_sq,
                    'item_id' => 0,
                    'invoice_id' => 0,
                    'invoice_no' => $quotation_no,
                    'sj_date' => $quotation_date,
                    'barcode' => $barcode,
                    'product_id' => $product_id,
                    'product_characteristic_id' => $characteristic_id ? (int) $characteristic_id : 0,
                    'product_name' => $product_name,
                    'metal_id' => $metal_id_sq,
                    'metal_type' => auragold_stock_history_metal_type($conn, $metal_id_sq),
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
                    'voucher_type' => 'Sale Quotation',
                    'design_no' => $design_no,
                    'category' => $diamond_category,
                    'comment' => 'auragold_doc|src=sq|qid=' . (int) $quotation_id . '|sqi=' . $sq_item_id . '|',
                ]);
                }
            }
        }
    }
    
    // ================== SALES ACCOUNT & MAKING SALES ACCOUNT LEDGER (sale quotation) ==================
    if ($is_update) {
        mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger 
            WHERE transaction_id = $quotation_id AND status = 1 
            AND transaction_type = 'sale_quotation_revenue'
        ");
    }
    $total_sales_amt = 0;
    $total_making_amt = 0;
    $total_tax_amt = 0;
    if (!empty($items) && is_array($items)) {
        foreach ($items as $item) {
            $metal_val = (float)($item['metal_value'] ?? 0);
            $diamond_amt = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
            $stone_amt = (float)($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
            $making_amt = (float)($item['making_amount'] ?? $item['making'] ?? 0);
            if ($is_hedging) $making_amt = 0;
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
    }
    if ($total_sales_amt <= 0 && isset($metal_amt)) {
        $total_sales_amt = (float)$metal_amt;
    }
    if ($total_making_amt <= 0 && $grand_total > 0 && $total_sales_amt > 0) {
        $total_making_amt = max(0, $net_total - $total_sales_amt);
    }
    $has_against_res = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
    $has_against = ($has_against_res && mysqli_num_rows($has_against_res) > 0);
    if ($has_against_res) mysqli_free_result($has_against_res);
    $against_cols = $has_against ? ", against_ledger, against_invoice_no" : "";
    $get_ledger_balance = function($ledger_name) use ($conn, $ledger_br_scope) {
        $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = '" . mysqli_real_escape_string($conn, $ledger_name) . "' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
        return (float)($r['balance_amount'] ?? 0);
    };
    $ref_esc = $ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : "NULL";
    if ($total_sales_amt > 0) {
        $prev_sales = $get_ledger_balance('Sales Account');
        $new_sales_bal = $prev_sales - $total_sales_amt;
        $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$quotation_no'" : "";
        $sales_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Sales Account', 'sale_quotation_revenue', $quotation_id, '$quotation_no', '$quotation_date', 0.00, $total_sales_amt, $new_sales_bal, 'Sale Quotation: $quotation_no', $ref_esc, 1, $user_id, NOW() $against_vals)";
        if (!mysqli_query($conn, $sales_sql)) {
            throw new Exception("Sales Account ledger entry failed: " . mysqli_error($conn));
        }
    }
    if ($total_making_amt > 0) {
        $prev_making = $get_ledger_balance('Making Sales Account');
        $new_making_bal = $prev_making - $total_making_amt;
        $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$quotation_no'" : "";
        $making_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Making Sales Account', 'sale_quotation_revenue', $quotation_id, '$quotation_no', '$quotation_date', 0.00, $total_making_amt, $new_making_bal, 'Making charges - Quotation: $quotation_no', $ref_esc, 1, $user_id, NOW() $against_vals)";
        if (!mysqli_query($conn, $making_sql)) {
            throw new Exception("Making Sales Account ledger entry failed: " . mysqli_error($conn));
        }
    }
    
    // ================== CUSTOMER LEDGER: Sale Quotation (debit amount; debit metal when Hedging) ==================
    $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
    $has_gold_pure_cols = ($gpc && mysqli_num_rows($gpc) > 0);
    if ($gpc) mysqli_free_result($gpc);
    if ($customer_id > 0 || !empty($customer_name)) {
        $prev_balance_select = "balance_amount, balance_gold, balance_silver";
        if ($has_gold_pure_cols) $prev_balance_select .= ", balance_gold_pure";
        $previous_balance_record = null;
        if ($customer_id > 0) {
            $previous_balance_record = getRecord("
                SELECT $prev_balance_select
                FROM tbl_customer_ledger 
                WHERE customer_id = $customer_id AND status = 1 
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_balance WHERE customer_id = $customer_id LIMIT 1");
            }
        }
        if (!$previous_balance_record && !empty($customer_name)) {
            $customer_name_esc = mysqli_real_escape_string($conn, $customer_name);
            $previous_balance_record = getRecord("
                SELECT $prev_balance_select
                FROM tbl_customer_ledger 
                WHERE customer_name = '$customer_name_esc' AND status = 1 
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_balance WHERE customer_name = '$customer_name_esc' LIMIT 1");
            }
        }
        $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? 0);
        $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? 0);
        $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? 0);
        $prev_balance_gold_pure = $has_gold_pure_cols ? (float)($previous_balance_record['balance_gold_pure'] ?? 0) : 0;
        
        $ledger_debit_amount = $grand_total;
        $total_gold_weight = 0;
        $total_silver_weight = 0;
        if ($is_hedging && !empty($items) && is_array($items)) {
            foreach ($items as $item) {
                $net_weight = (float)($item['net_weight'] ?? $item['net_wt'] ?? $item['final_weight'] ?? $item['final_wt'] ?? $item['gross_weight'] ?? $item['gross_wt'] ?? 0);
                $purity_weight = (float)($item['purity_weight'] ?? $item['pure_weight'] ?? $item['pure_wt'] ?? $item['purity_wt'] ?? 0);
                $purity = (float)($item['purity'] ?? 0);
                if ($purity_weight <= 0 && $net_weight > 0 && $purity > 0) {
                    if ($purity <= 1) {
                        $purity_weight = $net_weight * $purity;
                    } else if ($purity <= 100) {
                        $purity_weight = $net_weight * ($purity / 100);
                    } else {
                        $purity_weight = $net_weight * ($purity / 1000);
                    }
                }
                if ($purity_weight <= 0) $purity_weight = $net_weight;
                $purity_pct = 0;
                if ($purity > 0) {
                    if ($purity <= 1) $purity_pct = $purity * 100;
                    else if ($purity <= 100) $purity_pct = $purity;
                    else $purity_pct = $purity / 10;
                }
                $product_name = trim($item['product_name'] ?? '');
                $is_gold = ($purity_pct >= 75) || (stripos($product_name, 'gold') !== false);
                $is_silver = ($purity_pct >= 50 && $purity_pct < 75) || (stripos($product_name, 'silver') !== false);
                if ($is_gold) {
                    $total_gold_weight += ($purity_weight > 0 ? $purity_weight : $net_weight);
                } else if ($is_silver) {
                    $total_silver_weight += ($purity_weight > 0 ? $purity_weight : $net_weight);
                }
            }
        }
        $total_gold_pure = $total_gold_weight;
        $new_balance_amount = $prev_balance_amount + $ledger_debit_amount;
        $new_balance_gold = $prev_balance_gold + $total_gold_weight;
        $new_balance_silver = $prev_balance_silver + $total_silver_weight;
        $new_balance_gold_pure = $prev_balance_gold_pure + $total_gold_pure;
        
        // Customer quotation line: show revenue contra (Sales / Making / Tax) like sale invoice — not Cash; Against Inv = quotation no.
        $against_parts = [];
        if ($total_sales_amt > 0.00001) {
            $against_parts[] = 'Sales Account(' . number_format($total_sales_amt, 2) . 'Cr)';
        }
        if ($total_making_amt > 0.00001) {
            $against_parts[] = 'Making Sales Account(' . number_format($total_making_amt, 2) . 'Cr)';
        }
        if ($total_tax_amt > 0.00001) {
            $against_parts[] = 'Tax Ledger(' . number_format($total_tax_amt, 2) . 'Cr)';
        }
        $against_ledger = implode(', ', $against_parts);
        if ($against_ledger === '') {
            $against_ledger = 'Sales Account(' . number_format($grand_total, 2) . 'Cr)';
        }
        $against_invoice_no = $quotation_no;
        $against_vals = $has_against ? ", " . ($against_ledger ? "'" . mysqli_real_escape_string($conn, $against_ledger) . "'" : "NULL") . ", " . ($against_invoice_no ? "'" . mysqli_real_escape_string($conn, $against_invoice_no) . "'" : "NULL") : "";
        $ledger_gold_pure_cols = $has_gold_pure_cols ? "debit_gold_pure, credit_gold_pure," : "";
        $ledger_balance_gold_pure_col = $has_gold_pure_cols ? ", balance_gold_pure" : "";
        $ledger_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$new_balance_gold_pure : "";
        $metal_vals = (float)$total_gold_weight . ", 0.000";
        if ($has_gold_pure_cols) $metal_vals .= ", " . (float)$total_gold_pure . ", 0.000";
        $metal_vals .= ", " . (float)$total_silver_weight . ", 0.000";
        $customer_name_esc_ledger = mysqli_real_escape_string($conn, $customer_name);
        $ledger_sql = "
            INSERT INTO tbl_customer_ledger (
                customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                description, reference_no, status, created_by, created_at
                $against_cols
            ) VALUES (
                " . ($customer_id > 0 ? $customer_id : 0) . "$ledger_branch_sql_val,
                '$customer_name_esc_ledger',
                'sale_quotation',
                $quotation_id,
                '$quotation_no',
                '$quotation_date',
                $ledger_debit_amount,
                0.00,
                $metal_vals,
                $new_balance_amount,
                $new_balance_gold $ledger_balance_gold_pure_val,
                $new_balance_silver,
                'Sale Quotation: $quotation_no" . ($is_hedging ? " (Hedging)" : "") . "',
                $ref_esc,
                1,
                $user_id,
                NOW()
                $against_vals
            )
        ";
        if (!mysqli_query($conn, $ledger_sql)) {
            throw new Exception("Customer ledger entry (sale quotation) failed: " . mysqli_error($conn));
        }
        
        // Hedging: post metal deduction to Hedging Account so Account Ledger shows debit (sale quotation deducts metal)
        if ($is_hedging && ($total_gold_weight > 0 || $total_silver_weight > 0)) {
            $ha_last = getRecord("SELECT balance_amount, balance_gold, balance_silver " . ($has_gold_pure_cols ? ", balance_gold_pure" : "") . " FROM tbl_customer_ledger WHERE customer_name = 'Hedging Account' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            $ha_prev_amt = (float)($ha_last['balance_amount'] ?? 0);
            $ha_prev_gold = (float)($ha_last['balance_gold'] ?? 0);
            $ha_prev_silver = (float)($ha_last['balance_silver'] ?? 0);
            $ha_prev_gold_pure = $has_gold_pure_cols ? (float)($ha_last['balance_gold_pure'] ?? 0) : 0;
            $ha_new_gold = $ha_prev_gold - $total_gold_weight;
            $ha_new_silver = $ha_prev_silver - $total_silver_weight;
            $ha_new_gold_pure = $ha_prev_gold_pure - $total_gold_pure;
            $ha_metal_vals = (float)$total_gold_weight . ", 0.000";
            if ($has_gold_pure_cols) $ha_metal_vals .= ", " . (float)$total_gold_pure . ", 0.000";
            $ha_metal_vals .= ", " . (float)$total_silver_weight . ", 0.000";
            $ha_balance_gold_pure_col = $has_gold_pure_cols ? ", balance_gold_pure" : "";
            $ha_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$ha_new_gold_pure : "";
            $ha_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold $ha_balance_gold_pure_col, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    0$ledger_branch_sql_val,
                    'Hedging Account',
                    'sale_quotation',
                    $quotation_id,
                    '$quotation_no',
                    '$quotation_date',
                    0.00,
                    0.00,
                    $ha_metal_vals,
                    $ha_prev_amt,
                    $ha_new_gold $ha_balance_gold_pure_val,
                    $ha_new_silver,
                    'Sale Quotation: $quotation_no (Hedging)',
                    $ref_esc,
                    1,
                    $user_id,
                    NOW()
                    " . ($has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$quotation_no'" : "") . "
                )
            ";
            if (!mysqli_query($conn, $ha_sql)) {
                throw new Exception("Hedging Account ledger entry (sale quotation) failed: " . mysqli_error($conn));
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
        sale_quotation_validate_metal_exchange_payments($conn, $payments);
        $___sq_me_has_ref = auragold_metal_exchange_document_init($conn, $is_update, (int) $quotation_id, 'sale_quotation_metal_exchange');

        foreach ($payments as $pay_seq => $payment) {
            $payment_type = esc($payment['payment_type'] ?? '');
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $transaction_no = esc($payment['transaction_no'] ?? '');
            $cheque_date = isset($payment['cheque_date']) && $payment['cheque_date'] ? esc($payment['cheque_date']) : NULL;
            $purity_carat = esc($payment['purity_carat'] ?? '');
            $amount = (float)($payment['amount'] ?? 0);
            $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
            $current_order_amount = (float)($payment['current_order_amount'] ?? ($amount - $previous_balance_amount));
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $quantity = (float)($payment['quantity'] ?? 0);

            if (auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                $payment_sql = "
                    INSERT INTO tbl_sale_quotation_payments (
                        quotation_id, payment_type, deposit_into, transaction_no,
                        cheque_date, purity_carat, amount, previous_balance_amount, current_order_amount, diamond_category, quantity,
                        status, created_at
                    ) VALUES (
                        $quotation_id, '$payment_type',
                        " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                        " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                        " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                        " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                        $amount,
                        $previous_balance_amount,
                        $current_order_amount,
                        " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                        $quantity,
                        1, NOW()
                    )
                ";

                if (!mysqli_query($conn, $payment_sql)) {
                    throw new Exception("Payment insert failed: " . mysqli_error($conn));
                }

                if (!sale_quotation_payment_is_auto_receipt_money($payment) && $amount > 0.00001) {
                    // Determine the account name based on payment type (legacy non–receipt-voucher lines)
                    $account_name = '';
                    if (strtolower($payment_type) === 'cash') {
                        $account_name = 'Cash';
                    } elseif (!empty($deposit_into)) {
                        $account_name = $deposit_into;
                    } elseif (strtolower($payment_type) === 'bank') {
                        $account_name = 'Bank';
                    } else {
                        $account_name = $payment_type;
                    }

                    $last_balance_record = getRecord("
                    SELECT balance_amount, balance_gold, balance_silver 
                    FROM tbl_customer_ledger 
                    WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . "
                    AND customer_name = '$customer_name' 
                    AND status = 1 
                    $ledger_br_scope
                    ORDER BY transaction_date DESC, id DESC 
                    LIMIT 1
                ");
                    $last_balance_amount = (float)($last_balance_record['balance_amount'] ?? 0);
                    $last_balance_gold = (float)($last_balance_record['balance_gold'] ?? 0);
                    $last_balance_silver = (float)($last_balance_record['balance_silver'] ?? 0);

                    $new_balance_amount = $last_balance_amount - $current_order_amount;

                    $payment_ledger_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, against_ledger, against_invoice_no,
                        status, created_by, created_at
                    ) VALUES (
                        " . ($customer_id > 0 ? $customer_id : 0) . "$ledger_branch_sql_val,
                        '$customer_name',
                        'quotation_payment',
                        $quotation_id,
                        '$quotation_no',
                        '$quotation_date',
                        0.00,
                        $current_order_amount,
                        $new_balance_amount,
                        $last_balance_gold,
                        $last_balance_silver,
                        'Payment for Sale Quotation: $quotation_no',
                        '$account_name',
                        '$quotation_no',
                        1,
                        $user_id,
                        NOW()
                    )
                ";

                    if (!mysqli_query($conn, $payment_ledger_sql)) {
                        throw new Exception("Payment ledger entry failed: " . mysqli_error($conn));
                    }

                    if (!empty($account_name)) {
                        $cash_balance_record = getRecord("
                        SELECT balance_amount 
                        FROM tbl_customer_ledger 
                        WHERE customer_name = '$account_name' 
                        AND status = 1 
                        $ledger_br_scope
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                        $cash_prev_balance = (float)($cash_balance_record['balance_amount'] ?? 0);
                        $cash_new_balance = $cash_prev_balance + $current_order_amount;

                        $cash_ledger_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, description, against_ledger, against_invoice_no,
                            status, created_by, created_at
                        ) VALUES (
                            0$ledger_branch_sql_val,
                            '$account_name',
                            'quotation_payment',
                            $quotation_id,
                            '$quotation_no',
                            '$quotation_date',
                            $current_order_amount,
                            0.00,
                            $cash_new_balance,
                            'Payment from $customer_name for Sale Quotation: $quotation_no',
                            '$customer_name',
                            '$quotation_no',
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
            $pm = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'sale_quotation_metal_exchange',
                (int) $quotation_id,
                $quotation_no,
                substr(trim((string) $quotation_date), 0, 10),
                $pm,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___sq_me_has_ref,
                'Sale Quotation — Metal Exchange',
                'sq_me',
                'SQ-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }
    
    $sq_money_payments = [];
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $payment) {
            if (sale_quotation_payment_is_auto_receipt_money($payment)) {
                $sq_money_payments[] = $payment;
            }
        }
    }
    if (!empty($sq_money_payments)) {
        sale_quotation_create_auto_receipt_voucher_and_post_ledger(
            $conn,
            $quotation_no,
            $quotation_date,
            $customer_id,
            $customer_name,
            $currency,
            $sales_person,
            $sq_money_payments,
            $user_id,
            $ref_no !== '' ? $ref_no : null,
            $ledger_has_branch_col,
            $ledger_branch_sql_val,
            $ledger_br_scope
        );
    }
    
    if ((int) $quotation_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'sale_quotation', (int) $quotation_id, $quotation_no, $quotation_date);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Sale Quotation',
        'verb' => $is_update ? 'updated' : 'created',
        'number' => $quotation_no,
        'party' => $customer_name,
        'doc_date' => $quotation_date,
        'due_date' => $due_date,
        'ref_id' => (int) $quotation_id,
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Sale quotation saved successfully',
        'order_id' => $quotation_id,
        'quotation_id' => $quotation_id,
        'order_no' => $quotation_no,
        'quotation_no' => $quotation_no,
        'new_barcodes' => $metal_exchange_barcodes_out,
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Sale Quotation Save Error: " . $e->getMessage());
    error_log("Sale Quotation Save Trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
