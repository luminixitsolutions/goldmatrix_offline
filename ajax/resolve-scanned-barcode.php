<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_barcode_prefix_settings.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_map.php';

header('Content-Type: application/json; charset=utf-8');

$scanned = isset($_GET['barcode']) ? trim((string) $_GET['barcode']) : '';
$scanned = preg_replace('/[\x00-\x1F\x7F]/', '', $scanned);

if ($scanned === '') {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit;
}

$working_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($working_branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
    $working_branch_id = (int) $_SESSION['working_branch_id'];
} elseif ($working_branch_id <= 0 && !empty($_SESSION['branch_id'])) {
    $working_branch_id = (int) $_SESSION['branch_id'];
}
if (isset($_GET['branch_id']) && (int) $_GET['branch_id'] > 0) {
    $working_branch_id = function_exists('auragold_resolve_branch_id_for_session')
        ? (int) auragold_resolve_branch_id_for_session((int) $_GET['branch_id'])
        : (int) $_GET['branch_id'];
}
$metal_id = isset($_GET['metal_id']) ? (int) $_GET['metal_id'] : 0;

$resolved = $scanned;
if (!empty($conn) && $conn instanceof mysqli) {
    if (preg_match('/^\d{1,4}$/', $scanned) && function_exists('auragold_resolve_four_digit_scan_to_full_barcode')) {
        $padded = str_pad(preg_replace('/\D/', '', $scanned), 4, '0', STR_PAD_LEFT);
        $fromScan = auragold_resolve_four_digit_scan_to_full_barcode($conn, $padded, $working_branch_id, $metal_id);
        if ($fromScan !== null && $fromScan !== '') {
            $resolved = $fromScan;
        }
    }
    if ($resolved === $scanned && function_exists('auragold_resolve_scanned_barcode')) {
        $resolved = auragold_resolve_scanned_barcode($conn, $scanned, [
            'branch_id' => $working_branch_id,
            'metal_id' => $metal_id,
        ]);
    }
    if ($resolved === '' || $resolved === $scanned) {
        if (preg_match('/^\d{1,4}$/', $scanned) && function_exists('auragold_resolve_physical_scan_input')) {
            $padded = str_pad(preg_replace('/\D/', '', $scanned), 4, '0', STR_PAD_LEFT);
            $fromMap = auragold_resolve_physical_scan_input($conn, $padded, $working_branch_id, $metal_id);
            if ($fromMap !== null && $fromMap !== '') {
                $resolved = $fromMap;
            }
        }
    }
}

$exists = false;
if ($resolved !== '' && !empty($conn) && $conn instanceof mysqli && function_exists('auragold_barcode_exists_in_system')) {
    $exists = auragold_barcode_exists_in_system($conn, $resolved);
}

if (ob_get_level() > 0) {
    ob_clean();
}
echo json_encode([
    'success' => true,
    'scanned_barcode' => $scanned,
    'resolved_barcode' => ($resolved !== '' && strcasecmp($resolved, $scanned) !== 0) ? $resolved : null,
    'barcode' => $resolved !== '' ? $resolved : $scanned,
    'exists_in_system' => $exists,
], JSON_UNESCAPED_UNICODE);
