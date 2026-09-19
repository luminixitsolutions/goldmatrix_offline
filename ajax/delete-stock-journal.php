<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_stock_journal_delete.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $characteristic_id = isset($_POST['characteristic_id']) ? (int) $_POST['characteristic_id'] : 0;
        $voucher = isset($_POST['voucher']) ? trim($_POST['voucher']) : '';
    } else {
        $item_id = isset($data['item_id']) ? (int) $data['item_id'] : 0;
        $characteristic_id = isset($data['characteristic_id']) ? (int) $data['characteristic_id'] : 0;
        $voucher = isset($data['voucher']) ? trim($data['voucher']) : '';
    }

    $by_characteristic = ($voucher === 'product_opening' && $characteristic_id > 0);
    if (!$by_characteristic && $item_id <= 0) {
        throw new Exception('Invalid item ID');
    }
    if ($by_characteristic && $characteristic_id <= 0) {
        throw new Exception('Invalid characteristic ID');
    }

    mysqli_begin_transaction($conn);

    try {
        $result = auragold_delete_stock_journal_entries($conn, [
            'voucher' => $by_characteristic ? 'product_opening' : 'purchase_invoice',
            'characteristic_id' => $characteristic_id,
            'item_id' => $item_id,
        ]);

        mysqli_commit($conn);

        $deleted_count = (int) ($result['deleted_count'] ?? 0);
        $barcode_count = count($result['barcodes'] ?? []);
        $stock_deleted = (int) ($result['stock_deleted'] ?? 0);
        $orphan_barcodes = $result['orphan_barcodes'] ?? [];

        $msg_parts = [];
        if ($deleted_count > 0) {
            $msg_parts[] = "$deleted_count stock journal entry/entries deleted";
        }
        if ($stock_deleted > 0) {
            $msg_parts[] = "$stock_deleted stock/barcode row(s) removed";
        }
        if (!empty($orphan_barcodes)) {
            $msg_parts[] = count($orphan_barcodes) . ' orphaned barcode(s) cleaned (' . implode(', ', array_slice($orphan_barcodes, 0, 5)) . (count($orphan_barcodes) > 5 ? '…' : '') . ')';
        }
        if (empty($msg_parts)) {
            $msg_parts[] = 'No stock journal entries or linked stock found to delete';
        }

        echo json_encode([
            'status' => 'success',
            'message' => implode('. ', $msg_parts) . '.',
            'deleted_count' => $deleted_count,
            'stock_deleted' => $stock_deleted,
            'barcodes_removed' => $result['barcodes'] ?? [],
            'orphan_barcodes' => $orphan_barcodes,
        ]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
    error_log('Delete Stock Journal Error: ' . $e->getMessage());
}
