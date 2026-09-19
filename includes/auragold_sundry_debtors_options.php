<?php
/**
 * Sundry Debtors / account group options (ledger-opening.php dropdown).
 */

if (!function_exists('auragold_sundry_debtors_options_list')) {
    /** @return list<array{id:int,name:string}> */
    function auragold_sundry_debtors_options_list(): array
    {
        return [
            ['id' => 1,  'name' => 'Primary'],
            ['id' => 2,  'name' => 'Capital Account'],
            ['id' => 3,  'name' => 'Loans (Liability)'],
            ['id' => 4,  'name' => 'Current Liabilities'],
            ['id' => 5,  'name' => 'Fixed Assets'],
            ['id' => 6,  'name' => 'Investments'],
            ['id' => 7,  'name' => 'Current Assets'],
            ['id' => 8,  'name' => 'Branch /Divisions'],
            ['id' => 9,  'name' => 'Misc.Expenses (ASSET)'],
            ['id' => 10, 'name' => 'Suspense A/C'],
            ['id' => 11, 'name' => 'Sales Account'],
            ['id' => 12, 'name' => 'Purchase Account'],
            ['id' => 13, 'name' => 'Direct Income'],
            ['id' => 14, 'name' => 'Direct Expenses'],
            ['id' => 15, 'name' => 'Indirect Income'],
            ['id' => 16, 'name' => 'Indirect Expenses'],
            ['id' => 17, 'name' => 'Reserves & Surplus'],
            ['id' => 18, 'name' => 'Bank OD A/C'],
            ['id' => 19, 'name' => 'Secured Loans'],
            ['id' => 20, 'name' => 'UnSecured Loans'],
            ['id' => 21, 'name' => 'Duties & Taxes'],
            ['id' => 22, 'name' => 'Provisions'],
            ['id' => 23, 'name' => 'Sundry Creditors'],
            ['id' => 24, 'name' => 'Stock-in-Hand'],
            ['id' => 25, 'name' => 'Deposits(Assets)'],
            ['id' => 26, 'name' => 'Loans & Advances(Asset)'],
            ['id' => 27, 'name' => 'Sundry Debtors'],
            ['id' => 28, 'name' => 'Cash-in Hand'],
            ['id' => 29, 'name' => 'Bank Account'],
            ['id' => 30, 'name' => 'Service Account'],
        ];
    }
}

if (!function_exists('auragold_sundry_debtors_id_by_name')) {
    function auragold_sundry_debtors_id_by_name(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        foreach (auragold_sundry_debtors_options_list() as $opt) {
            if (strcasecmp((string) ($opt['name'] ?? ''), $name) === 0) {
                return (int) ($opt['id'] ?? 0);
            }
        }

        return 0;
    }
}

if (!function_exists('auragold_sundry_debtors_name_by_id')) {
    function auragold_sundry_debtors_name_by_id(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        foreach (auragold_sundry_debtors_options_list() as $opt) {
            if ((int) ($opt['id'] ?? 0) === $id) {
                return trim((string) ($opt['name'] ?? ''));
            }
        }

        return '';
    }
}

if (!function_exists('auragold_sundry_debtors_names_list')) {
    /** @return list<string> */
    function auragold_sundry_debtors_names_list(): array
    {
        $names = [];
        foreach (auragold_sundry_debtors_options_list() as $opt) {
            $n = trim((string) ($opt['name'] ?? ''));
            if ($n !== '') {
                $names[] = $n;
            }
        }

        return $names;
    }
}
