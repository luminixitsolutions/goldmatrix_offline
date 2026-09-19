<?php
/**
 * Persistent 4-digit physical scan codes for small 82×38 thermal labels.
 * Maps e.g. 0001 → GD00001 (real barcode unchanged in inventory).
 */

if (!function_exists('auragold_ensure_tbl_barcode_physical_scan_map')) {
    function auragold_ensure_tbl_barcode_physical_scan_map($conn): bool
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = false;
        if ($done) {
            return true;
        }
        $sql = "CREATE TABLE IF NOT EXISTS `tbl_barcode_physical_scan_map` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `branch_id` int unsigned NOT NULL DEFAULT 0,
            `barcode` varchar(100) NOT NULL,
            `scan_code` char(4) NOT NULL,
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_bpsm_barcode_branch` (`barcode`(100), `branch_id`),
            UNIQUE KEY `uk_bpsm_scan_code_branch` (`scan_code`, `branch_id`),
            KEY `idx_bpsm_barcode` (`barcode`(100)),
            KEY `idx_bpsm_scan_code` (`scan_code`),
            KEY `idx_bpsm_branch_id` (`branch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $ok = (bool) @mysqli_query($conn, $sql);
        if ($ok) {
            $done = true;
        }
        return $ok;
    }
}

if (!function_exists('auragold_physical_scan_code_branch_id')) {
    function auragold_physical_scan_code_branch_id(): int
    {
        $branch_id = 0;
        if (function_exists('auragold_effective_branch_id')) {
            $branch_id = (int) auragold_effective_branch_id();
        }
        if ($branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
            $branch_id = (int) $_SESSION['working_branch_id'];
        } elseif ($branch_id <= 0 && !empty($_SESSION['branch_id'])) {
            $branch_id = (int) $_SESSION['branch_id'];
        }
        return max(0, $branch_id);
    }
}

if (!function_exists('auragold_is_valid_physical_scan_code')) {
    function auragold_is_valid_physical_scan_code(string $scan_code): bool
    {
        return (bool) preg_match('/^\d{4}$/', trim($scan_code));
    }
}

if (!function_exists('auragold_derive_physical_scan_code_from_barcode')) {
    /**
     * Deterministic 4-digit fallback from barcode suffix (e.g. GD00001 → 0001).
     */
    function auragold_derive_physical_scan_code_from_barcode(string $barcode): ?string
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        if (preg_match('/(\d{4})$/', $barcode, $m)) {
            return $m[1];
        }
        if (preg_match('/(\d+)$/', $barcode, $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= 9999) {
                return str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            }
        }
        return null;
    }
}

if (!function_exists('auragold_serial_suffixes_for_scan_lookup')) {
    /**
     * SQL suffix variants for a 4-digit scan token (0123 → 0123 and 123 for ASD123).
     *
     * @return list<string>
     */
    function auragold_serial_suffixes_for_scan_lookup(string $scan_code): array
    {
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return [];
        }
        $out = [$scan_code];
        $trimmed = ltrim($scan_code, '0');
        if ($trimmed !== '' && $trimmed !== $scan_code) {
            $out[] = $trimmed;
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('auragold_scan_suffix_sql_fragment')) {
    function auragold_scan_suffix_sql_fragment(mysqli $conn, string $scan_code): string
    {
        $conds = [];
        foreach (auragold_serial_suffixes_for_scan_lookup($scan_code) as $suffix) {
            $esc = mysqli_real_escape_string($conn, $suffix);
            $n = strlen($suffix);
            if ($n >= 1 && $n <= 4) {
                $conds[] = 'RIGHT(TRIM(barcode), ' . $n . ") = '$esc'";
            }
        }
        if ($conds === []) {
            return '1=0';
        }

        return '(' . implode(' OR ', $conds) . ')';
    }
}

if (!function_exists('auragold_pick_best_derived_scan_match')) {
    /**
     * @param list<string> $matches
     */
    function auragold_pick_best_derived_scan_match(array $matches, int $metal_id = 0, string $scan_code = ''): ?string
    {
        if ($matches === []) {
            return null;
        }
        $scan_code = trim($scan_code);
        if (count($matches) === 1) {
            return $matches[0];
        }

        global $conn;
        if ($scan_code !== '' && !empty($conn) && $conn instanceof mysqli
            && function_exists('auragold_lookup_barcode_by_physical_scan_code')) {
            $mapped = auragold_lookup_barcode_by_physical_scan_code($conn, $scan_code);
            if ($mapped !== null && $mapped !== '') {
                foreach ($matches as $bc) {
                    if (strcasecmp($bc, $mapped) === 0) {
                        return $bc;
                    }
                }
            }
            if (function_exists('auragold_lookup_physical_scan_code_for_barcode')) {
                foreach ($matches as $bc) {
                    $owned = auragold_lookup_physical_scan_code_for_barcode($conn, $bc);
                    if ($owned === $scan_code) {
                        return $bc;
                    }
                }
            }
        }

        if ($metal_id > 0 && function_exists('getRecord') && !empty($conn) && $conn instanceof mysqli) {
            foreach ($matches as $bc) {
                $bc_esc = mysqli_real_escape_string($conn, $bc);
                $row = getRecord("SELECT metal_id FROM tbl_stock WHERE barcode = '$bc_esc' AND status = 1 ORDER BY id DESC LIMIT 1");
                if ($row && (int) ($row['metal_id'] ?? 0) === $metal_id) {
                    return $bc;
                }
            }
        }

        $score = static function (string $bc) use ($scan_code): int {
            $pts = max(0, 24 - strlen($bc));
            if (preg_match('/^([A-Za-z]{2,4})(\d+)$/', $bc, $m)) {
                $pts += 12;
                if (strlen((string) $m[2]) <= 5) {
                    $pts += 8;
                } else {
                    $pts -= 16;
                }
                if ($scan_code !== '' && function_exists('auragold_derive_physical_scan_code_from_barcode')
                    && auragold_derive_physical_scan_code_from_barcode($bc) === $scan_code) {
                    $pts += 6;
                }
            }
            return $pts;
        };

        usort($matches, static function (string $a, string $b) use ($score): int {
            $diff = $score($b) <=> $score($a);
            if ($diff !== 0) {
                return $diff;
            }
            return strlen($a) <=> strlen($b);
        });

        return $matches[0];
    }
}

if (!function_exists('auragold_force_upsert_physical_scan_code')) {
    /**
     * Insert or update scan-code mapping (used when auto-assign fails but a code is known).
     */
    function auragold_force_upsert_physical_scan_code(mysqli $conn, string $barcode, string $scan_code, int $branch_id = -1): bool
    {
        $barcode = trim($barcode);
        $scan_code = trim($scan_code);
        if ($barcode === '' || !auragold_is_valid_physical_scan_code($scan_code)) {
            return false;
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return false;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);
        $bc_esc = mysqli_real_escape_string($conn, $barcode);
        $sc_esc = mysqli_real_escape_string($conn, $scan_code);

        $existing_bc = auragold_lookup_physical_scan_code_for_barcode($conn, $barcode, $branch_id);
        if ($existing_bc !== null) {
            return true;
        }
        $existing_sc = auragold_lookup_barcode_by_physical_scan_code($conn, $scan_code, $branch_id);
        if ($existing_sc !== null && strcasecmp($existing_sc, $barcode) !== 0) {
            return false;
        }

        $ok = @mysqli_query(
            $conn,
            "INSERT INTO tbl_barcode_physical_scan_map (branch_id, barcode, scan_code, status)
             VALUES (" . (int) $branch_id . ", '$bc_esc', '$sc_esc', 1)
             ON DUPLICATE KEY UPDATE scan_code = VALUES(scan_code), status = 1, updated_at = CURRENT_TIMESTAMP"
        );
        if (!$ok) {
            error_log('[auragold] force upsert physical scan code failed for ' . $barcode . ' -> ' . $scan_code);
        }
        return (bool) $ok;
    }
}

if (!function_exists('auragold_force_assign_scan_code_for_barcode')) {
    /**
     * Print-time mapping: 0005 → BNG00005 (overrides stale/conflicting scan_code rows).
     */
    function auragold_force_assign_scan_code_for_barcode(mysqli $conn, string $barcode, string $scan_code, int $branch_id = -1): bool
    {
        $barcode = trim($barcode);
        $scan_code = trim($scan_code);
        if ($barcode === '' || !auragold_is_valid_physical_scan_code($scan_code)) {
            return false;
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return false;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);
        $bc_esc = mysqli_real_escape_string($conn, $barcode);
        $sc_esc = mysqli_real_escape_string($conn, $scan_code);

        @mysqli_query(
            $conn,
            "DELETE FROM tbl_barcode_physical_scan_map
             WHERE branch_id = " . (int) $branch_id . "
               AND scan_code = '$sc_esc'
               AND barcode != '$bc_esc'"
        );

        $existing = auragold_lookup_physical_scan_code_for_barcode($conn, $barcode, $branch_id);
        if ($existing === $scan_code) {
            return true;
        }
        if ($existing !== null) {
            return (bool) @mysqli_query(
                $conn,
                "UPDATE tbl_barcode_physical_scan_map
                 SET scan_code = '$sc_esc', status = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE barcode = '$bc_esc' AND branch_id = " . (int) $branch_id . "
                 LIMIT 1"
            );
        }

        return (bool) @mysqli_query(
            $conn,
            "INSERT INTO tbl_barcode_physical_scan_map (branch_id, barcode, scan_code, status)
             VALUES (" . (int) $branch_id . ", '$bc_esc', '$sc_esc', 1)"
        );
    }
}

if (!function_exists('auragold_scan_map_query_rows')) {
    /**
     * @return list<array<string,mixed>>
     */
    function auragold_scan_map_query_rows(mysqli $conn, string $sql): array
    {
        if (function_exists('getList')) {
            $rows = getList($sql);
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }
        }
        $rows = [];
        $q = @mysqli_query($conn, $sql);
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $rows[] = $row;
            }
            mysqli_free_result($q);
        }

        return $rows;
    }
}

if (!function_exists('auragold_next_free_physical_scan_code')) {
    /**
     * Next unused 4-digit scan code for this branch (optionally skip batch-reserved codes).
     *
     * @param array<string,bool> $reserved
     */
    function auragold_next_free_physical_scan_code(mysqli $conn, int $branch_id = -1, array $reserved = []): ?string
    {
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return null;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);

        for ($n = 1; $n <= 9999; $n++) {
            $code = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            if (isset($reserved[$code])) {
                continue;
            }
            $owner = auragold_lookup_barcode_by_physical_scan_code($conn, $code, $branch_id);
            if ($owner === null || $owner === '') {
                return $code;
            }
        }

        return null;
    }
}

if (!function_exists('auragold_resolve_four_digit_scan_to_full_barcode')) {
    /**
     * Expand 4-digit label scan to full barcode (0005 → BNG00005).
     */
    function auragold_resolve_four_digit_scan_to_full_barcode(mysqli $conn, string $scan_code, int $branch_id = -1, int $metal_id = 0): ?string
    {
        $digits = preg_replace('/\D/', '', trim($scan_code));
        if ($digits === '' || strlen($digits) > 4) {
            return null;
        }
        $scan_code = str_pad($digits, 4, '0', STR_PAD_LEFT);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return null;
        }
        if ($branch_id < 0 && function_exists('auragold_physical_scan_code_branch_id')) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        if ($branch_id <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $branch_id = (int) auragold_settings_main_branch_id();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['auragold_recent_print_scan_map'])
            && is_array($_SESSION['auragold_recent_print_scan_map'])
            && !empty($_SESSION['auragold_recent_print_scan_map'][$scan_code])) {
            $fromSession = trim((string) $_SESSION['auragold_recent_print_scan_map'][$scan_code]);
            if ($fromSession !== '') {
                if ($conn instanceof mysqli && function_exists('auragold_force_assign_scan_code_for_barcode')) {
                    auragold_force_assign_scan_code_for_barcode($conn, $fromSession, $scan_code, $branch_id);
                }
                return $fromSession;
            }
        }

        $branch_ids = [];
        if ($branch_id > 0) {
            $branch_ids[] = $branch_id;
        }
        if ($branch_id !== 0) {
            $branch_ids[] = 0;
        }
        if ($branch_ids === []) {
            $branch_ids[] = 0;
        }
        $branch_ids = array_values(array_unique($branch_ids));

        foreach ($branch_ids as $bid) {
            $fromMap = auragold_lookup_barcode_by_physical_scan_code($conn, $scan_code, $bid);
            if ($fromMap !== null && $fromMap !== '') {
                return $fromMap;
            }
        }

        $fromStock = auragold_lookup_barcode_by_derived_scan_suffix($conn, $scan_code, $branch_id, $metal_id);
        if ($fromStock !== null && $fromStock !== '') {
            auragold_force_assign_scan_code_for_barcode($conn, $fromStock, $scan_code, $branch_id);
            return $fromStock;
        }

        if (!function_exists('auragold_expand_physical_scan_code_with_prefix')) {
            require_once __DIR__ . '/auragold_barcode_prefix_settings.php';
        }
        if (function_exists('auragold_expand_physical_scan_code_with_prefix')) {
            $fromPrefix = auragold_expand_physical_scan_code_with_prefix($conn, $scan_code, $branch_id, $metal_id);
            if ($fromPrefix !== null && $fromPrefix !== '') {
                auragold_force_assign_scan_code_for_barcode($conn, $fromPrefix, $scan_code, $branch_id);
                return $fromPrefix;
            }
        }

        return null;
    }
}

if (!function_exists('auragold_register_barcode_physical_scan_map')) {
    /**
     * Save print-time mapping (BBR00005 → 0005) whenever label data is loaded.
     */
    function auragold_register_barcode_physical_scan_map(mysqli $conn, string $barcode, int $branch_id = -1): void
    {
        $barcode = trim($barcode);
        if ($barcode === '' || !preg_match('/^[A-Za-z]/', $barcode)) {
            return;
        }
        $derived = auragold_derive_physical_scan_code_from_barcode($barcode);
        if ($derived === null || !auragold_is_valid_physical_scan_code($derived)) {
            return;
        }
        auragold_force_assign_scan_code_for_barcode($conn, $barcode, $derived, $branch_id);
    }
}

if (!function_exists('auragold_collect_prefix_hints_from_scan_map')) {
    /**
     * Prefix rules from tbl_barcode_physical_scan_map (recent prints).
     *
     * @return list<array{prefix:string,digits:int}>
     */
    function auragold_collect_prefix_hints_from_scan_map(mysqli $conn, string $scan_code): array
    {
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return [];
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return [];
        }
        $sc_esc = mysqli_real_escape_string($conn, $scan_code);
        $rows = auragold_scan_map_query_rows(
            $conn,
            "SELECT barcode, scan_code
             FROM tbl_barcode_physical_scan_map
             WHERE status = 1
               AND (scan_code = '$sc_esc'
                    OR barcode REGEXP '^[A-Za-z][A-Za-z0-9]+$')
             ORDER BY updated_at DESC, id DESC
             LIMIT 200"
        );
        $hints = [];
        $seen = [];
        foreach ($rows as $row) {
            $bc = trim((string) ($row['barcode'] ?? ''));
            if ($bc === '') {
                continue;
            }
            $mapped = trim((string) ($row['scan_code'] ?? ''));
            if ($mapped === $scan_code || auragold_derive_physical_scan_code_from_barcode($bc) === $scan_code) {
                if (!preg_match('/^([A-Za-z]+)(\d+)$/', $bc, $m)) {
                    continue;
                }
                $prefix = (string) $m[1];
                $digits = max(1, strlen((string) $m[2]));
                $key = strtolower($prefix) . ':' . $digits;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $hints[] = ['prefix' => $prefix, 'digits' => $digits];
            }
        }

        return $hints;
    }
}

if (!function_exists('auragold_collect_prefix_hints_for_scan_code')) {
    /**
     * Discover letter-prefix rules from inventory rows matching a 4-digit scan suffix.
     *
     * @return list<array{prefix:string,digits:int}>
     */
    function auragold_collect_prefix_hints_for_scan_code(mysqli $conn, string $scan_code): array
    {
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return [];
        }
        $matches = auragold_collect_derived_scan_barcode_matches($conn, $scan_code, 0, 0, false);
        $hints = [];
        $seen = [];
        foreach ($matches as $bc) {
            if (!preg_match('/^([A-Za-z]+)(\d+)$/', $bc, $m)) {
                continue;
            }
            $prefix = (string) $m[1];
            $digits = max(1, strlen((string) $m[2]));
            $key = strtolower($prefix) . ':' . $digits;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $hints[] = ['prefix' => $prefix, 'digits' => $digits];
        }

        return $hints;
    }
}

if (!function_exists('auragold_claim_physical_scan_code_for_barcode')) {
    /**
     * Assign or update a barcode's 4-digit scan code when the code is free or already owned by this barcode.
     */
    function auragold_claim_physical_scan_code_for_barcode(mysqli $conn, string $barcode, string $scan_code, int $branch_id = -1): bool
    {
        $barcode = trim($barcode);
        $scan_code = trim($scan_code);
        if ($barcode === '' || !auragold_is_valid_physical_scan_code($scan_code)) {
            return false;
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return false;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);

        $owner = auragold_lookup_barcode_by_physical_scan_code($conn, $scan_code, $branch_id);
        if ($owner !== null && strcasecmp($owner, $barcode) !== 0) {
            return false;
        }

        $existing = auragold_lookup_physical_scan_code_for_barcode($conn, $barcode, $branch_id);
        if ($existing === $scan_code) {
            return true;
        }

        $bc_esc = mysqli_real_escape_string($conn, $barcode);
        $sc_esc = mysqli_real_escape_string($conn, $scan_code);

        if ($existing !== null) {
            $ok = @mysqli_query(
                $conn,
                "UPDATE tbl_barcode_physical_scan_map
                 SET scan_code = '$sc_esc', status = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE barcode = '$bc_esc'
                   AND branch_id = " . (int) $branch_id . "
                 LIMIT 1"
            );
            return (bool) $ok;
        }

        $ok = @mysqli_query(
            $conn,
            "INSERT INTO tbl_barcode_physical_scan_map (branch_id, barcode, scan_code, status)
             VALUES (" . (int) $branch_id . ", '$bc_esc', '$sc_esc', 1)"
        );
        return (bool) $ok;
    }
}

if (!function_exists('auragold_resolve_physical_scan_code_for_print')) {
    /**
     * Resolve scan code for label print: suffix-derived (BNG00010 → 0010) → DB → assign.
     */
    function auragold_resolve_physical_scan_code_for_print($conn, string $barcode, int $branch_id = -1): ?string
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }

        $derived = auragold_derive_physical_scan_code_from_barcode($barcode);

        if (!empty($conn) && $conn instanceof mysqli) {
            if ($derived !== null && auragold_is_valid_physical_scan_code($derived)) {
                $existing = auragold_lookup_physical_scan_code_for_barcode($conn, $barcode, $branch_id);
                if ($existing === $derived) {
                    return $derived;
                }
                if (auragold_claim_physical_scan_code_for_barcode($conn, $barcode, $derived, $branch_id)) {
                    return $derived;
                }
                if (auragold_force_assign_scan_code_for_barcode($conn, $barcode, $derived, $branch_id)) {
                    return $derived;
                }
                if ($existing !== null) {
                    return $existing;
                }
            }

            $from_db = auragold_get_or_assign_physical_scan_code($conn, $barcode, $branch_id);
            if ($from_db !== null) {
                return $from_db;
            }
        }

        return $derived;
    }
}

if (!function_exists('auragold_lookup_barcode_by_physical_scan_code')) {
    /**
     * Resolve 4-digit scan token to real barcode (branch-scoped, then any branch).
     */
    function auragold_lookup_barcode_by_physical_scan_code(mysqli $conn, string $scan_code, int $branch_id = -1): ?string
    {
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return null;
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return null;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);
        $sc_esc = mysqli_real_escape_string($conn, $scan_code);

        $branch_ids = [];
        if ($branch_id > 0) {
            $branch_ids[] = $branch_id;
        }
        if ($branch_id !== 0) {
            $branch_ids[] = 0;
        }
        if ($branch_ids === []) {
            $branch_ids[] = 0;
        }
        $branch_ids = array_values(array_unique($branch_ids));

        foreach ($branch_ids as $bid) {
            $rows = auragold_scan_map_query_rows(
                $conn,
                "SELECT barcode
                 FROM tbl_barcode_physical_scan_map
                 WHERE scan_code = '$sc_esc'
                   AND branch_id = " . (int) $bid . "
                   AND status = 1
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1"
            );
            if (!empty($rows[0]['barcode'])) {
                return trim((string) $rows[0]['barcode']);
            }
        }

        $rows = auragold_scan_map_query_rows(
            $conn,
            "SELECT barcode
             FROM tbl_barcode_physical_scan_map
             WHERE scan_code = '$sc_esc'
               AND status = 1
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        if (!empty($rows[0]['barcode'])) {
            return trim((string) $rows[0]['barcode']);
        }

        return null;
    }
}

if (!function_exists('auragold_lookup_physical_scan_code_for_barcode')) {
    /**
     * Existing scan code for a real barcode (does not assign).
     */
    function auragold_lookup_physical_scan_code_for_barcode(mysqli $conn, string $barcode, int $branch_id = -1): ?string
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return null;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);
        $bc_esc = mysqli_real_escape_string($conn, $barcode);
        $row = function_exists('getRecord')
            ? getRecord("
                SELECT scan_code
                FROM tbl_barcode_physical_scan_map
                WHERE barcode = '$bc_esc'
                  AND branch_id = " . (int) $branch_id . "
                  AND status = 1
                LIMIT 1
            ")
            : null;
        if (!$row || !auragold_is_valid_physical_scan_code((string) ($row['scan_code'] ?? ''))) {
            return null;
        }
        return (string) $row['scan_code'];
    }
}

if (!function_exists('auragold_get_or_assign_physical_scan_code')) {
    /**
     * Return persistent 4-digit scan code for a real barcode; assign next free code if needed.
     *
     * @return string|null 4-digit code or null when limit exceeded / DB error
     */
    function auragold_get_or_assign_physical_scan_code(mysqli $conn, string $barcode, int $branch_id = -1): ?string
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        if (!auragold_ensure_tbl_barcode_physical_scan_map($conn)) {
            return null;
        }
        if ($branch_id < 0) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        $branch_id = max(0, $branch_id);

        $existing = auragold_lookup_physical_scan_code_for_barcode($conn, $barcode, $branch_id);
        if ($existing !== null) {
            $derived = auragold_derive_physical_scan_code_from_barcode($barcode);
            if ($derived !== null
                && $existing !== $derived
                && auragold_claim_physical_scan_code_for_barcode($conn, $barcode, $derived, $branch_id)) {
                return $derived;
            }
            return $existing;
        }

        $derived = auragold_derive_physical_scan_code_from_barcode($barcode);
        if ($derived !== null && auragold_claim_physical_scan_code_for_barcode($conn, $barcode, $derived, $branch_id)) {
            return $derived;
        }

        $bc_esc = mysqli_real_escape_string($conn, $barcode);
        $max_row = function_exists('getRecord')
            ? getRecord("
                SELECT MAX(CAST(scan_code AS UNSIGNED)) AS mx
                FROM tbl_barcode_physical_scan_map
                WHERE branch_id = " . (int) $branch_id . "
                  AND scan_code REGEXP '^[0-9]{4}$'
            ")
            : null;
        $next = (int) ($max_row['mx'] ?? 0) + 1;
        if ($next < 1 || $next > 9999) {
            error_log('[auragold] Physical scan code limit reached for branch ' . $branch_id);
            return null;
        }
        $scan_code = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        $sc_esc = mysqli_real_escape_string($conn, $scan_code);

        $ok = @mysqli_query(
            $conn,
            "INSERT INTO tbl_barcode_physical_scan_map (branch_id, barcode, scan_code, status)
             VALUES (" . (int) $branch_id . ", '$bc_esc', '$sc_esc', 1)"
        );
        if ($ok) {
            return $scan_code;
        }

        // Race / duplicate: re-read existing mapping for this barcode.
        $existing = auragold_lookup_physical_scan_code_for_barcode($conn, $barcode, $branch_id);
        if ($existing !== null) {
            return $existing;
        }

        error_log('[auragold] Failed to assign physical scan code for barcode ' . $barcode);
        return null;
    }
}

if (!function_exists('auragold_stock_branch_sql_for_scan')) {
    function auragold_stock_branch_sql_for_scan(mysqli $conn, int $branch_id, string $alias = ''): string
    {
        if ($branch_id <= 0) {
            return '';
        }
        static $stock_has_branch = null;
        if ($stock_has_branch === null) {
            $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'branch_id'");
            $stock_has_branch = ($chk && mysqli_num_rows($chk) > 0);
            if ($chk) {
                mysqli_free_result($chk);
            }
        }
        if (!$stock_has_branch) {
            return '';
        }
        $col = ($alias !== '' ? ($alias . '.') : '') . 'branch_id';
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $branch_id === $main) {
            return ' AND (' . $col . ' = ' . (int) $branch_id . ' OR ' . $col . ' IS NULL OR ' . $col . ' = 0)';
        }

        return ' AND COALESCE(' . $col . ', 0) = ' . (int) $branch_id;
    }
}

if (!function_exists('auragold_collect_derived_scan_barcode_matches')) {
    /**
     * Find real barcodes whose 4-digit physical scan suffix matches (0010 → BNG00010).
     *
     * @return list<string>
     */
    function auragold_collect_derived_scan_barcode_matches(mysqli $conn, string $scan_code, int $branch_id = -1, int $metal_id = 0, bool $use_branch = true): array
    {
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return [];
        }
        if ($branch_id < 0 && function_exists('auragold_physical_scan_code_branch_id')) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }
        if ($branch_id <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $branch_id = (int) auragold_settings_main_branch_id();
        }

        $suffix_sql = auragold_scan_suffix_sql_fragment($conn, $scan_code);
        $branch_sql = ($use_branch && $branch_id > 0) ? auragold_stock_branch_sql_for_scan($conn, $branch_id) : '';
        $sj_status_sql = "(status = 'active' OR status = 1 OR status IS NULL OR TRIM(COALESCE(status, '')) = '')";
        $queries = [
            "SELECT TRIM(barcode) AS bc, IFNULL(metal_id, 0) AS metal_id FROM tbl_stock
             WHERE status = 1 AND barcode IS NOT NULL AND TRIM(barcode) != ''
               AND $suffix_sql $branch_sql
             ORDER BY id DESC LIMIT 30",
            "SELECT TRIM(barcode) AS bc, IFNULL(metal_id, 0) AS metal_id FROM tbl_stock_journal
             WHERE $sj_status_sql AND barcode IS NOT NULL AND TRIM(barcode) != ''
               AND $suffix_sql
             ORDER BY id DESC LIMIT 30",
            "SELECT TRIM(barcode) AS bc, 0 AS metal_id FROM tbl_product_characteristics
             WHERE status = 1 AND barcode IS NOT NULL AND TRIM(barcode) != ''
               AND $suffix_sql
             ORDER BY id DESC LIMIT 30",
            "SELECT TRIM(barcode) AS bc, 0 AS metal_id FROM tbl_purchase_invoice_items
             WHERE active = 1 AND barcode IS NOT NULL AND TRIM(barcode) != ''
               AND $suffix_sql
             ORDER BY id DESC LIMIT 30",
        ];

        $seen = [];
        $matches = [];
        $metalHits = [];
        foreach ($queries as $sql) {
            $rows = auragold_scan_map_query_rows($conn, $sql);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $bc = trim((string) ($row['bc'] ?? ''));
                if ($bc === '' || isset($seen[strtolower($bc)])) {
                    continue;
                }
                $derived = auragold_derive_physical_scan_code_from_barcode($bc);
                if ($derived !== $scan_code) {
                    continue;
                }
                $seen[strtolower($bc)] = true;
                $matches[] = $bc;
                if ($metal_id > 0 && (int) ($row['metal_id'] ?? 0) === $metal_id) {
                    $metalHits[] = $bc;
                }
            }
        }

        if (count($metalHits) === 1) {
            return $metalHits;
        }
        if ($metalHits !== []) {
            return $metalHits;
        }

        return $matches;
    }
}

if (!function_exists('auragold_lookup_barcode_by_derived_scan_suffix')) {
    /**
     * When physical map is stale, match stock by barcode suffix (0010 → …00010).
     */
    function auragold_lookup_barcode_by_derived_scan_suffix(mysqli $conn, string $scan_code, int $branch_id = -1, int $metal_id = 0): ?string
    {
        $scan_code = trim($scan_code);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return null;
        }
        if ($branch_id < 0 && function_exists('auragold_physical_scan_code_branch_id')) {
            $branch_id = auragold_physical_scan_code_branch_id();
        }

        $matches = auragold_collect_derived_scan_barcode_matches($conn, $scan_code, $branch_id, $metal_id, true);
        $picked = auragold_pick_best_derived_scan_match($matches, $metal_id, $scan_code);
        if ($picked !== null) {
            return $picked;
        }

        $matches = auragold_collect_derived_scan_barcode_matches($conn, $scan_code, 0, $metal_id, false);
        return auragold_pick_best_derived_scan_match($matches, $metal_id, $scan_code);
    }
}

if (!function_exists('auragold_resolve_physical_scan_input')) {
    /**
     * Resolve scanner input via physical scan map (4-digit tokens only).
     */
    function auragold_resolve_physical_scan_input(mysqli $conn, string $input, int $branch_id = -1, int $metal_id = 0): ?string
    {
        $input = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $input));
        $digits = preg_replace('/\D/', '', $input);
        if ($digits === '' || strlen($digits) > 4) {
            return null;
        }
        $scan_code = str_pad($digits, 4, '0', STR_PAD_LEFT);
        if (!auragold_is_valid_physical_scan_code($scan_code)) {
            return null;
        }
        if (function_exists('auragold_resolve_four_digit_scan_to_full_barcode')) {
            return auragold_resolve_four_digit_scan_to_full_barcode($conn, $scan_code, $branch_id, $metal_id);
        }
        return null;
    }
}
