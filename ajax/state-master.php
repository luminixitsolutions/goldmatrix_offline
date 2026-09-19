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

if ($action === 'add') {
    $country_id = (int) ($_POST['country_id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $comment_raw = trim((string) ($_POST['comment'] ?? ''));
    $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $status = $status ? 1 : 0;

    if ($country_id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Country and state name are required.']);
        exit;
    }

    $co = getRecord("SELECT id FROM tbl_countries WHERE id = $country_id LIMIT 1");
    if (!$co) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid country.']);
        exit;
    }

    $dup = getRecord("SELECT id FROM tbl_states WHERE country_id = $country_id AND LOWER(TRIM(name)) = LOWER(TRIM('$name')) LIMIT 1");
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'This state already exists for the selected country.']);
        exit;
    }

    $comment_sql = $comment_raw === '' ? 'NULL' : "'" . esc($comment_raw) . "'";
    $sql = "INSERT INTO tbl_states (country_id, name, status, comment) VALUES ($country_id, '$name', $status, $comment_sql)";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'id' => mysqli_insert_id($conn),
        'message' => 'State saved.',
    ]);
    exit;
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $country_id = (int) ($_POST['country_id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $comment_raw = trim((string) ($_POST['comment'] ?? ''));
    $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $status = $status ? 1 : 0;

    if ($id <= 0 || $country_id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit;
    }

    $st = getRecord("SELECT id FROM tbl_states WHERE id = $id LIMIT 1");
    if (!$st) {
        echo json_encode(['status' => 'error', 'message' => 'State not found.']);
        exit;
    }

    $co = getRecord("SELECT id FROM tbl_countries WHERE id = $country_id LIMIT 1");
    if (!$co) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid country.']);
        exit;
    }

    $dup = getRecord("SELECT id FROM tbl_states WHERE country_id = $country_id AND LOWER(TRIM(name)) = LOWER(TRIM('$name')) AND id != $id LIMIT 1");
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'This state already exists for the selected country.']);
        exit;
    }

    $comment_sql = $comment_raw === '' ? 'NULL' : "'" . esc($comment_raw) . "'";
    $sql = "UPDATE tbl_states SET country_id = $country_id, name = '$name', status = $status, comment = $comment_sql WHERE id = $id";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not update: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'State updated.']);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    $active_cities = getRecord("SELECT COUNT(*) AS c FROM tbl_cities WHERE state_id = $id AND status = 1");
    if ($active_cities && (int) $active_cities['c'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Deactivate or remove cities under this state first.']);
        exit;
    }

    if (!mysqli_query($conn, "UPDATE tbl_states SET status = 0 WHERE id = $id")) {
        echo json_encode(['status' => 'error', 'message' => 'Could not deactivate: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'State deactivated.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
