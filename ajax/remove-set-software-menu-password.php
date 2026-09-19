<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/auragold_set_software_menu_lock.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if (trim($password) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Enter password']);
    exit;
}

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
if ($conn === null) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

auragold_ensure_set_software_menu_lock_table($conn);

if (!auragold_set_software_menu_has_password($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'No menu password is set']);
    exit;
}

if (!auragold_set_software_menu_password_verify($conn, $password)) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    exit;
}

if ($action === 'verify') {
    echo json_encode(['status' => 'ok', 'message' => 'Password accepted']);
    exit;
}

if ($action !== 'remove') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$ok = auragold_set_software_menu_password_remove_and_unlock_all($conn);
$okMessage = function_exists('auragold_t')
    ? (string) auragold_t('set_software.menu_settings_removed')
    : 'Menu password removed and all menus unlocked.';
$errMessage = function_exists('auragold_t')
    ? (string) auragold_t('set_software.menu_settings_remove_error')
    : 'Could not remove the menu password.';

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => $errMessage]);
    exit;
}

$_SESSION['menu_settings_flash'] = [
    'type'    => 'success',
    'message' => $okMessage,
];

echo json_encode(['status' => 'ok', 'message' => $okMessage]);
