<?php
/**
 * Referral program settings (branch-scoped) — Set Software → Referral tab.
 * JSON shape matches reward point settings ({ metal_wise, active_key, blocks }).
 */

if (!function_exists('auragold_ensure_referral_settings_table')) {
    function auragold_ensure_referral_settings_table($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_auragold_referral_settings'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);

            return true;
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `tbl_auragold_referral_settings` (
              `branch_id` INT NOT NULL,
              `settings_json` LONGTEXT NULL COMMENT 'Referral rewards form payload',
              `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_auragold_referral_settings'");

        return $chk && mysqli_num_rows($chk) > 0;
    }
}

if (!function_exists('auragold_get_referral_settings')) {
    /**
     * @return array{metal_wise:int, active_key:string, blocks:array<string,array>}
     */
    function auragold_get_referral_settings($conn, int $branchId): array
    {
        require_once __DIR__ . '/auragold_reward_point_settings.php';

        if (!$conn instanceof mysqli || $branchId <= 0 || !auragold_ensure_referral_settings_table($conn)) {
            return auragold_reward_point_normalize_settings(null);
        }
        $bid = (int) $branchId;
        $row = @getRecord("SELECT settings_json AS j FROM tbl_auragold_referral_settings WHERE branch_id = {$bid} LIMIT 1");
        if (!$row || !isset($row['j']) || $row['j'] === '' || $row['j'] === null) {
            return auragold_reward_point_normalize_settings(null);
        }
        $dec = json_decode((string) $row['j'], true);
        if (!is_array($dec)) {
            return auragold_reward_point_normalize_settings(null);
        }
        if (!isset($dec['blocks']) || !is_array($dec['blocks'])) {
            $dec = [
                'metal_wise' => 0,
                'active_key' => '_all',
                'blocks'     => ['_all' => $dec],
            ];
        }

        return auragold_reward_point_normalize_settings($dec);
    }
}

if (!function_exists('auragold_save_referral_settings')) {
    /**
     * @param array{metal_wise?:int|string, active_key?:string, blocks?:array} $payload
     */
    function auragold_save_referral_settings($conn, int $branchId, array $payload): bool
    {
        require_once __DIR__ . '/auragold_reward_point_settings.php';

        if (!$conn instanceof mysqli || $branchId <= 0 || !auragold_ensure_referral_settings_table($conn)) {
            return false;
        }
        $norm = auragold_reward_point_normalize_settings($payload);
        $norm['metal_wise'] = !empty($payload['metal_wise']) ? 1 : 0;
        if (isset($payload['active_key']) && (string) $payload['active_key'] !== '') {
            $norm['active_key'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $payload['active_key']) ?: '_all';
        }
        $json = json_encode($norm, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $e = mysqli_real_escape_string($conn, $json);
        $bid = (int) $branchId;
        $sql = "INSERT INTO tbl_auragold_referral_settings (branch_id, settings_json) VALUES ({$bid}, '{$e}')
                ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json)";

        return @mysqli_query($conn, $sql);
    }
}
