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

if (!function_exists('purchase_quotation_delete_auto_payment_vouchers_for_refs')) {
    function purchase_quotation_delete_auto_payment_vouchers_for_refs($conn, array $ref_numbers): void
    {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return;
        }
        mysqli_free_result($chk);
        $uniq = [];
        foreach ($ref_numbers as $r) {
            $r = trim((string) $r);
            if ($r !== '') {
                $uniq[$r] = true;
            }
        }
        foreach (array_keys($uniq) as $ref) {
            $esc = mysqli_real_escape_string($conn, $ref);
            $rows = getList("SELECT id FROM tbl_payment_vouchers WHERE ref_no = '$esc' AND voucher_type = 'Purchase Quotation Payment'");
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $vid = (int) ($row['id'] ?? 0);
                if ($vid <= 0) {
                    continue;
                }
                mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'payment_voucher' AND transaction_id = $vid AND status = 1");
                mysqli_query($conn, "DELETE FROM tbl_payment_voucher_items WHERE voucher_id = $vid");
                mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE id = $vid");
            }
        }
    }
}

if (!function_exists('purchase_quotation_payment_is_auto_pv_money')) {
    function purchase_quotation_payment_is_auto_pv_money(array $payment): bool
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

if (!function_exists('purchase_quotation_validate_metal_exchange_payments')) {
    /** @param array<int, array<string, mixed>> $payments */
    function purchase_quotation_validate_metal_exchange_payments($conn, array $payments): void
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

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0;
    if ($user_id <= 0) {
        throw new Exception('User session expired. Please login again.');
    }
    $metal_exchange_barcodes_out = [];

    $has_pq_branch     = auragold_ensure_table_branch_id_column($conn, 'tbl_purchase_quotations');
    $hdr_branch        = auragold_transaction_header_branch_id();
    $eff_branch        = auragold_effective_branch_id();
    $pq_dup_branch_sql = ($has_pq_branch && $hdr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_branch) : '';

    $quotation_no_in = trim((string) ($_POST['order_no'] ?? ''));
    $quotation_no = esc($quotation_no_in);
    $supplier_id = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
    $supplier_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $against_type = esc($_POST['against_type'] ?? '');
    $against_id = isset($_POST['against_id']) ? (int) $_POST['against_id'] : 0;
    $currency = esc($_POST['currency'] ?? 'USD');
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_purchase_quotations') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $rate = isset($_POST['rate']) ? (float) $_POST['rate'] : 1.000000;
    $ref_no = esc($_POST['ref_no'] ?? '');
    $purchase_person = esc($_POST['sales_person'] ?? '');
    $quotation_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $ounce_rate = (float) ($_POST['ounce_rate'] ?? 0);
    $unfix_dmd_gms = isset($_POST['unfix_dmd_gms']) ? 1 : 0;
    $unfix_metal = isset($_POST['unfix_metal']) ? 1 : 0;
    $unfix = isset($_POST['unfix']) ? 1 : 0;
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

    $has_payment_comments = false;
    $pc_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotations LIKE 'payment_comments'");
    if ($pc_col && mysqli_num_rows($pc_col) > 0) {
        $has_payment_comments = true;
        mysqli_free_result($pc_col);
    } else {
        if ($pc_col) {
            mysqli_free_result($pc_col);
        }
        @mysqli_query($conn, "ALTER TABLE tbl_purchase_quotations ADD COLUMN payment_comments TEXT NULL DEFAULT NULL AFTER comment");
        $has_payment_comments = true;
    }

    $previous_balance = (float) ($_POST['previous_balance'] ?? 0);
    $previous_gold = (float) ($_POST['previous_gold'] ?? 0);
    $previous_silver = (float) ($_POST['previous_silver'] ?? 0);
    $subtotal = (float) ($_POST['subtotal'] ?? 0);
    $additional_amt = (float) ($_POST['additional_amt'] ?? 0);
    $net_total = (float) ($_POST['net_total'] ?? 0);
    $discount_amt = (float) ($_POST['discount_amt'] ?? 0);
    $grand_total = (float) ($_POST['grand_total'] ?? 0);
    $advance_payment = (float) ($_POST['advance_payment'] ?? 0);
    $metal_amt = (float) ($_POST['metal_amt'] ?? 0);
    $round_off = (float) ($_POST['round_off'] ?? 0);
    $return_invoice = (float) ($_POST['return_invoice'] ?? 0);
    $paid_amt = (float) ($_POST['paid_amt'] ?? 0);
    $balance_amt = (float) ($_POST['balance_amt'] ?? 0);

    if ($supplier_name === '') {
        throw new Exception('Supplier name is required');
    }

    $quotation_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    $is_update = ($quotation_id > 0);
    $current_quotation_no = '';
    $pq_row_branch_id = 0;
    if ($is_update) {
        $cur_row = getRecord('SELECT quotation_no, branch_id FROM tbl_purchase_quotations WHERE id = ' . (int) $quotation_id . ' LIMIT 1');
        $current_quotation_no = $cur_row ? trim((string) ($cur_row['quotation_no'] ?? '')) : '';
        if ($has_pq_branch) {
            $pq_row_branch_id = (int) ($cur_row['branch_id'] ?? 0);
            auragold_branch_require_document_access($conn, 'tbl_purchase_quotations', $quotation_id);
        }
    }

    if ($quotation_no === '') {
        $quotation_no = esc(function_exists('getNextPurchaseQuotationNo') ? getNextPurchaseQuotationNo($conn) : 'PQ-1');
    }

    if (!$is_update) {
        $cfg = function_exists('getPurchaseQuotationBillSeriesConfig') ? getPurchaseQuotationBillSeriesConfig($conn) : ['prefix' => 'PQ-', 'suffix' => '', 'start_count' => 1];
        $pq_active = auragold_doc_series_active_sql($conn, 'tbl_purchase_quotations');
        $existing_quotation = getRecord("SELECT id FROM tbl_purchase_quotations WHERE quotation_no = '$quotation_no'$pq_dup_branch_sql" . $pq_active);
        $guard = 0;
        while ($existing_quotation && $guard < 5000) {
            $quotation_no = esc(function_exists('bumpPurchaseQuotationNo') ? bumpPurchaseQuotationNo($conn, $quotation_no, $cfg) : ('PQ-' . ($guard + 2)));
            $existing_quotation = getRecord("SELECT id FROM tbl_purchase_quotations WHERE quotation_no = '$quotation_no'$pq_dup_branch_sql" . $pq_active);
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_purchase_quotations', 'quotation_no']], $quotation_no);
        }
    }

    if ($is_update) {
        $check_sql = "SELECT id FROM tbl_purchase_quotations WHERE id = $quotation_id";
        $check_result = mysqli_query($conn, $check_sql);
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            throw new Exception('Quotation not found');
        }
        if ($quotation_no !== $current_quotation_no) {
            $existing_quotation = getRecord("SELECT id FROM tbl_purchase_quotations WHERE quotation_no = '$quotation_no' AND id != $quotation_id$pq_dup_branch_sql" . auragold_doc_series_active_sql($conn, 'tbl_purchase_quotations'));
            if ($existing_quotation) {
                throw new Exception("Quotation number '$quotation_no' already exists.");
            }
        }
        purchase_quotation_delete_auto_payment_vouchers_for_refs($conn, array_filter([$current_quotation_no, $quotation_no]));

        $sql = "
            UPDATE tbl_purchase_quotations SET
                quotation_no = '$quotation_no',
                supplier_id = " . ($supplier_id > 0 ? $supplier_id : 'NULL') . ",
                supplier_name = '$supplier_name',
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                against_type = " . ($against_type ? "'$against_type'" : 'NULL') . ",
                against_id = " . ($against_id > 0 ? $against_id : 'NULL') . ",
                currency = '$currency', $fx_update_sql
                rate = $rate,
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                purchase_person = " . ($purchase_person ? "'$purchase_person'" : 'NULL') . ",
                quotation_date = '$quotation_date',
                due_date = " . ($due_date ? "'$due_date'" : 'NULL') . ",
                layaways_id = " . ($layaways ? (int) $layaways : 'NULL') . ",
                fixing_type = '$fixing_type',
                ounce_rate = $ounce_rate,
                unfix_dmd_gms = $unfix_dmd_gms,
                unfix_metal = $unfix_metal,
                unfix = $unfix,
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                subtotal = $subtotal,
                additional_amt = $additional_amt,
                net_total = $net_total,
                discount_amt = $discount_amt,
                grand_total = $grand_total,
                advance_payment = $advance_payment,
                metal_amt = $metal_amt,
                round_off = $round_off,
                return_invoice = $return_invoice,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                group_name = " . ($group_name ? "'$group_name'" : 'NULL') . ",
                comment = " . ($comment ? "'$comment'" : 'NULL') . "
                " . ($has_payment_comments ? ", payment_comments = '" . $payment_comments_esc . "'" : '') . "
                " . ($has_pq_branch && $eff_branch > 0 && $pq_row_branch_id === 0 ? ', branch_id = ' . (int) $eff_branch : '') . ",
                updated_at = NOW()
            WHERE id = $quotation_id
        ";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Quotation update failed: ' . mysqli_error($conn));
        }
        mysqli_query($conn, "DELETE FROM tbl_purchase_quotation_items WHERE quotation_id = $quotation_id");
        mysqli_query($conn, "DELETE FROM tbl_purchase_quotation_payments WHERE quotation_id = $quotation_id");
    } else {
        $check_no_sql = "SELECT id FROM tbl_purchase_quotations WHERE quotation_no = '$quotation_no'$pq_dup_branch_sql" . auragold_doc_series_active_sql($conn, 'tbl_purchase_quotations');
        $check_no_result = mysqli_query($conn, $check_no_sql);
        if ($check_no_result && mysqli_num_rows($check_no_result) > 0) {
            throw new Exception('Quotation number already exists');
        }
        $sql = "
            INSERT INTO tbl_purchase_quotations (
                quotation_no, supplier_id, supplier_name, against_of, against_type, against_id, currency$fx_insert_col_sql, rate, ref_no, purchase_person,
                quotation_date, due_date, layaways_id, fixing_type, ounce_rate,
                unfix_dmd_gms, unfix_metal, unfix,
                previous_balance, previous_gold, previous_silver,
                subtotal, additional_amt, net_total, discount_amt, grand_total,
                advance_payment, metal_amt, round_off, return_invoice,
                paid_amt, balance_amt, group_name, comment,
                " . ($has_pq_branch ? 'branch_id, ' : '') . "
                status, created_by, created_at
                " . ($has_payment_comments ? ', payment_comments' : '') . "
            ) VALUES (
                '$quotation_no', " . ($supplier_id > 0 ? $supplier_id : 'NULL') . ", '$supplier_name',
                " . ($against_of ? "'$against_of'" : 'NULL') . ",
                " . ($against_type ? "'$against_type'" : 'NULL') . ",
                " . ($against_id > 0 ? $against_id : 'NULL') . ",
                '$currency'$fx_insert_val_sql, $rate, " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                " . ($purchase_person ? "'$purchase_person'" : 'NULL') . ",
                '$quotation_date', " . ($due_date ? "'$due_date'" : 'NULL') . ",
                " . ($layaways ? (int) $layaways : 'NULL') . ",
                '$fixing_type', $ounce_rate,
                $unfix_dmd_gms, $unfix_metal, $unfix,
                $previous_balance, $previous_gold, $previous_silver,
                $subtotal, $additional_amt, $net_total, $discount_amt, $grand_total,
                $advance_payment, $metal_amt, $round_off, $return_invoice,
                $paid_amt, $balance_amt,
                " . ($group_name ? "'$group_name'" : 'NULL') . ",
                " . ($comment ? "'$comment'" : 'NULL') . ",
                " . ($has_pq_branch ? ((int) $hdr_branch > 0 ? (int) $hdr_branch : 'NULL') . ', ' : '') . "
                'draft', $user_id, NOW()
                " . ($has_payment_comments ? ", '" . $payment_comments_esc . "'" : '') . "
            )
        ";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Quotation insert failed: ' . mysqli_error($conn));
        }
        $quotation_id = (int) mysqli_insert_id($conn);
    }

    auragold_ensure_customer_ledger_branch_column($conn);
    $pq_ledger_branch_id = 0;
    if ($quotation_id > 0 && $has_pq_branch) {
        $pqr = getRecord('SELECT branch_id FROM tbl_purchase_quotations WHERE id = ' . (int) $quotation_id . ' LIMIT 1');
        $pq_ledger_branch_id = (int) ($pqr['branch_id'] ?? 0);
    }
    if ($pq_ledger_branch_id <= 0) {
        $pq_ledger_branch_id = $pq_row_branch_id > 0 ? $pq_row_branch_id : (($hdr_branch > 0) ? $hdr_branch : (($eff_branch > 0) ? $eff_branch : 0));
    }
    $ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = '';
    if ($ledger_has_branch_col) {
        $ledger_branch_sql_val = ', ' . ($pq_ledger_branch_id > 0 ? (string) (int) $pq_ledger_branch_id : 'NULL');
    }
    $ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql') ? auragold_customer_ledger_branch_scope_sql($conn, $pq_ledger_branch_id) : '';

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

    $pq_has_metal_qty = false;
    $pq_has_metal_weight = false;
    $pq_mq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotation_items LIKE 'metal_qty'");
    if ($pq_mq && mysqli_num_rows($pq_mq) > 0) {
        $pq_has_metal_qty = true;
        mysqli_free_result($pq_mq);
    } else {
        if ($pq_mq) {
            mysqli_free_result($pq_mq);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_purchase_quotation_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity');
        $pq_has_metal_qty = true;
    }
    $pq_mw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotation_items LIKE 'metal_weight'");
    if ($pq_mw && mysqli_num_rows($pq_mw) > 0) {
        $pq_has_metal_weight = true;
        mysqli_free_result($pq_mw);
    } else {
        if ($pq_mw) {
            mysqli_free_result($pq_mw);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_purchase_quotation_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty');
        $pq_has_metal_weight = true;
    }

    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';

    $invoice_used_barcodes = [];
    foreach ($items as $item) {
        $product_id = (int) ($item['product_id'] ?? 0);
        if ($product_id <= 0) {
            continue;
        }
        $characteristic_id = isset($item['characteristic_id']) ? (int) $item['characteristic_id'] : null;
        $barcode = esc(auragold_resolve_unique_invoice_item_barcode($conn, $item, $invoice_used_barcodes));
        $product_name = esc($item['product_name'] ?? '');
        $description = esc($item['description'] ?? '');
        $carat = esc($item['carat'] ?? '');
        $quantity = (float) ($item['quantity'] ?? 1);
        $gross_weight = (float) ($item['gross_weight'] ?? 0);
        $final_weight = (float) ($item['final_weight'] ?? 0);
        $net_weight = (float) ($item['net_weight'] ?? 0);
        $pure_weight = (float) ($item['pure_weight'] ?? 0);
        $making = (float) ($item['making'] ?? 0);
        $tax = (float) ($item['tax'] ?? 0);
        $amount = (float) ($item['amount'] ?? 0);
        $net_amount = (float) ($item['net_amount'] ?? 0);
        $net_amt_weight = (float) ($item['net_amt_weight'] ?? 0);
        $diamond_weight = (float) ($item['diamond_weight'] ?? 0);
        $gemstone_weight = (float) ($item['gemstone_weight'] ?? 0);
        $diamond_amount = (float) ($item['diamond_amount'] ?? 0);
        $design_no = esc($item['design_no'] ?? '');
        $metal_qty = (float) ($item['metal_qty'] ?? 1);
        $metal_weight = (float) ($item['metal_weight'] ?? 0);
        $mq_col = $pq_has_metal_qty ? ', metal_qty' : '';
        $mq_val = $pq_has_metal_qty ? ", $metal_qty" : '';
        $mw_col = $pq_has_metal_weight ? ', metal_weight' : '';
        $mw_val = $pq_has_metal_weight ? ", $metal_weight" : '';
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_purchase_quotation_items', $item);
        $item_sql = "
            INSERT INTO tbl_purchase_quotation_items (
                quotation_id, product_id, product_characteristic_id, barcode, product_name,
                description, carat, quantity, gross_weight, final_weight,
                net_weight, pure_weight, making_amount, tax_amount,
                amount, net_amount, net_amt_weight,
                diamond_weight, gemstone_weight, diamond_amount, design_no,
                status, created_at $mq_col $mw_col{$ef_parts['columns']}
            ) VALUES (
                $quotation_id, $product_id, " . ($characteristic_id ? $characteristic_id : 'NULL') . ',
                ' . ($barcode ? "'$barcode'" : 'NULL') . ",
                '$product_name',
                " . ($description ? "'$description'" : 'NULL') . ",
                " . ($carat ? "'$carat'" : 'NULL') . ",
                $quantity, $gross_weight, $final_weight,
                $net_weight, $pure_weight, $making, $tax,
                $amount, $net_amount, $net_amt_weight,
                $diamond_weight, $gemstone_weight, $diamond_amount,
                " . ($design_no ? "'$design_no'" : 'NULL') . ",
                1, NOW() $mq_val $mw_val{$ef_parts['values']}
            )
        ";
        if (!mysqli_query($conn, $item_sql)) {
            throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }

        $pq_item_id = (int) mysqli_insert_id($conn);
        $metal_id_pq = 0;
        if ($characteristic_id) {
            $ch_pq = getRecord('SELECT metal_id FROM tbl_product_characteristics WHERE id = ' . (int) $characteristic_id . ' AND status = 1');
            if ($ch_pq) {
                $metal_id_pq = (int) ($ch_pq['metal_id'] ?? 0);
            }
        }
        if ($metal_id_pq <= 0) {
            $dm_pq = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 ORDER BY id DESC LIMIT 1");
            if ($dm_pq) {
                $metal_id_pq = (int) ($dm_pq['metal_id'] ?? 0);
            }
        }
        $less_weight_pq = (float) ($item['less_weight'] ?? 0);
        $purity_pq = (float) ($item['purity'] ?? 0);
        $purity_weight_pq = (float) ($item['purity_weight'] ?? 0);
        $rate_pq = (float) ($item['rate'] ?? 0);
        $net_amt_with_tax_pq = (float) ($item['net_amt_with_tax'] ?? $net_amount);
        $sj_no_pq = 'PQ' . (int) $quotation_id . 'I' . $pq_item_id;
        if (strlen($sj_no_pq) > 48) {
            $sj_no_pq = 'P' . (int) $quotation_id . 'x' . $pq_item_id;
        }
        if (trim((string) $barcode) !== '') {
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_no_pq,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => $quotation_no,
            'sj_date' => $quotation_date,
            'barcode' => $barcode,
            'product_id' => $product_id,
            'product_characteristic_id' => $characteristic_id ? (int) $characteristic_id : 0,
            'product_name' => $product_name,
            'metal_id' => $metal_id_pq,
            'metal_type' => auragold_stock_history_metal_type($conn, $metal_id_pq),
            'quantity' => $quantity,
            'gross_weight' => $gross_weight,
            'less_weight' => $less_weight_pq,
            'net_weight' => $net_weight,
            'purity' => $purity_pq,
            'purity_weight' => $purity_weight_pq,
            'pure_weight' => $pure_weight,
            'final_weight' => $final_weight,
            'rate' => $rate_pq,
            'amount' => $amount,
            'making_amount' => $making,
            'tax_amount' => $tax,
            'net_amount' => $net_amount,
            'net_amt_with_tax' => $net_amt_with_tax_pq,
            'rfid_code' => '',
            'voucher_type' => 'Purchase Quotation',
            'design_no' => $design_no,
            'category' => '',
            'comment' => 'auragold_doc|src=pq|qid=' . (int) $quotation_id . '|pqi=' . $pq_item_id . '|',
        ]);
        }
    }

    $is_hedging = (strtolower(trim($fixing_type)) === 'hedging');

    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_id = $quotation_id AND status = 1
        AND transaction_type IN ('purchase_quotation', 'purchase_quotation_revenue')
    ");
    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE customer_name = 'Hedging Account' AND transaction_id = $quotation_id AND transaction_type = 'purchase_quotation' AND status = 1
    ");
    purchase_quotation_delete_auto_payment_vouchers_for_refs($conn, [$quotation_no]);

    $total_purchase_amt = 0.0;
    $total_making_amt = 0.0;
    $total_tax_amt = 0.0;
    foreach ($items as $item) {
        $metal_val = (float) ($item['metal_value'] ?? 0);
        $diamond_amt = (float) ($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
        $stone_amt = (float) ($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
        $making_amt = (float) ($item['making_amount'] ?? $item['making'] ?? 0);
        if ($is_hedging) {
            $making_amt = 0.0;
        }
        $tax_amt = (float) ($item['tax'] ?? 0);
        $amount = (float) ($item['amount'] ?? 0);
        $item_purchase = $metal_val + $diamond_amt + $stone_amt;
        if ($item_purchase <= 0 && $amount > 0) {
            $item_purchase = max(0, $amount - $making_amt);
        }
        $total_purchase_amt += $item_purchase;
        $total_making_amt += $making_amt;
        $total_tax_amt += $tax_amt;
    }
    if ($total_purchase_amt <= 0 && isset($metal_amt)) {
        $total_purchase_amt = (float) $metal_amt;
    }
    if ($total_making_amt <= 0 && $grand_total > 0 && $total_purchase_amt > 0) {
        $total_making_amt = max(0, $net_total - $total_purchase_amt);
    }

    $has_against_res = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
    $has_against = ($has_against_res && mysqli_num_rows($has_against_res) > 0);
    if ($has_against_res) {
        mysqli_free_result($has_against_res);
    }
    $against_cols = $has_against ? ', against_ledger, against_invoice_no' : '';
    $get_ledger_balance = function ($ledger_name) use ($conn, $ledger_br_scope) {
        $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = '" . mysqli_real_escape_string($conn, $ledger_name) . "' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");

        return (float) ($r['balance_amount'] ?? 0);
    };
    $ref_esc = $ref_no ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL';
    $qno_esc = mysqli_real_escape_string($conn, $quotation_no);

    if ($total_purchase_amt > 0.00001) {
        $prev_purchase = $get_ledger_balance('Purchase Account');
        $new_purchase_bal = $prev_purchase - $total_purchase_amt;
        $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $supplier_name) . "', '$qno_esc'" : '';
        $purchase_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Purchase Account', 'purchase_quotation_revenue', $quotation_id, '$qno_esc', '$quotation_date', 0.00, $total_purchase_amt, $new_purchase_bal, 'Purchase Quotation: $qno_esc', $ref_esc, 1, $user_id, NOW() $against_vals)";
        if (!mysqli_query($conn, $purchase_sql)) {
            throw new Exception('Purchase Account ledger entry failed: ' . mysqli_error($conn));
        }
    }
    if ($total_making_amt > 0.00001) {
        $prev_making = $get_ledger_balance('Making Purchase Account');
        $new_making_bal = $prev_making - $total_making_amt;
        $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $supplier_name) . "', '$qno_esc'" : '';
        $making_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Making Purchase Account', 'purchase_quotation_revenue', $quotation_id, '$qno_esc', '$quotation_date', 0.00, $total_making_amt, $new_making_bal, 'Making charges - Purchase Quotation: $qno_esc', $ref_esc, 1, $user_id, NOW() $against_vals)";
        if (!mysqli_query($conn, $making_sql)) {
            throw new Exception('Making Purchase Account ledger entry failed: ' . mysqli_error($conn));
        }
    }
    if ($total_tax_amt > 0.00001) {
        $prev_tax = $get_ledger_balance('Tax Ledger');
        $new_tax_bal = $prev_tax - $total_tax_amt;
        $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $supplier_name) . "', '$qno_esc'" : '';
        $tax_sql = "INSERT INTO tbl_customer_ledger (customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0$ledger_branch_sql_val, 'Tax Ledger', 'purchase_quotation_revenue', $quotation_id, '$qno_esc', '$quotation_date', 0.00, $total_tax_amt, $new_tax_bal, 'Tax - Purchase Quotation: $qno_esc', $ref_esc, 1, $user_id, NOW() $against_vals)";
        if (!mysqli_query($conn, $tax_sql)) {
            throw new Exception('Tax Ledger entry failed: ' . mysqli_error($conn));
        }
    }

    $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
    $has_gold_pure_cols = ($gpc && mysqli_num_rows($gpc) > 0);
    if ($gpc) {
        mysqli_free_result($gpc);
    }

    if ($supplier_id > 0 || $supplier_name !== '') {
        $prev_balance_select = 'balance_amount, balance_gold, balance_silver';
        if ($has_gold_pure_cols) {
            $prev_balance_select .= ', balance_gold_pure';
        }
        $previous_balance_record = null;
        if ($supplier_id > 0) {
            $previous_balance_record = getRecord("
                SELECT $prev_balance_select FROM tbl_customer_ledger
                WHERE customer_id = $supplier_id AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_balance WHERE customer_id = $supplier_id LIMIT 1");
            }
        }
        if (!$previous_balance_record && $supplier_name !== '') {
            $sn_esc = mysqli_real_escape_string($conn, $supplier_name);
            $previous_balance_record = getRecord("
                SELECT $prev_balance_select FROM tbl_customer_ledger
                WHERE customer_name = '$sn_esc' AND status = 1
                $ledger_br_scope
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_balance WHERE customer_name = '$sn_esc' LIMIT 1");
            }
        }
        $prev_balance_amount = (float) ($previous_balance_record['balance_amount'] ?? 0);
        $prev_balance_gold = (float) ($previous_balance_record['balance_gold'] ?? 0);
        $prev_balance_silver = (float) ($previous_balance_record['balance_silver'] ?? 0);
        $prev_balance_gold_pure = $has_gold_pure_cols ? (float) ($previous_balance_record['balance_gold_pure'] ?? 0) : 0.0;

        $ledger_debit_amount = $grand_total;
        $total_gold_weight = 0.0;
        $total_silver_weight = 0.0;
        if ($is_hedging && !empty($items)) {
            foreach ($items as $item) {
                $net_weight = (float) ($item['net_weight'] ?? $item['net_wt'] ?? $item['final_weight'] ?? $item['final_wt'] ?? $item['gross_weight'] ?? $item['gross_wt'] ?? 0);
                $purity_weight = (float) ($item['purity_weight'] ?? $item['pure_weight'] ?? $item['pure_wt'] ?? $item['purity_wt'] ?? 0);
                $purity = (float) ($item['purity'] ?? 0);
                if ($purity_weight <= 0 && $net_weight > 0 && $purity > 0) {
                    if ($purity <= 1) {
                        $purity_weight = $net_weight * $purity;
                    } elseif ($purity <= 100) {
                        $purity_weight = $net_weight * ($purity / 100);
                    } else {
                        $purity_weight = $net_weight * ($purity / 1000);
                    }
                }
                if ($purity_weight <= 0) {
                    $purity_weight = $net_weight;
                }
                $purity_pct = 0;
                if ($purity > 0) {
                    if ($purity <= 1) {
                        $purity_pct = $purity * 100;
                    } elseif ($purity <= 100) {
                        $purity_pct = $purity;
                    } else {
                        $purity_pct = $purity / 10;
                    }
                }
                $product_name_it = trim((string) ($item['product_name'] ?? ''));
                $is_gold = ($purity_pct >= 75) || (stripos($product_name_it, 'gold') !== false);
                $is_silver = ($purity_pct >= 50 && $purity_pct < 75) || (stripos($product_name_it, 'silver') !== false);
                if ($is_gold) {
                    $total_gold_weight += ($purity_weight > 0 ? $purity_weight : $net_weight);
                } elseif ($is_silver) {
                    $total_silver_weight += ($purity_weight > 0 ? $purity_weight : $net_weight);
                }
            }
        }
        $total_gold_pure = $total_gold_weight;
        $new_balance_amount = $prev_balance_amount + $ledger_debit_amount;
        $new_balance_gold = $prev_balance_gold + $total_gold_weight;
        $new_balance_silver = $prev_balance_silver + $total_silver_weight;
        $new_balance_gold_pure = $prev_balance_gold_pure + $total_gold_pure;

        $against_parts = [];
        if ($total_purchase_amt > 0.00001) {
            $against_parts[] = 'Purchase Account(' . number_format($total_purchase_amt, 2) . 'Cr)';
        }
        if ($total_making_amt > 0.00001) {
            $against_parts[] = 'Making Purchase Account(' . number_format($total_making_amt, 2) . 'Cr)';
        }
        if ($total_tax_amt > 0.00001) {
            $against_parts[] = 'Tax Ledger(' . number_format($total_tax_amt, 2) . 'Cr)';
        }
        $against_ledger = implode(', ', $against_parts);
        if ($against_ledger === '') {
            $against_ledger = 'Purchase Account(' . number_format($grand_total, 2) . 'Cr)';
        }
        $against_invoice_no = $quotation_no;
        $against_vals = $has_against ? ', ' . ($against_ledger ? "'" . mysqli_real_escape_string($conn, $against_ledger) . "'" : 'NULL') . ', ' . ($against_invoice_no ? "'" . mysqli_real_escape_string($conn, $against_invoice_no) . "'" : 'NULL') : '';
        $ledger_gold_pure_cols = $has_gold_pure_cols ? 'debit_gold_pure, credit_gold_pure,' : '';
        $ledger_balance_gold_pure_col = $has_gold_pure_cols ? ', balance_gold_pure' : '';
        $ledger_balance_gold_pure_val = $has_gold_pure_cols ? ', ' . (float) $new_balance_gold_pure : '';
        $metal_vals = (float) $total_gold_weight . ', 0.000';
        if ($has_gold_pure_cols) {
            $metal_vals .= ', ' . (float) $total_gold_pure . ', 0.000';
        }
        $metal_vals .= ', ' . (float) $total_silver_weight . ', 0.000';
        $supplier_name_esc_ledger = mysqli_real_escape_string($conn, $supplier_name);
        $ledger_sql = "
            INSERT INTO tbl_customer_ledger (
                customer_id$ledger_branch_sql_col, customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                description, reference_no, status, created_by, created_at
                $against_cols
            ) VALUES (
                " . ($supplier_id > 0 ? $supplier_id : 0) . "$ledger_branch_sql_val,
                '$supplier_name_esc_ledger',
                'purchase_quotation',
                $quotation_id,
                '$qno_esc',
                '$quotation_date',
                $ledger_debit_amount,
                0.00,
                $metal_vals,
                $new_balance_amount,
                $new_balance_gold $ledger_balance_gold_pure_val,
                $new_balance_silver,
                'Purchase Quotation: $qno_esc" . ($is_hedging ? ' (Hedging)' : '') . "',
                $ref_esc,
                1,
                $user_id,
                NOW()
                $against_vals
            )
        ";
        if (!mysqli_query($conn, $ledger_sql)) {
            throw new Exception('Supplier ledger entry (purchase quotation) failed: ' . mysqli_error($conn));
        }

        if ($is_hedging && ($total_gold_weight > 0 || $total_silver_weight > 0)) {
            $ha_last = getRecord("SELECT balance_amount, balance_gold, balance_silver " . ($has_gold_pure_cols ? ', balance_gold_pure' : '') . " FROM tbl_customer_ledger WHERE customer_name = 'Hedging Account' AND status = 1 $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1");
            $ha_prev_amt = (float) ($ha_last['balance_amount'] ?? 0);
            $ha_prev_gold = (float) ($ha_last['balance_gold'] ?? 0);
            $ha_prev_silver = (float) ($ha_last['balance_silver'] ?? 0);
            $ha_prev_gold_pure = $has_gold_pure_cols ? (float) ($ha_last['balance_gold_pure'] ?? 0) : 0.0;
            $ha_new_gold = $ha_prev_gold + $total_gold_weight;
            $ha_new_silver = $ha_prev_silver + $total_silver_weight;
            $ha_new_gold_pure = $ha_prev_gold_pure + $total_gold_pure;
            $ha_metal_vals = '0.000, ' . (float) $total_gold_weight;
            if ($has_gold_pure_cols) {
                $ha_metal_vals .= ', 0.000, ' . (float) $total_gold_pure;
            }
            $ha_metal_vals .= ', 0.000, ' . (float) $total_silver_weight;
            $ha_balance_gold_pure_col = $has_gold_pure_cols ? ', balance_gold_pure' : '';
            $ha_balance_gold_pure_val = $has_gold_pure_cols ? ', ' . (float) $ha_new_gold_pure : '';
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
                    'purchase_quotation',
                    $quotation_id,
                    '$qno_esc',
                    '$quotation_date',
                    0.00,
                    0.00,
                    $ha_metal_vals,
                    $ha_prev_amt,
                    $ha_new_gold $ha_balance_gold_pure_val,
                    $ha_new_silver,
                    'Purchase Quotation: $qno_esc (Hedging)',
                    $ref_esc,
                    1,
                    $user_id,
                    NOW()
                    " . ($has_against ? ", '" . mysqli_real_escape_string($conn, $supplier_name) . "', '$qno_esc'" : '') . '
                )
            ';
            if (!mysqli_query($conn, $ha_sql)) {
                throw new Exception('Hedging Account ledger entry (purchase quotation) failed: ' . mysqli_error($conn));
            }
        }
    }

    $payments = [];
    if (isset($_POST['payments'])) {
        if (is_string($_POST['payments'])) {
            $payments = json_decode($_POST['payments'], true);
        } elseif (is_array($_POST['payments'])) {
            $payments = $_POST['payments'];
        }
    }
    if (!is_array($payments)) {
        $payments = [];
    }

    $__pq_me_has_ref = false;
    if (!empty($payments)) {
        purchase_quotation_validate_metal_exchange_payments($conn, $payments);
        $__pq_me_has_ref = auragold_metal_exchange_document_init($conn, $is_update, (int) $quotation_id, 'purchase_quotation_metal_exchange');
    }

    foreach ($payments as $pay_seq => $payment) {
        $payment_type = esc($payment['payment_type'] ?? '');
        $diamond_category = esc($payment['diamond_category'] ?? '');
        $transaction_no_p = esc($payment['transaction_no'] ?? '');
        $transfer_from = esc($payment['transfer_from'] ?? '');
        $deposit_into = esc($payment['deposit_into'] ?? '');
        $product = esc($payment['product'] ?? '');
        $cheque_date = esc($payment['cheque_date'] ?? '');
        $weight = (float) ($payment['weight'] ?? 0);
        $metal = esc($payment['metal'] ?? '');
        $quantity = (float) ($payment['quantity'] ?? 0);
        $purity_carat = esc($payment['purity_carat'] ?? '');
        $amount = (float) ($payment['amount'] ?? 0);
        $raw_pt = trim((string) ($payment['payment_type'] ?? ''));
        $__insert_row = auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)
            || (($amount > 1e-8) || $raw_pt !== '');

        if ($__insert_row) {
            $payment_sql = "
                INSERT INTO tbl_purchase_quotation_payments (
                    quotation_id, payment_type, diamond_category, transaction_no,
                    transfer_from, deposit_into, product, cheque_date,
                    weight, metal, quantity, purity_carat, amount,
                    status, created_at
                ) VALUES (
                    $quotation_id, '$payment_type',
                    " . ($diamond_category ? "'$diamond_category'" : 'NULL') . ',
                    ' . ($transaction_no_p ? "'$transaction_no_p'" : 'NULL') . ',
                    ' . ($transfer_from ? "'$transfer_from'" : 'NULL') . ',
                    ' . ($deposit_into ? "'$deposit_into'" : 'NULL') . ',
                    ' . ($product ? "'$product'" : 'NULL') . ',
                    ' . ($cheque_date ? "'$cheque_date'" : 'NULL') . ",
                    $weight,
                    " . ($metal ? "'$metal'" : 'NULL') . ",
                    $quantity,
                    " . ($purity_carat ? "'$purity_carat'" : 'NULL') . ",
                    $amount,
                    1, NOW()
                )
            ";
            if (!mysqli_query($conn, $payment_sql)) {
                throw new Exception('Payment insert failed: ' . mysqli_error($conn));
            }
        }

        $__pq_pm = auragold_payment_merge_stored_details($payment);
        auragold_post_metal_exchange_payment_to_stock(
            $conn,
            'purchase_quotation_metal_exchange',
            (int) $quotation_id,
            trim((string) $quotation_no_in),
            substr(trim((string) $quotation_date), 0, 10),
            $__pq_pm,
            auragold_metal_exchange_default_branch_id(),
            is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
            $__pq_me_has_ref,
            'Purchase Quotation — Metal Exchange',
            'pq_me',
            'PQ-ME-',
            $metal_exchange_barcodes_out
        );
    }

    $pq_money_payments = [];
    foreach ($payments as $payment) {
        if (purchase_quotation_payment_is_auto_pv_money($payment)) {
            $pq_money_payments[] = $payment;
        }
    }

    if (!empty($pq_money_payments) && function_exists('purchase_invoice_post_auto_payment_voucher_ledger')) {
        $total_pv = 0.0;
        foreach ($pq_money_payments as $p) {
            $total_pv += (float) ($p['amount'] ?? 0);
        }
        if ($total_pv > 0.00001) {
            $pv_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_chk && mysqli_num_rows($pv_chk) > 0) {
                mysqli_free_result($pv_chk);
                $last_pv = getRecord('SELECT voucher_no FROM tbl_payment_vouchers ORDER BY id DESC LIMIT 1');
                $pv_num = 1;
                if ($last_pv && !empty($last_pv['voucher_no']) && preg_match('/PV[- ]?(\d+)/i', (string) $last_pv['voucher_no'], $m)) {
                    $pv_num = (int) $m[1] + 1;
                }
                $pq_payment_voucher_no = 'PV-' . $pv_num;
                $pv_no_esc = mysqli_real_escape_string($conn, $pq_payment_voucher_no);
                $curr_esc = mysqli_real_escape_string($conn, $currency !== '' ? $currency : 'AED');
                $sup_esc = mysqli_real_escape_string($conn, $supplier_name);
                $qno_sql_esc = mysqli_real_escape_string($conn, $quotation_no);
                $cmt_esc = mysqli_real_escape_string($conn, 'Payment against Purchase Quotation: ' . $quotation_no);
                $uid_sql = $user_id > 0 ? (string) $user_id : 'NULL';
                $pv_header = "
                    INSERT INTO tbl_payment_vouchers (
                        voucher_no, customer_id, customer_name, ref_no, voucher_type,
                        voucher_date, total_amount, total_gold, total_silver,
                        comment, status, created_by, created_at
                    ) VALUES (
                        '$pv_no_esc',
                        " . ($supplier_id > 0 ? $supplier_id : 'NULL') . ",
                        '$sup_esc',
                        '$qno_sql_esc',
                        'Purchase Quotation Payment',
                        '$quotation_date',
                        $total_pv,
                        0, 0,
                        '$cmt_esc',
                        'saved',
                        $uid_sql,
                        NOW()
                    )
                ";
                if (!mysqli_query($conn, $pv_header)) {
                    throw new Exception('Payment voucher header failed: ' . mysqli_error($conn));
                }
                $pq_pv_id = (int) mysqli_insert_id($conn);
                foreach ($pq_money_payments as $p) {
                    $pt = esc($p['payment_type'] ?? 'cash');
                    $dep = esc($p['deposit_into'] ?? 'Cash');
                    $amt_line = (float) ($p['amount'] ?? 0);
                    if ($amt_line <= 0) {
                        continue;
                    }
                    $pvi_sql = "
                        INSERT INTO tbl_payment_voucher_items (
                            voucher_id, payment_type, deposit_into, amount,
                            previous_balance_amount, status, created_at
                        ) VALUES (
                            $pq_pv_id,
                            " . ($pt !== '' ? "'$pt'" : 'NULL') . ",
                            " . ($dep !== '' ? "'$dep'" : 'NULL') . ",
                            $amt_line,
                            0,
                            1,
                            NOW()
                        )
                    ";
                    if (!mysqli_query($conn, $pvi_sql)) {
                        throw new Exception('Payment voucher item failed: ' . mysqli_error($conn));
                    }
                }
                $cap = 'Purchase Quotation ' . $quotation_no;
                purchase_invoice_post_auto_payment_voucher_ledger(
                    $conn,
                    $pq_pv_id,
                    $pq_payment_voucher_no,
                    $quotation_no,
                    $quotation_date,
                    $supplier_id,
                    $supplier_name,
                    $pq_money_payments,
                    $user_id,
                    $ref_no !== '' ? $ref_no : null,
                    $has_gold_pure_cols,
                    $cap,
                    $ledger_has_branch_col,
                    $ledger_branch_sql_val,
                    $ledger_br_scope
                );
            } elseif ($pv_chk) {
                mysqli_free_result($pv_chk);
            }
        }
    }

    $task_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_tasks'");
    if ($task_tbl && mysqli_num_rows($task_tbl) > 0) {
        mysqli_free_result($task_tbl);
        $title_esc = mysqli_real_escape_string($conn, 'Purchase Quotation Created');
        $desc_esc = mysqli_real_escape_string($conn, 'Purchase Quotation No : ' . $quotation_no);
        @mysqli_query($conn, "
            INSERT INTO tbl_tasks (title, description, module, reference_id, status, created_by, created_at)
            VALUES ('$title_esc', '$desc_esc', 'Purchase', $quotation_id, 1, " . ($user_id > 0 ? $user_id : 'NULL') . ', NOW())
        ');
    } elseif ($task_tbl) {
        mysqli_free_result($task_tbl);
    }

    if ((int) $quotation_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'purchase_quotation', (int) $quotation_id, $quotation_no, $quotation_date);
    }

    mysqli_commit($conn);

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Purchase Quotation',
        'verb' => $is_update ? 'updated' : 'created',
        'number' => $quotation_no,
        'party' => $supplier_name,
        'doc_date' => $quotation_date,
        'due_date' => $due_date,
        'ref_id' => (int) $quotation_id,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Purchase Quotation Created Successfully',
        'order_id' => $quotation_id,
        'quotation_id' => $quotation_id,
        'quotation_no' => $quotation_no,
        'order_no' => $quotation_no,
        'new_barcodes' => $metal_exchange_barcodes_out,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log('Purchase Quotation Save Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
