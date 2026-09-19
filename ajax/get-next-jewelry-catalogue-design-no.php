<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/jewelry_catalogue_create_include.php';

header('Content-Type: application/json; charset=utf-8');

auragold_ensure_jewelry_catalogue_table($conn);

$excludeId = isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : 0;
$cfg = auragold_jewelry_catalogue_bill_series_config($conn);
$designNo = auragold_next_jewelry_catalogue_design_no($conn, $excludeId);

echo json_encode([
    'success' => true,
    'design_no' => $designNo,
    'prefix' => $cfg['prefix'] ?? 'JC-',
    'suffix' => $cfg['suffix'] ?? '',
    'start_count' => (int) ($cfg['start_count'] ?? 1),
    'from_series_table' => !empty($cfg['from_series_table']),
], JSON_UNESCAPED_UNICODE);
