<?php
/**
 * Verify master password before opening Add Branch form (JSON).
 * Uses tbl_settings.branch_password_hash (bcrypt) on the registry connection.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/ensure_tbl_settings.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

if (!auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Only admin users can add branches.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request method']);
    exit;
}

$password = isset($_REQUEST['password']) ? (string) $_REQUEST['password'] : '';
$password = trim($password);

if ($password === '') {
    echo json_encode(['ok' => false, 'message' => 'Password is required']);
    exit;
}

if (!auragold_ensure_tbl_settings_branch_password($conn_master)) {
    echo json_encode([
        'ok'      => false,
        'message' => 'Could not create or update tbl_settings. Check DB permissions or run admin/sql/branch_add_secure.sql',
    ]);
    exit;
}

$row = getRecordMaster('SELECT id, branch_password_hash FROM tbl_settings ORDER BY id ASC LIMIT 1');
if (!$row) {
    echo json_encode(['ok' => false, 'message' => 'tbl_settings row missing']);
    exit;
}

$hash = isset($row['branch_password_hash']) ? trim((string) $row['branch_password_hash']) : '';

if ($hash === '') {
    echo json_encode([
        'ok'      => false,
        'message' => 'Branch password hash is missing after setup. Contact administrator.',
    ]);
    exit;
}

if (!password_verify($password, $hash)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid Password']);
    exit;
}

echo json_encode(['ok' => true, 'message' => '']);
