<?php

session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_extra_fields_schema.php';

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

$branch_id = auragold_resolve_extra_fields_branch_id(
    isset($_POST['settings_branch_id']) ? (int) $_POST['settings_branch_id'] : null
);
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$result = auragold_delete_extra_field($conn, $id, $branch_id);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['message'],
]);
