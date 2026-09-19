<?php

/**
 * Shared URL helpers for platinum stock (page + export).
 */
if (!function_exists('gas_app_web_root_path_from_script')) {
    function gas_app_web_root_path_from_script(): string
    {
        $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($sn === '' || $sn === '/') {
            return '';
        }
        $dir = rtrim(dirname($sn), '/');
        if (preg_match('#^(.*)/admin(?:/|$)#u', $dir . '/', $m)) {
            return rtrim($m[1], '/') ?: '';
        }

        return '';
    }
}

if (!function_exists('gas_public_url_for_stored_path')) {
    /**
     * Browser URL for a stored path such as uploads/stock_journal/file.jpg (under site root per $SiteUrl).
     */
    function gas_public_url_for_stored_path(?string $path, $SiteUrl): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (strpos($path, '/') === 0) {
            if (preg_match('#^/uploads/#', $path)) {
                $under = auragold_uploads_public_rel(ltrim($path, '/'));
                $base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
                if ($base !== '') {
                    return $base . '/' . $under;
                }
                $appRoot = gas_app_web_root_path_from_script();
                if ($appRoot !== '') {
                    return $appRoot . '/' . $under;
                }

                return '/' . $under;
            }

            return $path;
        }
        $rel = ltrim($path, '/');
        $under = auragold_uploads_public_rel($rel);
        $base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
        if ($base !== '') {
            return $base . '/' . $under;
        }
        $appRoot = gas_app_web_root_path_from_script();
        if ($appRoot !== '') {
            return $appRoot . '/' . $under;
        }

        return '/' . $under;
    }
}
