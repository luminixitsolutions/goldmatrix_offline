<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/stock_transfer_pending_schema.php';
require_once __DIR__ . '/../includes/auragold_stock_cross_transfer_log_schema.php';
require_once __DIR__ . '/../includes/auragold_stock_transfer_save_helpers.php';
require_once __DIR__ . '/../includes/stock_transfer_doc_schema.php';
require_once __DIR__ . '/../includes/current_stock_balance.php';
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body.']);
    exit;
}

$action = isset($payload['action']) ? trim((string) $payload['action']) : 'save';
$from_branch = isset($payload['from_branch_id']) ? (int) $payload['from_branch_id'] : 0;
$to_branch   = isset($payload['to_branch_id']) ? (int) $payload['to_branch_id'] : 0;
$transfer_date = isset($payload['transfer_date']) ? trim((string) $payload['transfer_date']) : '';
$metal_id = isset($payload['metal_id']) ? (int) $payload['metal_id'] : 0;
$product_id = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
$characteristic_id = isset($payload['characteristic_id']) ? (int) $payload['characteristic_id'] : 0;
$transfer_wt = isset($payload['transfer_wt']) ? (float) $payload['transfer_wt'] : 0.0;

if ($from_branch <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select source branch.']);
    exit;
}
if ($metal_id <= 0 || $product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select metal and product.']);
    exit;
}

if ($transfer_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transfer_date)) {
    $transfer_date = date('Y-m-d');
}

try {
    $stConn = auragold_stock_transfer_central_mysqli();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$stock_in_types = function_exists('auragold_metal_exchange_inventory_stock_in_types_sql')
    ? auragold_metal_exchange_inventory_stock_in_types_sql()
    : "'opening','purchase','stock_journal','balance','sale_return','inward'";

/**
 * Available weight on a stock lot (current preferred, else opening).
 */
$lot_avail_wt = static function (array $row): float {
    if (function_exists('auragold_inventory_stock_available_weight')) {
        return (float) auragold_inventory_stock_available_weight($row);
    }
    $cw = (float) ($row['current_weight'] ?? 0);
    if ($cw > 1e-8) {
        return $cw;
    }
    return (float) ($row['opening_weight'] ?? 0);
};

/**
 * @return array{lots: array<int,array<string,mixed>>, available_wt: float, available_qty: float}
 */
$fetch_loose_lots = static function (mysqli $conn, int $branch_id, int $product_id, int $metal_id, int $characteristic_id) use ($stock_in_types, $lot_avail_wt): array {
    $has_status = false;
    $st_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'status'");
    if ($st_chk && mysqli_num_rows($st_chk) > 0) {
        $has_status = true;
    }
    if ($st_chk) {
        mysqli_free_result($st_chk);
    }
    $status_sql = $has_status ? ' AND status = 1' : '';
    $char_sql = $characteristic_id > 0
        ? ' AND (product_characteristic_id = ' . $characteristic_id . ' OR product_characteristic_id IS NULL OR product_characteristic_id = 0)'
        : '';

    // Prefer untagged (empty barcode) lots — true loose metal stock.
    $sql = "
        SELECT * FROM tbl_stock
        WHERE branch_id = " . (int) $branch_id . "
          AND product_id = " . (int) $product_id . "
          AND metal_id = " . (int) $metal_id . "
          AND stock_type IN ($stock_in_types)
          AND (barcode IS NULL OR TRIM(barcode) = '')
          AND (
                COALESCE(current_weight, 0) > 0
             OR COALESCE(opening_weight, 0) > 0
             OR COALESCE(current_qty, 0) > 0
             OR COALESCE(opening_qty, 0) > 0
          )
          $status_sql
          $char_sql
        ORDER BY
            CASE WHEN COALESCE(current_weight, 0) > 0 OR COALESCE(current_qty, 0) > 0 THEN 0 ELSE 1 END,
            id ASC
    ";
    $lots = [];
    $q = mysqli_query($conn, $sql);
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            if (function_exists('auragold_stock_row_is_inventory_for_issue') && !auragold_stock_row_is_inventory_for_issue($row)) {
                continue;
            }
            $lots[] = $row;
        }
        mysqli_free_result($q);
    }

    // Fallback: same product/metal at branch even if barcode present (still weight-based transfer).
    if (empty($lots)) {
        $sql2 = "
            SELECT * FROM tbl_stock
            WHERE branch_id = " . (int) $branch_id . "
              AND product_id = " . (int) $product_id . "
              AND metal_id = " . (int) $metal_id . "
              AND stock_type IN ($stock_in_types)
              AND (
                    COALESCE(current_weight, 0) > 0
                 OR COALESCE(opening_weight, 0) > 0
                 OR COALESCE(current_qty, 0) > 0
                 OR COALESCE(opening_qty, 0) > 0
              )
              $status_sql
              $char_sql
            ORDER BY
                CASE WHEN barcode IS NULL OR TRIM(barcode) = '' THEN 0 ELSE 1 END,
                CASE WHEN COALESCE(current_weight, 0) > 0 OR COALESCE(current_qty, 0) > 0 THEN 0 ELSE 1 END,
                id ASC
        ";
        $q2 = mysqli_query($conn, $sql2);
        if ($q2) {
            while ($row = mysqli_fetch_assoc($q2)) {
                if (function_exists('auragold_stock_row_is_inventory_for_issue') && !auragold_stock_row_is_inventory_for_issue($row)) {
                    continue;
                }
                $lots[] = $row;
            }
            mysqli_free_result($q2);
        }
    }

    $avail_wt = 0.0;
    $avail_qty = 0.0;
    foreach ($lots as $lot) {
        $avail_wt += $lot_avail_wt($lot);
        $cq = (float) ($lot['current_qty'] ?? 0);
        $oq = (float) ($lot['opening_qty'] ?? 0);
        $avail_qty += ($cq > 0 ? $cq : $oq);
    }

    return [
        'lots' => $lots,
        'available_wt' => round($avail_wt, 3),
        'available_qty' => round($avail_qty, 3),
    ];
};

if ($action === 'balance') {
    $pack = $fetch_loose_lots($stConn, $from_branch, $product_id, $metal_id, $characteristic_id);
    $bal = function_exists('auragold_get_current_stock_balance_row')
        ? auragold_get_current_stock_balance_row($stConn, $product_id, $from_branch, $metal_id)
        : null;
    $display_gross = (float) ($pack['available_wt'] ?? 0);
    if ($display_gross <= 0 && is_array($bal)) {
        $display_gross = (float) ($bal['display_gross_weight'] ?? 0);
    }
    echo json_encode([
        'success' => true,
        'display_gross_weight' => $display_gross,
        'display_qty' => (float) ($pack['available_qty'] ?? 0),
        'display_pure_weight' => is_array($bal) ? (float) ($bal['display_pure_weight'] ?? 0) : 0,
        'branch_id' => $from_branch,
        'lots_count' => count($pack['lots']),
    ]);
    exit;
}

// --- save ---
if ($to_branch <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select destination branch.']);
    exit;
}
if ($from_branch === $to_branch) {
    echo json_encode(['success' => false, 'message' => 'Source and destination branch must be different.']);
    exit;
}
if (function_exists('auragold_branch_is_main_or_sub_of_settings_main')) {
    if (!auragold_branch_is_main_or_sub_of_settings_main($from_branch)
        || !auragold_branch_is_main_or_sub_of_settings_main($to_branch)) {
        echo json_encode(['success' => false, 'message' => 'Invalid branch for stock transfer.']);
        exit;
    }
}
if ($transfer_wt <= 0.00001) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid transfer weight.']);
    exit;
}

$destRow = auragold_stock_transfer_branch_row_by_id($stConn, $to_branch);
if (!$destRow || empty($destRow['id'])) {
    echo json_encode(['success' => false, 'message' => 'Destination branch not found in tbl_branches.']);
    exit;
}
if (function_exists('auragold_tbl_branch_row_is_active') && !auragold_tbl_branch_row_is_active($destRow)) {
    echo json_encode(['success' => false, 'message' => 'Destination branch is not active.']);
    exit;
}

$destCr = auragold_branch_row_db_credentials($destRow);
$destDbResolved = trim((string) ($destCr['db_name'] ?? ''));
$sourceDb = auragold_stock_transfer_mysqli_database($stConn);
if ($sourceDb === '' && defined('DB_NAME')) {
    $sourceDb = trim((string) DB_NAME);
}
if ($destDbResolved === '') {
    $destDbResolved = $sourceDb;
}
if ($destDbResolved === '') {
    echo json_encode(['success' => false, 'message' => 'Could not resolve destination database name.']);
    exit;
}

$crossPhysicalDb = ($sourceDb !== '' && strcasecmp($destDbResolved, $sourceDb) !== 0);
$created_by = (int) ($_SESSION['user_id'] ?? 0);

$has_sj = false;
$sj_check = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
if ($sj_check && mysqli_num_rows($sj_check) > 0) {
    $has_sj = true;
}
if ($sj_check) {
    mysqli_free_result($sj_check);
}

$has_reference = false;
$ref_chk = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
if ($ref_chk && mysqli_num_rows($ref_chk) >= 2) {
    $has_reference = true;
}
if ($ref_chk) {
    mysqli_free_result($ref_chk);
}

$has_status_col = false;
$st_chk = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock LIKE 'status'");
if ($st_chk && mysqli_num_rows($st_chk) > 0) {
    $has_status_col = true;
}
if ($st_chk) {
    mysqli_free_result($st_chk);
}

$has_updated_at = false;
$ua_chk = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock LIKE 'updated_at'");
if ($ua_chk && mysqli_num_rows($ua_chk) > 0) {
    $has_updated_at = true;
}
if ($ua_chk) {
    mysqli_free_result($ua_chk);
}

if (!auragold_ensure_stock_cross_transfer_log_table($stConn)) {
    echo json_encode(['success' => false, 'message' => 'Could not ensure tbl_stock_cross_transfer_log: ' . mysqli_error($stConn)]);
    exit;
}

$destConn = null;
$crossDbConnectWarning = '';
if ($crossPhysicalDb) {
    try {
        $destConn = auragold_stock_transfer_mysqli_to_branch_db($destRow);
    } catch (Throwable $e) {
        // Local/dev: destination DB user may be missing — stage pending on source DB instead.
        $crossDbConnectWarning = $e->getMessage();
        $crossPhysicalDb = false;
        $destConn = null;
        $destDbResolved = $sourceDb !== '' ? $sourceDb : $destDbResolved;
    }
}
if (!$crossPhysicalDb && !auragold_ensure_stock_transfer_pending_table($stConn)) {
    echo json_encode(['success' => false, 'message' => 'Could not create tbl_stock_transfer_pending: ' . mysqli_error($stConn)]);
    exit;
}

$pack = $fetch_loose_lots($stConn, $from_branch, $product_id, $metal_id, $characteristic_id);
$available_wt = (float) $pack['available_wt'];
if ($available_wt <= 0.00001) {
    echo json_encode(['success' => false, 'message' => 'No available loose stock weight for this product at the source branch.']);
    exit;
}
if ($transfer_wt > $available_wt + 0.0001) {
    echo json_encode([
        'success' => false,
        'message' => 'Transfer weight (' . number_format($transfer_wt, 3, '.', '') . ') exceeds balance stock (' . number_format($available_wt, 3, '.', '') . ').',
        'available_wt' => $available_wt,
    ]);
    exit;
}

if (!function_exists('auragold_stock_history_audit_insert_row')) {
    require_once __DIR__ . '/../includes/stock_history_audit_journal.php';
}

$pname = '';
$pnr = @mysqli_query($stConn, 'SELECT name FROM tbl_products WHERE id = ' . $product_id . ' LIMIT 1');
if ($pnr && ($pnx = mysqli_fetch_assoc($pnr))) {
    $pname = trim((string) ($pnx['name'] ?? ''));
}
if ($pnr) {
    mysqli_free_result($pnr);
}
$metal_type = '';
$mtqr = @mysqli_query(
    $stConn,
    "SELECT TRIM(COALESCE(NULLIF(system_name,''), NULLIF(display_name,''))) AS n FROM tbl_metal WHERE id = " . $metal_id . " LIMIT 1"
);
if ($mtqr && ($mtx = mysqli_fetch_assoc($mtqr))) {
    $metal_type = trim((string) ($mtx['n'] ?? ''));
}
if ($mtqr) {
    mysqli_free_result($mtqr);
}

/**
 * Partial deduct from a source lot (same rules as material-issue / metal-exchange).
 */
$partial_deduct_lot = static function (mysqli $conn, array $stock_row, float $deduct_weight, float $deduct_qty, bool $has_updated_at): void {
    $src_id = (int) ($stock_row['id'] ?? 0);
    if ($src_id <= 0 || $deduct_weight <= 0) {
        throw new Exception('Invalid source lot for deduct.');
    }
    $stock_rate_val = (float) ($stock_row['rate'] ?? 0);
    $prev_cq = (float) ($stock_row['current_qty'] ?? 0);
    $prev_cw = (float) ($stock_row['current_weight'] ?? 0);
    $op_q = (float) ($stock_row['opening_qty'] ?? 0);
    $op_w = (float) ($stock_row['opening_weight'] ?? 0);
    $sold_q = $deduct_qty;
    $ua = $has_updated_at ? ', updated_at = NOW()' : '';

    if ($prev_cq > 0 || $prev_cw > 0) {
        if ($sold_q <= 0 && $prev_cw > 0 && $prev_cq > 0) {
            $sold_q = $prev_cq * ($deduct_weight / $prev_cw);
        }
        $balance_weight = $prev_cw - $deduct_weight;
        $new_cq = max(0.0, $prev_cq - $sold_q);
        if ($balance_weight <= 0.00001) {
            $sql = "UPDATE tbl_stock SET current_weight = 0, current_qty = 0, value = 0$ua WHERE id = $src_id";
        } else {
            $new_val = $stock_rate_val * $balance_weight;
            $sql = "UPDATE tbl_stock SET current_weight = $balance_weight, current_qty = $new_cq, final_weight = $balance_weight, value = $new_val$ua WHERE id = $src_id";
        }
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Source stock update failed: ' . mysqli_error($conn));
        }
    } else {
        $new_op_q = max(0.0, $op_q - $sold_q);
        $new_op_w = max(0.0, $op_w - $deduct_weight);
        if ($new_op_w <= 0.00001 && $new_op_q <= 0.00001) {
            $sql = "UPDATE tbl_stock SET opening_qty = 0, opening_weight = 0, final_weight = 0, value = 0$ua WHERE id = $src_id";
        } else {
            $new_val = $stock_rate_val * $new_op_w;
            $sql = "UPDATE tbl_stock SET opening_qty = $new_op_q, opening_weight = $new_op_w, final_weight = $new_op_w, value = $new_val$ua WHERE id = $src_id";
        }
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Source stock update failed: ' . mysqli_error($conn));
        }
    }
};

$destPendingIds = [];
$remaining = round($transfer_wt, 3);
$processed = 0;
$total_moved_wt = 0.0;
$transfer_doc_id = 0;
$transfer_invoice_no = '';

try {
    if ($crossPhysicalDb && $destConn) {
        if (!auragold_ensure_stock_transfer_pending_table($destConn)) {
            throw new Exception('Destination tbl_stock_transfer_pending: ' . mysqli_error($destConn));
        }
        mysqli_begin_transaction($destConn);
    }

    mysqli_begin_transaction($stConn);

    try {
        $transferDoc = auragold_stock_transfer_doc_create($stConn, $from_branch, $to_branch, $transfer_date, $created_by);
        $transfer_doc_id = (int) $transferDoc['id'];
        $transfer_invoice_no = (string) $transferDoc['invoice_no'];

        foreach ($pack['lots'] as $lotPreview) {
            if ($remaining <= 0.00001) {
                break;
            }

            $stock_id = (int) ($lotPreview['id'] ?? 0);
            if ($stock_id <= 0) {
                continue;
            }

            $lock_sql = 'SELECT * FROM tbl_stock WHERE id = ' . $stock_id . ($has_status_col ? ' AND status = 1' : '') . ' FOR UPDATE';
            $lock_q = mysqli_query($stConn, $lock_sql);
            $stock_row = ($lock_q && mysqli_num_rows($lock_q) > 0) ? mysqli_fetch_assoc($lock_q) : null;
            if ($lock_q) {
                mysqli_free_result($lock_q);
            }
            if (!$stock_row) {
                continue;
            }
            if (in_array($stock_row['stock_type'] ?? '', ['outward'], true)) {
                continue;
            }
            $src_branch_id = isset($stock_row['branch_id']) && $stock_row['branch_id'] !== null && $stock_row['branch_id'] !== ''
                ? (int) $stock_row['branch_id'] : 0;
            if ($src_branch_id !== 0 && $src_branch_id !== $from_branch) {
                continue;
            }

            $lot_wt = $lot_avail_wt($stock_row);
            if ($lot_wt <= 0.00001) {
                continue;
            }

            $move_wt = min($remaining, $lot_wt);
            $move_wt = round($move_wt, 3);
            if ($move_wt <= 0.00001) {
                continue;
            }

            $prev_cq = (float) ($stock_row['current_qty'] ?? 0);
            $prev_cw = (float) ($stock_row['current_weight'] ?? 0);
            $op_q = (float) ($stock_row['opening_qty'] ?? 0);
            $base_qty = $prev_cq > 0 ? $prev_cq : $op_q;
            $base_wt = $prev_cw > 0 ? $prev_cw : (float) ($stock_row['opening_weight'] ?? 0);
            $move_qty = 0.0;
            if ($base_wt > 0 && $base_qty > 0) {
                $move_qty = round($base_qty * ($move_wt / $base_wt), 3);
            }
            if ($move_qty <= 0 && $move_wt > 0) {
                $move_qty = ($move_wt + 0.00001 >= $lot_wt) ? max(1.0, $base_qty) : 0.0;
            }

            $ow_prod_id = (int) $stock_row['product_id'];
            $ow_char_id = (isset($stock_row['product_characteristic_id']) && $stock_row['product_characteristic_id'] !== '' && $stock_row['product_characteristic_id'] !== null)
                ? (int) $stock_row['product_characteristic_id'] : ($characteristic_id > 0 ? $characteristic_id : null);
            // Loose transfer: keep barcode empty on outward / pending so dest receives as loose.
            $ow_metal_id = (int) ($stock_row['metal_id'] ?? $metal_id);
            if ($ow_metal_id <= 0) {
                $ow_metal_id = $metal_id > 0 ? $metal_id : 1;
            }
            $ow_purity = (float) ($stock_row['opening_purity'] ?? 100);
            if ($ow_purity <= 0) {
                $ow_purity = 100.0;
            }
            $ow_rate = (float) ($stock_row['rate'] ?? 0);
            $ow_value = $ow_rate > 0 ? ($ow_rate * $move_wt) : (float) ($stock_row['value'] ?? 0);
            if ($ow_value > 0 && (float) ($stock_row['value'] ?? 0) > 0 && $base_wt > 0) {
                $ow_value = ((float) $stock_row['value'] / $base_wt) * $move_wt;
            }

            $td_esc = mysqli_real_escape_string($stConn, $transfer_date);
            $char_sql = $ow_char_id !== null ? (string) $ow_char_id : 'NULL';
            $src_id = (int) $stock_row['id'];

            $ow_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
            $ow_vals = "$ow_prod_id, $char_sql, NULL, $from_branch, $ow_metal_id, $move_wt, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value, $move_wt, $move_qty, 'outward', '$td_esc', NOW()";
            if ($has_status_col) {
                $ow_cols .= ", status";
                $ow_vals .= ", 1";
            }
            if ($has_sj) {
                $ow_cols .= ", stock_journal_id";
                $ow_vals .= ", NULL";
            }
            if ($has_reference) {
                $ow_cols .= ", reference_id, reference_type";
                $ow_vals .= ", " . (int) $transfer_doc_id . ", 'stock_transfer'";
            }
            if (!mysqli_query($stConn, "INSERT INTO tbl_stock ($ow_cols) VALUES ($ow_vals)")) {
                throw new Exception('Outward insert failed: ' . mysqli_error($stConn));
            }
            $outward_id = (int) mysqli_insert_id($stConn);

            $partial_deduct_lot($stConn, $stock_row, $move_wt, $move_qty, $has_updated_at);

            $src_db_esc = mysqli_real_escape_string($stConn, $sourceDb);
            $dst_db_esc = mysqli_real_escape_string($stConn, $destDbResolved);
            $hasLogDoc = false;
            $ldc = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock_cross_transfer_log LIKE 'transfer_doc_id'");
            if ($ldc && mysqli_num_rows($ldc) > 0) {
                $hasLogDoc = true;
            }
            if ($ldc) {
                mysqli_free_result($ldc);
            }
            $log_sql = "
                INSERT INTO tbl_stock_cross_transfer_log (
                    source_branch_id, destination_branch_id, source_db, destination_db,
                    barcode, stock_id, outward_stock_id" . ($hasLogDoc ? ', transfer_doc_id' : '') . ", destination_stock_id, move_qty, move_wt, transfer_date, created_by, status
                ) VALUES (
                    $from_branch, $to_branch, '$src_db_esc', '$dst_db_esc',
                    NULL,
                    $src_id, $outward_id" . ($hasLogDoc ? ', ' . (int) $transfer_doc_id : '') . ", NULL,
                    $move_qty, $move_wt, '$td_esc', " . ($created_by > 0 ? (string) $created_by : 'NULL') . ", 'completed'
                )
            ";
            if (!mysqli_query($stConn, $log_sql)) {
                throw new Exception('Transfer log insert failed: ' . mysqli_error($stConn));
            }

            auragold_stock_history_audit_insert_row($stConn, [
                'sj_invoice_no' => 'STOUT-' . $outward_id,
                'invoice_no' => $transfer_invoice_no,
                'sj_date' => $transfer_date,
                'barcode' => '',
                'product_id' => $ow_prod_id,
                'product_characteristic_id' => (int) ($ow_char_id ?? 0),
                'product_name' => $pname,
                'metal_id' => $ow_metal_id,
                'metal_type' => $metal_type,
                'quantity' => $move_qty,
                'gross_weight' => $move_wt,
                'less_weight' => 0,
                'net_weight' => $move_wt,
                'purity' => $ow_purity,
                'purity_weight' => 0,
                'pure_weight' => 0,
                'final_weight' => $move_wt,
                'rate' => $ow_rate,
                'amount' => $ow_value,
                'making_amount' => 0,
                'tax_amount' => 0,
                'net_amount' => $ow_value,
                'net_amt_with_tax' => $ow_value,
                'voucher_type' => 'Stock Transfer (Out)',
                'comment' => 'auragold_doc|src=st_loose|doc=' . $transfer_doc_id . '|inv=' . $transfer_invoice_no
                    . '|from=' . $from_branch . '|to=' . $to_branch
                    . ($crossPhysicalDb ? '|dstdb=' . $destDbResolved . '|in_transit=1|' : '|'),
            ]);

            if ($crossPhysicalDb && $destConn) {
                $pendingId = auragold_stock_transfer_insert_pending_in_transit(
                    $destConn,
                    $from_branch,
                    $to_branch,
                    $ow_prod_id,
                    $ow_char_id,
                    '',
                    $ow_metal_id,
                    $ow_purity,
                    $move_qty,
                    $move_wt,
                    $ow_rate,
                    $ow_value,
                    $transfer_date,
                    $outward_id,
                    $src_id,
                    $transfer_doc_id
                );
                $destPendingIds[] = $pendingId;
            } else {
                $hasPendDoc = false;
                $pdc = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock_transfer_pending LIKE 'transfer_doc_id'");
                if ($pdc && mysqli_num_rows($pdc) > 0) {
                    $hasPendDoc = true;
                }
                if ($pdc) {
                    mysqli_free_result($pdc);
                }
                $pending_sql = "
                    INSERT INTO tbl_stock_transfer_pending (
                        from_branch_id, to_branch_id, product_id, product_characteristic_id, barcode, metal_id, opening_purity,
                        move_qty, move_wt, rate, value, transfer_date, source_stock_id, outward_stock_id"
                        . ($hasPendDoc ? ', transfer_doc_id' : '') . ", status, received_stock_id, received_at
                    ) VALUES (
                        $from_branch, $to_branch, $ow_prod_id, $char_sql, NULL, $ow_metal_id, $ow_purity,
                        $move_qty, $move_wt, $ow_rate, $ow_value, '$td_esc', $src_id, $outward_id"
                        . ($hasPendDoc ? ', ' . (int) $transfer_doc_id : '') . ", 'pending', NULL, NULL
                    )
                ";
                if (!mysqli_query($stConn, $pending_sql)) {
                    throw new Exception('Transfer pending record failed: ' . mysqli_error($stConn));
                }
            }

            auragold_stock_transfer_doc_bump_totals($stConn, $transfer_doc_id, $move_qty, $move_wt);
            $remaining = round($remaining - $move_wt, 3);
            $total_moved_wt += $move_wt;
            $processed++;
        }

        if ($processed === 0 || $total_moved_wt <= 0.00001) {
            throw new Exception('Could not deduct transfer weight from source stock.');
        }
        if ($remaining > 0.001) {
            throw new Exception('Only ' . number_format($total_moved_wt, 3, '.', '') . ' wt available; could not fulfill ' . number_format($transfer_wt, 3, '.', '') . '.');
        }

        mysqli_commit($stConn);
        if ($crossPhysicalDb && $destConn) {
            mysqli_commit($destConn);
        }
    } catch (Throwable $e) {
        mysqli_rollback($stConn);
        if ($crossPhysicalDb && $destConn) {
            mysqli_rollback($destConn);
            if (!empty($destPendingIds)) {
                mysqli_begin_transaction($destConn);
                auragold_stock_transfer_dest_delete_pending_by_ids($destConn, $destPendingIds);
                mysqli_commit($destConn);
            }
        }
        throw $e;
    }

    if ($destConn) {
        mysqli_close($destConn);
        $destConn = null;
    }

    $opDb = auragold_stock_transfer_mysqli_database($stConn);
    if ($opDb === '' && defined('DB_NAME')) {
        $opDb = (string) DB_NAME;
    }

    $msg = 'Invoice ' . $transfer_invoice_no . '. '
        . ($crossPhysicalDb
            ? 'Transferred ' . number_format($total_moved_wt, 3, '.', '') . ' wt (loose) in transit on database "' . $destDbResolved . '". Source stock deducted; receive at destination to post into stock.'
            : 'Transferred ' . number_format($total_moved_wt, 3, '.', '') . ' wt (loose) in transit for branch #' . $to_branch . '. Outward stock created; use Stock Receive History to receive.');

    $out = [
        'success' => true,
        'message' => $msg,
        'count' => $processed,
        'moved_wt' => round($total_moved_wt, 3),
        'invoice_no' => $transfer_invoice_no,
        'transfer_doc_id' => $transfer_doc_id,
        'product_name' => $pname,
        'database' => $opDb,
        'destination_database' => $destDbResolved,
        'cross_database' => $crossPhysicalDb,
    ];
    if ($crossDbConnectWarning !== '') {
        $out['connect_fallback'] = true;
        $out['connect_warning'] = $crossDbConnectWarning;
    }
    echo json_encode($out);
} catch (Throwable $e) {
    if ($destConn instanceof mysqli) {
        @mysqli_close($destConn);
    }
    @mysqli_rollback($stConn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
