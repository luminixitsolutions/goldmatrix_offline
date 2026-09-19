<?php

/**
 * Bank reconciliation — bank ledger accounts (sundry_debtors_id = 29) and statements.
 */
require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';

if (!function_exists('auragold_ledger_description_display')) {
    /**
     * Normalize ledger description text for UI (fixes ??? from latin1/UTF-8 mismatch).
     */
    function auragold_ledger_description_display(string $desc): string
    {
        if ($desc === '') {
            return '';
        }
        // Arrow between voucher side and against-ledger (Contra/Journal) often stored as ???
        $desc = preg_replace('/\)\s*\?\?\?\s*/', ') <-> ', $desc);
        // Em dash and other unsupported UTF-8 chars stored as ???
        $desc = preg_replace('/\s*\?\?\?\s*/', ' - ', $desc);
        return trim($desc);
    }
}

if (!function_exists('auragold_bank_reconciliation_exclude_names')) {
    function auragold_bank_reconciliation_exclude_names(): array
    {
        return ['phonepe', 'phonepay', 'gpay', 'google pay', 'paytm', 'upi', '0.00', '0'];
    }
}

if (!function_exists('auragold_bank_reconciliation_is_excluded_name')) {
    function auragold_bank_reconciliation_is_excluded_name(string $name): bool
    {
        $n = trim(strtolower($name));
        if ($n === '') {
            return true;
        }
        if (in_array($n, auragold_bank_reconciliation_exclude_names(), true)) {
            return true;
        }
        return (bool) preg_match('/^[0-9.]+$/', $n);
    }
}

if (!function_exists('auragold_bank_reconciliation_effective_branch_id')) {
    function auragold_bank_reconciliation_effective_branch_id(): int
    {
        if (function_exists('auragold_effective_branch_id')) {
            $bid = (int) auragold_effective_branch_id();
            if ($bid > 0) {
                return $bid;
            }
        }
        if (!empty($_SESSION['working_branch_id'])) {
            return (int) $_SESSION['working_branch_id'];
        }
        if (!empty($_SESSION['branch_id'])) {
            return (int) $_SESSION['branch_id'];
        }
        return 0;
    }
}

if (!function_exists('auragold_bank_reconciliation_branch_sql')) {
    function auragold_bank_reconciliation_branch_sql(mysqli $conn, string $prefix = 'l'): string
    {
        if (function_exists('auragold_account_ledger_branch_scope_sql')) {
            $scoped = auragold_account_ledger_branch_scope_sql($prefix);
            if ($scoped !== '') {
                return $scoped;
            }
        }
        $bid = auragold_bank_reconciliation_effective_branch_id();
        if ($bid > 0 && function_exists('auragold_customer_ledger_branch_scope_sql')) {
            return auragold_customer_ledger_branch_scope_sql($conn, $bid);
        }
        return '';
    }
}

if (!function_exists('auragold_bank_reconciliation_accounts')) {
    /**
     * Bank ledger accounts visible for the working branch.
     *
     * @return list<array<string, mixed>>
     */
    function auragold_bank_reconciliation_accounts(mysqli $conn, int $branch_id = 0): array
    {
        if ($branch_id <= 0) {
            $branch_id = auragold_bank_reconciliation_effective_branch_id();
        }

        $has_cust_branch = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_customers', 'branch_id');

        $rows = getList("
            SELECT c.id, c.name, c.bank_account_no, c.bank_name, c.bank_ifsc_code, c.bank_branch
                   " . ($has_cust_branch ? ', c.branch_id' : '') . "
            FROM tbl_customers c
            WHERE c.status = 1
              AND c.sundry_debtors_id = 29
              AND TRIM(IFNULL(c.name, '')) != ''
            ORDER BY c.name ASC
        ");
        if (!is_array($rows)) {
            return [];
        }

        auragold_ensure_customer_ledger_branch_column($conn);
        $ledger_br = auragold_bank_reconciliation_branch_sql($conn, 'l');

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if (auragold_bank_reconciliation_is_excluded_name($name)) {
                continue;
            }
            $cid = (int) ($r['id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }

            if ($branch_id > 0) {
                $cust_branch_ok = true;
                if ($has_cust_branch) {
                    $cb = (int) ($r['branch_id'] ?? 0);
                    if ($cb > 0 && $cb !== $branch_id) {
                        $cust_branch_ok = false;
                    }
                }
                if (!$cust_branch_ok) {
                    $name_esc = mysqli_real_escape_string($conn, $name);
                    $chk = getRecord("
                        SELECT COUNT(*) AS n
                        FROM tbl_customer_ledger l
                        WHERE l.status = 1
                          AND (l.customer_id = {$cid} OR LOWER(TRIM(l.customer_name)) = LOWER(TRIM('{$name_esc}')))
                          {$ledger_br}
                        LIMIT 1
                    ");
                    if ((int) ($chk['n'] ?? 0) <= 0) {
                        continue;
                    }
                }
            }

            $out[] = [
                'id'              => $cid,
                'name'            => $name,
                'bank_account_no' => trim((string) ($r['bank_account_no'] ?? '')),
                'bank_name'       => trim((string) ($r['bank_name'] ?? '')),
                'bank_ifsc_code'  => trim((string) ($r['bank_ifsc_code'] ?? '')),
                'bank_branch'     => trim((string) ($r['bank_branch'] ?? '')),
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_bank_reconciliation_voucher_label')) {
    function auragold_bank_reconciliation_voucher_label(array $row): string
    {
        $tt = strtolower(trim((string) ($row['transaction_type'] ?? '')));
        $map = [
            'opening'              => 'Opening',
            'payment_voucher'      => 'Payment Voucher',
            'receipt_voucher'       => 'Receipt Voucher',
            'sale_receipt_voucher' => 'Sale Receipt Voucher',
            'contra_voucher'       => 'Contra Voucher',
            'journal_voucher'      => 'Journal Voucher',
            'sale_invoice'         => 'Sale Invoice',
            'purchase_invoice'     => 'Purchase Invoice',
            'payment'              => 'Payment',
            'receipt'              => 'Receipt',
            'metal_to_amount'      => 'Metal To Amount',
            'amount_to_metal'      => 'Amount To Metal',
        ];
        if (isset($map[$tt])) {
            return $map[$tt];
        }
        if ($tt === '') {
            return '—';
        }
        return ucwords(str_replace('_', ' ', $tt));
    }
}

if (!function_exists('auragold_bank_reconciliation_statement')) {
    /**
     * @return array{
     *   opening: float,
     *   closing: float,
     *   total_debit: float,
     *   total_credit: float,
     *   rows: list<array<string, mixed>>
     * }
     */
    function auragold_bank_reconciliation_statement(
        mysqli $conn,
        int $customer_id,
        string $customer_name,
        string $from_date = '',
        string $to_date = '',
        int $branch_id = 0
    ): array {
        auragold_ensure_customer_ledger_branch_column($conn);

        $customer_name = trim($customer_name);
        if ($customer_id <= 0 && $customer_name === '') {
            return ['opening' => 0.0, 'closing' => 0.0, 'total_debit' => 0.0, 'total_credit' => 0.0, 'rows' => []];
        }

        $ledger_br = auragold_bank_reconciliation_branch_sql($conn, 'l');
        $excl_pb = " AND COALESCE(l.transaction_type,'') <> 'previous_balance_payment'";

        $party_parts = [];
        if ($customer_id > 0) {
            $party_parts[] = 'l.customer_id = ' . (int) $customer_id;
        }
        if ($customer_name !== '') {
            $name_esc = mysqli_real_escape_string($conn, $customer_name);
            $party_parts[] = "LOWER(TRIM(l.customer_name)) = LOWER(TRIM('{$name_esc}'))";
        }
        $party_sql = '(' . implode(' OR ', $party_parts) . ')';

        $from_date = trim($from_date);
        $to_date = trim($to_date);
        $date_between = '';
        if ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $date_between .= " AND l.transaction_date >= '" . esc($from_date) . "'";
        }
        if ($to_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $date_between .= " AND l.transaction_date <= '" . esc($to_date) . " 23:59:59'";
        }

        $opening = 0.0;
        if ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $ob = getRecord("
                SELECT COALESCE(SUM(l.debit_amount - l.credit_amount), 0) AS bal
                FROM tbl_customer_ledger l
                WHERE l.status = 1 AND {$party_sql} {$excl_pb} {$ledger_br}
                  AND l.transaction_date < '" . esc($from_date) . "'
            ");
            $opening = round((float) ($ob['bal'] ?? 0), 2);
        } else {
            $open_row = getRecord("
                SELECT l.balance_amount, l.debit_amount, l.credit_amount
                FROM tbl_customer_ledger l
                WHERE l.status = 1 AND {$party_sql} {$ledger_br}
                  AND l.transaction_type = 'opening'
                ORDER BY l.id DESC
                LIMIT 1
            ");
            if ($open_row) {
                $opening = round((float) ($open_row['balance_amount'] ?? 0), 2);
            }
        }

        $rows = getList("
            SELECT
                l.id,
                l.transaction_date,
                l.transaction_type,
                l.transaction_no,
                l.transaction_id,
                COALESCE(l.against_ledger, '') AS against_ledger,
                COALESCE(l.against_invoice_no, l.reference_no, '') AS against_invoice_no,
                COALESCE(l.description, '') AS description,
                l.debit_amount,
                l.credit_amount
            FROM tbl_customer_ledger l
            WHERE l.status = 1 AND {$party_sql} {$excl_pb} {$ledger_br}
              {$date_between}
            ORDER BY l.transaction_date ASC, l.created_at ASC, l.id ASC
        ");
        if (!is_array($rows)) {
            $rows = [];
        }

        $running = $opening;
        $total_debit = 0.0;
        $total_credit = 0.0;
        $out_rows = [];

        foreach ($rows as $r) {
            $debit = round((float) ($r['debit_amount'] ?? 0), 2);
            $credit = round((float) ($r['credit_amount'] ?? 0), 2);
            $total_debit += $debit;
            $total_credit += $credit;
            $running = round($running + $debit - $credit, 2);
            if (abs($running) < 0.005) {
                $running = 0.0;
            }

            $vno = trim((string) ($r['transaction_no'] ?? ''));
            if ($vno === '' && !empty($r['transaction_id'])) {
                $vno = (string) $r['transaction_id'];
            }

            $out_rows[] = [
                'id'                 => (int) ($r['id'] ?? 0),
                'date'               => (string) ($r['transaction_date'] ?? ''),
                'voucher_no'         => $vno,
                'voucher_type'       => auragold_bank_reconciliation_voucher_label($r),
                'against_ledger'     => (string) ($r['against_ledger'] ?? ''),
                'against_invoice_no' => (string) ($r['against_invoice_no'] ?? ''),
                'description'        => auragold_ledger_description_display((string) ($r['description'] ?? '')),
                'debit'              => $debit,
                'credit'             => $credit,
                'balance'            => $running,
            ];
        }

        $closing = round($opening + $total_debit - $total_credit, 2);
        if (abs($closing) < 0.005) {
            $closing = 0.0;
        }

        return [
            'opening'       => $opening,
            'closing'       => $closing,
            'total_debit'   => round($total_debit, 2),
            'total_credit'  => round($total_credit, 2),
            'rows'          => $out_rows,
        ];
    }
}
