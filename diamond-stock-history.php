<?php
session_start();
require_once 'config.php';

/**
 * Carat for diamond/stone rows: master carat or derived from pure weight (matches diamond-stone-analysis).
 */
function diamond_stock_history_carat_display(array $row) {
    $pc = isset($row['characteristic_carat']) ? (float) $row['characteristic_carat'] : 0;
    if ($pc > 0) {
        return $pc;
    }
    $pure = (float) ($row['pure_wt'] ?? 0);
    if (abs($pure) > 0.000001) {
        return $pure / 0.2;
    }
    return 0.0;
}

// Reuse journal image helper name from stock-history (optional for future)
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
    return '';
}

$stock_id = isset($_GET['stock_id']) ? (int) $_GET['stock_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$characteristic_id = isset($_GET['characteristic_id']) ? (int) $_GET['characteristic_id'] : 0;

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
    $bc_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $adv_barcode)) . '%';
    $adv_where_append .= " AND (
        (s.barcode IS NOT NULL AND TRIM(s.barcode) != '' AND s.barcode LIKE '$bc_like')
        OR EXISTS (SELECT 1 FROM tbl_stock_journal sj_advbc WHERE sj_advbc.product_id = s.product_id AND sj_advbc.status = 'active' AND sj_advbc.barcode IS NOT NULL AND TRIM(sj_advbc.barcode) != '' AND sj_advbc.barcode LIKE '$bc_like')
        OR EXISTS (SELECT 1 FROM tbl_purchase_invoice_items pii_advbc WHERE pii_advbc.product_id = s.product_id AND pii_advbc.status = 1 AND pii_advbc.barcode IS NOT NULL AND TRIM(pii_advbc.barcode) != '' AND pii_advbc.barcode LIKE '$bc_like')
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

$inward_page = isset($_GET['inward_page']) ? (int) $_GET['inward_page'] : 1;
$inward_per_page = isset($_GET['inward_per_page']) ? (int) $_GET['inward_per_page'] : 10;
$inward_offset = ($inward_page - 1) * $inward_per_page;

$outward_page = isset($_GET['outward_page']) ? (int) $_GET['outward_page'] : 1;
$outward_per_page = isset($_GET['outward_per_page']) ? (int) $_GET['outward_per_page'] : 10;
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
$diamond_history_href = 'diamond-stock-history.php' . (count($sh_q) ? '?' . http_build_query($sh_q) : '');

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
$diamond_history_clear_href = 'diamond-stock-history.php' . (count($sh_q_clear) ? '?' . http_build_query($sh_q_clear) : '');

$diamond_current_tab_q = ['tab' => 'current-stock'];
if ($product_id > 0) {
    $diamond_current_tab_q['product_id'] = $product_id;
}
if ($characteristic_id > 0) {
    $diamond_current_tab_q['characteristic_id'] = $characteristic_id;
}
$diamond_current_stock_href = 'diamond-stone-analysis.php?' . http_build_query($diamond_current_tab_q);

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

// Diamond & Stones metal scope only (same as diamond-stone-analysis.php)
$scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND display_name = 'Diamond & Stones' ORDER BY display_name ASC");
$scope_metal_ids = array_map('intval', array_column($scope_metals ?: [], 'id'));
if (empty($scope_metal_ids)) {
    $scope_metal_ids = [4];
}

/* Include jobwork diamond transfer balance rows so Add Weight / department transfers appear as inward. */
$inward_where = "s.status = 1 AND s.stock_type IN ('opening', 'purchase', 'balance')";
if ($product_id > 0) {
    $inward_where .= " AND s.product_id = $product_id";
}
if ($characteristic_id > 0) {
    $inward_where .= " AND s.product_characteristic_id = $characteristic_id";
}
$inward_where .= $adv_where_append;
$stock_history_metal_scope_sql = ' AND s.metal_id IN (' . implode(',', array_map('intval', $scope_metal_ids)) . ')';
$inward_where .= $stock_history_metal_scope_sql;

require_once __DIR__ . '/includes/stock_history_queries.php';

// Carat totals (derived per row)
$diamond_inward_carat_sum = 0;
foreach ($inward_totals_all as $_r) {
    $diamond_inward_carat_sum += diamond_stock_history_carat_display($_r);
}
$diamond_outward_carat_sum = 0;
foreach ($outward_totals_all as $_r) {
    $diamond_outward_carat_sum += diamond_stock_history_carat_display($_r);
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Diamond &amp; Stone Stock History - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
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

/* Match stock-history.php — navy #11294b + gold accents */
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

.stock-history-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 20px;
    height: calc(100vh - 200px);
    overflow: hidden;
}

@media (max-width: 1100px) {
    .stock-history-wrapper {
        grid-template-columns: 1fr;
        height: auto;
        min-height: 400px;
    }
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
    text-decoration: none;
    display: inline-block;
}

.history-table .view-btn:hover {
    background: #fdf8f0;
    border-color: #11294b;
    color: #0a1f38;
}

.history-table .history-invoice-link {
    color: #11294b;
    font-weight: 600;
    text-decoration: none;
}

.history-table tfoot tr {
    background: #f5f0e6 !important;
    border-top: 2px solid #c9a962;
}

.history-table tfoot td {
    font-weight: 600;
    color: #11294b;
}

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
<div class="page-loader"><div class="bg-primary"></div></div>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <div class="app-brand demo">
                <span class="app-brand-logo demo"><img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid"></span>
                <a href="index-2.html" class="app-brand-text demo sidenav-text font-weight-normal ml-2">Empire</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto"><i class="ion ion-md-menu align-middle"></i></a>
            </div>
            <div class="sidenav-divider mt-0"></div>
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item active">
                    <a href="billing-sales-invoice.html" class="sidenav-link"><i class="sidenav-icon feather icon-file-text"></i><div>Sales Invoice</div></a>
                </li>
            </ul>
        </div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index-2.html" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo"><img src="assets/img/logo-dark.png" alt="Brand Logo" class="img-fluid"></span>
                    <span class="app-brand-text demo font-weight-normal ml-2">Empire</span>
                </a>
                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:"><i class="ion ion-md-menu text-large align-middle"></i></a>
                </div>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#layout-navbar-collapse"><span class="navbar-toggler-icon"></span></button>
                <div class="navbar-collapse collapse" id="layout-navbar-collapse">
                    <div class="navbar-nav align-items-lg-center ml-auto">
                        <div class="demo-navbar-notifications nav-item dropdown mr-lg-3">
                            <a class="nav-link dropdown-toggle hide-arrow" href="#" data-toggle="dropdown"><i class="feather icon-bell navbar-icon align-middle"></i><span class="badge badge-danger badge-dot indicator"></span></a>
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
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php'; ?>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card mb-4" style="height: calc(100vh - 120px); display: flex; flex-direction: column; overflow: hidden;">
                                <div class="card-body" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">

                                    <div class="tabs-container">
                                        <ul class="tabs-list">
                                            <li>
                                                <a href="<?= htmlspecialchars($diamond_current_stock_href, ENT_QUOTES, 'UTF-8') ?>" class="tab-link">Diamond Current Stock</a>
                                            </li>
                                            <li>
                                                <a href="<?= htmlspecialchars($diamond_history_href, ENT_QUOTES, 'UTF-8') ?>" class="tab-link active">Diamond Stock Availability (Wt)</a>
                                            </li>
                                        </ul>
                                        <div class="tabs-toolbar-actions">
                                            <button type="button" class="btn-icon stock-history-filter-btn" title="Advance Filter" data-toggle="modal" data-target="#diamondStockAdvFilterModal">
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

                                    <div class="stock-history-wrapper">

                                        <div class="stock-panel">
                                            <div class="panel-header">
                                                <div class="panel-header-main">
                                                    <span class="panel-title">Inward Stock</span>
                                                    <span class="panel-product-name" title="<?= htmlspecialchars($selected_product_label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($selected_product_label) ?></span>
                                                </div>
                                                <div class="panel-header-actions" style="position: relative;">
                                                    <i class="feather icon-settings gear-icon" id="inwardColumnSettingsBtn" title="Column Settings"></i>
                                                    <div class="columns-dropdown" id="inwardColumnsDropdown">
                                                        <div class="columns-dropdown-header">Columns</div>
                                                        <div class="columns-dropdown-search">
                                                            <input type="text" id="inwardColumnSearch" placeholder="Search columns...">
                                                        </div>
                                                        <div class="columns-dropdown-list" id="inwardColumnsList"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="panel-table-container">
                                                <table class="table history-table" id="inwardTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="width:52px;min-width:52px;" data-col="image">Photo</th>
                                                            <th class="text-center" style="min-width:72px;" data-col="action">Action</th>
                                                            <th data-col="date" style="min-width:96px;">Date</th>
                                                            <th data-col="product_name" style="min-width:120px;">Product Name</th>
                                                            <th data-col="type_of_voucher" style="min-width:110px;">Voucher Type</th>
                                                            <th data-col="invoice_no" style="min-width:100px;">Invoice No.</th>
                                                            <th data-col="against_invoice" style="min-width:120px;">Against Invoice No</th>
                                                            <th data-col="net_wt" style="min-width:88px;">Net Wt.</th>
                                                            <th data-col="gross_wt" style="min-width:88px;">Gross Wt</th>
                                                            <th data-col="carat" style="min-width:72px;">Carat</th>
                                                            <th data-col="branch" style="min-width:120px;">Branch Name</th>
                                                            <th data-col="qty" style="min-width:56px;">Qty.</th>
                                                            <th data-col="article" style="min-width:90px;">Article</th>
                                                            <th data-col="barcode" style="min-width:110px;">Barcode</th>
                                                            <th data-col="purchase_amount" style="min-width:110px;">Purchase Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
<?php if (!empty($inward_data)): ?>
<?php foreach ($inward_data as $row):
    $bc_raw = trim((string) ($row['barcode'] ?? ''));
    $attach_item_id = (int) ($row['sj_attach_item_id'] ?? 0);
    $attach_rel = stock_history_journal_image_path($conn, $attach_item_id, $bc_raw);
    $attach_href = $attach_rel !== '' ? htmlspecialchars($attach_rel, ENT_QUOTES, 'UTF-8') : '';
    $invoice_id = (int) ($row['invoice_id'] ?? 0);
    $view_url = $invoice_id > 0 ? 'purchase-invoice.php?id=' . $invoice_id : '#';
    $date = !empty($row['transaction_date']) ? date('d-m-Y', strtotime($row['transaction_date'])) : '';
    $voucher_type = ucfirst(str_replace('_', ' ', (string) ($row['type_of_voucher'] ?? '')));
    $nw = (float) ($row['net_wt'] ?? 0);
    $gw = (float) ($row['gross_wt'] ?? 0);
    $carat = diamond_stock_history_carat_display($row);
    $br = htmlspecialchars($row['branch_name'] ?? '');
    $qty = (float) ($row['qty'] ?? 0);
    $art = htmlspecialchars($row['article'] ?? '');
    $bc = htmlspecialchars($bc_raw);
    $amt = (float) ($row['amount'] ?? $row['net_amt'] ?? 0);
    $inv_no = trim((string) ($row['invoice_no'] ?? ''));
    $against_inv = trim((string) ($row['against_invoice_no'] ?? ''));
    $pn = htmlspecialchars($row['product_name'] ?? '');
?>
                                                        <tr>
                                                            <td data-col="image" class="stock-history-img-cell text-center"><?php if ($attach_href !== ''): ?><a href="<?= $attach_href ?>" target="_blank" rel="noopener" class="stock-history-img-link" title="Open attachment"><img src="<?= $attach_href ?>" alt="" class="stock-history-thumb" loading="lazy"></a><?php else: ?><div class="image-placeholder"><i class="feather icon-image"></i></div><?php endif; ?></td>
                                                            <td data-col="action" style="text-align:center;"><?php if ($attach_href !== ''): ?><a href="<?= $attach_href ?>" target="_blank" rel="noopener" class="view-btn">View</a><?php elseif ($invoice_id > 0): ?><a href="<?= htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8') ?>" class="view-btn">View</a><?php else: ?><span style="color:#94a3b8;font-size:0.75rem;">—</span><?php endif; ?></td>
                                                            <td data-col="date"><?= htmlspecialchars($date) ?></td>
                                                            <td data-col="product_name"><?= $pn ?></td>
                                                            <td data-col="type_of_voucher"><?= htmlspecialchars($voucher_type) ?></td>
                                                            <td data-col="invoice_no"><?= $inv_no !== '' ? htmlspecialchars($inv_no) : '<span style="color:#94a3b8;font-size:0.75rem;">—</span>' ?></td>
                                                            <td data-col="against_invoice"><?= $against_inv !== '' ? '<span class="history-invoice-link">'.htmlspecialchars($against_inv).'</span>' : '<span style="color:#94a3b8;font-size:0.75rem;">—</span>' ?></td>
                                                            <td data-col="net_wt"><?= number_format($nw, 3) ?></td>
                                                            <td data-col="gross_wt"><?= number_format($gw, 3) ?></td>
                                                            <td data-col="carat"><?= number_format($carat, 3) ?></td>
                                                            <td data-col="branch"><?= $br ?></td>
                                                            <td data-col="qty"><?= number_format($qty, 0) ?></td>
                                                            <td data-col="article"><?= $art ?></td>
                                                            <td data-col="barcode"><?= $bc !== '' ? $bc : '—' ?></td>
                                                            <td data-col="purchase_amount"><?= number_format($amt, 2) ?></td>
                                                        </tr>
<?php endforeach; ?>
<?php else: ?>
                                                        <tr><td colspan="15" class="text-center text-muted" style="padding:24px;">No inward stock data found.</td></tr>
<?php endif; ?>
                                                    </tbody>
<?php if (!empty($inward_data)): ?>
                                                    <tfoot>
                                                        <tr>
                                                            <td data-col="image" style="padding:8px 10px;"></td>
                                                            <td data-col="action" style="padding:8px 10px;"></td>
                                                            <td data-col="date" style="padding:8px 10px;"></td>
                                                            <td data-col="product_name" style="padding:8px 10px;"></td>
                                                            <td data-col="type_of_voucher" style="padding:8px 10px;"></td>
                                                            <td data-col="invoice_no" style="padding:8px 10px;"></td>
                                                            <td data-col="against_invoice" style="padding:8px 10px;"></td>
                                                            <td data-col="net_wt" style="padding:8px 10px;"><?= number_format($inward_totals['total_net_wt'] ?? 0, 3) ?></td>
                                                            <td data-col="gross_wt" style="padding:8px 10px;"><?= number_format($inward_totals['total_gross_wt'] ?? 0, 3) ?></td>
                                                            <td data-col="carat" style="padding:8px 10px;"><?= number_format($diamond_inward_carat_sum, 3) ?></td>
                                                            <td data-col="branch" style="padding:8px 10px;">—</td>
                                                            <td data-col="qty" style="padding:8px 10px;"><?= number_format($inward_totals['total_qty'] ?? 0, 0) ?></td>
                                                            <td data-col="article" style="padding:8px 10px;font-weight:700;">Grand Total</td>
                                                            <td data-col="barcode" style="padding:8px 10px;">—</td>
                                                            <td data-col="purchase_amount" style="padding:8px 10px;"><?= number_format($inward_totals['total_amount'] ?? 0, 2) ?></td>
                                                        </tr>
                                                    </tfoot>
<?php endif; ?>
                                                </table>
                                            </div>

                                            <div class="panel-footer">
                                                <div class="panel-footer-info">
                                                    Showing <?php if ((int) $inward_total <= 0): ?>0 to 0 of 0<?php else: ?><?= $inward_offset + 1 ?> to <?= min($inward_offset + $inward_per_page, $inward_total) ?> of <?= (int) $inward_total ?><?php endif; ?> entries
                                                </div>
                                                <div class="panel-footer-total" title="Total gross weight">
                                                    <?= number_format($inward_totals['total_gross_wt'] ?? 0, 3) ?>
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
                                                    $in_start_page = max(1, $inward_page - 2);
                                                    $in_end_page = min($inward_total_pages, $inward_page + 2);
                                                    for ($i = $in_start_page; $i <= $in_end_page; $i++) {
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

                                        <div class="stock-panel">
                                            <div class="panel-header">
                                                <div class="panel-header-main">
                                                    <span class="panel-title">Outward Stock</span>
                                                    <span class="panel-product-name" title="<?= htmlspecialchars($selected_product_label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($selected_product_label) ?></span>
                                                </div>
                                                <div class="panel-header-actions" style="position: relative;">
                                                    <i class="feather icon-settings gear-icon" id="outwardColumnSettingsBtn" title="Column Settings"></i>
                                                    <div class="columns-dropdown" id="outwardColumnsDropdown">
                                                        <div class="columns-dropdown-header">Columns</div>
                                                        <div class="columns-dropdown-search">
                                                            <input type="text" id="outwardColumnSearch" placeholder="Search columns...">
                                                        </div>
                                                        <div class="columns-dropdown-list" id="outwardColumnsList"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="panel-table-container">
                                                <table class="table history-table" id="outwardTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="width:52px;min-width:52px;" data-col="image">Photo</th>
                                                            <th class="text-center" style="min-width:72px;" data-col="action">Action</th>
                                                            <th data-col="date" style="min-width:96px;">Date</th>
                                                            <th data-col="product_name" style="min-width:120px;">Product Name</th>
                                                            <th data-col="type_of_voucher" style="min-width:110px;">Voucher Type</th>
                                                            <th data-col="invoice_no" style="min-width:100px;">Invoice No.</th>
                                                            <th data-col="against_invoice" style="min-width:120px;">Against Invoice No</th>
                                                            <th data-col="net_wt" style="min-width:88px;">Net Wt.</th>
                                                            <th data-col="gross_wt" style="min-width:88px;">Gross Wt</th>
                                                            <th data-col="carat" style="min-width:72px;">Carat</th>
                                                            <th data-col="branch" style="min-width:120px;">Branch Name</th>
                                                            <th data-col="qty" style="min-width:56px;">Qty.</th>
                                                            <th data-col="article" style="min-width:90px;">Article</th>
                                                            <th data-col="barcode" style="min-width:110px;">Barcode</th>
                                                            <th data-col="purchase_amount" style="min-width:110px;">Purchase Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
<?php if (!empty($outward_data)): ?>
<?php foreach ($outward_data as $row):
    $bc_raw = trim((string) ($row['barcode'] ?? ''));
    $attach_item_id = (int) ($row['sj_attach_item_id'] ?? 0);
    $attach_rel = stock_history_journal_image_path($conn, $attach_item_id, $bc_raw);
    $attach_href = $attach_rel !== '' ? htmlspecialchars($attach_rel, ENT_QUOTES, 'UTF-8') : '';
    $invoice_id = (int) ($row['invoice_id'] ?? 0);
    $view_url = $invoice_id > 0 ? 'purchase-invoice.php?id=' . $invoice_id : '#';
    $date = !empty($row['transaction_date']) ? date('d-m-Y', strtotime($row['transaction_date'])) : '';
    $voucher_type = ucfirst(str_replace('_', ' ', (string) ($row['type_of_voucher'] ?? '')));
    $nw = (float) ($row['net_wt'] ?? 0);
    $gw = (float) ($row['gross_wt'] ?? 0);
    $carat = diamond_stock_history_carat_display($row);
    $br = htmlspecialchars($row['branch_name'] ?? '');
    $qty = (float) ($row['qty'] ?? 0);
    $art = htmlspecialchars($row['article'] ?? '');
    $bc = htmlspecialchars($bc_raw);
    $amt = (float) ($row['amount'] ?? $row['net_amt'] ?? 0);
    $inv_no = trim((string) ($row['invoice_no'] ?? ''));
    $against_inv = trim((string) ($row['against_invoice_no'] ?? ''));
    $pn = htmlspecialchars($row['product_name'] ?? '');
?>
                                                        <tr>
                                                            <td data-col="image" class="stock-history-img-cell text-center"><?php if ($attach_href !== ''): ?><a href="<?= $attach_href ?>" target="_blank" rel="noopener" class="stock-history-img-link" title="Open attachment"><img src="<?= $attach_href ?>" alt="" class="stock-history-thumb" loading="lazy"></a><?php else: ?><div class="image-placeholder"><i class="feather icon-image"></i></div><?php endif; ?></td>
                                                            <td data-col="action" style="text-align:center;"><?php if ($attach_href !== ''): ?><a href="<?= $attach_href ?>" target="_blank" rel="noopener" class="view-btn">View</a><?php elseif ($invoice_id > 0): ?><a href="<?= htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8') ?>" class="view-btn">View</a><?php else: ?><span style="color:#94a3b8;font-size:0.75rem;">—</span><?php endif; ?></td>
                                                            <td data-col="date"><?= htmlspecialchars($date) ?></td>
                                                            <td data-col="product_name"><?= $pn ?></td>
                                                            <td data-col="type_of_voucher"><?= htmlspecialchars($voucher_type) ?></td>
                                                            <td data-col="invoice_no"><?= $inv_no !== '' ? htmlspecialchars($inv_no) : '<span style="color:#94a3b8;font-size:0.75rem;">—</span>' ?></td>
                                                            <td data-col="against_invoice"><?= $against_inv !== '' ? '<span class="history-invoice-link">'.htmlspecialchars($against_inv).'</span>' : '<span style="color:#94a3b8;font-size:0.75rem;">—</span>' ?></td>
                                                            <td data-col="net_wt"><?= number_format($nw, 3) ?></td>
                                                            <td data-col="gross_wt"><?= number_format($gw, 3) ?></td>
                                                            <td data-col="carat"><?= number_format($carat, 3) ?></td>
                                                            <td data-col="branch"><?= $br ?></td>
                                                            <td data-col="qty"><?= number_format($qty, 0) ?></td>
                                                            <td data-col="article"><?= $art ?></td>
                                                            <td data-col="barcode"><?= $bc !== '' ? $bc : '—' ?></td>
                                                            <td data-col="purchase_amount"><?= number_format($amt, 2) ?></td>
                                                        </tr>
<?php endforeach; ?>
<?php else: ?>
                                                        <tr><td colspan="15" class="text-center text-muted" style="padding:24px;">No outward stock data found.</td></tr>
<?php endif; ?>
                                                    </tbody>
<?php if (!empty($outward_data)): ?>
                                                    <tfoot>
                                                        <tr>
                                                            <td data-col="image" style="padding:8px 10px;"></td>
                                                            <td data-col="action" style="padding:8px 10px;"></td>
                                                            <td data-col="date" style="padding:8px 10px;"></td>
                                                            <td data-col="product_name" style="padding:8px 10px;"></td>
                                                            <td data-col="type_of_voucher" style="padding:8px 10px;"></td>
                                                            <td data-col="invoice_no" style="padding:8px 10px;"></td>
                                                            <td data-col="against_invoice" style="padding:8px 10px;"></td>
                                                            <td data-col="net_wt" style="padding:8px 10px;"><?= number_format($outward_totals['total_net_wt'] ?? 0, 3) ?></td>
                                                            <td data-col="gross_wt" style="padding:8px 10px;"><?= number_format($outward_totals['total_gross_wt'] ?? 0, 3) ?></td>
                                                            <td data-col="carat" style="padding:8px 10px;"><?= number_format($diamond_outward_carat_sum, 3) ?></td>
                                                            <td data-col="branch" style="padding:8px 10px;">—</td>
                                                            <td data-col="qty" style="padding:8px 10px;"><?= number_format($outward_totals['total_qty'] ?? 0, 0) ?></td>
                                                            <td data-col="article" style="padding:8px 10px;font-weight:700;">Grand Total</td>
                                                            <td data-col="barcode" style="padding:8px 10px;">—</td>
                                                            <td data-col="purchase_amount" style="padding:8px 10px;"><?= number_format($outward_totals['total_amount'] ?? 0, 2) ?></td>
                                                        </tr>
                                                    </tfoot>
<?php endif; ?>
                                                </table>
                                            </div>

                                            <div class="panel-footer">
                                                <div class="panel-footer-info">
                                                    Showing <?php if ((int) $outward_total <= 0): ?>0 to 0 of 0<?php else: ?><?= $outward_offset + 1 ?> to <?= min($outward_offset + $outward_per_page, $outward_total) ?> of <?= (int) $outward_total ?><?php endif; ?> entries
                                                </div>
                                                <div class="panel-footer-total" title="Total gross weight">
                                                    <?= number_format($outward_totals['total_gross_wt'] ?? 0, 3) ?>
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
                                                    $out_start_page = max(1, $outward_page - 2);
                                                    $out_end_page = min($outward_total_pages, $outward_page + 2);
                                                    for ($i = $out_start_page; $i <= $out_end_page; $i++) {
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
        </div>
    </div>
</div>

<div class="modal fade stock-history-adv-modal" id="diamondStockAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="diamondStockAdvFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="diamondStockAdvFilterModalLabel">Advance Filter</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="get" action="diamond-stock-history.php" id="diamondStockAdvFilterForm">
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
                    <a href="<?= htmlspecialchars($diamond_history_clear_href, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-adv-clear">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>

<script>
$(document).ready(function() {
    $('.top-navbar .dropdown-menu .dropdown-item').addClass('active');
    $('.top-navbar .mega-menu .dropdown-item').addClass('active');

    $('#inwardPerPageSelect').on('change', function() {
        const perPage = $(this).val();
        const url = new URL(window.location.href);
        url.searchParams.set('inward_per_page', perPage);
        url.searchParams.set('inward_page', '1');
        window.location.href = url.toString();
    });

    $('#outwardPerPageSelect').on('change', function() {
        const perPage = $(this).val();
        const url = new URL(window.location.href);
        url.searchParams.set('outward_per_page', perPage);
        url.searchParams.set('outward_page', '1');
        window.location.href = url.toString();
    });

    $('.page-btn[data-type="inward"]').on('click', function() {
        const page = $(this).data('page');
        if (page && !$(this).is(':disabled')) {
            const url = new URL(window.location.href);
            url.searchParams.set('inward_page', String(page));
            window.location.href = url.toString();
        }
    });

    $('.page-btn[data-type="outward"]').on('click', function() {
        const page = $(this).data('page');
        if (page && !$(this).is(':disabled')) {
            const url = new URL(window.location.href);
            url.searchParams.set('outward_page', String(page));
            window.location.href = url.toString();
        }
    });

    const diamondColumnDefinitions = [
        { key: 'image', label: 'Photo', visible: true },
        { key: 'action', label: 'Action', visible: true },
        { key: 'date', label: 'Date', visible: true },
        { key: 'product_name', label: 'Product Name', visible: true },
        { key: 'type_of_voucher', label: 'Voucher Type', visible: true },
        { key: 'invoice_no', label: 'Invoice No.', visible: true },
        { key: 'against_invoice', label: 'Against Invoice No', visible: true },
        { key: 'net_wt', label: 'Net Wt.', visible: true },
        { key: 'gross_wt', label: 'Gross Wt', visible: true },
        { key: 'carat', label: 'Carat', visible: true },
        { key: 'branch', label: 'Branch Name', visible: true },
        { key: 'qty', label: 'Qty.', visible: true },
        { key: 'article', label: 'Article', visible: true },
        { key: 'barcode', label: 'Barcode', visible: true },
        { key: 'purchase_amount', label: 'Purchase Amount', visible: true }
    ];

    function initInwardColumnDropdown() {
        const columnsList = $('#inwardColumnsList');
        columnsList.empty();
        diamondColumnDefinitions.forEach(function(col) {
            const checked = col.visible ? 'checked' : '';
            const item = $(
                '<div class="columns-dropdown-item">' +
                    '<input type="checkbox" id="inward_col_' + col.key + '" data-col="' + col.key + '" ' + checked + '>' +
                    '<label for="inward_col_' + col.key + '">' + col.label + '</label>' +
                '</div>'
            );
            columnsList.append(item);
        });

        $('#inwardColumnSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('#inwardColumnsList .columns-dropdown-item').each(function() {
                const label = $(this).find('label').text().toLowerCase();
                $(this).toggle(label.indexOf(searchTerm) !== -1);
            });
        });

        $('#inwardColumnsList input[type="checkbox"]').on('change', function() {
            const colKey = $(this).data('col');
            const isVisible = $(this).is(':checked');
            toggleColumnVisibility('inward', colKey, isVisible);
        });
    }

    function initOutwardColumnDropdown() {
        const columnsList = $('#outwardColumnsList');
        columnsList.empty();
        diamondColumnDefinitions.forEach(function(col) {
            const checked = col.visible ? 'checked' : '';
            const item = $(
                '<div class="columns-dropdown-item">' +
                    '<input type="checkbox" id="outward_col_' + col.key + '" data-col="' + col.key + '" ' + checked + '>' +
                    '<label for="outward_col_' + col.key + '">' + col.label + '</label>' +
                '</div>'
            );
            columnsList.append(item);
        });

        $('#outwardColumnSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('#outwardColumnsList .columns-dropdown-item').each(function() {
                const label = $(this).find('label').text().toLowerCase();
                $(this).toggle(label.indexOf(searchTerm) !== -1);
            });
        });

        $('#outwardColumnsList input[type="checkbox"]').on('change', function() {
            const colKey = $(this).data('col');
            const isVisible = $(this).is(':checked');
            toggleColumnVisibility('outward', colKey, isVisible);
        });
    }

    function toggleColumnVisibility(tableType, colKey, isVisible) {
        const tableId = tableType === 'inward' ? '#inwardTable' : '#outwardTable';
        const headerCells = $(tableId).find('thead th[data-col="' + colKey + '"]');
        const bodyCells = $(tableId).find('tbody td[data-col="' + colKey + '"]');
        const footerCells = $(tableId).find('tfoot td[data-col="' + colKey + '"]');
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

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.panel-header-actions').length) {
            $('.columns-dropdown').removeClass('show');
        }
    });

    initInwardColumnDropdown();
    initOutwardColumnDropdown();

    diamondColumnDefinitions.forEach(function(col) {
        toggleColumnVisibility('inward', col.key, !!col.visible);
        toggleColumnVisibility('outward', col.key, !!col.visible);
    });
});
</script>

</body>
</html>
