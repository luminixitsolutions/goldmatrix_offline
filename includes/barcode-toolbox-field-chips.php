<?php
/**
 * Barcode Setting toolbox chips — column set/order/labels match common-modal.php
 * Show/Hide Columns (modal-col-* / data-column). Set $barcode_toolbox_divider before include.
 */
if (!isset($barcode_toolbox_divider)) {
    $barcode_toolbox_divider = 'All columns';
}
require_once __DIR__ . '/barcode-toolbox-columns.php';
$barcode_toolbox_columns = auragold_barcode_toolbox_columns();
?>
                    <!-- Extra image / attachment fields (label designer; not in modal table list) -->
                    <div class="toolbox-field-item toolbox-field-image" data-field="CompanyName">CompanyName</div>
                    <div class="toolbox-field-item toolbox-field-image" data-field="CompanyLogo">CompanyLogo</div>
                    <div class="toolbox-field-item toolbox-field-image" data-field="Photo">Photo</div>
                    <div class="toolbox-field-item toolbox-field-image" data-field="ProductImage">ProductImage</div>
                    <div class="toolbox-field-item toolbox-field-image" data-field="AttachImage">AttachImage</div>
                    <div class="toolbox-field-item toolbox-field-image" data-field="ImageUrl">ImageUrl</div>
                    <div class="toolbox-field-item toolbox-field-image toolbox-field-strip" data-field="StripLine" title="Horizontal strip line">StripLine</div>
                    <div class="toolbox-field-item toolbox-field-image toolbox-field-white-strip" data-field="WhiteStrip" title="Blank white rectangular strip">White Strip</div>
                    <div class="toolbox-fields-divider"><?php echo htmlspecialchars($barcode_toolbox_divider, ENT_QUOTES, 'UTF-8'); ?></div>
                    <!-- Same order as common-modal.php table settings -->
<?php foreach ($barcode_toolbox_columns as $btc): ?>
                    <div class="toolbox-field-item" data-field="<?php echo htmlspecialchars($btc['field'], ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($btc['search'])): ?> data-search="<?php echo htmlspecialchars($btc['search'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo htmlspecialchars($btc['label'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endforeach; ?>
