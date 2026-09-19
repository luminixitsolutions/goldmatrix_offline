<?php
/**
 * Branch-scoped data: effective branch from session (working context, login branch, or tbl_branches login).
 * Use for filtering lists and validating access when tbl_* tables have branch_id.
 */
if (!function_exists('auragold_registry_main_branch_id_for_login')) {
    /**
     * First registry row with main_branch_id = 0 (the "Main" option uses login_branch_id 0 in the UI).
     */
    function auragold_registry_main_branch_id_for_login(): int {
        if (!function_exists('getRecordMaster')) {
            return 0;
        }
        $r = getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
        return ($r && !empty($r['id'])) ? (int) $r['id'] : 0;
    }
}

if (!function_exists('auragold_effective_branch_id')) {
    function auragold_effective_branch_id(): int {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        if (!empty($_SESSION['working_branch_id'])) {
            return (int) $_SESSION['working_branch_id'];
        }
        if (!empty($_SESSION['branch_id'])) {
            return (int) $_SESSION['branch_id'];
        }
        $src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
        if ($src === 'branch' && !empty($_SESSION['Admin']['id'])) {
            return (int) $_SESSION['Admin']['id'];
        }
        // Legacy: "Main" login posted login_branch_id 0, which did not set branch_id — scope to registry main row.
        if ($src === 'user' && !empty($_SESSION['user_id'])) {
            $mid = auragold_registry_main_branch_id_for_login();
            if ($mid > 0) {
                return $mid;
            }
        }
        return 0;
    }
}

if (!function_exists('auragold_branch_root_main_id_for_branch')) {
    /**
     * Top-level main tbl_branches.id for a row: the row itself when main_branch_id is 0, otherwise main_branch_id.
     */
    function auragold_branch_root_main_id_for_branch(int $branchId): int {
        if ($branchId <= 0 || !function_exists('getRecordMaster')) {
            return 0;
        }
        $row = @getRecordMaster(
            'SELECT id, IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = ' . (int) $branchId . ' AND status = 1 LIMIT 1'
        );
        if (!$row || empty($row['id'])) {
            return 0;
        }
        $mb = (int) ($row['mb'] ?? 0);
        if ($mb === 0) {
            return (int) $row['id'];
        }
        return $mb;
    }
}

if (!function_exists('auragold_resolve_branch_id_for_session')) {
    /**
     * When the session has a working/login branch (effective id &gt; 0), APIs must use that branch_id
     * and ignore forged client values. When effective is 0, the requested branch_id may be used (e.g. main office UI).
     */
    function auragold_resolve_branch_id_for_session(int $requestedFromClient): int {
        if (!function_exists('auragold_effective_branch_id')) {
            return $requestedFromClient;
        }
        $eff = (int) auragold_effective_branch_id();
        return $eff > 0 ? $eff : $requestedFromClient;
    }
}

if (!function_exists('auragold_tbl_has_column')) {
    function auragold_tbl_has_column($conn, string $table, string $column, bool $refresh = false): bool {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            return false;
        }
        static $cache = [];
        $cacheKey = spl_object_hash($conn) . ':' . $table . ':' . $column;
        if (!$refresh && array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        $cache[$cacheKey] = $ok;
        return $ok;
    }
}

if (!function_exists('auragold_ensure_sale_invoice_branch_id_column')) {
    /**
     * Adds tbl_sale_invoices.branch_id if missing (FK to tbl_branches.id).
     */
    function auragold_ensure_sale_invoice_branch_id_column($conn): bool {
        if (!auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE tbl_sale_invoices ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER created_by"
            );
        }
        return auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id');
    }
}

if (!function_exists('auragold_ensure_pos_sale_invoice_branch_id_column')) {
    /**
     * Adds tbl_pos_sale_invoices.branch_id if missing (FK to tbl_branches.id).
     */
    function auragold_ensure_pos_sale_invoice_branch_id_column($conn): bool {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoices'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }

            return false;
        }
        mysqli_free_result($chk);
        if (!auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE tbl_pos_sale_invoices ADD COLUMN branch_id INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER created_by"
            );
        }

        return auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id');
    }
}

if (!function_exists('auragold_branch_can_access_sale_invoice_row')) {
    /**
     * @param array $row tbl_sale_invoices row (must include branch_id if column exists)
     */
    function auragold_branch_can_access_sale_invoice_row(array $row): bool {
        $eff = auragold_effective_branch_id();
        if ($eff <= 0) {
            return true;
        }
        if (!array_key_exists('branch_id', $row)) {
            return true;
        }
        $bid = (int) ($row['branch_id'] ?? 0);
        if ($bid === 0) {
            return false;
        }
        return $bid === $eff;
    }
}

if (!function_exists('auragold_sale_invoices_branch_where_sql')) {
    /**
     * SQL AND clause for tbl_sale_invoices alias (e.g. si).
     * Main registry branch includes legacy rows with branch_id NULL/0 (same rules as customer ledger).
     */
    function auragold_sale_invoices_branch_where_sql($conn, string $alias = 'si'): string {
        $eff = auragold_effective_branch_id();
        if ($eff <= 0 || !auragold_ensure_sale_invoice_branch_id_column($conn)) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'si';
        }
        $eff = (int) $eff;
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
        }
        return " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
    }
}

if (!function_exists('auragold_sale_quotations_branch_where_sql')) {
    /**
     * SQL AND clause for tbl_sale_quotations alias (e.g. o).
     * Main registry branch includes legacy rows with branch_id NULL/0.
     */
    function auragold_sale_quotations_branch_where_sql($conn, string $alias = 'o'): string {
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'branch_id')) {
            return '';
        }
        $eff = (int) auragold_effective_branch_id();
        if ($eff <= 0) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'o';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
        }
        return " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
    }
}

if (!function_exists('auragold_purchase_invoices_branch_where_sql')) {
    /**
     * SQL AND clause for tbl_purchase_invoices alias (e.g. pi).
     */
    function auragold_purchase_invoices_branch_where_sql($conn, string $alias = 'pi'): string {
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id')) {
            return '';
        }
        $eff = (int) auragold_effective_branch_id();
        if ($eff <= 0) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'pi';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
        }
        return " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
    }
}

if (!function_exists('auragold_tbl_stock_branch_and_sql')) {
    /**
     * Branch filter for tbl_stock (closing stock, inventory value). Omits predicate when effective branch is unset.
     *
     * @param string $tableAlias e.g. 's' or '' for unqualified column names
     */
    function auragold_tbl_stock_branch_and_sql(mysqli $conn, string $tableAlias = ''): string {
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
            return '';
        }
        $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($eff <= 0) {
            return '';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        $col = $tableAlias !== '' ? preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias) . '.' : '';
        if ($main > 0 && $eff === $main) {
            return " AND ({$col}branch_id = {$eff} OR {$col}branch_id IS NULL OR {$col}branch_id = 0)";
        }
        return " AND COALESCE({$col}branch_id, 0) = {$eff}";
    }
}

if (!function_exists('auragold_settings_branch_id_valid')) {
    /**
     * @param int $id tbl_branches.id
     */
    function auragold_settings_branch_id_valid($id): bool {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        if (!function_exists('getRecordMaster')) {
            return false;
        }
        $r = @getRecordMaster('SELECT id FROM tbl_branches WHERE id = ' . $id . ' LIMIT 1');
        return $r !== null && !empty($r['id']);
    }
}

if (!function_exists('auragold_branch_name_map_by_ids')) {
    /**
     * Resolve tbl_branches.name from registry (master DB) for display when working DB has no tbl_branches copy.
     *
     * @param int[] $ids
     * @return array<int, string> id => name
     */
    function auragold_branch_name_map_by_ids(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
            return $v > 0;
        })));
        if ($ids === [] || !function_exists('getListMaster')) {
            return [];
        }
        $in   = implode(',', $ids);
        $rows = getListMaster('SELECT id, name FROM tbl_branches WHERE id IN (' . $in . ')');
        $m    = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $m[$id] = trim((string) ($r['name'] ?? ''));
            }
        }
        return $m;
    }
}

if (!function_exists('auragold_enrich_rows_branch_name_from_registry')) {
    /**
     * Sets branch_name from registry tbl_branches using branch_id on each row (mutates array).
     */
    function auragold_enrich_rows_branch_name_from_registry(array &$rows): void {
        if ($rows === []) {
            return;
        }
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) ($r['branch_id'] ?? 0);
        }
        $map = auragold_branch_name_map_by_ids($ids);
        foreach ($rows as &$r) {
            $bid = (int) ($r['branch_id'] ?? 0);
            if ($bid > 0 && !empty($map[$bid])) {
                $r['branch_name'] = $map[$bid];
            } else {
                $r['branch_name'] = $bid > 0 ? ('Branch #' . $bid) : 'N/A';
            }
        }
        unset($r);
    }
}

if (!function_exists('auragold_working_db_main_branch_id')) {
    /**
     * Main branch row (main_branch_id = 0) in the current working / operational DB.
     * Ids can differ from the central registry (e.g. registry main=1, branch DB main=47).
     */
    function auragold_working_db_main_branch_id(): int {
        static $cached = null;
        static $cached_for_db = '';
        global $conn;
        $db_key = '';
        if ($conn instanceof mysqli) {
            $db_key = (string) mysqli_get_server_info($conn) . '|';
            $db_res = @mysqli_query($conn, 'SELECT DATABASE() AS db');
            if ($db_res && ($db_row = mysqli_fetch_assoc($db_res))) {
                $db_key .= (string) ($db_row['db'] ?? '');
            }
            if ($db_res) {
                mysqli_free_result($db_res);
            }
        }
        if ($cached !== null && $cached_for_db === $db_key && $db_key !== '') {
            return $cached;
        }
        $cached = 0;
        $cached_for_db = $db_key;
        if (!function_exists('getRecord')) {
            return 0;
        }
        $r = @getRecord('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
        $cached = ($r && !empty($r['id'])) ? (int) $r['id'] : 0;
        return $cached;
    }
}

if (!function_exists('auragold_normalize_branch_scope_for_working_db')) {
    /**
     * When session/registry scope is the registry main id but ledger rows live under the working DB main id, remap scope.
     */
    function auragold_normalize_branch_scope_for_working_db(int $scope_branch_id): int {
        if ($scope_branch_id <= 0) {
            return $scope_branch_id;
        }
        $registry_main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        $working_main = auragold_working_db_main_branch_id();
        if ($registry_main > 0 && $scope_branch_id === $registry_main && $working_main > 0 && $working_main !== $registry_main) {
            return $working_main;
        }
        return $scope_branch_id;
    }
}

if (!function_exists('auragold_customer_ledger_branch_is_main_scope')) {
    function auragold_customer_ledger_branch_is_main_scope(int $scope_branch_id): bool {
        if ($scope_branch_id <= 0) {
            return false;
        }
        $registry_main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        $working_main = auragold_working_db_main_branch_id();
        if ($registry_main > 0 && $scope_branch_id === $registry_main) {
            return true;
        }
        if ($working_main > 0 && $scope_branch_id === $working_main) {
            return true;
        }
        if (function_exists('auragold_branch_root_main_id_for_branch')) {
            $root = (int) auragold_branch_root_main_id_for_branch($scope_branch_id);
            if ($root > 0 && $scope_branch_id === $root) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('auragold_account_ledger_resolved_branch_ids')) {
    /**
     * Branch id list for ledger queries — same rules as accountledger-report.php (working branch, then tree root / session main).
     *
     * @return list<int>
     */
    function auragold_account_ledger_resolved_branch_ids(): array {
        $al_main_branch_id = function_exists('auragold_branch_stock_transfer_tree_root_id')
            ? (int) auragold_branch_stock_transfer_tree_root_id()
            : 0;
        if ($al_main_branch_id <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $al_main_branch_id = (int) auragold_settings_main_branch_id();
        }
        $tr_effective_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($tr_effective_branch_id <= 0 && $al_main_branch_id <= 0 && session_status() === PHP_SESSION_ACTIVE) {
            $al_wb = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
            if ($al_wb > 0 && function_exists('auragold_branch_root_main_id_for_branch')) {
                $al_main_branch_id = (int) auragold_branch_root_main_id_for_branch($al_wb);
            }
        }
        if ($tr_effective_branch_id > 0) {
            $ids = [$tr_effective_branch_id];
        } elseif ($al_main_branch_id > 0) {
            $ids = [$al_main_branch_id];
        } else {
            return [];
        }
        if (function_exists('auragold_normalize_branch_scope_for_working_db')) {
            foreach ($ids as $k => $id) {
                $ids[$k] = auragold_normalize_branch_scope_for_working_db((int) $id);
            }
            $ids = array_values(array_unique(array_filter($ids)));
        }
        return $ids;
    }
}

if (!function_exists('auragold_account_ledger_branch_scope_sql')) {
    /**
     * SQL AND fragment for tbl_customer_ledger branch scope — matches accountledger-report.php legacy NULL/0 on main branch.
     */
    function auragold_account_ledger_branch_scope_sql(string $columnPrefix = ''): string {
        global $conn;
        $col = ($columnPrefix !== '' ? rtrim($columnPrefix, '.') . '.' : '');
        if (!($conn instanceof mysqli) || !function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id')) {
            return '';
        }
        $ids = auragold_account_ledger_resolved_branch_ids();
        if ($ids === []) {
            return '';
        }
        $main_for_legacy = function_exists('auragold_working_db_main_branch_id') ? (int) auragold_working_db_main_branch_id() : 0;
        if ($main_for_legacy <= 0) {
            $main_for_legacy = function_exists('auragold_branch_stock_transfer_tree_root_id')
                ? (int) auragold_branch_stock_transfer_tree_root_id()
                : 0;
        }
        if ($main_for_legacy <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $main_for_legacy = (int) auragold_settings_main_branch_id();
        }
        if ($main_for_legacy > 0 && function_exists('auragold_normalize_branch_scope_for_working_db')) {
            $main_for_legacy = auragold_normalize_branch_scope_for_working_db($main_for_legacy);
        }
        $id_list = implode(',', array_map('intval', $ids));
        $includes_main_legacy = ($main_for_legacy > 0 && in_array($main_for_legacy, $ids, true));
        if ($includes_main_legacy) {
            return " AND ({$col}branch_id IN ($id_list) OR {$col}branch_id IS NULL OR {$col}branch_id = 0)";
        }
        return " AND {$col}branch_id IN ($id_list)";
    }
}

if (!function_exists('auragold_account_ledger_party_cl_balance')) {
    /**
     * Closing amount balance for a party — same rules as accountledger-report.php View All Ledger (SUM debit − credit).
     * Tries customer_name first (ledger report filter), then customer_id; retries branch scope then no branch.
     *
     * @return array{found: bool, balance_amount: float, balance_gold: float, balance_silver: float, balance_diamond: float, balance_gemstone: float}
     */
    function auragold_account_ledger_party_cl_balance(int $customer_id, string $customer_name, bool $has_balance_gold_pure = false): array {
        global $conn;
        $empty = [
            'found' => false,
            'balance_amount' => 0.0,
            'balance_gold' => 0.0,
            'balance_silver' => 0.0,
            'balance_diamond' => 0.0,
            'balance_gemstone' => 0.0,
        ];
        if (!($conn instanceof mysqli) || !function_exists('getRecord')) {
            return $empty;
        }

        $party_scopes = [];
        $trim_name = trim($customer_name);
        if ($trim_name !== '') {
            $esc_name = mysqli_real_escape_string($conn, $trim_name);
            $party_scopes[] = "LOWER(TRIM(customer_name)) = LOWER(TRIM('$esc_name'))";
        }
        if ($customer_id > 0) {
            $party_scopes[] = 'customer_id = ' . (int) $customer_id;
        }
        if ($party_scopes === []) {
            return $empty;
        }

        $branch_sqls = [];
        if (function_exists('auragold_account_ledger_branch_scope_sql')) {
            $scoped = auragold_account_ledger_branch_scope_sql('');
            if ($scoped !== '') {
                $branch_sqls[] = $scoped;
            }
        }
        if (function_exists('auragold_working_db_main_branch_id')) {
            $wm = (int) auragold_working_db_main_branch_id();
            if ($wm > 0 && function_exists('auragold_normalize_branch_scope_for_working_db')) {
                $wm = auragold_normalize_branch_scope_for_working_db($wm);
            }
            if ($wm > 0) {
                $legacy = ' AND (branch_id = ' . $wm . ' OR branch_id IS NULL OR branch_id = 0)';
                if (!in_array($legacy, $branch_sqls, true)) {
                    $branch_sqls[] = $legacy;
                }
            }
        }
        $branch_sqls[] = '';

        $ledger_excl_pb = " AND COALESCE(transaction_type,'') <> 'previous_balance_payment'";
        $hedging_metal_sql = "LOWER(COALESCE(description,'')) LIKE '%(hedging)%'";
        $payment_metal_sql = "(COALESCE(transaction_type,'') = 'payment' AND (ABS(COALESCE(debit_gold,0)) + ABS(COALESCE(credit_gold,0)) + ABS(COALESCE(debit_silver,0)) + ABS(COALESCE(credit_silver,0)) > 0.00001))";
        $rv_pv_metal_sql = "(COALESCE(transaction_type,'') IN ('receipt_voucher','sale_receipt_voucher','payment_voucher') AND (ABS(COALESCE(debit_gold,0)) + ABS(COALESCE(credit_gold,0)) + ABS(COALESCE(debit_silver,0)) + ABS(COALESCE(credit_silver,0)) > 0.00001))";
        $ledger_metal_view_sql = "($hedging_metal_sql OR $payment_metal_sql OR $rv_pv_metal_sql)";

        foreach ($party_scopes as $party_scope) {
            foreach ($branch_sqls as $brLedgerAnd) {
                $cnt = getRecord("SELECT COUNT(*) AS n FROM tbl_customer_ledger WHERE status = 1 AND ($party_scope) $ledger_excl_pb $brLedgerAnd");
                if ((int) ($cnt['n'] ?? 0) <= 0) {
                    continue;
                }

                $amount_row = getRecord("
                    SELECT COALESCE(SUM(debit_amount - credit_amount), 0) AS net_amt
                    FROM tbl_customer_ledger
                    WHERE status = 1 AND ($party_scope) $ledger_excl_pb $brLedgerAnd
                ");
                $balance_amount = (float) ($amount_row['net_amt'] ?? 0);

                $metal_cl_row = getRecord("
                    SELECT
                        COALESCE(SUM(debit_gold - credit_gold), 0) AS net_gold,
                        COALESCE(SUM(debit_silver - credit_silver), 0) AS net_silver
                        " . ($has_balance_gold_pure ? ", COALESCE(SUM(debit_gold_pure - credit_gold_pure), 0) AS net_gold_pure" : "") . "
                    FROM tbl_customer_ledger
                    WHERE status = 1 AND ($party_scope) AND ($ledger_metal_view_sql)
                    $brLedgerAnd
                ");
                $balance_gold = $has_balance_gold_pure && isset($metal_cl_row['net_gold_pure'])
                    ? (float) $metal_cl_row['net_gold_pure']
                    : (float) ($metal_cl_row['net_gold'] ?? 0);
                $balance_silver = (float) ($metal_cl_row['net_silver'] ?? 0);

                $diamond_select = '';
                if (function_exists('auragold_tbl_has_column')) {
                    if (auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'balance_diamond')) {
                        $diamond_select .= ', balance_diamond';
                    }
                    if (auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'balance_gemstone')) {
                        $diamond_select .= ', balance_gemstone';
                    }
                }
                $last_row = getRecord("
                    SELECT balance_amount $diamond_select
                    FROM tbl_customer_ledger
                    WHERE status = 1 AND ($party_scope)
                    $brLedgerAnd
                    ORDER BY id DESC
                    LIMIT 1
                ");

                return [
                    'found' => true,
                    'balance_amount' => $balance_amount,
                    'balance_gold' => $balance_gold,
                    'balance_silver' => $balance_silver,
                    'balance_diamond' => (float) ($last_row['balance_diamond'] ?? 0),
                    'balance_gemstone' => (float) ($last_row['balance_gemstone'] ?? 0),
                ];
            }
        }

        return $empty;
    }
}

if (!function_exists('auragold_customer_ledger_branch_and_sql')) {
    /**
     * SQL AND fragment for tbl_customer_ledger branch scope (includes legacy NULL/0 rows on main branch).
     */
    function auragold_customer_ledger_branch_and_sql(int $scope_branch_id): string {
        if ($scope_branch_id <= 0) {
            return '';
        }
        global $conn;
        if (!($conn instanceof mysqli) || !function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id')) {
            return ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
        }
        $scope_branch_id = auragold_normalize_branch_scope_for_working_db($scope_branch_id);
        if (auragold_customer_ledger_branch_is_main_scope($scope_branch_id)) {
            return ' AND (branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }
        return ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
    }
}

if (!function_exists('auragold_settings_main_branch_id')) {
    /**
     * First main branch (main_branch_id = 0), for defaulting legacy settings.
     */
    function auragold_settings_main_branch_id(): int {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!function_exists('getRecordMaster')) {
            $cached = 0;
            return 0;
        }
        $main = @getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
        $cached = ($main && !empty($main['id'])) ? (int) $main['id'] : 0;
        return $cached;
    }
}

if (!function_exists('auragold_branch_stock_transfer_tree_root_id')) {
    /**
     * Root main branch for stock transfer scope: follows session working/login branch so users in
     * e.g. Gold Matrix (db goldmatrix_gm_1) are not tied to whichever main sorts first by id in the registry.
     * Falls back to auragold_settings_main_branch_id() when there is no effective branch.
     */
    function auragold_branch_stock_transfer_tree_root_id(): int {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = 0;
        if (function_exists('auragold_effective_branch_id') && function_exists('auragold_branch_root_main_id_for_branch')) {
            $eff = (int) auragold_effective_branch_id();
            if ($eff > 0) {
                $cached = auragold_branch_root_main_id_for_branch($eff);
            }
        }
        if ($cached <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $cached = (int) auragold_settings_main_branch_id();
        }
        return $cached;
    }
}

if (!function_exists('auragold_branch_is_main_or_sub_of_settings_main')) {
    /**
     * True when the branch is the session stock-transfer tree root or a sub-branch linked via main_branch_id.
     * Other top-level mains (different companies / DB contexts) are excluded.
     */
    function auragold_branch_is_main_or_sub_of_settings_main(int $branchId): bool {
        if ($branchId <= 0) {
            return false;
        }
        if (!function_exists('getRecordMaster')) {
            return true;
        }
        $main = function_exists('auragold_branch_stock_transfer_tree_root_id')
            ? (int) auragold_branch_stock_transfer_tree_root_id()
            : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);
        if ($main <= 0) {
            return true;
        }
        if ($branchId === $main) {
            return true;
        }
        $row = @getRecordMaster(
            'SELECT IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = ' . (int) $branchId . ' AND status = 1 LIMIT 1'
        );
        if (!$row) {
            return false;
        }
        return (int) ($row['mb'] ?? 0) === $main;
    }
}

if (!function_exists('auragold_filter_branches_for_login_scope')) {
    /**
     * Branch filter dropdown: login main + its sub-branches only (excludes other top-level mains).
     * Sub-branch login: only that sub-branch.
     *
     * @return list<array{id:int,name:string}>
     */
    function auragold_filter_branches_for_login_scope(): array {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $effective = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        $tree_root = function_exists('auragold_branch_stock_transfer_tree_root_id')
            ? (int) auragold_branch_stock_transfer_tree_root_id()
            : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);

        $sub_login = false;
        $sub_id    = 0;
        $sub_name  = '';
        if ($effective > 0 && function_exists('getRecordMaster')) {
            $login_br = getRecordMaster(
                'SELECT id, name, IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = '
                . $effective . ' AND status = 1 LIMIT 1'
            );
            if ($login_br) {
                $is_registry_sub = (int) ($login_br['mb'] ?? 0) > 0;
                $is_tree_sub     = ($tree_root > 0 && $effective !== $tree_root);
                if ($is_registry_sub || $is_tree_sub) {
                    $sub_login = true;
                    $sub_id    = $effective;
                    $sub_name  = trim((string) ($login_br['name'] ?? ''));
                }
            }
        }

        if ($sub_login && $sub_id > 0) {
            $cached = [[
                'id'   => $sub_id,
                'name' => $sub_name !== '' ? $sub_name : ('Branch #' . $sub_id),
            ]];
            return $cached;
        }

        if ($tree_root > 0 && function_exists('getListMaster')) {
            $rows = getListMaster(
                'SELECT id, name FROM tbl_branches WHERE status = 1 AND (id = ' . $tree_root
                . ' OR IFNULL(main_branch_id, 0) = ' . $tree_root . ') ORDER BY name ASC'
            );
            $cached = is_array($rows) ? $rows : [];
            return $cached;
        }

        if (function_exists('getListMaster')) {
            $rows = getListMaster('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
            $cached = is_array($rows) ? $rows : [];
            return $cached;
        }

        $cached = [];
        return $cached;
    }
}

if (!function_exists('auragold_sanitize_adv_branch_for_login_scope')) {
    /** Ignore adv_branch values outside login main + sub-branches. */
    function auragold_sanitize_adv_branch_for_login_scope(int $advBranch): int {
        if ($advBranch <= 0) {
            return 0;
        }
        foreach (auragold_filter_branches_for_login_scope() as $row) {
            if ((int) ($row['id'] ?? 0) === $advBranch) {
                return $advBranch;
            }
        }
        return 0;
    }
}

if (!function_exists('auragold_settings_branch_id')) {
    /**
     * Branch context for Set Software settings screens (barcode, voucher, invoice print, bill series).
     * Logged-in branch users are always scoped to their branch; super-admin may use ?branch_id= or POST settings_branch_id.
     *
     * @return int tbl_branches.id (0 if none)
     */
    function auragold_settings_branch_id(): int {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $eff = auragold_effective_branch_id();
        if ($eff > 0) {
            $cached = $eff;
            return $cached;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_branch_id'])) {
            $p = (int) $_POST['settings_branch_id'];
            if ($p > 0 && auragold_settings_branch_id_valid($p)) {
                $cached = $p;
                return $cached;
            }
        }
        if (isset($_GET['branch_id'])) {
            $g = (int) $_GET['branch_id'];
            if ($g > 0 && auragold_settings_branch_id_valid($g)) {
                $cached = $g;
                return $cached;
            }
        }

        $mid = auragold_settings_main_branch_id();
        $cached = $mid;
        return $cached;
    }
}

if (!function_exists('auragold_index_exists_on_table')) {
    function auragold_index_exists_on_table($conn, string $table, string $indexName): bool {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $indexName = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
        if ($table === '' || $indexName === '') {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '" . mysqli_real_escape_string($conn, $indexName) . "'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_ensure_branch_id_on_settings_tables')) {
    /**
     * Adds branch_id to barcode / voucher / invoice print settings tables, backfills from main branch, adjusts unique keys.
     * Safe to call multiple times per request (static guard).
     */
    function auragold_ensure_branch_id_on_settings_tables($conn): void {
        static $done = false;
        if ($done || !$conn instanceof mysqli) {
            return;
        }
        $done = true;

        $mid = auragold_settings_main_branch_id();
        if ($mid <= 0) {
            return;
        }

        // --- tbl_barcode_settings
        $t = 'tbl_barcode_settings';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            if (!auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `branch_id` INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER `id`");
                @mysqli_query($conn, "ALTER TABLE `$t` ADD KEY `idx_barcode_settings_branch` (`branch_id`)");
            }
            if (auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, 'UPDATE `' . $t . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
            }
        } elseif ($chk) {
            mysqli_free_result($chk);
        }

        // --- tbl_voucher_settings
        $t = 'tbl_voucher_settings';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            if (!auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `branch_id` INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER `id`");
            }
            if (auragold_tbl_has_column($conn, $t, 'branch_id')) {
                // Drop legacy NULL-branch rows when this branch already has a row for the same metal (avoids uk_branch_metal duplicate).
                @mysqli_query(
                    $conn,
                    'DELETE v1 FROM `' . $t . '` v1
                    INNER JOIN `' . $t . '` v2 ON v1.metal_wise = v2.metal_wise AND v2.branch_id = ' . (int) $mid . '
                    WHERE v1.branch_id IS NULL'
                );
                @mysqli_query($conn, 'UPDATE `' . $t . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
                if (auragold_index_exists_on_table($conn, $t, 'uk_metal_wise')) {
                    @mysqli_query($conn, 'ALTER TABLE `' . $t . '` DROP INDEX `uk_metal_wise`');
                }
                if (!auragold_index_exists_on_table($conn, $t, 'uk_branch_metal')) {
                    @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD UNIQUE KEY `uk_branch_metal` (`branch_id`, `metal_wise`)');
                }
                if (!auragold_index_exists_on_table($conn, $t, 'idx_voucher_settings_branch')) {
                    @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD KEY `idx_voucher_settings_branch` (`branch_id`)');
                }
            }
        } elseif ($chk) {
            mysqli_free_result($chk);
        }

        // --- tbl_invoice_print_settings
        $t = 'tbl_invoice_print_settings';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            if (!auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `branch_id` INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER `id`");
            }
            if (auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, 'UPDATE `' . $t . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
                if (auragold_index_exists_on_table($conn, $t, 'setting_type_key')) {
                    @mysqli_query($conn, 'ALTER TABLE `' . $t . '` DROP INDEX `setting_type_key`');
                }
                if (!auragold_index_exists_on_table($conn, $t, 'uk_branch_setting_type_key')) {
                    @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD UNIQUE KEY `uk_branch_setting_type_key` (`branch_id`, `setting_type`, `setting_key`)');
                }
                if (!auragold_index_exists_on_table($conn, $t, 'idx_invoice_print_branch')) {
                    @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD KEY `idx_invoice_print_branch` (`branch_id`)');
                }
            }
        } elseif ($chk) {
            mysqli_free_result($chk);
        }

        // --- tbl_bill_series (may already exist from prior migrations)
        $t = 'tbl_bill_series';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            if (auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, 'UPDATE `' . $t . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
            }
        } elseif ($chk) {
            mysqli_free_result($chk);
        }
    }
}

if (!function_exists('auragold_ensure_table_branch_id_column')) {
    /**
     * Ensures tbl_*.branch_id exists; backfills NULLs with main branch. Adds a non-unique index on branch_id.
     */
    function auragold_ensure_table_branch_id_column($conn, string $table, string $afterColumn = 'id'): bool {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $afterColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $afterColumn);
        if ($table === '') {
            return false;
        }
        if (auragold_tbl_has_column($conn, $table, 'branch_id')) {
            $mid = auragold_settings_main_branch_id();
            if ($mid > 0) {
                @mysqli_query($conn, 'UPDATE `' . $table . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
            }
            return true;
        }
        $after = ($afterColumn !== '') ? (" AFTER `" . $afterColumn . "`") : '';
        @mysqli_query(
            $conn,
            "ALTER TABLE `" . $table . "` ADD COLUMN `branch_id` INT NULL DEFAULT NULL COMMENT 'FK tbl_branches.id'" . $after
        );
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return false;
        }
        @mysqli_query($conn, "ALTER TABLE `" . $table . "` ADD KEY `idx_branch_id` (`branch_id`)");
        $mid = auragold_settings_main_branch_id();
        if ($mid > 0) {
            @mysqli_query($conn, 'UPDATE `' . $table . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
        }
        return true;
    }
}

if (!function_exists('auragold_transaction_header_branch_id')) {
    /**
     * Branch id for new/edited document headers: effective branch, else main branch (super-admin default).
     */
    function auragold_transaction_header_branch_id(): int {
        $eff = auragold_effective_branch_id();
        if ($eff > 0) {
            return $eff;
        }
        $m = auragold_settings_main_branch_id();
        return $m > 0 ? $m : 0;
    }
}

if (!function_exists('auragold_effective_branch_list_scope_sql')) {
    /**
     * AND fragment for listing/resolving documents by branch (Material Issue/Receive, search, etc.).
     * Sub-branch sessions: only that branch_id. Main-branch session: that id plus legacy NULL/0 rows.
     */
    function auragold_effective_branch_list_scope_sql(mysqli $conn, string $table): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return '';
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return '';
        }
        $eff = auragold_effective_branch_id();
        if ($eff <= 0) {
            return '';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return ' AND (branch_id = ' . (int) $eff . ' OR branch_id IS NULL OR branch_id = 0)';
        }
        return ' AND branch_id = ' . (int) $eff;
    }
}

if (!function_exists('auragold_branch_require_document_access')) {
    /**
     * Branch users may only open documents for their branch (legacy rows with branch_id NULL/0 are allowed).
     *
     * @throws Exception
     */
    function auragold_branch_require_document_access($conn, string $table, int $rowId, string $idColumn = 'id'): void {
        if ($rowId <= 0 || !$conn instanceof mysqli) {
            return;
        }
        $eff = auragold_effective_branch_id();
        if ($eff <= 0 || !auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return;
        }
        $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $idColumn);
        if ($idColumn === '') {
            $idColumn = 'id';
        }
        $row = getRecord('SELECT branch_id FROM `' . $table . '` WHERE `' . $idColumn . '` = ' . (int) $rowId . ' LIMIT 1');
        if (!$row) {
            throw new Exception('Record not found.');
        }
        $bid = (int) ($row['branch_id'] ?? 0);
        if ($bid > 0 && $bid !== $eff) {
            throw new Exception('This document belongs to another branch.');
        }
    }
}

if (!function_exists('auragold_sql_and_branch_scope')) {
    /**
     * SQL AND fragment for listing queries (branch login filters to own branch).
     *
     * @param string $alias Table alias (e.g. sq)
     */
    function auragold_sql_and_branch_scope($conn, string $table, string $alias): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $eff = auragold_effective_branch_id();
        if ($eff <= 0 || !auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return '';
        }
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($alias === '') {
            return '';
        }
        return ' AND ' . $alias . '.branch_id = ' . (int) $eff . ' ';
    }
}

if (!function_exists('auragold_metal_carat_shared_master_tables')) {
    /** Master tables whose rows are shared by branch_id when branches use the same operational database. */
    function auragold_metal_carat_shared_master_tables(): array {
        return ['tbl_metal', 'tbl_carat'];
    }
}

if (!function_exists('auragold_branch_operational_db_name')) {
    /** tbl_branches.db_name for a registry branch id, or DB_NAME when unset. */
    function auragold_branch_operational_db_name(int $branchId): string {
        if ($branchId <= 0) {
            return defined('DB_NAME') ? (string) DB_NAME : '';
        }
        if (function_exists('getRecordMaster')) {
            $row = @getRecordMaster(
                'SELECT db_name FROM tbl_branches WHERE id = ' . (int) $branchId . ' LIMIT 1'
            );
            $db = trim((string) ($row['db_name'] ?? ''));
            if ($db !== '') {
                return $db;
            }
        }
        return defined('DB_NAME') ? (string) DB_NAME : '';
    }
}

if (!function_exists('auragold_branches_share_operational_database')) {
    function auragold_branches_share_operational_database(int $branchA, int $branchB): bool {
        if ($branchA <= 0 || $branchB <= 0 || $branchA === $branchB) {
            return $branchA > 0 && $branchA === $branchB;
        }
        $dbA = auragold_branch_operational_db_name($branchA);
        $dbB = auragold_branch_operational_db_name($branchB);
        return $dbA !== '' && $dbB !== '' && strcasecmp($dbA, $dbB) === 0;
    }
}

if (!function_exists('auragold_metal_carat_master_branch_id')) {
    /**
     * Branch id used to read tbl_metal / tbl_carat when multiple registry branches share one database.
     * Sub-branches reuse the main branch master rows (same ids); no duplicate tbl_metal rows are created.
     */
    function auragold_metal_carat_master_branch_id(int $scopeBranchId): int {
        if ($scopeBranchId <= 0) {
            return 0;
        }
        $root = function_exists('auragold_branch_root_main_id_for_branch')
            ? (int) auragold_branch_root_main_id_for_branch($scopeBranchId)
            : $scopeBranchId;
        if ($root <= 0) {
            $root = $scopeBranchId;
        }
        if ($root !== $scopeBranchId && auragold_branches_share_operational_database($scopeBranchId, $root)) {
            return $root;
        }
        return $scopeBranchId;
    }
}

if (!function_exists('auragold_master_list_sql_suffix')) {
    /**
     * Append to WHERE clauses on master tables (tbl_* managed under Masters screen).
     * Ensures branch_id column exists; when the user has a branch context, lists only that branch.
     *
     * @param string $branchColumnSql e.g. "branch_id", "uc.branch_id" (letters, numbers, underscore, dot only)
     * @return string e.g. " AND branch_id = 3 " or ""
     */
    function auragold_master_list_sql_suffix($conn, string $table, string $branchColumnSql = ''): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return '';
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return '';
        }
        $eff = auragold_effective_branch_id();
        if ($eff <= 0) {
            return '';
        }
        $scopeBranchId = (int) $eff;
        if (in_array($table, auragold_metal_carat_shared_master_tables(), true)
            && function_exists('auragold_metal_carat_master_branch_id')) {
            $scopeBranchId = auragold_metal_carat_master_branch_id($scopeBranchId);
            if ($scopeBranchId <= 0) {
                return '';
            }
        }
        $col = $branchColumnSql !== '' ? $branchColumnSql : 'branch_id';
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $col)) {
            $col = 'branch_id';
        }
        return ' AND ' . $col . ' = ' . (int) $scopeBranchId . ' ';
    }
}

if (!function_exists('auragold_master_list_sql_for_branch_id')) {
    /**
     * Filter a master list by an explicit branch (e.g. voucher-type branch dropdown when HQ has no effective branch).
     *
     * @param string $branchColumnSql e.g. "branch_id", "uc.branch_id"
     * @return string e.g. " AND branch_id = 3 " or ""
     */
    function auragold_master_list_sql_for_branch_id($conn, string $table, int $branchId, string $branchColumnSql = ''): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || $branchId <= 0) {
            return '';
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return '';
        }
        $scopeBranchId = (int) $branchId;
        if (in_array($table, auragold_metal_carat_shared_master_tables(), true)
            && function_exists('auragold_metal_carat_master_branch_id')) {
            $scopeBranchId = auragold_metal_carat_master_branch_id($scopeBranchId);
            if ($scopeBranchId <= 0) {
                return '';
            }
        }
        $col = $branchColumnSql !== '' ? $branchColumnSql : 'branch_id';
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $col)) {
            $col = 'branch_id';
        }
        return ' AND ' . $col . ' = ' . (int) $scopeBranchId . ' ';
    }
}

if (!function_exists('auragold_voucher_type_settings_resolve_branch_id')) {
    /**
     * Target branch for voucher-type screen saves (metal/tax alloc, field visibility, payment buttons).
     * Branch logins are locked to their branch; HQ passes branch_id in POST/GET.
     */
    function auragold_voucher_type_settings_resolve_branch_id($postedBranchId): int {
        $posted = (int) $postedBranchId;
        $eff    = auragold_effective_branch_id();
        if ($eff > 0) {
            return $eff;
        }
        if ($posted > 0 && auragold_settings_branch_id_valid($posted)) {
            return $posted;
        }
        return auragold_transaction_header_branch_id();
    }
}

if (!function_exists('auragold_ensure_voucher_type_child_tables_branch_scope')) {
    /**
     * Adds branch_id to voucher-type satellite tables and unique keys (voucher_type_id + branch_id + …).
     */
    function auragold_ensure_voucher_type_child_tables_branch_scope($conn): void {
        static $done = false;
        if ($done || !$conn instanceof mysqli) {
            return;
        }
        $done = true;

        $mid = auragold_settings_main_branch_id();

        $specs = [
            'tbl_voucher_metal_allocations' => [
                'uniq' => ['name' => 'uk_vt_branch_metal', 'cols' => ['voucher_type_id', 'branch_id', 'metal_id']],
            ],
            'tbl_voucher_tax_allocations' => [
                'uniq' => ['name' => 'uk_vt_branch_tax', 'cols' => ['voucher_type_id', 'branch_id', 'tax_id']],
            ],
            'tbl_voucher_field_visibility' => [
                'uniq' => ['name' => 'uk_vt_branch_fv', 'cols' => ['voucher_type_id', 'branch_id']],
            ],
        ];

        foreach ($specs as $t => $meta) {
            $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
            if (!$chk || mysqli_num_rows($chk) === 0) {
                if ($chk) {
                    mysqli_free_result($chk);
                }
                continue;
            }
            mysqli_free_result($chk);

            auragold_ensure_table_branch_id_column($conn, $t, 'id');
            if ($mid > 0 && auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, 'UPDATE `' . $t . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
            }
            $u = $meta['uniq'];
            if (!auragold_index_exists_on_table($conn, $t, $u['name'])) {
                $colList = '`' . implode('`,`', $u['cols']) . '`';
                @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD UNIQUE KEY `' . $u['name'] . '` (' . $colList . ')');
            }
        }

        $t = 'tbl_voucher_payment_buttons';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            // This table has no `id` column: PK is voucher_type_id (see schema / INSERT in ajax/voucher-type.php).
            auragold_ensure_table_branch_id_column($conn, $t, 'voucher_type_id');
            if ($mid > 0 && auragold_tbl_has_column($conn, $t, 'branch_id')) {
                @mysqli_query($conn, 'UPDATE `' . $t . '` SET branch_id = ' . (int) $mid . ' WHERE branch_id IS NULL');
            }
            if (auragold_tbl_has_column($conn, $t, 'branch_id') && !auragold_index_exists_on_table($conn, $t, 'uk_vt_branch_pay')) {
                @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD UNIQUE KEY `uk_vt_branch_pay` (`voucher_type_id`, `branch_id`)');
            }
        } elseif ($chk) {
            mysqli_free_result($chk);
        }
    }
}

if (!function_exists('auragold_bill_series_row_for_voucher_type')) {
    /**
     * Load tbl_bill_series row for a voucher type for the current branch (effective branch, else main).
     * Falls back to main-branch series when the branch has no row (legacy / not configured yet).
     *
     * @return array<string,mixed>|null
     */
    function auragold_bill_series_row_for_voucher_type($conn, $voucherTypeId) {
        $voucherTypeId = (int) $voucherTypeId;
        if ($voucherTypeId < 1 || !$conn instanceof mysqli) {
            return null;
        }
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_bill_series', 'branch_id')) {
            return getRecord(
                'SELECT prefix, suffix, start_count FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = ' . $voucherTypeId . ' LIMIT 1'
            );
        }
        $bid = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($bid <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $bid = (int) auragold_settings_main_branch_id();
        }
        if ($bid <= 0) {
            return getRecord(
                'SELECT prefix, suffix, start_count FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = ' . $voucherTypeId . ' LIMIT 1'
            );
        }
        $row = getRecord(
            'SELECT prefix, suffix, start_count FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = ' . $voucherTypeId
            . ' AND branch_id = ' . $bid . ' LIMIT 1'
        );
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if (!$row && $main > 0 && $main !== $bid) {
            $row = getRecord(
                'SELECT prefix, suffix, start_count FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = ' . $voucherTypeId
                . ' AND branch_id = ' . $main . ' LIMIT 1'
            );
        }
        return $row;
    }
}

if (!function_exists('auragold_master_branch_id_for_writes')) {
    /**
     * branch_id value for INSERT/UPDATE on master tables (same rules as document headers).
     */
    function auragold_master_branch_id_for_writes($conn, string $table): int {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return auragold_transaction_header_branch_id();
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        return auragold_transaction_header_branch_id();
    }
}

if (!function_exists('auragold_master_can_mutate_row')) {
    /**
     * Branch users may only change master rows belonging to their branch (HQ / eff<=0: all rows).
     */
    function auragold_master_can_mutate_row($conn, string $table, int $id): bool {
        if (!$conn instanceof mysqli || $id <= 0) {
            return false;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return true;
        }
        $eff = auragold_effective_branch_id();
        if ($eff <= 0) {
            return true;
        }
        if (!function_exists('getRecord')) {
            return false;
        }
        $row = getRecord('SELECT branch_id FROM `' . $table . '` WHERE id = ' . (int) $id . ' LIMIT 1');
        if (!$row) {
            return false;
        }
        $bid = (int) ($row['branch_id'] ?? 0);
        if ($bid <= 0) {
            $bid = auragold_settings_main_branch_id();
        }
        return $bid === $eff;
    }
}

if (!function_exists('auragold_copy_master_rows_preserve_id_branch')) {
    /**
     * Copy master rows from one branch_id to another keeping the same `id` (and metal_id on carat).
     * Used for tbl_metal / tbl_carat so product and stock FKs stay aligned with the main branch.
     *
     * @return int Rows inserted, or -1 on failure
     */
    function auragold_copy_master_rows_preserve_id_branch($conn, string $table, int $fromBranchId, int $toBranchId): int {
        if (!$conn instanceof mysqli) {
            return -1;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || $fromBranchId <= 0 || $toBranchId <= 0 || $fromBranchId === $toBranchId) {
            return 0;
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return 0;
        }
        $rs = mysqli_query($conn, 'SHOW COLUMNS FROM `' . $table . '`');
        $fields    = [];
        $selectSql = [];
        if (!$rs) {
            return -1;
        }
        while ($row = mysqli_fetch_assoc($rs)) {
            $f = $row['Field'] ?? '';
            if ($f === '') {
                continue;
            }
            $fields[] = '`' . str_replace('`', '``', $f) . '`';
            if (strcasecmp($f, 'branch_id') === 0) {
                $selectSql[] = (string) (int) $toBranchId;
            } else {
                $selectSql[] = '`' . str_replace('`', '``', $f) . '`';
            }
        }
        mysqli_free_result($rs);
        if ($fields === []) {
            return 0;
        }
        $where = '`branch_id` = ' . (int) $fromBranchId;
        if (auragold_tbl_has_column($conn, $table, 'status')) {
            $where .= ' AND `status` = 1';
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $fields) . ') SELECT ' . implode(',', $selectSql)
            . ' FROM `' . $table . '` WHERE ' . $where;
        if (!mysqli_query($conn, $sql)) {
            return -1;
        }
        $n = (int) mysqli_affected_rows($conn);
        $mxR = @mysqli_query($conn, 'SELECT COALESCE(MAX(`id`), 0) + 1 AS n FROM `' . $table . '`');
        if ($mxR && ($mx = mysqli_fetch_assoc($mxR))) {
            @mysqli_query($conn, 'ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . (int) ($mx['n'] ?? 1));
            mysqli_free_result($mxR);
        } elseif ($mxR) {
            mysqli_free_result($mxR);
        }
        return $n;
    }
}

if (!function_exists('auragold_copy_master_rows_simple_branch')) {
    /**
     * Copy active master rows from one branch_id to another (new PKs). Used to seed a sub-branch from its main.
     *
     * @return int Rows inserted, or -1 on failure
     */
    function auragold_copy_master_rows_simple_branch($conn, string $table, int $fromBranchId, int $toBranchId): int {
        if (!$conn instanceof mysqli) {
            return -1;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || $fromBranchId <= 0 || $toBranchId <= 0 || $fromBranchId === $toBranchId) {
            return 0;
        }
        auragold_ensure_table_branch_id_column($conn, $table);
        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            return 0;
        }
        $rs = mysqli_query($conn, 'SHOW COLUMNS FROM `' . $table . '`');
        $fields    = [];
        $selectSql = [];
        if (!$rs) {
            return -1;
        }
        while ($row = mysqli_fetch_assoc($rs)) {
            $f = $row['Field'] ?? '';
            if ($f === '' || strcasecmp($f, 'id') === 0) {
                continue;
            }
            $fields[] = '`' . str_replace('`', '``', $f) . '`';
            if (strcasecmp($f, 'branch_id') === 0) {
                $selectSql[] = (string) (int) $toBranchId;
            } else {
                $selectSql[] = '`' . str_replace('`', '``', $f) . '`';
            }
        }
        mysqli_free_result($rs);
        if ($fields === []) {
            return 0;
        }
        $where = '`branch_id` = ' . (int) $fromBranchId;
        if (auragold_tbl_has_column($conn, $table, 'status')) {
            $where .= ' AND `status` = 1';
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $fields) . ') SELECT ' . implode(',', $selectSql)
            . ' FROM `' . $table . '` WHERE ' . $where;
        if (!mysqli_query($conn, $sql)) {
            return -1;
        }
        return (int) mysqli_affected_rows($conn);
    }
}

if (!function_exists('auragold_copy_currency_and_rates_between_branches')) {
    /**
     * Copy tbl_currency then tbl_currency_exchange_rate with remapped currency_id FKs.
     */
    function auragold_copy_currency_and_rates_between_branches($conn, int $fromBranchId, int $toBranchId): void {
        if (!$conn instanceof mysqli || $fromBranchId <= 0 || $toBranchId <= 0 || !function_exists('getList')) {
            return;
        }
        auragold_ensure_table_branch_id_column($conn, 'tbl_currency');
        if (!auragold_tbl_has_column($conn, 'tbl_currency', 'branch_id')) {
            return;
        }
        $curRows = getList(
            'SELECT * FROM tbl_currency WHERE branch_id = ' . (int) $fromBranchId . ' AND status = 1 ORDER BY id ASC'
        );
        if (empty($curRows)) {
            return;
        }
        $idMap = [];
        foreach ($curRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $oldId = (int) ($r['id'] ?? 0);
            if ($oldId <= 0) {
                continue;
            }
            unset($r['id']);
            $r['branch_id'] = $toBranchId;
            $cols = [];
            $vals = [];
            foreach ($r as $col => $val) {
                if (!is_string($col) || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                    continue;
                }
                $cols[] = '`' . $col . '`';
                if ($val === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = "'" . mysqli_real_escape_string($conn, (string) $val) . "'";
                }
            }
            if ($cols === []) {
                continue;
            }
            $ins = 'INSERT INTO tbl_currency (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
            if (mysqli_query($conn, $ins)) {
                $nid = (int) mysqli_insert_id($conn);
                if ($nid > 0) {
                    $idMap[$oldId] = $nid;
                }
            }
        }
        if ($idMap === [] || !auragold_tbl_has_column($conn, 'tbl_currency_exchange_rate', 'branch_id')) {
            return;
        }
        $rateRows = getList(
            'SELECT * FROM tbl_currency_exchange_rate WHERE branch_id = ' . (int) $fromBranchId . ' AND status = 1 ORDER BY id ASC'
        );
        if (empty($rateRows)) {
            return;
        }
        foreach ($rateRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $cid = (int) ($r['currency_id'] ?? 0);
            if ($cid <= 0 || empty($idMap[$cid])) {
                continue;
            }
            unset($r['id']);
            $r['branch_id']   = $toBranchId;
            $r['currency_id'] = $idMap[$cid];
            $cols = [];
            $vals = [];
            foreach ($r as $col => $val) {
                if (!is_string($col) || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                    continue;
                }
                $cols[] = '`' . $col . '`';
                if ($val === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = "'" . mysqli_real_escape_string($conn, (string) $val) . "'";
                }
            }
            if ($cols === []) {
                continue;
            }
            mysqli_query(
                $conn,
                'INSERT INTO tbl_currency_exchange_rate (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'
            );
        }
    }
}

if (!function_exists('auragold_seed_subbranch_masters_from_main')) {
    /**
     * Copy GST (tax master), Currency (+ exchange rates), Location, Unit from main branch into a new sub-branch.
     * tbl_metal / tbl_carat are not copied when branches share a database (same PK ids as main branch).
     * Safe to call multiple times: skips if sub-branch already has tbl_location rows.
     *
     * @return array{ok:bool,skipped?:bool,tables?:array<string,int>,message?:string}
     */
    function auragold_seed_subbranch_masters_from_main($conn, int $mainBranchId, int $subBranchId, array $opts = []): array {
        $out = ['ok' => true, 'tables' => []];
        if (!$conn instanceof mysqli || $mainBranchId <= 0 || $subBranchId <= 0 || $mainBranchId === $subBranchId) {
            return $out;
        }
        auragold_ensure_table_branch_id_column($conn, 'tbl_location');
        $chk = @getRecord('SELECT COUNT(*) AS c FROM tbl_location WHERE branch_id = ' . (int) $subBranchId);
        if ($chk && (int) ($chk['c'] ?? 0) > 0) {
            return array_merge($out, ['skipped' => true]);
        }
        $simple = ['tbl_location', 'tbl_unit'];
        if (empty($opts['skip_tax_master'])) {
            $simple[] = 'tbl_tax_master';
        }
        foreach ($simple as $t) {
            if (!auragold_tbl_has_column($conn, $t, 'branch_id')) {
                continue;
            }
            $n = auragold_copy_master_rows_simple_branch($conn, $t, $mainBranchId, $subBranchId);
            if ($n >= 0) {
                $out['tables'][$t] = $n;
            }
        }
        auragold_copy_currency_and_rates_between_branches($conn, $mainBranchId, $subBranchId);
        return $out;
    }
}

if (!function_exists('auragold_seed_subbranch_carat_from_main_if_empty')) {
    /**
     * Ensures Carat (Karat) master exists for a sub-branch: copies active rows from the main branch.
     * Use when full master seed was skipped (sub-branch already had metals) or carat copy failed earlier.
     */
    function auragold_seed_subbranch_carat_from_main_if_empty(mysqli $conn, int $mainBranchId, int $subBranchId): void {
        if ($mainBranchId <= 0 || $subBranchId <= 0 || $mainBranchId === $subBranchId) {
            return;
        }
        if (function_exists('auragold_branches_share_operational_database')
            && auragold_branches_share_operational_database($subBranchId, $mainBranchId)) {
            return;
        }
        auragold_ensure_table_branch_id_column($conn, 'tbl_carat');
        if (!auragold_tbl_has_column($conn, 'tbl_carat', 'branch_id')) {
            return;
        }
        $chk = @getRecord('SELECT COUNT(*) AS c FROM tbl_carat WHERE branch_id = ' . (int) $subBranchId);
        if ($chk && (int) ($chk['c'] ?? 0) > 0) {
            return;
        }
        if (function_exists('auragold_copy_master_rows_preserve_id_branch')) {
            auragold_copy_master_rows_preserve_id_branch($conn, 'tbl_carat', $mainBranchId, $subBranchId);
        } else {
            auragold_copy_master_rows_simple_branch($conn, 'tbl_carat', $mainBranchId, $subBranchId);
        }
    }
}

if (!function_exists('auragold_branch_barcode_prefix_digit_from_registry')) {
    /**
     * Barcode prefix / numeric length from tbl_branches (registry). Columns may be absent on older DBs.
     *
     * @return array{prefix:string,digit:int} digit 0 = not set; use tbl_settings fallback.
     */
    function auragold_branch_barcode_prefix_digit_from_registry(int $branch_id): array {
        $out = ['prefix' => '', 'digit' => 0];
        if ($branch_id <= 0 || !function_exists('getRecordMaster')) {
            return $out;
        }
        $r = @getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . (int) $branch_id . ' LIMIT 1');
        if (!$r || !is_array($r)) {
            return $out;
        }
        if (array_key_exists('branch_barcode_prefix', $r)) {
            $out['prefix'] = trim((string) ($r['branch_barcode_prefix'] ?? ''));
        }
        if (array_key_exists('barcode_num_digits', $r)) {
            $d = (int) ($r['barcode_num_digits'] ?? 0);
            if ($d > 0) {
                $out['digit'] = $d;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_barcode_default_prefix_digit')) {
    /**
     * Resolve default prefix and digit length: branch registry first, then tbl_settings (working DB).
     *
     * @return array{prefix:string,digit:int}
     */
    function auragold_barcode_default_prefix_digit(mysqli $conn, int $branch_id): array {
        $br = auragold_branch_barcode_prefix_digit_from_registry($branch_id);
        $prefix = $br['prefix'];
        $digit  = $br['digit'];

        $settingsRow = null;
        $tbl_exists = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_settings'");
        if ($tbl_exists && mysqli_num_rows($tbl_exists) > 0) {
            mysqli_free_result($tbl_exists);
            $rs = @mysqli_query($conn, 'SELECT barcode_prefix, barcode_digit_length FROM tbl_settings LIMIT 1');
            if ($rs && mysqli_num_rows($rs) > 0) {
                $settingsRow = mysqli_fetch_assoc($rs);
                mysqli_free_result($rs);
            } elseif ($rs) {
                mysqli_free_result($rs);
            }
        } elseif ($tbl_exists) {
            mysqli_free_result($tbl_exists);
        }

        if ($prefix === '') {
            $prefix = 'RN';
            if ($settingsRow && trim((string) ($settingsRow['barcode_prefix'] ?? '')) !== '') {
                $prefix = trim((string) $settingsRow['barcode_prefix']);
            }
        }
        if ($digit < 1) {
            $digit = 5;
            if ($settingsRow && (int) ($settingsRow['barcode_digit_length'] ?? 0) > 0) {
                $digit = (int) $settingsRow['barcode_digit_length'];
            }
        }
        if ($digit < 1) {
            $digit = 5;
        }
        return ['prefix' => $prefix, 'digit' => $digit];
    }
}

if (!function_exists('auragold_branch_registry_pin_digits')) {
    /**
     * Postal PIN digits from registry tbl_branches: prefers `pincode`, then legacy `zip_code`.
     * Uses single-column SELECTs so missing columns never cause SQL errors.
     *
     * @return string digits only (often 6 for India); empty if none
     */
    function auragold_branch_registry_pin_digits(int $branch_id): string
    {
        global $conn_master;
        if ($branch_id <= 0 || empty($conn_master) || !$conn_master instanceof mysqli) {
            return '';
        }
        $t = 'tbl_branches';
        $id = (int) $branch_id;
        if (auragold_tbl_has_column($conn_master, $t, 'pincode')) {
            $row = function_exists('getRecordMaster')
                ? @getRecordMaster('SELECT `pincode` FROM `' . $t . '` WHERE id = ' . $id . ' LIMIT 1')
                : null;
            if (is_array($row) && isset($row['pincode'])) {
                $d = preg_replace('/\D/', '', (string) $row['pincode']);
                if ($d !== '') {
                    return $d;
                }
            }
        }
        if (auragold_tbl_has_column($conn_master, $t, 'zip_code')) {
            $row = function_exists('getRecordMaster')
                ? @getRecordMaster('SELECT `zip_code` FROM `' . $t . '` WHERE id = ' . $id . ' LIMIT 1')
                : null;
            if (is_array($row) && isset($row['zip_code'])) {
                $d = preg_replace('/\D/', '', (string) $row['zip_code']);
                if ($d !== '') {
                    return $d;
                }
            }
        }

        return '';
    }
}

if (!function_exists('auragold_customer_billing_pin_digits')) {
    /**
     * Customer billing PIN from tbl_customers using the first existing column (billing_zip_code, …).
     *
     * @param mysqli $conn working DB connection (customer table)
     */
    function auragold_customer_billing_pin_digits($conn, int $customer_id): string
    {
        if ($customer_id <= 0 || !$conn instanceof mysqli) {
            return '';
        }
        $t = 'tbl_customers';
        $id = (int) $customer_id;
        $try = ['billing_zip_code', 'billing_pincode', 'pincode', 'zip'];
        foreach ($try as $col) {
            if (!auragold_tbl_has_column($conn, $t, $col)) {
                continue;
            }
            $row = @getRecord('SELECT `' . $col . '` AS `_pv` FROM `' . $t . '` WHERE id = ' . $id . ' LIMIT 1');
            if (is_array($row) && isset($row['_pv']) && trim((string) $row['_pv']) !== '') {
                return preg_replace('/\D/', '', (string) $row['_pv']);
            }
        }

        return '';
    }
}

if (!function_exists('auragold_stock_journal_session_username')) {
    /**
     * Login display name for stock journal audit (Username, else first + last name).
     */
    function auragold_stock_journal_session_username(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        $a = $_SESSION['Admin'] ?? [];
        if (!is_array($a)) {
            return '';
        }
        $u = trim((string) ($a['Username'] ?? $a['username'] ?? ''));
        if ($u !== '') {
            return $u;
        }
        $fn = trim((string) ($a['Fname'] ?? $a['fname'] ?? ''));
        $ln = trim((string) ($a['Lname'] ?? $a['lname'] ?? ''));

        return trim($fn . ' ' . $ln);
    }
}

if (!function_exists('auragold_ensure_stock_journal_audit_columns')) {
    /**
     * Adds created/modified username columns on tbl_stock_journal when missing.
     */
    function auragold_ensure_stock_journal_audit_columns($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $t = 'tbl_stock_journal';
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
        if (!$r || mysqli_num_rows($r) === 0) {
            if ($r) {
                mysqli_free_result($r);
            }

            return;
        }
        if ($r) {
            mysqli_free_result($r);
        }
        $ensure = static function ($conn, string $col, string $def) use ($t) {
            if (auragold_tbl_has_column($conn, $t, $col)) {
                return;
            }
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if ($safe === '') {
                return;
            }
            @mysqli_query($conn, 'ALTER TABLE `' . $t . '` ADD COLUMN `' . $safe . '` ' . $def);
        };
        $ensure($conn, 'created_by_username', "VARCHAR(191) NULL DEFAULT NULL COMMENT 'Login username at create' AFTER `created_by`");
        if (auragold_tbl_has_column($conn, $t, 'updated_at')) {
            $ensure($conn, 'modified_by', 'INT NULL DEFAULT NULL AFTER `updated_at`');
        } else {
            $ensure($conn, 'modified_by', 'INT NULL DEFAULT NULL');
        }
        $ensure($conn, 'modified_by_username', 'VARCHAR(191) NULL DEFAULT NULL AFTER `modified_by`');
    }
}
