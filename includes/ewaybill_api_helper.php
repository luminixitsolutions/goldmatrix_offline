<?php

/**
 * WhiteBooks e-Way Bill API helper: config merge, HTTP client, logging, token persistence.
 */
if (!function_exists('ewaybill_config_file_path')) {
    function ewaybill_config_file_path(): string
    {
        return __DIR__ . '/../config/ewaybill_config.php';
    }
}

if (!function_exists('ewaybill_fix_typo_whitebooks_host')) {
    /** Fixes known hostname typo that breaks DNS resolution. */
    function ewaybill_fix_typo_whitebooks_host(string $s): string
    {
        return str_replace('apisandbbox', 'apisandbox', $s);
    }
}

if (!function_exists('ewaybill_normalize_whitebooks_api_base')) {
    /**
     * NIC v1.03 API root ends with /ewayapi; legacy installs may store host only.
     */
    function ewaybill_normalize_whitebooks_api_base(string $base): string
    {
        $b = rtrim(ewaybill_fix_typo_whitebooks_host(trim($base)), '/');
        if ($b === '') {
            return '';
        }
        if (preg_match('#/ewayapi$#i', $b)) {
            return $b;
        }
        if (preg_match('#^https?://[^/\s]+$#i', $b)) {
            return $b . '/ewaybillapi/v1.03/ewayapi';
        }

        return $b;
    }
}

if (!function_exists('ewaybill_try_whitebooks_genewaybill_post_url')) {
    /**
     * WhiteBooks Generate E-Way Bill POST URL: {api_root}/genewaybill?email=...
     *
     * @param array<string, string> $config merged settings (base_url, email)
     *
     * @return array{ok: bool, url?: string, message?: string}
     */
    function ewaybill_try_whitebooks_genewaybill_post_url(array $config): array
    {
        $raw = trim((string) ($config['base_url'] ?? ''));
        if ($raw !== '' && strpos($raw, 'apisandbbox') !== false) {
            return ['ok' => false, 'message' => 'Wrong WhiteBooks host: apisandbbox. Use apisandbox.'];
        }
        $raw = ewaybill_fix_typo_whitebooks_host($raw);
        if ($raw === '' && defined('EWAY_BASE_URL')) {
            $raw = ewaybill_fix_typo_whitebooks_host((string) EWAY_BASE_URL);
        }
        if ($raw === '') {
            $raw = 'https://apisandbox.whitebooks.in/ewaybillapi/v1.03/ewayapi';
        }
        $baseUrl = ewaybill_normalize_whitebooks_api_base($raw);
        $email   = trim((string) ($config['email'] ?? ''));
        if ($email === '') {
            return ['ok' => false, 'message' => 'E-Way email is missing in configuration.'];
        }
        $url = $baseUrl . '/genewaybill?email=' . rawurlencode($email);
        if (strpos($url, 'apisandbbox') !== false) {
            return ['ok' => false, 'message' => 'Wrong WhiteBooks host: apisandbbox. Use apisandbox.'];
        }

        return ['ok' => true, 'url' => $url];
    }
}

if (!function_exists('ewaybill_whitebooks_get_endpoint_base')) {
    /**
     * API root (.../ewayapi) for GET helpers (same normalization as generate).
     *
     * @param array<string, string> $mergedConfig
     */
    function ewaybill_whitebooks_get_endpoint_base(array $mergedConfig): string
    {
        $raw = ewaybill_fix_typo_whitebooks_host(trim((string) ($mergedConfig['base_url'] ?? '')));
        if ($raw === '' && defined('EWAY_BASE_URL')) {
            $raw = ewaybill_fix_typo_whitebooks_host((string) EWAY_BASE_URL);
        }
        if ($raw === '') {
            $raw = 'https://apisandbox.whitebooks.in/ewaybillapi/v1.03/ewayapi';
        }

        return ewaybill_normalize_whitebooks_api_base($raw);
    }
}

if (!function_exists('ewaybill_whitebooks_authenticate_url')) {
    /**
     * WhiteBooks NIC GET authenticate: .../ewaybillapi/v1.03/authenticate?email=...
     * Generate E-Way uses root .../ewayapi; authenticate is one segment above (must not append
     * /ewaybillapi/v1.03/authenticate to an already-normalized .../ewayapi URL — that yields a bad path
     * and often an HTML body with HTTP 200, which json_decode cannot parse).
     *
     * @param array<string, string> $mergedConfig
     */
    function ewaybill_whitebooks_authenticate_url(array $mergedConfig, string $queryString): string
    {
        $apiRoot = rtrim(ewaybill_whitebooks_get_endpoint_base($mergedConfig), '/');
        if (preg_match('#/ewayapi$#i', $apiRoot)) {
            $prefix = preg_replace('#/ewayapi$#i', '', $apiRoot);
        } else {
            $prefix = $apiRoot;
        }

        $url = rtrim($prefix, '/') . '/authenticate';

        return $queryString !== '' ? $url . '?' . $queryString : $url;
    }
}

if (!function_exists('ewaybill_load_file_config')) {
    /**
     * @return array<string, string>
     */
    function ewaybill_load_file_config(): array
    {
        $f = ewaybill_config_file_path();
        if (is_file($f)) {
            require_once $f;
        }
        return [
            'base_url'     => ewaybill_fix_typo_whitebooks_host(defined('EWAY_BASE_URL') ? (string) EWAY_BASE_URL : ''),
            'email'        => defined('EWAY_EMAIL') ? (string) EWAY_EMAIL : '',
            'username'     => defined('EWAY_USERNAME') ? (string) EWAY_USERNAME : '',
            'password'     => defined('EWAY_PASSWORD') ? (string) EWAY_PASSWORD : '',
            'ip_address'   => defined('EWAY_IP_ADDRESS') ? (string) EWAY_IP_ADDRESS : '',
            'client_id'    => defined('EWAY_CLIENT_ID') ? (string) EWAY_CLIENT_ID : '',
            'client_secret' => defined('EWAY_CLIENT_SECRET') ? (string) EWAY_CLIENT_SECRET : '',
            'gstin'        => defined('EWAY_GSTIN') ? (string) EWAY_GSTIN : '',
        ];
    }
}

if (!function_exists('ewaybill_mask_secret')) {
    function ewaybill_mask_secret(string $s): string
    {
        if ($s === '') {
            return '—';
        }
        $len = strlen($s);
        if ($len <= 4) {
            return '****';
        }

        return str_repeat('*', $len - 4) . substr($s, -4);
    }
}

if (!function_exists('ewaybill_validate_whitebooks_genewaybill_url')) {
    /**
     * Ensures POST targets NIC Generate E-Way Bill API, not /authenticate or unrelated endpoints.
     */
    function ewaybill_validate_whitebooks_genewaybill_url(string $url): ?string
    {
        $u = trim($url);
        if (strpos($u, 'apisandbbox') !== false) {
            return 'Wrong WhiteBooks host: apisandbbox. Use apisandbox.';
        }
        if ($u === '') {
            return 'Generate URL is empty. Set EWAY_BASE_URL in admin/config/ewaybill_config.php.';
        }
        $lu = strtolower($u);
        if (strpos($lu, '/ewayapi/genewaybill') === false && strpos($lu, 'genewaybill') === false) {
            return 'Generate URL must be WhiteBooks NIC Generate E-Way Bill endpoint (path containing /ewayapi/genewaybill), not authentication or test URLs. Check admin/config/ewaybill_config.php (EWAY_BASE_URL / WHITEBOOKS_GENERATE_URL).';
        }
        foreach (['/authenticate', '/auth/token', '/oauth/token'] as $bad) {
            if (strpos($lu, $bad) !== false && strpos($lu, 'genewaybill') === false) {
                return 'Generate URL points to authentication, not genewaybill. Fix admin/config/ewaybill_config.php.';
            }
        }

        return null;
    }
}

if (!function_exists('ewaybill_collect_eway_debug_urls')) {
    /**
     * @param array<string, string> $mergedCfg from ewaybill_merged_config
     *
     * @return array{base_url: string, final_generate_url: string, final_get_url: string}
     */
    function ewaybill_collect_eway_debug_urls(array $mergedCfg): array
    {
        $base = rtrim((string) ($mergedCfg['base_url'] ?? ''), '/');
        if ($base === '' && defined('EWAY_BASE_URL')) {
            $base = rtrim((string) EWAY_BASE_URL, '/');
        }

        return [
            'base_url'             => $base,
            'final_generate_url'   => (string) ($GLOBALS['AURAGOLD_EWAY_LAST_GENERATE_URL'] ?? ''),
            'final_get_url'        => (string) ($GLOBALS['AURAGOLD_EWAY_LAST_GET_URL'] ?? ''),
        ];
    }
}

if (!function_exists('ewaybill_redact_url_for_log')) {
    function ewaybill_redact_url_for_log(string $url): string
    {
        return (string) preg_replace('/([?&]password=)[^&]*/i', '$1***', $url);
    }
}

if (!function_exists('ewaybill_redact_headers_for_log')) {
    /**
     * @param array<string, string> $h
     */
    function ewaybill_redact_headers_for_log(array $h): string
    {
        $c = $h;
        foreach (['client_secret', 'Client_Secret', 'CLIENT_SECRET', 'authtoken', 'Authtoken', 'AuthToken'] as $k) {
            if (isset($c[$k]) && $c[$k] !== '') {
                $c[$k] = '***';
            }
        }
        if (isset($c['client_secret'])) {
            $c['client_secret'] = '***';
        }
        if (isset($c['authtoken'])) {
            $c['authtoken'] = '***';
        }

        return (string) json_encode($c, JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('ewaybill_redact_secrets_for_eway_ui')) {
    /**
     * Recursively redact API responses for browser / JSON output (never show full client_secret, authtoken, etc.).
     *
     * @param mixed $d
     * @return mixed
     */
    function ewaybill_redact_secrets_for_eway_ui($d)
    {
        if (is_array($d)) {
            $o = [];
            foreach ($d as $k => $v) {
                $kl = strtolower((string) $k);
                if (in_array($kl, ['client_secret', 'clientsecret', 'authtoken', 'auth_token', 'password', 'sek'], true)) {
                    $o[$k] = '***';
                } elseif ($kl === 'client_id' && is_string($v) && strlen($v) > 10) {
                    $o[$k] = substr($v, 0, 6) . '…' . substr($v, -4);
                } elseif ($kl === 'header' && is_array($v)) {
                    $o[$k] = ewaybill_redact_secrets_for_eway_ui($v);
                } else {
                    $o[$k] = is_array($v) || is_object($v) ? ewaybill_redact_secrets_for_eway_ui($v) : $v;
                }
            }

            return $o;
        }
        if (is_object($d)) {
            return ewaybill_redact_secrets_for_eway_ui((array) $d);
        }

        return $d;
    }
}

if (!function_exists('ewaybill_sanitize_eway_api_json_for_ui')) {
    function ewaybill_sanitize_eway_api_json_for_ui(string $json): string
    {
        if ($json === '') {
            return '';
        }
        $a = json_decode($json, true);
        if (!is_array($a)) {
            return $json;
        }

        return (string) json_encode(ewaybill_redact_secrets_for_eway_ui($a), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('ewaybill_nic_is_distance_rejected_error')) {
    /**
     * True when NIC/WhiteBooks rejects transDistance vs PIN master (error 212, distance/pincode text, etc.).
     *
     * @param array<string, mixed> $out
     */
    function ewaybill_nic_is_distance_rejected_error(array $out): bool
    {
        $msg = (string) ($out['error'] ?? '');
        if (preg_match('/"errorCodes"\s*:\s*"[^"]*212/', $msg)) {
            return true;
        }
        if (preg_match('/\b212\b/', $msg) && stripos($msg, 'errorCodes') !== false) {
            return true;
        }
        if (stripos($msg, 'distance between the pincodes') !== false) {
            return true;
        }
        if (stripos($msg, 'pincodes given is too high') !== false) {
            return true;
        }
        $raw = $out['raw'] ?? null;
        if (is_array($raw)) {
            $nest = $raw['error'] ?? null;
            if (is_array($nest)) {
                $m2 = (string) ($nest['message'] ?? '');
                if (preg_match('/212/', $m2) && stripos($m2, 'errorCodes') !== false) {
                    return true;
                }
                $ib = (string) ($nest['info'] ?? '');
                if ($ib !== '') {
                    $dec = @base64_decode($ib, true);
                    if (is_string($dec) && stripos($dec, 'distance') !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

if (!function_exists('ewaybill_ensure_tables')) {
    /**
     * Create e-Way Bill tables if they do not exist (same schema as migrations/create_ewaybill_tables.sql).
     * Idempotent: runs at most once per PHP request.
     */
    function ewaybill_ensure_tables(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $ddls = [
            'CREATE TABLE IF NOT EXISTS tbl_ewaybill_api_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_eway_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS tbl_ewaybill_api_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gstin VARCHAR(20) NOT NULL DEFAULT \'\',
  email VARCHAR(150) NULL,
  username VARCHAR(100) NOT NULL DEFAULT \'\',
  auth_token TEXT NULL,
  sek TEXT NULL,
  token_expiry DATETIME NULL,
  response_json LONGTEXT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_eway_gstin_username (gstin(20), username(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS tbl_ewaybill_api_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_name VARCHAR(100) NOT NULL DEFAULT \'\',
  request_url TEXT NULL,
  request_headers LONGTEXT NULL,
  request_body LONGTEXT NULL,
  response_body LONGTEXT NULL,
  http_code INT NULL,
  status VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
        foreach ($ddls as $sql) {
            if (!@mysqli_query($conn, $sql)) {
                $cache = false;
                return false;
            }
        }
        $cache = true;
        return true;
    }
}

if (!function_exists('ewaybill_merged_config')) {
    /**
     * File defaults + tbl_ewaybill_api_settings (DB values override when non-empty; password/secret only when stored).
     *
     * @return array<string, string>
     */
    function ewaybill_merged_config(mysqli $conn): array
    {
        $fileDefaults = ewaybill_load_file_config();
        $base = $fileDefaults;
        if (!ewaybill_ensure_tables($conn)) {
            return $base;
        }
        $q = @mysqli_query($conn, 'SELECT setting_key, setting_value FROM tbl_ewaybill_api_settings');
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $k = trim((string) ($row['setting_key'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $v = $row['setting_value'] ?? null;
                if ($v === null) {
                    continue;
                }
                if (!array_key_exists($k, $base)) {
                    continue;
                }
                if ($k === 'password' || $k === 'client_secret') {
                    $sv = (string) $v;
                    if ($sv !== '') {
                        $base[$k] = $sv;
                    }
                    continue;
                }
                $s = trim((string) $v);
                if ($s !== '') {
                    if ($k === 'base_url') {
                        $s = ewaybill_fix_typo_whitebooks_host($s);
                    }
                    $base[$k] = $s;
                }
            }
            mysqli_free_result($q);
        }

        /** DB GSTIN overrides file — reject only values that are not usable as seller/API GSTIN; allow sandbox …Q000-style IDs. */
        $mergedGst = strtoupper(preg_replace('/\s+/', '', (string) ($base['gstin'] ?? '')));
        if ($mergedGst !== '' && !ewaybill_is_acceptable_eway_api_gstin($mergedGst)) {
            $fb = strtoupper(preg_replace('/\s+/', '', (string) ($fileDefaults['gstin'] ?? '')));
            if ($fb !== '' && ewaybill_is_acceptable_eway_api_gstin($fb)) {
                $base['gstin'] = $fb;
            } else {
                $base['gstin'] = '';
            }
        }

        return $base;
    }
}

if (!function_exists('ewaybill_is_whitebooks_sandbox_mode')) {
    /**
     * True when the configured WhiteBooks base URL targets sandbox (credential GSTIN may use non-NIC shapes).
     *
     * @param array<string, string> $config merged e-way config (needs base_url)
     */
    function ewaybill_is_whitebooks_sandbox_mode(array $config): bool
    {
        $u = (string) ($config['base_url'] ?? '');

        return stripos($u, 'apisandbox.whitebooks.in') !== false
            || stripos($u, 'sandbox') !== false;
    }
}

if (!function_exists('ewaybill_effective_ip_address_header')) {
    /**
     * WhiteBooks sandbox portal registers IP(header) as 0.0.0.0 — always send that for sandbox base URLs.
     * Production uses the configured ip_address from merged settings / file.
     *
     * @param array<string, string> $config merged e-way config (needs base_url, ip_address)
     */
    function ewaybill_effective_ip_address_header(array $config): string
    {
        if (ewaybill_is_whitebooks_sandbox_mode($config)) {
            return '0.0.0.0';
        }

        return trim((string) ($config['ip_address'] ?? ''));
    }
}

if (!function_exists('ewaybill_upsert_setting')) {
    function ewaybill_upsert_setting(mysqli $conn, string $key, string $value): bool
    {
        if (!ewaybill_ensure_tables($conn)) {
            return false;
        }
        $k = $key;
        if ($k === '' || !preg_match('/^[a-z0-9_]+$/i', $k)) {
            return false;
        }
        $sql = 'INSERT INTO tbl_ewaybill_api_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()';
        $st = $conn->prepare($sql);
        if (!$st) {
            return false;
        }
        $st->bind_param('ss', $k, $value);
        $ok = $st->execute();
        $st->close();

        return (bool) $ok;
    }
}

if (!function_exists('ewaybill_log_api_request')) {
    /**
     * @param array<string, string> $headers
     */
    function ewaybill_log_api_request(
        mysqli $conn,
        string $apiName,
        string $urlRedacted,
        array $headers,
        ?string $body,
        string $responseBody,
        ?int $httpCode,
        string $status
    ): void {
        $hJson = ewaybill_redact_headers_for_log($headers);
        $sql = 'INSERT INTO tbl_ewaybill_api_logs (api_name, request_url, request_headers, request_body, response_body, http_code, status) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)';
        $st = $conn->prepare($sql);
        if (!$st) {
            return;
        }
        $b = $body;
        $rb = $responseBody;
        $st->bind_param(
            'sssssis',
            $apiName,
            $urlRedacted,
            $hJson,
            $b,
            $rb,
            $httpCode,
            $status
        );
        if (!$st->execute()) {
            $st->close();
            return;
        }
        $st->close();
    }
}

if (!function_exists('ewaybill_array_first_string')) {
    function ewaybill_array_first_string($data, array $candidates): string
    {
        if (!is_array($data)) {
            return '';
        }
        foreach ($candidates as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }
}

if (!function_exists('ewaybill_extract_eway_no_explicit_paths')) {
    /**
     * Known WhiteBooks/NIC keys for E-Way bill number (root + one-level nested).
     *
     * @param array<string, mixed> $r
     */
    function ewaybill_extract_eway_no_explicit_paths(array $r): string
    {
        $billKeys = ['ewayBillNo', 'eway_bill_no', 'ewayBillNumber', 'EwbNo', 'ewbNo', 'EWAYBILLNO'];
        foreach ($billKeys as $k) {
            if (!array_key_exists($k, $r)) {
                continue;
            }
            $v = $r[$k];
            if ($v === null || $v === '') {
                continue;
            }
            if (is_string($v) || is_numeric($v)) {
                $s = trim((string) $v);
                if ($s !== '') {
                    return $s;
                }
            }
        }
        $nestedOne   = [];
        $containers = ['data', 'Data', 'result', 'Result', 'ResponseData'];
        foreach ($containers as $container) {
            foreach ($billKeys as $bk) {
                $nestedOne[] = [$container, $bk];
            }
        }
        foreach ($nestedOne as $path) {
            $node = $r;
            foreach ($path as $seg) {
                if (!is_array($node) || !array_key_exists($seg, $node)) {
                    $node = null;
                    break;
                }
                $node = $node[$seg];
            }
            if ($node === null) {
                continue;
            }
            if (is_string($node) || is_numeric($node)) {
                $s = trim((string) $node);
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return '';
    }
}

if (!function_exists('ewaybill_extract_eway_no_from_api_response')) {
    /**
     * WhiteBooks/NIC may nest e-way number under data/Data/result (recursive).
     */
    function ewaybill_extract_eway_no_from_api_response(array $r): string
    {
        $ex = ewaybill_extract_eway_no_explicit_paths($r);
        if ($ex !== '') {
            return $ex;
        }
        foreach (['ewayBillNo', 'eway_bill_no', 'ewayBillNumber', 'EwbNo', 'ewbNo', 'EWAYBILLNO'] as $k) {
            if (!empty($r[$k]) && (is_string($r[$k]) || is_numeric($r[$k]))) {
                $s = trim((string) $r[$k]);
                if ($s !== '') {
                    return $s;
                }
            }
        }
        foreach (['data', 'Data', 'result', 'Result', 'dataItem', 'ResponseData'] as $nk) {
            if (isset($r[$nk]) && is_array($r[$nk])) {
                $inner = ewaybill_extract_eway_no_from_api_response($r[$nk]);
                if ($inner !== '') {
                    return $inner;
                }
            }
        }

        return '';
    }
}

if (!function_exists('ewaybill_extract_eway_dates_from_api_response')) {
    /**
     * Best-effort bill date + validity from nested NIC-style JSON.
     *
     * @return array{0: string, 1: string}
     */
    function ewaybill_extract_eway_dates_from_api_response(array $response): array
    {
        $date_out = date('Y-m-d H:i:s');
        $dt       = null;
        foreach ([$response, $response['data'] ?? null, $response['Data'] ?? null, $response['result'] ?? null, $response['Result'] ?? null, $response['ResponseData'] ?? null] as $node) {
            if (!is_array($node)) {
                continue;
            }
            $dt = $node['ewayBillDate'] ?? $node['EwbDt'] ?? $node['ewbDate'] ?? null;
            if ($dt !== null && (string) $dt !== '') {
                break;
            }
        }
        if ($dt !== null && (string) $dt !== '') {
            $ts = strtotime((string) $dt);
            if ($ts !== false) {
                $date_out = date('Y-m-d H:i:s', $ts);
            }
        }
        $valid_upto = '';
        foreach ([$response, $response['data'] ?? null, $response['Data'] ?? null, $response['result'] ?? null, $response['Result'] ?? null, $response['ResponseData'] ?? null] as $node) {
            if (!is_array($node)) {
                continue;
            }
            $vu = $node['validUpto'] ?? $node['validTill'] ?? $node['ValidUpto'] ?? null;
            if ($vu !== null && (string) $vu !== '') {
                $valid_upto = is_string($vu) ? $vu : (string) $vu;
                break;
            }
        }

        return [$date_out, $valid_upto];
    }
}

if (!function_exists('ewaybill_sale_invoice_doc_date_dmY')) {
    function ewaybill_sale_invoice_doc_date_dmY(mysqli $conn, int $invoice_id): string
    {
        $row = function_exists('getRecord') ? @getRecord('SELECT invoice_date FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1') : null;
        if (is_array($row) && !empty($row['invoice_date'])) {
            $ts = strtotime((string) $row['invoice_date']);
            if ($ts !== false) {
                return date('d/m/Y', $ts);
            }
        }

        return date('d/m/Y');
    }
}

if (!function_exists('ewaybill_whitebooks_http_get_json')) {
    /**
     * @return array{http: int, body: string, curl_err: string}
     */
    function ewaybill_whitebooks_http_get_json(string $url, array $headerLines): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $resp_body = curl_exec($ch);
        $curl_err  = curl_errno($ch) !== 0 ? (string) curl_error($ch) : '';
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp_body === false) {
            $resp_body = '';
        }

        return ['http' => $http_code, 'body' => is_string($resp_body) ? $resp_body : '', 'curl_err' => $curl_err];
    }
}

if (!function_exists('ewaybill_whitebooks_fetch_ewaybill_by_sale_document')) {
    /**
     * GET /ewayapi/getewaybillgeneratedbyconsigner — document-based lookup when generate omits ewb number.
     *
     * @return array{ok: bool, eway_no: string, bill_date: string, valid_upto: string, raw: ?array, http: int, err: string}
     */
    function ewaybill_whitebooks_fetch_ewaybill_by_sale_document(
        mysqli $conn,
        array $mergedConfig,
        string $sellerGstin,
        int $invoice_id,
        string $invoice_no
    ): array {
        $empty = ['ok' => false, 'eway_no' => '', 'bill_date' => '', 'valid_upto' => '', 'raw' => null, 'http' => 0, 'err' => ''];
        $gstin = strtoupper(preg_replace('/\s+/', '', $sellerGstin));
        if (strlen($gstin) !== 15) {
            return array_merge($empty, ['err' => 'Invalid seller GSTIN for fetch.']);
        }
        $apiRoot = ewaybill_whitebooks_get_endpoint_base($mergedConfig);
        $email = trim((string) ($mergedConfig['email'] ?? ''));
        if ($email === '') {
            return array_merge($empty, ['err' => 'Email missing in e-Way config.']);
        }
        $docNo = str_replace('/', '-', trim($invoice_no));
        if ($docNo === '') {
            return array_merge($empty, ['err' => 'Invoice number empty.']);
        }
        $docDate = ewaybill_sale_invoice_doc_date_dmY($conn, $invoice_id);
        $qs      = http_build_query(
            [
                'email'   => $email,
                'docType' => 'INV',
                'docNo'   => $docNo,
                'docDate' => $docDate,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $curl_url = $apiRoot . '/getewaybillgeneratedbyconsigner?' . $qs;
        $GLOBALS['AURAGOLD_EWAY_LAST_GET_URL'] = $curl_url;

        $tok = '';
        if (function_exists('auragold_eway_ensure_auth_token_for_generate')) {
            $tok = auragold_eway_ensure_auth_token_for_generate($conn, $gstin);
        }
        $hdrLines = [
            'Content-Type: application/json',
            'ip_address: ' . ewaybill_effective_ip_address_header($mergedConfig),
            'client_id: ' . trim((string) ($mergedConfig['client_id'] ?? '')),
            'client_secret: ' . trim((string) ($mergedConfig['client_secret'] ?? '')),
            'gstin: ' . $gstin,
        ];
        if ($tok !== '') {
            $hdrLines[] = 'authtoken: ' . $tok;
        }

        $got  = ewaybill_whitebooks_http_get_json($curl_url, $hdrLines);
        $http = (int) ($got['http'] ?? 0);
        $body = (string) ($got['body'] ?? '');
        $cErr = (string) ($got['curl_err'] ?? '');

        $reqMeta = json_encode(
            [
                'method'   => 'GET',
                'endpoint' => 'getewaybillgeneratedbyconsigner',
                'docType'  => 'INV',
                'docNo'    => $docNo,
                'docDate'  => $docDate,
            ],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $hdrArr = [
            'Content-Type'  => 'application/json',
            'ip_address'    => ewaybill_effective_ip_address_header($mergedConfig),
            'client_id'     => (string) ($mergedConfig['client_id'] ?? ''),
            'client_secret' => (string) ($mergedConfig['client_secret'] ?? ''),
            'gstin'         => $gstin,
        ];
        if ($tok !== '') {
            $hdrArr['authtoken'] = $tok;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $rb = $body !== '' ? $body : ($cErr !== '' ? $cErr : '(empty)');
            ewaybill_log_api_request(
                $conn,
                'getewaybill_by_document',
                $curl_url,
                $hdrArr,
                $reqMeta,
                $rb,
                $http,
                $cErr !== '' ? 'fetch_curl_error' : 'fetch_non_json'
            );

            return array_merge($empty, ['http' => $http, 'err' => $cErr !== '' ? $cErr : 'Response is not JSON']);
        }

        $ewNo = ewaybill_extract_eway_no_from_api_response($decoded);
        [$bd, $vu] = ewaybill_extract_eway_dates_from_api_response($decoded);
        $scOk      = ewaybill_status_cd_indicates_success($decoded);

        $logStatus = 'fetch_ok';
        if ($ewNo !== '') {
            $logStatus = 'got_eway_no';
        } elseif ($scOk === false) {
            $logStatus = 'fetch_status_not_success';
        } else {
            $logStatus = 'no_eway_no_in_fetch';
        }

        ewaybill_log_api_request(
            $conn,
            'getewaybill_by_document',
            $curl_url,
            $hdrArr,
            $reqMeta,
            (string) json_encode(ewaybill_redact_secrets_for_eway_ui($decoded), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            $http,
            $logStatus
        );

        if ($ewNo !== '') {
            return [
                'ok'         => true,
                'eway_no'    => $ewNo,
                'bill_date'  => $bd,
                'valid_upto' => $vu,
                'raw'        => $decoded,
                'http'       => $http,
                'err'        => '',
            ];
        }

        return [
            'ok'         => false,
            'eway_no'    => '',
            'bill_date'  => '',
            'valid_upto' => '',
            'raw'        => $decoded,
            'http'       => $http,
            'err'        => $scOk === false ? 'Get E-Way bill API indicated failure' : 'No e-way number in Get API response',
        ];
    }
}

if (!function_exists('ewaybill_log_genewaybill_api_mirror')) {
    /**
     * Mirror generate call into tbl_ewaybill_api_logs (full request_url stored; headers JSON redacted).
     */
    function ewaybill_log_genewaybill_api_mirror(
        mysqli $conn,
        string $requestUrlFull,
        string $headersJson,
        string $requestBody,
        string $responseBody,
        ?int $httpCode,
        string $status
    ): void {
        $hArr = json_decode($headersJson, true);
        if (!is_array($hArr)) {
            $hArr = ['_headers' => $headersJson];
        }
        $hc = $httpCode === null ? null : (int) $httpCode;
        if ($hc === null) {
            $hc = 0;
        }
        ewaybill_log_api_request($conn, 'generate_ewaybill', $requestUrlFull, $hArr, $requestBody, $responseBody, $hc, $status);
    }
}

if (!function_exists('ewaybill_find_nested_array')) {
    /**
     * @return array<string, mixed>
     */
    function ewaybill_find_nested_array($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        foreach (['Data', 'data', 'result', 'Result', 'dataItem'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                return $data[$k];
            }
        }

        return is_array($data) ? $data : [];
    }
}

if (!function_exists('ewaybill_status_cd_indicates_success')) {
    /**
     * GST / WhiteBooks style: status_cd (or StatusCd) 1 / "1" = success, 0 = failure.
     * Returns null if the response does not carry a status_cd-style field (checks root, then Data).
     */
    function ewaybill_status_cd_indicates_success(array $d): ?bool
    {
        foreach (['status_cd', 'StatusCd', 'statusCode', 'StatusCode'] as $k) {
            if (!array_key_exists($k, $d)) {
                continue;
            }
            $v = $d[$k];
            if (is_int($v) || is_float($v)) {
                return (int) $v === 1;
            }
            $s = is_string($v) ? trim($v) : (string) $v;
            if ($s === '1' || $s === '01') {
                return true;
            }
            if ($s === '0' || $s === '00' || $s === '') {
                return false;
            }
            if (is_numeric($s) && (int) $s === 1) {
                return true;
            }

            return false;
        }
        if (isset($d['Data']) && is_array($d['Data'])) {
            return ewaybill_status_cd_indicates_success($d['Data']);
        }

        return null;
    }
}

if (!function_exists('ewaybill_parse_expiry')) {
    function ewaybill_parse_expiry($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            $n = (float) $v;
            if ($n > 2000000000) {
                $d = (int) round($n / 1000);
                return date('Y-m-d H:i:s', $d);
            }
        }
        $s = is_string($v) ? trim($v) : '';
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }

        return null;
    }
}

if (!function_exists('ewaybill_extract_token_fields')) {
    /**
     * @param array<string, mixed> $decoded
     * @return array{auth_token: string, sek: string, expiry: ?string}
     */
    function ewaybill_extract_token_fields(array $decoded): array
    {
        $nested = ewaybill_find_nested_array($decoded);
        if ($nested === []) {
            $nested = $decoded;
        }
        $auth = ewaybill_array_first_string(
            $nested,
            [
                'AuthToken', 'authToken', 'authtoken', 'auth_token', 'token', 'Token',
            ]
        );
        if ($auth === '') {
            $auth = ewaybill_array_first_string(
                $decoded,
                ['AuthToken', 'authToken', 'authtoken', 'auth_token', 'token', 'Token']
            );
        }
        $sek = ewaybill_array_first_string(
            $nested,
            ['Sek', 'sek', 'SekData', 'sekData'],
        );
        if ($sek === '') {
            $sek = ewaybill_array_first_string(
                $decoded,
                ['Sek', 'sek', 'SekData', 'sekData'],
            );
        }
        $exV = null;
        foreach (['TokenExpiry', 'tokenExpiry', 'token_expiry', 'Expiry', 'expiry', 'validTill'] as $k) {
            if (isset($nested[$k]) && $nested[$k] !== null && (string) $nested[$k] !== '') {
                $exV = $nested[$k];
                break;
            }
        }
        if ($exV === null) {
            foreach (['TokenExpiry', 'tokenExpiry', 'token_expiry', 'Expiry', 'expiry', 'validTill'] as $k) {
                if (isset($decoded[$k]) && $decoded[$k] !== null && (string) $decoded[$k] !== '') {
                    $exV = $decoded[$k];
                    break;
                }
            }
        }
        $exSql = ewaybill_parse_expiry($exV);

        return [
            'auth_token' => $auth,
            'sek'        => $sek,
            'expiry'     => $exSql,
        ];
    }
}

if (!function_exists('ewaybill_is_auth_response_success')) {
    function ewaybill_is_auth_response_success(array $decoded, int $http): bool
    {
        if ($http !== 200) {
            return false;
        }
        $f = ewaybill_extract_token_fields($decoded);
        if ($f['auth_token'] !== '' || $f['sek'] !== '') {
            return true;
        }
        $st = ewaybill_status_cd_indicates_success($decoded);
        if ($st === false) {
            return false;
        }
        if ($st === true) {
            return true;
        }
        if (isset($decoded['ErrorDetails']) && is_string($decoded['ErrorDetails']) && $decoded['ErrorDetails'] !== '') {
            return false;
        }
        if (isset($decoded['error_code']) && (int) $decoded['error_code'] > 0) {
            return false;
        }
        if (isset($decoded['ErrorCode']) && (string) $decoded['ErrorCode'] !== '' && (string) $decoded['ErrorCode'] !== '0') {
            return false;
        }

        return false;
    }
}

if (!function_exists('ewaybill_upsert_token')) {
    function ewaybill_upsert_token(
        mysqli $conn,
        string $gstin,
        string $email,
        string $username,
        string $authToken,
        string $sek,
        ?string $expirySql,
        string $responseJson
    ): void {
        $ex = $expirySql;
        $st = $conn->prepare(
            'INSERT INTO tbl_ewaybill_api_tokens (gstin, email, username, auth_token, sek, token_expiry, response_json, status, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE email = VALUES(email), auth_token = VALUES(auth_token), '
            . 'sek = VALUES(sek), token_expiry = VALUES(token_expiry), response_json = VALUES(response_json), status = 1, updated_at = NOW()'
        );
        if (!$st) {
            return;
        }
        $st->bind_param(
            'sssssss',
            $gstin,
            $email,
            $username,
            $authToken,
            $sek,
            $ex,
            $responseJson
        );
        $st->execute();
        $st->close();
    }
}

if (!function_exists('ewaybill_get_ops_mysqli')) {
    function ewaybill_get_ops_mysqli(): ?mysqli
    {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }
        global $conn_master;
        if (isset($conn_master) && $conn_master instanceof mysqli) {
            return $conn_master;
        }

        return null;
    }
}

if (!function_exists('ewaybill_authenticate')) {
    /**
     * Call WhiteBooks authenticate; log request/response; persist tokens on success.
     *
     * @return array{ok: bool, http_code: int|null, curl_error: string|null, data: array|null, message: string, raw_body: string, logged_status: string}
     */
    function ewaybill_authenticate(?mysqli $opConn = null): array
    {
        $conn = $opConn ?? ewaybill_get_ops_mysqli();
        if ($conn === null) {
            return [
                'ok'            => false,
                'http_code'     => null,
                'curl_error'    => 'Database connection is not available.',
                'data'          => null,
                'message'       => 'Database connection is not available.',
                'raw_body'      => '',
                'logged_status' => 'config_error',
            ];
        }
        if (!ewaybill_ensure_tables($conn)) {
            return [
                'ok'            => false,
                'http_code'     => null,
                'curl_error'    => null,
                'data'          => null,
                'message'       => 'e-Way Bill database tables could not be created. Check the MySQL user has CREATE permission.',
                'raw_body'      => '',
                'logged_status' => 'schema_error',
            ];
        }
        ewaybill_load_file_config();
        $c = ewaybill_merged_config($conn);
        $base = rtrim((string) ($c['base_url'] ?? ''), '/');
        if ($base === '') {
            return [
                'ok'            => false,
                'http_code'     => null,
                'curl_error'    => null,
                'data'          => null,
                'message'       => 'E-Way Bill base URL is not configured.',
                'raw_body'      => '',
                'logged_status' => 'config_error',
            ];
        }
        $query = http_build_query(
            [
                'email'    => $c['email'] ?? '',
                'username' => $c['username'] ?? '',
                'password' => $c['password'] ?? '',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $url = ewaybill_whitebooks_authenticate_url($c, $query);
        $headers = [
            'ip_address'    => ewaybill_effective_ip_address_header($c),
            'client_id'     => (string) ($c['client_id'] ?? ''),
            'client_secret' => (string) ($c['client_secret'] ?? ''),
            'gstin'         => (string) ($c['gstin'] ?? ''),
            'Accept'        => 'application/json',
        ];
        $headerLines = [];
        foreach ($headers as $name => $val) {
            $headerLines[] = $name . ': ' . $val;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok'            => false,
                'http_code'     => null,
                'curl_error'    => 'cURL could not be initialized.',
                'data'          => null,
                'message'       => 'cURL could not be initialized.',
                'raw_body'      => '',
                'logged_status' => 'curl_error',
            ];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        $raw   = (string) curl_exec($ch);
        $errno = (int) curl_errno($ch);
        $cErr  = $errno ? (string) curl_error($ch) : '';
        $hCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $redactedUrl = ewaybill_redact_url_for_log($url);
        if ($errno) {
            ewaybill_log_api_request(
                $conn,
                'authenticate',
                $redactedUrl,
                $headers,
                null,
                'cURL error: ' . $cErr,
                $hCode > 0 ? $hCode : null,
                'curl_error'
            );

            return [
                'ok'            => false,
                'http_code'     => $hCode > 0 ? $hCode : null,
                'curl_error'    => $cErr,
                'data'          => null,
                'message'       => $cErr !== '' ? 'Network error: ' . $cErr : 'A network error occurred while calling the API.',
                'raw_body'      => $raw,
                'logged_status' => 'curl_error',
            ];
        }

        $decoded = null;
        if ($raw !== '') {
            $rawForJson = trim($raw);
            if (strncmp($rawForJson, "\xEF\xBB\xBF", 3) === 0) {
                $rawForJson = substr($rawForJson, 3);
            }
            $decoded = json_decode($rawForJson, true);
        }
        if (!is_array($decoded)) {
            ewaybill_log_api_request(
                $conn,
                'authenticate',
                $redactedUrl,
                $headers,
                null,
                $raw,
                $hCode,
                'invalid_json'
            );
            if ($hCode !== 200) {
                $msg = 'The API did not return HTTP 200. Response: ' . ewaybill_clip_text($raw, 2000);
            } else {
                $hint = ewaybill_clip_text(preg_replace('/\s+/', ' ', strip_tags($raw)), 400);
                $msg = 'The API response was not valid JSON.';
                if ($hint !== '') {
                    $msg .= ' Body preview: ' . $hint;
                }
            }

            return [
                'ok'            => false,
                'http_code'     => $hCode,
                'curl_error'    => null,
                'data'          => null,
                'message'       => $msg,
                'raw_body'      => $raw,
                'logged_status' => 'invalid_json',
            ];
        }

        $okAuth = ewaybill_is_auth_response_success($decoded, $hCode);
        $stLog  = ($hCode === 200 && $okAuth) ? 'ok' : (($hCode === 200) ? 'error' : 'http_error');
        ewaybill_log_api_request(
            $conn,
            'authenticate',
            $redactedUrl,
            $headers,
            null,
            $raw,
            $hCode,
            $stLog
        );
        if ($hCode !== 200) {
            $msg = ewaybill_user_message_for_failed_json($decoded, $hCode) ?: ('HTTP ' . $hCode . '. ' . ewaybill_clip_text($raw, 1500));

            return [
                'ok'            => false,
                'http_code'     => $hCode,
                'curl_error'    => null,
                'data'          => $decoded,
                'message'       => $msg,
                'raw_body'      => $raw,
                'logged_status' => 'http_error',
            ];
        }
        if ($okAuth) {
            $tok = ewaybill_extract_token_fields($decoded);
            if ($tok['auth_token'] !== '' || $tok['sek'] !== '') {
                $g  = (string) ($c['gstin'] ?? '');
                $em = (string) ($c['email'] ?? '');
                $un = (string) ($c['username'] ?? '');
                ewaybill_upsert_token(
                    $conn,
                    $g,
                    $em,
                    $un,
                    (string) $tok['auth_token'],
                    (string) $tok['sek'],
                    $tok['expiry'],
                    (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
        }

        $ok = $okAuth;
        if (!$ok) {
            $msg = ewaybill_user_message_for_failed_json($decoded, $hCode) ?: (ewaybill_clip_text($raw, 2000) ?: 'Authentication was not successful.');
        } else {
            $tok = ewaybill_extract_token_fields($decoded);
            if ($tok['auth_token'] !== '' || $tok['sek'] !== '') {
                $msg = 'Authentication successful. Token details have been stored.';
            } elseif (ewaybill_is_whitebooks_sandbox_mode($c)) {
                $msg = 'Sandbox authentication successful. Token/SEK not returned by this sandbox response.';
            } else {
                $msg = 'The API returned success (status_cd=1). No AuthToken or SEK fields were in the response body, so the token table was not updated. Some sandbox or test calls only echo the request; production responses should include AuthToken and Sek (often under Data).';
            }
        }

        return [
            'ok'            => $ok,
            'http_code'     => $hCode,
            'curl_error'    => null,
            'data'          => $decoded,
            'message'       => $msg,
            'raw_body'      => $raw,
            'logged_status' => $ok ? 'ok' : 'error',
        ];
    }
}

if (!function_exists('ewaybill_clip_text')) {
    function ewaybill_clip_text(string $s, int $max = 2000): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max) . '…';
    }
}

if (!function_exists('ewaybill_user_message_for_failed_json')) {
    function ewaybill_user_message_for_failed_json(array $d, int $h): string
    {
        foreach (['message', 'Message', 'ErrorMessage', 'error', 'ErrorDetails', 'errorMessage'] as $k) {
            if (!empty($d[$k]) && is_string($d[$k])) {
                $s = trim($d[$k]);
                if ($s !== '') {
                    return 'API error: ' . ewaybill_clip_text($s, 800);
                }
            }
        }
        if (isset($d['Error']) && is_array($d['Error']) && !empty($d['Error']['Message']) && is_string($d['Error']['Message'])) {
            return 'API error: ' . ewaybill_clip_text(trim($d['Error']['Message']), 800);
        }

        return $h !== 200 ? 'HTTP ' . $h : '';
    }
}

if (!function_exists('ewaybill_fetch_token_row_for_config')) {
    /**
     * @return array<string, string|null>|null
     */
    function ewaybill_fetch_token_row_for_config(mysqli $conn, string $gstin, string $username): ?array
    {
        $g = $gstin;
        $u = $username;
        $st = $conn->prepare(
            'SELECT * FROM tbl_ewaybill_api_tokens WHERE gstin = ? AND username = ? LIMIT 1'
        );
        if (!$st) {
            return null;
        }
        $st->bind_param('ss', $g, $u);
        if (!$st->execute()) {
            $st->close();
            return null;
        }
        $res = $st->get_result();
        if (!$res) {
            $st->close();
            return null;
        }
        $row = $res->fetch_assoc();
        $st->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('auragold_eway_ensure_auth_token_for_generate')) {
    /**
     * Resolves a valid auth token for genewaybill (NIC v1.03: `authtoken` header; missing/invalid → API error 238).
     * Uses tbl_ewaybill_api_tokens, then ewaybill_authenticate() if missing or near expiry.
     */
    function auragold_eway_ensure_auth_token_for_generate(mysqli $conn, string $gstin): string
    {
        $g = strtoupper(preg_replace('/\s+/', '', $gstin));
        if (strlen($g) !== 15) {
            return '';
        }
        ewaybill_load_file_config();
        $cfg = ewaybill_merged_config($conn);
        $username = trim((string) ($cfg['username'] ?? ''));

        $row = null;
        if ($username !== '') {
            $row = ewaybill_fetch_token_row_for_config($conn, $g, $username);
        }
        if (!is_array($row) || trim((string) ($row['auth_token'] ?? '')) === '') {
            $st = $conn->prepare('SELECT auth_token, token_expiry FROM tbl_ewaybill_api_tokens WHERE gstin = ? AND status = 1 ORDER BY updated_at DESC, id DESC LIMIT 1');
            if ($st) {
                $st->bind_param('s', $g);
                if ($st->execute()) {
                    $res = $st->get_result();
                    if ($res) {
                        $r2 = $res->fetch_assoc();
                        if (is_array($r2)) {
                            $row = $r2;
                        }
                    }
                }
                $st->close();
            }
        }

        $tok = is_array($row) ? trim((string) ($row['auth_token'] ?? '')) : '';
        if ($tok !== '' && !empty($row['token_expiry'])) {
            $ts = strtotime((string) $row['token_expiry']);
            if ($ts && $ts < time() + 120) {
                $tok = '';
            }
        }
        if ($tok !== '') {
            return $tok;
        }
        if (!function_exists('ewaybill_authenticate')) {
            return '';
        }
        $authRes = ewaybill_authenticate($conn);
        if (empty($authRes['ok'])) {
            return '';
        }
        if ($username !== '') {
            $row = ewaybill_fetch_token_row_for_config($conn, $g, $username);
        } else {
            $row = null;
            $st = $conn->prepare('SELECT auth_token, token_expiry FROM tbl_ewaybill_api_tokens WHERE gstin = ? AND status = 1 ORDER BY updated_at DESC, id DESC LIMIT 1');
            if ($st) {
                $st->bind_param('s', $g);
                if ($st->execute()) {
                    $res = $st->get_result();
                    if ($res) {
                        $r2 = $res->fetch_assoc();
                        if (is_array($r2)) {
                            $row = $r2;
                        }
                    }
                }
                $st->close();
            }
        }
        if (!is_array($row)) {
            return '';
        }

        return trim((string) ($row['auth_token'] ?? ''));
    }
}

if (!function_exists('ewaybill_ensure_generate_log_table')) {
    function ewaybill_ensure_generate_log_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $sql = 'CREATE TABLE IF NOT EXISTS tbl_ewaybill_generate_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NULL,
  invoice_no VARCHAR(100) NULL,
  request_url TEXT,
  request_headers LONGTEXT,
  request_body LONGTEXT,
  response_body LONGTEXT,
  http_code INT NULL,
  status_cd VARCHAR(10) NULL,
  status_desc TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        @mysqli_query($conn, $sql);
        $done = true;
    }
}

if (!function_exists('ewaybill_ensure_sale_invoice_eway_extras')) {
    /**
     * Add e-Way columns on tbl_sale_invoices if missing.
     * Mirrors migrations/ewaybill_sale_invoice_columns_user_request.sql and transport fields
     * (per migrations/ewaybill_sale_invoice_integration.sql comments); safe to run repeatedly.
     */
    function ewaybill_ensure_sale_invoice_eway_extras(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $alters = [
            'customer_gstin VARCHAR(20) NULL DEFAULT NULL',
            'eway_vehicle_no VARCHAR(32) NULL DEFAULT NULL',
            'eway_distance_km DECIMAL(10,2) NULL DEFAULT NULL',
            'eway_bill_no VARCHAR(50) NULL DEFAULT NULL',
            'eway_bill_date VARCHAR(50) NULL DEFAULT NULL',
            'eway_valid_upto VARCHAR(50) NULL DEFAULT NULL',
            'eway_status VARCHAR(50) NULL DEFAULT NULL',
            'eway_response LONGTEXT NULL',
            'eway_generated_at DATETIME NULL DEFAULT NULL',
            'eway_trans_mode VARCHAR(2) NULL DEFAULT NULL',
            'eway_transporter_name VARCHAR(200) NULL DEFAULT NULL',
            'eway_transporter_id VARCHAR(20) NULL DEFAULT NULL',
            'eway_trans_doc_no VARCHAR(100) NULL DEFAULT NULL',
            'eway_trans_doc_date VARCHAR(20) NULL DEFAULT NULL',
            'eway_vehicle_type VARCHAR(1) NULL DEFAULT NULL',
            'eway_enable TINYINT(1) NOT NULL DEFAULT 0',
            'eway_to_pincode VARCHAR(10) NULL DEFAULT NULL',
            'eway_trans_distance VARCHAR(12) NULL DEFAULT NULL',
            'eway_request_json LONGTEXT NULL DEFAULT NULL',
        ];
        foreach ($alters as $def) {
            $col = preg_match('/^([a-z0-9_]+)\s/si', $def, $m) ? $m[1] : '';
            if ($col === '') {
                continue;
            }
            $ck = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
            if ($ck && mysqli_num_rows($ck) === 0) {
                @mysqli_query($conn, 'ALTER TABLE tbl_sale_invoices ADD COLUMN ' . $def);
            }
            if ($ck) {
                mysqli_free_result($ck);
            }
        }
        $done = true;
    }
}

if (!function_exists('ewaybill_ensure_pos_sale_invoice_eway_extras')) {
    /**
     * Add e-Way columns on tbl_pos_sale_invoices if missing (mirror of tbl_sale_invoices).
     */
    function ewaybill_ensure_pos_sale_invoice_eway_extras(mysqli $conn): void
    {
        static $donePos = false;
        if ($donePos) {
            return;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoices'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }

            return;
        }
        mysqli_free_result($t);
        $alters = [
            'customer_gstin VARCHAR(20) NULL DEFAULT NULL',
            'eway_vehicle_no VARCHAR(32) NULL DEFAULT NULL',
            'eway_distance_km DECIMAL(10,2) NULL DEFAULT NULL',
            'eway_bill_no VARCHAR(50) NULL DEFAULT NULL',
            'eway_bill_date VARCHAR(50) NULL DEFAULT NULL',
            'eway_valid_upto VARCHAR(50) NULL DEFAULT NULL',
            'eway_status VARCHAR(50) NULL DEFAULT NULL',
            'eway_response LONGTEXT NULL',
            'eway_generated_at DATETIME NULL DEFAULT NULL',
            'eway_trans_mode VARCHAR(2) NULL DEFAULT NULL',
            'eway_transporter_name VARCHAR(200) NULL DEFAULT NULL',
            'eway_transporter_id VARCHAR(20) NULL DEFAULT NULL',
            'eway_trans_doc_no VARCHAR(100) NULL DEFAULT NULL',
            'eway_trans_doc_date VARCHAR(20) NULL DEFAULT NULL',
            'eway_vehicle_type VARCHAR(1) NULL DEFAULT NULL',
            'eway_enable TINYINT(1) NOT NULL DEFAULT 0',
            'eway_to_pincode VARCHAR(10) NULL DEFAULT NULL',
            'eway_trans_distance VARCHAR(12) NULL DEFAULT NULL',
            'eway_request_json LONGTEXT NULL DEFAULT NULL',
        ];
        foreach ($alters as $def) {
            $col = preg_match('/^([a-z0-9_]+)\s/si', $def, $m) ? $m[1] : '';
            if ($col === '') {
                continue;
            }
            $ck = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
            if ($ck && mysqli_num_rows($ck) === 0) {
                @mysqli_query($conn, 'ALTER TABLE tbl_pos_sale_invoices ADD COLUMN ' . $def);
            }
            if ($ck) {
                mysqli_free_result($ck);
            }
        }
        $donePos = true;
    }
}

if (!function_exists('ewaybill_ensure_eway_bill_migrations')) {
    /**
     * One entry point: e-Way generate log table + sale invoice e-Way columns
     * (migrations/ewaybill_sale_invoice_integration.sql + column list from user request migration).
     */
    function ewaybill_ensure_eway_bill_migrations(mysqli $conn): void
    {
        ewaybill_ensure_generate_log_table($conn);
        ewaybill_ensure_sale_invoice_eway_extras($conn);
    }
}

if (!function_exists('ewaybill_log_generate')) {
    function ewaybill_log_generate(
        mysqli $conn,
        int $invoiceId,
        string $invNo,
        string $requestUrlFull,
        string $headersJson,
        string $body,
        string $responseBody,
        ?int $http,
        string $statusCd,
        string $statusDesc
    ): void {
        ewaybill_ensure_generate_log_table($conn);
        $st = $conn->prepare(
            'INSERT INTO tbl_ewaybill_generate_logs (invoice_id, invoice_no, request_url, request_headers, request_body, response_body, http_code, status_cd, status_desc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$st) {
            return;
        }
        $hc = $http === null ? 0 : (int) $http;
        $iid = $invoiceId;
        $in = $invNo;
        $ur = $requestUrlFull;
        $hj = $headersJson;
        $b = $body;
        $rb = $responseBody;
        $sc = $statusCd;
        $sd = $statusDesc;
        $st->bind_param('isssssiss', $iid, $in, $ur, $hj, $b, $rb, $hc, $sc, $sd);
        $st->execute();
        $st->close();
    }
}

if (!function_exists('ewaybill_resolve_trans_distance_string')) {
    /**
     * NIC transDistance: never read from live POST. Uses saved invoice only.
     * Second attempt / forced NIC: always "0" so master PIN distance is used.
     *
     * @param array<string, mixed> $inRow
     */
    function ewaybill_resolve_trans_distance_string(array $inRow, bool $forceNicZero): string
    {
        if ($forceNicZero) {
            return '0';
        }
        $transDistance = '0';
        if (isset($inRow['eway_trans_distance'])) {
            $dbDistance = trim((string) $inRow['eway_trans_distance']);
            if ($dbDistance !== '' && is_numeric($dbDistance) && (float) $dbDistance >= 0 && (float) $dbDistance <= 4000) {
                $transDistance = (string) (int) (float) $dbDistance;
            }
        } elseif (isset($inRow['eway_distance_km']) && $inRow['eway_distance_km'] !== null && (string) $inRow['eway_distance_km'] !== '') {
            $dbDistance = trim((string) $inRow['eway_distance_km']);
            if ($dbDistance !== '' && is_numeric($dbDistance) && (float) $dbDistance >= 0 && (float) $dbDistance <= 4000) {
                $transDistance = (string) (int) (float) $dbDistance;
            }
        }
        if ($transDistance === '' || ! is_numeric($transDistance) || (float) $transDistance < 0 || (float) $transDistance > 4000) {
            $transDistance = '0';
        }

        return $transDistance;
    }
}

if (!function_exists('ewaybill_sale_item_row_hsn')) {
    function ewaybill_sale_item_row_hsn(mysqli $conn, array $row): string
    {
        $pid = (int) ($row['product_id'] ?? 0);
        if ($pid > 0) {
            $pc = @getRecord('SELECT hsn FROM tbl_product_characteristics WHERE product_id = ' . $pid . ' AND status = 1 ORDER BY id ASC LIMIT 1');
            if ($pc && !empty($pc['hsn'])) {
                return preg_replace('/[^0-9]/', '', (string) $pc['hsn']);
            }
        }

        return '';
    }
}

if (!function_exists('ewaybill_is_valid_gstin')) {
    /**
     * Structural GSTIN validation (15 chars, PAN-style body + entity + Z + checksum).
     *
     * @param string $gstin Raw GSTIN (spaces tolerated)
     */
    function ewaybill_is_valid_gstin($gstin): bool
    {
        $gstin = strtoupper(trim((string) $gstin));
        $gstin = preg_replace('/\s+/', '', $gstin);
        if (strlen($gstin) !== 15) {
            return false;
        }

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{1}Z[A-Z0-9]{1}$/', $gstin);
    }
}

if (!function_exists('ewaybill_is_acceptable_eway_api_gstin')) {
    /**
     * Seller / e-Way API header GSTIN: NIC-valid OR 15-char GSP & sandbox shapes (e.g. WhiteBooks …Q000).
     * In sandbox mode, buyer GSTIN may also use this relaxed shape (see ewaybill_validate_nic_genewaybill_payload).
     */
    function ewaybill_is_acceptable_eway_api_gstin($gstin): bool
    {
        if (ewaybill_is_valid_gstin($gstin)) {
            return true;
        }
        $g = strtoupper(trim((string) $gstin));
        $g = preg_replace('/\s+/', '', $g);
        if (strlen($g) !== 15) {
            return false;
        }

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{3}$/', $g);
    }
}

if (!function_exists('ewaybill_is_valid_gstin_format')) {
    /** @deprecated Use ewaybill_is_valid_gstin() — kept for existing call sites */
    function ewaybill_is_valid_gstin_format(string $g): bool
    {
        return ewaybill_is_valid_gstin($g);
    }
}

if (!function_exists('ewaybill_expected_gst_state_from_pin6')) {
    /**
     * Rough PIN (first 3 digits) → GST state code for consistency checks (returns null if unknown).
     */
    function ewaybill_expected_gst_state_from_pin6(string $pin): ?int
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            return null;
        }
        $t = (int) substr($pin, 0, 3);
        if ($t >= 560 && $t <= 591) {
            return 29;
        }
        if ($t >= 400 && $t <= 445) {
            return 27;
        }
        if ($t >= 360 && $t <= 396) {
            return 24;
        }
        if ($t >= 600 && $t <= 643) {
            return 33;
        }
        if ($t >= 670 && $t <= 695) {
            return 32;
        }
        if ($t >= 500 && $t <= 509) {
            return 36;
        }
        if ($t >= 510 && $t <= 535) {
            return 37;
        }
        if ($t === 110) {
            return 7;
        }
        if ($t >= 121 && $t <= 136) {
            return 6;
        }
        if ($t >= 140 && $t <= 160) {
            return 3;
        }
        if ($t >= 700 && $t <= 743) {
            return 19;
        }
        if ($t >= 201 && $t <= 285) {
            return 9;
        }
        if ($t >= 302 && $t <= 345) {
            return 8;
        }

        return null;
    }
}

if (!function_exists('ewaybill_default_pin6_for_gst_state_code')) {
    /**
     * Representative PIN for GST state code (NIC PIN↔state consistency). Used when branch PIN is missing or wrong-state.
     *
     * @see https://docs.ewaybillgst.gov.in/
     */
    function ewaybill_default_pin6_for_gst_state_code(int $stateCode): string
    {
        $map = [
            1 => '180001', 2 => '171001', 3 => '141001', 4 => '160017', 5 => '248001',
            6 => '121001', 7 => '110001', 8 => '302001', 9 => '226001', 10 => '800001',
            11 => '737101', 12 => '791001', 13 => '797001', 14 => '795001', 15 => '796001',
            16 => '799001', 17 => '793001', 18 => '781001', 19 => '700001', 20 => '834001',
            21 => '751001', 22 => '492001', 23 => '452001', 24 => '380001', 25 => '396210',
            26 => '396230', 27 => '400001', 28 => '500001', 29 => '560001', 30 => '403001',
            31 => '682555', 32 => '695001', 33 => '600001', 34 => '605001', 35 => '744101',
            36 => '500001', 37 => '520001', 38 => '194101',
        ];

        return $map[$stateCode] ?? '';
    }
}

if (!function_exists('ewaybill_vehicle_matches_indian_pattern')) {
    /** After normalizing (no spaces/hyphens): typical Indian registration pattern. */
    function ewaybill_vehicle_matches_indian_pattern(string $v): bool
    {
        return $v !== '' && (bool) preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $v);
    }
}

if (!function_exists('ewaybill_finalize_vehicle_no_for_payload')) {
    /**
     * Normalize / default vehicleNo after NIC payload fields are assembled (before GSTIN/pin checks).
     * Sandbox empty → MH31AB1234; production empty → error; force sandbox sample → MH31AB1234 + road defaults.
     *
     * @param array<string, mixed> $payload
     */
    function ewaybill_finalize_vehicle_no_for_payload(array &$payload, bool $isSandbox, bool $forceSandboxSample): ?string
    {
        $tm = (string) ($payload['transMode'] ?? '1');
        if ($forceSandboxSample && $isSandbox) {
            $payload['transMode']    = '1';
            $payload['vehicleType']  = 'R';
            $payload['vehicleNo'] = 'MH31AB1234';

            return null;
        }
        $vehicleNo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($payload['vehicleNo'] ?? '')));
        if ($vehicleNo === '' && $isSandbox) {
            $vehicleNo = 'MH31AB1234';
        }
        if ($vehicleNo === '' && !$isSandbox && $tm === '1') {
            return 'Vehicle number is required. Example: MH31AB1234';
        }
        $payload['vehicleNo'] = $vehicleNo;
        if ($tm === '1' && !preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $vehicleNo)) {
            return 'Invalid vehicle number. Example: MH31AB1234';
        }

        return null;
    }
}

if (!function_exists('ewaybill_validate_nic_genewaybill_payload')) {
    /**
     * Validation on outgoing NIC JSON (strict GSTIN in production; sandbox allows WhiteBooks credential shapes).
     *
     * @param array<string, mixed> $a
     * @param array<string, string>|null $mergedCfg merged config for sandbox detection (null = treat as production)
     */
    function ewaybill_validate_nic_genewaybill_payload(array $a, ?array $mergedCfg = null): ?string
    {
        $isSandbox = is_array($mergedCfg) && ewaybill_is_whitebooks_sandbox_mode($mergedCfg);
        $fg = strtoupper(preg_replace('/\s+/', '', (string) ($a['fromGstin'] ?? '')));
        $tg = strtoupper(preg_replace('/\s+/', '', (string) ($a['toGstin'] ?? '')));
        if (!$isSandbox && !ewaybill_is_valid_gstin($fg)) {
            return 'Invalid seller GSTIN format.';
        }
        if ($isSandbox && !ewaybill_is_acceptable_eway_api_gstin($fg)) {
            return 'Invalid seller GSTIN format.';
        }
        if (!$isSandbox && !ewaybill_is_valid_gstin($tg)) {
            return 'Invalid buyer GSTIN format.';
        }
        if ($isSandbox && !ewaybill_is_acceptable_eway_api_gstin($tg)) {
            return 'Invalid buyer GSTIN format.';
        }
        $fsc = (int) substr($fg, 0, 2);
        $tsc = (int) substr($tg, 0, 2);
        if (!$isSandbox) {
            foreach (
                [
                    'fromStateCode'    => $fsc,
                    'actFromStateCode' => $fsc,
                    'toStateCode'      => $tsc,
                    'actToStateCode'   => $tsc,
                ] as $k => $expect
            ) {
                if (isset($a[$k]) && (int) $a[$k] !== $expect) {
                    return 'GSTIN state code does not match selected state';
                }
            }
        }
        $fpDigits = preg_replace('/\D/', '', (string) ($a['fromPincode'] ?? ''));
        $tpDigits = preg_replace('/\D/', '', (string) ($a['toPincode'] ?? ''));
        if (strlen($fpDigits) !== 6 || strlen($tpDigits) !== 6) {
            return 'From and to pincode must each be a valid 6-digit number.';
        }
        $fp = sprintf('%06d', (int) $fpDigits);
        $tp = sprintf('%06d', (int) $tpDigits);
        if (!$isSandbox) {
            $expF = ewaybill_expected_gst_state_from_pin6($fp);
            if ($expF !== null && $expF !== $fsc) {
                return 'Branch state and pincode mismatch. Please correct branch profile.';
            }
            $expT = ewaybill_expected_gst_state_from_pin6($tp);
            if ($expT !== null && $expT !== $tsc) {
                return 'GSTIN state code does not match selected state';
            }
        }
        /* vehicleNo: use ewaybill_finalize_vehicle_no_for_payload() after assembly; avoid duplicate logic here */
        $tid = isset($a['transporterId']) ? strtoupper(preg_replace('/\s+/', '', (string) $a['transporterId'])) : '';
        if ($tid !== '') {
            if ($isSandbox) {
                if (!ewaybill_is_acceptable_eway_api_gstin($tid)) {
                    return 'Invalid transporter GSTIN format.';
                }
            } elseif (!ewaybill_is_valid_gstin($tid)) {
                return 'Invalid transporter GSTIN format.';
            }
        }
        if ($tid !== '' && isset($a['transporterStateCode'])) {
            $tSt = (int) $a['transporterStateCode'];
            if ($tSt >= 1 && $tSt <= 99 && $tSt !== (int) substr($tid, 0, 2)) {
                return 'Transporter GSTIN state code does not match transporter state';
            }
        }

        return null;
    }
}

if (!function_exists('ewaybill_normalize_eway_taxes_by_place_of_supply')) {
    /**
     * Align header CGST/SGST/IGST with NIC rules using seller vs buyer state (GSTIN first 2 digits).
     * Interstate: all tax in IGST; intrastate: IGST 0, split into CGST/SGST (50/50 if only IGST stored).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    function ewaybill_normalize_eway_taxes_by_place_of_supply(bool $interstate, float $cgst, float $sgst, float $igst): array
    {
        $cgst = round((float) $cgst, 2);
        $sgst = round((float) $sgst, 2);
        $igst = round((float) $igst, 2);
        if ($interstate) {
            return [0.0, 0.0, round($cgst + $sgst + $igst, 2)];
        }
        $tot = round($cgst + $sgst + $igst, 2);
        if ($tot <= 0) {
            return [0.0, 0.0, 0.0];
        }
        if (($cgst + $sgst) > 0.01 && $igst < 0.01) {
            return [$cgst, $sgst, 0.0];
        }
        $half = round($tot / 2, 2);

        return [$half, round($tot - $half, 2), 0.0];
    }
}

if (!function_exists('ewaybill_sanitize_payload_json_for_debug')) {
    function ewaybill_sanitize_payload_json_for_debug(string $json): string
    {
        if ($json === '') {
            return '';
        }
        $a = json_decode($json, true);
        if (!is_array($a)) {
            return '';
        }

        return (string) json_encode(ewaybill_redact_secrets_for_eway_ui($a), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('ewaybill_format_payload_ui_debug')) {
    /**
     * Pretty-print JSON for UI debug (compare with Postman); secrets redacted.
     *
     * @param array<string, mixed>|string $jsonOrArray
     */
    function ewaybill_format_payload_ui_debug($jsonOrArray): string
    {
        if (is_array($jsonOrArray)) {
            $a = $jsonOrArray;
        } else {
            $s = trim((string) $jsonOrArray);
            if ($s === '') {
                return '';
            }
            $a = json_decode($s, true);
            if (!is_array($a)) {
                return $s;
            }
        }
        $san = function_exists('ewaybill_redact_secrets_for_eway_ui') ? ewaybill_redact_secrets_for_eway_ui($a) : $a;

        return (string) json_encode($san, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('ewaybill_whitebooks_sandbox_force_sample_payload_array')) {
    /**
     * Known-good NIC-shaped body for WhiteBooks sandbox testing (doc/totals from invoice).
     *
     * @param array<string, string> $cfg merged e-way config
     *
     * @return array<string, mixed>
     */
    function ewaybill_whitebooks_sandbox_force_sample_payload_array(mysqli $conn, int $invoice_id, array $cfg): array
    {
        $eg = __DIR__ . '/../api/eway_generate.php';
        if (is_file($eg)) {
            require_once $eg;
        }
        $inRow = getRecord('SELECT * FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        if (!is_array($inRow)) {
            $inRow = [];
        }
        $invNo   = str_replace('/', '-', trim((string) ($inRow['invoice_no'] ?? 'INV-' . $invoice_id)));
        $tss     = !empty($inRow['invoice_date']) ? strtotime((string) $inRow['invoice_date']) : time();
        $docDate = date('d/m/Y', $tss ? $tss : time());
        $fromG   = strtoupper(preg_replace('/\s+/', '', (string) ($cfg['gstin'] ?? '')));
        if (strlen($fromG) !== 15 && function_exists('auragold_branch_gstin_for_eway')) {
            $fromG = strtoupper(preg_replace('/\s+/', '', (string) auragold_branch_gstin_for_eway($conn)));
        }
        $bid = (int) ($inRow['branch_id'] ?? 0);
        if ($bid <= 0) {
            $bid = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : (int) ($_SESSION['branch_id'] ?? 0);
        }
        $brRow = null;
        if ($bid > 0) {
            $brRow = function_exists('getRecordMaster') ? @getRecordMaster('SELECT id, name, gst_no, address, location_area, pincode FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1') : null;
            if (!is_array($brRow)) {
                $brRow = getRecord('SELECT id, name, gst_no, address, location_area, pincode FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1');
            }
        }
        $fromName = is_array($brRow) ? trim((string) ($brRow['name'] ?? 'Seller')) : 'Seller';
        $fromAddr = is_array($brRow) ? trim((string) ($brRow['address'] ?? $brRow['location_area'] ?? '')) : 'Seller Address Line 1';
        if ($fromAddr === '') {
            $fromAddr = 'Seller Address Line 1';
        }
        $fromPlace = is_array($brRow) ? trim((string) ($brRow['location_area'] ?? '')) : '';
        if ($fromPlace === '') {
            $fromPlace = $fromName;
        }
        $fsc      = (int) substr($fromG, 0, 2);
        $fromPinD = function_exists('ewaybill_default_pin6_for_gst_state_code') ? ewaybill_default_pin6_for_gst_state_code($fsc) : '560001';
        if (is_array($brRow) && !empty($brRow['pincode'])) {
            $fp = preg_replace('/\D/', '', (string) $brRow['pincode']);
            if (strlen($fp) === 6) {
                $fromPinD = $fp;
            }
        }
        if (function_exists('auragold_branch_registry_pin_digits') && $bid > 0) {
            $f2 = auragold_branch_registry_pin_digits($bid);
            if (strlen($f2) === 6) {
                $fromPinD = $f2;
            }
        }
        /* Fixed NIC-shaped totals for sandbox Postman parity (3% IGST interstate). */
        $taxable = 50000.0;
        $grand   = 51500.0;
        $cg      = 0.0;
        $sg      = 0.0;
        $ig      = 1500.0;
        $toGst = '05AAACH6188F1ZM';
        $tpin  = 263652;
        $fpI   = function_exists('auragold_eway_pin_int') ? auragold_eway_pin_int($fromPinD, 560001) : (int) $fromPinD;
        $line = [
            'productName'    => 'Gold Item',
            'productDesc'    => 'Gold Item',
            'hsnCode'        => 1001,
            'quantity'       => 1.0,
            'qtyUnit'        => 'BOX',
            'taxableAmount'  => round($taxable, 2),
            'cgstRate'       => 0,
            'sgstRate'       => 0,
            'igstRate'       => 3.0,
            'cessRate'       => 0,
        ];

        return [
            'supplyType'        => 'O',
            'subSupplyType'     => '1',
            'docType'           => 'INV',
            'transactionType'   => 1,
            'docNo'             => $invNo,
            'docDate'           => $docDate,
            'fromGstin'         => $fromG,
            'toGstin'           => $toGst,
            'fromStateCode'     => $fsc,
            'toStateCode'       => 5,
            'actFromStateCode'  => $fsc,
            'actToStateCode'    => 5,
            'fromPincode'       => $fpI,
            'toPincode'         => $tpin,
            'fromTrdName'       => $fromName,
            'toTrdName'         => 'Sandbox Test Buyer',
            'fromAddr1'         => $fromAddr,
            'toAddr1'           => 'Beml Nagar',
            'fromPlace'         => $fromPlace,
            'toPlace'           => 'Beml Nagar',
            'vehicleNo'         => 'MH31AB1234',
            'vehicleType'       => 'R',
            'transMode'         => '1',
            'transDistance'     => '0',
            'totalValue'        => round($taxable, 2),
            'cgstValue'         => $cg,
            'sgstValue'         => $sg,
            'igstValue'         => $ig,
            'cessValue'         => 0,
            'cessNonAdvolValue' => 0,
            'totInvValue'       => round($grand, 2),
            'transDocNo'        => $invNo,
            'itemList'          => [$line],
        ];
    }
}

if (!function_exists('ewaybill_apply_seller_gstin_from_config')) {
    /**
     * Force seller GSTIN from merged config (file + tbl_ewaybill_api_settings) before NIC POST.
     * Syncs fromStateCode / actFromStateCode from GSTIN prefix when config gstin is non-empty.
     *
     * @param array<string, mixed> $payload NIC / generateEwayBill payload (mutated)
     */
    function ewaybill_apply_seller_gstin_from_config(?mysqli $conn, array &$payload): void
    {
        if (!$conn instanceof mysqli) {
            $gconn = $GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN'] ?? null;
            $conn = ($gconn instanceof mysqli) ? $gconn : null;
        }
        if (!$conn instanceof mysqli || !function_exists('ewaybill_merged_config')) {
            return;
        }
        $config = ewaybill_merged_config($conn);
        $g = strtoupper(trim((string) ($config['gstin'] ?? '')));
        $g = preg_replace('/\s+/', '', $g);
        if ($g === '' || !ewaybill_is_acceptable_eway_api_gstin($g)) {
            return;
        }
        $payload['fromGstin'] = $g;
        if (isset($payload['from_gstin'])) {
            $payload['from_gstin'] = $g;
        }
        $sc = (int) substr($g, 0, 2);
        if ($sc >= 1 && $sc <= 97) {
            $payload['fromStateCode'] = $sc;
            $payload['actFromStateCode'] = $sc;
        }

        @file_put_contents(__DIR__ . '/gstin_debug.txt', $g . "\n", LOCK_EX);
    }
}

if (!function_exists('ewaybill_persist_final_request_json')) {
    /**
     * Persist exact outgoing JSON before HTTP POST (compare with Postman).
     */
    function ewaybill_persist_final_request_json(mysqli $conn, int $invoiceId, string $payloadJson, array $cred, string $curlUrlRedacted): void
    {
        if ($invoiceId <= 0 || $payloadJson === '') {
            return;
        }
        $esc = mysqli_real_escape_string($conn, $payloadJson);
        @mysqli_query($conn, 'UPDATE tbl_sale_invoices SET eway_request_json = \'' . $esc . '\' WHERE id = ' . (int) $invoiceId . ' LIMIT 1');
        $hdr = [
            'Content-Type'  => 'application/json',
            'ip_address'    => (string) ($cred['ip_address'] ?? ''),
            'client_id'     => (string) ($cred['client_id'] ?? ''),
            'client_secret' => (string) ($cred['client_secret'] ?? ''),
            'gstin'         => '',
        ];
        ewaybill_log_api_request($conn, 'genewaybill', $curlUrlRedacted, $hdr, $payloadJson, '', 0, 'request_sent');
    }
}

if (!function_exists('ewaybill_build_payload_from_invoice')) {
    /**
     * Build WhiteBooks/NIC invoice array for generateEwayBill() — same shape for auto-save and manual generate.
     *
     * @param array<string, mixed> $options force_trans_distance_zero?: bool
     *
     * @return array{ok: bool, message?: string, invoice_no?: string, inv_payload?: array, gurl?: string, log_hdrs?: string, m?: array}
     */
    function ewaybill_build_payload_from_invoice(mysqli $conn, int $invoice_id, array $options = []): array
    {
        $fail = static function (string $msg): array {
            return ['ok' => false, 'message' => $msg];
        };
        if ($invoice_id <= 0) {
            return $fail('Invalid invoice id.');
        }
        $invPath = __DIR__ . '/../api/eway_generate.php';
        if (!is_file($invPath) || !function_exists('getRecord')) {
            return $fail('E-Way Bill dependencies are not available on this server.');
        }
        require_once $invPath;
        $gstPath = __DIR__ . '/auragold-gst.php';
        if (is_file($gstPath)) {
            require_once $gstPath;
        }
        ewaybill_load_file_config();
        $cfg       = ewaybill_merged_config($conn);
        $isSandbox = ewaybill_is_whitebooks_sandbox_mode($cfg);
        $configGst = strtoupper(preg_replace('/\s+/', '', (string) ($cfg['gstin'] ?? '')));
        $branchGst = '';
        if (function_exists('auragold_branch_gstin_for_eway')) {
            $branchGst = strtoupper(preg_replace('/\s+/', '', (string) auragold_branch_gstin_for_eway($conn)));
        }
        /** Prefer merged config seller GSTIN (NIC or GSP/sandbox shape); else valid branch gst_no. */
        if ($configGst !== '' && ($isSandbox ? ewaybill_is_acceptable_eway_api_gstin($configGst) : ewaybill_is_valid_gstin($configGst))) {
            $fromG = $configGst;
        } elseif ($branchGst !== '' && ($isSandbox ? ewaybill_is_acceptable_eway_api_gstin($branchGst) : ewaybill_is_valid_gstin($branchGst))) {
            $fromG = $branchGst;
        } else {
            $fromG = '';
        }
        if (trim((string) ($cfg['client_id'] ?? '')) === '' || trim((string) ($cfg['client_secret'] ?? '')) === '' || trim((string) ($cfg['email'] ?? '')) === '') {
            return $fail('E-Way Bill API settings: email, client_id and client_secret are required (Set Software → e-Way Bill API).');
        }
        if ($fromG === '' || ($isSandbox ? !ewaybill_is_acceptable_eway_api_gstin($fromG) : !ewaybill_is_valid_gstin($fromG))) {
            return $fail('Seller GSTIN is invalid or missing: enter the 15-character GSTIN from WhiteBooks for this credential set (sandbox may use formats like …Q000) in Set Software → e-Way Bill API, or set a NIC-valid gst_no on the branch.');
        }
        $inRow = getRecord('SELECT * FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
        if (!is_array($inRow) || empty($inRow['id'])) {
            return $fail('Sale invoice not found.');
        }
        $items = getList('SELECT * FROM tbl_sale_invoice_items WHERE invoice_id = ' . (int) $invoice_id . ' AND status = 1 ORDER BY id ASC');
        if (!is_array($items) || count($items) === 0) {
            return $fail('Invoice has no line items.');
        }
        foreach ($items as $it) {
            if (strlen(ewaybill_sale_item_row_hsn($conn, $it)) < 4) {
                $pn = trim((string) ($it['product_name'] ?? 'item'));

                return $fail('HSN is required for every line item (product HSN/characteristic): ' . $pn);
            }
        }
        $cid  = (int) ($inRow['customer_id'] ?? 0);
        $cust = $cid > 0 ? getRecord('SELECT * FROM tbl_customers WHERE id = ' . $cid . ' LIMIT 1') : null;
        if (!is_array($cust)) {
            return $fail('Link a customer to this invoice for e-Way Bill.');
        }
        $toGst = strtoupper(preg_replace('/\s+/', '', (string) ($inRow['customer_gstin'] ?? $cust['gstin'] ?? '')));
        $sandboxBuyerFallback = false;
        if (!$isSandbox) {
            if ($toGst === '' || strlen($toGst) !== 15) {
                return $fail('Customer GSTIN must be 15 characters for e-Way Bill.');
            }
            if (!ewaybill_is_valid_gstin($toGst)) {
                return $fail('Customer GSTIN must be 15 characters for e-Way Bill.');
            }
        } elseif ($toGst === '' || strlen($toGst) !== 15 || !ewaybill_is_valid_gstin($toGst)) {
            $sandboxBuyerFallback = true;
            $toGst                  = '05AAACH6188F1ZM';
        }
        if ($toGst === $fromG) {
            return $fail('Customer GSTIN must be different from seller GSTIN.');
        }
        $pin = '';
        if (!$sandboxBuyerFallback) {
            if (function_exists('auragold_customer_billing_pin_digits')) {
                $pin = auragold_customer_billing_pin_digits($conn, $cid);
            }
            if (strlen($pin) !== 6 && is_array($inRow)) {
                $invP = preg_replace('/\D/', '', (string) ($inRow['eway_to_pincode'] ?? ''));
                if (strlen($invP) === 6) {
                    $pin = $invP;
                }
            }
            if (strlen($pin) !== 6) {
                $pin = preg_replace('/\D/', '', (string) ($cust['billing_pincode'] ?? $cust['billing_zip_code'] ?? $cust['pincode'] ?? $cust['zip'] ?? ''));
            }
            if (strlen($pin) !== 6) {
                return $fail('Customer billing pincode (6 digits) is required for e-Way Bill. Set PIN in the customer profile (all billing/zip/pin fields) or enter "Customer billing PIN" on this invoice, then save again.');
            }
        } else {
            $pin = '263652';
        }
        $billState = trim((string) ($cust['billing_state'] ?? $cust['state'] ?? ''));
        if ($billState === '' && !empty($cust['billing_state_id']) && (int) $cust['billing_state_id'] > 0) {
            $sr = @getRecord('SELECT name FROM tbl_states WHERE id = ' . (int) $cust['billing_state_id'] . ' LIMIT 1');
            if ($sr && !empty($sr['name'])) {
                $billState = (string) $sr['name'];
            }
        }
        if (trim($billState) === '' && !$sandboxBuyerFallback) {
            return $fail('Customer billing state is required for e-Way Bill.');
        }
        $transMode = trim((string) ($inRow['eway_trans_mode'] ?? '1')) ?: '1';
        $vraw      = (string) ($inRow['eway_vehicle_no'] ?? '');
        if (function_exists('auragold_eway_normalize_vehicle_no')) {
            $vraw = auragold_eway_normalize_vehicle_no($vraw);
        } else {
            $vraw = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vraw));
        }
        if ($transMode === '1' && $vraw === '' && $isSandbox) {
            $ev = getenv('AURAGOLD_EWAY_DEFAULT_VEHICLE');
            $fallback = ($ev !== false && trim((string) $ev) !== '') ? trim((string) $ev) : 'MH31AB1234';
            if (function_exists('auragold_eway_normalize_vehicle_no')) {
                $vraw = auragold_eway_normalize_vehicle_no($fallback);
            } else {
                $vraw = strtoupper(preg_replace('/[^A-Z0-9]/', '', $fallback));
            }
        }
        $invNo = (string) ($inRow['invoice_no'] ?? '');
        $tss   = !empty($inRow['invoice_date']) ? strtotime((string) $inRow['invoice_date']) : time();
        $docDate = date('d/m/Y', $tss ? $tss : time());
        $bid  = (int) ($inRow['branch_id'] ?? 0);
        if ($bid <= 0) {
            $bid = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : (int) ($_SESSION['branch_id'] ?? 0);
        }
        $brRow = null;
        if ($bid > 0) {
            $brRow = function_exists('getRecordMaster') ? @getRecordMaster('SELECT id, name, gst_no, address, location_area, pincode FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1') : null;
            if (!is_array($brRow)) {
                $brRow = getRecord('SELECT id, name, gst_no, address, location_area, pincode FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1');
            }
        }
        $fromName = is_array($brRow) ? trim((string) ($brRow['name'] ?? 'Seller')) : 'Seller';
        $fromAddr = is_array($brRow) ? trim((string) ($brRow['address'] ?? $brRow['location_area'] ?? '')) : '';
        if ($fromAddr === '') {
            $fromAddr = $fromName !== '' ? ($fromName . ', principal place') : 'Principal place of business';
        }
        $fromPlaceStr = is_array($brRow) ? trim((string) ($brRow['location_area'] ?? '')) : '';
        if ($fromPlaceStr === '') {
            $fromPlaceStr = $fromName;
        }
        $sellerScPin = (int) substr($fromG, 0, 2);
        $fromPinD = ewaybill_default_pin6_for_gst_state_code($sellerScPin);
        if ($fromPinD === '') {
            $fromPinD = '400001';
        }
        if (is_array($brRow) && !empty($brRow['pincode'])) {
            $f = preg_replace('/\D/', '', (string) $brRow['pincode']);
            if (strlen($f) === 6) {
                $fromPinD = $f;
            }
        }
        if (function_exists('auragold_branch_registry_pin_digits') && $bid > 0) {
            $f2 = auragold_branch_registry_pin_digits($bid);
            if (strlen($f2) === 6) {
                $fromPinD = $f2;
            }
        }
        $pinMapsToState = ewaybill_expected_gst_state_from_pin6((string) $fromPinD);
        if ($pinMapsToState !== null && $pinMapsToState !== $sellerScPin) {
            $adjPin = ewaybill_default_pin6_for_gst_state_code($sellerScPin);
            if ($adjPin !== '') {
                $fromPinD = $adjPin;
            }
        }
        if (! preg_match('/^\d{6}$/', (string) $fromPinD) || ! preg_match('/^\d{6}$/', (string) $pin)) {
            return $fail('Invalid e-Way Bill PIN code. From PIN and Customer Billing PIN must be 6 digits.');
        }
        $fsc = (int) substr($fromG, 0, 2);
        $tsc = (int) substr($toGst, 0, 2);
        /** @var bool $interstate Same as fromStateCode !== toStateCode (GSTIN). */
        $interstate = ($fsc !== $tsc);
        $lineRows = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lineRows[] = [
                'product_id'   => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'net_amount'   => (float) ($row['net_amount'] ?? 0),
                'amount'       => (float) ($row['amount'] ?? 0),
                'tax'          => (float) ($row['tax_amount'] ?? 0),
                'tax_amount'   => (float) ($row['tax_amount'] ?? 0),
                'quantity'     => (float) ($row['quantity'] ?? 1),
                'net_weight'   => (float) ($row['net_weight'] ?? 0),
            ];
        }
        $ew_gt   = (float) ($inRow['grand_total'] ?? 0);
        $ew_cgst = (float) ($inRow['gst_cgst_amount'] ?? 0);
        $ew_sgst = (float) ($inRow['gst_sgst_amount'] ?? 0);
        $ew_igst = (float) ($inRow['gst_igst_amount'] ?? 0);
        [$ew_cgst, $ew_sgst, $ew_igst] = ewaybill_normalize_eway_taxes_by_place_of_supply($interstate, $ew_cgst, $ew_sgst, $ew_igst);
        $itemList = function_exists('auragold_eway_build_item_list_from_lines')
            ? auragold_eway_build_item_list_from_lines($conn, $lineRows, $interstate, $isSandbox)
            : [];
        if (count($itemList) < 1) {
            return $fail('Could not build e-Way line items. Check amounts and HSN on products.');
        }
        $ew_tx = round(max(0, $ew_gt - $ew_cgst - $ew_sgst - $ew_igst), 2);
        $sumL  = 0.0;
        foreach ($itemList as $il) {
            $sumL += (float) ($il['taxableAmount'] ?? 0);
        }
        if ($sumL > 0) {
            $ew_tx = round($sumL, 2);
        }
        $toName  = trim((string) ($cust['name'] ?? $inRow['customer_name'] ?? 'Buyer'));
        $toAddr  = trim((string) ($cust['billing_address'] ?? $cust['address'] ?? ''));
        $toPlace = trim((string) ($cust['city'] ?? $cust['billing_city'] ?? ''));
        if ($sandboxBuyerFallback) {
            $toPlace = 'Beml Nagar';
            if ($toAddr === '') {
                $toAddr = 'Beml Nagar';
            }
            if ($toName === '') {
                $toName = 'Sandbox Test Buyer';
            }
        }
        if ($toAddr === '') {
            $toAddr = $toPlace !== '' ? $toPlace : 'Buyer billing address';
        }
        $vtype   = strtoupper((string) ($inRow['eway_vehicle_type'] ?? 'R')) === 'O' ? 'O' : 'R';
        $tno     = trim((string) ($inRow['eway_trans_doc_no'] ?? '')) ?: $invNo;
        $tddOut  = '';
        $tddRaw  = trim((string) ($inRow['eway_trans_doc_date'] ?? ''));
        if ($tddRaw !== '' && $tddRaw !== '0000-00-00') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $tddRaw)) {
                $tx = strtotime($tddRaw);
                $tddOut = $tx ? date('d/m/Y', $tx) : '';
            } else {
                $tddOut = $tddRaw;
            }
        }
        $fpI = function_exists('auragold_eway_pin_int') ? auragold_eway_pin_int($fromPinD, 400001) : (int) $fromPinD;
        $tpI = function_exists('auragold_eway_pin_int') ? auragold_eway_pin_int($pin, 400001) : (int) $pin;
        $forceZero = !empty($options['force_trans_distance_zero']);
        $transDistance = $isSandbox ? '0' : ewaybill_resolve_trans_distance_string($inRow, $forceZero);
        $rawX = [
            'actFromStateCode' => $fsc,
            'actToStateCode'   => $tsc,
            'fromStateCode'    => $fsc,
            'toStateCode'      => $tsc,
            'fromPincode'      => $fpI,
            'toPincode'        => $tpI,
            'fromTrdName'      => $fromName,
            'toTrdName'        => $toName,
            'fromAddr1'        => $fromAddr,
            'toAddr1'          => $toAddr,
            'toPlace'          => $toPlace,
            'fromPlace'        => $fromPlaceStr,
            'transMode'        => (string) $transMode,
            'transDistance'    => $transDistance,
            'transDocNo'       => $tno,
            'vehicleType'      => $vtype,
            'transactionType'  => 1,
        ];
        /* Only NIC field transDistance is sent; duplicate "distance" breaks some validators. */
        $tnameRaw = trim((string) ($inRow['eway_transporter_name'] ?? ''));
        if ($tnameRaw !== '') {
            $rawX['transporterName'] = $tnameRaw;
        }
        $tidRaw = strtoupper(preg_replace('/\s+/', '', (string) ($inRow['eway_transporter_id'] ?? '')));
        if ($tidRaw !== '') {
            $rawX['transporterId'] = $tidRaw;
        }
        if ($tddOut !== '') {
            $rawX['transDocDate'] = $tddOut;
        }

        $probe           = array_merge(
            [
                'fromGstin' => $fromG,
                'toGstin'   => $toGst,
                'vehicleNo' => $vraw,
            ],
            $rawX
        );
        $sandboxForceSample = !empty($options['sandbox_force_sample']);
        $vehErr            = ewaybill_finalize_vehicle_no_for_payload($probe, $isSandbox, $sandboxForceSample);
        if ($vehErr !== null) {
            return $fail($vehErr);
        }
        $preApiErr = ewaybill_validate_nic_genewaybill_payload($probe, $cfg);
        if ($preApiErr !== null) {
            return $fail($preApiErr);
        }
        $rawX['transMode']   = (string) ($probe['transMode'] ?? $rawX['transMode']);
        $rawX['vehicleType'] = ((string) ($probe['vehicleType'] ?? $rawX['vehicleType'] ?? 'R')) === 'O' ? 'O' : 'R';
        $transMode           = $rawX['transMode'];
        $vtype               = $rawX['vehicleType'];
        $vraw                = (string) ($probe['vehicleNo'] ?? $vraw);

        $invPayload = [
            'invoice_no'     => $invNo,
            'invoice_date'   => $docDate,
            'from_gstin'     => $fromG,
            'to_gstin'       => $toGst,
            'vehicle_no'     => $vraw,
            'vehicle_type'   => $vtype,
            'total_amount'   => $ew_gt,
            'taxable_value'  => $ew_tx,
            'cgst_value'     => $ew_cgst,
            'sgst_value'     => $ew_sgst,
            'igst_value'     => $ew_igst,
            'interstate'     => $interstate,
            'transMode'      => (string) $transMode,
            'from_pincode'   => $fromPinD,
            'to_pincode'     => $pin,
            'itemList'       => $itemList,
            'raw_extra'      => $rawX,
            '_eway_invoice_id' => (int) $invoice_id,
        ];
        if ($tddOut !== '') {
            $invPayload['trans_doc_date'] = $tddOut;
        }
        $m = $cfg;
        $genTry = ewaybill_try_whitebooks_genewaybill_post_url($m);
        if (empty($genTry['ok'])) {
            return $fail((string) ($genTry['message'] ?? 'Could not build Generate E-Way Bill URL.'));
        }
        $gurl = (string) ($genTry['url'] ?? '');
        $gurlErr = ewaybill_validate_whitebooks_genewaybill_url($gurl);
        if ($gurlErr !== null) {
            return $fail($gurlErr);
        }
        $logHdrs = ewaybill_redact_headers_for_log(
            [
                'Content-Type'  => 'application/json',
                'ip_address'    => ewaybill_effective_ip_address_header($m),
                'client_id'     => $m['client_id'] ?? '',
                'client_secret' => (string) ($m['client_secret'] ?? ''),
                'gstin'         => $fromG,
            ]
        );

        return [
            'ok'          => true,
            'invoice_no'  => $invNo,
            'inv_payload' => $invPayload,
            'gurl'        => $gurl,
            'log_hdrs'    => $logHdrs,
            'm'           => $m,
        ];
    }
}

if (!function_exists('ewaybill_generate_from_sale_invoice')) {
    /**
     * @return array{ok: bool, status: string, message: string, ewayBillNo?: string, validUpto?: string, eway_bill: array}
     */
    function ewaybill_generate_from_sale_invoice(mysqli $conn, int $invoice_id): array
    {
        $baseFail = static function (string $msg) {
            return [
                'ok'        => false,
                'status'    => 'error',
                'message'   => $msg,
                'eway_bill' => [
                    'status'     => 'error',
                    'ewayBillNo' => '',
                    'ewayBillDate' => '',
                    'validUpto'  => '',
                    'message'    => $msg,
                ],
            ];
        };
        if ($invoice_id <= 0) {
            return $baseFail('Invalid invoice id.');
        }
        ewaybill_ensure_tables($conn);
        ewaybill_ensure_eway_bill_migrations($conn);
        unset($GLOBALS['AURAGOLD_EWAY_LAST_GENERATE_URL'], $GLOBALS['AURAGOLD_EWAY_LAST_GET_URL']);
        $invPath = __DIR__ . '/../api/eway_generate.php';
        if (!is_file($invPath) || !function_exists('getRecord')) {
            return $baseFail('E-Way Bill dependencies are not available on this server.');
        }
        require_once $invPath;
        $gstPath = __DIR__ . '/auragold-gst.php';
        if (is_file($gstPath)) {
            require_once $gstPath;
        }
        $GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN'] = $conn;

        $forceSample = isset($_POST['eway_sandbox_force_sample_payload']) && (string) $_POST['eway_sandbox_force_sample_payload'] === '1';
        $testRaw     = isset($_POST['eway_test_payload_json']) ? trim((string) $_POST['eway_test_payload_json']) : '';
        if ($forceSample) {
            ewaybill_load_file_config();
            $cfgFs = ewaybill_merged_config($conn);
            if (!ewaybill_is_whitebooks_sandbox_mode($cfgFs)) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return $baseFail('eway_sandbox_force_sample_payload applies only when e-Way base URL is WhiteBooks sandbox.');
            }
            $sampleArr = ewaybill_whitebooks_sandbox_force_sample_payload_array($conn, $invoice_id, $cfgFs);
            $testRaw   = (string) json_encode($sampleArr, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if ($testRaw !== '') {
            json_decode($testRaw);
            if (json_last_error() !== JSON_ERROR_NONE) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return $baseFail('eway_test_payload_json must be valid JSON.');
            }
            ewaybill_load_file_config();
            $cfgT = ewaybill_merged_config($conn);
            $fromGT = strtoupper(preg_replace('/\s+/', '', (string) ($cfgT['gstin'] ?? '')));
            if (function_exists('auragold_branch_gstin_for_eway')) {
                $fb = strtoupper(preg_replace('/\s+/', '', (string) auragold_branch_gstin_for_eway($conn)));
                if (strlen($fromGT) !== 15) {
                    $fromGT = $fb;
                }
            }
            if (trim((string) ($cfgT['client_id'] ?? '')) === '' || trim((string) ($cfgT['client_secret'] ?? '')) === '' || trim((string) ($cfgT['email'] ?? '')) === '') {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return $baseFail('E-Way Bill API settings required for test override.');
            }
            $genTryT = ewaybill_try_whitebooks_genewaybill_post_url($cfgT);
            if (empty($genTryT['ok'])) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return $baseFail((string) ($genTryT['message'] ?? 'Could not build Generate E-Way Bill URL.'));
            }
            $gurlT = (string) ($genTryT['url'] ?? '');
            $emT   = (string) ($cfgT['email'] ?? '');
            $gurlTErr = ewaybill_validate_whitebooks_genewaybill_url($gurlT);
            if ($gurlTErr !== null) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return $baseFail($gurlTErr);
            }
            $tdec  = json_decode($testRaw, true);
            if (!is_array($tdec)) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return $baseFail('eway_test_payload_json must decode to a JSON object.');
            }
            if (function_exists('auragold_eway_finalize_eway_payload_array')) {
                $tdec = auragold_eway_finalize_eway_payload_array($tdec);
            }
            $isSbT = ewaybill_is_whitebooks_sandbox_mode($cfgT);
            $vehOv = ewaybill_finalize_vehicle_no_for_payload($tdec, $isSbT, $forceSample);
            if ($vehOv !== null) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);
                $preJson = (string) json_encode($tdec, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

                return array_merge($baseFail($vehOv), [
                    'eway_debug_payload'        => ewaybill_format_payload_ui_debug($preJson),
                    'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($preJson),
                    'eway_debug_message'        => 'Vehicle validation after payload assembly.',
                ]);
            }
            $preOv = ewaybill_validate_nic_genewaybill_payload($tdec, $cfgT);
            if ($preOv !== null) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);
                $preJson = (string) json_encode($tdec, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

                return array_merge($baseFail($preOv), [
                    'eway_debug_payload'        => ewaybill_format_payload_ui_debug($preJson),
                    'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($preJson),
                    'eway_debug_message'        => 'Validation failed before API call; payload is finalized JSON (same shape as POST body).',
                ]);
            }
            $testRaw = (string) json_encode($tdec, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $invPayloadT = [
                '_eway_invoice_id'         => (int) $invoice_id,
                '_eway_json_body_override' => $testRaw,
            ];
            $GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED'] = [
                'email'         => $emT,
                'client_id'     => (string) ($cfgT['client_id'] ?? ''),
                'client_secret' => (string) ($cfgT['client_secret'] ?? ''),
                'ip_address'    => ewaybill_effective_ip_address_header($cfgT),
                'username'      => (string) ($cfgT['username'] ?? ''),
                'password'      => (string) ($cfgT['password'] ?? ''),
                'generate_url'  => $gurlT,
            ];
            $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN'] = '';
            if (function_exists('auragold_eway_ensure_auth_token_for_generate')) {
                $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN'] = auragold_eway_ensure_auth_token_for_generate($conn, $fromGT);
            }
            $invNoT = (string) (getRecord('SELECT invoice_no FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1')['invoice_no'] ?? '');
            $out    = function_exists('generateEwayBill') ? generateEwayBill($invPayloadT) : null;
            unset($GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED'], $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN']);
            $finalGenUrlLogged = trim((string) ($GLOBALS['AURAGOLD_EWAY_LAST_GENERATE_URL'] ?? ''));
            if ($finalGenUrlLogged === '') {
                $finalGenUrlLogged = $gurlT;
            }
            $logBody = (isset($GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']) && is_string($GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']))
                ? (string) $GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']
                : $testRaw;
            unset($GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']);
            $logHdrsT = ewaybill_redact_headers_for_log(
                [
                    'Content-Type'  => 'application/json',
                    'ip_address'    => ewaybill_effective_ip_address_header($cfgT),
                    'client_id'     => $cfgT['client_id'] ?? '',
                    'client_secret' => (string) ($cfgT['client_secret'] ?? ''),
                    'gstin'         => $fromGT,
                ]
            );
            if (!is_array($out)) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return array_merge($baseFail('E-Way Bill call did not return a result.'), [
                    'eway_debug_payload'       => ewaybill_sanitize_payload_json_for_debug($logBody),
                    'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($logBody),
                    'eway_debug_message'       => 'Compare this payload with Postman body.',
                ]);
            }
            $errMsg = (string) ($out['error'] ?? '');
            $stCd   = '';
            if (isset($out['raw']) && is_array($out['raw']) && array_key_exists('status_cd', $out['raw'])) {
                $stCd = (string) $out['raw']['status_cd'];
            }
            $rawLogJson = json_encode(
                ewaybill_redact_secrets_for_eway_ui(is_array($out['raw'] ?? null) ? $out['raw'] : []),
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            ewaybill_log_generate(
                $conn,
                (int) $invoice_id,
                $invNoT,
                $finalGenUrlLogged,
                $logHdrsT,
                $logBody,
                (string) $rawLogJson,
                200,
                $stCd,
                (string) ($out['message'] ?? $errMsg)
            );
            if (function_exists('ewaybill_log_genewaybill_api_mirror')) {
                ewaybill_log_genewaybill_api_mirror(
                    $conn,
                    $finalGenUrlLogged,
                    $logHdrsT,
                    $logBody,
                    (string) $rawLogJson,
                    200,
                    ($stCd !== '' ? 'status_cd_' . $stCd : 'genewaybill_ok')
                );
            }

            if (!empty($out['status']) && $out['status'] === true) {
                $msgNoEwb = 'API success but no E-Way Bill number returned. Check endpoint URL in ewaybill_config.php.';
                $genBlob  = isset($out['raw']) && is_array($out['raw']) ? $out['raw'] : [];
                $ewNo     = trim((string) ($out['eway_bill_no'] ?? ''));
                if ($ewNo === '' && is_array($genBlob)) {
                    $ewNo = ewaybill_extract_eway_no_from_api_response($genBlob);
                }
                $partial = !empty($out['success_without_eway_no']);
                if ($ewNo !== '') {
                    $partial = false;
                }
                if ($ewNo === '' && $partial && function_exists('ewaybill_whitebooks_fetch_ewaybill_by_sale_document')) {
                    $fetchRes = ewaybill_whitebooks_fetch_ewaybill_by_sale_document($conn, $cfgT, $fromGT, (int) $invoice_id, $invNoT);
                    if (!empty($fetchRes['ok']) && trim((string) ($fetchRes['eway_no'] ?? '')) !== '') {
                        $ewNo                  = trim((string) $fetchRes['eway_no']);
                        $out['eway_bill_date'] = (string) ($fetchRes['bill_date'] ?? '');
                        $out['valid_upto']     = (string) ($fetchRes['valid_upto'] ?? '');
                        $partial               = false;
                        $genBlob               = [
                            'generateResponse'      => isset($out['raw']) && is_array($out['raw']) ? $out['raw'] : [],
                            'getewaybillByDocument' => $fetchRes['raw'],
                        ];
                    } else {
                        $genBlob = [
                            'generateResponse'       => isset($out['raw']) && is_array($out['raw']) ? $out['raw'] : [],
                            'getewaybillByDocument'  => $fetchRes['raw'] ?? null,
                            'getewaybill_fetch_note' => (string) ($fetchRes['err'] ?? ''),
                        ];
                    }
                }
                $blobOk = is_array($genBlob) ? $genBlob : [];
                $jfull  = json_encode(
                    ewaybill_redact_secrets_for_eway_ui(is_array($blobOk) ? $blobOk : []),
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );
                $e4 = mysqli_real_escape_string($conn, (string) $jfull);
                if ($ewNo !== '') {
                    $ewD = (string) ($out['eway_bill_date'] ?? '');
                    $vu  = (string) ($out['valid_upto'] ?? '');
                    $dateSrc = $blobOk;
                    if (isset($blobOk['generateResponse']) && is_array($blobOk['generateResponse'])) {
                        $dateSrc = $blobOk['generateResponse'];
                    }
                    if ($ewD === '' || $vu === '') {
                        [$bdx, $vux] = ewaybill_extract_eway_dates_from_api_response(is_array($dateSrc) ? $dateSrc : []);
                        if ($ewD === '') {
                            $ewD = $bdx;
                        }
                        if ($vu === '') {
                            $vu = $vux;
                        }
                    }
                    $e1 = mysqli_real_escape_string($conn, $ewNo);
                    $e2 = mysqli_real_escape_string($conn, $ewD);
                    $e3 = mysqli_real_escape_string($conn, $vu);
                    @mysqli_query(
                        $conn,
                        "UPDATE tbl_sale_invoices SET eway_bill_no = '{$e1}', eway_bill_date = '{$e2}', eway_valid_upto = '{$e3}', eway_status = 'generated', eway_response = '{$e4}', eway_generated_at = NOW() WHERE id = " . (int) $invoice_id
                    );
                    $mOk = 'E-Way Bill generated successfully.';
                    unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                    return [
                        'ok'                        => true,
                        'status'                    => 'success',
                        'eway_db_status'            => 'generated',
                        'message'                   => $mOk,
                        'ewayBillNo'                => $ewNo,
                        'validUpto'                 => $vu,
                        'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($logBody),
                        'eway_bill'                 => [
                            'status'       => 'success',
                            'ewayBillNo'   => $ewNo,
                            'ewayBillDate' => $ewD,
                            'validUpto'    => $vu,
                            'message'      => $mOk,
                        ],
                    ];
                }
                if ($partial) {
                    $msgSandbox = 'Sandbox API returned success but no e-Way Bill number. This is sandbox response behavior.';
                    if (ewaybill_is_whitebooks_sandbox_mode($cfgT)) {
                        @mysqli_query(
                            $conn,
                            "UPDATE tbl_sale_invoices SET eway_status = 'sandbox_success_no_eway_number', eway_response = '{$e4}', eway_generated_at = NOW() WHERE id = " . (int) $invoice_id
                        );
                        unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                        return [
                            'ok'                        => true,
                            'status'                    => 'success',
                            'eway_db_status'            => 'sandbox_success_no_eway_number',
                            'message'                   => $msgSandbox,
                            'ewayBillNo'                => '',
                            'validUpto'                 => '',
                            'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($logBody),
                            'eway_bill'                 => [
                                'status'       => 'success',
                                'ewayBillNo'   => '',
                                'ewayBillDate' => '',
                                'validUpto'    => '',
                                'message'      => $msgSandbox,
                            ],
                        ];
                    }
                    @mysqli_query(
                        $conn,
                        "UPDATE tbl_sale_invoices SET eway_status = 'failed', eway_response = '{$e4}' WHERE id = " . (int) $invoice_id
                    );
                    unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                    return [
                        'ok'             => false,
                        'status'         => 'error',
                        'eway_db_status' => 'failed',
                        'message'        => 'The API returned success but no e-Way Bill number (ewayBillNo/EwbNo/ewbNo). Production requires a valid bill number.',
                        'ewayBillNo'     => '',
                        'validUpto'      => '',
                        'eway_bill'      => [
                            'status'       => 'error',
                            'ewayBillNo'   => '',
                            'ewayBillDate' => '',
                            'validUpto'    => '',
                            'message'      => $msgNoEwb,
                        ],
                    ];
                }
            }
            $euser = is_string($out['error'] ?? null) ? (string) $out['error'] : 'e-Way Bill generation failed.';
            $outForStore = is_array($out) ? ewaybill_redact_secrets_for_eway_ui($out) : ['e' => $euser];
            $jfail       = mysqli_real_escape_string(
                $conn,
                (string) json_encode($outForStore, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
            );
            @mysqli_query($conn, "UPDATE tbl_sale_invoices SET eway_status = 'failed', eway_response = '{$jfail}' WHERE id = " . (int) $invoice_id);
            $wrap = 'Invoice was saved, but e-Way Bill was not created: ' . ewaybill_clip_text($euser, 2000);
            unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);
            $finalFpT = '';
            if (is_array($out) && isset($out['final_payload_debug']) && trim((string) $out['final_payload_debug']) !== '') {
                $finalFpT = (string) $out['final_payload_debug'];
            } else {
                $finalFpT = ewaybill_format_payload_ui_debug($logBody);
            }

            return [
                'ok'                  => false,
                'status'              => 'error',
                'message'             => $wrap,
                'eway_debug_payload'       => ewaybill_sanitize_payload_json_for_debug($logBody),
                'final_payload_sent_to_api' => $finalFpT,
                'eway_debug_message'       => 'Compare this payload with Postman body.',
                'eway_bill'           => [
                    'status'       => 'error',
                    'ewayBillNo'   => '',
                    'ewayBillDate' => '',
                    'validUpto'    => '',
                    'message'      => ewaybill_clip_text($euser, 2000),
                ],
            ];
        }

        $out              = null;
        $euser            = 'e-Way Bill generation failed.';
        $lastDebugPayload = '';
        $invNo            = '';
        for ($apiAttempt = 1; $apiAttempt <= 2; $apiAttempt++) {
            if ($apiAttempt > 1) {
                $inR2 = getRecord('SELECT * FROM tbl_sale_invoices WHERE id = ' . (int) $invoice_id . ' LIMIT 1');
                if (is_array($inR2) && !empty($inR2['id'])) {
                    /* distance retry uses refreshed row */
                }
            }
            $built = ewaybill_build_payload_from_invoice($conn, $invoice_id, [
                'force_trans_distance_zero' => $apiAttempt >= 2,
                'sandbox_force_sample'      => $forceSample,
            ]);
            if (empty($built['ok'])) {
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return array_merge($baseFail((string) ($built['message'] ?? 'Could not build e-Way payload.')), [
                    'eway_debug_message' => 'Compare this payload with Postman body.',
                ]);
            }
            $invPayload = $built['inv_payload'];
            $gurl       = (string) $built['gurl'];
            $logHdrs    = (string) $built['log_hdrs'];
            $m          = $built['m'];
            $invNo      = (string) $built['invoice_no'];
            $fromG      = strtoupper(preg_replace('/\s+/', '', (string) ($invPayload['from_gstin'] ?? '')));

            $GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED'] = [
                'email'         => (string) ($m['email'] ?? ''),
                'client_id'     => (string) ($m['client_id'] ?? ''),
                'client_secret' => (string) ($m['client_secret'] ?? ''),
                'ip_address'    => ewaybill_effective_ip_address_header($m),
                'username'      => (string) ($m['username'] ?? ''),
                'password'      => (string) ($m['password'] ?? ''),
                'generate_url'  => $gurl,
            ];
            $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN'] = '';
            if (function_exists('auragold_eway_ensure_auth_token_for_generate')) {
                $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN'] = auragold_eway_ensure_auth_token_for_generate($conn, $fromG);
            }
            $out = function_exists('generateEwayBill') ? generateEwayBill($invPayload) : null;
            unset($GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED'], $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN']);
            $finalGenUrlLogged = trim((string) ($GLOBALS['AURAGOLD_EWAY_LAST_GENERATE_URL'] ?? ''));
            if ($finalGenUrlLogged === '') {
                $finalGenUrlLogged = $gurl;
            }
            $plJson  = (string) json_encode($invPayload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $logBody = (isset($GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']) && is_string($GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']) && $GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON'] !== '')
                ? (string) $GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']
                : $plJson;
            unset($GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON']);
            $lastDebugPayload = $logBody;
            if (is_array($out) && !empty($out['final_payload_debug'])) {
                $lastDebugPayload = (string) $out['final_payload_debug'];
            }
            if (!is_array($out)) {
                ewaybill_log_generate(
                    $conn,
                    (int) $invoice_id,
                    $invNo,
                    $finalGenUrlLogged,
                    $logHdrs,
                    $logBody,
                    '',
                    null,
                    '',
                    'no result'
                );
                if (function_exists('ewaybill_log_genewaybill_api_mirror')) {
                    ewaybill_log_genewaybill_api_mirror($conn, $finalGenUrlLogged, $logHdrs, $logBody, '', null, 'no_result');
                }
                unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                return array_merge($baseFail('E-Way Bill call did not return a result.'), [
                    'eway_debug_payload'        => ewaybill_sanitize_payload_json_for_debug($logBody),
                    'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($logBody),
                    'eway_debug_message'        => 'Compare this payload with Postman body.',
                ]);
            }
            $errMsg = (string) ($out['error'] ?? '');
            $stCd   = '';
            if (isset($out['raw']) && is_array($out['raw']) && array_key_exists('status_cd', $out['raw'])) {
                $stCd = (string) $out['raw']['status_cd'];
            }
            $rawLog = $out['raw'] ?? $out;
            $rawLogJson = json_encode(
                ewaybill_redact_secrets_for_eway_ui(is_array($rawLog) ? $rawLog : []),
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            ewaybill_log_generate(
                $conn,
                (int) $invoice_id,
                $invNo,
                $finalGenUrlLogged,
                $logHdrs,
                $logBody,
                (string) $rawLogJson,
                200,
                $stCd,
                (string) ($out['message'] ?? $errMsg)
            );
            if (function_exists('ewaybill_log_genewaybill_api_mirror')) {
                ewaybill_log_genewaybill_api_mirror(
                    $conn,
                    $finalGenUrlLogged,
                    $logHdrs,
                    $logBody,
                    (string) $rawLogJson,
                    200,
                    ($stCd !== '' ? 'status_cd_' . $stCd : 'genewaybill_ok')
                );
            }

            if (!empty($out['status']) && $out['status'] === true) {
                $msgNoEwb = 'API success but no E-Way Bill number returned. Check endpoint URL in ewaybill_config.php.';
                $genBlob  = isset($out['raw']) && is_array($out['raw']) ? $out['raw'] : [];
                $ewNo     = trim((string) ($out['eway_bill_no'] ?? ''));
                if ($ewNo === '' && is_array($genBlob)) {
                    $ewNo = ewaybill_extract_eway_no_from_api_response($genBlob);
                }
                $partial = !empty($out['success_without_eway_no']);
                if ($ewNo !== '') {
                    $partial = false;
                }
                if ($ewNo === '' && $partial && function_exists('ewaybill_whitebooks_fetch_ewaybill_by_sale_document')) {
                    $fetchRes = ewaybill_whitebooks_fetch_ewaybill_by_sale_document($conn, $m, $fromG, (int) $invoice_id, $invNo);
                    if (!empty($fetchRes['ok']) && trim((string) ($fetchRes['eway_no'] ?? '')) !== '') {
                        $ewNo                  = trim((string) $fetchRes['eway_no']);
                        $out['eway_bill_date'] = (string) ($fetchRes['bill_date'] ?? '');
                        $out['valid_upto']     = (string) ($fetchRes['valid_upto'] ?? '');
                        $partial               = false;
                        $genBlob               = [
                            'generateResponse'      => isset($out['raw']) && is_array($out['raw']) ? $out['raw'] : [],
                            'getewaybillByDocument' => $fetchRes['raw'],
                        ];
                    } else {
                        $genBlob = [
                            'generateResponse'       => isset($out['raw']) && is_array($out['raw']) ? $out['raw'] : [],
                            'getewaybillByDocument'  => $fetchRes['raw'] ?? null,
                            'getewaybill_fetch_note' => (string) ($fetchRes['err'] ?? ''),
                        ];
                    }
                }
                $blobOk = is_array($genBlob) ? $genBlob : [];
                $jfull  = json_encode(
                    ewaybill_redact_secrets_for_eway_ui(is_array($blobOk) ? $blobOk : []),
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );
                $e4 = mysqli_real_escape_string($conn, (string) $jfull);
                if ($ewNo !== '') {
                    $ewD = (string) ($out['eway_bill_date'] ?? '');
                    $vu  = (string) ($out['valid_upto'] ?? '');
                    $dateSrc = $blobOk;
                    if (isset($blobOk['generateResponse']) && is_array($blobOk['generateResponse'])) {
                        $dateSrc = $blobOk['generateResponse'];
                    }
                    if ($ewD === '' || $vu === '') {
                        [$bdx, $vux] = ewaybill_extract_eway_dates_from_api_response(is_array($dateSrc) ? $dateSrc : []);
                        if ($ewD === '') {
                            $ewD = $bdx;
                        }
                        if ($vu === '') {
                            $vu = $vux;
                        }
                    }
                    $e1 = mysqli_real_escape_string($conn, $ewNo);
                    $e2 = mysqli_real_escape_string($conn, $ewD);
                    $e3 = mysqli_real_escape_string($conn, $vu);
                    @mysqli_query(
                        $conn,
                        "UPDATE tbl_sale_invoices SET eway_bill_no = '{$e1}', eway_bill_date = '{$e2}', eway_valid_upto = '{$e3}', eway_status = 'generated', eway_response = '{$e4}', eway_generated_at = NOW() WHERE id = " . (int) $invoice_id
                    );
                    $mOk = 'E-Way Bill generated successfully.';
                    unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                    return [
                        'ok'                        => true,
                        'status'                    => 'success',
                        'eway_db_status'            => 'generated',
                        'message'                   => $mOk,
                        'ewayBillNo'                => $ewNo,
                        'validUpto'                 => $vu,
                        'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($lastDebugPayload),
                        'eway_bill'                 => [
                            'status'       => 'success',
                            'ewayBillNo'   => $ewNo,
                            'ewayBillDate' => $ewD,
                            'validUpto'    => $vu,
                            'message'      => $mOk,
                        ],
                    ];
                }
                if ($partial) {
                    $msgSandbox = 'Sandbox API returned success but no e-Way Bill number. This is sandbox response behavior.';
                    if (ewaybill_is_whitebooks_sandbox_mode($m)) {
                        @mysqli_query(
                            $conn,
                            "UPDATE tbl_sale_invoices SET eway_status = 'sandbox_success_no_eway_number', eway_response = '{$e4}', eway_generated_at = NOW() WHERE id = " . (int) $invoice_id
                        );
                        unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                        return [
                            'ok'                        => true,
                            'status'                    => 'success',
                            'eway_db_status'            => 'sandbox_success_no_eway_number',
                            'message'                   => $msgSandbox,
                            'ewayBillNo'                => '',
                            'validUpto'                 => '',
                            'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($lastDebugPayload),
                            'eway_bill'                 => [
                                'status'       => 'success',
                                'ewayBillNo'   => '',
                                'ewayBillDate' => '',
                                'validUpto'    => '',
                                'message'      => $msgSandbox,
                            ],
                        ];
                    }
                    @mysqli_query(
                        $conn,
                        "UPDATE tbl_sale_invoices SET eway_status = 'failed', eway_response = '{$e4}' WHERE id = " . (int) $invoice_id
                    );
                    unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

                    return [
                        'ok'             => false,
                        'status'         => 'error',
                        'eway_db_status' => 'failed',
                        'message'        => 'The API returned success but no e-Way Bill number (ewayBillNo/EwbNo/ewbNo). Production requires a valid bill number.',
                        'ewayBillNo'     => '',
                        'validUpto'      => '',
                        'eway_bill'      => [
                            'status'       => 'error',
                            'ewayBillNo'   => '',
                            'ewayBillDate' => '',
                            'validUpto'    => '',
                            'message'      => $msgNoEwb,
                        ],
                    ];
                }
            }
            $euser = is_string($out['error'] ?? null) ? (string) $out['error'] : 'e-Way Bill generation failed.';
            if (ewaybill_nic_is_distance_rejected_error(is_array($out) ? $out : []) && $apiAttempt === 1) {
                @mysqli_query(
                    $conn,
                    "UPDATE tbl_sale_invoices SET eway_trans_distance = '0', eway_distance_km = 0 WHERE id = " . (int) $invoice_id . ' LIMIT 1'
                );
                continue;
            }
            break;
        }
        $outForStore = is_array($out) ? ewaybill_redact_secrets_for_eway_ui($out) : ['e' => $euser];
        $jfail       = mysqli_real_escape_string(
            $conn,
            (string) json_encode($outForStore, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        );
        @mysqli_query($conn, "UPDATE tbl_sale_invoices SET eway_status = 'failed', eway_response = '{$jfail}' WHERE id = " . (int) $invoice_id);
        $wrap = 'Invoice was saved, but e-Way Bill was not created: ' . ewaybill_clip_text($euser, 2000);
        unset($GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN']);

        return [
            'ok'                       => false,
            'status'                   => 'error',
            'message'                  => $wrap,
            'eway_debug_payload'       => ewaybill_sanitize_payload_json_for_debug($lastDebugPayload),
            'final_payload_sent_to_api' => ewaybill_format_payload_ui_debug($lastDebugPayload),
            'eway_debug_message'       => 'Compare this payload with Postman body.',
            'eway_bill'                => [
                'status'       => 'error',
                'ewayBillNo'   => '',
                'ewayBillDate' => '',
                'validUpto'    => '',
                'message'      => ewaybill_clip_text($euser, 2000),
            ],
        ];
    }
}
