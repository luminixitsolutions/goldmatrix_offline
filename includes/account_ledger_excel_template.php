<?php
/**
 * Account Ledger Excel import template — headers + sample rows (opening balances + metals).
 */
require_once __DIR__ . '/auragold_sundry_debtors_options.php';
require_once __DIR__ . '/auragold_ledger_opening_metals.php';
require_once __DIR__ . '/auragold_ensure_ledger_customer.php';
require_once __DIR__ . '/account_ledger_fixed.php';
require_once __DIR__ . '/account_ledger_list_data.php';

if (!function_exists('auragold_account_ledger_excel_metal_keys')) {
    /** @return list<string> */
    function auragold_account_ledger_excel_metal_keys(): array
    {
        return ['gold', 'silver', 'platinum', 'diamond'];
    }
}

if (!function_exists('auragold_account_ledger_excel_metal_label')) {
    function auragold_account_ledger_excel_metal_label(string $ledger_key): string
    {
        $labels = [
            'gold'     => 'Gold',
            'silver'   => 'Silver',
            'platinum' => 'Platinum',
            'diamond'  => 'Diamond',
        ];

        return $labels[strtolower(trim($ledger_key))] ?? ucfirst($ledger_key);
    }
}

if (!function_exists('auragold_account_ledger_excel_headers')) {
    /** @return list<string> */
    function auragold_account_ledger_excel_headers(): array
    {
        $headers = [
            'Ledger',
            'Contact',
            'Sundry Debtors',
            'Branch Name',
            'Opening Balance',
            'Cr/Dr',
        ];
        foreach (auragold_account_ledger_excel_metal_keys() as $key) {
            $label = auragold_account_ledger_excel_metal_label($key);
            $headers[] = $label . ' Opening (gm)';
            $headers[] = $label . ' Cr/Dr';
        }

        return $headers;
    }
}

if (!function_exists('auragold_account_ledger_excel_sample_rows')) {
    /**
     * @return list<list<string>>
     */
    function auragold_account_ledger_excel_sample_rows($conn, int $branchId = 0): array
    {
        $defaultBranch = auragold_account_ledger_excel_branch_name($conn, $branchId);
        $metalZeros = [];
        foreach (auragold_account_ledger_excel_metal_keys() as $key) {
            $metalZeros[] = '0';
            $metalZeros[] = 'Cr';
        }

        // Two sample rows only — delete/replace before import.
        return [
            array_merge(
                [
                    'Cash',
                    '',
                    'Cash-in Hand',
                    $defaultBranch,
                    '5000',
                    'Dr',
                ],
                $metalZeros
            ),
            [
                'SAMPLE CUSTOMER',
                '9876543210',
                'Sundry Debtors',
                $defaultBranch,
                '1000',
                'Dr',
                '10.500',
                'Dr',
                '250.000',
                'Cr',
                '0',
                'Cr',
                '0',
                'Cr',
            ],
        ];
    }
}

if (!function_exists('auragold_account_ledger_excel_login_branch_id')) {
    function auragold_account_ledger_excel_login_branch_id(int $branchId = 0): int
    {
        if ($branchId > 0) {
            return $branchId;
        }
        if (function_exists('auragold_effective_branch_id')) {
            return (int) auragold_effective_branch_id();
        }

        return 0;
    }
}

if (!function_exists('auragold_account_ledger_excel_branch_name')) {
    function auragold_account_ledger_excel_branch_name($conn, int $branchId = 0): string
    {
        $branchId = auragold_account_ledger_excel_login_branch_id($branchId);
        if ($branchId <= 0) {
            return '';
        }
        $br = getRecord('SELECT name FROM tbl_branches WHERE status = 1 AND id = ' . (int) $branchId . ' LIMIT 1');
        if (!is_array($br)) {
            return '';
        }

        return trim((string) ($br['name'] ?? ''));
    }
}

if (!function_exists('auragold_account_ledger_excel_branch_names')) {
    /**
     * Dropdown list for Branch Name — current login/working branch only.
     *
     * @return list<string>
     */
    function auragold_account_ledger_excel_branch_names($conn, int $branchId = 0): array
    {
        $name = auragold_account_ledger_excel_branch_name($conn, $branchId);

        return $name !== '' ? [$name] : [];
    }
}
