<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/login_authenticate.php';
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
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid branch']);
    exit;
}
if (trim($password) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Enter the branch password']);
    exit;
}

if (empty($conn_master)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

auragold_ensure_tbl_branches_panel_password($conn_master);

$row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $id . ' LIMIT 1');
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Branch not found']);
    exit;
}

if ((int) ($row['status'] ?? 0) !== 1) {
    echo json_encode(['status' => 'error', 'message' => 'This branch is inactive']);
    exit;
}

$loginSrc = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
if ($loginSrc === 'user') {
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $userRow = getRecord('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
        if ((!$userRow || !is_array($userRow)) && function_exists('getRecordMaster')) {
            $userRow = getRecordMaster('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
        }
        if ($userRow) {
            $perm = auragold_validate_user_branch_switch_target($id, $userRow);
            if (empty($perm['ok'])) {
                $msg = trim((string) ($perm['message'] ?? ''));
                echo json_encode(['status' => 'error', 'message' => $msg !== '' ? $msg : 'You cannot switch to that branch.']);
                exit;
            }
        }
    }
}

if (!auragold_can_user_open_branch_row($row)) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot open that branch with this account']);
    exit;
}

if (!auragold_branch_panel_password_verify($row, $password)) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect branch password']);
    exit;
}

$token = auragold_branch_panel_grant_switch($id);
if ($token === '') {
    echo json_encode(['status' => 'error', 'message' => 'Could not start branch switch']);
    exit;
}

$name = trim((string) ($row['name'] ?? ''));
echo json_encode([
    'status'   => 'ok',
    'message'  => 'Password accepted',
    'redirect' => 'switch_branch.php?id=' . $id . '&token=' . rawurlencode($token),
    'branch_name' => $name,
]);
