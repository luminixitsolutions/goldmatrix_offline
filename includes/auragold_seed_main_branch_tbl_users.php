<?php
/**
 * After creating a main branch: reset tbl_users in that branch DB to a single default admin user.
 * Keeps branch_labels / user_branch_ids in sync with all mains + subs under that registry main.
 */
require_once __DIR__ . '/user_management_schema.php';

if (!function_exists('auragold_ensure_tbl_users_address_country_optional')) {
    function auragold_ensure_tbl_users_address_country_optional(mysqli $conn): void {
        auragold_um_ensure_column(
            $conn,
            'tbl_users',
            'Address',
            "`Address` VARCHAR(500) NULL DEFAULT NULL AFTER `EmailId`"
        );
        auragold_um_ensure_column(
            $conn,
            'tbl_users',
            'Country',
            "`Country` VARCHAR(200) NULL DEFAULT NULL AFTER `Address`"
        );
    }
}

if (!function_exists('auragold_family_branch_ids_and_labels')) {
    /**
     * @return array{ids:int[],labels:string[],ids_csv:string,labels_csv:string}
     */
    function auragold_family_branch_ids_and_labels(mysqli $conn_master, int $registryMainId): array {
        $registryMainId = (int) $registryMainId;
        $ids            = [];
        $labels         = [];
        if ($registryMainId <= 0) {
            return [
                'ids'         => [],
                'labels'      => [],
                'ids_csv'     => '',
                'labels_csv'  => '',
            ];
        }
        $rows = getListMaster(
            'SELECT id, name FROM tbl_branches WHERE id = ' . $registryMainId
            . ' OR main_branch_id = ' . $registryMainId . ' ORDER BY id ASC'
        );
        if (!is_array($rows)) {
            return [
                'ids'         => [],
                'labels'      => [],
                'ids_csv'     => '',
                'labels_csv'  => '',
            ];
        }
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $nm    = trim((string) ($r['name'] ?? ''));
            if ($nm !== '') {
                $labels[] = $nm;
            }
        }
        return [
            'ids'        => $ids,
            'labels'     => $labels,
            'ids_csv'    => auragold_um_normalize_branch_ids_list($ids),
            'labels_csv' => implode(',', $labels),
        ];
    }
}

if (!function_exists('auragold_main_branch_truncate_tbl_users')) {
    function auragold_main_branch_truncate_tbl_users(mysqli $branchConn): bool {
        mysqli_query($branchConn, 'SET FOREIGN_KEY_CHECKS=0');
        $chk = @mysqli_query($branchConn, "SHOW TABLES LIKE 'tbl_user_column_preferences'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            @mysqli_query($branchConn, 'TRUNCATE TABLE tbl_user_column_preferences');
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        $ok = @mysqli_query($branchConn, 'TRUNCATE TABLE tbl_users');
        mysqli_query($branchConn, 'SET FOREIGN_KEY_CHECKS=1');
        if ($ok) {
            return true;
        }
        mysqli_query($branchConn, 'SET FOREIGN_KEY_CHECKS=0');
        @mysqli_query($branchConn, 'DELETE FROM tbl_users');
        mysqli_query($branchConn, 'SET FOREIGN_KEY_CHECKS=1');
        return true;
    }
}

if (!function_exists('auragold_main_branch_reset_tbl_users_default_admin')) {
    /**
     * @param array{branch_name:string,contact1?:string,contact2?:string,mail?:string,address?:string,country?:string,status:int} $profile
     * @return array{ok:bool,message:string}
     */
    function auragold_main_branch_reset_tbl_users_default_admin(
        mysqli $conn_master,
        mysqli $branchConn,
        int $mainBranchId,
        array $profile
    ): array {
        $mainBranchId = (int) $mainBranchId;
        if ($mainBranchId <= 0) {
            return ['ok' => false, 'message' => 'Invalid main branch id.'];
        }
        $chk = mysqli_query($branchConn, "SHOW TABLES LIKE 'tbl_users'");
        if (!$chk || mysqli_num_rows($chk) < 1) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return ['ok' => false, 'message' => 'tbl_users missing in branch database.'];
        }
        mysqli_free_result($chk);

        auragold_ensure_user_management_columns($branchConn);
        auragold_ensure_tbl_users_address_country_optional($branchConn);

        if (!auragold_main_branch_truncate_tbl_users($branchConn)) {
            return ['ok' => false, 'message' => 'Could not clear tbl_users.'];
        }

        $fam = auragold_family_branch_ids_and_labels($conn_master, $mainBranchId);

        $branch_name = trim((string) ($profile['branch_name'] ?? ''));
        if ($branch_name === '') {
            $branch_name = 'Branch';
        }
        $contact1 = trim((string) ($profile['contact1'] ?? ''));
        $contact2 = trim((string) ($profile['contact2'] ?? ''));
        $mail     = trim((string) ($profile['mail'] ?? ''));
        $address  = trim((string) ($profile['address'] ?? ''));
        $country  = trim((string) ($profile['country'] ?? ''));
        $statusOk = !empty($profile['status']) ? 1 : 0;
        $statusEnum = $statusOk ? '1' : '0';

        $uname = 'admin';
        $pass  = '12345';

        $cols = [];
        $vals = [];

        $add = static function (string $col, string $sqlVal) use (&$cols, &$vals) {
            $cols[] = '`' . str_replace('`', '``', $col) . '`';
            $vals[] = $sqlVal;
        };

        $esc = static function (mysqli $c, string $s): string {
            return "'" . mysqli_real_escape_string($c, $s) . "'";
        };

        $colMap = [];
        $cr     = mysqli_query($branchConn, 'SHOW COLUMNS FROM tbl_users');
        if ($cr) {
            while ($row = mysqli_fetch_assoc($cr)) {
                $fn = (string) ($row['Field'] ?? '');
                if ($fn !== '') {
                    $colMap[strtolower($fn)] = $fn;
                }
            }
            mysqli_free_result($cr);
        }

        $pick = static function (string $logical, array $map): ?string {
            $l = strtolower($logical);
            return $map[$l] ?? null;
        };

        if ($pick('Fname', $colMap)) {
            $add($pick('Fname', $colMap), $esc($branchConn, $branch_name));
        }
        if ($pick('Lname', $colMap)) {
            $add($pick('Lname', $colMap), "''");
        }
        if ($pick('Username', $colMap)) {
            $add($pick('Username', $colMap), $esc($branchConn, $uname));
        }
        if ($pick('Phone', $colMap)) {
            $add($pick('Phone', $colMap), $contact1 !== '' ? $esc($branchConn, $contact1) : 'NULL');
        }
        if ($pick('EmailId', $colMap)) {
            $add($pick('EmailId', $colMap), $mail !== '' ? $esc($branchConn, $mail) : 'NULL');
        }
        if ($pick('Password', $colMap)) {
            $add($pick('Password', $colMap), $esc($branchConn, $pass));
        }
        if ($pick('Status', $colMap)) {
            $add($pick('Status', $colMap), "'" . mysqli_real_escape_string($branchConn, $statusEnum) . "'");
        }
        if ($pick('CreatedBy', $colMap)) {
            $add($pick('CreatedBy', $colMap), '0');
        }
        if ($pick('ModifiedBy', $colMap)) {
            $add($pick('ModifiedBy', $colMap), '0');
        }
        if ($pick('Address', $colMap) && $address !== '') {
            $add($pick('Address', $colMap), $esc($branchConn, $address));
        } elseif ($pick('Address', $colMap)) {
            $add($pick('Address', $colMap), 'NULL');
        }
        if ($pick('Country', $colMap) && $country !== '') {
            $add($pick('Country', $colMap), $esc($branchConn, $country));
        } elseif ($pick('Country', $colMap)) {
            $add($pick('Country', $colMap), 'NULL');
        }
        if ($pick('user_branch_ids', $colMap)) {
            $csv = $fam['ids_csv'];
            $add($pick('user_branch_ids', $colMap), $csv !== '' ? $esc($branchConn, $csv) : 'NULL');
        }
        if ($pick('branch_labels', $colMap)) {
            $lc = $fam['labels_csv'];
            $add($pick('branch_labels', $colMap), $lc !== '' ? $esc($branchConn, $lc) : 'NULL');
        }
        if ($pick('user_role', $colMap)) {
            $add($pick('user_role', $colMap), $esc($branchConn, 'Admin'));
        }

        if ($cols === []) {
            return ['ok' => false, 'message' => 'tbl_users has no recognized columns.'];
        }

        $sql = 'INSERT INTO tbl_users (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
        if (!mysqli_query($branchConn, $sql)) {
            return ['ok' => false, 'message' => 'Could not insert default admin: ' . mysqli_error($branchConn)];
        }

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('auragold_tbl_users_append_branch_id')) {
    /**
     * Append a branch id to every tbl_users row (comma-separated user_branch_ids).
     * Optionally append $branchLabel to branch_labels when that column exists.
     *
     * @return int Number of rows updated
     */
    function auragold_tbl_users_append_branch_id(mysqli $conn, int $branchId, string $branchLabel = ''): int {
        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            return 0;
        }
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        auragold_ensure_user_management_columns($conn);
        if (!auragold_tbl_has_column($conn, 'tbl_users', 'user_branch_ids')) {
            return 0;
        }
        $hasLabels = auragold_tbl_has_column($conn, 'tbl_users', 'branch_labels');
        $branchLabel = trim($branchLabel);

        $rs = @mysqli_query($conn, 'SELECT id, user_branch_ids' . ($hasLabels ? ', branch_labels' : '') . ' FROM tbl_users');
        if (!$rs) {
            return 0;
        }
        $updated = 0;
        while ($row = mysqli_fetch_assoc($rs)) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $ids = auragold_um_parse_branch_ids_string((string) ($row['user_branch_ids'] ?? ''));
            $already = false;
            foreach ($ids as $x) {
                if ((int) $x === $branchId) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }
            $ids[] = $branchId;
            $newIds = auragold_um_normalize_branch_ids_list($ids);
            $sets   = ['`user_branch_ids` = ' . ($newIds !== '' ? "'" . mysqli_real_escape_string($conn, $newIds) . "'" : 'NULL')];
            if ($hasLabels && $branchLabel !== '') {
                $labels = array_values(array_filter(array_map('trim', explode(',', (string) ($row['branch_labels'] ?? ''))), static function ($s) {
                    return $s !== '';
                }));
                $labels[] = $branchLabel;
                $newLabels = implode(',', $labels);
                $sets[]    = '`branch_labels` = ' . ($newLabels !== '' ? "'" . mysqli_real_escape_string($conn, $newLabels) . "'" : 'NULL');
            }
            if (@mysqli_query($conn, 'UPDATE tbl_users SET ' . implode(', ', $sets) . ' WHERE id = ' . $uid . ' LIMIT 1')) {
                $updated++;
            }
        }
        mysqli_free_result($rs);
        return $updated;
    }
}

if (!function_exists('auragold_new_sub_branch_append_branch_id_to_tbl_users')) {
    /**
     * After creating a sub-branch DB: append the new registry branch id to user_branch_ids on every tbl_users row.
     */
    function auragold_new_sub_branch_append_branch_id_to_tbl_users(
        int $newBranchId,
        string $dbName,
        string $dbUser,
        string $dbPass,
        string $branchLabel = ''
    ): int {
        $newBranchId = (int) $newBranchId;
        $dbName      = trim($dbName);
        if ($newBranchId <= 0 || $dbName === '') {
            return 0;
        }
        if (!function_exists('auragold_mysqli_connect_branch_or_registry')) {
            require_once __DIR__ . '/branch_create_db_after_save.php';
        }
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $sub  = auragold_mysqli_connect_branch_or_registry($host, $dbName, $dbUser, $dbPass);
        if (!$sub) {
            error_log('AuraGold sub-branch tbl_users append: could not connect to `' . $dbName . '`: ' . mysqli_connect_error());
            return 0;
        }
        mysqli_set_charset($sub, 'utf8mb4');
        $updated = auragold_tbl_users_append_branch_id($sub, $newBranchId, $branchLabel);
        mysqli_close($sub);
        return $updated;
    }
}

if (!function_exists('auragold_family_sync_default_admin_branch_assignments')) {
    /**
     * Update user_branch_ids + branch_labels for Username admin on every dedicated DB in the family.
     */
    function auragold_family_sync_default_admin_branch_assignments(mysqli $conn_master, int $registryMainId): void {
        $registryMainId = (int) $registryMainId;
        if ($registryMainId <= 0 || !defined('DB_NAME')) {
            return;
        }
        $fam = auragold_family_branch_ids_and_labels($conn_master, $registryMainId);

        $regDb = trim((string) DB_NAME);
        if ($regDb === '') {
            return;
        }
        $list = getListMaster(
            'SELECT id, db_name, db_users, db_password FROM tbl_branches WHERE '
            . '(id = ' . $registryMainId . ' OR main_branch_id = ' . $registryMainId . ') '
            . "AND TRIM(IFNULL(db_name,'')) <> '' ORDER BY id ASC"
        );
        if (!is_array($list) || $list === []) {
            return;
        }
        if (!function_exists('auragold_mysqli_connect_branch_or_registry')) {
            require_once __DIR__ . '/branch_create_db_after_save.php';
        }
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $done = [];
        foreach ($list as $row) {
            $dn = trim((string) ($row['db_name'] ?? ''));
            if ($dn === '' || strcasecmp($dn, $regDb) === 0) {
                continue;
            }
            $k = strtolower($dn);
            if (isset($done[$k])) {
                continue;
            }
            $done[$k] = true;
            $u = trim((string) ($row['db_users'] ?? ''));
            $p = (string) ($row['db_password'] ?? '');
            $sub = auragold_mysqli_connect_branch_or_registry($host, $dn, $u, $p);
            if (!$sub) {
                continue;
            }
            mysqli_set_charset($sub, 'utf8mb4');
            auragold_ensure_user_management_columns($sub);
            $hasIds    = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($sub, 'tbl_users', 'user_branch_ids');
            $hasLabels = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($sub, 'tbl_users', 'branch_labels');
            if ($hasIds || $hasLabels) {
                $idsEsc    = mysqli_real_escape_string($sub, $fam['ids_csv']);
                $labelsEsc = mysqli_real_escape_string($sub, $fam['labels_csv']);
                $sets = [];
                if ($hasIds) {
                    $sets[] = '`user_branch_ids` = ' . ($fam['ids_csv'] !== '' ? "'" . $idsEsc . "'" : 'NULL');
                }
                if ($hasLabels) {
                    $sets[] = '`branch_labels` = ' . ($fam['labels_csv'] !== '' ? "'" . $labelsEsc . "'" : 'NULL');
                }
                if ($sets !== []) {
                    $sql = 'UPDATE tbl_users SET ' . implode(', ', $sets)
                        . " WHERE LOWER(TRIM(`Username`)) = 'admin' LIMIT 50";
                    @mysqli_query($sub, $sql);
                }
            }
            mysqli_close($sub);
        }
    }
}
