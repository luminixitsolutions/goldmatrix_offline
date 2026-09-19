<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'No image uploaded.']);
    exit;
}

$file = $_FILES['image'];
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload failed.']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
if ($finfo) {
    finfo_close($finfo);
}
if ($mime === '' || !in_array($mime, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
    exit;
}

$ext = 'jpg';
if ($mime === 'image/png') {
    $ext = 'png';
} elseif ($mime === 'image/gif') {
    $ext = 'gif';
} elseif ($mime === 'image/webp') {
    $ext = 'webp';
}

$dir = dirname(__DIR__) . '/uploads/jewelry_catalogue';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$name = 'jcat_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = $dir . '/' . $name;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Could not save file.']);
    exit;
}

$rel = 'uploads/jewelry_catalogue/' . $name;
$url = function_exists('auragold_uploads_public_url')
    ? auragold_uploads_public_url($rel)
    : $rel;

echo json_encode([
    'success' => true,
    'path' => $rel,
    'url' => $url,
], JSON_UNESCAPED_UNICODE);
