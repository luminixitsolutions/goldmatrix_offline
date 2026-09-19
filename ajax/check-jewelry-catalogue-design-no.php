<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/jewelry_catalogue_create_include.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$designNo = isset($_GET['design_no']) ? trim((string) $_GET['design_no']) : '';
$excludeId = isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : 0;

if ($designNo === '') {
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => '',
        'design_no' => '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = auragold_jewelry_catalogue_check_design_no($conn, $designNo, $excludeId);
echo json_encode([
    'success' => true,
    'exists' => !empty($result['exists']),
    'message' => (string) ($result['message'] ?? ''),
    'design_no' => (string) ($result['design_no'] ?? ''),
], JSON_UNESCAPED_UNICODE);
