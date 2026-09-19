<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_gst_report_data.php';

if (empty($_SESSION['Admin']) && empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
$section = isset($_GET['section']) ? trim((string) $_GET['section']) : '';
$from = isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : date('Y-m-01');
$to = isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : date('Y-m-t');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-t');
}
if (!auragold_gst_report_is_valid($type)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid report type']);
    exit;
}

try {
    $data = auragold_gst_report_fetch($conn, $type, $from, $to, $section);
    $meta = auragold_gst_report_meta($type);
    echo json_encode([
        'ok' => true,
        'type' => $type,
        'section' => $data['section'] ?? $section,
        'sections' => $data['sections'] ?? [],
        'label' => $meta['label'] ?? $type,
        'purpose' => $meta['purpose'] ?? '',
        'from_date' => $from,
        'to_date' => $to,
        'columns' => $data['columns'] ?? [],
        'rows' => $data['rows'] ?? [],
        'totals' => $data['totals'] ?? [],
        'note' => $data['note'] ?? '',
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Failed to load report', 'error' => $e->getMessage()]);
}
