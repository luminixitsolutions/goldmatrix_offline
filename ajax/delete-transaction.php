<?php
session_start();
require_once '../config.php';
if (is_file(__DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php')) {
    require_once __DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Sale Fixing Direct: remove items + ledger, hard-delete SFD row, set linked PI fixing_type to Standard
if ($type === 'sale_fixing_direct') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $sf_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_fixing_direct'");
    if (!$sf_check || mysqli_num_rows($sf_check) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Table not found']);
        exit;
    }
    mysqli_free_result($sf_check);
    $sf = getRecord("SELECT id, against_of FROM tbl_sale_fixing_direct WHERE id = $id");
    if (!$sf) {
        echo json_encode(['status' => 'error', 'message' => 'Record not found']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        $against_of = $sf['against_of'] ?? '';
        $pi_no = function_exists('auragold_pi_invoice_from_sfd_against_of')
            ? auragold_pi_invoice_from_sfd_against_of($against_of)
            : '';
        // Auto-linked PI hedging posts Hedging Account rows as purchase_invoice — remove them when SFD is deleted (SFD has no separate HA ledger type).
        if ($pi_no !== '') {
            $pi_esc = mysqli_real_escape_string($conn, $pi_no);
            $pi_row = getRecord("SELECT id FROM tbl_purchase_invoices WHERE (status IS NULL OR status != 'deleted') AND (invoice_no = '$pi_esc' OR LOWER(TRIM(invoice_no)) = LOWER('$pi_esc')) LIMIT 1");
            if ($pi_row && (int)($pi_row['id'] ?? 0) > 0) {
                $pi_id_del = (int)$pi_row['id'];
                if (!mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE customer_name = 'Hedging Account' AND transaction_type = 'purchase_invoice' AND transaction_id = $pi_id_del AND status = 1")) {
                    throw new Exception('Hedging Account PI ledger: ' . mysqli_error($conn));
                }
            }
        }
        if (!mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'sale_fixing_direct' AND transaction_id = $id")) {
            throw new Exception('Ledger: ' . mysqli_error($conn));
        }
        $it_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_fixing_direct_items'");
        if ($it_tbl && mysqli_num_rows($it_tbl) > 0) {
            mysqli_free_result($it_tbl);
            if (!mysqli_query($conn, "DELETE FROM tbl_sale_fixing_direct_items WHERE fixing_id = $id")) {
                throw new Exception('Sale fixing items: ' . mysqli_error($conn));
            }
        } elseif ($it_tbl) {
            mysqli_free_result($it_tbl);
        }
        if (!mysqli_query($conn, "DELETE FROM tbl_sale_fixing_direct WHERE id = $id")) {
            throw new Exception(mysqli_error($conn));
        }
        if ($pi_no !== '') {
            $pi_esc = mysqli_real_escape_string($conn, $pi_no);
            mysqli_query($conn, "UPDATE tbl_purchase_invoices SET fixing_type = 'Standard' WHERE invoice_no = '$pi_esc' AND (status IS NULL OR status != 'deleted')");
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Sale fixing deleted. Linked purchase invoice set to Standard fixing.']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Purchase Fixing Direct (from sale invoice hedging): delete row; set linked sale invoice fixing_type to Standard
if ($type === 'purchase_fixing_direct') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $pf_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct'");
    if (!$pf_check || mysqli_num_rows($pf_check) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Table not found']);
        exit;
    }
    mysqli_free_result($pf_check);
    $pf_si_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'sale_invoice_no'");
    $pf_has_si = ($pf_si_col && mysqli_num_rows($pf_si_col) > 0);
    if ($pf_si_col) {
        mysqli_free_result($pf_si_col);
    }
    $sel = $pf_has_si ? 'id, sale_invoice_no, against_of' : 'id, against_of';
    $pf = getRecord("SELECT $sel FROM tbl_purchase_fixing_direct WHERE id = $id");
    if (!$pf) {
        echo json_encode(['status' => 'error', 'message' => 'Record not found']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        $pfi_del = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct_items'");
        if ($pfi_del && mysqli_num_rows($pfi_del) > 0) {
            mysqli_free_result($pfi_del);
            if (!mysqli_query($conn, "DELETE FROM tbl_purchase_fixing_direct_items WHERE fixing_id = $id")) {
                throw new Exception('Purchase fixing items: ' . mysqli_error($conn));
            }
        } elseif ($pfi_del) {
            mysqli_free_result($pfi_del);
        }
        if (!mysqli_query($conn, "DELETE FROM tbl_purchase_fixing_direct WHERE id = $id")) {
            throw new Exception(mysqli_error($conn));
        }
        $sino = trim((string) ($pf['sale_invoice_no'] ?? ''));
        if ($sino === '' && function_exists('auragold_si_invoice_from_pfd_against_of')) {
            $sino = auragold_si_invoice_from_pfd_against_of($pf['against_of'] ?? '');
        }
        if ($sino !== '') {
            $esc = mysqli_real_escape_string($conn, $sino);
            mysqli_query($conn, "UPDATE tbl_sale_invoices SET fixing_type = 'Standard' WHERE (status IS NULL OR status != 'deleted') AND (invoice_no = '$esc' OR LOWER(TRIM(invoice_no)) = LOWER('$esc'))");
            $si_map = getRecord("SELECT id FROM tbl_sale_invoices WHERE (status IS NULL OR status != 'deleted') AND (invoice_no = '$esc' OR LOWER(TRIM(invoice_no)) = LOWER('$esc')) LIMIT 1");
            if ($si_map && (int) ($si_map['id'] ?? 0) > 0) {
                @mysqli_query($conn, "DELETE FROM invoice_fixing_mapping WHERE source_type = 'sale_invoice' AND source_transaction_id = " . (int) $si_map['id']);
            }
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Purchase fixing deleted. Linked sale invoice set to Standard fixing.']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Standalone Old Jewelry Scrap Invoice (not auto-linked from purchase invoice ref_no PI:{id})
if ($type === 'old_jewelry_scrap_invoice') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $oj_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
    if (!$oj_chk || mysqli_num_rows($oj_chk) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Table not found']);
        exit;
    }
    mysqli_free_result($oj_chk);
    $oj = getRecord("SELECT id, ref_no, invoice_no, customer_name FROM tbl_old_jewelry_scrap_invoices WHERE id = $id");
    if (!$oj) {
        echo json_encode(['status' => 'error', 'message' => 'Record not found']);
        exit;
    }
    $rn = trim((string) ($oj['ref_no'] ?? ''));
    if ($rn !== '' && preg_match('/^PI:\d+$/i', $rn)) {
        echo json_encode(['status' => 'error', 'message' => 'This scrap invoice is linked to a Purchase Invoice. Remove the scrap payment on that invoice and save, or delete the purchase invoice.']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_id = $id AND transaction_type IN ('old_jewelry_scrap_invoice', 'old_jewelry_scrap_contra') AND status = 1");
        $ino_m = mysqli_real_escape_string($conn, trim((string) ($oj['invoice_no'] ?? '')));
        $cn_m = mysqli_real_escape_string($conn, trim((string) ($oj['customer_name'] ?? '')));
        if ($ino_m !== '' && $cn_m !== '') {
            mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE status = 1 AND customer_name = '$cn_m' AND transaction_no = '$ino_m' AND transaction_type IN ('old_jewelry_scrap_invoice', 'Old Jewelry - Scrap Invoice')");
        }
        if (!mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE id = $id")) {
            throw new Exception(mysqli_error($conn));
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Old jewelry scrap invoice deleted.']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Sale Order: block while Job Work Order exists; remove items/payments/issues then master.
if ($type === 'sale_order') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $so_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_orders'");
    if (!$so_chk || mysqli_num_rows($so_chk) == 0) {
        if ($so_chk) {
            mysqli_free_result($so_chk);
        }
        echo json_encode(['status' => 'error', 'message' => 'Table not found']);
        exit;
    }
    mysqli_free_result($so_chk);
    $so = getRecord('SELECT id, order_no FROM tbl_sale_orders WHERE id = ' . $id . ' LIMIT 1');
    if (!$so) {
        echo json_encode(['status' => 'error', 'message' => 'Sale order not found']);
        exit;
    }
    if (function_exists('auragold_sale_order_has_linked_jobwork_order')
        && auragold_sale_order_has_linked_jobwork_order($conn, $id)) {
        $tip = function_exists('auragold_sale_order_jobwork_save_blocked_tip')
            ? auragold_sale_order_jobwork_save_blocked_tip($conn, $id)
            : 'Delete Jobwork Queue records, then the Job Work Order, before deleting this sale order.';
        echo json_encode(['status' => 'error', 'message' => $tip]);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, 'DELETE FROM tbl_sale_order_items WHERE order_id = ' . $id);
        mysqli_query($conn, 'DELETE FROM tbl_sale_order_payments WHERE order_id = ' . $id);
        foreach (['tbl_sale_order_diamond_stock_issue', 'tbl_sale_order_stone_stock_issue'] as $iss_tbl) {
            $tq = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $iss_tbl) . "'");
            if ($tq && mysqli_num_rows($tq) > 0) {
                mysqli_free_result($tq);
                mysqli_query($conn, 'DELETE FROM `' . $iss_tbl . '` WHERE order_id = ' . $id);
            } elseif ($tq) {
                mysqli_free_result($tq);
            }
        }
        if (!mysqli_query($conn, 'DELETE FROM tbl_sale_orders WHERE id = ' . $id . ' LIMIT 1')) {
            throw new Exception(mysqli_error($conn));
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Sale order deleted successfully.']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Job Work Order: delete queue/items first (via shared helper), then master.
if ($type === 'jobwork_order') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    if (!function_exists('auragold_delete_jobwork_order_by_id')) {
        echo json_encode(['status' => 'error', 'message' => 'Delete helper not available']);
        exit;
    }
    $result = auragold_delete_jobwork_order_by_id($conn, $id);
    if (!empty($result['ok'])) {
        echo json_encode(['status' => 'success', 'message' => $result['message'] ?? 'Job work order deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Could not delete job work order.']);
    }
    exit;
}

// Payment Voucher: delete voucher, items, and ledger rows (Cash/Bank + party).
if ($type === 'payment_voucher') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $voucher = getRecord("SELECT id, customer_id, customer_name FROM tbl_payment_vouchers WHERE id = $id");
    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Payment voucher not found']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        $customer_id = (int) ($voucher['customer_id'] ?? 0);
        $customer_name = mysqli_real_escape_string($conn, $voucher['customer_name'] ?? '');
        if (!mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE transaction_type = 'payment_voucher' AND transaction_id = $id AND status = 1
        ")) {
            throw new Exception('Ledger delete failed: ' . mysqli_error($conn));
        }
        $t_ojb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
        if ($t_ojb && mysqli_num_rows($t_ojb) > 0) {
            mysqli_free_result($t_ojb);
            mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE comment LIKE '%[[PV_LINK_ID:" . (int) $id . "]]%'");
        } elseif ($t_ojb) {
            mysqli_free_result($t_ojb);
        }
        mysqli_query($conn, "DELETE FROM tbl_payment_voucher_items WHERE voucher_id = $id");
        if (!mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE id = $id")) {
            throw new Exception('Failed to delete payment voucher');
        }
        $has_balance_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_balance'");
        if ($has_balance_table && mysqli_num_rows($has_balance_table) > 0) {
            mysqli_free_result($has_balance_table);
            $last = null;
            if ($customer_id > 0) {
                $last = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_id = $customer_id AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            if (!$last && $customer_name !== '') {
                $last = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_name = '$customer_name' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            if ($last) {
                $amt = (float) ($last['balance_amount'] ?? 0);
                $gold = (float) ($last['balance_gold'] ?? 0);
                $silver = (float) ($last['balance_silver'] ?? 0);
                @mysqli_query($conn, "INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_updated)
                    VALUES ($customer_id, '$customer_name', $amt, $gold, $silver, NOW())
                    ON DUPLICATE KEY UPDATE balance_amount = $amt, balance_gold = $gold, balance_silver = $silver, last_updated = NOW()");
            }
        } elseif ($has_balance_table) {
            mysqli_free_result($has_balance_table);
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Payment voucher deleted successfully']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Journal Voucher: delete voucher, items, and ledger rows.
if ($type === 'journal_voucher' || $type === 'journal') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $voucher = getRecord("SELECT id, voucher_no FROM tbl_journal_vouchers WHERE id = $id");
    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Journal voucher not found']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        if (!mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE transaction_type = 'journal_voucher' AND transaction_id = $id AND status = 1
        ")) {
            throw new Exception('Ledger delete failed: ' . mysqli_error($conn));
        }
        mysqli_query($conn, "DELETE FROM tbl_journal_voucher_items WHERE voucher_id = $id");
        if (!mysqli_query($conn, "DELETE FROM tbl_journal_vouchers WHERE id = $id")) {
            throw new Exception('Failed to delete journal voucher');
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Journal voucher deleted successfully']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Contra Voucher: delete voucher, items, and ledger rows.
if ($type === 'contra_voucher' || $type === 'contra') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $voucher = getRecord("SELECT id, voucher_no FROM tbl_contra_vouchers WHERE id = $id");
    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Contra voucher not found']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        if (!mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE transaction_type = 'contra_voucher' AND transaction_id = $id AND status = 1
        ")) {
            throw new Exception('Ledger delete failed: ' . mysqli_error($conn));
        }
        mysqli_query($conn, "DELETE FROM tbl_contra_voucher_items WHERE voucher_id = $id");
        if (!mysqli_query($conn, "DELETE FROM tbl_contra_vouchers WHERE id = $id")) {
            throw new Exception('Failed to delete contra voucher');
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Contra voucher deleted successfully']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Receipt Voucher: delete voucher, items, and ledger rows (Cash/Bank + party).
if ($type === 'receipt_voucher') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }
    $voucher = getRecord("SELECT id, customer_id, customer_name FROM tbl_receipt_vouchers WHERE id = $id");
    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Receipt voucher not found']);
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        $customer_id = (int) ($voucher['customer_id'] ?? 0);
        $customer_name = mysqli_real_escape_string($conn, $voucher['customer_name'] ?? '');
        if (!mysqli_query($conn, "
            DELETE FROM tbl_customer_ledger
            WHERE transaction_type = 'receipt_voucher' AND transaction_id = $id AND status = 1
        ")) {
            throw new Exception('Ledger delete failed: ' . mysqli_error($conn));
        }
        $t_ojb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
        if ($t_ojb && mysqli_num_rows($t_ojb) > 0) {
            mysqli_free_result($t_ojb);
            mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE comment LIKE '%[[RV_LINK_ID:" . (int) $id . "]]%'");
        } elseif ($t_ojb) {
            mysqli_free_result($t_ojb);
        }
        mysqli_query($conn, "DELETE FROM tbl_receipt_voucher_items WHERE voucher_id = $id");
        if (!mysqli_query($conn, "DELETE FROM tbl_receipt_vouchers WHERE id = $id")) {
            throw new Exception('Failed to delete receipt voucher');
        }
        $has_balance_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_balance'");
        if ($has_balance_table && mysqli_num_rows($has_balance_table) > 0) {
            mysqli_free_result($has_balance_table);
            $last = null;
            if ($customer_id > 0) {
                $last = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_id = $customer_id AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            if (!$last && $customer_name !== '') {
                $last = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_name = '$customer_name' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
            }
            if ($last) {
                $amt = (float) ($last['balance_amount'] ?? 0);
                $gold = (float) ($last['balance_gold'] ?? 0);
                $silver = (float) ($last['balance_silver'] ?? 0);
                @mysqli_query($conn, "INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_updated)
                    VALUES ($customer_id, '$customer_name', $amt, $gold, $silver, NOW())
                    ON DUPLICATE KEY UPDATE balance_amount = $amt, balance_gold = $gold, balance_silver = $silver, last_updated = NOW()");
            }
        } elseif ($has_balance_table) {
            mysqli_free_result($has_balance_table);
        }
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Receipt voucher deleted successfully']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

$allowed_types = [
    'purchase_invoice' => 'tbl_purchase_invoices',
    'sale_invoice' => 'tbl_sale_invoices',
    'sale_return' => 'tbl_sale_returns',
    'purchase_return' => 'tbl_purchase_returns',
    'sale_quotation' => 'tbl_sale_quotations',
    'purchase_quotation' => 'tbl_purchase_quotations',
];

if ($id <= 0 || !isset($allowed_types[$type])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type or ID']);
    exit;
}

$table = $allowed_types[$type];

// Check table exists
$check = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
if (!$check || mysqli_num_rows($check) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Table not found']);
    exit;
}
if ($check) mysqli_free_result($check);

// Check if record exists and has status column
$col_check = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'status'");
$has_status = ($col_check && mysqli_num_rows($col_check) > 0);
if ($col_check) mysqli_free_result($col_check);

$record = getRecord("SELECT id FROM $table WHERE id = $id");
if (!$record) {
    echo json_encode(['status' => 'error', 'message' => 'Record not found']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    if ($type === 'sale_invoice') {
        $si_chk = getRecord("SELECT invoice_no FROM tbl_sale_invoices WHERE id = $id");
        if ($si_chk && !empty($si_chk['invoice_no']) && function_exists('auragold_si_has_active_purchase_fixing')) {
            if (auragold_si_has_active_purchase_fixing($si_chk['invoice_no'])) {
                throw new Exception('Delete the purchase fixing first, then you can delete this sale invoice.');
            }
        }
    }

    $pi_invoice_no_esc = '';
    if ($type === 'purchase_invoice') {
        $pi_row_chk = getRecord("SELECT invoice_no FROM tbl_purchase_invoices WHERE id = $id");
        if ($pi_row_chk && !empty($pi_row_chk['invoice_no']) && function_exists('auragold_pi_has_active_sale_fixing')) {
            if (auragold_pi_has_active_sale_fixing($pi_row_chk['invoice_no'])) {
                throw new Exception('Delete the sale fixing first, then you can delete this purchase invoice.');
            }
        }
        if ($pi_row_chk && !empty($pi_row_chk['invoice_no'])) {
            $pi_invoice_no_esc = mysqli_real_escape_string($conn, (string) $pi_row_chk['invoice_no']);
        }
    }

    // 1. Remove related customer ledger entries (so balances revert)
    $ledger_types = [];
    switch ($type) {
        case 'purchase_invoice':
            // "Purchase Invoice" = legacy type on some rows (e.g. old Purchase Account cash lines). payment_voucher ledgers removed by voucher id below.
            $ledger_types = ["'purchase_invoice'", "'Purchase Invoice'", "'previous_balance_payment'", "'payment'"];
            break;
        case 'sale_invoice':
            $ledger_types = ["'sale_invoice'", "'sale_revenue'", "'previous_balance_payment'", "'payment'"];
            break;
        case 'purchase_return':
            $ledger_types = ["'purchase_return'"];
            break;
        case 'sale_return':
            $ledger_types = ["'sale_return'"];
            break;
        case 'sale_quotation':
            $ledger_types = ["'sale_quotation'", "'sale_quotation_revenue'", "'quotation_payment'"];
            break;
        case 'purchase_quotation':
            $ledger_types = ["'purchase_quotation'", "'purchase_quotation_revenue'"];
            break;
    }
    if (!empty($ledger_types)) {
        $ledger_list = implode(',', $ledger_types);
        $del1 = "
            DELETE FROM tbl_customer_ledger
            WHERE transaction_type IN ($ledger_list) AND transaction_id = $id AND status = 1
        ";
        if (!mysqli_query($conn, $del1)) {
            throw new Exception('Ledger delete failed: ' . mysqli_error($conn));
        }
    }

    if ($type === 'sale_invoice') {
        @mysqli_query($conn, "DELETE FROM invoice_fixing_mapping WHERE source_type = 'sale_invoice' AND source_transaction_id = " . (int)$id);
        $si_row = getRecord("SELECT invoice_no FROM tbl_sale_invoices WHERE id = " . (int)$id);
        if ($si_row && !empty($si_row['invoice_no']) && function_exists('auragold_delete_purchase_fixing_direct_for_sale_invoice')) {
            auragold_delete_purchase_fixing_direct_for_sale_invoice($conn, (string) $si_row['invoice_no']);
        }
        if ($si_row && !empty($si_row['invoice_no'])) {
            $si_no_esc = mysqli_real_escape_string($conn, (string) $si_row['invoice_no']);
            if (function_exists('auragold_ensure_tbl_sale_receipt_vouchers')) {
                auragold_ensure_tbl_sale_receipt_vouchers($conn);
            }
            $srv_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
            if ($srv_chk && mysqli_num_rows($srv_chk) > 0) {
                mysqli_free_result($srv_chk);
                $srv_rows = getList("SELECT id FROM tbl_sale_receipt_vouchers WHERE sale_invoice_no = '$si_no_esc'");
                if (is_array($srv_rows)) {
                    foreach ($srv_rows as $srvr) {
                        $srvid = (int) ($srvr['id'] ?? 0);
                        if ($srvid <= 0) {
                            continue;
                        }
                        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'sale_receipt_voucher' AND transaction_id = $srvid AND status = 1");
                        mysqli_query($conn, "DELETE FROM tbl_sale_receipt_vouchers WHERE id = $srvid");
                    }
                }
            } elseif ($srv_chk) {
                mysqli_free_result($srv_chk);
            }
            $rv_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
            if ($rv_chk && mysqli_num_rows($rv_chk) > 0) {
                mysqli_free_result($rv_chk);
                $rv_rows = getList("SELECT id FROM tbl_receipt_vouchers WHERE ref_no = '$si_no_esc' AND voucher_type = 'Sale Invoice Payment'");
                if (is_array($rv_rows)) {
                    foreach ($rv_rows as $rvr) {
                        $rvid = (int) ($rvr['id'] ?? 0);
                        if ($rvid <= 0) {
                            continue;
                        }
                        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'receipt_voucher' AND transaction_id = $rvid AND status = 1");
                        mysqli_query($conn, "DELETE FROM tbl_receipt_voucher_items WHERE voucher_id = $rvid");
                        mysqli_query($conn, "DELETE FROM tbl_receipt_vouchers WHERE id = $rvid");
                    }
                }
            } elseif ($rv_chk) {
                mysqli_free_result($rv_chk);
            }
        }
        $si_ref_scrap = 'SI:' . (int) $id;
        $si_ref_esc = mysqli_real_escape_string($conn, $si_ref_scrap);
        $ojb_si_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
        if ($ojb_si_chk && mysqli_num_rows($ojb_si_chk) > 0) {
            mysqli_free_result($ojb_si_chk);
            $ojb_si_rows = getList("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE ref_no = '$si_ref_esc'");
            if (is_array($ojb_si_rows)) {
                foreach ($ojb_si_rows as $ojbs) {
                    $ojbid = (int) ($ojbs['id'] ?? 0);
                    if ($ojbid <= 0) {
                        continue;
                    }
                    mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE status = 1 AND transaction_type IN ('old_jewelry_scrap_invoice', 'old_jewelry_scrap_contra') AND transaction_id = $ojbid");
                    $ojb_meta = getRecord("SELECT invoice_no, customer_name FROM tbl_old_jewelry_scrap_invoices WHERE id = $ojbid LIMIT 1");
                    if ($ojb_meta) {
                        $ino_m = esc(trim((string) ($ojb_meta['invoice_no'] ?? '')));
                        $cn_m = esc(trim((string) ($ojb_meta['customer_name'] ?? '')));
                        if ($ino_m !== '' && $cn_m !== '') {
                            mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE status = 1 AND customer_name = '$cn_m' AND transaction_no = '$ino_m' AND transaction_type IN ('old_jewelry_scrap_invoice', 'Old Jewelry - Scrap Invoice')");
                        }
                    }
                    mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $ojbid");
                    mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE id = $ojbid");
                }
            }
        } elseif ($ojb_si_chk) {
            mysqli_free_result($ojb_si_chk);
        }

        // Child rows, stock reversal, and stock history (mirrors ajax/save-sale-invoice.php on edit — soft-delete alone left orphans)
        $invoice_id_si = (int) $id;
        $tbl_stock_has_reference_si = false;
        $__stk_ref_del = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        $__stk_ref_del_n = ($__stk_ref_del ? mysqli_num_rows($__stk_ref_del) : 0);
        if ($__stk_ref_del) {
            mysqli_free_result($__stk_ref_del);
        }
        if ($__stk_ref_del_n >= 2) {
            $tbl_stock_has_reference_si = true;
        }
        if ($tbl_stock_has_reference_si) {
            $rev_rows = getList("SELECT barcode, opening_weight, opening_qty, product_id, product_characteristic_id, branch_id FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = $invoice_id_si AND reference_type = 'sale_invoice'");
            if (is_array($rev_rows)) {
                foreach ($rev_rows as $rv) {
                    $ow = (float) ($rv['opening_weight'] ?? 0);
                    $oq = (float) ($rv['opening_qty'] ?? 0);
                    if ($ow <= 0 && $oq <= 0) {
                        continue;
                    }
                    $b = trim((string) ($rv['barcode'] ?? ''));
                    $target_id = 0;
                    if ($b !== '') {
                        $be = mysqli_real_escape_string($conn, $b);
                        $tr = getRecord("SELECT id FROM tbl_stock WHERE barcode = '$be' AND status = 1 AND stock_type IN ('inward','balance') ORDER BY id DESC LIMIT 1");
                        if ($tr && !empty($tr['id'])) {
                            $target_id = (int) $tr['id'];
                        }
                    }
                    if ($target_id <= 0) {
                        $pid = (int) ($rv['product_id'] ?? 0);
                        $bid = (int) ($rv['branch_id'] ?? 0);
                        if ($pid > 0 && $bid > 0) {
                            $cid_raw = $rv['product_characteristic_id'] ?? null;
                            if ($cid_raw !== null && $cid_raw !== '') {
                                $cid = (int) $cid_raw;
                                $tr = getRecord("SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id = $cid AND branch_id = $bid AND status = 1 AND stock_type IN ('inward','balance') ORDER BY id DESC LIMIT 1");
                            } else {
                                $tr = getRecord("SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id IS NULL AND branch_id = $bid AND status = 1 AND stock_type IN ('inward','balance') ORDER BY id DESC LIMIT 1");
                            }
                            if ($tr && !empty($tr['id'])) {
                                $target_id = (int) $tr['id'];
                            }
                        }
                    }
                    if ($target_id > 0) {
                        if (!mysqli_query($conn, "UPDATE tbl_stock SET current_weight = COALESCE(current_weight, 0) + $ow, current_qty = COALESCE(current_qty, 0) + $oq WHERE id = $target_id")) {
                            throw new Exception('Stock restore failed: ' . mysqli_error($conn));
                        }
                    }
                }
            }
            if (!mysqli_query($conn, "DELETE FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = $invoice_id_si AND reference_type = 'sale_invoice'")) {
                throw new Exception('Outward stock delete failed: ' . mysqli_error($conn));
            }
        }
        $sj_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal'");
        if ($sj_tbl && mysqli_num_rows($sj_tbl) > 0) {
            mysqli_free_result($sj_tbl);
            if (!mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=si|iid=" . $invoice_id_si . "|%'")) {
                throw new Exception('Stock journal delete failed: ' . mysqli_error($conn));
            }
        } elseif ($sj_tbl) {
            mysqli_free_result($sj_tbl);
        }
        if (!mysqli_query($conn, "DELETE FROM tbl_sale_invoice_payments WHERE invoice_id = $invoice_id_si")) {
            throw new Exception('Sale invoice payments delete failed: ' . mysqli_error($conn));
        }
        if (!mysqli_query($conn, "DELETE FROM tbl_sale_invoice_items WHERE invoice_id = $invoice_id_si")) {
            throw new Exception('Sale invoice items delete failed: ' . mysqli_error($conn));
        }
        $ew_log_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_ewaybill_generate_logs'");
        if ($ew_log_chk && mysqli_num_rows($ew_log_chk) > 0) {
            mysqli_free_result($ew_log_chk);
            @mysqli_query($conn, "DELETE FROM tbl_ewaybill_generate_logs WHERE invoice_id = $invoice_id_si");
        } elseif ($ew_log_chk) {
            mysqli_free_result($ew_log_chk);
        }
    }

    // Purchase invoice: remove ledgers tied to auto payment vouchers (ref = invoice no) and any orphan rows matching invoice number.
    if ($type === 'purchase_invoice' && $pi_invoice_no_esc !== '') {
        $pv_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
        if ($pv_tbl && mysqli_num_rows($pv_tbl) > 0) {
            mysqli_free_result($pv_tbl);
            $pv_rows = getList("SELECT id FROM tbl_payment_vouchers WHERE ref_no = '$pi_invoice_no_esc'");
            if (is_array($pv_rows) && count($pv_rows) > 0) {
                $pv_ids = [];
                foreach ($pv_rows as $pr) {
                    $vid = (int)($pr['id'] ?? 0);
                    if ($vid > 0) {
                        $pv_ids[] = $vid;
                    }
                }
                if (count($pv_ids) > 0) {
                    $in_list = implode(',', $pv_ids);
                    $del_pv_led = "
                        DELETE FROM tbl_customer_ledger
                        WHERE status = 1 AND transaction_type = 'payment_voucher' AND transaction_id IN ($in_list)
                    ";
                    if (!mysqli_query($conn, $del_pv_led)) {
                        throw new Exception('Payment voucher ledger delete failed: ' . mysqli_error($conn));
                    }
                }
            }
            if (!mysqli_query($conn, "
                DELETE pvi FROM tbl_payment_voucher_items pvi
                INNER JOIN tbl_payment_vouchers pv ON pvi.voucher_id = pv.id
                WHERE pv.ref_no = '$pi_invoice_no_esc'
            ")) {
                throw new Exception('Payment voucher items delete failed: ' . mysqli_error($conn));
            }
            if (!mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE ref_no = '$pi_invoice_no_esc'")) {
                throw new Exception('Payment voucher header delete failed: ' . mysqli_error($conn));
            }
        } elseif ($pv_tbl) {
            mysqli_free_result($pv_tbl);
        }
        // Rows posted with transaction_no = bill no (Purchase Account, Hedging, supplier, bank lines that still used PRIx, etc.).
        $del_by_no = "
            DELETE FROM tbl_customer_ledger
            WHERE status = 1 AND transaction_no = '$pi_invoice_no_esc'
            AND transaction_type IN ('purchase_invoice', 'Purchase Invoice', 'previous_balance_payment', 'payment')
        ";
        if (!mysqli_query($conn, $del_by_no)) {
            throw new Exception('Ledger delete by invoice no failed: ' . mysqli_error($conn));
        }
    }

    // Sale quotation: remove auto receipt vouchers (ref = quotation_no, Sale Quotation Payment)
    if ($type === 'sale_quotation') {
        $sq_row = getRecord("SELECT quotation_no FROM tbl_sale_quotations WHERE id = $id");
        if ($sq_row && !empty($sq_row['quotation_no'])) {
            $sq_ref_esc = mysqli_real_escape_string($conn, (string) $sq_row['quotation_no']);
            $rv_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
            if ($rv_chk && mysqli_num_rows($rv_chk) > 0) {
                mysqli_free_result($rv_chk);
                $rv_rows = getList("SELECT id FROM tbl_receipt_vouchers WHERE ref_no = '$sq_ref_esc' AND voucher_type = 'Sale Quotation Payment'");
                if (is_array($rv_rows)) {
                    foreach ($rv_rows as $rvr) {
                        $rvid = (int) ($rvr['id'] ?? 0);
                        if ($rvid <= 0) {
                            continue;
                        }
                        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'receipt_voucher' AND transaction_id = $rvid AND status = 1");
                        mysqli_query($conn, "DELETE FROM tbl_receipt_voucher_items WHERE voucher_id = $rvid");
                        mysqli_query($conn, "DELETE FROM tbl_receipt_vouchers WHERE id = $rvid");
                    }
                }
            } elseif ($rv_chk) {
                mysqli_free_result($rv_chk);
            }
        }
    }

    // Purchase quotation: remove auto payment vouchers (ref = quotation_no)
    if ($type === 'purchase_quotation') {
        $pq_row = getRecord("SELECT quotation_no FROM tbl_purchase_quotations WHERE id = $id");
        if ($pq_row && !empty($pq_row['quotation_no'])) {
            $pq_ref_esc = mysqli_real_escape_string($conn, (string) $pq_row['quotation_no']);
            $pv_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_chk && mysqli_num_rows($pv_chk) > 0) {
                mysqli_free_result($pv_chk);
                $pv_rows = getList("SELECT id FROM tbl_payment_vouchers WHERE ref_no = '$pq_ref_esc' AND voucher_type = 'Purchase Quotation Payment'");
                if (is_array($pv_rows)) {
                    foreach ($pv_rows as $pvr) {
                        $pvid = (int) ($pvr['id'] ?? 0);
                        if ($pvid <= 0) {
                            continue;
                        }
                        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'payment_voucher' AND transaction_id = $pvid AND status = 1");
                        mysqli_query($conn, "DELETE FROM tbl_payment_voucher_items WHERE voucher_id = $pvid");
                        mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE id = $pvid");
                    }
                }
            } elseif ($pv_chk) {
                mysqli_free_result($pv_chk);
            }
        }
    }

    // Sale return: remove auto payment vouchers (ref = return_no) and their ledger rows
    if ($type === 'sale_return') {
        $sr_row = getRecord("SELECT return_no FROM tbl_sale_returns WHERE id = $id");
        if ($sr_row && !empty($sr_row['return_no'])) {
            $sr_ref_esc = mysqli_real_escape_string($conn, (string)$sr_row['return_no']);
            $pv_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
            if ($pv_tbl && mysqli_num_rows($pv_tbl) > 0) {
                mysqli_free_result($pv_tbl);
                $pv_rows = getList("SELECT id FROM tbl_payment_vouchers WHERE ref_no = '$sr_ref_esc'");
                if (is_array($pv_rows) && count($pv_rows) > 0) {
                    $pv_ids = [];
                    foreach ($pv_rows as $pr) {
                        $vid = (int)($pr['id'] ?? 0);
                        if ($vid > 0) {
                            $pv_ids[] = $vid;
                        }
                    }
                    if (count($pv_ids) > 0) {
                        $in_list = implode(',', $pv_ids);
                        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE status = 1 AND transaction_type = 'payment_voucher' AND transaction_id IN ($in_list)");
                    }
                }
                mysqli_query($conn, "
                    DELETE pvi FROM tbl_payment_voucher_items pvi
                    INNER JOIN tbl_payment_vouchers pv ON pvi.voucher_id = pv.id
                    WHERE pv.ref_no = '$sr_ref_esc'
                ");
                mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE ref_no = '$sr_ref_esc'");
            } elseif ($pv_tbl) {
                mysqli_free_result($pv_tbl);
            }
        }
    }

    // 2. Soft-delete the main record
    if ($has_status) {
        $esc_status = mysqli_real_escape_string($conn, 'deleted');
        if (!mysqli_query($conn, "UPDATE $table SET status = '$esc_status' WHERE id = $id")) {
            throw new Exception('Failed to delete record: ' . mysqli_error($conn));
        }
    } else {
        // Table has no status column - hard delete (use only if your schema has no status)
        if (!mysqli_query($conn, "DELETE FROM $table WHERE id = $id")) {
            throw new Exception('Failed to delete record: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Transaction deleted successfully']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
