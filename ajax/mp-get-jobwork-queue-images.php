<?php
/**
 * Jobwork Queue modal — load saved gallery JSON for a job work order.
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid id']);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['ok' => false, 'message' => 'Table not found']);
    exit;
}
mysqli_free_result($tbl);

$col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'jobwork_queue_images'");
$has = ($col && mysqli_num_rows($col) > 0);
if ($col) {
    mysqli_free_result($col);
}
if (!$has) {
    @mysqli_query($conn, "ALTER TABLE `tbl_jobwork_orders` ADD COLUMN `jobwork_queue_images` TEXT NULL COMMENT 'Jobwork Queue gallery JSON'");
}

$row = function_exists('getRecord') ? @getRecord('SELECT jobwork_queue_images FROM tbl_jobwork_orders WHERE id = ' . $id . ' LIMIT 1') : null;
$raw = ($row && isset($row['jobwork_queue_images'])) ? trim((string)$row['jobwork_queue_images']) : '';
if ($raw === '') {
    echo json_encode(['ok' => true, 'primary' => '', 'images' => [], 'items' => []]);
    exit;
}

$dec = @json_decode($raw, true);
if (!$dec || empty($dec['images']) || !is_array($dec['images'])) {
    echo json_encode(['ok' => true, 'primary' => '', 'images' => [], 'items' => []]);
    exit;
}

$base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') . '/' : '';
$toUrl = function ($rel) use ($base) {
    $rel = trim((string)$rel);
    if ($rel === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $rel)) {
        return $rel;
    }
    return $base . auragold_uploads_public_rel(ltrim($rel, '/'));
};

$paths = [];
foreach ($dec['images'] as $p) {
    $p = trim((string)$p);
    if ($p !== '') {
        $paths[] = $p;
    }
}
$primaryRel = isset($dec['primary']) ? trim((string)$dec['primary']) : '';
if ($primaryRel === '' && !empty($paths)) {
    $primaryRel = $paths[0];
}

$urls = [];
$items = [];
foreach ($paths as $p) {
    $u = $toUrl($p);
    $urls[] = $u;
    $items[] = ['kind' => 'path', 'path' => $p];
}

$primaryUrl = $primaryRel !== '' ? $toUrl($primaryRel) : '';

echo json_encode([
    'ok' => true,
    'primary' => $primaryUrl,
    'images' => $urls,
    'items' => $items,
]);
