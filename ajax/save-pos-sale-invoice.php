<?php
/** When true: skip e-Way prerequisites and WhiteBooks generation — normal sale invoice save only. */
if (!defined('AURAGOLD_EWAY_DISABLED')) {
    define('AURAGOLD_EWAY_DISABLED', false);
}

require_once __DIR__ . '/../includes/session_init.php';
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold-gst.php';
if (is_file(__DIR__ . '/../includes/ewaybill_api_helper.php')) {
    require_once __DIR__ . '/../includes/ewaybill_api_helper.php';
}
require_once __DIR__ . '/../includes/auragold_pos_sale_invoice_schema.php';
if (function_exists('auragold_ensure_pos_sale_invoice_tables')) {
    auragold_ensure_pos_sale_invoice_tables($conn);
}
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';
require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';

header('Content-Type: application/json');
ob_start();

/**
 * Save sale invoice item images (data URLs) to uploads folder and return JSON of paths.
 * $group_image: JSON string {"primary":"data:...","images":["data:..."]} or single data URL string.
 * Returns JSON string for DB: {"primary":"uploads/pos-sale-invoice/1/item_5_0.png","images":["..."]} or empty string.
 */
function save_sale_invoice_item_images($group_image, $invoice_id, $item_id) {
    if (empty($group_image) || $invoice_id <= 0 || $item_id <= 0) return '';
    $data_urls = [];
    $primary_index = 0;
    if (is_string($group_image) && trim($group_image) !== '') {
        $trimmed = trim($group_image);
        if ($trimmed[0] === '{') {
            $dec = @json_decode($trimmed, true);
            if ($dec && !empty($dec['images']) && is_array($dec['images'])) {
                $data_urls = $dec['images'];
                $p = isset($dec['primary']) ? $dec['primary'] : ($data_urls[0] ?? '');
                $idx = array_search($p, $data_urls, true);
                $primary_index = ($idx !== false) ? $idx : 0;
            } elseif ($dec && !empty($dec['primary'])) {
                $data_urls = [ $dec['primary'] ];
            }
        } else {
            if (preg_match('/^data:image\//', $trimmed)) $data_urls = [ $trimmed ];
        }
    }
    if (empty($data_urls)) return '';

    $base_dir = dirname(__DIR__) . '/uploads/pos-sale-invoice/' . (int)$invoice_id;
    if (!is_dir($base_dir)) {
        if (!@mkdir($base_dir, 0755, true)) return '';
    }
    $paths = [];
    $primary_path = '';
    foreach ($data_urls as $i => $data_url) {
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/s', trim($data_url), $m)) continue;
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
        $safe_ext = in_array($ext, ['png','jpg','jpeg','gif','webp']) ? $ext : 'png';
        $filename = 'item_' . $item_id . '_' . $i . '.' . $safe_ext;
        $full_path = $base_dir . '/' . $filename;
        $b64 = preg_replace('/\s+/', '', $m[2]);
        $bin = @base64_decode($b64, true);
        if ($bin === false || @file_put_contents($full_path, $bin) === false) continue;
        $relative = 'uploads/pos-sale-invoice/' . (int)$invoice_id . '/' . $filename;
        $paths[] = $relative;
        if ($i === $primary_index) $primary_path = $relative;
    }
    if (empty($paths)) return '';
    if ($primary_path === '') $primary_path = $paths[0];
    return json_encode(['primary' => $primary_path, 'images' => $paths]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

/**
 * Merge JSON from tbl_pos_sale_invoice_payments.payment_details into the row (edit/save round-trip).
 */
if (!function_exists('sale_invoice_payment_merge_stored_details')) {
    function sale_invoice_payment_merge_stored_details(array $payment): array
    {
        return auragold_payment_merge_stored_details($payment);
    }
}

/**
 * Detect Metal Exchange line + resolved gross/pure wt (used for RV items + customer ledger credit gold).
 */
if (!function_exists('sale_invoice_metal_exchange_resolve')) {
    /**
     * @return array{is_me: bool, gross: float, pure: float, metal_id: int, qty: float, is_silver: bool}
     */
    function sale_invoice_metal_exchange_resolve($conn, array $payment): array
    {
        return auragold_metal_exchange_resolve($conn, $payment);
    }
}

/**
 * Metal Exchange on Sale Invoice party line: post weight on CREDIT side (mirror of purchase party DEBIT).
 */
if (!function_exists('sale_invoice_metal_exchange_ledger_wts')) {
    function sale_invoice_metal_exchange_ledger_wts($conn, array $payment) {
        $out = ['dg' => 0.0, 'cg' => 0.0, 'dgp' => 0.0, 'cgp' => 0.0, 'ds' => 0.0, 'cs' => 0.0];
        $r = sale_invoice_metal_exchange_resolve($conn, $payment);
        if (!$r['is_me']) {
            return $out;
        }
        if ($r['is_silver']) {
            $out['cs'] = $r['gross'];
        } else {
            $out['cg'] = $r['gross'];
            $out['cgp'] = $r['pure'];
        }

        return $out;
    }
}

if (!function_exists('sale_invoice_validate_metal_exchange_payments')) {
    /**
     * Metal exchange lines must use a real tbl_product_characteristics row for the chosen metal.
     *
     * @param array<int, array<string, mixed>> $payments
     *
     * @throws Exception
     */
    function sale_invoice_validate_metal_exchange_payments($conn, array $payments): void
    {
        foreach ($payments as $payment) {
            $payment = sale_invoice_payment_merge_stored_details($payment);
            if (!auragold_payment_is_metal_exchange_inward($conn, $payment)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $payment);
        }
    }
}

if (!function_exists('sale_invoice_validate_scrap_payments')) {
    /**
     * Scrap payment lines must use a real tbl_product_characteristics row for the chosen metal.
     *
     * @param array<int, array<string, mixed>> $payments
     *
     * @throws Exception
     */
    function sale_invoice_validate_scrap_payments($conn, array $payments): void
    {
        foreach ($payments as $payment) {
            $payment = sale_invoice_payment_merge_stored_details($payment);
            $amt = (float) ($payment['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            $pt = strtolower(trim((string) ($payment['payment_type'] ?? '')));
            $dep = strtolower(trim((string) ($payment['deposit_into'] ?? '')));
            $is_scrap = (strpos($pt, 'scrap') !== false) || ($dep === 'scrap');
            if (!$is_scrap) {
                continue;
            }
            $mid = (int) ($payment['scrap_metal_id'] ?? $payment['metal_id'] ?? 0);
            $pcid = (int) ($payment['scrap_product_id'] ?? $payment['product_id'] ?? 0);
            if ($mid <= 0) {
                throw new Exception('Scrap payment: select a metal.');
            }
            if ($pcid <= 0) {
                throw new Exception('Scrap payment: select a product from the search list (custom product names are not allowed).');
            }
            $row = getRecord(
                'SELECT pc.id FROM tbl_product_characteristics pc '
                . 'INNER JOIN tbl_products p ON p.id = pc.product_id '
                . "WHERE pc.id = $pcid AND pc.metal_id = $mid AND p.status = 1 AND pc.status = 1 LIMIT 1"
            );
            if (!$row) {
                throw new Exception('Scrap payment: the selected product is not valid for this metal or is inactive. Choose a product from the list.');
            }
        }
    }
}

if (!function_exists('sale_invoice_payment_is_auto_receipt_money')) {
    /** Cash / Bank / UPI / Card / Metal Exchange (same RV) — not Scrap (legacy payment lines on sale invoice no). */
    function sale_invoice_payment_is_auto_receipt_money(array $payment): bool
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

if (!function_exists('sale_invoice_delete_auto_receipt_vouchers_for_refs')) {
    /** Removes auto sale receipt vouchers (tbl_sale_receipt_vouchers) and legacy tbl_receipt_vouchers sale rows. */
    function sale_invoice_delete_auto_receipt_vouchers_for_refs($conn, array $ref_numbers): void
    {
        $refs = array_values(array_unique(array_filter(array_map('trim', $ref_numbers))));
        if (empty($refs)) {
            return;
        }
        if (function_exists('auragold_ensure_tbl_sale_receipt_vouchers')) {
            auragold_ensure_tbl_sale_receipt_vouchers($conn);
        }
        $in = [];
        foreach ($refs as $r) {
            $in[] = "'" . mysqli_real_escape_string($conn, $r) . "'";
        }
        $in_sql = implode(',', $in);

        $chk_srv = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
        if ($chk_srv && mysqli_num_rows($chk_srv) > 0) {
            mysqli_free_result($chk_srv);
            $rows = getList("SELECT id FROM tbl_sale_receipt_vouchers WHERE sale_invoice_no IN ($in_sql)");
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $vid = (int) ($row['id'] ?? 0);
                    if ($vid <= 0) {
                        continue;
                    }
                    mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_type = 'sale_receipt_voucher' AND transaction_id = $vid AND status = 1");
                    mysqli_query($conn, "DELETE FROM tbl_sale_receipt_vouchers WHERE id = $vid");
                }
            }
        } elseif ($chk_srv) {
            mysqli_free_result($chk_srv);
        }

        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }

            return;
        }
        mysqli_free_result($chk);
        $rows = getList("SELECT id FROM tbl_receipt_vouchers WHERE ref_no IN ($in_sql) AND voucher_type = 'Sale Invoice Payment'");
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

if (!function_exists('sale_invoice_create_auto_receipt_voucher_and_post_ledger')) {
    /**
     * One sale receipt voucher per sale (money lines) in tbl_sale_receipt_vouchers; ledger transaction_type sale_receipt_voucher.
     * Voucher number from bill series "Sale Receipt Voucher" (SRV- / bill-series.php).
     *
     * @param array<int, array<string, mixed>> $payments_money
     *
     * @throws Exception
     */
    function sale_invoice_create_auto_receipt_voucher_and_post_ledger(
        $conn,
        string $invoice_no,
        string $invoice_date,
        int $customer_id,
        string $customer_name,
        string $currency,
        string $sales_person,
        array $payments_money,
        int $user_id,
        ?string $ref_no,
        bool $ledger_has_branch = false,
        int $ledger_branch_id = 0
    ): void {
        if (empty($payments_money)) {
            return;
        }

        if (function_exists('auragold_ensure_tbl_sale_receipt_vouchers')) {
            auragold_ensure_tbl_sale_receipt_vouchers($conn);
        }

        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            throw new Exception('Sale receipt voucher header table is missing or not accessible after schema bootstrap.');
        }
        mysqli_free_result($chk);
        $chk2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_voucher_items'");
        if (!$chk2 || mysqli_num_rows($chk2) === 0) {
            if ($chk2) {
                mysqli_free_result($chk2);
            }
            throw new Exception('Sale receipt voucher item table is missing or not accessible after schema bootstrap.');
        }
        mysqli_free_result($chk2);

        $total_amount = 0.0;
        foreach ($payments_money as $p) {
            $total_amount += (float) ($p['current_order_amount'] ?? $p['amount'] ?? 0);
        }
        if ($total_amount <= 0.00001) {
            return;
        }

        $sum_cg = 0.0;
        $sum_cs = 0.0;
        $sum_cgp = 0.0;
        foreach ($payments_money as $pme) {
            $_mw = sale_invoice_metal_exchange_ledger_wts($conn, $pme);
            $sum_cg += (float) $_mw['cg'];
            $sum_cs += (float) $_mw['cs'];
            $sum_cgp += (float) $_mw['cgp'];
        }

        $voucher_no = function_exists('getNextSaleReceiptVoucherNo')
            ? getNextSaleReceiptVoucherNo($conn)
            : 'SRV-1';
        $voucher_no_esc = mysqli_real_escape_string($conn, $voucher_no);
        $inv_date_esc = mysqli_real_escape_string($conn, $invoice_date);
        $curr_esc = mysqli_real_escape_string($conn, $currency !== '' ? $currency : 'AED');
        $sp_esc = $sales_person !== '' ? "'" . mysqli_real_escape_string($conn, $sales_person) . "'" : 'NULL';
        $ref_sql = $ref_no !== null && $ref_no !== '' ? "'" . mysqli_real_escape_string($conn, $ref_no) . "'" : 'NULL';
        $comment_esc = mysqli_real_escape_string($conn, 'Receipt against Sale Invoice: ' . $invoice_no);
        $uid_sql = $user_id > 0 ? (string) $user_id : 'NULL';
        $inv_no_esc = mysqli_real_escape_string($conn, $invoice_no);
        $srv_branch_col = '';
        $srv_branch_val = '';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_receipt_vouchers', 'branch_id')) {
            $srv_branch_col = ', branch_id';
            $srv_branch_val = ', ' . ($ledger_branch_id > 0 ? (string) (int) $ledger_branch_id : 'NULL');
        }

        $ins_h = "
            INSERT INTO tbl_sale_receipt_vouchers (
                voucher_no, customer_id, customer_name, sale_invoice_no, against,
                sales_person, currency, voucher_date, fixing_type,
                previous_balance, previous_gold, previous_silver,
                total_amount, total_gold, total_silver, comment, status, created_by, created_at
                $srv_branch_col
            ) VALUES (
                '$voucher_no_esc',
                " . ($customer_id > 0 ? $customer_id : 'NULL') . ",
                '$customer_name',
                '$inv_no_esc',
                'Sale Invoice',
                $sp_esc,
                '$curr_esc',
                '$inv_date_esc',
                'Standard',
                0, 0, 0,
                $total_amount, " . (float) $sum_cg . ', ' . (float) $sum_cs . ",
                '$comment_esc',
                'saved',
                $uid_sql,
                NOW()
                $srv_branch_val
            )
        ";
        if (!mysqli_query($conn, $ins_h)) {
            throw new Exception('Sale receipt voucher header failed: ' . mysqli_error($conn));
        }
        $voucher_id = (int) mysqli_insert_id($conn);
        if ($voucher_id <= 0) {
            throw new Exception('Sale receipt voucher id missing after insert');
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
            $mx = sale_invoice_metal_exchange_resolve($conn, $payment);
            $is_me = $mx['is_me'];
            $qty_me = $is_me ? $mx['qty'] : 0.0;
            $gross_w = $is_me ? $mx['gross'] : 0.0;
            $pure_w = $is_me ? $mx['pure'] : 0.0;
            $mid_me = $is_me ? (int) $mx['metal_id'] : 0;
            $purity_carat_esc = esc($payment['purity_carat'] ?? '');
            $wt_sql = $is_me ? (string) $gross_w : '0';
            $mid_sql = ($is_me && $mid_me > 0) ? (string) $mid_me : 'NULL';
            $qty_sql = $is_me ? (string) $qty_me : '0';
            $purity_carat_sql = ($is_me && $purity_carat_esc !== '') ? "'$purity_carat_esc'" : 'NULL';
            $pure_wt_sql = $is_me ? (string) $pure_w : '0';
            $ins_i = "
                INSERT INTO tbl_sale_receipt_voucher_items (
                    sale_receipt_voucher_id, payment_type, diamond_category, transaction_no, deposit_into,
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
                    $wt_sql, $mid_sql, $qty_sql, $purity_carat_sql, $pure_wt_sql,
                    $amt, 0,
                    1, NOW()
                )
            ";
            if (!mysqli_query($conn, $ins_i)) {
                throw new Exception('Sale receipt voucher item failed: ' . mysqli_error($conn));
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
            if ($dep_raw === '' && (($pt === 'metal_exchange') || strpos($pt, 'metal-exchange') !== false || strpos($pt, 'm. exch') !== false || (strpos($pt, 'metal') !== false && strpos($pt, 'exch') !== false))) {
                $dep_raw = 'Metal Exchange';
            }
            if (auragold_payment_is_cheque_type($pt)) {
                $party_against_parts[] = auragold_cheque_payment_against_label($line_amt, 'receivable');
            } elseif ($dep_raw !== '') {
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
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
        }
        if (!$last_balance && $customer_name !== '') {
            $cols = $use_gold_pure ? 'balance_amount, balance_gold, balance_silver, balance_gold_pure' : 'balance_amount, balance_gold, balance_silver';
            $last_balance = getRecord("
                SELECT $cols FROM tbl_customer_ledger
                WHERE customer_name = '$customer_name' AND status = 1
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
        }
        $prev_amt = (float) ($last_balance['balance_amount'] ?? 0);
        $prev_gold = (float) ($last_balance['balance_gold'] ?? 0);
        $prev_silver = (float) ($last_balance['balance_silver'] ?? 0);
        $prev_gold_pure = $use_gold_pure ? (float) ($last_balance['balance_gold_pure'] ?? 0) : 0.0;

        $new_balance_amt_final = $prev_amt - $total_amount;
        $new_balance_gold = $prev_gold - $sum_cg;
        $new_balance_silver = $prev_silver - $sum_cs;
        $new_balance_gold_pure = $prev_gold_pure - $sum_cgp;

        $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $ledger_has_against = ($has_against && mysqli_num_rows($has_against) > 0);
        if ($has_against) {
            mysqli_free_result($has_against);
        }
        $against_cols = $ledger_has_against ? ', against_ledger, against_invoice_no' : '';
        $against_vals = '';
        if ($ledger_has_against) {
            $against_vals = $party_against_display !== ''
                ? ", '" . mysqli_real_escape_string($conn, $party_against_display) . "', '$invoice_no'"
                : ', NULL, NULL';
        }

        $rv_desc_base = 'Sale Receipt Voucher: ' . $voucher_no . ' (Sale ' . $invoice_no . ')';
        if ($sum_cg > 0.00001 || $sum_cs > 0.00001) {
            $rv_desc_base .= ' — Metal Exchange';
        }
        $desc_esc = mysqli_real_escape_string($conn, $rv_desc_base);

        $lb_col = $ledger_has_branch ? ', branch_id' : '';
        $lb_val = '';
        if ($ledger_has_branch) {
            $lb_val = $ledger_branch_id > 0 ? ', ' . (int) $ledger_branch_id : ', NULL';
        }

        if ($use_gold_pure) {
            $ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id$lb_col, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_gold_pure, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    $ledger_customer_id$lb_val,
                    '$customer_name',
                    'sale_receipt_voucher',
                    $voucher_id,
                    '$voucher_no_esc',
                    '$inv_date_esc',
                    0,
                    $total_amount,
                    0, " . (float) $sum_cg . ', 0, ' . (float) $sum_cgp . ', 0, ' . (float) $sum_cs . ",
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
                    customer_id$lb_col, customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, debit_silver, credit_silver,
                    balance_amount, balance_gold, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    $ledger_customer_id$lb_val,
                    '$customer_name',
                    'sale_receipt_voucher',
                    $voucher_id,
                    '$voucher_no_esc',
                    '$inv_date_esc',
                    0,
                    $total_amount,
                    0, " . (float) $sum_cg . ', 0, ' . (float) $sum_cs . ",
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
            throw new Exception('Sale receipt voucher party ledger failed: ' . mysqli_error($conn));
        }

        foreach ($payments_money as $item) {
            $pt_raw = strtolower(trim((string) ($item['payment_type'] ?? 'cash')));
            $line_amt = (float) ($item['current_order_amount'] ?? $item['amount'] ?? 0);
            $dep_raw = trim((string) ($item['deposit_into'] ?? ''));
            if ($dep_raw === '' && $pt_raw === 'cash') {
                $dep_raw = 'Cash';
            }
            if ($dep_raw === '' && (($pt_raw === 'metal_exchange') || strpos($pt_raw, 'm. exch') !== false || (strpos($pt_raw, 'metal') !== false && strpos($pt_raw, 'exch') !== false))) {
                $dep_raw = 'Metal Exchange';
            }
            if ($line_amt <= 0.00001 || $dep_raw === '') {
                continue;
            }
            if (auragold_payment_is_cheque_type($pt_raw)) {
                continue;
            }
            $dep_esc = esc($dep_raw);
            $cash_balance_record = getRecord("
                SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$dep_esc' AND status = 1
                ORDER BY transaction_date DESC, id DESC LIMIT 1
            ");
            $cash_prev_balance = (float) ($cash_balance_record['balance_amount'] ?? 0);
            $cash_new_balance = $cash_prev_balance + $line_amt;
            $cash_desc_esc = mysqli_real_escape_string($conn, 'Receipt from ' . $customer_name . ' (Sale Receipt Voucher ' . $voucher_no . ')');
            $ca_line_esc = mysqli_real_escape_string($conn, accountledger_against_party_payment_label($customer_name, $pt_raw, $line_amt));

            if ($ledger_has_against) {
                $cash_ledger_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lb_col, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at,
                        against_ledger, against_invoice_no
                    ) VALUES (
                        0$lb_val,
                        '$dep_esc',
                        'sale_receipt_voucher',
                        $voucher_id,
                        '$voucher_no_esc',
                        '$inv_date_esc',
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
                        '$invoice_no'
                    )
                ";
            } else {
                $cash_ledger_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id$lb_col, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, reference_no, status, created_by, created_at
                    ) VALUES (
                        0$lb_val,
                        '$dep_esc',
                        'sale_receipt_voucher',
                        $voucher_id,
                        '$voucher_no_esc',
                        '$inv_date_esc',
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
                throw new Exception('Sale receipt voucher cash/bank ledger failed: ' . mysqli_error($conn));
            }
        }
    }
}

try {
    $metal_exchange_barcodes_out = [];
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    
    // Log the request for debugging
    error_log("Sale Invoice Save Request - User ID: " . $user_id . ", POST data keys: " . implode(", ", array_keys($_POST)));
    
    // Get invoice data
    $invoice_no = esc($_POST['order_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'AED');
    $exchange_rate = function_exists('auragold_read_posted_exchange_rate') ? auragold_read_posted_exchange_rate() : (float)($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
    if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
    $__fx = function_exists('auragold_exchange_rate_sql_fragments') ? auragold_exchange_rate_sql_fragments($conn, 'tbl_pos_sale_invoices') : ['has'=>false,'insert_col'=>'','insert_val'=>'','update'=>'','rate'=>1];
    $fx_update_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? ($__fx['name'] . ' = ' . (float)$exchange_rate . ', ') : '';
    $fx_insert_col_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . $__fx['name']) : '';
    $fx_insert_val_sql = (!empty($__fx['has']) && !empty($__fx['name'])) ? (', ' . (float)$exchange_rate) : '';

    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $invoice_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type_raw = isset($_POST['fixing_type']) ? trim((string)$_POST['fixing_type']) : 'Standard';
    $fixing_type = esc($fixing_type_raw);
    $is_hedging = (strtolower($fixing_type_raw) === 'hedging');
    // Hedging: separate making from net. Sale invoice = net amount (exclude making). Making amount = purchase fixing entry, linked same invoice number.
    $making_amount_for_purchase_fixing = (float)($_POST['making_amount_for_sale_fixing'] ?? 0);
    $group_name = esc($_POST['group_name'] ?? '');
    $comment = esc($_POST['comment'] ?? '');
    $payment_comments_raw = isset($_POST['payment_comments']) ? $_POST['payment_comments'] : '[]';
    $payment_comments = is_string($payment_comments_raw) ? $payment_comments_raw : json_encode($payment_comments_raw);
    $payment_comments_esc = mysqli_real_escape_string($conn, $payment_comments);

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS invoice_fixing_mapping (
      id INT(11) NOT NULL AUTO_INCREMENT,
      source_type VARCHAR(32) NOT NULL,
      source_transaction_id INT(11) NOT NULL,
      source_invoice_no VARCHAR(64) DEFAULT NULL,
      against_invoice_type VARCHAR(32) DEFAULT NULL,
      against_invoice_id INT(11) DEFAULT NULL,
      against_invoice_no VARCHAR(64) DEFAULT NULL,
      fixing_type VARCHAR(32) DEFAULT 'Hedging',
      metal_type VARCHAR(16) DEFAULT NULL,
      fixing_weight DECIMAL(18,3) DEFAULT 0.000,
      fixing_rate DECIMAL(18,4) DEFAULT 0.0000,
      fixing_amount DECIMAL(18,2) DEFAULT 0.00,
      status TINYINT(4) DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_source (source_type, source_transaction_id),
      KEY idx_against (against_invoice_type, against_invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
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
    
    // Ensure discount_percent column exists
    static $has_discount_percent_col = null;
    if ($has_discount_percent_col === null) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'discount_percent'");
        $has_discount_percent_col = ($chk && mysqli_num_rows($chk) > 0);
        if (!$has_discount_percent_col) {
            mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoices ADD COLUMN discount_percent DECIMAL(10,2) DEFAULT 0 AFTER discount_amt");
            $has_discount_percent_col = true;
        }
        if ($chk) mysqli_free_result($chk);
    }

    // E-Way Bill: log table + tbl_sale_invoices (shared migration) + tbl_pos_sale_invoices columns
    if (function_exists('ewaybill_ensure_eway_bill_migrations')) {
        ewaybill_ensure_eway_bill_migrations($conn);
    } elseif (function_exists('ewaybill_ensure_generate_log_table')) {
        ewaybill_ensure_generate_log_table($conn);
    }
    if (function_exists('ewaybill_ensure_pos_sale_invoice_eway_extras')) {
        ewaybill_ensure_pos_sale_invoice_eway_extras($conn);
    } else {
        static $has_eway_bootstrap = null;
        if ($has_eway_bootstrap === null) {
            $ew_chk = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'eway_bill_no'");
            $do_boot = !($ew_chk && mysqli_num_rows($ew_chk) > 0);
            if ($ew_chk) {
                mysqli_free_result($ew_chk);
            }
            if ($do_boot) {
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN customer_gstin VARCHAR(20) NULL DEFAULT NULL');
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN eway_vehicle_no VARCHAR(32) NULL DEFAULT NULL');
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN eway_distance_km DECIMAL(10,2) NULL DEFAULT NULL');
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN eway_bill_no VARCHAR(50) NULL DEFAULT NULL');
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN eway_bill_date VARCHAR(50) NULL DEFAULT NULL');
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN eway_status VARCHAR(50) NULL DEFAULT NULL');
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN eway_response LONGTEXT NULL');
            }
            $has_eway_bootstrap = true;
        }
    }
    static $has_eway_columns = null;
    if ($has_eway_columns === null) {
        $ew_chk2 = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'eway_bill_no'");
        $has_eway_columns = ($ew_chk2 && mysqli_num_rows($ew_chk2) > 0);
        if ($ew_chk2) {
            mysqli_free_result($ew_chk2);
        }
    }

    // Must accept 1, "1", true (jQuery/JSON can send a number, not only the string "1")
    $enable_eway_bill = (int) ($_POST['enable_eway_bill'] ?? 0) === 1;
    $customer_gstin_raw = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['customer_gstin'] ?? '')));
    $customer_gstin_in = esc($customer_gstin_raw);
    $eway_vehicle_raw = strtoupper(trim((string) ($_POST['eway_vehicle_no'] ?? $_POST['vehicle_no'] ?? '')));
    $ed_post = $_POST['eway_trans_distance'] ?? $_POST['eway_distance_km'] ?? $_POST['distance'] ?? null;
    $ed_str  = ($ed_post !== null && (string) $ed_post !== '') ? trim(preg_replace('/\s+/', '', (string) $ed_post)) : '';
    $eway_trans_distance_save = '0';
    if ($ed_str !== '' && is_numeric($ed_str) && (float) $ed_str >= 0 && (float) $ed_str <= 4000) {
        $eway_trans_distance_save = (string) (int) (float) $ed_str;
    }
    $eway_distance_km_in = (float) $eway_trans_distance_save;
    $eway_trans_mode_in = trim((string) ($_POST['eway_trans_mode'] ?? '1')) ?: '1';
    $eway_transporter_name_in = substr((string) ($_POST['eway_transporter_name'] ?? ''), 0, 200);
    $eway_transporter_id_in = substr(preg_replace('/\s+/', '', (string) ($_POST['eway_transporter_id'] ?? '')), 0, 20);
    $eway_trans_doc_no_in   = substr((string) ($_POST['eway_trans_doc_no'] ?? ''), 0, 100);
    $eway_trans_doc_date_in = substr((string) ($_POST['eway_trans_doc_date'] ?? ''), 0, 20);
    $vt = strtoupper(trim((string) ($_POST['eway_vehicle_type'] ?? 'R')));
    $eway_vehicle_type_in   = $vt === 'O' ? 'O' : 'R';
    $eway_enable_sql        = $enable_eway_bill ? 1 : 0;
    $eway_to_pincode_in     = preg_replace('/\D/', '', (string) ($_POST['eway_to_pincode'] ?? ''));
    $eway_to_pincode_in     = (strlen($eway_to_pincode_in) === 6) ? $eway_to_pincode_in : '';

    if (!AURAGOLD_EWAY_DISABLED && $enable_eway_bill) {
        if (function_exists('ewaybill_is_valid_gstin')) {
            if ($customer_gstin_raw === '' || !ewaybill_is_valid_gstin($customer_gstin_raw)) {
                throw new Exception('Invalid buyer GSTIN format: ' . ($customer_gstin_raw !== '' ? $customer_gstin_raw : '(empty)') . '. Enter a valid 15-character GSTIN for e-Way Bill.');
            }
        }
        if ($eway_to_pincode_in === '') {
            throw new Exception('Customer billing PIN (6 digits) is required for e-Way Bill.');
        }
    }

    $grand_total = (float)($_POST['grand_total'] ?? 0);

    if (!AURAGOLD_EWAY_DISABLED && $enable_eway_bill) {
        $eway_vehicle_raw = strtoupper(preg_replace('/[^A-Z0-9]/', '', $eway_vehicle_raw));
        $forceSampleSave = isset($_POST['eway_sandbox_force_sample_payload']) && (string) $_POST['eway_sandbox_force_sample_payload'] === '1';
        $isSandboxSave = false;
        if (function_exists('ewaybill_is_whitebooks_sandbox_mode') && function_exists('ewaybill_merged_config')) {
            $isSandboxSave = ewaybill_is_whitebooks_sandbox_mode(ewaybill_merged_config($conn));
        }
        if ($forceSampleSave && $isSandboxSave) {
            $eway_vehicle_raw = 'MH31AB1234';
        } elseif ($eway_vehicle_raw === '' && $isSandboxSave) {
            $ev = getenv('AURAGOLD_EWAY_DEFAULT_VEHICLE');
            $eway_vehicle_raw = strtoupper(preg_replace('/[^A-Z0-9]/', '', ($ev !== false && trim((string) $ev) !== '') ? (string) $ev : 'MH31AB1234'));
        }
        /* Production + empty: leave blank — generation reports "Vehicle number is required. Example: MH31AB1234" */
    }
    $eway_vehicle_no_in = esc($eway_vehicle_raw);
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $metal_amt = (float)($_POST['metal_amt'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);
    $adjusted_balance_used = (float)($_POST['adjusted_balance_used'] ?? 0);
    $use_previous_balance = isset($_POST['use_previous_balance']) ? (int)$_POST['use_previous_balance'] : 0;
    $previous_balance_used_amt = (float)($_POST['previous_balance_used_amt'] ?? 0);
    
    // Optional: previous_diamond, previous_gemstone in Previous Balance
    $has_previous_diamond_gemstone = false;
    $pdg = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'previous_diamond'");
    if ($pdg && mysqli_num_rows($pdg) > 0) {
        $pdg2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'previous_gemstone'");
        $has_previous_diamond_gemstone = ($pdg2 && mysqli_num_rows($pdg2) > 0);
        if ($pdg2) mysqli_free_result($pdg2);
    }
    if ($pdg) mysqli_free_result($pdg);
    if (!$has_previous_diamond_gemstone) {
        @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoices ADD COLUMN previous_diamond DECIMAL(12,3) DEFAULT 0 AFTER previous_silver");
        @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoices ADD COLUMN previous_gemstone DECIMAL(12,3) DEFAULT 0 AFTER previous_diamond");
        $has_previous_diamond_gemstone = true;
    }
    // Check if table has use_previous_balance columns (run admin/sql/add_previous_balance_used_to_sale_invoices.sql to add)
    $has_previous_balance_used_columns = false;
    $cols = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'use_previous_balance'");
    if ($cols && mysqli_num_rows($cols) > 0) {
        $cols2 = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'previous_balance_used_amt'");
        $has_previous_balance_used_columns = ($cols2 && mysqli_num_rows($cols2) > 0);
    }
    
    // Check if update or insert
    $invoice_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $is_update = ($invoice_id > 0);

    $has_si_branch_id = auragold_ensure_pos_sale_invoice_branch_id_column($conn);
    $eff_branch       = auragold_effective_branch_id();
    $sale_inv_branch_row_id = 0;
    if ($is_update && $has_si_branch_id) {
        $own_br = getRecord("SELECT branch_id FROM tbl_pos_sale_invoices WHERE id = $invoice_id LIMIT 1");
        $sale_inv_branch_row_id = (int) ($own_br['branch_id'] ?? 0);
        if ($eff_branch > 0) {
            if ($sale_inv_branch_row_id > 0 && $sale_inv_branch_row_id !== $eff_branch) {
                throw new Exception('This invoice belongs to another branch. You can only edit invoices for your logged-in branch.');
            }
        }
    }

    // Link outward tbl_stock rows to sale invoices so edits remove stale rows (barcode + non-barcode paths)
    $tbl_stock_has_reference = false;
    $__stk_ref = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
    $__stk_ref_n = ($__stk_ref ? mysqli_num_rows($__stk_ref) : 0);
    if ($__stk_ref) {
        mysqli_free_result($__stk_ref);
    }
    if ($__stk_ref_n >= 2) {
        $tbl_stock_has_reference = true;
    } else {
        @mysqli_query($conn, "ALTER TABLE `tbl_stock` ADD COLUMN `reference_id` INT NULL DEFAULT NULL AFTER `transaction_date`");
        @mysqli_query($conn, "ALTER TABLE `tbl_stock` ADD COLUMN `reference_type` VARCHAR(50) NULL DEFAULT NULL AFTER `reference_id`");
        $__stk_ref2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        if ($__stk_ref2 && mysqli_num_rows($__stk_ref2) >= 2) {
            $tbl_stock_has_reference = true;
        }
        if ($__stk_ref2) {
            mysqli_free_result($__stk_ref2);
        }
    }
    
    // Validation
    if (empty($customer_name)) {
        throw new Exception("Customer name is required");
    }

    // Validate user session
    if ($user_id <= 0) {
        throw new Exception("User session expired. Please login again.");
    }
    
    if (empty($invoice_no)) {
        // Same bill series as Sales Invoice; next no considers both tbl_sale_invoices and tbl_pos_sale_invoices
        $invoice_no = esc(getNextPosSaleInvoiceNo($conn));
    }

    // For new invoices, if invoice_no already exists on an *active* POS/sale row, bump until unique.
    if (!$is_update) {
        $cfg = getSaleInvoiceBillSeriesConfig($conn);
        $pos_active = auragold_doc_series_active_sql($conn, 'tbl_pos_sale_invoices');
        $si_active = auragold_doc_series_active_sql($conn, 'tbl_sale_invoices');
        $existing_invoice = getRecord("SELECT id FROM tbl_pos_sale_invoices WHERE invoice_no = '$invoice_no'" . $pos_active);
        if (!$existing_invoice) {
            $existing_invoice = getRecord("SELECT id FROM tbl_sale_invoices WHERE invoice_no = '$invoice_no'" . $si_active);
        }
        $guard = 0;
        while ($existing_invoice && $guard < 5000) {
            $invoice_no = esc(bumpPosSaleInvoiceNo($conn, $invoice_no, $cfg));
            $existing_invoice = getRecord("SELECT id FROM tbl_pos_sale_invoices WHERE invoice_no = '$invoice_no'" . $pos_active);
            if (!$existing_invoice) {
                $existing_invoice = getRecord("SELECT id FROM tbl_sale_invoices WHERE invoice_no = '$invoice_no'" . $si_active);
            }
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_sale_invoice_no')) {
            auragold_release_soft_deleted_sale_invoice_no($conn, $invoice_no);
        }
    }
    
    if ($is_update) {
        // Get current invoice number to check if it's changing
        $current_invoice = getRecord("SELECT invoice_no FROM tbl_pos_sale_invoices WHERE id = $invoice_id");
        $current_invoice_no = $current_invoice ? $current_invoice['invoice_no'] : '';

        if (!empty($current_invoice_no) && function_exists('auragold_si_has_active_purchase_fixing')) {
            if (auragold_si_has_active_purchase_fixing($current_invoice_no)) {
                throw new Exception('Delete the purchase fixing first before editing this sale invoice. After you delete it, fixing type will switch to Standard.');
            }
        }
        
        // Check if invoice_no is being changed and if it conflicts with another active POS or standard sale invoice
        if ($invoice_no !== $current_invoice_no) {
            $pos_active = auragold_doc_series_active_sql($conn, 'tbl_pos_sale_invoices');
            $si_active = auragold_doc_series_active_sql($conn, 'tbl_sale_invoices');
            $existing_invoice = getRecord("SELECT id FROM tbl_pos_sale_invoices WHERE invoice_no = '$invoice_no' AND id != $invoice_id" . $pos_active);
            if (!$existing_invoice) {
                $existing_invoice = getRecord("SELECT id FROM tbl_sale_invoices WHERE invoice_no = '$invoice_no'" . $si_active);
            }
            if ($existing_invoice) {
                throw new Exception("Invoice number '$invoice_no' already exists. Please use a different invoice number.");
            }
            if (function_exists('auragold_release_soft_deleted_sale_invoice_no')) {
                auragold_release_soft_deleted_sale_invoice_no($conn, $invoice_no);
            }
        }
        
        // Update existing invoice
        $invoice_no_update = ($invoice_no !== $current_invoice_no) ? "invoice_no = '$invoice_no'" : "";
        
        // Check if adjusted_balance_used column exists
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'adjusted_balance_used'");
        $has_adjusted_balance_used = ($check_column && mysqli_num_rows($check_column) > 0);
        
        $adjusted_balance_used_field = $has_adjusted_balance_used ? "adjusted_balance_used = $adjusted_balance_used" : "";
        
        // Build UPDATE query with proper comma handling
        $update_fields = [];
        if ($invoice_no_update) {
            $update_fields[] = $invoice_no_update;
        }
        $update_fields[] = "customer_id = " . ($customer_id > 0 ? $customer_id : 0);
        $update_fields[] = "customer_name = '$customer_name'";
        $update_fields[] = "against_of = " . ($against_of ? "'$against_of'" : "NULL");
        $update_fields[] = "currency = '$currency'";
        if (!empty($__fx['has']) && !empty($__fx['name'])) {
            $update_fields[] = $__fx['name'] . " = " . (float)$exchange_rate;
        }
        $update_fields[] = "ref_no = " . ($ref_no ? "'$ref_no'" : "NULL");
        $update_fields[] = "sales_person = " . ($sales_person ? "'$sales_person'" : "NULL");
        $update_fields[] = "invoice_date = '$invoice_date'";
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
        if ($has_discount_percent_col) $update_fields[] = "discount_percent = $discount_percent";
        $update_fields[] = "redeem_points = $redeem_points";
        $update_fields[] = "grand_total = $grand_total";
        $update_fields[] = "advance_payment = $advance_payment";
        $update_fields[] = "metal_amt = $metal_amt";
        $update_fields[] = "round_off = $round_off";
        $update_fields[] = "paid_amt = $paid_amt";
        $update_fields[] = "balance_amt = $balance_amt";
        if ($adjusted_balance_used_field) {
            $update_fields[] = $adjusted_balance_used_field;
        }
        if ($has_previous_balance_used_columns) {
            $update_fields[] = "use_previous_balance = $use_previous_balance";
            $update_fields[] = "previous_balance_used_amt = $previous_balance_used_amt";
        }
        $update_fields[] = "group_name = " . ($group_name ? "'$group_name'" : "NULL");
        $update_fields[] = "comment = " . ($comment ? "'$comment'" : "NULL");
        $update_fields[] = "payment_comments = '$payment_comments_esc'";
        if ($has_eway_columns) {
            $update_fields[] = 'customer_gstin = ' . ($customer_gstin_in !== '' ? "'$customer_gstin_in'" : 'NULL');
            $update_fields[] = 'eway_vehicle_no = ' . ($eway_vehicle_no_in !== '' ? "'$eway_vehicle_no_in'" : 'NULL');
            $update_fields[] = 'eway_distance_km = ' . (float) $eway_distance_km_in;
            $update_fields[] = "eway_trans_distance = '" . mysqli_real_escape_string($conn, $eway_trans_distance_save) . "'";
            $update_fields[] = "eway_trans_mode = '" . mysqli_real_escape_string($conn, $eway_trans_mode_in) . "'";
            $update_fields[] = 'eway_transporter_name = ' . ($eway_transporter_name_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_transporter_name_in) . "'" : 'NULL');
            $update_fields[] = 'eway_transporter_id = ' . ($eway_transporter_id_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_transporter_id_in) . "'" : 'NULL');
            $update_fields[] = 'eway_trans_doc_no = ' . ($eway_trans_doc_no_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_trans_doc_no_in) . "'" : 'NULL');
            $update_fields[] = 'eway_trans_doc_date = ' . ($eway_trans_doc_date_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_trans_doc_date_in) . "'" : 'NULL');
            $update_fields[] = "eway_vehicle_type = '" . mysqli_real_escape_string($conn, $eway_vehicle_type_in) . "'";
            $update_fields[] = 'eway_enable = ' . (int) $eway_enable_sql;
            $update_fields[] = 'eway_to_pincode = ' . ($eway_to_pincode_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_to_pincode_in) . "'" : 'NULL');
        }
        if ($has_si_branch_id && $eff_branch > 0 && $sale_inv_branch_row_id === 0) {
            $update_fields[] = 'branch_id = ' . (int) $eff_branch;
        }
        $update_fields[] = "updated_at = NOW()";
        
        $sql = "UPDATE tbl_pos_sale_invoices SET " . implode(", ", $update_fields) . " WHERE id = $invoice_id";
        
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            error_log("Sale Invoice Update SQL Error: " . $error);
            error_log("Sale Invoice Update SQL: " . $sql);
            if (strpos($error, 'Duplicate entry') !== false) {
                throw new Exception("Invoice number '$invoice_no' already exists. Please use a different invoice number.");
            }
            throw new Exception("Invoice update failed: " . $error);
        }
        
        // Delete existing items and payments
        mysqli_query($conn, "DELETE FROM tbl_pos_sale_invoice_items WHERE invoice_id = $invoice_id");
        mysqli_query($conn, "DELETE FROM tbl_pos_sale_invoice_payments WHERE invoice_id = $invoice_id");
        
        // Reverse previous outward stock: restore qty/weight onto latest inward/balance row, then delete linked outward rows
        if ($tbl_stock_has_reference) {
            $rev_rows = getList("SELECT barcode, opening_weight, opening_qty, product_id, product_characteristic_id, branch_id FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = $invoice_id AND reference_type = 'pos_sale_invoice'");
            foreach ($rev_rows as $rv) {
                $ow = (float)($rv['opening_weight'] ?? 0);
                $oq = (float)($rv['opening_qty'] ?? 0);
                if ($ow <= 0 && $oq <= 0) {
                    continue;
                }
                $b = trim((string)($rv['barcode'] ?? ''));
                $target_id = 0;
                if ($b !== '') {
                    $be = mysqli_real_escape_string($conn, $b);
                    $tr = getRecord("SELECT id FROM tbl_stock WHERE barcode = '$be' AND status = 1 AND stock_type IN ('inward','balance') ORDER BY id DESC LIMIT 1");
                    if ($tr && !empty($tr['id'])) {
                        $target_id = (int)$tr['id'];
                    }
                }
                if ($target_id <= 0) {
                    $pid = (int)($rv['product_id'] ?? 0);
                    $bid = (int)($rv['branch_id'] ?? 0);
                    if ($pid > 0 && $bid > 0) {
                        $cid_raw = $rv['product_characteristic_id'] ?? null;
                        if ($cid_raw !== null && $cid_raw !== '') {
                            $cid = (int)$cid_raw;
                            $tr = getRecord("SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id = $cid AND branch_id = $bid AND status = 1 AND stock_type IN ('inward','balance') ORDER BY id DESC LIMIT 1");
                        } else {
                            $tr = getRecord("SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id IS NULL AND branch_id = $bid AND status = 1 AND stock_type IN ('inward','balance') ORDER BY id DESC LIMIT 1");
                        }
                        if ($tr && !empty($tr['id'])) {
                            $target_id = (int)$tr['id'];
                        }
                    }
                }
                if ($target_id > 0) {
                    mysqli_query($conn, "UPDATE tbl_stock SET current_weight = COALESCE(current_weight, 0) + $ow, current_qty = COALESCE(current_qty, 0) + $oq WHERE id = $target_id");
                }
            }
            $del_stock = mysqli_query($conn, "DELETE FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = $invoice_id AND reference_type = 'pos_sale_invoice'");
            if (!$del_stock) {
                throw new Exception("Failed to reverse previous outward stock: " . mysqli_error($conn));
            }
        }
    } else {
        // Insert new invoice
        // Check if adjusted_balance_used column exists
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'adjusted_balance_used'");
        $has_adjusted_balance_used = ($check_column && mysqli_num_rows($check_column) > 0);
        
        $adjusted_balance_used_field = $has_adjusted_balance_used ? "adjusted_balance_used, " : "";
        $adjusted_balance_used_value = $has_adjusted_balance_used ? "$adjusted_balance_used, " : "";
        
        // Build INSERT query with proper field and value lists
        $insert_fields = [
            "invoice_no", "customer_id", "customer_name", "against_of", "currency", "ref_no", "sales_person",
            "invoice_date", "due_date", "layaways_id", "fixing_type",
            "previous_balance", "previous_gold", "previous_silver"
        ];
        if (!empty($__fx['has']) && !empty($__fx['name']) && !in_array($__fx['name'], $insert_fields, true)) {
            $curPos = array_search('currency', $insert_fields, true);
            if ($curPos === false) { $insert_fields[] = $__fx['name']; }
            else { array_splice($insert_fields, (int)$curPos + 1, 0, [$__fx['name']]); }
        }
        if ($has_previous_diamond_gemstone) {
            $insert_fields[] = "previous_diamond";
            $insert_fields[] = "previous_gemstone";
        }
        $insert_fields = array_merge($insert_fields, [
            "subtotal", "additional_amt", "net_total", "reward_points",
            "coupon_code", "coupon_discount", "discount_amt", "discount_percent", "redeem_points",
            "grand_total", "advance_payment", "metal_amt", "round_off",
            "paid_amt", "balance_amt"
        ]);
        if ($has_adjusted_balance_used) {
            $insert_fields[] = "adjusted_balance_used";
        }
        if ($has_previous_balance_used_columns) {
            $insert_fields[] = "use_previous_balance";
            $insert_fields[] = "previous_balance_used_amt";
        }
        $insert_fields[] = "group_name";
        $insert_fields[] = "comment";
        $insert_fields[] = "payment_comments";
        if ($has_eway_columns) {
            $insert_fields[] = 'customer_gstin';
            $insert_fields[] = 'eway_vehicle_no';
            $insert_fields[] = 'eway_distance_km';
            $insert_fields[] = 'eway_trans_distance';
            $insert_fields[] = 'eway_trans_mode';
            $insert_fields[] = 'eway_transporter_name';
            $insert_fields[] = 'eway_transporter_id';
            $insert_fields[] = 'eway_trans_doc_no';
            $insert_fields[] = 'eway_trans_doc_date';
            $insert_fields[] = 'eway_vehicle_type';
            $insert_fields[] = 'eway_enable';
            $insert_fields[] = 'eway_to_pincode';
        }
        $insert_fields[] = "status";
        $insert_fields[] = "created_by";
        if ($has_si_branch_id) {
            $insert_fields[] = 'branch_id';
        }
        $insert_fields[] = "created_at";
        
        $insert_values = [
            "'$invoice_no'",
            ($customer_id > 0 ? $customer_id : 0),
            "'$customer_name'",
            ($against_of ? "'$against_of'" : "NULL"),
            "'$currency'",
            ($ref_no ? "'$ref_no'" : "NULL"),
            ($sales_person ? "'$sales_person'" : "NULL"),
            "'$invoice_date'",
            ($due_date ? "'$due_date'" : "NULL"),
            ($layaways ? (int)$layaways : "NULL"),
            "'$fixing_type'",
            $previous_balance,
            $previous_gold,
            $previous_silver
        ];
        if ($has_previous_diamond_gemstone) {
            $insert_values[] = $previous_diamond;
            $insert_values[] = $previous_gemstone;
        }
        $insert_values = array_merge($insert_values, [
            $subtotal,
            $additional_amt,
            $net_total,
            $reward_points,
            ($coupon_code ? "'$coupon_code'" : "NULL"),
            $coupon_discount,
            $discount_amt,
            $discount_percent,
            $redeem_points,
            $grand_total,
            $advance_payment,
            $metal_amt,
            $round_off,
            $paid_amt,
            $balance_amt
        ]);
        if ($has_adjusted_balance_used) {
            $insert_values[] = $adjusted_balance_used;
        }
        if ($has_previous_balance_used_columns) {
            $insert_values[] = $use_previous_balance;
            $insert_values[] = $previous_balance_used_amt;
        }
        $insert_values[] = ($group_name ? "'$group_name'" : "NULL");
        $insert_values[] = ($comment ? "'$comment'" : "NULL");
        $insert_values[] = "'$payment_comments_esc'";
        if ($has_eway_columns) {
            $insert_values[] = ($customer_gstin_in !== '' ? "'$customer_gstin_in'" : 'NULL');
            $insert_values[] = ($eway_vehicle_no_in !== '' ? "'$eway_vehicle_no_in'" : 'NULL');
            $insert_values[] = (float) $eway_distance_km_in;
            $insert_values[] = "'" . mysqli_real_escape_string($conn, $eway_trans_distance_save) . "'";
            $insert_values[] = "'" . mysqli_real_escape_string($conn, $eway_trans_mode_in) . "'";
            $insert_values[] = ($eway_transporter_name_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_transporter_name_in) . "'" : 'NULL');
            $insert_values[] = ($eway_transporter_id_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_transporter_id_in) . "'" : 'NULL');
            $insert_values[] = ($eway_trans_doc_no_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_trans_doc_no_in) . "'" : 'NULL');
            $insert_values[] = ($eway_trans_doc_date_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_trans_doc_date_in) . "'" : 'NULL');
            $insert_values[] = "'" . mysqli_real_escape_string($conn, $eway_vehicle_type_in) . "'";
            $insert_values[] = (int) $eway_enable_sql;
            $insert_values[] = ($eway_to_pincode_in !== '' ? "'" . mysqli_real_escape_string($conn, $eway_to_pincode_in) . "'" : 'NULL');
        }
        $insert_values[] = "'draft'";
        $insert_values[] = $user_id;
        if ($has_si_branch_id) {
            $insert_values[] = $eff_branch > 0 ? (string) (int) $eff_branch : 'NULL';
        }
        $insert_values[] = "NOW()";
        
        if (!empty($__fx['has']) && !empty($__fx['name'])) {
            $ratePos = array_search($__fx['name'], $insert_fields, true);
            if ($ratePos !== false && count($insert_values) < count($insert_fields)) {
                array_splice($insert_values, (int)$ratePos, 0, [(float)$exchange_rate]);
            }
        }
        $sql = "INSERT INTO tbl_pos_sale_invoices (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $insert_values) . ")";
        
        if (!mysqli_query($conn, $sql)) {
            $error = mysqli_error($conn);
            error_log("Sale Invoice Insert SQL Error: " . $error);
            error_log("Sale Invoice Insert SQL: " . $sql);
            throw new Exception("Invoice insert failed: " . $error);
        }
        
        $invoice_id = mysqli_insert_id($conn);
    }

    auragold_ensure_customer_ledger_branch_column($conn);
    $ledger_has_branch_col = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $sale_invoice_branch_for_ledger = 0;
    if (!empty($has_si_branch_id)) {
        $sib_row = getRecord('SELECT branch_id FROM tbl_pos_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        $sale_invoice_branch_for_ledger = (int) ($sib_row['branch_id'] ?? 0);
    }
    if ($sale_invoice_branch_for_ledger <= 0 && !empty($eff_branch) && (int) $eff_branch > 0) {
        $sale_invoice_branch_for_ledger = (int) $eff_branch;
    }
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = ($ledger_has_branch_col && $sale_invoice_branch_for_ledger > 0)
        ? ', ' . (int) $sale_invoice_branch_for_ledger
        : ($ledger_has_branch_col ? ', NULL' : '');

    // Save invoice items
    $items = [];
    if (isset($_POST['items'])) {
        if (is_string($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
        } else if (is_array($_POST['items'])) {
            $items = $_POST['items'];
        }
    }

    $hedge_metal_cost_sum = 0;
    $pfd_item_candidates = [];
    if ($is_hedging && !empty($items) && is_array($items)) {
        foreach ($items as $_hi) {
            $mc = (float)($_hi['metal_cost'] ?? 0);
            if ($mc <= 0) {
                $mc = (float)($_hi['metal_cost_amount'] ?? 0);
            }
            if ($mc <= 0) {
                $mv = (float)($_hi['metal_value'] ?? 0);
                $da = (float)($_hi['diamond_amount'] ?? $_hi['diamond_value'] ?? 0);
                $sa = (float)($_hi['stone_amount'] ?? $_hi['stone_charges'] ?? 0);
                $mc = $mv + $da + $sa;
            }
            if ($mc <= 0) {
                $amt = (float)($_hi['amount'] ?? 0);
                $mk = (float)($_hi['making_amount'] ?? $_hi['making'] ?? 0);
                $tx = (float)($_hi['tax'] ?? 0);
                $mc = max(0, $amt - $mk - $tx);
            }
            $hedge_metal_cost_sum += $mc;

            $net_weight = (float)($_hi['net_weight'] ?? $_hi['net_wt'] ?? $_hi['final_weight'] ?? $_hi['final_wt'] ?? $_hi['gross_weight'] ?? $_hi['gross_wt'] ?? 0);
            $purity_weight = (float)($_hi['purity_weight'] ?? $_hi['pure_weight'] ?? $_hi['pure_wt'] ?? $_hi['purity_wt'] ?? 0);
            $purity_pct = (float)($_hi['purity'] ?? 0);
            if ($purity_weight <= 0 && $net_weight > 0 && $purity_pct > 0) {
                if ($purity_pct <= 1) {
                    $purity_weight = $net_weight * $purity_pct;
                } elseif ($purity_pct <= 100) {
                    $purity_weight = $net_weight * ($purity_pct / 100);
                } else {
                    $purity_weight = $net_weight * ($purity_pct / 1000);
                }
            }
            if ($purity_weight <= 0) {
                $purity_weight = $net_weight;
            }
            $gross_wt = (float)($_hi['gross_weight'] ?? $_hi['gross_wt'] ?? $net_weight);
            $metal_rate = (float)($_hi['metal_rate'] ?? 0);
            $line_rate = $metal_rate > 0 ? $metal_rate : (float)($_hi['rate'] ?? 0);
            $line_purity = $purity_pct > 0 ? $purity_pct : 1.0;
            $pfd_item_candidates[] = [
                'metal_id' => (int)($_hi['metal_id'] ?? 0),
                'gross_wt' => $gross_wt,
                'purity_wt' => $purity_weight,
                'rate' => $line_rate,
                'amount' => max(0, $mc),
                'purity' => $line_purity,
            ];
        }
    }
    $hedge_amt = (float)$hedge_metal_cost_sum;
    if ($hedge_amt <= 0 && $is_hedging) {
        $hedge_amt = (float)$making_amount_for_purchase_fixing;
    }
    if ($hedge_amt <= 0 && $is_hedging) {
        $hedge_amt = (float)$metal_amt;
    }
    
    // Check tbl_stock columns (barcode; reference_id/type set at start of handler)
    $tbl_stock_has_barcode = false;
    $bc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
    if ($bc && mysqli_num_rows($bc) > 0) {
        $tbl_stock_has_barcode = true;
        mysqli_free_result($bc);
    }

    $tbl_pos_sale_invoice_items_has_images = false;
    $col_images = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'images'");
    if ($col_images && mysqli_num_rows($col_images) > 0) {
        $tbl_pos_sale_invoice_items_has_images = true;
        mysqli_free_result($col_images);
    }
    $tbl_pos_sale_invoice_items_has_diamond_category = false;
    $col_diamond_cat = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'diamond_category'");
    if ($col_diamond_cat && mysqli_num_rows($col_diamond_cat) > 0) {
        $tbl_pos_sale_invoice_items_has_diamond_category = true;
        mysqli_free_result($col_diamond_cat);
    }
    $tbl_pos_sale_invoice_items_has_metal_rate = false;
    $col_metal_rate = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'metal_rate'");
    if ($col_metal_rate && mysqli_num_rows($col_metal_rate) > 0) {
        $tbl_pos_sale_invoice_items_has_metal_rate = true;
        mysqli_free_result($col_metal_rate);
    }
    $tbl_pos_sale_invoice_items_has_calculation_type = false;
    $col_calc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'calculation_type'");
    if ($col_calc && mysqli_num_rows($col_calc) > 0) {
        $tbl_pos_sale_invoice_items_has_calculation_type = true;
        mysqli_free_result($col_calc);
    }
    $tbl_pos_sale_invoice_items_has_diamond_amount = false;
    $col_da = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'diamond_amount'");
    if ($col_da && mysqli_num_rows($col_da) > 0) {
        $tbl_pos_sale_invoice_items_has_diamond_amount = true;
        mysqli_free_result($col_da);
    }
    $tbl_pos_sale_invoice_items_has_stone_amount = false;
    $col_sa = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'stone_amount'");
    if ($col_sa && mysqli_num_rows($col_sa) > 0) {
        $tbl_pos_sale_invoice_items_has_stone_amount = true;
        mysqli_free_result($col_sa);
    }
    $tbl_pos_sale_invoice_items_has_stone_weight = false;
    $col_sw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'stone_weight'");
    if ($col_sw && mysqli_num_rows($col_sw) > 0) {
        $tbl_pos_sale_invoice_items_has_stone_weight = true;
        mysqli_free_result($col_sw);
    }
    $tbl_pos_sale_invoice_items_has_metal_value = false;
    $col_mv = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'metal_value'");
    if ($col_mv && mysqli_num_rows($col_mv) > 0) {
        $tbl_pos_sale_invoice_items_has_metal_value = true;
        mysqli_free_result($col_mv);
    }
    $tbl_pos_sale_invoice_items_has_metal_qty = false;
    $col_mq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'metal_qty'");
    if ($col_mq && mysqli_num_rows($col_mq) > 0) {
        $tbl_pos_sale_invoice_items_has_metal_qty = true;
        mysqli_free_result($col_mq);
    } else {
        if ($col_mq) mysqli_free_result($col_mq);
        @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoice_items ADD COLUMN metal_qty DECIMAL(12,2) DEFAULT 1.00 AFTER quantity");
        $tbl_pos_sale_invoice_items_has_metal_qty = true;
    }
    $tbl_pos_sale_invoice_items_has_metal_weight = false;
    $col_mw = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'metal_weight'");
    if ($col_mw && mysqli_num_rows($col_mw) > 0) {
        $tbl_pos_sale_invoice_items_has_metal_weight = true;
        mysqli_free_result($col_mw);
    } else {
        if ($col_mw) mysqli_free_result($col_mw);
        @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoice_items ADD COLUMN metal_weight DECIMAL(12,4) DEFAULT 0.0000 AFTER metal_qty");
        $tbl_pos_sale_invoice_items_has_metal_weight = true;
    }
    $tbl_pos_sale_invoice_items_has_sort_order = false;
    $col_sort = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'sort_order'");
    if ($col_sort && mysqli_num_rows($col_sort) > 0) {
        $tbl_pos_sale_invoice_items_has_sort_order = true;
        mysqli_free_result($col_sort);
    } else {
        if ($col_sort) mysqli_free_result($col_sort);
        @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoice_items ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 COMMENT 'Display order (drag-and-drop)' AFTER invoice_id");
        $tbl_pos_sale_invoice_items_has_sort_order = true;
    }

    $tbl_pos_sale_invoice_items_has_merge_group_index = false;
    $col_mgix = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'merge_group_index'");
    if ($col_mgix && mysqli_num_rows($col_mgix) > 0) {
        $tbl_pos_sale_invoice_items_has_merge_group_index = true;
        mysqli_free_result($col_mgix);
    } else {
        if ($col_mgix) {
            mysqli_free_result($col_mgix);
        }
        @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoice_items ADD COLUMN merge_group_index INT UNSIGNED NULL DEFAULT NULL COMMENT 'Same value = same product list row (merged modal lines)' AFTER net_amt_with_tax");
        $col_mgix2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_items LIKE 'merge_group_index'");
        if ($col_mgix2 && mysqli_num_rows($col_mgix2) > 0) {
            $tbl_pos_sale_invoice_items_has_merge_group_index = true;
        }
        if ($col_mgix2) {
            mysqli_free_result($col_mgix2);
        }
    }

    if (!empty($items) && is_array($items)) {
        $item_index = 0;
        foreach ($items as $item) {
            $sort_order = $item_index;
            $item_index++;
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $barcode = esc($item['barcode'] ?? '');
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
            $diamond_amount = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
            $stone_amount = (float)($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
            $stone_weight = (float)($item['stone_weight'] ?? 0);
            $metal_value = (float)($item['metal_value'] ?? 0);
            $metal_qty = (float)($item['metal_qty'] ?? 1);
            $metal_weight = (float)($item['metal_weight'] ?? 0);
            $merge_group_index_sql = 'NULL';
            if (!empty($tbl_pos_sale_invoice_items_has_merge_group_index) && isset($item['merge_group_index']) && $item['merge_group_index'] !== '' && $item['merge_group_index'] !== null) {
                $merge_group_index_sql = (string)(int)$item['merge_group_index'];
            }

            if ($product_id > 0) {
                $sort_order_col = $tbl_pos_sale_invoice_items_has_sort_order ? "sort_order, " : "";
                $sort_order_val = $tbl_pos_sale_invoice_items_has_sort_order ? "$sort_order, " : "";
                $diamond_cat_col = $tbl_pos_sale_invoice_items_has_diamond_category ? ", diamond_category" : "";
                $diamond_cat_val = $tbl_pos_sale_invoice_items_has_diamond_category ? ", " . ($diamond_category ? "'$diamond_category'" : "NULL") : "";
                $metal_rate_col = $tbl_pos_sale_invoice_items_has_metal_rate ? ", metal_rate" : "";
                $metal_rate_val = $tbl_pos_sale_invoice_items_has_metal_rate ? ", $metal_rate" : "";
                $calc_col = $tbl_pos_sale_invoice_items_has_calculation_type ? ", calculation_type" : "";
                $calc_val = $tbl_pos_sale_invoice_items_has_calculation_type ? ", " . ($calculation_type ? "'" . mysqli_real_escape_string($conn, $calculation_type) . "'" : "NULL") : "";
                $da_col = $tbl_pos_sale_invoice_items_has_diamond_amount ? ", diamond_amount" : "";
                $da_val = $tbl_pos_sale_invoice_items_has_diamond_amount ? ", $diamond_amount" : "";
                $sa_col = $tbl_pos_sale_invoice_items_has_stone_amount ? ", stone_amount" : "";
                $sa_val = $tbl_pos_sale_invoice_items_has_stone_amount ? ", $stone_amount" : "";
                $sw_col = $tbl_pos_sale_invoice_items_has_stone_weight ? ", stone_weight" : "";
                $sw_val = $tbl_pos_sale_invoice_items_has_stone_weight ? ", $stone_weight" : "";
                $mv_col = $tbl_pos_sale_invoice_items_has_metal_value ? ", metal_value" : "";
                $mv_val = $tbl_pos_sale_invoice_items_has_metal_value ? ", $metal_value" : "";
                $mq_col = $tbl_pos_sale_invoice_items_has_metal_qty ? ", metal_qty" : "";
                $mq_val = $tbl_pos_sale_invoice_items_has_metal_qty ? ", $metal_qty" : "";
                $mw_col = $tbl_pos_sale_invoice_items_has_metal_weight ? ", metal_weight" : "";
                $mw_val = $tbl_pos_sale_invoice_items_has_metal_weight ? ", $metal_weight" : "";
                $mg_col = $tbl_pos_sale_invoice_items_has_merge_group_index ? ", merge_group_index" : "";
                $mg_val = $tbl_pos_sale_invoice_items_has_merge_group_index ? ", " . $merge_group_index_sql : "";
                $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_pos_sale_invoice_items', $item);
                $ef_col = $ef_parts['columns'];
                $ef_val = $ef_parts['values'];
                // Insert invoice item
                $item_sql = "
                    INSERT INTO tbl_pos_sale_invoice_items (
                        invoice_id, $sort_order_col product_id, product_characteristic_id, barcode, product_name,
                        carat, quantity, gross_weight, less_weight, purity, purity_weight,
                        final_weight, net_weight, pure_weight, rate,
                        making_amount, amount, tax_amount, net_amount, net_amt_with_tax$mg_col,
                        design_no, location_id, status, created_at $diamond_cat_col $metal_rate_col $calc_col $da_col $sa_col $sw_col $mv_col $mq_col $mw_col$ef_col
                    ) VALUES (
                        $invoice_id, $sort_order_val $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                        " . ($barcode ? "'$barcode'" : "NULL") . ",
                        '$product_name',
                        " . ($carat ? "'$carat'" : "NULL") . ",
                        $quantity, $gross_weight, $less_weight, $purity, $purity_weight,
                        $final_weight, $net_weight, $pure_weight, $rate,
                        $making_amount, $amount, $tax, $net_amount, $net_amt_with_tax$mg_val,
                        " . ($design_no ? "'$design_no'" : "NULL") . ",
                        " . ($location_id ? $location_id : "NULL") . ",
                        1, NOW() $diamond_cat_val $metal_rate_val $calc_val $da_val $sa_val $sw_val $mv_val $mq_val $mw_val$ef_val
                    )
                ";
                
                if (!mysqli_query($conn, $item_sql)) {
                    throw new Exception("Item insert failed: " . mysqli_error($conn));
                }
                
                // Get the item ID that was just inserted
                $item_id = mysqli_insert_id($conn);
                
                // Save item images to uploads/pos-sale-invoice/{invoice_id}/ (and store paths in DB if column exists)
                $group_image_raw = $item['group_image'] ?? '';
                if ($group_image_raw !== '') {
                    $images_json = save_sale_invoice_item_images($group_image_raw, $invoice_id, $item_id);
                    if ($images_json !== '' && $tbl_pos_sale_invoice_items_has_images) {
                        $images_esc = mysqli_real_escape_string($conn, $images_json);
                        mysqli_query($conn, "UPDATE tbl_pos_sale_invoice_items SET images = '$images_esc' WHERE id = $item_id");
                    }
                }
                
                // Add outward stock entry (sale reduces inventory)
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
                    if ($net_amount > 0) {
                        $stock_value = $net_amount;
                    } else if ($amount > 0) {
                        $stock_value = $amount;
                    } else if ($net_amt_with_tax > 0) {
                        $stock_value = $net_amt_with_tax;
                    }
                    
                    // Default values if missing
                    if ($stock_purity <= 0) {
                        $stock_purity = 100.0; // Default to 100% if not specified
                    }
                    if ($branch_id <= 0) {
                        $hbr = 0;
                        if ($invoice_id > 0 && !empty($has_si_branch_id)) {
                            $hr = getRecord('SELECT branch_id FROM tbl_pos_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
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
                    
                    // Find source stock by barcode (balance update/insert done after outward)
                    $stock_row = null;
                    if ($tbl_stock_has_barcode && $barcode !== '') {
                        if (!function_exists('auragold_sale_barcode_stock_transfer_block_message')) {
                            require_once __DIR__ . '/../includes/auragold_stock_transfer_save_helpers.php';
                        }
                        if (function_exists('auragold_sale_barcode_stock_transfer_block_message')) {
                            $xferBlockMsg = auragold_sale_barcode_stock_transfer_block_message($conn, $barcode, (int) $branch_id);
                            if ($xferBlockMsg !== null && $xferBlockMsg !== '') {
                                throw new Exception($xferBlockMsg);
                            }
                        }
                        $barcode_esc = mysqli_real_escape_string($conn, $barcode);
                        // Only consider on-hand (non-outward) rows with available weight
                        $source_stock = getRecord("
                            SELECT id, barcode, current_qty, current_weight, product_id, product_characteristic_id, branch_id, metal_id, opening_purity, rate
                            FROM tbl_stock
                            WHERE barcode = '$barcode_esc' AND status = 1 AND COALESCE(current_weight, 0) > 0
                              AND (stock_type IS NULL OR stock_type NOT IN ('outward'))
                            ORDER BY id DESC
                            LIMIT 1
                        ");
                        if ($source_stock) {
                            $stock_row = $source_stock;
                        }
                    }

                    if (!empty($stock_row)) {
                        // 1. Get available weight BEFORE update (insufficient check disabled - allow save without restriction)
                        $sold_weight = $stock_weight;
                        $available_weight = (float)($stock_row['current_weight'] ?? 0);
                        $balance_weight = $available_weight - $sold_weight;

                        // 2. Insert outward entry (from source stock row)
                        $ow_prod_id = (int)$stock_row['product_id'];
                        $ow_char_id = (isset($stock_row['product_characteristic_id']) && $stock_row['product_characteristic_id'] !== '' && $stock_row['product_characteristic_id'] !== null)
                            ? (int)$stock_row['product_characteristic_id'] : 'NULL';
                        $ow_barcode_esc = mysqli_real_escape_string($conn, $stock_row['barcode']);
                        $ow_branch_id = (int)$stock_row['branch_id'];
                        $ow_metal_id = (int)$stock_row['metal_id'];
                        $ow_purity = (float)($stock_row['opening_purity'] ?? $stock_purity);
                        $ow_rate = (float)($stock_row['rate'] ?? 0);
                        $ow_value = $ow_rate * $sold_weight;
                        $has_sj = false;
                        $sj_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
                        if ($sj_check && mysqli_num_rows($sj_check) > 0) $has_sj = true;
                        $ow_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at";
                        $ow_vals = "$ow_prod_id, $ow_char_id, '$ow_barcode_esc', $ow_branch_id, $ow_metal_id, $sold_weight, $ow_purity, 1, $sold_weight, $ow_rate, $ow_value, $sold_weight, 1, 'outward', CURDATE(), 1, NOW()";
                        if ($has_sj) {
                            $ow_cols .= ", stock_journal_id";
                            $ow_vals .= ", NULL";
                        }
                        if ($tbl_stock_has_reference) {
                            $ow_cols = str_replace("transaction_date, status, created_at", "transaction_date, status, reference_id, reference_type, created_at", $ow_cols);
                            $ow_vals = str_replace("CURDATE(), 1, NOW()", "CURDATE(), 1, $invoice_id, 'pos_sale_invoice', NOW()", $ow_vals);
                        }
                        if (!mysqli_query($conn, "INSERT INTO tbl_stock ($ow_cols) VALUES ($ow_vals)")) {
                            throw new Exception("Outward stock insert failed: " . mysqli_error($conn));
                        }

                        // 3–4. Reduce quantity/weight on the same barcode row (no new balance row / barcode)
                        $src_id = (int)$stock_row['id'];
                        $prev_cq = (float)($stock_row['current_qty'] ?? 0);
                        $sold_q = (float)$quantity;
                        if ($sold_q <= 0 && $available_weight > 0 && $prev_cq > 0) {
                            $sold_q = $prev_cq * ($sold_weight / $available_weight);
                        }
                        $new_cq = max(0, $prev_cq - $sold_q);
                        if ($balance_weight <= 0) {
                            mysqli_query($conn, "UPDATE tbl_stock SET current_weight = 0, current_qty = 0, value = 0 WHERE id = $src_id");
                        } else {
                            $new_val = $ow_rate * $balance_weight;
                            mysqli_query($conn, "UPDATE tbl_stock SET current_weight = $balance_weight, current_qty = $new_cq, final_weight = $balance_weight, value = $new_val WHERE id = $src_id");
                        }
                    } else {
                        // Non-barcode: one outward entry per product line
                        $ref_esc = mysqli_real_escape_string($conn, (string)$invoice_id);
                        $barcode_esc = $barcode ? "'" . mysqli_real_escape_string($conn, $barcode) . "'" : "NULL";
                        $stock_cols = "product_id, product_characteristic_id, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
                        $stock_vals = "$product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ", $branch_id, $metal_id, $stock_weight, $stock_purity, $quantity, " . ($final_weight > 0 ? $final_weight : $stock_weight) . ", $rate, $stock_value, $stock_weight, $quantity, 'outward', '$invoice_date', NOW()";
                        if ($tbl_stock_has_barcode) {
                            $stock_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
                            $stock_vals = "$product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ", $barcode_esc, $branch_id, $metal_id, $stock_weight, $stock_purity, $quantity, " . ($final_weight > 0 ? $final_weight : $stock_weight) . ", $rate, $stock_value, $stock_weight, $quantity, 'outward', '$invoice_date', NOW()";
                        }
                        if ($tbl_stock_has_reference) {
                            $stock_cols = str_replace("transaction_date, created_at", "transaction_date, reference_id, reference_type, created_at", $stock_cols);
                            $stock_vals = str_replace("'$invoice_date', NOW()", "'$invoice_date', $invoice_id, 'pos_sale_invoice', NOW()", $stock_vals);
                        }
                        $stock_sql = "INSERT INTO tbl_stock ($stock_cols) VALUES ($stock_vals)";
                        if (!mysqli_query($conn, $stock_sql)) {
                            throw new Exception("Outward stock insert failed: " . mysqli_error($conn));
                        }
                    }

                    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
                    $sj_no_si = 'SI' . (int) $invoice_id . 'I' . (int) $item_id;
                    if (strlen($sj_no_si) > 48) {
                        $sj_no_si = 'S' . (int) $invoice_id . 'x' . (int) $item_id;
                    }
                    auragold_stock_history_audit_insert_row($conn, [
                        'sj_invoice_no' => $sj_no_si,
                        'item_id' => 0,
                        'invoice_id' => 0,
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
                        'voucher_type' => 'Sale Invoice',
                        'design_no' => $design_no,
                        'category' => $diamond_category,
                        'comment' => 'auragold_doc|src=si|iid=' . (int) $invoice_id . '|sii=' . (int) $item_id . '|',
                    ]);
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
        sale_invoice_validate_metal_exchange_payments($conn, $payments);
        sale_invoice_validate_scrap_payments($conn, $payments);
        $sip_has_payment_details = false;
        $_sipdc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_payments LIKE 'payment_details'");
        if ($_sipdc && mysqli_num_rows($_sipdc) > 0) {
            $sip_has_payment_details = true;
        } else {
            @mysqli_query($conn, "ALTER TABLE tbl_pos_sale_invoice_payments ADD COLUMN payment_details TEXT NULL COMMENT 'JSON copy of payment row (scrap weights, qty, etc.)'");
            $_sipdc2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoice_payments LIKE 'payment_details'");
            $sip_has_payment_details = ($_sipdc2 && mysqli_num_rows($_sipdc2) > 0);
        }
        if ($_sipdc) {
            mysqli_free_result($_sipdc);
        }
        if (isset($_sipdc2) && $_sipdc2) {
            mysqli_free_result($_sipdc2);
        }

        $___pos_si_me_has_ref = auragold_metal_exchange_document_init($conn, $is_update, (int) $invoice_id, 'pos_sale_invoice_metal_exchange');

        foreach ($payments as $pay_seq => $payment) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                continue;
            }
            $payment = sale_invoice_payment_merge_stored_details($payment);
            $payment_type = esc($payment['payment_type'] ?? '');
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $transaction_no = esc($payment['transaction_no'] ?? '');
            $cheque_date = isset($payment['cheque_date']) && $payment['cheque_date'] ? esc($payment['cheque_date']) : NULL;
            $purity_carat = esc($payment['purity_carat'] ?? '');
            $amount = (float) ($payment['amount'] ?? 0);
            $previous_balance_amount = (float) ($payment['previous_balance_amount'] ?? 0);
            $current_order_amount = (float) ($payment['current_order_amount'] ?? ($amount - $previous_balance_amount));
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $quantity = (float) ($payment['quantity'] ?? 0);
            $payment_details_esc = mysqli_real_escape_string($conn, json_encode($payment, JSON_UNESCAPED_UNICODE));
            $pd_ins_col = $sip_has_payment_details ? ', payment_details' : '';
            $pd_ins_val = $sip_has_payment_details ? ", '$payment_details_esc'" : '';

            $payment_sql = "
                    INSERT INTO tbl_pos_sale_invoice_payments (
                        invoice_id, payment_type, deposit_into, transaction_no,
                        cheque_date, purity_carat, amount, previous_balance_amount, current_order_amount, diamond_category, quantity
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
                        $current_order_amount,
                        " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                        $quantity
                        $pd_ins_val,
                        1, NOW()
                    )
                ";

            if (!mysqli_query($conn, $payment_sql)) {
                throw new Exception("Payment insert failed: " . mysqli_error($conn));
            }

            $pm_saved_pos = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'pos_sale_invoice_metal_exchange',
                (int) $invoice_id,
                trim((string) $invoice_no),
                substr(trim((string) $invoice_date), 0, 10),
                $pm_saved_pos,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___pos_si_me_has_ref,
                'POS Sale Invoice — Metal Exchange',
                'posi_me',
                'POSI-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }
    
    // ================== CUSTOMER LEDGER UPDATE ==================
    // Mirror of save-purchase-invoice.php: party invoice = DEBIT (PI = CREDIT); payments = party CREDIT (PI = DEBIT);
    // hedging / metal-cost / PF / HA postings use reversed Dr↔Cr vs purchase; Metal Exchange weight on party CREDIT columns; scrap label Dr (mirror PI Cr).
    if ($customer_id > 0 || !empty($customer_name)) {
        $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        $has_against = ($has_against && mysqli_num_rows($has_against) > 0);
        $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
        $has_gold_pure_cols = ($gpc && mysqli_num_rows($gpc) > 0);

        if ($is_update) {
            sale_invoice_delete_auto_receipt_vouchers_for_refs($conn, [trim((string) ($current_invoice_no ?? '')), trim((string) $invoice_no)]);

            // transaction_id is only unique per table (sale vs purchase both use 1,2,3…). Purchase payments
            // also use type "payment" + purchase invoice id — same numeric id as sale invoice would wipe PI history.
            // Scope deletes to this sale's voucher number(s) (transaction_no on ledger rows).
            $ledger_del_txn_nos = array_values(array_unique(array_filter([
                trim((string) ($current_invoice_no ?? '')),
                trim((string) $invoice_no),
            ])));
            $ledger_del_txn_in_parts = [];
            foreach ($ledger_del_txn_nos as $n) {
                if ($n !== '') {
                    $ledger_del_txn_in_parts[] = "'" . mysqli_real_escape_string($conn, $n) . "'";
                }
            }
            if (empty($ledger_del_txn_in_parts)) {
                $ledger_del_txn_in_parts[] = "'" . mysqli_real_escape_string($conn, $invoice_no) . "'";
            }
            $ledger_del_txn_no_in = implode(',', $ledger_del_txn_in_parts);

            mysqli_query($conn, "
                DELETE FROM tbl_customer_ledger 
                WHERE transaction_id = $invoice_id AND status = 1 
                AND transaction_no IN ($ledger_del_txn_no_in)
                AND transaction_type IN ('pos_sale_invoice', 'previous_balance_payment', 'payment', 'sale_revenue')
            ");
            mysqli_query($conn, "
                DELETE FROM tbl_customer_ledger 
                WHERE customer_name = 'Hedging Account' AND transaction_id = $invoice_id AND transaction_type = 'pos_sale_invoice' AND status = 1
                AND transaction_no IN ($ledger_del_txn_no_in)
            ");
        }
        
        // ================== DOUBLE-ENTRY ACCOUNTING: Sales, Making, Tax (Credit) ==================
        // Compute Sales = Metal + Diamond + Stone (without making); Making = total making; Tax = total tax
        $total_sales_amt = 0;   // Metal + Diamond + Stone amount
        $total_making_amt = 0;
        $total_tax_amt = 0;
        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                $metal_val = (float)($item['metal_value'] ?? 0);
                $diamond_amt = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
                $stone_amt = (float)($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
                $making_amt = (float)($item['making_amount'] ?? $item['making'] ?? 0);
                $tax_amt = (float)($item['tax'] ?? 0);
                $amount = (float)($item['amount'] ?? 0); // may include metal or net
                // Sales = metal value + diamond + stone (product value excluding making)
                $item_sales = $metal_val + $diamond_amt + $stone_amt;
                if ($item_sales <= 0 && $amount > 0) {
                    $item_sales = max(0, $amount - $making_amt); // fallback: amount minus making
                }
                $total_sales_amt += $item_sales;
                $total_making_amt += $making_amt;
                $total_tax_amt += $tax_amt;
            }
        }
        // Fallback when items have no breakdown: use invoice-level metal_amt, grand_total - net_total for tax
        if ($total_sales_amt <= 0) {
            $total_sales_amt = $metal_amt;
        }
        if ($total_tax_amt <= 0) {
            $total_tax_amt = max(0, $grand_total - $net_total);
        }
        if ($total_making_amt <= 0 && $grand_total > 0) {
            $total_making_amt = max(0, $net_total - $total_sales_amt - $total_tax_amt);
        }
        
        $against_cols = $has_against ? ", against_ledger, against_invoice_no" : "";
        
        // Helper to get previous balance for a ledger and compute new (credit = decrease balance for liability/equity style)
        $get_ledger_balance = function($ledger_name) use ($conn) {
            $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = '" . mysqli_real_escape_string($conn, $ledger_name) . "' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
            return (float)($r['balance_amount'] ?? 0);
        };
        // Making amount (sale): running balance on Making Sale Account; fallback to legacy "Making Amount Ledger" only
        $get_making_amount_ledger_balance = function() use ($conn) {
            $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = 'Making Sale Account' AND status = 1 ORDER BY id DESC LIMIT 1");
            if ($r && array_key_exists('balance_amount', $r)) {
                return (float) $r['balance_amount'];
            }
            $r = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = 'Making Amount Ledger' AND status = 1 ORDER BY id DESC LIMIT 1");

            return (float) ($r['balance_amount'] ?? 0);
        };
        
        // 1. Sales Account - Credit (Metal + Diamond + Stone)
        if ($total_sales_amt > 0) {
            $prev_sales = $get_ledger_balance('Sales Account');
            $new_sales_bal = $prev_sales - $total_sales_amt; // Credit increases (more negative for revenue)
            $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$invoice_no'" : "";
            $sales_sql = "INSERT INTO tbl_customer_ledger (customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0" . $ledger_branch_sql_val . ", 'Sales Account', 'sale_revenue', $invoice_id, '$invoice_no', '$invoice_date', 0.00, $total_sales_amt, $new_sales_bal, 'Sale Invoice: $invoice_no', " . ($ref_no ? "'$ref_no'" : "NULL") . ", 1, $user_id, NOW() $against_vals)";
            if (!mysqli_query($conn, $sales_sql)) {
                throw new Exception("Sales Account ledger entry failed: " . mysqli_error($conn));
            }
        }
        // 2. Making Sale Account - Credit (making charges on sale; mirrors Making Purchase Account debit on PI)
        if ($total_making_amt > 0) {
            $prev_making = $get_making_amount_ledger_balance();
            $new_making_bal = $prev_making - $total_making_amt;
            $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$invoice_no'" : "";
            $making_sql = "INSERT INTO tbl_customer_ledger (customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0" . $ledger_branch_sql_val . ", 'Making Sale Account', 'sale_revenue', $invoice_id, '$invoice_no', '$invoice_date', 0.00, $total_making_amt, $new_making_bal, 'Making charges - Sale Invoice: $invoice_no', " . ($ref_no ? "'$ref_no'" : "NULL") . ", 1, $user_id, NOW() $against_vals)";
            if (!mysqli_query($conn, $making_sql)) {
                throw new Exception("Making Sale Account ledger entry failed: " . mysqli_error($conn));
            }
        }
        // 3. Tax Ledger - Credit
        if ($total_tax_amt > 0) {
            $prev_tax = $get_ledger_balance('Tax Ledger');
            $new_tax_bal = $prev_tax - $total_tax_amt;
            $against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '$invoice_no'" : "";
            $tax_sql = "INSERT INTO tbl_customer_ledger (customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no, transaction_date, debit_amount, credit_amount, balance_amount, description, reference_no, status, created_by, created_at $against_cols) VALUES (0" . $ledger_branch_sql_val . ", 'Tax Ledger', 'sale_revenue', $invoice_id, '$invoice_no', '$invoice_date', 0.00, $total_tax_amt, $new_tax_bal, 'GST/Tax - Sale: $invoice_no', " . ($ref_no ? "'$ref_no'" : "NULL") . ", 1, $user_id, NOW() $against_vals)";
            if (!mysqli_query($conn, $tax_sql)) {
                throw new Exception("Tax Ledger entry failed: " . mysqli_error($conn));
            }
        }
        
        // Get previous balance from last ledger entry (same as purchase invoice)
        $prev_balance_select = "balance_amount, balance_gold, balance_silver";
        if ($has_gold_pure_cols) $prev_balance_select .= ", balance_gold_pure";
        $previous_balance_record = null;
        if ($customer_id > 0) {
            $previous_balance_record = getRecord("
                SELECT $prev_balance_select
                FROM tbl_customer_ledger 
                WHERE customer_id = $customer_id AND status = 1 
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_balance WHERE customer_id = $customer_id LIMIT 1");
            }
        }
        if (!$previous_balance_record && !empty($customer_name)) {
            $previous_balance_record = getRecord("
                SELECT $prev_balance_select
                FROM tbl_customer_ledger 
                WHERE customer_name = '$customer_name' AND status = 1 
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
            if (!$previous_balance_record) {
                $previous_balance_record = getRecord("SELECT $prev_balance_select FROM tbl_customer_balance WHERE customer_name = '$customer_name' LIMIT 1");
            }
        }
        
        $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? 0);
        $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? 0);
        $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? 0);
        $prev_balance_gold_pure = $has_gold_pure_cols ? (float)($previous_balance_record['balance_gold_pure'] ?? 0) : 0;
        
        // Total cash/bank payment (excluding previous balance)
        $total_cash_payment = 0;
        if (!empty($payments) && is_array($payments)) {
            foreach ($payments as $p) {
                $total_cash_payment += (float)($p['current_order_amount'] ?? ($p['amount'] ?? 0));
            }
        }

        // Party sale line: against_ledger (shared by full-from-PB and normal sale_invoice rows)
        $against_cols = $has_against ? ", against_ledger, against_invoice_no" : "";
        $ledger_gold_pure_cols = $has_gold_pure_cols ? "debit_gold_pure, credit_gold_pure," : "";
        $ledger_balance_gold_pure_col = $has_gold_pure_cols ? ", balance_gold_pure" : "";
        $against_ledger = '';
        $against_invoice_no = '';
        if (!empty($against_of)) {
            $against_bal = getRecord("SELECT balance_amount FROM tbl_customer_balance WHERE customer_name = '$against_of' ORDER BY last_updated DESC LIMIT 1");
            if ($against_bal) {
                $ab = (float)($against_bal['balance_amount'] ?? 0);
                $against_ledger = $against_of . '(' . number_format(abs($ab), 2) . ($ab >= 0 ? 'Dr' : 'Cr') . ')';
            } else {
                $against_ledger = $against_of;
            }
            $against_invoice_no = $ref_no;
        } else {
            $against_parts = [];
            if ($total_sales_amt > 0.00001) {
                $against_parts[] = 'Sales Account(' . number_format($total_sales_amt, 2) . 'Cr)';
            }
            if ($total_making_amt > 0.00001) {
                $against_parts[] = 'Making Sale Account(' . number_format($total_making_amt, 2) . 'Cr)';
            }
            if ($total_tax_amt > 0.00001) {
                $against_parts[] = 'Tax Ledger(' . number_format($total_tax_amt, 2) . 'Cr)';
            }
            $against_ledger = implode(', ', $against_parts);
            if ($against_ledger === '') {
                $against_ledger = 'Sales Account(' . number_format($grand_total, 2) . 'Cr)';
            }
            $against_invoice_no = $invoice_no;
        }
        $against_vals = $has_against ? ", " . ($against_ledger ? "'" . mysqli_real_escape_string($conn, $against_ledger) . "'" : "NULL") . ", " . ($against_invoice_no ? "'" . mysqli_real_escape_string($conn, $against_invoice_no) . "'" : "NULL") : "";

        // When full sale is paid from previous balance only (no cash): one sale_invoice row (debit sale, credit PB) — no separate previous_balance_payment line
        $full_from_previous = ($grand_total > 0 && $previous_balance_used_amt >= $grand_total && $total_cash_payment <= 0);

        if ($full_from_previous && $previous_balance_used_amt > 0) {
            $remaining_balance = $prev_balance_amount + (float)$previous_balance_used_amt;
            $amt_desc = number_format($previous_balance_used_amt, 2);
            $ledger_balance_gold_pure_val_fp = $has_gold_pure_cols ? ", " . (float)$prev_balance_gold_pure : "";
            $metal_vals_fp = "0.000, 0.000";
            if ($has_gold_pure_cols) {
                $metal_vals_fp .= ", 0.000, 0.000";
            }
            $metal_vals_fp .= ", 0.000, 0.000";
            $single_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 0) . $ledger_branch_sql_val . ",
                    '$customer_name',
                    'pos_sale_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    " . (float)$grand_total . ",
                    " . (float)$previous_balance_used_amt . ",
                    $metal_vals_fp,
                    $remaining_balance,
                    $prev_balance_gold $ledger_balance_gold_pure_val_fp,
                    $prev_balance_silver,
                    'Sale Invoice: $invoice_no (paid from previous balance - $amt_desc)',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    1,
                    $user_id,
                    NOW()
                    $against_vals
                )
            ";
            if (!mysqli_query($conn, $single_sql)) {
                throw new Exception("Sale invoice ledger entry failed (previous balance): " . mysqli_error($conn));
            }
            $new_balance_amount = $remaining_balance;
            $new_balance_gold = $prev_balance_gold;
            $new_balance_silver = $prev_balance_silver;
            $new_balance_gold_pure = $prev_balance_gold_pure;
        } else {
            // Normal: sale_invoice (credit includes previous balance when used) + payment entries — no separate previous_balance_payment row
            $ledger_debit_amount = $grand_total;
            $ledger_credit_amount = $previous_balance_used_amt > 0 ? $previous_balance_used_amt : 0;
            $new_balance_amount = $prev_balance_amount + $ledger_debit_amount - $ledger_credit_amount;
            $new_balance_gold_pure = $prev_balance_gold_pure;
            
            $fallback_gold = 0;
            $fallback_silver = 0;
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
                    if ($purity_weight <= 0) {
                        $purity_weight = $net_weight;
                    }
                    $purity_pct = 0;
                    if ($purity > 0) {
                        if ($purity <= 1) {
                            $purity_pct = $purity * 100;
                        } else if ($purity <= 100) {
                            $purity_pct = $purity;
                        } else {
                            $purity_pct = $purity / 10;
                        }
                    }
                    $product_name = trim($item['product_name'] ?? '');
                    $is_gold = ($purity_pct >= 75) || (stripos($product_name, 'gold') !== false);
                    $is_silver = ($purity_pct >= 50 && $purity_pct < 75) || (stripos($product_name, 'silver') !== false);
                    if ($is_gold) {
                        $fallback_gold += ($purity_weight > 0 ? $purity_weight : $net_weight);
                    } else if ($is_silver) {
                        $fallback_silver += ($purity_weight > 0 ? $purity_weight : $net_weight);
                    }
                }
            }

            if ($is_hedging) {
                $metal_is_silver = ($fallback_silver > $fallback_gold);
                $fix_wt = $metal_is_silver ? $fallback_silver : $fallback_gold;
                $r2_dg = $metal_is_silver ? 0 : $fix_wt;
                $r2_ds = $metal_is_silver ? $fix_wt : 0;
                $r2_gp = $r2_dg;

                $metal_zero = "0.000, 0.000";
                if ($has_gold_pure_cols) {
                    $metal_zero .= ", 0.000, 0.000";
                }
                $metal_zero .= ", 0.000, 0.000";

                // Mirror purchase-invoice hedging: PI party credits full invoice then debits metal-cost + credits metal;
                // SI party debits full GT then credits metal-cost + debits metal (same net receivable as PI net payable).
                $metal_cost_for_hedge_row = (float)$hedge_amt;
                $si_r1_debit = (float)$grand_total;
                $new_balance_amount = $prev_balance_amount + $si_r1_debit;
                $new_balance_gold = $prev_balance_gold;
                $new_balance_silver = $prev_balance_silver;
                $new_balance_gold_pure = $prev_balance_gold_pure;

                $ledger_balance_gold_pure_val_r1 = $has_gold_pure_cols ? ", " . (float)$new_balance_gold_pure : "";
                $ledger_sql_r1 = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 0) . $ledger_branch_sql_val . ",
                    '$customer_name',
                    'pos_sale_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    $si_r1_debit,
                    0.00,
                    $metal_zero,
                    $new_balance_amount,
                    $new_balance_gold $ledger_balance_gold_pure_val_r1,
                    $new_balance_silver,
                    'Sale Invoice: $invoice_no (Hedging)',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    1,
                    $user_id,
                    NOW()
                    $against_vals
                )
            ";
                if (!mysqli_query($conn, $ledger_sql_r1)) {
                    throw new Exception("Ledger entry failed: " . mysqli_error($conn));
                }

                if ($hedge_amt > 0 || $fix_wt > 0) {
                    $cr_h = (float)$metal_cost_for_hedge_row;
                    $si_row2_balance_amt = $new_balance_amount - $cr_h;
                    if ($metal_is_silver) {
                        $si_row2_balance_silver = $prev_balance_silver + $r2_ds;
                        $si_row2_balance_gold = $prev_balance_gold;
                        $si_row2_balance_gold_pure = $prev_balance_gold_pure;
                    } else {
                        $si_row2_balance_silver = $prev_balance_silver;
                        $si_row2_balance_gold = $prev_balance_gold + $r2_dg;
                        $si_row2_balance_gold_pure = $prev_balance_gold_pure + $r2_gp;
                    }

                    $ha_disp = getRecord("SELECT balance_amount FROM tbl_customer_ledger WHERE customer_name = 'Hedging Account' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
                    $ha_b = (float)($ha_disp['balance_amount'] ?? 0);
                    $hedge_against_txt = 'Hedging Account(' . number_format(abs($ha_b), 2) . ($ha_b >= 0 ? 'Dr' : 'Cr') . ')';
                    $link_inv = $invoice_no;
                    $against_vals_r2 = $has_against ? ", '" . mysqli_real_escape_string($conn, $hedge_against_txt) . "', '" . mysqli_real_escape_string($conn, $link_inv) . "'" : "";

                    if ($metal_is_silver) {
                        $r2_metal = "0.000, 0.000";
                        if ($has_gold_pure_cols) {
                            $r2_metal .= ", 0.000, 0.000";
                        }
                        $r2_metal .= ", " . (float)$r2_ds . ", 0.000";
                    } else {
                        $r2_metal = (float)$r2_dg . ", 0.000";
                        if ($has_gold_pure_cols) {
                            $r2_metal .= ", " . (float)$r2_gp . ", 0.000";
                        }
                        $r2_metal .= ", 0.000, 0.000";
                    }

                    $ledger_balance_gold_pure_val_r2 = $has_gold_pure_cols ? ", " . (float)$si_row2_balance_gold_pure : "";
                    $ledger_sql_r2 = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 0) . $ledger_branch_sql_val . ",
                    '$customer_name',
                    'pos_sale_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    0.00,
                    $cr_h,
                    $r2_metal,
                    $si_row2_balance_amt,
                    $si_row2_balance_gold $ledger_balance_gold_pure_val_r2,
                    $si_row2_balance_silver,
                    'Hedging offset: $invoice_no — Purchase Fixing (Hedging)',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    1,
                    $user_id,
                    NOW()
                    $against_vals_r2
                )
            ";
                    if (!mysqli_query($conn, $ledger_sql_r2)) {
                        throw new Exception("Hedging offset ledger entry failed: " . mysqli_error($conn));
                    }

                    $new_balance_amount = $si_row2_balance_amt;
                    $new_balance_gold = $si_row2_balance_gold;
                    $new_balance_silver = $si_row2_balance_silver;
                    $new_balance_gold_pure = $si_row2_balance_gold_pure;
                }

                if ($hedge_amt > 0 || $fix_wt > 0) {
                    $pf_last = getRecord("SELECT balance_amount, balance_gold, balance_silver " . ($has_gold_pure_cols ? ", balance_gold_pure" : "") . " FROM tbl_customer_ledger WHERE customer_name = 'Purchase Fixing Account' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
                    $pf_prev_amt = (float)($pf_last['balance_amount'] ?? 0);
                    $pf_prev_g = (float)($pf_last['balance_gold'] ?? 0);
                    $pf_prev_s = (float)($pf_last['balance_silver'] ?? 0);
                    $pf_prev_gp = $has_gold_pure_cols ? (float)($pf_last['balance_gold_pure'] ?? 0) : 0;
                    // Mirror PI hedging offset: debit PF (money + metal) vs prior credit PF.
                    $pf_new_amt = $pf_prev_amt + $hedge_amt;
                    $pf_new_g = $pf_prev_g + $r2_dg;
                    $pf_new_s = $pf_prev_s + $r2_ds;
                    $pf_new_gp = $pf_prev_gp + $r2_gp;
                    if ($metal_is_silver) {
                        $pf_metal = "0.000, 0.000";
                        if ($has_gold_pure_cols) {
                            $pf_metal .= ", 0.000, 0.000";
                        }
                        $pf_metal .= ", " . (float)$r2_ds . ", 0.000";
                    } else {
                        $pf_metal = (float)$r2_dg . ", 0.000";
                        if ($has_gold_pure_cols) {
                            $pf_metal .= ", " . (float)$r2_gp . ", 0.000";
                        }
                        $pf_metal .= ", 0.000, 0.000";
                    }
                    $pf_gp_bal = $has_gold_pure_cols ? ", " . (float)$pf_new_gp : "";
                    $pf_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                        balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                        description, reference_no, status, created_by, created_at
                        $against_cols
                    ) VALUES (
                        0" . $ledger_branch_sql_val . ",
                        'Purchase Fixing Account',
                        'pos_sale_invoice',
                        $invoice_id,
                        '$invoice_no',
                        '$invoice_date',
                        $hedge_amt,
                        0.00,
                        $pf_metal,
                        $pf_new_amt,
                        $pf_new_g $pf_gp_bal,
                        $pf_new_s,
                        'Sale hedge — Purchase Fixing: $invoice_no (Hedging)',
                        " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                        1,
                        $user_id,
                        NOW()
                        " . ($has_against ? ", '" . mysqli_real_escape_string($conn, 'Hedging Account') . "', '" . mysqli_real_escape_string($conn, $link_inv) . "'" : "") . "
                    )
                ";
                    if (!mysqli_query($conn, $pf_sql)) {
                        throw new Exception("Purchase Fixing Account ledger entry failed: " . mysqli_error($conn));
                    }

                    $ha_last = getRecord("SELECT balance_amount, balance_gold, balance_silver " . ($has_gold_pure_cols ? ", balance_gold_pure" : "") . " FROM tbl_customer_ledger WHERE customer_name = 'Hedging Account' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
                    $ha_prev_amt = (float)($ha_last['balance_amount'] ?? 0);
                    $ha_prev_gold = (float)($ha_last['balance_gold'] ?? 0);
                    $ha_prev_silver = (float)($ha_last['balance_silver'] ?? 0);
                    $ha_prev_gold_pure = $has_gold_pure_cols ? (float)($ha_last['balance_gold_pure'] ?? 0) : 0;
                    // Mirror PI Hedging Account: credit money + credit metal (was debit/debit on sale hedge).
                    $ha_new_amt = $ha_prev_amt - $hedge_amt;
                    $ha_new_gold = $ha_prev_gold + $r2_dg;
                    $ha_new_silver = $ha_prev_silver + $r2_ds;
                    $ha_new_gold_pure = $ha_prev_gold_pure + $r2_gp;
                    if ($metal_is_silver) {
                        $ha_metal_vals = "0.000, 0.000";
                        if ($has_gold_pure_cols) {
                            $ha_metal_vals .= ", 0.000, 0.000";
                        }
                        $ha_metal_vals .= ", 0.000, " . (float)$r2_ds;
                    } else {
                        $ha_metal_vals = "0.000, " . (float)$r2_dg;
                        if ($has_gold_pure_cols) {
                            $ha_metal_vals .= ", 0.000, " . (float)$r2_gp;
                        }
                        $ha_metal_vals .= ", 0.000, 0.000";
                    }
                    $ha_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$ha_new_gold_pure : "";
                    $ha_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                        balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                        description, reference_no, status, created_by, created_at
                        $against_cols
                    ) VALUES (
                        0" . $ledger_branch_sql_val . ",
                        'Hedging Account',
                        'pos_sale_invoice',
                        $invoice_id,
                        '$invoice_no',
                        '$invoice_date',
                        0.00,
                        $hedge_amt,
                        $ha_metal_vals,
                        $ha_new_amt,
                        $ha_new_gold $ha_balance_gold_pure_val,
                        $ha_new_silver,
                        'Sale hedge — Hedging: $invoice_no (Hedging)',
                        " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                        1,
                        $user_id,
                        NOW()
                        " . ($has_against ? ", '" . mysqli_real_escape_string($conn, $customer_name) . "', '" . mysqli_real_escape_string($conn, $link_inv) . "'" : "") . "
                    )
                ";
                    if (!mysqli_query($conn, $ha_sql)) {
                        throw new Exception("Hedging Account ledger entry failed: " . mysqli_error($conn));
                    }
                }
            } else {
                $total_gold_weight = 0;
                $total_silver_weight = 0;
                $total_gold_pure = 0;
                $new_balance_gold = $prev_balance_gold;
                $new_balance_silver = $prev_balance_silver;
                $new_balance_gold_pure = $prev_balance_gold_pure;

                $ledger_balance_gold_pure_val = $has_gold_pure_cols ? ", " . (float)$new_balance_gold_pure : "";
                $metal_vals = "0.000, 0.000";
                if ($has_gold_pure_cols) {
                    $metal_vals .= ", 0.000, 0.000";
                }
                $metal_vals .= ", 0.000, 0.000";
                $ledger_sql = "
                INSERT INTO tbl_customer_ledger (
                    customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                    transaction_date, debit_amount, credit_amount,
                    debit_gold, credit_gold, $ledger_gold_pure_cols debit_silver, credit_silver,
                    balance_amount, balance_gold $ledger_balance_gold_pure_col, balance_silver,
                    description, reference_no, status, created_by, created_at
                    $against_cols
                ) VALUES (
                    " . ($customer_id > 0 ? $customer_id : 0) . $ledger_branch_sql_val . ",
                    '$customer_name',
                    'pos_sale_invoice',
                    $invoice_id,
                    '$invoice_no',
                    '$invoice_date',
                    $ledger_debit_amount,
                    $ledger_credit_amount,
                    $metal_vals,
                    $new_balance_amount,
                    $new_balance_gold $ledger_balance_gold_pure_val,
                    $new_balance_silver,
                    'Sale Invoice: $invoice_no',
                    " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                    1,
                    $user_id,
                    NOW()
                    $against_vals
                )
            ";
                if (!mysqli_query($conn, $ledger_sql)) {
                    throw new Exception("Ledger entry failed: " . mysqli_error($conn));
                }
            }

            // Hedging: previous balance applied on last party sale_invoice row (same invoice — no separate previous_balance_payment line)
            if ($is_hedging && $previous_balance_used_amt > 0.00001) {
                $pb_amt = (float)$previous_balance_used_amt;
                $inv_esc = mysqli_real_escape_string($conn, $invoice_no);
                $last_party_row = getRecord("
                    SELECT id, balance_amount, credit_amount
                    FROM tbl_customer_ledger
                    WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . "
                    AND customer_name = '$customer_name' AND status = 1
                    AND transaction_type = 'pos_sale_invoice' AND transaction_no = '$inv_esc'
                    ORDER BY id DESC LIMIT 1
                ");
                if (!empty($last_party_row['id'])) {
                    $rid = (int)$last_party_row['id'];
                    $new_ca = (float)($last_party_row['credit_amount'] ?? 0) + $pb_amt;
                    $new_ba = (float)($last_party_row['balance_amount'] ?? 0) - $pb_amt;
                    mysqli_query($conn, "UPDATE tbl_customer_ledger SET credit_amount = $new_ca, balance_amount = $new_ba WHERE id = $rid AND status = 1 LIMIT 1");
                    $new_balance_amount = $new_ba;
                }
            }
        }
        
        // Money + metal exchange: auto sale receipt voucher (tbl_sale_receipt_vouchers, SRV bill series) + sale_receipt_voucher ledger; scrap stays on sale invoice no.
        $si_money_payments = [];
        if (!empty($payments) && is_array($payments)) {
            foreach ($payments as $payment) {
                if (sale_invoice_payment_is_auto_receipt_money($payment)) {
                    $si_money_payments[] = $payment;
                }
            }
        }
        if (!empty($si_money_payments)) {
            sale_invoice_create_auto_receipt_voucher_and_post_ledger(
                $conn,
                $invoice_no,
                $invoice_date,
                $customer_id,
                $customer_name,
                $currency,
                $sales_person,
                $si_money_payments,
                $user_id,
                $ref_no !== '' ? $ref_no : null,
                $ledger_has_branch_col,
                $sale_invoice_branch_for_ledger
            );
        }

        // Payment entries: party CREDIT (mirror of purchase party DEBIT); Metal Exchange / scrap same pattern as PI with reversed Dr/Cr columns.
        if (!empty($payments) && is_array($payments)) {
            foreach ($payments as $payment) {
                if (sale_invoice_payment_is_auto_receipt_money($payment)) {
                    continue;
                }
                $current_order_amount = (float)($payment['current_order_amount'] ?? ($payment['amount'] ?? 0));
                $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
                $deposit_into = esc($payment['deposit_into'] ?? 'Cash');
                $pay_type_raw = strtolower(trim($payment['payment_type'] ?? 'cash'));
                
                if ($current_order_amount > 0) {
                    $pay_bal_pure_sel = $has_gold_pure_cols ? ', balance_gold_pure' : '';
                    $last_balance_record = getRecord("
                        SELECT balance_amount, balance_gold, balance_silver $pay_bal_pure_sel
                        FROM tbl_customer_ledger 
                        WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . " 
                        AND customer_name = '$customer_name' 
                        AND status = 1 
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                    $current_running_balance_amount = (float)($last_balance_record['balance_amount'] ?? $new_balance_amount);
                    $current_running_balance_gold = (float)($last_balance_record['balance_gold'] ?? $new_balance_gold);
                    $current_running_balance_silver = (float)($last_balance_record['balance_silver'] ?? $new_balance_silver);
                    $current_running_balance_gold_pure = $has_gold_pure_cols ? (float)($last_balance_record['balance_gold_pure'] ?? $new_balance_gold_pure) : 0.0;
                    $current_running_balance_amount -= $current_order_amount;

                    $me_w = sale_invoice_metal_exchange_ledger_wts($conn, $payment);
                    $pay_dg = (float) $me_w['dg'];
                    $pay_cg = (float) $me_w['cg'];
                    $pay_dgp = (float) $me_w['dgp'];
                    $pay_cgp = (float) $me_w['cgp'];
                    $pay_ds = (float) $me_w['ds'];
                    $pay_cs = (float) $me_w['cs'];
                    $new_run_bg = $current_running_balance_gold + $pay_dg - $pay_cg;
                    $new_run_bs = $current_running_balance_silver + $pay_ds - $pay_cs;
                    $new_run_bgp = $has_gold_pure_cols ? ($current_running_balance_gold_pure + $pay_dgp - $pay_cgp) : 0.0;

                    $is_scrap_payment_line = (strpos($pay_type_raw, 'scrap') !== false);
                    $pay_crdr = $is_scrap_payment_line ? 'Dr' : 'Cr';
                    if (auragold_payment_is_cheque_type($pay_type_raw)) {
                        $payment_against_ledger = auragold_cheque_payment_against_label($current_order_amount, 'receivable');
                    } else {
                        $payment_against_ledger = $deposit_into . '(' . number_format($current_order_amount, 2) . $pay_crdr . ')';
                    }

                    $pay_desc_suffix = ($pay_cg > 0.00001 || $pay_cgp > 0.00001 || $pay_cs > 0.00001) ? ' — Metal Exchange' : '';
                    $pay_desc = mysqli_real_escape_string($conn, 'Payment for Sale Invoice: ' . $invoice_no . $pay_desc_suffix);

                    $gp_ins_cols = $has_gold_pure_cols ? 'debit_gold_pure, credit_gold_pure, ' : '';
                    $gp_ins_vals = $has_gold_pure_cols ? ("
                                " . $pay_dgp . ",
                                " . $pay_cgp . ",
") : '';
                    $bgp_ins_col = $has_gold_pure_cols ? ', balance_gold_pure' : '';
                    $bal_gold_vals = $has_gold_pure_cols ? $new_run_bg . ', ' . $new_run_bgp : (string) $new_run_bg;

                    $pay_against_cols = $has_against ? ", against_ledger, against_invoice_no" : "";
                    $pay_against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $payment_against_ledger) . "', '$invoice_no'" : "";
                    $payment_ledger_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            debit_gold, credit_gold, $gp_ins_cols debit_silver, credit_silver,
                            balance_amount, balance_gold $bgp_ins_col, balance_silver,
                            description, status, created_by, created_at
                            $pay_against_cols
                        ) VALUES (
                            " . ($customer_id > 0 ? $customer_id : 0) . $ledger_branch_sql_val . ",
                            '$customer_name',
                            'payment',
                            $invoice_id,
                            '$invoice_no',
                            '$invoice_date',
                            0.00,
                            $current_order_amount,
                            $pay_dg,
                            $pay_cg,
                            $gp_ins_vals
                            $pay_ds,
                            $pay_cs,
                            $current_running_balance_amount,
                            $bal_gold_vals,
                            $new_run_bs,
                            '$pay_desc',
                            1,
                            $user_id,
                            NOW()
                            $pay_against_vals
                        )
                    ";
                    if (!mysqli_query($conn, $payment_ledger_sql)) {
                        throw new Exception("Payment ledger entry failed: " . mysqli_error($conn));
                    }
                    $new_balance_amount = $current_running_balance_amount;
                    $new_balance_gold = $new_run_bg;
                    $new_balance_silver = $new_run_bs;
                    if ($has_gold_pure_cols) {
                        $new_balance_gold_pure = $new_run_bgp;
                    }
                    
                    // Cash/Bank ledger entry (customer pays us → Cash increases: debit, same pattern as purchase but reversed)
                    if (!empty($deposit_into) && auragold_payment_should_post_deposit_ledger($pay_type_raw)) {
                        $cash_balance_record = getRecord("
                            SELECT balance_amount 
                            FROM tbl_customer_ledger 
                            WHERE customer_name = '$deposit_into' 
                            AND status = 1 
                            ORDER BY transaction_date DESC, id DESC 
                            LIMIT 1
                        ");
                        $cash_prev_balance = (float)($cash_balance_record['balance_amount'] ?? 0);
                        $cash_new_balance = $cash_prev_balance + $current_order_amount; // Cash increases when customer pays
                        
                        $cash_against_cols = $has_against ? ", against_ledger, against_invoice_no" : "";
                        $cash_party_against = accountledger_against_party_payment_label($customer_name, $pay_type_raw, $current_order_amount);
                        $cash_against_vals = $has_against ? ", '" . mysqli_real_escape_string($conn, $cash_party_against) . "', '$invoice_no'" : "";
                        $cash_ledger_sql = "
                            INSERT INTO tbl_customer_ledger (
                                customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                                transaction_date, debit_amount, credit_amount,
                                balance_amount, description, status, created_by, created_at
                                $cash_against_cols
                            ) VALUES (
                                0" . $ledger_branch_sql_val . ",
                                '$deposit_into',
                                'payment',
                                $invoice_id,
                                '$invoice_no',
                                '$invoice_date',
                                $current_order_amount,
                                0.00,
                                $cash_new_balance,
                                'Receipt from $customer_name for Invoice: $invoice_no',
                                1,
                                $user_id,
                                NOW()
                                $cash_against_vals
                            )
                        ";
                        if (!mysqli_query($conn, $cash_ledger_sql)) {
                            throw new Exception("Cash ledger entry failed: " . mysqli_error($conn));
                        }
                    }
                }
            }
        }
        
        // Update tbl_customer_balance with final running balance (same as purchase invoice)
        $final_balance_record = null;
        if ($customer_id > 0) {
            $final_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_ledger 
                WHERE customer_id = $customer_id AND status = 1 
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
        }
        if (!$final_balance_record && !empty($customer_name)) {
            $final_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_ledger 
                WHERE customer_name = '$customer_name' AND status = 1 
                ORDER BY transaction_date DESC, id DESC 
                LIMIT 1
            ");
        }
        $final_balance_amount = $final_balance_record ? (float)($final_balance_record['balance_amount'] ?? 0) : $new_balance_amount;
        $final_balance_gold = $final_balance_record ? (float)($final_balance_record['balance_gold'] ?? 0) : $new_balance_gold;
        $final_balance_silver = $final_balance_record ? (float)($final_balance_record['balance_silver'] ?? 0) : $new_balance_silver;
        
        $existing_balance = null;
        if ($customer_id > 0) {
            $existing_balance = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_id = $customer_id LIMIT 1");
        } else if (!empty($customer_name)) {
            $existing_balance = getRecord("SELECT id FROM tbl_customer_balance WHERE customer_name = '$customer_name' LIMIT 1");
        }
        if ($existing_balance) {
            if ($customer_id > 0) {
                $balance_update_sql = "UPDATE tbl_customer_balance SET balance_amount = $final_balance_amount, balance_gold = $final_balance_gold, balance_silver = $final_balance_silver, last_transaction_date = '$invoice_date', last_updated = NOW() WHERE customer_id = $customer_id";
            } else {
                $balance_update_sql = "UPDATE tbl_customer_balance SET balance_amount = $final_balance_amount, balance_gold = $final_balance_gold, balance_silver = $final_balance_silver, last_transaction_date = '$invoice_date', last_updated = NOW() WHERE customer_name = '$customer_name'";
            }
        } else {
            $balance_update_sql = "INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_transaction_date, last_updated) VALUES (" . ($customer_id > 0 ? $customer_id : 0) . ", '$customer_name', $final_balance_amount, $final_balance_gold, $final_balance_silver, '$invoice_date', NOW())";
        }
        if (!mysqli_query($conn, $balance_update_sql)) {
            throw new Exception("Balance update failed: " . mysqli_error($conn));
        }
    }

    // Standard fixing: remove purchase fixing voucher rows (and line items) for this sale invoice
    if (!$is_hedging && $invoice_id > 0 && function_exists('auragold_delete_purchase_fixing_direct_for_sale_invoice')) {
        auragold_delete_purchase_fixing_direct_for_sale_invoice($conn, $invoice_no);
    }
    
    // Hedging: always create Purchase Fixing Direct row so transaction report shows it (amount may be 0 if no breakdown captured)
    if ($is_hedging) {
        if (function_exists('auragold_delete_purchase_fixing_direct_for_sale_invoice')) {
            auragold_delete_purchase_fixing_direct_for_sale_invoice($conn, $invoice_no);
        }

        $pf_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct'");
        if ($pf_table && mysqli_num_rows($pf_table) > 0) {
            mysqli_free_result($pf_table);
            $pf_columns = [];
            $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct");
            if ($cols) {
                while ($c = mysqli_fetch_assoc($cols)) {
                    $pf_columns[strtolower($c['Field'])] = $c['Field'];
                }
                mysqli_free_result($cols);
            }
            $voucher_src = null;
            foreach (['invoice_no', 'ref_no', 'order_no'] as $vc) {
                if (isset($pf_columns[$vc])) {
                    $voucher_src = $vc;
                    break;
                }
            }
            if ($voucher_src) {
                $voucher_field_sql = '`' . $pf_columns[$voucher_src] . '`';
                $last_pf = getRecord("SELECT $voucher_field_sql AS pf_last_voucher FROM tbl_purchase_fixing_direct ORDER BY id DESC LIMIT 1");
                $pf_num = 1;
                $last_no = $last_pf ? (string)($last_pf['pf_last_voucher'] ?? '') : '';
                if ($last_no !== '' && preg_match('/PFD-(\d+)/i', $last_no, $m)) {
                    $pf_num = (int)$m[1] + 1;
                } elseif ($last_no !== '' && preg_match('/PF-(\d+)/i', $last_no, $m)) {
                    $pf_num = (int)$m[1] + 1;
                } else {
                    $id_row = getRecord("SELECT COALESCE(MAX(id), 0) AS m FROM tbl_purchase_fixing_direct");
                    $pf_num = max(1, (int)($id_row['m'] ?? 0) + 1);
                }
                $pf_invoice_no = 'PFD-' . $pf_num;
                $against_of_pf = 'Fixing of ' . $invoice_no;
                $pf_customer = mysqli_real_escape_string($conn, $customer_name);
                $pf_date = mysqli_real_escape_string($conn, $invoice_date);
                $pf_fixing_type = mysqli_real_escape_string($conn, 'Hedging');
                $pf_amt = max(0, (float)$hedge_amt);
                $ins_cols = [];
                $ins_vals = [];
                if (isset($pf_columns['invoice_no'])) { $ins_cols[] = $pf_columns['invoice_no']; $ins_vals[] = "'$pf_invoice_no'"; }
                if (isset($pf_columns['ref_no'])) { $ins_cols[] = $pf_columns['ref_no']; $ins_vals[] = "'$pf_invoice_no'"; }
                if (isset($pf_columns['order_no'])) { $ins_cols[] = $pf_columns['order_no']; $ins_vals[] = "'$pf_invoice_no'"; }
                if (isset($pf_columns['customer_id']) && $customer_id > 0) { $ins_cols[] = $pf_columns['customer_id']; $ins_vals[] = (string)$customer_id; }
                if (isset($pf_columns['customer_name'])) { $ins_cols[] = $pf_columns['customer_name']; $ins_vals[] = "'$pf_customer'"; }
                if (isset($pf_columns['supplier_name'])) { $ins_cols[] = $pf_columns['supplier_name']; $ins_vals[] = "'$pf_customer'"; }
                if (isset($pf_columns['against_of'])) { $ins_cols[] = $pf_columns['against_of']; $ins_vals[] = "'" . mysqli_real_escape_string($conn, $against_of_pf) . "'"; }
                if (isset($pf_columns['sale_invoice_no'])) { $ins_cols[] = $pf_columns['sale_invoice_no']; $ins_vals[] = "'" . mysqli_real_escape_string($conn, $invoice_no) . "'"; }
                if (isset($pf_columns['reference'])) { $ins_cols[] = $pf_columns['reference']; $ins_vals[] = "'" . mysqli_real_escape_string($conn, $invoice_no) . "'"; }
                if (isset($pf_columns['invoice_date'])) { $ins_cols[] = $pf_columns['invoice_date']; $ins_vals[] = "'$pf_date'"; }
                if (isset($pf_columns['fixing_date'])) { $ins_cols[] = $pf_columns['fixing_date']; $ins_vals[] = "'$pf_date'"; }
                if (isset($pf_columns['fixing_type'])) { $ins_cols[] = $pf_columns['fixing_type']; $ins_vals[] = "'$pf_fixing_type'"; }
                if (isset($pf_columns['currency'])) { $ins_cols[] = $pf_columns['currency']; $ins_vals[] = "'$currency'"; }
                if (isset($pf_columns['subtotal'])) { $ins_cols[] = $pf_columns['subtotal']; $ins_vals[] = $pf_amt; }
                if (isset($pf_columns['net_total'])) { $ins_cols[] = $pf_columns['net_total']; $ins_vals[] = $pf_amt; }
                if (isset($pf_columns['grand_total'])) { $ins_cols[] = $pf_columns['grand_total']; $ins_vals[] = $pf_amt; }
                if (isset($pf_columns['total_amount'])) { $ins_cols[] = $pf_columns['total_amount']; $ins_vals[] = $pf_amt; }
                if (isset($pf_columns['paid_amt'])) { $ins_cols[] = $pf_columns['paid_amt']; $ins_vals[] = '0.00'; }
                if (isset($pf_columns['balance_amt'])) { $ins_cols[] = $pf_columns['balance_amt']; $ins_vals[] = '0.00'; }
                if (isset($pf_columns['status'])) { $ins_cols[] = $pf_columns['status']; $ins_vals[] = "'draft'"; }
                if (isset($pf_columns['created_by'])) { $ins_cols[] = $pf_columns['created_by']; $ins_vals[] = (string)$user_id; }
                if (isset($pf_columns['created_at'])) { $ins_cols[] = $pf_columns['created_at']; $ins_vals[] = 'NOW()'; }
                if (count($ins_cols) > 0) {
                    $pf_sql = "INSERT INTO tbl_purchase_fixing_direct (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $ins_vals) . ")";
                    if (!mysqli_query($conn, $pf_sql)) {
                        throw new Exception("Purchase fixing (making amount) entry failed: " . mysqli_error($conn));
                    }
                    $pfd_new_id = mysqli_insert_id($conn);
                    $pit_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct_items'");
                    if ($pfd_new_id > 0 && $pit_chk && mysqli_num_rows($pit_chk) > 0) {
                        mysqli_free_result($pit_chk);
                        foreach ($pfd_item_candidates as $cand) {
                            $gw = (float)($cand['gross_wt'] ?? 0);
                            $pw = (float)($cand['purity_wt'] ?? 0);
                            $am = (float)($cand['amount'] ?? 0);
                            if ($am <= 0 && $gw <= 0 && $pw <= 0) {
                                continue;
                            }
                            $mid = (int)($cand['metal_id'] ?? 0);
                            $mid_sql = $mid > 0 ? (string)$mid : 'NULL';
                            $rt = (float)($cand['rate'] ?? 0);
                            $pu = (float)($cand['purity'] ?? 1);
                            $pfi_sql = "INSERT INTO tbl_purchase_fixing_direct_items (fixing_id, metal_id, gross_wt, purity_wt, rate, amount, purity, created_at) VALUES ("
                                . (int)$pfd_new_id . ", $mid_sql, $gw, $pw, $rt, $am, $pu, NOW())";
                            if (!mysqli_query($conn, $pfi_sql)) {
                                throw new Exception("Purchase fixing item failed: " . mysqli_error($conn));
                            }
                        }
                    } elseif ($pit_chk) {
                        mysqli_free_result($pit_chk);
                    }
                } else {
                    error_log('Hedging: tbl_purchase_fixing_direct has no insertable columns matched — PFD row skipped');
                }
            } else {
                error_log('Hedging: tbl_purchase_fixing_direct has no invoice_no/ref_no/order_no — PFD row skipped');
            }
        } else {
            if ($pf_table) {
                mysqli_free_result($pf_table);
            }
        }
    }

    if ($invoice_id > 0) {
        @mysqli_query($conn, "DELETE FROM invoice_fixing_mapping WHERE source_type = 'pos_sale_invoice' AND source_transaction_id = " . (int)$invoice_id);
    }

    if ($is_hedging) {
        $fg = 0;
        $fs = 0;
        if (!empty($items) && is_array($items)) {
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
                if ($purity_weight <= 0) {
                    $purity_weight = $net_weight;
                }
                $purity_pct = 0;
                if ($purity > 0) {
                    if ($purity <= 1) {
                        $purity_pct = $purity * 100;
                    } else if ($purity <= 100) {
                        $purity_pct = $purity;
                    } else {
                        $purity_pct = $purity / 10;
                    }
                }
                $product_name = trim($item['product_name'] ?? '');
                $is_gold_it = ($purity_pct >= 75) || (stripos($product_name, 'gold') !== false);
                $is_silver_it = ($purity_pct >= 50 && $purity_pct < 75) || (stripos($product_name, 'silver') !== false);
                if ($is_gold_it) {
                    $fg += ($purity_weight > 0 ? $purity_weight : $net_weight);
                } else if ($is_silver_it) {
                    $fs += ($purity_weight > 0 ? $purity_weight : $net_weight);
                }
            }
        }
        $map_wt = ($fs > $fg) ? $fs : $fg;
        $mt = mysqli_real_escape_string($conn, ($fs > $fg) ? 'Silver' : 'Gold');
        $inv_esc_map = mysqli_real_escape_string($conn, $invoice_no);
        @mysqli_query($conn, "
            INSERT INTO invoice_fixing_mapping (
                source_type, source_transaction_id, source_invoice_no,
                against_invoice_type, against_invoice_id, against_invoice_no,
                fixing_type, metal_type, fixing_weight, fixing_rate, fixing_amount, status, created_at
            ) VALUES (
                'pos_sale_invoice', " . (int)$invoice_id . ", '$inv_esc_map',
                NULL, NULL, NULL,
                'Hedging', '$mt', " . (float)$map_wt . ", 0, " . (float)$hedge_amt . ", 1, NOW()
            )
        ");
    }

    // GST breakdown on invoice header (CGST/SGST vs IGST) from owner vs customer state
    if ($invoice_id > 0) {
        $sum_line_tax = 0.0;
        if (!empty($items) && is_array($items)) {
            foreach ($items as $it) {
                $sum_line_tax += (float) ($it['tax_amount'] ?? $it['tax'] ?? 0);
            }
        }
        $owner_st = '';
        $wb_gst = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : (isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : 0);
        if ($wb_gst > 0) {
            $owner_st = auragold_branch_profile_state_name($conn, $wb_gst);
        }
        $cust_st = trim((string) ($_POST['customer_billing_state'] ?? ''));
        if ($cust_st === '' && $customer_id > 0) {
            $crgst = getRecord('SELECT billing_state FROM tbl_customers WHERE id = ' . (int) $customer_id . ' LIMIT 1');
            if ($crgst && isset($crgst['billing_state'])) {
                $cust_st = trim((string) $crgst['billing_state']);
            }
        }
        $interstate = auragold_gst_is_interstate_transaction($owner_st, $cust_st, $conn);
        $gst_split = auragold_gst_split_totals_from_line_tax((float) $sum_line_tax, $interstate);
        $mode_esc = mysqli_real_escape_string($conn, (string) ($gst_split['mode'] ?? ''));
        $cgst_amt = (float) ($gst_split['cgst'] ?? 0);
        $sgst_amt = (float) ($gst_split['sgst'] ?? 0);
        $igst_amt = (float) ($gst_split['igst'] ?? 0);

        $chk_gst = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'gst_supply_mode'");
        if (!$chk_gst || mysqli_num_rows($chk_gst) === 0) {
            @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN gst_supply_mode VARCHAR(24) NULL DEFAULT NULL');
            @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN gst_cgst_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00');
            @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN gst_sgst_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00');
            @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN gst_igst_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        }
        if ($chk_gst) {
            mysqli_free_result($chk_gst);
        }
        mysqli_query(
            $conn,
            'UPDATE tbl_pos_sale_invoices SET gst_supply_mode = \'' . $mode_esc . '\', gst_cgst_amount = ' . $cgst_amt . ', gst_sgst_amount = ' . $sgst_amt . ', gst_igst_amount = ' . $igst_amt . ' WHERE id = ' . (int) $invoice_id
        );
    }

    $eway_json_summary   = ['generated' => false, 'skipped' => true, 'message' => ''];
    $eway_bill_for_response = [
        'status'     => 'skipped',
        'ewayBillNo' => '',
        'ewayBillDate' => '',
        'validUpto'  => '',
        'message'    => '',
    ];

    if ((int) $invoice_id > 0) {
        require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
        auragold_voucher_apply_pending_diamond_stone_from_post($conn, 'pos_sale_invoice', (int) $invoice_id, $invoice_no, $invoice_date);
    }

    require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';
    if (function_exists('auragold_sync_voucher_cheque_entries')) {
        auragold_sync_voucher_cheque_entries($conn, [
            'voucher_no' => $invoice_no,
            'voucher_type' => 'POS Sale Invoice',
            'voucher_date' => $invoice_date,
            'account_ledger' => $customer_name,
            'transaction_id' => (int) $invoice_id,
            'user_id' => (int) $user_id,
            'payments' => isset($payments) && is_array($payments) ? $payments : [],
            'pdc_direction' => 'receivable',
        ]);
    }

    mysqli_commit($conn);

    if (!AURAGOLD_EWAY_DISABLED && $enable_eway_bill && function_exists('ewaybill_generate_from_sale_invoice') && (int) $invoice_id > 0) {
        $existing_ew = @getRecord('SELECT eway_bill_no FROM tbl_pos_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        if ($existing_ew && !empty($existing_ew['eway_bill_no'])) {
            $eway_json_summary['skipped'] = false;
            $eway_json_summary['message'] = 'e-Way Bill already present for this invoice. Use Regenerate to create a new one.';
            $eway_bill_for_response = [
                'status'     => 'error',
                'ewayBillNo' => (string) $existing_ew['eway_bill_no'],
                'ewayBillDate' => '',
                'validUpto'  => '',
                'message'    => $eway_json_summary['message'],
            ];
        } else {
            $ewGen = ewaybill_generate_from_sale_invoice($conn, (int) $invoice_id);
            $eway_bill_for_response = [
                'status'     => (string) ($ewGen['eway_bill']['status'] ?? ($ewGen['ok'] ? 'success' : 'error')),
                'ewayBillNo' => (string) ($ewGen['eway_bill']['ewayBillNo'] ?? $ewGen['ewayBillNo'] ?? ''),
                'ewayBillDate' => (string) ($ewGen['eway_bill']['ewayBillDate'] ?? ''),
                'validUpto'  => (string) ($ewGen['eway_bill']['validUpto'] ?? $ewGen['validUpto'] ?? ''),
                'message'    => (string) ($ewGen['eway_bill']['message'] ?? $ewGen['message'] ?? ''),
            ];
            $eway_json_summary['skipped']  = false;
            $eway_json_summary['generated'] = !empty($ewGen['ok']);
            $eway_json_summary['eway_bill_no']  = $eway_bill_for_response['ewayBillNo'];
            $eway_json_summary['eway_bill_date'] = '';
            if (!empty($ewGen['ok'])) {
                $dbSt = (string) ($ewGen['eway_db_status'] ?? '');
                $eway_json_summary['eway_status'] = $dbSt !== '' ? $dbSt : 'success';
            } else {
                $eway_json_summary['eway_status'] = 'failed';
            }
            $eway_json_summary['message']  = (string) ($ewGen['message'] ?? $eway_bill_for_response['message']);
        }
    }

    if (!AURAGOLD_EWAY_DISABLED && !empty($enable_eway_bill) && (int) $invoice_id > 0) {
        $ewRespRow = @getRecord('SELECT eway_response FROM tbl_pos_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        if (is_array($ewRespRow) && isset($ewRespRow['eway_response']) && (string) $ewRespRow['eway_response'] !== '') {
            $er = (string) $ewRespRow['eway_response'];
            $eway_bill_for_response['api_response'] = function_exists('ewaybill_sanitize_eway_api_json_for_ui')
                ? ewaybill_sanitize_eway_api_json_for_ui($er)
                : $er;
        }
    }

    $sync_si_scrap = __DIR__ . '/../includes/sync_sale_scrap_to_ojb.php';
    if (is_file($sync_si_scrap)) {
        require_once $sync_si_scrap;
        if (function_exists('syncSaleScrapToOjb')) {
            syncSaleScrapToOjb($conn, (int) $invoice_id);
        }
    }

    require_once __DIR__ . '/../includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'POS Sale Invoice',
        'verb' => $is_update ? 'updated' : 'created',
        'number' => $invoice_no,
        'party' => $customer_name,
        'doc_date' => $invoice_date,
        'due_date' => $due_date,
        'ref_id' => (int) $invoice_id,
    ]);

    ob_end_clean();
    $success_payload = [
        'status'     => 'success',
        'message'    => 'Sale invoice saved successfully',
        'order_id'   => $invoice_id,
        'invoice_id' => (int) $invoice_id,
        'order_no'   => $invoice_no,
        'eway'       => $eway_json_summary,
        'eway_bill'  => $eway_bill_for_response,
        'new_barcodes' => $metal_exchange_barcodes_out,
    ];
    if (empty($eway_json_summary['skipped']) && (($eway_json_summary['eway_status'] ?? '') === 'failed')) {
        $success_payload['eway_notice'] = 'e-Way Bill failed: ' . ($eway_json_summary['message'] ?? 'see eway_response on invoice');
    }
    if (isset($ewGen) && is_array($ewGen)) {
        if (!empty($ewGen['eway_debug_payload'])) {
            $success_payload['eway_debug_payload'] = (string) $ewGen['eway_debug_payload'];
        }
        if (!empty($ewGen['final_payload_sent_to_api'])) {
            $success_payload['final_payload_sent_to_api'] = (string) $ewGen['final_payload_sent_to_api'];
        }
        if (!empty($ewGen['eway_debug_message'])) {
            $success_payload['eway_debug_message'] = (string) $ewGen['eway_debug_message'];
        }
    }
    echo json_encode($success_payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Sale Invoice Save Error: " . $e->getMessage());
    error_log("Sale Invoice Save Trace: " . $e->getTraceAsString());
    ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
