<?php
/**
 * Ensure Cash / Bank / company payment-mode ledgers exist for voucher posting.
 */

if (!function_exists('auragold_payment_company_ledger_name')) {
    /**
     * Shop / company display name used when creating a missing company ledger.
     */
    function auragold_payment_company_ledger_name(): string
    {
        $name = trim((string) ($_SESSION['working_branch_name'] ?? ''));
        if ($name === '') {
            $bid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
            if ($bid > 0 && function_exists('getRecordMaster')) {
                $br = @getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1');
                if (is_array($br)) {
                    $name = trim((string) ($br['name'] ?? ''));
                }
            }
        }
        if ($name === '' && function_exists('auragold_app_name')) {
            $name = trim((string) auragold_app_name());
        }
        if ($name === '' && isset($GLOBALS['Proj_Title'])) {
            $name = trim((string) $GLOBALS['Proj_Title']);
        }
        if ($name === '') {
            $name = 'Company';
        }

        return $name;
    }
}

if (!function_exists('auragold_payment_mode_default_ledger_name')) {
    /**
     * Resolve ledger name for a payment line (deposit_into → defaults → company name).
     */
    function auragold_payment_mode_default_ledger_name(string $paymentType, string $depositInto = ''): string
    {
        $dep = trim($depositInto);
        if ($dep !== '') {
            return $dep;
        }
        $pt = strtolower(trim($paymentType));
        if ($pt === 'cash') {
            return 'Cash';
        }
        if ($pt === 'metal' || strpos($pt, 'metal') !== false) {
            return 'Metal Exchange';
        }
        if (in_array($pt, ['bank', 'cheque', 'check', 'upi', 'card'], true)) {
            // Prefer an existing bank ledger name later; fallback to company name.
            return auragold_payment_company_ledger_name();
        }

        return auragold_payment_company_ledger_name();
    }
}

if (!function_exists('auragold_payment_mode_sundry_debtors_id')) {
    /**
     * Chart group for auto-created payment ledgers (29 = Bank Account in this app).
     */
    function auragold_payment_mode_sundry_debtors_id(string $paymentType): ?int
    {
        $pt = strtolower(trim($paymentType));
        if (in_array($pt, ['bank', 'cheque', 'check', 'upi', 'card'], true)) {
            return 29;
        }

        return null;
    }
}

if (!function_exists('auragold_ensure_payment_mode_ledger')) {
    /**
     * Create tbl_customers (+ optional zero opening ledger row) when the payment ledger is missing.
     *
     * @return array{ok:bool,name:string,id:int,created:bool,message:string}
     */
    function auragold_ensure_payment_mode_ledger($conn, string $ledgerName, string $paymentType = 'cash', int $branchId = 0): array
    {
        $name = trim($ledgerName);
        if ($name === '') {
            $name = auragold_payment_company_ledger_name();
        }
        if ($name === '' || !($conn instanceof mysqli)) {
            return ['ok' => false, 'name' => $name, 'id' => 0, 'created' => false, 'message' => 'Invalid ledger name.'];
        }

        $name_esc = mysqli_real_escape_string($conn, $name);
        $existing = getRecord(
            "SELECT id, name FROM tbl_customers
             WHERE TRIM(name) = '{$name_esc}' AND status = 1
             LIMIT 1"
        );
        if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
            return [
                'ok' => true,
                'name' => (string) $existing['name'],
                'id' => (int) $existing['id'],
                'created' => false,
                'message' => 'exists',
            ];
        }

        $sd = auragold_payment_mode_sundry_debtors_id($paymentType);
        $sd_sql = $sd !== null ? (string) (int) $sd : 'NULL';
        $has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customers', 'branch_id');
        $branch_col = '';
        $branch_val = '';
        if ($has_branch && $branchId > 0) {
            $branch_col = ', branch_id';
            $branch_val = ', ' . (int) $branchId;
        }

        $sql = "INSERT INTO tbl_customers (name, sundry_debtors_id, status, created_at{$branch_col})
                VALUES ('{$name_esc}', {$sd_sql}, 1, NOW(){$branch_val})";
        if (!@mysqli_query($conn, $sql)) {
            // Race / duplicate — re-read
            $existing = getRecord(
                "SELECT id, name FROM tbl_customers WHERE TRIM(name) = '{$name_esc}' AND status = 1 LIMIT 1"
            );
            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
                return [
                    'ok' => true,
                    'name' => (string) $existing['name'],
                    'id' => (int) $existing['id'],
                    'created' => false,
                    'message' => 'exists',
                ];
            }

            return [
                'ok' => false,
                'name' => $name,
                'id' => 0,
                'created' => false,
                'message' => mysqli_error($conn) ?: 'Could not create ledger.',
            ];
        }

        $newId = (int) mysqli_insert_id($conn);

        // Seed a zero opening row so the ledger appears in account ledger / chart lists.
        if (function_exists('auragold_ensure_customer_ledger_branch_column')) {
            auragold_ensure_customer_ledger_branch_column($conn);
        }
        $ledger_has_branch = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
        $lb_col = $ledger_has_branch ? ', branch_id' : '';
        $lb_val = ($ledger_has_branch && $branchId > 0) ? (', ' . (int) $branchId) : ($ledger_has_branch ? ', NULL' : '');
        $openDesc = mysqli_real_escape_string($conn, 'Opening - auto-created for payment mode');
        @mysqli_query(
            $conn,
            "INSERT INTO tbl_customer_ledger (
                customer_id{$lb_col}, customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                balance_amount, balance_gold, balance_silver,
                description, status, created_at
            ) VALUES (
                {$newId}{$lb_val},
                '{$name_esc}',
                'opening',
                0,
                'OPEN-{$newId}',
                CURDATE(),
                0, 0,
                0, 0, 0,
                '{$openDesc}',
                1,
                NOW()
            )"
        );

        return [
            'ok' => true,
            'name' => $name,
            'id' => $newId,
            'created' => true,
            'message' => 'created',
        ];
    }
}
