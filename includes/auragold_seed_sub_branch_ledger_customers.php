<?php
/**
 * After a dedicated sub-branch database is provisioned: copy party masters and ledger names
 * from the parent main branch with zero opening balances for the new branch.
 */
require_once __DIR__ . '/branch_credentials.php';
require_once __DIR__ . '/branch_create_db_after_save.php';
require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';

if (!function_exists('auragold_registry_database_name')) {
    function auragold_registry_database_name(): string {
        if (defined('AURAGOLD_REGISTRY_DB')) {
            return trim((string) AURAGOLD_REGISTRY_DB);
        }
        return '';
    }
}

if (!function_exists('auragold_schema_table_exists_on_link')) {
    function auragold_schema_table_exists_on_link(mysqli $link, string $schema, string $table): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $schema) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        $s = mysqli_real_escape_string($link, $schema);
        $t = mysqli_real_escape_string($link, $table);
        $r = mysqli_query(
            $link,
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$s' AND TABLE_NAME = '$t' AND TABLE_TYPE = 'BASE TABLE' LIMIT 1"
        );
        return $r && mysqli_num_rows($r) > 0;
    }
}

if (!function_exists('auragold_resolve_main_branch_source_database')) {
    /**
     * Operational DB for a main tbl_branches row: explicit db_name, else registry.
     *
     * @return array{ok:bool,db_name:string,db_user:string,db_pass:string,message:string}
     */
    function auragold_resolve_main_branch_source_database(mysqli $conn_master, int $mainBranchId): array {
        $mainBranchId = (int) $mainBranchId;
        if ($mainBranchId <= 0) {
            return ['ok' => false, 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'message' => 'Invalid main branch id.'];
        }
        $eid = mysqli_real_escape_string($conn_master, (string) $mainBranchId);
        $r   = mysqli_query(
            $conn_master,
            'SELECT * FROM tbl_branches WHERE id = ' . $eid . ' AND IFNULL(main_branch_id, 0) = 0 LIMIT 1'
        );
        if (!$r || !($row = mysqli_fetch_assoc($r))) {
            if ($r) {
                mysqli_free_result($r);
            }
            return ['ok' => false, 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'message' => 'Main branch row not found.'];
        }
        mysqli_free_result($r);
        $cr      = auragold_branch_row_db_credentials($row);
        $reg     = auragold_registry_database_name();
        $srcDb   = trim((string) ($cr['db_name'] ?? ''));
        if ($srcDb === '') {
            $srcDb = $reg !== '' ? $reg : (defined('DB_NAME') ? trim((string) DB_NAME) : '');
        }
        if ($srcDb === '') {
            return ['ok' => false, 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'message' => 'Could not resolve main branch database name.'];
        }
        return [
            'ok'      => true,
            'db_name' => $srcDb,
            'db_user' => trim((string) ($cr['db_user'] ?? '')),
            'db_pass' => (string) ($cr['db_pass'] ?? ''),
            'message' => '',
        ];
    }
}

if (!function_exists('auragold_tbl_columns_set')) {
    /**
     * @return array<string,bool>
     */
    function auragold_tbl_columns_set(mysqli $link, string $table): array {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        if ($table === '') {
            return [];
        }
        $out = [];
        $r   = @mysqli_query($link, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $f = isset($row['Field']) ? strtolower((string) $row['Field']) : '';
                if ($f !== '') {
                    $out[$f] = true;
                }
            }
            mysqli_free_result($r);
        }
        return $out;
    }
}

if (!function_exists('auragold_seed_sub_branch_ledgers_and_parties_from_main')) {
    /**
     * Copy tbl_customer_types + tbl_customers from main branch DB, zero tbl_customer_balance,
     * and insert one opening row per distinct ledger name in tbl_customer_ledger for $newSubBranchId (balance 0).
     *
     * @return array{ok:bool,message:string}
     */
    function auragold_seed_sub_branch_ledgers_and_parties_from_main(
        mysqli $conn_master,
        int $mainBranchId,
        int $newSubBranchId,
        string $targetDbName,
        string $targetDbUser,
        string $targetDbPass
    ): array {
        $mainBranchId    = (int) $mainBranchId;
        $newSubBranchId  = (int) $newSubBranchId;
        $targetDbName    = trim($targetDbName);
        $resolved        = auragold_resolve_main_branch_source_database($conn_master, $mainBranchId);
        if (empty($resolved['ok'])) {
            return ['ok' => false, 'message' => $resolved['message'] !== '' ? $resolved['message'] : 'Could not resolve main branch database.'];
        }
        $sourceDb = $resolved['db_name'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $targetDbName) || !preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
            return ['ok' => false, 'message' => 'Invalid database name.'];
        }
        if (strcasecmp($sourceDb, $targetDbName) === 0) {
            return ['ok' => false, 'message' => 'Source and target database are the same; skip ledger copy.'];
        }
        if ($newSubBranchId <= 0) {
            return ['ok' => false, 'message' => 'Invalid new sub-branch id.'];
        }

        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $srcConn = auragold_mysqli_connect_branch_or_registry($host, $sourceDb, $resolved['db_user'], $resolved['db_pass']);
        if (!$srcConn) {
            return ['ok' => false, 'message' => 'Could not connect to main branch database `' . $sourceDb . '` for ledger copy.'];
        }
        mysqli_set_charset($srcConn, 'utf8mb4');

        $tgtConn = auragold_mysqli_connect_branch_or_registry($host, $targetDbName, $targetDbUser, $targetDbPass);
        if (!$tgtConn) {
            mysqli_close($srcConn);
            return ['ok' => false, 'message' => 'Could not connect to new sub-branch database for ledger copy.'];
        }
        mysqli_set_charset($tgtConn, 'utf8mb4');

        auragold_ensure_customer_ledger_branch_column($tgtConn);

        $sq = '`' . str_replace('`', '``', $sourceDb) . '`';
        $tq = '`' . str_replace('`', '``', $targetDbName) . '`';

        $need = ['tbl_customer_types', 'tbl_customers', 'tbl_customer_ledger'];
        foreach ($need as $t) {
            if (!auragold_schema_table_exists_on_link($srcConn, $sourceDb, $t)
                || !auragold_schema_table_exists_on_link($tgtConn, $targetDbName, $t)) {
                mysqli_close($srcConn);
                mysqli_close($tgtConn);
                return ['ok' => false, 'message' => 'Required table `' . $t . '` missing on source or target.'];
            }
        }

        mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=0');

        mysqli_query($tgtConn, 'DELETE FROM ' . $tq . '.`tbl_customer_ledger`');
        mysqli_query($tgtConn, 'DELETE FROM ' . $tq . '.`tbl_customers`');
        if (auragold_schema_table_exists_on_link($tgtConn, $targetDbName, 'tbl_customer_balance')) {
            mysqli_query($tgtConn, 'DELETE FROM ' . $tq . '.`tbl_customer_balance`');
        }
        mysqli_query($tgtConn, 'DELETE FROM ' . $tq . '.`tbl_customer_types`');

        // cPanel per-DB users cannot use INSERT…SELECT across schemas in one query on the target
        // link (it requires SELECT on the other DB with that same user). Read on $srcConn, write on $tgtConn.
        $backtickCol = static function (string $c): string {
            return '`' . str_replace('`', '``', $c) . '`';
        };

        $rTypes = mysqli_query($srcConn, 'SELECT * FROM ' . $sq . '.`tbl_customer_types`');
        if (!$rTypes) {
            $err = mysqli_error($srcConn);
            mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
            mysqli_close($srcConn);
            mysqli_close($tgtConn);
            return ['ok' => false, 'message' => 'Copy tbl_customer_types failed (read source): ' . $err];
        }
        while ($typeRow = mysqli_fetch_assoc($rTypes)) {
            $typeCols   = array_keys($typeRow);
            $typeVals   = [];
            foreach ($typeRow as $v) {
                if ($v === null) {
                    $typeVals[] = 'NULL';
                } else {
                    $typeVals[] = '\'' . mysqli_real_escape_string($tgtConn, (string) $v) . '\'';
                }
            }
            $insT = 'INSERT INTO ' . $tq . '.`tbl_customer_types` ('
                . implode(',', array_map($backtickCol, $typeCols)) . ') VALUES ('
                . implode(',', $typeVals) . ')';
            if (!mysqli_query($tgtConn, $insT)) {
                $err = mysqli_error($tgtConn);
                mysqli_free_result($rTypes);
                mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
                mysqli_close($srcConn);
                mysqli_close($tgtConn);
                return ['ok' => false, 'message' => 'Copy tbl_customer_types failed: ' . $err];
            }
        }
        mysqli_free_result($rTypes);

        $rCusts = mysqli_query($srcConn, 'SELECT * FROM ' . $sq . '.`tbl_customers` WHERE status = 1');
        if (!$rCusts) {
            $err = mysqli_error($srcConn);
            mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
            mysqli_close($srcConn);
            mysqli_close($tgtConn);
            return ['ok' => false, 'message' => 'Copy tbl_customers failed (read source): ' . $err];
        }
        while ($custRow = mysqli_fetch_assoc($rCusts)) {
            $custCols = array_keys($custRow);
            $custVals = [];
            foreach ($custRow as $v) {
                if ($v === null) {
                    $custVals[] = 'NULL';
                } else {
                    $custVals[] = '\'' . mysqli_real_escape_string($tgtConn, (string) $v) . '\'';
                }
            }
            $insC = 'INSERT INTO ' . $tq . '.`tbl_customers` ('
                . implode(',', array_map($backtickCol, $custCols)) . ') VALUES ('
                . implode(',', $custVals) . ')';
            if (!mysqli_query($tgtConn, $insC)) {
                $err = mysqli_error($tgtConn);
                mysqli_free_result($rCusts);
                mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
                mysqli_close($srcConn);
                mysqli_close($tgtConn);
                return ['ok' => false, 'message' => 'Copy tbl_customers failed: ' . $err];
            }
        }
        mysqli_free_result($rCusts);

        if (auragold_schema_table_exists_on_link($tgtConn, $targetDbName, 'tbl_customer_balance')
            && auragold_schema_table_exists_on_link($srcConn, $sourceDb, 'tbl_customer_balance')) {
            $balCols = auragold_tbl_columns_set($tgtConn, 'tbl_customer_balance');
            if (isset($balCols['customer_id']) && isset($balCols['customer_name'])) {
                $insBal = mysqli_query(
                    $tgtConn,
                    'INSERT INTO ' . $tq . '.`tbl_customer_balance` (`customer_id`, `customer_name`, `balance_amount`, `balance_gold`, `balance_silver`, `last_transaction_date`, `last_updated`) '
                    . 'SELECT c.id, c.name, 0, 0, 0, CURDATE(), NOW() FROM ' . $tq . '.`tbl_customers` c WHERE c.status = 1'
                );
                if (!$insBal) {
                    error_log('AuraGold sub-branch seed: tbl_customer_balance insert: ' . mysqli_error($tgtConn));
                }
            }
        }

        $names = [];
        $nq    = mysqli_query(
            $srcConn,
            'SELECT DISTINCT TRIM(customer_name) AS nm FROM ' . $sq . '.`tbl_customer_ledger` WHERE status = 1 AND TRIM(COALESCE(customer_name, \'\')) != \'\''
        );
        if ($nq) {
            while ($row = mysqli_fetch_assoc($nq)) {
                $nm = trim((string) ($row['nm'] ?? ''));
                if ($nm !== '') {
                    $names[$nm] = true;
                }
            }
            mysqli_free_result($nq);
        }

        $ledgerCols = auragold_tbl_columns_set($tgtConn, 'tbl_customer_ledger');
        $todayEsc   = mysqli_real_escape_string($tgtConn, date('Y-m-d'));
        $bid        = $newSubBranchId;
        $inserted   = 0;

        foreach (array_keys($names) as $ledgerName) {
            $esc = mysqli_real_escape_string($tgtConn, $ledgerName);
            $cid = 0;
            $cr  = mysqli_query($tgtConn, 'SELECT id FROM ' . $tq . '.`tbl_customers` WHERE name = \'' . $esc . '\' AND status = 1 ORDER BY id ASC LIMIT 1');
            if ($cr && ($crow = mysqli_fetch_assoc($cr))) {
                $cid = (int) ($crow['id'] ?? 0);
            }
            if ($cr) {
                mysqli_free_result($cr);
            }

            $parts = ['customer_id', 'customer_name'];
            $vals  = [(string) (int) $cid, '\'' . $esc . '\''];
            if (isset($ledgerCols['branch_id'])) {
                $parts[] = 'branch_id';
                $vals[]  = (string) (int) $bid;
            }
            $parts = array_merge($parts, [
                'transaction_type', 'transaction_id', 'transaction_no', 'transaction_date',
                'debit_amount', 'credit_amount', 'debit_gold', 'credit_gold', 'debit_silver', 'credit_silver',
                'balance_amount', 'balance_gold', 'balance_silver', 'description', 'reference_no', 'against_ledger', 'against_invoice_no',
                'status', 'created_by', 'created_at',
            ]);
            $vals = array_merge($vals, [
                '\'opening\'', '0', '\'OPENING\'', '\'' . $todayEsc . '\'',
                '0.00', '0.00', '0', '0', '0', '0',
                '0.00', '0', '0', '\'Opening balance\'', '\'\'', '\'\'', '\'\'',
                '1', '0', 'NOW()',
            ]);
            if (isset($ledgerCols['debit_gold_pure'])) {
                $parts[] = 'debit_gold_pure';
                $vals[]  = '0';
            }
            if (isset($ledgerCols['credit_gold_pure'])) {
                $parts[] = 'credit_gold_pure';
                $vals[]  = '0';
            }
            if (isset($ledgerCols['balance_gold_pure'])) {
                $parts[] = 'balance_gold_pure';
                $vals[]  = '0';
            }

            $sqlIns = 'INSERT INTO ' . $tq . '.`tbl_customer_ledger` (`' . implode('`,`', $parts) . '`) VALUES (' . implode(',', $vals) . ')';
            if (mysqli_query($tgtConn, $sqlIns)) {
                $inserted++;
            }
        }

        mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');

        mysqli_close($srcConn);
        mysqli_close($tgtConn);

        return [
            'ok'      => true,
            'message' => 'Copied party masters and ' . $inserted . ' ledger opening row(s) with zero balance for sub-branch id ' . $newSubBranchId . '.',
        ];
    }
}
