<?php
/**
 * Currency dropdowns / masters: load active rows from tbl_currency only.
 */

if (!function_exists('auragold_load_master_currencies')) {
    /**
     * Active currencies for the current branch (same scope as Masters → Currency),
     * unique by name (avoids duplicate INR/AED when branch copies exist).
     *
     * @return list<array{id:mixed,name:mixed,symbol:mixed,is_base:mixed}>
     */
    function auragold_load_master_currencies($conn = null): array
    {
        if (!function_exists('getList')) {
            return [];
        }
        $suffix = '';
        if ($conn instanceof mysqli && function_exists('auragold_master_list_sql_suffix')) {
            $suffix = auragold_master_list_sql_suffix($conn, 'tbl_currency');
        }
        $rows = getList(
            'SELECT id, name, symbol, is_base FROM tbl_currency WHERE status = 1 '
            . $suffix
            . ' ORDER BY is_base DESC, name ASC, id ASC'
        );
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = $row;
        }

        return $out;
    }
}

if (!function_exists('auragold_currency_exchange_rates_by_name')) {
    /**
     * Map currency name (uppercase) => exchange rate (base units per 1 unit of currency).
     * Base currency is always 1.
     *
     * @return array<string,float>
     */
    function auragold_currency_exchange_rates_by_name($conn = null): array
    {
        if (!$conn instanceof mysqli) {
            global $conn;
        }
        if (!$conn instanceof mysqli) {
            return [];
        }
        require_once __DIR__ . '/dashboard_currency_display.php';
        $currencies = auragold_load_master_currencies($conn);
        $rateById = auragold_dashboard_currency_exchange_map($conn);
        $out = [];
        foreach ($currencies as $c) {
            $name = strtoupper(trim((string) ($c['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $id = (int) ($c['id'] ?? 0);
            if (!empty($c['is_base'])) {
                $out[$name] = 1.0;
                continue;
            }
            $out[$name] = ($id > 0 && isset($rateById[$id])) ? (float) $rateById[$id] : 0.0;
        }

        return $out;
    }
}
