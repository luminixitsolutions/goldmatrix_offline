<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid image id']);
    exit;
}

$row = getRecord("SELECT id, image_path FROM tbl_stock_journal_images WHERE id = $id");
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Image not found']);
    exit;
}

$path = $row['image_path'] ?? '';
$full_path = dirname(__DIR__) . '/' . $path;
if ($path !== '' && file_exists($full_path)) {
    @unlink($full_path);
}

mysqli_query($conn, "DELETE FROM tbl_stock_journal_images WHERE id = $id");
if (mysqli_affected_rows($conn) > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Image deleted']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed']);
}
