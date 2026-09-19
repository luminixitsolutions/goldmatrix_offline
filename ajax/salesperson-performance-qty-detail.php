<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_salesperson_performance_data.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$salesperson = isset($_GET['salesperson']) ? trim((string) $_GET['salesperson']) : '';
$cat_slug = isset($_GET['group']) ? trim((string) $_GET['group']) : '';
$from_get = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$to_get = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

if ($salesperson === '' || $cat_slug === '') {
    echo json_encode(['ok' => false, 'message' => 'Sales person and category are required.']);
    exit;
}

$from_ymd = '';
$to_ymd = '';
if ($from_get !== '' && $to_get !== '') {
    $range = auragold_sale_analysis_parse_range($from_get . ' - ' . $to_get);
    $from_ymd = $range['from_ymd'];
    $to_ymd = $range['to_ymd'];
} else {
    $today = new DateTimeImmutable('today');
    $y = (int) $today->format('Y');
    $m = (int) $today->format('n');
    $fyStart = $m >= 4 ? $y : ($y - 1);
    $range = auragold_sale_analysis_parse_range(sprintf('01-04-%d - 31-03-%d', $fyStart, $fyStart + 1));
    $from_ymd = $range['from_ymd'];
    $to_ymd = $range['to_ymd'];
}

$group_labels = [
    'gold' => 'Gold',
    'silver' => 'Silver',
    'platinum' => 'Platinum',
    'diamond_stones' => 'Diamond & Stones',
    'imitation_watches' => 'Imitation Or Watches',
    'other_services' => 'Other Or Services',
];

$rows = auragold_salesperson_performance_qty_detail_rows($conn, $from_ymd, $to_ymd, $salesperson, $cat_slug);

echo json_encode([
    'ok' => true,
    'salesperson' => $salesperson,
    'group' => $cat_slug,
    'group_label' => $group_labels[$cat_slug] ?? $cat_slug,
    'date_range' => $range['label'] ?? '',
    'count' => count($rows),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE);
