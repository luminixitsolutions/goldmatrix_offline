<?php
/**
 * Map tbl_metal.id ↔ dashboard slug (tbl_dashboard_metal_rates.metal).
 * IDs align with typical seed data: 1 Gold, 2 Silver, 3 Platinum, 4 Diamond & Stones.
 */

if (!function_exists('auragold_metal_id_to_dashboard_key')) {
    function auragold_metal_id_to_dashboard_key($metal_id): ?string
    {
        $id = (int) $metal_id;
        static $map = [
            1 => 'gold',
            2 => 'silver',
            3 => 'platinum',
            4 => 'diamond',
        ];
        return $map[$id] ?? null;
    }
}

if (!function_exists('auragold_dashboard_key_to_metal_id')) {
    function auragold_dashboard_key_to_metal_id(string $key): int
    {
        $k = strtolower(trim($key));
        if (preg_match('/^mext_(\d+)$/', $k, $m)) {
            return (int) $m[1];
        }
        static $map = [
            'gold' => 1,
            'silver' => 2,
            'platinum' => 3,
            'diamond' => 4,
        ];
        return (int) ($map[$k] ?? 0);
    }
}

if (!function_exists('auragold_dashboard_placeholder_block_extra_metal')) {
    /** Default single-line rate block when a custom master has no Carat rows yet. */
    function auragold_dashboard_placeholder_block_extra_metal(string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            $label = 'Metal';
        }
        $letters = preg_replace('/[^a-zA-Z]/', '', $label);
        $short = strtoupper(strlen($letters) >= 2 ? substr($letters, 0, 2) : substr($label, 0, 2));
        if ($short === '') {
            $short = 'Mx';
        }
        return [
            'label' => $label,
            'short' => $short,
            'hero_class' => 'metal-accent-other',
            'source_url' => '',
            'ounce_rate' => '0',
            'headline_rate' => '0.00',
            'headline_carat' => '—',
            'table_carat_label' => 'Rate line',
            'rows' => [
                ['carat' => '—', 'new_rate' => '0.00', 'sell_premium' => '—', 'conv' => '1', 'current' => '0.00'],
            ],
            'cards' => [
                ['label' => '—', 'value' => '0.00', 'class' => 'cc-other', 'image_url' => ''],
            ],
        ];
    }
}

if (!function_exists('auragold_dashboard_carat_card_class')) {
    function auragold_dashboard_carat_card_class(string $label, int $idx): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($label)));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'r' . $idx;
        }
        return 'cc-' . $slug;
    }
}

if (!function_exists('auragold_dashboard_carat_labels_for_save_validation')) {
    /**
     * Allowed carat/purity labels when saving dashboard rates: driven by Carat master for that metal, else built-in defaults.
     *
     * @return list<string>
     */
    function auragold_dashboard_carat_labels_for_save_validation($conn, string $metal_key): array
    {
        $defaults = [
            'gold' => ['24K', '22K', '21K', '18K', '14K', '10K'],
            'silver' => ['999', '958', '925', '875'],
            'platinum' => ['999', '950', '900'],
            'diamond' => ['0.30 ct', '0.50 ct', '0.70 ct', '1.00 ct', '1.50 ct', '2.00 ct'],
        ];
        $fallback = $defaults[strtolower($metal_key)] ?? [];
        if (!$conn instanceof mysqli || !function_exists('getList') || !function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_carat', 'metal_id')) {
            return $fallback;
        }
        $mid = auragold_dashboard_key_to_metal_id($metal_key);
        if ($mid <= 0) {
            return $fallback;
        }
        $suffix = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_carat') : '';
        $list = @getList('SELECT name FROM tbl_carat WHERE status = 1 AND metal_id = ' . (int) $mid . ' ' . $suffix);
        if (!is_array($list) || $list === []) {
            if (preg_match('/^mext_\d+$/i', $metal_key)) {
                return ['—'];
            }
            return $fallback;
        }
        $out = [];
        foreach ($list as $r) {
            $n = trim((string) ($r['name'] ?? ''));
            if ($n !== '') {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        if ($out === [] && preg_match('/^mext_\d+$/i', $metal_key)) {
            return ['—'];
        }
        return $out !== [] ? $out : $fallback;
    }
}

if (!function_exists('auragold_dashboard_apply_carat_master_rows')) {
    /**
     * Replace dashboard rate row/card templates from tbl_carat (metal-wise) when metal_id is set.
     * Legacy rows with NULL metal_id are treated as Gold so existing installs keep working.
     *
     * @param mysqli $conn
     * @param array    $dashboard_metals  Passed by reference; same structure as dashboard.php defaults.
     */
    function auragold_dashboard_apply_carat_master_rows($conn, array &$dashboard_metals): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_carat', 'metal_id')) {
            return;
        }
        if (!function_exists('getList')) {
            return;
        }
        $suffix = '';
        if (function_exists('auragold_master_list_sql_suffix')) {
            $suffix = auragold_master_list_sql_suffix($conn, 'tbl_carat');
        }
        $hasDashImg = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_carat', 'dashboard_image_path');
        $metalSqlSuffix = function_exists('auragold_master_list_sql_suffix')
            ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
            : '';
        $allMetals = @getList(
            'SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ' . $metalSqlSuffix . ' ORDER BY id ASC'
        );
        $metalById = [];
        if (is_array($allMetals)) {
            foreach ($allMetals as $mr) {
                $ix = (int) ($mr['id'] ?? 0);
                if ($ix > 0) {
                    $metalById[$ix] = $mr;
                }
            }
        }
        $sql = 'SELECT c.id, c.name, c.metal_id'
            . ($hasDashImg ? ', c.dashboard_image_path, c.dashboard_image_url' : '')
            . ' FROM tbl_carat c WHERE c.status = 1 ' . $suffix . ' ORDER BY c.metal_id ASC, c.id ASC';
        $list = @getList($sql);
        if (!is_array($list) || $list === []) {
            return;
        }

        $byKey = [];
        foreach ($list as $row) {
            $mid = isset($row['metal_id']) && $row['metal_id'] !== '' && $row['metal_id'] !== null
                ? (int) $row['metal_id']
                : 1;
            if ($mid <= 0) {
                $mid = 1;
            }
            $mrow = $metalById[$mid] ?? null;
            if ($mrow === null) {
                continue;
            }
            if (!function_exists('auragold_dashboard_rates_slug_for_metal_row')) {
                require_once __DIR__ . '/auragold_dashboard_metal_images.php';
            }
            $key = auragold_dashboard_rates_slug_for_metal_row($mrow);
            if ($key === '') {
                continue;
            }
            if (!isset($dashboard_metals[$key])) {
                if (strpos($key, 'mext_') === 0) {
                    $dashboard_metals[$key] = auragold_dashboard_placeholder_block_extra_metal(
                        trim((string) ($mrow['display_name'] ?? ''))
                    );
                } else {
                    continue;
                }
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (!isset($byKey[$key])) {
                $byKey[$key] = [];
            }
            $imgUrl = '';
            if ($hasDashImg && function_exists('auragold_dashboard_resolve_dashboard_image_src')) {
                $imgUrl = auragold_dashboard_resolve_dashboard_image_src(
                    (string) ($row['dashboard_image_path'] ?? ''),
                    (string) ($row['dashboard_image_url'] ?? '')
                );
            }
            $byKey[$key][] = ['name' => $name, 'image_url' => $imgUrl];
        }

        foreach ($byKey as $metalKey => $items) {
            if (!isset($dashboard_metals[$metalKey])) {
                continue;
            }
            if (!is_array($items) || $items === []) {
                continue;
            }
            $labels = [];
            $imageByLabel = [];
            foreach ($items as $it) {
                $nm = trim((string) ($it['name'] ?? ''));
                if ($nm === '') {
                    continue;
                }
                if (!in_array($nm, $labels, true)) {
                    $labels[] = $nm;
                }
                $iu = trim((string) ($it['image_url'] ?? ''));
                if ($iu !== '') {
                    $imageByLabel[$nm] = $iu;
                }
            }
            if ($labels === []) {
                continue;
            }
            $oldRows = isset($dashboard_metals[$metalKey]['rows']) && is_array($dashboard_metals[$metalKey]['rows'])
                ? $dashboard_metals[$metalKey]['rows']
                : [];
            $oldByCarat = [];
            foreach ($oldRows as $or) {
                $lab = trim((string) ($or['carat'] ?? ''));
                if ($lab !== '') {
                    $oldByCarat[$lab] = $or;
                }
            }
            $newRows = [];
            $i = 0;
            foreach ($labels as $lab) {
                $i++;
                if (isset($oldByCarat[$lab])) {
                    $newRows[] = $oldByCarat[$lab];
                } else {
                    $template = $oldRows[0] ?? null;
                    $nr = [
                        'carat' => $lab,
                        'new_rate' => isset($template['new_rate']) ? (string) $template['new_rate'] : '0.00',
                        'sell_premium' => '—',
                        'conv' => isset($template['conv']) ? (string) $template['conv'] : '1',
                        'current' => isset($template['current']) ? (string) $template['current'] : '0.00',
                    ];
                    $newRows[] = $nr;
                }
            }
            $dashboard_metals[$metalKey]['rows'] = $newRows;

            $cards = [];
            $ci = 0;
            foreach ($labels as $lab) {
                $ci++;
                $matchVal = '0.00';
                foreach ($newRows as $nr) {
                    if (trim((string) ($nr['carat'] ?? '')) === $lab) {
                        $matchVal = (string) ($nr['new_rate'] ?? $nr['current'] ?? '0.00');
                        break;
                    }
                }
                $cards[] = [
                    'label' => $lab,
                    'value' => $matchVal,
                    'class' => auragold_dashboard_carat_card_class($lab, $ci),
                    'image_url' => $imageByLabel[$lab] ?? '',
                ];
            }
            $dashboard_metals[$metalKey]['cards'] = $cards;
        }
    }
}
