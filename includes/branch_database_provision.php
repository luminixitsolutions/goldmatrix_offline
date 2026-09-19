<?php
/**
 * Branch DB provisioning: clone schema from main (registry) DB, optionally copy data,
 * seed Set Software + Masters tables, and sync tbl_branches to the main family (main + all subs, same ids as registry).
 * Option `minimal_schema` limits CREATE TABLE to masters + tbl_branches + tbl_bill_series instead of the whole registry.
 */

require_once __DIR__ . '/branch_tbl_branches_ip_subdomain.php';

if (!function_exists('auragold_branch_schema_clone_source_db')) {
    /**
     * Database to copy table structure (and default master data) from. Not always the same as DB_NAME: config may point
     * the app at a branch operation DB, while the full 165+ table schema lives in AURAGOLD_REGISTRY_DB (e.g. auragold).
     * Priority: AURAGOLD_SCHEMA_CLONE_SOURCE_DB (if set) → AURAGOLD_REGISTRY_DB → DB_NAME.
     */
    function auragold_branch_schema_clone_source_db(): string {
        $candidates = [];
        if (defined('AURAGOLD_SCHEMA_CLONE_SOURCE_DB') && (string) AURAGOLD_SCHEMA_CLONE_SOURCE_DB !== '') {
            $candidates[] = trim((string) AURAGOLD_SCHEMA_CLONE_SOURCE_DB);
        }
        if (defined('AURAGOLD_REGISTRY_DB') && (string) AURAGOLD_REGISTRY_DB !== '') {
            $candidates[] = trim((string) AURAGOLD_REGISTRY_DB);
        }
        if (defined('DB_NAME') && (string) DB_NAME !== '') {
            $candidates[] = trim((string) DB_NAME);
        }
        foreach ($candidates as $c) {
            if ($c !== '' && (bool) preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
                return $c;
            }
        }
        return '';
    }
}

/** Master / Set Software tables: order respects typical FK dependencies (parents before children). */
function auragold_branch_master_tables_ordered() {
    return [
        'tbl_settings',
        'tbl_voucher_types',
        'tbl_voucher_field_visibility',
        'tbl_calculation_modes',
        'tbl_tax_master',
        'tbl_currency',
        'tbl_currency_exchange_rate',
        'tbl_metal_exchange_rate',
        'tbl_unit',
        'tbl_unit_conversion',
        'tbl_location',
        'tbl_countries',
        'tbl_states',
        'tbl_cities',
        'tbl_carat',
        'tbl_collection',
        'tbl_clarity',
        'tbl_metal',
        'tbl_cut',
        'tbl_color',
        'tbl_shape',
        'tbl_sieve_size',
        'tbl_size',
        'tbl_document_type',
        'tbl_counter',
        'tbl_packet_type',
        'tbl_task_type',
        'tbl_loan_product_type',
        'tbl_loan_reason',
        'tbl_break_type',
        'tbl_campaign_group',
        'tbl_article',
        'tbl_cash_denomination',
        'tbl_customer_advance_policy',
        'tbl_remark',
        'tbl_barcode_settings',
        'tbl_accounting_master_modes',
        'tbl_accounting_calculation_settings',
        'tbl_accounting_financial_years',
        'tbl_dashboard_metal_meta',
        'tbl_dashboard_metal_rates',
        'tbl_voucher_settings',
        'tbl_voucher_payment_buttons',
        'tbl_invoice_print_settings',
        'tbl_users',
    ];
}

function auragold_schema_table_exists($link, $schema, $table) {
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

if (!function_exists('auragold_schema_base_table_column_names')) {
    /**
     * Physical column names for schema.table in ordinal order (SHOW COLUMNS).
     *
     * @return list<string>
     */
    function auragold_schema_base_table_column_names(mysqli $link, string $schema, string $table): array {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $schema) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [];
        }
        $db = '`' . str_replace('`', '``', $schema) . '`.`' . str_replace('`', '``', $table) . '`';
        $r   = @mysqli_query($link, 'SHOW COLUMNS FROM ' . $db);
        $out = [];
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $f = isset($row['Field']) ? (string) $row['Field'] : '';
                if ($f !== '') {
                    $out[] = $f;
                }
            }
            mysqli_free_result($r);
        }
        return $out;
    }
}

if (!function_exists('auragold_schema_table_column_names_intersection_ordered')) {
    /**
     * Columns present in both tables; order follows $preferredOrder (typically the registry / source).
     *
     * @param list<string> $preferredOrder
     * @param list<string> $secondColumns
     * @return list<string>
     */
    function auragold_schema_table_column_names_intersection_ordered(array $preferredOrder, array $secondColumns): array {
        $have = [];
        foreach ($secondColumns as $c) {
            if (!is_string($c) || $c === '') {
                continue;
            }
            $have[strtolower($c)] = true;
        }
        $out = [];
        foreach ($preferredOrder as $c) {
            if (!is_string($c) || $c === '') {
                continue;
            }
            if (!empty($have[strtolower($c)])) {
                $out[] = $c;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_database_exists_on_schema')) {
    /**
     * @param string $name Single MySQL database identifier
     */
    function auragold_database_exists_on_schema(mysqli $link, string $name): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return false;
        }
        $e = mysqli_real_escape_string($link, $name);
        $r = @mysqli_query($link, "SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$e' LIMIT 1");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return (bool) $ok;
    }
}

/**
 * Table names to CREATE in a new branch DB. Full clone: every base table in the registry.
 * Minimal: Set Software + masters, tbl_branches, tbl_bill_series (faster; add more tables later via migration or full repair).
 *
 * @return string[]
 */
function auragold_branch_provision_table_names(mysqli $link, string $sourceDb, bool $fullRegistryClone): array {
    $sourceDb = trim($sourceDb);
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
        return [];
    }
    if ($fullRegistryClone) {
        $esc = mysqli_real_escape_string($link, $sourceDb);
        $out = [];
        $r   = mysqli_query(
            $link,
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$esc' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        );
        while ($r && $row = mysqli_fetch_assoc($r)) {
            $out[] = $row['TABLE_NAME'];
        }
        return $out;
    }
    $candidates = array_merge(
        ['tbl_branches'],
        auragold_branch_master_tables_ordered(),
        ['tbl_bill_series']
    );
    $ordered = [];
    foreach ($candidates as $t) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $t) || in_array($t, $ordered, true)) {
            continue;
        }
        if (auragold_schema_table_exists($link, $sourceDb, $t)) {
            $ordered[] = $t;
        }
    }
    return $ordered;
}

/**
 * Registry must have tbl_branches. If the branch schema has no tbl_branches (e.g. partial structure.sql), create it LIKE registry.
 *
 * @return array{ok:bool,message:string}
 */
function auragold_branch_ensure_tbl_branches_in_target(
    mysqli $link,
    string $targetDb,
    string $sourceDb,
    string $tquoted,
    string $squoted
): array {
    if (!auragold_schema_table_exists($link, $sourceDb, 'tbl_branches')) {
        return [
            'ok'      => false,
            'message' => 'Registry database `' . $sourceDb . '` is missing the `tbl_branches` table.',
        ];
    }
    if (auragold_schema_table_exists($link, $targetDb, 'tbl_branches')) {
        return ['ok' => true, 'message' => ''];
    }
    if (!mysqli_query($link, "CREATE TABLE $tquoted.`tbl_branches` LIKE $squoted.`tbl_branches`")) {
        return [
            'ok'      => false,
            'message' => 'Could not create `tbl_branches` in branch database: ' . mysqli_error($link),
        ];
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * Copy all rows from source.table -> target.table (same name). Skips if table missing in either DB.
 *
 * @return array{0:bool,1:string} [success, error message or '']
 */
function auragold_copy_table_data($link, $targetDb, $sourceDb, $table, $tquoted, $squoted) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return [false, 'invalid table'];
    }
    if (!auragold_schema_table_exists($link, $sourceDb, $table) || !auragold_schema_table_exists($link, $targetDb, $table)) {
        return [true, ''];
    }
    $tq = '`' . str_replace('`', '``', $table) . '`';

    $srcCols = function_exists('auragold_schema_base_table_column_names')
        ? auragold_schema_base_table_column_names($link, $sourceDb, $table)
        : [];
    $tgtCols = function_exists('auragold_schema_base_table_column_names')
        ? auragold_schema_base_table_column_names($link, $targetDb, $table)
        : [];
    $common = (function_exists('auragold_schema_table_column_names_intersection_ordered') && $srcCols !== [] && $tgtCols !== [])
        ? auragold_schema_table_column_names_intersection_ordered($srcCols, $tgtCols)
        : [];
    if ($common === []) {
        if (!mysqli_query($link, "INSERT INTO $tquoted.$tq SELECT * FROM $squoted.$tq")) {
            return [false, mysqli_error($link)];
        }
        return [true, ''];
    }
    $parts = [];
    foreach ($common as $c) {
        $parts[] = '`' . str_replace('`', '``', $c) . '`';
    }
    $colList = implode(', ', $parts);
    if (!mysqli_query($link, 'INSERT INTO ' . $tquoted . '.' . $tq . ' (' . $colList . ') SELECT ' . $colList . ' FROM ' . $squoted . '.' . $tq)) {
        return [false, mysqli_error($link)];
    }
    return [true, ''];
}

/**
 * Replace target.tbl_branches with registry rows for one main “family”: the main row (main_branch_id = 0)
 * and every sub-branch row where main_branch_id = that main. Preserves the same ids as in the registry DB.
 *
 * @param int $verifyBranchRowId If &gt; 0, ensure this id exists in target after copy (the row being provisioned).
 * @return array{ok:bool,message:string}
 */
function auragold_branch_mirror_registry_tbl_branches_family_into_target(
    mysqli $link,
    string $targetDb,
    string $sourceDb,
    int $registryMainId,
    int $verifyBranchRowId = 0
): array {
    $registryMainId = (int) $registryMainId;
    $targetDb       = trim($targetDb);
    $sourceDb       = trim($sourceDb);
    if ($registryMainId <= 0) {
        return ['ok' => false, 'message' => 'Invalid registry main id for tbl_branches.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $targetDb) || !preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
        return ['ok' => false, 'message' => 'Invalid database name for tbl_branches mirror.'];
    }
    $tquoted = '`' . str_replace('`', '``', $targetDb) . '`';
    $squoted = '`' . str_replace('`', '``', $sourceDb) . '`';
    $ens = auragold_branch_ensure_tbl_branches_in_target($link, $targetDb, $sourceDb, $tquoted, $squoted);
    if (empty($ens['ok'])) {
        return $ens;
    }
    auragold_ensure_branches_ip_subdomain_for_mirror_dbs($link, $sourceDb, $targetDb);

    $mid     = (int) $registryMainId;

    $srcCols = auragold_schema_base_table_column_names($link, $sourceDb, 'tbl_branches');
    $tgtCols = auragold_schema_base_table_column_names($link, $targetDb, 'tbl_branches');
    $common  = auragold_schema_table_column_names_intersection_ordered($srcCols, $tgtCols);
    if ($common === []) {
        return [
            'ok'      => false,
            'message' => 'Could not resolve common `tbl_branches` columns between `' . $sourceDb . '` and `' . $targetDb . '`.',
        ];
    }
    $colSql = [];
    foreach ($common as $cn) {
        $colSql[] = '`' . str_replace('`', '``', $cn) . '`';
    }
    $colList = implode(', ', $colSql);

    mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=0');
    $del = mysqli_query($link, "DELETE FROM $tquoted.`tbl_branches`");
    if (!$del) {
        $err = mysqli_error($link);
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        return ['ok' => false, 'message' => 'Could not clear tbl_branches: ' . $err];
    }

    $ins = mysqli_query(
        $link,
        'INSERT INTO ' . $tquoted . '.`tbl_branches` (' . $colList . ') SELECT ' . $colList . ' FROM ' . $squoted . '.`tbl_branches` WHERE id = ' . $mid
        . ' OR IFNULL(main_branch_id, 0) = ' . $mid
    );
    if (!$ins) {
        $err = mysqli_error($link);
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        return ['ok' => false, 'message' => 'Could not copy tbl_branches family: ' . $err];
    }
    if ((int) mysqli_affected_rows($link) < 1) {
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        return ['ok' => false, 'message' => 'No tbl_branches rows were copied for main id ' . $mid . '.'];
    }
    mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');

    $verifyBranchRowId = (int) $verifyBranchRowId;
    if ($verifyBranchRowId > 0) {
        $v = mysqli_query($link, "SELECT 1 FROM $tquoted.`tbl_branches` WHERE id = $verifyBranchRowId LIMIT 1");
        if (!$v || mysqli_num_rows($v) < 1) {
            if ($v) {
                mysqli_free_result($v);
            }
            return ['ok' => false, 'message' => 'tbl_branches mirror missing branch id ' . $verifyBranchRowId . '.'];
        }
        mysqli_free_result($v);
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * After provisioning, target.tbl_branches lists the registry main row and all subs under it (same ids).
 *
 * @return array{ok:bool,message:string}
 */
function auragold_branch_reset_tbl_branches($link, $targetDb, $sourceDb, $branchRowId, $tquoted, $squoted) {
    $branchRowId = (int) $branchRowId;
    $targetDb    = trim((string) $targetDb);
    $sourceDb    = trim((string) $sourceDb);
    if ($branchRowId <= 0) {
        return ['ok' => false, 'message' => 'Invalid branch id for tbl_branches.'];
    }
    $ens = auragold_branch_ensure_tbl_branches_in_target($link, $targetDb, $sourceDb, $tquoted, $squoted);
    if (empty($ens['ok'])) {
        return $ens;
    }
    $chk = mysqli_query($link, "SELECT id, main_branch_id FROM $squoted.`tbl_branches` WHERE id = $branchRowId LIMIT 1");
    if (!$chk || !($br = mysqli_fetch_assoc($chk))) {
        if ($chk) {
            mysqli_free_result($chk);
        }
        return ['ok' => false, 'message' => 'Branch id ' . $branchRowId . ' not found in master tbl_branches.'];
    }
    mysqli_free_result($chk);
    $mb               = (int) ($br['main_branch_id'] ?? 0);
    $registryMainId   = ($mb === 0) ? (int) $br['id'] : $mb;

    return auragold_branch_mirror_registry_tbl_branches_family_into_target($link, $targetDb, $sourceDb, $registryMainId, $branchRowId);
}

/**
 * Push the same main-family tbl_branches snapshot to every dedicated database in that family (so siblings see new branches).
 */
function auragold_branch_sync_family_tbl_to_all_peer_databases(mysqli $conn_master, int $registryMainId): void {
    $registryMainId = (int) $registryMainId;
    if ($registryMainId <= 0) {
        return;
    }
    $regDb = function_exists('auragold_branch_schema_clone_source_db')
        ? trim((string) auragold_branch_schema_clone_source_db())
        : (defined('DB_NAME') ? trim((string) DB_NAME) : '');
    if ($regDb === '') {
        return;
    }
    if (!function_exists('getListMaster')) {
        return;
    }
    $list = getListMaster(
        'SELECT id, db_name, db_users, db_password FROM tbl_branches WHERE '
        . '(id = ' . $registryMainId . ' OR main_branch_id = ' . $registryMainId . ') '
        . "AND TRIM(IFNULL(db_name,'')) <> '' ORDER BY id ASC"
    );
    if (!is_array($list) || $list === []) {
        return;
    }
    if (!function_exists('auragold_mysqli_connect_branch_or_registry')) {
        require_once __DIR__ . '/branch_create_db_after_save.php';
    }
    $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
    $done = [];
    foreach ($list as $row) {
        $dn = trim((string) ($row['db_name'] ?? ''));
        if ($dn === '' || strcasecmp($dn, $regDb) === 0) {
            continue;
        }
        $k = strtolower($dn);
        if (isset($done[$k])) {
            continue;
        }
        $done[$k] = true;
        $u = trim((string) ($row['db_users'] ?? ''));
        $p = (string) ($row['db_password'] ?? '');
        $sub = auragold_mysqli_connect_branch_or_registry($host, $dn, $u, $p);
        if (!$sub) {
            error_log('AuraGold peer tbl_branches sync: could not connect to `' . $dn . '`: ' . mysqli_connect_error());
            continue;
        }
        mysqli_set_charset($sub, 'utf8mb4');
        $res = auragold_branch_mirror_registry_tbl_branches_family_into_target($sub, $dn, $regDb, $registryMainId, 0);
        mysqli_close($sub);
        if (empty($res['ok'])) {
            error_log('AuraGold peer tbl_branches sync `' . $dn . '`: ' . ($res['message'] ?? ''));
        }
    }
}

if (!function_exists('auragold_provision_mysqli_resolved_creds_for_clone')) {
    /**
     * cPanel: optional AURAGOLD_CLONE_SOURCE_USER to read the template DB; if unset, use DB_USER/DB_PASS.
     *
     * @return array{0:string,1:string} [user, pass]
     */
    function auragold_provision_mysqli_resolved_creds_for_clone(): array {
        if (defined('AURAGOLD_CLONE_SOURCE_USER') && trim((string) AURAGOLD_CLONE_SOURCE_USER) !== '') {
            $u = trim((string) AURAGOLD_CLONE_SOURCE_USER);
            $p = defined('AURAGOLD_CLONE_SOURCE_PASS') ? (string) AURAGOLD_CLONE_SOURCE_PASS : '';
        } else {
            $u = (string) (defined('DB_USER') ? DB_USER : '');
            $p = (string) (defined('DB_PASS') ? DB_PASS : '');
        }
        return [$u, $p];
    }
}

if (!function_exists('auragold_provision_branch_opts_use_separate_mysqli')) {
    /**
     * When template DB is read with AURAGOLD_CLONE_SOURCE_* and the new branch uses its own cPanel user,
     * use two mysqli connections (matches manual clone scripts on shared hosting).
     */
    function auragold_provision_branch_opts_use_separate_mysqli(array $opts): bool {
        if (!defined('AURAGOLD_CLONE_SOURCE_USER') || trim((string) AURAGOLD_CLONE_SOURCE_USER) === '') {
            return false;
        }
        $bu = trim((string) ($opts['branch_mysql_user'] ?? ''));
        if ($bu === '' || $bu === 'root') {
            return false;
        }
        $pSrc = (string) (defined('AURAGOLD_CLONE_SOURCE_PASS') ? AURAGOLD_CLONE_SOURCE_PASS : '');
        $pBr  = (string) ($opts['branch_mysql_pass'] ?? '');
        if (strcasecmp($bu, trim((string) AURAGOLD_CLONE_SOURCE_USER)) === 0 && $pBr === $pSrc) {
            return false;
        }
        return true;
    }
}

if (!function_exists('auragold_mysqli_provision_open')) {
    function auragold_mysqli_provision_open(string $host, string $user, string $pass, ?string $defaultDb = null): ?mysqli {
        $link = mysqli_init();
        if (!$link) {
            return null;
        }
        @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 60);
        $db = ($defaultDb !== null && $defaultDb !== '') ? $defaultDb : null;
        if (!@mysqli_real_connect($link, $host, $user, $pass, $db)) {
            @mysqli_close($link);
            return null;
        }
        return $link;
    }
}

if (!function_exists('auragold_provision_dual_create_table_from_show')) {
    /**
     * @return string Error message, empty on success
     */
    function auragold_provision_dual_create_table_from_show(
        mysqli $linkS,
        mysqli $linkT,
        string $sourceDb,
        string $targetDb,
        string $table
    ): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb) || !preg_match('/^[a-zA-Z0-9_]+$/', $targetDb)
            || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return 'invalid names';
        }
        $eS  = function ($i) use ($linkS) {
            return mysqli_real_escape_string($linkS, $i);
        };
        $eT  = function ($i) use ($linkT) {
            return mysqli_real_escape_string($linkT, $i);
        };
        $q   = 'SHOW CREATE TABLE `' . $eS($sourceDb) . '`.`' . $eS($table) . '`';
        $r   = mysqli_query($linkS, $q);
        if (!$r) {
            return (string) mysqli_error($linkS);
        }
        $row   = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        $create = '';
        if (is_array($row)) {
            if (!empty($row['Create Table'])) {
                $create = (string) $row['Create Table'];
            } elseif (isset($row[1])) {
                $create = (string) $row[1];
            }
        }
        if ($create === '') {
            return 'empty CREATE from SHOW';
        }
        if (!@mysqli_query($linkT, 'USE `' . $eT($targetDb) . '`')) {
            return (string) mysqli_error($linkT);
        }
        if (!@mysqli_query($linkT, 'DROP TABLE IF EXISTS `' . $eT($table) . '`')) {
            return (string) mysqli_error($linkT);
        }
        if (!@mysqli_query($linkT, $create)) {
            return (string) mysqli_error($linkT);
        }
        return '';
    }
}

if (!function_exists('auragold_provision_copy_table_data_separate_mysqli')) {
    /**
     * @return array{0:bool,1:string}
     */
    function auragold_provision_copy_table_data_separate_mysqli(
        mysqli $linkS,
        mysqli $linkT,
        string $sourceDb,
        string $targetDb,
        string $table
    ): array {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb) || !preg_match('/^[a-zA-Z0-9_]+$/', $targetDb)
            || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [false, 'invalid names'];
        }
        if (!auragold_schema_table_exists($linkS, $sourceDb, $table) || !auragold_schema_table_exists($linkT, $targetDb, $table)) {
            return [true, ''];
        }
        $eS = function ($i) use ($linkS) {
            return mysqli_real_escape_string($linkS, $i);
        };
        $res = mysqli_query($linkS, 'SELECT * FROM `' . $eS($sourceDb) . '`.`' . $eS($table) . '`');
        if (!$res) {
            return [false, (string) mysqli_error($linkS)];
        }
        if (mysqli_num_rows($res) === 0) {
            mysqli_free_result($res);
            return [true, ''];
        }
        $tEsc = function ($i) use ($linkT) {
            return mysqli_real_escape_string($linkT, $i);
        };
        $fieldMeta = mysqli_fetch_fields($res);
        if (!is_array($fieldMeta) || $fieldMeta === []) {
            mysqli_free_result($res);
            return [false, 'no column metadata for copy'];
        }
        $colOrder = [];
        foreach ($fieldMeta as $fi) {
            if ((string) $fi->name === '') {
                continue;
            }
            $colOrder[] = (string) $fi->name;
        }
        $tgtCols = function_exists('auragold_schema_base_table_column_names')
            ? auragold_schema_base_table_column_names($linkT, $targetDb, $table)
            : [];
        if ($tgtCols !== [] && function_exists('auragold_schema_table_column_names_intersection_ordered')) {
            $colOrder = auragold_schema_table_column_names_intersection_ordered($colOrder, $tgtCols);
        }
        $fields = [];
        foreach ($colOrder as $name) {
            $fields[] = '`' . str_replace('`', '``', $name) . '`';
        }
        mysqli_data_seek($res, 0);
        $columnList = implode(', ', $fields);
        if ($columnList === '') {
            mysqli_free_result($res);
            return [true, ''];
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $values = [];
            foreach ($colOrder as $cn) {
                if (!array_key_exists($cn, $row)) {
                    $values[] = 'NULL';
                    continue;
                }
                $value = $row[$cn];
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . $tEsc((string) $value) . "'";
                }
            }
            $sql = 'INSERT INTO `' . $tEsc($targetDb) . '`.`' . $tEsc($table) . '` (' . $columnList . ') VALUES ('
                . implode(', ', $values) . ')';
            if (!@mysqli_query($linkT, $sql)) {
                $err = (string) mysqli_error($linkT);
                mysqli_free_result($res);
                return [false, $err];
            }
        }
        mysqli_free_result($res);
        return [true, ''];
    }
}

if (!function_exists('auragold_branch_ensure_tbl_branches_in_target_separate_mysqli')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_branch_ensure_tbl_branches_in_target_separate_mysqli(
        mysqli $linkS,
        mysqli $linkT,
        string $targetDb,
        string $sourceDb,
        $tquoted,
        $squoted
    ) {
        $targetDb = trim((string) $targetDb);
        $sourceDb = trim((string) $sourceDb);
        if (!auragold_schema_table_exists($linkS, $sourceDb, 'tbl_branches')) {
            return [
                'ok'      => false,
                'message' => 'Registry database `' . $sourceDb . '` is missing the `tbl_branches` table.',
            ];
        }
        if (auragold_schema_table_exists($linkT, $targetDb, 'tbl_branches')) {
            return ['ok' => true, 'message' => ''];
        }
        $err = auragold_provision_dual_create_table_from_show($linkS, $linkT, $sourceDb, $targetDb, 'tbl_branches');
        if ($err !== '') {
            return ['ok' => false, 'message' => 'Could not create `tbl_branches` in branch database: ' . $err];
        }
        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('auragold_branch_mirror_family_separate_mysqli')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_branch_mirror_family_separate_mysqli(
        mysqli $linkS,
        mysqli $linkT,
        string $targetDb,
        string $sourceDb,
        int $registryMainId,
        int $verifyBranchRowId
    ) {
        $targetDb  = trim((string) $targetDb);
        $sourceDb  = trim((string) $sourceDb);
        $eS = function ($i) use ($linkS) {
            return mysqli_real_escape_string($linkS, $i);
        };
        $eT = function ($i) use ($linkT) {
            return mysqli_real_escape_string($linkT, $i);
        };
        if ($registryMainId <= 0) {
            return ['ok' => false, 'message' => 'Invalid registry main id for tbl_branches.'];
        }
        auragold_ensure_branches_ip_subdomain_columns($linkS, $sourceDb);
        if (strcasecmp($sourceDb, $targetDb) !== 0) {
            auragold_ensure_branches_ip_subdomain_columns($linkT, $targetDb);
        }
        $mid  = (int) $registryMainId;
        mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=0');
        $tRef = '`' . $eT($targetDb) . '`.`tbl_branches`';
        if (!mysqli_query($linkT, "DELETE FROM $tRef")) {
            $e = (string) mysqli_error($linkT);
            mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
            return ['ok' => false, 'message' => 'Could not clear tbl_branches: ' . $e];
        }
        $sel = mysqli_query(
            $linkS,
            "SELECT * FROM `" . $eS($sourceDb) . "`.`tbl_branches` WHERE id = $mid OR IFNULL(main_branch_id, 0) = $mid"
        );
        if (!$sel) {
            $e  = (string) mysqli_error($linkS);
            mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
            return ['ok' => false, 'message' => 'Could not read tbl_branches from source: ' . $e];
        }
        if (mysqli_num_rows($sel) < 1) {
            mysqli_free_result($sel);
            mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
            return ['ok' => false, 'message' => 'No tbl_branches rows were read for main id ' . $mid . '.'];
        }
        $fmeta = mysqli_fetch_fields($sel);
        $order = [];
        if (is_array($fmeta)) {
            foreach ($fmeta as $f) {
                if ((string) $f->name === '') {
                    continue;
                }
                $order[] = (string) $f->name;
            }
        }
        $tgtCols = auragold_schema_base_table_column_names($linkT, $targetDb, 'tbl_branches');
        $order     = auragold_schema_table_column_names_intersection_ordered($order, $tgtCols);
        $colSql    = [];
        foreach ($order as $n) {
            $colSql[] = '`' . str_replace('`', '``', $n) . '`';
        }
        if ($order === []) {
            mysqli_free_result($sel);
            mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
            return [
                'ok'      => false,
                'message' => 'No common `tbl_branches` columns between `' . $sourceDb . '` and `' . $targetDb . '` for mirror.',
            ];
        }
        mysqli_data_seek($sel, 0);
        while ($r = mysqli_fetch_assoc($sel)) {
            $values = [];
            foreach ($order as $cn) {
                $v = $r[$cn] ?? null;
                if ($v === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . $eT((string) $v) . "'";
                }
            }
            if (!@mysqli_query(
                $linkT,
                "INSERT INTO `" . $eT($targetDb) . "`.`tbl_branches` (" . implode(', ', $colSql) . ') VALUES ('
                . implode(', ', $values) . ')'
            )) {
                $e = (string) mysqli_error($linkT);
                mysqli_free_result($sel);
                mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
                return ['ok' => false, 'message' => 'Could not insert into tbl_branches: ' . $e];
            }
        }
        mysqli_free_result($sel);
        $verifyBranchRowId = (int) $verifyBranchRowId;
        if ($verifyBranchRowId > 0) {
            $v = mysqli_query($linkT, "SELECT 1 FROM $tRef WHERE id = " . (int) $verifyBranchRowId . ' LIMIT 1');
            if (!$v || mysqli_num_rows($v) < 1) {
                if ($v) {
                    mysqli_free_result($v);
                }
                mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
                return ['ok' => false, 'message' => 'tbl_branches id ' . (int) $verifyBranchRowId . ' not present in target.'];
            }
            mysqli_free_result($v);
        }
        mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('auragold_branch_reset_tbl_branches_separate_mysqli')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_branch_reset_tbl_branches_separate_mysqli(
        mysqli $linkS,
        mysqli $linkT,
        string $targetDb,
        string $sourceDb,
        int $branchRowId,
        $tquoted,
        $squoted
    ) {
        $branchRowId = (int) $branchRowId;
        $targetDb    = trim((string) $targetDb);
        $sourceDb    = trim((string) $sourceDb);
        if ($branchRowId <= 0) {
            return ['ok' => false, 'message' => 'Invalid branch id for tbl_branches.'];
        }
        $ens = auragold_branch_ensure_tbl_branches_in_target_separate_mysqli(
            $linkS,
            $linkT,
            $targetDb,
            $sourceDb,
            $tquoted,
            $squoted
        );
        if (empty($ens['ok'])) {
            return $ens;
        }
        $eS  = function ($i) use ($linkS) {
            return mysqli_real_escape_string($linkS, $i);
        };
        $chk = mysqli_query(
            $linkS,
            "SELECT id, main_branch_id FROM `" . $eS($sourceDb) . "`.`tbl_branches` WHERE id = $branchRowId LIMIT 1"
        );
        if (!$chk || !($br = mysqli_fetch_assoc($chk))) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return ['ok' => false, 'message' => 'Branch id ' . $branchRowId . ' not found in master tbl_branches.'];
        }
        mysqli_free_result($chk);
        $mb             = (int) ($br['main_branch_id'] ?? 0);
        $registryMainId = ($mb === 0) ? (int) $br['id'] : $mb;
        return auragold_branch_mirror_family_separate_mysqli(
            $linkS,
            $linkT,
            $targetDb,
            $sourceDb,
            $registryMainId,
            $branchRowId
        );
    }
}

if (!function_exists('auragold_provision_branch_database_separate_mysqli')) {
    /**
     * cPanel / shared hosting: source MySQL user reads the template; branch user has ALL on the new empty DB.
     */
    function auragold_provision_branch_database_separate_mysqli(
        string $targetDb,
        string $sourceDb,
        int $branchRowId,
        array $opts
    ) {
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        if (!defined('AURAGOLD_CLONE_SOURCE_USER') || trim((string) AURAGOLD_CLONE_SOURCE_USER) === '') {
            return ['ok' => false, 'message' => 'Separate MySQL is enabled but AURAGOLD_CLONE_SOURCE_USER is not set in config.'];
        }
        $sUser  = trim((string) AURAGOLD_CLONE_SOURCE_USER);
        $sPass  = (string) (defined('AURAGOLD_CLONE_SOURCE_PASS') ? AURAGOLD_CLONE_SOURCE_PASS : '');
        $bUser  = trim((string) ($opts['branch_mysql_user'] ?? ''));
        $bPass  = (string) ($opts['branch_mysql_pass'] ?? '');
        error_log(
            'Auragold provision (two-MySQL, standalone): SOURCE read user=' . $sUser
            . ' database=' . $sourceDb
            . ' | TARGET user=' . $bUser
            . ' database=' . $targetDb
            . ' (passwords not logged)'
        );
        $copyAllData = !empty($opts['copy_all_data']);
        $useMinimal  = !empty($opts['minimal_schema']) && !$copyAllData;
        $fullClone   = !$useMinimal;
        $seedMaster  = $copyAllData
            ? false
            : (array_key_exists('seed_master', $opts) ? (bool) $opts['seed_master'] : true);
        $omitMaster  = [];
        if (!empty($opts['omit_master_table_names']) && is_array($opts['omit_master_table_names'])) {
            foreach ($opts['omit_master_table_names'] as $tn) {
                $tn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tn);
                if ($tn !== '') {
                    $omitMaster[strtolower($tn)] = true;
                }
            }
        }

        if ($bUser === '') {
            return ['ok' => false, 'message' => 'Branch MySQL user is required for two-connection provision (branch_mysql_user / branch_mysql_pass in opts).'];
        }

        $linkS = auragold_mysqli_provision_open($host, $sUser, $sPass, $sourceDb);
        $linkT = auragold_mysqli_provision_open($host, $bUser, $bPass, $targetDb);
        if (!$linkS) {
            return [
                'ok'      => false,
                'message' => 'Could not connect to template database as the clone user (check AURAGOLD_CLONE_SOURCE_* in config): '
                    . mysqli_connect_error(),
            ];
        }
        if (!$linkT) {
            mysqli_close($linkS);
            return [
                'ok'      => false,
                'message' => 'Could not connect to the new branch database (check cPanel user/grants and password). '
                    . mysqli_connect_error(),
            ];
        }
        mysqli_set_charset($linkS, 'utf8mb4');
        mysqli_set_charset($linkT, 'utf8mb4');

        if (!auragold_database_exists_on_schema($linkS, $sourceDb) || !auragold_database_exists_on_schema($linkT, $targetDb)) {
            mysqli_close($linkS);
            mysqli_close($linkT);
            return ['ok' => false, 'message' => 'Source or target database was not found on the server.'];
        }
        $eeC = @mysqli_query(
            $linkT,
            "SELECT COUNT(*) AS c FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = '" . mysqli_real_escape_string($linkT, $targetDb) . "'"
            . " AND TABLE_TYPE = 'BASE TABLE'"
        );
        $nTbl = 0;
        if ($eeC && ($rC = mysqli_fetch_assoc($eeC)) !== null) {
            $nTbl = (int) ($rC['c'] ?? 0);
        }
        if ($eeC) {
            mysqli_free_result($eeC);
        }
        if ($nTbl > 0) {
            mysqli_close($linkS);
            mysqli_close($linkT);
            return [
                'ok'      => false,
                'message' => "Database `$targetDb` already has tables; use an empty database or a different name.",
            ];
        }

        $tquoted    = '`' . str_replace('`', '``', $targetDb) . '`';
        $squoted    = '`' . str_replace('`', '``', $sourceDb) . '`';
        $tables     = auragold_branch_provision_table_names($linkS, $sourceDb, $fullClone);
        if (empty($tables)) {
            mysqli_close($linkS);
            mysqli_close($linkT);
            return [
                'ok'      => false,
                'message' => 'No tables to create from source `' . $sourceDb . '`.',
            ];
        }
        mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=0');
        $errors  = [];
        $created = 0;
        foreach ($tables as $t) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
                continue;
            }
            $e = auragold_provision_dual_create_table_from_show($linkS, $linkT, $sourceDb, $targetDb, $t);
            if ($e !== '') {
                $errors[] = $t . ': ' . $e;
            } else {
                $created++;
            }
        }
        if (!empty($errors)) {
            mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
            mysqli_close($linkS);
            mysqli_close($linkT);
            return [
                'ok'      => false,
                'message' => 'Table structure copy failed (two-MySQL connection mode).',
                'tables'  => $created,
                'details' => $errors,
            ];
        }

        $masterSeeded = 0;
        if ($copyAllData) {
            foreach ($tables as $t) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $t) || strcasecmp($t, 'tbl_branches') === 0) {
                    continue;
                }
                [$ok, $err] = auragold_provision_copy_table_data_separate_mysqli(
                    $linkS,
                    $linkT,
                    $sourceDb,
                    $targetDb,
                    $t
                );
                if (!$ok) {
                    $errors[] = $t . ' (data): ' . $err;
                }
            }
            $rb = auragold_branch_reset_tbl_branches_separate_mysqli(
                $linkS,
                $linkT,
                $targetDb,
                $sourceDb,
                $branchRowId,
                $tquoted,
                $squoted
            );
            if (empty($rb['ok'])) {
                $errors[] = 'tbl_branches: ' . ($rb['message'] ?? '');
            }
        } elseif ($seedMaster) {
            foreach (auragold_branch_master_tables_ordered() as $t) {
                if (isset($omitMaster[strtolower($t)])) {
                    continue;
                }
                if (!auragold_schema_table_exists($linkS, $sourceDb, $t)) {
                    continue;
                }
                [$ok, $err] = auragold_provision_copy_table_data_separate_mysqli(
                    $linkS,
                    $linkT,
                    $sourceDb,
                    $targetDb,
                    $t
                );
                if (!$ok) {
                    $errors[] = $t . ': ' . $err;
                } else {
                    $masterSeeded++;
                }
            }
            $rb = auragold_branch_reset_tbl_branches_separate_mysqli(
                $linkS,
                $linkT,
                $targetDb,
                $sourceDb,
                $branchRowId,
                $tquoted,
                $squoted
            );
            if (empty($rb['ok'])) {
                $errors[] = 'tbl_branches: ' . ($rb['message'] ?? '');
            }
        } else {
            $rb = auragold_branch_reset_tbl_branches_separate_mysqli(
                $linkS,
                $linkT,
                $targetDb,
                $sourceDb,
                $branchRowId,
                $tquoted,
                $squoted
            );
            if (empty($rb['ok'])) {
                $errors[] = 'tbl_branches: ' . ($rb['message'] ?? '');
            }
        }

        if (empty($errors) && auragold_schema_table_exists($linkT, $targetDb, 'tbl_bill_series')) {
            if (@mysqli_query($linkT, 'USE `' . mysqli_real_escape_string($linkT, $targetDb) . '`')) {
                @mysqli_query($linkT, 'DELETE FROM ' . $tquoted . '.`tbl_bill_series`');
            }
            require_once __DIR__ . '/auragold_seed_branch_bill_series.php';
            if (function_exists('auragold_seed_bill_series_for_new_branch') && @mysqli_select_db($linkT, $targetDb)) {
                $prevConn        = $GLOBALS['conn'] ?? null;
                $GLOBALS['conn'] = $linkT;
                auragold_seed_bill_series_for_new_branch($linkT, $branchRowId, 0);
                $GLOBALS['conn'] = $prevConn;
            }
        }

        mysqli_query($linkT, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_close($linkS);
        mysqli_close($linkT);

        if (!empty($errors)) {
            return [
                'ok'            => false,
                'message'       => 'Provisioning (two-MySQL) finished with errors.',
                'tables'        => $created,
                'master_seeded' => $masterSeeded,
                'details'       => $errors,
            ];
        }

        if ($copyAllData) {
            $msg = "Database `$targetDb` is ready: full data copy (two-MySQL) from `$sourceDb` for branch id $branchRowId.";
        } elseif ($useMinimal) {
            $msg = "Database `$targetDb` is ready: minimal two-MySQL clone, tbl_branches and bill series aligned (branch id $branchRowId).";
        } elseif ($seedMaster) {
            $msg = "Database `$targetDb` is ready: $created table(s) via two-MySQL, masters + tbl_branches, branch id $branchRowId.";
        } else {
            $msg = "Database `$targetDb` is ready: structure (two-MySQL), branch id $branchRowId.";
        }
        return [
            'ok'            => true,
            'message'       => $msg,
            'tables'        => $created,
            'master_seeded' => $masterSeeded,
        ];
    }
}

/**
 * @param string $targetDb      Destination database (tbl_branches.db_name)
 * @param string $sourceDb      Template schema (AURAGOLD_SCHEMA_CLONE_SOURCE_DB / main DB, e.g. goldmatrix_main)
 * @param int    $branchRowId   tbl_branches.id to keep in the new DB (same id as master)
 * @param array  $opts          copy_all_data (bool), seed_master, omit_master_table_names, minimal_schema, and
 *                              branch_mysql_user / branch_mysql_pass (cPanel new branch) — when AURAGOLD_CLONE_SOURCE_* in config
 *                              is set, enables two-MySQL clone (read template, write to branch) like a standalone cPanel import script
 * @return array{ok:bool,message:string,tables?:int,details?:array,master_seeded?:int}
 */
function auragold_provision_branch_database($targetDb, $sourceDb, $branchRowId, array $opts = []) {
    $targetDb = trim((string) $targetDb);
    $sourceDb = trim((string) $sourceDb);
    $branchRowId = (int) $branchRowId;

    $copyAllData = !empty($opts['copy_all_data']);
    $useMinimal  = !empty($opts['minimal_schema']) && !$copyAllData;
    $fullClone   = !$useMinimal;
    $seedMaster  = $copyAllData ? false : (array_key_exists('seed_master', $opts) ? (bool) $opts['seed_master'] : true);

    $omitMaster = [];
    if (!empty($opts['omit_master_table_names']) && is_array($opts['omit_master_table_names'])) {
        foreach ($opts['omit_master_table_names'] as $tn) {
            $tn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tn);
            if ($tn !== '') {
                $omitMaster[strtolower($tn)] = true;
            }
        }
    }

    if ($branchRowId <= 0) {
        return ['ok' => false, 'message' => 'Invalid branch record id.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $targetDb) || !preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
        return ['ok' => false, 'message' => 'Invalid database name. Use only letters, numbers, and underscores.'];
    }
    if (strcasecmp($targetDb, $sourceDb) === 0) {
        return ['ok' => false, 'message' => 'Branch database name must be different from the main database (' . $sourceDb . ').'];
    }

    if (function_exists('auragold_provision_branch_opts_use_separate_mysqli') && auragold_provision_branch_opts_use_separate_mysqli($opts)) {
        return auragold_provision_branch_database_separate_mysqli($targetDb, $sourceDb, $branchRowId, $opts);
    }

    $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
    $cred = auragold_provision_mysqli_resolved_creds_for_clone();
    $link = mysqli_init();
    if (!$link) {
        return ['ok' => false, 'message' => 'MySQL: could not allocate connection.'];
    }
    @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 60);
    try {
        if (!@mysqli_real_connect($link, (string) $host, (string) $cred[0], (string) $cred[1])) {
            $err = mysqli_connect_error();
            @mysqli_close($link);

            return ['ok' => false, 'message' => 'MySQL connection failed: ' . $err];
        }
    } catch (Throwable $e) {
        @mysqli_close($link);

        return ['ok' => false, 'message' => 'MySQL connection failed: ' . $e->getMessage()];
    }
    mysqli_set_charset($link, 'utf8mb4');

    $esc = function ($identifier) use ($link) {
        return mysqli_real_escape_string($link, $identifier);
    };

    $meta = null;
    $r = mysqli_query($link, "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $esc($sourceDb) . "' LIMIT 1");
    if ($r && mysqli_num_rows($r)) {
        $meta = mysqli_fetch_assoc($r);
    }
    $charset = preg_match('/^[a-zA-Z0-9_]+$/', (string) ($meta['DEFAULT_CHARACTER_SET_NAME'] ?? ''))
        ? $meta['DEFAULT_CHARACTER_SET_NAME'] : 'utf8mb4';
    $collate = preg_match('/^[a-zA-Z0-9_]+$/', (string) ($meta['DEFAULT_COLLATION_NAME'] ?? ''))
        ? $meta['DEFAULT_COLLATION_NAME'] : 'utf8mb4_unicode_ci';

    $tquoted = '`' . str_replace('`', '``', $targetDb) . '`';
    $squoted = '`' . str_replace('`', '``', $sourceDb) . '`';

    $sqlCreateDb = "CREATE DATABASE IF NOT EXISTS $tquoted CHARACTER SET $charset COLLATE $collate";
    if (!mysqli_query($link, $sqlCreateDb)) {
        $err = mysqli_error($link);
        mysqli_close($link);
        return ['ok' => false, 'message' => 'CREATE DATABASE failed: ' . $err];
    }

    $r = mysqli_query($link, "SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $esc($targetDb) . "'");
    $existing = 0;
    if ($r && $row = mysqli_fetch_assoc($r)) {
        $existing = (int) $row['c'];
    }
    if ($existing > 0) {
        mysqli_close($link);
        return [
            'ok'      => false,
            'message' => "Database `$targetDb` already contains $existing table(s). Drop or empty it in phpMyAdmin first, or use another db_name.",
        ];
    }

    $tables = auragold_branch_provision_table_names($link, $sourceDb, $fullClone);
    if (empty($tables)) {
        mysqli_close($link);
        return [
            'ok'      => false,
            'message' => 'No tables to create from source `' . $sourceDb . '`. ' . ($useMinimal ? 'Minimal schema list was empty; ensure tbl_branches exists in the registry.' : ''),
        ];
    }

    mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=0');
    $errors  = [];
    $created = 0;

    foreach ($tables as $t) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
            continue;
        }
        $tq = '`' . str_replace('`', '``', $t) . '`';
        if (!mysqli_query($link, "CREATE TABLE $tquoted.$tq LIKE $squoted.$tq")) {
            $errors[] = $t . ': ' . mysqli_error($link);
            continue;
        }
        $created++;
    }

    if (!empty($errors)) {
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_close($link);
        return [
            'ok'      => false,
            'message' => 'Table structure clone failed.',
            'tables'  => $created,
            'details' => $errors,
        ];
    }

    $masterSeeded = 0;

    if ($copyAllData) {
        foreach ($tables as $t) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $t) || strcasecmp($t, 'tbl_branches') === 0) {
                continue;
            }
            [$ok, $err] = auragold_copy_table_data($link, $targetDb, $sourceDb, $t, $tquoted, $squoted);
            if (!$ok) {
                $errors[] = $t . ' (data): ' . $err;
            }
        }
        $rb = auragold_branch_reset_tbl_branches($link, $targetDb, $sourceDb, $branchRowId, $tquoted, $squoted);
        if (!$rb['ok']) {
            $errors[] = 'tbl_branches: ' . $rb['message'];
        }
    } elseif ($seedMaster) {
        foreach (auragold_branch_master_tables_ordered() as $t) {
            if (isset($omitMaster[strtolower($t)])) {
                continue;
            }
            if (!auragold_schema_table_exists($link, $sourceDb, $t)) {
                continue;
            }
            [$ok, $err] = auragold_copy_table_data($link, $targetDb, $sourceDb, $t, $tquoted, $squoted);
            if (!$ok) {
                $errors[] = $t . ': ' . $err;
            } else {
                $masterSeeded++;
            }
        }
        $rb = auragold_branch_reset_tbl_branches($link, $targetDb, $sourceDb, $branchRowId, $tquoted, $squoted);
        if (!$rb['ok']) {
            $errors[] = 'tbl_branches: ' . $rb['message'];
        }
    } else {
        $rb = auragold_branch_reset_tbl_branches($link, $targetDb, $sourceDb, $branchRowId, $tquoted, $squoted);
        if (!$rb['ok']) {
            $errors[] = 'tbl_branches: ' . $rb['message'];
        }
    }

    // Bill series: do not rely on copied rows (mixed branch_id). One row per voucher type for this branch (SI-, OJB-, …).
    if (empty($errors) && auragold_schema_table_exists($link, $targetDb, 'tbl_bill_series')) {
        @mysqli_query($link, 'DELETE FROM ' . $tquoted . '.`tbl_bill_series`');
        require_once __DIR__ . '/auragold_seed_branch_bill_series.php';
        if (function_exists('auragold_seed_bill_series_for_new_branch') && @mysqli_select_db($link, $targetDb)) {
            $prevConn        = $GLOBALS['conn'] ?? null;
            $GLOBALS['conn'] = $link;
            auragold_seed_bill_series_for_new_branch($link, $branchRowId, 0);
            $GLOBALS['conn'] = $prevConn;
        }
    }

    mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
    mysqli_close($link);

    if (!empty($errors)) {
        return [
            'ok'            => false,
            'message'       => 'Provisioning finished with errors.',
            'tables'        => $created,
            'master_seeded' => $masterSeeded,
            'details'       => $errors,
        ];
    }

    if ($copyAllData) {
        $msg = "Database `$targetDb` is ready: full data copy from `$sourceDb`, with tbl_branches limited to main family for branch id $branchRowId.";
    } elseif ($useMinimal) {
        $msg = "Database `$targetDb` is ready: minimal schema ($created table(s), masters + tbl_branches + bill series), with tbl_branches for this family (ids match `$sourceDb`).";
    } elseif ($seedMaster) {
        $msg = "Database `$targetDb` is ready: $created table(s), Set Software + master data copied, tbl_branches lists main + all subs for this registry family (same ids as `$sourceDb`).";
    } else {
        $msg = "Database `$targetDb` is ready: $created table(s) (structure only), tbl_branches lists main + subs for this family.";
    }

    return [
        'ok'            => true,
        'message'       => $msg,
        'tables'        => $created,
        'master_seeded' => $masterSeeded,
    ];
}

/**
 * Row count for a base table, or -1 on failure.
 */
function auragold_branch_table_row_count_in_schema(mysqli $link, string $tquoted, string $table) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return -1;
    }
    $tq = '`' . str_replace('`', '``', $table) . '`';
    $r  = @mysqli_query($link, "SELECT COUNT(*) AS c FROM $tquoted.$tq");
    if (!$r || !($row = mysqli_fetch_assoc($r))) {
        if ($r) {
            mysqli_free_result($r);
        }
        return -1;
    }
    $c = (int) ($row['c'] ?? 0);
    mysqli_free_result($r);
    return $c;
}

/**
 * An existing branch database has some tables; add any missing from the registry, optionally seed empty masters,
 * re-sync tbl_branches family, re-seed bill series (same as full provision, without requiring an empty database).
 *
 * @param array $opts seed_masters (bool, default true), omit_master_table_names (string[] like provision)
 * @return array{ok:bool,message:string,created?:int,master_seeded?:int,details?:array}
 */
function auragold_branch_backfill_from_registry(string $targetDb, string $sourceDb, int $branchRowId, array $opts = []) {
    $targetDb    = trim($targetDb);
    $sourceDb    = trim($sourceDb);
    $branchRowId = (int) $branchRowId;
    if ($branchRowId <= 0) {
        return ['ok' => false, 'message' => 'Invalid branch record id.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $targetDb) || !preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
        return ['ok' => false, 'message' => 'Invalid database name.'];
    }
    if (strcasecmp($targetDb, $sourceDb) === 0) {
        return ['ok' => false, 'message' => 'Branch database must be different from the registry database.'];
    }

    $seedMasters = !array_key_exists('seed_masters', $opts) || (bool) $opts['seed_masters'];
    $omitMaster  = [];
    if (!empty($opts['omit_master_table_names']) && is_array($opts['omit_master_table_names'])) {
        foreach ($opts['omit_master_table_names'] as $tn) {
            $tn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tn);
            if ($tn !== '') {
                $omitMaster[strtolower($tn)] = true;
            }
        }
    }

    $link = mysqli_init();
    if (!$link) {
        return ['ok' => false, 'message' => 'MySQL: could not allocate connection.'];
    }
    @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 60);
    $bHost   = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
    $bCred   = auragold_provision_mysqli_resolved_creds_for_clone();
    $bU      = (string) $bCred[0];
    $bP      = (string) $bCred[1];
    try {
        if (!@mysqli_real_connect($link, $bHost, $bU, $bP)) {
            $err = mysqli_connect_error();
            @mysqli_close($link);
            return ['ok' => false, 'message' => 'MySQL connection failed: ' . $err];
        }
    } catch (Throwable $e) {
        @mysqli_close($link);
        return ['ok' => false, 'message' => 'MySQL connection failed: ' . $e->getMessage()];
    }
    mysqli_set_charset($link, 'utf8mb4');

    if (!auragold_database_exists_on_schema($link, $sourceDb) || !auragold_database_exists_on_schema($link, $targetDb)) {
        mysqli_close($link);
        return ['ok' => false, 'message' => 'Source or target database not found.'];
    }

    $tquoted   = '`' . str_replace('`', '``', $targetDb) . '`';
    $squoted   = '`' . str_replace('`', '``', $sourceDb) . '`';
    $allTables = auragold_branch_provision_table_names($link, $sourceDb, true);
    if (empty($allTables)) {
        mysqli_close($link);
        return ['ok' => false, 'message' => 'No base tables in registry `' . $sourceDb . '`.'];
    }

    mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=0');
    $errors   = [];
    $created  = 0;
    foreach ($allTables as $t) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
            continue;
        }
        if (auragold_schema_table_exists($link, $targetDb, $t)) {
            continue;
        }
        $tq = '`' . str_replace('`', '``', $t) . '`';
        if (!mysqli_query($link, "CREATE TABLE $tquoted.$tq LIKE $squoted.$tq")) {
            $errors[] = $t . ': ' . mysqli_error($link);
            continue;
        }
        $created++;
    }

    $masterSeeded = 0;
    if ($seedMasters && empty($errors)) {
        foreach (auragold_branch_master_tables_ordered() as $t) {
            if (isset($omitMaster[strtolower($t)])) {
                continue;
            }
            if (strcasecmp($t, 'tbl_branches') === 0) {
                continue;
            }
            if (!auragold_schema_table_exists($link, $sourceDb, $t) || !auragold_schema_table_exists($link, $targetDb, $t)) {
                continue;
            }
            if (auragold_branch_table_row_count_in_schema($link, $tquoted, $t) > 0) {
                continue;
            }
            [$ok, $err] = auragold_copy_table_data($link, $targetDb, $sourceDb, $t, $tquoted, $squoted);
            if (!$ok) {
                $errors[] = $t . ' (data): ' . $err;
            } else {
                $masterSeeded++;
            }
        }
    }

    if (!empty($errors)) {
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_close($link);
        return [
            'ok'            => false,
            'message'       => 'Backfill finished with errors.',
            'created'       => $created,
            'master_seeded' => $masterSeeded,
            'details'       => $errors,
        ];
    }

    $rb = auragold_branch_reset_tbl_branches($link, $targetDb, $sourceDb, $branchRowId, $tquoted, $squoted);
    if (!$rb['ok']) {
        mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
        mysqli_close($link);
        return [
            'ok'            => false,
            'message'       => 'Tables updated but tbl_branches sync failed: ' . ($rb['message'] ?? ''),
            'created'       => $created,
            'master_seeded' => $masterSeeded,
        ];
    }

    if (auragold_schema_table_exists($link, $targetDb, 'tbl_bill_series')) {
        @mysqli_query($link, 'DELETE FROM ' . $tquoted . '.`tbl_bill_series`');
        require_once __DIR__ . '/auragold_seed_branch_bill_series.php';
        if (function_exists('auragold_seed_bill_series_for_new_branch') && @mysqli_select_db($link, $targetDb)) {
            $prevConn        = $GLOBALS['conn'] ?? null;
            $GLOBALS['conn'] = $link;
            auragold_seed_bill_series_for_new_branch($link, $branchRowId, 0);
            $GLOBALS['conn'] = $prevConn;
        }
    }

    mysqli_query($link, 'SET FOREIGN_KEY_CHECKS=1');
    mysqli_close($link);

    $msg = "Backfill complete: $created new table(s) from `$sourceDb`, master data copied to empty tables where needed, tbl_branches and bill series aligned for branch id $branchRowId.";
    return [
        'ok'            => true,
        'message'       => $msg,
        'created'       => $created,
        'master_seeded' => $masterSeeded,
    ];
}
