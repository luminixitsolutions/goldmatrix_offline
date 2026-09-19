<?php
/**
 * Excel import for Account Ledger list (create/update ledgers + opening balance + metal opening).
 */
require_once __DIR__ . '/auragold_ensure_ledger_customer.php';
require_once __DIR__ . '/auragold_sundry_debtors_options.php';
require_once __DIR__ . '/auragold_ledger_opening_metals.php';
require_once __DIR__ . '/account_ledger_excel_template.php';

if (!function_exists('auragold_account_ledger_excel_ws_cell')) {
    /**
     * PhpSpreadsheet 5+ removed Worksheet::getCellByColumnAndRow. Column index is 1-based (A=1).
     */
    function auragold_account_ledger_excel_ws_cell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $columnIndex1Based, int $row): \PhpOffice\PhpSpreadsheet\Cell\Cell
    {
        $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex1Based) . $row;

        return $sheet->getCell($addr);
    }
}

if (!function_exists('auragold_account_ledger_excel_cell_value')) {
    function auragold_account_ledger_excel_cell_value(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): string
    {
        $val = $cell->getValue();
        if (is_int($val) || is_float($val)) {
            if ($val >= 1000000000 && $val < 1e15) {
                return sprintf('%.0f', (float) $val);
            }
        }

        $formatted = trim((string) $cell->getFormattedValue());
        if ($formatted !== '') {
            return $formatted;
        }

        return trim((string) ($val ?? ''));
    }
}

if (!function_exists('auragold_account_ledger_excel_norm_header')) {
    function auragold_account_ledger_excel_norm_header(string $h): string
    {
        $h = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $h), '_'));

        return $h;
    }
}

if (!function_exists('auragold_account_ledger_excel_col_map')) {
    /** @return array<string,int> */
    function auragold_account_ledger_excel_col_map(array $headerRow): array
    {
        $aliases = [
            'ledger'            => ['ledger', 'ledger_name', 'name', 'account_ledger', 'party'],
            'contact'           => ['contact', 'mobile', 'mobile_no', 'phone', 'phone_no'],
            'group'             => ['group', 'group_name', 'ledger_group'],
            'sundry_debtors'    => ['sundry_debtors', 'sundry_debtors_id', 'sundry', 'account_group'],
            'branch_name'       => ['branch_name', 'branch', 'opening_branch'],
            'opening_balance'   => ['opening_balance', 'opening_bal', 'balance', 'opening', 'amount'],
            'crdr'              => ['cr_dr', 'crdr', 'cr_dr_type', 'drcr'],
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

        foreach ($headerRow as $idx => $cell) {
            $cellStr = trim((string) $cell);
            if ($cellStr === '') {
                continue;
            }
            foreach (auragold_account_ledger_excel_metal_keys() as $key) {
                $label = auragold_account_ledger_excel_metal_label($key);
                if (strcasecmp($cellStr, $label . ' Opening (gm)') === 0) {
                    $map['metal_' . $key . '_weight'] = (int) $idx;
                }
                if (strcasecmp($cellStr, $label . ' Cr/Dr') === 0) {
                    $map['metal_' . $key . '_crdr'] = (int) $idx;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('auragold_account_ledger_excel_parse_crdr')) {
    function auragold_account_ledger_excel_parse_crdr(string $raw): string
    {
        $v = strtolower(trim($raw));
        if ($v === 'cr' || $v === 'credit' || $v === 'c') {
            return 'Cr';
        }

        return 'Dr';
    }
}

if (!function_exists('auragold_account_ledger_excel_parse_crdr_post')) {
    function auragold_account_ledger_excel_parse_crdr_post(string $raw): string
    {
        return strtolower(auragold_account_ledger_excel_parse_crdr($raw)) === 'cr' ? 'credit' : 'debit';
    }
}

if (!function_exists('auragold_account_ledger_excel_resolve_branch_id')) {
    function auragold_account_ledger_excel_resolve_branch_id($conn, string $branchLabel, int $defaultBranchId = 0): int
    {
        $branchLabel = trim($branchLabel);
        if ($branchLabel === '') {
            return $defaultBranchId;
        }
        $esc = mysqli_real_escape_string($conn, $branchLabel);
        $row = getRecord("SELECT id FROM tbl_branches WHERE status = 1 AND TRIM(name) = '{$esc}' LIMIT 1");
        if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
            return (int) $row['id'];
        }

        return $defaultBranchId;
    }
}

if (!function_exists('auragold_account_ledger_excel_resolve_sundry_id')) {
    function auragold_account_ledger_excel_resolve_sundry_id(string $groupName, string $ledgerName, string $sundryName = ''): int
    {
        $sundryName = trim($sundryName);
        if ($sundryName !== '') {
            $byName = auragold_sundry_debtors_id_by_name($sundryName);
            if ($byName > 0) {
                return $byName;
            }
        }

        if (function_exists('auragold_ledger_customer_sundry_id')) {
            $mapped = auragold_ledger_customer_sundry_id($ledgerName);
            if ($mapped !== null) {
                return (int) $mapped;
            }
        }

        $byGroup = auragold_sundry_debtors_id_by_name($groupName);
        if ($byGroup > 0) {
            return $byGroup;
        }

        static $groupToSundry = [
            'cash-in hand' => 28,
            'bank accounts' => 29,
            'sales' => 11,
            'purchase' => 12,
            'primary' => 1,
            'loans & advances(asset)' => 26,
            'indirect expenses' => 16,
            'service account' => 30,
            'current liabilities' => 4,
            'current assets' => 7,
            'indirect income' => 15,
        ];
        $g = strtolower(trim($groupName));

        return $groupToSundry[$g] ?? 1;
    }
}

if (!function_exists('auragold_account_ledger_excel_metal_post_from_row')) {
    /**
     * @return array<int|string,array<string,mixed>>
     */
    function auragold_account_ledger_excel_metal_post_from_row(array $row, array $metals_list): array
    {
        $by_key = [];
        foreach ($metals_list as $metal) {
            $mid = (int) ($metal['id'] ?? 0);
            $key = (string) ($metal['ledger_key'] ?? 'gold');
            if ($mid <= 0 || isset($by_key[$key])) {
                continue;
            }
            $by_key[$key] = $mid;
        }

        $posted = [];
        foreach (auragold_account_ledger_excel_metal_keys() as $key) {
            if (!isset($by_key[$key])) {
                continue;
            }
            $wt = abs((float) ($row['metal_' . $key . '_weight'] ?? 0));
            $type = auragold_account_ledger_excel_parse_crdr_post((string) ($row['metal_' . $key . '_crdr'] ?? 'credit'));
            if ($wt <= 0.000001) {
                continue;
            }
            $posted[$by_key[$key]] = [
                'weight' => $wt,
                'type'   => $type,
            ];
        }

        return $posted;
    }
}

if (!function_exists('auragold_account_ledger_import_row')) {
    /**
     * @return array{ok:bool,action:string,message:string,ledger:string}
     */
    function auragold_account_ledger_import_row($conn, array $row, int $defaultBranchId, int $defaultCustomerTypeId, int $userId): array
    {
        require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        require_once __DIR__ . '/auragold_ensure_ledger_customer.php';
        require_once __DIR__ . '/account_ledger_fixed.php';

        auragold_ensure_customer_ledger_branch_column($conn);
        auragold_ensure_customer_is_fixed_column($conn);
        auragold_ledger_opening_ensure_metal_columns($conn);

        $name = trim((string) ($row['ledger'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'action' => 'skip', 'message' => 'Empty ledger name', 'ledger' => ''];
        }

        $contact = trim((string) ($row['contact'] ?? ''));
        $groupName = trim((string) ($row['group'] ?? ''));
        $sundryLabel = trim((string) ($row['sundry_debtors'] ?? ''));
        $openingBalance = abs((float) ($row['opening_balance'] ?? 0));
        $crdr = auragold_account_ledger_excel_parse_crdr((string) ($row['crdr'] ?? 'Dr'));
        $branchId = auragold_account_ledger_excel_resolve_branch_id(
            $conn,
            (string) ($row['branch_name'] ?? ''),
            $defaultBranchId
        );
        $markFixed = !empty($row['is_fixed']) || auragold_account_ledger_is_fixed_name($name);
        $sundryId = auragold_account_ledger_excel_resolve_sundry_id($groupName, $name, $sundryLabel);

        $opening_metals_master = auragold_ledger_opening_metals_list($conn, $branchId > 0 ? $branchId : $defaultBranchId);
        $opening_metals_post = auragold_account_ledger_excel_metal_post_from_row($row, $opening_metals_master);
        $opening_metal_values = auragold_ledger_opening_metals_from_post($opening_metals_post, $opening_metals_master);
        $opening_metal_update_sql = auragold_ledger_opening_metal_update_sql($conn, $opening_metal_values);
        list($opening_metal_insert_cols, $opening_metal_insert_vals) = auragold_ledger_opening_metal_insert_cols($conn, $opening_metal_values);

        $nameEsc = esc($name);
        $existing = getRecord(
            "SELECT id, mobile_no, COALESCE(is_fixed, 0) AS is_fixed, COALESCE(sundry_debtors_id, 0) AS sundry_debtors_id
             FROM tbl_customers
             WHERE TRIM(name) = '" . mysqli_real_escape_string($conn, $name) . "' AND status = 1 LIMIT 1"
        );
        $customerId = is_array($existing) ? (int) ($existing['id'] ?? 0) : 0;
        $action = 'updated';

        if ($customerId <= 0) {
            $mobile = $contact !== '' ? esc($contact) : esc('0000000000');
            $ctype = $defaultCustomerTypeId > 0 ? $defaultCustomerTypeId : 1;
            $hasBranchCol = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customers', 'branch_id');
            $branchSql = '';
            $branchVal = '';
            if ($hasBranchCol && $branchId > 0) {
                $branchSql = ', branch_id';
                $branchVal = ', ' . (int) $branchId;
            }
            $fixedSql = ', is_fixed';
            $fixedVal = ', ' . ($markFixed ? '1' : '0');
            $sql = "INSERT INTO tbl_customers (name, mobile_no, customer_type_id, sundry_debtors_id, status, created_at{$branchSql}{$fixedSql})
                    VALUES ('{$nameEsc}', '{$mobile}', {$ctype}, {$sundryId}, 1, NOW(){$branchVal}{$fixedVal})";
            if (!@mysqli_query($conn, $sql)) {
                return ['ok' => false, 'action' => 'error', 'message' => mysqli_error($conn) ?: 'Insert failed', 'ledger' => $name];
            }
            $customerId = (int) mysqli_insert_id($conn);
            $action = 'created';
        } else {
            if ($contact !== '' && trim((string) ($existing['mobile_no'] ?? '')) === '') {
                @mysqli_query($conn, "UPDATE tbl_customers SET mobile_no = '" . esc($contact) . "' WHERE id = " . $customerId);
            }
            if ($markFixed && (int) ($existing['is_fixed'] ?? 0) !== 1) {
                @mysqli_query($conn, 'UPDATE tbl_customers SET is_fixed = 1 WHERE id = ' . (int) $customerId);
            }
            if ($sundryId > 0 && (int) ($existing['sundry_debtors_id'] ?? 0) !== $sundryId) {
                @mysqli_query($conn, 'UPDATE tbl_customers SET sundry_debtors_id = ' . (int) $sundryId . ' WHERE id = ' . (int) $customerId);
            }
        }

        $debitAmount = $openingBalance > 0 && $crdr === 'Dr' ? $openingBalance : 0;
        $creditAmount = $openingBalance > 0 && $crdr === 'Cr' ? $openingBalance : 0;
        $balanceAmount = $openingBalance > 0 ? ($crdr === 'Dr' ? $openingBalance : -$openingBalance) : 0;
        $today = date('Y-m-d');
        $obBrSql = $branchId > 0 ? (string) (int) $branchId : 'NULL';

        $openingRow = null;
        if ($branchId > 0) {
            $openingRow = getRecord(
                "SELECT id FROM tbl_customer_ledger
                 WHERE (customer_id = {$customerId} OR customer_name = '{$nameEsc}')
                   AND transaction_type = 'opening' AND status = 1
                   AND COALESCE(branch_id, 0) = " . (int) $branchId . "
                 ORDER BY id DESC LIMIT 1"
            );
        } else {
            $openingRow = getRecord(
                "SELECT id FROM tbl_customer_ledger
                 WHERE (customer_id = {$customerId} OR customer_name = '{$nameEsc}')
                   AND transaction_type = 'opening' AND status = 1
                   AND (branch_id IS NULL OR branch_id = 0)
                 ORDER BY id DESC LIMIT 1"
            );
        }

        if (is_array($openingRow) && (int) ($openingRow['id'] ?? 0) > 0) {
            $upd = "UPDATE tbl_customer_ledger SET
                    customer_id = {$customerId},
                    customer_name = '{$nameEsc}',
                    debit_amount = {$debitAmount},
                    credit_amount = {$creditAmount},
                    balance_amount = {$balanceAmount},
                    branch_id = {$obBrSql},
                    {$opening_metal_update_sql}
                    WHERE id = " . (int) $openingRow['id'];
            if (!@mysqli_query($conn, $upd)) {
                return ['ok' => false, 'action' => 'error', 'message' => mysqli_error($conn) ?: 'Opening update failed', 'ledger' => $name];
            }
        } else {
            $ins = "INSERT INTO tbl_customer_ledger
                (customer_id, customer_name, branch_id, transaction_type, transaction_id, transaction_no, transaction_date,
                 debit_amount, credit_amount, {$opening_metal_insert_cols},
                 balance_amount, description, status, created_by, created_at)
                VALUES
                ({$customerId}, '{$nameEsc}', {$obBrSql}, 'opening', 0, 'OPENING', '{$today}',
                 {$debitAmount}, {$creditAmount}, {$opening_metal_insert_vals},
                 {$balanceAmount}, 'Opening balance (import)', 1, {$userId}, NOW())";
            if (!@mysqli_query($conn, $ins)) {
                return ['ok' => false, 'action' => 'error', 'message' => mysqli_error($conn) ?: 'Opening insert failed', 'ledger' => $name];
            }
        }

        return ['ok' => true, 'action' => $action, 'message' => 'OK', 'ledger' => $name];
    }
}
