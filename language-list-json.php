<?php
/**
 * List of target languages (Google format). Cached; prefer live Google API if key is set in config.
 * Admin only.
 */
session_start();
if (empty($_SESSION['Admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Forbidden', 'languages' => []]);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_google_translate.php';

$dir         = __DIR__ . '/assets/data';
$cacheFile   = $dir . '/google-translate-languages.cache.json';
$maxAge      = 7 * 24 * 3600;
$refresh     = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';
$useFallback = isset($_GET['use_fallback']) && (string) $_GET['use_fallback'] === '1';

if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

if ($useFallback) {
    $fb = auragold_google_translate_languages_fallback_file();
    if (is_file($fb)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        readfile($fb);
    } else {
        echo json_encode(['error' => 'no_fallback', 'languages' => [['language' => 'en', 'name' => 'English']], 'source' => 'min']);
    }
    exit;
}

if ($refresh && is_file($cacheFile)) {
    @unlink($cacheFile);
}

$serveCache = function ($path) {
    if (is_file($path)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        return true;
    }
    return false;
};

if (!$refresh && is_file($cacheFile) && (time() - filemtime($cacheFile) < $maxAge) && $serveCache($cacheFile)) {
    exit;
}

$live = [];
if (auragold_get_google_translate_api_key() !== '') {
    $live = auragold_google_api_list_languages('en');
}
if (is_array($live) && count($live) > 0) {
    $payload = [
        'languages' => $live,
        'source'    => 'google',
    ];
    @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (is_file($cacheFile) && $serveCache($cacheFile)) {
    exit;
}

$fb = auragold_google_translate_languages_fallback_file();
if (is_file($fb)) {
    $raw = @file_get_contents($fb);
    $j   = is_string($raw) ? @json_decode($raw, true) : null;
    if (is_array($j)) {
        if (!isset($j['source'])) {
            $j['source'] = 'fallback';
        }
        if (!@file_put_contents($cacheFile, json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
            // still output
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    if (is_file($fb)) {
        readfile($fb);
    } else {
        echo json_encode(['languages' => [['language' => 'en', 'name' => 'English']], 'source' => 'min']);
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['languages' => [['language' => 'en', 'name' => 'English']], 'source' => 'min']);
