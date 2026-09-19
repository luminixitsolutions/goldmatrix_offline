<?php

/**
 * Credit card master (Set Software) — branch-scoped when branch_id column exists.
 */

if (!function_exists('auragold_resolve_credit_card_branch_id')) {
    function auragold_resolve_credit_card_branch_id(?int $explicit = null): int
    {
        if ($explicit !== null && $explicit > 0) {
            if (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($explicit)) {
                return $explicit;
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_branch_id'])) {
            $p = (int) $_POST['settings_branch_id'];
            if ($p > 0 && (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($p))) {
                return $p;
            }
        }
        if (isset($_GET['branch_id'])) {
            $g = (int) $_GET['branch_id'];
            if ($g > 0 && (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($g))) {
                return $g;
            }
        }

        return function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
    }
}

if (!function_exists('auragold_ensure_tbl_credit_card')) {
    function auragold_ensure_tbl_credit_card($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `tbl_credit_card` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `branch_id` int DEFAULT NULL COMMENT 'FK tbl_branches.id',
            `name` varchar(255) NOT NULL DEFAULT '',
            `account_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'Account ledger name',
            `commission_account` varchar(255) NOT NULL DEFAULT '' COMMENT 'Commission ledger name',
            `commission_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `is_default` tinyint(1) NOT NULL DEFAULT 0,
            `sort_order` int NOT NULL DEFAULT 0,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_credit_card_branch` (`branch_id`),
            KEY `idx_credit_card_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) @mysqli_query($conn, $sql);
    }
}

if (!function_exists('auragold_credit_card_row_from_db')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function auragold_credit_card_row_from_db(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'branch_id' => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            'name' => trim((string) ($row['name'] ?? '')),
            'account_group' => trim((string) ($row['account_group'] ?? '')),
            'commission_account' => trim((string) ($row['commission_account'] ?? '')),
            'commission_percent' => (float) ($row['commission_percent'] ?? 0),
            'status' => (int) ($row['status'] ?? 1) === 1 ? 1 : 0,
            'is_default' => (int) ($row['is_default'] ?? 0) === 1 ? 1 : 0,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
}

if (!function_exists('auragold_get_credit_cards')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_get_credit_cards($conn, int $branch_id, bool $active_only = false): array
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        auragold_ensure_tbl_credit_card($conn);

        $where = ['status = 1'];
        if (auragold_tbl_has_column($conn, 'tbl_credit_card', 'branch_id') && $branch_id > 0) {
            $where[] = '(branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }

        $sql = 'SELECT * FROM `tbl_credit_card` WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, name ASC, id ASC';
        $rows = getList($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = auragold_credit_card_row_from_db($row);
        }

        return $out;
    }
}

if (!function_exists('auragold_get_credit_card_by_id')) {
    function auragold_get_credit_card_by_id($conn, int $id, int $branch_id = 0): ?array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return null;
        }
        auragold_ensure_tbl_credit_card($conn);

        $row = getRecord('SELECT * FROM `tbl_credit_card` WHERE id = ' . (int) $id . ' LIMIT 1');
        if (!$row || !is_array($row)) {
            return null;
        }
        if (auragold_tbl_has_column($conn, 'tbl_credit_card', 'branch_id') && $branch_id > 0) {
            $bid = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;
            if ($bid > 0 && $bid !== $branch_id) {
                return null;
            }
        }

        return auragold_credit_card_row_from_db($row);
    }
}

if (!function_exists('auragold_credit_card_clear_default')) {
    function auragold_credit_card_clear_default($conn, int $branch_id, int $except_id = 0): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $where = 'is_default = 1';
        if ($except_id > 0) {
            $where .= ' AND id != ' . (int) $except_id;
        }
        if (auragold_tbl_has_column($conn, 'tbl_credit_card', 'branch_id') && $branch_id > 0) {
            $where .= ' AND (branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }
        @mysqli_query($conn, 'UPDATE `tbl_credit_card` SET is_default = 0, updated_at = NOW() WHERE ' . $where);
    }
}

if (!function_exists('auragold_save_credit_card')) {
    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message:string,id?:int,card?:array<string,mixed>}
     */
    function auragold_save_credit_card($conn, int $branch_id, array $data): array
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'message' => 'Database unavailable.'];
        }
        auragold_ensure_tbl_credit_card($conn);

        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $name = trim((string) ($data['name'] ?? ''));
        $account_group = trim((string) ($data['account_group'] ?? ''));
        $commission_account = trim((string) ($data['commission_account'] ?? ''));
        $commission_percent = isset($data['commission_percent']) ? (float) $data['commission_percent'] : 0.0;
        $status = !empty($data['status']) ? 1 : 0;
        $is_default = !empty($data['is_default']) ? 1 : 0;

        if ($name === '') {
            return ['ok' => false, 'message' => 'Name is required.'];
        }
        if ($account_group === '') {
            return ['ok' => false, 'message' => 'A/C Group is required.'];
        }
        if ($commission_account === '') {
            return ['ok' => false, 'message' => 'Comm. A/C is required.'];
        }
        if ($commission_percent < 0) {
            return ['ok' => false, 'message' => 'Commission % cannot be negative.'];
        }

        if ($id > 0) {
            if (!auragold_master_can_mutate_row($conn, 'tbl_credit_card', $id)) {
                return ['ok' => false, 'message' => 'Access denied for this branch.'];
            }
        }

        $name_esc = mysqli_real_escape_string($conn, $name);
        $ag_esc = mysqli_real_escape_string($conn, $account_group);
        $ca_esc = mysqli_real_escape_string($conn, $commission_account);
        $pct = number_format($commission_percent, 4, '.', '');

        if ($is_default === 1) {
            auragold_credit_card_clear_default($conn, $branch_id, $id);
        }

        if ($id > 0) {
            $sql = "UPDATE `tbl_credit_card` SET
                name = '$name_esc',
                account_group = '$ag_esc',
                commission_account = '$ca_esc',
                commission_percent = '$pct',
                status = $status,
                is_default = $is_default,
                updated_at = NOW()
                WHERE id = " . (int) $id;
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn)];
            }
            $saved_id = $id;
        } else {
            $branch_sql = '';
            if (auragold_tbl_has_column($conn, 'tbl_credit_card', 'branch_id')) {
                $bid = function_exists('auragold_master_branch_id_for_writes')
                    ? auragold_master_branch_id_for_writes($conn, 'tbl_credit_card')
                    : ($branch_id > 0 ? $branch_id : 0);
                $branch_sql = ', branch_id';
                $branch_val = ', ' . (int) $bid;
            } else {
                $branch_val = '';
            }

            $sql = "INSERT INTO `tbl_credit_card`
                (name, account_group, commission_account, commission_percent, status, is_default, sort_order, created_at$branch_sql)
                VALUES ('$name_esc', '$ag_esc', '$ca_esc', '$pct', $status, $is_default, 0, NOW()$branch_val)";
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn)];
            }
            $saved_id = (int) mysqli_insert_id($conn);
        }

        $card = auragold_get_credit_card_by_id($conn, $saved_id, $branch_id);

        return [
            'ok' => true,
            'message' => $id > 0 ? 'Credit card updated.' : 'Credit card saved.',
            'id' => $saved_id,
            'card' => $card,
        ];
    }
}

if (!function_exists('auragold_delete_credit_card')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_delete_credit_card($conn, int $id, int $branch_id = 0): array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid credit card.'];
        }
        auragold_ensure_tbl_credit_card($conn);

        if (!auragold_master_can_mutate_row($conn, 'tbl_credit_card', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        @mysqli_query($conn, 'UPDATE `tbl_credit_card` SET status = 0, is_default = 0, updated_at = NOW() WHERE id = ' . (int) $id);

        return ['ok' => true, 'message' => 'Credit card deleted.'];
    }
}
