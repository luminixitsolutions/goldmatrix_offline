<?php
require_once __DIR__ . '/remote_license_gate.php';

/**
 * Session cookie + server-side max lifetime (30 days). Each authenticated request refreshes the cookie (sliding).
 * Compatible with PHP 7.2+ (array cookie params need 7.3+).
 */
if (!defined('AURAGOLD_SESSION_LIFETIME')) {
    define('AURAGOLD_SESSION_LIFETIME', 2592000);
}

/** Extended lifetime when "Remember me" is checked at login (365 days). */
if (!defined('AURAGOLD_SESSION_REMEMBER_LIFETIME')) {
    define('AURAGOLD_SESSION_REMEMBER_LIFETIME', 31536000);
}

if (!function_exists('auragold_session_effective_lifetime')) {
    function auragold_session_effective_lifetime(): int {
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auragold_remember_me'])) {
            return (int) AURAGOLD_SESSION_REMEMBER_LIFETIME;
        }
        return (int) AURAGOLD_SESSION_LIFETIME;
    }
}

/**
 * Optional idle logout: log out if there has been no HTTP request within this many seconds (sliding window).
 * Default 0 = disabled so the session is not ended by inactivity; use Logout to end the session.
 * Override via env AURAGOLD_IDLE_LOGOUT_SECONDS (e.g. 1800 for 30 minutes idle).
 */
if (!defined('AURAGOLD_IDLE_LOGOUT_SECONDS')) {
    $envIdle = getenv('AURAGOLD_IDLE_LOGOUT_SECONDS');
    $idleSec = 0;
    if ($envIdle !== false && $envIdle !== '') {
        $idleSec = (int) $envIdle;
        if ($idleSec < 0) {
            $idleSec = 0;
        }
    }
    define('AURAGOLD_IDLE_LOGOUT_SECONDS', $idleSec);
}

if (!function_exists('auragold_session_is_request_ajax')) {
    function auragold_session_is_request_ajax() {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($accept !== '' && stripos($accept, 'application/json') !== false) {
            return true;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && preg_match('#/(ajax|api)(/|$)#i', $uri)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('auragold_session_idle_enforce')) {
    function auragold_session_idle_enforce() {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (AURAGOLD_IDLE_LOGOUT_SECONDS <= 0) {
            return;
        }
        if ((int) ($_SESSION['user_id'] ?? 0) <= 0) {
            return;
        }
        $last = (int) ($_SESSION['auragold_last_activity'] ?? 0);
        if ($last <= 0) {
            return;
        }
        if ((time() - $last) <= AURAGOLD_IDLE_LOGOUT_SECONDS) {
            return;
        }

        $idle_bid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
        $msg      = 'Session expired due to inactivity. Please sign in again.';
        if ($idle_bid > 0) {
            $idle_loc = 'index.php?branch_entry=' . $idle_bid . '&login_error=' . rawurlencode($msg);
        } else {
            $idle_loc = 'index.php?login_error=' . rawurlencode($msg);
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if (auragold_session_is_request_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode([
                'session_expired' => true,
                'message'         => $msg,
                'redirect'        => $idle_loc,
            ]);
            exit;
        }

        header('Location: ' . $idle_loc);
        exit;
    }
}

/**
 * Re-send the session cookie with a fresh expiry so the browser keeps the session for a full configured period
 * from the last request (sliding window). Also touches session data so gc_maxlifetime counts from last activity.
 * Branch + financial year live in $_SESSION and stay bound to this cookie.
 */
if (!function_exists('auragold_session_force_logout_redirect')) {
    /**
     * Clear session and send user to login (HTML redirect or JSON for AJAX).
     */
    function auragold_session_force_logout_redirect(string $message, int $branch_entry = 0): void {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $redir = 'index.php?login_error=' . rawurlencode($message);
        if ($branch_entry > 0) {
            $redir .= '&branch_entry=' . $branch_entry;
        }
        if (function_exists('auragold_session_is_request_ajax') && auragold_session_is_request_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode([
                'session_expired' => true,
                'message'         => $message,
                'redirect'        => $redir,
            ]);
            exit;
        }
        header('Location: ' . $redir);
        exit;
    }
}

if (!function_exists('auragold_session_refresh_live_cookie')) {
    function auragold_session_refresh_live_cookie() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if ((int) ($_SESSION['user_id'] ?? 0) <= 0) {
            return;
        }

        $_SESSION['auragold_last_activity'] = time();

        $lifetime = auragold_session_effective_lifetime();
        @ini_set('session.gc_maxlifetime', (string) $lifetime);

        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $expires  = time() + $lifetime;
        $name     = session_name();
        $sid      = session_id();
        $path     = '/';

        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $sid, [
                'expires'  => $expires,
                'path'     => $path,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($name, $sid, $expires, $path, '', $secure, true);
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $initLifetime = (int) AURAGOLD_SESSION_LIFETIME;
    @ini_set('session.gc_maxlifetime', (string) $initLifetime);
    @ini_set('session.cookie_lifetime', (string) $initLifetime);

    if (!headers_sent()) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $initLifetime,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params($initLifetime, '/', '', $secure, true);
        }
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    auragold_session_idle_enforce();
    auragold_session_refresh_live_cookie();
}
