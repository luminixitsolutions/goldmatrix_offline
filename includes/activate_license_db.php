<?php
/**
 * Database connection for activate-license.php only.
 * Does not load remote_license_gate or session (page must work when license is STOP).
 */
function auragold_activate_license_connect(): ?mysqli
{
    require_once __DIR__ . '/branch_credentials.php';

    $__db_host     = getenv('DB_HOST') ?: 'localhost';
    $__registry_db = getenv('AURAGOLD_REGISTRY_DB') ?: 'auragold';
    $__boot_user   = getenv('AURAGOLD_BOOTSTRAP_USER') ?: 'root';
    $__boot_pass   = getenv('AURAGOLD_BOOTSTRAP_PASS');
    if ($__boot_pass === false) {
        $__boot_pass = '';
    }

    $__db_name = $__registry_db;
    $__db_user = $__boot_user;
    $__db_pass = $__boot_pass;

    $__bootstrapConn = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, $__registry_db);
    if ($__bootstrapConn) {
        mysqli_set_charset($__bootstrapConn, 'utf8mb4');
        $__res = mysqli_query(
            $__bootstrapConn,
            'SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1'
        );
        if ($__res && mysqli_num_rows($__res) > 0) {
            $__main = mysqli_fetch_assoc($__res);
            $__cr   = auragold_branch_row_db_credentials($__main);
            if ($__cr['db_name'] !== '') {
                $__db_name = $__cr['db_name'];
            }
            if ($__cr['db_user'] !== '') {
                $__db_user = $__cr['db_user'];
                $__db_pass = $__cr['db_pass'];
            }
        }
        mysqli_close($__bootstrapConn);
    }

    $__effective_db = $__db_name;
    $conn_master    = @mysqli_connect($__db_host, $__db_user, $__db_pass, $__effective_db);

    if (!$conn_master
        && ($__db_user !== $__boot_user || $__db_pass !== $__boot_pass || $__db_name !== $__registry_db)) {
        $conn_master = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, $__registry_db);
        if ($conn_master) {
            $__effective_db = $__registry_db;
        }
    }

    if ($conn_master) {
        mysqli_set_charset($conn_master, 'utf8mb4');
        $__tbl = mysqli_query($conn_master, "SHOW TABLES LIKE 'tbl_branches'");
        if (!$__tbl || mysqli_num_rows($__tbl) === 0) {
            mysqli_close($conn_master);
            $__effective_db = $__registry_db;
            $conn_master    = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, $__effective_db);
            if (!$conn_master) {
                $conn_master = @mysqli_connect($__db_host, $__db_user, $__db_pass, $__effective_db);
            }
            if ($conn_master) {
                mysqli_set_charset($conn_master, 'utf8mb4');
            }
        }
    }

    return $conn_master ?: null;
}

/**
 * Verify activation password against tbl_gst_calculation_snapshot.snapshot_version (bcrypt).
 */
function auragold_activate_license_verify_password(mysqli $conn, string $plainPassword): bool
{
    $plainPassword = trim($plainPassword);
    if ($plainPassword === '') {
        return false;
    }

    $sql = 'SELECT `snapshot_version` FROM `tbl_gst_calculation_snapshot` WHERE `id` = 1 LIMIT 1';
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        return false;
    }
    $row = mysqli_fetch_assoc($res);
    $hash = isset($row['snapshot_version']) ? trim((string) $row['snapshot_version']) : '';
    if ($hash === '') {
        return false;
    }

    return password_verify($plainPassword, $hash);
}
