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
if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch.']);
    exit;
}
if (function_exists('auragold_branch_is_main_or_sub_of_settings_main')
    && !auragold_branch_is_main_or_sub_of_settings_main($branch_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch.']);
    exit;
}

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
    $sql = auragold_stock_transfer_list_sql($conn, $branch_id, null, $session_default_branch);
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        echo json_encode([
            'success' => false,
            'message' => 'Could not load stock: ' . mysqli_error($conn),
        ]);
        exit;
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_free_result($res);

    $out = [];
    foreach ($rows as $r) {
        $out[] = auragold_stock_transfer_normalize_row($r);
    }

    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode(['success' => true, 'rows' => $out], $flags);
    if ($json === false) {
        echo json_encode(['success' => false, 'message' => 'Could not encode response.']);
        exit;
    }
    echo $json;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Stock transfer list error: ' . $e->getMessage(),
    ]);
}
