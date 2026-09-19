<?php

/**
 * Sale invoice page hero banner — custom upload with default fallback.
 */

function auragold_sale_invoice_page_banner_default()
{
    return 'images/sale_invoice.png';
}

function auragold_sale_invoice_page_banner_setting_key()
{
    return 'sale_invoice_page_banner_path';
}

function auragold_sale_invoice_page_banner_hidden_value()
{
    return '__hidden__';
}

function auragold_sale_invoice_page_banner_is_hidden($branch_id = null)
{
    if (!function_exists('getInvoicePrintSettingsByType')) {
        return false;
    }
    $settings = getInvoicePrintSettingsByType('default', $branch_id);
    $path = trim((string) ($settings[auragold_sale_invoice_page_banner_setting_key()] ?? ''));

    return $path === auragold_sale_invoice_page_banner_hidden_value();
}

function auragold_sale_invoice_page_banner_path($branch_id = null)
{
    $default = auragold_sale_invoice_page_banner_default();
    if (!function_exists('getInvoicePrintSettingsByType')) {
        return $default;
    }

    $settings = getInvoicePrintSettingsByType('default', $branch_id);
    $key = auragold_sale_invoice_page_banner_setting_key();
    $path = trim((string) ($settings[$key] ?? ''));
    if ($path === '' || $path === auragold_sale_invoice_page_banner_hidden_value()) {
        return $default;
    }

    $path = ltrim(str_replace('\\', '/', $path), '/');
    if (strpos($path, '..') !== false) {
        return $default;
    }

    $full = dirname(__DIR__) . '/' . $path;
    if (!is_file($full)) {
        return $default;
    }

    return $path;
}

function auragold_sale_invoice_page_banner_url($branch_id = null)
{
    $path = auragold_sale_invoice_page_banner_path($branch_id);
    $full = dirname(__DIR__) . '/' . ltrim($path, '/');
    $v = is_file($full) ? (int) filemtime($full) : 0;
    $url = $path . ($v > 0 ? '?v=' . $v : '');

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}
