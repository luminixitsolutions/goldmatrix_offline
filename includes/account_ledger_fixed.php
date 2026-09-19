<?php
/**
 * Fixed (system) account ledgers — cannot be deleted from Account Ledger.
 */

if (!function_exists('auragold_account_ledger_fixed_names')) {
    /** @return list<string> */
    function auragold_account_ledger_fixed_names(): array
    {
        return [
            'Cash',
            'Bank Account',
            'Sales Account',
            'Purchase Account',
            'Making Sale Account',
            'Making Sales Account',
            'Making Purchase Account',
            'Hedging Account',
            'Manufacturing Account',
            'Metal Exchange',
            'Profit And Loss',
            'Discount Allowed',
            'Discount Received',
            'Coupon Discount Allowed',
            'Coupon Discount Received',
            'RoundOFF Allowed',
            'RoundOFF Received',
            'Adjust Price_Discount',
            'Redeem Allowed',
            'PDC Payable',
            'PDC Receivable',
            'Advance Payment',
            'Salary',
            'Service Account',
        ];
    }
}

if (!function_exists('auragold_account_ledger_is_fixed_name')) {
    function auragold_account_ledger_is_fixed_name(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        foreach (auragold_account_ledger_fixed_names() as $fixed) {
            if (strcasecmp($fixed, $name) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('auragold_ensure_customer_is_fixed_column')) {
    function auragold_ensure_customer_is_fixed_column($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customers', 'is_fixed')) {
            return true;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customers LIKE 'is_fixed'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return true;
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        $ok = @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customers ADD COLUMN is_fixed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = system/fixed ledger; cannot delete' AFTER status"
        );
        return (bool) $ok;
    }
}

if (!function_exists('auragold_account_ledger_fixed_template_rows')) {
    /**
     * Rows for Account_Ledger_Import_Template.xlsx (fixed ledgers only).
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string}>
     */
    function auragold_account_ledger_fixed_template_rows(): array
    {
        require_once __DIR__ . '/account_ledger_list_data.php';
        $groupMap = function_exists('auragold_account_ledger_group_map')
            ? auragold_account_ledger_group_map()
            : [];
        $rows = [];
        foreach (auragold_account_ledger_fixed_names() as $name) {
            $group = $groupMap[$name] ?? 'Primary';
            $rows[] = [$name, '', $group, '', '0', 'Dr'];
        }
        return $rows;
    }
}
