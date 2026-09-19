<?php
/**
 * App UI language (Set Software → Language). English in locales/en.php;
 * optional manual PHP overrides in locales/{code}.php; Google Translation + DB cache for other languages.
 */
require_once __DIR__ . '/auragold_google_translate.php';

if (!function_exists('auragold_get_allowed_locales')) {
    function auragold_ensure_app_locale_widen($conn) {
        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }
        static $done;
        if ($done) {
            return;
        }
        $done = true;
        @mysqli_query(
            $conn,
            "ALTER TABLE `tbl_settings` MODIFY `app_locale` varchar(32) NULL DEFAULT 'en' COMMENT 'UI (Google / i18n code)'"
        );
    }

    function auragold_ensure_app_locale_column($conn) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_settings` LIKE 'app_locale'");
        if ($r && mysqli_num_rows($r) > 0) {
            mysqli_free_result($r);
            auragold_ensure_app_locale_widen($conn);
            $done[$key] = true;
            return true;
        }
        if ($r) {
            mysqli_free_result($r);
        }
        @mysqli_query(
            $conn,
            "ALTER TABLE `tbl_settings` ADD COLUMN `app_locale` varchar(32) NULL DEFAULT 'en' COMMENT 'UI language (i18n / Google code)'"
        );
        auragold_ensure_app_locale_widen($conn);
        $done[$key] = true;
        return true;
    }

    function auragold_i18n_warm_db_cache($conn, $locale) {
        if (function_exists('auragold_i18n_cache_preload_locale')) {
            auragold_i18n_cache_preload_locale($conn, (string) $locale);
        }
    }

    function auragold_sanitize_app_locale($code) {
        $c = trim((string) $code);
        if ($c === '' || (strlen($c) > 24) || !preg_match('/^[a-zA-Z0-9\-]+$/', $c)) {
            return 'en';
        }
        if ($c === 'en' || $c === 'en-US' || $c === 'en-GB') {
            return 'en';
        }
        $set = auragold_get_valid_language_codes_set();
        if (isset($set[$c])) {
            return $c;
        }
        foreach (array_keys($set) as $k) {
            if (strcasecmp((string) $k, $c) === 0) {
                return (string) $k;
            }
        }
        return 'en';
    }

    function auragold_load_english_i18n_array() {
        $f = __DIR__ . '/locales/en.php';
        if (is_file($f)) {
            $g = @include $f;
            if (is_array($g)) {
                return $g;
            }
        }
        return [];
    }

    function auragold_bootstrap_i18n($conn) {
        $GLOBALS['auragold_i18n_locale']   = 'en';
        $GLOBALS['auragold_i18n_en']       = auragold_load_english_i18n_array();
        $GLOBALS['auragold_i18n_manual']  = [];

        $sessKey = 'auragold_i18n_boot_v3';
        $enFile  = __DIR__ . '/locales/en.php';
        $enMtime = is_file($enFile) ? (int) filemtime($enFile) : 0;
        if (!empty($_SESSION[$sessKey]) && is_array($_SESSION[$sessKey])) {
            $boot = $_SESSION[$sessKey];
            $bootMtime = (int) ($boot['en_mtime'] ?? 0);
            if ($bootMtime === $enMtime && $bootMtime > 0) {
                $GLOBALS['auragold_i18n_locale']  = (string) ($boot['locale'] ?? 'en');
                $GLOBALS['auragold_i18n_en']      = is_array($boot['en'] ?? null) ? $boot['en'] : $GLOBALS['auragold_i18n_en'];
                $GLOBALS['auragold_i18n_manual']   = is_array($boot['manual'] ?? null) ? $boot['manual'] : [];
                if ($conn instanceof mysqli) {
                    auragold_i18n_warm_db_cache($conn, $GLOBALS['auragold_i18n_locale']);
                }
                return;
            }
        }

        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }
        auragold_ensure_app_locale_column($conn);
        $row = @getRecord("SELECT `id`, `app_locale` FROM `tbl_settings` ORDER BY `id` ASC LIMIT 1");
        $loc = 'en';
        if (is_array($row) && isset($row['app_locale']) && trim((string) $row['app_locale']) !== '') {
            $loc = auragold_sanitize_app_locale($row['app_locale']);
        } elseif (function_exists('auragold_save_app_locale')) {
            // Persist default English when column is empty / unset.
            auragold_save_app_locale($conn, 'en');
            $loc = 'en';
        }
        $manual = [];
        $mFile = __DIR__ . '/locales/' . $loc . '.php';
        if ($loc !== 'en' && is_file($mFile)) {
            $o = @include $mFile;
            if (is_array($o)) {
                $manual = $o;
            }
        }
        $GLOBALS['auragold_i18n_locale'] = $loc;
        $GLOBALS['auragold_i18n_manual'] = $manual;
        auragold_i18n_warm_db_cache($conn, $loc);
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$sessKey] = [
                'locale'   => $loc,
                'en'       => $GLOBALS['auragold_i18n_en'],
                'manual'   => $manual,
                'en_mtime' => $enMtime,
            ];
        }
    }

    function auragold_get_locale() {
        return isset($GLOBALS['auragold_i18n_locale']) ? (string) $GLOBALS['auragold_i18n_locale'] : 'en';
    }

    function auragold_t($key, $default = null) {
        global $conn;
        $key  = (string) $key;
        $en   = $GLOBALS['auragold_i18n_en'] ?? auragold_load_english_i18n_array();
        if (!is_array($en) || $en === []) {
            if ($default !== null) {
                return (string) $default;
            }
            return $key;
        }
        $enText = null;
        if (isset($en[$key])) {
            $enText = (string) $en[$key];
        }
        if ($enText === null) {
            $defF = __DIR__ . '/locales/en.php';
            if (is_file($defF)) {
                $e2 = @include $defF;
                if (is_array($e2) && isset($e2[$key])) {
                    $enText = (string) $e2[$key];
                }
            }
        }
        if ($enText === null) {
            $enText = $default !== null ? (string) $default : $key;
        }
        $loc = auragold_get_locale();
        if ((string) $loc === 'en' || (is_string($loc) && preg_match('/^en(\-[A-Za-z0-9]+)*$/', (string) $loc))) {
            return $enText;
        }
        $manual = $GLOBALS['auragold_i18n_manual'] ?? [];
        if (is_array($manual) && isset($manual[$key]) && trim((string) $manual[$key]) !== '') {
            return (string) $manual[$key];
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $cached = auragold_i18n_cache_get($conn, (string) $loc, (string) $key);
            if (is_string($cached)) {
                return $cached;
            }
        }
        if (auragold_get_google_translate_api_key() !== '' && $enText !== $key) {
            $t = auragold_google_translate_one($enText, (string) $loc, 'en');
            if ($t !== null && $t !== '') {
                if (isset($conn) && $conn instanceof mysqli) {
                    auragold_i18n_cache_set($conn, (string) $loc, (string) $key, $t);
                }
                return (string) $t;
            }
        }
        return $enText;
    }

    function auragold_get_html_lang() {
        $l = auragold_get_locale();
        if (preg_match('/^\w+([\-]\w+)*$/', $l)) {
            if (strtolower($l) === 'en' || $l === 'en-GB' || $l === 'en-US') {
                return 'en';
            }
            if ($l === 'hi' || $l === 'hi-IN') {
                return 'hi-IN';
            }
            if ($l === 'gu' || $l === 'gu-IN') {
                return 'gu-IN';
            }
            return $l;
        }
        return 'en';
    }

    function auragold_save_app_locale($conn, $code) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        $loc = auragold_sanitize_app_locale($code);
        auragold_ensure_app_locale_column($conn);
        $e = mysqli_real_escape_string($conn, $loc);
        if (!@mysqli_query($conn, "UPDATE `tbl_settings` SET `app_locale` = '{$e}' LIMIT 1")) {
            (bool) @mysqli_query(
                $conn,
                "INSERT INTO `tbl_settings` (`id`, `app_locale`) VALUES (1, '{$e}') ON DUPLICATE KEY UPDATE `app_locale` = VALUES(`app_locale`)"
            );
        }
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['auragold_locale'] = $loc;
            unset($_SESSION['auragold_i18n_boot_v1']);
            unset($_SESSION['auragold_i18n_boot_v2']);
            unset($_SESSION['auragold_i18n_boot_v3']);
        }
        if (function_exists('auragold_i18n_cache_reset_memory')) {
            auragold_i18n_cache_reset_memory();
        }
        $GLOBALS['auragold_i18n_locale']  = $loc;
        $GLOBALS['auragold_i18n_en']      = auragold_load_english_i18n_array();
        $GLOBALS['auragold_i18n_manual']  = [];
        if ($loc !== 'en' && is_file(__DIR__ . '/locales/' . $loc . '.php')) {
            $m = @include __DIR__ . '/locales/' . $loc . '.php';
            if (is_array($m)) {
                $GLOBALS['auragold_i18n_manual'] = $m;
            }
        }
        auragold_i18n_warm_db_cache($conn, $loc);

        // Sync Google Website Translator cookie (googtrans) for page-level widget
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $opts = [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ];
            if ($loc === 'en' || preg_match('/^en(\-[A-Za-z0-9]+)*$/', $loc)) {
                setcookie('googtrans', '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('googtrans', '/en/' . $loc, $opts);
            }
        }

        return true;
    }

    function auragold_get_allowed_locales() {
        $j  = auragold_load_languages_merged();
        $by = ['en' => 'English'];
        if (!empty($j['languages']) && is_array($j['languages'])) {
            foreach ($j['languages'] as $row) {
                if (is_array($row) && !empty($row['language']) && !empty($row['name'])) {
                    $c      = (string) $row['language'];
                    $by[$c] = (string) $row['name'];
                }
            }
        }
        uasort(
            $by,
            function ($a, $b) {
                return strcasecmp((string) $a, (string) $b);
            }
        );
        if (isset($by['en'])) {
            $en = $by['en'];
            unset($by['en']);
            $by = array_merge(['en' => $en], $by);
        }
        return $by;
    }

    function auragold_get_allowed_locale_codes() {
        return array_keys(auragold_get_allowed_locales());
    }
}
