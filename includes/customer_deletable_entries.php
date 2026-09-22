<?php
/**
 * Fetch customer-linked vouchers that can be bulk-deleted from Utilities.
 */
if (!function_exists('auragold_customer_deletable_party_sql')) {
    function auragold_customer_deletable_party_sql(int $customer_id, string $customer_name, string $id_col, string $name_col): string
    {
        $conds = [];
        if ($customer_id > 0) {
            $conds[] = $id_col . ' = ' . (int) $customer_id;
        }
        $customer_name = trim($customer_name);
        if ($customer_name !== '') {
            $esc = esc($customer_name);
            $conds[] = "LOWER(TRIM($name_col)) = LOWER('$esc')";
        }
        return $conds ? '(' . implode(' OR ', $conds) . ')' : '1=0';
    }
}

if (!function_exists('auragold_customer_deletable_ledger_items_sql')) {
    function auragold_customer_deletable_ledger_items_sql(string $customer_name, string $items_table, string $fk_col = 'voucher_id'): string
    {
        $customer_name = trim($customer_name);
        if ($customer_name === '') {
            return '1=0';
        }
        $esc = esc($customer_name);
        $status_sql = '';
        if (function_exists('auragold_tbl_has_column')) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli && auragold_tbl_has_column($conn, $items_table, 'status')) {
                $status_sql = ' AND items.status = 1';
            }
        }
        return "EXISTS (
            SELECT 1 FROM `$items_table` items
            WHERE items.`$fk_col` = main.id
            $status_sql
            AND (
                LOWER(TRIM(items.account_ledger)) = LOWER('$esc')
                OR items.account_ledger LIKE '%$esc%'
            )
        )";
    }
}

if (!function_exists('auragold_customer_deletable_resolve_party')) {
    /**
     * @return array{customer_id:int,customer_name:string}
     */
    function auragold_customer_deletable_resolve_party(mysqli $conn, int $customer_id, string $customer_name = ''): array
    {
        $customer_name = trim($customer_name);
        if ($customer_id > 0) {
            $row = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . (int) $customer_id . ' AND (status IS NULL OR status = 1) LIMIT 1');
            if ($row) {
                return [
                    'customer_id' => (int) ($row['id'] ?? 0),
                    'customer_name' => trim((string) ($row['name'] ?? $customer_name)),
                ];
            }
        }
        if ($customer_name !== '') {
            $esc = esc($customer_name);
            $row = getRecord("SELECT id, name FROM tbl_customers WHERE LOWER(TRIM(name)) = LOWER('$esc') AND (status IS NULL OR status = 1) LIMIT 1");
            if ($row) {
                return [
                    'customer_id' => (int) ($row['id'] ?? 0),
                    'customer_name' => trim((string) ($row['name'] ?? $customer_name)),
                ];
            }
        }
        return [
            'customer_id' => max(0, $customer_id),
            'customer_name' => $customer_name,
        ];
    }
}

if (!function_exists('auragold_customer_deletable_entry_row')) {
    function auragold_customer_deletable_entry_row(
        string $type,
        string $type_label,
        int $id,
        string $voucher_no,
        string $date,
        float $amount,
        float $balance = 0.0,
        array $extra = []
    ): array {
        return array_merge([
            'type' => $type,
            'type_label' => $type_label,
            'id' => $id,
            'voucher_no' => $voucher_no,
            'date' => $date,
            'amount' => $amount,
            'balance' => $balance,
            'deletable' => true,
            'block_reason' => '',
        ], $extra);
    }
}

if (!function_exists('auragold_customer_deletable_fetch_entries')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_customer_deletable_fetch_entries(mysqli $conn, int $customer_id, string $customer_name = ''): array
    {
        $party = auragold_customer_deletable_resolve_party($conn, $customer_id, $customer_name);
        $customer_id = (int) $party['customer_id'];
        $customer_name = trim((string) $party['customer_name']);
        if ($customer_id <= 0 && $customer_name === '') {
            return [];
        }

        $entries = [];
        $party_sale = auragold_customer_deletable_party_sql($customer_id, $customer_name, 'customer_id', 'customer_name');
        $party_purchase = auragold_customer_deletable_party_sql($customer_id, $customer_name, 'supplier_id', 'supplier_name');

        $add_rows = static function (array $rows, string $type, string $label, string $voucher_col, string $date_col, string $amount_col, string $balance_col = '') use (&$entries): void {
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $r) {
                $date = trim((string) ($r[$date_col] ?? ''));
                if ($date !== '' && strlen($date) > 10) {
                    $date = substr($date, 0, 10);
                }
                $entries[] = auragold_customer_deletable_entry_row(
                    $type,
                    $label,
                    (int) ($r['id'] ?? 0),
                    trim((string) ($r[$voucher_col] ?? '')),
                    $date,
                    (float) ($r[$amount_col] ?? 0),
                    $balance_col !== '' ? (float) ($r[$balance_col] ?? 0) : 0.0,
                    []
                );
            }
        };

        $table_exists = static function (mysqli $conn, string $table): bool {
            $chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
            $ok = $chk && mysqli_num_rows($chk) > 0;
            if ($chk) {
                mysqli_free_result($chk);
            }
            return $ok;
        };

        if ($table_exists($conn, 'tbl_sale_invoices')) {
            $rows = getList("SELECT id, invoice_no, invoice_date, grand_total, COALESCE(balance_amt,0) AS balance_amt
                FROM tbl_sale_invoices
                WHERE status != 'deleted' AND $party_sale
                ORDER BY invoice_date DESC, id DESC");
            $add_rows($rows, 'sale_invoice', 'Sale Invoice', 'invoice_no', 'invoice_date', 'grand_total', 'balance_amt');
        }

        if ($table_exists($conn, 'tbl_purchase_invoices')) {
            $rows = getList("SELECT id, invoice_no, invoice_date, grand_total, COALESCE(balance_amt,0) AS balance_amt
                FROM tbl_purchase_invoices
                WHERE status != 'deleted' AND $party_purchase
                ORDER BY invoice_date DESC, id DESC");
            $add_rows($rows, 'purchase_invoice', 'Purchase Invoice', 'invoice_no', 'invoice_date', 'grand_total', 'balance_amt');
        }

        if ($table_exists($conn, 'tbl_sale_returns')) {
            $rows = getList("SELECT id, return_no, return_date, grand_total
                FROM tbl_sale_returns
                WHERE status != 'deleted' AND $party_sale
                ORDER BY return_date DESC, id DESC");
            $add_rows($rows, 'sale_return', 'Sale Return', 'return_no', 'return_date', 'grand_total');
        }

        if ($table_exists($conn, 'tbl_purchase_returns')) {
            $rows = getList("SELECT id, return_no, return_date, grand_total, COALESCE(balance_amt,0) AS balance_amt
                FROM tbl_purchase_returns
                WHERE status != 'deleted' AND $party_purchase
                ORDER BY return_date DESC, id DESC");
            $add_rows($rows, 'purchase_return', 'Purchase Return', 'return_no', 'return_date', 'grand_total', 'balance_amt');
        }

        if ($table_exists($conn, 'tbl_sale_quotations')) {
            $rows = getList("SELECT id, quotation_no, quotation_date, COALESCE(grand_total, net_total, subtotal, 0) AS grand_total
                FROM tbl_sale_quotations
                WHERE status != 'deleted' AND $party_sale
                ORDER BY quotation_date DESC, id DESC");
            $add_rows($rows, 'sale_quotation', 'Sale Quotation', 'quotation_no', 'quotation_date', 'grand_total');
        }

        if ($table_exists($conn, 'tbl_purchase_quotations')) {
            $rows = getList("SELECT id, quotation_no, quotation_date, COALESCE(grand_total, net_total, subtotal, 0) AS grand_total
                FROM tbl_purchase_quotations
                WHERE status != 'deleted' AND $party_purchase
                ORDER BY quotation_date DESC, id DESC");
            $add_rows($rows, 'purchase_quotation', 'Purchase Quotation', 'quotation_no', 'quotation_date', 'grand_total');
        }

        if ($table_exists($conn, 'tbl_sale_orders')) {
            $rows = getList("SELECT id, order_no, order_date, COALESCE(NULLIF(grand_total,0), NULLIF(net_total,0), NULLIF(subtotal,0), 0) AS grand_total, COALESCE(balance_amt,0) AS balance_amt
                FROM tbl_sale_orders
                WHERE (status IS NULL OR status != 'deleted') AND $party_sale
                ORDER BY order_date DESC, id DESC");
            $add_rows($rows, 'sale_order', 'Sale Order', 'order_no', 'order_date', 'grand_total', 'balance_amt');
        }

        if ($table_exists($conn, 'tbl_jobwork_orders')) {
            $rows = getList("SELECT id, jobwork_no, order_date, COALESCE(grand_total,0) AS grand_total
                FROM tbl_jobwork_orders
                WHERE (status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled')) AND $party_sale
                ORDER BY order_date DESC, id DESC");
            $add_rows($rows, 'jobwork_order', 'Job Work Order', 'jobwork_no', 'order_date', 'grand_total');
        }

        if ($table_exists($conn, 'tbl_payment_vouchers')) {
            $rows = getList("SELECT id, voucher_no, voucher_date, COALESCE(total_amount,0) AS total_amount
                FROM tbl_payment_vouchers
                WHERE (status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled')) AND $party_sale
                ORDER BY voucher_date DESC, id DESC");
            $add_rows($rows, 'payment_voucher', 'Payment Voucher', 'voucher_no', 'voucher_date', 'total_amount');
        }

        if ($table_exists($conn, 'tbl_receipt_vouchers')) {
            $rows = getList("SELECT id, voucher_no, voucher_date, COALESCE(total_amount,0) AS total_amount
                FROM tbl_receipt_vouchers
                WHERE (status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled')) AND $party_sale
                ORDER BY voucher_date DESC, id DESC");
            $add_rows($rows, 'receipt_voucher', 'Receipt Voucher', 'voucher_no', 'voucher_date', 'total_amount');
        }

        if ($table_exists($conn, 'tbl_advance_payments')) {
            $rows = getList("SELECT id, voucher_no, voucher_date, COALESCE(total_amount,0) AS total_amount
                FROM tbl_advance_payments
                WHERE (status IS NULL OR LOWER(TRIM(COALESCE(status,''))) NOT IN ('deleted','cancelled')) AND $party_sale
                ORDER BY voucher_date DESC, id DESC");
            $add_rows($rows, 'advance_payment', 'Advance Payment', 'voucher_no', 'voucher_date', 'total_amount');
        }

        if ($table_exists($conn, 'tbl_journal_vouchers')) {
            $item_match = auragold_customer_deletable_ledger_items_sql($customer_name, 'tbl_journal_voucher_items');
            $rows = getList("SELECT main.id, main.voucher_no, main.voucher_date, COALESCE(main.debit_total, main.credit_total, 0) AS total_amount
                FROM tbl_journal_vouchers main
                WHERE (main.status IS NULL OR LOWER(TRIM(COALESCE(main.status,''))) NOT IN ('deleted','cancelled'))
                AND $item_match
                ORDER BY main.voucher_date DESC, main.id DESC");
            $add_rows($rows, 'journal_voucher', 'Journal Voucher', 'voucher_no', 'voucher_date', 'total_amount');
        }

        if ($table_exists($conn, 'tbl_contra_vouchers')) {
            $item_match = auragold_customer_deletable_ledger_items_sql($customer_name, 'tbl_contra_voucher_items');
            $rows = getList("SELECT main.id, main.voucher_no, main.voucher_date, COALESCE(main.debit_total, main.credit_total, 0) AS total_amount
                FROM tbl_contra_vouchers main
                WHERE (main.status IS NULL OR LOWER(TRIM(COALESCE(main.status,''))) NOT IN ('deleted','cancelled'))
                AND $item_match
                ORDER BY main.voucher_date DESC, main.id DESC");
            $add_rows($rows, 'contra_voucher', 'Contra Voucher', 'voucher_no', 'voucher_date', 'total_amount');
        }

        if ($table_exists($conn, 'tbl_sale_fixing_direct')) {
            $sfd_where = '1=1';
            $sf_status_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_fixing_direct LIKE 'status'");
            if ($sf_status_chk && mysqli_num_rows($sf_status_chk) > 0) {
                $sfd_where .= " AND (status IS NULL OR LOWER(TRIM(status)) <> 'deleted')";
            }
            if ($sf_status_chk) {
                mysqli_free_result($sf_status_chk);
            }
            $rows = getList("SELECT id, ref_no, COALESCE(fixing_date, created_at) AS fixing_date, COALESCE(total_amount,0) AS total_amount, against_of
                FROM tbl_sale_fixing_direct
                WHERE $sfd_where AND $party_sale
                ORDER BY fixing_date DESC, id DESC");
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $against_of = trim((string) ($r['against_of'] ?? ''));
                    $against_pi = '';
                    if (preg_match('/\b(PI-\d+)\b/i', $against_of, $m)) {
                        $against_pi = $m[1];
                    } elseif (preg_match('/\b(PRI\d+)\b/i', $against_of, $m)) {
                        $against_pi = $m[1];
                    }
                    $date = trim((string) ($r['fixing_date'] ?? ''));
                    if ($date !== '' && strlen($date) > 10) {
                        $date = substr($date, 0, 10);
                    }
                    $entries[] = auragold_customer_deletable_entry_row(
                        'sale_fixing_direct',
                        'Sale Fixing Direct',
                        (int) ($r['id'] ?? 0),
                        trim((string) ($r['ref_no'] ?? ('SFD-' . (int) ($r['id'] ?? 0)))),
                        $date,
                        (float) ($r['total_amount'] ?? 0),
                        0.0,
                        ['against_pi' => $against_pi]
                    );
                }
            }
        }

        if ($table_exists($conn, 'tbl_purchase_fixing_direct')) {
            $pfd_where = '1=1';
            $pf_status_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'status'");
            if ($pf_status_chk && mysqli_num_rows($pf_status_chk) > 0) {
                $pfd_where .= " AND (status IS NULL OR LOWER(TRIM(status)) <> 'deleted')";
            }
            if ($pf_status_chk) {
                mysqli_free_result($pf_status_chk);
            }
            $pfd_party = auragold_customer_deletable_party_sql($customer_id, $customer_name, 'customer_id', 'customer_name');
            $pfd_party_alt = auragold_customer_deletable_party_sql($customer_id, $customer_name, 'supplier_id', 'supplier_name');
            $rows = getList("SELECT id, COALESCE(invoice_no, ref_no, CONCAT('PFD-', id)) AS voucher_ref,
                COALESCE(fixing_date, invoice_date, created_at) AS fixing_date,
                COALESCE(grand_total, total_amount, net_total, subtotal, 0) AS total_amount,
                against_of, sale_invoice_no
                FROM tbl_purchase_fixing_direct
                WHERE $pfd_where AND ($pfd_party OR $pfd_party_alt)
                ORDER BY fixing_date DESC, id DESC");
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $against_si = trim((string) ($r['sale_invoice_no'] ?? ''));
                    if ($against_si === '') {
                        $against_of = trim((string) ($r['against_of'] ?? ''));
                        if ($against_of !== '' && preg_match('/Fixing of\s+(\S+)/i', $against_of, $m)) {
                            $against_si = trim($m[1]);
                        }
                    }
                    $date = trim((string) ($r['fixing_date'] ?? ''));
                    if ($date !== '' && strlen($date) > 10) {
                        $date = substr($date, 0, 10);
                    }
                    $entries[] = auragold_customer_deletable_entry_row(
                        'purchase_fixing_direct',
                        'Purchase Fixing Direct',
                        (int) ($r['id'] ?? 0),
                        trim((string) ($r['voucher_ref'] ?? '')),
                        $date,
                        (float) ($r['total_amount'] ?? 0),
                        0.0,
                        ['against_si' => $against_si]
                    );
                }
            }
        }

        if ($table_exists($conn, 'tbl_old_jewelry_scrap_invoices')) {
            $rows = getList("SELECT id, invoice_no, invoice_date, COALESCE(grand_total,0) AS grand_total, COALESCE(balance_amt, grand_total, 0) AS balance_amt, against_of, ref_no
                FROM tbl_old_jewelry_scrap_invoices
                WHERE (status IS NULL OR status = '' OR LOWER(TRIM(status)) NOT IN ('cancelled','deleted'))
                AND $party_sale
                ORDER BY invoice_date DESC, id DESC");
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $ref_raw = trim((string) ($r['ref_no'] ?? ''));
                    $linked_from_purchase = (bool) preg_match('/^PI:\d+$/i', $ref_raw);
                    $entries[] = auragold_customer_deletable_entry_row(
                        'old_jewelry_scrap_invoice',
                        'Old Jewelry Scrap Invoice',
                        (int) ($r['id'] ?? 0),
                        trim((string) ($r['invoice_no'] ?? '')),
                        trim((string) ($r['invoice_date'] ?? '')),
                        (float) ($r['grand_total'] ?? 0),
                        (float) ($r['balance_amt'] ?? 0),
                        [
                            'linked_from_purchase' => $linked_from_purchase,
                            'against_pi' => trim((string) ($r['against_of'] ?? '')),
                        ]
                    );
                }
            }
        }

        auragold_customer_deletable_apply_blocks($conn, $entries);

        usort($entries, static function ($a, $b) {
            $da = trim((string) ($a['date'] ?? ''));
            $db = trim((string) ($b['date'] ?? ''));
            if ($da !== $db) {
                return strcmp($db, $da);
            }
            return ((int) ($b['id'] ?? 0)) - ((int) ($a['id'] ?? 0));
        });

        return $entries;
    }
}

if (!function_exists('auragold_customer_deletable_apply_blocks')) {
    /**
     * @param array<int, array<string, mixed>> $entries
     */
    function auragold_customer_deletable_apply_blocks(mysqli $conn, array &$entries): void
    {
        $pi_invoice_nos_with_sfd = function_exists('auragold_pi_invoice_nos_with_active_sale_fixing')
            ? auragold_pi_invoice_nos_with_active_sale_fixing()
            : [];
        $si_invoice_nos_with_pfd = function_exists('auragold_si_invoice_nos_with_active_purchase_fixing')
            ? auragold_si_invoice_nos_with_active_purchase_fixing()
            : [];
        $so_ids_with_jwo = function_exists('auragold_sale_order_ids_with_jobwork_orders')
            ? auragold_sale_order_ids_with_jobwork_orders($conn)
            : [];
        $jwo_ids_with_queue = function_exists('auragold_jobwork_order_ids_with_queue_activity')
            ? auragold_jobwork_order_ids_with_queue_activity($conn)
            : [];

        foreach ($entries as $i => $t) {
            $type = (string) ($t['type'] ?? '');
            $voucher_key_upper = strtoupper(trim((string) ($t['voucher_no'] ?? '')));
            $blocked = false;
            $reason = '';

            if ($type === 'purchase_invoice' && $voucher_key_upper !== '' && !empty($pi_invoice_nos_with_sfd[$voucher_key_upper])) {
                $blocked = true;
                $reason = 'Delete the sale fixing first';
            } elseif ($type === 'sale_invoice' && $voucher_key_upper !== '' && !empty($si_invoice_nos_with_pfd[$voucher_key_upper])) {
                $blocked = true;
                $reason = 'Delete the purchase fixing first';
            } elseif ($type === 'sale_order' && !empty($so_ids_with_jwo[(int) ($t['id'] ?? 0)])) {
                $blocked = true;
                $reason = 'Delete Job Work Order first, then this Sale Order';
            } elseif ($type === 'jobwork_order') {
                $jid = (int) ($t['id'] ?? 0);
                if (!empty($jwo_ids_with_queue[$jid])
                    || (function_exists('auragold_jobwork_order_has_invoice') && auragold_jobwork_order_has_invoice($conn, $jid))
                ) {
                    $blocked = true;
                    $reason = 'Delete Jobwork Queue records first, then delete this Job Work Order';
                }
            } elseif ($type === 'old_jewelry_scrap_invoice' && !empty($t['linked_from_purchase'])) {
                $blocked = true;
                $reason = 'Remove scrap payment on Purchase Invoice or delete the PI';
            }

            $entries[$i]['deletable'] = !$blocked;
            $entries[$i]['block_reason'] = $reason;
        }
    }
}

if (!function_exists('auragold_customer_deletable_delete_endpoint')) {
    function auragold_customer_deletable_delete_endpoint(string $type): string
    {
        if ($type === 'advance_payment') {
            return 'ajax/delete-advance-payment.php';
        }
        return 'ajax/delete-transaction.php';
    }
}
