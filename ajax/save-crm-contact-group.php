<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/crm_contact_groups_schema.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

global $conn;
auragold_ensure_crm_contact_groups_tables($conn);

$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$active = isset($_POST['active']) ? (int) $_POST['active'] : 1;
$active = $active ? 1 : 0;

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Group name is required']);
    exit;
}

$name_esc = mysqli_real_escape_string($conn, $name);
if (!mysqli_query(
    $conn,
    "INSERT INTO tbl_crm_contact_groups (name, status, created_at) VALUES ('$name_esc', $active, NOW())"
)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save group']);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'id'     => (int) mysqli_insert_id($conn),
    'message' => 'Group saved',
]);
