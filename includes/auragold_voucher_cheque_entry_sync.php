<?php

/**
 * Auto-create cheque / PDC entries and account-ledger rows when vouchers are saved with cheque payments.
 */

if (!function_exists('auragold_voucher_cheque_sync_marker')) {
    function auragold_voucher_cheque_sync_marker(): string
    {
        return 'auragold_auto_cheque_sync';
    }
}

if (!function_exists('auragold_pdc_ledger_sync_marker')) {
    function auragold_pdc_ledger_sync_marker(): string
    {
        return 'auragold_auto_pdc_ledger_sync';
    }
}

if (!function_exists('auragold_payment_is_cheque_type')) {
    function auragold_payment_is_cheque_type($payment_type): bool
    {
        $t = strtolower(trim((string) $payment_type));
        if ($t === '') {
            return false;
        }
        return $t === 'cheque' || $t === 'check' || strpos($t, 'cheque') !== false;
    }
}

if (!function_exists('auragold_payment_should_post_deposit_ledger')) {
    /**
     * Cheque amounts post to PDC Receivable/Payable until cleared in Cheque Entry — not the bank/cash ledger.
     *
     * @param array<string, mixed>|string $payment Payment row or payment_type string
     */
    function auragold_payment_should_post_deposit_ledger($payment): bool
    {
        if (is_array($payment)) {
            return !auragold_payment_is_cheque_type($payment['payment_type'] ?? '');
        }

        return !auragold_payment_is_cheque_type($payment);
    }
}

if (!function_exists('auragold_cheque_payment_against_label')) {
    function auragold_cheque_payment_against_label(float $amount, string $direction): string
    {
        $pdc_name = auragold_pdc_system_ledger_name($direction);

        return auragold_pdc_against_ledger_label($pdc_name, $amount, $direction);
    }
}

if (!function_exists('auragold_voucher_cheque_pdc_direction')) {
    /**
     * @return 'receivable'|'payable'
     */
    function auragold_voucher_cheque_pdc_direction(string $voucher_type, ?string $explicit = null): string
    {
        if ($explicit === 'receivable' || $explicit === 'payable') {
            return $explicit;
        }
        $vt = strtolower(trim($voucher_type));
        $payable_hints = [
            'purchase',
            'payment voucher',
            'debit note',
            'expense',
            'material issue',
            'consignment out',
            'jobwork order',
        ];
        foreach ($payable_hints as $hint) {
            if (strpos($vt, $hint) !== false) {
                return 'payable';
            }
        }

        return 'receivable';
    }
}

if (!function_exists('auragold_cheque_payment_line_amount')) {
    function auragold_cheque_payment_line_amount(array $payment): float
    {
        $current = (float) ($payment['current_order_amount'] ?? 0);
        if ($current > 0) {
            return $current;
        }
        $amount = (float) ($payment['amount'] ?? 0);
        $prev = (float) ($payment['previous_balance_amount'] ?? 0);
        if ($amount > 0 && $prev > 0 && $amount > $prev) {
            return $amount - $prev;
        }

        return $amount;
    }
}

if (!function_exists('auragold_pdc_system_ledger_name')) {
    function auragold_pdc_system_ledger_name(string $direction): string
    {
        return $direction === 'payable' ? 'PDC Payable' : 'PDC Receivable';
    }
}

if (!function_exists('auragold_pdc_against_party_label')) {
    function auragold_pdc_against_party_label(string $party_name, float $amount, string $direction): string
    {
        $amt = number_format(abs($amount), 2, '.', '');
        $suffix = $direction === 'payable' ? 'Dr' : 'Cr';

        return trim($party_name) . '(' . $amt . $suffix . ')';
    }
}

if (!function_exists('auragold_pdc_against_ledger_label')) {
    function auragold_pdc_against_ledger_label(string $pdc_name, float $amount, string $direction): string
    {
        $amt = number_format(abs($amount), 2, '.', '');
        $suffix = $direction === 'payable' ? 'Cr' : 'Dr';

        return trim($pdc_name) . '(' . $amt . $suffix . ')';
    }
}

if (!function_exists('auragold_ensure_pdc_system_ledger')) {
    function auragold_ensure_pdc_system_ledger($conn, string $ledger_name, int $branch_id = 0): void
    {
        if (!$conn instanceof mysqli || trim($ledger_name) === '') {
            return;
        }
        if (!function_exists('auragold_ensure_customer_ledger_branch_column')) {
            require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        }
        auragold_ensure_customer_ledger_branch_column($conn);

        $name_esc = mysqli_real_escape_string($conn, trim($ledger_name));
        $scope = '';
        if ($branch_id > 0 && function_exists('auragold_customer_ledger_branch_scope_sql')) {
            $scope = auragold_customer_ledger_branch_scope_sql($conn, $branch_id);
        }
        $exists = getRecord(
            "SELECT id FROM tbl_customer_ledger WHERE customer_id = 0 AND customer_name = '$name_esc' AND status = 1 $scope LIMIT 1"
        );
        if ($exists) {
            return;
        }

        $today = date('Y-m-d');
        $branch_col = '';
        $branch_val = '';
        if ($branch_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id')) {
            $branch_col = ', branch_id';
            $branch_val = ', ' . (int) $branch_id;
        }

        @mysqli_query(
            $conn,
            "INSERT INTO tbl_customer_ledger (
                customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount, balance_amount, balance_gold, balance_silver,
                description, status, created_at $branch_col
            ) VALUES (
                0, '$name_esc', 'opening', 0, 'OPENING', '$today',
                0, 0, 0, 0, 0,
                'System ledger opening', 1, NOW() $branch_val
            )"
        );
    }
}

if (!function_exists('auragold_pdc_system_ledger_balance')) {
    function auragold_pdc_system_ledger_balance($conn, string $ledger_name, int $branch_id = 0): float
    {
        if (!$conn instanceof mysqli || trim($ledger_name) === '') {
            return 0.0;
        }
        $name_esc = mysqli_real_escape_string($conn, trim($ledger_name));
        $scope = '';
        if ($branch_id > 0 && function_exists('auragold_customer_ledger_branch_scope_sql')) {
            $scope = auragold_customer_ledger_branch_scope_sql($conn, $branch_id);
        }
        $row = getRecord(
            "SELECT balance_amount FROM tbl_customer_ledger
             WHERE customer_id = 0 AND customer_name = '$name_esc' AND status = 1 $scope
             ORDER BY transaction_date DESC, id DESC LIMIT 1"
        );

        return $row ? (float) ($row['balance_amount'] ?? 0) : 0.0;
    }
}

if (!function_exists('auragold_voucher_cheque_remove_auto_entries')) {
    function auragold_voucher_cheque_remove_auto_entries($conn, string $voucher_no, string $voucher_type): void
    {
        if (!$conn instanceof mysqli || trim($voucher_no) === '' || trim($voucher_type) === '') {
            return;
        }
        if (!function_exists('auragold_ensure_tbl_cheque_entry')) {
            require_once __DIR__ . '/auragold_cheque_entry_schema.php';
        }
        auragold_ensure_tbl_cheque_entry($conn);

        $marker = mysqli_real_escape_string($conn, auragold_voucher_cheque_sync_marker());
        $vno = mysqli_real_escape_string($conn, trim($voucher_no));
        $vtype = mysqli_real_escape_string($conn, trim($voucher_type));

        @mysqli_query(
            $conn,
            'UPDATE `tbl_cheque_entry` SET record_status = 0, updated_at = NOW()'
            . " WHERE record_status = 1 AND against_voucher_no = '$vno'"
            . " AND against_voucher_type = '$vtype'"
            . " AND comment LIKE '%$marker%'"
        );
    }
}

if (!function_exists('auragold_voucher_cheque_remove_auto_ledger_entries')) {
    function auragold_voucher_cheque_remove_auto_ledger_entries($conn, int $transaction_id, string $voucher_no = ''): void
    {
        if (!$conn instanceof mysqli || $transaction_id <= 0) {
            return;
        }
        $marker = mysqli_real_escape_string($conn, auragold_pdc_ledger_sync_marker());
        $tid = (int) $transaction_id;

        @mysqli_query(
            $conn,
            "DELETE FROM tbl_customer_ledger
             WHERE status = 1 AND transaction_id = $tid
             AND description LIKE '%$marker%'"
        );
    }
}

if (!function_exists('auragold_voucher_cheque_remove_bank_ledger_lines')) {
    function auragold_voucher_cheque_remove_bank_ledger_lines(
        $conn,
        int $transaction_id,
        string $deposit_into,
        ?float $amount = null,
        ?string $transaction_no = null
    ): void {
        if (!$conn instanceof mysqli || $transaction_id <= 0 || trim($deposit_into) === '') {
            return;
        }
        $dep = mysqli_real_escape_string($conn, trim($deposit_into));
        $tid = (int) $transaction_id;

        $amt_clause = '';
        if ($amount !== null && $amount > 0) {
            $amt = number_format($amount, 2, '.', '');
            $amt_clause = " AND (ABS(debit_amount - $amt) < 0.01 OR ABS(credit_amount - $amt) < 0.01)";
        }

        if ($transaction_no !== null && trim($transaction_no) !== '') {
            $vno = mysqli_real_escape_string($conn, trim($transaction_no));
            @mysqli_query(
                $conn,
                "DELETE FROM tbl_customer_ledger
                 WHERE status = 1 AND transaction_id = $tid AND transaction_no = '$vno'
                 AND customer_id = 0 AND customer_name = '$dep'
                 $amt_clause"
            );
        }

        @mysqli_query(
            $conn,
            "DELETE FROM tbl_customer_ledger
             WHERE status = 1 AND transaction_id = $tid
             AND customer_id = 0 AND customer_name = '$dep'
             $amt_clause"
        );
    }
}

if (!function_exists('auragold_voucher_cheque_remove_bank_ledger_line')) {
    function auragold_voucher_cheque_remove_bank_ledger_line(
        $conn,
        int $transaction_id,
        string $voucher_no,
        string $deposit_into,
        float $amount
    ): void {
        auragold_voucher_cheque_remove_bank_ledger_lines(
            $conn,
            $transaction_id,
            $deposit_into,
            $amount > 0 ? $amount : null,
            $voucher_no !== '' ? $voucher_no : null
        );
    }
}

if (!function_exists('auragold_voucher_cheque_strip_deposit_ledger_lines')) {
    /**
     * @param array<int, array<string, mixed>> $payments
     */
    function auragold_voucher_cheque_strip_deposit_ledger_lines(
        $conn,
        int $transaction_id,
        string $ledger_transaction_no,
        array $payments
    ): void {
        if (!$conn instanceof mysqli || $transaction_id <= 0) {
            return;
        }
        foreach ($payments as $payment) {
            if (!is_array($payment) || !auragold_payment_is_cheque_type($payment['payment_type'] ?? '')) {
                continue;
            }
            $deposit_into = trim((string) ($payment['deposit_into'] ?? ''));
            if ($deposit_into === '') {
                continue;
            }
            auragold_voucher_cheque_remove_bank_ledger_lines(
                $conn,
                $transaction_id,
                $deposit_into,
                null,
                $ledger_transaction_no !== '' ? $ledger_transaction_no : null
            );
        }
    }
}

if (!function_exists('auragold_voucher_cheque_update_party_against_ledger')) {
    function auragold_voucher_cheque_update_party_against_ledger(
        $conn,
        int $transaction_id,
        string $voucher_no,
        string $party_name,
        float $amount,
        string $direction,
        string $pdc_ledger_name
    ): void {
        if (!$conn instanceof mysqli || $transaction_id <= 0 || trim($voucher_no) === '' || trim($party_name) === '' || $amount <= 0) {
            return;
        }
        $vno = mysqli_real_escape_string($conn, trim($voucher_no));
        $party = mysqli_real_escape_string($conn, trim($party_name));
        $against = mysqli_real_escape_string($conn, auragold_pdc_against_ledger_label($pdc_ledger_name, $amount, $direction));
        $tid = (int) $transaction_id;
        $amt = number_format($amount, 2, '.', '');

        if ($direction === 'payable') {
            @mysqli_query(
                $conn,
                "UPDATE tbl_customer_ledger SET against_ledger = '$against'
                 WHERE status = 1 AND transaction_id = $tid AND transaction_no = '$vno'
                 AND customer_name = '$party' AND ABS(debit_amount - $amt) < 0.01"
            );
        } else {
            @mysqli_query(
                $conn,
                "UPDATE tbl_customer_ledger SET against_ledger = '$against'
                 WHERE status = 1 AND transaction_id = $tid AND transaction_no = '$vno'
                 AND customer_name = '$party' AND ABS(credit_amount - $amt) < 0.01"
            );
        }
    }
}

if (!function_exists('auragold_voucher_cheque_insert_pdc_ledger_entry')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_voucher_cheque_insert_pdc_ledger_entry(
        $conn,
        array $options,
        string $direction,
        string $pdc_ledger_name,
        string $party_name,
        float $amount,
        string $pdc_no
    ): array {
        if (!$conn instanceof mysqli || $amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid PDC ledger amount.'];
        }

        $transaction_id = (int) ($options['transaction_id'] ?? 0);
        $voucher_no = trim((string) ($options['voucher_no'] ?? ''));
        $voucher_date = trim((string) ($options['voucher_date'] ?? date('Y-m-d')));
        $branch_id = (int) ($options['branch_id'] ?? 0);
        $user_id = isset($options['user_id']) ? (int) $options['user_id'] : 0;

        if ($transaction_id <= 0 || $voucher_no === '') {
            return ['ok' => false, 'message' => 'Missing voucher reference for PDC ledger.'];
        }

        if (!function_exists('auragold_ensure_customer_ledger_branch_column')) {
            require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        }
        auragold_ensure_customer_ledger_branch_column($conn);
        auragold_ensure_pdc_system_ledger($conn, $pdc_ledger_name, $branch_id);

        $prev_balance = auragold_pdc_system_ledger_balance($conn, $pdc_ledger_name, $branch_id);
        $debit = $direction === 'receivable' ? $amount : 0.0;
        $credit = $direction === 'payable' ? $amount : 0.0;
        $new_balance = $prev_balance + $debit - $credit;

        $marker = auragold_pdc_ledger_sync_marker();
        $txn_type = $direction === 'payable' ? 'pdc_payable' : 'pdc_receivable';
        $against_party = auragold_pdc_against_party_label($party_name, $amount, $direction);
        $desc = $marker . '|' . trim((string) ($options['voucher_type'] ?? '')) . '|' . $voucher_no;

        $esc = static function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, (string) $v);
        };

        $branch_col = '';
        $branch_val = '';
        if ($branch_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id')) {
            $branch_col = ', branch_id';
            $branch_val = ', ' . (int) $branch_id;
        }

        $has_against = false;
        $ac = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
        if ($ac && mysqli_num_rows($ac) > 0) {
            $has_against = true;
        }
        if ($ac) {
            mysqli_free_result($ac);
        }

        $against_cols = $has_against ? ', against_ledger, against_invoice_no' : '';
        $against_vals = $has_against
            ? ", '" . $esc($against_party) . "', '" . $esc($voucher_no) . "'"
            : '';

        $sql = "INSERT INTO tbl_customer_ledger (
            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount,
            balance_amount, balance_gold, balance_silver,
            description, status, created_by, created_at
            $against_cols $branch_col
        ) VALUES (
            0,
            '" . $esc($pdc_ledger_name) . "',
            '" . $esc($txn_type) . "',
            $transaction_id,
            '" . $esc($pdc_no !== '' ? $pdc_no : $voucher_no) . "',
            '" . $esc($voucher_date) . "',
            " . number_format($debit, 2, '.', '') . ",
            " . number_format($credit, 2, '.', '') . ",
            " . number_format($new_balance, 2, '.', '') . ",
            0, 0,
            '" . $esc($desc) . "',
            1,
            " . ($user_id > 0 ? $user_id : 'NULL') . ",
            NOW()
            $against_vals $branch_val
        )";

        if (!mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => mysqli_error($conn)];
        }

        return ['ok' => true, 'message' => 'PDC ledger posted.'];
    }
}

if (!function_exists('auragold_sync_voucher_cheque_entries')) {
    /**
     * Create cheque-entry rows and PDC account-ledger rows for cheque payments on a saved voucher.
     *
     * @param array<string, mixed> $options voucher_no, voucher_type, voucher_date, account_ledger, payments,
     *                                      transaction_id, branch_id (optional), customer_id (optional),
     *                                      user_id (optional), pdc_direction (optional receivable|payable)
     * @return array{ok:bool,created:int,errors:array<int,string>}
     */
    function auragold_sync_voucher_cheque_entries($conn, array $options): array
    {
        $out = ['ok' => true, 'created' => 0, 'errors' => []];
        if (!$conn instanceof mysqli) {
            $out['ok'] = false;
            $out['errors'][] = 'Database unavailable.';
            return $out;
        }

        if (!function_exists('auragold_save_cheque_entry')) {
            require_once __DIR__ . '/auragold_cheque_entry_schema.php';
        }

        $voucher_no = trim((string) ($options['voucher_no'] ?? ''));
        $voucher_type = trim((string) ($options['voucher_type'] ?? ''));
        $voucher_date = trim((string) ($options['voucher_date'] ?? ''));
        $account_ledger = trim((string) ($options['account_ledger'] ?? ''));
        $transaction_id = (int) ($options['transaction_id'] ?? 0);
        $ledger_transaction_no = trim((string) ($options['ledger_transaction_no'] ?? $voucher_no));
        if ($ledger_transaction_no === '') {
            $ledger_transaction_no = $voucher_no;
        }
        $options['ledger_transaction_no'] = $ledger_transaction_no;
        $payments = $options['payments'] ?? [];
        if (!is_array($payments)) {
            $payments = [];
        }

        if ($voucher_no === '' || $voucher_type === '') {
            return $out;
        }

        $branch_id = 0;
        if (isset($options['branch_id'])) {
            $branch_id = (int) $options['branch_id'];
        } elseif (function_exists('auragold_effective_branch_id')) {
            $branch_id = (int) auragold_effective_branch_id();
        } elseif (function_exists('auragold_settings_branch_id')) {
            $branch_id = (int) auragold_settings_branch_id();
        }
        if (function_exists('auragold_cheque_entry_resolve_branch_id')) {
            $branch_id = auragold_cheque_entry_resolve_branch_id($branch_id > 0 ? $branch_id : null);
        }
        $options['branch_id'] = $branch_id;

        auragold_voucher_cheque_remove_auto_entries($conn, $voucher_no, $voucher_type);
        if ($transaction_id > 0) {
            auragold_voucher_cheque_remove_auto_ledger_entries($conn, $transaction_id);
            auragold_voucher_cheque_strip_deposit_ledger_lines(
                $conn,
                $transaction_id,
                $ledger_transaction_no,
                $payments
            );
        }

        $direction = auragold_voucher_cheque_pdc_direction(
            $voucher_type,
            isset($options['pdc_direction']) ? (string) $options['pdc_direction'] : null
        );
        $pdc_voucher_type = $direction === 'payable' ? 'PDC Payable' : 'PDC Receivable';
        $pdc_ledger_name = auragold_pdc_system_ledger_name($direction);
        $marker = auragold_voucher_cheque_sync_marker();

        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $payment_type = (string) ($payment['payment_type'] ?? '');
            if (!auragold_payment_is_cheque_type($payment_type)) {
                continue;
            }

            $amount = auragold_cheque_payment_line_amount($payment);
            if ($amount <= 0) {
                continue;
            }

            $ledger = $account_ledger;
            if ($ledger === '') {
                $ledger = trim((string) ($payment['account_ledger'] ?? $payment['party_name'] ?? ''));
            }
            if ($ledger === '') {
                $out['errors'][] = 'Cheque payment skipped: account ledger is empty.';
                continue;
            }

            $deposit_into = trim((string) ($payment['deposit_into'] ?? ''));
            $cheque_date = $payment['cheque_date'] ?? $voucher_date;
            $comment = $marker . '|' . $voucher_type . '|' . $voucher_no;

            $save = auragold_save_cheque_entry($conn, $branch_id, [
                'account_ledger' => $ledger,
                'bank_name' => $deposit_into,
                'cheque_no' => trim((string) ($payment['transaction_no'] ?? '')),
                'cheque_date' => $cheque_date,
                'amount' => $amount,
                'status' => '',
                'against_voucher_no' => $voucher_no,
                'against_voucher_type' => $voucher_type,
                'reference_voucher_type' => $voucher_type,
                'ref_invoice_no' => $voucher_no,
                'invoice_date' => $voucher_date,
                'comment' => $comment,
                'pdc_voucher_type' => $pdc_voucher_type,
            ]);

            if (empty($save['ok'])) {
                $out['ok'] = false;
                $out['errors'][] = (string) ($save['message'] ?? 'Cheque entry save failed.');
                continue;
            }

            $pdc_no = '';
            if (!empty($save['entry']['pdc_no'])) {
                $pdc_no = (string) $save['entry']['pdc_no'];
            } elseif (!empty($save['id'])) {
                $row = function_exists('auragold_get_cheque_entry_by_id')
                    ? auragold_get_cheque_entry_by_id($conn, (int) $save['id'], $branch_id)
                    : null;
                if (is_array($row) && !empty($row['pdc_no'])) {
                    $pdc_no = (string) $row['pdc_no'];
                }
            }

            if ($transaction_id > 0) {
                $bank_amount = (float) ($payment['amount'] ?? $amount);
                if ($bank_amount <= 0) {
                    $bank_amount = $amount;
                }
                if ($deposit_into !== '') {
                    auragold_voucher_cheque_remove_bank_ledger_line(
                        $conn,
                        $transaction_id,
                        $ledger_transaction_no,
                        $deposit_into,
                        $bank_amount
                    );
                }
                auragold_voucher_cheque_update_party_against_ledger(
                    $conn,
                    $transaction_id,
                    $ledger_transaction_no,
                    $ledger,
                    $amount,
                    $direction,
                    $pdc_ledger_name
                );
                $ledger_post = auragold_voucher_cheque_insert_pdc_ledger_entry(
                    $conn,
                    $options,
                    $direction,
                    $pdc_ledger_name,
                    $ledger,
                    $amount,
                    $pdc_no
                );
                if (empty($ledger_post['ok'])) {
                    $out['ok'] = false;
                    $out['errors'][] = (string) ($ledger_post['message'] ?? 'PDC ledger entry failed.');
                    continue;
                }
            }

            $out['created']++;
        }

        return $out;
    }
}
