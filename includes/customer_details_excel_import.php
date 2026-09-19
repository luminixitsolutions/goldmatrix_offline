<?php
/**
 * Excel import for Customer Details (tbl_customers full fields).
 */
require_once __DIR__ . '/customer_details_excel_template.php';
require_once __DIR__ . '/account_ledger_excel_import.php';
require_once __DIR__ . '/location-helpers.php';
require_once __DIR__ . '/account_ledger_fixed.php';

if (!function_exists('auragold_customer_details_excel_col_map')) {
    /** @return array<string,int> */
    function auragold_customer_details_excel_col_map(array $headerRow): array
    {
        $aliases = [
            'name'                  => ['name', 'ledger', 'ledger_name', 'customer_name'],
            'alternate_name'        => ['alternate_name', 'alt_name'],
            'first_name'            => ['first_name', 'firstname'],
            'last_name'             => ['last_name', 'lastname'],
            'mobile_country_code'   => ['mobile_country_code', 'mobile_code'],
            'mobile_no'             => ['mobile_no', 'mobile', 'contact'],
            'phone_country_code'    => ['phone_country_code', 'phone_code'],
            'phone_no'              => ['phone_no', 'phone'],
            'mail_id'               => ['mail_id', 'email', 'mail'],
            'identity_no'           => ['identity_no', 'short_code', 'short_code_identity_no', 'short_code_identity_no_'],
            'national_id'           => ['national_id'],
            'trade_no'              => ['trade_no'],
            'identity_issue_date'   => ['identity_issue_date'],
            'identity_expiry_date'  => ['identity_expiry_date'],
            'special_day'           => ['special_day'],
            'customer_type'         => ['customer_type', 'customer_type_name'],
            'registration_no'       => ['registration_no'],
            'registration_date'     => ['registration_date'],
            'gstin'                 => ['gstin', 'gstin_e_way'],
            'nationality'           => ['nationality'],
            'country'               => ['country'],
            'state'                 => ['state'],
            'city'                  => ['city'],
            'group'                 => ['group', 'group_name', 'ledger_group'],
            'sundry_debtors'        => ['sundry_debtors', 'sundry', 'account_group'],
            'kyc'                   => ['kyc'],
            'aml'                   => ['aml'],
            'bill_to_bill'          => ['bill_to_bill', 'bill_to_bill_'],
            'billing_address1'      => ['billing_address_1', 'billing_address1'],
            'billing_address2'      => ['billing_address_2', 'billing_address2'],
            'billing_country'       => ['billing_country'],
            'billing_state'         => ['billing_state'],
            'billing_city'          => ['billing_city'],
            'billing_zip_code'      => ['billing_zip_code', 'billing_zip'],
            'shipping_address1'     => ['shipping_address_1', 'shipping_address1'],
            'shipping_address2'     => ['shipping_address_2', 'shipping_address2'],
            'shipping_country'      => ['shipping_country'],
            'shipping_state'        => ['shipping_state'],
            'shipping_city'         => ['shipping_city'],
            'shipping_zip_code'     => ['shipping_zip_code', 'shipping_zip'],
            'bank_account_no'       => ['bank_account_no', 'account_no'],
            'bank_name'             => ['bank_name'],
            'bank_ifsc_code'        => ['bank_ifsc_code', 'ifsc'],
            'bank_branch'           => ['bank_branch'],
            'notes'                 => ['notes', 'note'],
        ];

        $map = [];
        foreach ($headerRow as $idx => $cell) {
            $norm = auragold_account_ledger_excel_norm_header((string) $cell);
            if ($norm === '') {
                continue;
            }
            foreach ($aliases as $key => $names) {
                if (in_array($norm, $names, true) && !isset($map[$key])) {
                    $map[$key] = (int) $idx;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('auragold_customer_details_excel_parse_date')) {
    function auragold_customer_details_excel_parse_date(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}

if (!function_exists('auragold_customer_details_excel_parse_yes_no')) {
    function auragold_customer_details_excel_parse_yes_no(string $raw, int $default = 0): int
    {
        $v = strtolower(trim($raw));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, ['yes', 'y', '1', 'true'], true)) {
            return 1;
        }
        if (in_array($v, ['no', 'n', '0', 'false'], true)) {
            return 0;
        }

        return $default;
    }
}

if (!function_exists('auragold_customer_details_excel_resolve_name')) {
    function auragold_customer_details_excel_resolve_name(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));

        return trim($first . ' ' . $last);
    }
}

if (!function_exists('auragold_customer_details_excel_normalize_mobile')) {
    function auragold_customer_details_excel_normalize_mobile(string $raw): string
    {
        $v = trim($raw);
        if ($v === '' || $v === '0' || preg_match('/^0+$/', $v)) {
            return '';
        }

        return $v;
    }
}

if (!function_exists('auragold_customer_details_excel_resolve_id_by_name')) {
    function auragold_customer_details_excel_resolve_id_by_name($conn, string $table, string $name, string $extraWhere = ''): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $esc = mysqli_real_escape_string($conn, $name);
        $sql = "SELECT id FROM {$table} WHERE status = 1 AND TRIM(name) = '{$esc}'";
        if ($extraWhere !== '') {
            $sql .= ' AND ' . $extraWhere;
        }
        $sql .= ' LIMIT 1';
        $row = getRecord($sql);

        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_customer_details_excel_resolve_state_id')) {
    function auragold_customer_details_excel_resolve_state_id($conn, string $stateName, int $countryId = 0): int
    {
        $stateName = trim($stateName);
        if ($stateName === '') {
            return 0;
        }
        $extra = $countryId > 0 ? 'country_id = ' . (int) $countryId : '';

        return auragold_customer_details_excel_resolve_id_by_name($conn, 'tbl_states', $stateName, $extra);
    }
}

if (!function_exists('auragold_customer_details_excel_resolve_city_id')) {
    function auragold_customer_details_excel_resolve_city_id($conn, string $cityName, int $stateId = 0): int
    {
        $cityName = trim($cityName);
        if ($cityName === '') {
            return 0;
        }
        $extra = $stateId > 0 ? 'state_id = ' . (int) $stateId : '';

        return auragold_customer_details_excel_resolve_id_by_name($conn, 'tbl_cities', $cityName, $extra);
    }
}

if (!function_exists('auragold_customer_details_excel_resolve_customer_type_id')) {
    function auragold_customer_details_excel_resolve_customer_type_id($conn, string $name, int $defaultId = 0): int
    {
        $id = auragold_customer_details_excel_resolve_id_by_name($conn, 'tbl_customer_types', $name);
        if ($id > 0) {
            return $id;
        }

        return $defaultId;
    }
}

if (!function_exists('auragold_customer_details_ensure_account_ledger')) {
    /**
     * Account Ledger lists tbl_customer_ledger names, not tbl_customers.
     * Create a zero opening row so imported customers appear on account-ledger.php.
     */
    function auragold_customer_details_ensure_account_ledger($conn, int $customerId, string $name): array
    {
        if ($customerId <= 0 || trim($name) === '') {
            return ['ok' => false, 'message' => 'Invalid customer for ledger'];
        }
        require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        require_once __DIR__ . '/auragold_ledger_opening_metals.php';

        auragold_ensure_customer_ledger_branch_column($conn);
        auragold_ledger_opening_ensure_metal_columns($conn);

        $nameEsc = esc($name);
        $nameSql = mysqli_real_escape_string($conn, $name);
        $existingLedger = getRecord(
            "SELECT id FROM tbl_customer_ledger
             WHERE status = 1 AND (customer_id = {$customerId} OR TRIM(customer_name) = '{$nameSql}')
             LIMIT 1"
        );
        if (is_array($existingLedger) && (int) ($existingLedger['id'] ?? 0) > 0) {
            $lid = (int) $existingLedger['id'];
            @mysqli_query(
                $conn,
                "UPDATE tbl_customer_ledger SET customer_id = {$customerId}, customer_name = '{$nameEsc}' WHERE id = {$lid}"
            );

            return ['ok' => true, 'message' => 'exists'];
        }

        $branchId = 0;
        if (function_exists('auragold_effective_branch_id')) {
            $branchId = (int) auragold_effective_branch_id();
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customers', 'branch_id') && $branchId > 0) {
            @mysqli_query($conn, 'UPDATE tbl_customers SET branch_id = ' . (int) $branchId . ' WHERE id = ' . (int) $customerId);
        }

        $userId = (int) ($_SESSION['Admin']['id'] ?? $_SESSION['user_id'] ?? 0);
        $today = date('Y-m-d');
        $obBrSql = $branchId > 0 ? (string) (int) $branchId : 'NULL';
        list($opening_metal_insert_cols, $opening_metal_insert_vals) = auragold_ledger_opening_metal_insert_cols($conn, []);

        $ins = "INSERT INTO tbl_customer_ledger
            (customer_id, customer_name, branch_id, transaction_type, transaction_id, transaction_no, transaction_date,
             debit_amount, credit_amount, {$opening_metal_insert_cols},
             balance_amount, description, status, created_by, created_at)
            VALUES
            ({$customerId}, '{$nameEsc}', {$obBrSql}, 'opening', 0, 'OPENING', '{$today}',
             0, 0, {$opening_metal_insert_vals},
             0, 'Opening balance (customer import)', 1, {$userId}, NOW())";
        if (!@mysqli_query($conn, $ins)) {
            return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Ledger insert failed'];
        }

        return ['ok' => true, 'message' => 'created'];
    }
}

if (!function_exists('auragold_customer_details_import_row')) {
    /**
     * @return array{ok:bool,action:string,message:string,name:string}
     */
    function auragold_customer_details_import_row($conn, array $row, int $defaultCustomerTypeId, bool $forceCreate = false): array
    {
        auragold_bootstrap_location_data($conn);

        $name = auragold_customer_details_excel_resolve_name($row);
        if ($name === '') {
            return ['ok' => false, 'action' => 'skip', 'message' => 'Name is required', 'name' => ''];
        }

        if (auragold_account_ledger_is_fixed_name($name)) {
            return ['ok' => false, 'action' => 'skip', 'message' => 'Cannot import fixed system ledger', 'name' => $name];
        }

        $mobileNo = auragold_customer_details_excel_normalize_mobile((string) ($row['mobile_no'] ?? ''));
        $customerTypeId = auragold_customer_details_excel_resolve_customer_type_id(
            $conn,
            (string) ($row['customer_type'] ?? ''),
            $defaultCustomerTypeId
        );

        $nameEsc = mysqli_real_escape_string($conn, $name);
        $existing = null;
        if (!$forceCreate) {
            // Match by mobile when provided so a new number creates a new customer
            // even if the name already exists. Same mobile updates that customer.
            if ($mobileNo !== '') {
                $mobileEsc = mysqli_real_escape_string($conn, $mobileNo);
                $existing = getRecord(
                    "SELECT id, mobile_no, customer_type_id FROM tbl_customers
                     WHERE mobile_no = '{$mobileEsc}' AND status = 1 LIMIT 1"
                );
            } else {
                $existing = getRecord(
                    "SELECT id, mobile_no, customer_type_id FROM tbl_customers
                     WHERE TRIM(name) = '{$nameEsc}' AND status = 1 LIMIT 1"
                );
            }
        }
        $customerId = is_array($existing) ? (int) ($existing['id'] ?? 0) : 0;
        $isUpdate = $customerId > 0;

        if (!$isUpdate && $customerTypeId <= 0) {
            return ['ok' => false, 'action' => 'error', 'message' => 'Customer Type is required for new customers', 'name' => $name];
        }

        $countryId = auragold_customer_details_excel_resolve_id_by_name($conn, 'tbl_countries', (string) ($row['country'] ?? ''));
        $stateId = auragold_customer_details_excel_resolve_state_id($conn, (string) ($row['state'] ?? ''), $countryId);
        $cityId = auragold_customer_details_excel_resolve_city_id($conn, (string) ($row['city'] ?? ''), $stateId);
        $nationalityId = auragold_customer_details_excel_resolve_id_by_name($conn, 'tbl_nationalities', (string) ($row['nationality'] ?? ''));
        $groupId = auragold_ledger_group_id_by_name((string) ($row['group'] ?? ''));
        $sundryId = auragold_sundry_debtors_id_by_name((string) ($row['sundry_debtors'] ?? ''));
        if ($sundryId <= 0) {
            $sundryId = auragold_sundry_debtors_id_by_name('Sundry Debtors');
        }
        if ($isUpdate && $customerTypeId <= 0) {
            $customerTypeId = (int) ($existing['customer_type_id'] ?? $defaultCustomerTypeId);
        }

        $fields = [
            'alternate_name'       => trim((string) ($row['alternate_name'] ?? '')),
            'first_name'           => trim((string) ($row['first_name'] ?? '')),
            'last_name'            => trim((string) ($row['last_name'] ?? '')),
            'mobile_country_code'  => trim((string) ($row['mobile_country_code'] ?? '971')) ?: '971',
            'mobile_no'            => $mobileNo,
            'phone_country_code'   => trim((string) ($row['phone_country_code'] ?? '971')) ?: '971',
            'phone_no'             => trim((string) ($row['phone_no'] ?? '')),
            'mail_id'              => trim((string) ($row['mail_id'] ?? '')),
            'identity_no'          => trim((string) ($row['identity_no'] ?? '')),
            'national_id'          => trim((string) ($row['national_id'] ?? '')),
            'trade_no'             => trim((string) ($row['trade_no'] ?? '')),
            'registration_no'      => trim((string) ($row['registration_no'] ?? '')),
            'billing_address1'     => trim((string) ($row['billing_address1'] ?? '')),
            'billing_address2'     => trim((string) ($row['billing_address2'] ?? '')),
            'billing_country'      => trim((string) ($row['billing_country'] ?? '')),
            'billing_state'        => trim((string) ($row['billing_state'] ?? '')),
            'billing_city'         => trim((string) ($row['billing_city'] ?? '')),
            'billing_zip_code'     => trim((string) ($row['billing_zip_code'] ?? '')),
            'shipping_address1'    => trim((string) ($row['shipping_address1'] ?? '')),
            'shipping_address2'    => trim((string) ($row['shipping_address2'] ?? '')),
            'shipping_country'     => trim((string) ($row['shipping_country'] ?? '')),
            'shipping_state'       => trim((string) ($row['shipping_state'] ?? '')),
            'shipping_city'        => trim((string) ($row['shipping_city'] ?? '')),
            'shipping_zip_code'    => trim((string) ($row['shipping_zip_code'] ?? '')),
            'bank_account_no'      => trim((string) ($row['bank_account_no'] ?? '')),
            'bank_name'            => trim((string) ($row['bank_name'] ?? '')),
            'bank_ifsc_code'       => trim((string) ($row['bank_ifsc_code'] ?? '')),
            'bank_branch'          => trim((string) ($row['bank_branch'] ?? '')),
            'notes'                => trim((string) ($row['notes'] ?? '')),
        ];

        $dates = [
            'identity_issue_date'  => auragold_customer_details_excel_parse_date((string) ($row['identity_issue_date'] ?? '')),
            'identity_expiry_date' => auragold_customer_details_excel_parse_date((string) ($row['identity_expiry_date'] ?? '')),
            'special_day'          => auragold_customer_details_excel_parse_date((string) ($row['special_day'] ?? '')),
            'registration_date'    => auragold_customer_details_excel_parse_date((string) ($row['registration_date'] ?? '')),
        ];

        $gstinRaw = strtoupper(preg_replace('/\s+/', '', (string) ($row['gstin'] ?? '')));
        if (strlen($gstinRaw) > 15) {
            $gstinRaw = substr($gstinRaw, 0, 15);
        }

        $kyc = auragold_customer_details_excel_parse_yes_no((string) ($row['kyc'] ?? ''), 0);
        $aml = auragold_customer_details_excel_parse_yes_no((string) ($row['aml'] ?? ''), 0);
        $billToBill = auragold_customer_details_excel_parse_yes_no((string) ($row['bill_to_bill'] ?? ''), 0);

        $sqlDate = static function (?string $d): string {
            return $d !== null && $d !== '' ? "'" . esc($d) . "'" : 'NULL';
        };

        if ($isUpdate) {
            $sql = "UPDATE tbl_customers SET
                name = '" . esc($name) . "',
                alternate_name = '" . esc($fields['alternate_name']) . "',
                first_name = '" . esc($fields['first_name']) . "',
                last_name = '" . esc($fields['last_name']) . "',
                mobile_country_code = '" . esc($fields['mobile_country_code']) . "',
                mobile_no = '" . esc($fields['mobile_no']) . "',
                phone_country_code = '" . esc($fields['phone_country_code']) . "',
                phone_no = '" . esc($fields['phone_no']) . "',
                mail_id = '" . esc($fields['mail_id']) . "',
                identity_no = '" . esc($fields['identity_no']) . "',
                national_id = '" . esc($fields['national_id']) . "',
                trade_no = '" . esc($fields['trade_no']) . "',
                identity_issue_date = " . $sqlDate($dates['identity_issue_date']) . ",
                identity_expiry_date = " . $sqlDate($dates['identity_expiry_date']) . ",
                special_day = " . $sqlDate($dates['special_day']) . ",
                customer_type_id = " . (int) $customerTypeId . ",
                registration_no = '" . esc($fields['registration_no']) . "',
                registration_date = " . $sqlDate($dates['registration_date']) . ",
                gstin = " . ($gstinRaw !== '' ? "'" . esc($gstinRaw) . "'" : 'NULL') . ",
                nationality_id = " . (int) $nationalityId . ",
                country_id = " . (int) $countryId . ",
                ledger_state_id = " . (int) $stateId . ",
                ledger_city_id = " . (int) $cityId . ",
                group_id = " . (int) $groupId . ",
                sundry_debtors_id = " . (int) $sundryId . ",
                kyc = " . (int) $kyc . ",
                aml = " . (int) $aml . ",
                bill_to_bill = " . (int) $billToBill . ",
                billing_address1 = '" . esc($fields['billing_address1']) . "',
                billing_address2 = '" . esc($fields['billing_address2']) . "',
                billing_country = '" . esc($fields['billing_country']) . "',
                billing_state = '" . esc($fields['billing_state']) . "',
                billing_city = '" . esc($fields['billing_city']) . "',
                billing_zip_code = '" . esc($fields['billing_zip_code']) . "',
                shipping_address1 = '" . esc($fields['shipping_address1']) . "',
                shipping_address2 = '" . esc($fields['shipping_address2']) . "',
                shipping_country = '" . esc($fields['shipping_country']) . "',
                shipping_state = '" . esc($fields['shipping_state']) . "',
                shipping_city = '" . esc($fields['shipping_city']) . "',
                shipping_zip_code = '" . esc($fields['shipping_zip_code']) . "',
                bank_account_no = '" . esc($fields['bank_account_no']) . "',
                bank_name = '" . esc($fields['bank_name']) . "',
                bank_ifsc_code = '" . esc($fields['bank_ifsc_code']) . "',
                bank_branch = '" . esc($fields['bank_branch']) . "',
                notes = '" . esc($fields['notes']) . "',
                updated_at = NOW()
                WHERE id = " . (int) $customerId . " AND status = 1";

            if (!@mysqli_query($conn, $sql)) {
                return ['ok' => false, 'action' => 'error', 'message' => mysqli_error($conn) ?: 'Update failed', 'name' => $name];
            }

            $ledgerRes = auragold_customer_details_ensure_account_ledger($conn, $customerId, $name);
            if (empty($ledgerRes['ok'])) {
                return ['ok' => false, 'action' => 'error', 'message' => $ledgerRes['message'] ?? 'Ledger create failed', 'name' => $name];
            }

            return ['ok' => true, 'action' => 'updated', 'message' => 'OK', 'name' => $name];
        }

        $insertMobile = $mobileNo !== '' ? esc($mobileNo) : esc('0000000000');
        $sql = "INSERT INTO tbl_customers
            (name, alternate_name, first_name, last_name, mobile_country_code, mobile_no, phone_country_code, phone_no,
             mail_id, identity_no, national_id, trade_no, identity_issue_date, identity_expiry_date, special_day,
             customer_type_id, registration_no, registration_date, gstin, nationality_id, country_id, ledger_state_id, ledger_city_id,
             group_id, sundry_debtors_id, kyc, aml, bill_to_bill,
             billing_address1, billing_address2, billing_country, billing_state, billing_city, billing_zip_code,
             shipping_address1, shipping_address2, shipping_country, shipping_state, shipping_city, shipping_zip_code,
             bank_account_no, bank_name, bank_ifsc_code, bank_branch, notes, status, created_at)
            VALUES
            ('" . esc($name) . "', '" . esc($fields['alternate_name']) . "', '" . esc($fields['first_name']) . "', '" . esc($fields['last_name']) . "',
             '" . esc($fields['mobile_country_code']) . "', '{$insertMobile}', '" . esc($fields['phone_country_code']) . "', '" . esc($fields['phone_no']) . "',
             '" . esc($fields['mail_id']) . "', '" . esc($fields['identity_no']) . "', '" . esc($fields['national_id']) . "', '" . esc($fields['trade_no']) . "',
             " . $sqlDate($dates['identity_issue_date']) . ", " . $sqlDate($dates['identity_expiry_date']) . ", " . $sqlDate($dates['special_day']) . ",
             " . (int) $customerTypeId . ", '" . esc($fields['registration_no']) . "', " . $sqlDate($dates['registration_date']) . ",
             " . ($gstinRaw !== '' ? "'" . esc($gstinRaw) . "'" : 'NULL') . ",
             " . (int) $nationalityId . ", " . (int) $countryId . ", " . (int) $stateId . ", " . (int) $cityId . ",
             " . (int) $groupId . ", " . (int) $sundryId . ", " . (int) $kyc . ", " . (int) $aml . ", " . (int) $billToBill . ",
             '" . esc($fields['billing_address1']) . "', '" . esc($fields['billing_address2']) . "', '" . esc($fields['billing_country']) . "',
             '" . esc($fields['billing_state']) . "', '" . esc($fields['billing_city']) . "', '" . esc($fields['billing_zip_code']) . "',
             '" . esc($fields['shipping_address1']) . "', '" . esc($fields['shipping_address2']) . "', '" . esc($fields['shipping_country']) . "',
             '" . esc($fields['shipping_state']) . "', '" . esc($fields['shipping_city']) . "', '" . esc($fields['shipping_zip_code']) . "',
             '" . esc($fields['bank_account_no']) . "', '" . esc($fields['bank_name']) . "', '" . esc($fields['bank_ifsc_code']) . "', '" . esc($fields['bank_branch']) . "',
             '" . esc($fields['notes']) . "', 1, NOW())";

        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'action' => 'error', 'message' => mysqli_error($conn) ?: 'Insert failed', 'name' => $name];
        }

        $customerId = (int) mysqli_insert_id($conn);
        $ledgerRes = auragold_customer_details_ensure_account_ledger($conn, $customerId, $name);
        if (empty($ledgerRes['ok'])) {
            return ['ok' => false, 'action' => 'error', 'message' => $ledgerRes['message'] ?? 'Ledger create failed', 'name' => $name];
        }

        return ['ok' => true, 'action' => 'created', 'message' => 'OK', 'name' => $name];
    }
}
