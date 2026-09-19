<?php
/**
 * Permanent branch removal: unassign users (registry), delete all app rows tagged with branch_id, then registry row.
 */
require_once __DIR__ . '/user_management_schema.php';
require_once __DIR__ . '/auragold_branch_data_scope.php';
if (!function_exists('auragold_branch_delete_unassign_users')) {
    /**
     * Remove branch id from tbl_users.user_branch_ids (comma-separated) on the registry connection.
     */
    function auragold_branch_delete_unassign_users(mysqli $conn_master, int $branchId): int {
        $branchId = (int) $branchId;
        if ($branchId <= 0 || !function_exists('auragold_ensure_user_management_columns')) {
            return 0;
        }
        auragold_ensure_user_management_columns($conn_master);
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn_master, 'tbl_users', 'user_branch_ids')) {
            return 0;
        }
        if (!function_exists('auragold_um_parse_branch_ids_string') || !function_exists('auragold_um_normalize_branch_ids_list')) {
            return 0;
        }
        $updated = 0;
        $rs = @mysqli_query($conn_master, 'SELECT id, user_branch_ids FROM tbl_users WHERE user_branch_ids IS NOT NULL AND TRIM(user_branch_ids) != \'\'');
        if (!$rs) {
            return 0;
        }
        while ($row = mysqli_fetch_assoc($rs)) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $ids = auragold_um_parse_branch_ids_string((string) ($row['user_branch_ids'] ?? ''));
            $before = count($ids);
            $ids = array_values(array_filter($ids, static function ($x) use ($branchId) {
                return (int) $x !== $branchId;
            }));
            if (count($ids) === $before) {
                continue;
            }
            $newList = auragold_um_normalize_branch_ids_list($ids);
            $esc = mysqli_real_escape_string($conn_master, $newList);
            if (@mysqli_query($conn_master, 'UPDATE tbl_users SET user_branch_ids = ' . ($newList === '' ? 'NULL' : "'$esc'") . ' WHERE id = ' . $uid . ' LIMIT 1')) {
                $updated++;
            }
        }
        mysqli_free_result($rs);
        return $updated;
    }
}

if (!function_exists('auragold_branch_delete_schema_tables_with_branch_id')) {
    /**
     * @return list<string>
     */
    function auragold_branch_delete_schema_tables_with_branch_id(mysqli $conn): array {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $dbRes = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if (!$dbRes) {
            return [];
        }
        $dbRow = mysqli_fetch_assoc($dbRes);
        mysqli_free_result($dbRes);
        $schema = isset($dbRow['d']) ? trim((string) $dbRow['d']) : '';
        if ($schema === '') {
            return [];
        }
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $out = [];
        $q = @mysqli_query(
            $conn,
            "SELECT DISTINCT TABLE_NAME AS t FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = '$schemaEsc' AND COLUMN_NAME = 'branch_id'"
        );
        if (!$q) {
            return [];
        }
        while ($r = mysqli_fetch_assoc($q)) {
            $t = $r['t'] ?? '';
            if (is_string($t) && preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
                $out[] = $t;
            }
        }
        mysqli_free_result($q);
        sort($out, SORT_STRING);
        return $out;
    }
}

if (!function_exists('auragold_branch_delete_all_app_rows_for_branch')) {
    /**
     * Deletes every row with branch_id = $branchId across tables in the application database.
     * Disables FK checks for one pass (document lines may not carry branch_id).
     */
    function auragold_branch_delete_all_app_rows_for_branch(mysqli $conn, int $branchId): array {
        $branchId = (int) $branchId;
        $report = ['tables' => [], 'errors' => []];
        if ($branchId <= 0) {
            $report['errors'][] = 'Invalid branch id';
            return $report;
        }
        $tables = auragold_branch_delete_schema_tables_with_branch_id($conn);
        @mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            // Registry branch rows are removed separately on $conn_master; skip app copy if present.
            if ($t === 'tbl_branches') {
                continue;
            }
            $sql = 'DELETE FROM `' . str_replace('`', '``', $t) . '` WHERE `branch_id` = ' . $branchId;
            if (@mysqli_query($conn, $sql)) {
                $n = (int) mysqli_affected_rows($conn);
                if ($n > 0) {
                    $report['tables'][$t] = $n;
                }
            } else {
                $report['errors'][] = $t . ': ' . mysqli_error($conn);
            }
        }
        @mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
        return $report;
    }
}

if (!function_exists('auragold_branch_delete_sub_branch_core')) {
    /**
     * Delete one sub-branch row (main_branch_id &gt; 0): app rows, unassign users, DROP dedicated DB, registry row.
     *
     * @return array{ok:bool,message?:string,appReport?:array,usersUpdated?:int,dbDrop?:array}
     */
    function auragold_branch_delete_sub_branch_core(mysqli $conn_master, $conn, int $branchId): array {
        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            return ['ok' => false, 'message' => 'Invalid branch id'];
        }
        $target = getRecordMaster(
            'SELECT id, name, main_branch_id, db_name, db_users, db_password FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1'
        );
        if (!$target) {
            return ['ok' => false, 'message' => 'Branch not found'];
        }
        if ((int) ($target['main_branch_id'] ?? 0) === 0) {
            return ['ok' => false, 'message' => 'Not a sub-branch row'];
        }

        if (!empty($_SESSION['working_branch_id']) && (int) $_SESSION['working_branch_id'] === $branchId) {
            unset($_SESSION['working_db'], $_SESSION['working_branch_id'], $_SESSION['working_branch_name']);
        }
        if (!empty($_SESSION['branch_id']) && (int) $_SESSION['branch_id'] === $branchId) {
            unset($_SESSION['branch_id']);
        }

        $dbNameRaw = isset($target['db_name']) ? (string) $target['db_name'] : '';
        $usesDedicatedDb = function_exists('auragold_branch_uses_dedicated_database')
            && auragold_branch_uses_dedicated_database($dbNameRaw);

        $appReport = ['tables' => [], 'errors' => []];
        if (!$usesDedicatedDb && !empty($conn) && $conn instanceof mysqli) {
            mysqli_begin_transaction($conn);
            $appReport = auragold_branch_delete_all_app_rows_for_branch($conn, $branchId);
            if (!empty($appReport['errors'])) {
                mysqli_rollback($conn);
                return [
                    'ok'      => false,
                    'message' => 'Could not delete all data for this branch in the application database.',
                    'appReport' => $appReport,
                ];
            }
            if (!mysqli_commit($conn)) {
                mysqli_rollback($conn);
                return ['ok' => false, 'message' => 'Could not commit data deletion.'];
            }
        }

        $usersUpdated = auragold_branch_delete_unassign_users($conn_master, $branchId);
        if (!function_exists('auragold_drop_branch_database_if_configured')) {
            require_once __DIR__ . '/branch_db_auto_credentials.php';
        }
        $branchDbUser = trim((string) ($target['db_users'] ?? ''));
        $branchDbPass = (string) ($target['db_password'] ?? '');
        $dbDrop = auragold_drop_branch_database_if_configured(
            $conn_master,
            $dbNameRaw !== '' ? $dbNameRaw : null,
            $branchDbUser !== '' ? $branchDbUser : null,
            $branchDbPass
        );
        if ($usesDedicatedDb && is_array($dbDrop) && empty($dbDrop['ok'])) {
            return [
                'ok'      => false,
                'message' => 'Could not drop branch database: ' . ($dbDrop['message'] ?? 'unknown error'),
                'appReport' => $appReport,
                'db_drop'   => $dbDrop,
            ];
        }

        $delOk = mysqli_query(
            $conn_master,
            'DELETE FROM tbl_branches WHERE id = ' . $branchId . ' AND IFNULL(main_branch_id, 0) > 0 LIMIT 1'
        );
        if (!$delOk || mysqli_affected_rows($conn_master) < 1) {
            return [
                'ok'      => false,
                'message' => 'Could not remove the branch record. Data may have been partially deleted.',
                'appReport' => $appReport,
            ];
        }

        return [
            'ok'            => true,
            'message'       => 'Sub-branch deleted.',
            'appReport'     => $appReport,
            'users_updated' => $usersUpdated,
            'db_drop'       => $dbDrop,
        ];
    }
}

if (!function_exists('auragold_branch_delete_main_branch_cascade')) {
    /**
     * Superadmin: delete all sub-branches under this main, then the main row (app data, DROP DB, registry).
     *
     * @param mysqli|null $conn Application DB connection (registry default).
     * @return array{ok:bool,message?:string,subs?:array,main_db_drop?:array}
     */
    function auragold_branch_delete_main_branch_cascade(mysqli $conn_master, $conn, int $mainId): array {
        $mainId = (int) $mainId;
        if ($mainId <= 0) {
            return ['ok' => false, 'message' => 'Invalid main branch id'];
        }
        $mainRow = getRecordMaster(
            'SELECT id, db_name, db_users, db_password FROM tbl_branches WHERE id = ' . $mainId . ' AND IFNULL(main_branch_id, 0) = 0 LIMIT 1'
        );
        if (!$mainRow) {
            return ['ok' => false, 'message' => 'Main branch not found'];
        }

        $subs = getListMaster(
            'SELECT id FROM tbl_branches WHERE main_branch_id = ' . $mainId . ' ORDER BY id DESC'
        );
        $subsReport = [];
        $usersUpdatedSubs = 0;
        if (is_array($subs)) {
            foreach ($subs as $s) {
                $sid = (int) ($s['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $r = auragold_branch_delete_sub_branch_core($conn_master, $conn, $sid);
                $subsReport[] = ['id' => $sid, 'result' => $r];
                if (empty($r['ok'])) {
                    return [
                        'ok'      => false,
                        'message' => 'Failed while deleting sub-branch #' . $sid . ': ' . ($r['message'] ?? 'unknown'),
                        'subs'    => $subsReport,
                    ];
                }
                $usersUpdatedSubs += (int) ($r['users_updated'] ?? 0);
            }
        }

        if (!empty($_SESSION['working_branch_id']) && (int) $_SESSION['working_branch_id'] === $mainId) {
            unset($_SESSION['working_db'], $_SESSION['working_branch_id'], $_SESSION['working_branch_name']);
        }
        if (!empty($_SESSION['branch_id']) && (int) $_SESSION['branch_id'] === $mainId) {
            unset($_SESSION['branch_id']);
        }

        $mainDbName = (string) ($mainRow['db_name'] ?? '');
        $mainUsesDedicatedDb = function_exists('auragold_branch_uses_dedicated_database')
            && auragold_branch_uses_dedicated_database($mainDbName);

        $appReport = ['tables' => [], 'errors' => []];
        if (!$mainUsesDedicatedDb && !empty($conn) && $conn instanceof mysqli) {
            mysqli_begin_transaction($conn);
            $appReport = auragold_branch_delete_all_app_rows_for_branch($conn, $mainId);
            if (!empty($appReport['errors'])) {
                mysqli_rollback($conn);
                return [
                    'ok'        => false,
                    'message'   => 'Could not delete application data for the main branch.',
                    'appReport' => $appReport,
                    'subs'      => $subsReport,
                ];
            }
            if (!mysqli_commit($conn)) {
                mysqli_rollback($conn);
                return ['ok' => false, 'message' => 'Could not commit main branch data deletion.', 'subs' => $subsReport];
            }
        }

        $usersUpdatedMain = auragold_branch_delete_unassign_users($conn_master, $mainId);
        if (!function_exists('auragold_drop_branch_database_if_configured')) {
            require_once __DIR__ . '/branch_db_auto_credentials.php';
        }
        $mainDbUser = trim((string) ($mainRow['db_users'] ?? ''));
        $mainDbPass = (string) ($mainRow['db_password'] ?? '');
        $dbDrop = auragold_drop_branch_database_if_configured(
            $conn_master,
            $mainDbName,
            $mainDbUser !== '' ? $mainDbUser : null,
            $mainDbPass
        );
        if ($mainUsesDedicatedDb && is_array($dbDrop) && empty($dbDrop['ok'])) {
            return [
                'ok'      => false,
                'message' => 'Could not drop main branch database: ' . ($dbDrop['message'] ?? 'unknown error'),
                'subs'    => $subsReport,
                'db_drop' => $dbDrop,
            ];
        }

        $delOk = mysqli_query(
            $conn_master,
            'DELETE FROM tbl_branches WHERE id = ' . $mainId . ' AND IFNULL(main_branch_id, 0) = 0 LIMIT 1'
        );
        if (!$delOk || mysqli_affected_rows($conn_master) < 1) {
            return [
                'ok'      => false,
                'message' => 'Could not remove the main branch record.',
                'subs'    => $subsReport,
            ];
        }

        return [
            'ok'            => true,
            'message'       => 'Main branch and all sub-branches were permanently deleted.',
            'subs'          => $subsReport,
            'users_updated' => $usersUpdatedSubs + $usersUpdatedMain,
            'data_deleted'  => $appReport,
            'db_drop'       => $dbDrop,
        ];
    }
}
