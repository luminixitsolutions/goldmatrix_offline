<?php
/**
 * Customer Details Excel import template — headers, sample rows, dropdown list sources.
 */
require_once __DIR__ . '/auragold_sundry_debtors_options.php';
require_once __DIR__ . '/location-helpers.php';

if (!function_exists('auragold_ledger_group_options_list')) {
    /** @return list<array{id:int,name:string}> */
    function auragold_ledger_group_options_list(): array
    {
        return [
            ['id' => 1, 'name' => 'Sundry Debtors'],
            ['id' => 2, 'name' => 'Sundry Creditors'],
            ['id' => 3, 'name' => 'Bank Accounts'],
            ['id' => 4, 'name' => 'Cash'],
            ['id' => 5, 'name' => 'Sales'],
            ['id' => 6, 'name' => 'Purchase'],
            ['id' => 7, 'name' => 'Expenses'],
            ['id' => 8, 'name' => 'Income'],
            ['id' => 9, 'name' => 'Capital'],
            ['id' => 10, 'name' => 'Loans & Advances'],
            ['id' => 11, 'name' => 'Fixed Assets'],
            ['id' => 12, 'name' => 'Current Assets'],
            ['id' => 13, 'name' => 'Current Liabilities'],
            ['id' => 14, 'name' => 'Investment'],
        ];
    }
}

if (!function_exists('auragold_ledger_group_id_by_name')) {
    function auragold_ledger_group_id_by_name(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        foreach (auragold_ledger_group_options_list() as $opt) {
            if (strcasecmp((string) ($opt['name'] ?? ''), $name) === 0) {
                return (int) ($opt['id'] ?? 0);
            }
        }

        return 0;
    }
}

if (!function_exists('auragold_ledger_group_names_list')) {
    /** @return list<string> */
    function auragold_ledger_group_names_list(): array
    {
        $names = [];
        foreach (auragold_ledger_group_options_list() as $opt) {
            $n = trim((string) ($opt['name'] ?? ''));
            if ($n !== '') {
                $names[] = $n;
            }
        }

        return $names;
    }
}

if (!function_exists('auragold_customer_details_excel_headers')) {
    /** @return list<string> */
    function auragold_customer_details_excel_headers(): array
    {
        return [
            'Name',
            'Alternate Name',
            'First Name',
            'Last Name',
            'Mobile Country Code',
            'Mobile No',
            'Phone Country Code',
            'Phone No',
            'Mail ID',
            'Short Code / Identity No',
            'National Id',
            'Trade No',
            'Identity Issue Date',
            'Identity Expiry Date',
            'Special Day',
            'Customer Type',
            'Registration No',
            'Registration Date',
            'GSTIN (e-Way)',
            'Nationality',
            'Country',
            'State',
            'City',
            'Group',
            'Sundry Debtors',
            'KYC',
            'AML',
            'Bill to Bill',
            'Billing Address 1',
            'Billing Address 2',
            'Billing Country',
            'Billing State',
            'Billing City',
            'Billing Zip Code',
            'Shipping Address 1',
            'Shipping Address 2',
            'Shipping Country',
            'Shipping State',
            'Shipping City',
            'Shipping Zip Code',
            'Bank Account No',
            'Bank Name',
            'Bank IFSC Code',
            'Bank Branch',
            'Notes',
        ];
    }
}

if (!function_exists('auragold_customer_details_excel_dropdown_map')) {
    /**
     * Header label => list key for ListValues sheet.
     *
     * @return array<string,string>
     */
    function auragold_customer_details_excel_dropdown_map(): array
    {
        return [
            'Customer Type'    => 'customer_type',
            'Nationality'      => 'nationality',
            'Country'          => 'country',
            'State'            => 'state',
            'City'             => 'city',
            'Group'            => 'group',
            'Sundry Debtors'   => 'sundry',
            'KYC'              => 'yes_no',
            'AML'              => 'yes_no',
            'Bill to Bill'     => 'yes_no',
            'Billing Country'  => 'country',
            'Billing State'    => 'state',
            'Billing City'     => 'city',
            'Shipping Country' => 'country',
            'Shipping State'   => 'state',
            'Shipping City'    => 'city',
        ];
    }
}

if (!function_exists('auragold_customer_details_excel_list_values')) {
    /**
     * @return array<string,list<string>>
     */
    function auragold_customer_details_excel_list_values($conn): array
    {
        auragold_bootstrap_location_data($conn);

        $customerTypes = [];
        foreach (getList('SELECT name FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC') as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $customerTypes[] = $n;
            }
        }

        $nationalities = [];
        foreach (getList('SELECT name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC') as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $nationalities[] = $n;
            }
        }

        $countries = [];
        foreach (getList('SELECT name FROM tbl_countries WHERE status = 1 ORDER BY name ASC') as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $countries[] = $n;
            }
        }

        $states = [];
        foreach (getList('SELECT name FROM tbl_states WHERE status = 1 ORDER BY name ASC LIMIT 3000') as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $states[] = $n;
            }
        }

        $cities = [];
        foreach (getList('SELECT name FROM tbl_cities WHERE status = 1 ORDER BY name ASC LIMIT 5000') as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $cities[] = $n;
            }
        }

        return [
            'customer_type' => $customerTypes,
            'nationality'   => $nationalities,
            'country'       => $countries,
            'state'         => $states,
            'city'          => $cities,
            'group'         => auragold_ledger_group_names_list(),
            'sundry'        => auragold_sundry_debtors_names_list(),
            'yes_no'        => ['Yes', 'No'],
        ];
    }
}

if (!function_exists('auragold_customer_details_excel_sample_rows')) {
    /**
     * @return list<list<string>>
     */
    function auragold_customer_details_excel_sample_rows($conn): array
    {
        $lists = auragold_customer_details_excel_list_values($conn);
        $ctype = !empty($lists['customer_type']) ? $lists['customer_type'][0] : 'Customer';
        $nationality = !empty($lists['nationality']) ? $lists['nationality'][0] : '';
        $country = 'India';
        if (!in_array($country, $lists['country'], true) && !empty($lists['country'])) {
            $country = $lists['country'][0];
        }
        $state = 'Maharashtra';
        if (!in_array($state, $lists['state'], true)) {
            $state = !empty($lists['state']) ? $lists['state'][0] : '';
        }
        $city = 'Nagpur';
        if (!in_array($city, $lists['city'], true)) {
            $city = !empty($lists['city']) ? $lists['city'][0] : '';
        }

        return [
            [
                'SAMPLE CUSTOMER ONE',
                '',
                'Sample',
                'Customer',
                '91',
                '9876543210',
                '91',
                '',
                'sample1@example.com',
                '',
                '',
                '',
                '',
                '',
                '',
                $ctype,
                '',
                '',
                '',
                $nationality,
                $country,
                $state,
                $city,
                'Sundry Debtors',
                'Sundry Debtors',
                'No',
                'No',
                'No',
                '123 Main Street',
                '',
                $country,
                $state,
                $city,
                '440001',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Sample customer row — delete before import.',
            ],
            [
                'SAMPLE CUSTOMER TWO',
                'Alt Name',
                '',
                '',
                '971',
                '501234567',
                '',
                '',
                '',
                'ID-001',
                '',
                'TR-100',
                '01-01-2020',
                '31-12-2030',
                '',
                $ctype,
                'REG-55',
                '15-06-2019',
                '',
                $nationality,
                $country,
                $state,
                $city,
                'Sundry Debtors',
                'Sundry Debtors',
                'Yes',
                'No',
                'Yes',
                'Office Block A',
                'Near City Center',
                $country,
                $state,
                $city,
                '400001',
                'Warehouse 2',
                '',
                $country,
                $state,
                $city,
                '400002',
                '1234567890',
                'Sample Bank',
                'SBIN0001234',
                'Main Branch',
                '',
            ],
        ];
    }
}
