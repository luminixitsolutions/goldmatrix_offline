<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/dass_barcode_composite_details.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$barcode = isset($_REQUEST['barcode']) ? trim((string) $_REQUEST['barcode']) : '';
if ($barcode === '' && isset($_REQUEST['barcode_no'])) {
    $barcode = trim((string) $_REQUEST['barcode_no']);
}

if (!($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

echo json_encode(dass_build_barcode_composite_details_payload($conn, $barcode), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
