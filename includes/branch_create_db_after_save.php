<?php
/**
 * After tbl_branches INSERT: optionally CREATE DATABASE + import schema (database/structure.sql or clone from registry).
 */
if (!function_exists('auragold_branch_mysql_identifier_ok')) {
    function auragold_branch_mysql_identifier_ok(string $name): bool {
        return $name !== '' && (bool) preg_match('/^[a-zA-Z0-9_]+$/', $name);
    }
}

if (!function_exists('auragold_database_exists_on_connection')) {
    function auragold_database_exists_on_connection(mysqli $link, string $dbName): bool {
        if (!auragold_branch_mysql_identifier_ok($dbName)) {
            return false;
        }
        $e = mysqli_real_escape_string($link, $dbName);
        $r = @mysqli_query(
            $link,
            "SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$e' LIMIT 1"
        );
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_mysqli_connect_branch_or_registry')) {
    /**
     * Connect to a branch schema. On production, only the per-branch MySQL user (tbl_branches) is used for that DB
     * (no DB_USER on the new branch DB; matches cPanel grant scope). On local, tries registry then per-branch.
     * PHP 8.1+ may throw mysqli_sql_exception on failed connect — catch and try the next pair.
     */
    function auragold_mysqli_connect_branch_or_registry(string $host, string $dbName, string $dbUser, string $dbPass): ?mysqli {
        $dbName = trim($dbName);
        if ($dbName === '') {
            return null;
        }
        if (defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT === 'prod') {
            $attempts = [];
            $u = trim($dbUser);
            if ($u !== '') {
                $attempts[] = [$u, (string) $dbPass];
            }
            if (defined('DB_USER')) {
                $ru = (string) DB_USER;
                $rp = defined('DB_PASS') ? (string) DB_PASS : '';
                if ($ru !== '') {
                    $dup = false;
                    foreach ($attempts as $a) {
                        if ($a[0] === $ru && $a[1] === $rp) {
                            $dup = true;
                            break;
                        }
                    }
                    if (!$dup) {
                        $attempts[] = [$ru, $rp];
                    }
                }
            }
            if ($attempts === []) {
                return null;
            }
            foreach ($attempts as $pair) {
                $link = mysqli_init();
                if (!$link) {
                    continue;
                }
                @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                try {
                    if (@mysqli_real_connect($link, $host, $pair[0], $pair[1], $dbName)) {
                        return $link;
                    }
                } catch (Throwable $e) {
                }
                @mysqli_close($link);
            }
            return null;
        }
        $attempts = [];
        if (defined('DB_USER')) {
            $ru = (string) DB_USER;
            $rp = defined('DB_PASS') ? (string) DB_PASS : '';
            if ($ru !== '') {
                $attempts[] = [$ru, $rp];
            }
        }
        $u = trim($dbUser);
        if ($u !== '') {
            $dup = false;
            foreach ($attempts as $a) {
                if ($a[0] === $u && $a[1] === (string) $dbPass) {
                    $dup = true;
                    break;
                }
            }
            if (!$dup) {
                $attempts[] = [$u, (string) $dbPass];
            }
        }
        foreach ($attempts as $pair) {
            $link = mysqli_init();
            if (!$link) {
                continue;
            }
            @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            try {
                if (@mysqli_real_connect($link, $host, $pair[0], $pair[1], $dbName)) {
                    return $link;
                }
            } catch (Throwable $e) {
                // Failed attempt; try next credential pair.
            }
            @mysqli_close($link);
        }
        return null;
    }
}

if (!function_exists('auragold_run_mysqli_multi_query_safe')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_run_mysqli_multi_query_safe(mysqli $link, string $sql): array {
        $sql = trim($sql);
        if ($sql === '') {
            return ['ok' => false, 'message' => 'Empty SQL.'];
        }
        if (!@mysqli_multi_query($link, $sql)) {
            return ['ok' => false, 'message' => mysqli_error($link)];
        }
        do {
            if ($res = mysqli_store_result($link)) {
                mysqli_free_result($res);
            }
        } while (mysqli_more_results($link) && @mysqli_next_result($link));

        if (mysqli_errno($link)) {
            return ['ok' => false, 'message' => mysqli_error($link)];
        }
        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('auragold_structure_sql_path')) {
    function auragold_structure_sql_path(): string {
        return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'structure.sql';
    }
}

if (!function_exists('auragold_count_tables_in_schema')) {
    function auragold_count_tables_in_schema(mysqli $link, string $schema): int {
        if (!auragold_branch_mysql_identifier_ok($schema)) {
            return -1;
        }
        $e = mysqli_real_escape_string($link, $schema);
        $r = @mysqli_query(
            $link,
            "SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$e' AND TABLE_TYPE = 'BASE TABLE'"
        );
        if (!$r || !($row = mysqli_fetch_assoc($r))) {
            return -1;
        }
        mysqli_free_result($r);
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('verifyNewBranchDatabaseConnection')) {
    /**
     * Verify mysqli can open the branch DB. Callers pass final cPanel-prefixed db name and user (do not re-prefix here).
     * Retries with delay. Password is never logged.
     *
     * @return array{0:bool,1:?string} [ok, last_connect_error]
     */
    function verifyNewBranchDatabaseConnection(
        $host,
        $dbUser,
        $dbPass,
        $dbName,
        $attempts = 10,
        $delaySeconds = 2
    ): array {
        $dbName = trim((string) $dbName);
        $dbUser = trim((string) $dbUser);
        $lastError = null;
        for ($i = 1; $i <= $attempts; $i++) {
            $conn = @mysqli_connect($host, $dbUser, $dbPass, $dbName);
            if ($conn) {
                mysqli_close($conn);
                return [true, null];
            }
            $lastError = mysqli_connect_error();
            error_log("Retry {$i}: DB not ready yet → " . (string) $lastError);
            sleep((int) $delaySeconds);
        }
        error_log(
            'verifyNewBranchDatabaseConnection: FAILED after ' . (int) $attempts . ' attempts'
            . ' host=' . (string) $host
            . ' db=' . $dbName
            . ' user=' . $dbUser
            . ' last_mysqli_error=' . (string) $lastError
        );
        return [false, $lastError];
    }
}

if (!function_exists('auragold_prod_verify_registry_user_opens_database')) {
    /**
     * After cPanel set_privileges_on_database: confirm DB_USER can open the branch schema before import.
     *
     * @return array{ok:bool,message:string}
     */
    function auragold_prod_verify_registry_user_opens_database(string $host, string $dbName): array {
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'message' => ''];
        }
        if (!function_exists('auragoldEnsureCpanelPrefix')) {
            require_once __DIR__ . '/cpanel_mysql_create_database.php';
        }
        $dbName = trim($dbName);
        if ($dbName === '' || !defined('DB_USER') || trim((string) DB_USER) === '') {
            return ['ok' => false, 'message' => 'Cannot verify branch database access: missing database name or DB_USER.'];
        }
        $apPrefix  = auragold_cpanel_auragold_db_prefix_string();
        $uRaw      = trim((string) DB_USER);
        $dbIn      = $dbName;
        $dbNameV   = auragoldEnsureCpanelPrefix($dbName, $apPrefix);
        $userV     = auragoldEnsureCpanelPrefix($uRaw, $apPrefix);
        if (strlen($userV) > 32) {
            $userV = substr($userV, 0, 32);
        }
        if ($dbNameV === '' || $userV === '') {
            return ['ok' => false, 'message' => 'Cannot verify branch database access: invalid cPanel-prefixed names.'];
        }
        $dbName  = $dbNameV;
        $pass    = defined('DB_PASS') ? (string) DB_PASS : '';
        if (function_exists('verifyNewBranchDatabaseConnection')) {
            error_log('Auragold [prod] verify registry: database_name_raw=' . $dbIn
                . ' user_raw(DB_USER)=' . $uRaw
                . ' mysql_user_normalized=' . $userV
                . ' database_name_normalized=' . $dbNameV
                . ' (host=' . $host . ')');
            $pair = verifyNewBranchDatabaseConnection($host, $userV, $pass, $dbNameV);
            if (empty($pair[0])) {
                $err = isset($pair[1]) && is_string($pair[1]) ? $pair[1] : '';
                if ($err === '' || $err === 'Unknown error') {
                    $err = (string) mysqli_connect_error();
                }
                if ($err === '') {
                    $err = '(empty after retries; see error_log for attempt lines)';
                }
                error_log('Auragold [prod] verify connection failed after retries, mysqli_connect_error: ' . $err);
                return [
                    'ok'      => false,
                    'message' => 'After cPanel set_privileges_on_database, user `' . $userV
                        . '` still cannot open `' . $dbNameV . '`: ' . $err,
                ];
            }
            error_log('Auragold [prod] verify connection: success DB=' . $dbNameV . ' USER=' . $userV);
        }

        $link = @mysqli_connect($host, $userV, $pass, $dbNameV);
        if (!$link) {
            $err = mysqli_connect_error();
            if (function_exists('verifyNewBranchDatabaseConnection')) {
                return [
                    'ok'      => false,
                    'message' => 'After cPanel set_privileges_on_database, could not re-open for SHOW GRANTS: ' . $err,
                ];
            }
            $link2 = mysqli_init();
            if (!$link2) {
                return ['ok' => false, 'message' => 'mysqli_init failed while verifying branch database access.'];
            }
            @mysqli_options($link2, MYSQLI_OPT_CONNECT_TIMEOUT, 15);
            try {
                if (!@mysqli_real_connect($link2, $host, $userV, $pass, $dbNameV)) {
                    $e = mysqli_connect_error();
                    @mysqli_close($link2);
                    return [
                        'ok'      => false,
                        'message' => 'After cPanel set_privileges_on_database, user `' . $userV
                            . '` still cannot open `' . $dbNameV . '`: ' . $e,
                    ];
                }
                $link = $link2;
            } catch (Throwable $e) {
                @mysqli_close($link2);
                return [
                    'ok'      => false,
                    'message' => 'After cPanel grant, user `' . $userV . '` could not open `' . $dbNameV . '`: ' . $e->getMessage(),
                ];
            }
        }

        $grantsOk = false;
        $gr       = @mysqli_query($link, 'SHOW GRANTS FOR CURRENT_USER()');
        if ($gr) {
            $lines = [];
            while ($row = mysqli_fetch_row($gr)) {
                $lines[] = (string) ($row[0] ?? '');
            }
            mysqli_free_result($gr);
            error_log('cPanel privilege verify SHOW GRANTS lines: ' . json_encode($lines, JSON_UNESCAPED_UNICODE));
            $quoted = '`' . str_replace('`', '``', $dbNameV) . '`';
            foreach ($lines as $line) {
                if (stripos($line, 'ALL PRIVILEGES ON *.*') !== false || stripos($line, 'ALL PRIVILEGES ON `*`') !== false) {
                    $grantsOk = true;
                    break;
                }
                if (stripos($line, $quoted) !== false || preg_match('/\bON\s+[`\']?' . preg_quote($dbNameV, '/') . '[`\']?\s*\./i', $line)) {
                    $grantsOk = true;
                    break;
                }
            }
            if (!$grantsOk && $lines !== []) {
                mysqli_close($link);
                return [
                    'ok'      => false,
                    'message' => 'MySQL SHOW GRANTS for `' . $userV . '` does not show privileges on `' . $dbNameV
                        . '`. cPanel may not have linked this user to the database. First grants: '
                        . implode(' | ', array_slice($lines, 0, 3)),
                ];
            }
        }

        mysqli_close($link);
        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('auragold_prod_cpanel_branch_provision_user_grant_and_verify')) {
    /**
     * Production only: cPanel create_user, set_privileges for the new branch user on the new DB only, mysqli verify with that user.
     *
     * @return array{ok:bool,message:string,final_db_name?:string,final_user?:string}
     */
    function auragold_prod_cpanel_branch_provision_user_grant_and_verify(
        string $host,
        string $dbName,
        string $dbUser,
        string $dbPass,
        mysqli $conn_master,
        int $newBranchId
    ): array {
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'message' => ''];
        }
        if (!function_exists('auragold_cpanel_uapi_mysql_create_user')) {
            require_once __DIR__ . '/cpanel_mysql_create_database.php';
        }
        $apPrefix  = auragold_cpanel_auragold_db_prefix_string();
        $rawDb     = trim($dbName);
        $rawU      = trim($dbUser);
        $finalDb   = auragoldEnsureCpanelPrefix($rawDb, $apPrefix);
        $finalU    = auragoldEnsureCpanelPrefix($rawU, $apPrefix);
        if (strlen($finalU) > 32) {
            $finalU = substr($finalU, 0, 32);
        }
        error_log('Branch provision (prod cPanel) raw DB=' . $rawDb . ', final DB=' . $finalDb);
        error_log('Branch provision (prod cPanel) raw USER=' . $rawU . ', final USER=' . $finalU);

        $cUser = auragold_cpanel_uapi_mysql_create_user($rawU, $dbPass);
        error_log('Auragold [prod] cPanel Mysql::create_user response: ' . print_r($cUser, true));
        if (empty($cUser['ok'])) {
            $msg = trim((string) ($cUser['message'] ?? 'UAPI create_user failed. See PHP error log for cPanel response.'));
            return [
                'ok'      => false,
                'message' => 'Production: could not create the MySQL user in cPanel. ' . $msg,
            ];
        }
        if (!empty($cUser['user_full_name'])) {
            $finalU = (string) $cUser['user_full_name'];
            if (strlen($finalU) > 32) {
                $finalU = substr($finalU, 0, 32);
            }
        }
        sleep(3);
        $p1 = auragold_cpanel_uapi_mysql_set_privileges_on_database_for_user($finalDb, $finalU, 'branch_user');
        error_log('Auragold [prod] cPanel set_privileges (branch) response: ' . print_r($p1, true));
        if (empty($p1['ok'])) {
            return [
                'ok'      => false,
                'message' => $p1['message'] ?? 'cPanel set_privileges (branch user) failed.',
            ];
        }
        error_log('Auragold [prod] FINAL DB NAME: ' . $finalDb);
        error_log('Auragold [prod] FINAL USER: ' . $finalU);
        error_log(
            'Auragold [prod] verify: using TARGET (new branch) MySQL credentials for mysqli_connect; host='
            . (string) $host . ' (password not logged; must match cPanel + tbl_branches)'
        );
        $bVerify = function_exists('verifyNewBranchDatabaseConnection')
            ? verifyNewBranchDatabaseConnection($host, $finalU, $dbPass, $finalDb)
            : [false, 'verify not loaded'];
        error_log('Auragold [prod] mysqli verify (TARGET branch user): ' . var_export($bVerify, true));
        if (empty($bVerify[0])) {
            $err = isset($bVerify[1]) && is_string($bVerify[1]) ? $bVerify[1] : '';
            error_log(
                'Auragold [prod] branch verify failed: host=' . (string) $host
                . ' final_db=' . $finalDb . ' final_user=' . $finalU
                . ' mysqli=' . (string) $err
            );
            return [
                'ok'      => false,
                'message' => 'Production: branch MySQL user could not connect after cPanel. ' . ($err !== '' ? $err : 'see error log'),
            ];
        }
        if ($newBranchId > 0) {
            $e1 = mysqli_real_escape_string($conn_master, $finalDb);
            $e2 = mysqli_real_escape_string($conn_master, $finalU);
            if (@mysqli_query(
                $conn_master,
                'UPDATE tbl_branches SET db_name = \'' . $e1 . '\', db_users = \'' . $e2
                . '\' WHERE id = ' . (int) $newBranchId
            )) {
                error_log('Auragold [prod] tbl_branches updated: id=' . (int) $newBranchId . ' db_name=' . $finalDb . ' db_users=' . $finalU);
            } else {
                error_log('Auragold [prod] tbl_branches final UPDATE failed: ' . mysqli_error($conn_master));
            }
        }
        return [
            'ok'            => true,
            'message'       => '',
            'final_db_name' => $finalDb,
            'final_user'    => $finalU,
        ];
    }
}

if (!function_exists('auragold_after_branch_insert_create_db_and_schema')) {
    /**
     * @param array $provisionOpts Merged into auragold_provision_branch_database options (e.g. omit_master_table_names).
     * @return array{ok:bool,skipped?:bool,message:string,provisioned?:bool}
     */
    function auragold_after_branch_insert_create_db_and_schema(
        mysqli $conn_master,
        string $db_name,
        string $db_user,
        string $db_pass,
        int $newBranchId,
        array $provisionOpts = []
    ): array {
        $db_name = trim($db_name);
        if ($db_name === '') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }

        if (!auragold_branch_mysql_identifier_ok($db_name)) {
            $msg = 'Invalid database name (use letters, numbers, underscores only).';
            error_log('AuraGold branch_create_db: ' . $msg . ' value=' . $db_name);
            return ['ok' => false, 'message' => $msg];
        }

        if (defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT === 'prod') {
            error_log(
                'Auragold [prod] branch DB provision: new_branch_id=' . (int) $newBranchId
                . ' database_name_requested=' . $db_name
                . ' branch_mysql_user_requested=' . $db_user
                . ' (per-branch user password not logged; cPanel grants/verify are branch user only; clone uses AURAGOLD_CLONE_*)'
            );
            if (!function_exists('auragoldEnsureCpanelPrefix')) {
                require_once __DIR__ . '/cpanel_mysql_create_database.php';
            }
            if (function_exists('auragold_cpanel_auragold_db_prefix_string') && function_exists('auragoldEnsureCpanelPrefix')) {
                $apPrefix = auragold_cpanel_auragold_db_prefix_string();
                $nName    = auragoldEnsureCpanelPrefix($db_name, $apPrefix);
                $nUser    = auragoldEnsureCpanelPrefix(trim((string) $db_user), $apPrefix);
                if (strlen($nUser) > 32) {
                    $nUser = substr($nUser, 0, 32);
                }
                if ($nName !== '' && $nUser !== '' && auragold_branch_mysql_identifier_ok($nName) && (bool) preg_match('/^[a-zA-Z0-9_]+$/', $nUser)) {
                    if (strcasecmp($nName, $db_name) !== 0 || (string) $nUser !== (string) $db_user) {
                        error_log('Auragold [prod] cPanel: normalized for tbl_branches — raw short db name/user → full prefix; db_name: '
                            . $db_name . ' -> ' . $nName . ' db_user: ' . $db_user . ' -> ' . $nUser);
                    }
                    if ((int) $newBranchId > 0
                        && (strcasecmp($nName, $db_name) !== 0 || (string) $nUser !== (string) $db_user)) {
                        $e1  = mysqli_real_escape_string($conn_master, $nName);
                        $e2  = mysqli_real_escape_string($conn_master, $nUser);
                        $qUp = 'UPDATE tbl_branches SET db_name = \'' . $e1 . '\', db_users = \'' . $e2
                            . '\' WHERE id = ' . (int) $newBranchId;
                        if (!@mysqli_query($conn_master, $qUp)) {
                            error_log('Auragold [prod] UPDATE tbl_branches cPanel-prefixed names failed: ' . mysqli_error($conn_master));
                        }
                    }
                    $db_name = $nName;
                    $db_user = $nUser;
                }
            }
        }

        if (!function_exists('auragold_branch_schema_clone_source_db')) {
            require_once __DIR__ . '/branch_database_provision.php';
        }
        $sourceDb = auragold_branch_schema_clone_source_db();

        if (defined('DB_NAME') && strcasecmp($db_name, (string) DB_NAME) === 0) {
            $msg = 'Branch database name must differ from the application (master) database.';
            error_log('AuraGold branch_create_db: ' . $msg);
            return ['ok' => false, 'message' => $msg];
        }
        if ($sourceDb !== '' && strcasecmp($db_name, $sourceDb) === 0) {
            $msg = 'Branch database name must differ from the schema template database (usually the central registry, e.g. ' . $sourceDb . ').';
            error_log('AuraGold branch_create_db: ' . $msg);
            return ['ok' => false, 'message' => $msg];
        }
        $dbExists = auragold_database_exists_on_connection($conn_master, $db_name);
        $tblCount = $dbExists ? auragold_count_tables_in_schema($conn_master, $db_name) : 0;
        if ($dbExists && $tblCount < 0) {
            error_log('AuraGold branch_create_db: could not count tables in `' . $db_name . '`');
            return ['ok' => false, 'message' => 'Could not inspect the branch database (permissions or connection).'];
        }

        if ($dbExists && $tblCount > 0) {
            error_log(
                'AuraGold branch_create_db: database `' . $db_name
                . '` already has tables; skipping create/import (branch id ' . (int) $newBranchId . ').'
            );
            return [
                'ok'      => true,
                'skipped' => true,
                'message' => 'Database already exists with tables; skipped create/import.',
            ];
        }

        $seedOpts = array_merge(
            [
                'seed_master'       => true,
                'minimal_schema'    => defined('AURAGOLD_BRANCH_MINIMAL_SCHEMA') && AURAGOLD_BRANCH_MINIMAL_SCHEMA,
                'branch_mysql_user' => $db_user,
                'branch_mysql_pass' => $db_pass,
            ],
            $provisionOpts
        );
        $branchDbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';

        if ($dbExists && $tblCount === 0 && $sourceDb !== '' && auragold_branch_mysql_identifier_ok($sourceDb)) {
            $gateEmpty = auragold_prod_cpanel_branch_provision_user_grant_and_verify(
                $branchDbHost,
                $db_name,
                $db_user,
                $db_pass,
                $conn_master,
                $newBranchId
            );
            if (empty($gateEmpty['ok'])) {
                return [
                    'ok'      => false,
                    'message' => $gateEmpty['message'] ?? 'Branch database privileges were not applied; schema import skipped.',
                ];
            }
            if (!empty($gateEmpty['final_db_name']) && (string) $gateEmpty['final_db_name'] !== (string) $db_name) {
                $db_name = (string) $gateEmpty['final_db_name'];
            }
            if (!empty($gateEmpty['final_user']) && (string) $gateEmpty['final_user'] !== (string) $db_user) {
                $db_user = (string) $gateEmpty['final_user'];
            }
            if (!function_exists('auragold_provision_branch_database')) {
                require_once __DIR__ . '/branch_database_provision.php';
            }
            $prov = auragold_provision_branch_database($db_name, $sourceDb, $newBranchId, $seedOpts);
            if (empty($prov['ok'])) {
                error_log('AuraGold branch_create_db: provision empty DB failed: ' . ($prov['message'] ?? ''));
                return [
                    'ok'      => false,
                    'message' => 'Empty database found but clone failed: ' . ($prov['message'] ?? ''),
                ];
            }
            return [
                'ok'          => true,
                'message'     => $prov['message'] ?? 'Branch database provisioned from registry.',
                'provisioned' => true,
            ];
        }

        $charset = 'utf8mb4';
        $collate = 'utf8mb4_unicode_ci';
        if ($sourceDb !== '' && auragold_branch_mysql_identifier_ok($sourceDb)) {
            $eReg = mysqli_real_escape_string($conn_master, $sourceDb);
            $meta = @mysqli_query(
                $conn_master,
                "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$eReg' LIMIT 1"
            );
            if ($meta && ($row = mysqli_fetch_assoc($meta))) {
                $c = (string) ($row['DEFAULT_CHARACTER_SET_NAME'] ?? '');
                $o = (string) ($row['DEFAULT_COLLATION_NAME'] ?? '');
                if ($c !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
                    $charset = $c;
                }
                if ($o !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $o)) {
                    $collate = $o;
                }
            }
            if ($meta) {
                mysqli_free_result($meta);
            }
        }

        if (!$dbExists) {
            if (defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT === 'prod') {
                if (!function_exists('auragold_cpanel_uapi_mysql_create_database')) {
                    require_once __DIR__ . '/cpanel_mysql_create_database.php';
                }
                $nameBeforeCpanel = $db_name;
                $cpRes            = auragold_cpanel_uapi_mysql_create_database($db_name);
                if (empty($cpRes['ok'])) {
                    error_log(
                        'Auragold [prod] cPanel create_database: failed message=' . ($cpRes['message'] ?? '')
                        . ' database_requested=' . $nameBeforeCpanel
                    );
                    return [
                        'ok'      => false,
                        'message' => $cpRes['message'] ?? 'Could not create database on production server.',
                    ];
                }
                $eff = trim((string) ($cpRes['db_name_effective'] ?? $db_name));
                if ($eff === '') {
                    $eff = $db_name;
                }
                if (strcasecmp($eff, $nameBeforeCpanel) !== 0) {
                    error_log(
                        'Auragold [prod] cPanel create_database: db_name in tbl was '
                        . $nameBeforeCpanel . ' cPanel effective full name is ' . $eff . ' — updating row id ' . (int) $newBranchId
                    );
                    $eEsc = mysqli_real_escape_string($conn_master, $eff);
                    if (@mysqli_query(
                        $conn_master,
                        'UPDATE tbl_branches SET db_name = \'' . $eEsc . '\' WHERE id = ' . (int) $newBranchId
                    )) {
                        $db_name = $eff;
                    } else {
                        error_log(
                            'Auragold [prod] UPDATE tbl_branches db_name to match cPanel failed: '
                            . mysqli_error($conn_master) . ' — continuing with cPanel name in this request only'
                        );
                        $db_name = $eff;
                    }
                } else {
                    $db_name = $eff;
                }
                sleep(3);
                if (auragold_database_exists_on_connection($conn_master, $db_name)) {
                    error_log('Auragold [prod] cPanel post-create: registry can see new DB in information_schema: ' . $db_name);
                } else {
                    error_log(
                        'Auragold [prod] cPanel post-create: registry $conn_master does not list `'
                        . $db_name
                        . '` in information_schema yet; continuing. Authoritative check is TARGET branch user mysqli after create_user+set_privileges (same as standalone script).'
                    );
                }
                $dbExists = true;
            } else {
                $qDb       = '`' . str_replace('`', '``', $db_name) . '`';
                $sqlCreate = 'CREATE DATABASE ' . $qDb . ' CHARACTER SET ' . $charset . ' COLLATE ' . $collate;
                if (!@mysqli_query($conn_master, $sqlCreate)) {
                    $err = mysqli_error($conn_master);
                    error_log('AuraGold branch_create_db: CREATE DATABASE failed: ' . $err);
                    return ['ok' => false, 'message' => 'Could not create database: ' . $err];
                }
            }
        }

        $gateNew = auragold_prod_cpanel_branch_provision_user_grant_and_verify(
            $branchDbHost,
            $db_name,
            $db_user,
            $db_pass,
            $conn_master,
            $newBranchId
        );
        if (empty($gateNew['ok'])) {
            return [
                'ok'      => false,
                'message' => $gateNew['message'] ?? 'Branch database privileges were not applied; schema import skipped.',
            ];
        }
        if (!empty($gateNew['final_db_name']) && (string) $gateNew['final_db_name'] !== (string) $db_name) {
            $db_name = (string) $gateNew['final_db_name'];
        }
        if (!empty($gateNew['final_user']) && (string) $gateNew['final_user'] !== (string) $db_user) {
            $db_user = (string) $gateNew['final_user'];
        }

        $newConn = auragold_mysqli_connect_branch_or_registry($branchDbHost, $db_name, $db_user, $db_pass);
        if (!$newConn) {
            // DB may exist empty after CREATE DATABASE; do not leave it unprovisioned — clone uses registry credentials only.
            $cerr = mysqli_connect_error();
            error_log('AuraGold branch_create_db: connect to new DB failed; falling back to provision: ' . $cerr);
            if (!function_exists('auragold_provision_branch_database')) {
                require_once __DIR__ . '/branch_database_provision.php';
            }
            $sourceDbFallback = auragold_branch_schema_clone_source_db();
            if ($sourceDbFallback !== '' && auragold_branch_mysql_identifier_ok($sourceDbFallback)) {
                $provFb = auragold_provision_branch_database($db_name, $sourceDbFallback, $newBranchId, $seedOpts);
                if (!empty($provFb['ok'])) {
                    return [
                        'ok'            => true,
                        'message'       => $provFb['message'] ?? 'Branch database provisioned from registry.',
                        'provisioned'   => true,
                    ];
                }
                return [
                    'ok'      => false,
                    'message' => 'Database exists but connection and provisioning both failed. Last error: ' . ($provFb['message'] ?? $cerr),
                ];
            }

            return ['ok' => false, 'message' => 'Database was created but connection failed: ' . $cerr];
        }
        mysqli_set_charset($newConn, 'utf8mb4');

        $path   = auragold_structure_sql_path();
        $rawSql = @file_get_contents($path);
        $useSql = is_string($rawSql) && preg_match('/CREATE\s+TABLE/i', $rawSql);

        if ($useSql) {
            $run = auragold_run_mysqli_multi_query_safe($newConn, $rawSql);
            mysqli_close($newConn);
            $newConn = null;
            if (!$run['ok']) {
                error_log('AuraGold branch_create_db: structure.sql import failed: ' . $run['message']);
                return [
                    'ok'      => false,
                    'message' => 'Database created but schema import failed: ' . $run['message'],
                ];
            }

            if (!function_exists('auragold_branch_reset_tbl_branches')) {
                require_once __DIR__ . '/branch_database_provision.php';
            }
            if ($sourceDb !== '' && auragold_branch_mysql_identifier_ok($sourceDb)) {
                $tquoted = '`' . str_replace('`', '``', $db_name) . '`';
                $squoted = '`' . str_replace('`', '``', $sourceDb) . '`';
                $rb      = auragold_branch_reset_tbl_branches($conn_master, $db_name, $sourceDb, $newBranchId, $tquoted, $squoted);
                if (empty($rb['ok'])) {
                    error_log('AuraGold branch_create_db: tbl_branches sync after SQL import: ' . ($rb['message'] ?? ''));
                    return [
                        'ok'      => false,
                        'message' => 'Database created but could not sync tbl_branches: ' . ($rb['message'] ?? ''),
                    ];
                }
            }

            if ($sourceDb === '' || !auragold_branch_mysql_identifier_ok($sourceDb)) {
                return [
                    'ok'      => false,
                    'message' => 'Database created and structure.sql ran, but no template database (AURAGOLD_REGISTRY_DB) was configured to copy missing tables from.',
                ];
            }
            if (!function_exists('auragold_branch_backfill_from_registry')) {
                require_once __DIR__ . '/branch_database_provision.php';
            }
            $bfOpts = [
                'seed_masters'      => true,
                'branch_mysql_user' => $db_user,
                'branch_mysql_pass' => $db_pass,
            ];
            if (!empty($provisionOpts['omit_master_table_names']) && is_array($provisionOpts['omit_master_table_names'])) {
                $bfOpts['omit_master_table_names'] = $provisionOpts['omit_master_table_names'];
            }
            $bf = auragold_branch_backfill_from_registry($db_name, $sourceDb, $newBranchId, $bfOpts);
            if (empty($bf['ok'])) {
                error_log('AuraGold branch_create_db: backfill after structure.sql: ' . ($bf['message'] ?? ''));
                return [
                    'ok'      => false,
                    'message' => 'Database created but could not add missing tables from template `' . $sourceDb . '`: ' . ($bf['message'] ?? ''),
                ];
            }

            return [
                'ok'          => true,
                'message'     => 'Database created: structure.sql applied, all tables from `' . $sourceDb . '` ensured. ' . (string) ($bf['message'] ?? ''),
                'provisioned' => true,
            ];
        }

        mysqli_close($newConn);

        if (!function_exists('auragold_provision_branch_database')) {
            require_once __DIR__ . '/branch_database_provision.php';
        }
        if ($sourceDb === '' || !auragold_branch_mysql_identifier_ok($sourceDb)) {
            error_log('AuraGold branch_create_db: no schema source DB; cannot clone.');
            return [
                'ok'      => false,
                'message' => 'Database created but no template database was resolved (AURAGOLD_REGISTRY_DB / DB_NAME) for a full table clone.',
            ];
        }

        $prov = auragold_provision_branch_database($db_name, $sourceDb, $newBranchId, $seedOpts);
        if (empty($prov['ok'])) {
            error_log('AuraGold branch_create_db: provision clone failed: ' . ($prov['message'] ?? ''));
            return [
                'ok'      => false,
                'message' => 'Database created but schema clone failed: ' . ($prov['message'] ?? 'unknown'),
            ];
        }

        return [
            'ok'          => true,
            'message'     => $prov['message'] ?? 'Database provisioned from registry.',
            'provisioned' => true,
        ];
    }
}
