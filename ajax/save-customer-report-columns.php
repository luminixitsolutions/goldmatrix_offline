<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['columns']) && is_array($input['columns'])) {
    $_SESSION['customer_report_columns'] = $input['columns'];
    echo json_encode([
        'status' => 'success',
        'message' => 'Column preferences saved successfully'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid column preferences'
    ]);
}
?>
