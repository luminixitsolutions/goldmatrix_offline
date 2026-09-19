<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$barcode_no = isset($_POST['barcode_no']) ? trim($_POST['barcode_no']) : '';

if ($barcode_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'Barcode is required']);
    exit;
}

$upload_base = dirname(__DIR__) . '/uploads/stock_journal';
// Physical folder: {project root}/uploads/stock_journal/ (same directory as config.php)
// DB image_path: uploads/stock_journal/<filename>; public URL: $SiteUrl + that path
if (!is_dir($upload_base)) {
    @mkdir(dirname(__DIR__) . '/uploads', 0755, true);
    @mkdir($upload_base, 0755, true);
}

$allowed = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp', 'image/x-png'];
$max_size = 2 * 1024 * 1024; // 2MB
$ext_ok = ['jpg', 'jpeg', 'png', 'webp'];
$saved = [];
$rejections = [];

$list = [];
foreach ($_FILES as $key => $f) {
    if ($key === 'item_id' || $key === 'barcode_no') {
        continue;
    }
    if (isset($f['tmp_name']) && is_string($f['tmp_name']) && $f['tmp_name'] !== '') {
        $list[] = $f;
    } elseif (isset($f['tmp_name']) && is_array($f['tmp_name'])) {
        foreach ($f['tmp_name'] as $i => $tmp) {
            if ($tmp !== '') {
                $list[] = [
                    'tmp_name' => $tmp,
                    'name' => $f['name'][$i] ?? '',
                    'type' => $f['type'][$i] ?? '',
                    'error' => $f['error'][$i] ?? 0,
                    'size' => $f['size'][$i] ?? 0,
                ];
            }
        }
    }
}

/**
 * Normalize client MIME (often empty or application/octet-stream on Windows / some browsers).
 */
function gas_upload_resolve_mime(string $tmp, string $name, string $reported): string {
    $reported = strtolower(trim($reported));
    if ($reported !== '' && $reported !== 'application/octet-stream') {
        return $reported;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    if (isset($map[$ext])) {
        return $map[$ext];
    }
    if ($tmp !== '' && is_readable($tmp) && function_exists('mime_content_type')) {
        $m = @mime_content_type($tmp);
        if (is_string($m) && $m !== '') {
            return strtolower($m);
        }
    }
    return $reported;
}

$barcode_esc = mysqli_real_escape_string($conn, $barcode_no);
$incoming_count = count($list);

foreach ($list as $file) {
    $err = (int) ($file['error'] ?? 0);
    if ($err !== UPLOAD_ERR_OK) {
        $rejections[] = 'Upload error code ' . $err;
        continue;
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size > $max_size) {
        $rejections[] = 'File too large (max 2MB)';
        continue;
    }
    if ($size <= 0) {
        $rejections[] = 'Empty file';
        continue;
    }
    $name = (string) ($file['name'] ?? '');
    $tmp = (string) ($file['tmp_name'] ?? '');
    $type = gas_upload_resolve_mime($tmp, $name, (string) ($file['type'] ?? ''));
    if (!in_array(strtolower($type), $allowed, true)) {
        $rejections[] = 'Unsupported type: ' . ($type !== '' ? $type : '(unknown)');
        continue;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $ext_ok, true)) {
        $fromMime = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/x-png' => 'png'];
        $tl = strtolower($type);
        if (isset($fromMime[$tl])) {
            $ext = $fromMime[$tl];
        }
    }
    if (!in_array($ext, $ext_ok, true)) {
        $rejections[] = 'Unsupported extension: .' . ($ext !== '' ? $ext : '(none)');
        continue;
    }
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
    if ($safe_name === '') {
        $safe_name = 'image.' . $ext;
    }
    $unique = date('YmdHis') . '_' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8) . '_' . $safe_name;
    $dest = $upload_base . '/' . $unique;
    if (!@move_uploaded_file($tmp, $dest)) {
        $rejections[] = 'Could not save file to disk (check uploads/stock_journal permissions)';
        continue;
    }
    $relative = 'uploads/stock_journal/' . $unique;
    $path_esc = mysqli_real_escape_string($conn, $relative);
    $ins = @mysqli_query($conn, "INSERT INTO tbl_stock_journal_images (item_id, barcode_no, image_path, created_at) VALUES ($item_id, '$barcode_esc', '$path_esc', NOW())");
    if (!$ins) {
        @unlink($dest);
        $rejections[] = 'Database: ' . ($conn instanceof mysqli ? mysqli_error($conn) : 'insert failed');
        continue;
    }
    $newId = (int) mysqli_insert_id($conn);
    if ($newId <= 0) {
        @unlink($dest);
        $rejections[] = 'Database insert returned no id';
        continue;
    }
    $saved[] = ['id' => $newId, 'path' => $relative];
}

$n = count($saved);
if ($n === 0) {
    if ($incoming_count === 0) {
        echo json_encode([
            'status' => 'error',
            'images' => [],
            'message' => 'No image files were received. Use the Browse button or drag files onto the upload area.',
        ]);
        exit;
    }
    $detail = $rejections !== [] ? implode(' ', array_unique($rejections)) : 'All files were rejected (type/size).';
    echo json_encode([
        'status' => 'error',
        'images' => [],
        'message' => 'No image was saved. ' . $detail,
    ]);
    exit;
}

echo json_encode(['status' => 'success', 'images' => $saved, 'message' => $n . ' image(s) uploaded']);
