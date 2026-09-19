<?php
/**
 * Map tbl_metal rows to dashboard metal keys (gold|silver|platinum|diamond).
 * Load latest carat row per metal that has an uploaded path or external URL.
 */
if (!function_exists('auragold_dashboard_key_for_metal_row')) {
    function auragold_dashboard_key_for_metal_row(array $m): ?string
    {
        $sn = strtolower(trim((string) ($m['system_name'] ?? '')));
        if (in_array($sn, ['gold', 'silver', 'platinum', 'diamond'], true)) {
            return $sn;
        }
        $dn = strtolower(trim((string) ($m['display_name'] ?? '')));
        if ($dn === '') {
            return null;
        }
        if (strpos($dn, 'diamond') !== false) {
            return 'diamond';
        }
        if (strpos($dn, 'platinum') !== false) {
            return 'platinum';
        }
        if (strpos($dn, 'silver') !== false) {
            return 'silver';
        }
        if (strpos($dn, 'gold') !== false) {
            return 'gold';
        }
        return null;
    }
}

if (!function_exists('auragold_dashboard_rates_slug_for_metal_row')) {
    /**
     * Slug stored in tbl_dashboard_metal_rates / used as $dashboard_metals key.
     * Standard names resolve to gold|silver|platinum|diamond; all other metals use mext_{id}.
     */
    function auragold_dashboard_rates_slug_for_metal_row(array $metalRow): string
    {
        $std = auragold_dashboard_key_for_metal_row($metalRow);
        if ($std !== null) {
            return $std;
        }
        $id = (int) ($metalRow['id'] ?? 0);

        return $id > 0 ? 'mext_' . $id : '';
    }
}

if (!function_exists('auragold_dashboard_resolve_dashboard_image_src')) {
    /**
     * Prefer upload path (site-relative); allow full http(s) URL in path column; else external URL.
     */
    function auragold_dashboard_resolve_dashboard_image_src(string $path, string $extUrl): string
    {
        $path = trim($path);
        $extUrl = trim($extUrl);
        if ($path !== '') {
            if (preg_match('#^https?://#i', $path)) {
                return $path;
            }
            return ltrim(str_replace('\\', '/', $path), '/');
        }
        return $extUrl;
    }
}

if (!function_exists('auragold_dashboard_metal_images_from_carats')) {
    /**
     * @return array<string,string> keyed by gold|silver|platinum|diamond → absolute URL or admin-relative URL
     */
    function auragold_dashboard_metal_images_from_carats($conn): array
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return [];
        }
        if (!auragold_tbl_has_column($conn, 'tbl_carat', 'metal_id')
            || !auragold_tbl_has_column($conn, 'tbl_carat', 'dashboard_image_path')) {
            return [];
        }
        require_once __DIR__ . '/auragold_carat_dashboard_image_schema.php';
        auragold_ensure_tbl_carat_dashboard_images($conn);
        require_once __DIR__ . '/auragold_metal_dashboard_image_schema.php';
        auragold_ensure_tbl_metal_dashboard_images($conn);

        $metalSqlSuffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
            : '';
        $metalCols = 'id, display_name, system_name';
        if (auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard')) {
            $metalCols .= ', show_on_dashboard';
        }
        $metals = getList(
            'SELECT ' . $metalCols . ' FROM tbl_metal WHERE status = 1 ' . $metalSqlSuffix . ' ORDER BY id ASC'
        );
        if (!is_array($metals)) {
            return [];
        }
        $suffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_carat')
            : '';
        $out = [];
        foreach ($metals as $mrow) {
            if (auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard')
                && (int) ($mrow['show_on_dashboard'] ?? 0) !== 1) {
                continue;
            }
            $key = auragold_dashboard_rates_slug_for_metal_row($mrow);
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $mid = (int) ($mrow['id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $sql = 'SELECT dashboard_image_path, dashboard_image_url FROM tbl_carat WHERE status = 1 AND metal_id = ' . $mid
                . " AND (NULLIF(TRIM(COALESCE(dashboard_image_path,'')),'') IS NOT NULL OR NULLIF(TRIM(COALESCE(dashboard_image_url,'')),'') IS NOT NULL)"
                . $suffix
                . ' ORDER BY id DESC LIMIT 1';
            $crow = function_exists('getRecord') ? getRecord($sql) : null;
            if (!is_array($crow)) {
                continue;
            }
            $resolved = auragold_dashboard_resolve_dashboard_image_src(
                (string) ($crow['dashboard_image_path'] ?? ''),
                (string) ($crow['dashboard_image_url'] ?? '')
            );
            if ($resolved !== '') {
                $out[$key] = $resolved;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_dashboard_carat_images_map_by_metal_key')) {
    /**
     * Carat label → image src for dashboard ticker/cards (per metal tab). Empty string = no image.
     *
     * @return array<string, array<string, string>>
     */
    function auragold_dashboard_carat_images_map_by_metal_key($conn): array
    {
        if (!$conn || !function_exists('auragold_tbl_has_column') || !function_exists('getList')) {
            return [];
        }
        if (!auragold_tbl_has_column($conn, 'tbl_carat', 'metal_id')
            || !auragold_tbl_has_column($conn, 'tbl_carat', 'dashboard_image_path')) {
            return [];
        }
        require_once __DIR__ . '/auragold_carat_dashboard_image_schema.php';
        auragold_ensure_tbl_carat_dashboard_images($conn);
        require_once __DIR__ . '/auragold_metal_dashboard_image_schema.php';
        auragold_ensure_tbl_metal_dashboard_images($conn);

        $suffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_carat')
            : '';
        $metalSqlSuffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
            : '';
        $allowedMids = null;
        if (auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard')) {
            $allowedMids = [];
            $mrows = @getList(
                'SELECT id FROM tbl_metal WHERE status = 1 AND show_on_dashboard = 1 ' . $metalSqlSuffix . ' ORDER BY id ASC'
            );
            if (is_array($mrows)) {
                foreach ($mrows as $mr) {
                    $allowedMids[(int) ($mr['id'] ?? 0)] = true;
                }
            }
        }
        $sql = 'SELECT metal_id, name, dashboard_image_path, dashboard_image_url FROM tbl_carat WHERE status = 1' . $suffix
            . ' ORDER BY metal_id ASC, id ASC';
        $list = @getList($sql);
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $row) {
            $mid = isset($row['metal_id']) && $row['metal_id'] !== '' && $row['metal_id'] !== null
                ? (int) $row['metal_id']
                : 1;
            if (is_array($allowedMids) && !isset($allowedMids[$mid])) {
                continue;
            }
            $metalRow = ['id' => $mid, 'display_name' => '', 'system_name' => ''];
            if (function_exists('getRecord')) {
                $mr = @getRecord(
                    'SELECT id, display_name, system_name FROM tbl_metal WHERE id = ' . (int) $mid . ' AND status = 1 LIMIT 1'
                );
                if (is_array($mr)) {
                    $metalRow = $mr;
                }
            }
            $key = auragold_dashboard_rates_slug_for_metal_row($metalRow);
            if ($key === '') {
                continue;
            }
            $lab = trim((string) ($row['name'] ?? ''));
            if ($lab === '') {
                continue;
            }
            $src = auragold_dashboard_resolve_dashboard_image_src(
                (string) ($row['dashboard_image_path'] ?? ''),
                (string) ($row['dashboard_image_url'] ?? '')
            );
            if ($src === '') {
                continue;
            }
            if (!isset($out[$key])) {
                $out[$key] = [];
            }
            $out[$key][$lab] = $src;
        }
        return $out;
    }
}

if (!function_exists('auragold_dashboard_metal_images_from_tbl_metal')) {
    /**
     * @return array<string,string> keyed by gold|silver|platinum|diamond
     */
    function auragold_dashboard_metal_images_from_tbl_metal($conn): array
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return [];
        }
        if (!auragold_tbl_has_column($conn, 'tbl_metal', 'dashboard_image_path')) {
            return [];
        }
        require_once __DIR__ . '/auragold_metal_dashboard_image_schema.php';
        auragold_ensure_tbl_metal_dashboard_images($conn);

        $suffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
            : '';
        $showClause = auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard') ? ' AND show_on_dashboard = 1' : '';
        $sql = 'SELECT id, display_name, system_name, dashboard_image_path, dashboard_image_url FROM tbl_metal WHERE status = 1'
            . $showClause . $suffix;
        $metals = getList($sql);
        if (!is_array($metals)) {
            return [];
        }
        $out = [];
        foreach ($metals as $mrow) {
            $key = auragold_dashboard_rates_slug_for_metal_row($mrow);
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $resolved = auragold_dashboard_resolve_dashboard_image_src(
                (string) ($mrow['dashboard_image_path'] ?? ''),
                (string) ($mrow['dashboard_image_url'] ?? '')
            );
            if ($resolved !== '') {
                $out[$key] = $resolved;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_dashboard_filter_metals_by_master_visibility')) {
    /**
     * Keep rate dashboard sections only for metals with show_on_dashboard = 1 in Masters.
     * Uses display_name from the first matching row (by id) as the card/tab label.
     *
     * @param array<string, array<string, mixed>> $dashboard_metals
     * @return array<string, array<string, mixed>>
     */
    function auragold_dashboard_filter_metals_by_master_visibility($conn, array $dashboard_metals): array
    {
        if (!$conn || !function_exists('auragold_tbl_has_column') || !function_exists('getList')) {
            return $dashboard_metals;
        }
        if (!auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard')) {
            return $dashboard_metals;
        }
        require_once __DIR__ . '/auragold_metal_dashboard_image_schema.php';
        auragold_ensure_tbl_metal_dashboard_images($conn);

        $suffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
            : '';
        $list = getList(
            'SELECT id, display_name, system_name, show_on_dashboard FROM tbl_metal WHERE status = 1' . $suffix . ' ORDER BY id ASC'
        );
        if (!is_array($list)) {
            return $dashboard_metals;
        }
        $byKey = [];
        foreach ($list as $r) {
            if ((int) ($r['show_on_dashboard'] ?? 0) !== 1) {
                continue;
            }
            $k = auragold_dashboard_rates_slug_for_metal_row($r);
            if ($k === '') {
                continue;
            }
            if (!isset($byKey[$k])) {
                $byKey[$k] = $r;
            }
        }
        $order_core = ['gold', 'silver', 'platinum', 'diamond'];
        $mext_ids = [];
        foreach (array_keys($byKey) as $sk) {
            if (preg_match('/^mext_(\d+)$/', (string) $sk, $mx)) {
                $mext_ids[(int) $mx[1]] = $sk;
            }
        }
        ksort($mext_ids, SORT_NUMERIC);
        $slug_order = [];
        foreach ($order_core as $fixed) {
            if (isset($byKey[$fixed])) {
                $slug_order[] = $fixed;
            }
        }
        foreach ($mext_ids as $slug) {
            $slug_order[] = $slug;
        }
        $out = [];
        foreach ($slug_order as $slug) {
            if (!isset($byKey[$slug])) {
                continue;
            }
            if (!isset($dashboard_metals[$slug])) {
                if (strpos($slug, 'mext_') === 0) {
                    require_once __DIR__ . '/dashboard_carat_master.php';
                    $dn = trim((string) ($byKey[$slug]['display_name'] ?? ''));
                    $dashboard_metals[$slug] = auragold_dashboard_placeholder_block_extra_metal($dn);
                } else {
                    continue;
                }
            }
            $block = $dashboard_metals[$slug];
            $dn = trim((string) ($byKey[$slug]['display_name'] ?? ''));
            if ($dn !== '') {
                $block['label'] = $dn;
            }
            $out[$slug] = $block;
        }
        return $out;
    }
}
