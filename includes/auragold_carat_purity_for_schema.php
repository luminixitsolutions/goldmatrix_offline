<?php
/**
 * Carat master: separate purity % for Sales, Purchase, and Common on one row.
 */
if (!function_exists('auragold_ensure_tbl_carat_purity_split')) {
    function auragold_ensure_tbl_carat_purity_split($conn): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return;
        }
        $t = 'tbl_carat';
        foreach (
            [
                'purity_sales'    => "DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'Purity % for sales vouchers' AFTER `purity`",
                'purity_purchase' => "DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'Purity % for purchase vouchers' AFTER `purity_sales`",
                'purity_common'   => "DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'Purity % for common/stock' AFTER `purity_purchase`",
            ] as $col => $def
        ) {
            if (!auragold_tbl_has_column($conn, $t, $col)) {
                @mysqli_query($conn, "ALTER TABLE `{$t}` ADD COLUMN `{$col}` {$def}");
            }
        }
        if (auragold_tbl_has_column($conn, $t, 'purity_common') && auragold_tbl_has_column($conn, $t, 'purity')) {
            @mysqli_query(
                $conn,
                "UPDATE `{$t}` SET
                    purity_common = purity
                 WHERE status = 1
                   AND purity IS NOT NULL AND TRIM(CAST(purity AS CHAR)) != ''
                   AND (purity_common IS NULL OR TRIM(CAST(purity_common AS CHAR)) = '')"
            );
            @mysqli_query(
                $conn,
                "UPDATE `{$t}` SET
                    purity_sales = purity
                 WHERE status = 1
                   AND purity IS NOT NULL AND TRIM(CAST(purity AS CHAR)) != ''
                   AND (purity_sales IS NULL OR TRIM(CAST(purity_sales AS CHAR)) = '')"
            );
            @mysqli_query(
                $conn,
                "UPDATE `{$t}` SET
                    purity_purchase = purity
                 WHERE status = 1
                   AND purity IS NOT NULL AND TRIM(CAST(purity AS CHAR)) != ''
                   AND (purity_purchase IS NULL OR TRIM(CAST(purity_purchase AS CHAR)) = '')"
            );
        }
    }
}

/** @deprecated use auragold_ensure_tbl_carat_purity_split */
if (!function_exists('auragold_ensure_tbl_carat_purity_for')) {
    function auragold_ensure_tbl_carat_purity_for($conn): void
    {
        auragold_ensure_tbl_carat_purity_split($conn);
    }
}

if (!function_exists('auragold_carat_has_split_purity')) {
    function auragold_carat_has_split_purity($conn): bool
    {
        return $conn instanceof mysqli
            && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_carat', 'purity_sales');
    }
}

/** @deprecated */
if (!function_exists('auragold_carat_has_purity_for')) {
    function auragold_carat_has_purity_for($conn): bool
    {
        return auragold_carat_has_split_purity($conn);
    }
}

if (!function_exists('auragold_carat_format_purity_display')) {
    function auragold_carat_format_purity_display($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $s = trim((string) $value);
        return $s === '' ? '-' : $s;
    }
}

if (!function_exists('auragold_carat_normalize_purity_input')) {
    function auragold_carat_normalize_purity_input($value): string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return '';
        }
        if (!is_numeric($s)) {
            return '';
        }
        return $s;
    }
}

if (!function_exists('auragold_carat_resolve_purity_for_context')) {
    /**
     * @param array<string,mixed> $row
     * @param string              $context sales|purchase|common|all
     */
    function auragold_carat_resolve_purity_for_context(array $row, string $context = 'common'): string
    {
        $ctx = strtolower(trim($context));
        if ($ctx === 'sale') {
            $ctx = 'sales';
        }
        if ($ctx === 'pur') {
            $ctx = 'purchase';
        }
        $legacy = trim((string) ($row['purity'] ?? ''));
        $sales = trim((string) ($row['purity_sales'] ?? ''));
        $purchase = trim((string) ($row['purity_purchase'] ?? ''));
        $common = trim((string) ($row['purity_common'] ?? ''));

        if ($ctx === 'sales') {
            return $sales !== '' ? $sales : ($common !== '' ? $common : $legacy);
        }
        if ($ctx === 'purchase') {
            return $purchase !== '' ? $purchase : ($common !== '' ? $common : $legacy);
        }
        if ($ctx === 'common') {
            return $common !== '' ? $common : $legacy;
        }
        return $common !== '' ? $common : ($sales !== '' ? $sales : ($purchase !== '' ? $purchase : $legacy));
    }
}

if (!function_exists('auragold_carat_apply_context_purity')) {
    /** @param array<string,mixed> $row */
    function auragold_carat_apply_context_purity(array $row, string $context = 'all'): array
    {
        $row['purity'] = auragold_carat_resolve_purity_for_context($row, $context);
        return $row;
    }
}

if (!function_exists('auragold_carat_purity_for_sql_filter')) {
    /** No row filter — each carat row holds all three purity values. */
    function auragold_carat_purity_for_sql_filter($conn, string $context, string $alias = ''): string
    {
        unset($conn, $context, $alias);
        return '';
    }
}

if (!function_exists('auragold_get_carat_list')) {
    /**
     * @param string $context sales|purchase|common|all — sets resolved `purity` for dropdowns
     * @param int $branchId optional branch scope (same as voucher metal tabs); 0 = session/default suffix
     */
    function auragold_get_carat_list($conn, string $context = 'all', int $branchId = 0): array
    {
        if (!$conn instanceof mysqli || !function_exists('getList')) {
            return [];
        }
        auragold_ensure_tbl_carat_purity_split($conn);
        $has_metal = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_carat', 'metal_id');
        $branchCol = $has_metal ? 'c.branch_id' : 'branch_id';
        $suffix = '';
        if ($branchId > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
            $suffix = auragold_master_list_sql_for_branch_id($conn, 'tbl_carat', $branchId, $branchCol);
        }
        if ($suffix === '' && function_exists('auragold_settings_main_branch_id')) {
            $mainBranch = (int) auragold_settings_main_branch_id();
            if ($mainBranch > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
                $suffix = auragold_master_list_sql_for_branch_id($conn, 'tbl_carat', $mainBranch, $branchCol);
            }
        }
        if ($suffix === '' && function_exists('auragold_master_list_sql_suffix')) {
            $suffix = auragold_master_list_sql_suffix($conn, 'tbl_carat', $branchCol);
        }
        $has_split = auragold_carat_has_split_purity($conn);
        $extraSplit = $has_split ? ', purity_sales, purity_purchase, purity_common' : '';
        $extraSplitAliased = $has_split ? ', c.purity_sales, c.purity_purchase, c.purity_common' : '';
        if ($has_metal) {
            $sql = 'SELECT c.id, c.name, c.purity, c.description, c.metal_id, m.display_name AS metal_name' . $extraSplitAliased
                . ' FROM tbl_carat c'
                . ' LEFT JOIN tbl_metal m ON m.id = c.metal_id AND m.status = 1'
                . ' WHERE c.status = 1 ' . $suffix
                . ' ORDER BY c.metal_id IS NULL, c.metal_id ASC, c.id ASC';
        } else {
            $sql = 'SELECT id, name, purity, description' . $extraSplit
                . ' FROM tbl_carat WHERE status = 1 ' . $suffix . ' ORDER BY id ASC';
        }
        $list = getList($sql);
        if (!is_array($list)) {
            $list = [];
        }
        if ($list === [] && $suffix !== '' && $branchId > 0) {
            $fallbackSuffix = '';
            if (function_exists('auragold_master_list_sql_suffix')) {
                $fallbackSuffix = auragold_master_list_sql_suffix($conn, 'tbl_carat', $branchCol);
            }
            if ($fallbackSuffix !== $suffix) {
                if ($has_metal) {
                    $sqlFb = 'SELECT c.id, c.name, c.purity, c.description, c.metal_id, m.display_name AS metal_name' . $extraSplitAliased
                        . ' FROM tbl_carat c'
                        . ' LEFT JOIN tbl_metal m ON m.id = c.metal_id AND m.status = 1'
                        . ' WHERE c.status = 1 ' . $fallbackSuffix
                        . ' ORDER BY c.metal_id IS NULL, c.metal_id ASC, c.id ASC';
                } else {
                    $sqlFb = 'SELECT id, name, purity, description' . $extraSplit
                        . ' FROM tbl_carat WHERE status = 1 ' . $fallbackSuffix . ' ORDER BY id ASC';
                }
                $listFb = getList($sqlFb);
                if (is_array($listFb) && $listFb !== []) {
                    $list = $listFb;
                }
            }
            if ($list === []) {
                if ($has_metal) {
                    $sqlAll = 'SELECT c.id, c.name, c.purity, c.description, c.metal_id, m.display_name AS metal_name' . $extraSplitAliased
                        . ' FROM tbl_carat c'
                        . ' LEFT JOIN tbl_metal m ON m.id = c.metal_id AND m.status = 1'
                        . ' WHERE c.status = 1'
                        . ' ORDER BY c.metal_id IS NULL, c.metal_id ASC, c.id ASC';
                } else {
                    $sqlAll = 'SELECT id, name, purity, description' . $extraSplit
                        . ' FROM tbl_carat WHERE status = 1 ORDER BY id ASC';
                }
                $listAll = getList($sqlAll);
                if (is_array($listAll)) {
                    $list = $listAll;
                }
            }
        }
        if (!is_array($list)) {
            return [];
        }
        $ctx = $context === '' ? 'all' : $context;
        foreach ($list as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($has_metal && (!isset($row['metal_name']) || $row['metal_name'] === null || $row['metal_name'] === '')) {
                $mid = isset($row['metal_id']) ? (int) $row['metal_id'] : 0;
                if ($mid > 0 && function_exists('getRecord')) {
                    $mr = @getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . $mid . ' AND status = 1 LIMIT 1');
                    if (is_array($mr)) {
                        $row['metal_name'] = trim((string) ($mr['display_name'] ?? ''));
                    }
                }
            }
            $list[$i] = auragold_carat_apply_context_purity($row, $ctx);
        }
        return $list;
    }
}
