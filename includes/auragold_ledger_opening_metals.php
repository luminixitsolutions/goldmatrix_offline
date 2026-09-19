<?php
/**
 * Metal-wise ledger opening helpers (maps tbl_metal master → tbl_customer_ledger metal columns).
 */

if (!function_exists('auragold_ledger_metal_key_from_names')) {
    /**
     * @return 'gold'|'silver'|'diamond'|'platinum'
     */
    function auragold_ledger_metal_key_from_names(string $display_name, string $system_name = ''): string
    {
        $n = strtolower(trim($display_name . ' ' . $system_name));
        if (strpos($n, 'silver') !== false) {
            return 'silver';
        }
        if (strpos($n, 'diamond') !== false) {
            return 'diamond';
        }
        if (strpos($n, 'platinum') !== false) {
            return 'platinum';
        }
        if (strpos($n, 'gold') !== false) {
            return 'gold';
        }

        return 'gold';
    }
}

if (!function_exists('auragold_ledger_opening_metals_list')) {
    /**
     * Active metals from master for ledger opening UI.
     *
     * @return list<array{id:int,display_name:string,system_name:string,ledger_key:string}>
     */
    function auragold_ledger_opening_metals_list($conn, int $branch_id = 0): array
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }

        $suffix = '';
        if ($branch_id > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
            $suffix = auragold_master_list_sql_for_branch_id($conn, 'tbl_metal', $branch_id);
        }
        if ($suffix === '' && function_exists('auragold_master_list_sql_for_working_branch')) {
            $suffix = auragold_master_list_sql_for_working_branch($conn, 'tbl_metal', $branch_id);
        }
        if ($suffix === '' && function_exists('auragold_master_list_sql_suffix')) {
            $suffix = auragold_master_list_sql_suffix($conn, 'tbl_metal');
        }

        $rows = getList(
            "SELECT id, display_name, system_name
             FROM tbl_metal
             WHERE status = 1 {$suffix}
             ORDER BY id ASC"
        );
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $display = trim((string) ($row['display_name'] ?? ''));
            $system = trim((string) ($row['system_name'] ?? ''));
            if ($display === '' && $system === '') {
                continue;
            }
            if ($display === '') {
                $display = $system;
            }
            $out[] = [
                'id' => $id,
                'display_name' => $display,
                'system_name' => $system,
                'ledger_key' => auragold_ledger_metal_key_from_names($display, $system),
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_ledger_opening_ensure_metal_columns')) {
    function auragold_ledger_opening_ensure_metal_columns($conn): void
    {
        if (!($conn instanceof mysqli)) {
            return;
        }
        if (function_exists('auragold_ensure_ledger_diamond_columns')) {
            require_once __DIR__ . '/ensure_metal_amount_conversion.php';
            auragold_ensure_ledger_diamond_columns($conn);
            auragold_ensure_ledger_platinum_columns($conn);
        }
    }
}

if (!function_exists('auragold_ledger_opening_metal_signed_balance')) {
    function auragold_ledger_opening_metal_signed_balance(array $opening_record, string $ledger_key): float
    {
        $key = strtolower(trim($ledger_key));
        $balance_col = 'balance_' . $key;
        if (isset($opening_record[$balance_col]) && (float) $opening_record[$balance_col] != 0.0) {
            return (float) $opening_record[$balance_col];
        }

        $debit_col = 'debit_' . $key;
        $credit_col = 'credit_' . $key;
        $dr = (float) ($opening_record[$debit_col] ?? 0);
        $cr = (float) ($opening_record[$credit_col] ?? 0);
        if ($dr > 0 || $cr > 0) {
            return $dr - $cr;
        }

        return 0.0;
    }
}

if (!function_exists('auragold_ledger_opening_metals_for_ui')) {
    /**
     * Build per-master-metal opening values for the form (signed balance on first metal per ledger key).
     *
     * @param list<array{id:int,ledger_key:string}> $metals_list
     * @return list<array{metal_id:int,weight:float,type:string}>
     */
    function auragold_ledger_opening_metals_for_ui(array $opening_record, array $metals_list): array
    {
        $assigned = [];
        $out = [];

        foreach ($metals_list as $metal) {
            $mid = (int) ($metal['id'] ?? 0);
            $ledger_key = (string) ($metal['ledger_key'] ?? 'gold');
            if ($mid <= 0) {
                continue;
            }

            $weight = 0.0;
            $type = 'Credit';
            if (!isset($assigned[$ledger_key])) {
                $signed = auragold_ledger_opening_metal_signed_balance($opening_record, $ledger_key);
                if (abs($signed) > 0.000001) {
                    $weight = abs($signed);
                    $type = $signed >= 0 ? 'Debit' : 'Credit';
                }
                $assigned[$ledger_key] = true;
            }

            $out[] = [
                'metal_id' => $mid,
                'weight' => $weight,
                'type' => $type,
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_ledger_opening_metals_from_post')) {
    /**
     * Aggregate POSTed metal openings into ledger column values.
     *
     * @param array<int|string,array<string,mixed>> $posted
     * @param list<array{id:int,ledger_key:string}> $metals_list
     * @return array<string,array{debit:float,credit:float,balance:float}>
     */
    function auragold_ledger_opening_metals_from_post(array $posted, array $metals_list): array
    {
        $by_id = [];
        foreach ($metals_list as $metal) {
            $by_id[(int) ($metal['id'] ?? 0)] = (string) ($metal['ledger_key'] ?? 'gold');
        }

        $signed = [
            'gold' => 0.0,
            'silver' => 0.0,
            'diamond' => 0.0,
            'platinum' => 0.0,
        ];

        foreach ($posted as $metal_id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $mid = (int) $metal_id;
            if ($mid <= 0 || !isset($by_id[$mid])) {
                continue;
            }
            $wt = abs((float) ($row['weight'] ?? 0));
            if ($wt <= 0.000001) {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? 'credit')));
            $signed[$by_id[$mid]] += ($type === 'debit') ? $wt : -$wt;
        }

        $out = [];
        foreach ($signed as $key => $val) {
            if ($val >= 0) {
                $out[$key] = ['debit' => $val, 'credit' => 0.0, 'balance' => $val];
            } else {
                $abs = abs($val);
                $out[$key] = ['debit' => 0.0, 'credit' => $abs, 'balance' => -$abs];
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_ledger_opening_metal_update_sql')) {
    function auragold_ledger_opening_metal_update_sql($conn, array $metal_values): string
    {
        auragold_ledger_opening_ensure_metal_columns($conn);

        $parts = [
            'debit_gold = ' . (float) ($metal_values['gold']['debit'] ?? 0),
            'credit_gold = ' . (float) ($metal_values['gold']['credit'] ?? 0),
            'balance_gold = ' . (float) ($metal_values['gold']['balance'] ?? 0),
            'debit_silver = ' . (float) ($metal_values['silver']['debit'] ?? 0),
            'credit_silver = ' . (float) ($metal_values['silver']['credit'] ?? 0),
            'balance_silver = ' . (float) ($metal_values['silver']['balance'] ?? 0),
        ];

        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_diamond')) {
            $parts[] = 'debit_diamond = ' . (float) ($metal_values['diamond']['debit'] ?? 0);
            $parts[] = 'credit_diamond = ' . (float) ($metal_values['diamond']['credit'] ?? 0);
            $parts[] = 'balance_diamond = ' . (float) ($metal_values['diamond']['balance'] ?? 0);
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_platinum')) {
            $parts[] = 'debit_platinum = ' . (float) ($metal_values['platinum']['debit'] ?? 0);
            $parts[] = 'credit_platinum = ' . (float) ($metal_values['platinum']['credit'] ?? 0);
            $parts[] = 'balance_platinum = ' . (float) ($metal_values['platinum']['balance'] ?? 0);
        }

        return implode(', ', $parts);
    }
}

if (!function_exists('auragold_ledger_opening_metal_insert_cols')) {
    function auragold_ledger_opening_metal_insert_cols($conn, array $metal_values): array
    {
        auragold_ledger_opening_ensure_metal_columns($conn);

        $cols = 'debit_gold, credit_gold, debit_silver, credit_silver, balance_gold, balance_silver';
        $vals = (float) ($metal_values['gold']['debit'] ?? 0) . ', '
            . (float) ($metal_values['gold']['credit'] ?? 0) . ', '
            . (float) ($metal_values['silver']['debit'] ?? 0) . ', '
            . (float) ($metal_values['silver']['credit'] ?? 0) . ', '
            . (float) ($metal_values['gold']['balance'] ?? 0) . ', '
            . (float) ($metal_values['silver']['balance'] ?? 0);

        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_diamond')) {
            $cols .= ', debit_diamond, credit_diamond, balance_diamond';
            $vals .= ', '
                . (float) ($metal_values['diamond']['debit'] ?? 0) . ', '
                . (float) ($metal_values['diamond']['credit'] ?? 0) . ', '
                . (float) ($metal_values['diamond']['balance'] ?? 0);
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_platinum')) {
            $cols .= ', debit_platinum, credit_platinum, balance_platinum';
            $vals .= ', '
                . (float) ($metal_values['platinum']['debit'] ?? 0) . ', '
                . (float) ($metal_values['platinum']['credit'] ?? 0) . ', '
                . (float) ($metal_values['platinum']['balance'] ?? 0);
        }

        return [$cols, $vals];
    }
}
