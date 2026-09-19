<?php
/**
 * Emits window GST globals + loads assets/js/auragold-gst-lines.js.
 * Requires includes/auragold-gst-page-vars.php (or pre-set $auragold_* variables).
 */
if (!isset($auragold_sale_owner_state)) {
    require_once __DIR__ . '/auragold-gst-page-vars.php';
}
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
?>
<script>
window.AURAGOLD_SALE_INVOICE_OWNER_STATE = <?php echo json_encode($auragold_sale_owner_state, $json_flags); ?>;
window.ownerState = <?php echo json_encode($auragold_sale_owner_state, $json_flags); ?>;
if (typeof window.customerState === 'undefined') { window.customerState = ''; }
window.AURAGOLD_GST_STATE_BY_NAME = <?php echo json_encode($auragold_gst_state_name_to_id, $json_flags); ?>;
window.productTaxes = <?php echo json_encode($auragold_tax_master_for_js, $json_flags); ?>;
</script>
<script src="assets/js/auragold-gst-lines.js"></script>
