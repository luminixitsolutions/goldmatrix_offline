<?php
/**
 * Copy one tbl_users row from the registry DB into a branch database.
 */
if (!function_exists('auragold_copy_registry_user_into_branch_db')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function auragold_copy_registry_user_into_branch_db(mysqli $conn, string $branchDb, int $userId): array {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Invalid user id.'];
        }
        if (!function_exists('auragold_branch_mysql_identifier_ok')) {
            require_once __DIR__ . '/branch_create_db_after_save.php';
        }
        $regDb = defined('DB_NAME') ? trim((string) DB_NAME) : '';
        $branchDb = trim($branchDb);
        if ($regDb === '' || !auragold_branch_mysql_identifier_ok($branchDb) || !auragold_branch_mysql_identifier_ok($regDb)) {
            return ['ok' => false, 'message' => 'Invalid database context for user copy.'];
        }
        $eUid = (int) $userId;
        $chk = mysqli_query($conn, 'SELECT 1 FROM `' . str_replace('`', '``', $regDb) . '`.`tbl_users` WHERE id = ' . $eUid . ' LIMIT 1');
        if (!$chk || mysqli_num_rows($chk) < 1) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return ['ok' => false, 'message' => 'User id ' . $eUid . ' was not found in the registry tbl_users.'];
        }
        mysqli_free_result($chk);
        $b = '`' . str_replace('`', '``', $branchDb) . '`';
        $s = '`' . str_replace('`', '``', $regDb) . '`';
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
        $ok = @mysqli_query($conn, "INSERT INTO $b.`tbl_users` SELECT * FROM $s.`tbl_users` WHERE id = $eUid LIMIT 1");
        $err = mysqli_error($conn);
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not copy login user into branch database: ' . $err];
        }
        return ['ok' => true, 'message' => ''];
    }
}
