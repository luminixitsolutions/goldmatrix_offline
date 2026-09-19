<?php 
session_start();
require_once 'config.php';

if (isset($_GET['ledger']) && (string)$_GET['ledger'] !== '' && (string)$_GET['ledger'] !== '0') {
    require __DIR__ . '/stock-history-ledger.php';
    return;
}

/**
 * First stock-journal attachment image path for a PI line (item_id) and/or barcode.
 * Paths are stored relative to the admin app (e.g. uploads/stock_journal/...).
 */
function stock_history_journal_image_path($conn, $item_id, $barcode) {
    static $table_ok = null;
    if ($table_ok === null) {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal_images'");
        $table_ok = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
    if (!$table_ok) {
        return '';
    }
    $item_id = (int) $item_id;
    $barcode = trim((string) $barcode);
    $bc_esc = esc($barcode);
    $no_barcode = ($barcode === '' || $barcode === '—');
    if ($item_id > 0 && !$no_barcode) {
        $row = getRecord("SELECT image_path FROM tbl_stock_journal_images WHERE item_id = $item_id AND barcode_no = '$bc_esc' ORDER BY id ASC LIMIT 1");
        if (!empty($row['image_path'])) {
            return trim($row['image_path']);
        }
    }
    if ($item_id > 0) {
        $row = getRecord("SELECT image_path FROM tbl_stock_journal_images WHERE item_id = $item_id ORDER BY id ASC LIMIT 1");
        if (!empty($row['image_path'])) {
            return trim($row['image_path']);
        }
    }
    if (!$no_barcode) {
        $row = getRecord("SELECT image_path FROM tbl_stock_journal_images WHERE barcode_no = '$bc_esc' ORDER BY id ASC LIMIT 1");
        if (!empty($row['image_path'])) {
            return trim($row['image_path']);
        }
    }
    return '';
}

// Get stock ID from query parameter
$stock_id = isset($_GET['stock_id']) ? (int)$_GET['stock_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$characteristic_id = isset($_GET['characteristic_id']) ? (int)$_GET['characteristic_id'] : 0;

// Get stock details
$stock_info = null;
if ($stock_id > 0) {
    $stock_info = getRecord("
        SELECT s.*, p.name as product_name, m.display_name as metal_name, b.name as branch_name
        FROM tbl_stock s
        LEFT JOIN tbl_products p ON s.product_id = p.id
        LEFT JOIN tbl_metal m ON s.metal_id = m.id
        LEFT JOIN tbl_branches b ON s.branch_id = b.id
        WHERE s.id = $stock_id AND s.status = 1
    ");
    if ($stock_info) {
        $product_id = $stock_info['product_id'];
        $characteristic_id = $stock_info['product_characteristic_id'];
    }
}

$adv_branch = isset($_GET['adv_branch']) ? (int) $_GET['adv_branch'] : 0;
$adv_category = isset($_GET['adv_category']) ? (int) $_GET['adv_category'] : 0;
$adv_barcode = isset($_GET['adv_barcode']) ? trim((string) $_GET['adv_barcode']) : '';
$adv_rfid = isset($_GET['adv_rfid']) ? trim((string) $_GET['adv_rfid']) : '';

$filter_branches = function_exists('auragold_filter_branches_for_login_scope')
    ? auragold_filter_branches_for_login_scope()
    : getList("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
if (!is_array($filter_branches)) {
    $filter_branches = [];
}
$adv_branch = function_exists('auragold_sanitize_adv_branch_for_login_scope')
    ? auragold_sanitize_adv_branch_for_login_scope($adv_branch)
    : $adv_branch;

$adv_filter_count = ($adv_branch > 0 ? 1 : 0) + ($adv_category > 0 ? 1 : 0) + ($adv_barcode !== '' ? 1 : 0) + ($adv_rfid !== '' ? 1 : 0);

$stock_rfid_col = '';
$rfid_col_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('rfid','rfid_code')");
if ($rfid_col_chk) {
    while ($row = mysqli_fetch_assoc($rfid_col_chk)) {
        $f = $row['Field'] ?? '';
        if ($f === 'rfid') {
            $stock_rfid_col = 'rfid';
            break;
        }
        if ($f === 'rfid_code') {
            $stock_rfid_col = 'rfid_code';
            break;
        }
    }
    mysqli_free_result($rfid_col_chk);
}

$filter_categories = getList("SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
if (!is_array($filter_categories)) {
    $filter_categories = [];
}

$adv_where_append = '';
if ($adv_branch > 0) {
    $adv_where_append .= ' AND s.branch_id = ' . $adv_branch;
}
if ($adv_category > 0) {
    $adv_where_append .= ' AND EXISTS (SELECT 1 FROM tbl_products p_adv WHERE p_adv.id = s.product_id AND p_adv.status = 1 AND p_adv.category_id = ' . $adv_category . ')';
}
if ($adv_barcode !== '') {
    $bc_esc_like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $adv_barcode);
    $bc_like = '%' . esc($bc_esc_like) . '%';
    // Per-row match only: a product-wide EXISTS on sj/pii made *every* lot pass whenever any
    // line for that product had the searched barcode. Tie sj/pii to the same lot via
    // product_characteristic_id and the stock row’s transaction date; include pc and s.barcode.
    // pc_filt: only when this stock row has no lot barcode. Otherwise the same characteristic
    // can carry a template barcode (e.g. RNN00002) while another lot shows a piece barcode
    // (e.g. RNN00001) — matching pc would wrongly keep both rows for a "RNN00002" search.
    $adv_where_append .= " AND (
        (s.barcode IS NOT NULL AND TRIM(s.barcode) != '' AND s.barcode LIKE '$bc_like')
        OR (
            (s.barcode IS NULL OR TRIM(s.barcode) = '')
            AND EXISTS (
            SELECT 1 FROM tbl_product_characteristics pc_filt
            WHERE pc_filt.id = s.product_characteristic_id AND pc_filt.status = 1
            AND pc_filt.barcode IS NOT NULL AND TRIM(pc_filt.barcode) != '' AND pc_filt.barcode LIKE '$bc_like'
        ))
        OR EXISTS (
            SELECT 1 FROM tbl_stock_journal sj_advbc
            WHERE sj_advbc.product_id = s.product_id
            AND (sj_advbc.product_characteristic_id = s.product_characteristic_id
                OR (sj_advbc.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
            AND sj_advbc.status = 'active'
            AND DATE(sj_advbc.sj_date) = DATE(s.created_at)
            AND ABS(TIMESTAMPDIFF(SECOND, sj_advbc.created_at, s.created_at)) <= 5
            AND sj_advbc.barcode IS NOT NULL AND TRIM(sj_advbc.barcode) != '' AND sj_advbc.barcode LIKE '$bc_like'
        )
        OR EXISTS (
            SELECT 1 FROM tbl_purchase_invoice_items pii_advbc
            WHERE pii_advbc.product_id = s.product_id
            AND (pii_advbc.product_characteristic_id = s.product_characteristic_id
                OR (pii_advbc.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
            AND pii_advbc.status = 1
            AND DATE(pii_advbc.created_at) = DATE(s.created_at)
            AND ABS(TIMESTAMPDIFF(SECOND, pii_advbc.created_at, s.created_at)) <= 5
            AND pii_advbc.barcode IS NOT NULL AND TRIM(pii_advbc.barcode) != '' AND pii_advbc.barcode LIKE '$bc_like'
        )
    )
    AND NOT (
        (s.barcode IS NULL OR TRIM(s.barcode) = '')
        AND EXISTS (
            SELECT 1 FROM tbl_stock_journal sj_bcm
            WHERE sj_bcm.product_id = s.product_id
            AND (sj_bcm.product_characteristic_id = s.product_characteristic_id
                OR (sj_bcm.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
            AND sj_bcm.status = 'active'
            AND DATE(sj_bcm.sj_date) = DATE(s.created_at)
            AND ABS(TIMESTAMPDIFF(SECOND, sj_bcm.created_at, s.created_at)) <= 5
            AND sj_bcm.barcode IS NOT NULL AND TRIM(sj_bcm.barcode) != ''
            AND sj_bcm.barcode NOT LIKE '$bc_like'
        )
    )
    AND NOT (
        (s.barcode IS NULL OR TRIM(s.barcode) = '')
        AND EXISTS (
            SELECT 1 FROM tbl_purchase_invoice_items pii_bcm
            WHERE pii_bcm.product_id = s.product_id
            AND (pii_bcm.product_characteristic_id = s.product_characteristic_id
                OR (pii_bcm.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
            AND pii_bcm.status = 1
            AND DATE(pii_bcm.created_at) = DATE(s.created_at)
            AND ABS(TIMESTAMPDIFF(SECOND, pii_bcm.created_at, s.created_at)) <= 5
            AND pii_bcm.barcode IS NOT NULL AND TRIM(pii_bcm.barcode) != ''
            AND pii_bcm.barcode NOT LIKE '$bc_like'
        )
    )";
}
if ($adv_rfid !== '') {
    $rf_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $adv_rfid)) . '%';
    $rf_parts = ["EXISTS (SELECT 1 FROM tbl_stock_journal sj_advrf WHERE sj_advrf.product_id = s.product_id AND sj_advrf.status = 'active' AND sj_advrf.rfid_code IS NOT NULL AND TRIM(sj_advrf.rfid_code) != '' AND sj_advrf.rfid_code LIKE '$rf_like')"];
    if ($stock_rfid_col !== '') {
        $c = $stock_rfid_col;
        $rf_parts[] = "(s.$c IS NOT NULL AND TRIM(s.$c) != '' AND s.$c LIKE '$rf_like')";
    }
    $adv_where_append .= ' AND (' . implode(' OR ', $rf_parts) . ')';
}

// Pagination for Inward Stock
$inward_page = isset($_GET['inward_page']) ? (int)$_GET['inward_page'] : 1;
$inward_per_page = isset($_GET['inward_per_page']) ? (int)$_GET['inward_per_page'] : 10;
$inward_offset = ($inward_page - 1) * $inward_per_page;

// Pagination for Outward Stock
$outward_page = isset($_GET['outward_page']) ? (int)$_GET['outward_page'] : 1;
$outward_per_page = isset($_GET['outward_per_page']) ? (int)$_GET['outward_per_page'] : 10;
$outward_offset = ($outward_page - 1) * $outward_per_page;

$sh_q = [];
if ($stock_id > 0) {
    $sh_q['stock_id'] = $stock_id;
}
if ($product_id > 0) {
    $sh_q['product_id'] = $product_id;
}
if ($characteristic_id > 0) {
    $sh_q['characteristic_id'] = $characteristic_id;
}
if ($adv_branch > 0) {
    $sh_q['adv_branch'] = $adv_branch;
}
if ($adv_category > 0) {
    $sh_q['adv_category'] = $adv_category;
}
if ($adv_barcode !== '') {
    $sh_q['adv_barcode'] = $adv_barcode;
}
if ($adv_rfid !== '') {
    $sh_q['adv_rfid'] = $adv_rfid;
}
if ($inward_per_page != 10) {
    $sh_q['inward_per_page'] = $inward_per_page;
}
if ($outward_per_page != 10) {
    $sh_q['outward_per_page'] = $outward_per_page;
}
$stock_history_tab_href = 'stock-history.php' . (count($sh_q) ? '?' . http_build_query($sh_q) : '');

$sh_q_clear = [];
if ($stock_id > 0) {
    $sh_q_clear['stock_id'] = $stock_id;
}
if ($product_id > 0) {
    $sh_q_clear['product_id'] = $product_id;
}
if ($characteristic_id > 0) {
    $sh_q_clear['characteristic_id'] = $characteristic_id;
}
if ($inward_per_page != 10) {
    $sh_q_clear['inward_per_page'] = $inward_per_page;
}
if ($outward_per_page != 10) {
    $sh_q_clear['outward_per_page'] = $outward_per_page;
}
$stock_history_clear_href = 'stock-history.php' . (count($sh_q_clear) ? '?' . http_build_query($sh_q_clear) : '');

// Product label for panel headers (URL product_id / stock context)
// When characteristic_id is set (e.g. View History from Diamond & Stone Analysis), use that row's metal — not an arbitrary first characteristic (often Gold).
$product_info = null;
if ($product_id > 0) {
    if ($characteristic_id > 0) {
        $cid = (int) $characteristic_id;
        $product_info = getRecord("
            SELECT p.*, m.display_name as metal_name
            FROM tbl_products p
            INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.id = $cid AND pc.status = 1
            LEFT JOIN tbl_metal m ON pc.metal_id = m.id
            WHERE p.id = $product_id AND p.status = 1
            LIMIT 1
        ");
    }
    if (!$product_info) {
        $product_info = getRecord("
            SELECT p.*, m.display_name as metal_name
            FROM tbl_products p
            LEFT JOIN tbl_product_characteristics pc ON p.id = pc.product_id AND pc.status = 1
            LEFT JOIN tbl_metal m ON pc.metal_id = m.id
            WHERE p.id = $product_id AND p.status = 1
            ORDER BY pc.id ASC
            LIMIT 1
        ");
    }
}

$selected_product_label = 'All Products';
if ($product_id > 0) {
    if ($product_info) {
        $selected_product_label = $product_info['name'] . (!empty($product_info['metal_name']) ? ' (' . $product_info['metal_name'] . ')' : '');
    } else {
        $selected_product_label = 'Product #' . $product_id;
    }
}

// Inward Stock Query (stock coming in - opening, purchases, journal/balance lots, sale returns — align with analysis aggregates)
$inward_where = "s.status = 1 AND s.stock_type IN ('opening', 'purchase', 'stock_journal', 'balance', 'sale_return', 'inward')";
if ($product_id > 0) {
    $inward_where .= " AND s.product_id = $product_id";
}
if ($characteristic_id > 0) {
    $inward_where .= " AND s.product_characteristic_id = $characteristic_id";
}
$inward_where .= $adv_where_append;
if (!isset($stock_history_metal_scope_sql)) {
    $stock_history_metal_scope_sql = '';
}
$inward_where .= $stock_history_metal_scope_sql;

require_once __DIR__ . '/includes/stock_history_queries.php';

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Stock History - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>

<style>
html, body{
    overflow-x: hidden !important;
    overflow-y: hidden !important;
    height: 100vh;
}

.layout-content {
    height: calc(100vh - 60px);
    overflow: hidden;
}

.container-fluid {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Stock history theme: navy #11294b + gold accents */
/* Tabs row + toolbar (filter / expand / export on same line as tabs) */
.tabs-container {
    background: #fff;
    border-bottom: 1px solid #cfd8e3;
    padding: 0 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-end;
    gap: 8px 16px;
}

.tabs-toolbar-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 8px 0 10px 0;
}

.tabs-toolbar-actions .btn-icon {
    background: #fdf8f0;
    border: 1px solid #d4c4a8;
    color: #11294b;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
    padding: 0;
}

.tabs-toolbar-actions .btn-icon:hover {
    background: #f5ecd8;
    border-color: #c9a962;
    color: #0a1f38;
}

.tabs-toolbar-actions .dropdown .btn-icon {
    margin: 0;
}

.stock-history-filter-btn {
    position: relative;
}

.stock-history-filter-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
    color: #11294b;
    background: #c9a962;
    border-radius: 999px;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(17, 41, 75, 0.2);
}

/* Advance Filter modal — navy + gold */
.stock-history-adv-modal .modal-content {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2);
}

.stock-history-adv-modal .modal-header {
    background: linear-gradient(180deg, #fdf8f0 0%, #f0e6d4 100%);
    border-bottom: 2px solid #c9a962;
    padding: 14px 20px;
}

.stock-history-adv-modal .modal-title {
    width: 100%;
    text-align: center;
    font-weight: 700;
    font-size: 1rem;
    color: #11294b;
    margin: 0;
}

.stock-history-adv-modal .close {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.6;
    color: #11294b;
    font-size: 1.5rem;
    font-weight: 400;
}

.stock-history-adv-modal .close:hover {
    opacity: 1;
}

.stock-history-adv-modal .modal-body {
    padding: 20px 24px 24px;
}

.stock-history-adv-modal .form-group label {
    font-weight: 600;
    font-size: 0.8rem;
    color: #11294b;
    margin-bottom: 6px;
}

.stock-history-adv-modal .form-control {
    border-radius: 8px;
    border-color: #cfd8e3;
    font-size: 0.875rem;
}

.stock-history-adv-modal .form-control:focus {
    border-color: #c9a962;
    box-shadow: 0 0 0 0.15rem rgba(201, 169, 98, 0.25);
}

.stock-history-adv-modal .modal-footer-adv {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 0 24px 24px;
    border: none;
}

.stock-history-adv-modal .btn-adv-apply {
    background: #fff;
    color: #11294b;
    border: 2px solid #c9a962;
    font-weight: 600;
    padding: 8px 28px;
    border-radius: 8px;
}

.stock-history-adv-modal .btn-adv-apply:hover {
    background: #fdf8f0;
    color: #0a1f38;
}

.stock-history-adv-modal .btn-adv-clear {
    background: #fff;
    color: #b45309;
    border: 2px solid #f5c2a7;
    font-weight: 600;
    padding: 8px 28px;
    border-radius: 8px;
}

.stock-history-adv-modal .btn-adv-clear:hover {
    background: #fff7ed;
    color: #9a3412;
}

.tabs-list {
    display: flex;
    flex-wrap: wrap;
    flex: 1;
    min-width: 0;
    gap: 4px;
    margin: 0;
    padding: 8px 0 0 0;
    list-style: none;
    align-items: flex-end;
}

.tabs-list li {
    margin: 0;
}

.tab-link {
    display: block;
    padding: 10px 20px;
    color: #64748b;
    text-decoration: none;
    border-radius: 8px 8px 0 0;
    font-weight: 500;
    font-size: 0.875rem;
    transition: background 0.2s, color 0.2s;
    cursor: pointer;
}

.tab-link:hover {
    color: #11294b;
    background: #fdf8f0;
}

.tab-link.active {
    color: #fff;
    background: #11294b;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.28);
    border-bottom: 3px solid #c9a962;
    margin-bottom: -1px;
}

/* Stock History Wrapper */
.stock-history-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 20px;
    height: calc(100vh - 200px);
    overflow: hidden;
}

.stock-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100%;
}

.panel-header {
    padding: 12px 18px;
    border-bottom: 1px solid #cfd8e3;
    background: linear-gradient(180deg, #fdfcf8 0%, #f0f4f8 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.panel-header-main {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    min-width: 0;
}

.panel-title {
    font-weight: 700;
    font-size: 0.8rem;
    color: #11294b;
    letter-spacing: 0.02em;
}

.panel-product-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: #11294b;
    padding: 4px 12px;
    background: #fffdf8;
    border: 1px solid #c9a962;
    border-radius: 999px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.panel-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.gear-icon {
    color: #64748b;
    cursor: pointer;
    font-size: 12px;
    transition: color 0.2s;
}

.gear-icon:hover {
    color: #11294b;
}

/* Table Container */
.panel-table-container {
    flex: 1;
    overflow: auto;
    padding: 0;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.table.history-table thead {
    background: #11294b;
}

.history-table thead {
    background: #11294b;
    position: sticky;
    top: 0;
    z-index: 10;
}

.history-table th {
    padding: 10px 8px;
    text-align: left;
    font-weight: 600;
    color: #fff;
    border-bottom: none;
    white-space: nowrap;
    font-size: 0.75rem;
}

.history-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.history-table th.sortable:hover {
    background: rgba(255, 255, 255, 0.12);
}

.history-table th .sort-arrows {
    display: inline-flex;
    flex-direction: column;
    margin-left: 4px;
    vertical-align: middle;
    font-size: 0.7rem;
    opacity: 0.5;
}

.history-table th.sortable:hover .sort-arrows {
    opacity: 1;
}

.stock-history-drag-handle {
    display: inline-block;
    margin-right: 4px;
    cursor: grab;
    opacity: 0.5;
    vertical-align: middle;
    line-height: 1;
}
.stock-history-drag-handle:hover {
    opacity: 0.9;
}
.history-table thead th.sortable-ghost {
    opacity: 0.55;
}

.history-table tbody tr {
    border-bottom: 1px solid #e8e4dc;
    transition: background 0.2s;
}

.history-table tbody tr:nth-child(even) {
    background: #faf8f5;
}

.history-table tbody tr:hover {
    background: #f5f0e6;
}

.history-table td {
    padding: 12px 10px;
    color: #000;
    vertical-align: middle;
}

.history-table .image-placeholder {
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.75rem;
}

.history-table .stock-history-img-cell {
    width: 52px;
    max-width: 52px;
    padding: 8px 6px !important;
    vertical-align: middle;
}

.history-table .stock-history-img-link {
    display: block;
    line-height: 0;
    border-radius: 4px;
    overflow: hidden;
}

.history-table .stock-history-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    display: block;
    background: #f8fafc;
}

.history-table .stock-history-img-link:hover .stock-history-thumb {
    border-color: #11294b;
    box-shadow: 0 0 0 1px rgba(17, 41, 75, 0.15);
}

.history-table .stock-history-img-cell .image-placeholder {
    margin-left: auto;
    margin-right: auto;
}

.history-table .view-btn {
    background: #fff;
    color: #11294b;
    border: 1px solid #c9a962;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.history-table .view-btn:hover {
    background: #fdf8f0;
    border-color: #11294b;
    color: #0a1f38;
}

.history-table th.col-accent,
.history-table td.col-accent {
    background: #fdf6e8 !important;
    color: #11294b;
    font-weight: 600;
}

.history-table thead th.col-accent {
    background: #1a4060 !important;
    color: #fff;
}

.history-table .history-invoice-link {
    color: #11294b;
    font-weight: 600;
    text-decoration: none;
}

.history-table .history-invoice-link:hover {
    color: #c9a962;
    text-decoration: underline;
}

.history-table tfoot tr {
    background: #f5f0e6 !important;
    border-top: 2px solid #c9a962;
}

.history-table tfoot td {
    font-weight: 600;
    color: #11294b;
}

/* Panel Footer */
.panel-footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.panel-footer-info {
    color: #64748b;
    font-size: 0.875rem;
}

.panel-footer-total {
    font-weight: 700;
    color: #fff;
    font-size: 0.875rem;
    padding: 8px 16px;
    border-radius: 8px;
    background: linear-gradient(180deg, #163a5c 0%, #11294b 100%);
    border: 1px solid #c9a962;
    box-shadow: 0 2px 6px rgba(17, 41, 75, 0.28);
}

.pagination-controls {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination-controls .page-btn {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.pagination-controls .page-btn:hover:not(:disabled) {
    background: #fdf8f0;
    border-color: #c9a962;
    color: #11294b;
}

.pagination-controls .page-btn.active {
    background: #11294b;
    border-color: #11294b;
    color: #fff;
}

.pagination-controls .page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-controls .show-all-dropdown {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.875rem;
    color: #64748b;
    background: #fff;
}

/* Column Settings Dropdown */
.columns-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    min-width: 250px;
    max-width: 300px;
    display: none;
    margin-top: 8px;
}

.columns-dropdown.show {
    display: block;
}

.columns-dropdown-header {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 600;
    font-size: 0.85rem;
    color: #000;
}

.columns-dropdown-search {
    padding: 8px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.columns-dropdown-search input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.8rem;
}

.columns-dropdown-list {
    max-height: 300px;
    overflow-y: auto;
    padding: 8px 0;
}

.columns-dropdown-item {
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: background 0.2s;
}

.columns-dropdown-item:hover {
    background: #f8fafc;
}

.columns-dropdown-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.columns-dropdown-item label {
    margin: 0;
    cursor: pointer;
    font-size: 0.8rem;
    color: #000;
    flex: 1;
}
</style>

<body>
<!-- [ Preloader ] Start -->
<div class="page-loader">
    <div class="bg-primary"></div>
</div>
<!-- [ Preloader ] End -->

<!-- [ Layout wrapper ] Start -->
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <!-- [ Layout sidenav ] Start -->
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <div class="app-brand demo">
                <span class="app-brand-logo demo">
                    <img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid">
                </span>
                <a href="index-2.html" class="app-brand-text demo sidenav-text font-weight-normal ml-2">Empire</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto">
                    <i class="ion ion-md-menu align-middle"></i>
                </a>
            </div>
            <div class="sidenav-divider mt-0"></div>
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item active">
                    <a href="billing-sales-invoice.html" class="sidenav-link">
                        <i class="sidenav-icon feather icon-file-text"></i>
                        <div>Sales Invoice</div>
                    </a>
                </li>
            </ul>
        </div>
        <!-- [ Layout sidenav ] End -->

        <!-- [ Layout container ] Start -->
        <div class="layout-container">
            <!-- [ Layout navbar ( Header ) ] Start -->
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index-2.html" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo">
                        <img src="assets/img/logo-dark.png" alt="Brand Logo" class="img-fluid">
                    </span>
                    <span class="app-brand-text demo font-weight-normal ml-2">Empire</span>
                </a>

                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:">
                        <i class="ion ion-md-menu text-large align-middle"></i>
                    </a>
                </div>

                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#layout-navbar-collapse">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="navbar-collapse collapse" id="layout-navbar-collapse">
                    <div class="navbar-nav align-items-lg-center ml-auto">
                        <div class="demo-navbar-notifications nav-item dropdown mr-lg-3">
                            <a class="nav-link dropdown-toggle hide-arrow" href="#" data-toggle="dropdown">
                                <i class="feather icon-bell navbar-icon align-middle"></i>
                                <span class="badge badge-danger badge-dot indicator"></span>
                            </a>
                        </div>
                        <div class="demo-navbar-user nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                                <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                                    <img src="assets/img/avatars/1.png" alt class="d-block ui-w-30 rounded-circle">
                                    <span class="px-1 mr-lg-2 ml-2 ml-lg-0">SUPER ADMIN</span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            <!-- [ Layout navbar ( Header ) ] End -->

            <!-- [ Layout content ] Start -->
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php';?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card mb-4" style="height: calc(100vh - 120px); display: flex; flex-direction: column; overflow: hidden;">
                                <div class="card-body" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">

                                    <div class="tabs-container">
                                        <ul class="tabs-list">
                                            <li>
                                                <a href="gold-silver-analysis.php?tab=current-stock" class="tab-link">Current Stock</a>
                                            </li>
                                            <li>
                                                <a href="<?= htmlspecialchars($stock_history_tab_href, ENT_QUOTES, 'UTF-8') ?>" class="tab-link active">Stock Availability (Wt)</a>
                                            </li>
                                            <li>
                                                <a href="gold-silver-analysis.php?tab=stock-details" class="tab-link">Stock Details</a>
                                            </li>
                                        </ul>
                                        <div class="tabs-toolbar-actions">
                                            <button type="button" class="btn-icon stock-history-filter-btn" title="Advance Filter" data-toggle="modal" data-target="#stockHistoryAdvFilterModal">
                                                <i class="feather icon-filter"></i>
                                                <?php if ($adv_filter_count > 0): ?>
                                                <span class="stock-history-filter-badge"><?= (int) $adv_filter_count ?></span>
                                                <?php endif; ?>
                                            </button>
                                            <button type="button" class="btn-icon" title="Expand/Collapse"><i class="feather icon-maximize-2"></i></button>
                                            <div class="dropdown">
                                                <button type="button" class="btn-icon" title="Export" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="feather icon-download"></i></button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#">Export to Excel</a>
                                                    <a class="dropdown-item" href="#">Export to PDF</a>
                                                    <a class="dropdown-item" href="#">Export to CSV</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stock History Panels -->
                                    <div class="stock-history-wrapper">
                                        
                                        <!-- Inward Stock Panel -->
                                        <div class="stock-panel">
                                            <div class="panel-header">
                                                <div class="panel-header-main">
                                                    <span class="panel-title">Inward Stock</span>
                                                    <span class="panel-product-name" title="<?= htmlspecialchars($selected_product_label) ?>"><?= htmlspecialchars($selected_product_label) ?></span>
                                                </div>
                                                <div class="panel-header-actions" style="position: relative;">
                                                    <i class="feather icon-settings gear-icon" id="inwardColumnSettingsBtn" title="Column Settings"></i>
                                                    <div class="columns-dropdown" id="inwardColumnsDropdown">
                                                        <div class="columns-dropdown-header">Columns</div>
                                                        <div class="columns-dropdown-search">
                                                            <input type="text" id="inwardColumnSearch" placeholder="Search columns...">
                                                        </div>
                                                        <div class="columns-dropdown-list" id="inwardColumnsList">
                                                            <!-- Will be populated by JavaScript -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="panel-table-container">
                                                <table class="table history-table" id="inwardTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="width: 52px; min-width: 52px;" data-col="image" title="Attached photo">Photo</th>
                                                            <th class="sortable" data-col="view" style="min-width: 72px; text-align: center;">View</th>
                                                            <th data-col="category" style="min-width: 90px;">Category</th>
                                                            <th class="sortable" data-col="date" style="min-width: 96px;">
                                                                Date
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="barcode" style="min-width: 110px;">
                                                                Barcode No
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="type_of_voucher" style="min-width: 120px;">
                                                                Voucher Type
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="against_invoice" style="min-width: 100px;">
                                                                Invoice
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable col-accent" data-col="gross_wt" style="min-width: 88px;">
                                                                Gross Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable col-accent" data-col="pure_wt" style="min-width: 88px;">
                                                                Pure Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="net_amt" style="min-width: 100px; display: none;">
                                                                Net Amt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="rfid" style="min-width: 100px; display: none;">
                                                                RFID
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="location" style="min-width: 100px; display: none;">
                                                                Location
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="voucher_type" style="min-width: 120px; display: none;">
                                                                Voucher Type (alt)
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="invoice" style="min-width: 100px; display: none;">
                                                                Invoice No.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="branch" style="min-width: 120px; display: none;">
                                                                Branch
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="qty" style="min-width: 80px; display: none;">
                                                                Qty.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="purity" style="min-width: 80px; display: none;">
                                                                Pu
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="requested_qty" style="min-width: 100px; display: none;">
                                                                Requested...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="requested_wt" style="min-width: 100px; display: none;">
                                                                Requested...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="stone_wt" style="min-width: 100px; display: none;">
                                                                Stone ...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="diamond_wt" style="min-width: 100px; display: none;">
                                                                Diamon...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="less_wt" style="min-width: 100px; display: none;">
                                                                Less Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="purity_wt" style="min-width: 100px; display: none;">
                                                                Purity Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="wastage_per" style="min-width: 100px; display: none;">
                                                                Wastage Per.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="wastage_wt" style="min-width: 100px; display: none;">
                                                                Wastage Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="net_wt" style="min-width: 100px; display: none;">
                                                                Net Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="alloy_wt" style="min-width: 100px; display: none;">
                                                                Alloy Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="final_wt" style="min-width: 100px; display: none;">
                                                                Final Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="standard_wt" style="min-width: 100px; display: none;">
                                                                Standar...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="actual_wt" style="min-width: 100px; display: none;">
                                                                Actual...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="national_wt" style="min-width: 100px; display: none;">
                                                                Nation...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="name" style="min-width: 100px; display: none;">
                                                                n...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="making_rate" style="min-width: 100px; display: none;">
                                                                Making Rate
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="amt" style="min-width: 100px; display: none;">
                                                                Amt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="making_amt" style="min-width: 100px; display: none;">
                                                                Makin...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="amount" style="min-width: 100px; display: none;">
                                                                Amount
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="hui_code" style="min-width: 100px; display: none;">
                                                                HUI...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="packet_wt" style="min-width: 100px; display: none;">
                                                                Packet Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="packet_length" style="min-width: 100px; display: none;">
                                                                Packet L...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="rate" style="min-width: 100px; display: none;">
                                                                Rate
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="hallmark1" style="min-width: 100px; display: none;">
                                                                Hallmar...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="hallmark2" style="min-width: 100px; display: none;">
                                                                Hallmar...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="net_amt_with_tax" style="min-width: 120px; display: none;">
                                                                Net Amt W...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="tax_amt" style="min-width: 100px; display: none;">
                                                                Tax Amt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="discount_per" style="min-width: 100px; display: none;">
                                                                Discount Per
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="discount_amt" style="min-width: 100px; display: none;">
                                                                Discount A...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="metal_value" style="min-width: 100px; display: none;">
                                                                Metal Val...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="purchase" style="min-width: 100px; display: none;">
                                                                Purchase
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        // Track used barcodes to ensure uniqueness
                                                        $used_barcodes = [];

                                                        if (!empty($inward_data)) {
                                                            foreach($inward_data as $row) {
                                                                // Ensure barcode is always displayed
                                                                $date = $row['transaction_date'] ? date('d-m-Y', strtotime($row['transaction_date'])) : '';
                                                                $voucher_type = ucfirst(str_replace('_', ' ', $row['type_of_voucher'] ?: 'Opening Stock'));
                                                                // Get single barcode for this stock entry
                                                                $barcode_value = '';
                                                                if (!empty($row['barcode']) && trim($row['barcode']) !== '') {
                                                                    // If multiple barcodes are comma-separated, take only the first one
                                                                    $barcodes = explode(',', trim($row['barcode']));
                                                                    $barcode_value = trim($barcodes[0]);
                                                                    // Only use if not already shown for another row (avoid same characteristic barcode on every row)
                                                                    if (in_array($barcode_value, $used_barcodes)) {
                                                                        $barcode_value = '';
                                                                    } else {
                                                                        $used_barcodes[] = $barcode_value;
                                                                    }
                                                                }
                                                                if (empty($barcode_value)) {
                                                                    // Fallback: first try to get from tbl_product_characteristics by id (highest priority)
                                                                    $product_characteristic_id = isset($row['product_characteristic_id']) ? (int)$row['product_characteristic_id'] : 0;
                                                                    if ($product_characteristic_id > 0) {
                                                                        $pc_barcode_query = "SELECT barcode FROM tbl_product_characteristics WHERE id = $product_characteristic_id AND barcode IS NOT NULL AND barcode != '' AND status = 1 LIMIT 1";
                                                                        $pc_barcode_result = getRecord($pc_barcode_query);
                                                                        if ($pc_barcode_result && !empty($pc_barcode_result['barcode'])) {
                                                                            $char_barcode = trim($pc_barcode_result['barcode']);
                                                                            if (!in_array($char_barcode, $used_barcodes)) {
                                                                                $barcode_value = $char_barcode;
                                                                                $used_barcodes[] = $char_barcode;
                                                                            }
                                                                        }
                                                                    }
                                                                    // Fallback: get from tbl_product_characteristics by product_id + metal_id (when stock has no product_characteristic_id)
                                                                    if (empty($barcode_value) && isset($row['product_id']) && isset($row['metal_id'])) {
                                                                        $pid = (int)$row['product_id'];
                                                                        $mid = (int)$row['metal_id'];
                                                                        $bid = isset($row['branch_id']) ? (int)$row['branch_id'] : 1;
                                                                        if ($pid > 0 && $mid > 0) {
                                                                            $pc_barcode_query = "SELECT barcode FROM tbl_product_characteristics WHERE product_id = $pid AND metal_id = $mid AND status = 1 AND barcode IS NOT NULL AND barcode != '' ORDER BY (branch_id = $bid) DESC, id ASC LIMIT 1";
                                                                            $pc_barcode_result = getRecord($pc_barcode_query);
                                                                            if ($pc_barcode_result && !empty($pc_barcode_result['barcode'])) {
                                                                                $char_barcode = trim($pc_barcode_result['barcode']);
                                                                                if (!in_array($char_barcode, $used_barcodes)) {
                                                                                    $barcode_value = $char_barcode;
                                                                                    $used_barcodes[] = $char_barcode;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                    
                                                                    // Fallback: try to get from tbl_stock directly
                                                                    if (empty($barcode_value)) {
                                                                        $stock_id = isset($row['id']) ? (int)$row['id'] : 0;
                                                                        if ($stock_id > 0) {
                                                                            $stock_barcode_query = "SELECT barcode FROM tbl_stock WHERE id = $stock_id AND barcode IS NOT NULL AND barcode != '' LIMIT 1";
                                                                            $stock_barcode_result = getRecord($stock_barcode_query);
                                                                            if ($stock_barcode_result && !empty($stock_barcode_result['barcode'])) {
                                                                                $barcode_value = trim($stock_barcode_result['barcode']);
                                                                                if (!in_array($barcode_value, $used_barcodes)) {
                                                                                    $used_barcodes[] = $barcode_value;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                    
                                                                    // If still no barcode, try to get from stock_journal directly matching by weight and amount
                                                                    if (empty($barcode_value) && $stock_id > 0 && isset($row['product_id'])) {
                                                                        $gross_weight = isset($row['gross_wt']) ? (float)$row['gross_wt'] : 0;
                                                                        $net_amt = isset($row['net_amt']) ? (float)$row['net_amt'] : 0;
                                                                        
                                                                        // Get item_id from purchase invoice using against_invoice_no
                                                                        $item_id = 0;
                                                                        if (isset($row['against_invoice_no']) && !empty($row['against_invoice_no'])) {
                                                                            $invoice_no = esc($row['against_invoice_no']);
                                                                            $pi_query = "SELECT id FROM tbl_purchase_invoices WHERE invoice_no = '$invoice_no' LIMIT 1";
                                                                            $pi_result = getRecord($pi_query);
                                                                            if ($pi_result) {
                                                                                $invoice_id = (int)$pi_result['id'];
                                                                                $pi_item_query = "SELECT id FROM tbl_purchase_invoice_items WHERE invoice_id = $invoice_id AND product_id = " . (int)$row['product_id'] . " LIMIT 1";
                                                                                $pi_item_result = getRecord($pi_item_query);
                                                                                if ($pi_item_result) {
                                                                                    $item_id = (int)$pi_item_result['id'];
                                                                                }
                                                                            }
                                                                        }
                                                                        
                                                                        // If still no item_id, try from invoice_id
                                                                        if ($item_id <= 0 && isset($row['invoice_id']) && $row['invoice_id'] > 0) {
                                                                            $invoice_id = (int)$row['invoice_id'];
                                                                            $pi_item_query = "SELECT id FROM tbl_purchase_invoice_items WHERE invoice_id = $invoice_id AND product_id = " . (int)$row['product_id'] . " LIMIT 1";
                                                                            $pi_item_result = getRecord($pi_item_query);
                                                                            if ($pi_item_result) {
                                                                                $item_id = (int)$pi_item_result['id'];
                                                                            }
                                                                        }
                                                                        
                                                                        if ($item_id > 0) {
                                                                            // Build used barcodes list for exclusion
                                                                            $used_barcodes_where = '';
                                                                            if (!empty($used_barcodes)) {
                                                                                $used_barcodes_escaped = array_map(function($b) { return "'" . esc($b) . "'"; }, $used_barcodes);
                                                                                $used_barcodes_where = " AND sj.barcode NOT IN (" . implode(',', $used_barcodes_escaped) . ")";
                                                                            }
                                                                            
                                                                            $barcode_query = "SELECT sj.barcode 
                                                                                              FROM tbl_stock_journal sj
                                                                                              WHERE sj.item_id = $item_id
                                                                                              AND sj.product_id = " . (int)$row['product_id'] . " 
                                                                                              AND (sj.product_characteristic_id = " . (int)($row['product_characteristic_id'] ?? 0) . " OR (sj.product_characteristic_id IS NULL AND " . (int)($row['product_characteristic_id'] ?? 0) . " = 0))
                                                                                              AND sj.status = 'active' 
                                                                                              AND sj.barcode IS NOT NULL 
                                                                                              AND sj.barcode != ''
                                                                                              AND ABS(sj.gross_weight - $gross_weight) < 0.001
                                                                                              AND ABS(sj.net_amt_with_tax - $net_amt) < 0.01
                                                                                              $used_barcodes_where
                                                                                              ORDER BY sj.id ASC 
                                                                                              LIMIT 1";
                                                                            
                                                                            $barcode_result = getRecord($barcode_query);
                                                                            if ($barcode_result && !empty($barcode_result['barcode'])) {
                                                                                $barcode_value = trim($barcode_result['barcode']);
                                                                                // Add to used barcodes list to prevent duplicates
                                                                                if (!in_array($barcode_value, $used_barcodes)) {
                                                                                    $used_barcodes[] = $barcode_value;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                    // Final fallback (no hardcoded B / 10-digit padding)
                                                                    if (empty($barcode_value)) {
                                                                        $barcode_value = '—';
                                                                    }
                                                                }
                                                                $attach_item_id = (int)($row['sj_attach_item_id'] ?? 0);
                                                                $attach_rel = stock_history_journal_image_path($conn, $attach_item_id, $barcode_value);
                                                                $attach_href = $attach_rel !== '' ? htmlspecialchars($attach_rel, ENT_QUOTES, 'UTF-8') : '';
                                                                $invoice_id = isset($row['invoice_id']) ? (int)$row['invoice_id'] : 0;
                                                                $view_url = $invoice_id > 0 ? 'purchase-invoice.php?id=' . $invoice_id : '#';
                                                                echo '<tr>';
                                                                echo '<td data-col="image" class="stock-history-img-cell text-center">';
                                                                if ($attach_href !== '') {
                                                                    echo '<a href="' . $attach_href . '" target="_blank" rel="noopener" class="stock-history-img-link" title="Open attachment">';
                                                                    echo '<img src="' . $attach_href . '" alt="" class="stock-history-thumb" loading="lazy">';
                                                                    echo '</a>';
                                                                } else {
                                                                    echo '<div class="image-placeholder"><i class="feather icon-image"></i></div>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="view" style="text-align: center;">';
                                                                if ($attach_href !== '') {
                                                                    echo '<a href="' . $attach_href . '" target="_blank" rel="noopener" class="view-btn" style="text-decoration: none; display: inline-block;">View</a>';
                                                                } elseif ($invoice_id > 0) {
                                                                    echo '<a href="' . htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8') . '" class="view-btn" style="text-decoration: none; display: inline-block;">View</a>';
                                                                } else {
                                                                    echo '<span style="color: #94a3b8; font-size: 0.75rem;">-</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="category"></td>';
                                                                echo '<td data-col="date">'.$date.'</td>';
                                                                $barcode_details = 'Barcode: ' . htmlspecialchars($barcode_value);
                                                                if (!empty($row['product_name'])) $barcode_details .= ' | Product: ' . htmlspecialchars($row['product_name']);
                                                                if (!empty($row['metal_name'])) $barcode_details .= ' | Metal: ' . htmlspecialchars($row['metal_name']);
                                                                if (!empty($row['hsn'])) $barcode_details .= ' | HSN: ' . htmlspecialchars($row['hsn']);
                                                                if (!empty($row['sku_code'])) $barcode_details .= ' | SKU: ' . htmlspecialchars($row['sku_code']);
                                                                echo '<td data-col="barcode" style="position: relative;">';
                                                                if (!empty($barcode_value) && trim($barcode_value) !== '' && $barcode_value !== '—') {
                                                                    echo '<span class="barcode-display" data-barcode="' . htmlspecialchars($barcode_value, ENT_QUOTES, 'UTF-8') . '" style="color: #11294b; font-size: 0.9rem; cursor: pointer; user-select: none;" title="' . $barcode_details . ' (Click to copy barcode)" onclick="stockHistoryCopyBarcode(event)">';
                                                                    echo htmlspecialchars($barcode_value);
                                                                    echo '</span>';
                                                                } else {
                                                                    echo '<span style="color: #94a3b8; font-size: 0.85rem;">-</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="type_of_voucher">'.htmlspecialchars($voucher_type).'</td>';
                                                                $inv_no = trim((string)($row['against_invoice_no'] ?? ''));
                                                                echo '<td data-col="against_invoice">';
                                                                if ($inv_no !== '') {
                                                                    echo '<span class="history-invoice-link">' . htmlspecialchars($inv_no) . '</span>';
                                                                } else {
                                                                    echo '<span style="color: #94a3b8; font-size: 0.75rem;">-</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="gross_wt" class="col-accent">'.number_format($row['gross_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="pure_wt" class="col-accent">'.number_format($row['pure_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="net_amt" style="display: none;">'.number_format($row['net_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="rfid" style="display: none;">'.htmlspecialchars($row['rfid'] ?: '').'</td>';
                                                                echo '<td data-col="location" style="display: none;">'.htmlspecialchars($row['location'] ?: '').'</td>';
                                                                echo '<td data-col="voucher_type" style="display: none;">'.htmlspecialchars($voucher_type).'</td>';
                                                                echo '<td data-col="invoice" style="display: none;">'.htmlspecialchars($row['against_invoice_no'] ?: '').'</td>';
                                                                echo '<td data-col="branch" style="display: none;">'.htmlspecialchars($row['branch_name'] ?: 'MAIN BRANCH').'</td>';
                                                                echo '<td data-col="qty" style="display: none;">'.number_format($row['qty'] ?: 0, 0).'</td>';
                                                                echo '<td data-col="purity" style="display: none;">'.number_format($row['purity'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="requested_qty" style="display: none;">'.number_format($row['requested_qty'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="requested_wt" style="display: none;">'.number_format($row['requested_wt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="stone_wt" style="display: none;">'.number_format($row['stone_wt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="diamond_wt" style="display: none;">'.number_format($row['diamond_wt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="less_wt" style="display: none;">'.number_format($row['less_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="purity_wt" style="display: none;">'.number_format($row['purity_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="wastage_per" style="display: none;">'.number_format($row['wastage_per'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="wastage_wt" style="display: none;">'.number_format($row['wastage_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="net_wt" style="display: none;">'.number_format($row['net_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="alloy_wt" style="display: none;">'.number_format($row['alloy_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="final_wt" style="display: none;">'.number_format($row['final_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="standard_wt" style="display: none;">'.number_format($row['standard_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="actual_wt" style="display: none;">'.number_format($row['actual_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="national_wt" style="display: none;">'.number_format($row['national_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="name" style="display: none;">'.htmlspecialchars($row['name'] ?: '').'</td>';
                                                                echo '<td data-col="making_rate" style="display: none;">'.number_format($row['making_rate'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="amt" style="display: none;">'.number_format($row['amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="making_amt" style="display: none;">'.number_format($row['making_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="amount" style="display: none;">'.number_format($row['amount'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="hui_code" style="display: none;">'.htmlspecialchars($row['hui_code'] ?: '0.00').'</td>';
                                                                echo '<td data-col="packet_wt" style="display: none;">'.number_format($row['packet_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="packet_length" style="display: none;">'.number_format($row['packet_length'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="rate" style="display: none;">'.number_format($row['rate'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="hallmark1" style="display: none;">'.htmlspecialchars($row['hallmark1'] ?: '0.00').'</td>';
                                                                echo '<td data-col="hallmark2" style="display: none;">'.htmlspecialchars($row['hallmark2'] ?: '0.00').'</td>';
                                                                echo '<td data-col="net_amt_with_tax" style="display: none;">'.number_format($row['net_amt_with_tax'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="tax_amt" style="display: none;">'.number_format($row['tax_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="discount_per" style="display: none;">'.number_format($row['discount_per'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="discount_amt" style="display: none;">'.number_format($row['discount_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="metal_value" style="display: none;">'.number_format($row['metal_value'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="purchase" style="display: none;">'.number_format($row['purchase'] ?: 0, 2).'</td>';
                                                                echo '</tr>';
                                                            }
                                                        } else {
                                                            echo '<tr><td colspan="50" class="text-center text-muted" style="padding: 40px;">No inward stock data found</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td data-col="image" style="padding: 8px 4px;"></td>
                                                            <td data-col="view" style="padding: 8px 4px;"></td>
                                                            <td data-col="category" style="padding: 8px 4px;"></td>
                                                            <td data-col="date" style="padding: 8px 4px;"></td>
                                                            <td data-col="barcode" style="padding: 8px 4px;"></td>
                                                            <td data-col="type_of_voucher" style="font-weight: 700; padding: 8px 4px;">Grand Total</td>
                                                            <td data-col="against_invoice" style="padding: 8px 4px;"></td>
                                                            <td data-col="gross_wt" class="col-accent" style="padding: 8px 4px;"><?= number_format($inward_totals['total_gross_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="pure_wt" class="col-accent" style="padding: 8px 4px;"><?= number_format($inward_totals['total_pure_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="net_amt" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="rfid" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="location" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="voucher_type" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="invoice" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="branch" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="qty" style="padding: 8px 4px; display: none;"><?= number_format($inward_totals['total_qty'] ?: 0, 0) ?></td>
                                                            <td data-col="purity" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="requested_qty" style="padding: 8px 4px;"><?= number_format($inward_totals['total_requested_qty'] ?: 0, 3) ?></td>
                                                            <td data-col="requested_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_requested_wt'] ?: 0, 2) ?></td>
                                                            <td data-col="stone_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_stone_wt'] ?: 0, 2) ?></td>
                                                            <td data-col="diamond_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_diamond_wt'] ?: 0, 2) ?></td>
                                                            <td data-col="less_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_less_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="purity_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_purity_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="wastage_per" style="padding: 8px 4px;"></td>
                                                            <td data-col="wastage_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_wastage_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="net_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_net_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="alloy_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_alloy_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="final_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_final_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="standard_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_standard_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="actual_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_actual_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="national_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_national_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="name" style="padding: 8px 4px;"></td>
                                                            <td data-col="making_rate" style="padding: 8px 4px;"></td>
                                                            <td data-col="amt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_net_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="making_amt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_making_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="amount" style="padding: 8px 4px;"><?= number_format($inward_totals['total_amount'] ?: 0, 2) ?></td>
                                                            <td data-col="hui_code" style="padding: 8px 4px;"></td>
                                                            <td data-col="packet_wt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_packet_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="packet_length" style="padding: 8px 4px;"><?= number_format($inward_totals['total_packet_length'] ?: 0, 3) ?></td>
                                                            <td data-col="rate" style="padding: 8px 4px;"></td>
                                                            <td data-col="hallmark1" style="padding: 8px 4px;"></td>
                                                            <td data-col="hallmark2" style="padding: 8px 4px;"></td>
                                                            <td data-col="net_amt_with_tax" style="padding: 8px 4px;"><?= number_format($inward_totals['total_net_amt_with_tax'] ?: 0, 2) ?></td>
                                                            <td data-col="tax_amt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_tax_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="discount_per" style="padding: 8px 4px;"></td>
                                                            <td data-col="discount_amt" style="padding: 8px 4px;"><?= number_format($inward_totals['total_discount_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="metal_value" style="padding: 8px 4px;"><?= number_format($inward_totals['total_metal_value'] ?: 0, 2) ?></td>
                                                            <td data-col="purchase" style="padding: 8px 4px;"><?= number_format($inward_totals['total_purchase'] ?: 0, 2) ?></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>

                                            <div class="panel-footer">
                                                <div class="panel-footer-info">
                                                    Showing <?= $inward_offset + 1 ?> to <?= min($inward_offset + $inward_per_page, $inward_total) ?> of <?= $inward_total ?> entries
                                                </div>
                                                <div class="panel-footer-total" title="Total gross weight">
                                                    <?= number_format($inward_totals['total_gross_wt'] ?: 0, 3) ?>
                                                </div>
                                                <div class="pagination-controls">
                                                    <select class="show-all-dropdown" id="inwardPerPageSelect">
                                                        <option value="10" <?= $inward_per_page == 10 ? 'selected' : '' ?>>10</option>
                                                        <option value="25" <?= $inward_per_page == 25 ? 'selected' : '' ?>>25</option>
                                                        <option value="50" <?= $inward_per_page == 50 ? 'selected' : '' ?>>50</option>
                                                        <option value="100" <?= $inward_per_page == 100 ? 'selected' : '' ?>>100</option>
                                                    </select>
                                                    <button type="button" class="page-btn" data-page="1" data-type="inward" <?= $inward_page == 1 ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevrons-left"></i>
                                                    </button>
                                                    <button type="button" class="page-btn" data-page="<?= max(1, $inward_page - 1) ?>" data-type="inward" <?= $inward_page == 1 ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevron-left"></i>
                                                    </button>
                                                    <?php
                                                    $start_page = max(1, $inward_page - 2);
                                                    $end_page = min($inward_total_pages, $inward_page + 2);
                                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                                        $active = ($i == $inward_page) ? 'active' : '';
                                                        echo '<button class="page-btn '.$active.'" data-page="'.$i.'" data-type="inward">'.$i.'</button>';
                                                    }
                                                    ?>
                                                    <button type="button" class="page-btn" data-page="<?= min($inward_total_pages, $inward_page + 1) ?>" data-type="inward" <?= $inward_page >= $inward_total_pages ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevron-right"></i>
                                                    </button>
                                                    <button type="button" class="page-btn" data-page="<?= $inward_total_pages ?>" data-type="inward" <?= $inward_page >= $inward_total_pages ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevrons-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Outward Stock Panel -->
                                        <div class="stock-panel">
                                            <div class="panel-header">
                                                <div class="panel-header-main">
                                                    <span class="panel-title">Outward Stock</span>
                                                    <span class="panel-product-name" title="<?= htmlspecialchars($selected_product_label) ?>"><?= htmlspecialchars($selected_product_label) ?></span>
                                                </div>
                                                <div class="panel-header-actions" style="position: relative;">
                                                    <i class="feather icon-settings gear-icon" id="outwardColumnSettingsBtn" title="Column Settings"></i>
                                                    <div class="columns-dropdown" id="outwardColumnsDropdown">
                                                        <div class="columns-dropdown-header">Columns</div>
                                                        <div class="columns-dropdown-search">
                                                            <input type="text" id="outwardColumnSearch" placeholder="Search columns...">
                                                        </div>
                                                        <div class="columns-dropdown-list" id="outwardColumnsList">
                                                            <!-- Will be populated by JavaScript -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="panel-table-container">
                                                <table class="table history-table" id="outwardTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="width: 52px; min-width: 52px;" data-col="image" title="Attached photo">Photo</th>
                                                            <th class="sortable col-accent" data-col="net_amt" style="min-width: 100px;">
                                                                Net Amt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="view" style="min-width: 72px; text-align: center;">View</th>
                                                            <th class="sortable" data-col="date" style="min-width: 96px;">
                                                                Date
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="barcode" style="min-width: 110px;">
                                                                Barcode No
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="rfid" style="min-width: 100px;">
                                                                RFID
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="against_invoice" style="min-width: 120px;">
                                                                Against Invoice No
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="type_of_voucher" style="min-width: 130px;">
                                                                Type Of Voucher
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="location" style="min-width: 100px; display: none;">
                                                                Location
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="vouch" style="min-width: 100px; display: none;">
                                                                Vouch
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="voucher_type" style="min-width: 120px; display: none;">
                                                                Voucher Type
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="invoice" style="min-width: 100px; display: none;">
                                                                Invoice
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="branch" style="min-width: 120px; display: none;">
                                                                Branch
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="qty" style="min-width: 80px; display: none;">
                                                                Qty.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="gross_wt" style="min-width: 100px; display: none;">
                                                                Gross Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="purity" style="min-width: 80px; display: none;">
                                                                Pu
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="pure_wt" style="min-width: 100px; display: none;">
                                                                Pure Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="requested_qty" style="min-width: 100px; display: none;">
                                                                Requested...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="requested_wt" style="min-width: 100px; display: none;">
                                                                Requested...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="stone_wt" style="min-width: 100px; display: none;">
                                                                Stone ...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="diamond_wt" style="min-width: 100px; display: none;">
                                                                Diamon...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="less_wt" style="min-width: 100px; display: none;">
                                                                Less Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="purity_wt" style="min-width: 100px; display: none;">
                                                                Purity Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="wastage_per" style="min-width: 100px; display: none;">
                                                                Wastage Per.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="wastage_wt" style="min-width: 100px; display: none;">
                                                                Wastage Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="net_wt" style="min-width: 100px; display: none;">
                                                                Net Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="alloy_wt" style="min-width: 100px; display: none;">
                                                                Alloy Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="final_wt" style="min-width: 100px; display: none;">
                                                                Final Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="standard_wt" style="min-width: 100px; display: none;">
                                                                Standar...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="actual_wt" style="min-width: 100px; display: none;">
                                                                Actual...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="national_wt" style="min-width: 100px; display: none;">
                                                                Nation...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="name" style="min-width: 100px; display: none;">
                                                                n...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="making_rate" style="min-width: 100px; display: none;">
                                                                Making Rate
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="amt" style="min-width: 100px; display: none;">
                                                                Amt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="making_amt" style="min-width: 100px; display: none;">
                                                                Makin...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="amount" style="min-width: 100px; display: none;">
                                                                Amount
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="hui_code" style="min-width: 100px; display: none;">
                                                                HUI...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="packet_wt" style="min-width: 100px; display: none;">
                                                                Packet Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="packet_length" style="min-width: 100px; display: none;">
                                                                Packet L...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="rate" style="min-width: 100px; display: none;">
                                                                Rate
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="hallmark1" style="min-width: 100px; display: none;">
                                                                Hallmar...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="hallmark2" style="min-width: 100px; display: none;">
                                                                Hallmar...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="net_amt_with_tax" style="min-width: 120px; display: none;">
                                                                Net Amt W...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="tax_amt" style="min-width: 100px; display: none;">
                                                                Tax Amt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="discount_per" style="min-width: 100px; display: none;">
                                                                Discount Per
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="discount_amt" style="min-width: 100px; display: none;">
                                                                Discount A...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="metal_value" style="min-width: 100px; display: none;">
                                                                Metal Val...
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" data-col="purchase" style="min-width: 100px; display: none;">
                                                                Purchase
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        if (!empty($outward_data)) {
                                                            foreach($outward_data as $row) {
                                                                $date = $row['transaction_date'] ? date('d-m-Y', strtotime($row['transaction_date'])) : '';
                                                                $voucher_type = ucfirst(str_replace('_', ' ', $row['type_of_voucher'] ?: 'Sales Invoice'));
                                                                echo '<tr>';
                                                                // Get single barcode for this stock entry
                                                                $barcode_value = '';
                                                                if (!empty($row['barcode']) && trim($row['barcode']) !== '') {
                                                                    // If multiple barcodes are comma-separated, take only the first one
                                                                    $barcodes = explode(',', trim($row['barcode']));
                                                                    $barcode_value = trim($barcodes[0]);
                                                                } else {
                                                                    // Fallback: first try to get from tbl_stock directly
                                                                    $stock_id = isset($row['id']) ? (int)$row['id'] : 0;
                                                                    if ($stock_id > 0) {
                                                                        $stock_barcode_query = "SELECT barcode FROM tbl_stock WHERE id = $stock_id AND barcode IS NOT NULL AND barcode != '' LIMIT 1";
                                                                        $stock_barcode_result = getRecord($stock_barcode_query);
                                                                        if ($stock_barcode_result && !empty($stock_barcode_result['barcode'])) {
                                                                            $barcode_value = trim($stock_barcode_result['barcode']);
                                                                        }
                                                                    }
                                                                    
                                                                    // If still no barcode, try to get from stock_journal directly (get only one)
                                                                    if (empty($barcode_value) && $stock_id > 0 && isset($row['product_id'])) {
                                                                        $barcode_query = "SELECT barcode 
                                                                                          FROM tbl_stock_journal 
                                                                                          WHERE product_id = " . (int)$row['product_id'] . " 
                                                                                          AND (product_characteristic_id = " . (int)($row['product_characteristic_id'] ?? 0) . " OR (product_characteristic_id IS NULL AND " . (int)($row['product_characteristic_id'] ?? 0) . " = 0))
                                                                                          AND status = 'active' 
                                                                                          AND barcode IS NOT NULL 
                                                                                          AND barcode != '' 
                                                                                          ORDER BY id ASC 
                                                                                          LIMIT 1";
                                                                        $barcode_result = getRecord($barcode_query);
                                                                        if ($barcode_result && !empty($barcode_result['barcode'])) {
                                                                            $barcode_value = trim($barcode_result['barcode']);
                                                                        }
                                                                    }
                                                                    // Final fallback (no hardcoded B / 10-digit padding)
                                                                    if (empty($barcode_value)) {
                                                                        $barcode_value = '—';
                                                                    }
                                                                }
                                                                $attach_item_id = (int)($row['sj_attach_item_id'] ?? 0);
                                                                $attach_rel = stock_history_journal_image_path($conn, $attach_item_id, $barcode_value);
                                                                $attach_href = $attach_rel !== '' ? htmlspecialchars($attach_rel, ENT_QUOTES, 'UTF-8') : '';
                                                                echo '<td data-col="image" class="stock-history-img-cell text-center">';
                                                                if ($attach_href !== '') {
                                                                    echo '<a href="' . $attach_href . '" target="_blank" rel="noopener" class="stock-history-img-link" title="Open attachment">';
                                                                    echo '<img src="' . $attach_href . '" alt="" class="stock-history-thumb" loading="lazy">';
                                                                    echo '</a>';
                                                                } else {
                                                                    echo '<div class="image-placeholder"><i class="feather icon-image"></i></div>';
                                                                }
                                                                echo '</td>';
                                                                $invoice_id = isset($row['invoice_id']) ? (int)$row['invoice_id'] : 0;
                                                                $view_url = $invoice_id > 0 ? 'purchase-invoice.php?id=' . $invoice_id : '#';
                                                                echo '<td data-col="net_amt" class="col-accent">'.number_format($row['net_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="view" style="text-align: center;">';
                                                                if ($attach_href !== '') {
                                                                    echo '<a href="' . $attach_href . '" target="_blank" rel="noopener" class="view-btn" style="text-decoration: none; display: inline-block;">View</a>';
                                                                } elseif ($invoice_id > 0) {
                                                                    echo '<a href="' . htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8') . '" class="view-btn" style="text-decoration: none; display: inline-block;">View</a>';
                                                                } else {
                                                                    echo '<span style="color: #94a3b8; font-size: 0.75rem;">-</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="date">'.$date.'</td>';
                                                                $barcode_details_out = 'Barcode: ' . htmlspecialchars($barcode_value);
                                                                if (!empty($row['product_name'])) $barcode_details_out .= ' | Product: ' . htmlspecialchars($row['product_name']);
                                                                if (!empty($row['metal_name'])) $barcode_details_out .= ' | Metal: ' . htmlspecialchars($row['metal_name']);
                                                                echo '<td data-col="barcode" style="position: relative;">';
                                                                if (!empty($barcode_value) && trim($barcode_value) !== '' && $barcode_value !== '—') {
                                                                    echo '<span class="barcode-display" data-barcode="' . htmlspecialchars($barcode_value, ENT_QUOTES, 'UTF-8') . '" style="color: #11294b; font-size: 0.9rem; cursor: pointer; user-select: none;" title="' . $barcode_details_out . ' (Click to copy barcode)" onclick="stockHistoryCopyBarcode(event)">';
                                                                    echo htmlspecialchars($barcode_value);
                                                                    echo '</span>';
                                                                } else {
                                                                    echo '<span style="color: #94a3b8; font-size: 0.85rem;">-</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="rfid">'.htmlspecialchars($row['rfid'] ?: '').'</td>';
                                                                $out_inv = trim((string)($row['against_invoice_no'] ?? ''));
                                                                echo '<td data-col="against_invoice">';
                                                                if ($out_inv !== '') {
                                                                    echo '<span class="history-invoice-link">' . htmlspecialchars($out_inv) . '</span>';
                                                                } else {
                                                                    echo '<span style="color: #94a3b8; font-size: 0.75rem;">-</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td data-col="type_of_voucher">'.htmlspecialchars($voucher_type).'</td>';
                                                                echo '<td data-col="location" style="display: none;">'.htmlspecialchars($row['location'] ?: '').'</td>';
                                                                echo '<td data-col="vouch" style="display: none;">'.htmlspecialchars($row['vouch'] ?: $voucher_type).'</td>';
                                                                echo '<td data-col="voucher_type" style="display: none;">'.htmlspecialchars($voucher_type).'</td>';
                                                                echo '<td data-col="invoice" style="display: none;">'.htmlspecialchars($row['against_invoice_no'] ?: '').'</td>';
                                                                echo '<td data-col="branch" style="display: none;">'.htmlspecialchars($row['branch_name'] ?: 'MAIN BRANCH').'</td>';
                                                                echo '<td data-col="qty" style="display: none;">'.number_format($row['qty'] ?: 0, 0).'</td>';
                                                                echo '<td data-col="gross_wt" style="display: none;">'.number_format($row['gross_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="purity" style="display: none;">'.number_format($row['purity'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="pure_wt" style="display: none;">'.number_format($row['pure_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="requested_qty" style="display: none;">'.number_format($row['requested_qty'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="requested_wt" style="display: none;">'.number_format($row['requested_wt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="stone_wt" style="display: none;">'.number_format($row['stone_wt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="diamond_wt" style="display: none;">'.number_format($row['diamond_wt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="less_wt" style="display: none;">'.number_format($row['less_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="purity_wt" style="display: none;">'.number_format($row['purity_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="wastage_per" style="display: none;">'.number_format($row['wastage_per'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="wastage_wt" style="display: none;">'.number_format($row['wastage_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="net_wt" style="display: none;">'.number_format($row['net_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="alloy_wt" style="display: none;">'.number_format($row['alloy_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="final_wt" style="display: none;">'.number_format($row['final_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="standard_wt" style="display: none;">'.number_format($row['standard_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="actual_wt" style="display: none;">'.number_format($row['actual_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="national_wt" style="display: none;">'.number_format($row['national_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="name" style="display: none;">'.htmlspecialchars($row['name'] ?: '').'</td>';
                                                                echo '<td data-col="making_rate" style="display: none;">'.number_format($row['making_rate'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="amt" style="display: none;">'.number_format($row['amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="making_amt" style="display: none;">'.number_format($row['making_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="amount" style="display: none;">'.number_format($row['amount'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="hui_code" style="display: none;">'.htmlspecialchars($row['hui_code'] ?: '0.00').'</td>';
                                                                echo '<td data-col="packet_wt" style="display: none;">'.number_format($row['packet_wt'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="packet_length" style="display: none;">'.number_format($row['packet_length'] ?: 0, 3).'</td>';
                                                                echo '<td data-col="rate" style="display: none;">'.number_format($row['rate'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="hallmark1" style="display: none;">'.htmlspecialchars($row['hallmark1'] ?: '0.00').'</td>';
                                                                echo '<td data-col="hallmark2" style="display: none;">'.htmlspecialchars($row['hallmark2'] ?: '0.00').'</td>';
                                                                echo '<td data-col="net_amt_with_tax" style="display: none;">'.number_format($row['net_amt_with_tax'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="tax_amt" style="display: none;">'.number_format($row['tax_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="discount_per" style="display: none;">'.number_format($row['discount_per'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="discount_amt" style="display: none;">'.number_format($row['discount_amt'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="metal_value" style="display: none;">'.number_format($row['metal_value'] ?: 0, 2).'</td>';
                                                                echo '<td data-col="purchase" style="display: none;">'.number_format($row['purchase'] ?: 0, 2).'</td>';
                                                                echo '</tr>';
                                                            }
                                                        } else {
                                                            echo '<tr><td colspan="50" class="text-center text-muted" style="padding: 40px;">No outward stock data found</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td data-col="image" style="padding: 8px 4px;"></td>
                                                            <td data-col="net_amt" class="col-accent" style="padding: 8px 4px;"><?= number_format($outward_totals['total_net_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="view" style="padding: 8px 4px;"></td>
                                                            <td data-col="date" style="padding: 8px 4px;"></td>
                                                            <td data-col="barcode" style="padding: 8px 4px;"></td>
                                                            <td data-col="rfid" style="padding: 8px 4px;"></td>
                                                            <td data-col="against_invoice" style="padding: 8px 4px;"></td>
                                                            <td data-col="type_of_voucher" style="font-weight: 700; padding: 8px 4px;">Grand Total</td>
                                                            <td data-col="location" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="vouch" style="padding: 8px 4px; display: none;"></td>
                                                            <td data-col="voucher_type" style="padding: 8px 4px;"></td>
                                                            <td data-col="invoice" style="padding: 8px 4px;"></td>
                                                            <td data-col="branch" style="padding: 8px 4px;"></td>
                                                            <td data-col="qty" style="padding: 8px 4px;"><?= number_format($outward_totals['total_qty'] ?: 0, 0) ?></td>
                                                            <td data-col="gross_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_gross_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="purity" style="padding: 8px 4px;"></td>
                                                            <td data-col="pure_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_pure_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="requested_qty" style="padding: 8px 4px;"><?= number_format($outward_totals['total_requested_qty'] ?: 0, 3) ?></td>
                                                            <td data-col="requested_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_requested_wt'] ?: 0, 2) ?></td>
                                                            <td data-col="stone_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_stone_wt'] ?: 0, 2) ?></td>
                                                            <td data-col="diamond_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_diamond_wt'] ?: 0, 2) ?></td>
                                                            <td data-col="less_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_less_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="purity_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_purity_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="wastage_per" style="padding: 8px 4px;"></td>
                                                            <td data-col="wastage_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_wastage_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="net_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_net_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="alloy_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_alloy_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="final_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_final_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="standard_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_standard_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="actual_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_actual_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="national_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_national_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="name" style="padding: 8px 4px;"></td>
                                                            <td data-col="making_rate" style="padding: 8px 4px;"></td>
                                                            <td data-col="amt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_net_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="making_amt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_making_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="amount" style="padding: 8px 4px;"><?= number_format($outward_totals['total_amount'] ?: 0, 2) ?></td>
                                                            <td data-col="hui_code" style="padding: 8px 4px;"></td>
                                                            <td data-col="packet_wt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_packet_wt'] ?: 0, 3) ?></td>
                                                            <td data-col="packet_length" style="padding: 8px 4px;"><?= number_format($outward_totals['total_packet_length'] ?: 0, 3) ?></td>
                                                            <td data-col="rate" style="padding: 8px 4px;"></td>
                                                            <td data-col="hallmark1" style="padding: 8px 4px;"></td>
                                                            <td data-col="hallmark2" style="padding: 8px 4px;"></td>
                                                            <td data-col="net_amt_with_tax" style="padding: 8px 4px;"><?= number_format($outward_totals['total_net_amt_with_tax'] ?: 0, 2) ?></td>
                                                            <td data-col="tax_amt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_tax_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="discount_per" style="padding: 8px 4px;"></td>
                                                            <td data-col="discount_amt" style="padding: 8px 4px;"><?= number_format($outward_totals['total_discount_amt'] ?: 0, 2) ?></td>
                                                            <td data-col="metal_value" style="padding: 8px 4px;"><?= number_format($outward_totals['total_metal_value'] ?: 0, 2) ?></td>
                                                            <td data-col="purchase" style="padding: 8px 4px;"><?= number_format($outward_totals['total_purchase'] ?: 0, 2) ?></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>

                                            <div class="panel-footer">
                                                <div class="panel-footer-info">
                                                    Showing <?= $outward_offset + 1 ?> to <?= min($outward_offset + $outward_per_page, $outward_total) ?> of <?= $outward_total ?> entries
                                                </div>
                                                <div class="panel-footer-total" title="Total gross weight">
                                                    <?= number_format($outward_totals['total_gross_wt'] ?: 0, 3) ?>
                                                </div>
                                                <div class="pagination-controls">
                                                    <select class="show-all-dropdown" id="outwardPerPageSelect">
                                                        <option value="10" <?= $outward_per_page == 10 ? 'selected' : '' ?>>10</option>
                                                        <option value="25" <?= $outward_per_page == 25 ? 'selected' : '' ?>>25</option>
                                                        <option value="50" <?= $outward_per_page == 50 ? 'selected' : '' ?>>50</option>
                                                        <option value="100" <?= $outward_per_page == 100 ? 'selected' : '' ?>>100</option>
                                                    </select>
                                                    <button type="button" class="page-btn" data-page="1" data-type="outward" <?= $outward_page == 1 ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevrons-left"></i>
                                                    </button>
                                                    <button type="button" class="page-btn" data-page="<?= max(1, $outward_page - 1) ?>" data-type="outward" <?= $outward_page == 1 ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevron-left"></i>
                                                    </button>
                                                    <?php
                                                    $start_page = max(1, $outward_page - 2);
                                                    $end_page = min($outward_total_pages, $outward_page + 2);
                                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                                        $active = ($i == $outward_page) ? 'active' : '';
                                                        echo '<button class="page-btn '.$active.'" data-page="'.$i.'" data-type="outward">'.$i.'</button>';
                                                    }
                                                    ?>
                                                    <button type="button" class="page-btn" data-page="<?= min($outward_total_pages, $outward_page + 1) ?>" data-type="outward" <?= $outward_page >= $outward_total_pages ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevron-right"></i>
                                                    </button>
                                                    <button type="button" class="page-btn" data-page="<?= $outward_total_pages ?>" data-type="outward" <?= $outward_page >= $outward_total_pages ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevrons-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <!-- [ Layout content ] End -->
        </div>
        <!-- [ Layout container ] End -->
    </div>
</div>
<!-- [ Layout wrapper ] End -->

<div class="modal fade stock-history-adv-modal" id="stockHistoryAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="stockHistoryAdvFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="stockHistoryAdvFilterModalLabel">Advance Filter</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="get" action="stock-history.php" id="stockHistoryAdvFilterForm">
                <?php if ($stock_id > 0): ?><input type="hidden" name="stock_id" value="<?= (int) $stock_id ?>"><?php endif; ?>
                <?php if ($product_id > 0): ?><input type="hidden" name="product_id" value="<?= (int) $product_id ?>"><?php endif; ?>
                <?php if ($characteristic_id > 0): ?><input type="hidden" name="characteristic_id" value="<?= (int) $characteristic_id ?>"><?php endif; ?>
                <input type="hidden" name="inward_page" value="1">
                <input type="hidden" name="outward_page" value="1">
                <?php if ($inward_per_page != 10): ?><input type="hidden" name="inward_per_page" value="<?= (int) $inward_per_page ?>"><?php endif; ?>
                <?php if ($outward_per_page != 10): ?><input type="hidden" name="outward_per_page" value="<?= (int) $outward_per_page ?>"><?php endif; ?>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="adv_branch">Branch</label>
                        <select class="form-control" id="adv_branch" name="adv_branch">
                            <option value="0" <?= $adv_branch <= 0 ? 'selected' : '' ?>>All Branches</option>
                            <?php foreach ($filter_branches as $fb): ?>
                                <option value="<?= (int) $fb['id'] ?>" <?= $adv_branch === (int) $fb['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fb['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adv_category">Category</label>
                        <select class="form-control" id="adv_category" name="adv_category">
                            <option value="0" <?= $adv_category <= 0 ? 'selected' : '' ?>>Select Category</option>
                            <?php foreach ($filter_categories as $fc): ?>
                                <option value="<?= (int) $fc['id'] ?>" <?= $adv_category === (int) $fc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="adv_barcode">Barcode No.</label>
                            <input type="text" class="form-control" id="adv_barcode" name="adv_barcode" value="<?= htmlspecialchars($adv_barcode) ?>" placeholder="" autocomplete="off">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="adv_rfid">RFID Code</label>
                            <input type="text" class="form-control" id="adv_rfid" name="adv_rfid" value="<?= htmlspecialchars($adv_rfid) ?>" placeholder="" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-adv">
                    <button type="submit" class="btn btn-adv-apply">Apply Filter</button>
                    <a href="<?= htmlspecialchars($stock_history_clear_href, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-adv-clear">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer-script.php';?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function stockHistoryCopyBarcodeFallback(text, onDone) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        if (onDone) onDone();
    } catch (err) {
        console.error('Copy failed:', err);
    }
    document.body.removeChild(ta);
}
function stockHistoryCopyBarcode(e) {
    if (!e || !e.currentTarget) return;
    e.preventDefault();
    e.stopPropagation();
    var el = e.currentTarget;
    var text = el.getAttribute('data-barcode') || '';
    if (!text) return;
    function flash() {
        var orig = el.style.color;
        el.style.color = '#059669';
        setTimeout(function () { el.style.color = orig; }, 800);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(flash).catch(function (err) {
            console.error('Failed to copy:', err);
            stockHistoryCopyBarcodeFallback(text, flash);
        });
    } else {
        stockHistoryCopyBarcodeFallback(text, flash);
    }
}
$(document).ready(function() {
    // Make all dropdown menu items active (not top-level nav links)
    $('.top-navbar .dropdown-menu .dropdown-item').addClass('active');
    $('.top-navbar .mega-menu .dropdown-item').addClass('active');
    
    // Per page changes
    $('#inwardPerPageSelect').on('change', function() {
        const perPage = $(this).val();
        const url = new URL(window.location.href);
        url.searchParams.set('inward_per_page', perPage);
        url.searchParams.set('inward_page', 1);
        window.location.href = url.toString();
    });

    $('#outwardPerPageSelect').on('change', function() {
        const perPage = $(this).val();
        const url = new URL(window.location.href);
        url.searchParams.set('outward_per_page', perPage);
        url.searchParams.set('outward_page', 1);
        window.location.href = url.toString();
    });

    // Pagination
    $('.page-btn[data-type="inward"]').on('click', function() {
        const page = $(this).data('page');
        if (page && !$(this).is(':disabled')) {
            const url = new URL(window.location.href);
            url.searchParams.set('inward_page', page);
            window.location.href = url.toString();
        }
    });

    $('.page-btn[data-type="outward"]').on('click', function() {
        const page = $(this).data('page');
        if (page && !$(this).is(':disabled')) {
            const url = new URL(window.location.href);
            url.searchParams.set('outward_page', page);
            window.location.href = url.toString();
        }
    });

    // Column visibility — default matches Stock Availability (Wt) reference layout (persisted in localStorage)
    const inwardColumnDefaults = [
        { key: 'image', label: 'Photo', visible: true },
        { key: 'view', label: 'View', visible: true },
        { key: 'category', label: 'Category', visible: true },
        { key: 'date', label: 'Date', visible: true },
        { key: 'barcode', label: 'Barcode No', visible: true },
        { key: 'type_of_voucher', label: 'Voucher Type', visible: true },
        { key: 'against_invoice', label: 'Invoice', visible: true },
        { key: 'gross_wt', label: 'Gross Wt', visible: true },
        { key: 'pure_wt', label: 'Pure Wt', visible: true },
        { key: 'net_amt', label: 'Net Amt', visible: false },
        { key: 'rfid', label: 'RFID', visible: false },
        { key: 'location', label: 'Location', visible: false },
        { key: 'voucher_type', label: 'Voucher Type (alt)', visible: false },
        { key: 'invoice', label: 'Invoice No.', visible: false },
        { key: 'branch', label: 'Branch', visible: false },
        { key: 'qty', label: 'Qty.', visible: false },
        { key: 'purity', label: 'Pu', visible: false },
        { key: 'requested_qty', label: 'Requested Qty', visible: false },
        { key: 'requested_wt', label: 'Requested Wt', visible: false },
        { key: 'stone_wt', label: 'Stone Wt', visible: false },
        { key: 'diamond_wt', label: 'Diamond Wt', visible: false },
        { key: 'less_wt', label: 'Less Wt.', visible: false },
        { key: 'purity_wt', label: 'Purity Wt', visible: false },
        { key: 'wastage_per', label: 'Wastage Per.', visible: false },
        { key: 'wastage_wt', label: 'Wastage Wt.', visible: false },
        { key: 'net_wt', label: 'Net Wt', visible: false },
        { key: 'alloy_wt', label: 'Alloy Wt.', visible: false },
        { key: 'final_wt', label: 'Final Wt', visible: false },
        { key: 'standard_wt', label: 'Standard Wt', visible: false },
        { key: 'actual_wt', label: 'Actual Wt', visible: false },
        { key: 'national_wt', label: 'National Wt', visible: false },
        { key: 'name', label: 'Name', visible: false },
        { key: 'making_rate', label: 'Making Rate', visible: false },
        { key: 'amt', label: 'Amt', visible: false },
        { key: 'making_amt', label: 'Making Amt', visible: false },
        { key: 'amount', label: 'Amount', visible: false },
        { key: 'hui_code', label: 'HUI Code', visible: false },
        { key: 'packet_wt', label: 'Packet Wt', visible: false },
        { key: 'packet_length', label: 'Packet Length', visible: false },
        { key: 'rate', label: 'Rate', visible: false },
        { key: 'hallmark1', label: 'Hallmark 1', visible: false },
        { key: 'hallmark2', label: 'Hallmark 2', visible: false },
        { key: 'net_amt_with_tax', label: 'Net Amt With Tax', visible: false },
        { key: 'tax_amt', label: 'Tax Amt', visible: false },
        { key: 'discount_per', label: 'Discount Per', visible: false },
        { key: 'discount_amt', label: 'Discount Amt', visible: false },
        { key: 'metal_value', label: 'Metal Value', visible: false },
        { key: 'purchase', label: 'Purchase', visible: false }
    ];

    const outwardColumnDefaults = [
        { key: 'image', label: 'Photo', visible: true },
        { key: 'net_amt', label: 'Net Amt', visible: true },
        { key: 'view', label: 'View', visible: true },
        { key: 'date', label: 'Date', visible: true },
        { key: 'barcode', label: 'Barcode No', visible: true },
        { key: 'rfid', label: 'RFID', visible: true },
        { key: 'against_invoice', label: 'Against Invoice No', visible: true },
        { key: 'type_of_voucher', label: 'Type Of Voucher', visible: true },
        { key: 'location', label: 'Location', visible: false },
        { key: 'vouch', label: 'Vouch', visible: false },
        { key: 'voucher_type', label: 'Voucher Type', visible: false },
        { key: 'invoice', label: 'Invoice', visible: false },
        { key: 'branch', label: 'Branch', visible: false },
        { key: 'qty', label: 'Qty.', visible: false },
        { key: 'gross_wt', label: 'Gross Wt', visible: false },
        { key: 'purity', label: 'Pu', visible: false },
        { key: 'pure_wt', label: 'Pure Wt.', visible: false },
        { key: 'requested_qty', label: 'Requested Qty', visible: false },
        { key: 'requested_wt', label: 'Requested Wt', visible: false },
        { key: 'stone_wt', label: 'Stone Wt', visible: false },
        { key: 'diamond_wt', label: 'Diamond Wt', visible: false },
        { key: 'less_wt', label: 'Less Wt.', visible: false },
        { key: 'purity_wt', label: 'Purity Wt', visible: false },
        { key: 'wastage_per', label: 'Wastage Per.', visible: false },
        { key: 'wastage_wt', label: 'Wastage Wt.', visible: false },
        { key: 'net_wt', label: 'Net Wt', visible: false },
        { key: 'alloy_wt', label: 'Alloy Wt.', visible: false },
        { key: 'final_wt', label: 'Final Wt', visible: false },
        { key: 'standard_wt', label: 'Standard Wt', visible: false },
        { key: 'actual_wt', label: 'Actual Wt', visible: false },
        { key: 'national_wt', label: 'National Wt', visible: false },
        { key: 'name', label: 'Name', visible: false },
        { key: 'making_rate', label: 'Making Rate', visible: false },
        { key: 'amt', label: 'Amt', visible: false },
        { key: 'making_amt', label: 'Making Amt', visible: false },
        { key: 'amount', label: 'Amount', visible: false },
        { key: 'hui_code', label: 'HUI Code', visible: false },
        { key: 'packet_wt', label: 'Packet Wt', visible: false },
        { key: 'packet_length', label: 'Packet Length', visible: false },
        { key: 'rate', label: 'Rate', visible: false },
        { key: 'hallmark1', label: 'Hallmark 1', visible: false },
        { key: 'hallmark2', label: 'Hallmark 2', visible: false },
        { key: 'net_amt_with_tax', label: 'Net Amt With Tax', visible: false },
        { key: 'tax_amt', label: 'Tax Amt', visible: false },
        { key: 'discount_per', label: 'Discount Per', visible: false },
        { key: 'discount_amt', label: 'Discount Amt', visible: false },
        { key: 'metal_value', label: 'Metal Value', visible: false },
        { key: 'purchase', label: 'Purchase', visible: false }
    ];

    const LS_SH_INWARD_VIS = 'auragold_stock_history_inward_col_visibility';
    const LS_SH_OUTWARD_VIS = 'auragold_stock_history_outward_col_visibility';
    const LS_SH_INWARD_ORDER = 'auragold_stock_history_inward_col_order';
    const LS_SH_OUTWARD_ORDER = 'auragold_stock_history_outward_col_order';

    function mergeColVisibility(defaults, lsKey) {
        try {
            const raw = localStorage.getItem(lsKey);
            if (!raw) {
                return defaults.map(function (d) { return Object.assign({}, d); });
            }
            const saved = JSON.parse(raw);
            if (!saved || typeof saved !== 'object') {
                return defaults.map(function (d) { return Object.assign({}, d); });
            }
            return defaults.map(function (d) {
                const c = Object.assign({}, d);
                if (Object.prototype.hasOwnProperty.call(saved, c.key)) {
                    c.visible = !!saved[c.key];
                }
                return c;
            });
        } catch (e) {
            return defaults.map(function (d) { return Object.assign({}, d); });
        }
    }

    var inwardColumnDefinitions = mergeColVisibility(inwardColumnDefaults, LS_SH_INWARD_VIS);
    var outwardColumnDefinitions = mergeColVisibility(outwardColumnDefaults, LS_SH_OUTWARD_VIS);

    function getColumnKeysFromTable(tableId) {
        const row = document.querySelector('#' + tableId + ' thead tr');
        if (!row) {
            return [];
        }
        return Array.prototype.map.call(row.querySelectorAll('th[data-col]'), function (th) {
            return th.getAttribute('data-col');
        });
    }

    function normalizeColumnOrder(savedOrder, currentKeys) {
        const valid = new Set(currentKeys);
        const out = [];
        if (Array.isArray(savedOrder)) {
            savedOrder.forEach(function (k) {
                if (valid.has(k)) {
                    out.push(k);
                    valid.delete(k);
                }
            });
        }
        currentKeys.forEach(function (k) {
            if (valid.has(k)) {
                out.push(k);
            }
        });
        return out;
    }

    function applyColumnOrderToTable(tableId, orderKeys) {
        const table = document.getElementById(tableId);
        if (!table || !orderKeys || orderKeys.length === 0) {
            return;
        }
        const theadRow = table.querySelector('thead tr');
        if (!theadRow) {
            return;
        }
        const thMap = {};
        theadRow.querySelectorAll('th[data-col]').forEach(function (th) {
            thMap[th.getAttribute('data-col')] = th;
        });
        orderKeys.forEach(function (k) {
            if (thMap[k]) {
                theadRow.appendChild(thMap[k]);
            }
        });
        function reorderRow(tr) {
            const tdMap = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                tdMap[td.getAttribute('data-col')] = td;
            });
            orderKeys.forEach(function (k) {
                if (tdMap[k]) {
                    tr.appendChild(tdMap[k]);
                }
            });
        }
        table.querySelectorAll('tbody tr').forEach(reorderRow);
        table.querySelectorAll('tfoot tr').forEach(reorderRow);
    }

    function loadAndApplyColumnOrder(tableId, lsKey, defaultKeys) {
        let keys = defaultKeys.slice();
        try {
            const raw = localStorage.getItem(lsKey);
            if (raw) {
                const saved = JSON.parse(raw);
                const currentKeys = getColumnKeysFromTable(tableId);
                keys = normalizeColumnOrder(saved, currentKeys);
            }
        } catch (e) {}
        applyColumnOrderToTable(tableId, keys);
        return keys;
    }

    function syncDataRowsToHeaderOrder(tableId) {
        const orderKeys = getColumnKeysFromTable(tableId);
        applyColumnOrderToTable(tableId, orderKeys);
    }

    function persistInwardVisibility() {
        const map = {};
        inwardColumnDefinitions.forEach(function (col) {
            const $cb = $('#inward_col_' + col.key);
            if ($cb.length) {
                map[col.key] = $cb.is(':checked');
            } else {
                map[col.key] = !!col.visible;
            }
        });
        try {
            localStorage.setItem(LS_SH_INWARD_VIS, JSON.stringify(map));
        } catch (e) {}
    }

    function persistOutwardVisibility() {
        const map = {};
        outwardColumnDefinitions.forEach(function (col) {
            const $cb = $('#outward_col_' + col.key);
            if ($cb.length) {
                map[col.key] = $cb.is(':checked');
            } else {
                map[col.key] = !!col.visible;
            }
        });
        try {
            localStorage.setItem(LS_SH_OUTWARD_VIS, JSON.stringify(map));
        } catch (e) {}
    }

    function saveColumnOrderKey(tableId, lsKey) {
        try {
            localStorage.setItem(lsKey, JSON.stringify(getColumnKeysFromTable(tableId)));
        } catch (e) {}
    }

    function prependDragHandles(tableId) {
        $('#' + tableId + ' thead th[data-col]').each(function () {
            const $th = $(this);
            if ($th.find('.stock-history-drag-handle').length) {
                return;
            }
            $th.prepend('<span class="stock-history-drag-handle" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>');
        });
    }

    // Initialize column dropdown for Inward Stock
    function initInwardColumnDropdown() {
        const columnsList = $('#inwardColumnsList');
        columnsList.empty();

        inwardColumnDefinitions.forEach(col => {
            if (col.key !== 'image') {
                const checked = col.visible ? 'checked' : '';
                const item = $(`
                    <div class="columns-dropdown-item">
                        <input type="checkbox" id="inward_col_${col.key}" data-col="${col.key}" ${checked}>
                        <label for="inward_col_${col.key}">${col.label}</label>
                    </div>
                `);
                columnsList.append(item);
            }
        });

        $('#inwardColumnSearch').off('input.sh').on('input.sh', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('#inwardColumnsList .columns-dropdown-item').each(function() {
                const label = $(this).find('label').text().toLowerCase();
                $(this).toggle(label.includes(searchTerm));
            });
        });

        $('#inwardColumnsList input[type="checkbox"]').off('change.sh').on('change.sh', function() {
            const colKey = $(this).data('col');
            const isVisible = $(this).is(':checked');
            toggleColumnVisibility('inward', colKey, isVisible);
            persistInwardVisibility();
        });
    }

    // Initialize column dropdown for Outward Stock
    function initOutwardColumnDropdown() {
        const columnsList = $('#outwardColumnsList');
        columnsList.empty();

        outwardColumnDefinitions.forEach(col => {
            if (col.key !== 'image') {
                const checked = col.visible ? 'checked' : '';
                const item = $(`
                    <div class="columns-dropdown-item">
                        <input type="checkbox" id="outward_col_${col.key}" data-col="${col.key}" ${checked}>
                        <label for="outward_col_${col.key}">${col.label}</label>
                    </div>
                `);
                columnsList.append(item);
            }
        });

        $('#outwardColumnSearch').off('input.sh').on('input.sh', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('#outwardColumnsList .columns-dropdown-item').each(function() {
                const label = $(this).find('label').text().toLowerCase();
                $(this).toggle(label.includes(searchTerm));
            });
        });

        $('#outwardColumnsList input[type="checkbox"]').off('change.sh').on('change.sh', function() {
            const colKey = $(this).data('col');
            const isVisible = $(this).is(':checked');
            toggleColumnVisibility('outward', colKey, isVisible);
            persistOutwardVisibility();
        });
    }

    // Toggle column visibility (includes footer row)
    function toggleColumnVisibility(tableType, colKey, isVisible) {
        const tableId = tableType === 'inward' ? '#inwardTable' : '#outwardTable';
        const headerCells = $(tableId).find(`thead th[data-col="${colKey}"]`);
        const bodyCells = $(tableId).find(`tbody td[data-col="${colKey}"]`);
        const footerCells = $(tableId).find(`tfoot td[data-col="${colKey}"]`);

        if (isVisible) {
            headerCells.css('display', '');
            bodyCells.css('display', '');
            footerCells.css('display', '');
        } else {
            headerCells.css('display', 'none');
            bodyCells.css('display', 'none');
            footerCells.css('display', 'none');
        }
    }

    // Toggle dropdown
    $('#inwardColumnSettingsBtn').on('click', function(e) {
        e.stopPropagation();
        $('#inwardColumnsDropdown').toggleClass('show');
        $('#outwardColumnsDropdown').removeClass('show');
    });

    $('#outwardColumnSettingsBtn').on('click', function(e) {
        e.stopPropagation();
        $('#outwardColumnsDropdown').toggleClass('show');
        $('#inwardColumnsDropdown').removeClass('show');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.panel-header-actions').length) {
            $('.columns-dropdown').removeClass('show');
        }
    });

    const inwardDefaultOrder = inwardColumnDefaults.map(function (c) { return c.key; });
    const outwardDefaultOrder = outwardColumnDefaults.map(function (c) { return c.key; });
    loadAndApplyColumnOrder('inwardTable', LS_SH_INWARD_ORDER, inwardDefaultOrder);
    loadAndApplyColumnOrder('outwardTable', LS_SH_OUTWARD_ORDER, outwardDefaultOrder);

    prependDragHandles('inwardTable');
    prependDragHandles('outwardTable');

    initInwardColumnDropdown();
    initOutwardColumnDropdown();

    inwardColumnDefinitions.forEach(col => {
        toggleColumnVisibility('inward', col.key, !!col.visible);
    });

    outwardColumnDefinitions.forEach(col => {
        toggleColumnVisibility('outward', col.key, !!col.visible);
    });

    if (typeof Sortable !== 'undefined') {
        var inwardTheadRow = document.querySelector('#inwardTable thead tr');
        if (inwardTheadRow) {
            Sortable.create(inwardTheadRow, {
                animation: 150,
                handle: '.stock-history-drag-handle',
                draggable: 'th[data-col]',
                filter: '.sort-arrows, .sort-arrows *',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    syncDataRowsToHeaderOrder('inwardTable');
                    saveColumnOrderKey('inwardTable', LS_SH_INWARD_ORDER);
                }
            });
        }
        var outwardTheadRow = document.querySelector('#outwardTable thead tr');
        if (outwardTheadRow) {
            Sortable.create(outwardTheadRow, {
                animation: 150,
                handle: '.stock-history-drag-handle',
                draggable: 'th[data-col]',
                filter: '.sort-arrows, .sort-arrows *',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    syncDataRowsToHeaderOrder('outwardTable');
                    saveColumnOrderKey('outwardTable', LS_SH_OUTWARD_ORDER);
                }
            });
        }
    }
});
</script>

</body>
</html>

