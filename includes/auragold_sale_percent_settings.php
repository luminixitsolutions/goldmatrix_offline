<?php

/**
 * Sale percentage master (Set Software → Masters): metal-wise and product-wise rules.
 */

if (!function_exists('auragold_sale_percent_tax_modes')) {
    /**
     * @return array<string, string>
     */
    function auragold_sale_percent_tax_modes(): array
    {
        return [
            'without_tax' => 'Without tax (Net / Purchase amount)',
            'with_tax'      => 'With tax (Net amount + tax)',
        ];
    }
}

if (!function_exists('auragold_sale_percent_making_types')) {
    /**
     * @return list<string>
     */
    function auragold_sale_percent_making_types(): array
    {
        return ['Fix', 'Per Gram', 'Per Piece', 'Per Kilogram', 'Per Percent', 'MRP', 'M.KT'];
    }
}

if (!function_exists('auragold_sale_percent_normalize_making_type')) {
    function auragold_sale_percent_normalize_making_type(string $type): string
    {
        $type = trim($type);
        if ($type === '' || strcasecmp($type, 'Percentage') === 0) {
            return $type === '' ? 'Fix' : 'Per Percent';
        }
        foreach (auragold_sale_percent_making_types() as $allowed) {
            if (strcasecmp($allowed, $type) === 0) {
                return $allowed;
            }
        }

        return 'Fix';
    }
}

if (!function_exists('auragold_sale_percent_apply_making_modes')) {
    /**
     * @return array<string, string>
     */
    function auragold_sale_percent_apply_making_modes(): array
    {
        return [
            'sales' => 'Sales',
            'purchase' => 'Purchase',
        ];
    }
}

if (!function_exists('auragold_sale_percent_normalize_apply_making_on')) {
    function auragold_sale_percent_normalize_apply_making_on(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if ($mode === 'purchase' || $mode === 'purchases' || $mode === 'buy') {
            return 'purchase';
        }

        return 'sales';
    }
}

if (!function_exists('auragold_sale_percent_normalize_tax_mode')) {
    function auragold_sale_percent_normalize_tax_mode(string $mode): string
    {
        return strtolower(trim($mode)) === 'with_tax' ? 'with_tax' : 'without_tax';
    }
}

if (!function_exists('auragold_sale_percent_normalize_scope')) {
    function auragold_sale_percent_normalize_scope(string $scope): string
    {
        return strtolower(trim($scope)) === 'product' ? 'product' : 'metal';
    }
}

if (!function_exists('auragold_resolve_sale_percent_branch_id')) {
    function auragold_resolve_sale_percent_branch_id(?int $explicit = null): int
    {
        if ($explicit !== null && $explicit > 0) {
            if (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($explicit)) {
                return $explicit;
            }
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['settings_branch_id'])) {
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

if (!function_exists('auragold_ensure_tbl_sale_percent_settings')) {
    function auragold_ensure_tbl_sale_percent_settings($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `tbl_sale_percent_settings` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `branch_id` int DEFAULT NULL COMMENT 'FK tbl_branches.id',
            `scope_type` varchar(16) NOT NULL DEFAULT 'metal' COMMENT 'metal or product',
            `metal_type` varchar(64) DEFAULT NULL COMMENT 'Metal display name when scope_type=metal',
            `product_id` int unsigned DEFAULT NULL COMMENT 'tbl_products.id when scope_type=product',
            `product_name` varchar(255) DEFAULT NULL COMMENT 'Cached product name for list',
            `sale_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `tax_mode` varchar(16) NOT NULL DEFAULT 'without_tax' COMMENT 'without_tax or with_tax',
            `making_type` varchar(32) NOT NULL DEFAULT 'Fix',
            `making_rate` decimal(14,4) NOT NULL DEFAULT 0.0000,
            `apply_making_on` varchar(16) NOT NULL DEFAULT 'sales' COMMENT 'sales or purchase',
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int NOT NULL DEFAULT 0,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sps_branch_scope` (`branch_id`, `scope_type`, `status`),
            KEY `idx_sps_metal` (`metal_type`),
            KEY `idx_sps_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!@mysqli_query($conn, $sql)) {
            return false;
        }

        if (function_exists('auragold_tbl_has_column')) {
            if (!auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'making_type')) {
                @mysqli_query($conn, "ALTER TABLE `tbl_sale_percent_settings` ADD COLUMN `making_type` varchar(32) NOT NULL DEFAULT 'Fix' AFTER `tax_mode`");
            }
            if (!auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'making_rate')) {
                @mysqli_query($conn, "ALTER TABLE `tbl_sale_percent_settings` ADD COLUMN `making_rate` decimal(14,4) NOT NULL DEFAULT 0.0000 AFTER `making_type`");
            }
            if (!auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'apply_making_on')) {
                @mysqli_query($conn, "ALTER TABLE `tbl_sale_percent_settings` ADD COLUMN `apply_making_on` varchar(16) NOT NULL DEFAULT 'sales' COMMENT 'sales or purchase' AFTER `making_rate`");
            }
        }

        return true;
    }
}

if (!function_exists('auragold_sale_percent_row_from_db')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function auragold_sale_percent_row_from_db(array $row): array
    {
        $scope = auragold_sale_percent_normalize_scope((string) ($row['scope_type'] ?? 'metal'));
        $tax_modes = auragold_sale_percent_tax_modes();
        $apply_modes = auragold_sale_percent_apply_making_modes();
        $tax_mode = auragold_sale_percent_normalize_tax_mode((string) ($row['tax_mode'] ?? 'without_tax'));
        $making_type = auragold_sale_percent_normalize_making_type((string) ($row['making_type'] ?? 'Fix'));
        $apply_making_on = auragold_sale_percent_normalize_apply_making_on((string) ($row['apply_making_on'] ?? 'sales'));

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'branch_id'    => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            'scope_type'   => $scope,
            'metal_type'   => trim((string) ($row['metal_type'] ?? '')),
            'product_id'   => isset($row['product_id']) ? (int) $row['product_id'] : 0,
            'product_name' => trim((string) ($row['product_name'] ?? '')),
            'sale_percent' => round((float) ($row['sale_percent'] ?? 0), 4),
            'tax_mode'     => $tax_mode,
            'tax_mode_label' => $tax_modes[$tax_mode] ?? '',
            'making_type'  => $making_type,
            'making_rate'  => round((float) ($row['making_rate'] ?? 0), 4),
            'apply_making_on' => $apply_making_on,
            'apply_making_on_label' => $apply_modes[$apply_making_on] ?? 'Sales',
            'status'       => (int) ($row['status'] ?? 1) === 1 ? 1 : 0,
            'sort_order'   => (int) ($row['sort_order'] ?? 0),
        ];
    }
}

if (!function_exists('auragold_get_sale_percent_settings')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_get_sale_percent_settings($conn, int $branch_id = 0, ?string $scope_type = null, bool $active_only = false): array
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        auragold_ensure_tbl_sale_percent_settings($conn);

        $where = ['1=1'];
        if ($scope_type !== null && $scope_type !== '') {
            $where[] = "scope_type = '" . mysqli_real_escape_string($conn, auragold_sale_percent_normalize_scope($scope_type)) . "'";
        }
        if ($active_only) {
            $where[] = 'status = 1';
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'branch_id') && $branch_id > 0) {
            $where[] = '(branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }

        $rows = getList('SELECT * FROM `tbl_sale_percent_settings` WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, id ASC');
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = auragold_sale_percent_row_from_db($row);
        }

        return $out;
    }
}

if (!function_exists('auragold_get_sale_percent_setting_by_id')) {
    function auragold_get_sale_percent_setting_by_id($conn, int $id, int $branch_id = 0): ?array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return null;
        }
        auragold_ensure_tbl_sale_percent_settings($conn);

        $row = getRecord('SELECT * FROM `tbl_sale_percent_settings` WHERE id = ' . (int) $id . ' LIMIT 1');
        if (!$row || !is_array($row)) {
            return null;
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'branch_id') && $branch_id > 0) {
            $bid = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;
            if ($bid > 0 && $bid !== $branch_id) {
                return null;
            }
        }

        return auragold_sale_percent_row_from_db($row);
    }
}

if (!function_exists('auragold_sale_percent_settings_client_map')) {
    /**
     * Map for product modal JS: product rules override metal rules.
     *
     * @return array{by_metal: array<string, array>, by_product: array<string, array>}
     */
    function auragold_sale_percent_settings_client_map($conn, int $branch_id = 0): array
    {
        $by_metal = [];
        $by_product = [];
        foreach (auragold_get_sale_percent_settings($conn, $branch_id, null, true) as $row) {
            $entry = [
                'sale_percent' => (float) ($row['sale_percent'] ?? 0),
                'tax_mode'     => (string) ($row['tax_mode'] ?? 'without_tax'),
                'making_type'  => (string) ($row['making_type'] ?? 'Fix'),
                'making_rate'  => (float) ($row['making_rate'] ?? 0),
                'apply_making_on' => (string) ($row['apply_making_on'] ?? 'sales'),
            ];
            if (($row['scope_type'] ?? '') === 'product') {
                $pid = (int) ($row['product_id'] ?? 0);
                if ($pid > 0) {
                    $by_product[(string) $pid] = $entry;
                }
            } else {
                $metal = trim((string) ($row['metal_type'] ?? ''));
                if ($metal !== '') {
                    $by_metal[$metal] = $entry;
                }
            }
        }

        return ['by_metal' => $by_metal, 'by_product' => $by_product];
    }
}

if (!function_exists('auragold_resolve_sale_percent_for_item')) {
    /**
     * @return array{sale_percent: float, tax_mode: string, making_type: string, making_rate: float, apply_making_on: string}|null
     */
    function auragold_resolve_sale_percent_for_item($conn, int $branch_id, int $product_id, string $metal_name): ?array
    {
        $map = auragold_sale_percent_settings_client_map($conn, $branch_id);
        $pack = static function (array $e): array {
            return [
                'sale_percent' => (float) ($e['sale_percent'] ?? 0),
                'tax_mode'     => auragold_sale_percent_normalize_tax_mode((string) ($e['tax_mode'] ?? 'without_tax')),
                'making_type'  => auragold_sale_percent_normalize_making_type((string) ($e['making_type'] ?? 'Fix')),
                'making_rate'  => round((float) ($e['making_rate'] ?? 0), 4),
                'apply_making_on' => auragold_sale_percent_normalize_apply_making_on((string) ($e['apply_making_on'] ?? 'sales')),
            ];
        };
        if ($product_id > 0 && isset($map['by_product'][(string) $product_id])) {
            return $pack($map['by_product'][(string) $product_id]);
        }
        $metal = trim($metal_name);
        if ($metal !== '' && isset($map['by_metal'][$metal])) {
            return $pack($map['by_metal'][$metal]);
        }

        return null;
    }
}

if (!function_exists('auragold_save_sale_percent_setting')) {
    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message:string,id?:int,row?:array<string,mixed>}
     */
    function auragold_save_sale_percent_setting($conn, int $branch_id, array $data): array
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'message' => 'Database unavailable.'];
        }
        auragold_ensure_tbl_sale_percent_settings($conn);

        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $scope = auragold_sale_percent_normalize_scope((string) ($data['scope_type'] ?? 'metal'));
        $metal = trim((string) ($data['metal_type'] ?? ''));
        $product_id = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $product_name = trim((string) ($data['product_name'] ?? ''));
        $sale_percent = round((float) ($data['sale_percent'] ?? 0), 4);
        $tax_mode = auragold_sale_percent_normalize_tax_mode((string) ($data['tax_mode'] ?? 'without_tax'));
        $making_type = auragold_sale_percent_normalize_making_type((string) ($data['making_type'] ?? 'Fix'));
        $making_rate = round((float) ($data['making_rate'] ?? 0), 4);
        $apply_making_on = auragold_sale_percent_normalize_apply_making_on((string) ($data['apply_making_on'] ?? 'sales'));
        $status = !empty($data['status']) ? 1 : 0;
        $sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        if ($sale_percent < 0) {
            return ['ok' => false, 'message' => 'Sale percentage cannot be negative.'];
        }
        if ($making_rate < 0) {
            return ['ok' => false, 'message' => 'Making rate cannot be negative.'];
        }
        if ($scope === 'metal' && $metal === '') {
            return ['ok' => false, 'message' => 'Select a metal type.'];
        }
        if ($scope === 'product') {
            if ($product_id <= 0) {
                return ['ok' => false, 'message' => 'Select a product.'];
            }
            if ($product_name === '') {
                $pr = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $product_id . ' LIMIT 1');
                if ($pr && !empty($pr['name'])) {
                    $product_name = trim((string) $pr['name']);
                }
            }
        }

        if ($id > 0 && function_exists('auragold_master_can_mutate_row')
            && !auragold_master_can_mutate_row($conn, 'tbl_sale_percent_settings', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        $scope_esc = mysqli_real_escape_string($conn, $scope);
        $metal_esc = mysqli_real_escape_string($conn, $metal);
        $product_name_esc = mysqli_real_escape_string($conn, $product_name);
        $tax_esc = mysqli_real_escape_string($conn, $tax_mode);
        $making_type_esc = mysqli_real_escape_string($conn, $making_type);
        $apply_making_esc = mysqli_real_escape_string($conn, $apply_making_on);
        $has_making_cols = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'making_type')
            && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'making_rate')
            && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'apply_making_on');

        $dupSql = "SELECT id FROM `tbl_sale_percent_settings` WHERE scope_type = '{$scope_esc}'";
        if ($id > 0) {
            $dupSql .= ' AND id != ' . (int) $id;
        }
        if ($scope === 'metal') {
            $dupSql .= " AND metal_type = '{$metal_esc}'";
        } else {
            $dupSql .= ' AND product_id = ' . (int) $product_id;
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'branch_id') && $branch_id > 0) {
            $dupSql .= ' AND (branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }
        $dupSql .= ' LIMIT 1';
        $dup = getRecord($dupSql);
        if ($dup && is_array($dup)) {
            return ['ok' => false, 'message' => $scope === 'metal'
                ? 'A rule for this metal already exists. Edit the existing row instead.'
                : 'A rule for this product already exists. Edit the existing row instead.'];
        }

        if ($id > 0) {
            $making_sql = $has_making_cols
                ? ", making_type = '{$making_type_esc}', making_rate = {$making_rate}, apply_making_on = '{$apply_making_esc}'"
                : '';
            $sql = "UPDATE `tbl_sale_percent_settings` SET
                scope_type = '{$scope_esc}',
                metal_type = " . ($scope === 'metal' ? "'{$metal_esc}'" : 'NULL') . ",
                product_id = " . ($scope === 'product' ? (int) $product_id : 'NULL') . ",
                product_name = " . ($scope === 'product' ? "'{$product_name_esc}'" : 'NULL') . ",
                sale_percent = {$sale_percent},
                tax_mode = '{$tax_esc}'{$making_sql},
                status = {$status},
                sort_order = " . (int) $sort_order . ",
                updated_at = NOW()
                WHERE id = " . (int) $id;
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Could not update rule.'];
            }
            $saved_id = $id;
        } else {
            $branch_sql = '';
            $branch_val = '';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_percent_settings', 'branch_id')) {
                $bid = function_exists('auragold_master_branch_id_for_writes')
                    ? (int) auragold_master_branch_id_for_writes($conn, 'tbl_sale_percent_settings')
                    : ($branch_id > 0 ? $branch_id : 0);
                $branch_sql = ', branch_id';
                $branch_val = ', ' . (int) $bid;
            }

            $making_cols = $has_making_cols ? ', making_type, making_rate, apply_making_on' : '';
            $making_vals = $has_making_cols
                ? ", '{$making_type_esc}', {$making_rate}, '{$apply_making_esc}'"
                : '';

            $sql = "INSERT INTO `tbl_sale_percent_settings`
                (scope_type, metal_type, product_id, product_name, sale_percent, tax_mode{$making_cols}, status, sort_order, created_at{$branch_sql})
                VALUES (
                    '{$scope_esc}',
                    " . ($scope === 'metal' ? "'{$metal_esc}'" : 'NULL') . ",
                    " . ($scope === 'product' ? (int) $product_id : 'NULL') . ",
                    " . ($scope === 'product' ? "'{$product_name_esc}'" : 'NULL') . ",
                    {$sale_percent},
                    '{$tax_esc}'{$making_vals},
                    {$status},
                    " . (int) $sort_order . ",
                    NOW(){$branch_val}
                )";
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Could not save rule.'];
            }
            $saved_id = (int) mysqli_insert_id($conn);
        }

        $row = auragold_get_sale_percent_setting_by_id($conn, $saved_id, $branch_id);

        return [
            'ok'      => true,
            'message' => $id > 0 ? 'Rule updated.' : 'Rule saved.',
            'id'      => $saved_id,
            'row'     => $row,
        ];
    }
}

if (!function_exists('auragold_delete_sale_percent_setting')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_delete_sale_percent_setting($conn, int $id, int $branch_id = 0): array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid rule.'];
        }
        auragold_ensure_tbl_sale_percent_settings($conn);

        if (function_exists('auragold_master_can_mutate_row')
            && !auragold_master_can_mutate_row($conn, 'tbl_sale_percent_settings', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        if (!mysqli_query($conn, 'DELETE FROM `tbl_sale_percent_settings` WHERE id = ' . (int) $id . ' LIMIT 1')) {
            return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Could not delete rule.'];
        }

        return ['ok' => true, 'message' => 'Rule deleted.'];
    }
}
