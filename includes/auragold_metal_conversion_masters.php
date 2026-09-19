<?php

/**
 * Map tbl_metal display/system name to dashboard rate bucket + ledger family.
 */
function auragold_metal_master_to_keys(?string $display, ?string $system): array
{
    $s = strtolower(trim((string) $display) . ' ' . trim((string) $system));
    if ($s === '') {
        return ['dashboard' => null, 'ledger' => null];
    }
    if (preg_match('/\b(imitation|imitation|watch|memoline|other\s+service|memo\s*line)\b/i', $s)) {
        return ['dashboard' => null, 'ledger' => null];
    }
    if (strpos($s, 'diamond') !== false || (strpos($s, 'stone') !== false && strpos($s, 'gold') === false) || preg_match('/\b(gem|gemstone|carat\s*rap)\b/i', $s)) {
        return ['dashboard' => 'diamond', 'ledger' => 'diamond'];
    }
    if (strpos($s, 'platinum') !== false || preg_match('/\bpt\b/i', $s)) {
        return ['dashboard' => 'platinum', 'ledger' => 'platinum'];
    }
    if (strpos($s, 'silver') !== false) {
        return ['dashboard' => 'silver', 'ledger' => 'silver'];
    }
    if (strpos($s, 'gold') !== false || preg_match('/\b(au|22k|24k|18k|14k|10k|k22|k24|karat|carat)\b/i', $s)) {
        return ['dashboard' => 'gold', 'ledger' => 'gold'];
    }
    return ['dashboard' => null, 'ledger' => null];
}

/**
 * Latest dashboard rate row for one metal key (gold/silver/platinum/diamond), branch-aware.
 * Picks the single row with greatest updated_at (then id) among branch 0 and working branch.
 *
 * @return array{ rate: float, carat_label: string, updated_at: string|null }|null
 */
function auragold_latest_dashboard_rate_for_metal(mysqli $conn, string $dashboard_key, int $branch_id = 0): ?array
{
    if (!function_exists('auragold_dashboard_rates_tables_exist') || !auragold_dashboard_rates_tables_exist($conn)) {
        return null;
    }
    if (!function_exists('auragold_ensure_dashboard_metal_rates_branch_columns')) {
        require_once __DIR__ . '/dashboard_metal_rates_branch_schema.php';
    }
    auragold_ensure_dashboard_metal_rates_branch_columns($conn);
    $dk = preg_replace('/[^a-z]/', '', strtolower($dashboard_key));
    if (!in_array($dk, ['gold', 'silver', 'platinum', 'diamond'], true)) {
        return null;
    }
    $has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id');
    $bid = max(0, (int) $branch_id);
    if ($has_branch) {
        $bids = $bid > 0 ? '0, ' . (int) $bid : '0';
        $q = "
            SELECT carat_label, rate, updated_at, id, branch_id
            FROM tbl_dashboard_metal_rates
            WHERE metal = '" . mysqli_real_escape_string($conn, $dk) . "'
              AND branch_id IN ($bids)
            ORDER BY COALESCE(updated_at, '1970-01-01 00:00:00') DESC, id DESC
            LIMIT 1
        ";
    } else {
        $q = "
            SELECT carat_label, rate, updated_at, id
            FROM tbl_dashboard_metal_rates
            WHERE metal = '" . mysqli_real_escape_string($conn, $dk) . "'
            ORDER BY COALESCE(updated_at, '1970-01-01 00:00:00') DESC, id DESC
            LIMIT 1
        ";
    }
    $row = function_exists('getRecord') ? getRecord($q) : null;
    if (!$row) {
        return null;
    }
    $raw = $row['rate'] ?? 0;
    if ($raw === null || $raw === '') {
        $rate = 0.0;
    } else {
        $rate = (float) str_replace([',', ' '], '', (string) $raw);
    }

    return [
        'rate'         => $rate,
        'carat_label'  => (string) ($row['carat_label'] ?? ''),
        'updated_at'   => !empty($row['updated_at']) ? (string) $row['updated_at'] : null,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function auragold_metal_conversion_master_list(mysqli $conn, int $branch_id = 0): array
{
    if (function_exists('auragold_effective_branch_id') && (int) $branch_id === 0) {
        $branch_id = (int) auragold_effective_branch_id();
    }
    if ($branch_id <= 0 && !empty($GLOBALS['auragold_working_branch_id'])) {
        $branch_id = (int) $GLOBALS['auragold_working_branch_id'];
    }
    $sfx = '';
    if (function_exists('auragold_master_list_sql_suffix')) {
        $sfx = auragold_master_list_sql_suffix($conn, 'tbl_metal');
    } elseif (function_exists('auragold_master_list_sql_for_branch_id') && $branch_id > 0) {
        $sfx = auragold_master_list_sql_for_branch_id($conn, 'tbl_metal', $branch_id);
    } elseif (function_exists('auragold_settings_main_branch_id') && function_exists('auragold_master_list_sql_for_branch_id')) {
        $mb = (int) auragold_settings_main_branch_id();
        if ($mb > 0) {
            $sfx = auragold_master_list_sql_for_branch_id($conn, 'tbl_metal', $mb);
        }
    }
    if (trim($sfx) === '') {
        $sfx = ' AND 1=1';
    } elseif (strpos(ltrim($sfx), 'AND') !== 0) {
        $sfx = ' AND ' . $sfx;
    }
    $sql = "SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 " . $sfx . " ORDER BY id ASC";
    if (function_exists('getList')) {
        $rows = getList($sql);
    } else {
        $rows = [];
    }
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = [];
    foreach ($rows as $r) {
        $disp = trim((string) ($r['display_name'] ?? ''));
        $sys = trim((string) ($r['system_name'] ?? ''));
        $keys = auragold_metal_master_to_keys($disp, $sys);
        $dash = $keys['dashboard'];
        $rate = null;
        $carat = '';
        $upd = null;
        if ($dash !== null) {
            $lr = auragold_latest_dashboard_rate_for_metal($conn, $dash, $branch_id);
            if ($lr) {
                $rate = (float) $lr['rate'];
                $carat = (string) $lr['carat_label'];
                $upd = $lr['updated_at'];
            } else {
                $rate = 0.0;
            }
        }
        $out[] = [
            'id'               => (int) ($r['id'] ?? 0),
            'label'            => $disp !== '' ? $disp : $sys,
            'display_name'     => $disp,
            'system_name'      => $sys,
            'dashboard_key'   => $dash,
            'ledger_key'      => $keys['ledger'],
            'rate'            => $rate,
            'carat_label'     => $carat,
            'rate_updated_at' => $upd,
        ];
    }
    return $out;
}
