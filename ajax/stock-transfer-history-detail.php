<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/stock_transfer_history_fetch.php';
require_once __DIR__ . '/../includes/stock_transfer_pending_schema.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

$doc_id = isset($_GET['doc_id']) ? (int) $_GET['doc_id'] : 0;
$outward_id = isset($_GET['outward_id']) ? (int) $_GET['outward_id'] : 0;
$invoice_no = isset($_GET['invoice_no']) ? trim((string) $_GET['invoice_no']) : '';

if ($doc_id <= 0 && $outward_id <= 0 && $invoice_no === '') {
    echo json_encode(['success' => false, 'message' => 'doc_id, outward_id or invoice_no required.']);
    exit;
}

try {
    $conn = function_exists('auragold_stock_transfer_central_mysqli')
        ? auragold_stock_transfer_central_mysqli()
        : $GLOBALS['conn'];
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if ($doc_id <= 0 && $invoice_no !== '') {
    require_once __DIR__ . '/../includes/stock_transfer_doc_schema.php';
    auragold_ensure_stock_transfer_doc_table($conn);
    $invEsc = mysqli_real_escape_string($conn, $invoice_no);
    $qr = @mysqli_query($conn, "SELECT id FROM tbl_stock_transfer_doc WHERE invoice_no = '$invEsc' LIMIT 1");
    if ($qr && ($row = mysqli_fetch_assoc($qr))) {
        $doc_id = (int) ($row['id'] ?? 0);
    }
    if ($qr) {
        mysqli_free_result($qr);
    }
    if ($doc_id <= 0 && preg_match('/^ST-0*([0-9]+)$/i', $invoice_no, $m)) {
        // Legacy: invoice was ST-{outward_id}
        $outward_id = (int) $m[1];
    }
}

$lines = auragold_stock_transfer_history_lines_for_doc($conn, $doc_id, $outward_id, $invoice_no);
$items = [];
$total_wt = 0.0;
$total_qty = 0.0;
foreach ($lines as $r) {
    $wt = (float) ($r['gross_wt'] ?? $r['net_wt'] ?? 0);
    $qty = (float) ($r['qty'] ?? 0);
    $total_wt += $wt;
    $total_qty += $qty;
    $items[] = [
        'outward_id' => (int) ($r['outward_id'] ?? 0),
        'product_name' => (string) ($r['product_name'] ?? ''),
        'barcode' => (string) ($r['barcode'] ?? ''),
        'net_wt' => (float) ($r['net_wt'] ?? 0),
        'gross_wt' => (float) ($r['gross_wt'] ?? 0),
        'qty' => $qty,
        'from_branch' => (string) ($r['from_branch_name'] ?? ''),
        'to_branch' => (string) ($r['to_branch_name'] ?? ''),
        'status' => auragold_stock_transfer_history_status_label($r),
        'date' => (string) ($r['transaction_date'] ?? $r['created_at'] ?? ''),
    ];
}

$invOut = $invoice_no;
if ($invOut === '' && !empty($lines[0]['invoice_no'])) {
    $invOut = (string) $lines[0]['invoice_no'];
}
if ($invOut === '' && $doc_id > 0) {
    $invOut = 'ST-' . str_pad((string) $doc_id, 6, '0', STR_PAD_LEFT);
}

echo json_encode([
    'success' => true,
    'invoice_no' => $invOut,
    'transfer_doc_id' => $doc_id,
    'count' => count($items),
    'total_wt' => round($total_wt, 3),
    'total_qty' => round($total_qty, 3),
    'items' => $items,
]);
