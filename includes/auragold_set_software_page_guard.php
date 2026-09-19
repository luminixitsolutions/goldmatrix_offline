<?php

/**
 * Server-side lock check for Set Software pages (call after config.php + login).
 */
if (!function_exists('auragold_set_software_page_guard')) {
    function auragold_set_software_page_guard(): void
    {
        if (empty($_SESSION['Admin'])) {
            return;
        }

        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        require_once __DIR__ . '/auragold_set_software_menu_lock.php';
        auragold_set_software_menu_lock_require_page($conn);
    }
}
