<?php
/**
 * Boot currency rate map + base currency name for voucher pages.
 * Sets: $auragold_currency_rates_by_name, $auragold_base_currency_name
 * Also aliases $pi_* for purchase-invoice compatibility.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    global $conn;
}

if (!function_exists('auragold_load_master_currencies')) {
    require_once __DIR__ . '/currency_master.php';
}

if (!isset($currencies) || !is_array($currencies)) {
    $currencies = function_exists('auragold_load_master_currencies')
        ? auragold_load_master_currencies(isset($conn) && $conn instanceof mysqli ? $conn : null)
        : [];
}
if (!is_array($currencies)) {
    $currencies = [];
}

$auragold_currency_rates_by_name = function_exists('auragold_currency_exchange_rates_by_name')
    ? auragold_currency_exchange_rates_by_name(isset($conn) && $conn instanceof mysqli ? $conn : null)
    : [];
if (!is_array($auragold_currency_rates_by_name)) {
    $auragold_currency_rates_by_name = [];
}

$auragold_base_currency_name = '';
foreach ($currencies as $_acCur) {
    if (!empty($_acCur['is_base'])) {
        $auragold_base_currency_name = trim((string) ($_acCur['name'] ?? ''));
        break;
    }
}
if ($auragold_base_currency_name === '' && !empty($currencies[0]['name'])) {
    $auragold_base_currency_name = trim((string) $currencies[0]['name']);
}

$auragold_currency_symbols_by_name = [];
foreach ($currencies as $_acSymCur) {
    if (!is_array($_acSymCur)) {
        continue;
    }
    $symName = strtoupper(trim((string) ($_acSymCur['name'] ?? '')));
    if ($symName === '') {
        continue;
    }
    $symVal = trim((string) ($_acSymCur['symbol'] ?? ''));
    $auragold_currency_symbols_by_name[$symName] = $symVal !== '' ? $symVal : $symName;
}
unset($_acSymCur, $symName, $symVal);

// Purchase-invoice aliases
$pi_currency_rates_by_name = $auragold_currency_rates_by_name;
$pi_base_currency_name = $auragold_base_currency_name;
