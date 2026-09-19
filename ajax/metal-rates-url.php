<?php

session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_metal_rate_urls.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

auragold_ensure_branch_id_on_settings_tables($conn);

$branch_id = auragold_resolve_metal_rate_url_branch_id(
    isset($_POST['settings_branch_id']) ? (int) $_POST['settings_branch_id'] : null
);

$action = trim((string) ($_POST['action'] ?? 'save'));

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $result = auragold_delete_metal_rate_url($conn, $id, $branch_id);
    echo json_encode([
        'success' => $result['ok'],
        'message' => $result['message'],
    ]);
    exit;
}

$result = auragold_save_metal_rate_url($conn, $branch_id, [
    'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
    'url' => $_POST['url'] ?? '',
    'label' => $_POST['label'] ?? '',
    'status' => isset($_POST['status']) ? (int) $_POST['status'] : 0,
    'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
]);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['message'],
    'id' => $result['id'] ?? null,
    'row' => $result['row'] ?? null,
]);
