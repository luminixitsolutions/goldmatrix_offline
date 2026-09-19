<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/location-helpers.php';

header('Content-Type: application/json');

auragold_bootstrap_location_data($conn);

$state_id = isset($_GET['state_id']) ? (int) $_GET['state_id'] : 0;
if ($state_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid state']);
    exit;
}

$state_id = (int) $state_id;
$rows = getList("SELECT id, name FROM tbl_cities WHERE state_id = $state_id AND status = 1 ORDER BY name ASC");

echo json_encode(['status' => 'success', 'cities' => $rows]);
