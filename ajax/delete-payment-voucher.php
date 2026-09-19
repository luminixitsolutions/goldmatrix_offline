<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$voucher_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($voucher_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher ID']);
    exit;
}

$voucher = getRecord("SELECT id, customer_id, customer_name FROM tbl_payment_vouchers WHERE id = $voucher_id");
if (!$voucher) {
    echo json_encode(['status' => 'error', 'message' => 'Voucher not found']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $customer_id = (int)($voucher['customer_id'] ?? 0);
    $customer_name = mysqli_real_escape_string($conn, $voucher['customer_name'] ?? '');

    // 1. Remove ledger entry for this voucher (so balance reverts)
    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_type = 'payment_voucher' AND transaction_id = $voucher_id AND status = 1
    ");

    // 2. Remove scrap invoices auto-created from this payment voucher (OJB-* [[PV_LINK_ID:n]])
    $t_ojb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
    if ($t_ojb && mysqli_num_rows($t_ojb) > 0) {
        mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE comment LIKE '%[[PV_LINK_ID:" . (int)$voucher_id . "]]%'");
    }

    // 2b. Reverse outward metal stock + stock history rows for metal-exchange lines
    auragold_prepare_tbl_stock_reference_columns($conn);
    auragold_metal_exchange_delete_stock_for_reference($conn, 'payment_voucher', $voucher_id);
    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=pv_out|rid=" . (int) $voucher_id . "|%'");
    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=pv|hid=" . (int) $voucher_id . "|%'");

    // 3. Delete voucher items
    mysqli_query($conn, "DELETE FROM tbl_payment_voucher_items WHERE voucher_id = $voucher_id");

    // 4. Delete voucher
    if (!mysqli_query($conn, "DELETE FROM tbl_payment_vouchers WHERE id = $voucher_id")) {
        throw new Exception('Failed to delete voucher');
    }

    // 5. Update tbl_customer_balance to last ledger balance for this customer
    $has_balance_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_balance'");
    if ($has_balance_table && mysqli_num_rows($has_balance_table) > 0) {
        $last = null;
        if ($customer_id > 0) {
            $last = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_id = $customer_id AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
        }
        if (!$last && $customer_name !== '') {
            $last = getRecord("SELECT balance_amount, balance_gold, balance_silver FROM tbl_customer_ledger WHERE customer_name = '$customer_name' AND status = 1 ORDER BY transaction_date DESC, id DESC LIMIT 1");
        }
        if ($last) {
            $amt = (float)($last['balance_amount'] ?? 0);
            $gold = (float)($last['balance_gold'] ?? 0);
            $silver = (float)($last['balance_silver'] ?? 0);
            $up = "INSERT INTO tbl_customer_balance (customer_id, customer_name, balance_amount, balance_gold, balance_silver, last_updated)
                  VALUES ($customer_id, '$customer_name', $amt, $gold, $silver, NOW())
                  ON DUPLICATE KEY UPDATE balance_amount = $amt, balance_gold = $gold, balance_silver = $silver, last_updated = NOW()";
            @mysqli_query($conn, $up);
        }
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Payment voucher deleted successfully']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
