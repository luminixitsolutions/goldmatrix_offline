<?php
/**
 * Adds tbl_customer_ledger.branch_id when missing (opening and branch-scoped rows).
 */
function auragold_ensure_customer_ledger_branch_column(mysqli $conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'branch_id'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customer_ledger ADD COLUMN branch_id INT UNSIGNED NULL DEFAULT NULL AFTER customer_id"
        );
    }
    if ($r) {
        mysqli_free_result($r);
    }
    $done = true;
}

/**
 * AND fragment for prior-balance SELECTs on tbl_customer_ledger when posting for a document branch.
 *
 * @param int $branch_id Resolved branch (voucher/invoice header). Use 0 to omit (legacy behaviour).
 */
function auragold_customer_ledger_branch_scope_sql(mysqli $conn, int $branch_id): string {
    if ($branch_id <= 0 || !$conn instanceof mysqli) {
        return '';
    }
    auragold_ensure_customer_ledger_branch_column($conn);
    if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id')) {
        return '';
    }
    $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    if ($main > 0 && $branch_id === $main) {
        return ' AND (branch_id = ' . (int) $branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
    }
    return ' AND COALESCE(branch_id, 0) = ' . (int) $branch_id;
}
