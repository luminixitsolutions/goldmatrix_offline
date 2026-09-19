<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/jwm_material_links.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['success' => false, 'lines' => [], 'message' => 'Unauthorized']);
    exit;
}

$doc_type = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';
$doc_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_repair = !empty($_GET['from_repair']) && (string) $_GET['from_repair'] !== '0';
$filter_jwo_id = isset($_GET['jobwork_order_id']) ? (int) $_GET['jobwork_order_id'] : 0;

if (!in_array($doc_type, ['issue', 'receive'], true) || $doc_id < 1 || !$conn) {
    echo json_encode(['success' => false, 'lines' => [], 'message' => 'Invalid request']);
    exit;
}

$map = jwm_batch_material_voucher_lines_map($conn, [$doc_id], $doc_type, $is_repair, $filter_jwo_id);
$lines = $map[$doc_id] ?? [];

echo json_encode(['success' => true, 'lines' => $lines], JSON_UNESCAPED_UNICODE);
