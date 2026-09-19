<?php
/**
 * Resolve a job work order line by tag / barcode (tbl_jobwork_order_items).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

$tag = isset($_GET['tag']) ? trim((string) $_GET['tag']) : '';
if ($tag === '' && isset($_POST['tag'])) {
    $tag = trim((string) $_POST['tag']);
}
if ($tag === '') {
    echo json_encode(['ok' => false, 'message' => 'Enter a tag number.']);
    exit;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
if (!$chk || mysqli_num_rows($chk) < 1) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work items table not found.']);
    exit;
}
mysqli_free_result($chk);

$jwo_item_cols = [];
$colq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
if ($colq) {
    while ($cr = mysqli_fetch_assoc($colq)) {
        $jwo_item_cols[$cr['Field']] = true;
    }
    mysqli_free_result($colq);
}

$item_barcode_expr = "''";
if (!empty($jwo_item_cols['barcode_no']) && !empty($jwo_item_cols['barcode'])) {
    $item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), NULLIF(TRIM(ji.barcode),''), '')";
} elseif (!empty($jwo_item_cols['barcode_no'])) {
    $item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), '')";
} elseif (!empty($jwo_item_cols['barcode'])) {
    $item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode),''), '')";
}

$tag_esc = mysqli_real_escape_string($conn, $tag);

$jwo_cols = [];
$jcol = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_orders');
if ($jcol) {
    while ($cr = mysqli_fetch_assoc($jcol)) {
        $jwo_cols[$cr['Field']] = true;
    }
    mysqli_free_result($jcol);
}

$mfg_sel = !empty($jwo_cols['manufacturing_time_seconds']) ? 'j.manufacturing_time_seconds' : '0';

$soi_has_images = false;
$soi_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_items LIKE 'images'");
if ($soi_chk && mysqli_num_rows($soi_chk) > 0) {
    $soi_has_images = true;
}
if ($soi_chk) {
    mysqli_free_result($soi_chk);
}

$extra = '';
if (!empty($jwo_item_cols['product_id'])) {
    $extra .= ', ji.product_id AS line_product_id';
} else {
    $extra .= ', NULL AS line_product_id';
}

$soi_sel = '';
if ($soi_has_images) {
    $soi_sel = ", (SELECT soi.images FROM tbl_sale_order_items soi WHERE soi.order_id = j.sale_order_id AND (
        (ji.product_id IS NOT NULL AND ji.product_id > 0 AND soi.product_id = ji.product_id)
        OR (
            (ji.product_id IS NULL OR ji.product_id = 0)
            AND LENGTH(TRIM(IFNULL(soi.barcode,''))) > 0
            AND TRIM(IFNULL(soi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$item_barcode_expr},'')) COLLATE utf8mb4_unicode_ci
        )
    ) ORDER BY soi.id ASC LIMIT 1) AS sale_item_images";
}

$so_no_expr = 'j.sale_order_no';
if (empty($jwo_cols['sale_order_no']) && !empty($jwo_cols['sale_order_id'])) {
    $so_no_expr = "(SELECT so.order_no FROM tbl_sale_orders so WHERE so.id = j.sale_order_id LIMIT 1)";
} elseif (empty($jwo_cols['sale_order_no'])) {
    $so_no_expr = "''";
}

$where_parts = [];
if (!empty($jwo_item_cols['barcode'])) {
    $where_parts[] = "(TRIM(IFNULL(ji.barcode,'')) <> '' AND TRIM(IFNULL(ji.barcode,'')) COLLATE utf8mb4_unicode_ci = '{$tag_esc}' COLLATE utf8mb4_unicode_ci)";
}
if (!empty($jwo_item_cols['barcode_no'])) {
    $where_parts[] = "(TRIM(IFNULL(ji.barcode_no,'')) <> '' AND TRIM(IFNULL(ji.barcode_no,'')) COLLATE utf8mb4_unicode_ci = '{$tag_esc}' COLLATE utf8mb4_unicode_ci)";
}
if (empty($where_parts)) {
    echo json_encode(['ok' => false, 'message' => 'No barcode column on job work items.']);
    exit;
}
$where_sql = implode(' OR ', $where_parts);

$sql = "SELECT j.id AS jobwork_order_id,
 TRIM(COALESCE(j.customer_name,'')) AS customer_name,
    j.order_date,
    j.due_date,
    TRIM(COALESCE(j.jobwork_no,'')) AS jobwork_no,
    TRIM(COALESCE({$so_no_expr},'')) AS sale_order_no,
    {$mfg_sel} AS manufacturing_time_seconds,
    TRIM(COALESCE(ji.product_name,'')) AS product_name,
    TRIM(COALESCE({$item_barcode_expr},'')) AS tag_no
    {$extra}
    {$soi_sel}
    FROM tbl_jobwork_order_items ji
    INNER JOIN tbl_jobwork_orders j ON j.id = ji.jobwork_order_id
    WHERE ({$where_sql})
    ORDER BY ji.id ASC
    LIMIT 1";

$res = @mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode(['ok' => false, 'message' => 'Lookup failed.']);
    exit;
}
$row = mysqli_fetch_assoc($res);
mysqli_free_result($res);

if (!$row || (int) ($row['jobwork_order_id'] ?? 0) < 1) {
    echo json_encode(['ok' => false, 'message' => 'No job work order found for this tag.']);
    exit;
}

$jid = (int) $row['jobwork_order_id'];
$jw_no = trim((string) ($row['jobwork_no'] ?? ''));
$so_trim = trim((string) ($row['sale_order_no'] ?? ''));
$jw_no_disp = strtoupper(preg_replace('/\s+/', '', $jw_no));
$so_disp = strtoupper(preg_replace('/\s+/', '', $so_trim));

$od = $row['order_date'] ?? '';
$dd = $row['due_date'] ?? '';
$od_iso = ($od && strtotime((string) $od)) ? date('Y-m-d', strtotime((string) $od)) : '';
$dd_iso = ($dd && strtotime((string) $dd)) ? date('Y-m-d', strtotime((string) $dd)) : '';

$mfg = (int) ($row['manufacturing_time_seconds'] ?? 0);
if ($mfg < 0) {
    $mfg = 0;
}

$tag_no = trim((string) ($row['tag_no'] ?? ''));
if ($tag_no === '') {
    $tag_no = $tag;
}

$line_desc = trim((string) ($row['product_name'] ?? ''));

$image_url = '';
$img_raw = isset($row['sale_item_images']) ? trim((string) $row['sale_item_images']) : '';
if ($img_raw !== '') {
    $dec = @json_decode($img_raw, true);
    if ($dec && !empty($dec['primary'])) {
        $p = trim((string) $dec['primary']);
        if ($p !== '') {
            if (preg_match('#^https?://#i', $p)) {
                $image_url = $p;
            } else {
                $image_url = auragold_uploads_public_url($p);
            }
        }
    }
}

echo json_encode([
    'ok' => true,
    'jobwork_order_id'      => $jid,
    'customer_name'         => trim((string) ($row['customer_name'] ?? '')),
    'order_date_iso'        => $od_iso,
    'due_date_iso'          => $dd_iso,
    'jobwork_no_disp'       => $jw_no_disp,
    'sale_order_no_disp'    => $so_disp,
    'manufacturing_time_seconds' => $mfg,
    'line_description'      => $line_desc,
    'tag_no'                => $tag_no,
    'image_url'             => $image_url,
]);
