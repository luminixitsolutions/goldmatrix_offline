<?php

session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_credit_card_schema.php';

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

$branch_id = auragold_resolve_credit_card_branch_id(
    isset($_POST['settings_branch_id']) ? (int) $_POST['settings_branch_id'] : null
);

$action = trim((string) ($_POST['action'] ?? 'save'));

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $result = auragold_delete_credit_card($conn, $id, $branch_id);
    echo json_encode([
        'success' => $result['ok'],
        'message' => $result['message'],
    ]);
    exit;
}

$result = auragold_save_credit_card($conn, $branch_id, [
    'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
    'name' => $_POST['name'] ?? '',
    'account_group' => $_POST['account_group'] ?? '',
    'commission_account' => $_POST['commission_account'] ?? '',
    'commission_percent' => $_POST['commission_percent'] ?? 0,
    'status' => isset($_POST['status']) ? (int) $_POST['status'] : 0,
    'is_default' => isset($_POST['is_default']) ? (int) $_POST['is_default'] : 0,
]);

echo json_encode([
    'success' => $result['ok'],
    'message' => $result['message'],
    'id' => $result['id'] ?? null,
    'card' => $result['card'] ?? null,
]);
