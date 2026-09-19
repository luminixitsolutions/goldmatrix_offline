<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/branch_panel_password.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$password = isset($_POST['password']) ? trim((string) $_POST['password']) : '';
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid branch']);
    exit;
}
if ($password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Enter a password']);
    exit;
}
if (strlen($password) < 4) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 4 characters']);
    exit;
}

if (empty($conn_master)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

auragold_ensure_tbl_branches_panel_password($conn_master);

$target = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $id . ' LIMIT 1');
if (!$target) {
    echo json_encode(['status' => 'error', 'message' => 'Branch not found']);
    exit;
}

if (!auragold_branch_panel_may_manage_password($target)) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot change the password for this branch']);
    exit;
}

if (!auragold_branch_panel_password_set($conn_master, $id, $password)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save password']);
    exit;
}

echo json_encode(['status' => 'ok', 'message' => 'Panel password updated']);
