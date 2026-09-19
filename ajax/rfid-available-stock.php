<?php
/**
 * JSON list of available stock rows for RFID / Barcode Scan (filtered).
 * One row per barcode + branch: balance = SUM(current_qty), SUM(current_weight) across tbl_stock lines.
 */
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/../includes/mp-jobwork-queue-diamond-stock.php';

/**
 * Drop any accidental output from includes (notices, whitespace) so the client always gets valid JSON.
 */
function rfid_avail_json_out(array $payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    echo ($json !== false) ? $json : '{"success":false,"message":"JSON encode failed"}';
    exit;
}

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    rfid_avail_json_out(['success' => false, 'message' => 'Unauthorized']);
}

function rfid_tbl_exists($conn, $table) {
    $t = mysqli_real_escape_string($conn, $table);
    $r = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    $ok = $r && mysqli_num_rows($r) > 0;
    if ($r) {
        mysqli_free_result($r);
    }
    return $ok;
}

/** @return array<string, array{Field:string,Type:string}> */
function rfid_stock_journal_columns($conn) {
    $cols = [];
    $r = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_stock_journal');
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $f = strtolower((string) ($row['Field'] ?? ''));
            if ($f !== '') {
                $cols[$f] = $row;
            }
        }
        mysqli_free_result($r);
    }
    return $cols;
}

function rfid_sj_has_col(array $sj_cols, $name) {
    return isset($sj_cols[strtolower((string) $name)]);
}

/** WHERE fragment for "journal row is active" — supports tinyint or varchar status. */
function rfid_sj_active_sql(array $sj_cols) {
    if (!rfid_sj_has_col($sj_cols, 'status')) {
        return '1=1';
    }
    $type = strtolower((string) ($sj_cols['status']['Type'] ?? ''));
    if (strpos($type, 'char') !== false || strpos($type, 'text') !== false || strpos($type, 'enum') !== false) {
        return "(status = 'active' OR LOWER(TRIM(CAST(status AS CHAR))) = 'active')";
    }
    return '(status = 1 OR status = \'1\')';
}

try {

if (!rfid_tbl_exists($conn, 'tbl_stock')) {
    rfid_avail_json_out(['success' => true, 'rows' => [], 'totals' => ['qty' => 0, 'final_wt' => 0]]);
}

$sj_cols = [];
$has_stock_journal = rfid_tbl_exists($conn, 'tbl_stock_journal');
if ($has_stock_journal) {
    $sj_cols = rfid_stock_journal_columns($conn);
    if (!rfid_sj_has_col($sj_cols, 'id') || !rfid_sj_has_col($sj_cols, 'barcode')) {
        $has_stock_journal = false;
    }
}

$has_sj_group_name = $has_stock_journal && rfid_sj_has_col($sj_cols, 'group_name');

/** When Stock Journal is saved, inward purchase/opening rows are zeroed but keep reference_type=stock_journal + opening_weight (see save-stock-journal.php). Include those in "available" unless the barcode was sold (sale outward). */
$stock_has_reference_type = false;
$ref_type_col_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_type'");
if ($ref_type_col_chk && mysqli_num_rows($ref_type_col_chk) > 0) {
    $stock_has_reference_type = true;
}
if ($ref_type_col_chk) {
    mysqli_free_result($ref_type_col_chk);
}

$sold_bc_join_frag = '';
$having_balance_frag = 'HAVING (SUM(COALESCE(s.current_qty,0)) > 0 OR SUM(COALESCE(s.current_weight,0)) > 0)';
if ($stock_has_reference_type) {
    $sold_bc_join_frag = "
    LEFT JOIN (
        SELECT barcode, branch_id
        FROM tbl_stock
        WHERE status = 1
          AND (stock_type IS NULL OR LOWER(TRIM(stock_type)) = 'outward')
          AND (LOWER(TRIM(COALESCE(reference_type, ''))) = 'sale_invoice')
          AND (barcode IS NOT NULL AND TRIM(COALESCE(barcode, '')) <> '')
        GROUP BY barcode, branch_id
    ) sold_bc ON sold_bc.barcode = s.barcode AND sold_bc.branch_id = s.branch_id
    ";
    /* After SJ merge, current_* are 0 but opening_* remain; reference_type may be stock_journal or unset on legacy rows. */
    $having_balance_frag = 'HAVING (
        (SUM(COALESCE(s.current_qty,0)) > 0 OR SUM(COALESCE(s.current_weight,0)) > 0)
        OR (
            MAX(CASE WHEN sold_bc.barcode IS NOT NULL THEN 1 ELSE 0 END) = 0
            AND SUM(CASE
                WHEN LOWER(TRIM(COALESCE(s.stock_type, \'\'))) IN (\'purchase\', \'opening\', \'inward\', \'balance\')
                 AND (
                    LOWER(TRIM(COALESCE(s.reference_type, \'\'))) = \'stock_journal\'
                    OR (
                        TRIM(COALESCE(s.reference_type, \'\')) = \'\'
                        AND COALESCE(s.opening_weight, s.final_weight, 0) > 0
                    )
                 )
                THEN COALESCE(s.opening_weight, s.final_weight, 0)
                ELSE 0
            END) > 0
        )
    )';
} else {
    /* No reference_type column: cannot detect SJ merge via reference — treat non-outward rows with opening qty/wt as on-hand. */
    $having_balance_frag = 'HAVING (
        (SUM(COALESCE(s.current_qty,0)) > 0 OR SUM(COALESCE(s.current_weight,0)) > 0)
        OR SUM(CASE WHEN LOWER(TRIM(COALESCE(s.stock_type, \'\'))) IN (\'purchase\', \'opening\', \'inward\', \'balance\')
            THEN COALESCE(s.opening_weight, s.final_weight, 0) ELSE 0 END) > 0
        OR SUM(CASE WHEN LOWER(TRIM(COALESCE(s.stock_type, \'\'))) IN (\'purchase\', \'opening\', \'inward\', \'balance\')
            THEN COALESCE(s.opening_qty, 0) ELSE 0 END) > 0
    )';
}

$metal_has_display = false;
$metal_has_system = false;
$metal_has_name = false;
$mh = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_metal');
if ($mh) {
    while ($mr = mysqli_fetch_assoc($mh)) {
        $fn = strtolower((string) ($mr['Field'] ?? ''));
        if ($fn === 'display_name') {
            $metal_has_display = true;
        }
        if ($fn === 'system_name') {
            $metal_has_system = true;
        }
        if ($fn === 'name') {
            $metal_has_name = true;
        }
    }
    mysqli_free_result($mh);
}
if ($metal_has_display && $metal_has_system) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.display_name), \'\'), NULLIF(TRIM(m.system_name), \'\'), \'\')';
} elseif ($metal_has_display) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.display_name), \'\'), \'\')';
} elseif ($metal_has_system) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.system_name), \'\'), \'\')';
} elseif ($metal_has_name) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.name), \'\'), \'\')';
} else {
    $metal_name_expr = '\'\'';
}

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$branch_id = isset($in['branch_id']) ? (int) $in['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);
$metal_id = isset($in['metal_id']) ? (int) $in['metal_id'] : 0;
/** Optional comma-separated metal IDs (e.g. Jobwork Queue diamond picker — all "Diamond & Stones" masters). When non-empty, overrides single $metal_id. */
$metal_ids_list = [];
if (isset($in['metal_ids']) && trim((string) $in['metal_ids']) !== '') {
    foreach (explode(',', (string) $in['metal_ids']) as $p) {
        $v = (int) trim($p);
        if ($v > 0) {
            $metal_ids_list[] = $v;
        }
    }
    $metal_ids_list = array_values(array_unique($metal_ids_list));
}
$product_id = isset($in['product_id']) ? (int) $in['product_id'] : 0;
$category_id = isset($in['category_id']) ? (int) $in['category_id'] : 0;
$article = isset($in['article']) ? trim((string) $in['article']) : '';
$voucher_type = isset($in['voucher_type']) ? trim((string) $in['voucher_type']) : '';
$karat_id = isset($in['karat_id']) ? (int) $in['karat_id'] : 0;
$group_name = isset($in['group_name']) ? trim((string) $in['group_name']) : '';
$invoice_no = isset($in['invoice_no']) ? trim((string) $in['invoice_no']) : '';
$gross_wt = isset($in['gross_wt']) ? trim((string) $in['gross_wt']) : '';
$barcode_no = isset($in['barcode_no']) ? trim((string) $in['barcode_no']) : '';
$rfid_code = isset($in['rfid_code']) ? trim((string) $in['rfid_code']) : '';
$jobwork_order_id = isset($in['jobwork_order_id']) ? (int) $in['jobwork_order_id'] : 0;

$inner_where = [
    's.status = 1',
    "(s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> '')",
    // Sale invoices insert stock_type=outward rows; including them in SUM() keeps sold barcodes "positive". Only count on-hand rows.
    "(s.stock_type IS NULL OR LOWER(TRIM(s.stock_type)) <> 'outward')",
];
// Available list uses tbl_stock current_weight / current_qty (already reduced after partial jobwork issues).
// Do not hide rows that were partially issued on this job — balance HAVING still applies below.

// Match Stock History branch scope (adv_branch): tbl_stock.branch_id only — same barcodes as inward for that branch.
if ($branch_id > 0) {
    $inner_where[] = 's.branch_id = ' . $branch_id;
}
if (!empty($metal_ids_list)) {
    $inner_where[] = 's.metal_id IN (' . implode(',', array_map('intval', $metal_ids_list)) . ')';
} elseif ($metal_id > 0) {
    $inner_where[] = 's.metal_id = ' . $metal_id;
}
if ($product_id > 0) {
    $inner_where[] = 's.product_id = ' . $product_id;
}
if ($category_id > 0) {
    $inner_where[] = 'p.category_id = ' . $category_id;
}
if ($article !== '') {
    $inner_where[] = "p.article LIKE '%" . mysqli_real_escape_string($conn, $article) . "%'";
}
if ($barcode_no !== '') {
    $inner_where[] = "s.barcode LIKE '%" . mysqli_real_escape_string($conn, $barcode_no) . "%'";
}

$outer_where = [];
$kn_for_karat = null;

if ($voucher_type !== '' && $has_stock_journal && rfid_sj_has_col($sj_cols, 'voucher_type')) {
    $outer_where[] = "sj.voucher_type = '" . mysqli_real_escape_string($conn, $voucher_type) . "'";
}
if ($karat_id > 0 && rfid_tbl_exists($conn, 'tbl_carat')) {
    $kr = getRecord('SELECT name FROM tbl_carat WHERE id = ' . $karat_id . ' AND status = 1 LIMIT 1');
    if ($kr && isset($kr['name']) && trim((string) $kr['name']) !== '') {
        $kn_for_karat = mysqli_real_escape_string($conn, trim((string) $kr['name']));
        if ($has_stock_journal && rfid_sj_has_col($sj_cols, 'karat')) {
            $outer_where[] = "(pc.carat = '$kn_for_karat' OR CAST(sj.karat AS CHAR) = '$kn_for_karat' OR sj.karat = " . $karat_id . ')';
        } elseif ($has_stock_journal) {
            $outer_where[] = "pc.carat = '$kn_for_karat'";
        } else {
            $inner_where[] = "pc.carat = '$kn_for_karat'";
        }
    }
}
if ($group_name !== '' && $has_sj_group_name && $has_stock_journal) {
    $outer_where[] = "sj.group_name LIKE '%" . mysqli_real_escape_string($conn, $group_name) . "%'";
}
if ($invoice_no !== '' && $has_stock_journal && rfid_sj_has_col($sj_cols, 'invoice_no')) {
    $outer_where[] = "sj.invoice_no LIKE '%" . mysqli_real_escape_string($conn, $invoice_no) . "%'";
}
if ($rfid_code !== '') {
    $rc = mysqli_real_escape_string($conn, $rfid_code);
    if ($has_stock_journal && rfid_sj_has_col($sj_cols, 'rfid_code')) {
        $outer_where[] = "(pc.sku_code LIKE '%$rc%' OR sj.rfid_code LIKE '%$rc%')";
    } elseif ($has_stock_journal) {
        $outer_where[] = "pc.sku_code LIKE '%$rc%'";
    } else {
        $inner_where[] = "pc.sku_code LIKE '%$rc%'";
    }
}
if ($gross_wt !== '') {
    $gw = mysqli_real_escape_string($conn, $gross_wt);
    $gross_expr = ($has_stock_journal && rfid_sj_has_col($sj_cols, 'gross_weight'))
        ? 'COALESCE(sj.gross_weight, s.opening_weight, 0)'
        : 'COALESCE(s.opening_weight, 0)';
    if (is_numeric($gross_wt)) {
        $gwn = (float) $gross_wt;
        $outer_where[] = '(ABS(' . $gross_expr . ' - ' . $gwn . ') < 0.0001 OR CAST(' . $gross_expr . " AS CHAR) LIKE '%$gw%')";
    } else {
        $outer_where[] = "CAST($gross_expr AS CHAR) LIKE '%$gw%'";
    }
}

$inner_sql = implode(' AND ', $inner_where);
$outer_sql = count($outer_where) ? 'WHERE ' . implode(' AND ', $outer_where) : '';

$inner_where_simple = $inner_where;
if ($kn_for_karat !== null && $has_stock_journal) {
    $has_pc_carat = false;
    foreach ($inner_where_simple as $w) {
        if (strpos($w, 'pc.carat') !== false) {
            $has_pc_carat = true;
            break;
        }
    }
    if (!$has_pc_carat) {
        $inner_where_simple[] = "pc.carat = '$kn_for_karat'";
    }
}
$inner_sql_simple = implode(' AND ', $inner_where_simple);

$outer_where_simple = [];
if ($gross_wt !== '') {
    $gw = mysqli_real_escape_string($conn, $gross_wt);
    $expr = 'COALESCE(s.opening_weight, 0)';
    if (is_numeric($gross_wt)) {
        $gwn = (float) $gross_wt;
        $outer_where_simple[] = '(ABS(' . $expr . ' - ' . $gwn . ') < 0.0001 OR CAST(' . $expr . " AS CHAR) LIKE '%$gw%')";
    } else {
        $outer_where_simple[] = "CAST($expr AS CHAR) LIKE '%$gw%'";
    }
}
$outer_sql_simple = count($outer_where_simple) ? 'WHERE ' . implode(' AND ', $outer_where_simple) : '';

$sj_join_sql = '';
if ($has_stock_journal) {
    $sj_status_sql = rfid_sj_active_sql($sj_cols);
    $sj_sel = ['sj1.id', 'sj1.barcode'];
    foreach (['voucher_type', 'location', 'invoice_no', 'rfid_code', 'karat', 'gross_weight', 'net_weight', 'final_weight', 'purity_weight', 'pure_weight'] as $sjf) {
        if (rfid_sj_has_col($sj_cols, $sjf)) {
            $sj_sel[] = 'sj1.' . $sjf;
        } else {
            $sj_sel[] = 'NULL AS ' . $sjf;
        }
    }
    if ($has_sj_group_name) {
        $sj_sel[] = 'sj1.group_name';
    } else {
        $sj_sel[] = 'NULL AS group_name';
    }
    $sj_sel_sql = implode(",\n            ", $sj_sel);
    $sj_join_sql = "
    LEFT JOIN (
        SELECT
            $sj_sel_sql
        FROM tbl_stock_journal sj1
        INNER JOIN (
            SELECT barcode, MAX(id) AS max_id
            FROM tbl_stock_journal
            WHERE ($sj_status_sql) AND barcode IS NOT NULL AND TRIM(barcode) <> ''
            GROUP BY barcode
        ) sjmx ON sjmx.max_id = sj1.id
    ) sj ON (sj.barcode COLLATE utf8mb4_general_ci) = (s.barcode COLLATE utf8mb4_general_ci)
        AND s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> ''
    ";
}

$select_cols = "
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_qty,
        s.opening_purity,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.sku_code AS pc_rfid,
        pc.carat AS pc_carat,
        sj.voucher_type AS sj_voucher_type,
        sj.location AS sj_location,
        sj.invoice_no AS sj_invoice_no,
        sj.rfid_code AS sj_rfid,
        sj.karat AS sj_karat,
        sj.gross_weight AS sj_gross_weight,
        sj.net_weight AS sj_net_weight,
        sj.final_weight AS sj_final_weight,
        sj.purity_weight AS sj_purity_weight,
        sj.pure_weight AS sj_pure_weight,
        sj.group_name AS sj_group_name";

$agg_subquery = "
    SELECT s.barcode, s.branch_id,
        SUM(COALESCE(s.current_qty,0)) AS bal_qty,
        SUM(COALESCE(s.current_weight,0)) AS bal_wt,
        MAX(s.id) AS pick_id
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sold_bc_join_frag
    WHERE $inner_sql
    GROUP BY s.barcode, s.branch_id
    $having_balance_frag
";

if ($has_stock_journal) {
    $sql = "
    SELECT $select_cols
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_sql
    $outer_sql
    ORDER BY s.barcode ASC, s.branch_id ASC
    LIMIT 2000
    ";
} else {
    $sql = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_qty,
        s.opening_purity,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.sku_code AS pc_rfid,
        pc.carat AS pc_carat,
        NULL AS sj_voucher_type,
        NULL AS sj_location,
        NULL AS sj_invoice_no,
        NULL AS sj_rfid,
        NULL AS sj_karat,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_final_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_group_name
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $outer_sql
    ORDER BY s.barcode ASC, s.branch_id ASC
    LIMIT 2000
    ";
}

$agg_subquery_simple = "
    SELECT s.barcode, s.branch_id,
        SUM(COALESCE(s.current_qty,0)) AS bal_qty,
        SUM(COALESCE(s.current_weight,0)) AS bal_wt,
        MAX(s.id) AS pick_id
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sold_bc_join_frag
    WHERE $inner_sql_simple
    GROUP BY s.barcode, s.branch_id
    $having_balance_frag
";

$sql_simple = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_qty,
        s.opening_purity,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.sku_code AS pc_rfid,
        pc.carat AS pc_carat,
        NULL AS sj_voucher_type,
        NULL AS sj_location,
        NULL AS sj_invoice_no,
        NULL AS sj_rfid,
        NULL AS sj_karat,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_final_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_group_name
    FROM ($agg_subquery_simple) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $outer_sql_simple
    ORDER BY s.barcode ASC, s.branch_id ASC
    LIMIT 2000
";

$sql_minimal = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_qty,
        s.opening_purity,
        IFNULL(b.name, '') AS branch_name,
        '' AS metal_name,
        IFNULL(p.name, '') AS product_name,
        IFNULL(p.article, '') AS article,
        p.id AS product_id,
        pc.sku_code AS pc_rfid,
        pc.carat AS pc_carat,
        NULL AS sj_voucher_type,
        NULL AS sj_location,
        NULL AS sj_invoice_no,
        NULL AS sj_rfid,
        NULL AS sj_karat,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_final_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_group_name
    FROM ($agg_subquery_simple) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $outer_sql_simple
    ORDER BY s.barcode ASC, s.branch_id ASC
    LIMIT 2000
";

$list = [];
$res = mysqli_query($conn, $sql);
if (!$res) {
    error_log('rfid-available-stock primary: ' . mysqli_error($conn));
    $res = mysqli_query($conn, $sql_simple);
}
if (!$res) {
    error_log('rfid-available-stock simple: ' . mysqli_error($conn));
    $res = mysqli_query($conn, $sql_minimal);
}
if (!$res) {
    error_log('rfid-available-stock minimal: ' . mysqli_error($conn));
    rfid_avail_json_out([
        'success' => false,
        'message' => 'Could not load stock. Check server logs.',
        'rows' => [],
        'totals' => ['qty' => 0, 'final_wt' => 0],
    ]);
}
while ($row = mysqli_fetch_assoc($res)) {
    $list[] = $row;
}

$tot_qty = 0.0;
$tot_final = 0.0;
$out_rows = [];

foreach ($list as $r) {
    $qty = (float) ($r['current_qty'] ?? 0);
    $fw = (float) ($r['current_weight'] ?? 0);
    if ($fw <= 0) {
        $fw = (float) ($r['final_weight'] ?? 0);
    }
    if ($fw <= 0) {
        $fw = (float) ($r['sj_final_weight'] ?? 0);
    }
    if ($fw <= 0) {
        $fw = (float) ($r['opening_weight'] ?? 0);
    }
    // Stock Journal merge zeroes current_* on tbl_stock; opening_qty / opening_weight remain (save-stock-journal.php).
    if ($qty <= 0) {
        $oq = (float) ($r['opening_qty'] ?? 0);
        if ($oq > 0) {
            $qty = $oq;
        } elseif ($fw > 0) {
            $qty = 1.0;
        }
    }
    $tot_qty += $qty;
    $tot_final += $fw;

    $gross = $r['sj_gross_weight'];
    if ($gross === null || $gross === '') {
        $gross = $r['opening_weight'];
    }
    $net = $r['sj_net_weight'];
    $purity_wt = $r['sj_purity_weight'];
    if ($purity_wt === null || $purity_wt === '') {
        $purity_wt = $r['sj_pure_weight'];
    }
    $rfid_disp = $r['pc_rfid'] ?? '';
    if ($rfid_disp === null || $rfid_disp === '') {
        $rfid_disp = $r['sj_rfid'] ?? '';
    }
    $carat_disp = $r['pc_carat'] ?? '';
    if ($carat_disp === null || $carat_disp === '') {
        $carat_disp = $r['sj_karat'] ?? '';
    }

    $out_rows[] = [
        'stock_id' => (int) ($r['stock_id'] ?? 0),
        'isScanned' => 'No',
        'branch' => (string) ($r['branch_name'] ?? ''),
        'carat' => $carat_disp !== null && $carat_disp !== '' ? (string) $carat_disp : '',
        'action' => '',
        'metal' => (string) ($r['metal_name'] ?? ''),
        'product_code' => (string) ($r['product_id'] ?? ''),
        'product_name' => (string) ($r['product_name'] ?? ''),
        'article' => (string) ($r['article'] ?? ''),
        'rfid_code' => (string) $rfid_disp,
        'barcode' => (string) ($r['barcode'] ?? ''),
        'qty' => $qty,
        'balance_wt' => $fw,
        'location' => (string) ($r['sj_location'] ?? ''),
        'gross_wt' => $gross,
        'purity_wt' => $purity_wt,
        'net_wt' => $net,
        'final_wt' => $fw,
        'voucher_type' => (string) ($r['sj_voucher_type'] ?? ''),
        'invoice_no' => (string) ($r['sj_invoice_no'] ?? ''),
    ];
}

rfid_avail_json_out([
    'success' => true,
    'rows' => $out_rows,
    'totals' => [
        'qty' => $tot_qty,
        'final_wt' => $tot_final,
    ],
]);

} catch (Throwable $e) {
    error_log('rfid-available-stock fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    rfid_avail_json_out([
        'success' => false,
        'message' => 'Server error while loading stock.',
        'rows' => [],
        'totals' => ['qty' => 0, 'final_wt' => 0],
    ]);
}
