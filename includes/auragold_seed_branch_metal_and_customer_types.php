<?php
/**
 * After a new tbl_branches row: default tbl_metal (scoped by branch_id when present)
 * and tbl_customer_types (idempotent by `name`, matching UNIQUE `name`; optional branch_id on insert).
 *
 * Sub-branches: copy tbl_metal + tbl_carat from the parent main branch (branch_id remapped), not hardcoded defaults.
 */
require_once __DIR__ . '/auragold_seed_sub_branch_ledger_customers.php';
require_once __DIR__ . '/branch_create_db_after_save.php';
if (!function_exists('auragold_schema_table_reachable')) {
    function auragold_schema_table_reachable(mysqli $conn, string $table): bool {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        if ($t === '' || !($conn instanceof mysqli)) {
            return false;
        }
        $r = @mysqli_query($conn, 'SELECT 1 FROM `' . str_replace('`', '``', $t) . '` LIMIT 1');
        if ($r) {
            mysqli_free_result($r);
        }
        return (bool) $r;
    }
}

if (!function_exists('auragold_metal_default_rows_for_new_branch')) {
    /**
     * @return list<array{display_name:string, hsn_code:?string, system_name:?string, created_by:?int}>
     */
    function auragold_metal_default_rows_for_new_branch(): array {
        return [
            ['display_name' => 'Gold', 'hsn_code' => '12345', 'system_name' => 'ewew', 'created_by' => 0],
            ['display_name' => 'Silver', 'hsn_code' => null, 'system_name' => null, 'created_by' => null],
            ['display_name' => 'Platinum', 'hsn_code' => null, 'system_name' => null, 'created_by' => null],
            ['display_name' => 'Diamond & Stones', 'hsn_code' => null, 'system_name' => null, 'created_by' => null],
            ['display_name' => 'Imitation Or Watches', 'hsn_code' => null, 'system_name' => null, 'created_by' => null],
            ['display_name' => 'Other Or Services', 'hsn_code' => null, 'system_name' => null, 'created_by' => null],
        ];
    }
}

if (!function_exists('auragold_customer_type_default_rows_for_new_branch')) {
    /**
     * @return list<array{name:string,code:string,sort:int}>
     */
    function auragold_customer_type_default_rows_for_new_branch(): array {
        return [
            ['name' => 'Customer', 'code' => 'CUSTOMER', 'sort' => 1],
            ['name' => 'WholeSaler', 'code' => 'WHOLESALER', 'sort' => 2],
            ['name' => 'Job Worker', 'code' => 'JOB_WORKER', 'sort' => 3],
            ['name' => 'Employee', 'code' => 'EMPLOYEE', 'sort' => 4],
            ['name' => 'Sales Person', 'code' => 'SALES_PERSON', 'sort' => 5],
            ['name' => 'Supplier', 'code' => 'SUPPLIER', 'sort' => 6],
            ['name' => 'Qbo Account', 'code' => 'QBO_ACCOUNT', 'sort' => 7],
            ['name' => 'Retailer', 'code' => 'RETAILER', 'sort' => 8],
        ];
    }
}

if (!function_exists('auragold_seed_sub_branch_metal_and_carat_from_main')) {
    /**
     * Replace provisioned tbl_metal / tbl_carat in a new sub-branch DB with copies from the main branch,
     * keeping the same `id` values as main (only branch_id changes to the new sub-branch).
     *
     * @return array{ok:bool,message:string,metals?:int,carats?:int}
     */
    function auragold_seed_sub_branch_metal_and_carat_from_main(
        mysqli $conn_master,
        int $mainBranchId,
        int $newSubBranchId,
        string $targetDbName,
        string $targetDbUser,
        string $targetDbPass,
        array $opts = []
    ): array {
        $skipCarat      = !empty($opts['skip_carat']);
        $skipTaxMaster  = !empty($opts['skip_tax_master']);
        $mainBranchId   = (int) $mainBranchId;
        $newSubBranchId = (int) $newSubBranchId;
        $targetDbName   = trim($targetDbName);
        if ($mainBranchId <= 0 || $newSubBranchId <= 0) {
            return ['ok' => false, 'message' => 'Invalid main or sub-branch id.'];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $targetDbName)) {
            return ['ok' => false, 'message' => 'Invalid target database name.'];
        }

        if (!function_exists('auragold_resolve_main_branch_source_database')) {
            return ['ok' => false, 'message' => 'Main branch database resolver is not available.'];
        }
        $resolved = auragold_resolve_main_branch_source_database($conn_master, $mainBranchId);
        if (empty($resolved['ok'])) {
            return ['ok' => false, 'message' => $resolved['message'] !== '' ? $resolved['message'] : 'Could not resolve main branch database.'];
        }
        $sourceDb = trim((string) ($resolved['db_name'] ?? ''));
        if ($sourceDb === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
            return ['ok' => false, 'message' => 'Invalid main branch database name.'];
        }

        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';

        if (strcasecmp($sourceDb, $targetDbName) === 0) {
            // Same operational database: metal/carat masters stay on the main branch (shared ids).
            if (!function_exists('auragold_seed_subbranch_masters_from_main')) {
                require_once __DIR__ . '/auragold_branch_data_scope.php';
            }
            $link = $GLOBALS['conn'] ?? null;
            if (!$link instanceof mysqli) {
                $link = auragold_mysqli_connect_branch_or_registry($host, $targetDbName, $targetDbUser, $targetDbPass);
            }
            if (!$link) {
                return ['ok' => false, 'message' => 'Could not connect to branch database for master copy.'];
            }
            $seedOpts = [];
            if ($skipTaxMaster) {
                $seedOpts['skip_tax_master'] = true;
            }
            $seed = auragold_seed_subbranch_masters_from_main($link, $mainBranchId, $newSubBranchId, $seedOpts);
            return [
                'ok'            => true,
                'message'       => 'Metals shared with main branch (same database). Other masters copied where needed.'
                    . ($skipCarat ? ' Carat master was not copied.' : '')
                    . ($skipTaxMaster ? ' Tax master was not copied.' : ''),
                'metals'        => 0,
                'carats'        => 0,
                'shared_master' => true,
                'other_masters' => $seed['tables'] ?? [],
            ];
        }

        $srcConn = auragold_mysqli_connect_branch_or_registry($host, $sourceDb, $resolved['db_user'], $resolved['db_pass']);
        if (!$srcConn) {
            return ['ok' => false, 'message' => 'Could not connect to main branch database `' . $sourceDb . '` for metal/carat copy.'];
        }
        mysqli_set_charset($srcConn, 'utf8mb4');

        $tgtConn = auragold_mysqli_connect_branch_or_registry($host, $targetDbName, $targetDbUser, $targetDbPass);
        if (!$tgtConn) {
            mysqli_close($srcConn);
            return ['ok' => false, 'message' => 'Could not connect to new sub-branch database for metal/carat copy.'];
        }
        mysqli_set_charset($tgtConn, 'utf8mb4');

        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }

        $sq = '`' . str_replace('`', '``', $sourceDb) . '`';
        $tq = '`' . str_replace('`', '``', $targetDbName) . '`';

        $tablesToCopy = ['tbl_metal'];
        if (!$skipCarat) {
            $tablesToCopy[] = 'tbl_carat';
        }
        foreach ($tablesToCopy as $t) {
            if (!auragold_schema_table_exists_on_link($srcConn, $sourceDb, $t)
                || !auragold_schema_table_exists_on_link($tgtConn, $targetDbName, $t)) {
                mysqli_close($srcConn);
                mysqli_close($tgtConn);
                return ['ok' => false, 'message' => 'Required table `' . $t . '` missing on source or target.'];
            }
        }

        $metalHasBranch = auragold_tbl_has_column($srcConn, 'tbl_metal', 'branch_id');
        $caratHasBranch = auragold_tbl_has_column($srcConn, 'tbl_carat', 'branch_id');
        $metalWhere     = '`status` = 1';
        if ($metalHasBranch) {
            $metalWhere = '`branch_id` = ' . (int) $mainBranchId;
            if (auragold_tbl_has_column($srcConn, 'tbl_metal', 'status')) {
                $metalWhere .= ' AND `status` = 1';
            }
        }
        $caratWhere = '`status` = 1';
        if ($caratHasBranch) {
            $caratWhere = '`branch_id` = ' . (int) $mainBranchId;
            if (auragold_tbl_has_column($srcConn, 'tbl_carat', 'status')) {
                $caratWhere .= ' AND `status` = 1';
            }
        }

        $tgtMetalCols = auragold_tbl_columns_set($tgtConn, 'tbl_metal');
        $tgtCaratCols = auragold_tbl_columns_set($tgtConn, 'tbl_carat');

        $backtickCol = static function (string $c): string {
            return '`' . str_replace('`', '``', $c) . '`';
        };

        $resetAutoInc = static function (mysqli $link, string $dbQ, string $table) use ($backtickCol): void {
            $t = str_replace('`', '``', $table);
            $r = @mysqli_query($link, 'SELECT COALESCE(MAX(`id`), 0) + 1 AS n FROM ' . $dbQ . '.`' . $t . '`');
            if ($r && ($mx = mysqli_fetch_assoc($r))) {
                $n = (int) ($mx['n'] ?? 1);
                if ($n < 1) {
                    $n = 1;
                }
                @mysqli_query($link, 'ALTER TABLE ' . $dbQ . '.`' . $t . '` AUTO_INCREMENT = ' . $n);
            }
            if ($r) {
                mysqli_free_result($r);
            }
        };

        $insertRow = static function (mysqli $link, string $dbQ, string $table, array $row, array $allowedCols, array $overrides) use ($backtickCol): bool {
            $cols = [];
            $vals = [];
            foreach ($row as $col => $val) {
                if (!is_string($col)) {
                    continue;
                }
                $lc = strtolower($col);
                if (!isset($allowedCols[$lc])) {
                    continue;
                }
                if (array_key_exists($lc, $overrides)) {
                    $val = $overrides[$lc];
                }
                $cols[] = $backtickCol($col);
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($val) || is_float($val)) {
                    $vals[] = (string) $val;
                } else {
                    $vals[] = '\'' . mysqli_real_escape_string($link, (string) $val) . '\'';
                }
            }
            if ($cols === []) {
                return false;
            }
            $sql = 'INSERT INTO ' . $dbQ . '.`' . str_replace('`', '``', $table) . '` ('
                . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';

            return (bool) mysqli_query($link, $sql);
        };

        mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=0');
        if (!$skipCarat) {
            mysqli_query($tgtConn, 'DELETE FROM ' . $tq . '.`tbl_carat`');
        }
        mysqli_query($tgtConn, 'DELETE FROM ' . $tq . '.`tbl_metal`');

        $metalCount = 0;
        $rMetals    = mysqli_query($srcConn, 'SELECT * FROM ' . $sq . '.`tbl_metal` WHERE ' . $metalWhere . ' ORDER BY `id` ASC');
        if (!$rMetals) {
            $err = mysqli_error($srcConn);
            mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
            mysqli_close($srcConn);
            mysqli_close($tgtConn);
            return ['ok' => false, 'message' => 'Could not read metals from main branch: ' . $err];
        }
        while ($metalRow = mysqli_fetch_assoc($rMetals)) {
            $overrides = [];
            if (isset($tgtMetalCols['branch_id'])) {
                $overrides['branch_id'] = $newSubBranchId;
            }
            if (!$insertRow($tgtConn, $tq, 'tbl_metal', $metalRow, $tgtMetalCols, $overrides)) {
                $err = mysqli_error($tgtConn);
                mysqli_free_result($rMetals);
                mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
                mysqli_close($srcConn);
                mysqli_close($tgtConn);
                return ['ok' => false, 'message' => 'Copy tbl_metal failed: ' . $err];
            }
            $metalCount++;
        }
        mysqli_free_result($rMetals);
        if ($metalCount > 0) {
            $resetAutoInc($tgtConn, $tq, 'tbl_metal');
        }

        $caratCount = 0;
        if (!$skipCarat) {
            $rCarats = mysqli_query($srcConn, 'SELECT * FROM ' . $sq . '.`tbl_carat` WHERE ' . $caratWhere . ' ORDER BY `id` ASC');
            if (!$rCarats) {
                $err = mysqli_error($srcConn);
                mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
                mysqli_close($srcConn);
                mysqli_close($tgtConn);
                return ['ok' => false, 'message' => 'Could not read carats from main branch: ' . $err];
            }
            while ($caratRow = mysqli_fetch_assoc($rCarats)) {
                $overrides = [];
                if (isset($tgtCaratCols['branch_id'])) {
                    $overrides['branch_id'] = $newSubBranchId;
                }
                if (!$insertRow($tgtConn, $tq, 'tbl_carat', $caratRow, $tgtCaratCols, $overrides)) {
                    $err = mysqli_error($tgtConn);
                    mysqli_free_result($rCarats);
                    mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
                    mysqli_close($srcConn);
                    mysqli_close($tgtConn);
                    return ['ok' => false, 'message' => 'Copy tbl_carat failed: ' . $err];
                }
                $caratCount++;
            }
            mysqli_free_result($rCarats);
            if ($caratCount > 0) {
                $resetAutoInc($tgtConn, $tq, 'tbl_carat');
            }
        }

        mysqli_query($tgtConn, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_close($srcConn);
        mysqli_close($tgtConn);

        $msg = 'Copied ' . $metalCount . ' metal(s) from main branch (same ids, branch_id ' . $newSubBranchId . ').';
        if (!$skipCarat) {
            $msg = 'Copied ' . $metalCount . ' metal(s) and ' . $caratCount . ' carat row(s) from main branch (same ids, branch_id ' . $newSubBranchId . ').';
        }

        return [
            'ok'      => true,
            'message' => $msg,
            'metals'  => $metalCount,
            'carats'  => $caratCount,
        ];
    }
}

if (!function_exists('auragold_seed_metal_and_customer_types_for_new_branch')) {
    /**
     * @param mysqli $conn             Operational (branch) database
     * @param int    $registryBranchId New tbl_branches.id
     * @param array  $opts             skip_metal (bool) — when metals were copied from main branch
     */
    function auragold_seed_metal_and_customer_types_for_new_branch(mysqli $conn, int $registryBranchId, array $opts = []): void {
        $registryBranchId = (int) $registryBranchId;
        if ($registryBranchId < 1) {
            return;
        }
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        if (!function_exists('auragold_tbl_has_column')) {
            return;
        }

        $bid       = (int) $registryBranchId;
        $skipMetal = !empty($opts['skip_metal']);

        if (!$skipMetal && function_exists('auragold_metal_carat_master_branch_id')) {
            $metalMasterBranchId = auragold_metal_carat_master_branch_id($bid);
            if ($metalMasterBranchId > 0 && $metalMasterBranchId !== $bid) {
                $skipMetal = true;
            }
        }

        // --- tbl_metal ---
        if (!$skipMetal && auragold_schema_table_reachable($conn, 'tbl_metal')) {
            $hasBranch   = auragold_tbl_has_column($conn, 'tbl_metal', 'branch_id');
            $metals      = auragold_metal_default_rows_for_new_branch();
            if ($hasBranch) {
                $cntR = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `tbl_metal` WHERE `branch_id` = ' . (int) $bid);
            } else {
                $cntR = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `tbl_metal`');
            }
            $cct = 0;
            if ($cntR && ($rw = mysqli_fetch_assoc($cntR))) {
                $cct = (int) ($rw['c'] ?? 0);
            }
            if ($cntR) {
                mysqli_free_result($cntR);
            }
            if ($cct === 0) {
                foreach ($metals as $m) {
                    $escName = mysqli_real_escape_string($conn, (string) $m['display_name']);
                    if ($hasBranch) {
                        $qEx = "SELECT 1 FROM `tbl_metal` WHERE `display_name` = '{$escName}' AND `branch_id` = " . (int) $bid . ' LIMIT 1';
                    } else {
                        $qEx = "SELECT 1 FROM `tbl_metal` WHERE `display_name` = '{$escName}' LIMIT 1";
                    }
                    $eR = @mysqli_query($conn, $qEx);
                    if ($eR) {
                        if (mysqli_num_rows($eR) > 0) {
                            mysqli_free_result($eR);
                            continue;
                        }
                        mysqli_free_result($eR);
                    }

                    $hp = [];
                    $vp = [];
                    if ($hasBranch) {
                        $hp[] = '`branch_id`';
                        $vp[] = (string) (int) $bid;
                    }
                    $hp[] = '`display_name`';
                    $vp[] = '\'' . $escName . '\'';
                    $hp[] = '`hsn_code`';
                    if ($m['hsn_code'] !== null && (string) $m['hsn_code'] !== '') {
                        $vp[] = '\'' . mysqli_real_escape_string($conn, (string) $m['hsn_code']) . '\'';
                    } else {
                        $vp[] = 'NULL';
                    }
                    $hp[] = '`system_name`';
                    if ($m['system_name'] !== null && (string) $m['system_name'] !== '') {
                        $vp[] = '\'' . mysqli_real_escape_string($conn, (string) $m['system_name']) . '\'';
                    } else {
                        $vp[] = 'NULL';
                    }
                    $hp[] = '`status`';
                    $vp[] = '1';
                    if (auragold_tbl_has_column($conn, 'tbl_metal', 'created_by')) {
                        $hp[] = '`created_by`';
                        if (isset($m['created_by']) && $m['created_by'] !== null) {
                            $vp[] = (string) (int) $m['created_by'];
                        } else {
                            $vp[] = 'NULL';
                        }
                    }
                    if (auragold_tbl_has_column($conn, 'tbl_metal', 'modified_by')) {
                        $hp[] = '`modified_by`';
                        $vp[] = 'NULL';
                    }
                    if (auragold_tbl_has_column($conn, 'tbl_metal', 'created_at')) {
                        $hp[] = '`created_at`';
                        $vp[] = 'NOW()';
                    }
                    if (auragold_tbl_has_column($conn, 'tbl_metal', 'updated_at')) {
                        $hp[] = '`updated_at`';
                        $vp[] = 'NULL';
                    }
                    @mysqli_query(
                        $conn,
                        'INSERT INTO `tbl_metal` (' . implode(',', $hp) . ') VALUES (' . implode(',', $vp) . ')'
                    );
                }
            }
        }

        // --- tbl_customer_types (merge missing) ---
        if (auragold_schema_table_reachable($conn, 'tbl_customer_types')) {
            $hasCBranch = auragold_tbl_has_column($conn, 'tbl_customer_types', 'branch_id');
            $types      = auragold_customer_type_default_rows_for_new_branch();
            foreach ($types as $t) {
                $cEsc = mysqli_real_escape_string($conn, (string) $t['code']);
                $nEsc = mysqli_real_escape_string($conn, (string) $t['name']);
                // Schema uses UNIQUE KEY `name` (`name`). After sub-branch provision we copy types from the
                // main DB; those rows often keep the main branch's `branch_id`. A code lookup scoped to
                // this branch then misses and INSERT duplicates `name` (e.g. "Customer").
                $r = @mysqli_query(
                    $conn,
                    'SELECT 1 FROM `tbl_customer_types` WHERE LOWER(`name`) = LOWER(\'' . $nEsc . '\') LIMIT 1'
                );
                if ($r && mysqli_num_rows($r) > 0) {
                    mysqli_free_result($r);
                    continue;
                }
                if ($r) {
                    mysqli_free_result($r);
                }
                $hp2  = ['`name`', '`code`', '`status`', '`sort_order`'];
                $vp2  = ['\'' . $nEsc . '\'', '\'' . $cEsc . '\'', '1', (string) (int) $t['sort']];
                if ($hasCBranch) {
                    $hp2[] = '`branch_id`';
                    $vp2[] = (string) (int) $bid;
                }
                if (auragold_tbl_has_column($conn, 'tbl_customer_types', 'created_at')) {
                    $hp2[] = '`created_at`';
                    $vp2[] = 'NOW()';
                }
                if (auragold_tbl_has_column($conn, 'tbl_customer_types', 'updated_at')) {
                    $hp2[] = '`updated_at`';
                    $vp2[] = 'NULL';
                }
                @mysqli_query(
                    $conn,
                    'INSERT INTO `tbl_customer_types` (' . implode(',', $hp2) . ') VALUES (' . implode(',', $vp2) . ')'
                );
            }
        }
    }
}
