<?php
/**
 * Fetch contra / journal voucher rows for list export.
 *
 * @return list<array<string, mixed>>
 */
function auragold_simple_voucher_list_export_rows(mysqli $conn, string $kind, int $limit = 5000): array {
    $table = ($kind === 'journal') ? 'tbl_journal_vouchers' : 'tbl_contra_vouchers';
    $t = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$t || mysqli_num_rows($t) === 0) {
        return [];
    }

    if ($kind === 'journal') {
        $rows = getList("
            SELECT id, voucher_no, voucher_date, comment, debit_total, credit_total, status, created_at
            FROM tbl_journal_vouchers
            ORDER BY id DESC
            LIMIT " . (int) $limit
        );
    } else {
        $rows = getList("
            SELECT id, voucher_no, voucher_date, total_amount, comment, status, created_at
            FROM tbl_contra_vouchers
            ORDER BY id DESC
            LIMIT " . (int) $limit
        );
    }

    return is_array($rows) ? $rows : [];
}

function auragold_simple_voucher_list_export_title(string $kind): string {
    return $kind === 'journal' ? 'Journal Voucher List' : 'Contra Voucher List';
}
