<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/dashboard_helpers.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$DASHBOARD_PAGE_TITLE = 'Manufacturing Dashboard';

$dateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$mfgBounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
$mfg = auragold_manufacturing_dashboard($mfgBounds['start'], $mfgBounds['end']);

require __DIR__ . '/includes/dashboard_shell_top.php';
require __DIR__ . '/includes/partials/dashboard_manufacturing_home.php';
require __DIR__ . '/includes/dashboard_shell_bottom.php';
