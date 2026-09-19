<?php

/**
 * Cheque / PDC entry list (Operations → Payments & Receipts).
 */

if (!function_exists('auragold_ensure_tbl_cheque_entry')) {
    function auragold_ensure_tbl_cheque_entry($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `tbl_cheque_entry` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `branch_id` int DEFAULT NULL COMMENT 'FK tbl_branches.id',
            `pdc_no` varchar(50) NOT NULL DEFAULT '',
            `account_no` varchar(100) NOT NULL DEFAULT '',
            `account_ledger` varchar(255) NOT NULL DEFAULT '',
            `bank_name` varchar(255) NOT NULL DEFAULT '',
            `cheque_no` varchar(100) NOT NULL DEFAULT '',
            `cheque_date` date DEFAULT NULL,
            `pay_date` date DEFAULT NULL,
            `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
            `branch_name` varchar(255) NOT NULL DEFAULT '',
            `status` varchar(50) NOT NULL DEFAULT 'Pending',
            `bounced_cleared_date` date DEFAULT NULL,
            `against_voucher_no` varchar(100) NOT NULL DEFAULT '',
            `against_voucher_type` varchar(100) NOT NULL DEFAULT '',
            `nsf_fees` decimal(14,2) NOT NULL DEFAULT 0.00,
            `recoverable` tinyint(1) NOT NULL DEFAULT 0,
            `invoice_date` date DEFAULT NULL,
            `reference_voucher_type` varchar(100) NOT NULL DEFAULT '',
            `ref_invoice_no` varchar(100) NOT NULL DEFAULT '',
            `comment` text,
            `pdc_voucher_type` varchar(100) NOT NULL DEFAULT '',
            `record_status` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_cheque_entry_branch` (`branch_id`),
            KEY `idx_cheque_entry_pdc_no` (`pdc_no`),
            KEY `idx_cheque_entry_status` (`status`),
            KEY `idx_cheque_entry_record_status` (`record_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) @mysqli_query($conn, $sql);
    }
}

if (!function_exists('auragold_cheque_entry_resolve_branch_id')) {
    function auragold_cheque_entry_resolve_branch_id(?int $explicit = null): int
    {
        if ($explicit !== null && $explicit > 0) {
            if (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($explicit)) {
                return $explicit;
            }
        }
        return function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
    }
}

if (!function_exists('auragold_cheque_entry_format_date')) {
    function auragold_cheque_entry_format_date($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : $value;
    }
}

if (!function_exists('auragold_cheque_entry_row_from_db')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function auragold_cheque_entry_row_from_db(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'branch_id' => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            'pdc_no' => trim((string) ($row['pdc_no'] ?? '')),
            'account_no' => trim((string) ($row['account_no'] ?? '')),
            'account_ledger' => trim((string) ($row['account_ledger'] ?? '')),
            'bank_name' => trim((string) ($row['bank_name'] ?? '')),
            'cheque_no' => trim((string) ($row['cheque_no'] ?? '')),
            'cheque_date' => (string) ($row['cheque_date'] ?? ''),
            'cheque_date_fmt' => auragold_cheque_entry_format_date($row['cheque_date'] ?? ''),
            'pay_date' => (string) ($row['pay_date'] ?? ''),
            'pay_date_fmt' => auragold_cheque_entry_format_date($row['pay_date'] ?? ''),
            'amount' => (float) ($row['amount'] ?? 0),
            'branch_name' => trim((string) ($row['branch_name'] ?? '')),
            'status' => trim((string) ($row['status'] ?? 'Pending')),
            'bounced_cleared_date' => (string) ($row['bounced_cleared_date'] ?? ''),
            'bounced_cleared_date_fmt' => auragold_cheque_entry_format_date($row['bounced_cleared_date'] ?? ''),
            'against_voucher_no' => trim((string) ($row['against_voucher_no'] ?? '')),
            'against_voucher_type' => trim((string) ($row['against_voucher_type'] ?? '')),
            'nsf_fees' => (float) ($row['nsf_fees'] ?? 0),
            'recoverable' => (int) ($row['recoverable'] ?? 0) === 1 ? 1 : 0,
            'invoice_date' => (string) ($row['invoice_date'] ?? ''),
            'invoice_date_fmt' => auragold_cheque_entry_format_date($row['invoice_date'] ?? ''),
            'reference_voucher_type' => trim((string) ($row['reference_voucher_type'] ?? '')),
            'ref_invoice_no' => trim((string) ($row['ref_invoice_no'] ?? '')),
            'comment' => trim((string) ($row['comment'] ?? '')),
            'pdc_voucher_type' => trim((string) ($row['pdc_voucher_type'] ?? '')),
        ];
    }
}

if (!function_exists('auragold_cheque_entry_pdc_direction_from_type')) {
    function auragold_cheque_entry_pdc_direction_from_type($pdc_voucher_type): string
    {
        $t = strtolower(trim((string) $pdc_voucher_type));
        if (strpos($t, 'payable') !== false) {
            return 'payable';
        }

        return 'receivable';
    }
}

if (!function_exists('auragold_cheque_entry_next_pdc_no')) {
    function auragold_cheque_entry_next_pdc_no($conn, int $branch_id = 0, string $direction = 'receivable'): string
    {
        if (!$conn instanceof mysqli) {
            return 'PDC-1';
        }
        $direction = strtolower(trim($direction)) === 'payable' ? 'payable' : 'receivable';
        if (function_exists('getNextPdcEntryNo')) {
            $pdc = getNextPdcEntryNo($conn, $direction);
            if (function_exists('getPdcEntryBillSeriesConfig') && function_exists('bumpPdcEntryNo')) {
                $cfg = getPdcEntryBillSeriesConfig($conn, $direction);
                $guard = 0;
                while ($guard < 5000) {
                    $esc = mysqli_real_escape_string($conn, $pdc);
                    $exists = getRecord(
                        'SELECT id FROM `tbl_cheque_entry` WHERE record_status = 1 AND pdc_no = \''
                        . $esc . '\' LIMIT 1'
                    );
                    if (!$exists) {
                        break;
                    }
                    $pdc = bumpPdcEntryNo($conn, $pdc, $cfg);
                    $guard++;
                }
            }
            return $pdc;
        }

        auragold_ensure_tbl_cheque_entry($conn);

        $where = 'record_status = 1';
        if (auragold_tbl_has_column($conn, 'tbl_cheque_entry', 'branch_id') && $branch_id > 0) {
            $where .= ' AND (branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }

        $row = getRecord('SELECT pdc_no FROM `tbl_cheque_entry` WHERE ' . $where . ' ORDER BY id DESC LIMIT 1');
        $next = 1;
        if ($row && !empty($row['pdc_no']) && preg_match('/(\d+)\s*$/', (string) $row['pdc_no'], $m)) {
            $next = (int) $m[1] + 1;
        }

        return 'PDC-' . $next;
    }
}

if (!function_exists('auragold_cheque_entry_parse_date_filter')) {
    function auragold_cheque_entry_parse_date_filter($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}

if (!function_exists('auragold_cheque_entry_filter_options')) {
    /**
     * @return array{banks:array<int,string>,ledgers:array<int,string>,branches:array<int,array{id:int,name:string}>}
     */
    function auragold_cheque_entry_filter_options($conn, int $branch_id = 0): array
    {
        $out = ['banks' => [], 'ledgers' => [], 'branches' => []];
        if (!$conn instanceof mysqli) {
            return $out;
        }

        if (function_exists('getListMaster')) {
            $brRows = getListMaster('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
            if (is_array($brRows)) {
                foreach ($brRows as $br) {
                    $name = trim((string) ($br['name'] ?? ''));
                    if ($name !== '') {
                        $out['branches'][] = ['id' => (int) ($br['id'] ?? 0), 'name' => $name];
                    }
                }
            }
        }

        auragold_ensure_tbl_cheque_entry($conn);
        $where = 'record_status = 1';
        if (auragold_tbl_has_column($conn, 'tbl_cheque_entry', 'branch_id') && $branch_id > 0) {
            $where .= ' AND (branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }

        $bankRows = getList(
            'SELECT DISTINCT TRIM(bank_name) AS v FROM `tbl_cheque_entry` WHERE ' . $where
            . " AND TRIM(IFNULL(bank_name,'')) != '' ORDER BY v ASC"
        );
        if (is_array($bankRows)) {
            foreach ($bankRows as $r) {
                $v = trim((string) ($r['v'] ?? ''));
                if ($v !== '') {
                    $out['banks'][] = $v;
                }
            }
        }

        $ledgerRows = getList(
            'SELECT DISTINCT TRIM(account_ledger) AS v FROM `tbl_cheque_entry` WHERE ' . $where
            . " AND TRIM(IFNULL(account_ledger,'')) != '' ORDER BY v ASC"
        );
        if (is_array($ledgerRows)) {
            foreach ($ledgerRows as $r) {
                $v = trim((string) ($r['v'] ?? ''));
                if ($v !== '') {
                    $out['ledgers'][] = $v;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_get_cheque_entries')) {
    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    function auragold_get_cheque_entries($conn, int $branch_id = 0, string $search = '', int $limit = 500, int $offset = 0, array $filters = []): array
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        auragold_ensure_tbl_cheque_entry($conn);

        $where = ['record_status = 1'];
        if (auragold_tbl_has_column($conn, 'tbl_cheque_entry', 'branch_id') && $branch_id > 0) {
            $where[] = '(branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }

        $search = trim($search);
        if ($search !== '') {
            $q = mysqli_real_escape_string($conn, $search);
            $where[] = "(
                pdc_no LIKE '%$q%' OR account_no LIKE '%$q%' OR account_ledger LIKE '%$q%'
                OR bank_name LIKE '%$q%' OR cheque_no LIKE '%$q%' OR branch_name LIKE '%$q%'
                OR status LIKE '%$q%' OR against_voucher_no LIKE '%$q%' OR against_voucher_type LIKE '%$q%'
                OR reference_voucher_type LIKE '%$q%' OR ref_invoice_no LIKE '%$q%'
                OR pdc_voucher_type LIKE '%$q%' OR comment LIKE '%$q%'
            )";
        }

        $chequeFrom = auragold_cheque_entry_parse_date_filter($filters['cheque_date_from'] ?? '');
        $chequeTo = auragold_cheque_entry_parse_date_filter($filters['cheque_date_to'] ?? '');
        if ($chequeFrom !== '') {
            $where[] = "cheque_date >= '" . mysqli_real_escape_string($conn, $chequeFrom) . "'";
        }
        if ($chequeTo !== '') {
            $where[] = "cheque_date <= '" . mysqli_real_escape_string($conn, $chequeTo) . "'";
        }

        $payFrom = auragold_cheque_entry_parse_date_filter($filters['pay_date_from'] ?? '');
        $payTo = auragold_cheque_entry_parse_date_filter($filters['pay_date_to'] ?? '');
        if ($payFrom !== '') {
            $where[] = "pay_date >= '" . mysqli_real_escape_string($conn, $payFrom) . "'";
        }
        if ($payTo !== '') {
            $where[] = "pay_date <= '" . mysqli_real_escape_string($conn, $payTo) . "'";
        }

        $branchName = trim((string) ($filters['branch_name'] ?? ''));
        if ($branchName !== '') {
            $where[] = "branch_name = '" . mysqli_real_escape_string($conn, $branchName) . "'";
        }

        $ledger = trim((string) ($filters['account_ledger'] ?? ''));
        if ($ledger !== '') {
            $where[] = "account_ledger = '" . mysqli_real_escape_string($conn, $ledger) . "'";
        }

        $pdcType = trim((string) ($filters['pdc_voucher_type'] ?? ''));
        if ($pdcType !== '') {
            $where[] = "pdc_voucher_type = '" . mysqli_real_escape_string($conn, $pdcType) . "'";
        }

        $bankName = trim((string) ($filters['bank_name'] ?? ''));
        if ($bankName !== '') {
            $where[] = "bank_name = '" . mysqli_real_escape_string($conn, $bankName) . "'";
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = "status = '" . mysqli_real_escape_string($conn, $status) . "'";
        }

        $invoiceNo = trim((string) ($filters['ref_invoice_no'] ?? ''));
        if ($invoiceNo !== '') {
            $esc = mysqli_real_escape_string($conn, $invoiceNo);
            $where[] = "ref_invoice_no LIKE '%$esc%'";
        }

        $chequeNo = trim((string) ($filters['cheque_no'] ?? ''));
        if ($chequeNo !== '') {
            $esc = mysqli_real_escape_string($conn, $chequeNo);
            $where[] = "cheque_no LIKE '%$esc%'";
        }

        $accountNo = trim((string) ($filters['account_no'] ?? ''));
        if ($accountNo !== '') {
            $esc = mysqli_real_escape_string($conn, $accountNo);
            $where[] = "account_no LIKE '%$esc%'";
        }

        $limit = max(1, min(2000, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT * FROM `tbl_cheque_entry` WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . (int) $offset . ', ' . (int) $limit;
        $rows = getList($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = auragold_cheque_entry_row_from_db($row);
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_get_cheque_entry_by_id')) {
    function auragold_get_cheque_entry_by_id($conn, int $id, int $branch_id = 0): ?array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return null;
        }
        auragold_ensure_tbl_cheque_entry($conn);

        $row = getRecord('SELECT * FROM `tbl_cheque_entry` WHERE id = ' . (int) $id . ' AND record_status = 1 LIMIT 1');
        if (!$row || !is_array($row)) {
            return null;
        }
        if (auragold_tbl_has_column($conn, 'tbl_cheque_entry', 'branch_id') && $branch_id > 0) {
            $bid = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;
            if ($bid > 0 && $bid !== $branch_id) {
                return null;
            }
        }

        return auragold_cheque_entry_row_from_db($row);
    }
}

if (!function_exists('auragold_cheque_entry_nullable_date_sql')) {
    function auragold_cheque_entry_nullable_date_sql($conn, $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'NULL';
        }
        $ts = strtotime($value);
        if (!$ts) {
            return 'NULL';
        }

        return "'" . mysqli_real_escape_string($conn, date('Y-m-d', $ts)) . "'";
    }
}

if (!function_exists('auragold_save_cheque_entry')) {
    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message:string,id?:int,entry?:array<string,mixed>}
     */
    function auragold_save_cheque_entry($conn, int $branch_id, array $data): array
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'message' => 'Database unavailable.'];
        }
        auragold_ensure_tbl_cheque_entry($conn);

        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $limited_update = $id > 0 && !empty($data['limited_update']);

        if ($limited_update) {
            $existing = auragold_get_cheque_entry_by_id($conn, $id, 0);
            if (!$existing) {
                return ['ok' => false, 'message' => 'Cheque entry not found.'];
            }
            $data = array_merge($existing, [
                'id' => $id,
                'status' => array_key_exists('status', $data) ? (string) $data['status'] : ($existing['status'] ?? ''),
                'bounced_cleared_date' => array_key_exists('bounced_cleared_date', $data)
                    ? $data['bounced_cleared_date']
                    : ($existing['bounced_cleared_date'] ?? ''),
                'nsf_fees' => array_key_exists('nsf_fees', $data) ? $data['nsf_fees'] : ($existing['nsf_fees'] ?? 0),
                'recoverable' => array_key_exists('recoverable', $data) ? $data['recoverable'] : ($existing['recoverable'] ?? 0),
            ]);
        }

        $pdc_no = trim((string) ($data['pdc_no'] ?? ''));
        if ($pdc_no === '') {
            $pdc_direction = auragold_cheque_entry_pdc_direction_from_type($data['pdc_voucher_type'] ?? '');
            $pdc_no = auragold_cheque_entry_next_pdc_no($conn, $branch_id, $pdc_direction);
        }

        $account_ledger = trim((string) ($data['account_ledger'] ?? ''));
        if ($account_ledger === '') {
            return ['ok' => false, 'message' => 'Account Ledger is required.'];
        }

        if ($id > 0 && function_exists('auragold_master_can_mutate_row')
            && !auragold_master_can_mutate_row($conn, 'tbl_cheque_entry', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        $branch_name = trim((string) ($data['branch_name'] ?? ''));
        if ($branch_name === '' && $branch_id > 0) {
            $br = getRecord('SELECT name FROM tbl_branches WHERE id = ' . (int) $branch_id . ' LIMIT 1');
            if ($br && !empty($br['name'])) {
                $branch_name = trim((string) $br['name']);
            }
        }

        $fields = [
            'pdc_no' => $pdc_no,
            'account_no' => trim((string) ($data['account_no'] ?? '')),
            'account_ledger' => $account_ledger,
            'bank_name' => trim((string) ($data['bank_name'] ?? '')),
            'cheque_no' => trim((string) ($data['cheque_no'] ?? '')),
            'branch_name' => $branch_name,
            'status' => trim((string) ($data['status'] ?? '')),
            'against_voucher_no' => trim((string) ($data['against_voucher_no'] ?? '')),
            'against_voucher_type' => trim((string) ($data['against_voucher_type'] ?? '')),
            'reference_voucher_type' => trim((string) ($data['reference_voucher_type'] ?? '')),
            'ref_invoice_no' => trim((string) ($data['ref_invoice_no'] ?? '')),
            'comment' => trim((string) ($data['comment'] ?? '')),
            'pdc_voucher_type' => trim((string) ($data['pdc_voucher_type'] ?? '')),
        ];

        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
        $nsf_fees = isset($data['nsf_fees']) ? (float) $data['nsf_fees'] : 0.0;
        $recoverable = !empty($data['recoverable']) ? 1 : 0;

        // When marking Cleared without a cleared date, default to today so ledger date is set.
        $status_for_date = trim((string) ($fields['status'] ?? ''));
        $bcd_for_date = trim((string) ($data['bounced_cleared_date'] ?? ''));
        if (strcasecmp($status_for_date, 'Cleared') === 0 && ($bcd_for_date === '' || $bcd_for_date === '0000-00-00')) {
            $data['bounced_cleared_date'] = date('Y-m-d');
        }

        $esc = static function ($v) use ($conn) {
            return mysqli_real_escape_string($conn, (string) $v);
        };

        $set = [];
        foreach ($fields as $col => $val) {
            $set[] = "`$col` = '" . $esc($val) . "'";
        }
        $set[] = 'amount = ' . number_format($amount, 2, '.', '');
        $set[] = 'nsf_fees = ' . number_format($nsf_fees, 2, '.', '');
        $set[] = 'recoverable = ' . (int) $recoverable;
        $set[] = 'cheque_date = ' . auragold_cheque_entry_nullable_date_sql($conn, $data['cheque_date'] ?? '');
        $set[] = 'pay_date = ' . auragold_cheque_entry_nullable_date_sql($conn, $data['pay_date'] ?? '');
        $set[] = 'bounced_cleared_date = ' . auragold_cheque_entry_nullable_date_sql($conn, $data['bounced_cleared_date'] ?? '');
        $set[] = 'invoice_date = ' . auragold_cheque_entry_nullable_date_sql($conn, $data['invoice_date'] ?? '');
        $set[] = 'updated_at = NOW()';

        if ($id > 0) {
            $sql = 'UPDATE `tbl_cheque_entry` SET ' . implode(', ', $set) . ' WHERE id = ' . (int) $id;
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn)];
            }
            $saved_id = $id;
        } else {
            $branch_sql = '';
            $branch_val = '';
            if (auragold_tbl_has_column($conn, 'tbl_cheque_entry', 'branch_id')) {
                $bid = function_exists('auragold_master_branch_id_for_writes')
                    ? auragold_master_branch_id_for_writes($conn, 'tbl_cheque_entry')
                    : ($branch_id > 0 ? $branch_id : 0);
                $branch_sql = ', branch_id';
                $branch_val = ', ' . (int) $bid;
            }

            $insert_cols = array_keys($fields);
            $insert_vals = array_map(static function ($v) use ($esc) {
                return "'" . $esc($v) . "'";
            }, array_values($fields));

            $sql = 'INSERT INTO `tbl_cheque_entry` (`' . implode('`, `', $insert_cols)
                . '`, amount, nsf_fees, recoverable, cheque_date, pay_date, bounced_cleared_date, invoice_date, record_status, created_at'
                . $branch_sql . ') VALUES (' . implode(', ', $insert_vals)
                . ', ' . number_format($amount, 2, '.', '')
                . ', ' . number_format($nsf_fees, 2, '.', '')
                . ', ' . (int) $recoverable
                . ', ' . auragold_cheque_entry_nullable_date_sql($conn, $data['cheque_date'] ?? '')
                . ', ' . auragold_cheque_entry_nullable_date_sql($conn, $data['pay_date'] ?? '')
                . ', ' . auragold_cheque_entry_nullable_date_sql($conn, $data['bounced_cleared_date'] ?? '')
                . ', ' . auragold_cheque_entry_nullable_date_sql($conn, $data['invoice_date'] ?? '')
                . ', 1, NOW()' . $branch_val . ')';

            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn)];
            }
            $saved_id = (int) mysqli_insert_id($conn);
        }

        $entry = auragold_get_cheque_entry_by_id($conn, $saved_id, 0);
        $new_status = trim((string) ($fields['status'] ?? ''));

        if ($saved_id > 0) {
            require_once __DIR__ . '/auragold_cheque_entry_clearance_sync.php';
            $user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
            if ($user_id <= 0 && !empty($_SESSION['Admin']['id'])) {
                $user_id = (int) $_SESSION['Admin']['id'];
            } elseif ($user_id <= 0 && !empty($_SESSION['user_id'])) {
                $user_id = (int) $_SESSION['user_id'];
            }
            // Prefer document branch on the cheque row for ledger posting / Account Ledger filter.
            $clearance_branch = $branch_id;
            if (is_array($entry) && (int) ($entry['branch_id'] ?? 0) > 0) {
                $clearance_branch = (int) $entry['branch_id'];
            }
            $clearance = auragold_sync_cheque_entry_clearance($conn, $clearance_branch, $saved_id, $user_id);
            if (strcasecmp($new_status, 'Cleared') === 0 && empty($clearance['ok'])) {
                return [
                    'ok' => false,
                    'message' => (string) ($clearance['message'] ?? 'PDC clearance ledger posting failed.'),
                ];
            }
            $entry = auragold_get_cheque_entry_by_id($conn, $saved_id, 0);
            $ok_message = $id > 0 ? 'Cheque entry updated.' : 'Cheque entry saved.';
            if (strcasecmp($new_status, 'Cleared') === 0 && !empty($clearance['ok']) && !empty($clearance['message'])) {
                $ok_message = (string) $clearance['message'];
            }

            return [
                'ok' => true,
                'message' => $ok_message,
                'id' => $saved_id,
                'entry' => $entry,
            ];
        }

        return [
            'ok' => true,
            'message' => $id > 0 ? 'Cheque entry updated.' : 'Cheque entry saved.',
            'id' => $saved_id,
            'entry' => $entry,
        ];
    }
}

if (!function_exists('auragold_delete_cheque_entry')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_delete_cheque_entry($conn, int $id, int $branch_id = 0): array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid cheque entry.'];
        }
        auragold_ensure_tbl_cheque_entry($conn);

        if (function_exists('auragold_master_can_mutate_row')
            && !auragold_master_can_mutate_row($conn, 'tbl_cheque_entry', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        @mysqli_query($conn, 'UPDATE `tbl_cheque_entry` SET record_status = 0, updated_at = NOW() WHERE id = ' . (int) $id);

        require_once __DIR__ . '/auragold_cheque_entry_clearance_sync.php';
        auragold_remove_cheque_entry_clearance_ledger($conn, $id);

        return ['ok' => true, 'message' => 'Cheque entry deleted.'];
    }
}
