<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/location-helpers.php';

header('Content-Type: application/json');

auragold_bootstrap_location_data($conn);

$country_id = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
if ($country_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid country']);
    exit;
}

$country_id = (int) $country_id;
$rows = getList("SELECT id, name FROM tbl_states WHERE country_id = $country_id AND status = 1 ORDER BY name ASC");

echo json_encode(['status' => 'success', 'states' => $rows]);
