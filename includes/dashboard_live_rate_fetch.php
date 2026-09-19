<?php
/**
 * Fetch live metal rates from whitelisted public rate pages (AED/gram or USD/oz).
 */

if (!function_exists('auragold_dashboard_live_rate_allowed_urls')) {
    /**
     * @return list<string>
     */
    function auragold_dashboard_live_rate_allowed_urls(): array
    {
        return [
            'https://dubaicityofgold.com/',
            'https://dubaicityofgold.com',
            'https://igold.ae/gold-rate',
            'https://ae.fkjewellers.com/pages/today-gold-price-in-uae-gold-rate',
            'https://ae.fkjewellers.com/pages/today-gold-price-in-uae-gold-rate-in-uae',
            'https://www.kitco.com/',
            'https://www.kitco.com',
            'https://goldprice.org/',
            'https://goldprice.org',
        ];
    }
}

if (!function_exists('auragold_dashboard_live_rate_normalize_url')) {
    function auragold_dashboard_live_rate_normalize_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Canonical FK page that embeds the karat table
        if (preg_match('#^https://ae\.fkjewellers\.com/pages/today-gold-price-in-uae-gold-rate/?$#i', $url)) {
            return 'https://ae.fkjewellers.com/pages/today-gold-price-in-uae-gold-rate-in-uae';
        }
        return $url;
    }
}

if (!function_exists('auragold_dashboard_live_rate_is_allowed')) {
    function auragold_dashboard_live_rate_is_allowed(string $url, $conn = null): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $allowed = auragold_dashboard_live_rate_allowed_urls();
        if (in_array($url, $allowed, true)) {
            return true;
        }
        $norm = rtrim($url, '/');
        foreach ($allowed as $a) {
            if (rtrim($a, '/') === $norm) {
                return true;
            }
        }
        // Also allow active URLs from Metal Rates Url master
        if ($conn instanceof mysqli && function_exists('auragold_metal_rate_url_is_active')) {
            return auragold_metal_rate_url_is_active($conn, $url);
        }
        if (function_exists('auragold_metal_rate_url_is_active')) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli) {
                return auragold_metal_rate_url_is_active($conn, $url);
            }
        }
        return false;
    }
}

if (!function_exists('auragold_dashboard_live_rate_http_get')) {
    /**
     * @return array{ok:bool,body:string,error:string,http_code:int}
     */
    function auragold_dashboard_live_rate_http_get(string $url): array
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($errno || $body === false) {
                return ['ok' => false, 'body' => '', 'error' => $err !== '' ? $err : 'cURL failed', 'http_code' => $code];
            }
            if ($code >= 400) {
                return ['ok' => false, 'body' => (string) $body, 'error' => 'HTTP ' . $code, 'http_code' => $code];
            }
            return ['ok' => true, 'body' => (string) $body, 'error' => '', 'http_code' => $code];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$ua}\r\nAccept: text/html\r\n",
                'timeout' => 25,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return ['ok' => false, 'body' => '', 'error' => 'Could not download page', 'http_code' => 0];
        }
        return ['ok' => true, 'body' => (string) $body, 'error' => '', 'http_code' => 200];
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_number')) {
    function auragold_dashboard_live_rate_parse_number(string $raw): ?float
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $raw = preg_replace('/[^\d.,\-]/', '', $raw) ?? '';
        $raw = str_replace(',', '', $raw);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $n = (float) $raw;
        return $n > 0 ? $n : null;
    }
}

if (!function_exists('auragold_dashboard_live_rate_karat_from_label')) {
    function auragold_dashboard_live_rate_karat_from_label(string $label): ?int
    {
        if (preg_match('/(\d+)\s*K/i', $label, $m)) {
            $k = (int) $m[1];
            return ($k >= 1 && $k <= 24) ? $k : null;
        }
        return null;
    }
}

if (!function_exists('auragold_dashboard_live_rate_purity_from_label')) {
    function auragold_dashboard_live_rate_purity_from_label(string $label): ?int
    {
        if (preg_match('/\b(999|995|958|925|916|900|875|800|750)\b/', $label, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}

if (!function_exists('auragold_dashboard_live_rate_expand_to_labels')) {
    /**
     * Map parsed rates onto sheet labels; derive missing gold/silver rows from pure rate.
     *
     * @param array<string,float> $parsed  keys like 24K, 22K, 999, gold, silver
     * @param list<string>        $labels
     * @return array<string,float>
     */
    function auragold_dashboard_live_rate_expand_to_labels(array $parsed, array $labels, string $metal): array
    {
        $out = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            if (isset($parsed[$label])) {
                $out[$label] = round((float) $parsed[$label], 2);
                continue;
            }
            // Case-insensitive / loose match
            foreach ($parsed as $k => $v) {
                if (strcasecmp((string) $k, $label) === 0) {
                    $out[$label] = round((float) $v, 2);
                    continue 2;
                }
            }
            if ($metal === 'gold') {
                $karat = auragold_dashboard_live_rate_karat_from_label($label);
                $base24 = $parsed['24K'] ?? $parsed['gold'] ?? null;
                if ($karat !== null && $base24 !== null && $base24 > 0) {
                    $out[$label] = round($base24 * ($karat / 24.0), 2);
                }
            } elseif ($metal === 'silver' || $metal === 'platinum') {
                $purity = auragold_dashboard_live_rate_purity_from_label($label);
                $base = $parsed['999'] ?? $parsed[$metal] ?? null;
                if ($purity !== null && $base !== null && $base > 0) {
                    $out[$label] = round($base * ($purity / 999.0), 2);
                }
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_dashboard_live_rate_usd_per_oz_to_base_per_gram')) {
    /**
     * Convert USD/troy-oz to base currency per gram using tbl_currency_exchange_rate.
     * Exchange rate: base units per 1 USD (e.g. 3.67 AED per 1 USD).
     */
    function auragold_dashboard_live_rate_usd_per_oz_to_base_per_gram($conn, float $usdPerOz): ?float
    {
        if ($usdPerOz <= 0 || !$conn) {
            return null;
        }
        require_once __DIR__ . '/dashboard_currency_display.php';
        $currencies = function_exists('getList')
            ? getList('SELECT id, name, symbol, is_base FROM tbl_currency WHERE status = 1 ORDER BY is_base DESC, name ASC')
            : [];
        if (!is_array($currencies)) {
            $currencies = [];
        }
        $usdId = 0;
        foreach ($currencies as $c) {
            $name = strtoupper(trim((string) ($c['name'] ?? '')));
            $sym = strtoupper(trim((string) ($c['symbol'] ?? '')));
            if ($name === 'USD' || $sym === 'USD' || $sym === '$') {
                $usdId = (int) ($c['id'] ?? 0);
                break;
            }
        }
        if ($usdId <= 0) {
            return null;
        }
        $map = auragold_dashboard_currency_exchange_map($conn);
        $usdToBase = isset($map[$usdId]) ? (float) $map[$usdId] : 0.0;
        if ($usdToBase <= 0) {
            return null;
        }
        // 1 troy ounce = 31.1034768 grams
        $usdPerGram = $usdPerOz / 31.1034768;
        return round($usdPerGram * $usdToBase, 2);
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_dubaicityofgold')) {
    /** @return array<string,float> */
    function auragold_dashboard_live_rate_parse_dubaicityofgold(string $html): array
    {
        $rates = [];
        if (preg_match_all(
            '/sortd-gold-type[^>]*>([^<]+)<\/span>\s*<span[^>]*sortd-gold-value[^>]*>([^<]+)<\/span>/i',
            $html,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $row) {
                $type = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $val = auragold_dashboard_live_rate_parse_number($row[2]);
                if ($val === null) {
                    continue;
                }
                if (preg_match('/(\d+)\s*K/i', $type, $km)) {
                    $rates[$km[1] . 'K'] = $val;
                }
            }
        }
        return $rates;
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_igold')) {
    /** @return array<string,float> */
    function auragold_dashboard_live_rate_parse_igold(string $html): array
    {
        $rates = [];
        if (preg_match('/chart-content[^>]*>\s*Gold\s*<\/div>\s*<div[^>]*chart-content[^>]*>\s*([0-9.,]+)\s*<\/div>/i', $html, $m)) {
            $n = auragold_dashboard_live_rate_parse_number($m[1]);
            if ($n !== null) {
                $rates['gold'] = $n;
                $rates['24K'] = $n;
            }
        }
        if (preg_match('/chart-content[^>]*>\s*Silver\s*<\/div>\s*<div[^>]*chart-content[^>]*>\s*([0-9.,]+)\s*<\/div>/i', $html, $m)) {
            $n = auragold_dashboard_live_rate_parse_number($m[1]);
            if ($n !== null) {
                $rates['silver'] = $n;
                $rates['999'] = $n;
            }
        }
        return $rates;
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_fkjewellers')) {
    /** @return array<string,float> */
    function auragold_dashboard_live_rate_parse_fkjewellers(string $html): array
    {
        $rates = [];
        if (preg_match_all('/1\s*Gram\s*(\d+)\s*K\s*<\/td>\s*<td[^>]*>\s*<span[^>]*>\s*<span[^>]*>\s*([0-9.,]+)/i', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $n = auragold_dashboard_live_rate_parse_number($row[2]);
                if ($n !== null) {
                    $rates[(int) $row[1] . 'K'] = $n;
                }
            }
        }
        if (!isset($rates['24K']) && preg_match('/class="gold_24"[^>]*>(.*?)<\/span>/is', $html, $m)) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])) ?? '');
            $n = auragold_dashboard_live_rate_parse_number($text);
            if ($n !== null) {
                $rates['24K'] = $n;
                $rates['gold'] = $n;
            }
        }
        return $rates;
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_kitco')) {
    /**
     * Kitco embeds USD/oz bids in page JSON.
     *
     * @return array{ounce:array<string,float>,note?:string}
     */
    function auragold_dashboard_live_rate_parse_kitco(string $html): array
    {
        $ounce = [];
        foreach (['Gold', 'Silver', 'Platinum'] as $metalName) {
            if (preg_match('/"' . $metalName . '":\s*\{\s*"results":\s*\[\s*\{[^}]*?"bid"\s*:\s*([0-9.]+)/i', $html, $m)) {
                $n = auragold_dashboard_live_rate_parse_number($m[1]);
                if ($n !== null) {
                    $ounce[strtolower($metalName)] = $n;
                }
            }
        }
        return ['ounce' => $ounce];
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_bullions_co_in')) {
    /**
     * bullions.co.in — gold/silver per gram in INR from rate tables.
     *
     * @return array<string,float>
     */
    function auragold_dashboard_live_rate_parse_bullions_co_in(string $html, string $metal = 'gold'): array
    {
        $rates = [];
        $metal = strtolower(trim($metal));
        if ($metal === 'gold') {
            if (preg_match_all(
                '/Gold\s+(\d+)\s+Karat[\s\S]*?<\/td>\s*<td[^>]*>\s*([0-9][0-9,]*)/i',
                $html,
                $m,
                PREG_SET_ORDER
            )) {
                foreach ($m as $row) {
                    $k = (int) ($row[1] ?? 0);
                    $val = auragold_dashboard_live_rate_parse_number((string) ($row[2] ?? ''));
                    if ($val !== null && $k >= 1 && $k <= 24) {
                        $rates[$k . 'K'] = $val;
                    }
                }
            }
            if ($rates === [] && preg_match('/Gold\s+24\s+Karat[\s\S]{0,200}?([0-9]{1,3}[0-9,]*)/i', $html, $m24)) {
                $v24 = auragold_dashboard_live_rate_parse_number((string) ($m24[1] ?? ''));
                if ($v24 !== null) {
                    $rates['24K'] = $v24;
                    $rates['gold'] = $v24;
                }
            }
        } elseif ($metal === 'silver') {
            if (preg_match(
                '/Silver\s+999\s+Fine[\s\S]*?<\/td>\s*<td[^>]*>\s*([0-9][0-9,]*)/i',
                $html,
                $sm
            )) {
                $val = auragold_dashboard_live_rate_parse_number((string) ($sm[1] ?? ''));
                if ($val !== null) {
                    $rates['999'] = $val;
                    $rates['silver'] = $val;
                }
            }
        }

        return $rates;
    }
}

if (!function_exists('auragold_dashboard_live_rate_parse_generic_html')) {
    /**
     * Best-effort parser for allowed URLs without a dedicated adapter.
     *
     * @return array<string,float>
     */
    function auragold_dashboard_live_rate_parse_generic_html(string $html, string $metal): array
    {
        $metal = strtolower(trim($metal));
        $parsed = auragold_dashboard_live_rate_parse_bullions_co_in($html, $metal);
        if ($parsed !== []) {
            return $parsed;
        }
        if ($metal === 'gold') {
            $parsed = auragold_dashboard_live_rate_parse_dubaicityofgold($html);
            if ($parsed !== []) {
                return $parsed;
            }
            $parsed = auragold_dashboard_live_rate_parse_fkjewellers($html);
            if ($parsed !== []) {
                return $parsed;
            }
            $parsed = auragold_dashboard_live_rate_parse_igold($html);
            if (isset($parsed['24K']) || isset($parsed['gold'])) {
                return $parsed;
            }
            if (preg_match_all('/(\d{1,2})\s*[Kk][^0-9]{0,24}([0-9]{2,3}[0-9,]*\.?\d*)/', $html, $m, PREG_SET_ORDER)) {
                foreach ($m as $row) {
                    $k = (int) ($row[1] ?? 0);
                    $val = auragold_dashboard_live_rate_parse_number((string) ($row[2] ?? ''));
                    if ($val !== null && $k >= 1 && $k <= 24) {
                        $parsed[$k . 'K'] = $val;
                    }
                }
            }
        } elseif ($metal === 'silver' || $metal === 'platinum') {
            $all = auragold_dashboard_live_rate_parse_igold($html);
            if ($metal === 'silver' && isset($all['silver'])) {
                return ['999' => (float) $all['silver'], 'silver' => (float) $all['silver']];
            }
            if (preg_match('/\b999\b[^0-9]{0,24}([0-9]{1,3}[0-9,]*\.?\d*)/', $html, $sm)) {
                $val = auragold_dashboard_live_rate_parse_number((string) ($sm[1] ?? ''));
                if ($val !== null) {
                    return ['999' => $val, $metal => $val];
                }
            }
        }

        return $parsed;
    }
}

if (!function_exists('auragold_dashboard_fetch_live_rates')) {
    /**
     * @param list<string> $sheet_labels
     * @return array{status:string,message:string,source_url:string,rows:list<array{carat:string,rate:string}>,ounce_rate?:string,meta?:array<string,mixed>}
     */
    function auragold_dashboard_fetch_live_rates($conn, string $url, string $metal, array $sheet_labels): array
    {
        $metal = strtolower(trim($metal));
        $url = trim($url);
        $fetchUrl = auragold_dashboard_live_rate_normalize_url($url);

        if (!auragold_dashboard_live_rate_is_allowed($url, $conn) && !auragold_dashboard_live_rate_is_allowed($fetchUrl, $conn)) {
            return [
                'status' => 'error',
                'message' => 'This URL is not active in Metal Rates Url (Set Software → Masters), or is not allowed.',
                'source_url' => $url,
                'rows' => [],
            ];
        }

        if ($metal === 'diamond' || strpos($metal, 'diamond') === 0) {
            return [
                'status' => 'error',
                'message' => 'Live diamond rates are not available from these gold/silver websites. Enter diamond rates manually.',
                'source_url' => $url,
                'rows' => [],
            ];
        }

        $host = strtolower((string) (parse_url($fetchUrl, PHP_URL_HOST) ?? ''));

        if ($host === 'goldprice.org' || $host === 'www.goldprice.org') {
            return [
                'status' => 'error',
                'message' => 'goldprice.org blocks automated requests. Please use Dubai City of Gold, iGold, or FK Jewellers.',
                'source_url' => $url,
                'rows' => [],
            ];
        }

        $http = auragold_dashboard_live_rate_http_get($fetchUrl);
        if (!$http['ok']) {
            return [
                'status' => 'error',
                'message' => 'Could not download rates: ' . ($http['error'] !== '' ? $http['error'] : 'unknown error'),
                'source_url' => $url,
                'rows' => [],
            ];
        }

        $html = $http['body'];
        $parsed = [];
        $ounceRate = null;
        $note = '';

        if (strpos($host, 'dubaicityofgold') !== false) {
            if ($metal !== 'gold') {
                return [
                    'status' => 'error',
                    'message' => 'Dubai City of Gold publishes gold jewellery rates only. Switch to the Gold tab.',
                    'source_url' => $url,
                    'rows' => [],
                ];
            }
            $parsed = auragold_dashboard_live_rate_parse_dubaicityofgold($html);
        } elseif (strpos($host, 'igold.ae') !== false || strpos($host, 'lgold.ae') !== false) {
            $all = auragold_dashboard_live_rate_parse_igold($html);
            if ($metal === 'gold') {
                $parsed = $all;
            } elseif ($metal === 'silver') {
                $parsed = isset($all['silver']) ? ['silver' => $all['silver'], '999' => $all['silver']] : [];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'iGold page provides gold and silver pure metal rates only.',
                    'source_url' => $url,
                    'rows' => [],
                ];
            }
            if ($metal === 'gold' && isset($parsed['24K'])) {
                $note = 'Lower carats calculated from 24K (pure) rate.';
            }
        } elseif (strpos($host, 'fkjewellers') !== false) {
            if ($metal !== 'gold') {
                return [
                    'status' => 'error',
                    'message' => 'FK Jewellers page provides gold rates only. Switch to the Gold tab.',
                    'source_url' => $url,
                    'rows' => [],
                ];
            }
            $parsed = auragold_dashboard_live_rate_parse_fkjewellers($html);
            if (count($parsed) === 1 && isset($parsed['24K'])) {
                $note = 'Other carats calculated from 24K rate.';
            }
        } elseif (strpos($host, 'kitco.com') !== false) {
            $kitco = auragold_dashboard_live_rate_parse_kitco($html);
            $key = $metal === 'platinum' ? 'platinum' : ($metal === 'silver' ? 'silver' : 'gold');
            if ($metal === 'gold' || $metal === 'silver' || $metal === 'platinum') {
                $oz = $kitco['ounce'][$key] ?? null;
                if ($oz === null) {
                    return [
                        'status' => 'error',
                        'message' => 'Could not find ' . $metal . ' spot price on Kitco.',
                        'source_url' => $url,
                        'rows' => [],
                    ];
                }
                $ounceRate = $oz;
                $perGram = auragold_dashboard_live_rate_usd_per_oz_to_base_per_gram($conn, $oz);
                if ($perGram === null) {
                    return [
                        'status' => 'error',
                        'message' => 'Kitco rates are USD/oz. Add a USD exchange rate in Masters → Currency (base units per 1 USD) to convert to per-gram.',
                        'source_url' => $url,
                        'rows' => [],
                        'ounce_rate' => number_format($oz, 2, '.', ''),
                    ];
                }
                if ($metal === 'gold') {
                    $parsed = ['24K' => $perGram, 'gold' => $perGram];
                } else {
                    $parsed = ['999' => $perGram, $metal => $perGram];
                }
                $note = 'Converted from Kitco USD/oz to base currency per gram using your exchange rate.';
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Kitco fetch supports gold, silver, and platinum only.',
                    'source_url' => $url,
                    'rows' => [],
                ];
            }
        } elseif (strpos($host, 'bullions.co.in') !== false) {
            $parsed = auragold_dashboard_live_rate_parse_bullions_co_in($html, $metal);
            if ($metal === 'gold' && isset($parsed['24K']) && count($parsed) === 1) {
                $note = 'Other carats calculated from 24K rate.';
            }
        } else {
            $parsed = auragold_dashboard_live_rate_parse_generic_html($html, $metal);
            if ($parsed === []) {
                return [
                    'status' => 'error',
                    'message' => 'Could not read rates from this website. Try Dubai City of Gold, iGold, FK Jewellers, or bullions.co.in.',
                    'source_url' => $url,
                    'rows' => [],
                ];
            }
            $note = 'Rates parsed using generic table detection.';
        }

        if ($parsed === []) {
            return [
                'status' => 'error',
                'message' => 'Downloaded the page but could not find rate values. The site layout may have changed.',
                'source_url' => $url,
                'rows' => [],
            ];
        }

        $expanded = auragold_dashboard_live_rate_expand_to_labels($parsed, $sheet_labels, $metal);
        if ($expanded === []) {
            // Fall back to returning whatever we parsed with original keys
            foreach ($parsed as $k => $v) {
                if (in_array($k, ['gold', 'silver', 'platinum'], true)) {
                    continue;
                }
                $expanded[(string) $k] = round((float) $v, 2);
            }
        }

        if ($expanded === []) {
            return [
                'status' => 'error',
                'message' => 'Rates were found but could not be matched to your sheet carat labels.',
                'source_url' => $url,
                'rows' => [],
            ];
        }

        $rows = [];
        foreach ($expanded as $carat => $rate) {
            $rows[] = [
                'carat' => (string) $carat,
                'rate' => number_format((float) $rate, 2, '.', ''),
            ];
        }

        $msg = 'Rates fetched successfully. Review the sheet, then click Save.';
        if ($note !== '') {
            $msg .= ' (' . $note . ')';
        }

        $out = [
            'status' => 'ok',
            'message' => $msg,
            'source_url' => $url,
            'rows' => $rows,
        ];
        if ($ounceRate !== null) {
            $out['ounce_rate'] = number_format((float) $ounceRate, 2, '.', '');
        }
        return $out;
    }
}
