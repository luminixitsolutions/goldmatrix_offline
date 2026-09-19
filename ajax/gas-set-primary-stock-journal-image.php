<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$barcode = isset($_POST['barcode_no']) ? trim((string) $_POST['barcode_no']) : '';
$primary_id = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;

if ($barcode === '' || $primary_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Barcode and image id are required']);
    exit;
}

if (!($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

$esc = mysqli_real_escape_string($conn, $barcode);
$rs = @mysqli_query(
    $conn,
    "SELECT id, item_id, image_path FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc') ORDER BY id ASC"
);
$rows = [];
if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $rows[] = $row;
    }
    mysqli_free_result($rs);
}

if ($rows === []) {
    echo json_encode(['status' => 'error', 'message' => 'No images for this barcode']);
    exit;
}

$found = false;
foreach ($rows as $r) {
    if ((int) ($r['id'] ?? 0) === $primary_id) {
        $found = true;
        break;
    }
}
if (!$found) {
    echo json_encode(['status' => 'error', 'message' => 'Image not found for this barcode']);
    exit;
}

$primary_row = null;
$others = [];
foreach ($rows as $r) {
    if ((int) ($r['id'] ?? 0) === $primary_id) {
        $primary_row = $r;
    } else {
        $others[] = $r;
    }
}
$ordered = array_merge($primary_row !== null ? [$primary_row] : [], $others);

mysqli_begin_transaction($conn);
try {
    if (!mysqli_query($conn, "DELETE FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')")) {
        throw new Exception(mysqli_error($conn));
    }
    foreach ($ordered as $r) {
        $iid = (int) ($r['item_id'] ?? 0);
        $path = (string) ($r['image_path'] ?? '');
        $path_esc = mysqli_real_escape_string($conn, $path);
        $sql = "INSERT INTO tbl_stock_journal_images (item_id, barcode_no, image_path, created_at) VALUES ($iid, '$esc', '$path_esc', NOW())";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception(mysqli_error($conn));
        }
    }
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Could not reorder: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Primary image updated']);
