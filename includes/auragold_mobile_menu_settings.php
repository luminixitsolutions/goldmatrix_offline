<?php

/**
 * Mobile top-nav visibility (desktop always shows full menu per permissions).
 * Settings stored in tbl_auragold_mobile_menu_settings (single row id=1).
 */

require_once __DIR__ . '/sidebar_permission_tree_data.php';

if (!function_exists('auragold_ensure_mobile_menu_settings_table')) {
    function auragold_ensure_mobile_menu_settings_table($link): bool
    {
        if (!$link instanceof mysqli) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `tbl_auragold_mobile_menu_settings` (
            `id` tinyint unsigned NOT NULL DEFAULT 1,
            `enabled_json` longtext DEFAULT NULL COMMENT 'JSON {modules:[], pages:[]}; NULL = all menus enabled',
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!@mysqli_query($link, $sql)) {
            return false;
        }

        @mysqli_query($link, 'INSERT IGNORE INTO `tbl_auragold_mobile_menu_settings` (`id`) VALUES (1)');

        return true;
    }
}

if (!function_exists('auragold_mobile_menu_all_module_keys')) {
    /**
     * @return list<string>
     */
    function auragold_mobile_menu_all_module_keys(): array
    {
        $keys = [];
        foreach (auragold_sidebar_permission_tree_data() as $mod) {
            if (!empty($mod['menu']) && !empty($mod['key'])) {
                $keys[] = (string) $mod['key'];
            }
        }

        return $keys;
    }
}

if (!function_exists('auragold_mobile_menu_all_page_keys')) {
    /**
     * @return list<string> module.page
     */
    function auragold_mobile_menu_all_page_keys(): array
    {
        $keys = [];
        foreach (auragold_sidebar_permission_tree_data() as $mod) {
            $mk = (string) ($mod['key'] ?? '');
            if ($mk === '') {
                continue;
            }
            foreach ($mod['pages'] ?? [] as $p) {
                $pk = (string) ($p['key'] ?? '');
                if ($pk !== '') {
                    $keys[] = $mk . '.' . $pk;
                }
            }
        }

        return $keys;
    }
}

if (!function_exists('auragold_mobile_menu_default_enabled_config')) {
    /**
     * @return array{modules: list<string>, pages: list<string>}
     */
    function auragold_mobile_menu_default_enabled_config(): array
    {
        return [
            'modules' => auragold_mobile_menu_all_module_keys(),
            'pages'   => auragold_mobile_menu_all_page_keys(),
        ];
    }
}

if (!function_exists('auragold_mobile_menu_normalize_enabled_config')) {
    /**
     * @param array<string, mixed>|null $raw
     * @return array{modules: list<string>, pages: list<string>}
     */
    function auragold_mobile_menu_normalize_enabled_config(?array $raw): array
    {
        $defaults = auragold_mobile_menu_default_enabled_config();
        if ($raw === null) {
            return $defaults;
        }

        $modules = [];
        if (isset($raw['modules']) && is_array($raw['modules'])) {
            foreach ($raw['modules'] as $m) {
                $m = (string) $m;
                if ($m !== '' && in_array($m, $defaults['modules'], true)) {
                    $modules[] = $m;
                }
            }
        }

        $pages = [];
        if (isset($raw['pages']) && is_array($raw['pages'])) {
            foreach ($raw['pages'] as $p) {
                $p = (string) $p;
                if ($p !== '' && in_array($p, $defaults['pages'], true)) {
                    $pages[] = $p;
                }
            }
        }

        return [
            'modules' => array_values(array_unique($modules)),
            'pages'   => array_values(array_unique($pages)),
        ];
    }
}

if (!function_exists('auragold_mobile_menu_get_enabled_config')) {
    /**
     * NULL = never saved (all menus enabled on mobile).
     *
     * @return array{modules: list<string>, pages: list<string>}|null
     */
    function auragold_mobile_menu_get_enabled_config($link): ?array
    {
        static $cached = null;
        static $loaded = false;
        if ($loaded) {
            return $cached;
        }
        $loaded = true;
        $cached = null;

        if (!$link instanceof mysqli) {
            return null;
        }
        if (!auragold_ensure_mobile_menu_settings_table($link)) {
            return null;
        }

        $r = @mysqli_query($link, 'SELECT `enabled_json` FROM `tbl_auragold_mobile_menu_settings` WHERE `id` = 1 LIMIT 1');
        if (!$r) {
            return null;
        }
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if (!is_array($row)) {
            return null;
        }

        $json = $row['enabled_json'] ?? null;
        if ($json === null || trim((string) $json) === '') {
            return null;
        }

        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return auragold_mobile_menu_default_enabled_config();
        }

        $cached = auragold_mobile_menu_normalize_enabled_config($decoded);

        return $cached;
    }
}

if (!function_exists('auragold_mobile_menu_is_module_enabled')) {
    function auragold_mobile_menu_is_module_enabled($link, string $moduleKey): bool
    {
        $cfg = auragold_mobile_menu_get_enabled_config($link);
        if ($cfg === null) {
            return true;
        }

        return in_array($moduleKey, $cfg['modules'], true);
    }
}

if (!function_exists('auragold_mobile_menu_is_page_enabled')) {
    function auragold_mobile_menu_is_page_enabled($link, string $moduleKey, string $pageKey): bool
    {
        if (!auragold_mobile_menu_is_module_enabled($link, $moduleKey)) {
            return false;
        }
        $cfg = auragold_mobile_menu_get_enabled_config($link);
        if ($cfg === null) {
            return true;
        }

        return in_array($moduleKey . '.' . $pageKey, $cfg['pages'], true);
    }
}

if (!function_exists('auragold_mobile_menu_get_js_filter_config')) {
    /**
     * Disabled module/page keys for client-side mobile nav filter.
     *
     * @return array{disabledModules: list<string>, disabledPages: list<string>}
     */
    function auragold_mobile_menu_get_js_filter_config($link): array
    {
        $allModules = auragold_mobile_menu_all_module_keys();
        $allPages   = auragold_mobile_menu_all_page_keys();
        $cfg        = auragold_mobile_menu_get_enabled_config($link);

        if ($cfg === null) {
            return [
                'disabledModules' => [],
                'disabledPages'   => [],
            ];
        }

        return [
            'disabledModules' => array_values(array_diff($allModules, $cfg['modules'])),
            'disabledPages'   => array_values(array_diff($allPages, $cfg['pages'])),
        ];
    }
}

if (!function_exists('auragold_mobile_menu_save_from_post')) {
    /**
     * @param array<string, mixed> $post $_POST
     * @return bool
     */
    function auragold_mobile_menu_save_from_post($link, array $post): bool
    {
        if (!$link instanceof mysqli) {
            return false;
        }
        if (!auragold_ensure_mobile_menu_settings_table($link)) {
            return false;
        }

        $modules = [];
        if (isset($post['mobile_menu_modules']) && is_array($post['mobile_menu_modules'])) {
            foreach ($post['mobile_menu_modules'] as $m) {
                $modules[] = (string) $m;
            }
        }

        $pages = [];
        if (isset($post['mobile_menu_pages']) && is_array($post['mobile_menu_pages'])) {
            foreach ($post['mobile_menu_pages'] as $p) {
                $pages[] = (string) $p;
            }
        }

        $payload = auragold_mobile_menu_normalize_enabled_config([
            'modules' => $modules,
            'pages'   => $pages,
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $esc = mysqli_real_escape_string($link, $json);
        $ok  = @mysqli_query(
            $link,
            "UPDATE `tbl_auragold_mobile_menu_settings` SET `enabled_json` = '{$esc}', `updated_at` = NOW() WHERE `id` = 1"
        );

        if ($ok && mysqli_affected_rows($link) === 0) {
            @mysqli_query(
                $link,
                "INSERT INTO `tbl_auragold_mobile_menu_settings` (`id`, `enabled_json`) VALUES (1, '{$esc}')"
            );
        }

        return (bool) $ok;
    }
}

if (!function_exists('auragold_mobile_menu_form_checked_modules')) {
    /**
     * @return array<string, bool>
     */
    function auragold_mobile_menu_form_checked_modules($link): array
    {
        $cfg = auragold_mobile_menu_get_enabled_config($link);
        if ($cfg === null) {
            $out = [];
            foreach (auragold_mobile_menu_all_module_keys() as $k) {
                $out[$k] = true;
            }

            return $out;
        }
        $out = [];
        foreach (auragold_mobile_menu_all_module_keys() as $k) {
            $out[$k] = in_array($k, $cfg['modules'], true);
        }

        return $out;
    }
}

if (!function_exists('auragold_mobile_menu_form_checked_pages')) {
    /**
     * @return array<string, bool>
     */
    function auragold_mobile_menu_form_checked_pages($link): array
    {
        $cfg = auragold_mobile_menu_get_enabled_config($link);
        if ($cfg === null) {
            $out = [];
            foreach (auragold_mobile_menu_all_page_keys() as $k) {
                $out[$k] = true;
            }

            return $out;
        }
        $out = [];
        foreach (auragold_mobile_menu_all_page_keys() as $k) {
            $out[$k] = in_array($k, $cfg['pages'], true);
        }

        return $out;
    }
}
