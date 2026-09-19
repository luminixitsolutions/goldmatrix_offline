<?php
/**
 * Production: cPanel UAPI — Mysql::create_database, Mysql::create_user, Mysql::set_privileges_on_database.
 *
 * Branch provisioning: create_database, create_user, set_privileges_on_database for the new branch user on the new DB only
 * (registry/DB_USER is not granted on the new branch DB). See auragold_cpanel_uapi_mysql_set_privileges_for_app_user for
 * ad-hoc/tools only. Local CREATE DATABASE is unchanged; AURAGOLD_PROJECT === 'prod' uses cPanel UAPI.
 *
 * Success: metadata.result === 1, status === 1, etc. (auragold_cpanel_uapi_is_success).
 *
 * Requires config.php: $cpanelUser, $apiToken, $domain in prod.
 * After UAPI success, branch access is confirmed with the new branch user via mysqli in branch_create_db_after_save
 * (not via registry $conn_master information_schema alone).
 */
if (!function_exists('auragold_cpanel_auragold_db_prefix_string')) {
    /**
     * Same prefix as admin/config.php: $db_prefix / constant AURAGOLD_DB_PREFIX (cPanel must use this account MySQL prefix).
     *
     * @return string Trailing-underscore prefix, e.g. goldmatrix_ or auragold_
     */
    function auragold_cpanel_auragold_db_prefix_string(): string {
        if (!defined('AURAGOLD_DB_PREFIX')) {
            return 'auragold_';
        }
        $p = trim((string) AURAGOLD_DB_PREFIX);
        if ($p === '') {
            return 'auragold_';
        }
        if (substr($p, -1) !== '_') {
            $p .= '_';
        }
        return $p;
    }
}

if (!function_exists('auragoldEnsureCpanelPrefix')) {
    /**
     * @param string|null $prefix cPanel / branch prefix; if null, uses AURAGOLD_DB_PREFIX (config).
     */
    function auragoldEnsureCpanelPrefix($name, $prefix = null) {
        if ($prefix === null) {
            $prefix = auragold_cpanel_auragold_db_prefix_string();
        }
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        if (strpos($name, $prefix) === 0) {
            return $name;
        }
        return $prefix . $name;
    }
}

if (!function_exists('auragoldCpanelShortName')) {
    function auragoldCpanelShortName($name, $prefix = null) {
        if ($prefix === null) {
            $prefix = auragold_cpanel_auragold_db_prefix_string();
        }
        $name = trim((string) $name);
        if (strpos($name, $prefix) === 0) {
            return substr($name, strlen($prefix));
        }
        return $name;
    }
}

if (!function_exists('auragold_cpanel_uapi_extract_database_name_from_create_response')) {
    /**
     * @param array<string,mixed> $decoded
     */
    function auragold_cpanel_uapi_extract_database_name_from_create_response(array $decoded): string {
        $candidates = [
            $decoded['data'] ?? null,
        ];
        $r = $decoded['result'] ?? null;
        if (is_array($r)) {
            $candidates[] = $r['data'] ?? null;
            if (isset($r['data']) && is_array($r['data'])) {
                $candidates[] = $r['data']['database'] ?? $r['data']['name'] ?? $r['data']['db'] ?? null;
            }
        }
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '' && preg_match('/^[a-zA-Z0-9_]+$/', trim($c))) {
                return trim($c);
            }
            if (is_array($c)) {
                foreach (['database', 'name', 'db', 'value'] as $k) {
                    if (!empty($c[$k]) && is_string($c[$k]) && trim((string) $c[$k]) !== '') {
                        $v = trim((string) $c[$k]);
                        if (preg_match('/^[a-zA-Z0-9_]+$/', $v)) {
                            return $v;
                        }
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('auragold_cpanel_uapi_is_success')) {
    /**
     * cPanel UAPI success: metadata.result === 1 OR status === 1 (root or nested result).
     */
    function auragold_cpanel_uapi_is_success(array $decoded): bool {
        $meta = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
        if ((int) ($meta['result'] ?? 0) === 1) {
            return true;
        }
        if ((int) ($decoded['status'] ?? 0) === 1) {
            return true;
        }
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        if ((int) ($data['status'] ?? 0) === 1) {
            return true;
        }
        $res = isset($decoded['result']) ? $decoded['result'] : null;
        if (is_array($res)) {
            if ((int) ($res['status'] ?? 0) === 1) {
                return true;
            }
            $rmeta = isset($res['metadata']) && is_array($res['metadata']) ? $res['metadata'] : [];
            if ((int) ($rmeta['result'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('auragold_cpanel_uapi_failure_message')) {
    /**
     * Build error text: metadata.reason, result.metadata.reason, errors, warnings, messages.
     */
    function auragold_cpanel_uapi_failure_message(string $prefix, array $decoded, string $rawBody): string {
        $segments = [];
        $p = trim($prefix);
        if ($p !== '') {
            $segments[] = $p;
        }
        $meta = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
        if (!empty($meta['reason'])) {
            $segments[] = 'metadata.reason: ' . trim((string) $meta['reason']);
        }
        $appendBlock = static function (string $label, $val) use (&$segments): void {
            if ($val === null || $val === '' || $val === []) {
                return;
            }
            $segments[] = $label . ': ' . (is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE));
        };
        foreach (['errors', 'warnings', 'messages'] as $key) {
            if (!empty($decoded[$key])) {
                $appendBlock($key, $decoded[$key]);
            }
        }
        $res = isset($decoded['result']) && is_array($decoded['result']) ? $decoded['result'] : null;
        if ($res !== null) {
            $rmeta = isset($res['metadata']) && is_array($res['metadata']) ? $res['metadata'] : [];
            if (!empty($rmeta['reason'])) {
                $segments[] = 'result.metadata.reason: ' . trim((string) $rmeta['reason']);
            }
            foreach (['errors', 'warnings', 'messages'] as $key) {
                if (!empty($res[$key])) {
                    $appendBlock('result.' . $key, $res[$key]);
                }
            }
        }
        $out = trim(implode(' ', array_filter($segments, static function ($s) {
            return $s !== '';
        })));
        if ($out === '') {
            $out = $p !== '' ? $p : 'cPanel UAPI returned failure.';
        }
        if ($rawBody !== '') {
            $out .= ' Raw JSON: ' . substr($rawBody, 0, 4000);
        }
        return $out;
    }
}

if (!function_exists('auragold_cpanel_uapi_privileges_failure_user_message')) {
    function auragold_cpanel_uapi_privileges_failure_user_message(string $detail): string {
        $detail = trim($detail);
        if ($detail === '') {
            $detail = 'Unknown error';
        }
        return 'Database created but failed to assign MySQL user privileges: ' . $detail;
    }
}

if (!function_exists('auragold_cpanel_uapi_full_privilege_list_string')) {
    /**
     * Exact comma-separated list cPanel UAPI accepts when "ALL PRIVILEGES" literal is rejected.
     */
    function auragold_cpanel_uapi_full_privilege_list_string(): string {
        return implode(',', [
            'ALTER', 'ALTER ROUTINE', 'CREATE', 'CREATE ROUTINE', 'CREATE TEMPORARY TABLES',
            'CREATE VIEW', 'DELETE', 'DROP', 'EVENT', 'EXECUTE', 'INDEX', 'INSERT',
            'LOCK TABLES', 'REFERENCES', 'SELECT', 'SHOW VIEW', 'TRIGGER', 'UPDATE',
        ]);
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_set_privileges_request')) {
    /**
     * GET /execute/Mysql/set_privileges_on_database?user=&database=&privileges=
     *
     * @return array{response:string,http_code:int,curl_error:string}
     */
    function auragold_cpanel_uapi_mysql_set_privileges_request(
        string $dom,
        string $cpUser,
        string $apiToken,
        string $mysqlUser,
        string $dbName,
        string $privilegesParam
    ): array {
        $url = 'https://' . $dom . ':2083/execute/Mysql/set_privileges_on_database'
            . '?user=' . rawurlencode($mysqlUser)
            . '&database=' . rawurlencode($dbName)
            . '&privileges=' . rawurlencode($privilegesParam);

        $ch = curl_init($url);
        if ($ch === false) {
            return ['response' => '', 'http_code' => 0, 'curl_error' => 'curl_init failed'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: cpanel ' . $cpUser . ':' . $apiToken,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        if (defined('CURLOPT_SSL_VERIFYPEER')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        }

        $response = curl_exec($ch);
        $cerr     = curl_error($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'response'   => is_string($response) ? $response : '',
            'http_code'  => $code,
            'curl_error' => $cerr,
        ];
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_create_database')) {
    /**
     * @return array{ok:bool,message:string,skipped?:bool,db_name_requested?:string,db_name_effective?:string,uapi_name_param?:string}
     */
    function auragold_cpanel_uapi_mysql_create_database(string $dbName): array {
        $nameRawRequested = trim($dbName);
        if ($nameRawRequested === '') {
            return ['ok' => false, 'message' => 'Empty database name.'];
        }
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL extension is required for cPanel database creation on production.'];
        }

        $user = isset($GLOBALS['cpanelUser']) ? trim((string) $GLOBALS['cpanelUser']) : '';
        $tok  = isset($GLOBALS['apiToken']) ? trim((string) $GLOBALS['apiToken']) : '';
        $dom  = isset($GLOBALS['domain']) ? trim((string) $GLOBALS['domain']) : '';

        if ($user === '' || $tok === '' || $dom === '') {
            return [
                'ok'      => false,
                'message' => 'Production cPanel API is not configured (set $cpanelUser, $apiToken, $domain in config.php for prod).',
            ];
        }
        $apPrefix  = auragold_cpanel_auragold_db_prefix_string();
        $forCpanel = auragoldEnsureCpanelPrefix($nameRawRequested, $apPrefix);
        if ($forCpanel === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $forCpanel)) {
            return [
                'ok'                 => false,
                'message'            => 'Invalid database name after cPanel prefix normalization.',
                'db_name_requested'  => $nameRawRequested,
                'uapi_name_param'    => $forCpanel,
                'db_name_effective'  => $forCpanel,
            ];
        }
        $uapi   = $forCpanel;
        $expect = $forCpanel;
        $dbName = $nameRawRequested;

        error_log(
            'Auragold cPanel [prod] create_database: name_raw=' . $nameRawRequested
            . ' name_normalized=' . $forCpanel
            . ' uapi_name_param=' . $uapi
            . ' auragold_db_prefix=' . $apPrefix
        );

        $nameParam = rawurlencode($uapi);
        $url       = 'https://' . $dom . ':2083/execute/Mysql/create_database?name=' . $nameParam;

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok'                => false,
                'message'           => 'Could not initialize cURL for cPanel API.',
                'db_name_requested' => $dbName,
                'uapi_name_param'   => $uapi,
                'db_name_effective' => $expect,
            ];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: cpanel ' . $user . ':' . $tok,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        if (defined('CURLOPT_SSL_VERIFYPEER')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        }

        $response = curl_exec($ch);
        $cerr     = curl_error($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $raw = is_string($response) ? $response : '';
        if ($raw === '' && ($cerr !== '' || $code > 0)) {
            $raw = '(empty body; HTTP ' . $code . '; curl_error=' . $cerr . ')';
        }
        error_log('cPanel API response: ' . $raw);

        if ($response === false || $response === '') {
            return [
                'ok'                 => false,
                'message'            => 'cPanel create_database request failed' . ($cerr !== '' ? ': ' . $cerr : '') . ' (HTTP ' . $code . ').',
                'db_name_requested'  => $dbName,
                'uapi_name_param'    => $uapi,
                'db_name_effective'  => $expect,
            ];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return [
                'ok'                 => false,
                'message'            => 'cPanel create_database returned non-JSON (HTTP ' . $code . '): ' . substr((string) $response, 0, 2000),
                'db_name_requested'  => $dbName,
                'uapi_name_param'    => $uapi,
                'db_name_effective'  => $expect,
            ];
        }
        if (auragold_cpanel_uapi_is_success($decoded)) {
            $fromJson = auragold_cpanel_uapi_extract_database_name_from_create_response($decoded);
            $use      = $fromJson !== '' ? $fromJson : $expect;
            if ($fromJson !== '' && strcasecmp($fromJson, $expect) !== 0) {
                error_log(
                    'Auragold cPanel [prod] create_database: cPanel response database name ('
                    . $fromJson . ') differs from expected (' . $expect . '). Using cPanel name.'
                );
            }
            error_log(
                'Auragold cPanel [prod] create_database: result=success db_name_effective='
                . $use
            );
            return [
                'ok'                 => true,
                'message'            => '',
                'db_name_requested'  => $dbName,
                'uapi_name_param'    => $uapi,
                'db_name_effective'  => $use,
            ];
        }

        $reason = '';
        $meta   = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
        if (!empty($meta['reason'])) {
            $reason = trim((string) $meta['reason']);
        }
        $lr = strtolower($reason . ' ' . (string) json_encode($decoded));
        if (strpos($lr, 'exists') !== false || strpos($lr, 'already') !== false) {
            error_log(
                'Auragold cPanel [prod] create_database: UAPI said already exists; using effective name=' . $expect
            );
            return [
                'ok'                 => true,
                'message'            => 'Database already present on server.',
                'db_name_requested'  => $dbName,
                'uapi_name_param'    => $uapi,
                'db_name_effective'  => $expect,
            ];
        }

        return [
            'ok'                 => false,
            'message'            => auragold_cpanel_uapi_failure_message('cPanel create_database failed.', $decoded, (string) $response),
            'db_name_requested'  => $dbName,
            'uapi_name_param'    => $uapi,
            'db_name_effective'  => $expect,
        ];
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_authorized_curl_get')) {
    /**
     * @return array{response:string,http_code:int,curl_error:string}
     */
    function auragold_cpanel_uapi_mysql_authorized_curl_get(string $url, string $cpUser, string $apiToken): array {
        if (!function_exists('curl_init')) {
            return ['response' => '', 'http_code' => 0, 'curl_error' => 'curl not available'];
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return ['response' => '', 'http_code' => 0, 'curl_error' => 'curl_init failed'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: cpanel ' . $cpUser . ':' . $apiToken,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        if (defined('CURLOPT_SSL_VERIFYPEER')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        }
        $response = curl_exec($ch);
        $cerr     = curl_error($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [
            'response'   => is_string($response) ? $response : '',
            'http_code'  => $code,
            'curl_error' => $cerr,
        ];
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_create_user')) {
    /**
     * cPanel Mysql::create_user — same as a working UAPI call: full MySQL name in `name=`, e.g. goldmatrix_myappuser
     * (not the short suffix alone). Must match the prefix in config ($db_prefix / AURAGOLD_DB_PREFIX). Max 32 chars.
     * Password is never written to error_log.
     *
     * @return array{ok:bool,message:string,skipped?:bool,user_name_uapi_param?:string,user_full_name?:string}
     */
    function auragold_cpanel_uapi_mysql_create_user(string $branchMysqlUserFull, string $password): array {
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL is required for cPanel create_user.'];
        }
        $uRaw = trim($branchMysqlUserFull);
        if ($uRaw === '') {
            return ['ok' => false, 'message' => 'Empty MySQL username for cPanel create_user.'];
        }
        if ($password === '') {
            return ['ok' => false, 'message' => 'Empty MySQL password for cPanel create_user.'];
        }
        $user = isset($GLOBALS['cpanelUser']) ? trim((string) $GLOBALS['cpanelUser']) : '';
        $tok  = isset($GLOBALS['apiToken']) ? trim((string) $GLOBALS['apiToken']) : '';
        $dom  = isset($GLOBALS['domain']) ? trim((string) $GLOBALS['domain']) : '';
        if ($user === '' || $tok === '' || $dom === '') {
            return ['ok' => false, 'message' => 'Production cPanel API is not configured.'];
        }
        $apPrefix = auragold_cpanel_auragold_db_prefix_string();
        $userFull = auragoldEnsureCpanelPrefix($uRaw, $apPrefix);
        if (strlen($userFull) > 32) {
            $userFull = substr($userFull, 0, 32);
        }
        if ($userFull === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $userFull)) {
            return [
                'ok'     => false,
                'message' => 'Invalid MySQL username for cPanel create_user (use letters, numbers, underscore; max 32).',
            ];
        }
        $nameUapi = $userFull;
        $url      = 'https://' . $dom . ':2083/execute/Mysql/create_user'
            . '?name=' . rawurlencode($nameUapi)
            . '&password=' . rawurlencode($password);

        error_log('Auragold [prod] cPanel create_user: raw_user=' . $uRaw . ' full_name_sent_in_uapi=' . $nameUapi . ' (password not logged)');

        $exec = auragold_cpanel_uapi_mysql_authorized_curl_get($url, $user, $tok);
        $raw  = $exec['response'];
        if ($raw === '' && $exec['curl_error'] !== '') {
            $raw = '(empty; HTTP ' . $exec['http_code'] . '; ' . $exec['curl_error'] . ')';
        }
        error_log('Auragold [prod] cPanel create_user UAPI response: ' . $raw);

        if ($exec['response'] === '' && $exec['http_code'] === 0) {
            return [
                'ok'     => false,
                'message' => 'cPanel create_user request failed' . ($exec['curl_error'] !== '' ? ': ' . $exec['curl_error'] : ''),
            ];
        }
        $decoded = json_decode((string) $exec['response'], true);
        if (!is_array($decoded)) {
            return [
                'ok'     => false,
                'message' => 'cPanel create_user returned non-JSON. Raw: ' . substr((string) $exec['response'], 0, 2000),
            ];
        }
        if (auragold_cpanel_uapi_is_success($decoded)) {
            return [
                'ok'                 => true,
                'message'            => '',
                'user_name_uapi_param' => $nameUapi,
                'user_full_name'     => $userFull,
            ];
        }
        $lr = strtolower((string) json_encode($decoded));
        if (strpos($lr, 'exists') !== false
            || strpos($lr, 'already') !== false
            || strpos($lr, 'duplicate') !== false) {
            error_log('Auragold [prod] cPanel create_user: UAPI said user already exists; continuing. ' . $lr);
            return [
                'ok'                 => true,
                'message'            => 'MySQL user may already exist on the server.',
                'user_name_uapi_param' => $nameUapi,
                'user_full_name'     => $userFull,
            ];
        }
        $detail = auragold_cpanel_uapi_failure_message('cPanel create_user failed.', $decoded, (string) $exec['response']);
        return [
            'ok'     => false,
            'message' => $detail,
        ];
    }
}

if (!function_exists('auragold_cpanel_uapi_run_set_privileges_on_database_loop')) {
    /**
     * @return array{ok:bool,message:string,privileges_mode?:string}
     */
    function auragold_cpanel_uapi_run_set_privileges_on_database_loop(
        string $dom,
        string $cpUser,
        string $apiToken,
        string $dbName,
        string $mysqlUser,
        string $logContext
    ): array {
        $listFull = auragold_cpanel_uapi_full_privilege_list_string();
        $attempts = [
            ['label' => 'FULL_COMMA_LIST', 'privileges' => $listFull],
            ['label' => 'ALL_PRIVILEGES_LITERAL', 'privileges' => 'ALL PRIVILEGES'],
        ];

        $urlWithoutToken = 'https://' . $dom . ':2083/execute/Mysql/set_privileges_on_database'
            . '?user=' . rawurlencode($mysqlUser)
            . '&database=' . rawurlencode($dbName)
            . '&privileges=';

        error_log(
            'Auragold [prod] cPanel set_privileges [' . $logContext . ']: UAPI base (no token): ' . $urlWithoutToken
        );

        $lastDecoded = null;
        $lastRaw     = '';
        $nAtt        = count($attempts);
        foreach ($attempts as $idx => $attempt) {
            $privilegesSent = $attempt['privileges'];
            $exec = auragold_cpanel_uapi_mysql_set_privileges_request($dom, $cpUser, $apiToken, $mysqlUser, $dbName, $privilegesSent);
            $response         = $exec['response'];
            if ($response === '' && $exec['curl_error'] !== '') {
                $response = '(empty body; HTTP ' . $exec['http_code'] . '; curl_error=' . $exec['curl_error'] . ')';
            }
            error_log('Auragold [prod] cPanel set_privileges [' . $logContext . ',' . $attempt['label'] . '] response: ' . $response);
            $lastRaw = $response;
            if ($response === '' || (string) $response === '') {
                return [
                    'ok'     => false,
                    'message' => auragold_cpanel_uapi_privileges_failure_user_message(
                        'HTTP/cURL failed: ' . ($exec['curl_error'] !== '' ? $exec['curl_error'] : 'empty response')
                    ),
                ];
            }
            $decoded = json_decode((string) $response, true);
            if (!is_array($decoded)) {
                if ($idx < $nAtt - 1) {
                    error_log('cPanel set_privileges: ' . $attempt['label'] . ' non-JSON; retrying with next privilege mode.');
                    continue;
                }
                return [
                    'ok'     => false,
                    'message' => auragold_cpanel_uapi_privileges_failure_user_message(
                        'Non-JSON: ' . substr((string) $response, 0, 2000)
                    ),
                ];
            }
            $lastDecoded = $decoded;
            if (auragold_cpanel_uapi_is_success($decoded)) {
                return [
                    'ok'              => true,
                    'message'         => '',
                    'privileges_mode' => $attempt['label'],
                ];
            }
            $lr = strtolower((string) json_encode($decoded));
            if ($idx < $nAtt - 1) {
                error_log(
                    'cPanel set_privileges: ' . $attempt['label'] . ' did not succeed; retrying. '
                    . (strpos($lr, 'privilege') !== false || strpos($lr, 'invalid') !== false
                        ? 'UAPI: privilege/invalid hint. ' : '')
                );
                continue;
            }
            $detail = trim(auragold_cpanel_uapi_failure_message('', $decoded, (string) $response));
            if ($detail === '') {
                $detail = 'UAPI set_privileges_on_database did not report success.';
            }
            return [
                'ok'     => false,
                'message' => auragold_cpanel_uapi_privileges_failure_user_message($detail),
            ];
        }
        $detail = $lastDecoded !== null
            ? auragold_cpanel_uapi_failure_message('', $lastDecoded, $lastRaw)
            : trim($lastRaw);
        return [
            'ok'     => false,
            'message' => auragold_cpanel_uapi_privileges_failure_user_message($detail !== '' ? $detail : 'All privilege modes failed.'),
        ];
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_set_privileges_on_database_for_user')) {
    /**
     * Mysql::set_privileges_on_database for an arbitrary MySQL user and database (full cPanel-prefixed names in UAPI).
     *
     * @return array{ok:bool,message:string,skipped?:bool,privileges_mode?:string}
     */
    function auragold_cpanel_uapi_mysql_set_privileges_on_database_for_user(
        string $dbName,
        string $mysqlUserToGrant,
        string $logContext
    ): array {
        $dbName = trim($dbName);
        if ($dbName === '') {
            return ['ok' => false, 'message' => 'Empty database for privilege grant.'];
        }
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (trim($mysqlUserToGrant) === '') {
            return ['ok' => false, 'message' => 'Empty MySQL user for privilege grant.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL is required.'];
        }
        $cpUser = isset($GLOBALS['cpanelUser']) ? trim((string) $GLOBALS['cpanelUser']) : '';
        $tok    = isset($GLOBALS['apiToken']) ? trim((string) $GLOBALS['apiToken']) : '';
        $dom    = isset($GLOBALS['domain']) ? trim((string) $GLOBALS['domain']) : '';
        if ($cpUser === '' || $tok === '' || $dom === '') {
            return ['ok' => false, 'message' => 'Production cPanel API is not configured.'];
        }
        $apPrefix        = auragold_cpanel_auragold_db_prefix_string();
        $dbNameRaw       = $dbName;
        $userRaw         = trim($mysqlUserToGrant);
        $dbNameForCpanel = auragoldEnsureCpanelPrefix($dbName, $apPrefix);
        if ($dbNameForCpanel === '') {
            return ['ok' => false, 'message' => 'Empty database name for privilege after normalization.'];
        }
        $mysqlForCpanel = auragoldEnsureCpanelPrefix($userRaw, $apPrefix);
        if (strlen($mysqlForCpanel) > 32) {
            $mysqlForCpanel = substr($mysqlForCpanel, 0, 32);
        }
        error_log(
            'Auragold [prod] set_priv context=' . $logContext
            . ' db_raw=' . $dbNameRaw . ' db_final=' . $dbNameForCpanel
            . ' user_raw=' . $userRaw . ' user_final=' . $mysqlForCpanel
        );
        return auragold_cpanel_uapi_run_set_privileges_on_database_loop(
            $dom,
            $cpUser,
            $tok,
            $dbNameForCpanel,
            $mysqlForCpanel,
            $logContext
        );
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_set_privileges_for_app_user')) {
    /**
     * Tooling only: grant DB_USER on a database. Branch provisioning does not call this (branch DB = branch user only).
     *
     * @return array{ok:bool,message:string,skipped?:bool,privileges_mode?:string}
     */
    function auragold_cpanel_uapi_mysql_set_privileges_for_app_user(string $dbName): array {
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (!defined('DB_USER') || trim((string) DB_USER) === '') {
            return ['ok' => false, 'message' => 'DB_USER is not defined in config.'];
        }
        return auragold_cpanel_uapi_mysql_set_privileges_on_database_for_user($dbName, (string) DB_USER, 'app_DB_USER');
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_delete_database')) {
    /**
     * cPanel Mysql::delete_database — removes a branch schema (cPanel account scope).
     *
     * @return array{ok:bool,message:string,skipped?:bool,method?:string}
     */
    function auragold_cpanel_uapi_mysql_delete_database(string $dbName): array {
        $nameRawRequested = trim($dbName);
        if ($nameRawRequested === '') {
            return ['ok' => false, 'message' => 'Empty database name.'];
        }
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL is required for cPanel database deletion on production.'];
        }
        $user = isset($GLOBALS['cpanelUser']) ? trim((string) $GLOBALS['cpanelUser']) : '';
        $tok  = isset($GLOBALS['apiToken']) ? trim((string) $GLOBALS['apiToken']) : '';
        $dom  = isset($GLOBALS['domain']) ? trim((string) $GLOBALS['domain']) : '';
        if ($user === '' || $tok === '' || $dom === '') {
            return [
                'ok'      => false,
                'message' => 'Production cPanel API is not configured (set $cpanelUser, $apiToken, $domain in config.php for prod).',
            ];
        }
        if (!function_exists('auragold_cpanel_auragold_db_prefix_string')) {
            return ['ok' => false, 'message' => 'cPanel prefix helper is not available.'];
        }
        $apPrefix  = auragold_cpanel_auragold_db_prefix_string();
        $forCpanel = auragoldEnsureCpanelPrefix($nameRawRequested, $apPrefix);
        if ($forCpanel === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $forCpanel)) {
            return ['ok' => false, 'message' => 'Invalid database name after cPanel prefix normalization.'];
        }
        $url  = 'https://' . $dom . ':2083/execute/Mysql/delete_database?name=' . rawurlencode($forCpanel);
        $exec = auragold_cpanel_uapi_mysql_authorized_curl_get($url, $user, $tok);
        $raw  = (string) ($exec['response'] ?? '');
        $code = (int) ($exec['http_code'] ?? 0);
        $cerr = trim((string) ($exec['curl_error'] ?? ''));
        error_log('Auragold cPanel [prod] delete_database: name=' . $forCpanel . ' HTTP=' . $code . ' body=' . substr($raw, 0, 1500));

        if ($raw === '' && ($cerr !== '' || $code > 0)) {
            return [
                'ok'      => false,
                'message' => 'cPanel delete_database request failed' . ($cerr !== '' ? ': ' . $cerr : '') . ' (HTTP ' . $code . ').',
                'method'  => 'cpanel_uapi',
            ];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'ok'      => false,
                'message' => 'cPanel delete_database returned non-JSON (HTTP ' . $code . '): ' . substr($raw, 0, 500),
                'method'  => 'cpanel_uapi',
            ];
        }
        if (auragold_cpanel_uapi_is_success($decoded)) {
            return [
                'ok'      => true,
                'message' => 'Database `' . $forCpanel . '` deleted via cPanel.',
                'method'  => 'cpanel_uapi',
            ];
        }
        $detail = auragold_cpanel_uapi_failure_message('cPanel delete_database failed.', $decoded, $raw);
        if (preg_match('/does not exist|not found|cannot find|unknown database/i', $detail)) {
            return [
                'ok'      => true,
                'message' => 'Database `' . $forCpanel . '` was already removed.',
                'method'  => 'cpanel_uapi',
            ];
        }
        return ['ok' => false, 'message' => $detail, 'method' => 'cpanel_uapi'];
    }
}

if (!function_exists('auragold_cpanel_uapi_mysql_delete_user')) {
    /**
     * cPanel Mysql::delete_user — optional cleanup after branch DB drop.
     *
     * @return array{ok:bool,message:string,skipped?:bool}
     */
    function auragold_cpanel_uapi_mysql_delete_user(string $mysqlUser): array {
        $uRaw = trim($mysqlUser);
        if ($uRaw === '') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (!defined('AURAGOLD_PROJECT') || AURAGOLD_PROJECT !== 'prod') {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL is required for cPanel user deletion on production.'];
        }
        $user = isset($GLOBALS['cpanelUser']) ? trim((string) $GLOBALS['cpanelUser']) : '';
        $tok  = isset($GLOBALS['apiToken']) ? trim((string) $GLOBALS['apiToken']) : '';
        $dom  = isset($GLOBALS['domain']) ? trim((string) $GLOBALS['domain']) : '';
        if ($user === '' || $tok === '' || $dom === '') {
            return ['ok' => true, 'skipped' => true, 'message' => 'cPanel API not configured; skipped MySQL user cleanup.'];
        }
        $apPrefix  = auragold_cpanel_auragold_db_prefix_string();
        $userFull  = auragoldEnsureCpanelPrefix($uRaw, $apPrefix);
        if ($userFull === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $userFull)) {
            return ['ok' => false, 'message' => 'Invalid MySQL username for cPanel delete_user.'];
        }
        $url  = 'https://' . $dom . ':2083/execute/Mysql/delete_user?name=' . rawurlencode($userFull);
        $exec = auragold_cpanel_uapi_mysql_authorized_curl_get($url, $user, $tok);
        $raw  = (string) ($exec['response'] ?? '');
        error_log('Auragold cPanel [prod] delete_user: name=' . $userFull . ' body=' . substr($raw, 0, 800));
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && auragold_cpanel_uapi_is_success($decoded)) {
            return ['ok' => true, 'message' => 'MySQL user `' . $userFull . '` deleted via cPanel.'];
        }
        $detail = is_array($decoded)
            ? auragold_cpanel_uapi_failure_message('cPanel delete_user failed.', $decoded, $raw)
            : 'cPanel delete_user returned non-JSON.';
        if (preg_match('/does not exist|not found|cannot find/i', $detail)) {
            return ['ok' => true, 'message' => 'MySQL user `' . $userFull . '` was already removed.'];
        }
        return ['ok' => false, 'message' => $detail];
    }
}
