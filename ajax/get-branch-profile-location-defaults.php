<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/branch_profile_schema.php';

header('Content-Type: application/json; charset=utf-8');

auragold_ensure_tbl_branches_profile_columns($conn_master);

$bid = auragold_default_branch_id_for_ledger_defaults();
if ($bid <= 0) {
    echo json_encode([
        'ok'    => true,
        'empty' => true,
    ]);
    exit;
}

$row = getRecordMaster('SELECT profile_country_id, profile_state_id, profile_city_id, profile_phone_country_code FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
if (!$row) {
    echo json_encode(['ok' => false, 'message' => 'Branch not found']);
    exit;
}

$profile_country_id = isset($row['profile_country_id']) ? (int) $row['profile_country_id'] : 0;
$profile_state_id   = isset($row['profile_state_id']) ? (int) $row['profile_state_id'] : 0;
$profile_city_id    = isset($row['profile_city_id']) ? (int) $row['profile_city_id'] : 0;

$profile_country_name = '';
$profile_state_name   = '';
$profile_city_name    = '';

if ($profile_country_id > 0) {
    $loc = @getRecord('SELECT name FROM tbl_countries WHERE id = ' . $profile_country_id . ' LIMIT 1');
    if ($loc && !empty($loc['name'])) {
        $profile_country_name = (string) $loc['name'];
    }
}
if ($profile_state_id > 0) {
    $loc = @getRecord('SELECT name FROM tbl_states WHERE id = ' . $profile_state_id . ' LIMIT 1');
    if ($loc && !empty($loc['name'])) {
        $profile_state_name = (string) $loc['name'];
    }
}
if ($profile_city_id > 0) {
    $loc = @getRecord('SELECT name FROM tbl_cities WHERE id = ' . $profile_city_id . ' LIMIT 1');
    if ($loc && !empty($loc['name'])) {
        $profile_city_name = (string) $loc['name'];
    }
}

echo json_encode([
    'ok'                           => true,
    'empty'                        => false,
    'profile_country_id'           => $profile_country_id,
    'profile_state_id'             => $profile_state_id,
    'profile_city_id'              => $profile_city_id,
    'profile_country_name'         => $profile_country_name,
    'profile_state_name'           => $profile_state_name,
    'profile_city_name'            => $profile_city_name,
    'profile_phone_country_code'   => trim((string) ($row['profile_phone_country_code'] ?? '')),
]);
