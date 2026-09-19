<?php
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/dashboard_live_rate_fetch.php';
require_once dirname(__DIR__) . '/includes/auragold_metal_rate_urls.php';

if (isset($conn) && $conn instanceof mysqli) {
    auragold_ensure_tbl_metal_rate_urls($conn);
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method', 'rows' => []]);
    exit;
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.', 'rows' => []]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    $data = $_POST;
}

$url = isset($data['url']) ? trim((string) $data['url']) : '';
$metal = isset($data['metal']) ? strtolower(trim((string) $data['metal'])) : 'gold';
$labels = [];
if (isset($data['labels']) && is_array($data['labels'])) {
    foreach ($data['labels'] as $lab) {
        $lab = trim((string) $lab);
        if ($lab !== '') {
            $labels[] = $lab;
        }
    }
}

if ($url === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please select a URL.', 'rows' => []]);
    exit;
}

$result = auragold_dashboard_fetch_live_rates(isset($conn) ? $conn : null, $url, $metal, $labels);
echo json_encode($result);
