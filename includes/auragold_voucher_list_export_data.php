<?php
/**
 * Fetch voucher rows for list export (payment / receipt).
 *
 * @return list<array<string, mixed>>
 */
function auragold_voucher_list_export_rows(mysqli $conn, string $kind, array $filters, int $limit = 5000): array {
    require_once __DIR__ . '/auragold_voucher_list_query.php';

    $alias = ($kind === 'receipt') ? 'rv' : 'pv';
    $table = ($kind === 'receipt') ? 'tbl_receipt_vouchers' : 'tbl_payment_vouchers';
    $where = auragold_voucher_list_build_where($conn, $alias, $filters);

    $branchJoin = '';
    $branchSelect = "'' AS branch_name";
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'branch_id')) {
        $branchSelect = 'COALESCE(b.name, \'\') AS branch_name';
        $branchJoin = " LEFT JOIN tbl_branches b ON b.id = $alias.branch_id";
    }

    $rows = getList("
        SELECT $alias.id, $alias.voucher_no, $alias.customer_name, $alias.ref_no, $alias.voucher_date,
               $alias.total_amount, $alias.total_gold, $alias.sales_person, $alias.against, $alias.against_of,
               $alias.voucher_type, $alias.comment, $branchSelect
        FROM $table $alias
        $branchJoin
        WHERE $where
        ORDER BY $alias.id DESC
        LIMIT " . (int) $limit
    );

    return is_array($rows) ? $rows : [];
}

function auragold_voucher_list_export_title(string $kind): string {
    return $kind === 'receipt' ? 'Receipt Voucher List' : 'Payment Voucher List';
}
