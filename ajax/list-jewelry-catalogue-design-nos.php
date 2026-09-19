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

$metalId = isset($_GET['metal_id']) ? (int) $_GET['metal_id'] : 0;
$items = auragold_jewelry_catalogue_list_design_nos($conn, $metalId);

echo json_encode([
    'success' => true,
    'items' => $items,
    'total' => count($items),
], JSON_UNESCAPED_UNICODE);
