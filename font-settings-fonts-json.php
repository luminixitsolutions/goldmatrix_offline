<?php
/**
 * Cached JSON from fonts.google.com/metadata/fonts for Font Setting (Select2 + search).
 * Not public: requires Admin session.
 */
session_start();
if (empty($_SESSION['Admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Forbidden', 'familyMetadataList' => []]);
    exit;
}

$dir = __DIR__ . '/assets/data';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$cacheFile = $dir . '/google-fonts-metadata.cache.json';
$maxAge = 7 * 24 * 3600;

$refresh = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';
if ($refresh && is_file($cacheFile)) {
    @unlink($cacheFile);
}

if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $maxAge) && !$refresh) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    readfile($cacheFile);
    exit;
}

$ctx = stream_context_create([
    'http' => [
        'timeout'   => 45,
        'user_agent' => 'GoldMatrix Font Settings (admin)',
    ],
    'https' => [
        'timeout'   => 45,
    ],
]);

$raw = @file_get_contents('https://fonts.google.com/metadata/fonts', false, $ctx);
if ($raw !== false && strlen($raw) > 500) {
    @file_put_contents($cacheFile, $raw);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    echo $raw;
    exit;
}

if (is_file($cacheFile)) {
    header('Content-Type: application/json; charset=utf-8');
    readfile($cacheFile);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['familyMetadataList' => []]);
