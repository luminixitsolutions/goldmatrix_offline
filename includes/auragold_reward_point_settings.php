<?php
/**
 * Reward Point settings (branch-scoped) — Set Software → Reward Point tab.
 */

if (!function_exists('auragold_ensure_reward_point_settings_table')) {
    function auragold_ensure_reward_point_settings_table($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_auragold_reward_point_settings'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);

            return true;
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `tbl_auragold_reward_point_settings` (
              `branch_id` INT NOT NULL,
              `settings_json` LONGTEXT NULL COMMENT 'Reward Point UI state (metal-wise blocks)',
              `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        return auragold_reward_point_settings_table_exists($conn);
    }
}

if (!function_exists('auragold_reward_point_settings_table_exists')) {
    function auragold_reward_point_settings_table_exists($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_auragold_reward_point_settings'");

        return $chk && mysqli_num_rows($chk) > 0;
    }
}

if (!function_exists('auragold_reward_point_allowed_redeem_on')) {
    /**
     * Stored values for Redeem On (labels: Amount, Making Amount).
     *
     * @return list<string>
     */
    function auragold_reward_point_allowed_redeem_on(): array
    {
        return ['amount', 'making_amount'];
    }
}

if (!function_exists('auragold_reward_point_normalize_redeem_on')) {
    /**
     * Maps legacy saved values (invoice / pos / both) to the current list.
     */
    function auragold_reward_point_normalize_redeem_on(string $raw): string
    {
        $t = trim($raw);
        if ($t === '') {
            return '';
        }
        if (in_array($t, auragold_reward_point_allowed_redeem_on(), true)) {
            return $t;
        }
        if (strcasecmp($t, 'Making Amount') === 0) {
            return 'making_amount';
        }
        if (strcasecmp($t, 'Amount') === 0) {
            return 'amount';
        }
        /* Legacy: invoice → Amount; pos / both → Making Amount */
        if (in_array($t, ['pos', 'both'], true)) {
            return 'making_amount';
        }
        if ($t === 'invoice') {
            return 'amount';
        }

        return '';
    }
}

if (!function_exists('auragold_reward_point_default_block')) {
    /**
     * @return array<string, mixed>
     */
    function auragold_reward_point_default_block(): array
    {
        return [
            'earn_invoice_value' => '',
            'earn_point'         => '',
            'min_invoice'        => '',
            'valid_days'         => '',
            'one_pt_value'       => '',
            'redeem_on'          => '',
            'is_otp'             => 0,
            'auto_round_off'     => 0,
        ];
    }
}

if (!function_exists('auragold_reward_point_normalize_settings')) {
    /**
     * @param mixed $decoded
     * @return array{metal_wise:int, active_key:string, blocks:array<string,array>}
     */
    function auragold_reward_point_normalize_settings($decoded): array
    {
        $defBlock = auragold_reward_point_default_block();
        $out = [
            'metal_wise' => 0,
            'active_key' => '_all',
            'blocks'     => ['_all' => $defBlock],
        ];
        if (!is_array($decoded)) {
            return $out;
        }
        $out['metal_wise'] = !empty($decoded['metal_wise']) ? 1 : 0;
        $ak = isset($decoded['active_key']) ? (string) $decoded['active_key'] : '_all';
        $out['active_key'] = $ak !== '' ? $ak : '_all';
        $blocks = isset($decoded['blocks']) && is_array($decoded['blocks']) ? $decoded['blocks'] : [];
        $merged = [];
        foreach ($blocks as $k => $row) {
            $key = (string) $k;
            if ($key === '') {
                continue;
            }
            $r = is_array($row) ? $row : [];
            $merged[$key] = array_merge($defBlock, [
                'earn_invoice_value' => isset($r['earn_invoice_value']) ? (string) $r['earn_invoice_value'] : '',
                'earn_point'         => isset($r['earn_point']) ? (string) $r['earn_point'] : '',
                'min_invoice'        => isset($r['min_invoice']) ? (string) $r['min_invoice'] : '',
                'valid_days'         => isset($r['valid_days']) ? (string) $r['valid_days'] : '',
                'one_pt_value'       => isset($r['one_pt_value']) ? (string) $r['one_pt_value'] : '',
                'redeem_on'          => auragold_reward_point_normalize_redeem_on(isset($r['redeem_on']) ? (string) $r['redeem_on'] : ''),
                'is_otp'             => !empty($r['is_otp']) ? 1 : 0,
                'auto_round_off'     => !empty($r['auto_round_off']) ? 1 : 0,
            ]);
        }
        if (!isset($merged['_all'])) {
            $merged['_all'] = $defBlock;
        }
        $out['blocks'] = $merged;

        return $out;
    }
}

if (!function_exists('auragold_get_reward_point_settings')) {
    /**
     * @return array{metal_wise:int, active_key:string, blocks:array<string,array>}
     */
    function auragold_get_reward_point_settings($conn, int $branchId): array
    {
        if (!$conn instanceof mysqli || $branchId <= 0 || !auragold_ensure_reward_point_settings_table($conn)) {
            return auragold_reward_point_normalize_settings(null);
        }
        $bid = (int) $branchId;
        $row = @getRecord("SELECT `settings_json` AS j FROM `tbl_auragold_reward_point_settings` WHERE `branch_id` = {$bid} LIMIT 1");
        if (!$row || !isset($row['j']) || $row['j'] === '' || $row['j'] === null) {
            return auragold_reward_point_normalize_settings(null);
        }
        $dec = json_decode((string) $row['j'], true);

        return auragold_reward_point_normalize_settings($dec);
    }
}

if (!function_exists('auragold_save_reward_point_settings')) {
    /**
     * @param array{metal_wise?:int|string, active_key?:string, blocks?:array} $payload
     */
    function auragold_save_reward_point_settings($conn, int $branchId, array $payload): bool
    {
        if (!$conn instanceof mysqli || $branchId <= 0 || !auragold_ensure_reward_point_settings_table($conn)) {
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
        $sql = "INSERT INTO `tbl_auragold_reward_point_settings` (`branch_id`, `settings_json`) VALUES ({$bid}, '{$e}')
                ON DUPLICATE KEY UPDATE `settings_json` = VALUES(`settings_json`)";

        return @mysqli_query($conn, $sql);
    }
}
