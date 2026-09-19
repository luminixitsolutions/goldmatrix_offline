<?php
session_start();
require_once "config.php";
require_once __DIR__ . '/includes/auragold_product_branch_login_context.php';
require_once __DIR__ . "/includes/product_opening_save_core.php";

require_once __DIR__ . '/includes/auragold_subbranch_product_local_sync.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$ctx         = auragold_product_opening_mysqli_for_login($conn);
$ctxOk       = !empty($ctx['ok']) && $ctx['link'] instanceof mysqli;
$saveConn    = $ctxOk ? $ctx['link'] : $conn;
$branchConn  = $conn;
$closeAfter  = $ctxOk && !empty($ctx['close_after']);
if (!$ctxOk) {
    $msg = isset($ctx['message']) && (string) $ctx['message'] !== ''
        ? (string) $ctx['message']
        : 'Could not determine which database to save the product. Check branch setup.';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

mysqli_begin_transaction($saveConn);

try {

    $result = auragold_product_opening_save($saveConn, $_POST, []);

    mysqli_commit($saveConn);

} catch (Exception $e) {

    mysqli_rollback($saveConn);

    if ($closeAfter) {
        mysqli_close($saveConn);
    }

    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
    exit;
}

$syncNote = '';
if (!empty($ctx['is_sub'])
    && (int) ($ctx['sub_branch_id'] ?? 0) > 0
    && !empty($result['product_id'])
    && $saveConn instanceof mysqli
    && $branchConn instanceof mysqli
    && auragold_subbranch_catalog_and_local_differ($saveConn, $branchConn)) {
    try {
        auragold_sync_subbranch_product_local_from_catalog(
            $saveConn,
            $branchConn,
            (int) $result['product_id'],
            (int) $ctx['sub_branch_id'],
            true
        );
    } catch (Throwable $syncEx) {
        $syncNote = ' Saved on main catalog; local branch copy failed: ' . $syncEx->getMessage();
    }
}

if ($closeAfter) {
    mysqli_close($saveConn);
}

$payload = [
    'status'     => 'success',
    'message'    => 'Product saved successfully' . $syncNote,
    'product_id' => $result['product_id'],
];

echo json_encode($payload);
