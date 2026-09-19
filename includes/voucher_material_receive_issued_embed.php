<?php

/** Inline issued-reference data for Material Receive — must load before voucher_diamond_stone_assets.php */
if (($auragold_voucher_ds_kind ?? '') !== 'material_receive') {
    return;
}

$mr_embed_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

?>
<script>
window.MATERIAL_RECEIVE_ISSUED_DIAMONDS = <?php echo json_encode(
    is_array($mr_issued_diamond_rows ?? null) ? $mr_issued_diamond_rows : [],
    $mr_embed_flags
); ?>;
window.MATERIAL_RECEIVE_ISSUED_STONES = <?php echo json_encode(
    is_array($mr_issued_stone_rows ?? null) ? $mr_issued_stone_rows : [],
    $mr_embed_flags
); ?>;
window.MATERIAL_RECEIVE_ISSUED_METAL_EXCHANGE = <?php echo json_encode(
    is_array($mr_issued_metal_exchange_rows ?? null) ? $mr_issued_metal_exchange_rows : [],
    $mr_embed_flags
); ?>;
window.__materialIssueReferenceDiamondRows = window.MATERIAL_RECEIVE_ISSUED_DIAMONDS.slice();
window.__materialIssueReferenceStoneRows = window.MATERIAL_RECEIVE_ISSUED_STONES.slice();
</script>
