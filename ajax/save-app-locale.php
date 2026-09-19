<?php
/**
 * Persist UI language (Google Translate + app_locale) — used by header widget.
 * POST app_locale=mr|hi|ar|en|...
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$loggedIn = (int) ($_SESSION['user_id'] ?? 0) > 0
    || (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin']));
if (!$loggedIn) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

$code = isset($_POST['app_locale']) ? (string) $_POST['app_locale'] : 'en';
$allowed = ['en', 'hi', 'mr', 'gu', 'ta', 'te', 'kn', 'bn', 'pa', 'ar'];
$code = strtolower(trim($code));
if (preg_match('/^en(\-[a-z0-9]+)*$/i', $code)) {
    $code = 'en';
}
if (!in_array($code, $allowed, true)) {
    // Accept "Marathi" etc. via sanitize if available
    if (function_exists('auragold_sanitize_app_locale')) {
        $code = auragold_sanitize_app_locale($code);
    }
}
if (!in_array($code, $allowed, true)) {
    $code = 'en';
}

$ok = false;
if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_save_app_locale')) {
    $ok = (bool) auragold_save_app_locale($conn, $code);
}

echo json_encode([
    'ok'     => $ok,
    'locale' => $code,
]);
