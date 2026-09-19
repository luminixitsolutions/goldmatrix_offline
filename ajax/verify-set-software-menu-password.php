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

$menuKey = isset($_POST['menu_key']) ? trim((string) $_POST['menu_key']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$return = isset($_POST['return']) ? trim((string) $_POST['return']) : '';

if ($menuKey === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid menu']);
    exit;
}

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

if (!auragold_set_software_menu_is_locked($conn, $menuKey)) {
    auragold_set_software_menu_grant_unlock($menuKey);
    $href = auragold_set_software_menu_href_by_key($menuKey);
    $redirect = $return !== '' ? $return : ($href !== '' ? $href : 'index.php');
    echo json_encode(['status' => 'ok', 'redirect' => $redirect]);
    exit;
}

if (!auragold_set_software_menu_password_verify($conn, $password)) {
    echo json_encode(['status' => 'error', 'message' => 'Incorrect password']);
    exit;
}

auragold_set_software_menu_grant_unlock($menuKey);

$href = auragold_set_software_menu_href_by_key($menuKey);
$redirect = $return !== '' ? $return : ($href !== '' ? $href : 'index.php');

// Basic path sanitization — only allow relative php pages
if (preg_match('#^(https?://|//|\.\.)#i', $redirect)) {
    $redirect = $href !== '' ? $href : 'index.php';
}

echo json_encode([
    'status'   => 'ok',
    'message'  => 'Password accepted',
    'redirect' => $redirect,
]);
