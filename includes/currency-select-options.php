<?php
/**
 * Renders <option> tags for currency <select>. Only currencies from $currencies (tbl_currency master) are shown.
 */
if (!isset($currencies) || !is_array($currencies) || $currencies === []) {
    if (function_exists('auragold_load_master_currencies')) {
        global $conn;
        $currencies = auragold_load_master_currencies(isset($conn) && $conn instanceof mysqli ? $conn : null);
    } else {
        $currencies = [];
    }
}

// Unique by name (masters may have one row per branch).
$currencies_unique = [];
$currency_names_seen = [];
foreach ($currencies as $c) {
    if (!is_array($c)) {
        continue;
    }
    $n = trim((string) ($c['name'] ?? ''));
    if ($n === '') {
        continue;
    }
    $key = strtolower($n);
    if (isset($currency_names_seen[$key])) {
        continue;
    }
    $currency_names_seen[$key] = true;
    $currencies_unique[] = $c;
}
$currencies = $currencies_unique;

$currency_name_in_list = function (array $list, string $name): bool {
    $name = trim($name);
    if ($name === '') {
        return false;
    }
    foreach ($list as $row) {
        $n = trim((string) ($row['name'] ?? ''));
        if ($n !== '' && strcasecmp($n, $name) === 0) {
            return true;
        }
    }

    return false;
};

$sel = isset($selected_currency) ? trim((string) $selected_currency) : '';
if ($sel !== '' && !$currency_name_in_list($currencies, $sel)) {
    $sel = '';
}
if ($sel === '') {
    foreach ($currencies as $c) {
        if (!empty($c['is_base'])) {
            $sel = trim((string) ($c['name'] ?? ''));
            break;
        }
    }
    if ($sel === '' && !empty($currencies)) {
        $sel = trim((string) ($currencies[0]['name'] ?? ''));
    }
}

if (empty($currencies)) {
    echo '<option value="">' . htmlspecialchars('No currency defined in master') . "</option>\n";
} else {
    foreach ($currencies as $c) {
        $n = trim((string) ($c['name'] ?? ''));
        if ($n === '') {
            continue;
        }
        $is_sel = ($sel !== '' && strcasecmp($sel, $n) === 0);
        $cid = (int) ($c['id'] ?? 0);
        $is_base = !empty($c['is_base']) ? '1' : '0';
        echo '<option value="' . htmlspecialchars($n) . '"'
            . ' data-currency-id="' . $cid . '"'
            . ' data-is-base="' . $is_base . '"'
            . ($is_sel ? ' selected' : '') . '>'
            . htmlspecialchars($n) . "</option>\n";
    }
}
