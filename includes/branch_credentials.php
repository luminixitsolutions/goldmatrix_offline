<?php
/**
 * Read per-branch DB settings from a tbl_branches row (supports common column name variants).
 *
 * @return array{db_name:string,db_user:string,db_pass:string}
 */
function auragold_branch_row_db_credentials(array $row) {
    $pick = static function (array $r, array $names) {
        foreach ($r as $k => $v) {
            foreach ($names as $n) {
                if (strcasecmp((string) $k, $n) === 0) {
                    return $v;
                }
            }
        }
        return null;
    };

    $db_name = trim((string) ($pick($row, ['db_name', 'database', 'db']) ?? ''));
    $db_user = trim((string) ($pick($row, ['db_users', 'db_user', 'db_username', 'database_user']) ?? ''));
    $db_pass = (string) ($pick($row, ['db_password', 'db_pass', 'db_passwd', 'database_password']) ?? '');

    return [
        'db_name' => $db_name,
        'db_user' => $db_user,
        'db_pass' => $db_pass,
    ];
}

/**
 * True if tbl_branches row is “active” (handles status / Status, 1, '1', active, etc.).
 */
function auragold_tbl_branch_row_is_active(array $row) {
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, 'status') !== 0) {
            continue;
        }
        if ($v === null || $v === '') {
            return false;
        }
        if (is_numeric($v)) {
            return (int) $v === 1;
        }
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'active', 'true', 'yes'], true);
    }
    return true;
}

if (!function_exists('auragold_registry_mysqli')) {
    /**
     * mysqli to AURAGOLD_REGISTRY_DB (canonical tbl_branches). Null if config could not open it.
     */
    function auragold_registry_mysqli(): ?mysqli {
        $m = $GLOBALS['auragold_registry_mysqli'] ?? null;
        return ($m instanceof mysqli) ? $m : null;
    }
}

if (!function_exists('auragold_registry_tbl_branches_row_by_db_name')) {
    /**
     * tbl_branches row from the central registry DB matched by operational db_name.
     */
    function auragold_registry_tbl_branches_row_by_db_name(string $dbName): ?array {
        $dbName = trim($dbName);
        if ($dbName === '') {
            return null;
        }

        $lookup = static function (string $tryName): ?array {
            $tryName = trim($tryName);
            if ($tryName === '') {
                return null;
            }
            $reg = auragold_registry_mysqli();
            if ($reg instanceof mysqli && function_exists('esc')) {
                $res = mysqli_query(
                    $reg,
                    "SELECT * FROM tbl_branches WHERE LOWER(TRIM(IFNULL(db_name, ''))) = LOWER('" . esc($tryName) . "') LIMIT 1"
                );
                if ($res && mysqli_num_rows($res) > 0) {
                    $row = mysqli_fetch_assoc($res);
                    mysqli_free_result($res);
                    return is_array($row) ? $row : null;
                }
                if ($res) {
                    mysqli_free_result($res);
                }
            }
            if (function_exists('getRecordMaster') && function_exists('esc')) {
                $row = getRecordMaster(
                    "SELECT * FROM tbl_branches WHERE LOWER(TRIM(IFNULL(db_name, ''))) = LOWER('" . esc($tryName) . "') LIMIT 1"
                );
                return is_array($row) ? $row : null;
            }
            return null;
        };

        $candidates = [$dbName];
        if (preg_match('/^goldsmatrix_(.+)$/i', $dbName, $m)) {
            $candidates[] = 'goldmatrix_' . $m[1];
        } elseif (preg_match('/^goldmatrix_(.+)$/i', $dbName, $m)) {
            $candidates[] = 'goldsmatrix_' . $m[1];
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $candidate) {
            $row = $lookup($candidate);
            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('auragold_registry_tbl_branches_row_by_id')) {
    /**
     * tbl_branches row from the central registry DB (not from $conn_master’s operational schema).
     */
    function auragold_registry_tbl_branches_row_by_id(int $id): ?array {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $reg = auragold_registry_mysqli();
        if ($reg) {
            $res = mysqli_query($reg, 'SELECT * FROM tbl_branches WHERE id = ' . $id . ' LIMIT 1');
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                mysqli_free_result($res);
                return is_array($row) ? $row : null;
            }
            if ($res) {
                mysqli_free_result($res);
            }
            return null;
        }
        if (function_exists('getRecordMaster')) {
            return getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $id . ' LIMIT 1');
        }
        return null;
    }
}

if (!function_exists('auragold_registry_list_tbl_branches_ordered')) {
    /**
     * @return list<array<string,mixed>>
     */
    function auragold_registry_list_tbl_branches_ordered(): array {
        $sql = 'SELECT * FROM tbl_branches ORDER BY IFNULL(main_branch_id, 0) ASC, id ASC';
        $reg = auragold_registry_mysqli();
        if ($reg) {
            $data = [];
            $res = mysqli_query($reg, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $data[] = $row;
                }
                mysqli_free_result($res);
            }
            return $data;
        }
        return function_exists('getListMaster') ? getListMaster($sql) : [];
    }
}

if (!function_exists('auragold_registry_list_tbl_branches_with_db_name')) {
    /**
     * @return list<array<string,mixed>>
     */
    function auragold_registry_list_tbl_branches_with_db_name(): array {
        $sql = 'SELECT id, name, db_name, db_users, db_password FROM tbl_branches '
            . "WHERE TRIM(IFNULL(db_name,'')) <> '' ORDER BY id ASC";
        $reg = auragold_registry_mysqli();
        if ($reg) {
            $data = [];
            $res = mysqli_query($reg, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $data[] = $row;
                }
                mysqli_free_result($res);
            }
            return $data;
        }
        return function_exists('getListMaster') ? getListMaster($sql) : [];
    }
}

if (!function_exists('auragold_registry_active_branches_list')) {
    /**
     * Active branches for dropdowns (Opening balance, filters, vouchers).
     * Uses central registry first — getListMaster() reads $conn_master (operational DB)
     * where tbl_branches is often only the current main + sub-branches.
     *
     * @return list<array{id:int,name:string}>
     */
    function auragold_registry_active_branches_list(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = [];
        $reg = auragold_registry_mysqli();
        if ($reg instanceof mysqli) {
            $res = @mysqli_query(
                $reg,
                'SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC'
            );
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $cached[] = [
                        'id' => $id,
                        'name' => trim((string) ($row['name'] ?? '')),
                    ];
                }
                mysqli_free_result($res);
            }
        }

        if ($cached === [] && function_exists('getListMaster')) {
            $rows = getListMaster('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $cached[] = [
                        'id' => $id,
                        'name' => trim((string) ($row['name'] ?? '')),
                    ];
                }
            }
        }

        return $cached;
    }
}
