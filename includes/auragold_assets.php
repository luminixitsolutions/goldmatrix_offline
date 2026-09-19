<?php
/**
 * Static asset URLs with filemtime cache-busting for browser long-cache + safe deploys.
 */
if (!function_exists('auragold_asset_ver')) {
    function auragold_asset_ver(string $relPath): string
    {
        static $cache = [];
        $rel = ltrim(str_replace('\\', '/', trim($relPath)), '/');
        if ($rel === '') {
            return '1';
        }
        if (!isset($cache[$rel])) {
            $abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $cache[$rel] = is_file($abs) ? (string) (int) filemtime($abs) : '1';
        }
        return $cache[$rel];
    }
}

if (!function_exists('auragold_asset_url')) {
    function auragold_asset_url(string $relPath): string
    {
        $rel = ltrim(str_replace('\\', '/', trim($relPath)), '/');
        return $rel . '?v=' . auragold_asset_ver($rel);
    }
}

if (!function_exists('auragold_echo_stylesheet')) {
    function auragold_echo_stylesheet(string $relPath): void
    {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(auragold_asset_url($relPath), ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}

if (!function_exists('auragold_echo_script')) {
    /**
     * @param bool $defer Append defer (safe for footer bundle after jQuery in head).
     */
    function auragold_echo_script(string $relPath, bool $defer = false): void
    {
        $deferAttr = $defer ? ' defer' : '';
        echo '<script src="' . htmlspecialchars(auragold_asset_url($relPath), ENT_QUOTES, 'UTF-8') . '"' . $deferAttr . '></script>' . "\n";
    }
}
