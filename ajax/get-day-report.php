<?php

session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_day_report_data.php';

header('Content-Type: application/json');

$report_date = isset($_GET['date']) ? trim((string) $_GET['date']) : date('Y-m-d');

if ($report_date === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Date is required',
    ]);
    exit;
}

try {
    $data = auragold_day_report_collect($conn, $report_date);
    echo json_encode(array_merge(['status' => 'success'], $data));
} catch (Throwable $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
