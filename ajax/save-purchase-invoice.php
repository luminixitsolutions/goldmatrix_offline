<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/branch_profile_schema.php';
require_once __DIR__ . '/../includes/dashboard_currency_display.php';
require_once __DIR__ . '/../includes/invoice_item_unique_barcode.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';
require_once __DIR__ . '/../includes/auragold_product_metal_tab_match.php';
require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';
require_once __DIR__ . '/../includes/auragold_purchase_invoice_barcode_images.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

/**
 * Gross/pure metal weights for Metal Exchange payment line (party ledger).
 * Silver → debit_silver; gold / platinum / other → debit_gold + debit_gold_pure.
 */
if (!function_exists('purchase_invoice_metal_exchange_ledger_wts')) {
    function purchase_invoice_metal_exchange_ledger_wts($conn, array $payment) {
        $out = ['dg' => 0.0, 'cg' => 0.0, 'dgp' => 0.0, 'cgp' => 0.0, 'ds' => 0.0, 'cs' => 0.0];
        $dep = strtolower(trim((string) ($payment['deposit_into'] ?? '')));
        $pt = strtolower(trim((string) ($payment['payment_type'] ?? '')));
        $is_me = ($dep === 'metal exchange')
            || (strpos($pt, 'm. exch') !== false)
            || (strpos($pt, 'metal') !== false && strpos($pt, 'exch') !== false);
        if (!$is_me) {
            return $out;
        }
        $qty = (float) ($payment['quantity'] ?? 1);
        if ($qty < 1e-8) {
            $qty = 1.0;
        }
        $gross = (float) ($payment['metal_exchange_gross_wt'] ?? 0) * $qty;
        $pure = (float) ($payment['metal_exchange_purity_wt'] ?? 0) * $qty;
        $mid = (int) ($payment['metal_exchange_metal_id'] ?? 0);
        $nm = '';
        if ($mid > 0) {
            $mr = getRecord("SELECT LOWER(TRIM(COALESCE(display_name, system_name, ''))) AS n FROM tbl_metal WHERE id = $mid LIMIT 1");
            $nm = strtolower(trim((string) ($mr['n'] ?? '')));
        }
        if (strpos($nm, 'silver') !== false) {
            $out['ds'] = $gross;
        } else {
            $out['dg'] = $gross;
            $out['dgp'] = $pure;
        }
        return $out;
    }
}

if (!function_exists('purchase_invoice_payment_is_auto_pv_money')) {
    /**
     * Cash / Bank / UPI / Card / Online / Metal Exchange → one Payment Voucher + consolidated payment_voucher ledger.
     * Scrap stays separate (legacy payment rows).
     */
    function purchase_invoice_payment_is_auto_pv_money(array $payment): bool
    {
        $amt = (float) ($payment['amount'] ?? 0);
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

if (!function_exists('purchase_invoice_post_auto_payment_voucher_ledger')) {
    /**
     * One supplier line (debit) + consolidated Against Ledger + cash/bank credits; mirrors Sale RV but reversed.
     *
     * @param array<int, array<string, mixed>> $payments_money
     */
    function purchase_invoice_post_auto_payment_voucher_ledger(
        $conn,
        int $pi_pv_id,
        string $pi_payment_voucher_no,
        string $invoice_no,
        string $invoice_date,
        int $supplier_id,
        string $supplier_name,
        array $payments_money,
        int $user_id,
        ?string $ref_no,
        bool $has_gold_pure_cols,
        ?string $ledger_doc_caption = null,
        bool $ledger_has_branch_col = false,
        string $ledger_branch_sql_val = '',
        string $ledger_br_scope = ''
    ): void {
        $lbcol = $ledger_has_branch_col ? ', branch_id' : '';
        if ($pi_pv_id <= 0 || empty($payments_money)) {
            return;
        }

        $total_supplier_debit = 0.0;
        foreach ($payments_money as $p) {
            $total_supplier_debit += (float) ($p['amount'] ?? 0);
        }
        if ($total_supplier_debit <= 0.00001) {
            return;
        }

        $sum_dg = 0.0;
        $sum_dgp = 0.0;
        $sum_ds = 0.0;
        foreach ($payments_money as $__pw) {
            $__mw = purchase_invoice_metal_exchange_ledger_wts($conn, $__pw);
            $sum_dg += (float) $__mw['dg'];
            $sum_dgp += (float) $__mw['dgp'];
            $sum_ds += (float) $__mw['ds'];
        }

        $party_against_parts = [];
        foreach ($payments_money as $p) {
            $line_amt = (float) ($p['amount'] ?? 0);
            if ($line_amt <= 0.00001) {
                continue;
            }
            $pt = strtolower(trim((string) ($p['payment_type'] ?? 'cash')));
            $dep_raw = trim((string) ($p['deposit_into'] ?? ''));
            if ($dep_raw === '' && $pt === 'cash') {
                $dep_raw = 'Cash';
            }
            if ($dep_raw === '' && (($pt === 'metal_exchange') || strpos($pt, 'm. exch') !== false || strpos($pt, 'metal-exchange') !== false || (strpos($pt, 'metal') !== false && strpos($pt, 'exch') !== false))) {
                $dep_raw = 'Metal Exchange';
            }
            if (auragold_payment_is_cheque_type($pt)) {
                $party_against_parts[] = auragold_cheque_payment_against_label($line_amt, 'payable');
            } elseif ($dep_raw !== '') {
                $party_against_parts[] = $dep_raw . '(' . number_format($line_amt, 2) . 'Dr)';
            }
        }
        $party_against_display = implode(', ', $party_against_parts);

        $pv_esc = mysqli_real_escape_string($conn, $pi_payment_voucher_no);
        $inv_esc = mysqli_real_escape_string($conn, $invoice_no);
        $inv_date_esc = mysqli_real_escape_string($conn, $invoice_date);
        $ref_sql = ($ref_no !== null && $ref_no !== '') ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL';
        $uid_sql = $user_id > 0 ? (string) $user_id : 'NULL';

        $pay_bal_pure_sel = $has_gold_pure_cols ? ', balance_gold_pure' : '';
        $last_balance = null;
        if ($supplier_id > 0) {
            $last_balance = getRecord("
                SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel
                FROM tbl_customer_ledger
                WHERE customer_id = $supplier_id AND customer_name = '$supplier_name' AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
        }
        if (!$last_balance && $supplier_name !== '') {
            $last_balance = getRecord("
                SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel
                FROM tbl_customer_ledger
                WHERE customer_name = '$supplier_name' AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
        }
        $prev_amt = (float) ($last_balance['balance_amount'] ?? 0);
        $prev_gold = (float) ($last_balance['balance_gold'] ?? 0);
        $prev_silver = (float) ($last_balance['balance_silver'] ?? 0);
        $prev_gold_pure = $has_gold_pure_cols ? (float) ($last_balance['balance_gold_pure'] ?? 0) : 0.0;

        $new_balance_amt = $prev_amt - $total_supplier_debit;
        $party_bal_gold = $prev_gold + $sum_dg;
        $party_bal_silver = $prev_silver + $sum_ds;
        $party_bal_gold_pure = $has_gold_pure_cols ? ($prev_gold_pure + $sum_dgp) : 0.0;

        $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $ledger_has_against = ($has_against && mysqli_num_rows($has_against) > 0);
        if ($has_against) {
            mysqli_free_result($has_against);
        }
        $against_cols = $ledger_has_against ? ', against_ledger, against_invoice_no' : '';
        $against_vals = '';
        if ($ledger_has_against) {
            $against_vals = $party_against_display !== ''
                ? ", '" . mysqli_real_escape_string($conn, $party_against_display) . "', '$inv_esc'"
                : ', NULL, NULL';
        }

        $cap = $ledger_doc_caption !== null && $ledger_doc_caption !== ''
            ? $ledger_doc_caption
            : ('Purchase ' . $invoice_no);
        $desc_base = 'Payment Voucher: ' . $pi_payment_voucher_no . ' (' . $cap . ')';
        if ($sum_dg > 1e-8 || $sum_dgp > 1e-8 || $sum_ds > 1e-8) {
            $desc_base .= ' — Metal Exchange';
        }
        $desc_esc = mysqli_real_escape_string($conn, $desc_base);
        $ledger_cust_id = $supplier_id > 0 ? $supplier_id : 0;

        if ($has_gold_pure_cols) {
            $party_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_gold_pure, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    $ledger_cust_id$ledger_branch_sql_val,
                    '$supplier_name',
                    'payment_voucher',
                    $pi_pv_id,
                    '$pv_esc',
                    '$inv_date_esc',
                    $total_supplier_debit,
                    0,
                    " . (float) $sum_dg . ', 0, ' . (float) $sum_dgp . ", 0, " . (float) $sum_ds . ", 0,
                    $new_balance_amt,
                    $party_bal_gold,
                    $party_bal_gold_pure,
                    $party_bal_silver,
                    '$desc_esc',
                    $ref_sql,
                    1,
                    $uid_sql,
                    NOW()
                    $against_vals
                )
            ";
        } else {
            $party_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    $ledger_cust_id$ledger_branch_sql_val,
                    '$supplier_name',
                    'payment_voucher',
                    $pi_pv_id,
                    '$pv_esc',
                    '$inv_date_esc',
                    $total_supplier_debit,
                    0,
                    " . (float) $sum_dg . ', 0, ' . (float) $sum_ds . ", 0,
                    $new_balance_amt,
                    $party_bal_gold,
                    $party_bal_silver,
                    '$desc_esc',
                    $ref_sql,
                    1,
                    $uid_sql,
                    NOW()
                    $against_vals
                )
            ";
        }
        if (!mysqli_query($conn, $party_sql)) {
            throw new Exception('Payment voucher party ledger failed: ' . mysqli_error($conn));
        }

        foreach ($payments_money as $item) {
            $cur = (float) ($item['amount'] ?? 0);
            $prev = (float) ($item['previous_balance_amount'] ?? 0);
            $tot = $cur + $prev;
            if ($tot <= 0.00001) {
                continue;
            }
            $pt_raw = strtolower(trim((string) ($item['payment_type'] ?? 'cash')));
            $dep_raw = trim((string) ($item['deposit_into'] ?? ''));
            if ($dep_raw === '' && $pt_raw === 'cash') {
                $dep_raw = 'Cash';
            }
            if ($dep_raw === '' && (($pt_raw === 'metal_exchange') || strpos($pt_raw, 'm. exch') !== false || strpos($pt_raw, 'metal-exchange') !== false || (strpos($pt_raw, 'metal') !== false && strpos($pt_raw, 'exch') !== false))) {
                $dep_raw = 'Metal Exchange';
            }
            if ($dep_raw === '') {
                continue;
            }
            if (auragold_payment_is_cheque_type($pt_raw)) {
                continue;
            }
            $dep_esc = esc($dep_raw);
            $me_line = purchase_invoice_metal_exchange_ledger_wts($conn, $item);
            $cg = (float) $me_line['dg'];
            $cgp = (float) $me_line['dgp'];
            $cs = (float) $me_line['ds'];
            $has_line_metal = ($cg > 1e-8 || $cgp > 1e-8 || $cs > 1e-8);

            $bal_sel = $has_gold_pure_cols
                ? 'balance_amount, balance_gold, balance_silver, balance_gold_pure'
                : 'balance_amount, balance_gold, balance_silver';
            $cash_balance_record = getRecord("
                SELECT $bal_sel FROM tbl_customer_ledger
                WHERE customer_name = '$dep_esc' AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
            $cash_prev_balance = (float) ($cash_balance_record['balance_amount'] ?? 0);
            $cash_new_balance = $cash_prev_balance - $tot;
            $acc_prev_g = (float) ($cash_balance_record['balance_gold'] ?? 0);
            $acc_prev_s = (float) ($cash_balance_record['balance_silver'] ?? 0);
            $acc_prev_gp = $has_gold_pure_cols ? (float) ($cash_balance_record['balance_gold_pure'] ?? 0) : 0.0;
            $acc_new_g = $has_line_metal ? ($acc_prev_g - $cg) : $acc_prev_g;
            $acc_new_s = $has_line_metal ? ($acc_prev_s - $cs) : $acc_prev_s;
            $acc_new_gp = ($has_gold_pure_cols && $has_line_metal) ? ($acc_prev_gp - $cgp) : $acc_prev_gp;

            $cash_desc_esc = mysqli_real_escape_string($conn, 'Payment to ' . $supplier_name . ' (Payment Voucher ' . $pi_payment_voucher_no . ')' . ($has_line_metal ? ' — Metal Exchange' : ''));
            $ca_line_esc = mysqli_real_escape_string($conn, accountledger_against_party_payment_label($supplier_name, $pt_raw, $tot));

            if ($has_line_metal && $has_gold_pure_cols) {
                if ($ledger_has_against) {
                    $cash_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,
                        balance_amount, balance_gold, balance_gold_pure, balance_silver,
                        description, reference_no, status, created_by, created_at,
                        against_ledger, against_invoice_no
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$inv_date_esc',
                        0,
                        $tot,
                        0, $cg, 0, $cgp, 0, $cs,
                        $cash_new_balance,
                        $acc_new_g,
                        $acc_new_gp,
                        $acc_new_s,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW(),
                        '$ca_line_esc',
                        '$inv_esc'
                    )
                ";
                } else {
                    $cash_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,
                        balance_amount, balance_gold, balance_gold_pure, balance_silver,
                        description, reference_no, status, created_by, created_at
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$inv_date_esc',
                        0,
                        $tot,
                        0, $cg, 0, $cgp, 0, $cs,
                        $cash_new_balance,
                        $acc_new_g,
                        $acc_new_gp,
                        $acc_new_s,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW()
                    )
                ";
                }
            } elseif ($has_line_metal && !$has_gold_pure_cols) {
                if ($ledger_has_against) {
                    $cash_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, debit_silver, credit_silver,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at,
                        against_ledger, against_invoice_no
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$inv_date_esc',
                        0,
                        $tot,
                        0, $cg, 0, $cs,
                        $cash_new_balance,
                        $acc_new_g,
                        $acc_new_s,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW(),
                        '$ca_line_esc',
                        '$inv_esc'
                    )
                ";
                } else {
                    $cash_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, debit_silver, credit_silver,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$inv_date_esc',
                        0,
                        $tot,
                        0, $cg, 0, $cs,
                        $cash_new_balance,
                        $acc_new_g,
                        $acc_new_s,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW()
                    )
                ";
                }
            } elseif ($ledger_has_against) {
                $cash_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
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
                        '$inv_date_esc',
                        0,
                        $tot,
                        $cash_new_balance,
                        0,
                        0,
                        '$cash_desc_esc',
                        $ref_sql,
                        1,
                        $uid_sql,
                        NOW(),
                        '$ca_line_esc',
                        '$inv_esc'
                    )
                ";
            } else {
                $cash_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lbcol, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at
                    ) VALUES (
                        0$ledger_branch_sql_val,
                        '$dep_esc',
                        'payment_voucher',
                        $pi_pv_id,
                        '$pv_esc',
                        '$inv_date_esc',
                        0,
                        $tot,
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
            if (!mysqli_query($conn, $cash_sql)) {
                throw new Exception('Payment voucher cash/bank/metal ledger failed: ' . mysqli_error($conn));
            }
        }
    }
}

if (!function_exists('purchase_invoice_validate_metal_exchange_payments')) {
    /** @param array<int, array<string, mixed>> $payments */
    function purchase_invoice_validate_metal_exchange_payments($conn, array $payments): void
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

    // Get invoice data
    $invoice_no = esc($_POST['order_no'] ?? '');
    $supplier_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $supplier_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency_post = trim((string) ($_POST['currency'] ?? ''));
    $currency = esc(
        $currency_post !== ''
            ? $currency_post
            : auragold_branch_profile_currency_display_label(
                isset($conn) && $conn instanceof mysqli ? $conn : null,
                isset($conn_master) && $conn_master instanceof mysqli ? $conn_master : null
            )
    );
    $exchange_rate = (float) ($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) {
        $exchange_rate = 1.0;
    }
    $ref_no = esc($_POST['ref_no'] ?? '');
    $purchase_person = esc($_POST['sales_person'] ?? '');
    $invoice_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $hedge_contract_ref = esc($_POST['hedge_contract_ref'] ?? '');
    $hedge_date = esc($_POST['hedge_date'] ?? '');
    $making_amount_for_sale_fixing = (float)($_POST['making_amount_for_sale_fixing'] ?? 0);
    $group_name = esc($_POST['group_name'] ?? '');
    $comment = esc($_POST['comment'] ?? '');
    $payment_comments_raw = isset($_POST['payment_comments']) ? $_POST['payment_comments'] : '[]';
    $payment_comments = is_string($payment_comments_raw) ? $payment_comments_raw : json_encode($payment_comments_raw);
    $payment_comments_esc = mysqli_real_escape_string($conn, $payment_comments);
    
    // Summary values
    $previous_balance = (float)($_POST['previous_balance'] ?? 0);
    $previous_gold = (float)($_POST['previous_gold'] ?? 0);
    $previous_silver = (float)($_POST['previous_silver'] ?? 0);
    $previous_diamond = (float)($_POST['previous_diamond'] ?? 0);
    $previous_gemstone = (float)($_POST['previous_gemstone'] ?? 0);
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $additional_amt = (float)($_POST['additional_amt'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $reward_points = (float)($_POST['reward_points'] ?? 0);
    $coupon_code = esc($_POST['coupon_code'] ?? '');
    $coupon_discount = (float)($_POST['coupon_discount'] ?? 0);
    $discount_amt = (float)($_POST['discount_amt'] ?? 0);
    $discount_percent = (float)($_POST['discount_percent'] ?? 0);
    $redeem_points = (float)($_POST['redeem_points'] ?? 0);
    
    static $has_discount_percent_col = null;
    if ($has_discount_percent_col === null) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'discount_percent'");
        $has_discount_percent_col = ($chk && mysqli_num_rows($chk) > 0);
        if (!$has_discount_percent_col) {
            @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoices ADD COLUMN discount_percent DECIMAL(10,2) DEFAULT 0 AFTER discount_amt");
            $has_discount_percent_col = true;
        }
        if ($chk) mysqli_free_result($chk);
    }
    static $has_payment_comments_col = null;
    if ($has_payment_comments_col === null) {
        $pcc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'payment_comments'");
        $has_payment_comments_col = ($pcc && mysqli_num_rows($pcc) > 0);
        if (!$has_payment_comments_col) {
            @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoices ADD COLUMN payment_comments TEXT NULL AFTER comment");
            $has_payment_comments_col = true;
        }
        if ($pcc) mysqli_free_result($pcc);
    }
    static $has_show_in_stock_journal_col = null;
    if ($has_show_in_stock_journal_col === null) {
        $sjsj = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'show_in_stock_journal'");
        $has_show_in_stock_journal_col = ($sjsj && mysqli_num_rows($sjsj) > 0);
        if (!$has_show_in_stock_journal_col) {
            @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoices ADD COLUMN show_in_stock_journal TINYINT(1) NOT NULL DEFAULT 1 AFTER comment");
            $has_show_in_stock_journal_col = true;
        }
        if ($sjsj) mysqli_free_result($sjsj);
    }
    static $has_exchange_rate_col = null;
    if ($has_exchange_rate_col === null) {
        $erc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'exchange_rate'");
        $has_exchange_rate_col = ($erc && mysqli_num_rows($erc) > 0);
        if (!$has_exchange_rate_col) {
            @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoices ADD COLUMN exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1.000000 AFTER currency");
            $has_exchange_rate_col = true;
        }
        if ($erc) mysqli_free_result($erc);
    }
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $metal_amt = (float)($_POST['metal_amt'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $show_in_stock_journal = isset($_POST['show_in_stock_journal']) ? (int)$_POST['show_in_stock_journal'] : 1;
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);
    $use_previous_balance = isset($_POST['use_previous_balance']) ? (int)$_POST['use_previous_balance'] : 0;
    $previous_balance_used_amt = (float)($_POST['previous_balance_used_amt'] ?? 0);
    
    // Optional: previous_diamond, previous_gemstone in Previous Balance
    $has_previous_diamond_gemstone = false;
    $pdg = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'previous_diamond'");
    if ($pdg && mysqli_num_rows($pdg) > 0) {
        $pdg2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'previous_gemstone'");
        $has_previous_diamond_gemstone = ($pdg2 && mysqli_num_rows($pdg2) > 0);
        if ($pdg2) mysqli_free_result($pdg2);
    }
    if ($pdg) mysqli_free_result($pdg);
    if (!$has_previous_diamond_gemstone) {
        @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoices ADD COLUMN previous_diamond DECIMAL(12,3) DEFAULT 0 AFTER previous_silver");
        @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoices ADD COLUMN previous_gemstone DECIMAL(12,3) DEFAULT 0 AFTER previous_diamond");
        $has_previous_diamond_gemstone = true;
    }
    // Check if table has use_previous_balance columns (run admin/sql/add_previous_balance_used_to_purchase_invoices.sql to add)
    $has_previous_balance_used_columns = false;
    $cols = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'use_previous_balance'");
    if ($cols && mysqli_num_rows($cols) > 0) {
        $cols2 = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'previous_balance_used_amt'");
        $has_previous_balance_used_columns = ($cols2 && mysqli_num_rows($cols2) > 0);
    }
    // Check if table has hedging columns (run admin/sql/add_hedging_columns_to_purchase_invoices.sql to add)
    $has_hedge_columns = false;
    $hcol = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'hedge_contract_ref'");
    if ($hcol && mysqli_num_rows($hcol) > 0) {
        $hcol2 = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'hedge_date'");
        $has_hedge_columns = ($hcol2 && mysqli_num_rows($hcol2) > 0);
    }
    // Making amount for sale fixing (when fixing_type = Hedging): run admin/sql/add_making_amount_for_sale_fixing_to_purchase_invoices.sql to add column
    $has_making_for_sale_fixing_column = false;
    $mcol = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'making_amount_for_sale_fixing'");
    if ($mcol && mysqli_num_rows($mcol) > 0) {
        $has_making_for_sale_fixing_column = true;
    }
    
    // When Hedging with making amount 0: treat invoice as fully fixed by sale fixing (against entry); show balance 0
    if ($fixing_type === 'Hedging' && $making_amount_for_sale_fixing == 0) {
        $balance_amt = 0;
    }
    
    // Check if update or insert
    $invoice_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $is_update = ($invoice_id > 0);

    $pi_has_branch_id_col = function_exists('auragold_ensure_table_branch_id_column')
        ? auragold_ensure_table_branch_id_column($conn, 'tbl_purchase_invoices', 'id')
        : (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id'));
    $pi_header_branch_id = function_exists('auragold_transaction_header_branch_id') ? (int) auragold_transaction_header_branch_id() : 0;
    $eff_branch_pi = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if ($pi_header_branch_id <= 0 && $eff_branch_pi > 0) {
        $pi_header_branch_id = $eff_branch_pi;
    }
    $pi_existing_row_branch = 0;
    if ($is_update && $pi_has_branch_id_col) {
        $own_b = getRecord('SELECT branch_id FROM tbl_purchase_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        $pi_existing_row_branch = (int) ($own_b['branch_id'] ?? 0);
        if ($eff_branch_pi > 0 && $pi_existing_row_branch > 0 && $pi_existing_row_branch !== $eff_branch_pi) {
            throw new Exception('This invoice belongs to another branch.');
        }
    }
    
    // Validation
    if (empty($supplier_name)) {
        throw new Exception("Supplier / vendor name is required");
    }
    if ($supplier_id <= 0) {
        throw new Exception("Please select a vendor (supplier) from the list");
    }
    
    if (empty($invoice_no)) {
        // Bill series: voucher "Purchase Invoice" in tbl_bill_series + tbl_voucher_types; else legacy PI-1, PI-2
        $invoice_no = function_exists('getNextPurchaseInvoiceNo') ? esc(getNextPurchaseInvoiceNo($conn)) : 'PI-1';
    }

    // New invoice: bump until invoice_no is unique among active rows (ignore soft-deleted)
    if (!$is_update) {
        $cfg = function_exists('getPurchaseInvoiceBillSeriesConfig') ? getPurchaseInvoiceBillSeriesConfig($conn) : ['prefix' => 'PI-', 'suffix' => '', 'start_count' => 1];
        $pi_active = auragold_doc_series_active_sql($conn, 'tbl_purchase_invoices');
        $existing_invoice = getRecord("SELECT id FROM tbl_purchase_invoices WHERE invoice_no = '$invoice_no'" . $pi_active);
        $guard = 0;
        while ($existing_invoice && $guard < 5000) {
            $invoice_no = esc(function_exists('bumpPurchaseInvoiceNo') ? bumpPurchaseInvoiceNo($conn, $invoice_no, $cfg) : ('PI-' . ($guard + 2)));
            $existing_invoice = getRecord("SELECT id FROM tbl_purchase_invoices WHERE invoice_no = '$invoice_no'" . $pi_active);
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_purchase_invoices', 'invoice_no']], $invoice_no);
        }
    }

    // Parse line items early so new invoices fail before header insert if no products
    $items = [];
    if (isset($_POST['items'])) {
        if (is_string($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
        } elseif (is_array($_POST['items'])) {
            $items = $_POST['items'];
        }
    }
    if (!is_array($items)) {
        $items = [];
    }
    if (!$is_update) {
        $productLineCount = 0;
        foreach ($items as $it) {
            if ((int)($it['product_id'] ?? 0) > 0) {
                $productLineCount++;
            }
        }
        if ($productLineCount === 0) {
            throw new Exception('At least one product line is required');
        }
    }
    
    if ($is_update) {
        // Get current invoice number to check if it's changing
        $current_invoice = getRecord("SELECT invoice_no FROM tbl_purchase_invoices WHERE id = $invoice_id");
        $current_invoice_no = $current_invoice ? $current_invoice['invoice_no'] : '';
        
        if ($current_invoice_no !== '' && function_exists('auragold_pi_has_active_sale_fixing') && auragold_pi_has_active_sale_fixing($current_invoice_no)) {
            throw new Exception('Delete the sale fixing first before saving changes to this purchase invoice.');
        }
        
        // Check if invoice_no is being changed and if it conflicts with another invoice
        if ($invoice_no !== $current_invoice_no) {
            $existing_invoice = getRecord("SELECT id FROM tbl_purchase_invoices WHERE invoice_no = '$invoice_no' AND id != $invoice_id" . auragold_doc_series_active_sql($conn, 'tbl_purchase_invoices'));
            if ($existing_invoice) {
                throw new Exception("Invoice number '$invoice_no' already exists. Please use a different invoice number.");
            }
        }
        
        // Update existing invoice
        // Only update invoice_no if it's different from current
        $invoice_no_update = ($invoice_no !== $current_invoice_no) ? "invoice_no = '$invoice_no'," : "";

        $pi_update_branch_set = '';
        if ($pi_has_branch_id_col && $pi_existing_row_branch === 0) {
            $fill_br = $eff_branch_pi > 0 ? $eff_branch_pi : $pi_header_branch_id;
            if ($fill_br > 0) {
                $pi_update_branch_set = ",\n                branch_id = " . (int) $fill_br;
            }
        }
        
        $sql = "
            UPDATE tbl_purchase_invoices SET
                $invoice_no_update
                supplier_id = " . ($supplier_id > 0 ? $supplier_id : 0) . ",
                supplier_name = '$supplier_name',
                against_of = " . ($against_of ? "'$against_of'" : "NULL") . ",
                currency = '$currency',
                " . (!empty($has_exchange_rate_col) ? "exchange_rate = $exchange_rate,\n                " : "") . "
                ref_no = " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                purchase_person = " . ($purchase_person ? "'$purchase_person'" : "NULL") . ",
                invoice_date = '$invoice_date',
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ",
                layaways_id = " . ($layaways ? (int)$layaways : "NULL") . ",
                fixing_type = '$fixing_type',
                " . ($has_hedge_columns ? "hedge_contract_ref = " . ($hedge_contract_ref ? "'$hedge_contract_ref'" : "NULL") . ",\n                hedge_date = " . ($hedge_date ? "'$hedge_date'" : "NULL") . ",\n                " : "") . "
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver
                " . ($has_previous_diamond_gemstone ? ",\n                previous_diamond = $previous_diamond,\n                previous_gemstone = $previous_gemstone" : "") . ",
                subtotal = $subtotal,
                additional_amt = $additional_amt,
                net_total = $net_total,
                reward_points = $reward_points,
                coupon_code = " . ($coupon_code ? "'$coupon_code'" : "NULL") . ",
                coupon_discount = $coupon_discount,
                discount_amt = $discount_amt,
                " . (isset($has_discount_percent_col) && $has_discount_percent_col ? "discount_percent = $discount_percent,\n                " : "") . "
                redeem_points = $redeem_points,
                grand_total = $grand_total,
                advance_payment = $advance_payment,
                metal_amt = $metal_amt,
                round_off = $round_off,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                " . ($has_previous_balance_used_columns ? "use_previous_balance = $use_previous_balance,\n                previous_balance_used_amt = $previous_balance_used_amt,\n                " : "") . "
                " . ($has_making_for_sale_fixing_column ? "making_amount_for_sale_fixing = $making_amount_for_sale_fixing,\n                " : "") . "
                group_name = " . ($group_name ? "'$group_name'" : "NULL") . ",
                comment = " . ($comment ? "'$comment'" : "NULL") . "
                " . (isset($has_payment_comments_col) && $has_payment_comments_col ? ",\n                payment_comments = '$payment_comments_esc'" : "") . "
                " . (!empty($has_show_in_stock_journal_col) ? ",\n                show_in_stock_journal = $show_in_stock_journal" : "") . "
                $pi_update_branch_set
                , updated_at = NOW()
            WHERE id = $invoice_id
        ";
        
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            // Check if it's a duplicate key error
            if (strpos($error, 'Duplicate entry') !== false) {
                throw new Exception("Invoice number '$invoice_no' already exists. Please use a different invoice number.");
            }
            throw new Exception("Invoice update failed: " . $error);
        }
        
        // Get old invoice items BEFORE deleting (to find matching stock records)
        $old_items = getList("SELECT id, product_id, product_characteristic_id, created_at FROM tbl_purchase_invoice_items WHERE invoice_id = $invoice_id");
        
        // Get invoice date for fallback deletion
        $invoice_record = getRecord("SELECT invoice_date FROM tbl_purchase_invoices WHERE id = $invoice_id");
        $invoice_date = $invoice_record ? $invoice_record['invoice_date'] : '';
        
        // Delete old stock records that were created for this invoice
        // Match by product_id, product_characteristic_id, date, and stock_type='purchase'
        if (!empty($old_items)) {
            foreach ($old_items as $old_item) {
                $old_product_id = (int)$old_item['product_id'];
                $old_char_id = $old_item['product_characteristic_id'] ? (int)$old_item['product_characteristic_id'] : 'NULL';
                $old_date = date('Y-m-d', strtotime($old_item['created_at']));
                $old_timestamp = $old_item['created_at'];
                
                // Delete stock records that match this invoice item
                // Match by product_id, characteristic_id, date, and stock_type='purchase'
                // Also match by timestamp (within 5 minutes of item creation)
                if ($old_char_id === 'NULL') {
                    $delete_stock_sql = "
                        DELETE FROM tbl_stock 
                        WHERE product_id = $old_product_id 
                        AND product_characteristic_id IS NULL
                        AND DATE(created_at) = '$old_date'
                        AND stock_type = 'purchase'
                        AND ABS(TIMESTAMPDIFF(MINUTE, created_at, '$old_timestamp')) <= 5
                    ";
                } else {
                    $delete_stock_sql = "
                        DELETE FROM tbl_stock 
                        WHERE product_id = $old_product_id 
                        AND product_characteristic_id = $old_char_id
                        AND DATE(created_at) = '$old_date'
                        AND stock_type = 'purchase'
                        AND ABS(TIMESTAMPDIFF(MINUTE, created_at, '$old_timestamp')) <= 5
                    ";
                }
                mysqli_query($conn, $delete_stock_sql);
            }
        } elseif ($invoice_date) {
            // Fallback: If no old items found, delete stock records by invoice date and product_id
            // This is less precise but will catch cases where items were already deleted
            // We'll delete stock records that match products from this invoice
            // Note: This is a broader delete, but necessary to prevent duplicates
            $delete_stock_sql = "
                DELETE s FROM tbl_stock s
                INNER JOIN tbl_purchase_invoice_items pii ON (
                    s.product_id = pii.product_id 
                    AND (s.product_characteristic_id = pii.product_characteristic_id OR (s.product_characteristic_id IS NULL AND pii.product_characteristic_id IS NULL))
                    AND DATE(s.created_at) = DATE(pii.created_at)
                    AND ABS(TIMESTAMPDIFF(MINUTE, s.created_at, pii.created_at)) <= 5
                )
                WHERE pii.invoice_id = $invoice_id
                AND s.stock_type = 'purchase'
            ";
            mysqli_query($conn, $delete_stock_sql);
        }
        
        // Delete stock journal entries that reference these invoice items (must be done before deleting items due to FK constraint)
        if (!empty($old_items)) {
            $old_item_ids = array_column($old_items, 'id');
            if (!empty($old_item_ids)) {
                $item_ids_str = implode(',', array_map('intval', $old_item_ids));
                mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE item_id IN ($item_ids_str)");
            }
        } else {
            // Fallback: Delete stock journal entries for all items in this invoice
            $all_item_ids = getList("SELECT id FROM tbl_purchase_invoice_items WHERE invoice_id = $invoice_id");
            if (!empty($all_item_ids)) {
                $item_ids_str = implode(',', array_map(function($item) { return (int)$item['id']; }, $all_item_ids));
                if ($item_ids_str) {
                    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE item_id IN ($item_ids_str)");
                }
            }
        }

        // Drop any PI stock-history rows for this invoice (covers legacy sj_invoice_no shapes, wrong item_id links, or rows missed by item_id IN (...))
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=pi|iid=" . (int) $invoice_id . "|%'");
        
        // Delete existing items and payments (after we've deleted stock journal entries)
        mysqli_query($conn, "DELETE FROM tbl_purchase_invoice_items WHERE invoice_id = $invoice_id");
        mysqli_query($conn, "DELETE FROM tbl_purchase_invoice_payments WHERE invoice_id = $invoice_id");
    } else {
        // Insert new invoice
        $sql = "
            INSERT INTO tbl_purchase_invoices (
                invoice_no, supplier_id, supplier_name, against_of, currency
                " . (!empty($has_exchange_rate_col) ? ", exchange_rate" : "") . ",
                ref_no, purchase_person,
                invoice_date, due_date, layaways_id, fixing_type
                " . ($has_hedge_columns ? ",\n                hedge_contract_ref, hedge_date" : "") . "
                " . ($has_making_for_sale_fixing_column ? ",\n                making_amount_for_sale_fixing" : "") . ",
                previous_balance, previous_gold, previous_silver
                " . ($has_previous_diamond_gemstone ? ",\n                previous_diamond, previous_gemstone" : "") . "
                " . ($has_previous_balance_used_columns ? ",\n                use_previous_balance, previous_balance_used_amt" : "") . ",
                subtotal, additional_amt, net_total, reward_points,
                coupon_code, coupon_discount, discount_amt
                " . (isset($has_discount_percent_col) && $has_discount_percent_col ? ", discount_percent" : "") . ",
                redeem_points,
                grand_total, advance_payment, metal_amt, round_off,
                paid_amt, balance_amt, group_name, comment
                " . (isset($has_payment_comments_col) && $has_payment_comments_col ? ", payment_comments" : "") . "
                " . (!empty($has_show_in_stock_journal_col) ? ", show_in_stock_journal" : "") . "
                " . ($pi_has_branch_id_col ? ", branch_id" : "") . ",
                status, created_by, created_at
            ) VALUES (
                '$invoice_no', " . ($supplier_id > 0 ? $supplier_id : 0) . ", '$supplier_name', " . ($against_of ? "'$against_of'" : "NULL") . ",
                '$currency'" . (!empty($has_exchange_rate_col) ? ", $exchange_rate" : "") . ", " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                " . ($purchase_person ? "'$purchase_person'" : "NULL") . ",
                '$invoice_date', " . ($due_date ? "'$due_date'" : "NULL") . ",
                " . ($layaways ? (int)$layaways : "NULL") . ",
                '$fixing_type'
                " . ($has_hedge_columns ? ",\n                " . ($hedge_contract_ref ? "'$hedge_contract_ref'" : "NULL") . ", " . ($hedge_date ? "'$hedge_date'" : "NULL") : "") . "
                " . ($has_making_for_sale_fixing_column ? ",\n                $making_amount_for_sale_fixing" : "") . ",
                $previous_balance, $previous_gold, $previous_silver
                " . ($has_previous_diamond_gemstone ? ",\n                $previous_diamond, $previous_gemstone" : "") . "
                " . ($has_previous_balance_used_columns ? ",\n                $use_previous_balance, $previous_balance_used_amt" : "") . ",
                $subtotal, $additional_amt, $net_total, $reward_points,
                " . ($coupon_code ? "'$coupon_code'" : "NULL") . ",
                $coupon_discount, $discount_amt
                " . (isset($has_discount_percent_col) && $has_discount_percent_col ? ", $discount_percent" : "") . ",
                $redeem_points,
                $grand_total, $advance_payment, $metal_amt, $round_off,
                $paid_amt, $balance_amt,
                " . ($group_name ? "'$group_name'" : "NULL") . ",
                " . ($comment ? "'$comment'" : "NULL") . "
                " . (isset($has_payment_comments_col) && $has_payment_comments_col ? ", '$payment_comments_esc'" : "") . "
                " . (!empty($has_show_in_stock_journal_col) ? ", $show_in_stock_journal" : "") . "
                " . ($pi_has_branch_id_col ? ", " . ($pi_header_branch_id > 0 ? (int) $pi_header_branch_id : "NULL") : "") . ",
                'draft', $user_id, NOW()
            )
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Invoice insert failed: " . mysqli_error($conn));
        }
        
        $invoice_id = mysqli_insert_id($conn);
    }
    
    // Save invoice items ($items parsed above)
    if (!empty($items) && is_array($items)) {
        static $pi_has_merge_group_index_column = null;
        if ($pi_has_merge_group_index_column === null) {
            $mgix = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'merge_group_index'");
            $pi_has_merge_group_index_column = ($mgix && mysqli_num_rows($mgix) > 0);
            if (!$pi_has_merge_group_index_column) {
                @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN merge_group_index INT UNSIGNED NULL DEFAULT NULL COMMENT 'Same value = same product list row (merged modal lines)' AFTER reverse");
                $mgix2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'merge_group_index'");
                $pi_has_merge_group_index_column = ($mgix2 && mysqli_num_rows($mgix2) > 0);
                if ($mgix2) {
                    @mysqli_free_result($mgix2);
                }
            }
            if ($mgix) {
                @mysqli_free_result($mgix);
            }
        }
        static $pi_has_barcode_no_column = null;
        if ($pi_has_barcode_no_column === null) {
            $bcn = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'barcode_no'");
            $pi_has_barcode_no_column = ($bcn && mysqli_num_rows($bcn) > 0);
            if (!$pi_has_barcode_no_column) {
                @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN barcode_no VARCHAR(100) NULL DEFAULT NULL COMMENT 'Tag barcode (may repeat across diamond composite lines)' AFTER barcode");
                $bcn2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'barcode_no'");
                $pi_has_barcode_no_column = ($bcn2 && mysqli_num_rows($bcn2) > 0);
                if ($bcn2) {
                    @mysqli_free_result($bcn2);
                }
            }
            if ($bcn) {
                @mysqli_free_result($bcn);
            }
        }
        $used_barcodes_in_request = []; // ensure unique barcode per item in this invoice
        foreach ($items as $item) {
            $active = isset($item['active']) ? (int)$item['active'] : 1;
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $rfid = esc($item['rfid'] ?? '');
            $voucher_type = esc($item['voucher_type'] ?? '');
            $barcode = esc($item['barcode'] ?? '');
            $design_no = esc($item['design_no'] ?? '');
            $huid = esc($item['huid'] ?? '');
            $category_id = isset($item['category_id']) && $item['category_id'] ? (int)$item['category_id'] : NULL;
            $diamond_category = esc($item['diamond_category'] ?? $item['category'] ?? '');
            $calculation_type = esc($item['calculation_type'] ?? $item['calculation'] ?? '');
            $product_name = esc($item['product_name'] ?? '');
            $location_id = isset($item['location_id']) && $item['location_id'] ? (int)$item['location_id'] : NULL;
            $carat = esc($item['carat'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $metal_qty = (float)($item['metal_qty'] ?? 1);
            if ($quantity <= 0 && $metal_qty > 0) {
                $quantity = $metal_qty;
            }
            if ($metal_qty <= 0 && $quantity > 0) {
                $metal_qty = $quantity;
            }
            $metal_weight = (float)($item['metal_weight'] ?? 0);
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
            $wastage_per = (float)($item['wastage_per'] ?? 0);
            $wastage_wt = (float)($item['wastage_wt'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? 0);
            $alloy_wt = (float)($item['alloy_wt'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $metal_rate = (float)($item['metal_rate'] ?? $item['rate'] ?? 0);
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
            $making_discount_amt = (float)($item['making_discount_amt'] ?? $item['making_discount_amount'] ?? 0);
            $making_amount = (float)($item['making_amount'] ?? $item['making'] ?? 0);
            $making_actual_value = (float)($item['making_actual_value'] ?? 0);
            $making_cost = (float)($item['making_cost'] ?? 0);
            // Hedging: keep full amounts on PI (total = metal + making); sale fixing entry uses making_amount_for_sale_fixing separately
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
            $sale_amount_with = (float)($item['sale_amount_with'] ?? $item['purchase_amount_with'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            // Keep amount / net_amount / purchase_amount aligned (imitation often only sends purchase_amount).
            if ($amount <= 0 && $purchase_amount > 0) {
                $amount = $purchase_amount;
            }
            if ($amount <= 0 && $net_amount > 0) {
                $amount = $net_amount;
            }
            if ($net_amount <= 0 && $amount > 0) {
                $net_amount = $amount;
            }
            if ($purchase_amount <= 0 && $amount > 0) {
                $purchase_amount = $amount;
            }
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
            $merge_group_index_sql = 'NULL';
            if (isset($item['merge_group_index']) && $item['merge_group_index'] !== '' && $item['merge_group_index'] !== null) {
                $merge_group_index_sql = (string)(int)$item['merge_group_index'];
            }
            $group_image = isset($item['group_image']) ? $item['group_image'] : '';
            
            if ($product_id > 0) {
                // Diamond & Stones modal often sends the same tag barcode for every line (jewellery + diamond + gemstone).
                // Each saved line must get a distinct inventory barcode; the shared tag is stored in barcode_no when that column exists.
                // Same logic as sale quotations / sale order / purchase quotation (invoice_item_unique_barcode.php): product opening prefix, else tbl_settings, else RN + 5 digits.
                $client_barcode_trim = trim((string)($item['barcode'] ?? ''));
                $barcode_no_for_sql = null;
                if ($client_barcode_trim !== '' && in_array($client_barcode_trim, $used_barcodes_in_request, true)) {
                    $barcode_no_for_sql = esc($client_barcode_trim);
                }
                $barcode_raw = auragold_resolve_unique_invoice_item_barcode($conn, $item, $used_barcodes_in_request);
                $barcode = esc($barcode_raw);
                
                // Check if group_image column exists
                static $has_group_image_column = null;
                if ($has_group_image_column === null) {
                    $gicol = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'group_image'");
                    $has_group_image_column = ($gicol && mysqli_num_rows($gicol) > 0);
                }
                // Check if diamond_category column exists (add if missing)
                static $has_diamond_category_column = null;
                if ($has_diamond_category_column === null) {
                    $dcc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'diamond_category'");
                    $has_diamond_category_column = ($dcc && mysqli_num_rows($dcc) > 0);
                    if (!$has_diamond_category_column) {
                        @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN diamond_category VARCHAR(100) NULL DEFAULT NULL AFTER calculation_type");
                        $has_diamond_category_column = true;
                    }
                    if ($dcc) @mysqli_free_result($dcc);
                }
                // Check if metal_rate column exists (add if missing)
                static $has_metal_rate_column = null;
                if ($has_metal_rate_column === null) {
                    $mrc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'metal_rate'");
                    $has_metal_rate_column = ($mrc && mysqli_num_rows($mrc) > 0);
                    if (!$has_metal_rate_column) {
                        @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN metal_rate DECIMAL(12,4) NULL DEFAULT NULL AFTER rate");
                        $has_metal_rate_column = true;
                    }
                    if ($mrc) @mysqli_free_result($mrc);
                }
                // Check if metal_qty column exists (add if missing)
                static $has_metal_qty_column = null;
                if ($has_metal_qty_column === null) {
                    $mqc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'metal_qty'");
                    $has_metal_qty_column = ($mqc && mysqli_num_rows($mqc) > 0);
                    if (!$has_metal_qty_column) {
                        @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity");
                        $has_metal_qty_column = true;
                    }
                    if ($mqc) @mysqli_free_result($mqc);
                }
                // Check if metal_weight column exists (add if missing)
                static $has_metal_weight_column = null;
                if ($has_metal_weight_column === null) {
                    $mwc = mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'metal_weight'");
                    $has_metal_weight_column = ($mwc && mysqli_num_rows($mwc) > 0);
                    if (!$has_metal_weight_column) {
                        @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty");
                        $has_metal_weight_column = true;
                    }
                    if ($mwc) @mysqli_free_result($mwc);
                }
                
                // Insert invoice item with all fields
                // Note: Some columns may not exist in the table yet - we'll handle that with ALTER TABLE if needed
                $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_purchase_invoice_items', $item);
                $item_sql = "
                    INSERT INTO tbl_purchase_invoice_items (
                        invoice_id, active, product_id, product_characteristic_id, rfid, voucher_type, barcode" . (!empty($pi_has_barcode_no_column) ? ", barcode_no" : "") . ", 
                        design_no, huid, category_id, calculation_type" . ($has_diamond_category_column ? ", diamond_category" : "") . ", product_name, location_id, carat, 
                        quantity" . (isset($has_metal_qty_column) && $has_metal_qty_column ? ", metal_qty" : "") . (isset($has_metal_weight_column) && $has_metal_weight_column ? ", metal_weight" : "") . ", pkt_wt, pkt_less_wt, requested_purity, requested_wt, gross_weight, less_weight, 
                        gold_loss_wt, gold_loss_value, setting_charge, net_weight, purity, purity_weight, 
                        wastage_per, wastage_wt, final_weight, alloy_wt, rate" . (isset($has_metal_rate_column) && $has_metal_rate_column ? ", metal_rate" : "") . ", metal_value, metal_cost, amount,
                        discount_type, discount_per, discount_amount, discount_type2, discount_per2, 
                        discount_amount2, discounted_amt, discounted_per, making_type, making_rate, making_discount_amt,
                        making_amount, making_actual_value, making_cost, min_price, minimum, stone_charge_type,
                        stone_weight, stone_rate, stone_amount, stone_cost, diamond_amount, purchase_amount,
                        sale_amount, sale_amount_with, net_amount, tax, other_charge_type, other_weight, other_rate,
                        other_info, other_amount, hallmark_amount, hallmark_rate, net_amt_with_tax, reverse" . 
                        ($has_group_image_column ? ", group_image" : "") . 
                        (!empty($pi_has_merge_group_index_column) ? ", merge_group_index" : "") . $ef_parts['columns'] . ",
                        status, created_at
                    ) VALUES (
                        $invoice_id, $active, $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                        " . ($rfid ? "'$rfid'" : "NULL") . ",
                        " . ($voucher_type ? "'$voucher_type'" : "NULL") . ",
                        " . ($barcode ? "'$barcode'" : "NULL") . (!empty($pi_has_barcode_no_column) ? ", " . (($barcode_no_for_sql !== null && $barcode_no_for_sql !== '') ? "'$barcode_no_for_sql'" : "NULL") : "") . ",
                        " . ($design_no ? "'$design_no'" : "NULL") . ",
                        " . ($huid ? "'$huid'" : "NULL") . ",
                        " . ($category_id ? $category_id : "NULL") . ",
                        " . ($calculation_type ? "'" . mysqli_real_escape_string($conn, $calculation_type) . "'" : "NULL") . "
                        " . ($has_diamond_category_column ? ", " . ($diamond_category ? "'" . mysqli_real_escape_string($conn, $diamond_category) . "'" : "NULL") : "") . ",
                        '$product_name',
                        " . ($location_id ? $location_id : "NULL") . ",
                        " . ($carat ? "'$carat'" : "NULL") . ",
                        $quantity" . (isset($has_metal_qty_column) && $has_metal_qty_column ? ", $metal_qty" : "") . (isset($has_metal_weight_column) && $has_metal_weight_column ? ", $metal_weight" : "") . ", $pkt_wt, $pkt_less_wt, $requested_purity, $requested_wt,
                        $gross_weight, $less_weight, $gold_loss_wt, $gold_loss_value, $setting_charge,
                        $net_weight, $purity, $purity_weight, $wastage_per, $wastage_wt,
                        $final_weight, $alloy_wt, $rate" . (isset($has_metal_rate_column) && $has_metal_rate_column ? ", $metal_rate" : "") . ", $metal_value, $metal_cost, $amount,
                        " . ($discount_type ? "'$discount_type'" : "NULL") . ",
                        $discount_per, $discount_amount,
                        " . ($discount_type2 ? "'$discount_type2'" : "NULL") . ",
                        $discount_per2, $discount_amount2, $discounted_amt, $discounted_per,
                        " . ($making_type ? "'$making_type'" : "NULL") . ",
                        $making_rate, $making_discount_amt, $making_amount, $making_actual_value, $making_cost,
                        $min_price, $minimum,
                        " . ($stone_charge_type ? "'$stone_charge_type'" : "NULL") . ",
                        $stone_weight, $stone_rate, $stone_amount, $stone_cost, $diamond_amount, $purchase_amount,
                        $sale_amount, $sale_amount_with, $net_amount, $tax,
                        " . ($other_charge_type ? "'$other_charge_type'" : "NULL") . ",
                        $other_weight, $other_rate,
                        " . ($other_info ? "'$other_info'" : "NULL") . ",
                        $other_amount, $hallmark_amount, $hallmark_rate, $net_amt_with_tax, $reverse" . 
                        ($has_group_image_column ? ", " . ($group_image ? "'" . mysqli_real_escape_string($conn, $group_image) . "'" : "NULL") : "") . 
                        (!empty($pi_has_merge_group_index_column) ? ", " . $merge_group_index_sql : "") . $ef_parts['values'] . ",
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
                        
                        throw new Exception("Database column '$missing_column' is missing. Please run the SQL file: admin/table_structure_purchase_invoice_items.sql to add all missing columns. Error: " . $error);
                    } else {
                        throw new Exception("Item insert failed: " . $error);
                    }
                }
                
                // Get the item ID that was just inserted
                $item_id = mysqli_insert_id($conn);

                // Persist photos to disk and sync to tbl_stock_journal_images (Diamond & Stones stock list by barcode)
                $group_image_raw = isset($item['group_image']) ? $item['group_image'] : '';
                if ($group_image_raw !== '' && $item_id > 0) {
                    $processed_gi = auragold_purchase_invoice_process_group_image($group_image_raw, (int) $invoice_id, (int) $item_id);
                    if ($processed_gi !== '') {
                        static $pi_items_has_images = null;
                        if ($pi_items_has_images === null) {
                            $ic = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'images'");
                            $pi_items_has_images = ($ic && mysqli_num_rows($ic) > 0);
                            if (!$pi_items_has_images) {
                                @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_items ADD COLUMN images LONGTEXT NULL DEFAULT NULL COMMENT 'JSON image paths' AFTER reverse");
                                $ic2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'images'");
                                $pi_items_has_images = ($ic2 && mysqli_num_rows($ic2) > 0);
                            }
                            if ($ic) {
                                @mysqli_free_result($ic);
                            }
                            if (isset($ic2) && $ic2) {
                                @mysqli_free_result($ic2);
                            }
                        }
                        $gi_esc = mysqli_real_escape_string($conn, $processed_gi);
                        $upd_gi = "UPDATE tbl_purchase_invoice_items SET group_image = '$gi_esc'";
                        if ($pi_items_has_images) {
                            $upd_gi .= ", images = '$gi_esc'";
                        }
                        $upd_gi .= ' WHERE id = ' . (int) $item_id;
                        @mysqli_query($conn, $upd_gi);
                        $bc_sync = trim((string) $barcode_raw);
                        if ($bc_sync !== '') {
                            auragold_sync_purchase_barcode_images_to_journal($conn, $bc_sync, $processed_gi, (int) $item_id);
                        }
                    }
                }
                
                // Add stock entry for purchase invoice (inward stock)
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
                    if ($purchase_amount > 0) {
                        $stock_value = $purchase_amount;
                    } else if ($net_amount > 0) {
                        $stock_value = $net_amount;
                    } else if ($amount > 0) {
                        $stock_value = $amount;
                    }
                    
                    // Default values if missing
                    if ($stock_purity <= 0) {
                        $stock_purity = 100.0; // Default to 100% if not specified
                    }
                    if ($branch_id <= 0) {
                        $hbr = 0;
                        if ($invoice_id > 0 && !empty($pi_has_branch_id_col)) {
                            $hr = getRecord('SELECT branch_id FROM tbl_purchase_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
                            $hbr = (int) ($hr['branch_id'] ?? 0);
                        }
                        if ($hbr <= 0 && function_exists('auragold_transaction_header_branch_id')) {
                            $hbr = (int) auragold_transaction_header_branch_id();
                        }
                        $branch_id = $hbr > 0 ? $hbr : 1;
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

                    $post_metal_id = isset($item['metal_id']) ? (int) $item['metal_id'] : 0;
                    if ($post_metal_id > 0) {
                        $metal_id = $post_metal_id;
                    }

                    $diamond_tab_category = function_exists('auragold_normalize_diamond_tab_category')
                        ? auragold_normalize_diamond_tab_category((string) ($item['diamond_category'] ?? $item['category'] ?? $diamond_category))
                        : trim((string) ($item['diamond_category'] ?? $item['category'] ?? $diamond_category));
                    if ($diamond_tab_category !== '' && function_exists('auragold_resolve_characteristic_for_diamond_category_purchase')) {
                        $resolved_ds = auragold_resolve_characteristic_for_diamond_category_purchase(
                            $conn,
                            $product_id,
                            $characteristic_id ? (int) $characteristic_id : 0,
                            $branch_id,
                            $metal_id,
                            $diamond_tab_category
                        );
                        if ((int) ($resolved_ds['characteristic_id'] ?? 0) > 0) {
                            $characteristic_id = (int) $resolved_ds['characteristic_id'];
                        }
                        if ((int) ($resolved_ds['metal_id'] ?? 0) > 0) {
                            $metal_id = (int) $resolved_ds['metal_id'];
                        }
                        if (!empty($resolved_ds['diamond_category'])) {
                            $diamond_tab_category = (string) $resolved_ds['diamond_category'];
                        }
                    }
                    
                    // Insert stock entry with stock_type='purchase' only (no outward during purchase)
                    $barcode_esc = $barcode ? "'" . mysqli_real_escape_string($conn, $barcode) . "'" : "NULL";
                    $stock_sql = "
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
                        ) VALUES (
                            $product_id,
                            " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                            $barcode_esc,
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
                            '$invoice_date',
                            NOW()
                        )
                    ";
                    
                    if (!mysqli_query($conn, $stock_sql)) {
                        throw new Exception("Stock insert failed: " . mysqli_error($conn) . " | SQL: " . $stock_sql);
                    }

                    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
                    $pure_weight_audit = (float)($item['pure_weight'] ?? $item['pure_wt'] ?? 0);
                    if ($pure_weight_audit <= 0 && $net_weight > 0 && $purity > 0) {
                        $pure_weight_audit = $net_weight * $purity / 100;
                    }
                    $pw_audit = (float)($item['purity_weight'] ?? $item['purity_wt'] ?? 0);
                    if ($pw_audit <= 0 && $net_weight > 0 && $purity > 0) {
                        $pw_audit = $net_weight * $purity / 100;
                    }
                    // Hyphenated key avoids collisions with legacy values like PI110 (PI + 1 + 10 without a separator)
                    $sj_no_pi = 'PI-' . (int) $invoice_id . '-' . (int) $item_id;
                    if (strlen($sj_no_pi) > 48) {
                        $sj_no_pi = 'P' . (int) $invoice_id . 'x' . (int) $item_id;
                    }
                    auragold_stock_history_audit_insert_row($conn, [
                        'sj_invoice_no' => $sj_no_pi,
                        'item_id' => (int) $item_id,
                        'invoice_id' => (int) $invoice_id,
                        'invoice_no' => $invoice_no,
                        'sj_date' => $invoice_date,
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
                        'purity_weight' => $pw_audit,
                        'pure_weight' => $pure_weight_audit,
                        'final_weight' => $final_weight,
                        'rate' => $rate,
                        'amount' => $amount,
                        'making_amount' => $making_amount,
                        'tax_amount' => $tax,
                        'net_amount' => $net_amount,
                        'net_amt_with_tax' => $net_amt_with_tax,
                        'rfid_code' => $rfid,
                        'voucher_type' => 'Purchase Invoice',
                        'design_no' => $design_no,
                        'category' => $diamond_tab_category !== '' ? $diamond_tab_category : trim((string) ($item['diamond_category'] ?? $item['category'] ?? $diamond_category)),
                        'comment' => 'auragold_doc|src=pi|iid=' . (int) $invoice_id . '|pii=' . (int) $item_id . '|',
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
        purchase_invoice_validate_metal_exchange_payments($conn, $payments);
        $pip_has_payment_details = false;
        $_pdc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_payments LIKE 'payment_details'");
        if ($_pdc && mysqli_num_rows($_pdc) > 0) {
            $pip_has_payment_details = true;
        } else {
            @mysqli_query($conn, "ALTER TABLE tbl_purchase_invoice_payments ADD COLUMN payment_details TEXT NULL COMMENT 'JSON copy of payment row (scrap weights, qty, etc.)'");
            $_pdc2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_payments LIKE 'payment_details'");
            $pip_has_payment_details = ($_pdc2 && mysqli_num_rows($_pdc2) > 0);
        }

        $__pi_me_has_ref = auragold_metal_exchange_document_init($conn, $is_update, (int) $invoice_id, 'purchase_invoice_metal_exchange');

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
            $payment_details_esc = mysqli_real_escape_string($conn, json_encode($payment, JSON_UNESCAPED_UNICODE));
            $pd_ins_col = $pip_has_payment_details ? ', payment_details' : '';
            $pd_ins_val = $pip_has_payment_details ? ", '$payment_details_esc'" : '';

            if (auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                // Try to insert with previous_balance_amount column (if it exists)
                $payment_sql = "
                        INSERT INTO tbl_purchase_invoice_payments (
                            invoice_id, payment_type, deposit_into, transaction_no,
                            cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity
                            $pd_ins_col,
                            status, created_at
                        ) VALUES (
                            $invoice_id, '$payment_type',
                            " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                            " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                            " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                            " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                            $amount,
                            $previous_balance_amount,
                            " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                            $quantity
                            $pd_ins_val,
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
                                INSERT INTO tbl_purchase_invoice_payments (
                                    invoice_id, payment_type, deposit_into, transaction_no,
                                    cheque_date, purity_carat, amount, diamond_category, quantity
                                    $pd_ins_col,
                                    status, created_at
                                ) VALUES (
                                    $invoice_id, '$payment_type',
                                    " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                                    " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                                    " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                                    " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                                    $amount,
                                    " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                                    $quantity
                                    $pd_ins_val,
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

            $pm_saved = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'purchase_invoice_metal_exchange',
                (int) $invoice_id,
                trim((string) preg_replace('/\s.+/', '', (string) $invoice_no)),
                substr(trim((string) $invoice_date), 0, 10),
                $pm_saved,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $__pi_me_has_ref,
                'Purchase Invoice — Metal Exchange',
                'pi_me',
                'PI-ME-',
                $metal_exchange_barcodes_out
            );
        }

        if ($_pdc) {
            mysqli_free_result($_pdc);
        }
        if (isset($_pdc2) && $_pdc2) {
            mysqli_free_result($_pdc2);
        }
    }
    
    // Set when purchase payments create a linked tbl_payment_vouchers row (used to avoid duplicate PV in cash block below).
    $pi_payment_voucher_no = null;
    $pi_pv_id = 0;

    auragold_ensure_customer_ledger_branch_column($conn);
    $pi_ledger_branch_id = 0;
    if ($invoice_id > 0 && !empty($pi_has_branch_id_col)) {
        $pibr = getRecord('SELECT branch_id FROM tbl_purchase_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        $pi_ledger_branch_id = (int) ($pibr['branch_id'] ?? 0);
    }
    if ($pi_ledger_branch_id <= 0) {
        if ($pi_header_branch_id > 0) {
            $pi_ledger_branch_id = $pi_header_branch_id;
        } elseif ($eff_branch_pi > 0) {
            $pi_ledger_branch_id = $eff_branch_pi;
        }
    }
    $ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = '';
    if ($ledger_has_branch_col) {
        $ledger_branch_sql_val = ', ' . ($pi_ledger_branch_id > 0 ? (string) (int) $pi_ledger_branch_id : 'NULL');
    }
    $ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql') ? auragold_customer_ledger_branch_scope_sql($conn, $pi_ledger_branch_id) : '';

    // Update customer ledger (same logic as sale invoice: single entry when full from previous balance)
    if ($supplier_id > 0 || !empty($supplier_name)) {
        if ($is_update) {
            // Same numeric id can exist on sale and purchase invoices; type "payment" is used for both.
            // Restrict deletes to this PI's voucher numbers only (PRIx, PV-x, etc.), not sale invoice numbers.
            $pi_del_txn_nos = array_values(array_unique(array_filter([
                trim((string) ($current_invoice_no ?? '')),
                trim((string) $invoice_no),
            ])));
            $pv_tbl_del = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_tbl_del && mysqli_num_rows($pv_tbl_del) > 0) {
                mysqli_free_result($pv_tbl_del);
                $ref_esc_list = [];
                foreach (array_unique([trim((string) ($current_invoice_no ?? '')), trim((string) $invoice_no)]) as $rfn) {
                    if ($rfn !== '') {
                        $ref_esc_list[] = "'" . mysqli_real_escape_string($conn, $rfn) . "'";
                    }
                }
                if (!empty($ref_esc_list)) {
                    $refs_in = implode(',', $ref_esc_list);
                    $pv_nos = getList("SELECT DISTINCT voucher_no FROM tbl_payment_vouchers WHERE ref_no IN ($refs_in)");
                    if (is_array($pv_nos)) {
                        foreach ($pv_nos as $pvr) {
                            $vn = trim((string) ($pvr['voucher_no'] ?? ''));
                            if ($vn !== '') {
                                $pi_del_txn_nos[] = $vn;
                            }
                        }
                    }
                }
            } elseif ($pv_tbl_del) {
                mysqli_free_result($pv_tbl_del);
            }
            $pi_del_txn_nos = array_values(array_unique(array_filter($pi_del_txn_nos)));
            $pi_del_in_parts = [];
            foreach ($pi_del_txn_nos as $n) {
                $pi_del_in_parts[] = "'" . mysqli_real_escape_string($conn, $n) . "'";
            }
            if (empty($pi_del_in_parts)) {
                $pi_del_in_parts[] = "'" . mysqli_real_escape_string($conn, $invoice_no) . "'";
            }
            $pi_del_txn_no_in = implode(',', $pi_del_in_parts);

            $pv_led_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_led_tbl && mysqli_num_rows($pv_led_tbl) > 0) {
                mysqli_free_result($pv_led_tbl);
                $ref_esc_pv = [];
                foreach (array_unique([trim((string) ($current_invoice_no ?? '')), trim((string) $invoice_no)]) as $rfn) {
                    if ($rfn !== '') {
                        $ref_esc_pv[] = "'" . mysqli_real_escape_string($conn, $rfn) . "'";
                    }
                }
                if (!empty($ref_esc_pv)) {
                    $pv_id_rows = getList('SELECT id FROM tbl_payment_vouchers WHERE ref_no IN (' . implode(',', $ref_esc_pv) . ')');
                    if (is_array($pv_id_rows)) {
                        foreach ($pv_id_rows as $pvr) {
                            $vid = (int) ($pvr['id'] ?? 0);
                            if ($vid > 0) {
                                mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'payment_voucher' AND transaction_id = $vid AND status = 1");
                            }
                        }
                    }
                }
            } elseif ($pv_led_tbl) {
                mysqli_free_result($pv_led_tbl);
            }

            mysqli_query($conn, "
                DELETE FROM tbl_customer_ledger 
                WHERE transaction_id = $invoice_id AND status = 1 
                AND transaction_no IN ($pi_del_txn_no_in)
                AND transaction_type IN ('purchase_invoice', 'Purchase Invoice', 'previous_balance_payment', 'payment')
            ");
        }
        
        $has_gold_pure_cols = false;
        $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
        if ($gpc && mysqli_num_rows($gpc) > 0) { $has_gold_pure_cols = true; }
        if ($gpc) mysqli_free_result($gpc);
        $prev_bal_cols = "balance_amount, balance_gold, balance_silver" . ($has_gold_pure_cols ? ", balance_gold_pure" : "");

        $previous_balance_record = null;
        if ($supplier_id > 0) {
            $previous_balance_record = getRecord("
                SELECT $prev_bal_cols
                FROM tbl_customer_ledger 
                WHERE customer_id = $supplier_id AND status = 1 
                $ledger_br_scope
                ORDER BY id DESC 
                LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_balance WHERE customer_id = $supplier_id LIMIT 1");
            }
        }
        if (!$previous_balance_record && !empty($supplier_name)) {
            $previous_balance_record = getRecord("
                SELECT $prev_bal_cols
                FROM tbl_customer_ledger 
                WHERE customer_name = '$supplier_name' AND status = 1 
                $ledger_br_scope
                ORDER BY id DESC 
                LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_balance WHERE customer_name = '$supplier_name' LIMIT 1");
            }
        }
        
        $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? 0);
        $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? 0);
        $prev_balance_gold_pure = (float)($previous_balance_record['balance_gold_pure'] ?? 0);
        $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? 0);
        
        $total_cash_payment = 0;
        if (!empty($payments) && is_array($payments)) {
            foreach ($payments as $p) {
                $total_cash_payment += (float)($p['amount'] ?? 0) - (float)($p['previous_balance_amount'] ?? 0);
            }
        }
        $full_from_previous = ($grand_total > 0 && $previous_balance_used_amt >= $grand_total && $total_cash_payment <= 0);

        $total_purchase_making_amt = 0;
        if (!empty($items) && is_array($items)) {
            foreach ($items as $__mpa_it) {
                $total_purchase_making_amt += (float)($__mpa_it['making_amount'] ?? $__mpa_it['making'] ?? 0);
            }
        }
        $ledger_mpa_against_q = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $ledger_mpa_has_against = ($ledger_mpa_against_q && mysqli_num_rows($ledger_mpa_against_q) > 0);
        if ($ledger_mpa_against_q) {
            mysqli_free_result($ledger_mpa_against_q);
        }
        $ledger_mpa_against_cols = $ledger_mpa_has_against ? ", against_ledger, against_invoice_no" : "";
        $ledger_mpa_against_vals = $ledger_mpa_has_against ? ", '" . mysqli_real_escape_string($conn, $supplier_name) . "', '$invoice_no'" : "";
        
        if ($full_from_previous && $previous_balance_used_amt > 0) {
            // One entry only: purchase paid from previous balance — debit = amount used, balance = prev + debit (remaining)
            $remaining_balance = $prev_balance_amount + (float)$previous_balance_used_amt;
            $amt_desc = number_format($previous_balance_used_amt, 2);
            $single_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, against_ledger, against_invoice_no,
                    status, created_by, created_at
                ) VALUES (
                    " . ($supplier_id > 0 ? $supplier_id : 0) . $ledger_branch_sql_val . ",
                    '$supplier_name',
                    'previous_balance_payment',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    " . (float)$previous_balance_used_amt . ",
                    0.00,
                    0.000,
                    0.000,
                    0.000,
                    0.000,
                    $remaining_balance,
                    $prev_balance_gold,
                    $prev_balance_silver,
                    'Purchase $invoice_no (paid from previous balance - $amt_desc)',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    'Previous Balance',
                    'Previous Balance',
                    1,
                    $user_id,
                    NOW()
                )
            ";
            if (!mysqli_query($conn, $single_sql)) {
                throw new Exception("Previous balance payment ledger entry failed: " . mysqli_error($conn));
            }
            $new_balance_amount = $remaining_balance;
            $new_balance_gold = $prev_balance_gold;
            $new_balance_silver = $prev_balance_silver;
        } else {
            // Metal quantities from items (used for both Standard and Hedging)
            // Gold: use purity_weight for gold credit/debit when metal is gold
            $total_gold_weight = 0;
            $total_gold_purity_weight = 0;
            $total_silver_weight = 0;
            if (!empty($items) && is_array($items)) {
                foreach ($items as $item) {
                    $metal_id = isset($item['metal_id']) ? (int)$item['metal_id'] : 0;
                    $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                    $product_name = isset($item['product_name']) ? trim($item['product_name']) : '';
                    $net_weight = (float)($item['net_weight'] ?? $item['gross_weight'] ?? $item['metal_weight'] ?? $item['weight'] ?? 0);
                    $purity_weight = (float)($item['purity_weight'] ?? $item['pure_weight'] ?? $item['pure_wt'] ?? $item['purity_wt'] ?? $item['final_weight'] ?? 0);
                    if ($purity_weight <= 0 && $net_weight > 0) {
                        $purity_pct = (float)($item['purity'] ?? 0);
                        if ($purity_pct > 0) {
                            if ($purity_pct <= 1) {
                                $purity_weight = $net_weight * $purity_pct;
                            } else {
                                $purity_weight = $net_weight * ($purity_pct / 100);
                            }
                        }
                    }
                    // When metal_id not sent from form, resolve from product or product name (e.g. "neckless - Gold")
                    if ($metal_id <= 0 && $product_id > 0) {
                        $pc = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 LIMIT 1");
                        if ($pc && !empty($pc['metal_id'])) {
                            $metal_id = (int)$pc['metal_id'];
                        }
                    }
                    if ($metal_id <= 0 && $product_name !== '') {
                        $name_lower = strtolower($product_name);
                        if (strpos($name_lower, 'gold') !== false) {
                            $metal_id = -1; // treat as gold by name
                        } else if (strpos($name_lower, 'silver') !== false) {
                            $metal_id = -2; // treat as silver by name
                        }
                    }
                    if ($metal_id > 0) {
                        $metal = getRecord("SELECT id, COALESCE(display_name, system_name, '') as metal_name FROM tbl_metal WHERE id = $metal_id LIMIT 1");
                        if ($metal) {
                            $metal_name = strtolower($metal['metal_name'] ?? '');
                            if (strpos($metal_name, 'gold') !== false) {
                                $total_gold_weight += $net_weight;
                                $total_gold_purity_weight += ($purity_weight > 0 ? $purity_weight : $net_weight);
                            } else if (strpos($metal_name, 'silver') !== false) {
                                $total_silver_weight += $net_weight;
                            }
                        }
                    } else if ($metal_id === -1) {
                        $total_gold_weight += $net_weight;
                        $total_gold_purity_weight += ($purity_weight > 0 ? $purity_weight : $net_weight);
                    } else if ($metal_id === -2) {
                        $total_silver_weight += $net_weight;
                    }
                }
            }
            // Gross = total_gold_weight (e.g. 10); Pure = purity wt when set (e.g. 9.5), else same as gross
            $gold_pure_for_ledger = $total_gold_purity_weight > 0 ? $total_gold_purity_weight : $total_gold_weight;
            // Metal cost used for sale fixing / hedging offset (same as auto SF amount)
            $metal_cost_for_hedge_row = ($fixing_type === 'Hedging')
                ? max(0, (float)$grand_total - (float)$making_amount_for_sale_fixing)
                : 0;

            if ($fixing_type === 'Hedging') {
                // Purchase Account row: full invoice credit, no metal (metal + metal-cost money sit on the Hedging offset row).
                $ledger_debit_amount = 0;
                $ledger_credit_amount = $grand_total;
                $new_balance_amount = $prev_balance_amount + $grand_total;
                $ledger_debit_gold = 0;
                $ledger_credit_gold = 0;
                $ledger_debit_gold_pure = 0;
                $ledger_credit_gold_pure = 0;
                $ledger_debit_silver = 0;
                $ledger_credit_silver = 0;
                $new_balance_gold = $prev_balance_gold;
                $new_balance_gold_pure = $prev_balance_gold_pure;
                $new_balance_silver = $prev_balance_silver;
            } else {
                $ledger_debit_amount = 0;
                $ledger_credit_amount = $grand_total;
                $new_balance_amount = $prev_balance_amount + $ledger_credit_amount;
                $ledger_debit_gold = $total_gold_weight;
                $ledger_credit_gold = 0;
                $ledger_debit_gold_pure = $gold_pure_for_ledger;
                $ledger_credit_gold_pure = 0;
                $ledger_debit_silver = $total_silver_weight;
                $ledger_credit_silver = 0;
                $new_balance_gold = $prev_balance_gold + $total_gold_weight;
                $new_balance_gold_pure = $prev_balance_gold_pure + $gold_pure_for_ledger;
                $new_balance_silver = $prev_balance_silver + $total_silver_weight;
            }
            
            $against_ledger = '';
            $against_invoice_no = $invoice_no;
            if (!empty($against_of)) {
                $against_balance = getRecord("SELECT balance_amount FROM tbl_customer_balance WHERE customer_name = '$against_of' ORDER BY last_updated DESC LIMIT 1");
                if ($against_balance) {
                    $ab = (float)($against_balance['balance_amount'] ?? 0);
                    $against_ledger = $against_of . '(' . number_format(abs($ab), 2) . ($ab >= 0 ? 'Dr' : 'Cr') . ')';
                } else {
                    $against_ledger = $against_of;
                }
                $against_invoice_no = $ref_no;
            } else {
                // Purchase Invoice entry: against Purchase Account (for supplier ledger display)
                $against_ledger = 'Purchase Account';
            }
            
            $ledger_gold_pure_cols = $has_gold_pure_cols ? "debit_gold_pure, credit_gold_pure," : "";
            $ledger_gold_pure_vals = $has_gold_pure_cols ? (float)$ledger_debit_gold_pure . ", " . (float)$ledger_credit_gold_pure . ", " : "";
            $ledger_balance_gold_pure_col = $has_gold_pure_cols ? ", balance_gold_pure" : "";
            $ledger_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$new_balance_gold_pure : "";
            $ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount, 
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                    description, reference_no, against_ledger, against_invoice_no,
                    status, created_by, created_at
                ) VALUES (
                    " . ($supplier_id > 0 ? $supplier_id : 0) . $ledger_branch_sql_val . ",
                    '$supplier_name',
                    'purchase_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    $ledger_debit_amount,
                    $ledger_credit_amount,
                    " . (float)$ledger_debit_gold . ",
                    " . (float)$ledger_credit_gold . ",
                    $ledger_gold_pure_vals " . (float)$ledger_debit_silver . ",
                    " . (float)$ledger_credit_silver . ",
                    $new_balance_amount,
                    $new_balance_gold $ledger_balance_gold_pure_val,
                    $new_balance_silver,
                    'Purchase Invoice: $invoice_no" . ($fixing_type === 'Hedging' ? ' — Hedging' : '') . "',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    " . ($against_ledger ? "'$against_ledger'" : "NULL") . ",
                    " . ($against_invoice_no ? "'$against_invoice_no'" : "NULL") . ",
                    1,
                    $user_id,
                    NOW()
                )
            ";
            if (!mysqli_query($conn, $ledger_sql)) {
                throw new Exception("Ledger entry failed: " . mysqli_error($conn));
            }
            // Hedging: second row = debit metal cost only (not full invoice); metal qty on credit side here (not on Purchase Account row).
            // Description must contain '(Hedging)' for accountledger-report.php metal columns.
            if ($fixing_type === 'Hedging' && $grand_total > 0) {
                $hedge_debit_balance_amount = $new_balance_amount - $metal_cost_for_hedge_row;
                $hedge_debit_balance_gold = $new_balance_gold - (float)$total_gold_weight;
                $hedge_debit_balance_gold_pure = $new_balance_gold_pure - (float)$gold_pure_for_ledger;
                $hedge_debit_balance_silver = $new_balance_silver - (float)$total_silver_weight;
                $hedge_gold_pure_cols = $has_gold_pure_cols ? "debit_gold_pure, credit_gold_pure," : "";
                $tg = (float)$total_gold_weight;
                $gp = (float)$gold_pure_for_ledger;
                $ts = (float)$total_silver_weight;
                $hedge_metal_vals = $has_gold_pure_cols
                    ? "0.000, $tg, 0.000, $gp, 0.000, $ts"
                    : "0.000, $tg, 0.000, $ts";
                $hedge_balance_gold_pure_col = $has_gold_pure_cols ? ", balance_gold_pure" : "";
                $hedge_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$hedge_debit_balance_gold_pure : "";
                $hedge_debit_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, $hedge_gold_pure_cols debit_silver, credit_silver,
                        balance_amount, balance_gold $hedge_balance_gold_pure_col, balance_silver,
                        description, reference_no, against_ledger, against_invoice_no,
                        status, created_by, created_at
                    ) VALUES (
                        " . ($supplier_id > 0 ? $supplier_id : 0) . $ledger_branch_sql_val . ",
                        '$supplier_name',
                        'purchase_invoice',
                        $invoice_id,
                        '$invoice_no',
                        '$invoice_date',
                        $metal_cost_for_hedge_row,
                        0.00,
                        $hedge_metal_vals,
                        $hedge_debit_balance_amount,
                        $hedge_debit_balance_gold $hedge_balance_gold_pure_val,
                        $hedge_debit_balance_silver,
                        'Hedging offset: $invoice_no (Fixing of PI) (Hedging)',
                        " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                        'Hedging',
                        " . ($against_invoice_no ? "'$against_invoice_no'" : "NULL") . ",
                        1,
                        $user_id,
                        NOW()
                    )
                ";
                if (!mysqli_query($conn, $hedge_debit_sql)) {
                    throw new Exception("Hedging debit ledger entry failed: " . mysqli_error($conn));
                }
            }
            // Hedging Account (PI): one ledger row — metal-cost credit (if any) + gold/silver credit (if any).
            if ($fixing_type === 'Hedging') {
                $ha_has_money = $metal_cost_for_hedge_row > 0;
                $ha_has_metal_wt = ($total_gold_weight > 0 || $total_silver_weight > 0);
                if ($ha_has_money || $ha_has_metal_wt) {
                    $ha_last = getRecord("SELECT balance_amount, balance_gold, balance_silver " . ($has_gold_pure_cols ? ", balance_gold_pure" : "") . " FROM tbl_customer_ledger WHERE customer_name = 'Hedging Account' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
                    $ha_prev_amt = (float)($ha_last['balance_amount'] ?? 0);
                    $ha_prev_gold = (float)($ha_last['balance_gold'] ?? 0);
                    $ha_prev_silver = (float)($ha_last['balance_silver'] ?? 0);
                    $ha_prev_gold_pure = $has_gold_pure_cols ? (float)($ha_last['balance_gold_pure'] ?? 0) : 0;
                    $mc_ha = (float)$metal_cost_for_hedge_row;
                    $ha_credit_amt = $ha_has_money ? $mc_ha : 0;
                    $ha_new_amt = $ha_has_money ? ($ha_prev_amt - $mc_ha) : $ha_prev_amt;
                    if ($ha_has_metal_wt) {
                        $ha_new_gold = $ha_prev_gold + $total_gold_weight;
                        $ha_new_silver = $ha_prev_silver + $total_silver_weight;
                        $ha_new_gold_pure = $ha_prev_gold_pure + $gold_pure_for_ledger;
                        $ha_metal_vals = "0.000, " . (float)$total_gold_weight;
                        if ($has_gold_pure_cols) {
                            $ha_metal_vals .= ", 0.000, " . (float)$gold_pure_for_ledger;
                        }
                        $ha_metal_vals .= ", 0.000, " . (float)$total_silver_weight;
                    } else {
                        $ha_new_gold = $ha_prev_gold;
                        $ha_new_silver = $ha_prev_silver;
                        $ha_new_gold_pure = $ha_prev_gold_pure;
                        $ha_metal_vals = "0.000, 0.000";
                        if ($has_gold_pure_cols) {
                            $ha_metal_vals .= ", 0.000, 0.000";
                        }
                        $ha_metal_vals .= ", 0.000, 0.000";
                    }
                    $ha_balance_gold_pure_col = $has_gold_pure_cols ? ", balance_gold_pure" : "";
                    $ha_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$ha_new_gold_pure : "";
                    $ha_supplier_esc = mysqli_real_escape_string($conn, $supplier_name);
                    $ha_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                        balance_amount, balance_gold $ha_balance_gold_pure_col, balance_silver,
                        description, reference_no, against_ledger, against_invoice_no,
                        status, created_by, created_at
                    ) VALUES (
                        0" . $ledger_branch_sql_val . ",
                        'Hedging Account',
                        'purchase_invoice',
                        $invoice_id,
                        '$invoice_no',
                        '$invoice_date',
                        0.00,
                        $ha_credit_amt,
                        $ha_metal_vals,
                        $ha_new_amt,
                        $ha_new_gold $ha_balance_gold_pure_val,
                        $ha_new_silver,
                        'Purchase Invoice: $invoice_no (Hedging)',
                        " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                        '$ha_supplier_esc',
                        '$invoice_no',
                        1,
                        $user_id,
                        NOW()
                    )
                ";
                    if (!mysqli_query($conn, $ha_sql)) {
                        throw new Exception("Hedging Account ledger entry (purchase) failed: " . mysqli_error($conn));
                    }
                }
            }
            // Purchase Account: debit full invoice (matches supplier credit). Supplier row only sets against_ledger text — this is the actual Purchase ledger line.
            if ($grand_total > 0) {
                $pa_has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
                $pa_against_cols = ($pa_has_against && mysqli_num_rows($pa_has_against) > 0) ? ", against_ledger, against_invoice_no" : "";
                $pa_against_vals = ($pa_has_against && mysqli_num_rows($pa_has_against) > 0)
                    ? ", '" . mysqli_real_escape_string($conn, $supplier_name) . "', '$invoice_no'"
                    : "";
                if ($pa_has_against) {
                    mysqli_free_result($pa_has_against);
                }
                $pa_prev = getRecord("
                    SELECT balance_amount FROM tbl_customer_ledger
                    WHERE customer_name = 'Purchase Account' AND status = 1
                    $ledger_br_scope
                    ORDER BY transaction_date DESC, id DESC LIMIT 1
                ");
                $pa_bal = (float)($pa_prev['balance_amount'] ?? 0) + (float)$grand_total;
                $pa_desc = 'Purchase Invoice: ' . $invoice_no . ' — ' . $supplier_name;
                $pa_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, description, status, created_by, created_at
                        $pa_against_cols
                    ) VALUES (
                        0" . $ledger_branch_sql_val . ",
                        'Purchase Account',
                        'purchase_invoice',
                        $invoice_id,
                        '$invoice_no',
                        '$invoice_date',
                        " . (float)$grand_total . ",
                        0.00,
                        $pa_bal,
                        '" . mysqli_real_escape_string($conn, $pa_desc) . "',
                        1,
                        $user_id,
                        NOW()
                        $pa_against_vals
                    )
                ";
                if (!mysqli_query($conn, $pa_sql)) {
                    throw new Exception('Purchase Account ledger entry failed: ' . mysqli_error($conn));
                }
            }
        }

        if ($total_purchase_making_amt > 0) {
            $mpa_prev = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = 'Making Purchase Account' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            $mpa_bal = (float)($mpa_prev['balance_amount'] ?? 0) + (float)$total_purchase_making_amt;
            $mpa_desc = 'Making charges - Purchase: ' . $invoice_no;
            $mpa_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    balance_amount, description, reference_no, status, created_by, created_at
                    $ledger_mpa_against_cols
                ) VALUES (
                    0" . $ledger_branch_sql_val . ",
                    'Making Purchase Account',
                    'purchase_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    " . (float)$total_purchase_making_amt . ",
                    0.00,
                    $mpa_bal,
                    '" . mysqli_real_escape_string($conn, $mpa_desc) . "',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    1,
                    $user_id,
                    NOW()
                    $ledger_mpa_against_vals
                )
            ";
            if (!mysqli_query($conn, $mpa_sql)) {
                throw new Exception('Making Purchase Account ledger entry failed: ' . mysqli_error($conn));
            }
        }
        
        // If there are payments (skip when full from previous balance — already added single entry)
        if (!$full_from_previous && !empty($payments) && is_array($payments)) {
            $txn_for_payment = $invoice_no;
            $pv_table_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_table_chk && mysqli_num_rows($pv_table_chk) > 0) {
                mysqli_free_result($pv_table_chk);
                $total_payment_post = 0.0;
                foreach ($payments as $__p) {
                    $total_payment_post += (float)($__p['amount'] ?? 0) + (float)($__p['previous_balance_amount'] ?? 0);
                }
                if ($total_payment_post > 0) {
                    if ($is_update) {
                        mysqli_query($conn, "
                            DELETE pvi FROM tbl_payment_voucher_items pvi
                            INNER JOIN tbl_payment_vouchers pv ON pvi.voucher_id = pv.id
                            WHERE pv.ref_no = '$invoice_no'
                        ");
                        mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE ref_no = '$invoice_no'");
                    }
                    $last_pv = getRecord("SELECT voucher_no FROM tbl_payment_vouchers ORDER BY id DESC LIMIT 1");
                    $pv_num = 1;
                    if ($last_pv && !empty($last_pv['voucher_no']) && preg_match('/PV[- ]?(\d+)/i', $last_pv['voucher_no'], $m)) {
                        $pv_num = (int)$m[1] + 1;
                    }
                    $pi_payment_voucher_no = 'PV-' . $pv_num;
                    $pv_sum_gold = 0.0;
                    $pv_sum_silver = 0.0;
                    foreach ($payments as $__psum) {
                        $__mw = purchase_invoice_metal_exchange_ledger_wts($conn, $__psum);
                        $pv_sum_gold += (float) $__mw['dg'];
                        $pv_sum_silver += (float) $__mw['ds'];
                    }
                    $pv_header_sql = "
                        INSERT INTO tbl_payment_vouchers (
                            voucher_no, customer_id, customer_name, ref_no, voucher_type,
                            voucher_date, total_amount, total_gold, total_silver,
                            comment, status, created_by, created_at
                        ) VALUES (
                            '$pi_payment_voucher_no',
                            " . ($supplier_id > 0 ? $supplier_id : 'NULL') . ",
                            '$supplier_name',
                            '$invoice_no',
                            'Payment Voucher',
                            '$invoice_date',
                            $total_payment_post,
                            " . (float) $pv_sum_gold . ',
                            ' . (float) $pv_sum_silver . ",
                            'Payment against Purchase Invoice',
                            'draft',
                            $user_id,
                            NOW()
                        )
                    ";
                    if (!mysqli_query($conn, $pv_header_sql)) {
                        throw new Exception('Payment voucher header failed: ' . mysqli_error($conn));
                    }
                    $pi_pv_id = (int)mysqli_insert_id($conn);
                    $pvi_ext = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_payment_voucher_items LIKE 'weight'");
                    $pvi_has_metal_cols = ($pvi_ext && mysqli_num_rows($pvi_ext) > 0);
                    if ($pvi_ext) {
                        mysqli_free_result($pvi_ext);
                    }
                    foreach ($payments as $__p) {
                        $__cur = (float)($__p['amount'] ?? 0);
                        $__prev = (float)($__p['previous_balance_amount'] ?? 0);
                        if ($__cur + $__prev <= 0) {
                            continue;
                        }
                        $__pt = esc($__p['payment_type'] ?? 'cash');
                        $__dep = esc($__p['deposit_into'] ?? 'Cash');
                        $__mx = purchase_invoice_metal_exchange_ledger_wts($conn, $__p);
                        $__gross_w = (float) $__mx['dg'] + (float) $__mx['ds'];
                        $__pure_w = (float) $__mx['dgp'];
                        if ($__pure_w <= 1e-8 && (float) $__mx['dg'] > 1e-8) {
                            $__pure_w = (float) $__mx['dg'];
                        }
                        $__mid = (int) ($__p['metal_exchange_metal_id'] ?? 0);
                        $__qty = (float) ($__p['quantity'] ?? 0);
                        $__pc = esc($__p['purity_carat'] ?? '');
                        if ($pvi_has_metal_cols && ($__gross_w > 1e-8 || $__mid > 0 || $__pure_w > 1e-8)) {
                            $wt_sql = (string) $__gross_w;
                            $pw_sql = (string) $__pure_w;
                            $mid_sql = $__mid > 0 ? (string) $__mid : 'NULL';
                            $qty_sql = $__qty > 1e-8 ? (string) $__qty : '0';
                            $pc_sql = ($__pc !== '') ? "'$__pc'" : 'NULL';
                            $pvi_sql = "
                            INSERT INTO tbl_payment_voucher_items (
                                voucher_id, payment_type, deposit_into, amount,
                                previous_balance_amount, weight, metal_id, quantity, purity_carat, purity_wt,
                                status, created_at
                            ) VALUES (
                                $pi_pv_id,
                                '$__pt',
                                '$__dep',
                                $__cur,
                                $__prev,
                                $wt_sql, $mid_sql, $qty_sql, $pc_sql, $pw_sql,
                                1,
                                NOW()
                            )
                        ";
                        } else {
                            $pvi_sql = "
                            INSERT INTO tbl_payment_voucher_items (
                                voucher_id, payment_type, deposit_into, amount,
                                previous_balance_amount, status, created_at
                            ) VALUES (
                                $pi_pv_id,
                                '$__pt',
                                '$__dep',
                                $__cur,
                                $__prev,
                                1,
                                NOW()
                            )
                        ";
                        }
                        if (!mysqli_query($conn, $pvi_sql)) {
                            throw new Exception('Payment voucher item failed: ' . mysqli_error($conn));
                        }
                    }
                    $txn_for_payment = $pi_payment_voucher_no;
                }
            } elseif ($pv_table_chk) {
                mysqli_free_result($pv_table_chk);
            }
            $txn_for_payment_sql = mysqli_real_escape_string($conn, $txn_for_payment);

            foreach ($payments as $payment) {
                $current_order_amount_pb = (float)($payment['amount'] ?? 0);
                $previous_balance_amount_pb = (float)($payment['previous_balance_amount'] ?? 0);
                $payment_amount_pb = $current_order_amount_pb + $previous_balance_amount_pb;
                $deposit_into_pb = esc($payment['deposit_into'] ?? 'Cash');

                if ($payment_amount_pb > 0 && $previous_balance_amount_pb > 0) {
                    $last_balance_record = getRecord("
                        SELECT balance_amount, balance_gold, balance_silver 
                        FROM tbl_customer_ledger 
                        WHERE customer_id = " . ($supplier_id > 0 ? $supplier_id : 0) . "
                        AND customer_name = '$supplier_name' 
                        AND status = 1 
                        $ledger_br_scope
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                    $current_running_balance_amount = (float)($last_balance_record['balance_amount'] ?? $new_balance_amount);
                    $current_running_balance_gold = (float)($last_balance_record['balance_gold'] ?? $new_balance_gold);
                    $current_running_balance_silver = (float)($last_balance_record['balance_silver'] ?? $new_balance_silver);

                    $current_running_balance_amount -= $previous_balance_amount_pb;

                    $prev_balance_payment_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, balance_gold, balance_silver,
                            description, against_ledger, against_invoice_no,
                            status, created_by, created_at
                        ) VALUES (
                            " . ($supplier_id > 0 ? $supplier_id : 0) . $ledger_branch_sql_val . ",
                            '$supplier_name',
                            'previous_balance_payment',
                            $invoice_id,
                            '$txn_for_payment_sql',
                            '$invoice_date',
                            0.00,
                            $previous_balance_amount_pb,
                            $current_running_balance_amount,
                            $current_running_balance_gold,
                            $current_running_balance_silver,
                            'Payment for Previous Balance - Purchase Invoice: $invoice_no',
                            '$deposit_into_pb',
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
            }

            $pi_money_payments = [];
            foreach ($payments as $payment) {
                if (purchase_invoice_payment_is_auto_pv_money($payment)) {
                    $pi_money_payments[] = $payment;
                }
            }

            $can_use_pv_ledger = ($pi_pv_id > 0 && $pi_payment_voucher_no !== null && $pi_payment_voucher_no !== '');
            if ($can_use_pv_ledger && !empty($pi_money_payments)) {
                purchase_invoice_post_auto_payment_voucher_ledger(
                    $conn,
                    $pi_pv_id,
                    $pi_payment_voucher_no,
                    $invoice_no,
                    $invoice_date,
                    $supplier_id,
                    $supplier_name,
                    $pi_money_payments,
                    $user_id,
                    $ref_no !== '' ? $ref_no : null,
                    $has_gold_pure_cols,
                    null,
                    $ledger_has_branch_col,
                    $ledger_branch_sql_val,
                    $ledger_br_scope
                );
            }

            foreach ($payments as $payment) {
                $current_order_amount = (float)($payment['amount'] ?? 0);
                $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
                $payment_amount = $current_order_amount + $previous_balance_amount;
                $payment_type_raw = strtolower(trim((string) ($payment['payment_type'] ?? 'cash')));
                $payment_type = esc($payment['payment_type'] ?? 'cash');
                $deposit_into = esc($payment['deposit_into'] ?? 'Cash');

                if ($can_use_pv_ledger && purchase_invoice_payment_is_auto_pv_money($payment) && $current_order_amount > 0) {
                    continue;
                }

                if ($payment_amount <= 0) {
                    continue;
                }

                if ($current_order_amount > 0) {
                        // Get the latest balance from database
                        $pay_bal_pure_sel = $has_gold_pure_cols ? ', balance_gold_pure' : '';
                        $last_balance_record = getRecord("
                            SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel
                            FROM tbl_customer_ledger 
                            WHERE customer_id = " . ($supplier_id > 0 ? $supplier_id : 0) . "
                            AND customer_name = '$supplier_name' 
                            AND status = 1 
                            $ledger_br_scope
                            ORDER BY transaction_date DESC, id DESC 
                            LIMIT 1
                        ");
                        $current_running_balance_amount = (float)($last_balance_record['balance_amount'] ?? $new_balance_amount);
                        $current_running_balance_gold = (float)($last_balance_record['balance_gold'] ?? $new_balance_gold);
                        $current_running_balance_silver = (float)($last_balance_record['balance_silver'] ?? $new_balance_silver);
                        $current_running_balance_gold_pure = $has_gold_pure_cols ? (float)($last_balance_record['balance_gold_pure'] ?? $new_balance_gold_pure) : 0.0;
                        
                        // Deduct current order payment
                        $current_running_balance_amount -= $current_order_amount;
                        
                        // Metal Exchange (and similar): post gross/pure weight to gold or silver columns for account ledger
                        $me_w = purchase_invoice_metal_exchange_ledger_wts($conn, $payment);
                        $pay_dg = (float) $me_w['dg'];
                        $pay_cg = (float) $me_w['cg'];
                        $pay_dgp = (float) $me_w['dgp'];
                        $pay_cgp = (float) $me_w['cgp'];
                        $pay_ds = (float) $me_w['ds'];
                        $pay_cs = (float) $me_w['cs'];
                        $new_run_bg = $current_running_balance_gold + $pay_dg - $pay_cg;
                        $new_run_bs = $current_running_balance_silver + $pay_ds - $pay_cs;
                        $new_run_bgp = $has_gold_pure_cols ? ($current_running_balance_gold_pure + $pay_dgp - $pay_cgp) : 0.0;
                        
                        // Get supplier balance for against_ledger display
                        $supplier_balance_for_display = $current_running_balance_amount;
                        $supplier_crdr = $supplier_balance_for_display >= 0 ? 'Dr' : 'Cr';
                        $supplier_against_ledger = $supplier_name . '(' . number_format(abs($supplier_balance_for_display), 2) . $supplier_crdr . ')';
                        // Party line stays Debit in DB (running balance = debit − credit). Scrap settlement: show Credit in account ledger UI — label Cr matches that display.
                        $is_scrap_payment_line = (strtolower(trim((string) $payment_type)) === 'scrap');
                        $pay_crdr = $is_scrap_payment_line ? 'Cr' : 'Dr';
                        if (auragold_payment_is_cheque_type($payment_type_raw)) {
                            $payment_against_ledger = auragold_cheque_payment_against_label($current_order_amount, 'payable');
                        } else {
                            $payment_against_ledger = $deposit_into . '(' . number_format($current_order_amount, 2) . $pay_crdr . ')';
                        }
                        
                        $pay_desc_suffix = ($pay_dg > 0.00001 || $pay_dgp > 0.00001 || $pay_ds > 0.00001) ? ' — Metal Exchange' : '';
                        $pay_desc = mysqli_real_escape_string($conn, 'Payment for Purchase Invoice: ' . $invoice_no . $pay_desc_suffix);
                        $gp_ins_cols = $has_gold_pure_cols ? 'debit_gold_pure, credit_gold_pure, ' : '';
                        $gp_ins_vals = $has_gold_pure_cols ? ("
                                " . $pay_dgp . ",
                                " . $pay_cgp . ",
") : '';
                        $bgp_ins_col = $has_gold_pure_cols ? ', balance_gold_pure' : '';
                        $bal_gold_vals = $has_gold_pure_cols ? $new_run_bg . ', ' . $new_run_bgp : (string) $new_run_bg;
                        
                        // Create supplier ledger entry - Payment Voucher: show on debit side (user requirement)
                        $payment_ledger_sql = "
                            INSERT INTO tbl_customer_ledger (
                                customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                                transaction_date, debit_amount, credit_amount,
                                debit_gold, credit_gold, $gp_ins_cols debit_silver, credit_silver,
                                balance_amount, balance_gold $bgp_ins_col, balance_silver,
                                description, against_ledger, against_invoice_no,
                                status, created_by, created_at
                            ) VALUES (
                                " . ($supplier_id > 0 ? $supplier_id : 0) . $ledger_branch_sql_val . ",
                                '$supplier_name',
                                'payment',
                                $invoice_id,
                                '$txn_for_payment_sql',
                                '$invoice_date',
                                $current_order_amount,
                                0.00,
                                $pay_dg,
                                $pay_cg,
                                $gp_ins_vals
                                $pay_ds,
                                $pay_cs,
                                $current_running_balance_amount,
                                $bal_gold_vals,
                                $new_run_bs,
                                '$pay_desc',
                                '" . mysqli_real_escape_string($conn, $payment_against_ledger) . "',
                                '$invoice_no',
                                1,
                                $user_id,
                                NOW()
                            )
                        ";
                        
                        if (!mysqli_query($conn, $payment_ledger_sql)) {
                            throw new Exception("Payment ledger entry failed: " . mysqli_error($conn));
                        }
                        
                        // Update running balance for next iteration
                        $new_balance_amount = $current_running_balance_amount;
                        $new_balance_gold = $new_run_bg;
                        $new_balance_silver = $new_run_bs;
                        if ($has_gold_pure_cols) {
                            $new_balance_gold_pure = $new_run_bgp;
                        }
                    }
                    
                    // Create Cash/Payment Account ledger entry for total payment (current + previous balance)
                    $total_payment_received = $current_order_amount + $previous_balance_amount;
                    if ($total_payment_received > 0 && !empty($deposit_into) && auragold_payment_should_post_deposit_ledger($payment_type_raw)) {
                        // Get Cash account balance
                        $cash_balance_record = getRecord("
                            SELECT balance_amount 
                            FROM tbl_customer_ledger 
                            WHERE customer_name = '$deposit_into' 
                            AND status = 1 
                            $ledger_br_scope
                            ORDER BY transaction_date DESC, id DESC 
                            LIMIT 1
                        ");
                        $cash_prev_balance = (float)($cash_balance_record['balance_amount'] ?? 0);
                        $cash_new_balance = $cash_prev_balance - $total_payment_received; // Cash decreases when we pay
                        
                        $bank_against_ledger = mysqli_real_escape_string($conn, accountledger_against_party_payment_label($supplier_name, $payment_type, $total_payment_received));
                        
                        $cash_ledger_sql = "
                            INSERT INTO tbl_customer_ledger (
                                customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                                transaction_date, debit_amount, credit_amount,
                                balance_amount, description, against_ledger, against_invoice_no,
                                status, created_by, created_at
                            ) VALUES (
                                0" . $ledger_branch_sql_val . ",
                                '$deposit_into',
                                'payment',
                                $invoice_id,
                                '$txn_for_payment_sql',
                                '$invoice_date',
                                0.00,
                                $total_payment_received,
                                $cash_new_balance,
                                'Payment to $supplier_name for Invoice: $invoice_no (Current: " . number_format($current_order_amount, 2) . ", Previous Balance: " . number_format($previous_balance_amount, 2) . ")',
                                '$bank_against_ledger',
                                '$invoice_no',
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
        
        // Update customer balance summary with final running balance from database
        // Get the final balance from the last ledger entry (after all payments)
        $final_balance_record = null;
        if ($supplier_id > 0) {
            $final_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_ledger 
                WHERE customer_id = $supplier_id 
                AND status = 1 
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
        } else if (!empty($supplier_name)) {
            $final_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_ledger 
                WHERE customer_name = '$supplier_name' 
                AND status = 1 
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
        }
        
        // Use final balance from database, or fallback to calculated balance
        $final_balance_amount = $final_balance_record ? (float)($final_balance_record['balance_amount'] ?? 0) : $new_balance_amount;
        $final_balance_gold = $final_balance_record ? (float)($final_balance_record['balance_gold'] ?? 0) : $new_balance_gold;
        $final_balance_silver = $final_balance_record ? (float)($final_balance_record['balance_silver'] ?? 0) : $new_balance_silver;
        
        // Check if balance record exists
        $existing_balance = null;
        if ($supplier_id > 0) {
            $existing_balance = getRecord("
                SELECT id FROM tbl_customer_balance 
                WHERE customer_id = $supplier_id 
                LIMIT 1
            ");
        } else if (!empty($supplier_name)) {
            $existing_balance = getRecord("
                SELECT id FROM tbl_customer_balance 
                WHERE customer_name = '$supplier_name' 
                LIMIT 1
            ");
        }
        
        if ($existing_balance) {
            // Update existing record
            if ($supplier_id > 0) {
                $balance_update_sql = "
                    UPDATE tbl_customer_balance SET
                        balance_amount = $final_balance_amount,
                        balance_gold = $final_balance_gold,
                        balance_silver = $final_balance_silver,
                        last_transaction_date = '$invoice_date',
                        last_updated = NOW()
                    WHERE customer_id = $supplier_id
                ";
            } else {
                $balance_update_sql = "
                    UPDATE tbl_customer_balance SET
                        balance_amount = $final_balance_amount,
                        balance_gold = $final_balance_gold,
                        balance_silver = $final_balance_silver,
                        last_transaction_date = '$invoice_date',
                        last_updated = NOW()
                    WHERE customer_name = '$supplier_name'
                ";
            }
        } else {
            // Insert new record
            $balance_update_sql = "
                INSERT INTO tbl_customer_balance (
                    customer_id, customer_name, balance_amount, balance_gold, balance_silver,
                    last_transaction_date, last_updated
                ) VALUES (
                    " . ($supplier_id > 0 ? $supplier_id : 0) . ",
                    '$supplier_name',
                    $final_balance_amount,
                    $final_balance_gold,
                    $final_balance_silver,
                    '$invoice_date',
                    NOW()
                )
            ";
        }
        
        if (!mysqli_query($conn, $balance_update_sql)) {
            throw new Exception("Balance update failed: " . mysqli_error($conn));
        }
    }
    
    // If Payment Mode = 'Cash': legacy Payment Voucher row only (Purchase Account debit = full grand_total is posted with the invoice above).
    $net_amount = 0;
    $has_cash_payment = false;
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $p) {
            $pt = strtolower(trim($p['payment_type'] ?? ''));
            if ($pt === 'cash') {
                $net_amount += (float)($p['amount'] ?? 0);
                $has_cash_payment = true;
            }
        }
    }
    if ($has_cash_payment && $net_amount > 0) {
        // On update: remove previous auto-created payment voucher (only if not already created with all payments above); drop legacy cash-only Purchase Account lines (type "Purchase Invoice")
        if ($is_update) {
            if (!$pi_payment_voucher_no) {
                mysqli_query($conn, "
                    DELETE pvi FROM tbl_payment_voucher_items pvi
                    INNER JOIN tbl_payment_vouchers pv ON pvi.voucher_id = pv.id
                    WHERE pv.ref_no = '$invoice_no'
                ");
                mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE ref_no = '$invoice_no'");
            }
            mysqli_query($conn, "
                DELETE FROM tbl_customer_ledger 
                WHERE transaction_no = '$invoice_no' 
                AND customer_name = 'Purchase Account' AND transaction_type = 'Purchase Invoice'
                AND status = 1
            ");
        }
        
        // Store record in tbl_payment_vouchers (legacy path: cash-only PI save without upfront voucher above)
        if (!$pi_payment_voucher_no) {
            $pv_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_table && mysqli_num_rows($pv_table) > 0) {
                mysqli_free_result($pv_table);
                $last_pv = getRecord("SELECT voucher_no FROM tbl_payment_vouchers ORDER BY id DESC LIMIT 1");
                $pv_num = 1;
                if ($last_pv && !empty($last_pv['voucher_no']) && preg_match('/PV[- ]?(\d+)/i', $last_pv['voucher_no'], $m)) {
                    $pv_num = (int)$m[1] + 1;
                }
                $pv_no = 'PV-' . $pv_num;
                $pv_date = date('Y-m-d');
                
                $pv_sql = "
                    INSERT INTO tbl_payment_vouchers (
                        voucher_no, customer_id, customer_name, ref_no, voucher_type,
                        voucher_date, total_amount, total_gold, total_silver,
                        comment, status, created_by, created_at
                    ) VALUES (
                        '$pv_no',
                        " . ($supplier_id > 0 ? $supplier_id : "NULL") . ",
                        '$supplier_name',
                        '$invoice_no',
                        'Payment Voucher',
                        '$pv_date',
                        $net_amount,
                        0.000,
                        0.000,
                        'Cash paid against Purchase Invoice',
                        'draft',
                        $user_id,
                        NOW()
                    )
                ";
                if (!mysqli_query($conn, $pv_sql)) {
                    throw new Exception("Payment voucher insert failed: " . mysqli_error($conn));
                }
                $pv_id = mysqli_insert_id($conn);
                
                $pvi_sql = "
                    INSERT INTO tbl_payment_voucher_items (
                        voucher_id, payment_type, deposit_into, amount,
                        previous_balance_amount, status, created_at
                    ) VALUES (
                        $pv_id, 'Cash', 'Cash', $net_amount, 0.00, 1, NOW()
                    )
                ";
                if (!mysqli_query($conn, $pvi_sql)) {
                    throw new Exception("Payment voucher item insert failed: " . mysqli_error($conn));
                }
            } elseif ($pv_table) {
                mysqli_free_result($pv_table);
            }
        }
    }
    
    // Hedging: create a sale fixing entry (against this purchase invoice); create even when making amount is 0 so "Fixing of PI-X" appears
    if ($fixing_type === 'Hedging') {
        $sf_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_fixing_direct'");
        if ($sf_table && mysqli_num_rows($sf_table) > 0) {
            $sf_columns = [];
            $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_fixing_direct");
            if ($cols) {
                while ($c = mysqli_fetch_assoc($cols)) {
                    $sf_columns[strtolower($c['Field'])] = $c['Field'];
                }
            }
            // Next ref/no: use ref_no, invoice_no, or order_no whichever exists (support SF- or SFD- style)
            $ref_col = isset($sf_columns['ref_no']) ? 'ref_no' : (isset($sf_columns['invoice_no']) ? 'invoice_no' : (isset($sf_columns['order_no']) ? 'order_no' : null));
            if ($ref_col === null) {
                $ref_col = 'ref_no'; // fallback; insert will fail if ref_no missing
            }
            $last_sf = getRecord("SELECT `" . $sf_columns[$ref_col] . "` FROM tbl_sale_fixing_direct ORDER BY id DESC LIMIT 1");
            $sf_num = 1;
            $last_no = $last_sf ? ($last_sf[$sf_columns[$ref_col]] ?? '') : '';
            if (!empty($last_no) && preg_match('/SF-(\d+)/', $last_no, $m)) {
                $sf_num = (int)$m[1] + 1;
            } elseif (!empty($last_no) && preg_match('/SFD-(\d+)/', $last_no, $m)) {
                $sf_num = (int)$m[1] + 1;
            }
            $against_of_sf = 'Fixing of ' . $invoice_no;
            $sf_supplier = mysqli_real_escape_string($conn, $supplier_name);
            $sf_invoice_date = mysqli_real_escape_string($conn, $invoice_date);
            $sf_fixing_type = mysqli_real_escape_string($conn, 'Hedging');
            // Sale fixing amount = metal cost only (total minus making)
            $sf_amt = (float)$grand_total - (float)$making_amount_for_sale_fixing;
            if ($sf_amt < 0) $sf_amt = 0;

            $existing_sf = getRecord("SELECT id FROM tbl_sale_fixing_direct WHERE against_of = '" . mysqli_real_escape_string($conn, $against_of_sf) . "' LIMIT 1");
            if ($existing_sf && !empty($existing_sf['id'])) {
                // One sale fixing per purchase invoice: UPDATE existing row
                $sf_id = (int)$existing_sf['id'];
                $upd_parts = [];
                if (isset($sf_columns['customer_id']) && $supplier_id > 0) $upd_parts[] = "customer_id = " . (string)$supplier_id;
                if (isset($sf_columns['customer_name'])) $upd_parts[] = "customer_name = '$sf_supplier'";
                if (isset($sf_columns['invoice_date'])) $upd_parts[] = "invoice_date = '$sf_invoice_date'";
                if (isset($sf_columns['fixing_date'])) $upd_parts[] = "fixing_date = '$sf_invoice_date'";
                if (isset($sf_columns['subtotal'])) $upd_parts[] = "subtotal = $sf_amt";
                if (isset($sf_columns['net_total'])) $upd_parts[] = "net_total = $sf_amt";
                if (isset($sf_columns['grand_total'])) $upd_parts[] = "grand_total = $sf_amt";
                if (isset($sf_columns['total_amount'])) $upd_parts[] = "total_amount = $sf_amt";
                if (count($upd_parts) > 0) {
                    $sf_sql = "UPDATE tbl_sale_fixing_direct SET " . implode(', ', $upd_parts) . " WHERE id = $sf_id";
                    if (!mysqli_query($conn, $sf_sql)) {
                        throw new Exception("Sale fixing update failed: " . mysqli_error($conn));
                    }
                }
            } else {
                // No existing sale fixing for this PI: INSERT one
                $sf_ref_no = 'SF-' . $sf_num;
                $ins_cols = [];
                $ins_vals = [];
                if (isset($sf_columns['ref_no'])) { $ins_cols[] = 'ref_no'; $ins_vals[] = "'$sf_ref_no'"; }
                if (isset($sf_columns['invoice_no'])) { $ins_cols[] = 'invoice_no'; $ins_vals[] = "'$sf_ref_no'"; }
                if (isset($sf_columns['order_no'])) { $ins_cols[] = 'order_no'; $ins_vals[] = "'$sf_ref_no'"; }
                if (isset($sf_columns['customer_id']) && $supplier_id > 0) { $ins_cols[] = 'customer_id'; $ins_vals[] = (string)$supplier_id; }
                if (isset($sf_columns['customer_name'])) { $ins_cols[] = 'customer_name'; $ins_vals[] = "'$sf_supplier'"; }
                if (isset($sf_columns['against_of'])) { $ins_cols[] = 'against_of'; $ins_vals[] = "'" . mysqli_real_escape_string($conn, $against_of_sf) . "'"; }
                if (isset($sf_columns['invoice_date'])) { $ins_cols[] = 'invoice_date'; $ins_vals[] = "'$sf_invoice_date'"; }
                if (isset($sf_columns['fixing_date'])) { $ins_cols[] = 'fixing_date'; $ins_vals[] = "'$sf_invoice_date'"; }
                if (isset($sf_columns['fixing_type'])) { $ins_cols[] = 'fixing_type'; $ins_vals[] = "'$sf_fixing_type'"; }
                if (isset($sf_columns['subtotal'])) { $ins_cols[] = 'subtotal'; $ins_vals[] = $sf_amt; }
                if (isset($sf_columns['net_total'])) { $ins_cols[] = 'net_total'; $ins_vals[] = $sf_amt; }
                if (isset($sf_columns['grand_total'])) { $ins_cols[] = 'grand_total'; $ins_vals[] = $sf_amt; }
                if (isset($sf_columns['total_amount'])) { $ins_cols[] = 'total_amount'; $ins_vals[] = $sf_amt; }
                if (isset($sf_columns['paid_amt'])) { $ins_cols[] = 'paid_amt'; $ins_vals[] = '0.00'; }
                if (isset($sf_columns['balance_amt'])) { $ins_cols[] = 'balance_amt'; $ins_vals[] = '0.00'; }
                if (isset($sf_columns['advance_payment'])) { $ins_cols[] = 'advance_payment'; $ins_vals[] = '0.00'; }
                if (isset($sf_columns['status'])) { $ins_cols[] = 'status'; $ins_vals[] = "'draft'"; }
                if (isset($sf_columns['created_by'])) { $ins_cols[] = 'created_by'; $ins_vals[] = (string)$user_id; }
                if (isset($sf_columns['created_at'])) { $ins_cols[] = 'created_at'; $ins_vals[] = 'NOW()'; }
                if (count($ins_cols) > 0) {
                    $sf_sql = "INSERT INTO tbl_sale_fixing_direct (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $ins_vals) . ")";
                    if (!mysqli_query($conn, $sf_sql)) {
                        throw new Exception("Sale fixing (metal cost) entry failed: " . mysqli_error($conn));
                    }
                }
            }
        }
    }
    
    if ((int) $invoice_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'purchase_invoice', (int) $invoice_id, $invoice_no, $invoice_date);
    }

    require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';
    if (function_exists('auragold_sync_voucher_cheque_entries')) {
        auragold_sync_voucher_cheque_entries($conn, [
            'voucher_no' => $invoice_no,
            'voucher_type' => 'Purchase Invoice',
            'voucher_date' => $invoice_date,
            'account_ledger' => $supplier_name,
            'transaction_id' => (int) $invoice_id,
            'ledger_transaction_no' => isset($txn_for_payment) ? (string) $txn_for_payment : $invoice_no,
            'user_id' => (int) $user_id,
            'payments' => isset($payments) && is_array($payments) ? $payments : [],
            'pdc_direction' => 'payable',
        ]);
    }

    mysqli_commit($conn);

    // Create linked Old Jewellery Scrap (OJB-*) when this invoice has scrap payment — Old Jewellery list uses OJB invoice no., not PI
    $sync_ojb = __DIR__ . '/../includes/sync_purchase_scrap_to_ojb.php';
    if (is_file($sync_ojb)) {
        require_once $sync_ojb;
        if (function_exists('syncPurchaseScrapToOjb')) {
            syncPurchaseScrapToOjb($conn, (int) $invoice_id);
        }
    }

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Purchase Invoice',
        'verb' => $is_update ? 'updated' : 'created',
        'number' => $invoice_no,
        'party' => $supplier_name,
        'doc_date' => $invoice_date,
        'due_date' => $due_date,
        'ref_id' => (int) $invoice_id,
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Purchase invoice saved successfully',
        'invoice_id' => $invoice_id,
        'invoice_no' => $invoice_no,
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

