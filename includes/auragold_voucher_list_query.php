<?php
/**
 * Shared WHERE clause builder for payment/receipt voucher list (filter + export).
 *
 * @return array{where: string, params: array<string, string>}
 */
function auragold_voucher_list_filter_from_request() {
    return [
        'date_from'           => isset($_REQUEST['date_from']) ? trim((string) $_REQUEST['date_from']) : '',
        'date_to'             => isset($_REQUEST['date_to']) ? trim((string) $_REQUEST['date_to']) : '',
        'voucher_no'          => isset($_REQUEST['voucher_no']) ? trim((string) $_REQUEST['voucher_no']) : '',
        'branch_id'           => isset($_REQUEST['branch_id']) ? (int) $_REQUEST['branch_id'] : 0,
        'branch_type'         => isset($_REQUEST['branch_type']) ? trim((string) $_REQUEST['branch_type']) : '',
        'ledger_name'         => isset($_REQUEST['ledger_name']) ? trim((string) $_REQUEST['ledger_name']) : '',
        'against_voucher_type'=> isset($_REQUEST['against_voucher_type']) ? trim((string) $_REQUEST['against_voucher_type']) : '',
        'against_invoice_no'  => isset($_REQUEST['against_invoice_no']) ? trim((string) $_REQUEST['against_invoice_no']) : '',
        'account_no'          => isset($_REQUEST['account_no']) ? trim((string) $_REQUEST['account_no']) : '',
        'q'                   => isset($_REQUEST['q']) ? trim((string) $_REQUEST['q']) : '',
    ];
}

/**
 * @param mysqli $conn
 * @param string $alias Table alias e.g. pv or rv
 * @param array<string, mixed> $filters
 */
function auragold_voucher_list_build_where($conn, $alias, array $filters) {
    $parts = ['1=1'];

    if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
        $df = mysqli_real_escape_string($conn, $filters['date_from']);
        $parts[] = "$alias.voucher_date >= '$df'";
    }
    if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
        $dt = mysqli_real_escape_string($conn, $filters['date_to']);
        $parts[] = "$alias.voucher_date <= '$dt'";
    }
    if (!empty($filters['voucher_no'])) {
        $vn = mysqli_real_escape_string($conn, $filters['voucher_no']);
        $parts[] = "$alias.voucher_no LIKE '%$vn%'";
    }
    if (!empty($filters['branch_id']) && function_exists('auragold_tbl_has_column')) {
        $table = ($alias === 'pv') ? 'tbl_payment_vouchers' : 'tbl_receipt_vouchers';
        if (auragold_tbl_has_column($conn, $table, 'branch_id')) {
            $parts[] = $alias . '.branch_id = ' . (int) $filters['branch_id'];
        }
    }
    if (!empty($filters['branch_type']) && in_array($filters['branch_type'], ['main', 'sub'], true)
        && function_exists('auragold_tbl_has_column')) {
        $table = ($alias === 'pv') ? 'tbl_payment_vouchers' : 'tbl_receipt_vouchers';
        if (auragold_tbl_has_column($conn, $table, 'branch_id')) {
            $typeCond = ($filters['branch_type'] === 'main')
                ? 'IFNULL(bf.main_branch_id, 0) = 0'
                : 'IFNULL(bf.main_branch_id, 0) > 0';
            $parts[] = "EXISTS (SELECT 1 FROM tbl_branches bf WHERE bf.id = $alias.branch_id AND $typeCond)";
        }
    }
    if (!empty($filters['ledger_name'])) {
        $ln = mysqli_real_escape_string($conn, $filters['ledger_name']);
        $parts[] = "$alias.customer_name LIKE '%$ln%'";
    }
    if (!empty($filters['against_voucher_type'])) {
        $av = mysqli_real_escape_string($conn, $filters['against_voucher_type']);
        $parts[] = "($alias.against LIKE '%$av%' OR $alias.voucher_type LIKE '%$av%')";
    }
    if (!empty($filters['against_invoice_no'])) {
        $ai = mysqli_real_escape_string($conn, $filters['against_invoice_no']);
        $parts[] = "$alias.against_of LIKE '%$ai%'";
    }
    if (!empty($filters['account_no'])) {
        $an = mysqli_real_escape_string($conn, $filters['account_no']);
        $parts[] = "$alias.ref_no LIKE '%$an%'";
    }
    if (!empty($filters['q'])) {
        $q = mysqli_real_escape_string($conn, $filters['q']);
        $parts[] = "($alias.voucher_no LIKE '%$q%' OR $alias.customer_name LIKE '%$q%' OR $alias.ref_no LIKE '%$q%')";
    }

    return implode(' AND ', $parts);
}

function auragold_voucher_list_filters_to_query_string(array $filters) {
    $out = [];
    foreach ($filters as $k => $v) {
        if ($v === '' || $v === null || $v === 0) {
            continue;
        }
        $out[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }
    return implode('&', $out);
}
