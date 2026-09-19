<?php
$__auragold_eway_cred_local = __DIR__ . '/../includes/eway_whitebooks_credentials.local.php';
if (is_file($__auragold_eway_cred_local)) {
    require_once $__auragold_eway_cred_local;
}
/**
 * WhiteBooks e-Way Bill API helper.
 *
 * Credentials (v1.03): WHITEBOOKS_EMAIL, WHITEBOOKS_CLIENT_ID,
 * WHITEBOOKS_CLIENT_SECRET, plus GSTIN (tbl_branches.gst_no or WHITEBOOKS_AUTH_GSTIN).
 * Optional: WHITEBOOKS_IP_ADDRESS (defaults to $_SERVER['SERVER_ADDR']), WHITEBOOKS_GENERATE_URL.
 * Local file: admin/includes/eway_whitebooks_credentials.local.php
 *
 * From GSTIN: tbl_branches.gst_no for the working branch (see auragold_branch_gstin_for_eway()).
 *
 * genewaybill also requires the `authtoken` header (from WhiteBooks /authenticate, stored in tbl_ewaybill_api_tokens).
 * Sale-invoice flow sets $GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN'] before calling generateEwayBill().
 *
 * In save-sale-invoice.php: set env WHITEBOOKS_EWAY_ITEMLIST=0 to omit itemList if your tenant rejects it.
 * Optional: WHITEBOOKS_EWAY_APPEND_DOC_SUFFIX=1 appends a short suffix to docNo (testing duplicate-doc errors only).
 */

if (!function_exists('auragold_eway_server_ip')) {
    /** Public IP for WhiteBooks `ip_address` header (localhost is rejected / connection reset). */
    function auragold_eway_server_ip(): string
    {
        return '103.235.104.230';
    }
}

if (!function_exists('auragold_eway_credentials')) {
    /**
     * @return array{email:string,client_id:string,client_secret:string,ip_address:string,username:string,password:string,generate_url:string}
     */
    function auragold_eway_credentials(): array
    {
        $email = (string) (getenv('WHITEBOOKS_EMAIL') ?: '');
        $client_id = (string) (getenv('WHITEBOOKS_CLIENT_ID') ?: '');
        $client_secret = (string) (getenv('WHITEBOOKS_CLIENT_SECRET') ?: '');
        $ip_address = (string) (getenv('WHITEBOOKS_IP_ADDRESS') ?: '');
        $username = (string) (getenv('WHITEBOOKS_USERNAME') ?: '');
        $password = (string) (getenv('WHITEBOOKS_PASSWORD') ?: '');
        if (defined('WHITEBOOKS_EMAIL') && WHITEBOOKS_EMAIL !== '') {
            $email = (string) WHITEBOOKS_EMAIL;
        }
        if (defined('WHITEBOOKS_CLIENT_ID') && WHITEBOOKS_CLIENT_ID !== '') {
            $client_id = (string) WHITEBOOKS_CLIENT_ID;
        }
        if (defined('WHITEBOOKS_CLIENT_SECRET') && WHITEBOOKS_CLIENT_SECRET !== '') {
            $client_secret = (string) WHITEBOOKS_CLIENT_SECRET;
        }
        if (defined('WHITEBOOKS_IP_ADDRESS') && WHITEBOOKS_IP_ADDRESS !== '') {
            $ip_address = (string) WHITEBOOKS_IP_ADDRESS;
        }
        if (defined('WHITEBOOKS_USERNAME') && WHITEBOOKS_USERNAME !== '') {
            $username = (string) WHITEBOOKS_USERNAME;
        }
        if (defined('WHITEBOOKS_PASSWORD') && WHITEBOOKS_PASSWORD !== '') {
            $password = (string) WHITEBOOKS_PASSWORD;
        }
        // v1.03 sandbox expects ?email= on URL; legacy path was /ewaybill/generate
        $gen_url = (string) (getenv('WHITEBOOKS_GENERATE_URL') ?: 'https://apisandbox.whitebooks.in/ewaybillapi/v1.03/ewayapi/genewaybill');
        if (defined('WHITEBOOKS_GENERATE_URL') && WHITEBOOKS_GENERATE_URL !== '') {
            $gen_url = (string) WHITEBOOKS_GENERATE_URL;
        }
        $isSandboxUrl = stripos($gen_url, 'apisandbox.whitebooks.in') !== false || stripos($gen_url, 'sandbox') !== false;
        if ($isSandboxUrl) {
            $ip_address = '0.0.0.0';
        } else {
            if ($ip_address === '') {
                $ip_address = auragold_eway_server_ip();
            }
            $ip_address = trim((string) $ip_address);
            $bad_ips = ['127.0.0.1', '0.0.0.0', '::1', '::ffff:127.0.0.1'];
            if ($ip_address === '' || in_array($ip_address, $bad_ips, true)) {
                $ip_address = '103.235.104.230';
            }
        }

        if (trim($email) === '' && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE
            && !empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            $admin = $_SESSION['Admin'];
            foreach (['EmailId', 'email', 'Email', 'mail_id', 'MailId'] as $key) {
                if (!empty($admin[$key])) {
                    $cand = trim((string) $admin[$key]);
                    if ($cand !== '' && filter_var($cand, FILTER_VALIDATE_EMAIL)) {
                        $email = $cand;
                        break;
                    }
                }
            }
        }

        return [
            'email' => trim($email),
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'ip_address' => $ip_address,
            'username' => $username,
            'password' => $password,
            'generate_url' => $gen_url,
        ];
    }
}

if (!function_exists('auragold_eway_log_line')) {
    /** Append one line to admin/logs/eway_log.txt */
    function auragold_eway_log_line(string $line): void
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/eway_log.txt';
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('auragold_eway_normalize_vehicle_no')) {
    /** NIC-style: letters/digits only, e.g. MH12AB1234 */
    function auragold_eway_normalize_vehicle_no(string $vehicle): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $vehicle));
    }
}

if (!function_exists('auragold_eway_normalize_pin6')) {
    /** 6-digit India PIN digits only; fallback when missing/invalid. */
    function auragold_eway_normalize_pin6($value, string $fallback = '400001'): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen($digits) === 6 ? $digits : $fallback;
    }
}

if (!function_exists('auragold_eway_pin_int')) {
    /** JSON schema expects Number for fromPincode/toPincode (not string). */
    function auragold_eway_pin_int($value, int $fallback = 400001): int
    {
        $s = auragold_eway_normalize_pin6($value, (string) $fallback);
        $n = (int) $s;

        return $n > 0 ? $n : $fallback;
    }
}

if (!function_exists('auragold_branch_gstin_for_eway')) {
    /** Shop GSTIN from tbl_branches.gst_no (My Profile). */
    function auragold_branch_gstin_for_eway($conn): string
    {
        $bid = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : (isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : 0);
        if ($bid <= 0 || !$conn) {
            return '';
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_branches LIKE 'gst_no'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }

            return '';
        }
        mysqli_free_result($chk);
        $r = null;
        if (function_exists('getRecordMaster')) {
            $r = @getRecordMaster('SELECT gst_no FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1');
        }
        if (!$r) {
            $r = @getRecord('SELECT gst_no FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1');
        }

        $g = strtoupper(preg_replace('/\s+/', '', (string) ($r['gst_no'] ?? '')));
        if (strlen($g) === 15) {
            return $g;
        }
        $fb = getenv('WHITEBOOKS_AUTH_GSTIN');
        if ($fb !== false && trim((string) $fb) !== '') {
            return strtoupper(preg_replace('/\s+/', '', (string) $fb));
        }
        if (defined('WHITEBOOKS_AUTH_GSTIN') && WHITEBOOKS_AUTH_GSTIN !== '') {
            return strtoupper(preg_replace('/\s+/', '', (string) WHITEBOOKS_AUTH_GSTIN));
        }

        return $g;
    }
}

if (!function_exists('auragold_eway_build_item_list_from_lines')) {
    /**
     * Build NIC-style item lines for APIs that expect HSN + taxable value (best-effort from saved invoice lines).
     *
     * @param mixed $conn mysqli
     * @param array $lines Same shape as decoded items[] from save-sale-invoice
     * @return list<array<string,mixed>>
     */
    function auragold_eway_build_item_list_from_lines($conn, array $lines, bool $interstate = false, bool $sandboxNicHints = false): array
    {
        $out = [];
        foreach ($lines as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = (int) ($row['product_id'] ?? 0);
            $hsn = '';
            if ($pid > 0 && $conn) {
                $pc = @getRecord('SELECT hsn FROM tbl_product_characteristics WHERE product_id = ' . $pid . ' AND status = 1 ORDER BY id ASC LIMIT 1');
                if ($pc && !empty($pc['hsn'])) {
                    $hsn = preg_replace('/[^0-9]/', '', (string) $pc['hsn']);
                }
                if ($hsn === '') {
                    $mid = @getRecord('SELECT metal_id FROM tbl_product_characteristics WHERE product_id = ' . $pid . ' AND status = 1 ORDER BY id ASC LIMIT 1');
                    $metal_id = (int) ($mid['metal_id'] ?? 0);
                    if ($metal_id > 0) {
                        $mr = @getRecord('SELECT hsn_code FROM tbl_metal WHERE id = ' . $metal_id . ' LIMIT 1');
                        if ($mr && !empty($mr['hsn_code'])) {
                            $hsn = preg_replace('/[^0-9]/', '', (string) $mr['hsn_code']);
                        }
                    }
                }
            }
            if ($hsn === '') {
                $hsn = $sandboxNicHints ? '1001' : '711319';
            }
            $hsnInt = (int) preg_replace('/\D/', '', (string) $hsn);
            if ($hsnInt < 1) {
                $hsnInt = $sandboxNicHints ? 1001 : 711319;
            }
            $taxable = (float) ($row['net_amount'] ?? $row['amount'] ?? 0);
            $tax = (float) ($row['tax'] ?? $row['tax_amount'] ?? 0);
            if ($taxable <= 0) {
                $taxable = (float) ($row['net_amt_with_tax'] ?? 0) - $tax;
            }
            if ($taxable < 0) {
                $taxable = 0;
            }
            $gst_rate = 0.0;
            if ($taxable > 0 && $tax > 0) {
                $gst_rate = round(($tax / $taxable) * 100, 2);
            }
            $pname = trim((string) ($row['product_name'] ?? $row['name'] ?? ''));
            if ($pname === '') {
                $pname = 'Goods';
            }
            $qty = (float) ($row['quantity'] ?? $row['qty'] ?? $row['net_weight'] ?? 1);
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $qtyUnit = $sandboxNicHints ? 'BOX' : 'NOS';
            if ($interstate) {
                $out[] = [
                    'productName' => $pname,
                    'productDesc' => $pname,
                    'hsnCode' => $hsnInt,
                    'quantity' => round($qty, 3),
                    'qtyUnit' => $qtyUnit,
                    'taxableAmount' => round($taxable, 2),
                    'cgstRate' => 0,
                    'sgstRate' => 0,
                    'igstRate' => $gst_rate,
                    'cessRate' => 0,
                ];
            } else {
                $half = round($gst_rate / 2, 2);
                $other = round($gst_rate - $half, 2);

                $out[] = [
                    'productName' => $pname,
                    'productDesc' => $pname,
                    'hsnCode' => $hsnInt,
                    'quantity' => round($qty, 3),
                    'qtyUnit' => $qtyUnit,
                    'taxableAmount' => round($taxable, 2),
                    'cgstRate' => $half,
                    'sgstRate' => $other,
                    'igstRate' => 0,
                    'cessRate' => 0,
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_eway_parse_nic_suggested_km')) {
    /**
     * When NIC returns error 702, error.message can be JSON: { "fromPinCode", "toPinCode", "distance" }.
     * Use that distance and call generate again (handled in generateEwayBill).
     *
     * @param array<string, mixed> $response
     */
    function auragold_eway_parse_nic_suggested_km(array $response): ?float
    {
        $nest = $response['error'] ?? null;
        if (!is_array($nest)) {
            return null;
        }
        $m = (string) ($nest['message'] ?? $nest['Message'] ?? '');
        if ($m === '' || (isset($m[0]) && $m[0] !== '{' && $m[0] !== '[')) {
            return null;
        }
        $j = json_decode($m, true);
        if (!is_array($j) || !isset($j['distance'])) {
            return null;
        }
        $d = (float) $j['distance'];

        return $d > 0 ? $d : null;
    }
}

if (!function_exists('auragold_eway_nic_error_info_text')) {
    /**
     * NIC/WhiteBooks often return extra detail in error.info as Base64 (plain English after decode).
     */
    function auragold_eway_nic_error_info_text($errorNode): string
    {
        if (!is_array($errorNode)) {
            return '';
        }
        $infoB64 = (string) ($errorNode['info'] ?? $errorNode['Info'] ?? '');
        if ($infoB64 === '') {
            return '';
        }
        $dec = @base64_decode($infoB64, true);
        if (!is_string($dec) || $dec === '') {
            return '';
        }
        $s = trim($dec);
        if (isset($s[0]) && $s[0] === ',') {
            $s = trim(substr($s, 1));
        }
        $s = trim($s);

        return $s;
    }
}

if (!function_exists('auragold_eway_cred_is_sandbox')) {
    /** True when outgoing Generate URL targets WhiteBooks sandbox (relaxed GSTIN / state checks elsewhere). */
    function auragold_eway_cred_is_sandbox(): bool
    {
        $u = (string) (($GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED']['generate_url'] ?? ''));
        if ($u === '' && function_exists('auragold_eway_credentials')) {
            $c = auragold_eway_credentials();
            $u = (string) ($c['generate_url'] ?? '');
        }

        return stripos($u, 'sandbox') !== false || stripos($u, 'apisandbox.whitebooks.in') !== false;
    }
}

if (!function_exists('auragold_eway_recompute_nic_tax_amounts')) {
    /**
     * Align header tax values and item rates with place-of-supply (GSTIN state codes).
     * Interstate: IGST only (default rate 3% when lines have no igstRate). Intrastate: CGST+SGST (default 1.5+1.5).
     * Sets totInvValue = totalValue + sum taxes + cess + otherValue. Removes duplicate "distance" key.
     *
     * @param array<string, mixed> $a
     */
    function auragold_eway_recompute_nic_tax_amounts(array &$a): void
    {
        if (empty($a['itemList']) || !is_array($a['itemList'])) {
            unset($a['distance']);

            return;
        }
        $fsc = (int) ($a['fromStateCode'] ?? 0);
        $tsc = (int) ($a['toStateCode'] ?? 0);
        $interstate = ($fsc !== $tsc);

        $tv = 0.0;
        foreach ($a['itemList'] as $row) {
            if (is_array($row)) {
                $tv += round((float) ($row['taxableAmount'] ?? 0), 2);
            }
        }
        if ($tv <= 0) {
            $tv = round((float) ($a['totalValue'] ?? 0), 2);
        }
        if ($tv <= 0) {
            unset($a['distance']);

            return;
        }

        $cess = round((float) ($a['cessValue'] ?? 0), 2);
        $cessNa = round((float) ($a['cessNonAdvolValue'] ?? 0), 2);
        $other = round((float) ($a['otherValue'] ?? 0), 2);

        if ($interstate) {
            $r = 0.0;
            foreach ($a['itemList'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $r = max($r, (float) ($row['igstRate'] ?? 0));
            }
            if ($r <= 0) {
                $r = 3.0;
            }
            foreach ($a['itemList'] as $ix => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $a['itemList'][$ix]['cgstRate'] = 0.0;
                $a['itemList'][$ix]['sgstRate'] = 0.0;
                $a['itemList'][$ix]['igstRate'] = $r;
            }
            $igstVal = round($tv * $r / 100, 2);
            $a['cgstValue'] = 0.0;
            $a['sgstValue'] = 0.0;
            $a['igstValue'] = $igstVal;
        } else {
            $rComb = 0.0;
            foreach ($a['itemList'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rComb = max($rComb, (float) ($row['cgstRate'] ?? 0) + (float) ($row['sgstRate'] ?? 0));
            }
            if ($rComb <= 0) {
                $rComb = 3.0;
            }
            $half = round($rComb / 2, 2);
            $otherR = round($rComb - $half, 2);
            foreach ($a['itemList'] as $ix => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $a['itemList'][$ix]['igstRate'] = 0.0;
                $a['itemList'][$ix]['cgstRate'] = $half;
                $a['itemList'][$ix]['sgstRate'] = $otherR;
            }
            $a['igstValue'] = 0.0;
            $a['cgstValue'] = round($tv * $half / 100, 2);
            $a['sgstValue'] = round($tv * $otherR / 100, 2);
        }

        $a['totalValue'] = round($tv, 2);
        $a['totInvValue'] = round(
            $tv + (float) $a['cgstValue'] + (float) $a['sgstValue'] + (float) $a['igstValue'] + $cess + $cessNa + $other,
            2
        );

        unset($a['distance']);
    }
}

if (!function_exists('auragold_eway_finalize_eway_payload_array')) {
    /**
     * Normalize NIC JSON types/format before POST (match Postman behaviour).
     *
     * @param array<string, mixed> $a
     *
     * @return array<string, mixed>
     */
    function auragold_eway_finalize_eway_payload_array(array $a): array
    {
        if (isset($a['docNo'])) {
            $a['docNo'] = str_replace('/', '-', (string) $a['docNo']);
        }
        if (isset($a['transDocDate'])) {
            $td = trim((string) $a['transDocDate']);
            if ($td === '') {
                unset($a['transDocDate']);
            } else {
                $a['transDocDate'] = $td;
            }
        }
        $a['transactionType'] = (int) ($a['transactionType'] ?? 1);
        if (isset($a['transMode'])) {
            $a['transMode'] = (string) $a['transMode'];
        }
        foreach (['fromGstin', 'toGstin'] as $gk) {
            if (isset($a[$gk])) {
                $a[$gk] = strtoupper(preg_replace('/\s+/', '', (string) $a[$gk]));
            }
        }
        if (isset($a['vehicleNo'])) {
            $a['vehicleNo'] = function_exists('auragold_eway_normalize_vehicle_no')
                ? auragold_eway_normalize_vehicle_no((string) $a['vehicleNo'])
                : strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $a['vehicleNo']));
        }
        if (isset($a['vehicleType'])) {
            $vt = strtoupper(trim((string) $a['vehicleType']));
            $a['vehicleType'] = ($vt === 'O') ? 'O' : 'R';
        }
        if (isset($a['transporterId'])) {
            $tid = strtoupper(preg_replace('/\s+/', '', (string) $a['transporterId']));
            if ($tid === '') {
                unset($a['transporterId']);
            } else {
                $a['transporterId'] = $tid;
            }
        }
        if (isset($a['transporterName'])) {
            $tn = trim((string) $a['transporterName']);
            if ($tn === '') {
                unset($a['transporterName']);
            } else {
                $a['transporterName'] = $tn;
            }
        }
        if (isset($a['subSupplyDesc'])) {
            $sd = trim((string) $a['subSupplyDesc']);
            if ($sd === '') {
                unset($a['subSupplyDesc']);
            } else {
                $a['subSupplyDesc'] = $sd;
            }
        }
        if (!empty($a['itemList']) && is_array($a['itemList'])) {
            foreach ($a['itemList'] as $ix => $row) {
                if (!is_array($row)) {
                    unset($a['itemList'][$ix]);
                    continue;
                }
                $pn = trim((string) ($row['productName'] ?? ''));
                if ($pn === '') {
                    $pn = 'Goods';
                    $a['itemList'][$ix]['productName'] = $pn;
                }
                $pd = trim((string) ($row['productDesc'] ?? ''));
                if ($pd === '') {
                    $a['itemList'][$ix]['productDesc'] = $pn;
                }
                $hcRaw = isset($row['hsnCode']) ? preg_replace('/\D/', '', (string) $row['hsnCode']) : '';
                $a['itemList'][$ix]['hsnCode'] = $hcRaw !== '' ? (int) $hcRaw : 1001;
                $q = isset($row['quantity']) ? (float) $row['quantity'] : 0.0;
                if ($q <= 0) {
                    $a['itemList'][$ix]['quantity'] = 1.0;
                }
                $ta = (float) ($row['taxableAmount'] ?? 0);
                if ($ta <= 0) {
                    $a['itemList'][$ix]['taxableAmount'] = 0.01;
                }
                $qu = isset($a['itemList'][$ix]['qtyUnit']) ? trim((string) $a['itemList'][$ix]['qtyUnit']) : '';
                if ($qu === '') {
                    $a['itemList'][$ix]['qtyUnit'] = 'NOS';
                }
            }
            $a['itemList'] = array_values(array_filter($a['itemList'], static function ($r) {
                return is_array($r);
            }));
        }
        foreach (['fromPincode', 'toPincode', 'fromStateCode', 'toStateCode', 'actFromStateCode', 'actToStateCode'] as $ik) {
            if (array_key_exists($ik, $a)) {
                $a[$ik] = (int) $a[$ik];
            }
        }

        auragold_eway_recompute_nic_tax_amounts($a);

        return $a;
    }
}

if (!function_exists('auragold_eway_json_debug_payload')) {
    /** Pretty JSON with secrets redacted (compare POST body with Postman). */
    function auragold_eway_json_debug_payload(array $eway_data): string
    {
        $h = __DIR__ . '/../includes/ewaybill_api_helper.php';
        if (is_file($h)) {
            require_once $h;
        }
        $san = function_exists('ewaybill_redact_secrets_for_eway_ui') ? ewaybill_redact_secrets_for_eway_ui($eway_data) : $eway_data;

        return (string) json_encode($san, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

if (!function_exists('auragold_eway_validate_before_http_post')) {
    /**
     * Final NIC payload rules before curl: distance "0", strip empty fields, GSTIN/vehicle/state checks.
     * Mutates $eway_data in place.
     *
     * @param array<string, mixed> $eway_data
     *
     * @return array{status: bool, error: string, raw: null}|null null = OK
     */
    function auragold_eway_validate_before_http_post(array &$eway_data, bool $allowNonzeroTransDistance = false): ?array
    {
        $helper = __DIR__ . '/../includes/ewaybill_api_helper.php';
        if (is_file($helper) && !function_exists('ewaybill_is_valid_gstin')) {
            require_once $helper;
        }
        $ewConn = $GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN'] ?? null;
        if ($ewConn instanceof mysqli && function_exists('ewaybill_apply_seller_gstin_from_config')) {
            ewaybill_apply_seller_gstin_from_config($ewConn, $eway_data);
        }

        if (!$allowNonzeroTransDistance) {
            $eway_data['transDistance'] = '0';
        }
        unset($eway_data['distance']);

        foreach (['transDocDate', 'transporterName', 'subSupplyDesc'] as $ek) {
            if (!isset($eway_data[$ek])) {
                continue;
            }
            if (trim((string) $eway_data[$ek]) === '') {
                unset($eway_data[$ek]);
            }
        }

        $eway_data['fromGstin'] = strtoupper(trim((string) ($eway_data['fromGstin'] ?? '')));
        $eway_data['fromGstin'] = preg_replace('/\s+/', '', $eway_data['fromGstin']);
        $eway_data['toGstin'] = strtoupper(trim((string) ($eway_data['toGstin'] ?? '')));
        $eway_data['toGstin'] = preg_replace('/\s+/', '', $eway_data['toGstin']);

        $isSandboxPost = function_exists('auragold_eway_cred_is_sandbox') && auragold_eway_cred_is_sandbox();

        if (!function_exists('ewaybill_is_valid_gstin')) {
            return ['status' => false, 'error' => 'e-Way Bill GSTIN validator could not be loaded.', 'raw' => null];
        }
        if ($isSandboxPost) {
            $okFrom = function_exists('ewaybill_is_acceptable_eway_api_gstin')
                ? ewaybill_is_acceptable_eway_api_gstin($eway_data['fromGstin'])
                : (strlen($eway_data['fromGstin']) === 15);
            $okTo = function_exists('ewaybill_is_acceptable_eway_api_gstin')
                ? ewaybill_is_acceptable_eway_api_gstin($eway_data['toGstin'])
                : (strlen($eway_data['toGstin']) === 15);
        } else {
            $okFrom = function_exists('ewaybill_is_acceptable_eway_api_gstin')
                ? ewaybill_is_acceptable_eway_api_gstin($eway_data['fromGstin'])
                : ewaybill_is_valid_gstin($eway_data['fromGstin']);
            $okTo = ewaybill_is_valid_gstin($eway_data['toGstin']);
        }
        if (!$okFrom) {
            return ['status' => false, 'error' => 'Invalid seller GSTIN format: ' . $eway_data['fromGstin'], 'raw' => null];
        }
        if (!$okTo) {
            return ['status' => false, 'error' => 'Invalid buyer GSTIN format: ' . $eway_data['toGstin'], 'raw' => null];
        }

        $fromState = substr($eway_data['fromGstin'], 0, 2);
        $toState = substr($eway_data['toGstin'], 0, 2);
        if (!$isSandboxPost) {
            if ((int) $fromState !== (int) ($eway_data['fromStateCode'] ?? 0)) {
                return ['status' => false, 'error' => 'Seller GSTIN state mismatch', 'raw' => null];
            }
            if ((int) $toState !== (int) ($eway_data['toStateCode'] ?? 0)) {
                return ['status' => false, 'error' => 'Buyer GSTIN state mismatch', 'raw' => null];
            }
        }

        if (empty($eway_data['itemList']) || !is_array($eway_data['itemList'])) {
            return ['status' => false, 'error' => 'itemList is required for e-Way Bill.', 'raw' => null];
        }
        foreach (['fromTrdName', 'fromAddr1', 'fromPlace', 'toTrdName', 'toAddr1', 'toPlace', 'docNo', 'docDate'] as $reqK) {
            if (!isset($eway_data[$reqK]) || trim((string) $eway_data[$reqK]) === '') {
                return ['status' => false, 'error' => 'Missing required field for e-Way Bill: ' . $reqK, 'raw' => null];
            }
        }

        $tm = (string) ($eway_data['transMode'] ?? '1');
        $forceSamplePost = isset($_POST['eway_sandbox_force_sample_payload']) && (string) $_POST['eway_sandbox_force_sample_payload'] === '1';
        if ($tm === '1') {
            if ($isSandboxPost && $forceSamplePost) {
                $eway_data['transMode']   = '1';
                $eway_data['vehicleType'] = 'R';
                $eway_data['vehicleNo']   = 'MH31AB1234';
            } else {
                $vehicleNo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($eway_data['vehicleNo'] ?? '')));
                if ($vehicleNo === '' && $isSandboxPost) {
                    $vehicleNo = 'MH31AB1234';
                }
                if ($vehicleNo === '' && !$isSandboxPost) {
                    return ['status' => false, 'error' => 'Vehicle number is required. Example: MH31AB1234', 'raw' => null];
                }
                $eway_data['vehicleNo'] = $vehicleNo;
                if (!preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $eway_data['vehicleNo'])) {
                    return ['status' => false, 'error' => 'Invalid vehicle number. Example: MH31AB1234', 'raw' => null];
                }
            }
        }

        @file_put_contents(
            __DIR__ . '/eway_debug.json',
            (string) json_encode($eway_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        return null;
    }
}

if (!function_exists('generateEwayBill')) {
    /**
     * WhiteBooks e-Way generate: headers include client_id, client_secret, gstin, authtoken (when set globally).
     *
     * @param array $invoice invoice_no, invoice_date (d/m/Y), from_gstin, to_gstin, vehicle_no, total_amount, distance;
     *                        optional: transMode, itemList, raw_extra
     * @return array{status:bool, eway_bill_no?:string, eway_bill_date?:string, error?:string|array, raw?:mixed}
     */
    function generateEwayBill(array $invoice): array
    {
        /** @var ?array{email?:string,client_id?:string,client_secret?:string,ip_address?:string,username?:string,password?:string,generate_url?:string} $cred */
        if (!empty($GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED']) && is_array($GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED'])) {
            $cred = $GLOBALS['AURAGOLD_EWAYBILL_CONFIG_CRED'];
        } else {
            $cred = auragold_eway_credentials();
        }
        if (($cred['email'] ?? '') === '' || ($cred['client_id'] ?? '') === '' || ($cred['client_secret'] ?? '') === '') {
            return ['status' => false, 'error' => 'Configure WHITEBOOKS_EMAIL, WHITEBOOKS_CLIENT_ID, WHITEBOOKS_CLIENT_SECRET (env or constants / local file).'];
        }
        $genUrl = trim((string) ($cred['generate_url'] ?? ''));
        $isSandboxGen = stripos($genUrl, 'apisandbox.whitebooks.in') !== false || stripos($genUrl, 'sandbox') !== false;
        if ($isSandboxGen) {
            $cred['ip_address'] = '0.0.0.0';
        } else {
            $ip_c = trim((string) ($cred['ip_address'] ?? ''));
            $bad_ips = ['', '0.0.0.0', '127.0.0.1', '::1', '::ffff:127.0.0.1'];
            if (in_array($ip_c, $bad_ips, true)) {
                $cred['ip_address'] = function_exists('auragold_eway_server_ip') ? auragold_eway_server_ip() : '103.235.104.230';
            } else {
                $cred['ip_address'] = $ip_c;
            }
        }

        $__ewh = __DIR__ . '/../includes/ewaybill_api_helper.php';
        if (is_file($__ewh) && !function_exists('ewaybill_extract_eway_no_from_api_response')) {
            require_once $__ewh;
        }

        $override_raw = isset($invoice['_eway_json_body_override']) ? trim((string) $invoice['_eway_json_body_override']) : '';
        if ($override_raw !== '') {
            $tmp = json_decode($override_raw, true);
            if (!is_array($tmp)) {
                return ['status' => false, 'error' => 'Payload override (_eway_json_body_override) must be a JSON object.', 'raw' => null];
            }
            $eway_data = auragold_eway_finalize_eway_payload_array($tmp);
            $allowNz = !empty($invoice['_nic_702_retry']);
            $pre = auragold_eway_validate_before_http_post($eway_data, $allowNz);
            if ($pre !== null) {
                $pre['final_payload_debug'] = auragold_eway_json_debug_payload($eway_data);

                return $pre;
            }
            $payload_json = json_encode($eway_data, JSON_UNESCAPED_UNICODE);
            if ($payload_json === false) {
                return ['status' => false, 'error' => 'Could not encode override JSON.'];
            }

            goto auragold_eway_send_http;
        }

        $helperGst = __DIR__ . '/../includes/ewaybill_api_helper.php';
        if (is_file($helperGst) && !function_exists('ewaybill_is_valid_gstin')) {
            require_once $helperGst;
        }

        $from = strtoupper(preg_replace('/\s+/', '', (string) ($invoice['from_gstin'] ?? '')));
        $to = strtoupper(preg_replace('/\s+/', '', (string) ($invoice['to_gstin'] ?? '')));
        if (function_exists('ewaybill_is_valid_gstin')) {
            if ($isSandboxGen) {
                $fromOk = function_exists('ewaybill_is_acceptable_eway_api_gstin')
                    ? ewaybill_is_acceptable_eway_api_gstin($from)
                    : (strlen($from) === 15);
                $toOk = function_exists('ewaybill_is_acceptable_eway_api_gstin')
                    ? ewaybill_is_acceptable_eway_api_gstin($to)
                    : (strlen($to) === 15);
            } else {
                $fromOk = function_exists('ewaybill_is_acceptable_eway_api_gstin')
                    ? ewaybill_is_acceptable_eway_api_gstin($from)
                    : ewaybill_is_valid_gstin($from);
                $toOk = ewaybill_is_valid_gstin($to);
            }
            if (!$fromOk) {
                return ['status' => false, 'error' => 'Invalid seller GSTIN format: ' . $from, 'raw' => null];
            }
            if (!$toOk) {
                return ['status' => false, 'error' => 'Invalid buyer GSTIN format: ' . $to, 'raw' => null];
            }
        } elseif (strlen($from) !== 15 || strlen($to) !== 15) {
            return ['status' => false, 'error' => 'Valid 15-char from_gstin and to_gstin are required for e-Way Bill.', 'raw' => null];
        }
        if ($from === $to) {
            return [
                'status' => false,
                'error' => 'e-Way Bill cannot use the same GSTIN for seller and buyer. Enter the customer\'s GSTIN on the invoice (not your shop GSTIN).',
                'raw' => null,
            ];
        }

        $transModeEarly = (string) ($invoice['transMode'] ?? '1');
        $vehicle = auragold_eway_normalize_vehicle_no((string) ($invoice['vehicle_no'] ?? ''));
        if ($transModeEarly === '1' && $vehicle === '') {
            if (!$isSandboxGen) {
                return ['status' => false, 'error' => 'Vehicle number is required. Example: MH31AB1234', 'raw' => null];
            }
            /* Sandbox: empty vehicle is defaulted to MH31AB1234 in auragold_eway_validate_before_http_post */
        }

        if (!array_key_exists('distance', $invoice) || $invoice['distance'] === null || (is_string($invoice['distance']) && trim((string) $invoice['distance']) === '')) {
            $invoice['distance'] = 0;
        }
        $distance = (float) $invoice['distance'];
        if ($distance < 0) {
            return ['status' => false, 'error' => 'Distance (km) cannot be negative.', 'raw' => null];
        }

        $from_state = (int) substr($from, 0, 2);
        $to_state = (int) substr($to, 0, 2);
        if ($from_state < 1 || $to_state < 1) {
            return ['status' => false, 'error' => 'Invalid state code in seller or buyer GSTIN (first two digits).', 'raw' => null];
        }
        /** NIC tax split: follow GSTIN state codes only (same as fromStateCode vs toStateCode). */
        $interstate = array_key_exists('interstate', $invoice)
            ? (bool) $invoice['interstate']
            : ($from_state !== $to_state);
        $tot_val = round((float) ($invoice['total_amount'] ?? 0), 2);
        $cgst_val = round((float) ($invoice['cgst_value'] ?? $invoice['cgst'] ?? 0), 2);
        $sgst_val = round((float) ($invoice['sgst_value'] ?? $invoice['sgst'] ?? 0), 2);
        $igst_val = round((float) ($invoice['igst_value'] ?? $invoice['igst'] ?? 0), 2);
        $taxable_val = round((float) ($invoice['taxable_value'] ?? 0), 2);
        if ($taxable_val <= 0 && $tot_val > 0) {
            $taxable_val = round(max(0, $tot_val - $cgst_val - $sgst_val - $igst_val), 2);
        }
        if ($taxable_val <= 0 && $tot_val > 0) {
            $taxable_val = round(max(0.01, $tot_val / 1.03), 2);
        }

        $doc_no_out = str_replace('/', '-', (string) ($invoice['invoice_no'] ?? ''));
        $append_doc = getenv('WHITEBOOKS_EWAY_APPEND_DOC_SUFFIX') === '1';
        if (!$append_doc && defined('WHITEBOOKS_EWAY_APPEND_DOC_SUFFIX') && constant('WHITEBOOKS_EWAY_APPEND_DOC_SUFFIX')) {
            $append_doc = true;
        }
        if ($doc_no_out !== '' && $append_doc) {
            $doc_no_out .= '-' . substr(str_replace('.', '', (string) microtime(true)), -8);
        }

        $pin_from = auragold_eway_pin_int($invoice['from_pincode'] ?? $invoice['fromPincode'] ?? '', 400001);
        $pin_to = auragold_eway_pin_int($invoice['to_pincode'] ?? $invoice['toPincode'] ?? $invoice['customer_pincode'] ?? '', 400001);
        /* Default NIC master distance; auragold_eway_validate_before_http_post enforces transDistance "0" unless _nic_702_retry */
        $dist_str = '0';

        $eway_data = [
            'supplyType' => 'O',
            'subSupplyType' => '1',
            'docType' => 'INV',
            'transactionType' => 1,
            'docNo' => $doc_no_out,
            'docDate' => (string) ($invoice['invoice_date'] ?? ''),
            'fromGstin' => $from,
            'toGstin' => $to,
            'fromStateCode' => $from_state,
            'toStateCode' => $to_state,
            'fromPincode' => $pin_from,
            'toPincode' => $pin_to,
            'actFromStateCode' => $from_state,
            'actToStateCode' => $to_state,
            'vehicleNo' => $vehicle,
            'vehicleType' => strtoupper(trim((string) ($invoice['vehicle_type'] ?? 'R'))) === 'O' ? 'O' : 'R',
            'transMode' => (string) ($invoice['transMode'] ?? '1'),
            'transDistance' => $dist_str,
            'totalValue' => $taxable_val > 0 ? $taxable_val : $tot_val,
            'cgstValue' => $cgst_val,
            'sgstValue' => $sgst_val,
            'igstValue' => $igst_val,
            'cessValue' => 0,
            'cessNonAdvolValue' => 0,
            'totInvValue' => $tot_val,
            'transDocNo' => $doc_no_out,
        ];
        $td_inv = trim((string) ($invoice['trans_doc_date'] ?? ''));
        if ($td_inv !== '') {
            $eway_data['transDocDate'] = $td_inv;
        }
        $item_list_use = null;
        if (!empty($invoice['itemList']) && is_array($invoice['itemList'])) {
            $clean_lines = [];
            foreach ($invoice['itemList'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ((float) ($row['taxableAmount'] ?? 0) <= 0) {
                    continue;
                }
                $clean_lines[] = $row;
            }
            if (!empty($clean_lines)) {
                $item_list_use = $clean_lines;
            }
        }
        if ($item_list_use !== null) {
            $eway_data['itemList'] = $item_list_use;
        } else {
            $line_tax = $taxable_val > 0 ? $taxable_val : round(max(0, $tot_val / 1.03), 2);
            $gst_rate_line = ($line_tax > 0 && ($cgst_val + $sgst_val + $igst_val) > 0)
                ? round((($cgst_val + $sgst_val + $igst_val) / $line_tax) * 100, 2)
                : 3;
            if ($interstate) {
                $eway_data['itemList'] = [[
                    'productName' => 'Goods',
                    'productDesc' => 'Goods',
                    'hsnCode' => 711319,
                    'quantity' => 1,
                    'qtyUnit' => 'NOS',
                    'taxableAmount' => round($line_tax, 2),
                    'cgstRate' => 0,
                    'sgstRate' => 0,
                    'igstRate' => $gst_rate_line > 0 ? $gst_rate_line : 3,
                ]];
            } else {
                $half = round($gst_rate_line / 2, 2);
                $other = round($gst_rate_line - $half, 2);
                $eway_data['itemList'] = [[
                    'productName' => 'Goods',
                    'productDesc' => 'Goods',
                    'hsnCode' => 711319,
                    'quantity' => 1,
                    'qtyUnit' => 'NOS',
                    'taxableAmount' => round($line_tax, 2),
                    'cgstRate' => $half,
                    'sgstRate' => $other,
                    'igstRate' => 0,
                ]];
            }
        }
        if (!empty($invoice['raw_extra']) && is_array($invoice['raw_extra'])) {
            $eway_data = array_merge($eway_data, $invoice['raw_extra']);
        }

        $eway_data['fromPincode'] = auragold_eway_pin_int($eway_data['fromPincode'] ?? '', 400001);
        $eway_data['toPincode'] = auragold_eway_pin_int($eway_data['toPincode'] ?? '', 400001);
        $eway_data['actFromStateCode'] = (int) ($eway_data['actFromStateCode'] ?? $eway_data['fromStateCode'] ?? $from_state);
        $eway_data['actToStateCode'] = (int) ($eway_data['actToStateCode'] ?? $eway_data['toStateCode'] ?? $to_state);

        if (!empty($eway_data['itemList']) && is_array($eway_data['itemList'])) {
            $sum_line_taxable = 0.0;
            foreach ($eway_data['itemList'] as $iln) {
                if (is_array($iln)) {
                    $sum_line_taxable += (float) ($iln['taxableAmount'] ?? 0);
                }
            }
            if ($sum_line_taxable > 0) {
                $eway_data['totalValue'] = round($sum_line_taxable, 2);
            }
        }

        $eway_data = auragold_eway_finalize_eway_payload_array($eway_data);

        $allowNz = !empty($invoice['_nic_702_retry']);
        $preErr = auragold_eway_validate_before_http_post($eway_data, $allowNz);
        if ($preErr !== null) {
            $preErr['final_payload_debug'] = auragold_eway_json_debug_payload($eway_data);

            return $preErr;
        }

        $payload_json = json_encode($eway_data, JSON_UNESCAPED_UNICODE);
        if ($payload_json === false) {
            return ['status' => false, 'error' => 'Could not encode request JSON.'];
        }

        auragold_eway_send_http:

        $GLOBALS['AURAGOLD_EWAY_LAST_OUTGOING_JSON'] = $payload_json;

        /** Log outgoing JSON (for “Invalid request!” — NIC rarely adds detail; compare body to WhiteBooks sample). */
        $req_log = $payload_json;
        if (strlen($req_log) > 12000) {
            $req_log = substr($req_log, 0, 12000) . '…[truncated]';
        }
        $gstin_log_from = strtoupper(preg_replace('/\s+/', '', (string) ($eway_data['fromGstin'] ?? $from ?? '')));
        $gstin_log_to = strtoupper(preg_replace('/\s+/', '', (string) ($eway_data['toGstin'] ?? $to ?? '')));
        auragold_eway_log_line(
            date('Y-m-d H:i:s')
            . ' REQ docNo=' . ($eway_data['docNo'] ?? '')
            . ' from=' . $gstin_log_from
            . ' to=' . $gstin_log_to
            . ' totInv=' . ($eway_data['totInvValue'] ?? '')
            . ' json=' . $req_log
        );

        $curl_url = str_replace('apisandbbox', 'apisandbox', (string) $cred['generate_url']);
        if (strpos($curl_url, 'email=') === false && $cred['email'] !== '') {
            $curl_url .= (strpos($curl_url, '?') !== false ? '&' : '?') . 'email=' . rawurlencode($cred['email']);
        }
        $GLOBALS['AURAGOLD_EWAY_LAST_GENERATE_URL'] = $curl_url;
        $curlLu = strtolower($curl_url);
        if (strpos($curlLu, '/ewayapi/genewaybill') === false && strpos($curlLu, 'genewaybill') === false) {
            return ['status' => false, 'error' => 'Generate URL must be WhiteBooks NIC Generate E-Way Bill (path .../ewayapi/genewaybill), not authentication. Check WHITEBOOKS_GENERATE_URL / admin/config/ewaybill_config.php.', 'raw' => null];
        }

        $connEw = $GLOBALS['AURAGOLD_EWAY_MYSQLI_CONN'] ?? null;
        $invEwId = (int) ($invoice['_eway_invoice_id'] ?? 0);
        if ($connEw instanceof mysqli && $invEwId > 0 && function_exists('ewaybill_persist_final_request_json')) {
            $redUrl = $curl_url;
            ewaybill_persist_final_request_json($connEw, $invEwId, $payload_json, $cred, $redUrl);
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: */*',
            'ip_address: ' . $cred['ip_address'],
            'client_id: ' . $cred['client_id'],
            'client_secret: ' . $cred['client_secret'],
            'gstin: ' . $gstin_log_from,
        ];
        $authTok = (string) ($GLOBALS['AURAGOLD_EWAYBILL_AUTH_TOKEN'] ?? '');
        if ($authTok !== '') {
            $headers[] = 'authtoken: ' . $authTok;
        }
        if ($cred['email'] !== '' && strpos($curl_url, 'email=') === false) {
            $headers[] = 'email: ' . $cred['email'];
        }

        $logs_dir = dirname(__DIR__) . '/logs';
        if (!is_dir($logs_dir)) {
            @mkdir($logs_dir, 0755, true);
        }
        $verbose_stream = @fopen($logs_dir . '/eway_curl_verbose.log', 'ab');
        $ch = curl_init($curl_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // Localhost / dev testing only — do not use in production without proper CA verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        if ($verbose_stream) {
            curl_setopt($ch, CURLOPT_STDERR, $verbose_stream);
        }
        $resp_body = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_err = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($verbose_stream) {
            fflush($verbose_stream);
            fclose($verbose_stream);
        }
        if ($curl_errno !== 0) {
            @file_put_contents($logs_dir . '/eway_error.log', date('c') . ' errno=' . $curl_errno . ' ' . $curl_err . "\n", FILE_APPEND | LOCK_EX);
        }

        auragold_eway_log_line(
            date('Y-m-d H:i:s')
            . ' HTTP ' . $http_code
            . ' docNo=' . ($eway_data['docNo'] ?? '')
            . ' resp=' . (is_string($resp_body) ? $resp_body : '')
        );

        if ($resp_body === false || $resp_body === '') {
            return ['status' => false, 'error' => 'Generate request failed: ' . ($curl_err ?: 'empty response'), 'raw' => null];
        }

        $response = json_decode($resp_body, true);
        if (!is_array($response)) {
            return ['status' => false, 'error' => 'Response is not JSON: ' . substr((string) $resp_body, 0, 800), 'raw' => $resp_body];
        }

        $ewb = '';
        if (function_exists('ewaybill_extract_eway_no_from_api_response')) {
            $ewb = ewaybill_extract_eway_no_from_api_response($response);
        }
        if ($ewb === '') {
            $ewb = (string) (
                $response['ewayBillNo']
                ?? $response['eway_bill_no']
                ?? $response['ewayBillNumber']
                ?? $response['EwbNo']
                ?? $response['ewbNo']
                ?? ''
            );
            if ($ewb === '' && isset($response['data']['ewayBillNo'])) {
                $ewb = (string) $response['data']['ewayBillNo'];
            }
            if ($ewb === '' && isset($response['Data']['ewayBillNo'])) {
                $ewb = (string) $response['Data']['ewayBillNo'];
            }
            if ($ewb === '' && isset($response['data']['EwbNo'])) {
                $ewb = (string) $response['data']['EwbNo'];
            }
        }
        $status_cd_ok = (isset($response['status_cd']) && (string) $response['status_cd'] === '1')
            || (isset($response['Status']) && (string) $response['Status'] === '1');
        $successByCd = function_exists('ewaybill_status_cd_indicates_success')
            ? ewaybill_status_cd_indicates_success($response) === true
            : $status_cd_ok;

        if ($successByCd && $ewb === '') {
            return [
                'status' => true,
                'eway_bill_no' => '',
                'eway_bill_date' => '',
                'valid_upto' => '',
                'raw' => $response,
                'success_without_eway_no' => true,
            ];
        }

        if ($ewb !== '') {
            $dt = null;
            foreach ([$response, $response['data'] ?? null, $response['Data'] ?? null, $response['result'] ?? null, $response['Result'] ?? null] as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $dt = $node['ewayBillDate'] ?? $node['EwbDt'] ?? null;
                if ($dt !== null && (string) $dt !== '') {
                    break;
                }
            }
            $date_out = date('Y-m-d H:i:s');
            if ($dt !== null && (string) $dt !== '') {
                $ts = strtotime((string) $dt);
                if ($ts !== false) {
                    $date_out = date('Y-m-d H:i:s', $ts);
                }
            }
            $valid_upto = '';
            foreach ([$response, $response['data'] ?? null, $response['Data'] ?? null, $response['result'] ?? null, $response['Result'] ?? null] as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $vu = $node['validUpto'] ?? $node['validTill'] ?? null;
                if ($vu !== null && (string) $vu !== '') {
                    $valid_upto = is_string($vu) ? $vu : (string) $vu;
                    break;
                }
            }

            return [
                'status' => true,
                'eway_bill_no' => (string) $ewb,
                'eway_bill_date' => $date_out,
                'valid_upto' => $valid_upto,
                'raw' => $response,
            ];
        }

        /** WhiteBooks/NIC: failures often use status_cd "0" + nested error (not top-level message). */
        $status_cd = $response['status_cd'] ?? $response['Status'] ?? null;
        if ($status_cd !== null && (string) $status_cd === '0') {
            $sug702 = function_exists('auragold_eway_parse_nic_suggested_km') ? auragold_eway_parse_nic_suggested_km($response) : null;
            if ($sug702 !== null && $sug702 > 0 && empty($invoice['_nic_702_retry'])) {
                $inv2 = $invoice;
                $inv2['distance'] = (float) $sug702;
                if (!empty($inv2['raw_extra']) && is_array($inv2['raw_extra'])) {
                    $inv2['raw_extra']['transDistance'] = (string) max(1, (int) round($sug702));
                }
                $inv2['_nic_702_retry'] = true;

                return generateEwayBill($inv2);
            }
            $nested = $response['error'] ?? null;
            $msg = '';
            if (is_array($nested)) {
                $msg = (string) ($nested['message'] ?? $nested['Message'] ?? '');
            }
            if ($msg === '') {
                $msg = (string) ($response['message'] ?? $response['Message'] ?? '');
            }
            if ($msg === '') {
                $msg = 'NIC/WhiteBooks rejected the request (status_cd=0). Check payload vs portal rules; see admin/logs/eway_log.txt.';
            }
            $infoPlain = function_exists('auragold_eway_nic_error_info_text') ? auragold_eway_nic_error_info_text(is_array($nested) ? $nested : null) : '';
            if ($infoPlain !== '' && stripos($msg, $infoPlain) === false) {
                $msg = trim($infoPlain . ' ' . $msg);
            }
            if (stripos($msg, '238') !== false || preg_match('/"errorCodes"\s*:\s*"238"/', $msg)) {
                $msg = 'Invalid or missing API auth token (e-Way error 238). Run Authenticate in Set Software → e-Way Bill API (e-Way portal username/password), then try again. Original: ' . $msg;
            }
            if ($infoPlain !== '' && (stripos($infoPlain, 'distance') !== false || stripos($infoPlain, 'pincod') !== false)) {
                $msg .= ' Set "Distance (km)" to 0 to use the NIC master distance for the two PINs, or enter a value within the allowed tolerance of that distance, then save the invoice and try again.';
            }

            return ['status' => false, 'error' => $msg, 'raw' => $response];
        }

        $err = $response['message'] ?? $response['error'] ?? $response['ErrorMessage'] ?? $response;

        return [
            'status' => false,
            'error' => is_string($err) ? $err : json_encode($err),
            'raw' => $response,
        ];
    }
}

if (!function_exists('auragold_eway_mask_secret')) {
    function auragold_eway_mask_secret(string $s, int $edges = 3): string
    {
        $s = trim($s);
        $len = strlen($s);
        if ($len <= $edges * 2) {
            return $len > 0 ? str_repeat('•', min(12, $len)) : '(empty)';
        }

        return substr($s, 0, $edges) . '…' . substr($s, -$edges);
    }
}

if (!function_exists('auragold_eway_test_authentication')) {
    /**
     * Validates header-based credentials + GSTIN (no separate auth API). Optionally POSTs a minimal generate to verify HTTP.
     *
     * @param mixed $conn mysqli branch connection
     * @return array{ok:bool, message:string, authtoken_received:bool, generate_url:string, branch_gstin:string, credential_flags:array, api_response?:array, curl_error?:string, raw_body?:string, http_code?:int}
     */
    function auragold_eway_test_authentication($conn, ?string $gstin_override = null): array
    {
        $cred = auragold_eway_credentials();
        $flags = [
            'email' => $cred['email'] !== '',
            'client_id' => $cred['client_id'] !== '',
            'client_secret' => $cred['client_secret'] !== '',
            'ip_address' => $cred['ip_address'] !== '',
        ];
        if (!($flags['email'] && $flags['client_id'] && $flags['client_secret'])) {
            $missing = [];
            if (!$flags['email']) {
                $missing[] = 'WHITEBOOKS_EMAIL';
            }
            if (!$flags['client_id']) {
                $missing[] = 'WHITEBOOKS_CLIENT_ID';
            }
            if (!$flags['client_secret']) {
                $missing[] = 'WHITEBOOKS_CLIENT_SECRET';
            }

            return [
                'ok' => false,
                'message' => 'Missing: ' . implode(', ', $missing) . '. Add to admin/includes/eway_whitebooks_credentials.local.php (or env). For email: use the same address as your WhiteBooks portal login (or set tbl_users.EmailId for the logged-in user to reuse it automatically).',
                'authtoken_received' => false,
                'generate_url' => $cred['generate_url'],
                'branch_gstin' => '',
                'credential_flags' => $flags,
            ];
        }

        $gst = $gstin_override !== null ? strtoupper(preg_replace('/\s+/', '', $gstin_override)) : auragold_branch_gstin_for_eway($conn);
        if (strlen($gst) !== 15) {
            return [
                'ok' => false,
                'message' => 'Need a valid 15-character GSTIN (tbl_branches.gst_no or WHITEBOOKS_AUTH_GSTIN).',
                'authtoken_received' => false,
                'generate_url' => $cred['generate_url'],
                'branch_gstin' => $gst,
                'credential_flags' => $flags,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Header-based settings OK (email, client_id, client_secret, gstin). ip_address sent will be: ' . $cred['ip_address'] . '. Full test: save a sale invoice ≥ ₹50,000 with vehicle & distance; check admin/logs/eway_log.txt.',
            'authtoken_received' => true,
            'generate_url' => $cred['generate_url'],
            'branch_gstin' => $gst,
            'credential_flags' => $flags,
        ];
    }
}
