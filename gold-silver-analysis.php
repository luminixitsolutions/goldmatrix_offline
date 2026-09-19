<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_analysis_show_in_stock_sql.php';
require_once __DIR__ . '/includes/gold_silver_analysis_helpers.php';

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'current-stock';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if ($per_page < 10) {
    $per_page = 10;
} elseif ($per_page > 100) {
    $per_page = 100;
}
$offset = ($page - 1) * $per_page;

require_once __DIR__ . '/includes/gold_silver_analysis_roll_up_include.php';

$stock_data = [];
$total_stock = 0;
$total_pages = 1;
$totals = [];
$gsa_cs_attach_image_map = [];

// Branches for filters: login main + sub-branches (set in gold_silver_analysis_roll_up_include.php)
if (!isset($branches) || !is_array($branches)) {
    $branches = [];
}
$metals = $scope_metals;

$scope_ids_sql = implode(',', array_map('intval', $scope_metal_ids));

$gsa_filter_stock_exists_sql = '';
if (!empty($branch_ids)) {
    $gsa_filter_stock_exists_sql = ' AND s0.branch_id IN (' . implode(',', array_map('intval', $branch_ids)) . ')';
}

$gsa_pc_filter_sql = '';
if (!empty($branch_ids) && !empty($conn_master) && function_exists('getRecordMaster')) {
    if (count($branch_ids) === 1) {
        $gsa_bid = (int) $branch_ids[0];
        $gsa_br = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . $gsa_bid . ' LIMIT 1');
        if ($gsa_br && (int) ($gsa_br['main_branch_id'] ?? 0) > 0) {
            $gsa_pc_filter_sql = ' AND pc.branch_id = ' . $gsa_bid;
        } elseif ($gsa_br) {
            $gsa_pc_filter_sql = ' AND (pc.branch_id = ' . $gsa_bid . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
        }
    } else {
        $gsa_pc_filter_sql = ' AND pc.branch_id IN (' . implode(',', array_map('intval', $branch_ids)) . ')';
    }
}

// Metal lives on tbl_product_characteristics, not tbl_products (see auragold.sql).
$filter_products = getList("
    SELECT DISTINCT p.id, p.name
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1 AND pc.metal_id IN ($scope_ids_sql)
    WHERE p.status = 1 AND EXISTS (
        SELECT 1 FROM tbl_stock s0
        WHERE s0.product_id = p.id AND s0.status = 1
        AND s0.metal_id IN ($scope_ids_sql)
        AND s0.stock_type IN ('opening','purchase','stock_journal','outward','balance','inward','sale_return')
        $gsa_filter_stock_exists_sql
        AND " . auragold_sql_show_in_stock_for_product_and_stock_subquery('s0', 'p') . "
    ) $gsa_pc_filter_sql
    ORDER BY p.name ASC
");

if (!is_array($filter_products)) {
    $filter_products = [];
}

$filter_articles = getList("
    SELECT DISTINCT TRIM(p.article) AS article
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1 AND pc.metal_id IN ($scope_ids_sql)
    WHERE p.status = 1 AND EXISTS (
        SELECT 1 FROM tbl_stock s0
        WHERE s0.product_id = p.id AND s0.status = 1
        AND s0.metal_id IN ($scope_ids_sql)
        AND s0.stock_type IN ('opening','purchase','stock_journal','outward','balance','inward','sale_return')
        $gsa_filter_stock_exists_sql
        AND " . auragold_sql_show_in_stock_for_product_and_stock_subquery('s0', 'p') . "
    ) AND p.article IS NOT NULL AND TRIM(p.article) != '' $gsa_pc_filter_sql
    ORDER BY article ASC
");

if (!is_array($filter_articles)) {
    $filter_articles = [];
}
$filter_carat = getList('SELECT id, name FROM tbl_carat WHERE status = 1 ORDER BY name ASC');
if (!is_array($filter_carat)) {
    $filter_carat = [];
}
$filter_categories = getList('SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC');
if (!is_array($filter_categories)) {
    $filter_categories = [];
}

$gsa_base_q = [];
if ($search_raw !== '') {
    $gsa_base_q['search'] = $search_raw;
}
if (!empty($branch_ids)) {
    $gsa_base_q['branch'] = $branch_ids;
}
if (!empty($metal_filter_ids)) {
    $gsa_base_q['metal'] = $metal_filter_ids;
}
if ($per_page != 20) {
    $gsa_base_q['per_page'] = $per_page;
}
if ($adv_to_sql !== '') {
    $gsa_base_q['adv_to'] = $adv_to_sql;
}
if ($adv_serial !== 'both') {
    $gsa_base_q['adv_serial'] = $adv_serial;
}
if (!empty($adv_product_ids)) {
    $gsa_base_q['adv_product'] = $adv_product_ids;
}
if (!empty($adv_articles)) {
    $gsa_base_q['adv_article'] = $adv_articles;
}
if (!empty($adv_karat_ids)) {
    $gsa_base_q['adv_karat'] = $adv_karat_ids;
}
if (!empty($adv_category_ids)) {
    $gsa_base_q['adv_category'] = $adv_category_ids;
}
if ($adv_group !== '') {
    $gsa_base_q['adv_group'] = $adv_group;
}
if ($adv_gross_wt !== '') {
    $gsa_base_q['adv_gross'] = $adv_gross_wt;
}
$gsa_href_stock = 'gold-silver-analysis.php?' . http_build_query(array_merge($gsa_base_q, ['tab' => 'current-stock']));
$gsa_href_details = 'gold-silver-analysis.php?' . http_build_query(array_merge($gsa_base_q, ['tab' => 'stock-details']));
$gsa_clear_href = 'gold-silver-analysis.php?' . http_build_query(['tab' => $active_tab]);
$gsa_export_query = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';

?>

<!DOCTYPE html>

<html lang="en" class="default-style">

<head>
    <title>Gold / Silver Analysis - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Gold / Silver Analysis - Current Stock" />
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

/* ===== PAGE STYLING ===== */
.stock-analysis-wrapper {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* Tabs row + toolbar (navy #11294b + gold — same pattern as stock-history) */
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
    position: relative;
    z-index: 30;
}

.tabs-toolbar-actions .dropdown-menu {
    z-index: 2500;
    min-width: 11rem;
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

.gsa-toolbar-item {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.gsa-toolbar-item .gsa-toolbar-hint {
    font-size: 0.65rem;
    font-weight: 600;
    color: #64748b;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.gsa-filter-pill {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 2px solid #11294b;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    color: #11294b;
    cursor: pointer;
    transition: box-shadow 0.2s, border-color 0.2s, background 0.2s;
    padding: 0;
}

.gsa-filter-pill:hover {
    border-color: #c9a962;
    background: linear-gradient(180deg, #fdf8f0 0%, #f5ecd8 100%);
    box-shadow: 0 2px 8px rgba(17, 41, 75, 0.12);
}

.gsa-filter-pill .feather {
    width: 18px;
    height: 18px;
}

.gsa-col-dropdown {
    min-width: 240px;
    max-height: 320px;
    overflow-y: auto;
    padding: 8px 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(17, 41, 75, 0.12);
}

.gsa-col-dropdown .dropdown-header {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #11294b;
    padding: 8px 16px 4px;
}

.gsa-col-dropdown .gsa-col-check-label {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    font-size: 0.875rem;
    color: #334155;
    cursor: pointer;
}

.gsa-col-dropdown .gsa-col-check-label:hover {
    background: #f8fafc;
}

.gsa-col-dropdown .gsa-col-cb {
    margin-right: 10px;
    accent-color: #11294b;
}

.gsa-filter-btn {
    position: relative;
}

.gsa-filter-badge {
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

.gsa-adv-modal .modal-content {
    border: none;
    border-radius: 12px;
    overflow: visible;
    box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2);
}

.gsa-adv-modal .modal-dialog {
    max-width: 960px;
    width: calc(100vw - 32px);
}

.gsa-adv-modal .mp-ms-panel {
    z-index: 1060;
}

.gsa-adv-modal .modal-body .filter-grid {
    margin-top: 0;
}

.gsa-adv-modal .filter-grid .gsa-adv-section.filter-field-full {
    grid-column: 1 / -1;
    margin: 12px 0 4px;
    padding: 0 0 6px;
    border-bottom: 1px solid #e2e8f0;
}

.gsa-adv-modal .filter-grid .gsa-adv-section.filter-field-full:first-child {
    margin-top: 0;
}

.gsa-adv-modal .filter-field > .filter-field-label {
    margin: 0;
    color: #435474;
    font-weight: 600;
    font-size: 13px;
}

.gsa-adv-modal .modal-header.gsa-adv-modal-header {
    flex-direction: column;
    align-items: stretch;
    padding: 16px 44px 14px 20px;
    background: linear-gradient(135deg, #11294b 0%, #1a3d66 100%);
    border-bottom: 3px solid #c9a962;
}

.gsa-adv-modal .modal-title {
    width: 100%;
    text-align: center;
    font-weight: 700;
    font-size: 1.05rem;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.gsa-adv-modal .modal-title .gsa-adv-title-icon {
    width: 22px;
    height: 22px;
    stroke: #c9a962;
    color: #c9a962;
}

.gsa-adv-modal .modal-sub {
    text-align: center;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.78);
    margin: 8px 0 0;
    padding: 0;
    line-height: 1.35;
}

.gsa-adv-modal .close {
    position: absolute;
    right: 14px;
    top: 18px;
    transform: none;
    opacity: 0.85;
    color: #fff;
    text-shadow: none;
    font-size: 1.5rem;
    font-weight: 400;
}

.gsa-adv-modal .close:hover {
    opacity: 1;
    color: #c9a962;
}

.gsa-adv-modal .modal-body {
    padding: 20px 22px 12px;
    background: #fafbfc;
    overflow: visible;
}

.gsa-adv-modal .gsa-adv-section {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin: 16px 0 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid #e2e8f0;
}

.gsa-adv-modal .gsa-adv-section:first-of-type {
    margin-top: 0;
}

.gsa-adv-modal .form-control {
    border-radius: 8px;
    border-color: #cfd8e3;
    font-size: 0.875rem;
}

.gsa-adv-modal .form-control:focus {
    border-color: #c9a962;
    box-shadow: 0 0 0 0.15rem rgba(201, 169, 98, 0.25);
}

.gsa-adv-modal .modal-footer-adv {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 16px 22px 22px;
    border: none;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}

.gsa-adv-modal .btn-adv-apply {
    background: #fff;
    color: #11294b;
    border: 2px solid #11294b;
    font-weight: 600;
    padding: 8px 26px;
    border-radius: 8px;
}

.gsa-adv-modal .btn-adv-apply:hover {
    background: #f8fafc;
    color: #0a1f38;
}

.gsa-adv-modal .btn-adv-clear {
    background: #fff;
    color: #b45309;
    border: 2px solid #f5c2a7;
    font-weight: 600;
    padding: 8px 26px;
    border-radius: 8px;
}

.gsa-adv-modal .btn-adv-clear:hover {
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

/* Table Container */
.table-container {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.table-wrapper {
    flex: 1;
    overflow: auto;
    padding: 20px;
}

/* Table Styling */
.stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.stock-table thead {
    background: #f8fafc;
    position: sticky;
    top: 0;
    z-index: 10;
}

.stock-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 600;
    color: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    font-size: 0.8rem;
}

.stock-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.stock-table th.sortable:hover {
    background: #f1f5f9;
}

/* Global style.css: .table thead th { position: static !important } — Sortable handle still works inline */
.table.stock-table thead th.gsa-th-reorder {
    position: relative !important;
}

/* Gold move icon after label (Feather icon-move) */
.stock-table thead th.gsa-th-reorder .gsa-th-drag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    margin-left: 0.35rem;
    margin-right: 0.15rem;
    cursor: grab;
    color: #c9a962;
    line-height: 1;
    flex-shrink: 0;
}

.stock-table thead th.gsa-th-reorder .gsa-th-drag .feather {
    width: 0.95rem;
    height: 0.95rem;
}

.stock-table thead th.gsa-th-reorder .gsa-th-drag:active {
    cursor: grabbing;
}

.stock-table.gsa-details-table thead th.gsa-th-reorder .gsa-th-drag {
    color: #c9a962;
}

.gsa-sortable-ghost {
    opacity: 0.45;
}

.gsa-sortable-chosen {
    opacity: 0.9;
}

.stock-table.gsa-details-table {
    border: 1px solid #cfd8e3;
    box-shadow: 0 1px 3px rgba(17, 41, 75, 0.06);
}

.stock-table.gsa-details-table th,
.stock-table.gsa-details-table td {
    border-left: 1px solid #dce4ed;
    border-right: 1px solid #dce4ed;
}

.stock-table.gsa-details-table thead {
    background: #11294b;
}

.stock-table.gsa-details-table thead th {
    background: #11294b;
    color: #fff;
    border-bottom: 1px solid #c9a962;
    text-align: center;
    vertical-align: middle;
}

.stock-table.gsa-details-table thead th.gsa-text-cell {
    text-align: left;
}

.stock-table.gsa-details-table thead th.gsa-group-head {
    font-weight: 700;
    border-left: 1px solid rgba(201, 169, 98, 0.4);
}

.stock-table.gsa-details-table thead th.gsa-group-head:first-of-type {
    border-left: 1px solid rgba(201, 169, 98, 0.4);
}

.stock-table.gsa-details-table .gsa-subhead {
    font-size: 0.72rem;
    font-weight: 600;
    background: #0e2038;
    color: #f8fafc;
}

.stock-table.gsa-details-table tbody td {
    text-align: right;
    white-space: nowrap;
}

.stock-table.gsa-details-table tbody td.gsa-text-cell {
    text-align: left;
    white-space: normal;
}

.stock-table.gsa-details-table tbody td.gsa-action-cell {
    text-align: center;
}

.stock-table.gsa-details-table tfoot td {
    background: linear-gradient(180deg, #eef4fb 0%, #e3ebf5 100%);
    color: #11294b;
    font-weight: 700;
    border-top: 2px solid #c9a962;
    text-align: right;
    font-size: 0.875rem;
}

.stock-table.gsa-details-table tfoot td.gsa-text-cell {
    text-align: left;
    color: #11294b;
}

.stock-table.gsa-details-table tbody td.negative {
    color: #dc2626;
}

.stock-table.gsa-details-table tfoot td.negative {
    color: #b91c1c;
}

.stock-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.2s;
}

.stock-table tbody tr:hover {
    background: #f8fafc;
}

.stock-table td {
    padding: 12px 10px;
    color: #000;
    vertical-align: middle;
}

.stock-table td.negative {
    color: #dc2626;
    font-weight: 500;
}

.stock-table .view-history-btn {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.stock-table .view-history-btn:hover {
    background: #0a1f38;
}

.stock-table td[data-col="attach_image"] {
    width: 72px;
    max-width: 80px;
    text-align: center;
    padding: 8px 6px !important;
}

.stock-table .gsa-attach-img-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.stock-table .gsa-attach-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    display: block;
    background: #f8fafc;
}

.stock-table a.gsa-attach-img-link:hover .gsa-attach-thumb {
    border-color: #11294b;
    box-shadow: 0 0 0 1px rgba(17, 41, 75, 0.15);
}

.stock-table .gsa-attach-placeholder {
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.85rem;
    margin: 0 auto;
}

/* Table Footer */
.table-footer {
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-footer-info {
    color: #64748b;
    font-size: 0.875rem;
}

.table-footer-totals {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.table-footer-totals .total-item {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.table-footer-totals .total-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 2px;
}

.table-footer-totals .total-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #ffffff;
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
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination-controls .page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-controls .page-btn.active {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination-controls .show-all-dropdown {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.875rem;
    color: #64748b;
    background: #fff;
}

/* Filter Section */
.filter-section {
    background: #fff;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-section .form-control {
    height: 36px;
    font-size: 0.875rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px 12px;
}

.filter-section label {
    font-size: 0.875rem;
    color: #000;
    font-weight: 500;
    margin-bottom: 0;
    margin-right: 8px;
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
            <!-- Brand demo -->
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

            <!-- Links -->
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
                <!-- [ content ] Start -->
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php';?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card mb-4" style="height: calc(100vh - 120px); display: flex; flex-direction: column; overflow: hidden;">
                                <div class="card-body" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">

                                    <div class="stock-analysis-wrapper">
                                        
                                        <div class="tabs-container">
                                            <ul class="tabs-list">
                                                <li>
                                                    <a href="<?= htmlspecialchars($gsa_href_stock, ENT_QUOTES, 'UTF-8') ?>"
                                                       class="tab-link <?= $active_tab == 'current-stock' ? 'active' : '' ?>">
                                                        Current Stock
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="<?= htmlspecialchars($gsa_href_details, ENT_QUOTES, 'UTF-8') ?>"
                                                       class="tab-link <?= $active_tab == 'stock-details' ? 'active' : '' ?>">
                                                        Stock Details
                                                    </a>
                                                </li>
                                            </ul>
                                            <div class="tabs-toolbar-actions">
                                                <div class="gsa-toolbar-item">
                                                    <button type="button" class="gsa-filter-pill gsa-filter-btn" title="Advance Filter" data-toggle="modal" data-target="#gsaAdvFilterModal">
                                                        <i class="feather icon-filter"></i>
                                                        <?php if ($adv_filter_count > 0): ?>
                                                        <span class="gsa-filter-badge"><?= (int) $adv_filter_count ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                    <span class="gsa-toolbar-hint">Filter</span>
                                                </div>
                                                <div class="gsa-toolbar-item">
                                                    <button type="button" class="btn-icon" title="Refresh" onclick="location.reload();"><i class="feather icon-refresh-cw"></i></button>
                                                    <span class="gsa-toolbar-hint">Refresh</span>
                                                </div>
                                                <div class="gsa-toolbar-item">
                                                    <button type="button" class="btn-icon" title="Expand/Collapse"><i class="feather icon-maximize-2"></i></button>
                                                    <span class="gsa-toolbar-hint">Expand</span>
                                                </div>
                                                <?php if ($active_tab == 'current-stock'): ?>
                                                <div class="gsa-toolbar-item">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn-icon" id="gsaCsColPickerBtn" title="Show/Hide Columns" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="feather icon-settings"></i></button>
                                                        <div class="dropdown-menu dropdown-menu-right gsa-col-dropdown" onclick="event.stopPropagation();">
                                                            <div class="dropdown-header">Show / Hide Columns</div>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="attach_image" checked> Attach Image</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="product_name" checked> Product Name</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="metal" checked> Metal</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="qty" checked> Qty</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="gross_weight" checked> Gross Weight</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="pure_weight" checked> Pure/D.Wt.</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="article" checked> Article</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="branch_name" checked> Branch Name</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="net_wt" checked> Net Wt</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="stone_wt" checked> Stone Wt</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-cs-col-cb" data-cs-key="purchase_amount" checked> Purchase Amount</label>
                                                        </div>
                                                    </div>
                                                    <span class="gsa-toolbar-hint">Columns</span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($active_tab == 'stock-details'): ?>
                                                <div class="gsa-toolbar-item">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn-icon" id="gsaColPickerBtn" title="Columns" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="feather icon-settings"></i></button>
                                                        <div class="dropdown-menu dropdown-menu-right gsa-col-dropdown" onclick="event.stopPropagation();">
                                                            <div class="dropdown-header">Show columns</div>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="product" checked> Product</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="metal" checked> Metal</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="article" checked> Article</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="location" checked> Location</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="gross" checked> Gross Wt. (all)</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="pure" checked> Pure Wt. (all)</label>
                                                            <label class="gsa-col-check-label mb-0"><input type="checkbox" class="gsa-col-cb" data-gsa-key="pcs" checked> Pcs (all)</label>
                                                        </div>
                                                    </div>
                                                    <span class="gsa-toolbar-hint">Columns</span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($active_tab == 'stock-details'): ?>
                                                <div class="gsa-toolbar-item">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn-icon" title="Export" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="feather icon-download"></i></button>
                                                        <div class="dropdown-menu dropdown-menu-right">
                                                            <a class="dropdown-item" href="#" id="gsaExportStockDetailsExcel"><i class="feather icon-file-text text-success mr-2"></i>Excel</a>
                                                            <a class="dropdown-item" href="#" id="gsaExportStockDetailsPdf"><i class="feather icon-file text-danger mr-2"></i>PDF</a>
                                                        </div>
                                                    </div>
                                                    <span class="gsa-toolbar-hint">Export</span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Filter row: Current Stock only (Stock Details: use funnel Advance Filter) -->
                                        <?php if ($active_tab == 'current-stock'): ?>
                                        <div class="filter-section">
                                            <div class="d-flex align-items-center">
                                                <label>Search:</label>
                                                <input type="text" class="form-control" placeholder="Search products..." 
                                                       value="<?= htmlspecialchars($search_raw, ENT_QUOTES, 'UTF-8') ?>" 
                                                       id="searchInput" style="width: 250px;">
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label>Branch:</label>
                                                <?php if (!empty($gsa_sub_branch_login) && count($branches) === 1): ?>
                                                <select class="form-control" id="branchFilter" style="width: 180px;" disabled>
                                                    <option value="<?= (int) $branches[0]['id'] ?>" selected>
                                                        <?= htmlspecialchars($branches[0]['name']) ?>
                                                    </option>
                                                </select>
                                                <?php else: ?>
                                                <select class="form-control" id="branchFilter" style="width: 180px;">
                                                    <option value="0">All Branches</option>
                                                    <?php foreach($branches as $branch): ?>
                                                        <option value="<?= $branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($branch['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label>Metal:</label>
                                                <select class="form-control" id="metalFilter" style="width: 150px;">
                                                    <option value="0">All Metals</option>
                                                    <?php foreach($metals as $metal): ?>
                                                        <option value="<?= $metal['id'] ?>" <?= $metal_filter == $metal['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($metal['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <input type="hidden" id="searchInput" value="<?= htmlspecialchars($search_raw, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endif; ?>
                                      
                                        <!-- Table Container -->
                                        <div class="table-container">
                                            <div class="table-wrapper">
                                                <?php if ($active_tab == 'current-stock'): ?>
                                                <table class="table stock-table" id="gsaCurrentStockTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="gsa-th-fixed" data-col="action" style="width: 120px;">Action</th>
                                                            <th class="gsa-th-fixed" data-col="attach_image" style="width: 72px; min-width: 72px;" title="Attached photo">Attach Image</th>
                                                            <th class="sortable gsa-th-reorder" data-col="product_name" style="min-width: 150px;">
                                                                Product Name
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="metal" style="min-width: 100px;">
                                                                Metal
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="qty" style="min-width: 80px;">
                                                                Qty
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="gross_weight" style="min-width: 120px;">
                                                                Gross Weight
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="pure_weight" style="min-width: 120px;">
                                                                Pure/D.Wt.
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="article" style="min-width: 100px;">
                                                                Article
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="branch_name" style="min-width: 120px;">
                                                                Branch Name
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="net_wt" style="min-width: 100px;">
                                                                Net Wt
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="stone_wt" style="min-width: 100px;">
                                                                Stone Wt
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                            <th class="sortable gsa-th-reorder" data-col="purchase_amount" style="min-width: 130px;">
                                                                Purchase Amount
                                                                <span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="gsaTableBody">
                                                        <tr><td colspan="12" class="text-center text-muted" style="padding: 40px;">Loading stock data…</td></tr>
                                                    </tbody>
                                                </table>
                                                <?php else: ?>
                                                <table class="table stock-table gsa-details-table" id="gsaDetailsTable">
                                                    <thead>
                                                        <tr>
                                                            <th rowspan="2" data-gsa-key="product" class="sortable gsa-text-cell gsa-th-reorder" style="min-width: 140px;">Product<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                            <th rowspan="2" data-gsa-key="metal" class="sortable gsa-text-cell gsa-th-reorder" style="min-width: 90px;">Metal<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                            <th rowspan="2" data-gsa-key="article" class="sortable gsa-text-cell gsa-th-reorder" style="min-width: 100px;">Article<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                            <th rowspan="2" data-gsa-key="location" class="sortable gsa-text-cell gsa-th-reorder" style="min-width: 100px;">Location<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                            <th colspan="4" data-gsa-key="gross" class="gsa-group-head gsa-th-reorder">Gross Wt.<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                            <th colspan="4" data-gsa-key="pure" class="gsa-group-head gsa-th-reorder">Pure Wt.<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                            <th colspan="4" data-gsa-key="pcs" class="gsa-group-head gsa-th-reorder">Pcs<span class="gsa-th-drag" title="Drag to reorder columns"><i class="feather icon-move" aria-hidden="true"></i></span></th>
                                                        </tr>
                                                        <tr>
                                                            <th data-gsa-key="gross" class="gsa-subhead">Opening</th>
                                                            <th data-gsa-key="gross" class="gsa-subhead">Wt. In</th>
                                                            <th data-gsa-key="gross" class="gsa-subhead">Wt. Out</th>
                                                            <th data-gsa-key="gross" class="gsa-subhead">Closing</th>
                                                            <th data-gsa-key="pure" class="gsa-subhead">Opening</th>
                                                            <th data-gsa-key="pure" class="gsa-subhead">Wt. In</th>
                                                            <th data-gsa-key="pure" class="gsa-subhead">Wt. Out</th>
                                                            <th data-gsa-key="pure" class="gsa-subhead">Closing</th>
                                                            <th data-gsa-key="pcs" class="gsa-subhead">Opening</th>
                                                            <th data-gsa-key="pcs" class="gsa-subhead">Wt. In</th>
                                                            <th data-gsa-key="pcs" class="gsa-subhead">Wt. Out</th>
                                                            <th data-gsa-key="pcs" class="gsa-subhead">Closing</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="gsaTableBody">
                                                        <tr><td colspan="16" class="text-center text-muted" style="padding: 40px;">Loading stock data…</td></tr>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="gsa-details-tfoot-row" id="gsaDetailsTfootRow">
                                                            <td colspan="4" class="gsa-text-cell gsa-total-merge" style="font-weight: 700;">Total</td>
                                                            <td data-gsa-key="gross" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="gross" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="gross" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="gross" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pure" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pure" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pure" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pure" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pcs" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pcs" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pcs" class="gsa-tfoot-val">—</td>
                                                            <td data-gsa-key="pcs" class="gsa-tfoot-val">—</td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Table Footer -->
                                            <div class="table-footer">
                                                <div class="table-footer-info" id="gsaFooterInfo">Loading…</div>
                                                <?php if ($active_tab == 'current-stock'): ?>
                                                <div class="table-footer-totals gsa-cs-footer-totals">
                                                    <div class="total-item" data-cs-key="qty">
                                                        <span class="total-label">Qty</span>
                                                        <span class="total-value" id="gsaTotalQty">—</span>
                                                    </div>
                                                    <div class="total-item" data-cs-key="gross_weight">
                                                        <span class="total-label">Gross Weight</span>
                                                        <span class="total-value" id="gsaTotalGross">—</span>
                                                    </div>
                                                    <div class="total-item" data-cs-key="pure_weight">
                                                        <span class="total-label">Pure/D.Wt.</span>
                                                        <span class="total-value" id="gsaTotalPure">—</span>
                                                    </div>
                                                    <div class="total-item" data-cs-key="net_wt">
                                                        <span class="total-label">Net Wt</span>
                                                        <span class="total-value" id="gsaTotalNet">—</span>
                                                    </div>
                                                    <div class="total-item" data-cs-key="stone_wt">
                                                        <span class="total-label">Stone Wt</span>
                                                        <span class="total-value" id="gsaTotalStone">—</span>
                                                    </div>
                                                    <div class="total-item" data-cs-key="purchase_amount">
                                                        <span class="total-label">Purchase Amount</span>
                                                        <span class="total-value" id="gsaTotalPurchase">—</span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>

                                                <div class="pagination-controls" id="gsaPaginationNav">
                                                    <?php if ($active_tab == 'stock-details'): ?>
                                                    <span class="gsa-page-size-label" style="font-size:0.8125rem;color:#64748b;margin-right:8px;white-space:nowrap;">Show</span>
                                                    <?php endif; ?>
                                                    <select class="show-all-dropdown" id="perPageSelect" title="Page size">
                                                        <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                                                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                                                        <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                                                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                                                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                                                    </select>
                                                    <span class="gsa-page-btns" id="gsaPageBtns"></span>
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

<div class="modal fade gsa-adv-modal" id="gsaAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="gsaAdvFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header gsa-adv-modal-header position-relative">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h5 class="modal-title" id="gsaAdvFilterModalLabel">
                    <i class="feather icon-filter gsa-adv-title-icon"></i>
                    Advance Filter
                </h5>
                <p class="modal-sub mb-0">Refine Gold / Silver analysis by branch, metal, product and more.</p>
            </div>
            <form method="get" action="gold-silver-analysis.php" id="gsaAdvFilterForm">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="page" value="1">
                <?php if ($per_page != 20): ?><input type="hidden" name="per_page" value="<?= (int) $per_page ?>"><?php endif; ?>
                <div class="modal-body">
                    <div class="filter-grid">
                        <div class="gsa-adv-section filter-field-full">Date &amp; barcode</div>
                        <div class="filter-field">
                            <label for="gsa_adv_to">To Date</label>
                            <input type="date" class="form-control" id="gsa_adv_to" name="adv_to" value="<?= $adv_to_sql !== '' ? htmlspecialchars($adv_to_sql, ENT_QUOTES, 'UTF-8') : '' ?>">
                        </div>
                        <div class="filter-field">
                            <label for="gsa_adv_serial">Serialized Barcode</label>
                            <select class="form-control" id="gsa_adv_serial" name="adv_serial">
                                <option value="both" <?= $adv_serial === 'both' ? 'selected' : '' ?>>Both</option>
                                <option value="yes" <?= $adv_serial === 'yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="no" <?= $adv_serial === 'no' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>

                        <div class="gsa-adv-section filter-field-full">Branch &amp; metal</div>
                        <?php if (!empty($gsa_sub_branch_login) && !empty($gsa_sub_branch_id)): ?>
                        <div class="filter-field">
                            <label class="filter-field-label">Branch</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($branches[0]['name'] ?? ('Branch #' . (int) $gsa_sub_branch_id), ENT_QUOTES, 'UTF-8') ?>" readonly tabindex="-1">
                            <input type="hidden" name="branch[]" value="<?= (int) $gsa_sub_branch_id ?>">
                        </div>
                        <?php else: ?>
                        <div class="filter-field">
                            <label class="filter-field-label">Branch</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Branches">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Branches</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($branches as $br): ?>
                                            <label class="mp-ms-opt"><input type="checkbox" name="branch[]" value="<?= (int) $br['id'] ?>" <?= in_array((int) $br['id'], $branch_ids, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($br['name'] ?? '') ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="filter-field">
                            <label class="filter-field-label">Metal</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Metals">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Metals</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($metals as $mt): ?>
                                            <label class="mp-ms-opt"><input type="checkbox" name="metal[]" value="<?= (int) $mt['id'] ?>" <?= in_array((int) $mt['id'], $metal_filter_ids, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($mt['name'] ?? '') ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="gsa-adv-section filter-field-full">Product attributes</div>
                        <div class="filter-field">
                            <label class="filter-field-label">Product</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Products">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Products</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($filter_products as $fp): ?>
                                            <label class="mp-ms-opt"><input type="checkbox" name="adv_product[]" value="<?= (int) $fp['id'] ?>" <?= in_array((int) $fp['id'], $adv_product_ids, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($fp['name'] ?? '') ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Article</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Articles">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Articles</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($filter_articles as $fa):
                                            $art = isset($fa['article']) ? (string) $fa['article'] : '';
                                            if ($art === '') {
                                                continue;
                                            }
                                            ?>
                                            <label class="mp-ms-opt"><input type="checkbox" name="adv_article[]" value="<?= htmlspecialchars($art, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($art, $adv_articles, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($art) ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Karat</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Karat">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Karat</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($filter_carat as $fcar): ?>
                                            <label class="mp-ms-opt"><input type="checkbox" name="adv_karat[]" value="<?= (int) $fcar['id'] ?>" <?= in_array((int) $fcar['id'], $adv_karat_ids, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($fcar['name'] ?? '') ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label class="filter-field-label">Category</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Categories">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Categories</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($filter_categories as $fct): ?>
                                            <label class="mp-ms-opt"><input type="checkbox" name="adv_category[]" value="<?= (int) $fct['id'] ?>" <?= in_array((int) $fct['id'], $adv_category_ids, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($fct['name'] ?? '') ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="gsa-adv-section filter-field-full">Other</div>
                        <div class="filter-field">
                            <label for="gsa_adv_group">Group Name</label>
                            <input type="text" class="form-control" id="gsa_adv_group" name="adv_group" value="<?= htmlspecialchars($adv_group, ENT_QUOTES, 'UTF-8') ?>" placeholder="" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label for="gsa_adv_gross">Gross Wt</label>
                            <input type="text" class="form-control" id="gsa_adv_gross" name="adv_gross" value="<?= htmlspecialchars($adv_gross_wt, ENT_QUOTES, 'UTF-8') ?>" placeholder="" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-adv">
                    <button type="submit" class="btn btn-adv-apply">Apply Filter</button>
                    <a href="<?= htmlspecialchars($gsa_clear_href, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-adv-clear">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer-script.php';?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
function mpMsUpdateLabel(wrap) {
    var btn = wrap.querySelector('.mp-ms-btn');
    var list = wrap.querySelector('.mp-ms-list');
    var ph = wrap.getAttribute('data-mp-label') || 'Select';
    if (!btn || !list) return;
    var opts = list.querySelectorAll('input[type="checkbox"]');
    var checked = list.querySelectorAll('input[type="checkbox"]:checked');
    var n = checked.length;
    var total = opts.length;
    if (n === 0) {
        btn.textContent = ph;
    } else if (total && n === total) {
        btn.textContent = ph + ' (all)';
    } else {
        btn.textContent = ph + ' (' + n + ')';
    }
}

function initMpMultiSelectDropdowns(root) {
    root = root || document;
    root.querySelectorAll('[data-mp-ms]').forEach(function (wrap) {
        if (wrap._mpMsInit) return;
        wrap._mpMsInit = true;
        var btn = wrap.querySelector('.mp-ms-btn');
        var panel = wrap.querySelector('.mp-ms-panel');
        var search = wrap.querySelector('.mp-ms-search');
        var list = wrap.querySelector('.mp-ms-list');
        var allCb = wrap.querySelector('.mp-ms-check-all');

        function syncAll() {
            var opts = list.querySelectorAll('input[type="checkbox"]');
            var checked = list.querySelectorAll('input[type="checkbox"]:checked');
            if (allCb) {
                allCb.indeterminate = checked.length > 0 && checked.length < opts.length;
                allCb.checked = opts.length > 0 && checked.length === opts.length;
            }
            mpMsUpdateLabel(wrap);
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = panel.classList.contains('is-open');
            document.querySelectorAll('#gsaAdvFilterModal .mp-ms-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('#gsaAdvFilterModal .mp-ms-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (!wasOpen) {
                panel.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });

        if (allCb) {
            allCb.addEventListener('change', function () {
                var v = allCb.checked;
                list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                    if (lab.style.display === 'none') return;
                    var cb = lab.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = v;
                });
                syncAll();
            });
        }
        list.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'checkbox' && e.target !== allCb) syncAll();
        });

        if (search) {
            search.addEventListener('input', function () {
                var q = (search.value || '').toLowerCase().trim();
                list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                    var t = (lab.textContent || '').toLowerCase();
                    lab.style.display = !q || t.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }
        syncAll();
    });

    if (!document._gsaMpMsDocClick) {
        document._gsaMpMsDocClick = true;
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('#gsaAdvFilterModal .mp-ms')) return;
            document.querySelectorAll('#gsaAdvFilterModal .mp-ms-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('#gsaAdvFilterModal .mp-ms-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
        });
    }
}

$(document).ready(function() {
    var gsaAdvForm = document.getElementById('gsaAdvFilterForm');
    if (gsaAdvForm) {
        initMpMultiSelectDropdowns(gsaAdvForm);
    }

    $('#gsaAdvFilterForm').on('submit', function() {
        $(this).find('input[name="search"]').remove();
        var s = ($('#searchInput').val() || '').trim();
        if (s.length) {
            $('<input>', { type: 'hidden', name: 'search', value: s }).appendTo(this);
        }
    });

    // Search functionality
    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });

    // Filter changes
    $('#branchFilter, #metalFilter').on('change', function() {
        applyFilters();
    });

    // Per page change
    $('#perPageSelect').on('change', function() {
        applyFilters();
    });

    // Pagination + deferred table load
    var gsaState = {
        page: <?= (int) $page ?>,
        perPage: <?= (int) $per_page ?>,
        tab: <?= json_encode($active_tab) ?>,
        loading: false
    };

    function gsaBuildApiUrl(extra) {
        var params = new URLSearchParams(window.location.search);
        params.set('tab', gsaState.tab);
        params.set('page', String(gsaState.page));
        params.set('per_page', String(gsaState.perPage));
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                params.set(k, String(extra[k]));
            });
        }
        return 'ajax/get-gold-silver-analysis-data.php?' + params.toString();
    }

    function gsaPushUrl() {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', gsaState.tab);
        if (gsaState.page > 1) {
            url.searchParams.set('page', String(gsaState.page));
        } else {
            url.searchParams.delete('page');
        }
        if (gsaState.perPage !== 20) {
            url.searchParams.set('per_page', String(gsaState.perPage));
        } else {
            url.searchParams.delete('per_page');
        }
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', url.toString());
        }
    }

    function gsaFmtQty(n, decimals) {
        var x = parseFloat(n);
        if (!isFinite(x)) x = 0;
        if (x < 0) {
            return '(' + Math.abs(x).toFixed(decimals) + ')';
        }
        return x.toFixed(decimals);
    }

    function gsaRenderPagination(pg) {
        var nav = document.getElementById('gsaPageBtns');
        if (!nav) return;
        var cur = pg.page || 1;
        var totalPages = Math.max(1, pg.total_pages || 1);
        nav.innerHTML = '';

        function addBtn(html, pageNum, disabled, active) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-btn' + (active ? ' active' : '');
            btn.innerHTML = html;
            if (disabled) {
                btn.disabled = true;
            } else {
                btn.setAttribute('data-page', String(pageNum));
            }
            nav.appendChild(btn);
        }

        addBtn('<i class="feather icon-chevrons-left"></i>', 1, cur <= 1, false);
        addBtn('<i class="feather icon-chevron-left"></i>', cur - 1, cur <= 1, false);
        var start = Math.max(1, cur - 2);
        var end = Math.min(totalPages, cur + 2);
        for (var i = start; i <= end; i++) {
            addBtn(String(i), i, false, i === cur);
        }
        addBtn('<i class="feather icon-chevron-right"></i>', cur + 1, cur >= totalPages, false);
        addBtn('<i class="feather icon-chevrons-right"></i>', totalPages, cur >= totalPages, false);
    }

    function gsaApplyTotals(totals, tfootHtml) {
        if (!totals) return;
        if (gsaState.tab === 'current-stock') {
            var el;
            el = document.getElementById('gsaTotalQty');
            if (el) el.textContent = gsaFmtQty(totals.total_qty || 0, 0);
            el = document.getElementById('gsaTotalGross');
            if (el) el.textContent = gsaFmtQty(totals.total_gross_weight || 0, 3);
            el = document.getElementById('gsaTotalPure');
            if (el) el.textContent = gsaFmtQty(totals.total_pure_weight || 0, 3);
            el = document.getElementById('gsaTotalNet');
            if (el) el.textContent = gsaFmtQty(totals.total_net_weight || 0, 3);
            el = document.getElementById('gsaTotalStone');
            if (el) el.textContent = gsaFmtQty(totals.total_stone_weight || 0, 3);
            el = document.getElementById('gsaTotalPurchase');
            if (el) el.textContent = Number(totals.total_purchase_amount || 0).toFixed(2);
        } else if (tfootHtml) {
            var row = document.getElementById('gsaDetailsTfootRow');
            if (row) {
                var wrap = document.createElement('tr');
                wrap.innerHTML = '<td></td>' + tfootHtml;
                while (row.cells.length > 1) {
                    row.deleteCell(1);
                }
                for (var c = 1; c < wrap.cells.length; c++) {
                    row.appendChild(wrap.cells[c].cloneNode(true));
                }
            }
        }
    }

    function gsaLoadSummary() {
        fetch(gsaBuildApiUrl({ summary: 1 }), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    gsaApplyTotals(data.totals, data.tfoot_cells_html || '');
                }
            })
            .catch(function () {});
    }

    function gsaLoadTable(loadSummary) {
        if (gsaState.loading) return;
        gsaState.loading = true;
        var tbody = document.getElementById('gsaTableBody');
        var colspan = gsaState.tab === 'current-stock' ? 12 : 16;
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="padding:40px;">Loading stock data…</td></tr>';
        }
        var info = document.getElementById('gsaFooterInfo');
        if (info) info.textContent = 'Loading…';
        gsaPushUrl();

        fetch(gsaBuildApiUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                gsaState.loading = false;
                if (data.status !== 'success') {
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="padding:40px;">' + (data.message || 'Load failed') + '</td></tr>';
                    }
                    return;
                }
                if (tbody) tbody.innerHTML = data.tbody_html || '';
                var pg = data.pagination || {};
                if (info) {
                    if ((pg.total || 0) > 0) {
                        info.textContent = 'Showing ' + pg.range_from + ' to ' + pg.range_to + ' of ' + pg.total + ' entries';
                    } else {
                        info.textContent = 'No entries';
                    }
                }
                gsaRenderPagination(pg);
                if (loadSummary !== false) {
                    gsaLoadSummary();
                }
            })
            .catch(function () {
                gsaState.loading = false;
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="padding:40px;">Load failed</td></tr>';
                }
            });
    }

    $(document).on('click', '#gsaPageBtns .page-btn', function () {
        var page = parseInt($(this).data('page'), 10);
        if (!page || $(this).is(':disabled')) return;
        gsaState.page = page;
        gsaLoadTable(false);
    });

    gsaLoadTable(true);

    function applyFilters() {
        const url = new URL(window.location.href);
        const search = $('#searchInput').val();
        const branch = $('#branchFilter').val();
        const metal = $('#metalFilter').val();
        const perPage = $('#perPageSelect').val();
        
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        
        if (branch && branch != '0') {
            url.searchParams.set('branch', branch);
        } else {
            url.searchParams.set('branch', '0');
        }
        
        if (metal && metal != '0') {
            url.searchParams.set('metal', metal);
        } else {
            url.searchParams.delete('metal');
        }
        
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('page', 1);
        gsaState.perPage = parseInt(perPage, 10) || 20;
        gsaState.page = 1;
        
        window.location.href = url.toString();
    }

    // View History button (delegated — rows load via AJAX)
    $(document).on('click', '.view-history-btn', function() {
        const stockId = $(this).data('stock-id');
        const productId = $(this).data('product-id');
        const characteristicId = $(this).data('characteristic-id');
        const branchId = parseInt($(this).data('branch-id'), 10) || 0;
        let url = 'stock-history.php?stock_id=' + stockId;
        if (productId) url += '&product_id=' + productId;
        if (characteristicId) url += '&characteristic_id=' + characteristicId;
        if (branchId > 0) url += '&adv_branch=' + branchId;
        window.location.href = url;
    });

    (function () {
        var csTable = document.getElementById('gsaCurrentStockTable');
        if (!csTable || typeof Sortable === 'undefined') return;
        var GSA_CS_KEYS = ['action', 'attach_image', 'product_name', 'metal', 'qty', 'gross_weight', 'pure_weight', 'article', 'branch_name', 'net_wt', 'stone_wt', 'purchase_amount'];
        var GSA_CS_ORDER_LS = 'auragold_gsa_current_stock_col_order_v3';

        function getCsOrder() {
            return Array.prototype.map.call(csTable.querySelectorAll('thead th[data-col]'), function (th) {
                return th.getAttribute('data-col');
            });
        }

        function syncCsBody(order) {
            csTable.querySelectorAll('tbody tr').forEach(function (tr) {
                if (tr.cells.length === 1 && !tr.querySelector('td[data-col]')) return;
                var byCol = {};
                tr.querySelectorAll('td[data-col]').forEach(function (td) {
                    byCol[td.getAttribute('data-col')] = td;
                });
                order.forEach(function (k) {
                    if (byCol[k]) tr.appendChild(byCol[k]);
                });
            });
        }

        function applyCsOrderArray(order) {
            if (!Array.isArray(order) || order.length !== GSA_CS_KEYS.length) return;
            var need = {};
            GSA_CS_KEYS.forEach(function (k) { need[k] = 0; });
            order.forEach(function (k) {
                if (Object.prototype.hasOwnProperty.call(need, k)) need[k]++;
            });
            if (!GSA_CS_KEYS.every(function (k) { return need[k] === 1; })) return;
            if (order[0] !== 'action' || order[1] !== 'attach_image') return;
            var tr = csTable.querySelector('thead tr');
            var byCol = {};
            tr.querySelectorAll('th[data-col]').forEach(function (th) {
                byCol[th.getAttribute('data-col')] = th;
            });
            order.forEach(function (k) {
                if (byCol[k]) tr.appendChild(byCol[k]);
            });
            syncCsBody(order);
        }

        function applySavedCsOrder() {
            try {
                var j = localStorage.getItem(GSA_CS_ORDER_LS);
                if (!j) return;
                applyCsOrderArray(JSON.parse(j));
            } catch (e) {}
        }

        function saveCsOrder() {
            try {
                localStorage.setItem(GSA_CS_ORDER_LS, JSON.stringify(getCsOrder()));
            } catch (e) {}
        }

        applySavedCsOrder();
        var lastGoodCsOrder = getCsOrder().slice();

        var csTheadRow = csTable.querySelector('thead tr');
        if (!csTheadRow) return;
        Sortable.create(csTheadRow, {
            animation: 150,
            handle: '.gsa-th-drag',
            draggable: 'th.gsa-th-reorder',
            filter: '.gsa-th-fixed',
            preventOnFilter: false,
            ghostClass: 'gsa-sortable-ghost',
            chosenClass: 'gsa-sortable-chosen',
            onEnd: function () {
                var ord = getCsOrder();
                if (ord[0] !== 'action' || ord[1] !== 'attach_image') {
                    applyCsOrderArray(lastGoodCsOrder);
                    return;
                }
                syncCsBody(ord);
                saveCsOrder();
                lastGoodCsOrder = ord.slice();
            }
        });
    })();

    // Current Stock: column visibility (settings gear)
    (function () {
        var table = document.getElementById('gsaCurrentStockTable');
        if (!table || typeof jQuery === 'undefined') return;
        var $ = jQuery;
        var LS_KEY = 'gsa_cs_colvis_v3';
        var KEYS = ['attach_image', 'product_name', 'metal', 'qty', 'gross_weight', 'pure_weight', 'article', 'branch_name', 'net_wt', 'stone_wt', 'purchase_amount'];

        function applyKey(key, show) {
            var disp = show ? '' : 'none';
            $(table).find('[data-col="' + key + '"]').css('display', disp);
            $('.gsa-cs-footer-totals .total-item[data-cs-key="' + key + '"]').css('display', show ? '' : 'none');
        }

        function persist() {
            var o = {};
            KEYS.forEach(function (k) {
                var $h = $(table).find('thead th[data-col="' + k + '"]');
                o[k] = $h.length ? $h.first().is(':visible') : true;
            });
            try {
                localStorage.setItem(LS_KEY, JSON.stringify(o));
            } catch (e) {}
        }

        function load() {
            var raw = null;
            try {
                raw = localStorage.getItem(LS_KEY);
            } catch (e) {}
            var o = {};
            if (raw) {
                try {
                    o = JSON.parse(raw) || {};
                } catch (e) {
                    o = {};
                }
            }
            KEYS.forEach(function (k) {
                var on = o[k] !== false;
                applyKey(k, on);
                $('.gsa-cs-col-cb[data-cs-key="' + k + '"]').prop('checked', on);
            });
        }

        $('.gsa-cs-col-cb').on('change', function () {
            var k = $(this).attr('data-cs-key');
            if (!k) return;
            var on = $(this).prop('checked');
            // Keep at least one data column visible
            if (!on) {
                var left = KEYS.filter(function (t) {
                    if (t === k) return false;
                    return $('.gsa-cs-col-cb[data-cs-key="' + t + '"]').prop('checked');
                }).length;
                if (left === 0) {
                    $(this).prop('checked', true);
                    return;
                }
            }
            applyKey(k, on);
            persist();
        });

        $(document).on('click', '#gsaCsColPickerBtn + .gsa-col-dropdown .gsa-col-check-label, .gsa-col-dropdown .gsa-cs-col-cb', function (e) {
            e.stopPropagation();
        });

        load();
    })();

    // Stock Details: column order + visibility (settings) + footer colspan
    (function () {
        var table = document.getElementById('gsaDetailsTable');
        if (!table) return;
        var LS_KEY = 'gsa_sd_colvis_v1';
        var ORDER_LS = 'auragold_gsa_stock_details_col_order';
        var KEYS = ['product', 'metal', 'article', 'location', 'gross', 'pure', 'pcs'];
        var TEXT_KEYS = ['product', 'metal', 'article', 'location'];
        var DETAILS_ORDER_DEFAULT = ['product', 'metal', 'article', 'location', 'gross', 'pure', 'pcs'];

        function gsaSameKeySet(a, b) {
            var x = a.slice().sort().join('\0');
            var y = b.slice().sort().join('\0');
            return x === y;
        }

        function gsaValidDetailsOrder(order) {
            if (!order || order.length !== 7) return false;
            return gsaSameKeySet(order.slice(0, 4), TEXT_KEYS) && gsaSameKeySet(order.slice(4), ['gross', 'pure', 'pcs']);
        }

        function getDetailsHeaderOrder() {
            var tr1 = table.querySelector('thead tr:first-child');
            return Array.prototype.map.call(tr1.querySelectorAll('th[data-gsa-key]'), function (th) {
                return th.getAttribute('data-gsa-key');
            });
        }

        function loadSavedDetailsOrder() {
            try {
                var j = localStorage.getItem(ORDER_LS);
                if (!j) return null;
                var o = JSON.parse(j);
                if (gsaValidDetailsOrder(o)) return o;
            } catch (e) {}
            return null;
        }

        function gsaReorderDetailDataRow(tr, order7) {
            var textKeys = { product: null, metal: null, article: null, location: null };
            var buckets = { gross: [], pure: [], pcs: [] };
            tr.querySelectorAll('td[data-gsa-key]').forEach(function (td) {
                var k = td.getAttribute('data-gsa-key');
                if (Object.prototype.hasOwnProperty.call(textKeys, k)) {
                    textKeys[k] = td;
                } else if (buckets[k]) {
                    buckets[k].push(td);
                }
            });
            while (tr.firstChild) tr.removeChild(tr.firstChild);
            order7.forEach(function (k) {
                if (textKeys[k]) tr.appendChild(textKeys[k]);
                else if (buckets[k]) buckets[k].forEach(function (td) { tr.appendChild(td); });
            });
        }

        function gsaReorderDetailFootRow(tr, order7) {
            var merge = tr.querySelector('td.gsa-total-merge');
            var buckets = { gross: [], pure: [], pcs: [] };
            tr.querySelectorAll('td[data-gsa-key]').forEach(function (td) {
                var k = td.getAttribute('data-gsa-key');
                if (buckets[k]) buckets[k].push(td);
            });
            while (tr.firstChild) tr.removeChild(tr.firstChild);
            tr.appendChild(merge);
            order7.forEach(function (k) {
                if (!buckets[k]) return;
                buckets[k].forEach(function (td) { tr.appendChild(td); });
            });
        }

        function applyDetailsColumnOrder(order) {
            if (!gsaValidDetailsOrder(order)) return;
            var tr1 = table.querySelector('thead tr:first-child');
            var tr2 = table.querySelector('thead tr:nth-child(2)');
            var byKeyTh = {};
            tr1.querySelectorAll('th[data-gsa-key]').forEach(function (th) {
                byKeyTh[th.getAttribute('data-gsa-key')] = th;
            });
            order.forEach(function (k) {
                if (byKeyTh[k]) tr1.appendChild(byKeyTh[k]);
            });
            var subBy = { gross: [], pure: [], pcs: [] };
            tr2.querySelectorAll('th[data-gsa-key]').forEach(function (th) {
                var gk = th.getAttribute('data-gsa-key');
                if (subBy[gk]) subBy[gk].push(th);
            });
            while (tr2.firstChild) tr2.removeChild(tr2.firstChild);
            order.forEach(function (k) {
                if (subBy[k]) subBy[k].forEach(function (th) { tr2.appendChild(th); });
            });
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                if (tr.querySelector('td[data-gsa-key]')) gsaReorderDetailDataRow(tr, order);
            });
            var foot = table.querySelector('tfoot tr');
            if (foot) gsaReorderDetailFootRow(foot, order);
        }

        var lastDetailsOrder = loadSavedDetailsOrder() || DETAILS_ORDER_DEFAULT.slice();
        applyDetailsColumnOrder(lastDetailsOrder);
        lastDetailsOrder = getDetailsHeaderOrder().slice();

        function applyKey(key, show) {
            var disp = show ? '' : 'none';
            $(table).find('[data-gsa-key="' + key + '"]').css('display', disp);
        }

        function visibleTextCount() {
            var n = 0;
            TEXT_KEYS.forEach(function (k) {
                var $h = $(table).find('thead tr:first th[data-gsa-key="' + k + '"]');
                if ($h.length && $h.is(':visible')) n++;
            });
            return n;
        }

        function updateTotalColspan() {
            $(table).find('tfoot td.gsa-total-merge').attr('colspan', Math.max(visibleTextCount(), 1));
        }

        function persist() {
            var o = {};
            KEYS.forEach(function (k) {
                var $x = $(table).find('thead tr:first th[data-gsa-key="' + k + '"]');
                if ($x.length) {
                    o[k] = $x.first().is(':visible');
                }
            });
            try {
                localStorage.setItem(LS_KEY, JSON.stringify(o));
            } catch (e) {}
        }

        function load() {
            var raw = localStorage.getItem(LS_KEY);
            if (raw) {
                var o = {};
                try {
                    o = JSON.parse(raw) || {};
                } catch (e) {
                    o = {};
                }
                KEYS.forEach(function (k) {
                    var on = o[k] !== false;
                    applyKey(k, on);
                    $('.gsa-col-cb[data-gsa-key="' + k + '"]').prop('checked', on);
                });
            }
            updateTotalColspan();
        }

        $('.gsa-col-cb').on('change', function () {
            var k = $(this).attr('data-gsa-key');
            if (!k) return;
            var on = $(this).prop('checked');
            if (!on && TEXT_KEYS.indexOf(k) >= 0) {
                var left = TEXT_KEYS.filter(function (t) {
                    if (t === k) return false;
                    return $('.gsa-col-cb[data-gsa-key="' + t + '"]').prop('checked');
                }).length;
                if (left === 0) {
                    $(this).prop('checked', true);
                    return;
                }
            }
            applyKey(k, on);
            updateTotalColspan();
            persist();
        });

        $(document).on('click', '.gsa-col-check-label', function (e) {
            e.stopPropagation();
        });

        load();

        var tr1 = table.querySelector('thead tr:first-child');
        if (tr1 && typeof Sortable !== 'undefined') {
            Sortable.create(tr1, {
                animation: 150,
                handle: '.gsa-th-drag',
                draggable: 'th[data-gsa-key]',
                ghostClass: 'gsa-sortable-ghost',
                chosenClass: 'gsa-sortable-chosen',
                onEnd: function () {
                    var o = getDetailsHeaderOrder();
                    if (!gsaValidDetailsOrder(o)) {
                        applyDetailsColumnOrder(lastDetailsOrder);
                        return;
                    }
                    applyDetailsColumnOrder(o);
                    lastDetailsOrder = getDetailsHeaderOrder().slice();
                    try {
                        localStorage.setItem(ORDER_LS, JSON.stringify(lastDetailsOrder));
                    } catch (e) {}
                }
            });
        }
    })();
});
</script>
<?php if ($active_tab == 'stock-details'): ?>
<script>
(function () {
    if (typeof jQuery === 'undefined') {
        return;
    }
    var qs = <?php echo json_encode($gsa_export_query, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    jQuery(function ($) {
        $('#gsaExportStockDetailsExcel').on('click', function (e) {
            e.preventDefault();
            window.location.href = 'ajax/export-gold-silver-analysis-stock-details-excel.php' + (qs ? ('?' + qs) : '');
        });
        $('#gsaExportStockDetailsPdf').on('click', function (e) {
            e.preventDefault();
            window.location.href = 'ajax/export-gold-silver-analysis-stock-details-pdf.php' + (qs ? ('?' + qs) : '');
        });
    });
})();
</script>
<?php endif; ?>

</body>
</html>

