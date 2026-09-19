<?php
/**
 * Metal rate source URLs (Set Software master) — shown on dashboard Rate Conversions.
 * Only active rows appear in the dashboard dropdown.
 */

if (!function_exists('auragold_metal_rate_url_default_seeds')) {
    /**
     * @return list<array{url:string,label:string}>
     */
    function auragold_metal_rate_url_default_seeds(): array
    {
        return [
            ['url' => 'https://dubaicityofgold.com/', 'label' => 'Dubai City of Gold'],
            ['url' => 'https://igold.ae/gold-rate', 'label' => 'iGold'],
            [
                'url' => 'https://ae.fkjewellers.com/pages/today-gold-price-in-uae-gold-rate',
                'label' => 'FK Jewellers',
            ],
            ['url' => 'https://www.kitco.com/', 'label' => 'Kitco'],
            ['url' => 'https://goldprice.org/', 'label' => 'GoldPrice'],
        ];
    }
}

if (!function_exists('auragold_resolve_metal_rate_url_branch_id')) {
    function auragold_resolve_metal_rate_url_branch_id(?int $explicit = null): int
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

if (!function_exists('auragold_ensure_tbl_metal_rate_urls')) {
    function auragold_ensure_tbl_metal_rate_urls($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `tbl_metal_rate_urls` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `branch_id` int DEFAULT NULL COMMENT 'FK tbl_branches.id',
            `url` varchar(500) NOT NULL DEFAULT '',
            `label` varchar(255) NOT NULL DEFAULT '',
            `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
            `sort_order` int NOT NULL DEFAULT 0,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_metal_rate_urls_branch` (`branch_id`),
            KEY `idx_metal_rate_urls_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!@mysqli_query($conn, $sql)) {
            return false;
        }

        auragold_seed_tbl_metal_rate_urls_if_empty($conn);

        return true;
    }
}

if (!function_exists('auragold_seed_tbl_metal_rate_urls_if_empty')) {
    function auragold_seed_tbl_metal_rate_urls_if_empty($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $rs = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `tbl_metal_rate_urls`');
        if (!$rs) {
            return;
        }
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        if ((int) ($row['c'] ?? 0) > 0) {
            return;
        }

        $branch_id = 0;
        if (function_exists('auragold_master_branch_id_for_writes')) {
            $branch_id = (int) auragold_master_branch_id_for_writes($conn, 'tbl_metal_rate_urls');
        } elseif (function_exists('auragold_settings_branch_id')) {
            $branch_id = (int) auragold_settings_branch_id();
        }

        $sort = 0;
        foreach (auragold_metal_rate_url_default_seeds() as $seed) {
            $url = trim((string) ($seed['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($seed['label'] ?? ''));
            $url_esc = mysqli_real_escape_string($conn, $url);
            $label_esc = mysqli_real_escape_string($conn, $label);
            $bid_sql = auragold_tbl_has_column($conn, 'tbl_metal_rate_urls', 'branch_id')
                ? (', ' . (int) $branch_id)
                : '';
            $bid_col = $bid_sql !== '' ? ', branch_id' : '';
            @mysqli_query(
                $conn,
                "INSERT INTO `tbl_metal_rate_urls` (url, label, status, sort_order, created_at{$bid_col})
                 VALUES ('{$url_esc}', '{$label_esc}', 1, " . (int) $sort . ", NOW(){$bid_sql})"
            );
            $sort++;
        }
    }
}

if (!function_exists('auragold_metal_rate_url_row_from_db')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function auragold_metal_rate_url_row_from_db(array $row): array
    {
        $url = trim((string) ($row['url'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'branch_id' => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            'url' => $url,
            'label' => $label,
            'display' => $label !== '' ? $label : $url,
            'status' => (int) ($row['status'] ?? 1) === 1 ? 1 : 0,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
}

if (!function_exists('auragold_get_metal_rate_urls')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_get_metal_rate_urls($conn, int $branch_id = 0, bool $active_only = false): array
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        auragold_ensure_tbl_metal_rate_urls($conn);

        $where = ['1=1'];
        if ($active_only) {
            $where[] = 'status = 1';
        }
        if (auragold_tbl_has_column($conn, 'tbl_metal_rate_urls', 'branch_id') && $branch_id > 0) {
            $where[] = '(branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }

        $sql = 'SELECT * FROM `tbl_metal_rate_urls` WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, id ASC';
        $rows = getList($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = auragold_metal_rate_url_row_from_db($row);
        }

        return $out;
    }
}

if (!function_exists('auragold_get_metal_rate_url_by_id')) {
    function auragold_get_metal_rate_url_by_id($conn, int $id, int $branch_id = 0): ?array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return null;
        }
        auragold_ensure_tbl_metal_rate_urls($conn);

        $row = getRecord('SELECT * FROM `tbl_metal_rate_urls` WHERE id = ' . (int) $id . ' LIMIT 1');
        if (!$row || !is_array($row)) {
            return null;
        }
        if (auragold_tbl_has_column($conn, 'tbl_metal_rate_urls', 'branch_id') && $branch_id > 0) {
            $bid = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;
            if ($bid > 0 && $bid !== $branch_id) {
                return null;
            }
        }

        return auragold_metal_rate_url_row_from_db($row);
    }
}

if (!function_exists('auragold_normalize_metal_rate_url')) {
    function auragold_normalize_metal_rate_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }
}

if (!function_exists('auragold_save_metal_rate_url')) {
    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message:string,id?:int,row?:array<string,mixed>}
     */
    function auragold_save_metal_rate_url($conn, int $branch_id, array $data): array
    {
        if (!$conn instanceof mysqli) {
            return ['ok' => false, 'message' => 'Database unavailable.'];
        }
        auragold_ensure_tbl_metal_rate_urls($conn);

        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $url = auragold_normalize_metal_rate_url((string) ($data['url'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));
        $status = !empty($data['status']) ? 1 : 0;
        $sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        if ($url === '') {
            return ['ok' => false, 'message' => 'URL is required.'];
        }
        if (strlen($url) > 500) {
            return ['ok' => false, 'message' => 'URL is too long.'];
        }
        if (strlen($label) > 255) {
            return ['ok' => false, 'message' => 'Label is too long.'];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'Please enter a valid URL.'];
        }

        if ($id > 0 && function_exists('auragold_master_can_mutate_row')
            && !auragold_master_can_mutate_row($conn, 'tbl_metal_rate_urls', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        $url_esc = mysqli_real_escape_string($conn, $url);
        $label_esc = mysqli_real_escape_string($conn, $label);

        // Prevent duplicate URL for same branch scope
        $dupSql = "SELECT id FROM `tbl_metal_rate_urls` WHERE url = '{$url_esc}'";
        if ($id > 0) {
            $dupSql .= ' AND id != ' . (int) $id;
        }
        if (auragold_tbl_has_column($conn, 'tbl_metal_rate_urls', 'branch_id') && $branch_id > 0) {
            $dupSql .= ' AND (branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        }
        $dupSql .= ' LIMIT 1';
        $dup = getRecord($dupSql);
        if ($dup && is_array($dup)) {
            return ['ok' => false, 'message' => 'This URL is already added.'];
        }

        if ($id > 0) {
            $sql = "UPDATE `tbl_metal_rate_urls` SET
                url = '{$url_esc}',
                label = '{$label_esc}',
                status = {$status},
                sort_order = " . (int) $sort_order . ",
                updated_at = NOW()
                WHERE id = " . (int) $id;
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Could not update URL.'];
            }
            $saved_id = $id;
        } else {
            $branch_sql = '';
            $branch_val = '';
            if (auragold_tbl_has_column($conn, 'tbl_metal_rate_urls', 'branch_id')) {
                $bid = function_exists('auragold_master_branch_id_for_writes')
                    ? (int) auragold_master_branch_id_for_writes($conn, 'tbl_metal_rate_urls')
                    : ($branch_id > 0 ? $branch_id : 0);
                $branch_sql = ', branch_id';
                $branch_val = ', ' . (int) $bid;
            }

            $sql = "INSERT INTO `tbl_metal_rate_urls`
                (url, label, status, sort_order, created_at{$branch_sql})
                VALUES ('{$url_esc}', '{$label_esc}', {$status}, " . (int) $sort_order . ", NOW(){$branch_val})";
            if (!mysqli_query($conn, $sql)) {
                return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Could not save URL.'];
            }
            $saved_id = (int) mysqli_insert_id($conn);
        }

        $row = auragold_get_metal_rate_url_by_id($conn, $saved_id, $branch_id);

        return [
            'ok' => true,
            'message' => $id > 0 ? 'URL updated.' : 'URL saved.',
            'id' => $saved_id,
            'row' => $row,
        ];
    }
}

if (!function_exists('auragold_delete_metal_rate_url')) {
    /**
     * Hard-delete a metal rate URL row.
     *
     * @return array{ok:bool,message:string}
     */
    function auragold_delete_metal_rate_url($conn, int $id, int $branch_id = 0): array
    {
        if (!$conn instanceof mysqli || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid URL.'];
        }
        auragold_ensure_tbl_metal_rate_urls($conn);

        if (function_exists('auragold_master_can_mutate_row')
            && !auragold_master_can_mutate_row($conn, 'tbl_metal_rate_urls', $id)) {
            return ['ok' => false, 'message' => 'Access denied for this branch.'];
        }

        if (!mysqli_query($conn, 'DELETE FROM `tbl_metal_rate_urls` WHERE id = ' . (int) $id . ' LIMIT 1')) {
            return ['ok' => false, 'message' => mysqli_error($conn) ?: 'Could not delete URL.'];
        }

        return ['ok' => true, 'message' => 'URL deleted.'];
    }
}

if (!function_exists('auragold_metal_rate_url_is_active')) {
    /**
     * Whether $url matches an active metal-rate URL in the DB (for fetch allowlist).
     */
    function auragold_metal_rate_url_is_active($conn, string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !$conn instanceof mysqli) {
            return false;
        }
        auragold_ensure_tbl_metal_rate_urls($conn);

        $urls = auragold_get_metal_rate_urls($conn, 0, true);
        $norm = rtrim($url, '/');
        foreach ($urls as $row) {
            $u = trim((string) ($row['url'] ?? ''));
            if ($u === '') {
                continue;
            }
            if ($u === $url || rtrim($u, '/') === $norm) {
                return true;
            }
        }

        return false;
    }
}
