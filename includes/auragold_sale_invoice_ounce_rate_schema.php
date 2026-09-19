<?php
/**
 * Sale invoice ounce rate columns + helpers (gold/silver ounce modal data).
 */

if (!function_exists('auragold_ensure_sale_invoice_ounce_rate_columns')) {
    function auragold_ensure_sale_invoice_ounce_rate_columns($conn): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [
            'enabled' => false,
            'rate' => false,
            'json' => false,
        ];
        if (!($conn instanceof mysqli)) {
            return $cache;
        }
        $table = 'tbl_sale_invoices';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return $cache;
        }
        mysqli_free_result($chk);

        $defs = [
            'enabled' => "ADD COLUMN `ounce_rate_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `fixing_type`",
            'rate' => "ADD COLUMN `ounce_rate` DECIMAL(18,6) NOT NULL DEFAULT 0.000000 AFTER `ounce_rate_enabled`",
            'json' => "ADD COLUMN `ounce_rate_json` TEXT NULL AFTER `ounce_rate`",
        ];
        foreach ($defs as $key => $alter) {
            $col = $key === 'enabled' ? 'ounce_rate_enabled' : ($key === 'rate' ? 'ounce_rate' : 'ounce_rate_json');
            $c = @mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($c && mysqli_num_rows($c) > 0) {
                $cache[$key] = true;
                mysqli_free_result($c);
                continue;
            }
            if ($c) {
                mysqli_free_result($c);
            }
            if (@mysqli_query($conn, "ALTER TABLE `$table` $alter")) {
                $cache[$key] = true;
            }
        }
        return $cache;
    }
}

if (!function_exists('auragold_ounce_metal_rate_divisor')) {
    function auragold_ounce_metal_rate_divisor(): float
    {
        return 32.0;
    }
}

if (!function_exists('auragold_ounce_calc_usd_rate')) {
    /** US$ Rate = US Gold (oz) / @ GM; default @ GM = 32 when not set. */
    function auragold_ounce_calc_usd_rate($us_oz, $at_gm = null): string
    {
        $oz = is_numeric($us_oz) ? (float) $us_oz : 0.0;
        if ($oz <= 0) {
            return '';
        }
        $gm = ($at_gm !== null && $at_gm !== '' && is_numeric($at_gm) && (float) $at_gm > 0)
            ? (float) $at_gm
            : auragold_ounce_metal_rate_divisor();
        return auragold_format_ounce_decimal($oz / $gm, 3);
    }
}

if (!function_exists('auragold_format_ounce_decimal')) {
    function auragold_format_ounce_decimal($val, int $decimals = 3): string
    {
        if ($val === '' || $val === null) {
            return '';
        }
        if (!is_numeric($val)) {
            return trim((string) $val);
        }
        $s = number_format((float) $val, $decimals, '.', '');
        if ($decimals > 0) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        return $s;
    }
}

if (!function_exists('auragold_voucher_ounce_rate_empty_payload')) {
    function auragold_voucher_ounce_rate_empty_payload(): array
    {
        $today = date('Y-m-d');
        $blank = [
            'daily_date' => $today,
            'us_oz' => '',
            'loss_rate' => '',
            'usd_rate' => '',
            'at_gm' => '',
        ];
        return [
            'enabled' => false,
            'gold' => $blank,
            'silver' => array_merge($blank, ['daily_date' => $today]),
            'by_date' => [],
        ];
    }
}

if (!function_exists('auragold_voucher_ounce_rate_from_row')) {
    function auragold_voucher_ounce_rate_from_row(?array $row): array
    {
        $out = auragold_voucher_ounce_rate_empty_payload();
        if (!$row || !is_array($row)) {
            return $out;
        }
        if (!empty($row['ounce_rate_json'])) {
            $dec = json_decode((string) $row['ounce_rate_json'], true);
            if (is_array($dec)) {
                foreach (['gold', 'silver'] as $metal) {
                    if (!empty($dec[$metal]) && is_array($dec[$metal])) {
                        $out[$metal] = array_merge($out[$metal], $dec[$metal]);
                    }
                }
                if (isset($dec['enabled'])) {
                    $out['enabled'] = !empty($dec['enabled']);
                }
                if (!empty($dec['by_date']) && is_array($dec['by_date'])) {
                    $out['by_date'] = $dec['by_date'];
                }
            }
        }
        if (!empty($row['ounce_rate_enabled'])) {
            $out['enabled'] = true;
        }
        if (isset($row['ounce_rate']) && (float) $row['ounce_rate'] > 0 && ($out['gold']['us_oz'] === '' || $out['gold']['us_oz'] === null)) {
            $out['gold']['us_oz'] = (string) $row['ounce_rate'];
        }
        if (empty($out['by_date']) || !is_array($out['by_date'])) {
            $out['by_date'] = [];
        }
        return $out;
    }
}

if (!function_exists('auragold_voucher_ounce_rate_dashboard_defaults')) {
    function auragold_voucher_ounce_rate_dashboard_defaults($conn): array
    {
        $out = auragold_voucher_ounce_rate_empty_payload();
        if (!($conn instanceof mysqli)) {
            return $out;
        }
        $meta = [];
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_meta'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return $out;
        }
        mysqli_free_result($chk);

        $bid = function_exists('auragold_settings_branch_id') ? auragold_settings_branch_id() : 0;
        $hasBranch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_meta', 'branch_id');
        if ($hasBranch && $bid > 0) {
            $rm = @mysqli_query($conn, 'SELECT metal, ounce_rate FROM tbl_dashboard_metal_meta WHERE branch_id IN (0, ' . (int) $bid . ') ORDER BY metal ASC, branch_id DESC');
        } else {
            $rm = @mysqli_query($conn, 'SELECT metal, ounce_rate FROM tbl_dashboard_metal_meta ORDER BY metal ASC');
        }
        if ($rm) {
            while ($row = mysqli_fetch_assoc($rm)) {
                $m = strtolower(trim((string) ($row['metal'] ?? '')));
                if ($m !== '' && !isset($meta[$m])) {
                    $meta[$m] = (string) ($row['ounce_rate'] ?? '');
                }
            }
            mysqli_free_result($rm);
        }

        foreach (['gold' => 'gold', 'silver' => 'silver'] as $key => $metalKey) {
            $oz = isset($meta[$metalKey]) ? (float) $meta[$metalKey] : 0;
            if ($oz > 0) {
                $out[$key]['us_oz'] = (string) $oz;
                $atGm = (float) ($out[$key]['at_gm'] ?? 0);
                $out[$key]['usd_rate'] = auragold_ounce_calc_usd_rate($oz, $atGm > 0 ? $atGm : null);
                if ($atGm <= 0) {
                    $out[$key]['at_gm'] = auragold_format_ounce_decimal(auragold_ounce_metal_rate_divisor(), 3);
                }
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_voucher_ounce_rate_state_for_date')) {
    /**
     * Dashboard defaults + date-wise rows from tbl_metal_exchange_rate (for new invoices / page load).
     *
     * @param array{gold?:int,silver?:int} $metalIds
     */
    function auragold_voucher_ounce_rate_state_for_date($conn, array $metalIds, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $state = auragold_voucher_ounce_rate_dashboard_defaults($conn);
        $state['gold']['daily_date'] = $date;
        $state['silver']['daily_date'] = $date;
        if (!($conn instanceof mysqli)) {
            return $state;
        }
        if (!function_exists('auragold_ensure_metal_exchange_rate_table')) {
            require_once __DIR__ . '/auragold_metal_exchange_rate_schema.php';
        }
        if (!auragold_ensure_metal_exchange_rate_table($conn)) {
            return $state;
        }
        $dateEsc = mysqli_real_escape_string($conn, $date);
        $branchSql = '';
        $loadedFromExchange = false;
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal_exchange_rate', 'branch_id')) {
            $bid = function_exists('auragold_settings_branch_id') ? auragold_settings_branch_id() : 0;
            if ($bid > 0) {
                $branchSql = ' AND branch_id IN (0, ' . (int) $bid . ')';
            }
        }
        foreach (['gold', 'silver'] as $key) {
            $mid = (int) ($metalIds[$key] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $row = getRecord(
                "SELECT ounce_rate, metal_rate_per_gram, unit_conversion_rate
                 FROM tbl_metal_exchange_rate
                 WHERE metal_id = $mid AND rate_date = '$dateEsc' AND status = 1 $branchSql
                 ORDER BY branch_id DESC, id DESC LIMIT 1"
            );
            if (!$row || (float) ($row['ounce_rate'] ?? 0) <= 0) {
                continue;
            }
            $block = [
                'daily_date' => $date,
                'us_oz' => (string) $row['ounce_rate'],
            ];
            if (isset($row['metal_rate_per_gram']) && (float) $row['metal_rate_per_gram'] > 0) {
                $block['at_gm'] = number_format((float) $row['metal_rate_per_gram'], 3, '.', '');
            }
            $block['usd_rate'] = auragold_ounce_calc_usd_rate(
                $row['ounce_rate'],
                ($block['at_gm'] ?? '') !== '' ? $block['at_gm'] : null
            );
            $state[$key] = array_merge($state[$key], $block);
            if (!isset($state['by_date'][$date]) || !is_array($state['by_date'][$date])) {
                $state['by_date'][$date] = [];
            }
            $state['by_date'][$date][$key] = $block;
            $loadedFromExchange = true;
        }
        if ($loadedFromExchange) {
            $state['enabled'] = true;
        }
        return $state;
    }
}

if (!function_exists('auragold_parse_posted_ounce_rate_payload')) {
    function auragold_parse_posted_ounce_rate_payload(): array
    {
        $raw = $_POST['ounce_rate_json'] ?? $_POST['ounce_rate_data'] ?? '';
        if (is_string($raw) && trim($raw) !== '') {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                return $dec;
            }
        }
        return auragold_voucher_ounce_rate_empty_payload();
    }
}

if (!function_exists('auragold_sale_invoice_ounce_rate_save_values')) {
    /**
     * @return array{enabled:int,rate:float,json:string}
     */
    function auragold_sale_invoice_ounce_rate_save_values($conn): array
    {
        $payload = auragold_parse_posted_ounce_rate_payload();
        $enabled = !empty($_POST['ounce_rate_enabled']) || !empty($payload['enabled']) ? 1 : 0;
        $goldOz = (float) ($payload['gold']['us_oz'] ?? $_POST['ounce_rate'] ?? 0);
        if ($goldOz <= 0 && $enabled) {
            $goldOz = (float) ($_POST['ounce_rate'] ?? 0);
        }
        $payload['enabled'] = $enabled === 1;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{}';
        }
        return [
            'enabled' => $enabled,
            'rate' => $goldOz,
            'json' => mysqli_real_escape_string($conn, $json),
        ];
    }
}
