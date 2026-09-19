<?php

/**
 * Post account-ledger entries when a cheque entry status is set to Cleared (PDC Clearance).
 */

if (!function_exists('auragold_pdc_clearance_sync_marker')) {
    function auragold_pdc_clearance_sync_marker(): string
    {
        return 'auragold_auto_pdc_clearance_sync';
    }
}

if (!function_exists('auragold_cheque_entry_clearance_direction')) {
    function auragold_cheque_entry_clearance_direction(string $pdc_voucher_type): string
    {
        $t = strtolower(trim($pdc_voucher_type));
        if (strpos($t, 'payable') !== false) {
            return 'payable';
        }

        return 'receivable';
    }
}

if (!function_exists('auragold_pdc_clearance_against_pdc_ledger_label')) {
    function auragold_pdc_clearance_against_pdc_ledger_label(string $pdc_ledger_name, float $amount, string $direction): string
    {
        $amt = number_format(abs($amount), 6, '.', '');
        $suffix = $direction === 'payable' ? 'Dr' : 'Cr';

        return trim($pdc_ledger_name) . '(' . $amt . $suffix . ')';
    }
}

if (!function_exists('auragold_pdc_clearance_against_bank_label')) {
    function auragold_pdc_clearance_against_bank_label(string $bank_name, float $amount, string $direction): string
    {
        $amt = number_format(abs($amount), 6, '.', '');
        $suffix = $direction === 'payable' ? 'Cr' : 'Dr';

        return trim($bank_name) . '(' . $amt . $suffix . ')';
    }
}

if (!function_exists('auragold_pdc_clearance_against_party_label')) {
    function auragold_pdc_clearance_against_party_label(string $party_name, float $amount, string $direction): string
    {
        $amt = number_format(abs($amount), 6, '.', '');
        $suffix = $direction === 'payable' ? 'Dr' : 'Cr';

        return trim($party_name) . '(' . $amt . $suffix . ')';
    }
}

if (!function_exists('auragold_allocate_next_pdc_clearance_no')) {
    function auragold_allocate_next_pdc_clearance_no($conn): string
    {
        if (!function_exists('getNextPdcClearanceNo')) {
            return 'PC-1';
        }
        $pc = getNextPdcClearanceNo($conn);
        if (function_exists('getPdcClearanceBillSeriesConfig') && function_exists('bumpPdcClearanceNo')) {
            $cfg = getPdcClearanceBillSeriesConfig($conn);
            $guard = 0;
            while ($guard < 5000) {
                $esc = mysqli_real_escape_string($conn, $pc);
                $exists = getRecord(
                    "SELECT id FROM tbl_customer_ledger
                     WHERE status = 1 AND transaction_type = 'pdc_clearance'
                     AND transaction_no = '$esc' LIMIT 1"
                );
                if (!$exists) {
                    break;
                }
                $pc = bumpPdcClearanceNo($conn, $pc, $cfg);
                $guard++;
            }
        }

        return $pc;
    }
}

if (!function_exists('auragold_remove_cheque_entry_clearance_ledger')) {
    function auragold_remove_cheque_entry_clearance_ledger($conn, int $cheque_entry_id): void
    {
        if (!$conn instanceof mysqli || $cheque_entry_id <= 0) {
            return;
        }
        $marker = mysqli_real_escape_string($conn, auragold_pdc_clearance_sync_marker());
        $tid = (int) $cheque_entry_id;

        @mysqli_query(
            $conn,
            "DELETE FROM tbl_customer_ledger
             WHERE status = 1 AND transaction_id = $tid
             AND transaction_type = 'pdc_clearance'
             AND description LIKE '%$marker%'"
        );
    }
}

if (!function_exists('auragold_insert_pdc_clearance_ledger_row')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_insert_pdc_clearance_ledger_row(
        $conn,
        int $cheque_entry_id,
        string $ledger_name,
        string $clearance_no,
        string $clearance_date,
        float $debit,
        float $credit,
        string $against_ledger,
        string $against_invoice_no,
        int $branch_id,
        int $user_id,
        string $desc_extra = ''
    ): array {
        if (!$conn instanceof mysqli || trim($ledger_name) === '' || $cheque_entry_id <= 0) {
            return ['ok' => false, 'message' => 'Invalid clearance ledger row.'];
        }

        if (!function_exists('auragold_ensure_customer_ledger_branch_column')) {
            require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        }
        auragold_ensure_customer_ledger_branch_column($conn);

        $pdc_ledgers = ['PDC Receivable', 'PDC Payable'];
        if (function_exists('auragold_ensure_pdc_system_ledger') && in_array(trim($ledger_name), $pdc_ledgers, true)) {
            require_once __DIR__ . '/auragold_voucher_cheque_entry_sync.php';
            auragold_ensure_pdc_system_ledger($conn, $ledger_name, $branch_id);
        }

        $scope = '';
        if ($branch_id > 0 && function_exists('auragold_customer_ledger_branch_scope_sql')) {
            $scope = auragold_customer_ledger_branch_scope_sql($conn, $branch_id);
        }
        $name_esc = mysqli_real_escape_string($conn, trim($ledger_name));
        $bal_row = getRecord(
            "SELECT balance_amount FROM tbl_customer_ledger
             WHERE customer_name = '$name_esc' AND status = 1 $scope
             ORDER BY transaction_date DESC, id DESC LIMIT 1"
        );
        $prev_balance = $bal_row ? (float) ($bal_row['balance_amount'] ?? 0) : 0.0;
        $new_balance = $prev_balance + $debit - $credit;

        $marker = auragold_pdc_clearance_sync_marker();
        // Keep marker first so remove-by-LIKE stays reliable; append human text for Account Ledger.
        $desc = $marker . '|cheque_entry=' . $cheque_entry_id;
        if ($desc_extra !== '') {
            $desc .= '|' . $desc_extra;
        }

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
            ? ", '" . $esc($against_ledger) . "', '" . $esc($against_invoice_no) . "'"
            : '';

        $sql = "INSERT INTO tbl_customer_ledger (
            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount,
            balance_amount, balance_gold, balance_silver,
            description, status, created_by, created_at
            $against_cols $branch_col
        ) VALUES (
            0,
            '" . $esc($ledger_name) . "',
            'pdc_clearance',
            " . (int) $cheque_entry_id . ",
            '" . $esc($clearance_no) . "',
            '" . $esc($clearance_date) . "',
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

        return ['ok' => true, 'message' => 'Clearance ledger posted.'];
    }
}

if (!function_exists('auragold_sync_cheque_entry_clearance')) {
    /**
     * When status = Cleared: debit/credit PDC ledger and post the matching bank ledger row
     * so Account Ledger (e.g. BOI) shows the clearance.
     *
     * @return array{ok:bool,message:string,clearance_no?:string}
     */
    function auragold_sync_cheque_entry_clearance($conn, int $branch_id, int $cheque_entry_id, int $user_id = 0): array
    {
        if (!$conn instanceof mysqli || $cheque_entry_id <= 0) {
            return ['ok' => false, 'message' => 'Invalid cheque entry.'];
        }

        if (!function_exists('auragold_get_cheque_entry_by_id')) {
            require_once __DIR__ . '/auragold_cheque_entry_schema.php';
        }
        if (!function_exists('auragold_pdc_system_ledger_name')) {
            require_once __DIR__ . '/auragold_voucher_cheque_entry_sync.php';
        }

        // Load by id only — do not fail when session/settings branch differs from row.branch_id.
        $entry = auragold_get_cheque_entry_by_id($conn, $cheque_entry_id, 0);
        if (!$entry) {
            return ['ok' => false, 'message' => 'Cheque entry not found.'];
        }

        $status = trim((string) ($entry['status'] ?? ''));
        if (strcasecmp($status, 'Cleared') !== 0) {
            auragold_remove_cheque_entry_clearance_ledger($conn, $cheque_entry_id);

            return ['ok' => true, 'message' => 'Clearance ledger removed.'];
        }

        $amount = (float) ($entry['amount'] ?? 0);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Cheque amount must be greater than zero for clearance.'];
        }

        $bank_name = trim((string) ($entry['bank_name'] ?? ''));
        if ($bank_name === '') {
            return ['ok' => false, 'message' => 'Bank Name is required to clear this cheque.'];
        }

        $party = trim((string) ($entry['account_ledger'] ?? ''));
        if ($party === '') {
            return ['ok' => false, 'message' => 'Account Ledger is required to clear this cheque.'];
        }

        // Prefer the cheque row's branch so Account Ledger branch filter includes the bank line.
        $post_branch_id = (int) ($entry['branch_id'] ?? 0);
        if ($post_branch_id <= 0) {
            $post_branch_id = $branch_id > 0 ? $branch_id : 0;
        }

        $pdc_no = trim((string) ($entry['pdc_no'] ?? ''));
        $cheque_no = trim((string) ($entry['cheque_no'] ?? ''));
        $direction = auragold_cheque_entry_clearance_direction((string) ($entry['pdc_voucher_type'] ?? ''));
        $pdc_ledger = auragold_pdc_system_ledger_name($direction);

        $clearance_date = trim((string) ($entry['bounced_cleared_date'] ?? ''));
        if ($clearance_date === '' || $clearance_date === '0000-00-00') {
            $clearance_date = trim((string) ($entry['pay_date'] ?? ''));
        }
        if ($clearance_date === '' || $clearance_date === '0000-00-00') {
            $clearance_date = date('Y-m-d');
        } else {
            $ts = strtotime($clearance_date);
            $clearance_date = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
        }

        // Persist cleared date when user left it blank (so list + ledger dates stay consistent).
        $bcd_raw = trim((string) ($entry['bounced_cleared_date'] ?? ''));
        if ($bcd_raw === '' || $bcd_raw === '0000-00-00') {
            @mysqli_query(
                $conn,
                'UPDATE `tbl_cheque_entry` SET bounced_cleared_date = \''
                . mysqli_real_escape_string($conn, $clearance_date)
                . '\', updated_at = NOW() WHERE id = ' . (int) $cheque_entry_id
            );
        }

        if (!function_exists('auragold_ensure_payment_mode_ledger')) {
            require_once __DIR__ . '/auragold_ensure_payment_mode_ledger.php';
        }
        $ensured = auragold_ensure_payment_mode_ledger($conn, $bank_name, 'bank', $post_branch_id);
        if (!empty($ensured['ok']) && trim((string) ($ensured['name'] ?? '')) !== '') {
            $bank_name = trim((string) $ensured['name']);
        }

        auragold_remove_cheque_entry_clearance_ledger($conn, $cheque_entry_id);

        $clearance_no = auragold_allocate_next_pdc_clearance_no($conn);

        if ($direction === 'payable') {
            $pdc_debit = $amount;
            $pdc_credit = 0.0;
            $bank_debit = 0.0;
            $bank_credit = $amount;
        } else {
            $pdc_debit = 0.0;
            $pdc_credit = $amount;
            $bank_debit = $amount;
            $bank_credit = 0.0;
        }

        $against_bank = auragold_pdc_clearance_against_bank_label($bank_name, $amount, $direction);
        $against_pdc_on_bank = auragold_pdc_clearance_against_pdc_ledger_label($pdc_ledger, $amount, $direction);

        $label_bits = [];
        if ($pdc_no !== '') {
            $label_bits[] = $pdc_no;
        }
        if ($cheque_no !== '') {
            $label_bits[] = 'Chq ' . $cheque_no;
        }
        if ($party !== '') {
            $label_bits[] = $party;
        }
        $human = 'PDC Clearance ' . $clearance_no
            . ($label_bits !== [] ? ' (' . implode(' / ', $label_bits) . ')' : '')
            . ' — ' . $bank_name;

        $pdc_res = auragold_insert_pdc_clearance_ledger_row(
            $conn,
            $cheque_entry_id,
            $pdc_ledger,
            $clearance_no,
            $clearance_date,
            $pdc_debit,
            $pdc_credit,
            $against_bank,
            $pdc_no !== '' ? $pdc_no : $clearance_no,
            $post_branch_id,
            $user_id,
            'pdc_ledger|' . $human
        );
        if (empty($pdc_res['ok'])) {
            return $pdc_res;
        }

        $bank_res = auragold_insert_pdc_clearance_ledger_row(
            $conn,
            $cheque_entry_id,
            $bank_name,
            $clearance_no,
            $clearance_date,
            $bank_debit,
            $bank_credit,
            $against_pdc_on_bank,
            $pdc_no !== '' ? $pdc_no : $clearance_no,
            $post_branch_id,
            $user_id,
            'bank_ledger|' . $human
        );
        if (empty($bank_res['ok'])) {
            auragold_remove_cheque_entry_clearance_ledger($conn, $cheque_entry_id);

            return $bank_res;
        }

        return [
            'ok' => true,
            'message' => 'PDC clearance posted to ' . $bank_name . '.',
            'clearance_no' => $clearance_no,
        ];
    }
}
