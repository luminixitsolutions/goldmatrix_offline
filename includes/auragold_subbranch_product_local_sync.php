<?php
/**
 * Copy activated sub-branch product rows from the main catalog DB into the login branch DB.
 */

if (!function_exists('auragold_mysqli_database_name')) {
    function auragold_mysqli_database_name(mysqli $conn): string {
        $r = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if ($r && ($row = mysqli_fetch_assoc($r))) {
            mysqli_free_result($r);
            return trim((string) ($row['d'] ?? ''));
        }
        if ($r) {
            mysqli_free_result($r);
        }
        return '';
    }
}

if (!function_exists('auragold_mysqli_table_exists')) {
    function auragold_mysqli_table_exists(mysqli $conn, string $table): bool {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $ok = ($r && mysqli_num_rows($r) > 0);
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_mysqli_table_columns')) {
    /** @return array<int, string> */
    function auragold_mysqli_table_columns(mysqli $conn, string $table): array {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !auragold_mysqli_table_exists($conn, $table)) {
            return [];
        }
        $cols = [];
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $f = (string) ($row['Field'] ?? '');
                if ($f !== '') {
                    $cols[] = $f;
                }
            }
            mysqli_free_result($r);
        }
        return $cols;
    }
}

if (!function_exists('auragold_mysqli_sql_value')) {
    function auragold_mysqli_sql_value($v): string {
        if ($v === null) {
            return 'NULL';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        $s = (string) $v;
        if (function_exists('esc')) {
            return "'" . esc($s) . "'";
        }
        return "'" . addslashes($s) . "'";
    }
}

if (!function_exists('auragold_mysqli_copy_rows')) {
    /**
     * @return int Rows copied/upserted
     */
    function auragold_mysqli_copy_rows(mysqli $src, mysqli $dst, string $table, string $whereSql): int {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || $whereSql === '') {
            return 0;
        }
        if (!auragold_mysqli_table_exists($src, $table) || !auragold_mysqli_table_exists($dst, $table)) {
            return 0;
        }

        $dstCols = auragold_mysqli_table_columns($dst, $table);
        if ($dstCols === []) {
            return 0;
        }
        $dstColMap = array_fill_keys($dstCols, true);

        $res = @mysqli_query($src, "SELECT * FROM `$table` WHERE $whereSql");
        if (!$res) {
            return 0;
        }

        $count = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            $useCols = [];
            $vals = [];
            foreach ($row as $k => $v) {
                if (!isset($dstColMap[$k])) {
                    continue;
                }
                $useCols[] = '`' . $k . '`';
                $vals[] = auragold_mysqli_sql_value($v);
            }
            if ($useCols === []) {
                continue;
            }
            $updates = [];
            foreach ($useCols as $c) {
                $updates[] = $c . ' = VALUES(' . $c . ')';
            }
            $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $useCols) . ') VALUES (' . implode(', ', $vals) . ')'
                . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            if (@mysqli_query($dst, $sql)) {
                $count++;
            }
        }
        mysqli_free_result($res);
        return $count;
    }
}

if (!function_exists('auragold_subbranch_catalog_and_local_differ')) {
    function auragold_subbranch_catalog_and_local_differ(mysqli $catalogConn, mysqli $branchConn): bool {
        if ($catalogConn === $branchConn) {
            return false;
        }
        $a = auragold_mysqli_database_name($catalogConn);
        $b = auragold_mysqli_database_name($branchConn);
        return ($a !== '' && $b !== '' && strcasecmp($a, $b) !== 0);
    }
}

if (!function_exists('auragold_ensure_subbranch_product_branch_settings')) {
    function auragold_ensure_subbranch_product_branch_settings(mysqli $catalogConn, mysqli $branchConn, int $productId, int $subBranchId): void {
        if ($productId <= 0 || $subBranchId <= 0) {
            return;
        }
        if (!function_exists('auragold_ensure_product_branch_local_schema')) {
            require_once __DIR__ . '/auragold_product_branch_local_schema.php';
        }
        auragold_ensure_product_branch_local_schema($branchConn);
        if (!auragold_mysqli_table_exists($branchConn, 'tbl_product_branch_settings')) {
            return;
        }

        $chk = @mysqli_query(
            $branchConn,
            'SELECT product_id FROM tbl_product_branch_settings WHERE product_id = '
            . (int) $productId . ' AND branch_id = ' . (int) $subBranchId . ' LIMIT 1'
        );
        $has = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
        if ($has) {
            return;
        }

        $catId = 'NULL';
        $stk = 1;
        $bc = 1;
        if (auragold_mysqli_table_exists($catalogConn, 'tbl_product_branch_settings')) {
            $srcSet = @mysqli_query(
                $catalogConn,
                'SELECT category_id, is_stock_item, is_barcode FROM tbl_product_branch_settings WHERE product_id = '
                . (int) $productId . ' AND branch_id = ' . (int) $subBranchId . ' LIMIT 1'
            );
            if ($srcSet && ($sr = mysqli_fetch_assoc($srcSet))) {
                $cid = (int) ($sr['category_id'] ?? 0);
                $catId = $cid > 0 ? (string) $cid : 'NULL';
                $stk = (int) ($sr['is_stock_item'] ?? 1);
                if (array_key_exists('is_barcode', $sr)) {
                    $bc = (int) ($sr['is_barcode'] ?? 1);
                }
            }
            if ($srcSet) {
                mysqli_free_result($srcSet);
            }
        }
        if ($catId === 'NULL' && auragold_mysqli_table_exists($catalogConn, 'tbl_products')) {
            $pr = @mysqli_query($catalogConn, 'SELECT category_id FROM tbl_products WHERE id = ' . (int) $productId . ' LIMIT 1');
            if ($pr && ($prow = mysqli_fetch_assoc($pr))) {
                $cid = (int) ($prow['category_id'] ?? 0);
                $catId = $cid > 0 ? (string) $cid : 'NULL';
            }
            if ($pr) {
                mysqli_free_result($pr);
            }
        }

        $has_bc = function_exists('auragold_tbl_has_column')
            ? auragold_tbl_has_column($branchConn, 'tbl_product_branch_settings', 'is_barcode')
            : false;
        $bc_sql = $has_bc ? ', is_barcode' : '';
        $bc_val = $has_bc ? ', ' . (int) $bc : '';
        @mysqli_query(
            $branchConn,
            'INSERT INTO tbl_product_branch_settings (product_id, branch_id, category_id, is_stock_item' . $bc_sql . ', updated_at)
            VALUES (' . (int) $productId . ', ' . (int) $subBranchId . ', ' . $catId . ', ' . (int) $stk . $bc_val . ', NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()'
        );
    }
}

if (!function_exists('auragold_sync_subbranch_product_local_from_catalog')) {
    /**
     * Mirror one product's branch-scoped rows from catalog DB into the login branch DB.
     */
    function auragold_sync_subbranch_product_local_from_catalog(
        mysqli $catalogConn,
        mysqli $branchConn,
        int $productId,
        int $subBranchId,
        bool $activate = true
    ): void {
        if ($productId <= 0 || $subBranchId <= 0) {
            return;
        }
        if (!auragold_subbranch_catalog_and_local_differ($catalogConn, $branchConn)) {
            return;
        }

        if (!function_exists('auragold_ensure_product_branch_local_schema')) {
            require_once __DIR__ . '/auragold_product_branch_local_schema.php';
        }
        auragold_ensure_product_branch_local_schema($branchConn);
        if (function_exists('auragold_ensure_tbl_product_branches_is_active')) {
            auragold_ensure_tbl_product_branches_is_active($branchConn);
        }

        $pid = (int) $productId;
        $sub = (int) $subBranchId;

        if (!$activate) {
            if (auragold_mysqli_table_exists($branchConn, 'tbl_product_branches')
                && function_exists('auragold_tbl_product_branches_has_is_active')
                && auragold_tbl_product_branches_has_is_active($branchConn)) {
                @mysqli_query(
                    $branchConn,
                    'UPDATE tbl_product_branches SET is_active = 0 WHERE product_id = ' . $pid . ' AND branch_id = ' . $sub
                );
            }
            if (auragold_mysqli_table_exists($branchConn, 'tbl_stock')) {
                @mysqli_query(
                    $branchConn,
                    "UPDATE tbl_stock SET status = 0, updated_at = NOW() WHERE product_id = $pid AND branch_id = $sub"
                );
            }
            return;
        }

        auragold_mysqli_copy_rows($catalogConn, $branchConn, 'tbl_products', 'id = ' . $pid);

        if (auragold_mysqli_table_exists($catalogConn, 'tbl_product_branches')) {
            auragold_mysqli_copy_rows(
                $catalogConn,
                $branchConn,
                'tbl_product_branches',
                'product_id = ' . $pid . ' AND branch_id = ' . $sub
            );
            if (function_exists('auragold_tbl_product_branches_has_is_active')
                && auragold_tbl_product_branches_has_is_active($branchConn)) {
                @mysqli_query(
                    $branchConn,
                    'INSERT INTO tbl_product_branches (product_id, branch_id, is_active) VALUES (' . $pid . ', ' . $sub . ', 1)
                    ON DUPLICATE KEY UPDATE is_active = 1'
                );
            }
        }

        auragold_mysqli_copy_rows(
            $catalogConn,
            $branchConn,
            'tbl_product_branch_settings',
            'product_id = ' . $pid . ' AND branch_id = ' . $sub
        );
        auragold_ensure_subbranch_product_branch_settings($catalogConn, $branchConn, $pid, $sub);

        auragold_mysqli_copy_rows(
            $catalogConn,
            $branchConn,
            'tbl_product_characteristics',
            'product_id = ' . $pid . ' AND branch_id = ' . $sub . ' AND status = 1'
        );

        if (auragold_mysqli_table_exists($catalogConn, 'tbl_product_tax')) {
            $taxWhere = 'product_id = ' . $pid . ' AND status = 1';
            $taxCols = auragold_mysqli_table_columns($catalogConn, 'tbl_product_tax');
            if (in_array('branch_id', $taxCols, true)) {
                $taxWhere .= ' AND branch_id = ' . $sub;
            }
            auragold_mysqli_copy_rows($catalogConn, $branchConn, 'tbl_product_tax', $taxWhere);
        }

        auragold_mysqli_copy_rows(
            $catalogConn,
            $branchConn,
            'tbl_stock',
            'product_id = ' . $pid . ' AND branch_id = ' . $sub . ' AND status = 1'
        );
    }
}
