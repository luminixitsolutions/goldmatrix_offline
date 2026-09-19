<?php
/**
 * Jobwork Queue modal — save multi-image gallery (paths + new data URLs) on tbl_jobwork_orders.jobwork_queue_images
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$jobwork_order_id = isset($_POST['jobwork_order_id']) ? (int)$_POST['jobwork_order_id'] : 0;
if ($jobwork_order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Job work order id required']);
    exit;
}

$payload_raw = isset($_POST['images_payload']) ? (string)$_POST['images_payload'] : '';
$payload = @json_decode($payload_raw, true);
if (!$payload || !isset($payload['items']) || !is_array($payload['items'])) {
    echo json_encode(['ok' => false, 'message' => 'Invalid images payload']);
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

$exists = @mysqli_query($conn, 'SELECT id FROM tbl_jobwork_orders WHERE id = ' . $jobwork_order_id . ' LIMIT 1');
if (!$exists || mysqli_num_rows($exists) === 0) {
    if ($exists) {
        mysqli_free_result($exists);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work order not found']);
    exit;
}
mysqli_free_result($exists);

$col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'jobwork_queue_images'");
if (!$col || mysqli_num_rows($col) === 0) {
    if ($col) {
        mysqli_free_result($col);
    }
    @mysqli_query($conn, "ALTER TABLE `tbl_jobwork_orders` ADD COLUMN `jobwork_queue_images` TEXT NULL COMMENT 'Jobwork Queue gallery JSON'");
} elseif ($col) {
    mysqli_free_result($col);
}

if (count($payload['items']) === 0) {
    if (!@mysqli_query($conn, 'UPDATE tbl_jobwork_orders SET jobwork_queue_images = NULL WHERE id = ' . $jobwork_order_id)) {
        echo json_encode(['ok' => false, 'message' => 'Could not clear images']);
        exit;
    }
    echo json_encode(['ok' => true, 'message' => 'Images cleared', 'paths' => []]);
    exit;
}

$primary_idx = isset($payload['primary']) ? (int)$payload['primary'] : 0;
if ($primary_idx < 0) {
    $primary_idx = 0;
}

$base_dir = dirname(__DIR__) . '/uploads/jobwork-queue/' . $jobwork_order_id;
if (!is_dir($base_dir)) {
    if (!@mkdir($base_dir, 0755, true)) {
        echo json_encode(['ok' => false, 'message' => 'Could not create upload directory']);
        exit;
    }
}

$paths = [];
foreach ($payload['items'] as $item) {
    if (!is_array($item)) {
        continue;
    }
    $kind = isset($item['kind']) ? (string)$item['kind'] : '';
    if ($kind === 'path') {
        $p = isset($item['path']) ? trim((string)$item['path']) : '';
        if ($p === '') {
            continue;
        }
        if (preg_match('#^https?://#i', $p)) {
            if (preg_match('#/(?:admin/)?(uploads/jobwork-queue/.+)$#i', $p, $m)) {
                $paths[] = $m[1];
            } elseif (preg_match('#/(uploads/jobwork-queue/.+)$#i', $p, $m)) {
                $paths[] = $m[1];
            }
            continue;
        }
        if (strpos($p, 'uploads/jobwork-queue/') === 0) {
            $paths[] = $p;
        }
        continue;
    }
    if ($kind === 'data') {
        $dataUrl = isset($item['data']) ? trim((string)$item['data']) : '';
        if ($dataUrl === '' || !preg_match('/^data:image\/(\w+);base64,(.+)$/s', $dataUrl, $m)) {
            continue;
        }
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $safe_ext = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? $ext : 'png';
        $filename = 'jq_' . $jobwork_order_id . '_' . str_replace('.', '_', uniqid('', true)) . '.' . $safe_ext;
        $full_path = $base_dir . '/' . $filename;
        $b64 = preg_replace('/\s+/', '', $m[2]);
        $bin = @base64_decode($b64, true);
        if ($bin === false || @file_put_contents($full_path, $bin) === false) {
            continue;
        }
        $rel = 'uploads/jobwork-queue/' . $jobwork_order_id . '/' . $filename;
        $paths[] = $rel;
    }
}

if (empty($paths)) {
    @mysqli_query($conn, 'UPDATE tbl_jobwork_orders SET jobwork_queue_images = NULL WHERE id = ' . $jobwork_order_id);
    echo json_encode(['ok' => true, 'message' => 'Images cleared', 'paths' => []]);
    exit;
}

if ($primary_idx >= count($paths)) {
    $primary_idx = 0;
}
$primary_path = $paths[$primary_idx];

$json_out = json_encode(['primary' => $primary_path, 'images' => $paths], JSON_UNESCAPED_SLASHES);
if ($json_out === false) {
    echo json_encode(['ok' => false, 'message' => 'Could not encode JSON']);
    exit;
}

$esc = mysqli_real_escape_string($conn, $json_out);
$sql = 'UPDATE tbl_jobwork_orders SET jobwork_queue_images = \'' . $esc . '\' WHERE id = ' . $jobwork_order_id;
if (!@mysqli_query($conn, $sql)) {
    echo json_encode(['ok' => false, 'message' => 'Could not save']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Saved', 'paths' => $paths, 'primary' => $primary_path]);
