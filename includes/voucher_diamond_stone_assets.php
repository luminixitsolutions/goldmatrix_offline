<?php

/** Requires $auragold_voucher_ds_kind — modal markup + JS (after jQuery/Bootstrap). */
if (empty($auragold_voucher_ds_kind) || !is_string($auragold_voucher_ds_kind)) {
    return;
}
$auragold_vds_url_param = isset($auragold_voucher_ds_url_param) && is_string($auragold_voucher_ds_url_param) && $auragold_voucher_ds_url_param !== ''
    ? $auragold_voucher_ds_url_param
    : 'id';
$auragold_vds_db_id = isset($auragold_voucher_ds_db_id) ? (int) $auragold_voucher_ds_db_id : 0;

?>
<script>
window.AURAGOLD_VOUCHER_DS = <?php echo json_encode([
    'voucherKind' => $auragold_voucher_ds_kind,
    'urlIdParam' => $auragold_vds_url_param,
    'windowDbIdKey' => '__auragoldVoucherDbId',
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.__auragoldVoucherDbId = Math.max(parseInt(String(window.__auragoldVoucherDbId || '0'), 10) || 0, <?php echo $auragold_vds_db_id; ?>);
</script>
<?php
require __DIR__ . '/sale-order-diamond-stock-modal.php';
require __DIR__ . '/sale-order-stone-stock-modal.php';
?>
<script src="assets/js/sale-order-diamond-modal.js"></script>
<script src="assets/js/sale-order-stone-modal.js"></script>
<?php if (($auragold_voucher_ds_kind ?? '') === 'material_receive'): ?>
<script src="assets/js/material-receive-me-issued.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/material-receive-me-issued.js'); ?>"></script>
<script src="assets/js/material-receive-issued-receive.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/material-receive-issued-receive.js'); ?>"></script>
<?php endif; ?>
<script src="assets/js/auragold-voucher-diamond-stone-orderdata.js"></script>
<script src="assets/js/auragold-voucher-diamond-stone-payment-bind.js"></script>
