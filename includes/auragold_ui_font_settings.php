<?php
/**
 * Global UI font settings (Set Software → Font Setting).
 * Stored in tbl_settings.app_ui_font_json; applied via header after newcss.css.
 */
if (!function_exists('auragold_ui_font_defaults')) {
    function auragold_ui_font_defaults() {
        return [
            'font_family'         => 'Roboto',
            'google_fonts_href'   => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,600;0,700&display=swap',
            'body_size_px'        => 14,
            'body_weight'         => 400,
            'line_height'         => 1.5,
            'body_letter_spacing' => 'normal',
            'topnav_size_px'      => 11,
            'topnav_weight'      => 500,
            'topnav_active_weight' => 600,
        ];
    }

    function auragold_ui_font_sanitize_family($s) {
        $s = trim((string) $s);
        if (strlen($s) > 120) {
            $s = substr($s, 0, 120);
        }
        if (!preg_match('/^[\p{L}\p{N}\s\-\',"]+$/u', $s)) {
            return 'Roboto';
        }
        return $s;
    }

    function auragold_ui_font_sanitize_google_href($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return auragold_ui_font_defaults()['google_fonts_href'];
        }
        if (strlen($url) > 600) {
            return auragold_ui_font_defaults()['google_fonts_href'];
        }
        if (!preg_match('#^https://fonts\.googleapis\.com/#i', $url)) {
            return auragold_ui_font_defaults()['google_fonts_href'];
        }
        return $url;
    }

    function auragold_ensure_app_ui_font_json_column($conn) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_settings` LIKE 'app_ui_font_json'");
        if ($r && mysqli_num_rows($r) > 0) {
            mysqli_free_result($r);
            $done[$key] = true;
            return true;
        }
        if ($r) {
            mysqli_free_result($r);
        }
        @mysqli_query(
            $conn,
            "ALTER TABLE `tbl_settings` ADD COLUMN `app_ui_font_json` TEXT NULL COMMENT 'Global UI font (Set Software)'"
        );
        $done[$key] = true;
        return true;
    }

    function auragold_invalidate_app_ui_font_settings_cache() {
        unset($GLOBALS['auragold_app_ui_font_settings_cached'], $GLOBALS['auragold_app_ui_font_has_custom']);
    }

    function auragold_get_app_ui_font_settings($conn) {
        if (isset($GLOBALS['auragold_app_ui_font_settings_cached']) && is_array($GLOBALS['auragold_app_ui_font_settings_cached'])) {
            return $GLOBALS['auragold_app_ui_font_settings_cached'];
        }
        $defaults = auragold_ui_font_defaults();
        if (!$conn || !($conn instanceof mysqli)) {
            return $defaults;
        }
        auragold_ensure_app_ui_font_json_column($conn);
        $row = @getRecord("SELECT `app_ui_font_json` FROM `tbl_settings` ORDER BY `id` ASC LIMIT 1");
        if (!$row || empty($row['app_ui_font_json'])) {
            $GLOBALS['auragold_app_ui_font_has_custom'] = false;
            $GLOBALS['auragold_app_ui_font_settings_cached'] = $defaults;
            return $defaults;
        }
        $GLOBALS['auragold_app_ui_font_has_custom'] = true;
        $j = @json_decode((string) $row['app_ui_font_json'], true);
        if (!is_array($j)) {
            $GLOBALS['auragold_app_ui_font_settings_cached'] = $defaults;
            return $defaults;
        }
        $out = $defaults;
        foreach ($defaults as $k => $v) {
            if (array_key_exists($k, $j)) {
                if ($k === 'body_size_px' || $k === 'topnav_size_px') {
                    $out[$k] = max(10, min(24, (int) $j[$k]));
                } elseif ($k === 'body_weight' || $k === 'topnav_weight' || $k === 'topnav_active_weight') {
                    $w = (int) $j[$k];
                    $out[$k] = in_array($w, [300, 400, 500, 600, 700], true) ? $w : $defaults[$k];
                } elseif ($k === 'line_height') {
                    $lh = (float) $j[$k];
                    $out[$k] = max(1, min(2.2, $lh));
                } elseif ($k === 'body_letter_spacing') {
                    $b = trim((string) $j[$k]);
                    if ($b === 'normal' || $b === '') {
                        $out[$k] = 'normal';
                    } elseif (preg_match('/^[\-0-9.]+(px|em|rem)$/', $b)) {
                        $out[$k] = $b;
                    } else {
                        $out[$k] = 'normal';
                    }
                } elseif ($k === 'font_family') {
                    $out[$k] = auragold_ui_font_sanitize_family($j[$k]);
                } elseif ($k === 'google_fonts_href') {
                    $out[$k] = auragold_ui_font_sanitize_google_href($j[$k]);
                }
            }
        }
        $GLOBALS['auragold_app_ui_font_settings_cached'] = $out;
        return $out;
    }

    function auragold_save_app_ui_font_settings($conn, array $in) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        auragold_ensure_app_ui_font_json_column($conn);
        $d = auragold_ui_font_defaults();
        $d['font_family'] = auragold_ui_font_sanitize_family($in['font_family'] ?? 'Roboto');
        $d['google_fonts_href'] = auragold_ui_font_sanitize_google_href($in['google_fonts_href'] ?? '');
        $d['body_size_px'] = max(10, min(24, (int) ($in['body_size_px'] ?? 14)));
        $d['body_weight'] = in_array((int) ($in['body_weight'] ?? 400), [300, 400, 500, 600, 700], true)
            ? (int) $in['body_weight'] : 400;
        $d['line_height'] = max(1, min(2.2, (float) ($in['line_height'] ?? 1.5)));
        $bsp = trim((string) ($in['body_letter_spacing'] ?? 'normal'));
        if ($bsp === '' || $bsp === 'normal') {
            $d['body_letter_spacing'] = 'normal';
        } elseif (preg_match('/^[\-0-9.]+(px|em|rem)$/', $bsp)) {
            $d['body_letter_spacing'] = $bsp;
        } else {
            $d['body_letter_spacing'] = 'normal';
        }
        $d['topnav_size_px'] = max(10, min(20, (int) ($in['topnav_size_px'] ?? 11)));
        $d['topnav_weight'] = in_array((int) ($in['topnav_weight'] ?? 500), [300, 400, 500, 600, 700], true)
            ? (int) $in['topnav_weight'] : 500;
        $d['topnav_active_weight'] = in_array((int) ($in['topnav_active_weight'] ?? 600), [300, 400, 500, 600, 700], true)
            ? (int) $in['topnav_active_weight'] : 600;
        $json = mysqli_real_escape_string($conn, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $q = "UPDATE `tbl_settings` SET `app_ui_font_json` = '{$json}' LIMIT 1";
        if (!@mysqli_query($conn, $q)) {
            $q2 = "INSERT INTO `tbl_settings` (`id`, `app_ui_font_json`) VALUES (1, '{$json}') ON DUPLICATE KEY UPDATE `app_ui_font_json` = VALUES(`app_ui_font_json`)";
            return (bool) @mysqli_query($conn, $q2);
        }
        if (mysqli_affected_rows($conn) === 0) {
            @mysqli_query(
                $conn,
                "INSERT INTO `tbl_settings` (`id`, `app_ui_font_json`) VALUES (1, '{$json}') ON DUPLICATE KEY UPDATE `app_ui_font_json` = '{$json}'"
            );
        }
        auragold_invalidate_app_ui_font_settings_cache();
        return true;
    }

    function auragold_ui_font_print_google_fonts_link() {
        $def = auragold_ui_font_defaults();
        $href = $def['google_fonts_href'];
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $s = auragold_get_app_ui_font_settings($GLOBALS['conn']);
            if (!empty($s['google_fonts_href'])) {
                $href = $s['google_fonts_href'];
            }
        }
        $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        echo "    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
        echo "    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
        echo "    <link href=\"{$href}\" rel=\"stylesheet\">\n";
    }

    function auragold_ui_font_print_overrides_style() {
        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
            return;
        }
        $s = auragold_get_app_ui_font_settings($GLOBALS['conn']);
        if (empty($GLOBALS['auragold_app_ui_font_has_custom'])) {
            return;
        }
        $fam = auragold_ui_font_sanitize_family($s['font_family']);
        $stack = '\'' . str_replace("'", "\\'", $fam) . '\', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        $bodyPx = (int) $s['body_size_px'];
        $navPx = (int) $s['topnav_size_px'];
        $bW = (int) $s['body_weight'];
        $nW = (int) $s['topnav_weight'];
        $nWa = (int) $s['topnav_active_weight'];
        $lh = (float) $s['line_height'];
        if ($lh < 1) {
            $lh = 1.5;
        }
        if ($lh > 2.2) {
            $lh = 2.2;
        }
        $ls = 'normal';
        if ($s['body_letter_spacing'] !== 'normal' && is_string($s['body_letter_spacing'])
            && preg_match('/^[\-0-9.]+(px|em|rem)$/', $s['body_letter_spacing'])) {
            $ls = $s['body_letter_spacing'];
        }
        echo "    <style id=\"auragold-ui-font-overrides\">\n";
        echo "    :root {\n";
        echo "      --app-font: {$stack};\n";
        echo "      --js-text-base: {$bodyPx}px;\n";
        echo "      --js-text-topnav: {$navPx}px;\n";
        echo "      --js-fw-body: {$bW};\n";
        echo "      --js-fw-medium: {$nW};\n";
        echo "      --js-fw-nav-active: {$nWa};\n";
        echo "    }\n";
        echo "    body { font-weight: {$bW} !important; line-height: {$lh} !important; letter-spacing: {$ls}; }\n";
        echo "    #auragoldTopNav.top-navbar .nav.nav-tabs .nav-item > .nav-link,\n";
        echo "    #auragoldTopNav.top-navbar .nav-tabs .nav-link,\n";
        echo "    nav#auragoldTopNav .nav .nav-link,\n";
        echo "    .top-navbar .nav-link { font-weight: {$nW} !important; }\n";
        echo "    #auragoldTopNav.top-navbar .nav-tabs .nav-link.active,\n";
        echo "    nav#auragoldTopNav .nav .nav-link.active,\n";
        echo "    .top-navbar .nav-link.active { font-weight: {$nWa} !important; }\n";
        echo "    </style>\n";
    }
}