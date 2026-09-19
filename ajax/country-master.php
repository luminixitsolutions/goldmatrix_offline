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
    $name = esc($_POST['name'] ?? '');
    $code = esc($_POST['code'] ?? '');
    $code3 = esc($_POST['code3'] ?? '');
    $comment_raw = trim((string) ($_POST['comment'] ?? ''));
    $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $status = $status ? 1 : 0;

    if ($name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Country name is required.']);
        exit;
    }

    $dup = getRecord("SELECT id FROM tbl_countries WHERE LOWER(TRIM(name)) = LOWER(TRIM('$name')) LIMIT 1");
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'A country with this name already exists.']);
        exit;
    }

    if ($code !== '') {
        $dupc = getRecord("SELECT id FROM tbl_countries WHERE LOWER(TRIM(code)) = LOWER(TRIM('$code')) AND code IS NOT NULL AND code != '' LIMIT 1");
        if ($dupc) {
            echo json_encode(['status' => 'error', 'message' => 'This country code is already in use.']);
            exit;
        }
    }

    $comment_sql = $comment_raw === '' ? 'NULL' : "'" . esc($comment_raw) . "'";
    $code_sql = $code === '' ? 'NULL' : "'$code'";
    $code3_sql = $code3 === '' ? 'NULL' : "'$code3'";

    $sql = "INSERT INTO tbl_countries (name, code, code3, status, comment) VALUES ('$name', $code_sql, $code3_sql, $status, $comment_sql)";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'id' => mysqli_insert_id($conn),
        'message' => 'Country saved.',
    ]);
    exit;
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $code = esc($_POST['code'] ?? '');
    $code3 = esc($_POST['code3'] ?? '');
    $comment_raw = trim((string) ($_POST['comment'] ?? ''));
    $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;
    $status = $status ? 1 : 0;

    if ($id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit;
    }

    $row = getRecord("SELECT id FROM tbl_countries WHERE id = $id LIMIT 1");
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Country not found.']);
        exit;
    }

    $dup = getRecord("SELECT id FROM tbl_countries WHERE LOWER(TRIM(name)) = LOWER(TRIM('$name')) AND id != $id LIMIT 1");
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'A country with this name already exists.']);
        exit;
    }

    if ($code !== '') {
        $dupc = getRecord("SELECT id FROM tbl_countries WHERE LOWER(TRIM(code)) = LOWER(TRIM('$code')) AND code IS NOT NULL AND code != '' AND id != $id LIMIT 1");
        if ($dupc) {
            echo json_encode(['status' => 'error', 'message' => 'This country code is already in use.']);
            exit;
        }
    }

    $comment_sql = $comment_raw === '' ? 'NULL' : "'" . esc($comment_raw) . "'";
    $code_sql = $code === '' ? 'NULL' : "'$code'";
    $code3_sql = $code3 === '' ? 'NULL' : "'$code3'";

    $sql = "UPDATE tbl_countries SET name = '$name', code = $code_sql, code3 = $code3_sql, status = $status, comment = $comment_sql, updated_at = NOW() WHERE id = $id";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not update: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Country updated.']);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    $active_states = getRecord("SELECT COUNT(*) AS c FROM tbl_states WHERE country_id = $id AND status = 1");
    if ($active_states && (int) $active_states['c'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Deactivate or remove states under this country first.']);
        exit;
    }

    if (!mysqli_query($conn, "UPDATE tbl_countries SET status = 0, updated_at = NOW() WHERE id = $id")) {
        echo json_encode(['status' => 'error', 'message' => 'Could not deactivate: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Country deactivated.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
