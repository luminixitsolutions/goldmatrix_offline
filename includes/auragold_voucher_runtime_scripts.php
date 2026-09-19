<?php
/**
 * Echo after footer-script / jQuery. Requires $auragold_voucher_runtime_client (array) set by auragold_voucher_runtime_bootstrap() or payload_only().
 */
if (!isset($auragold_voucher_runtime_client) || !is_array($auragold_voucher_runtime_client)) {
    $auragold_voucher_runtime_client = [];
}
require_once __DIR__ . '/auragold_extra_fields_product_modal_bootstrap.php';
require_once __DIR__ . '/auragold_sale_percent_settings_bootstrap.php';
require_once __DIR__ . '/auragold_product_modal_catalog_design_assets.php';
$prd_detail_js = __DIR__ . '/../assets/js/product-modal-row-detail-view.js';
$prd_detail_ver = is_file($prd_detail_js) ? (int) filemtime($prd_detail_js) : time();
$prd_search_js = __DIR__ . '/../assets/js/product-search-modal-common.js';
$prd_search_ver = is_file($prd_search_js) ? (int) filemtime($prd_search_js) : time();
?>
<script src="assets/js/product-modal-row-detail-view.js?v=<?php echo $prd_detail_ver; ?>"></script>
<script src="assets/js/product-search-modal-common.js?v=<?php echo $prd_search_ver; ?>"></script>
<script src="assets/js/auragold-voucher-runtime-apply.js"></script>
<script>
window.AURAGOLD_VOUCHER_RUNTIME = <?php echo json_encode($auragold_voucher_runtime_client, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
document.addEventListener('DOMContentLoaded', function () {
    if (window.AuragoldVoucherRuntime && typeof window.AuragoldVoucherRuntime.applyTransactionVoucherRuntime === 'function') {
        window.AuragoldVoucherRuntime.applyTransactionVoucherRuntime(window.AURAGOLD_VOUCHER_RUNTIME || {});
    }
    if (typeof window.openProductRowDetailModal === 'function') {
        window.editProductRowInTable = function (rowElement) {
            window.openProductRowDetailModal(rowElement);
        };
    }
});
</script>
