<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_stock_journal_delete.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $barcodes = [];
    if (!empty($data['barcodes']) && is_array($data['barcodes'])) {
        $barcodes = $data['barcodes'];
    } elseif (!empty($data['barcode'])) {
        $barcodes = [(string) $data['barcode']];
    }

    $barcodes = array_values(array_unique(array_filter(array_map(static function ($b) {
        return trim((string) $b);
    }, $barcodes))));

    if (empty($barcodes)) {
        throw new Exception('No barcodes selected');
    }

    mysqli_begin_transaction($conn);
    try {
        $result = auragold_delete_barcodes_from_stock($conn, $barcodes);
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    $deleted = $result['deleted_barcodes'] ?? [];
    $skipped = $result['skipped'] ?? [];
    if (empty($deleted)) {
        throw new Exception(empty($skipped) ? 'Nothing was deleted' : 'Selected barcodes were not found in stock');
    }

    $msg = count($deleted) . ' barcode(s) deleted from stock';
    if (!empty($skipped)) {
        $msg .= '. Skipped: ' . implode(', ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '…' : '');
    }

    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'deleted_barcodes' => $deleted,
        'journal_deleted' => (int) ($result['journal_deleted'] ?? 0),
        'stock_deleted' => (int) ($result['stock_deleted'] ?? 0),
        'skipped' => $skipped,
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
