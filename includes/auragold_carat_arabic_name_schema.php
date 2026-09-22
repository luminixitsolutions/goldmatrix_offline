<?php
/**
 * Optional Arabic display name on tbl_carat (Masters → Carat).
 */
if (!function_exists('auragold_ensure_tbl_carat_arabic_name')) {
    function auragold_ensure_tbl_carat_arabic_name($conn): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return;
        }
        $t = 'tbl_carat';
        if (!auragold_tbl_has_column($conn, $t, 'arabic_name')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `arabic_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Arabic display name' AFTER `name`"
            );
            auragold_tbl_has_column($conn, $t, 'arabic_name', true);
        } else {
            $r = @mysqli_query($conn, "SHOW FULL COLUMNS FROM `{$t}` LIKE 'arabic_name'");
            if ($r && ($col = mysqli_fetch_assoc($r))) {
                $collation = strtolower((string) ($col['Collation'] ?? ''));
                if ($collation !== '' && strpos($collation, 'utf8mb4') === false) {
                    @mysqli_query(
                        $conn,
                        "ALTER TABLE `{$t}` MODIFY COLUMN `arabic_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Arabic display name'"
                    );
                }
            }
            if ($r) {
                mysqli_free_result($r);
            }
        }
    }
}

if (!function_exists('auragold_carat_has_arabic_name')) {
    function auragold_carat_has_arabic_name($conn): bool
    {
        return $conn instanceof mysqli
            && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_carat', 'arabic_name');
    }
}

if (!function_exists('auragold_carat_arabic_name_for_raw')) {
    /**
     * Resolve tbl_carat.arabic_name from karat id, purity, or label (18K).
     */
    function auragold_carat_arabic_name_for_raw($raw, $conn = null): string
    {
        if (!($conn instanceof mysqli)) {
            return '';
        }
        auragold_ensure_tbl_carat_arabic_name($conn);
        if (!auragold_carat_has_arabic_name($conn)) {
            return '';
        }
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0' || $raw === '0.0' || $raw === '0.00') {
            return '';
        }
        $pick = static function ($row): string {
            if (!is_array($row)) {
                return '';
            }
            return trim((string) ($row['arabic_name'] ?? ''));
        };

        if (!is_numeric($raw)) {
            $esc = mysqli_real_escape_string($conn, $raw);
            $ar = $pick(@getRecord("SELECT arabic_name FROM tbl_carat WHERE status = 1 AND name = '$esc' ORDER BY id ASC LIMIT 1"));
            if ($ar !== '') {
                return $ar;
            }
            if (preg_match('/^(\d+)\s*K?$/i', $raw, $m)) {
                $escK = mysqli_real_escape_string($conn, $m[1] . 'K');
                $ar = $pick(@getRecord("SELECT arabic_name FROM tbl_carat WHERE status = 1 AND name = '$escK' ORDER BY id ASC LIMIT 1"));
                if ($ar !== '') {
                    return $ar;
                }
            }
            return '';
        }

        $n = (float) $raw;
        if ($n <= 0) {
            return '';
        }
        $ar = $pick(@getRecord(
            'SELECT arabic_name FROM tbl_carat WHERE status = 1'
            . ' AND ABS(CAST(purity AS DECIMAL(12,4)) - ' . round($n, 4) . ') < 0.051'
            . ' ORDER BY id ASC LIMIT 1'
        ));
        if ($ar !== '') {
            return $ar;
        }

        $whole = (abs($n - round($n)) < 0.001) ? (int) round($n) : null;
        if ($whole === null) {
            return '';
        }
        $escName = mysqli_real_escape_string($conn, (string) $whole . 'K');
        $escNameAlt = mysqli_real_escape_string($conn, (string) $whole . 'k');
        $escNum = mysqli_real_escape_string($conn, (string) $whole);
        $ar = $pick(@getRecord(
            "SELECT arabic_name FROM tbl_carat WHERE status = 1 AND (name = '$escName' OR name = '$escNameAlt' OR name = '$escNum') ORDER BY id ASC LIMIT 1"
        ));
        if ($ar !== '') {
            return $ar;
        }
        return $pick(@getRecord('SELECT arabic_name FROM tbl_carat WHERE id = ' . (int) $whole . ' AND status = 1 LIMIT 1'));
    }
}

if (!function_exists('auragold_carat_karat_label_with_arabic')) {
    /** e.g. "18K / عيار الذهب" */
    function auragold_carat_karat_label_with_arabic($raw, $conn = null, string $label = ''): string
    {
        if ($label === '' && function_exists('auragold_barcode_format_carat_label')) {
            $label = auragold_barcode_format_carat_label($raw, $conn);
        }
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        if (preg_match('/^(\d+)\s*K?$/i', $label, $m)) {
            $label = $m[1] . 'K';
        }
        if (!($conn instanceof mysqli)) {
            return $label;
        }
        $arabic = auragold_carat_arabic_name_for_raw($raw, $conn);
        if ($arabic === '') {
            $arabic = auragold_carat_arabic_name_for_raw($label, $conn);
        }
        return $arabic !== '' ? ($label . ' / ' . $arabic) : $label;
    }
}
