<?php
/**
 * Soft-delete an Account Ledger (customer). Blocks fixed/system ledgers.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/account_ledger_fixed.php';

auragold_require_login_or_exit();
auragold_ensure_customer_is_fixed_column($conn);

$customerId = (int) ($_POST['customer_id'] ?? 0);
$ledgerName = trim((string) ($_POST['ledger'] ?? $_POST['ledger_name'] ?? ''));

$row = null;
if ($customerId > 0) {
    $row = getRecord('SELECT id, name, COALESCE(is_fixed, 0) AS is_fixed, status FROM tbl_customers WHERE id = ' . $customerId . ' LIMIT 1');
} elseif ($ledgerName !== '') {
    $esc = mysqli_real_escape_string($conn, $ledgerName);
    $row = getRecord("SELECT id, name, COALESCE(is_fixed, 0) AS is_fixed, status FROM tbl_customers WHERE TRIM(name) = '{$esc}' AND status = 1 LIMIT 1");
}

if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Ledger not found']);
    exit;
}

$name = trim((string) ($row['name'] ?? ''));
$isFixed = (int) ($row['is_fixed'] ?? 0) === 1 || auragold_account_ledger_is_fixed_name($name);
if ($isFixed) {
    echo json_encode(['status' => 'error', 'message' => 'This is a fixed system ledger and cannot be deleted.']);
    exit;
}

$id = (int) $row['id'];
$nameEsc = mysqli_real_escape_string($conn, $name);

// Soft-delete customer + opening rows only (keep transaction history intact by leaving non-opening ledger rows).
@mysqli_query($conn, 'UPDATE tbl_customers SET status = 0, updated_at = NOW() WHERE id = ' . $id);
@mysqli_query(
    $conn,
    "UPDATE tbl_customer_ledger SET status = 0
     WHERE status = 1 AND transaction_type = 'opening'
       AND (customer_id = {$id} OR TRIM(customer_name) = '{$nameEsc}')"
);

echo json_encode(['status' => 'success', 'message' => 'Ledger deleted']);
