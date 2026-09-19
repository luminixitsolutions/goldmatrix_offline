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

$options_raw = $_POST['dropdown_options'] ?? [];
if (is_string($options_raw)) {
    $decoded = json_decode($options_raw, true);
    $options_raw = is_array($decoded) ? $decoded : [];
}

$result = auragold_save_extra_field($conn, $branch_id, [
    'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
    'metal_type' => $_POST['metal_type'] ?? 'Gold',
    'display_name' => $_POST['display_name'] ?? '',
    'field_type' => $_POST['field_type'] ?? 'text',
    'dropdown_options' => $options_raw,
    'status' => isset($_POST['status']) ? (int) $_POST['status'] : 0,
]);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['message'],
    'id' => $result['id'] ?? null,
    'field' => $result['field'] ?? null,
]);
