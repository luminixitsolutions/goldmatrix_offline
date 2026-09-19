<?php
/**
 * Barcode Setting toolbox — extra fields from Extra Fields page (metal-wise).
 * Expects $barcode_extra_fields as list of auragold_extra_field_row_from_db rows.
 */
if (!isset($barcode_extra_fields) || !is_array($barcode_extra_fields)) {
    $barcode_extra_fields = [];
}
$barcode_extra_fields = array_values(array_filter($barcode_extra_fields, static function ($ef) {
    if (!is_array($ef)) {
        return false;
    }
    $id = (int) ($ef['id'] ?? 0);
    $label = trim((string) ($ef['display_name'] ?? ''));

    return $id > 0 && $label !== '' && (int) ($ef['status'] ?? 0) === 1;
}));
if ($barcode_extra_fields === []) {
    return;
}
?>
                    <div class="toolbox-fields-divider">Extra columns</div>
<?php foreach ($barcode_extra_fields as $ef):
    $ef_id = (int) ($ef['id'] ?? 0);
    $ef_label = trim((string) ($ef['display_name'] ?? ''));
    $ef_key = function_exists('auragold_barcode_extra_field_key')
        ? auragold_barcode_extra_field_key($ef_id)
        : ('ExtraField_' . $ef_id);
    $ef_search = strtolower($ef_label . ' extra field extra-field ef_' . $ef_id);
?>
                    <div class="toolbox-field-item toolbox-field-extra" data-field="<?php echo htmlspecialchars($ef_key, ENT_QUOTES, 'UTF-8'); ?>" data-extra-field-id="<?php echo $ef_id; ?>" data-search="<?php echo htmlspecialchars($ef_search, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ef_label, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endforeach; ?>
