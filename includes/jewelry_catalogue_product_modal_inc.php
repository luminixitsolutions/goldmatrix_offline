<?php
/**
 * Product selection modal assets for jewelry-catalogue-create.php (sale-invoice style).
 * Expects: $conn, optional $jcc_metals (id + name).
 */

$metals_sql_suffix = '';
if (function_exists('auragold_master_list_sql_suffix')) {
    $metals_sql_suffix = auragold_master_list_sql_suffix($conn, 'tbl_metal');
}
$metals = getList(
    'SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ' . $metals_sql_suffix . ' ORDER BY id ASC'
);
if (!is_array($metals)) {
    $metals = [];
}
if (count($metals) > 1) {
    $seen_metal_tab = [];
    $metals_dedup = [];
    foreach ($metals as $mrow) {
        $dn = strtolower(trim((string) ($mrow['display_name'] ?? '')));
        if ($dn === '' || isset($seen_metal_tab[$dn])) {
            continue;
        }
        $seen_metal_tab[$dn] = true;
        $metals_dedup[] = $mrow;
    }
    $metals = $metals_dedup;
}

if (!isset($carats)) {
    $carat_suffix = function_exists('auragold_master_list_sql_suffix')
        ? auragold_master_list_sql_suffix($conn, 'tbl_carat') : '';
    $carats = getList('SELECT id, name, purity, description FROM tbl_carat WHERE status = 1 ' . $carat_suffix . ' ORDER BY id ASC') ?: [];
}
if (!isset($locations)) {
    $locations = getList('SELECT id, name FROM tbl_location WHERE status = 1 ORDER BY id ASC') ?: [];
}
if (!isset($categories)) {
    $categories = getList('SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC') ?: [];
}

$jcc_empty_row_path = __DIR__ . '/fragments/product_modal_empty_row_inner.html';
$jcc_empty_row_html = '';
if (is_file($jcc_empty_row_path)) {
    $jcc_empty_row_html = trim((string) file_get_contents($jcc_empty_row_path));
}

if (!isset($auragold_voucher_runtime_client)) {
    require_once __DIR__ . '/auragold_voucher_runtime_settings.php';
    $auragold_voucher_runtime_client = auragold_voucher_runtime_bootstrap($conn, $metals, 'Jewelry Catalogue');
}

?>
<?php include __DIR__ . '/../footer-script.php'; ?>

<?php
$common_modal_show_images_column = false;
$common_modal_show_add_product_icon = true;
include __DIR__ . '/common-modal.php';
?>

<?php require __DIR__ . '/voucher_diamond_stone_assets.php'; ?>

<script>
window.carats = <?php echo json_encode($carats, JSON_UNESCAPED_UNICODE); ?>;
window.locations = <?php echo json_encode($locations, JSON_UNESCAPED_UNICODE); ?>;
window.categories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;
window.metals = <?php echo json_encode($metals, JSON_UNESCAPED_UNICODE); ?>;
window.JCC_MODAL_EMPTY_ROW_HTML = <?php echo json_encode($jcc_empty_row_html, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="assets/js/product-modal-add-item-common.js"></script>
<script src="assets/js/product-search-modal-common.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/product-search-modal-common.js'); ?>"></script>
<script src="assets/js/product-list-table-shared.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/product-list-table-shared.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<?php include __DIR__ . '/auragold_voucher_runtime_scripts.php'; ?>
<script src="assets/js/jewelry-catalogue-create-modal.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/jewelry-catalogue-create-modal.js'); ?>"></script>
