<?php

require_once __DIR__ . '/auragold_barcode_scan_log_schema.php';

/**
 * @return array{key: string, label: string, numeric?: bool}[]
 */
function auragold_barcode_scan_report_columns(): array
{
    return [
        ['key' => 'sr', 'label' => 'Sr'],
        ['key' => 'scan_date', 'label' => 'Scan Date'],
        ['key' => 'scan_time', 'label' => 'Scan Time'],
        ['key' => 'scan_status', 'label' => 'Status'],
        ['key' => 'scan_code', 'label' => 'Scan Code'],
        ['key' => 'barcode', 'label' => 'Barcode'],
        ['key' => 'rfid_code', 'label' => 'RFID Code'],
        ['key' => 'product_name', 'label' => 'Product'],
        ['key' => 'article', 'label' => 'Article'],
        ['key' => 'metal_name', 'label' => 'Metal'],
        ['key' => 'branch_name', 'label' => 'Branch'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'qty', 'label' => 'Qty', 'numeric' => true],
        ['key' => 'gross_wt', 'label' => 'Gross Wt', 'numeric' => true],
        ['key' => 'net_wt', 'label' => 'Net Wt', 'numeric' => true],
        ['key' => 'final_wt', 'label' => 'Final Wt', 'numeric' => true],
        ['key' => 'voucher_type', 'label' => 'Voucher Type'],
        ['key' => 'invoice_no', 'label' => 'Invoice No.'],
        ['key' => 'user_name', 'label' => 'Scanned By'],
    ];
}

/**
 * @param mysqli|null $conn
 * @param array<string,mixed> $opts
 * @return array{success: bool, rows: array<int,array<string,mixed>>, summary: array<string,mixed>, from_date: string, to_date: string, error?: string}
 */
function auragold_barcode_scan_report_fetch($conn, array $opts = []): array
{
    if (!$conn || !($conn instanceof mysqli)) {
        return ['success' => false, 'rows' => [], 'summary' => [], 'from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d'), 'error' => 'Database unavailable'];
    }

    auragold_ensure_tbl_barcode_scan_log($conn);

    $from_date = isset($opts['from_date']) ? trim((string) $opts['from_date']) : date('Y-m-d');
    $to_date = isset($opts['to_date']) ? trim((string) $opts['to_date']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
        $from_date = date('Y-m-d');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        $to_date = date('Y-m-d');
    }
    if ($from_date > $to_date) {
        $tmp = $from_date;
        $from_date = $to_date;
        $to_date = $tmp;
    }

    $q = isset($opts['q']) ? trim((string) $opts['q']) : (isset($opts['search']) ? trim((string) $opts['search']) : '');
    $status = isset($opts['status']) ? strtolower(trim((string) $opts['status'])) : '';
    $unlimited = !empty($opts['unlimited']);
    $limit = isset($opts['limit']) ? (int) $opts['limit'] : 5000;
    if ($limit < 1) {
        $limit = 500;
    }
    if (!$unlimited && $limit > 10000) {
        $limit = 10000;
    }

    $where = [
        "l.scan_date >= '" . mysqli_real_escape_string($conn, $from_date) . "'",
        "l.scan_date <= '" . mysqli_real_escape_string($conn, $to_date) . "'",
    ];

    $branch_id = 0;
    if (function_exists('auragold_effective_branch_id')) {
        $branch_id = (int) auragold_effective_branch_id();
    } elseif (!empty($_SESSION['working_branch_id'])) {
        $branch_id = (int) $_SESSION['working_branch_id'];
    }
    if ($branch_id > 0) {
        $where[] = '(l.branch_id IS NULL OR l.branch_id = 0 OR l.branch_id = ' . (int) $branch_id . ')';
    }

    if ($status === 'matched' || $status === 'unknown') {
        $where[] = "l.scan_status = '" . mysqli_real_escape_string($conn, $status) . "'";
    }

    if ($q !== '') {
        $qe = mysqli_real_escape_string($conn, $q);
        $like = "'%" . $qe . "%'";
        $where[] = "(l.scan_code LIKE $like OR l.barcode LIKE $like OR l.rfid_code LIKE $like
            OR l.product_name LIKE $like OR l.metal_name LIKE $like OR l.branch_name LIKE $like
            OR l.user_name LIKE $like OR l.invoice_no LIKE $like)";
    }

    $where_sql = implode(' AND ', $where);
    $limit_sql = $unlimited ? '' : (' LIMIT ' . (int) $limit);

    $sql = "SELECT l.id, l.scan_date, l.scan_time, l.scanned_at, l.scan_status, l.scan_code,
        l.barcode, l.rfid_code, l.product_code, l.product_name, l.article, l.metal_name,
        l.branch_name, l.location, l.qty, l.gross_wt, l.net_wt, l.final_wt,
        l.voucher_type, l.invoice_no, l.user_name
        FROM tbl_barcode_scan_log l
        WHERE $where_sql
        ORDER BY l.scanned_at DESC, l.id DESC" . $limit_sql;

    $res = @mysqli_query($conn, $sql);
    if (!$res) {
        return ['success' => false, 'rows' => [], 'summary' => [], 'from_date' => $from_date, 'to_date' => $to_date, 'error' => 'Query failed'];
    }

    $rows = [];
    $summary = [
        'total_records' => 0,
        'matched_count' => 0,
        'unknown_count' => 0,
        'total_qty' => 0.0,
        'total_gross_wt' => 0.0,
        'total_net_wt' => 0.0,
        'total_final_wt' => 0.0,
    ];
    $sr = 0;

    while ($row = mysqli_fetch_assoc($res)) {
        $sr++;
        $scan_date = (string) ($row['scan_date'] ?? '');
        $scan_time = (string) ($row['scan_time'] ?? '');
        $scan_status = (string) ($row['scan_status'] ?? '');
        $qty = is_numeric($row['qty'] ?? null) ? (float) $row['qty'] : 0.0;
        $gross = is_numeric($row['gross_wt'] ?? null) ? (float) $row['gross_wt'] : 0.0;
        $net = is_numeric($row['net_wt'] ?? null) ? (float) $row['net_wt'] : 0.0;
        $final = is_numeric($row['final_wt'] ?? null) ? (float) $row['final_wt'] : 0.0;

        $summary['total_records']++;
        if ($scan_status === 'unknown') {
            $summary['unknown_count']++;
        } else {
            $summary['matched_count']++;
        }
        $summary['total_qty'] += $qty;
        $summary['total_gross_wt'] += $gross;
        $summary['total_net_wt'] += $net;
        $summary['total_final_wt'] += $final;

        $rows[] = [
            'sr' => $sr,
            'id' => (int) ($row['id'] ?? 0),
            'scan_date' => $scan_date !== '' ? date('d-m-Y', strtotime($scan_date)) : '',
            'scan_time' => $scan_time !== '' ? substr($scan_time, 0, 8) : '',
            'scanned_at' => (string) ($row['scanned_at'] ?? ''),
            'scan_status' => $scan_status,
            'scan_status_label' => $scan_status === 'unknown' ? 'Unknown' : 'Matched',
            'scan_code' => (string) ($row['scan_code'] ?? ''),
            'barcode' => (string) ($row['barcode'] ?? ''),
            'rfid_code' => (string) ($row['rfid_code'] ?? ''),
            'product_code' => (string) ($row['product_code'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'article' => (string) ($row['article'] ?? ''),
            'metal_name' => (string) ($row['metal_name'] ?? ''),
            'branch_name' => (string) ($row['branch_name'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'qty' => $row['qty'],
            'gross_wt' => $row['gross_wt'],
            'net_wt' => $row['net_wt'],
            'final_wt' => $row['final_wt'],
            'voucher_type' => (string) ($row['voucher_type'] ?? ''),
            'invoice_no' => (string) ($row['invoice_no'] ?? ''),
            'user_name' => (string) ($row['user_name'] ?? ''),
        ];
    }
    mysqli_free_result($res);

    return [
        'success' => true,
        'rows' => $rows,
        'summary' => $summary,
        'from_date' => $from_date,
        'to_date' => $to_date,
    ];
}

/**
 * @param array<string,mixed> $filters
 */
function auragold_barcode_scan_report_filters_from_request(array $filters = []): array
{
    $from = isset($filters['from_date']) ? trim((string) $filters['from_date']) : (isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : date('Y-m-d'));
    $to = isset($filters['to_date']) ? trim((string) $filters['to_date']) : (isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : date('Y-m-d'));
    $status = isset($filters['status']) ? trim((string) $filters['status']) : (isset($_GET['status']) ? trim((string) $_GET['status']) : '');
    $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
    if ($q === '' && isset($filters['search'])) {
        $q = trim((string) $filters['search']);
    }
    if ($q === '' && isset($_GET['q'])) {
        $q = trim((string) $_GET['q']);
    }
    if ($q === '' && isset($_GET['search'])) {
        $q = trim((string) $_GET['search']);
    }

    return [
        'from_date' => $from,
        'to_date' => $to,
        'status' => $status,
        'q' => $q,
        'unlimited' => true,
    ];
}
