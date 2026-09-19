<?php
/**
 * Ensure tbl_customers exists for a ledger name shown on Account Ledger
 * (system ledgers may exist only in tbl_customer_ledger with customer_id = 0).
 */

if (!function_exists('auragold_ledger_customer_sundry_id')) {
    function auragold_ledger_customer_sundry_id(string $ledgerName): ?int
    {
        static $map = [
            'Cash' => 28,
            'Bank Account' => 29,
            'Sales Account' => 11,
            'Purchase Account' => 12,
            'Making Sale Account' => 11,
            'Making Sales Account' => 11,
            'Making Purchase Account' => 12,
            'Hedging Account' => 1,
            'Manufacturing Account' => 1,
            'Metal Exchange' => 1,
            'Profit And Loss' => 1,
            'Discount Allowed' => 16,
            'Discount Received' => 15,
            'Coupon Discount Allowed' => 16,
            'Coupon Discount Received' => 15,
            'RoundOFF Allowed' => 16,
            'RoundOFF Received' => 15,
            'Adjust Price_Discount' => 16,
            'Redeem Allowed' => 16,
            'PDC Payable' => 4,
            'PDC Receivable' => 7,
            'Advance Payment' => 26,
            'Salary' => 16,
            'Service Account' => 30,
        ];

        $name = trim($ledgerName);
        if ($name === '') {
            return null;
        }

        return $map[$name] ?? 1;
    }
}

if (!function_exists('auragold_ensure_ledger_customer')) {
    /**
     * @return array{ok:bool,name:string,id:int,created:bool,message:string}
     */
    function auragold_ensure_ledger_customer($conn, string $ledgerName, int $branchId = 0): array
    {
        $name = trim($ledgerName);
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

        $ledger_row = getRecord(
            "SELECT id FROM tbl_customer_ledger
             WHERE TRIM(customer_name) = '{$name_esc}' AND status = 1
             LIMIT 1"
        );
        if (!$ledger_row) {
            return ['ok' => false, 'name' => $name, 'id' => 0, 'created' => false, 'message' => 'Ledger not found.'];
        }

        $sd = auragold_ledger_customer_sundry_id($name);
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
                'message' => mysqli_error($conn) ?: 'Could not create ledger customer.',
            ];
        }

        $newId = (int) mysqli_insert_id($conn);

        // Link legacy system rows (customer_id = 0) to the new customer record.
        @mysqli_query(
            $conn,
            "UPDATE tbl_customer_ledger
             SET customer_id = {$newId}
             WHERE customer_id = 0 AND TRIM(customer_name) = '{$name_esc}' AND status = 1"
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
