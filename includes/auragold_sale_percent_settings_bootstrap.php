<?php

/**
 * Expose sale percent master rules to voucher / product modal JavaScript.
 */
if (!empty($GLOBALS['auragold_sale_percent_settings_bootstrapped'])) {
    return;
}
$GLOBALS['auragold_sale_percent_settings_bootstrapped'] = true;

if (!function_exists('auragold_sale_percent_settings_client_map')) {
    require_once __DIR__ . '/auragold_sale_percent_settings.php';
}

$auragold_sale_percent_client_map = ['by_metal' => [], 'by_product' => []];
if (isset($conn) && $conn instanceof mysqli) {
    if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
        auragold_ensure_branch_id_on_settings_tables($conn);
    }
    auragold_ensure_tbl_sale_percent_settings($conn);
    $auragold_sale_percent_client_map = auragold_sale_percent_settings_client_map(
        $conn,
        function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0
    );
}

$ef_json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<script>
window.AURAGOLD_SALE_PERCENT_SETTINGS = <?php echo json_encode($auragold_sale_percent_client_map, $ef_json_flags); ?>;
</script>
