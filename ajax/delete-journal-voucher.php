<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$voucher_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($voucher_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher ID']);
    exit;
}

$voucher = getRecord("SELECT id FROM tbl_journal_vouchers WHERE id = $voucher_id");
if (!$voucher) {
    echo json_encode(['status' => 'error', 'message' => 'Voucher not found']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_type = 'journal_voucher' AND transaction_id = $voucher_id
    ");
    mysqli_query($conn, "DELETE FROM tbl_journal_voucher_items WHERE voucher_id = $voucher_id");
    if (!mysqli_query($conn, "DELETE FROM tbl_journal_vouchers WHERE id = $voucher_id")) {
        throw new Exception('Failed to delete journal voucher');
    }
    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Journal voucher deleted successfully']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
