<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Prefix/digits from request when provided; otherwise branch defaults (tbl_branches) + tbl_settings.
$branch_id = isset($_REQUEST['branch_id']) ? (int) $_REQUEST['branch_id'] : 0;
if ($branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
    $branch_id = auragold_effective_branch_id();
}

$prefix = isset($_REQUEST['prefix']) ? trim((string)$_REQUEST['prefix']) : '';
$digitRequested = isset($_REQUEST['digit']) ? (int)$_REQUEST['digit'] : 0;

$defaults = ['prefix' => 'RN', 'digit' => 5];
if (function_exists('auragold_barcode_default_prefix_digit')) {
    $defaults = auragold_barcode_default_prefix_digit($conn, $branch_id);
}
if ($prefix === '') {
    $prefix = $defaults['prefix'];
}
$digit = $digitRequested;
if ($digit < 1) {
    $digit = (int) ($defaults['digit'] ?? 5);
}
if ($digit < 1) {
    $digit = 5;
}

$used_barcodes = [];
if (isset($_REQUEST['used']) && is_array($_REQUEST['used'])) {
    foreach ($_REQUEST['used'] as $u) {
        $u = trim((string)$u);
        if ($u !== '') $used_barcodes[] = $u;
    }
} elseif (isset($_REQUEST['used']) && is_string($_REQUEST['used'])) {
    $parts = array_map('trim', explode(',', $_REQUEST['used']));
    foreach ($parts as $u) {
        if ($u !== '') $used_barcodes[] = $u;
    }
}

$candidate = isset($_REQUEST['candidate']) ? trim((string) $_REQUEST['candidate']) : '';

try {
    if ($candidate !== '' && !in_array($candidate, $used_barcodes, true) && function_exists('auragold_barcode_exists_in_system') && !auragold_barcode_exists_in_system($conn, $candidate)) {
        echo json_encode(['success' => true, 'barcode' => $candidate]);
        exit;
    }
    $merge_used = $used_barcodes;
    if ($candidate !== '') {
        $merge_used[] = $candidate;
    }
    $barcode = generateBarcode($conn, $prefix, (int) $digit, $merge_used);
    echo json_encode(['success' => true, 'barcode' => $barcode]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
