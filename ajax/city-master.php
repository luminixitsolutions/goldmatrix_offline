<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/location-helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

auragold_ensure_location_tables($conn);

$action = $_POST['action'] ?? '';
$has_comment = false;
$rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_cities LIKE 'comment'");
if ($rc && mysqli_num_rows($rc) > 0) {
    $has_comment = true;
}

if ($action === 'add') {
    $state_id = (int) ($_POST['state_id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $comment_raw = trim((string) ($_POST['comment'] ?? ''));
    $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $status = $status ? 1 : 0;

    if ($state_id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'message' => 'State and city name are required.']);
        exit;
    }

    $st = getRecord("SELECT id FROM tbl_states WHERE id = $state_id LIMIT 1");
    if (!$st) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid state.']);
        exit;
    }

    $dup = getRecord("SELECT id FROM tbl_cities WHERE state_id = $state_id AND LOWER(TRIM(name)) = LOWER(TRIM('$name')) LIMIT 1");
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'This city already exists for the selected state.']);
        exit;
    }

    if ($has_comment) {
        $comment_sql = $comment_raw === '' ? 'NULL' : "'" . esc($comment_raw) . "'";
        $sql = "INSERT INTO tbl_cities (state_id, name, comment, status) VALUES ($state_id, '$name', $comment_sql, $status)";
    } else {
        $sql = "INSERT INTO tbl_cities (state_id, name, status) VALUES ($state_id, '$name', $status)";
    }

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'id' => mysqli_insert_id($conn),
        'message' => 'City saved.',
    ]);
    exit;
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $state_id = (int) ($_POST['state_id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $comment_raw = trim((string) ($_POST['comment'] ?? ''));
    $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $status = $status ? 1 : 0;

    if ($id <= 0 || $state_id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit;
    }

    $city = getRecord("SELECT id FROM tbl_cities WHERE id = $id LIMIT 1");
    if (!$city) {
        echo json_encode(['status' => 'error', 'message' => 'City not found.']);
        exit;
    }

    $st = getRecord("SELECT id FROM tbl_states WHERE id = $state_id LIMIT 1");
    if (!$st) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid state.']);
        exit;
    }

    $dup = getRecord("SELECT id FROM tbl_cities WHERE state_id = $state_id AND LOWER(TRIM(name)) = LOWER(TRIM('$name')) AND id != $id LIMIT 1");
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'This city already exists for the selected state.']);
        exit;
    }

    if ($has_comment) {
        $comment_sql = $comment_raw === '' ? 'NULL' : "'" . esc($comment_raw) . "'";
        $sql = "UPDATE tbl_cities SET state_id = $state_id, name = '$name', comment = $comment_sql, status = $status WHERE id = $id";
    } else {
        $sql = "UPDATE tbl_cities SET state_id = $state_id, name = '$name', status = $status WHERE id = $id";
    }

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not update: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'City updated.']);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    if (!mysqli_query($conn, "UPDATE tbl_cities SET status = 0 WHERE id = $id")) {
        echo json_encode(['status' => 'error', 'message' => 'Could not deactivate: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'City deactivated.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
