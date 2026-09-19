<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_ensure_stock_journal_audit_columns')) {
    auragold_ensure_stock_journal_audit_columns($conn);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing stock journal row id']);
    exit;
}

// Editable columns (Name and Barcode are read-only, not in this list)
$text_columns = ['code', 'group_name', 'comment', 'rfid_code', 'voucher_type', 'design_no', 'huid_no', 'category', 'calculation', 'location', 'discount_type', 'making_type', 'stone_charge_type', 'other_charge_type', 'other_info'];
$number_columns = ['quantity', 'karat', 'pkt_wt', 'pkt_less_wt', 'requested_purity', 'requested', 'gross_weight', 'less_weight', 'gold_loss_1', 'gold_loss_2', 'setting_charge', 'net_weight', 'purity', 'purity_weight', 'pure_weight', 'wastage_per', 'wastage_wt', 'final_weight', 'alloy_wt', 'rate', 'metal_value', 'metal_cost', 'amount', 'discount_per', 'discount_amount', 'discount', 'making_rate', 'making_amount', 'making_cost', 'minimum_price', 'stone_weight', 'stone_rate', 'stone_amount', 'stone_cost', 'diamond_amount', 'purchase_amount', 'sale_amount', 'net_amount', 'tax_amount', 'other_weight', 'other_rate', 'other_amount', 'hallmark_amount', 'hallmark_rate', 'net_amt_with_tax', 'reverse'];

$existing_cols = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_journal");
while ($row = mysqli_fetch_assoc($res)) {
    $existing_cols[$row['Field']] = true;
}

$set_parts = [];
$values = [];
$types = '';

foreach ($text_columns as $col) {
    if (empty($existing_cols[$col])) continue;
    $set_parts[] = "`$col` = ?";
    $values[] = isset($_POST[$col]) ? trim($_POST[$col]) : '';
    $types .= 's';
}
foreach ($number_columns as $col) {
    if (empty($existing_cols[$col])) continue;
    $set_parts[] = "`$col` = ?";
    if ($col === 'karat') {
        $karatResolved = function_exists('auragold_resolve_stock_journal_karat_value')
            ? auragold_resolve_stock_journal_karat_value($_POST['karat'] ?? '', $conn)
            : (isset($_POST['karat']) && $_POST['karat'] !== '' ? (float) $_POST['karat'] : null);
        $values[] = ($karatResolved !== null) ? $karatResolved : 0;
    } else {
        $values[] = isset($_POST[$col]) ? (float)$_POST[$col] : 0;
    }
    $types .= 'd';
}

$extra_fields_raw = null;
if (isset($_POST['extra_fields'])) {
    $ef_decoded = json_decode((string) $_POST['extra_fields'], true);
    if (is_array($ef_decoded)) {
        $extra_fields_raw = $ef_decoded;
    }
}
if ($extra_fields_raw !== null && auragold_ensure_extra_fields_json_column($conn, 'tbl_stock_journal')) {
    $ef_json = auragold_extra_fields_json_from_item(['extra_fields' => $extra_fields_raw]);
    $set_parts[] = '`extra_fields_json` = ?';
    $values[] = $ef_json;
    $types .= 's';
}

if (empty($set_parts)) {
    echo json_encode(['status' => 'error', 'message' => 'No editable columns found']);
    exit;
}

$uid = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0;
if (!empty($existing_cols['modified_by'])) {
    $set_parts[] = '`modified_by` = ?';
    $values[] = $uid;
    $types .= 'i';
}
if (!empty($existing_cols['modified_by_username'])) {
    $set_parts[] = '`modified_by_username` = ?';
    $values[] = function_exists('auragold_stock_journal_session_username') ? auragold_stock_journal_session_username() : '';
    $types .= 's';
}

$set_parts[] = 'updated_at = NOW()';
$values[] = $id;
$types .= 'i';

// Verify row exists
$row = getRecord("SELECT id, item_id FROM tbl_stock_journal WHERE id = $id AND status = 'active'");
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Stock journal row not found']);
    exit;
}

$sql = "UPDATE tbl_stock_journal SET " . implode(', ', $set_parts) . " WHERE id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . mysqli_error($conn)]);
    exit;
}
$params = array_merge([$types], $values);
$refs = [];
foreach ($params as $k => $v) {
    $refs[$k] = &$params[$k];
}
call_user_func_array([$stmt, 'bind_param'], $refs);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . mysqli_error($conn)]);
    exit;
}
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($affected === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No row updated']);
    exit;
}

if ($extra_fields_raw !== null) {
    $pi_item_id = (int) ($row['item_id'] ?? 0);
    if ($pi_item_id > 0 && auragold_ensure_extra_fields_json_column($conn, 'tbl_purchase_invoice_items')) {
        $ef_json_pi = auragold_extra_fields_json_from_item(['extra_fields' => $extra_fields_raw]);
        if ($ef_json_pi !== null) {
            @mysqli_query(
                $conn,
                "UPDATE tbl_purchase_invoice_items SET extra_fields_json = '" . mysqli_real_escape_string($conn, $ef_json_pi) . "' WHERE id = " . $pi_item_id . " LIMIT 1"
            );
        }
    }
}

echo json_encode(['status' => 'success', 'message' => 'Updated successfully']);
