<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json');

$metal_type = isset($_GET['metal_type']) ? trim((string) $_GET['metal_type']) : '';
if ($metal_type === '') {
    echo json_encode(['success' => 0, 'message' => 'Metal type is required.']);
    exit;
}
if (strlen($metal_type) > 50) {
    $metal_type = substr($metal_type, 0, 50);
}

$label_size_preset = isset($_GET['label_size_preset']) ? trim((string) $_GET['label_size_preset']) : '';
$label_width_mm = isset($_GET['label_width_mm']) ? (float) $_GET['label_width_mm'] : null;
$label_height_mm = isset($_GET['label_height_mm']) ? (float) $_GET['label_height_mm'] : null;

$row = ($label_size_preset !== '')
    ? getBarcodeSettings($metal_type, $label_size_preset, $label_width_mm, $label_height_mm)
    : getBarcodeSettings($metal_type);
$snap = auragold_barcode_settings_designer_snapshot($row);
if (!$snap) {
    echo json_encode(['success' => 1, 'settings' => null, 'message' => 'No saved settings for this metal and label size.']);
    exit;
}

echo json_encode(['success' => 1, 'settings' => $snap]);
