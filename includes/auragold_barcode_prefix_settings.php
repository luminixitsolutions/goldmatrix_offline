<?php
/**
 * Branch-wise barcode prefix settings (default + per-metal).
 */
if (!function_exists('auragold_bps_master_conn')) {
    function auragold_bps_master_conn() {
        global $conn_master;
        if (isset($conn_master) && $conn_master instanceof mysqli) {
            return $conn_master;
        }
        return null;
    }
}

if (!function_exists('auragold_bps_table_has_column')) {
    function auragold_bps_table_has_column(mysqli $link, string $table, string $column): bool {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            return false;
        }
        $q = @mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$q) {
            return false;
        }
        $ok = mysqli_num_rows($q) > 0;
        mysqli_free_result($q);
        return $ok;
    }
}

if (!function_exists('auragold_ensure_branch_barcode_prefix_columns')) {
    function auragold_ensure_branch_barcode_prefix_columns($conn_master = null): void {
        if (!$conn_master instanceof mysqli) {
            $conn_master = auragold_bps_master_conn();
        }
        if (!$conn_master instanceof mysqli) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!auragold_bps_table_has_column($conn_master, 'tbl_branches', 'branch_barcode_prefix')) {
            @mysqli_query(
                $conn_master,
                "ALTER TABLE `tbl_branches` ADD COLUMN `branch_barcode_prefix` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Prefix for barcodes'"
            );
        }
        if (!auragold_bps_table_has_column($conn_master, 'tbl_branches', 'barcode_num_digits')) {
            @mysqli_query(
                $conn_master,
                "ALTER TABLE `tbl_branches` ADD COLUMN `barcode_num_digits` INT NULL DEFAULT NULL COMMENT 'Barcode numeric length for this branch'"
            );
        }

        @mysqli_query(
            $conn_master,
            "CREATE TABLE IF NOT EXISTS `tbl_branch_barcode_metal_prefix` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `branch_id` INT NOT NULL,
                `metal_id` INT NOT NULL,
                `barcode_prefix` VARCHAR(50) NOT NULL DEFAULT '',
                `barcode_digits` INT NOT NULL DEFAULT 5,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_branch_metal_barcode_prefix` (`branch_id`, `metal_id`),
                KEY `idx_bbmp_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('auragold_barcode_prefix_hardcoded_metal_defaults')) {
    /** @return array<string, array{prefix:string,digits:int}> keyed by metal display_name */
    function auragold_barcode_prefix_hardcoded_metal_defaults(): array {
        return [
            'Gold'                 => ['prefix' => 'GD', 'digits' => 5],
            'Silver'               => ['prefix' => 'SV', 'digits' => 5],
            'Platinum'             => ['prefix' => 'PT', 'digits' => 5],
            'Diamond & Stones'     => ['prefix' => 'DM', 'digits' => 5],
            'Imitation Or Watches' => ['prefix' => 'IM', 'digits' => 5],
            'Other Or Services'    => ['prefix' => 'OS', 'digits' => 5],
        ];
    }
}

if (!function_exists('auragold_get_branch_barcode_prefix_settings')) {
    /**
     * @return array{
     *   default_prefix:string,
     *   default_digits:int,
     *   metals: list<array{metal_id:int,display_name:string,prefix:string,digits:int}>
     * }
     */
    function auragold_get_branch_barcode_prefix_settings($conn, int $branch_id): array {
        $master = auragold_bps_master_conn();
        auragold_ensure_branch_barcode_prefix_columns($master);

        $default_prefix = '';
        $default_digits = 5;
        if ($branch_id > 0 && $master instanceof mysqli && function_exists('getRecordMaster')) {
            $has_prefix_col = auragold_bps_table_has_column($master, 'tbl_branches', 'branch_barcode_prefix');
            $has_digits_col = auragold_bps_table_has_column($master, 'tbl_branches', 'barcode_num_digits');
            $cols = ['id'];
            if ($has_prefix_col) {
                $cols[] = 'branch_barcode_prefix';
            }
            if ($has_digits_col) {
                $cols[] = 'barcode_num_digits';
            }
            $br = getRecordMaster('SELECT ' . implode(', ', $cols) . ' FROM tbl_branches WHERE id = ' . (int) $branch_id . ' LIMIT 1');
            if ($br) {
                if ($has_prefix_col) {
                    $default_prefix = trim((string) ($br['branch_barcode_prefix'] ?? ''));
                }
                if ($has_digits_col) {
                    $d = (int) ($br['barcode_num_digits'] ?? 0);
                    if ($d > 0) {
                        $default_digits = $d;
                    }
                }
            }
        }
        if ($default_prefix === '' && function_exists('auragold_barcode_default_prefix_digit') && $conn instanceof mysqli) {
            $fb = auragold_barcode_default_prefix_digit($conn, $branch_id);
            $default_prefix = trim((string) ($fb['prefix'] ?? 'RN'));
            $default_digits = max(1, (int) ($fb['digit'] ?? 5));
        }
        if ($default_prefix === '') {
            $default_prefix = 'RN';
        }

        $metals = [];
        if ($conn instanceof mysqli && function_exists('getList')) {
            $suffix = function_exists('auragold_master_list_sql_suffix')
                ? auragold_master_list_sql_suffix($conn, 'tbl_metal')
                : '';
            $rows = getList(
                'SELECT id, display_name FROM tbl_metal WHERE status = 1' . $suffix . ' ORDER BY id ASC'
            );
            if (!is_array($rows)) {
                $rows = [];
            }

            $saved = [];
            if ($branch_id > 0 && $master instanceof mysqli) {
                $q = @mysqli_query(
                    $master,
                    'SELECT metal_id, barcode_prefix, barcode_digits FROM tbl_branch_barcode_metal_prefix WHERE branch_id = ' . (int) $branch_id
                );
                if ($q) {
                    while ($r = mysqli_fetch_assoc($q)) {
                        $saved[(int) ($r['metal_id'] ?? 0)] = $r;
                    }
                    mysqli_free_result($q);
                }
            }

            $hardcoded = auragold_barcode_prefix_hardcoded_metal_defaults();
            foreach ($rows as $row) {
                $mid = (int) ($row['id'] ?? 0);
                $dn = trim((string) ($row['display_name'] ?? ''));
                if ($mid <= 0 || $dn === '') {
                    continue;
                }
                $prefix = '';
                $digits = 0;
                if (isset($saved[$mid])) {
                    $prefix = trim((string) ($saved[$mid]['barcode_prefix'] ?? ''));
                    $digits = (int) ($saved[$mid]['barcode_digits'] ?? 0);
                }
                if ($prefix === '') {
                    $hc = $hardcoded[$dn] ?? null;
                    $prefix = $hc ? (string) $hc['prefix'] : 'RN';
                }
                if ($digits < 1) {
                    $hc = $hardcoded[$dn] ?? null;
                    $digits = $hc ? (int) $hc['digits'] : $default_digits;
                }
                if ($digits < 1) {
                    $digits = 5;
                }
                $metals[] = [
                    'metal_id'     => $mid,
                    'display_name' => $dn,
                    'prefix'       => $prefix,
                    'digits'       => $digits,
                ];
            }
        }

        return [
            'default_prefix' => $default_prefix,
            'default_digits' => $default_digits,
            'metals'         => $metals,
        ];
    }
}

if (!function_exists('auragold_save_branch_barcode_prefix_settings')) {
    function auragold_save_branch_barcode_prefix_settings(int $branch_id, array $post): bool {
        $master = auragold_bps_master_conn();
        if (!$master instanceof mysqli || $branch_id <= 0) {
            return false;
        }
        auragold_ensure_branch_barcode_prefix_columns($master);

        $default_prefix = trim((string) ($post['default_prefix'] ?? ''));
        $default_digits = max(1, min(32, (int) ($post['default_digits'] ?? 5)));
        $prefix_esc = mysqli_real_escape_string($master, $default_prefix);

        $sets = [];
        if (auragold_bps_table_has_column($master, 'tbl_branches', 'barcode_num_digits')) {
            $sets[] = 'barcode_num_digits = ' . (int) $default_digits;
        }
        if (auragold_bps_table_has_column($master, 'tbl_branches', 'branch_barcode_prefix')) {
            $sets[] = 'branch_barcode_prefix = ' . ($default_prefix !== '' ? "'$prefix_esc'" : 'NULL');
        }
        if (empty($sets)) {
            return false;
        }
        $ok = @mysqli_query(
            $master,
            'UPDATE tbl_branches SET ' . implode(', ', $sets) . ' WHERE id = ' . (int) $branch_id . ' LIMIT 1'
        );
        if (!$ok) {
            return false;
        }

        $rows = isset($post['metal']) && is_array($post['metal']) ? $post['metal'] : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $metal_id = (int) ($row['metal_id'] ?? 0);
            if ($metal_id <= 0) {
                continue;
            }
            $mp = trim((string) ($row['prefix'] ?? ''));
            $md = max(1, min(32, (int) ($row['digits'] ?? $default_digits)));
            $mp_esc = mysqli_real_escape_string($master, $mp);
            @mysqli_query(
                $master,
                "INSERT INTO tbl_branch_barcode_metal_prefix (branch_id, metal_id, barcode_prefix, barcode_digits)
                 VALUES (" . (int) $branch_id . ", $metal_id, '$mp_esc', $md)
                 ON DUPLICATE KEY UPDATE barcode_prefix = VALUES(barcode_prefix), barcode_digits = VALUES(barcode_digits)"
            );
        }

        return true;
    }
}

if (!function_exists('auragold_branch_metal_barcode_prefix_row')) {
    /**
     * @return array{prefix:string,digits:int}|null
     */
    function auragold_branch_metal_barcode_prefix_row(int $branch_id, int $metal_id): ?array {
        $master = auragold_bps_master_conn();
        if (!$master instanceof mysqli || $branch_id <= 0 || $metal_id <= 0) {
            return null;
        }
        auragold_ensure_branch_barcode_prefix_columns($master);
        $q = @mysqli_query(
            $master,
            'SELECT barcode_prefix, barcode_digits FROM tbl_branch_barcode_metal_prefix
             WHERE branch_id = ' . (int) $branch_id . ' AND metal_id = ' . (int) $metal_id . ' LIMIT 1'
        );
        if (!$q || mysqli_num_rows($q) === 0) {
            if ($q) {
                mysqli_free_result($q);
            }
            return null;
        }
        $r = mysqli_fetch_assoc($q);
        mysqli_free_result($q);
        $prefix = trim((string) ($r['barcode_prefix'] ?? ''));
        $digits = (int) ($r['barcode_digits'] ?? 0);
        if ($prefix === '' && $digits < 1) {
            return null;
        }
        return [
            'prefix' => $prefix !== '' ? $prefix : 'RN',
            'digits' => $digits > 0 ? $digits : 5,
        ];
    }
}

if (!function_exists('auragold_barcode_prefix_candidates')) {
    /**
     * Branch prefix rules for expanding digits-only scans (0010 → BNG00010).
     *
     * @return list<array{prefix:string,digits:int}>
     */
    function auragold_barcode_prefix_candidates(mysqli $conn, int $branch_id, int $metal_id = 0): array
    {
        $candidates = [];
        $seen = [];

        $add = static function (string $prefix, int $digits) use (&$candidates, &$seen): void {
            $prefix = trim($prefix);
            if ($prefix === '') {
                return;
            }
            $digits = max(1, $digits);
            $key = strtolower($prefix) . ':' . $digits;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $candidates[] = ['prefix' => $prefix, 'digits' => $digits];
        };

        if ($metal_id > 0 && $branch_id > 0 && function_exists('auragold_branch_metal_barcode_prefix_row')) {
            $mr = auragold_branch_metal_barcode_prefix_row($branch_id, $metal_id);
            if (is_array($mr)) {
                $add((string) ($mr['prefix'] ?? ''), (int) ($mr['digits'] ?? 5));
            }
        }

        if ($branch_id > 0) {
            $master = auragold_bps_master_conn();
            if ($master instanceof mysqli) {
                auragold_ensure_branch_barcode_prefix_columns($master);
                $q = @mysqli_query(
                    $master,
                    'SELECT barcode_prefix, barcode_digits FROM tbl_branch_barcode_metal_prefix WHERE branch_id = ' . (int) $branch_id
                );
                if ($q) {
                    while ($row = mysqli_fetch_assoc($q)) {
                        $add((string) ($row['barcode_prefix'] ?? ''), (int) ($row['barcode_digits'] ?? 5));
                    }
                    mysqli_free_result($q);
                }
            }
            if (function_exists('auragold_barcode_default_prefix_digit')) {
                $bd = auragold_barcode_default_prefix_digit($conn, $branch_id);
                $add((string) ($bd['prefix'] ?? ''), (int) ($bd['digit'] ?? 5));
            }
        }

        if ($candidates === []) {
            $master = auragold_bps_master_conn();
            if ($master instanceof mysqli) {
                auragold_ensure_branch_barcode_prefix_columns($master);
                $q = @mysqli_query(
                    $master,
                    'SELECT DISTINCT barcode_prefix, barcode_digits FROM tbl_branch_barcode_metal_prefix
                     WHERE barcode_prefix IS NOT NULL AND TRIM(barcode_prefix) != \'\''
                );
                if ($q) {
                    while ($row = mysqli_fetch_assoc($q)) {
                        $add((string) ($row['barcode_prefix'] ?? ''), (int) ($row['barcode_digits'] ?? 5));
                    }
                    mysqli_free_result($q);
                }
            }
        }

        if ($candidates === []) {
            $tbl_exists = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_settings'");
            if ($tbl_exists && mysqli_num_rows($tbl_exists) > 0) {
                mysqli_free_result($tbl_exists);
                $rs = @mysqli_query($conn, 'SELECT barcode_prefix, barcode_digit_length FROM tbl_settings LIMIT 1');
                if ($rs && mysqli_num_rows($rs) > 0) {
                    $row = mysqli_fetch_assoc($rs);
                    mysqli_free_result($rs);
                    $add((string) ($row['barcode_prefix'] ?? ''), (int) ($row['barcode_digit_length'] ?? 5));
                } elseif ($rs) {
                    mysqli_free_result($rs);
                }
            } elseif ($tbl_exists) {
                mysqli_free_result($tbl_exists);
            }
        }

        return $candidates;
    }
}

if (!function_exists('auragold_expand_physical_scan_code_with_prefix')) {
    /**
     * Expand 4-digit label scan (0010) to full prefixed barcode (BNG00010).
     */
    function auragold_expand_physical_scan_code_with_prefix(mysqli $conn, string $scan_code, int $branch_id = -1, int $metal_id = 0): ?string
    {
        if (!function_exists('auragold_is_valid_physical_scan_code')) {
            require_once __DIR__ . '/auragold_barcode_scan_map.php';
        }
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return null;
        }
        if ($branch_id < 0 && function_exists('auragold_physical_scan_code_branch_id')) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        if ($branch_id <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $branch_id = (int) auragold_settings_main_branch_id();
        }

        if (function_exists('auragold_collect_derived_scan_barcode_matches')) {
            $stockMatches = auragold_collect_derived_scan_barcode_matches($conn, $scan_code, $branch_id, $metal_id, true);
            $picked = function_exists('auragold_pick_best_derived_scan_match')
                ? auragold_pick_best_derived_scan_match($stockMatches, $metal_id, $scan_code)
                : ($stockMatches[0] ?? null);
            if ($picked !== null && $picked !== '') {
                return $picked;
            }
            $stockMatches = auragold_collect_derived_scan_barcode_matches($conn, $scan_code, 0, $metal_id, false);
            $picked = function_exists('auragold_pick_best_derived_scan_match')
                ? auragold_pick_best_derived_scan_match($stockMatches, $metal_id, $scan_code)
                : ($stockMatches[0] ?? null);
            if ($picked !== null && $picked !== '') {
                return $picked;
            }
        }

        $candidates = auragold_barcode_prefix_candidates($conn, max(0, $branch_id), $metal_id);

        if (function_exists('auragold_collect_prefix_hints_for_scan_code')) {
            foreach (auragold_collect_prefix_hints_for_scan_code($conn, $scan_code) as $hint) {
                $candidates[] = $hint;
            }
        }

        if (function_exists('auragold_collect_prefix_hints_from_scan_map')) {
            foreach (auragold_collect_prefix_hints_from_scan_map($conn, $scan_code) as $hint) {
                $candidates[] = $hint;
            }
        }

        if (function_exists('auragold_collect_derived_scan_barcode_matches')) {
            $hintMatches = auragold_collect_derived_scan_barcode_matches($conn, $scan_code, 0, $metal_id, false);
            foreach ($hintMatches as $hintBc) {
                if (preg_match('/^([A-Za-z]+)(\d+)$/', $hintBc, $m)) {
                    $candidates[] = [
                        'prefix' => (string) $m[1],
                        'digits' => max(1, strlen((string) $m[2])),
                    ];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $serialForms = function_exists('auragold_serial_suffixes_for_scan_lookup')
            ? auragold_serial_suffixes_for_scan_lookup($scan_code)
            : [$scan_code];

        $existsHits = [];
        $derivedHits = [];
        $built = [];
        foreach ($candidates as $c) {
            $prefix = trim((string) ($c['prefix'] ?? ''));
            $digits = max(1, (int) ($c['digits'] ?? 5));
            if ($prefix === '') {
                continue;
            }
            foreach ($serialForms as $ser) {
                $ser = trim((string) $ser);
                if ($ser === '') {
                    continue;
                }
                $numPart = str_pad($ser, $digits, '0', STR_PAD_LEFT);
                if (strlen($ser) > $digits) {
                    $numPart = substr($ser, -$digits);
                }
                $full = $prefix . $numPart;
                $built[] = $full;
                if (function_exists('auragold_derive_physical_scan_code_from_barcode')
                    && auragold_derive_physical_scan_code_from_barcode($full) === $scan_code) {
                    $derivedHits[] = $full;
                }
                if (function_exists('auragold_barcode_exists_in_system') && auragold_barcode_exists_in_system($conn, $full)) {
                    $existsHits[] = $full;
                }
            }
        }

        if (count($existsHits) === 1) {
            return $existsHits[0];
        }
        if ($existsHits !== []) {
            $derivedMatches = [];
            foreach ($existsHits as $hit) {
                if (function_exists('auragold_derive_physical_scan_code_from_barcode')
                    && auragold_derive_physical_scan_code_from_barcode($hit) === $scan_code) {
                    $derivedMatches[] = $hit;
                }
            }
            if ($derivedMatches !== []) {
                if (function_exists('auragold_pick_best_derived_scan_match')) {
                    $picked = auragold_pick_best_derived_scan_match($derivedMatches, $metal_id, $scan_code);
                    if ($picked !== null && $picked !== '') {
                        return $picked;
                    }
                }
                usort($derivedMatches, static fn ($a, $b) => strlen($a) <=> strlen($b));
                return $derivedMatches[0];
            }
            usort($existsHits, static fn ($a, $b) => strlen($a) <=> strlen($b));
            return $existsHits[0];
        }
        if (count($derivedHits) === 1) {
            return $derivedHits[0];
        }
        if ($derivedHits !== []) {
            if (function_exists('auragold_pick_best_derived_scan_match')) {
                $picked = auragold_pick_best_derived_scan_match($derivedHits, $metal_id, $scan_code);
                if ($picked !== null && $picked !== '') {
                    return $picked;
                }
            }
            usort($derivedHits, static fn ($a, $b) => strlen($a) <=> strlen($b));
            return $derivedHits[0];
        }
        if (count($built) === 1) {
            return $built[0];
        }

        return null;
    }
}

if (!function_exists('auragold_resolve_scanned_barcode')) {
    /**
     * Expand digits-only scanner input (e.g. 00006) to full branch/metal barcode (e.g. FZ00006).
     * If the scan already includes a letter prefix, it is returned unchanged.
     *
     * @param array{branch_id?:int,metal_id?:int} $opts
     */
    function auragold_resolve_scanned_barcode(mysqli $conn, string $barcode, array $opts = []): string {
        $barcode = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $barcode));
        if ($barcode === '') {
            return '';
        }

        $branch_id = (int) ($opts['branch_id'] ?? 0);
        if ($branch_id <= 0 && function_exists('auragold_physical_scan_code_branch_id')) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        if ($branch_id <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $branch_id = (int) auragold_settings_main_branch_id();
        }

        $metal_id = (int) ($opts['metal_id'] ?? 0);
        $digitsOnly = preg_replace('/\D/', '', $barcode);
        $isPureNumeric = ($digitsOnly !== '' && $digitsOnly === $barcode);
        $isPhysicalScanToken = $isPureNumeric && strlen($digitsOnly) >= 1 && strlen($digitsOnly) <= 4;

        if (!$isPhysicalScanToken
            && function_exists('auragold_barcode_exists_in_system')
            && auragold_barcode_exists_in_system($conn, $barcode)) {
            return $barcode;
        }

        // 82×38 physical label scan tokens (0010 → BNG00010) via map, suffix, then prefix rules.
        if ($isPhysicalScanToken) {
            $padded4 = str_pad($digitsOnly, 4, '0', STR_PAD_LEFT);
            if (!function_exists('auragold_resolve_physical_scan_input')) {
                require_once __DIR__ . '/auragold_barcode_scan_map.php';
            }
            $mapped = auragold_resolve_physical_scan_input($conn, $padded4, $branch_id, $metal_id);
            if ($mapped !== null && $mapped !== '') {
                return $mapped;
            }
            $expanded = auragold_expand_physical_scan_code_with_prefix($conn, $padded4, $branch_id, $metal_id);
            if ($expanded !== null && $expanded !== '') {
                if (function_exists('auragold_claim_physical_scan_code_for_barcode')) {
                    auragold_claim_physical_scan_code_for_barcode($conn, $expanded, $padded4, $branch_id);
                }
                return $expanded;
            }
            $barcode = $padded4;
        }

        // Already includes a letter prefix (FZ00006, KA00006, …) — use as scanned.
        if (preg_match('/^[A-Za-z]/', $barcode)) {
            return $barcode;
        }

        $serial = preg_replace('/\D/', '', $barcode);
        if ($serial === '') {
            return $barcode;
        }

        $candidates = [];
        if ($metal_id > 0 && $branch_id > 0 && function_exists('auragold_branch_metal_barcode_prefix_row')) {
            $mr = auragold_branch_metal_barcode_prefix_row($branch_id, $metal_id);
            if ($mr && trim((string) ($mr['prefix'] ?? '')) !== '') {
                $candidates[] = [
                    'prefix' => trim((string) $mr['prefix']),
                    'digits' => max(1, (int) ($mr['digits'] ?? 5)),
                ];
            }
        }
        if ($branch_id > 0) {
            foreach (auragold_barcode_prefix_candidates($conn, $branch_id, $metal_id) as $c) {
                $candidates[] = $c;
            }
        }

        $seen = [];
        $uniq = [];
        foreach ($candidates as $c) {
            $p = trim((string) ($c['prefix'] ?? ''));
            if ($p === '') {
                continue;
            }
            $k = strtolower($p);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $uniq[] = [
                'prefix' => $p,
                'digits' => max(1, (int) ($c['digits'] ?? 5)),
            ];
        }

        $buildFull = static function (string $prefix, int $digits, string $ser): string {
            $numPart = str_pad($ser, $digits, '0', STR_PAD_LEFT);
            if (strlen($ser) > $digits) {
                $numPart = $ser;
            }
            return $prefix . $numPart;
        };

        foreach ($uniq as $c) {
            $full = $buildFull($c['prefix'], (int) $c['digits'], $serial);
            if (function_exists('auragold_barcode_exists_in_system') && auragold_barcode_exists_in_system($conn, $full)) {
                return $full;
            }
        }

        // Suffix match in stock (branch-scoped when possible): …00006 → FZ00006
        $serial_esc = mysqli_real_escape_string($conn, $serial);
        $branch_sql = '';
        static $stock_has_branch = null;
        if ($stock_has_branch === null) {
            $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'branch_id'");
            $stock_has_branch = ($chk && mysqli_num_rows($chk) > 0);
            if ($chk) {
                mysqli_free_result($chk);
            }
        }
        if ($stock_has_branch && $branch_id > 0) {
            $bid = (int) $branch_id;
            $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
            if ($main > 0 && $bid === $main) {
                $branch_sql = ' AND (branch_id = ' . $bid . ' OR branch_id IS NULL OR branch_id = 0)';
            } else {
                $branch_sql = ' AND COALESCE(branch_id, 0) = ' . $bid;
            }
        }
        $suffix_rows = function_exists('getList')
            ? getList("
                SELECT TRIM(barcode) AS bc, product_characteristic_id
                FROM tbl_stock
                WHERE status = 1
                AND barcode IS NOT NULL AND TRIM(barcode) != ''
                AND TRIM(barcode) LIKE '%$serial_esc'
                $branch_sql
                ORDER BY id DESC
                LIMIT 10
            ")
            : [];
        if (is_array($suffix_rows) && count($suffix_rows) === 1 && !empty($suffix_rows[0]['bc'])) {
            return trim((string) $suffix_rows[0]['bc']);
        }
        if (is_array($suffix_rows) && count($suffix_rows) > 1 && $metal_id > 0) {
            foreach ($suffix_rows as $sr) {
                $bc = trim((string) ($sr['bc'] ?? ''));
                $pcid = (int) ($sr['product_characteristic_id'] ?? 0);
                if ($bc === '' || $pcid <= 0) {
                    continue;
                }
                $pc = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = $pcid LIMIT 1");
                if ($pc && (int) ($pc['metal_id'] ?? 0) === $metal_id) {
                    return $bc;
                }
            }
        }

        if (!empty($uniq)) {
            $c = $uniq[0];
            return $buildFull($c['prefix'], (int) $c['digits'], $serial);
        }

        return $barcode;
    }
}
