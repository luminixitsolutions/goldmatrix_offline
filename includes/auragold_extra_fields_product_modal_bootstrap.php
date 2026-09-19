<?php

/**
 * Product modal: load active extra fields by metal + product-modal-extra-fields.js
 * Include after product-modal-add-item-common.js (or via auragold_voucher_runtime_scripts.php).
 */
if (!empty($GLOBALS['auragold_extra_fields_product_modal_bootstrapped'])) {
    return;
}
$GLOBALS['auragold_extra_fields_product_modal_bootstrapped'] = true;

if (!function_exists('auragold_get_active_extra_fields_by_metal_map')) {
    require_once __DIR__ . '/auragold_extra_fields_schema.php';
}

$auragold_extra_fields_by_metal_pm = [];
if (isset($conn) && $conn instanceof mysqli) {
    if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
        auragold_ensure_branch_id_on_settings_tables($conn);
    }
    auragold_ensure_tbl_extra_fields($conn);
    $auragold_extra_fields_by_metal_pm = auragold_get_active_extra_fields_by_metal_map(
        $conn,
        function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0
    );
}

$ef_js_path = __DIR__ . '/../assets/js/product-modal-extra-fields.js';
$ef_js_ver = is_file($ef_js_path) ? (int) filemtime($ef_js_path) : time();
$mb_js_path = __DIR__ . '/../assets/js/product-modal-multi-barcode.js';
$mb_js_ver = is_file($mb_js_path) ? (int) filemtime($mb_js_path) : time();
$ef_json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<script>
window.AURAGOLD_EXTRA_FIELDS_BY_METAL = <?php echo json_encode($auragold_extra_fields_by_metal_pm, $ef_json_flags); ?>;
</script>
<script src="assets/js/product-modal-multi-barcode.js?v=<?php echo $mb_js_ver; ?>"></script>
<script src="assets/js/product-modal-extra-fields.js?v=<?php echo $ef_js_ver; ?>"></script>
