<?php
/**
 * Grid data for Assign Inventory Items: assigned rows per sale person and/or unassigned stock.
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/ensure_sales_team_inventory_assign_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aii_data_json_out(array $payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($payload, $flags);
    exit;
}

$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    aii_data_json_out(['success' => false, 'message' => 'Unauthorized', 'rows' => []]);
}

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$sales_person = isset($in['sales_person']) ? trim((string) $in['sales_person']) : '';
$filter_type = isset($in['filter_type']) ? strtolower(trim((string) $in['filter_type'])) : 'assign';
if (!in_array($filter_type, ['all', 'assign', 'unassign'], true)) {
    $filter_type = 'assign';
}

$date_from = isset($in['date_from']) ? trim((string) $in['date_from']) : '';
$date_to = isset($in['date_to']) ? trim((string) $in['date_to']) : '';
$est_from = isset($in['est_date_from']) ? trim((string) $in['est_date_from']) : '';
$est_to = isset($in['est_date_to']) ? trim((string) $in['est_date_to']) : '';
$barcode_f = isset($in['barcode']) ? trim((string) $in['barcode']) : '';
$invoice_f = isset($in['invoice_no']) ? trim((string) $in['invoice_no']) : '';

$branch_id = isset($in['branch_id']) ? (int) $in['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);

auragold_ensure_sales_team_inventory_assign_schema($conn);

function aii_parse_date_ymd($s) {
    $s = trim($s);
    if ($s === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
}

/**
 * @return array<int, array<string,mixed>>
 */
function aii_load_assigned_rows($conn, $sales_person, $date_from, $date_to, $est_from, $est_to, $barcode_f, $invoice_f, $branch_id) {
    $sp_esc = mysqli_real_escape_string($conn, $sales_person);
    $sql = "SELECT id, barcode_no, row_json, created_at, updated_at FROM tbl_sales_team_inventory_assign WHERE sales_person = '$sp_esc'";
    if ($branch_id > 0) {
        $sql .= ' AND branch_id = ' . (int) $branch_id;
    }
    $sql .= ' ORDER BY id ASC';
    $res = @mysqli_query($conn, $sql);
    $out = [];
    if (!$res) {
        return $out;
    }
    $df = aii_parse_date_ymd($date_from);
    $dt = aii_parse_date_ymd($date_to);
    $ef = aii_parse_date_ymd($est_from);
    $et = aii_parse_date_ymd($est_to);

    while ($row = mysqli_fetch_assoc($res)) {
        $created = isset($row['created_at']) ? substr((string) $row['created_at'], 0, 10) : '';
        if ($df !== '' && $created !== '' && strcmp($created, $df) < 0) {
            continue;
        }
        if ($dt !== '' && $created !== '' && strcmp($created, $dt) > 0) {
            continue;
        }

        $j = null;
        if (!empty($row['row_json'])) {
            $j = json_decode((string) $row['row_json'], true);
        }
        if (!is_array($j)) {
            $j = [];
        }
        $bc = trim((string) ($j['barcode_no'] ?? $row['barcode_no'] ?? ''));
        if ($barcode_f !== '' && stripos($bc, $barcode_f) === false) {
            continue;
        }
        $inv = trim((string) ($j['invoice_no'] ?? ''));
        if ($invoice_f !== '' && stripos($inv, $invoice_f) === false) {
            continue;
        }
        $est = trim((string) ($j['est_return_date'] ?? ''));
        if ($ef !== '' && $est !== '') {
            $estD = aii_parse_date_ymd($est);
            if ($estD !== '' && strcmp($estD, $ef) < 0) {
                continue;
            }
        }
        if ($et !== '' && $est !== '') {
            $estD = aii_parse_date_ymd($est);
            if ($estD !== '' && strcmp($estD, $et) > 0) {
                continue;
            }
        }

        $desc = trim((string) ($j['product_name'] ?? ''));
        if ($desc === '' && !empty($j['description'])) {
            $desc = trim((string) $j['description']);
        }
        $qty = $j['quantity'] ?? $j['qty'] ?? '';
        $qty = $qty === '' || $qty === null ? '' : (float) $qty;

        $out[] = [
            '_row_kind' => 'assign',
            'invoice_no' => $inv,
            'voucher_type' => trim((string) ($j['voucher_type'] ?? '')),
            'description' => $desc,
            'assign_date' => $created,
            'est_return_date' => $est,
            'qty' => $qty,
            'carat' => trim((string) ($j['carat'] ?? '')),
            'final_wt' => $j['final_wt'] ?? '',
            'amount' => $j['amount'] ?? '',
            'net_amount' => $j['net_amount'] ?? '',
            'barcode_no' => $bc,
            'item_code' => trim((string) ($j['rfid_code'] ?? $j['item_code'] ?? '')),
            'sales_person' => $sales_person,
        ];
    }
    mysqli_free_result($res);
    return $out;
}

/**
 * @return array<int, array<string,mixed>>
 */
function aii_load_unassigned_stock_rows($conn, $barcode_f, $invoice_f, $branch_id) {
    $out = [];
    if ($branch_id <= 0) {
        return $out;
    }
    if (!@mysqli_query($conn, 'SELECT 1 FROM tbl_stock LIMIT 1')) {
        return $out;
    }
    $bf = $barcode_f !== '' ? mysqli_real_escape_string($conn, $barcode_f) : '';
    $bid = (int) $branch_id;

    $notExists = 'NOT EXISTS (SELECT 1 FROM tbl_sales_team_inventory_assign a WHERE a.barcode_no = s.barcode)';
    $sql = "
    SELECT s.barcode AS barcode_no, MAX(p.name) AS product_name,
        SUM(COALESCE(s.current_qty,0)) AS sqty,
        SUM(COALESCE(s.current_weight,0)) AS swt
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON p.id = s.product_id
    WHERE s.status = 1 AND s.barcode IS NOT NULL AND TRIM(s.barcode) <> ''
    AND s.branch_id = $bid
    AND (s.stock_type IS NULL OR LOWER(TRIM(s.stock_type)) <> 'outward')
    AND $notExists
    ";
    if ($bf !== '') {
        $sql .= " AND s.barcode LIKE '%$bf%' ";
    }
    $sql .= ' GROUP BY s.barcode HAVING sqty > 0 OR swt > 0 ORDER BY s.barcode ASC LIMIT 800';

    $res = @mysqli_query($conn, $sql);
    if (!$res) {
        $sql = "
        SELECT s.barcode AS barcode_no, MAX(p.name) AS product_name, 1 AS sqty
        FROM tbl_stock s
        LEFT JOIN tbl_products p ON p.id = s.product_id
        WHERE s.status = 1 AND s.barcode IS NOT NULL AND TRIM(s.barcode) <> ''
        AND s.branch_id = $bid
        AND (s.stock_type IS NULL OR LOWER(TRIM(s.stock_type)) <> 'outward')
        AND $notExists
        ";
        if ($bf !== '') {
            $sql .= " AND s.barcode LIKE '%$bf%' ";
        }
        $sql .= ' GROUP BY s.barcode ORDER BY s.barcode ASC LIMIT 800';
        $res = @mysqli_query($conn, $sql);
    }
    if (!$res) {
        return $out;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $bc = trim((string) ($row['barcode_no'] ?? ''));
        if ($bc === '') {
            continue;
        }
        if ($invoice_f !== '') {
            continue;
        }
        $qty = isset($row['sqty']) ? (float) $row['sqty'] : 0;
        $out[] = [
            '_row_kind' => 'unassign',
            'invoice_no' => '',
            'voucher_type' => '',
            'description' => trim((string) ($row['product_name'] ?? '')),
            'assign_date' => '',
            'est_return_date' => '',
            'qty' => $qty,
            'carat' => '',
            'final_wt' => '',
            'amount' => '',
            'net_amount' => '',
            'barcode_no' => $bc,
            'item_code' => '',
            'sales_person' => '',
        ];
    }
    mysqli_free_result($res);
    return $out;
}

$rows = [];
if ($filter_type === 'assign' || $filter_type === 'all') {
    if ($sales_person === '') {
        if ($filter_type === 'assign') {
            aii_data_json_out(['success' => true, 'rows' => [], 'message' => 'Select a sale person']);
        }
    } elseif ($branch_id <= 0) {
        if ($filter_type === 'assign') {
            aii_data_json_out(['success' => true, 'rows' => [], 'message' => 'Select a branch']);
        }
    } else {
        $rows = array_merge($rows, aii_load_assigned_rows($conn, $sales_person, $date_from, $date_to, $est_from, $est_to, $barcode_f, $invoice_f, $branch_id));
    }
}

if ($filter_type === 'unassign' || $filter_type === 'all') {
    $un = aii_load_unassigned_stock_rows($conn, $barcode_f, $invoice_f, $branch_id);
    if ($filter_type === 'unassign') {
        $rows = $un;
    } else {
        $rows = array_merge($rows, $un);
    }
}

aii_data_json_out(['success' => true, 'rows' => $rows]);
