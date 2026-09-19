<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$prefs = [
    'visibility' => isset($input['visibility']) && is_array($input['visibility']) ? $input['visibility'] : [],
    'order'       => isset($input['order']) && is_array($input['order']) ? $input['order'] : [],
    'widths'      => isset($input['widths']) && is_array($input['widths']) ? $input['widths'] : [],
];

$_SESSION['kyc_report_prefs'] = $prefs;

echo json_encode([
    'status'  => 'success',
    'message' => 'Preferences saved',
]);
