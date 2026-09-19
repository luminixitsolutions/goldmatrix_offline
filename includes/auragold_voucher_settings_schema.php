<?php

/**
 * tbl_voucher_settings — create if missing, add missing columns, seed default metals.
 * Idempotent; safe on every request (static guard).
 */
if (!function_exists('auragold_normalize_voucher_metal_row')) {
    /**
     * Normalize one metal payload from POST/JSON (snake_case keys, strip internals).
     */
    function auragold_normalize_voucher_metal_row(array $row): array
    {
        $camelMap = [
            'minimumAmountColumn' => 'minimum_amount_column',
            'reverseCalculationResultColumn' => 'reverse_calculation_result_column',
            'defaultDiscountType' => 'default_discount_type',
            'defaultCalculationType' => 'default_calculation_type',
            'stockAvailabilityCheckBy' => 'stock_availability_check_by',
            'wastageWtCalculation' => 'wastage_wt_calculation',
        ];
        foreach ($camelMap as $from => $to) {
            if (isset($row[$from]) && (!isset($row[$to]) || $row[$to] === '')) {
                $row[$to] = $row[$from];
            }
        }
        $clean = [];
        $keys = [
            'minimum_amount_column',
            'reverse_calculation_result_column',
            'default_discount_type',
            'default_calculation_type',
            'stock_availability_check_by',
            'wastage_wt_calculation',
        ];
        foreach ($keys as $k) {
            if (array_key_exists($k, $row)) {
                $clean[$k] = $row[$k];
            }
        }
        return $clean;
    }
}

if (!function_exists('auragold_voucher_settings_row_from_db')) {
    function auragold_voucher_settings_row_from_db(array $r, array $defaults, bool $hasWastageCol): array
    {
        $wastageCalc = $defaults['wastage_wt_calculation'];
        if ($hasWastageCol && isset($r['wastage_wt_calculation']) && in_array($r['wastage_wt_calculation'], ['GoldWt', 'FinalWt'], true)) {
            $wastageCalc = $r['wastage_wt_calculation'];
        }
        return [
            'minimum_amount_column' => $r['minimum_amount_column'] ?? $defaults['minimum_amount_column'],
            'reverse_calculation_result_column' => $r['reverse_calculation_result_column'] ?? $defaults['reverse_calculation_result_column'],
            'default_discount_type' => $r['default_discount_type'] ?? $defaults['default_discount_type'],
            'default_calculation_type' => $r['default_calculation_type'] ?? $defaults['default_calculation_type'],
            'stock_availability_check_by' => $r['stock_availability_check_by'] ?? $defaults['stock_availability_check_by'],
            'wastage_wt_calculation' => $wastageCalc,
        ];
    }
}

if (!function_exists('auragold_resolve_voucher_settings_branch_id')) {
    /**
     * Branch id for voucher settings load/save (POST settings_branch_id wins on save).
     */
    function auragold_resolve_voucher_settings_branch_id(?int $explicit = null): int
    {
        if ($explicit !== null && $explicit > 0) {
            return $explicit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_branch_id'])) {
            $p = (int) $_POST['settings_branch_id'];
            if ($p > 0 && (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($p))) {
                return $p;
            }
        }
        return function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
    }
}

if (!function_exists('auragold_ensure_tbl_voucher_settings_keys')) {
    /**
     * Repair id PK/AUTO_INCREMENT and uniq_branch_metal (branch_id, metal_wise) on existing installs.
     */
    function auragold_ensure_tbl_voucher_settings_keys($conn): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column') || !function_exists('auragold_index_exists_on_table')) {
            return;
        }
        $table = 'tbl_voucher_settings';
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return;
        }
        mysqli_free_result($chk);

        if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
            @mysqli_query($conn, "ALTER TABLE `{$table}` ADD COLUMN `branch_id` int(11) NULL DEFAULT NULL COMMENT 'FK tbl_branches.id' AFTER `id`");
        }

        $hasPk = false;
        $keys = @mysqli_query($conn, "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        if ($keys) {
            $hasPk = mysqli_num_rows($keys) > 0;
            mysqli_free_result($keys);
        }
        if (!$hasPk) {
            @mysqli_query($conn, "ALTER TABLE `{$table}` MODIFY `id` int(11) unsigned NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
        } else {
            @mysqli_query($conn, "ALTER TABLE `{$table}` MODIFY `id` int(11) unsigned NOT NULL AUTO_INCREMENT");
        }

        if (auragold_tbl_has_column($conn, $table, 'branch_id')) {
            @mysqli_query(
                $conn,
                "DELETE t1 FROM `{$table}` t1
                INNER JOIN `{$table}` t2
                  ON (t1.branch_id <=> t2.branch_id) AND t1.metal_wise = t2.metal_wise AND t1.id < t2.id"
            );
            @mysqli_query(
                $conn,
                "DELETE v1 FROM `{$table}` v1
                INNER JOIN `{$table}` v2 ON v1.metal_wise = v2.metal_wise AND v2.branch_id IS NOT NULL
                WHERE v1.branch_id IS NULL"
            );
            foreach (['uk_metal_wise', 'uk_branch_metal'] as $oldIdx) {
                if (auragold_index_exists_on_table($conn, $table, $oldIdx)) {
                    @mysqli_query($conn, "ALTER TABLE `{$table}` DROP INDEX `{$oldIdx}`");
                }
            }
            if (!auragold_index_exists_on_table($conn, $table, 'uniq_branch_metal')) {
                @mysqli_query($conn, "ALTER TABLE `{$table}` ADD UNIQUE KEY `uniq_branch_metal` (`branch_id`, `metal_wise`)");
            }
        }
    }
}

if (!function_exists('auragold_ensure_tbl_voucher_settings')) {
    function auragold_ensure_tbl_voucher_settings($conn): void
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $table = 'tbl_voucher_settings';

        @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `{$table}` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `branch_id` int(11) NULL DEFAULT NULL COMMENT 'FK tbl_branches.id',
              `metal_wise` varchar(80) NOT NULL COMMENT 'Gold, Silver, Platinum, etc.',
              `minimum_amount_column` varchar(50) NOT NULL DEFAULT 'Amount',
              `reverse_calculation_result_column` varchar(50) NOT NULL DEFAULT 'MakingRate',
              `default_discount_type` varchar(50) NOT NULL DEFAULT 'Fix',
              `default_calculation_type` varchar(50) NOT NULL DEFAULT 'Fix',
              `stock_availability_check_by` varchar(50) NOT NULL DEFAULT 'Carat',
              `wastage_wt_calculation` varchar(50) NOT NULL DEFAULT 'GoldWt' COMMENT 'GoldWt|FinalWt',
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_branch_metal` (`branch_id`, `metal_wise`),
              KEY `idx_updated` (`updated_at`),
              KEY `idx_voucher_settings_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (!function_exists('auragold_tbl_has_column')) {
            return;
        }

        auragold_ensure_tbl_voucher_settings_keys($conn);

        $columnAlters = [
            'minimum_amount_column' => "varchar(50) NOT NULL DEFAULT 'Amount'",
            'reverse_calculation_result_column' => "varchar(50) NOT NULL DEFAULT 'MakingRate'",
            'default_discount_type' => "varchar(50) NOT NULL DEFAULT 'Fix'",
            'default_calculation_type' => "varchar(50) NOT NULL DEFAULT 'Fix'",
            'stock_availability_check_by' => "varchar(50) NOT NULL DEFAULT 'Carat'",
            'wastage_wt_calculation' => "varchar(50) NOT NULL DEFAULT 'GoldWt' COMMENT 'GoldWt|FinalWt'",
            'updated_at' => 'datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
        ];
        foreach ($columnAlters as $col => $def) {
            if (!auragold_tbl_has_column($conn, $table, $col)) {
                @mysqli_query($conn, "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        }

        if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
            auragold_ensure_branch_id_on_settings_tables($conn);
            auragold_ensure_tbl_voucher_settings_keys($conn);
        }
    }
}

/**
 * Upsert one metal's voucher settings for the active settings branch.
 *
 * @param mysqli $conn
 * @param int    $branch_id  From auragold_settings_branch_id()
 * @param string $metal_wise
 * @param array  $data       Keys: minimum_amount_column, reverse_calculation_result_column, ...
 * @return array{ok:bool,message:string}
 */
if (!function_exists('auragold_save_voucher_settings_for_metal')) {
    function auragold_save_voucher_settings_for_metal($conn, int $branch_id, string $metal_wise, array $data): array
    {
        $table = 'tbl_voucher_settings';
        auragold_ensure_tbl_voucher_settings($conn);

        $metals = function_exists('getVoucherSettingMetals') ? getVoucherSettingMetals() : [];
        if (!in_array($metal_wise, $metals, true)) {
            return ['ok' => false, 'message' => 'Invalid metal: ' . $metal_wise];
        }
        if ($branch_id <= 0) {
            return ['ok' => false, 'message' => 'Invalid branch_id for save.'];
        }

        $data = auragold_normalize_voucher_metal_row($data);
        $defaults = function_exists('getVoucherSettingsDefaults') ? getVoucherSettingsDefaults() : [];
        $minimum_amount_options = ['Amount', 'MakingAmount', 'NetAmount', 'NetAmountWithTax', 'Rate'];
        $reverse_calc_options = ['DiscountAmount', 'MakingRate', 'Rate'];
        $discount_type_options = ['Fix', 'On Amount', 'On Making Amount', 'On Diamond Amount', 'On Stone Amount', 'On Net Amount'];
        $calculation_type_options = ['Fix', 'Quantity X Rate', 'Carat X Rate'];
        $stock_check_options = ['Carat', 'GrossWt', 'Quantity'];
        $wastage_wt_calc_options = ['GoldWt', 'FinalWt'];

        $minimum_amount_column = trim((string) ($data['minimum_amount_column'] ?? $defaults['minimum_amount_column'] ?? 'Amount'));
        if (!in_array($minimum_amount_column, $minimum_amount_options, true)) {
            $minimum_amount_column = 'Amount';
        }
        $reverse_calculation_result_column = trim((string) ($data['reverse_calculation_result_column'] ?? $defaults['reverse_calculation_result_column'] ?? 'MakingRate'));
        if (!in_array($reverse_calculation_result_column, $reverse_calc_options, true)) {
            $reverse_calculation_result_column = 'MakingRate';
        }
        $default_discount_type = trim((string) ($data['default_discount_type'] ?? $defaults['default_discount_type'] ?? 'Fix'));
        if (!in_array($default_discount_type, $discount_type_options, true)) {
            $default_discount_type = 'Fix';
        }
        $default_calculation_type = trim((string) ($data['default_calculation_type'] ?? $defaults['default_calculation_type'] ?? 'Fix'));
        if (!in_array($default_calculation_type, $calculation_type_options, true)) {
            $default_calculation_type = 'Fix';
        }
        $stock_availability_check_by = trim((string) ($data['stock_availability_check_by'] ?? $defaults['stock_availability_check_by'] ?? 'Carat'));
        if (!in_array($stock_availability_check_by, $stock_check_options, true)) {
            $stock_availability_check_by = 'Carat';
        }
        $wastage_wt_calculation = trim((string) ($data['wastage_wt_calculation'] ?? $defaults['wastage_wt_calculation'] ?? 'GoldWt'));
        if (!in_array($wastage_wt_calculation, $wastage_wt_calc_options, true)) {
            $wastage_wt_calculation = 'GoldWt';
        }

        $sql = "INSERT INTO `{$table}` (
            branch_id, metal_wise, minimum_amount_column, reverse_calculation_result_column,
            default_discount_type, default_calculation_type, stock_availability_check_by,
            wastage_wt_calculation, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            minimum_amount_column = VALUES(minimum_amount_column),
            reverse_calculation_result_column = VALUES(reverse_calculation_result_column),
            default_discount_type = VALUES(default_discount_type),
            default_calculation_type = VALUES(default_calculation_type),
            stock_availability_check_by = VALUES(stock_availability_check_by),
            wastage_wt_calculation = VALUES(wastage_wt_calculation),
            updated_at = NOW()";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => mysqli_error($conn)];
        }
        $bid = (int) $branch_id;
        mysqli_stmt_bind_param(
            $stmt,
            'isssssss',
            $bid,
            $metal_wise,
            $minimum_amount_column,
            $reverse_calculation_result_column,
            $default_discount_type,
            $default_calculation_type,
            $stock_availability_check_by,
            $wastage_wt_calculation
        );
        $ok = mysqli_stmt_execute($stmt);
        $err = $ok ? '' : mysqli_error($conn);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['ok' => false, 'message' => $err !== '' ? $err : ('Save failed for ' . $metal_wise)];
        }
        return ['ok' => true, 'message' => ''];
    }
}
