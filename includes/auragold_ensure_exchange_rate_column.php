<?php
/**
 * Ensure exchange_rate column exists on a voucher table (DECIMAL 18,6 default 1).
 * Prefer AFTER currency when that column exists.
 *
 * @return bool true if column is available
 */
if (!function_exists('auragold_ensure_exchange_rate_column')) {
    function auragold_ensure_exchange_rate_column($conn, string $table): bool
    {
        if (!($conn instanceof mysqli) || $table === '') {
            return false;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return (bool) $cache[$table];
        }
        $erc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE 'exchange_rate'");
        if ($erc && mysqli_num_rows($erc) > 0) {
            $cache[$table] = true;
            return true;
        }
        // Also accept legacy currency_rate
        $crc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE 'currency_rate'");
        if ($crc && mysqli_num_rows($crc) > 0) {
            $cache[$table] = 'currency_rate';
            return true;
        }
        $after = '';
        $curCol = @mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE 'currency'");
        if ($curCol && mysqli_num_rows($curCol) > 0) {
            $after = ' AFTER `currency`';
        }
        $ok = @mysqli_query(
            $conn,
            "ALTER TABLE `{$table}` ADD COLUMN `exchange_rate` DECIMAL(18,6) NOT NULL DEFAULT 1.000000{$after}"
        );
        $cache[$table] = (bool) $ok;
        return (bool) $ok;
    }
}

if (!function_exists('auragold_read_posted_exchange_rate')) {
    function auragold_read_posted_exchange_rate(): float
    {
        $exchange_rate = (float) ($_POST['exchange_rate'] ?? $_POST['currency_rate'] ?? 1);
        if ($exchange_rate <= 0) {
            $exchange_rate = 1.0;
        }
        return $exchange_rate;
    }
}

if (!function_exists('auragold_exchange_rate_sql_fragments')) {
    /**
     * @return array{col:string,has:bool,name:string} col is empty or ", exchange_rate" / ", currency_rate"
     */
    function auragold_exchange_rate_sql_fragments($conn, string $table): array
    {
        $has = auragold_ensure_exchange_rate_column($conn, $table);
        if (!$has) {
            return ['has' => false, 'name' => '', 'insert_col' => '', 'insert_val' => '', 'update' => ''];
        }
        static $cacheName = [];
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (!isset($cacheName[$tableSafe])) {
            $erc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$tableSafe}` LIKE 'exchange_rate'");
            if ($erc && mysqli_num_rows($erc) > 0) {
                $cacheName[$tableSafe] = 'exchange_rate';
            } else {
                $cacheName[$tableSafe] = 'currency_rate';
            }
        }
        $name = $cacheName[$tableSafe];
        $rate = auragold_read_posted_exchange_rate();
        return [
            'has' => true,
            'name' => $name,
            'insert_col' => ', ' . $name,
            'insert_val' => ', ' . $rate,
            'update' => $name . ' = ' . $rate . ',',
            'rate' => $rate,
        ];
    }
}
