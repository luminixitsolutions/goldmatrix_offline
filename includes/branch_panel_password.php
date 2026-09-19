<?php
/**
 * Per-branch panel password (Go to branch gate). Default: Admin@12345
 * Stored as bcrypt in tbl_branches.panel_password_hash (registry DB).
 */
if (!defined('AURAGOLD_BRANCH_PANEL_DEFAULT_PASSWORD')) {
    define('AURAGOLD_BRANCH_PANEL_DEFAULT_PASSWORD', 'Admin@12345');
}

if (!function_exists('auragold_branch_panel_password_default_hash')) {
    function auragold_branch_panel_password_default_hash(): string
    {
        static $cached = null;
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $cached = password_hash(AURAGOLD_BRANCH_PANEL_DEFAULT_PASSWORD, PASSWORD_DEFAULT);

        return $cached;
    }
}

if (!function_exists('auragold_ensure_tbl_branches_panel_password')) {
    /**
     * Add panel_password_hash to registry tbl_branches; backfill empty rows with default hash.
     */
    function auragold_ensure_tbl_branches_panel_password(mysqli $link): bool
    {
        if (!$link) {
            return false;
        }
        $r = @mysqli_query($link, "SHOW TABLES LIKE 'tbl_branches'");
        $exists = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        if (!$exists) {
            return false;
        }
        $c = @mysqli_query($link, "SHOW COLUMNS FROM `tbl_branches` LIKE 'panel_password_hash'");
        $hasCol = $c && mysqli_num_rows($c) > 0;
        if ($c) {
            mysqli_free_result($c);
        }
        if (!$hasCol) {
            if (!@mysqli_query(
                $link,
                "ALTER TABLE `tbl_branches` ADD COLUMN `panel_password_hash` VARCHAR(255) NULL DEFAULT NULL COMMENT 'bcrypt — required to open branch panel (Go to branch)'"
            )) {
                return false;
            }
        }
        $defaultHash = auragold_branch_panel_password_default_hash();
        @mysqli_query(
            $link,
            "UPDATE `tbl_branches` SET `panel_password_hash` = '" . mysqli_real_escape_string($link, $defaultHash) . "' "
            . "WHERE (`panel_password_hash` IS NULL OR TRIM(`panel_password_hash`) = '')"
        );

        return true;
    }
}

if (!function_exists('auragold_branch_panel_password_row_hash')) {
    function auragold_branch_panel_password_row_hash(array $row): string
    {
        foreach ($row as $k => $v) {
            if (strcasecmp((string) $k, 'panel_password_hash') === 0) {
                return trim((string) $v);
            }
        }

        return '';
    }
}

if (!function_exists('auragold_branch_panel_password_verify')) {
    function auragold_branch_panel_password_verify(array $branchRow, string $password): bool
    {
        $password = (string) $password;
        if ($password === '') {
            return false;
        }
        $hash = auragold_branch_panel_password_row_hash($branchRow);
        if ($hash === '') {
            return $password === AURAGOLD_BRANCH_PANEL_DEFAULT_PASSWORD;
        }

        return password_verify($password, $hash);
    }
}

if (!function_exists('auragold_branch_panel_password_set')) {
    function auragold_branch_panel_password_set(mysqli $link, int $branchId, string $newPassword): bool
    {
        $branchId = (int) $branchId;
        $newPassword = trim($newPassword);
        if ($branchId <= 0 || $newPassword === '') {
            return false;
        }
        if (!auragold_ensure_tbl_branches_panel_password($link)) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $esc = mysqli_real_escape_string($link, $hash);

        return (bool) @mysqli_query(
            $link,
            'UPDATE tbl_branches SET panel_password_hash = \'' . $esc . '\' WHERE id = ' . $branchId . ' LIMIT 1'
        );
    }
}

if (!function_exists('auragold_branch_panel_grant_switch')) {
    /** @return string one-time token for switch_branch.php */
    function auragold_branch_panel_grant_switch(int $branchId): string
    {
        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            return '';
        }
        if (!isset($_SESSION['branch_panel_switch']) || !is_array($_SESSION['branch_panel_switch'])) {
            $_SESSION['branch_panel_switch'] = [];
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['branch_panel_switch'][(string) $branchId] = [
            'token' => $token,
            'ts'    => time(),
        ];

        return $token;
    }
}

if (!function_exists('auragold_branch_panel_consume_switch')) {
    function auragold_branch_panel_consume_switch(int $branchId, string $token): bool
    {
        $branchId = (int) $branchId;
        $token = trim($token);
        if ($branchId <= 0 || $token === '') {
            return false;
        }
        $key = (string) $branchId;
        if (empty($_SESSION['branch_panel_switch'][$key]) || !is_array($_SESSION['branch_panel_switch'][$key])) {
            return false;
        }
        $rec = $_SESSION['branch_panel_switch'][$key];
        unset($_SESSION['branch_panel_switch'][$key]);
        $age = time() - (int) ($rec['ts'] ?? 0);
        if ($age > 300) {
            return false;
        }
        $stored = (string) ($rec['token'] ?? '');

        return $stored !== '' && hash_equals($stored, $token);
    }
}

if (!function_exists('auragold_branch_panel_may_manage_password')) {
    /** Main-branch admin may set panel password for branches in their scope. */
    function auragold_branch_panel_may_manage_password(array $targetRow): bool
    {
        if (empty($_SESSION['Admin'])) {
            return false;
        }
        if (!function_exists('auragold_session_is_admin_login_type') || !auragold_session_is_admin_login_type()) {
            return false;
        }
        if (!auragold_can_user_open_branch_row($targetRow)) {
            return false;
        }
        $scopeMain = function_exists('auragold_session_restrict_sub_branch_ops_main_id')
            ? auragold_session_restrict_sub_branch_ops_main_id()
            : 0;
        if ($scopeMain <= 0) {
            return true;
        }
        $rowMain = (int) ($targetRow['main_branch_id'] ?? 0);
        $rowId = (int) ($targetRow['id'] ?? 0);

        return $rowMain === 0 ? ($rowId === $scopeMain) : ($rowMain === $scopeMain);
    }
}
