<?php

/**
 * Require an authenticated session. Call after config.php (session must be started).
 * Full page: redirect to index.php. AJAX / API-style requests: 401 + JSON (session_expired, redirect).
 */
if (!function_exists('auragold_is_logged_in_session')) {
    function auragold_is_logged_in_session(): bool
    {
        if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            return true;
        }
        return (int) ($_SESSION['user_id'] ?? 0) > 0;
    }
}

if (!function_exists('auragold_require_login_or_exit')) {
    function auragold_require_login_or_exit(): void
    {
        if (auragold_is_logged_in_session()) {
            return;
        }
        $msg = 'Please sign in to continue.';
        $loc = 'index.php?login_error=' . rawurlencode($msg);
        if (function_exists('auragold_session_is_request_ajax') && auragold_session_is_request_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode([
                'status'          => 'error',
                'session_expired' => true,
                'message'         => $msg,
                'redirect'        => $loc,
            ]);
            exit;
        }
        header('Location: ' . $loc);
        exit;
    }
}
