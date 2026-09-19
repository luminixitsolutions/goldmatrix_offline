<?php
/**
 * Jewellery catalogue Design No. dropdown in product selection modal (all transaction vouchers).
 * Include after footer-script.php and product-modal-add-item-common.js.
 */
if (!empty($GLOBALS['auragold_product_modal_catalog_design_assets_loaded'])) {
    return;
}
$jsPath = dirname(__DIR__) . '/assets/js/product-modal-catalog-design-no.js';
if (!is_file($jsPath)) {
    return;
}
$GLOBALS['auragold_product_modal_catalog_design_assets_loaded'] = true;
$jsVer = (int) @filemtime($jsPath);
?>
<script src="assets/js/product-modal-catalog-design-no.js?v=<?php echo $jsVer; ?>"></script>
