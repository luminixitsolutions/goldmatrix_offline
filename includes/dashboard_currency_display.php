<?php
/**
 * Dashboard metal rates: display currency (tbl_currency) + exchange (tbl_currency_exchange_rate).
 * Stored rates in DB are always in the base currency (tbl_currency.is_base = 1).
 * Exchange rate row: `rate` = number of base-currency units for one unit of that currency
 *   (e.g. 1 USD = 3.67 AED → rate 3.67). Display in foreign = base_amount / rate.
 */

if (!function_exists('auragold_dashboard_currency_exchange_map')) {
    /**
     * @return array<int,float> currency_id => latest active rate (base units per 1 unit of that currency)
     */
    function auragold_dashboard_currency_exchange_map($conn)
    {
        if (!$conn) {
            return [];
        }
        $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_currency_exchange_rate'");
        if (!$tbl || mysqli_num_rows($tbl) === 0) {
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            return [];
        }
        mysqli_free_result($tbl);

        $sql = "
            SELECT r.currency_id, r.rate
            FROM tbl_currency_exchange_rate r
            INNER JOIN (
                SELECT currency_id, MAX(id) AS mid
                FROM tbl_currency_exchange_rate
                WHERE status = 1
                GROUP BY currency_id
            ) t ON r.currency_id = t.currency_id AND r.id = t.mid
            WHERE r.status = 1
        ";
        $rows = @mysqli_query($conn, $sql);
        if (!$rows) {
            return [];
        }
        $out = [];
        while ($row = mysqli_fetch_assoc($rows)) {
            $cid = (int) ($row['currency_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $out[$cid] = (float) ($row['rate'] ?? 0);
        }
        mysqli_free_result($rows);

        return $out;
    }
}

if (!function_exists('auragold_dashboard_branch_id_for_currency_preferences')) {
    /**
     * Branch row used for dashboard display currency (matches My Profile target branch).
     */
    function auragold_dashboard_branch_id_for_currency_preferences(): int
    {
        $src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
        if ($src === 'branch') {
            $id = (int) ($_SESSION['user_id'] ?? 0);
            return $id > 0 ? $id : 0;
        }
        return (int) ($_SESSION['working_branch_id'] ?? 0);
    }
}

if (!function_exists('auragold_dashboard_resolve_base_currency')) {
    /**
     * @param array<int,array<string,mixed>> $currencies
     * @return array<string,mixed>|null
     */
    function auragold_dashboard_resolve_base_currency(array $currencies)
    {
        foreach ($currencies as $c) {
            if (!empty($c['is_base'])) {
                return $c;
            }
        }
        return $currencies[0] ?? null;
    }
}

if (!function_exists('auragold_dashboard_resolve_display_currency')) {
    /**
     * Display currency for rates: branch profile preference, else system base.
     *
     * @param array<int,array<string,mixed>> $currencies
     * @param int $pref_currency_id from tbl_branches.profile_base_currency_id
     * @return array{row:array<string,mixed>|null,id:int}
     */
    function auragold_dashboard_resolve_display_currency(array $currencies, $base_row, int $base_id, int $pref_currency_id): array
    {
        if ($pref_currency_id > 0) {
            foreach ($currencies as $c) {
                if ((int) ($c['id'] ?? 0) === $pref_currency_id) {
                    return ['row' => $c, 'id' => $pref_currency_id];
                }
            }
        }
        return ['row' => $base_row, 'id' => $base_id];
    }
}

if (!function_exists('auragold_branch_profile_currency_display_label')) {
    /**
     * Display label for branch dashboard preference (tbl_branches.profile_base_currency_id),
     * else system base from tbl_currency. Prefers currency name (e.g. INR) over symbol (e.g. Rs).
     * Used where stored rows omit currency (e.g. stock journal list fallback).
     */
    function auragold_branch_profile_currency_display_label(?mysqli $conn = null, ?mysqli $conn_master = null): string
    {
        $legacy = 'AED';
        if (!$conn instanceof mysqli || !function_exists('getList')) {
            return $legacy;
        }
        $currencies = getList("SELECT id, name, symbol, is_base FROM tbl_currency WHERE status = 1 ORDER BY is_base DESC, name ASC");
        if (!is_array($currencies)) {
            $currencies = [];
        }
        $base_currency_row = auragold_dashboard_resolve_base_currency($currencies);
        $base_currency_id = $base_currency_row ? (int) ($base_currency_row['id'] ?? 0) : 0;

        $pref_currency_id = 0;
        $pref_branch_id = 0;
        if (function_exists('auragold_my_profile_target_branch_id')) {
            $pref_branch_id = auragold_my_profile_target_branch_id();
        } elseif (function_exists('auragold_dashboard_branch_id_for_currency_preferences')) {
            $pref_branch_id = auragold_dashboard_branch_id_for_currency_preferences();
        }
        if ($conn_master instanceof mysqli && $pref_branch_id > 0) {
            $brCur = @mysqli_query(
                $conn_master,
                'SELECT profile_base_currency_id FROM tbl_branches WHERE id = ' . (int) $pref_branch_id . ' LIMIT 1'
            );
            if ($brCur && ($brRow = mysqli_fetch_assoc($brCur))) {
                $pref_currency_id = (int) ($brRow['profile_base_currency_id'] ?? 0);
            }
            if ($brCur) {
                mysqli_free_result($brCur);
            }
        }

        $display_res = auragold_dashboard_resolve_display_currency($currencies, $base_currency_row, $base_currency_id, $pref_currency_id);
        $row = $display_res['row'];
        if ($row) {
            $name = trim((string) ($row['name'] ?? ''));
            $symbol = trim((string) ($row['symbol'] ?? ''));
            $label = ($name !== '' ? $name : ($symbol !== '' ? $symbol : $legacy));
        } else {
            $label = $legacy;
        }
        if ($label === '') {
            $label = $legacy;
        }

        return $label;
    }
}
