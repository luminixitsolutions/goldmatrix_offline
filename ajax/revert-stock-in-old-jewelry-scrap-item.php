<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_balance.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$stock_id = isset($_POST['stock_id']) ? (int)$_POST['stock_id'] : 0;

if ($stock_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid stock ID']);
    exit;
}

$row = getRecord("SELECT id, source_invoice_id, source_item_id, barcode, branch_id FROM tbl_old_jewelry_stock WHERE id = $stock_id");
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Stock record not found']);
    exit;
}

$source_invoice_id = (int) ($row['source_invoice_id'] ?? 0);
$source_item_id = (int) $row['source_item_id'];
$oj_barcode = trim((string) ($row['barcode'] ?? ''));
$oj_branch = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;
$bid_for_stock = $oj_branch > 0 ? $oj_branch : 1;

$inv_meta = null;
if ($source_invoice_id > 0) {
    $inv_meta = getRecord("SELECT invoice_no FROM tbl_old_jewelry_scrap_invoices WHERE id = $source_invoice_id LIMIT 1");
}
$invoice_no_plain = $inv_meta ? trim((string) ($inv_meta['invoice_no'] ?? '')) : '';

// Stock History journal row created at stock-in: sj_invoice_no = 'OJB-{invoice_id}-{stock_row_id}'
$sj_no = 'OJB-' . $source_invoice_id . '-' . $stock_id;
$sj_esc = mysqli_real_escape_string($conn, $sj_no);

$use_tx = mysqli_begin_transaction($conn);

// Remove matching stock journal audit row (same key as auragold_oj_scrap_insert_stock_history_journal_line)
$tj = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal'");
if ($tj && mysqli_num_rows($tj) > 0) {
    mysqli_free_result($tj);
    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE sj_invoice_no = '$sj_esc' LIMIT 1");
}

// Remove mirror row in tbl_stock (same barcode + branch as Stock In insert) so inventory no longer shows this piece
$tstk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
if ($tstk && mysqli_num_rows($tstk) > 0) {
    mysqli_free_result($tstk);
    if ($oj_barcode !== '') {
        $bc_esc = mysqli_real_escape_string($conn, $oj_barcode);
        $has_st = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_type'");
        $has_st_col = ($has_st && mysqli_num_rows($has_st) > 0);
        if ($has_st) {
            mysqli_free_result($has_st);
        }
        if ($has_st_col) {
            mysqli_query($conn, "DELETE FROM tbl_stock WHERE barcode = '$bc_esc' AND branch_id = $bid_for_stock AND stock_type = 'purchase' ORDER BY id DESC LIMIT 1");
        } else {
            mysqli_query($conn, "DELETE FROM tbl_stock WHERE barcode = '$bc_esc' AND branch_id = $bid_for_stock ORDER BY id DESC LIMIT 1");
        }
    }
}

// Remove from Stocked list; scrap balance on OJB line is restored automatically (line gross minus SUM(stock))
if (!mysqli_query($conn, "DELETE FROM tbl_old_jewelry_stock WHERE id = $stock_id")) {
    if ($use_tx) {
        mysqli_rollback($conn);
    }
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit;
}

if ($use_tx) {
    mysqli_commit($conn);
}

// is_stocked + stocked_at: sync the live scrap line (handles stale source_item_id after sync replaced items)
if ($source_item_id > 0) {
    $line_ok = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoice_items WHERE id = $source_item_id AND status = 1 LIMIT 1");
    if ($line_ok) {
        auragold_oj_scrap_sync_is_stocked_for_item($conn, $source_item_id);
    } elseif ($source_invoice_id > 0) {
        $fallback = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $source_invoice_id AND status = 1 ORDER BY id ASC LIMIT 1");
        if ($fallback) {
            auragold_oj_scrap_sync_is_stocked_for_item($conn, (int) $fallback['id']);
        }
    }
} elseif ($source_invoice_id > 0) {
    $fallback = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $source_invoice_id AND status = 1 ORDER BY id ASC LIMIT 1");
    if ($fallback) {
        auragold_oj_scrap_sync_is_stocked_for_item($conn, (int) $fallback['id']);
    }
}

$msg = 'Stock In reverted. Removed from Stocked';
if ($invoice_no_plain !== '') {
    $msg .= '; balance gross is available again on ' . $invoice_no_plain . ' (Old Jewellery-Scrap).';
} else {
    $msg .= '; scrap balance on the job work / refinery invoice is available again.';
}

echo json_encode([
    'status' => 'success',
    'message' => $msg,
    'stock_id' => $stock_id,
    'source_invoice_id' => $source_invoice_id,
    'invoice_no' => $invoice_no_plain,
]);
