<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/stock_transfer_data.php';
require_once __DIR__ . '/../includes/stock_transfer_pending_schema.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

try {
    $conn = auragold_stock_transfer_central_mysqli();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$barcode = isset($_GET['barcode']) ? trim((string) $_GET['barcode']) : '';
if ($branch_id <= 0 || $barcode === '') {
    echo json_encode(['success' => false, 'message' => 'Branch and barcode are required.']);
    exit;
}
if (function_exists('auragold_branch_is_main_or_sub_of_settings_main')
    && !auragold_branch_is_main_or_sub_of_settings_main($branch_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch.']);
    exit;
}

$bc = mysqli_real_escape_string($conn, $barcode);

$session_default_branch = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $session_default_branch = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $session_default_branch = (int) $_SESSION['branch_id'];
}
if ($session_default_branch <= 0) {
    $fb = getRecord('SELECT id FROM tbl_branches WHERE status = 1 ORDER BY id ASC LIMIT 1');
    $session_default_branch = ($fb && !empty($fb['id'])) ? (int) $fb['id'] : 0;
}

try {
    $sql = auragold_stock_transfer_list_sql($conn, $branch_id, $bc, $session_default_branch);
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        echo json_encode([
            'success' => false,
            'message' => 'Lookup failed: ' . mysqli_error($conn),
        ]);
        exit;
    }

    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No available stock for this barcode at the selected branch.']);
        exit;
    }

    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode(['success' => true, 'row' => auragold_stock_transfer_normalize_row($row)], $flags);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Barcode lookup error: ' . $e->getMessage(),
    ]);
}
