<?php
/**
 * Partial reduce of a barcoded diamond issue on a job work order.
 * Remaining weight/qty stays on the job; balance returns to inward stock.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__) . '/includes/mp-jobwork-queue-diamond-stock.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$jobwork_order_id = isset($_POST['jobwork_order_id']) ? (int) $_POST['jobwork_order_id'] : 0;
$issue_id = isset($_POST['issue_id']) ? (int) $_POST['issue_id'] : 0;
$new_weight = isset($_POST['new_weight']) ? (float) str_replace(',', '', (string) $_POST['new_weight']) : 0.0;
$new_qty = isset($_POST['new_qty']) ? (float) str_replace(',', '', (string) $_POST['new_qty']) : 0.0;

if ($jobwork_order_id < 1 || $issue_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Job work order and diamond issue are required.']);
    exit;
}
if (!is_finite($new_weight) || $new_weight <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Enter a valid weight greater than zero.']);
    exit;
}
if (!is_finite($new_qty) || $new_qty < 0) {
    echo json_encode(['ok' => false, 'message' => 'Enter a valid quantity.']);
    exit;
}

mp_jwq_ensure_diamond_issue_table($conn);
$tbl = mp_jwq_diamond_issue_table_name();

$rec = function_exists('getRecord')
    ? getRecord('SELECT * FROM `' . $tbl . '` WHERE id = ' . $issue_id . ' AND jobwork_order_id = ' . $jobwork_order_id . ' LIMIT 1')
    : null;
if (!$rec || empty($rec['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Diamond issue not found on this job.']);
    exit;
}

$old_w = (float) ($rec['weight_out'] ?? $rec['weight'] ?? 0);
$old_q = (float) ($rec['qty_out'] ?? $rec['qty'] ?? 0);
if ($old_q <= 0.0000001) {
    $old_q = 1.0;
}
$new_weight = round($new_weight, 4);
$new_qty = round($new_qty, 4);

if ($new_weight >= $old_w - 0.0000001) {
    echo json_encode(['ok' => false, 'message' => 'New weight must be less than current weight (' . number_format($old_w, 3, '.', '') . ' g).']);
    exit;
}
if ($new_qty > $old_q + 0.0000001) {
    echo json_encode(['ok' => false, 'message' => 'New quantity cannot exceed current quantity (' . number_format($old_q, 3, '.', '') . ').']);
    exit;
}

$stock_id = (int) ($rec['stock_id'] ?? 0);
$barcode = trim((string) ($rec['barcode'] ?? ''));
$item_id = (int) ($rec['jobwork_order_item_id'] ?? 0);
if ($stock_id < 1 || $barcode === '') {
    echo json_encode(['ok' => false, 'message' => 'This diamond row cannot be reduced (no barcoded stock issue).']);
    exit;
}

$use_tx = function_exists('mysqli_begin_transaction');
if ($use_tx) {
    @mysqli_begin_transaction($conn);
}

$tx_ok = true;
$tx_err = '';
$stats = ['saved_rows' => 0, 'excluded_stock_ids' => []];

$payload = [[
    'stock_id' => $stock_id,
    'barcode' => $barcode,
    'weight' => $new_weight,
    'qty' => $new_qty > 0.0000001 ? $new_qty : $old_q,
    'jobwork_order_item_id' => $item_id,
    'product_name' => trim((string) ($rec['product_name'] ?? '')),
    'diamond_category' => trim((string) ($rec['diamond_category'] ?? 'Diamond')),
]];

mp_jwq_apply_diamond_stock_consumption($conn, $jobwork_order_id, $payload, $tx_ok, $tx_err, 0, 0, 0, 0, $stats);

$line_updates = [];
if ($tx_ok && function_exists('mp_jwq_recalculate_line_diamond_weights')) {
    $line_updates = mp_jwq_recalculate_line_diamond_weights($conn, $jobwork_order_id);
}

$returned_w = round($old_w - $new_weight, 4);
$returned_q = round(max(0.0, $old_q - ($new_qty > 0.0000001 ? $new_qty : $old_q)), 4);

/* Outward + inward "balance" rows are trimmed inside mp_jwq_apply_diamond_stock_consumption
   (negative delta -> mp_jwq_restore_stock_after_issue_removal -> mp_jwq_trim_transfer_stock_rows). */

if ($tx_ok && $use_tx) {
    @mysqli_commit($conn);
} elseif (!$tx_ok && $use_tx) {
    @mysqli_rollback($conn);
}

if (!$tx_ok) {
    echo json_encode(['ok' => false, 'message' => $tx_err !== '' ? $tx_err : 'Could not reduce diamond weight.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Reduced to ' . number_format($new_weight, 3, '.', '') . ' g. Balance ' . number_format($returned_w, 3, '.', '') . ' g returned to stock.',
    'issue_id' => $issue_id,
    'new_weight' => $new_weight,
    'new_qty' => $new_qty > 0.0000001 ? $new_qty : $old_q,
    'returned_weight' => $returned_w,
    'returned_qty' => $returned_q,
    'line_diamond_weights' => $line_updates,
], JSON_UNESCAPED_UNICODE);
