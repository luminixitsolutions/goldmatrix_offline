<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_report_data.php';

header('Content-Type: application/json; charset=utf-8');

function bsr_json_out(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['Admin'])) {
    bsr_json_out(['success' => false, 'message' => 'Unauthorized']);
}

$fetch = auragold_barcode_scan_report_fetch($conn, array_merge(
    auragold_barcode_scan_report_filters_from_request(),
    ['unlimited' => false, 'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 5000]
));

if (empty($fetch['success'])) {
    bsr_json_out(['success' => false, 'message' => $fetch['error'] ?? 'Failed to load report']);
}

bsr_json_out([
    'success' => true,
    'rows' => $fetch['rows'],
    'summary' => $fetch['summary'],
    'total' => count($fetch['rows']),
    'from_date' => $fetch['from_date'],
    'to_date' => $fetch['to_date'],
]);
