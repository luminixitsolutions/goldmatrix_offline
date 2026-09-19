<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/location-helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

auragold_bootstrap_location_data($conn);

$state_id = isset($_POST['state_id']) ? (int) $_POST['state_id'] : 0;
$name = trim((string) ($_POST['name'] ?? ''));
$comment = trim((string) ($_POST['comment'] ?? ''));
$active = isset($_POST['active']) ? (int) $_POST['active'] : 1;
$status = $active ? 1 : 0;

if ($state_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a state.']);
    exit;
}
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'City name is required.']);
    exit;
}

$st = getRecord("SELECT id FROM tbl_states WHERE id = $state_id AND status = 1 LIMIT 1");
if (!$st) {
    echo json_encode(['success' => false, 'message' => 'Invalid state.']);
    exit;
}

$name_esc = mysqli_real_escape_string($conn, $name);
$dup = getRecord("SELECT id FROM tbl_cities WHERE state_id = $state_id AND LOWER(TRIM(name)) = LOWER(TRIM('$name_esc')) LIMIT 1");
if ($dup) {
    echo json_encode(['success' => false, 'message' => 'This city already exists for the selected state.']);
    exit;
}

$has_comment = false;
$rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_cities LIKE 'comment'");
if ($rc && mysqli_num_rows($rc) > 0) {
    $has_comment = true;
}

if ($has_comment) {
    $comment_esc = mysqli_real_escape_string($conn, $comment);
    $sql = "INSERT INTO tbl_cities (state_id, name, comment, status) VALUES ($state_id, '$name_esc', " .
        ($comment !== '' ? "'$comment_esc'" : 'NULL') . ", $status)";
} else {
    $sql = "INSERT INTO tbl_cities (state_id, name, status) VALUES ($state_id, '$name_esc', $status)";
}

if (!mysqli_query($conn, $sql)) {
    echo json_encode(['success' => false, 'message' => 'Could not save city: ' . mysqli_error($conn)]);
    exit;
}

$city_id = mysqli_insert_id($conn);
echo json_encode([
    'success' => true,
    'message' => 'City saved.',
    'city' => ['id' => $city_id, 'name' => $name],
]);
