<?php
/**
 * Renders Currency + Rate combined control (#currency / #currencyRate).
 *
 * Optional vars:
 * - $selected_currency
 * - $auragold_saved_exchange_rate (string|float)
 * - $edit_order (array) — used when rate/currency not passed explicitly
 * - $auragold_currency_select_class (extra classes on select)
 */
if (!isset($selected_currency) || $selected_currency === '') {
    if (!empty($edit_order) && is_array($edit_order)) {
        $selected_currency = $edit_order['currency'] ?? '';
    } else {
        $selected_currency = '';
    }
}

$auragold_saved_exchange_rate = isset($auragold_saved_exchange_rate) ? (string) $auragold_saved_exchange_rate : '';
if ($auragold_saved_exchange_rate === '' && !empty($edit_order) && is_array($edit_order)) {
    if (isset($edit_order['exchange_rate']) && $edit_order['exchange_rate'] !== '' && $edit_order['exchange_rate'] !== null) {
        $auragold_saved_exchange_rate = (string) $edit_order['exchange_rate'];
    } elseif (isset($edit_order['currency_rate']) && $edit_order['currency_rate'] !== '' && $edit_order['currency_rate'] !== null) {
        $auragold_saved_exchange_rate = (string) $edit_order['currency_rate'];
    }
}
if ($auragold_saved_exchange_rate === '') {
    $auragold_saved_exchange_rate = '1';
}

$selClass = 'form-control form-control-sm auragold-currency-select';
if (!empty($auragold_currency_select_class)) {
    $selClass .= ' ' . trim((string) $auragold_currency_select_class);
}
?>
<div class="pi-currency-rate-combo">
    <select class="<?php echo htmlspecialchars($selClass, ENT_QUOTES, 'UTF-8'); ?>" id="currency" title="Currency">
        <?php include __DIR__ . '/currency-select-options.php'; ?>
    </select>
    <input type="number" class="form-control form-control-sm auragold-currency-rate" id="currencyRate" name="currency_rate"
           value="<?php echo htmlspecialchars($auragold_saved_exchange_rate, ENT_QUOTES, 'UTF-8'); ?>"
           step="0.000001" min="0" title="Currency Rate" placeholder="Rate">
</div>
<?php
// Avoid leaking into next include
unset($auragold_saved_exchange_rate, $auragold_currency_select_class);
?>
