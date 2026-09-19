<?php
/**
 * Post-save ledger, payment voucher, and supplier balance for purchase return.
 * Mirrors save-sale-return.php accounting with debits/credits reversed for purchase context.
 *
 * Expected variables in scope: $conn, $user_id, $return_id, $return_no, $return_date, $ref_no,
 * $supplier_id, $supplier_name, $grand_total, $net_total, $items, $payments, $metal_amt_post,
 * $old_return_no_snapshot, $is_update
 */

$__pr_sup_ok = ($supplier_id > 0 || trim((string)$supplier_name) !== '');
if (!$__pr_sup_ok) {
    return;
}
$items = is_array($items) ? $items : [];
if (count($items) === 0 && (float)$grand_total <= 0.00001) {
    return;
}

$has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
$has_against = ($has_against && mysqli_num_rows($has_against) > 0);
$gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
$has_gold_pure_cols = ($gpc && mysqli_num_rows($gpc) > 0);

if (!isset($ledger_br_scope)) {
    $ledger_br_scope = '';
}
if (!isset($ledger_branch_sql_col)) {
    $ledger_branch_sql_col = '';
}
if (!isset($ledger_branch_sql_val)) {
    $ledger_branch_sql_val = '';
}

mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'purchase_return' AND transaction_id = " . (int)$return_id . " AND status = 1");

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

$total_purchase_amt = 0.0;
$total_making_amt = 0.0;
$total_tax_amt = 0.0;
foreach ($items as $item) {
    $metal_val = (float)($item['metal_value'] ?? 0);
    $diamond_amt = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
    $stone_amt = (float)($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
    $making_amt = (float)($item['making_amount'] ?? $item['making'] ?? 0);
    $tax_amt = (float)($item['tax'] ?? $item['tax_amount'] ?? 0);
    $amount = (float)($item['amount'] ?? 0);
    $item_purchase = $metal_val + $diamond_amt + $stone_amt;
    if ($item_purchase <= 0 && $amount > 0) {
        $item_purchase = max(0, $amount - $making_amt);
    }
    $total_purchase_amt += $item_purchase;
    $total_making_amt += $making_amt;
    $total_tax_amt += $tax_amt;
}
if ($total_purchase_amt <= 0) {
    $total_purchase_amt = (float)$metal_amt_post;
}
if ($total_tax_amt <= 0) {
    $total_tax_amt = max(0, (float)$grand_total - (float)$net_total);
}
if ($total_making_amt <= 0 && (float)$grand_total > 0) {
    $total_making_amt = max(0, (float)$net_total - $total_purchase_amt - $total_tax_amt);
}

$get_ledger_balance = function ($ledger_name) use ($conn, $ledger_br_scope) {
    $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = '" . mysqli_real_escape_string($conn, $ledger_name) . "' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
    return (float)($r['balance_amount'] ?? 0);
};

$against_cols = $has_against ? ', against_ledger, against_invoice_no' : '';
$return_no_sql = mysqli_real_escape_string($conn, (string)$return_no);
$return_date_sql = mysqli_real_escape_string($conn, (string)$return_date);
$supplier_name_sql = mysqli_real_escape_string($conn, (string)$supplier_name);
$ref_esc = $ref_no ? "'" . mysqli_real_escape_string($conn, (string)$ref_no) . "'" : 'NULL';

if ($total_purchase_amt > 0.00001) {
    $prev_pur = $get_ledger_balance('Purchase Account');
    $new_pur_bal = $prev_pur - $total_purchase_amt;
    $against_vals = $has_against ? ", '" . $supplier_name_sql . "', '$return_no_sql'" : '';
    $pur_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Purchase Account', 'purchase_return', " . (int)$return_id . ", '$return_no_sql', '$return_date_sql', 0.00, $total_purchase_amt, $new_pur_bal, 'Purchase Return (reversal): $return_no_sql', $ref_esc, 1, " . (int)$user_id . ", NOW() $against_vals)";
    if (!mysqli_query($conn, $pur_sql)) {
        throw new Exception('Purchase Account ledger (purchase return) failed: ' . mysqli_error($conn));
    }
}
if ($total_making_amt > 0.00001) {
    $prev_mk = $get_ledger_balance('Making Purchase Account');
    $new_mk_bal = $prev_mk - $total_making_amt;
    $against_vals = $has_against ? ", '" . $supplier_name_sql . "', '$return_no_sql'" : '';
    $mk_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Making Purchase Account', 'purchase_return', " . (int)$return_id . ", '$return_no_sql', '$return_date_sql', 0.00, $total_making_amt, $new_mk_bal, 'Making charges reversal - Purchase Return: $return_no_sql', $ref_esc, 1, " . (int)$user_id . ", NOW() $against_vals)";
    if (!mysqli_query($conn, $mk_sql)) {
        throw new Exception('Making Purchase Account ledger (purchase return) failed: ' . mysqli_error($conn));
    }
}
if ($total_tax_amt > 0.00001) {
    $prev_tax = $get_ledger_balance('Tax Ledger');
    $new_tax_bal = $prev_tax - $total_tax_amt;
    $against_vals = $has_against ? ", '" . $supplier_name_sql . "', '$return_no_sql'" : '';
    $tax_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Tax Ledger', 'purchase_return', " . (int)$return_id . ", '$return_no_sql', '$return_date_sql', 0.00, $total_tax_amt, $new_tax_bal, 'GST/Tax reversal - Purchase Return: $return_no_sql', $ref_esc, 1, " . (int)$user_id . ", NOW() $against_vals)";
    if (!mysqli_query($conn, $tax_sql)) {
        throw new Exception('Tax Ledger (purchase return) failed: ' . mysqli_error($conn));
    }
}

if ((float)$grand_total > 0.00001) {
    $prev_balance_select = 'balance_amount, balance_gold, balance_silver';
    if ($has_gold_pure_cols) {
        $prev_balance_select .= ', balance_gold_pure';
    }
    $previous_balance_record = null;
    if ($supplier_id > 0) {
        $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_ledger WHERE customer_id = " . (int)$supplier_id . " AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
    }
    if (!$previous_balance_record && $supplier_name !== '') {
        $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_ledger WHERE customer_name = '$supplier_name_sql' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
    }
    $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? 0);
    $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? 0);
    $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? 0);
    $prev_balance_gold_pure = $has_gold_pure_cols ? (float)($previous_balance_record['balance_gold_pure'] ?? 0) : 0.0;

    $new_balance_amount = $prev_balance_amount - (float)$grand_total;
    $against_vals_s = $has_against ? ", 'Purchase Return', '$return_no_sql'" : '';
    $ledger_gold_pure_cols = '';
    $metal_vals = '0.000, 0.000';
    $balance_metal_vals = (string)$prev_balance_gold . ', ' . (string)$prev_balance_silver;
    if ($has_gold_pure_cols) {
        $ledger_gold_pure_cols = 'debit_gold_pure, credit_gold_pure, ';
        $metal_vals = '0.000, 0.000, 0.000, 0.000';
        $balance_metal_vals = (string)$prev_balance_gold . ', ' . (string)$prev_balance_gold_pure . ', ' . (string)$prev_balance_silver;
    }
    $metal_vals .= ', 0.000, 0.000';
    $gr = (float)$grand_total;
    $sup_ledger_sql = "
        INSERT INTO tbl_customer_ledger (
            customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount,
            debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
            balance_amount, balance_gold" . ($has_gold_pure_cols ? ', balance_gold_pure' : '') . ", balance_silver,
            description, reference_no, status, created_by, created_at
            $against_cols
        ) VALUES (
            " . ($supplier_id > 0 ? (int)$supplier_id : 0) . "$ledger_branch_sql_val,
            '$supplier_name_sql',
            'purchase_return',
            " . (int)$return_id . ",
            '$return_no_sql',
            '$return_date_sql',
            $gr,
            0.00,
            $metal_vals,
            $new_balance_amount,
            $balance_metal_vals,
            'Purchase Return: $return_no_sql',
            $ref_esc,
            1,
            " . (int)$user_id . ",
            NOW()
            $against_vals_s
        )
    ";
    if (!mysqli_query($conn, $sup_ledger_sql)) {
        throw new Exception('Supplier ledger (purchase return) failed: ' . mysqli_error($conn));
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
            " . ($supplier_id > 0 ? (int)$supplier_id : 'NULL') . ",
            '$supplier_name_sql',
            '$return_no_sql',
            'Payment Voucher',
            '$return_date_sql',
            $pv_total_money,
            0,
            0,
            'Purchase return settlement / refund from supplier',
            'draft',
            " . (int)$user_id . ",
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
    if ($supplier_id > 0) {
        $last_balance = getRecord("SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel FROM tbl_customer_ledger WHERE customer_id = " . (int)$supplier_id . " AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
    }
    if (!$last_balance && $supplier_name !== '') {
        $last_balance = getRecord("SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel FROM tbl_customer_ledger WHERE customer_name = '$supplier_name_sql' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
    }
    $prev_amt = (float)($last_balance['balance_amount'] ?? 0);
    $prev_gold = (float)($last_balance['balance_gold'] ?? 0);
    $prev_silver = (float)($last_balance['balance_silver'] ?? 0);
    $prev_gold_pure = $has_gold_pure_cols ? (float)($last_balance['balance_gold_pure'] ?? 0) : 0.0;
    $new_balance_amt = $prev_amt - $pv_total_money;

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
            $party_against_parts[] = $dep_esc2 . '(' . number_format($line_amt, 2) . 'Dr)';
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

    $desc_pv = mysqli_real_escape_string($conn, 'Payment Voucher: ' . $pi_payment_voucher_no . ' (Purchase Return ' . $return_no . ')');
    $ledger_sup_id = $supplier_id > 0 ? (int)$supplier_id : 0;
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
                $ledger_sup_id$ledger_branch_sql_val,
                '$supplier_name_sql',
                'payment_voucher',
                $pi_pv_id,
                '$pv_esc',
                '$return_date_sql',
                0,
                $pv_total_money,
                0, 0, 0, 0, 0, 0,
                $new_balance_amt,
                $prev_gold,
                $prev_gold_pure,
                $prev_silver,
                '$desc_pv',
                $ref_esc,
                1,
                " . (int)$user_id . ",
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
                $ledger_sup_id$ledger_branch_sql_val,
                '$supplier_name_sql',
                'payment_voucher',
                $pi_pv_id,
                '$pv_esc',
                '$return_date_sql',
                0,
                $pv_total_money,
                0, 0, 0, 0,
                $new_balance_amt,
                $prev_gold,
                $prev_silver,
                '$desc_pv',
                $ref_esc,
                1,
                " . (int)$user_id . ",
                NOW()
                $against_vals_pv
            )
        ";
    }
    if (!mysqli_query($conn, $party_sql)) {
        throw new Exception('Payment voucher supplier ledger failed: ' . mysqli_error($conn));
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
        $cash_new_balance = $cash_prev_balance + $tot;
        $acc_prev_g = (float)($cash_balance_record['balance_gold'] ?? 0);
        $acc_prev_s = (float)($cash_balance_record['balance_silver'] ?? 0);
        $acc_prev_gp = $has_gold_pure_cols ? (float)($cash_balance_record['balance_gold_pure'] ?? 0) : 0.0;
        $cash_desc_esc = mysqli_real_escape_string($conn, 'Receipt from ' . $supplier_name . ' (Payment Voucher ' . $pi_payment_voucher_no . ')');
        $ca_line_esc = mysqli_real_escape_string($conn, $supplier_name . '(' . number_format($tot, 2) . ')');
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
                    $tot,
                    0,
                    $cash_new_balance,
                    $acc_prev_g,
                    $acc_prev_s,
                    '$cash_desc_esc',
                    $ref_esc,
                    1,
                    " . (int)$user_id . ",
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
                    $tot,
                    0,
                    $cash_new_balance,
                    $acc_prev_g,
                    $acc_prev_s,
                    '$cash_desc_esc',
                    $ref_esc,
                    1,
                    " . (int)$user_id . ",
                    NOW()
                )
            ";
        }
        if (!mysqli_query($conn, $cash_sql)) {
            throw new Exception('Payment voucher cash/bank ledger failed: ' . mysqli_error($conn));
        }
    }
}

$final_balance_record = null;
if ($supplier_id > 0) {
    $final_balance_record = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_id = " . (int)$supplier_id . " AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
} elseif (!empty($supplier_name)) {
    $final_balance_record = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_name = '$supplier_name_sql' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
}
$final_balance_amount = $final_balance_record ? (float)($final_balance_record['balance_amount'] ?? 0) : 0;
$final_balance_gold = $final_balance_record ? (float)($final_balance_record['balance_gold'] ?? 0) : 0;
$final_balance_silver = $final_balance_record ? (float)($final_balance_record['balance_silver'] ?? 0) : 0;

$existing_balance = null;
if ($supplier_id > 0) {
    $existing_balance = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_id = " . (int)$supplier_id . " LIMIT 1");
} elseif (!empty($supplier_name)) {
    $existing_balance = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_name = '$supplier_name_sql' LIMIT 1");
}

if ($existing_balance) {
    if ($supplier_id > 0) {
        $balance_update_sql = "
            UPDATE tbl_customer_balance SET
                balance_amount = $final_balance_amount,
                balance_gold = $final_balance_gold,
                balance_silver = $final_balance_silver,
                last_transaction_date = '$return_date_sql',
                last_updated = NOW()
            WHERE customer_id = " . (int)$supplier_id . "
        ";
    } else {
        $balance_update_sql = "
            UPDATE tbl_customer_balance SET
                balance_amount = $final_balance_amount,
                balance_gold = $final_balance_gold,
                balance_silver = $final_balance_silver,
                last_transaction_date = '$return_date_sql',
                last_updated = NOW()
            WHERE customer_name = '$supplier_name_sql'
        ";
    }
} else {
    $balance_update_sql = "
        INSERT INTO tbl_customer_balance (
            customer_id, customer_name, balance_amount, balance_gold, balance_silver,
            last_transaction_date, last_updated
        ) VALUES (
            " . ($supplier_id > 0 ? (int)$supplier_id : 0) . ",
            '$supplier_name_sql',
            $final_balance_amount,
            $final_balance_gold,
            $final_balance_silver,
            '$return_date_sql',
            NOW()
        )
    ";
}
if (!mysqli_query($conn, $balance_update_sql)) {
    throw new Exception('Balance update failed: ' . mysqli_error($conn));
}
