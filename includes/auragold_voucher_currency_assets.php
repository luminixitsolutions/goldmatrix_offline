<?php
/**
 * Echo CSS + JS boot for voucher currency rate UI.
 * Call after footer-script.php (needs DOM later; script is deferred via DOMContentLoaded).
 *
 * Expects $auragold_currency_rates_by_name and $auragold_base_currency_name
 * (from auragold_voucher_currency_boot.php) or falls back to empty.
 */
if (!isset($auragold_currency_rates_by_name) || !is_array($auragold_currency_rates_by_name)) {
    $auragold_currency_rates_by_name = isset($pi_currency_rates_by_name) && is_array($pi_currency_rates_by_name)
        ? $pi_currency_rates_by_name
        : [];
}
if (!isset($auragold_base_currency_name)) {
    $auragold_base_currency_name = isset($pi_base_currency_name) ? (string) $pi_base_currency_name : '';
}

$cssPath = __DIR__ . '/../assets/css/auragold-currency-rate-combo.css';
$jsPath = __DIR__ . '/../assets/js/auragold-voucher-currency-rate.js';
$cssV = @filemtime($cssPath) ?: time();
$jsV = @filemtime($jsPath) ?: time();
?>
<link rel="stylesheet" href="assets/css/auragold-currency-rate-combo.css?v=<?php echo (int) $cssV; ?>">
<script>
window.AURAGOLD_CURRENCY_RATES = <?php echo json_encode($auragold_currency_rates_by_name, JSON_UNESCAPED_UNICODE); ?>;
window.AURAGOLD_BASE_CURRENCY = <?php echo json_encode((string) $auragold_base_currency_name, JSON_UNESCAPED_UNICODE); ?>;
window.AURAGOLD_CURRENCY_SYMBOLS = <?php echo json_encode(isset($auragold_currency_symbols_by_name) && is_array($auragold_currency_symbols_by_name) ? $auragold_currency_symbols_by_name : [], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/auragold-voucher-currency-rate.js?v=<?php echo (int) $jsV; ?>"></script>
<?php
