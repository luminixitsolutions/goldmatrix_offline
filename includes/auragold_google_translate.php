<?php
/**
 * Google Cloud Translation API v2 helper + i18n string cache in DB.
 * Set AURAGOLD_GOOGLE_TRANSLATE_API_KEY in config.php (or env) for the full
 * Google language list, batch translate, and automatic UI translation.
 */
if (!function_exists('auragold_get_google_translate_api_key')) {
    function auragold_invalidate_google_translate_key_cache() {
        unset($GLOBALS['auragold_google_translate_key_cached']);
    }

    function auragold_ensure_google_translate_key_column($conn) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_settings` LIKE 'google_translate_api_key'");
        if ($r && mysqli_num_rows($r) > 0) {
            mysqli_free_result($r);
            $done[$key] = true;
            return true;
        }
        if ($r) {
            mysqli_free_result($r);
        }
        $ok = (bool) @mysqli_query(
            $conn,
            "ALTER TABLE `tbl_settings` ADD COLUMN `google_translate_api_key` varchar(300) NULL DEFAULT NULL COMMENT 'Google Cloud Translation (Language settings)'"
        );
        if ($ok) {
            $done[$key] = true;
        }
        return $ok;
    }

    function auragold_get_google_translate_api_key() {
        if (array_key_exists('auragold_google_translate_key_cached', $GLOBALS)) {
            return (string) $GLOBALS['auragold_google_translate_key_cached'];
        }
        $out = '';
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            auragold_ensure_google_translate_key_column($conn);
            if (function_exists('getRecord')) {
                $row = @getRecord("SELECT `google_translate_api_key` AS k FROM `tbl_settings` ORDER BY `id` ASC LIMIT 1");
                if (is_array($row) && isset($row['k']) && trim((string) $row['k']) !== '') {
                    $out = trim((string) $row['k']);
                }
            }
        }
        if ($out === '' && !empty($_ENV['AURAGOLD_GOOGLE_TRANSLATE_API_KEY'])) {
            $out = trim((string) $_ENV['AURAGOLD_GOOGLE_TRANSLATE_API_KEY']);
        }
        if ($out === '') {
            $e = getenv('AURAGOLD_GOOGLE_TRANSLATE_API_KEY');
            if ($e !== false && trim((string) $e) !== '') {
                $out = trim((string) $e);
            }
        }
        if ($out === '' && defined('AURAGOLD_GOOGLE_TRANSLATE_API_KEY')) {
            $out = trim((string) AURAGOLD_GOOGLE_TRANSLATE_API_KEY);
        }
        $GLOBALS['auragold_google_translate_key_cached'] = $out;
        return $out;
    }

    function auragold_is_google_translate_api_key_in_database($conn) {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return false;
        }
        auragold_ensure_google_translate_key_column($conn);
        $r = @getRecord("SELECT (IFNULL(LENGTH(`google_translate_api_key`),0) > 0) AS b FROM `tbl_settings` ORDER BY `id` ASC LIMIT 1");
        return is_array($r) && !empty($r['b']);
    }

    function auragold_save_google_translate_api_key($conn, $keyOrNull) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        auragold_ensure_google_translate_key_column($conn);
        if ($keyOrNull === null || (string) $keyOrNull === '') {
            if (!@mysqli_query($conn, "UPDATE `tbl_settings` SET `google_translate_api_key` = NULL LIMIT 1")) {
                return (bool) @mysqli_query(
                    $conn,
                    "INSERT INTO `tbl_settings` (`id`, `google_translate_api_key`) VALUES (1, NULL) ON DUPLICATE KEY UPDATE `google_translate_api_key` = NULL"
                );
            }
        } else {
            $k  = (string) $keyOrNull;
            if (strlen($k) > 280) {
                $k = substr($k, 0, 280);
            }
            $k = mysqli_real_escape_string($conn, $k);
            if (!@mysqli_query($conn, "UPDATE `tbl_settings` SET `google_translate_api_key` = '{$k}' LIMIT 1")) {
                (bool) @mysqli_query(
                    $conn,
                    "INSERT INTO `tbl_settings` (`id`, `google_translate_api_key`) VALUES (1, '{$k}') ON DUPLICATE KEY UPDATE `google_translate_api_key` = '{$k}'"
                );
            }
        }
        if (function_exists('auragold_invalidate_google_translate_key_cache')) {
            auragold_invalidate_google_translate_key_cache();
        }
        $GLOBALS['auragold_google_translate_key_cached'] = $keyOrNull ? trim((string) $keyOrNull) : '';
        return true;
    }

    function auragold_google_translate_languages_dir() {
        $d = __DIR__ . '/../assets/data';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }

    function auragold_google_translate_languages_cache_file() {
        return auragold_google_translate_languages_dir() . '/google-translate-languages.cache.json';
    }

    function auragold_google_translate_languages_fallback_file() {
        return auragold_google_translate_languages_dir() . '/google-translate-languages.fallback.json';
    }

    function auragold_ensure_i18n_cache_table($conn) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_auragold_i18n_cache'");
        if ($t && mysqli_num_rows($t) > 0) {
            mysqli_free_result($t);
            $done[$key] = true;
            return true;
        }
        if ($t) {
            mysqli_free_result($t);
        }
        $ok = (bool) @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `tbl_auragold_i18n_cache` (
  `locale` varchar(32) NOT NULL,
  `msg_key` varchar(191) NOT NULL,
  `msg_value` longtext,
  `updated_at` int unsigned NULL,
  PRIMARY KEY (`locale`, `msg_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if ($ok) {
            $done[$key] = true;
        }
        return $ok;
    }

    function auragold_i18n_locale_uses_db_cache($locale) {
        $loc = trim((string) $locale);
        if ($loc === '' || $loc === 'en' || preg_match('/^en(\-[A-Za-z0-9]+)*$/', $loc)) {
            return false;
        }
        return true;
    }

    function auragold_i18n_cache_reset_memory($locale = null) {
        if ($locale === null) {
            unset($GLOBALS['auragold_i18n_db_cache'], $GLOBALS['auragold_i18n_db_cache_loaded']);
            return;
        }
        $loc = (string) $locale;
        unset($GLOBALS['auragold_i18n_db_cache'][$loc], $GLOBALS['auragold_i18n_db_cache_loaded'][$loc]);
    }

    /**
     * Load all cached strings for a locale in one query (avoids ~180 lookups per page).
     */
    function auragold_i18n_cache_preload_locale($conn, $locale) {
        if (!$conn || !($conn instanceof mysqli) || !auragold_i18n_locale_uses_db_cache($locale)) {
            return;
        }
        $loc = (string) $locale;
        if (!empty($GLOBALS['auragold_i18n_db_cache_loaded'][$loc])) {
            return;
        }
        auragold_ensure_i18n_cache_table($conn);
        if (!isset($GLOBALS['auragold_i18n_db_cache']) || !is_array($GLOBALS['auragold_i18n_db_cache'])) {
            $GLOBALS['auragold_i18n_db_cache'] = [];
        }
        if (!isset($GLOBALS['auragold_i18n_db_cache_loaded']) || !is_array($GLOBALS['auragold_i18n_db_cache_loaded'])) {
            $GLOBALS['auragold_i18n_db_cache_loaded'] = [];
        }
        $GLOBALS['auragold_i18n_db_cache'][$loc] = [];
        $e = mysqli_real_escape_string($conn, $loc);
        $rows = function_exists('getList')
            ? getList("SELECT `msg_key`, `msg_value` FROM `tbl_auragold_i18n_cache` WHERE `locale` = '{$e}'")
            : [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $mk = (string) ($row['msg_key'] ?? '');
                if ($mk === '') {
                    continue;
                }
                $GLOBALS['auragold_i18n_db_cache'][$loc][$mk] = (string) ($row['msg_value'] ?? '');
            }
        }
        $GLOBALS['auragold_i18n_db_cache_loaded'][$loc] = true;
    }

    function auragold_i18n_cache_get($conn, $locale, $key) {
        if (!$conn) {
            return null;
        }
        $loc = (string) $locale;
        $k = (string) $key;
        $k = (strlen($k) > 191) ? substr($k, 0, 191) : $k;
        if (!empty($GLOBALS['auragold_i18n_db_cache_loaded'][$loc])) {
            $cache = $GLOBALS['auragold_i18n_db_cache'][$loc] ?? [];
            if (array_key_exists($k, $cache)) {
                return (string) $cache[$k];
            }
            return null;
        }
        auragold_ensure_i18n_cache_table($conn);
        $e = mysqli_real_escape_string($conn, $loc);
        $ek = mysqli_real_escape_string($conn, $k);
        $r = @getRecord("SELECT `msg_value` FROM `tbl_auragold_i18n_cache` WHERE `locale` = '{$e}' AND `msg_key` = '{$ek}' LIMIT 1");
        if (is_array($r) && array_key_exists('msg_value', $r)) {
            return (string) $r['msg_value'];
        }
        return null;
    }

    function auragold_i18n_cache_set($conn, $locale, $key, $value) {
        if (!$conn) {
            return false;
        }
        auragold_ensure_i18n_cache_table($conn);
        $loc = (string) $locale;
        $k = (string) $key;
        $k = (strlen($k) > 191) ? substr($k, 0, 191) : $k;
        $e = mysqli_real_escape_string($conn, $loc);
        $ek = mysqli_real_escape_string($conn, $k);
        $ev = mysqli_real_escape_string($conn, (string) $value);
        $t  = (int) time();
        $q  = "INSERT INTO `tbl_auragold_i18n_cache` (`locale`, `msg_key`, `msg_value`, `updated_at`)
              VALUES ('{$e}', '{$ek}', '{$ev}', {$t})
              ON DUPLICATE KEY UPDATE `msg_value` = VALUES(`msg_value`), `updated_at` = VALUES(`updated_at`)";
        $ok = (bool) @mysqli_query($conn, $q);
        if ($ok) {
            if (!isset($GLOBALS['auragold_i18n_db_cache']) || !is_array($GLOBALS['auragold_i18n_db_cache'])) {
                $GLOBALS['auragold_i18n_db_cache'] = [];
            }
            if (!isset($GLOBALS['auragold_i18n_db_cache'][$loc]) || !is_array($GLOBALS['auragold_i18n_db_cache'][$loc])) {
                $GLOBALS['auragold_i18n_db_cache'][$loc] = [];
            }
            $GLOBALS['auragold_i18n_db_cache'][$loc][$k] = (string) $value;
        }
        return $ok;
    }

    function auragold_i18n_cache_purge_locale($conn, $locale) {
        if (!$conn) {
            return;
        }
        $loc = (string) $locale;
        auragold_i18n_cache_reset_memory($loc);
        $e = mysqli_real_escape_string($conn, $loc);
        @mysqli_query($conn, "DELETE FROM `tbl_auragold_i18n_cache` WHERE `locale` = '{$e}'");
    }

    function auragold_http_get($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT, 'GoldMatrix/auragold');
            $r = @curl_exec($ch);
            curl_close($ch);
            return is_string($r) ? $r : '';
        }
        $c = @stream_context_create(
            [
                'http' => [
                    'timeout'    => 60,
                    'user_agent' => 'GoldMatrix/auragold',
                ],
            ]
        );
        $r = @file_get_contents($url, false, $c);
        return is_string($r) ? $r : '';
    }

    /**
     * @return list<array{language:string, name:string}>
     */
    function auragold_google_api_list_languages($target = 'en') {
        $key = auragold_get_google_translate_api_key();
        if ($key === '') {
            return [];
        }
        $url = 'https://translation.googleapis.com/language/translate/v2/languages?key='
            . rawurlencode($key) . '&target=' . rawurlencode((string) $target);
        $raw = auragold_http_get($url);
        $j   = is_string($raw) ? @json_decode($raw, true) : null;
        if (!is_array($j) || !isset($j['data']['languages']) || !is_array($j['data']['languages'])) {
            return [];
        }
        $out = [];
        foreach ($j['data']['languages'] as $row) {
            if (!is_array($row) || !isset($row['language'], $row['name'])) {
                continue;
            }
            $out[] = [
                'language' => (string) $row['language'],
                'name'     => (string) $row['name'],
            ];
        }
        return $out;
    }

    /**
     * @param list<string> $texts
     * @return list<string>|null
     */
    function auragold_google_translate_v2_batch(array $texts, $target, $source = 'en') {
        if ($texts === [] || (string) $source === (string) $target) {
            return $texts;
        }
        $key = auragold_get_google_translate_api_key();
        if ($key === '') {
            return null;
        }
        $src = (string) $source;
        $tgt = (string) $target;
        if ($src === $tgt) {
            return $texts;
        }
        $qparams = 'key=' . rawurlencode($key)
            . '&source=' . rawurlencode($src)
            . '&target=' . rawurlencode($tgt)
            . '&format=' . rawurlencode('text');
        foreach (array_values($texts) as $one) {
            $qparams .= '&q=' . rawurlencode((string) $one);
        }
        $url = 'https://translation.googleapis.com/language/translate/v2';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $qparams);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                ['Content-Type: application/x-www-form-urlencoded; charset=utf-8']
            );
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            $r = @curl_exec($ch);
            curl_close($ch);
        } else {
            $c = @stream_context_create(
                [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-type: application/x-www-form-urlencoded; charset=utf-8\r\n",
                        'content' => $qparams,
                        'timeout' => 120,
                    ],
                ]
            );
            $r = @file_get_contents($url, false, $c);
        }
        if (!is_string($r) || $r === '') {
            return null;
        }
        $j = @json_decode($r, true);
        if (!is_array($j) || !isset($j['data']['translations']) || !is_array($j['data']['translations'])) {
            return null;
        }
        $out = [];
        foreach ($j['data']['translations'] as $tr) {
            if (is_array($tr) && isset($tr['translatedText'])) {
                $out[] = (string) $tr['translatedText'];
            } else {
                $out[] = '';
            }
        }
        if (count($out) !== count($texts)) {
            return null;
        }
        return $out;
    }

    /**
     * @return string|null null if API not configured or error
     */
    function auragold_google_translate_one($text, $target, $source = 'en') {
        if ((string) $source === (string) $target) {
            return (string) $text;
        }
        $a = auragold_google_translate_v2_batch([(string) $text], (string) $target, (string) $source);
        if (!is_array($a) || !isset($a[0])) {
            return null;
        }
        return (string) $a[0];
    }

    /**
     * @return list<array{language:string, name:string}>
     */
    function auragold_load_languages_merged() {
        $byCode = [];
        $push = function (array $data) use (&$byCode) {
            if (empty($data['languages']) || !is_array($data['languages'])) {
                return;
            }
            foreach ($data['languages'] as $row) {
                if (is_array($row) && !empty($row['language']) && !empty($row['name'])) {
                    $c                 = (string) $row['language'];
                    $byCode[$c]        = [
                        'language' => $c,
                        'name'     => (string) $row['name'],
                    ];
                }
            }
        };
        $cf = auragold_google_translate_languages_cache_file();
        if (is_file($cf)) {
            $raw = @file_get_contents($cf);
            $j   = is_string($raw) ? @json_decode($raw, true) : null;
            if (is_array($j)) {
                $push($j);
            }
        }
        $fb = auragold_google_translate_languages_fallback_file();
        if (is_file($fb)) {
            $raw = @file_get_contents($fb);
            $j   = is_string($raw) ? @json_decode($raw, true) : null;
            if (is_array($j)) {
                $push($j);
            }
        }
        if (count($byCode) === 0) {
            $byCode['en'] = ['language' => 'en', 'name' => 'English'];
        } elseif (!isset($byCode['en'])) {
            $byCode['en'] = ['language' => 'en', 'name' => 'English'];
        }
        uasort(
            $byCode,
            function ($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            }
        );
        return [
            'languages' => array_values($byCode),
            'source'    => is_file($cf) ? 'cache_or_fallback' : (is_file($fb) ? 'fallback' : 'min'),
        ];
    }

    /**
     * @return array<string, true> language code (as stored) => true
     */
    function auragold_get_valid_language_codes_set() {
        static $out = null;
        if (is_array($out)) {
            return $out;
        }
        $j  = auragold_load_languages_merged();
        $m  = ['en' => true];
        $l  = is_array($j) && !empty($j['languages']) ? $j['languages'] : [];
        foreach ($l as $row) {
            if (is_array($row) && !empty($row['language'])) {
                $c = (string) $row['language'];
                $m[$c] = true;
            }
        }
        $out = $m;
        return $out;
    }

    /**
     * Build menu translation cache: English `locales/en.php` into $target, skip manual .php and existing DB.
     */
    function auragold_prewarm_i18n_for_locale($conn, $target) {
        if (!$conn) {
            return 0;
        }
        if ((string) $target === '' || (string) $target === 'en' || preg_match('/^en(\-[A-Za-z0-9]+)*$/', (string) $target)) {
            return 0;
        }
        $enFile = __DIR__ . '/locales/en.php';
        if (!is_file($enFile)) {
            return 0;
        }
        $en = @include $enFile;
        if (!is_array($en)) {
            return 0;
        }
        $manual = [];
        $mFile  = __DIR__ . '/locales/' . $target . '.php';
        if (is_file($mFile)) {
            $mo = @include $mFile;
            if (is_array($mo)) {
                $manual = $mo;
            }
        }
        auragold_ensure_i18n_cache_table($conn);
        auragold_i18n_cache_preload_locale($conn, (string) $target);
        $n     = 0;
        $batch = [];
        $keys  = [];
        $flush = function () use (&$batch, &$keys, $conn, $target, &$n) {
            if (count($batch) === 0) {
                return;
            }
            $tr = auragold_google_translate_v2_batch($batch, (string) $target, 'en');
            if (!is_array($tr) || count($tr) !== count($batch)) {
                for ($i = 0, $c = count($keys); $i < $c; $i++) {
                    $one = auragold_google_translate_one($batch[$i] ?? '', (string) $target, 'en');
                    if ($one === null) {
                        continue;
                    }
                    auragold_i18n_cache_set($conn, (string) $target, (string) $keys[$i], $one);
                    $n++;
                }
                $batch = [];
                $keys  = [];
                return;
            }
            for ($i = 0, $c = count($keys); $i < $c; $i++) {
                auragold_i18n_cache_set($conn, (string) $target, (string) $keys[$i], (string) $tr[$i]);
                $n++;
            }
            $batch = [];
            $keys  = [];
        };
        foreach ($en as $msgKey => $enText) {
            if (!is_string($enText) && !is_numeric($enText)) {
                continue;
            }
            $enText = (string) $enText;
            if ($enText === '' || (string) $msgKey === '') {
                continue;
            }
            if (isset($manual[$msgKey]) && trim((string) $manual[$msgKey]) !== '') {
                continue;
            }
            if (auragold_i18n_cache_get($conn, (string) $target, (string) $msgKey) !== null) {
                continue;
            }
            $keys[]  = (string) $msgKey;
            $batch[] = $enText;
            if (count($batch) >= 80) {
                $flush();
            }
        }
        $flush();
        return $n;
    }
}
