<?php

/**
 * When tbl_users has branch grants that hide every top-nav module (e.g. first visit after switching
 * to a new branch), restrict full-page access to dashboard + permission management only.
 */
if (defined('AURAGOLD_MINIMAL_NAV_GATE')) {
    return;
}
define('AURAGOLD_MINIMAL_NAV_GATE', 1);

require_once __DIR__ . '/auragold_sidebar_nav_permissions.php';

/**
 * Enforce allowlist for browser requests when the navbar would be empty.
 */
function auragold_minimal_nav_gate_enforce()
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!isset($_SESSION['login_source']) || (string) $_SESSION['login_source'] !== 'user') {
        return;
    }
    if ((int) ($_SESSION['user_id'] ?? 0) <= 0) {
        return;
    }
    if (!function_exists('auragold_nav_sidebar_entirely_hidden_for_session') || !auragold_nav_sidebar_entirely_hidden_for_session()) {
        return;
    }

    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    if ($sn !== '' && preg_match('#(/|\\\\)ajax(/|\\\\)#', $sn)) {
        return;
    }
    if ($sn !== '' && preg_match('#(/|\\\\)api(/|\\\\)#', $sn)) {
        return;
    }

    $script = basename($sn);
    if ($script === '' || $script === 'index.php') {
        return;
    }

    $allow = [
        'dashboard.php',
        'permission-management.php',
        'logout.php',
        'my-profile.php',
        'change-password.php',
    ];
    if (in_array($script, $allow, true)) {
        return;
    }

    header('Location: dashboard.php');
    exit;
}

auragold_minimal_nav_gate_enforce();
