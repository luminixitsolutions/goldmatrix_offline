<?php
/**
 * Load dashboard metal rates from tbl_dashboard_metal_rates / tbl_dashboard_metal_meta.
 * Merges into $dashboard_metals defaults when tables exist.
 * With branch_id column: loads branch 0 (global) then overlays the requested branch.
 */

if (!function_exists('auragold_dashboard_rates_tables_exist')) {
    function auragold_dashboard_rates_tables_exist($conn)
    {
        if (!$conn) {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rates'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_dashboard_carat_labels_match')) {
    function auragold_dashboard_carat_labels_match($cardLabel, $rowCarat)
    {
        $a = trim((string) $cardLabel);
        $b = trim((string) $rowCarat);
        if ($a === '' || $b === '') {
            return false;
        }
        if (strcasecmp($a, $b) === 0) {
            return true;
        }
        if (stripos($b, $a) === 0 || stripos($a, $b) === 0) {
            return true;
        }
        return false;
    }
}

if (!function_exists('auragold_format_dashboard_rate_display')) {
    function auragold_format_dashboard_rate_display($val, $is_diamond_row)
    {
        if ($val === null || $val === '') {
            return $is_diamond_row ? '0' : '0.00';
        }
        if ($is_diamond_row) {
            $n = (float) str_replace([',', ' '], '', (string) $val);
            return $n == floor($n) ? (string) (int) $n : number_format($n, 2, '.', '');
        }
        return number_format((float) $val, 2, '.', '');
    }
}

if (!function_exists('auragold_load_dashboard_metals_from_db')) {
    /**
     * @param mysqli $conn
     * @param array $defaults
     * @param int $branch_id Branch scope (0 = legacy global only). Overlays branch-specific rows when &gt; 0.
     * @return array{ metals: array, rates_updated: string|null, db_connected: bool }
     */
    function auragold_load_dashboard_metals_from_db($conn, array $defaults, $branch_id = 0)
    {
        $out = $defaults;
        $rates_updated = null;
        if (!$conn || !auragold_dashboard_rates_tables_exist($conn)) {
            return ['metals' => $out, 'rates_updated' => $rates_updated, 'db_connected' => (bool) $conn];
        }

        require_once __DIR__ . '/dashboard_metal_rates_branch_schema.php';
        auragold_ensure_dashboard_metal_rates_branch_columns($conn);
        $has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id');
        $bid = max(0, (int) $branch_id);

        $meta = [];
        if ($has_branch) {
            $rm = @mysqli_query(
                $conn,
                'SELECT metal, branch_id, source_url, ounce_rate FROM tbl_dashboard_metal_meta WHERE branch_id IN (0, ' . (int) $bid . ') ORDER BY metal ASC, branch_id DESC'
            );
            if ($rm) {
                while ($row = mysqli_fetch_assoc($rm)) {
                    $m = $row['metal'];
                    if (!isset($meta[$m])) {
                        $meta[$m] = $row;
                    }
                }
                mysqli_free_result($rm);
            }
        } else {
            $rm = @mysqli_query($conn, 'SELECT metal, source_url, ounce_rate FROM tbl_dashboard_metal_meta');
            if ($rm) {
                while ($row = mysqli_fetch_assoc($rm)) {
                    $meta[$row['metal']] = $row;
                }
                mysqli_free_result($rm);
            }
        }

        $ratesByMetal = [];
        if ($has_branch) {
            $ingest_rates = static function (array &$bucket, $rq): void {
                while ($row = mysqli_fetch_assoc($rq)) {
                    $m = $row['metal'];
                    $cl = $row['carat_label'];
                    if (!isset($bucket[$m])) {
                        $bucket[$m] = [];
                    }
                    $bucket[$m][$cl] = $row;
                }
            };
            $rq0 = @mysqli_query(
                $conn,
                'SELECT metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at, branch_id FROM tbl_dashboard_metal_rates WHERE branch_id = 0 ORDER BY metal, sort_order, id'
            );
            if ($rq0) {
                $ingest_rates($ratesByMetal, $rq0);
                mysqli_free_result($rq0);
            }
            if ($bid > 0) {
                $rqb = @mysqli_query(
                    $conn,
                    'SELECT metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at, branch_id FROM tbl_dashboard_metal_rates WHERE branch_id = ' . (int) $bid . ' ORDER BY metal, sort_order, id'
                );
                if ($rqb) {
                    $ingest_rates($ratesByMetal, $rqb);
                    mysqli_free_result($rqb);
                }
            }
            foreach ($ratesByMetal as $m => $map) {
                foreach ($map as $cl => $row) {
                    if (!empty($row['updated_at'])) {
                        $rates_updated = $row['updated_at'];
                    }
                }
            }
            $mx = @mysqli_query(
                $conn,
                'SELECT MAX(updated_at) AS mx FROM tbl_dashboard_metal_rates WHERE branch_id IN (0, ' . (int) $bid . ')'
            );
            if ($mx && ($rmax = mysqli_fetch_assoc($mx)) && !empty($rmax['mx'])) {
                $rates_updated = $rmax['mx'];
            }
            if ($mx) {
                mysqli_free_result($mx);
            }
        } else {
            $rq = @mysqli_query($conn, 'SELECT metal, carat_label, rate, sell_premium, conversion_rate, sort_order, updated_at FROM tbl_dashboard_metal_rates ORDER BY metal, sort_order, id');
            if ($rq) {
                while ($row = mysqli_fetch_assoc($rq)) {
                    $m = $row['metal'];
                    if (!isset($ratesByMetal[$m])) {
                        $ratesByMetal[$m] = [];
                    }
                    $ratesByMetal[$m][$row['carat_label']] = $row;
                    if (!empty($row['updated_at'])) {
                        $rates_updated = $row['updated_at'];
                    }
                }
                mysqli_free_result($rq);
            }

            $mx = @mysqli_query($conn, 'SELECT MAX(updated_at) AS mx FROM tbl_dashboard_metal_rates');
            if ($mx && ($rmax = mysqli_fetch_assoc($mx)) && !empty($rmax['mx'])) {
                $rates_updated = $rmax['mx'];
            }
            if ($mx) {
                mysqli_free_result($mx);
            }
        }

        foreach ($out as $metal_key => &$config) {
            if (isset($meta[$metal_key])) {
                $config['source_url'] = (string) $meta[$metal_key]['source_url'];
                $oz = $meta[$metal_key]['ounce_rate'];
                $config['ounce_rate'] = $oz !== null && $oz !== '' ? (string) $oz : ($config['ounce_rate'] ?? '0');
            }

            $is_diamond = ($metal_key === 'diamond');
            $map = isset($ratesByMetal[$metal_key]) ? $ratesByMetal[$metal_key] : [];

            foreach ($config['rows'] as &$rrow) {
                $cl = $rrow['carat'];
                if (!isset($map[$cl])) {
                    continue;
                }
                $db = $map[$cl];
                $rate_disp = auragold_format_dashboard_rate_display($db['rate'], $is_diamond);
                $rrow['new_rate'] = $rate_disp;
                $rrow['current'] = $rate_disp;
                $rrow['conv'] = rtrim(rtrim(number_format((float) $db['conversion_rate'], 8, '.', ''), '0'), '.');
                if ($rrow['conv'] === '') {
                    $rrow['conv'] = '1';
                }
                if ($db['sell_premium'] !== null && $db['sell_premium'] !== '') {
                    $rrow['sell_premium'] = auragold_format_dashboard_rate_display($db['sell_premium'], false);
                } else {
                    $rrow['sell_premium'] = '—';
                }
            }
            unset($rrow);

            $h24 = null;
            foreach ($config['rows'] as $rrow) {
                if ($metal_key === 'gold' && isset($rrow['carat']) && strtoupper(trim($rrow['carat'])) === '24K') {
                    $h24 = $rrow['new_rate'];
                    break;
                }
            }
            if ($h24 !== null) {
                $config['headline_rate'] = $h24;
                $config['headline_carat'] = '24K';
            } elseif (!empty($config['rows'][0]['new_rate'])) {
                $config['headline_rate'] = $config['rows'][0]['new_rate'];
                $config['headline_carat'] = $config['rows'][0]['carat'];
            }

            foreach ($config['cards'] as &$card) {
                foreach ($config['rows'] as $rrow) {
                    if (auragold_dashboard_carat_labels_match($card['label'], $rrow['carat'])) {
                        $card['value'] = $rrow['new_rate'];
                        break;
                    }
                }
            }
            unset($card);
        }
        unset($config);

        $formatted_time = null;
        if ($rates_updated) {
            $ts = strtotime($rates_updated);
            if ($ts) {
                $formatted_time = date('d-m-Y  g:i A', $ts);
            }
        }

        return ['metals' => $out, 'rates_updated' => $formatted_time, 'db_connected' => true];
    }
}
