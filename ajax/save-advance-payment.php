<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';

if (!function_exists('advance_payment_validate_metal_exchange_items')) {
    /**
     * @param array<int, array<string, mixed>> $items
     */
    function advance_payment_validate_metal_exchange_items($conn, array $items): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $__mrg = auragold_payment_merge_stored_details($item);
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mrg)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mrg);
        }
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $has_ap_branch = auragold_ensure_table_branch_id_column($conn, 'tbl_advance_payments');
    $hdr_branch    = auragold_transaction_header_branch_id();
    $eff_branch    = auragold_effective_branch_id();
    $ap_dup_sql    = ($has_ap_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';

    $voucher_id = isset($_POST['voucher_id']) ? (int)$_POST['voucher_id'] : 0;
    $advance_voucher_existed = ($voucher_id > 0);
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
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_advance_payments') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
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
    $metal_exchange_barcodes_out = [];

    // Validation
    if (empty($customer_name)) {
        throw new Exception('Customer name is required');
    }

    if (empty($voucher_no)) {
        throw new Exception('Voucher number is required');
    }

    $ap_row_branch_id = 0;
    if ($voucher_id > 0 && $has_ap_branch) {
        $ap_br = getRecord("SELECT branch_id FROM tbl_advance_payments WHERE id = $voucher_id LIMIT 1");
        $ap_row_branch_id = (int) ($ap_br['branch_id'] ?? 0);
        auragold_branch_require_document_access($conn, 'tbl_advance_payments', $voucher_id);
    }

    // Check if voucher number already exists (for new vouchers)
    if ($voucher_id == 0) {
        $existing = getRecord("SELECT id FROM tbl_advance_payments WHERE voucher_no = '$voucher_no'$ap_dup_sql" . auragold_doc_series_active_sql($conn, 'tbl_advance_payments'));
        if ($existing) {
            throw new Exception('Voucher number already exists');
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_advance_payments', 'voucher_no']], $voucher_no);
        }
    }

    if ($voucher_id > 0) {
        // Update existing voucher
        $update_query = "
            UPDATE tbl_advance_payments SET
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
                " . ($has_ap_branch && $eff_branch > 0 && $ap_row_branch_id === 0 ? ', branch_id = ' . (int) $eff_branch : '') . ",
                updated_at = NOW()
            WHERE id = $voucher_id
        ";

        if (!mysqli_query($conn, $update_query)) {
            throw new Exception('Error updating voucher: ' . mysqli_error($conn));
        }

        // Delete existing items
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=ap|hid=" . (int) $voucher_id . "|%'");
        mysqli_query($conn, "DELETE FROM tbl_advance_payment_items WHERE voucher_id = $voucher_id");
    } else {
        // Insert new voucher
        $insert_query = "
            INSERT INTO tbl_advance_payments (
                voucher_no, customer_id, customer_name, ref_no, receipt_no, voucher_type, against,
                sales_person, against_of, currency$fx_insert_col_sql, voucher_date, due_date, layaways_id,
                fixing_type, previous_balance, previous_gold, previous_silver,
                total_amount, total_gold, total_silver, comment, status, created_by,
                " . ($has_ap_branch ? 'branch_id, ' : '') . "created_at
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
                " . ($has_ap_branch ? ((int) $hdr_branch > 0 ? (int) $hdr_branch : 'NULL') . ', ' : '') . "
                NOW()
            )
        ";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception('Error inserting voucher: ' . mysqli_error($conn));
        }

        $voucher_id = mysqli_insert_id($conn);
    }

    if (is_array($items)) {
        advance_payment_validate_metal_exchange_items($conn, $items);
    }
    $___ap_me_has_ref = auragold_metal_exchange_document_init($conn, $advance_voucher_existed, (int) $voucher_id, 'advance_payment_metal_exchange');

    // Insert advance payment items
    if (is_array($items) && count($items) > 0) {
        foreach ($items as $pay_seq => $item) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $item)) {
                continue;
            }
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
            $purity_wt = isset($item['purity_wt']) ? (float)$item['purity_wt'] : 0.000;

            $amount = isset($item['amount']) ? (float)$item['amount'] : 0.00;
            $previous_balance_amount = isset($item['previous_balance_amount']) ? (float)$item['previous_balance_amount'] : 0.00;
            
            $item_query = "
                INSERT INTO tbl_advance_payment_items (
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
            $ap_line_id = (int) mysqli_insert_id($conn);
            $ap_dt = trim((string) ($_POST['voucher_date'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ap_dt)) {
                $ap_dt = date('Y-m-d');
            }
            require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
            $aw = $purity_wt > 0 ? $purity_wt : $weight;
            auragold_stock_history_audit_for_document_barcode_line($conn, 'Advance Payment', trim((string) ($_POST['voucher_no'] ?? '')), $ap_dt, 'AP', (int) $voucher_id, $ap_line_id, 'ap', [
                'barcode' => trim((string) ($item['barcode_no'] ?? $item['barcode'] ?? '')),
                'product_id' => $product_id,
                'metal_id' => $metal_id,
                'quantity' => $quantity,
                'gross_weight' => $weight,
                'less_weight' => 0,
                'net_weight' => $aw,
                'purity_weight' => $purity_wt,
                'pure_weight' => $aw,
                'final_weight' => $aw,
                'purity' => 0,
                'rate' => 0,
                'amount' => $amount,
                'tax_amount' => 0,
                'net_amount' => $amount,
                'net_amt_with_tax' => $amount,
                'category' => trim((string) ($item['diamond_category'] ?? '')),
            ]);

            $ap_plain_no = trim((string) $voucher_no);
            $ap_me_pm = auragold_payment_merge_stored_details($item);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'advance_payment_metal_exchange',
                (int) $voucher_id,
                $ap_plain_no,
                substr(trim((string) $voucher_date), 0, 10),
                $ap_me_pm,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___ap_me_has_ref,
                'Advance Payment — Metal Exchange',
                'ap_me',
                'AP-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }

    $ap_branch_for_ledger = 0;
    if ($has_ap_branch) {
        $apb = getRecord("SELECT branch_id FROM tbl_advance_payments WHERE id = $voucher_id LIMIT 1");
        $ap_branch_for_ledger = (int) ($apb['branch_id'] ?? 0);
    }
    if ($ap_branch_for_ledger <= 0 && $eff_branch > 0) {
        $ap_branch_for_ledger = (int) $eff_branch;
    }
    auragold_ensure_customer_ledger_branch_column($conn);
    $ledger_has_branch_col = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = ($ledger_has_branch_col && $ap_branch_for_ledger > 0)
        ? ', ' . (int) $ap_branch_for_ledger
        : ($ledger_has_branch_col ? ', NULL' : '');
    $ledger_br_scope = auragold_customer_ledger_branch_scope_sql($conn, $ap_branch_for_ledger);

    // Update customer ledger - create ledger entry for advance payment
    // Advance payment = credit to customer (reduces their outstanding balance)
    if ($total_amount > 0 || $total_gold > 0 || $total_silver > 0) {
        // Get the current balance from the last ledger entry
        $last_ledger = null;
        if ($customer_id > 0) {
            $last_ledger = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_ledger 
                WHERE customer_id = $customer_id AND status = 1 
                $ledger_br_scope
                ORDER BY id DESC LIMIT 1
            ");
        }
        if (!$last_ledger && !empty($customer_name)) {
            $last_ledger = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_ledger 
                WHERE customer_name = '$customer_name' AND status = 1 
                $ledger_br_scope
                ORDER BY id DESC LIMIT 1
            ");
        }
        
        $current_balance_amount = (float)($last_ledger['balance_amount'] ?? 0);
        $current_balance_gold = (float)($last_ledger['balance_gold'] ?? 0);
        $current_balance_silver = (float)($last_ledger['balance_silver'] ?? 0);
        
        // Advance payment is a credit (customer pays us, so their balance decreases/becomes more positive)
        // If customer owes us -2000, and pays 3000 advance, new balance = -2000 + 3000 = +1000
        $new_balance_amount = $current_balance_amount + $total_amount;
        $new_balance_gold = $current_balance_gold + $total_gold;
        $new_balance_silver = $current_balance_silver + $total_silver;
        
        // Check if ledger entry already exists for this voucher (for updates)
        $existing_ledger = getRecord("
            SELECT id FROM tbl_customer_ledger 
            WHERE transaction_type = 'advance_payment' 
            AND transaction_id = $voucher_id 
            AND status = 1
        ");
        
        if ($existing_ledger) {
            // Update existing ledger entry
            // First, we need to recalculate: remove old entry effect, apply new
            $old_ledger_entry = getRecord("
                SELECT credit_amount, credit_gold, credit_silver 
                FROM tbl_customer_ledger 
                WHERE id = " . (int)$existing_ledger['id']
            );
            $old_credit_amount = (float)($old_ledger_entry['credit_amount'] ?? 0);
            $old_credit_gold = (float)($old_ledger_entry['credit_gold'] ?? 0);
            $old_credit_silver = (float)($old_ledger_entry['credit_silver'] ?? 0);
            
            // Recalculate new balance: current - old_credit + new_credit
            $new_balance_amount = $current_balance_amount - $old_credit_amount + $total_amount;
            $new_balance_gold = $current_balance_gold - $old_credit_gold + $total_gold;
            $new_balance_silver = $current_balance_silver - $old_credit_silver + $total_silver;
            
            $br_set = $ledger_has_branch_col
                ? ', branch_id = ' . ($ap_branch_for_ledger > 0 ? (int) $ap_branch_for_ledger : 'NULL')
                : '';
            $update_ledger = "
                UPDATE tbl_customer_ledger SET
                    customer_id = " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                    customer_name = '$customer_name',
                    transaction_date = '$voucher_date',
                    transaction_no = '$voucher_no',
                    credit_amount = $total_amount,
                    credit_gold = $total_gold,
                    credit_silver = $total_silver,
                    balance_amount = $new_balance_amount,
                    balance_gold = $new_balance_gold,
                    balance_silver = $new_balance_silver,
                    description = 'Advance Payment - $voucher_no',
                    reference_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . "
                    $br_set
                    , updated_at = NOW()
                WHERE id = " . (int)$existing_ledger['id'];
            
            if (!mysqli_query($conn, $update_ledger)) {
                throw new Exception('Error updating customer ledger: ' . mysqli_error($conn));
            }
        } else {
            // Insert new ledger entry
            $insert_ledger = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount, debit_gold, credit_gold,
                    debit_silver, credit_silver, balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 'NULL') . $ledger_branch_sql_val . ",
                    '$customer_name',
                    'advance_payment',
                    $voucher_id,
                    '$voucher_no',
                    '$voucher_date',
                    0,
                    $total_amount,
                    0,
                    $total_gold,
                    0,
                    $total_silver,
                    $new_balance_amount,
                    $new_balance_gold,
                    $new_balance_silver,
                    'Advance Payment - $voucher_no',
                    " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                    1,
                    " . ($created_by ? $created_by : 'NULL') . ",
                    NOW()
                )
            ";
            
            if (!mysqli_query($conn, $insert_ledger)) {
                throw new Exception('Error inserting customer ledger entry: ' . mysqli_error($conn));
            }
        }
        
        // Update customer balance summary table
        $existing_balance = null;
        if ($customer_id > 0) {
            $existing_balance = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_id = $customer_id");
        }
        if (!$existing_balance && !empty($customer_name)) {
            $existing_balance = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_name = '$customer_name'");
        }
        
        if ($existing_balance) {
            $update_balance = "
                UPDATE tbl_customer_balance SET
                    balance_amount = $new_balance_amount,
                    balance_gold = $new_balance_gold,
                    balance_silver = $new_balance_silver,
                    last_transaction_date = '$voucher_date',
                    last_updated = NOW()
                WHERE id = " . (int)$existing_balance['id'];
            mysqli_query($conn, $update_balance);
        } else {
            $insert_balance = "
                INSERT INTO tbl_customer_balance (
                    customer_id, customer_name, balance_amount, balance_gold, balance_silver,
                    last_transaction_date, last_updated
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                    '$customer_name',
                    $new_balance_amount,
                    $new_balance_gold,
                    $new_balance_silver,
                    '$voucher_date',
                    NOW()
                )
            ";
            mysqli_query($conn, $insert_balance);
        }
    }

    if ((int) $voucher_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'advance_payment', (int) $voucher_id, $voucher_no, $voucher_date);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Advance Payment',
        'verb' => $advance_voucher_existed ? 'updated' : 'created',
        'number' => $voucher_no,
        'party' => $customer_name,
        'doc_date' => $voucher_date,
        'due_date' => $due_date,
        'ref_id' => (int) $voucher_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Advance payment saved successfully',
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
