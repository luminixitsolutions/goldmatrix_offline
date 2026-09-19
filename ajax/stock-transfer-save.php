<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/stock_transfer_pending_schema.php';
require_once __DIR__ . '/../includes/auragold_stock_cross_transfer_log_schema.php';
require_once __DIR__ . '/../includes/auragold_stock_transfer_save_helpers.php';
require_once __DIR__ . '/../includes/stock_transfer_doc_schema.php';

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

$from_branch = isset($payload['from_branch_id']) ? (int) $payload['from_branch_id'] : 0;
$to_branch   = isset($payload['to_branch_id']) ? (int) $payload['to_branch_id'] : 0;
$transfer_date = isset($payload['transfer_date']) ? trim((string) $payload['transfer_date']) : '';
$stock_ids   = isset($payload['stock_ids']) && is_array($payload['stock_ids']) ? $payload['stock_ids'] : [];
$loose_items = isset($payload['loose_items']) && is_array($payload['loose_items']) ? $payload['loose_items'] : [];

if ($from_branch <= 0 || $to_branch <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select source and destination branch.']);
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
if (empty($stock_ids) && empty($loose_items)) {
    echo json_encode(['success' => false, 'message' => 'No items to transfer.']);
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
    echo json_encode(['success' => false, 'message' => 'Could not resolve destination database name (tbl_branches.db_name is empty and source database is unknown).']);
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

$mirrorDbRaw = getenv('AURAGOLD_MIRROR_STOCK_DB');
$mirrorDbName = ($mirrorDbRaw === false || $mirrorDbRaw === null) ? '' : trim((string) $mirrorDbRaw);
if ($mirrorDbName === '' || strcasecmp($mirrorDbName, 'none') === 0 || strcasecmp($mirrorDbName, 'off') === 0) {
    $mirrorDbName = '';
}

$destConn = null;

if ($crossPhysicalDb) {
    try {
        $destConn = auragold_stock_transfer_mysqli_to_branch_db($destRow);
    } catch (Throwable $e) {
        // Local/dev: destination DB user may be missing — stage pending on source DB instead.
        $crossPhysicalDb = false;
        $destConn = null;
        if ($sourceDb !== '') {
            $destDbResolved = $sourceDb;
        }
    }
}

if (!$crossPhysicalDb && !auragold_ensure_stock_transfer_pending_table($stConn)) {
    echo json_encode(['success' => false, 'message' => 'Could not create tbl_stock_transfer_pending: ' . mysqli_error($stConn)]);
    exit;
}

/**
 * @return array<int,array<string,mixed>>
 */
$build_jobs = static function (mysqli $stConn, array $stock_ids, int $from_branch, bool $has_status_col) {
    $jobs = [];
    foreach ($stock_ids as $sid) {
        $stock_id = (int) $sid;
        if ($stock_id <= 0) {
            continue;
        }
        $sql = 'SELECT * FROM tbl_stock WHERE id = ' . $stock_id . ($has_status_col ? ' AND status = 1' : '') . ' LIMIT 1';
        $q = mysqli_query($stConn, $sql);
        $stock_row = ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
        if ($q) {
            mysqli_free_result($q);
        }
        if (!$stock_row) {
            throw new Exception('Stock row not found: ' . $stock_id);
        }
        $src_branch_id = isset($stock_row['branch_id']) && $stock_row['branch_id'] !== null && $stock_row['branch_id'] !== ''
            ? (int) $stock_row['branch_id'] : 0;
        if ($src_branch_id !== 0 && $src_branch_id !== $from_branch) {
            throw new Exception('Stock #' . $stock_id . ' is not at the source branch.');
        }
        if (in_array($stock_row['stock_type'] ?? '', ['outward'], true)) {
            throw new Exception('Invalid stock type for transfer: #' . $stock_id);
        }

        $cw = (float) ($stock_row['current_weight'] ?? 0);
        $cq = (float) ($stock_row['current_qty'] ?? 0);
        $move_wt = $cw > 0 ? $cw : (float) ($stock_row['opening_weight'] ?? 0);
        $move_qty = $cq > 0 ? $cq : (float) ($stock_row['opening_qty'] ?? 1);
        if ($move_wt <= 0) {
            $move_wt = (float) ($stock_row['final_weight'] ?? 0);
        }
        if ($move_qty <= 0 && $move_wt > 0) {
            $move_qty = 1.0;
        }
        if ($move_wt <= 0 && $move_qty <= 0) {
            throw new Exception('Stock #' . $stock_id . ' has no available quantity.');
        }

        $jobs[] = [
            'stock_id' => $stock_id,
            'stock_row' => $stock_row,
            'move_wt' => $move_wt,
            'move_qty' => $move_qty,
        ];
    }
    return $jobs;
};

$destPendingIds = [];

try {
    $jobs = !empty($stock_ids) ? $build_jobs($stConn, $stock_ids, $from_branch, $has_status_col) : [];
    if (empty($jobs) && empty($loose_items)) {
        throw new Exception('No valid stock lines to process.');
    }

    // Allocate invoice up-front so cross-DB pending rows can store transfer_doc_id.
    $transferDoc = auragold_stock_transfer_doc_create($stConn, $from_branch, $to_branch, $transfer_date, $created_by);
    $transfer_doc_id = (int) $transferDoc['id'];
    $transfer_invoice_no = (string) $transferDoc['invoice_no'];

    if ($crossPhysicalDb && $destConn) {
        if (!auragold_ensure_stock_transfer_pending_table($destConn)) {
            throw new Exception('Destination tbl_stock_transfer_pending: ' . mysqli_error($destConn));
        }
        mysqli_begin_transaction($destConn);
        try {
            foreach ($jobs as $i => $job) {
                $stock_row = $job['stock_row'];
                $bc = trim((string) ($stock_row['barcode'] ?? ''));
                if ($bc !== '' && auragold_stock_transfer_dest_has_pending_barcode($destConn, $to_branch, $bc)) {
                    throw new Exception('Barcode already in transit at destination branch: ' . $bc);
                }
                if ($bc !== '' && auragold_stock_transfer_dest_has_active_barcode($destConn, $to_branch, $bc)) {
                    throw new Exception('Duplicate barcode at destination branch: ' . $bc);
                }
                $ow_prod_id = (int) $stock_row['product_id'];
                $ow_char_id = (isset($stock_row['product_characteristic_id']) && $stock_row['product_characteristic_id'] !== '' && $stock_row['product_characteristic_id'] !== null)
                    ? (int) $stock_row['product_characteristic_id'] : null;
                $ow_metal_id = (int) ($stock_row['metal_id'] ?? 0);
                if ($ow_metal_id <= 0) {
                    $ow_metal_id = 1;
                }
                $ow_purity = (float) ($stock_row['opening_purity'] ?? 100);
                if ($ow_purity <= 0) {
                    $ow_purity = 100.0;
                }
                $ow_rate = (float) ($stock_row['rate'] ?? 0);
                $move_wt = (float) $job['move_wt'];
                $move_qty = (float) $job['move_qty'];
                $ow_value = (float) ($stock_row['value'] ?? 0);
                if ($ow_value <= 0 && $ow_rate > 0 && $move_wt > 0) {
                    $ow_value = $ow_rate * $move_wt;
                }
                $pendingId = auragold_stock_transfer_insert_pending_in_transit(
                    $destConn,
                    $from_branch,
                    $to_branch,
                    $ow_prod_id,
                    $ow_char_id,
                    (string) ($stock_row['barcode'] ?? ''),
                    $ow_metal_id,
                    $ow_purity,
                    $move_qty,
                    $move_wt,
                    $ow_rate,
                    $ow_value,
                    $transfer_date,
                    null,
                    (int) $job['stock_id'],
                    $transfer_doc_id
                );
                $jobs[$i]['dest_pending_id'] = $pendingId;
                $destPendingIds[] = $pendingId;
            }
            mysqli_commit($destConn);
        } catch (Throwable $e) {
            mysqli_rollback($destConn);
            throw $e;
        }
    }

    mysqli_begin_transaction($stConn);

    $mirror_ops = [];
    $processed = 0;

    try {
        foreach ($jobs as $job) {
            $stock_id = (int) $job['stock_id'];
            $move_wt_job = (float) $job['move_wt'];
            $move_qty_job = (float) $job['move_qty'];

            $lock_sql = 'SELECT * FROM tbl_stock WHERE id = ' . $stock_id . ($has_status_col ? ' AND status = 1' : '') . ' FOR UPDATE';
            $lock_q = mysqli_query($stConn, $lock_sql);
            $stock_row = ($lock_q && mysqli_num_rows($lock_q) > 0) ? mysqli_fetch_assoc($lock_q) : null;
            if ($lock_q) {
                mysqli_free_result($lock_q);
            }
            if (!$stock_row) {
                throw new Exception('Stock row not found (concurrent change?): ' . $stock_id);
            }

            $cw = (float) ($stock_row['current_weight'] ?? 0);
            $cq = (float) ($stock_row['current_qty'] ?? 0);
            $move_wt = $cw > 0 ? $cw : (float) ($stock_row['opening_weight'] ?? 0);
            $move_qty = $cq > 0 ? $cq : (float) ($stock_row['opening_qty'] ?? 1);
            if ($move_wt <= 0) {
                $move_wt = (float) ($stock_row['final_weight'] ?? 0);
            }
            if ($move_qty <= 0 && $move_wt > 0) {
                $move_qty = 1.0;
            }
            if ($move_wt <= 0 && $move_qty <= 0) {
                throw new Exception('Stock #' . $stock_id . ' no longer has available quantity.');
            }
            if (abs($move_wt - $move_wt_job) > 0.0001 || abs($move_qty - $move_qty_job) > 0.0001) {
                throw new Exception('Stock #' . $stock_id . ' changed while saving; please reload and try again.');
            }

            $src_branch_id = isset($stock_row['branch_id']) && $stock_row['branch_id'] !== null && $stock_row['branch_id'] !== ''
                ? (int) $stock_row['branch_id'] : 0;
            if ($src_branch_id !== 0 && $src_branch_id !== $from_branch) {
                throw new Exception('Stock #' . $stock_id . ' is not at the source branch.');
            }
            if (in_array($stock_row['stock_type'] ?? '', ['outward'], true)) {
                throw new Exception('Invalid stock type for transfer: #' . $stock_id);
            }

            $ow_prod_id = (int) $stock_row['product_id'];
            $ow_char_id = (isset($stock_row['product_characteristic_id']) && $stock_row['product_characteristic_id'] !== '' && $stock_row['product_characteristic_id'] !== null)
                ? (int) $stock_row['product_characteristic_id'] : null;
            $ow_barcode_esc = mysqli_real_escape_string($stConn, (string) ($stock_row['barcode'] ?? ''));
            $ow_metal_id = (int) ($stock_row['metal_id'] ?? 0);
            if ($ow_metal_id <= 0) {
                $ow_metal_id = 1;
            }
            $ow_purity = (float) ($stock_row['opening_purity'] ?? 100);
            if ($ow_purity <= 0) {
                $ow_purity = 100.0;
            }
            $ow_rate = (float) ($stock_row['rate'] ?? 0);
            $ow_value = (float) ($stock_row['value'] ?? 0);
            if ($ow_value <= 0 && $ow_rate > 0 && $move_wt > 0) {
                $ow_value = $ow_rate * $move_wt;
            }

            $td_esc = mysqli_real_escape_string($stConn, $transfer_date);
            $char_sql = $ow_char_id !== null ? (string) $ow_char_id : 'NULL';

            if (!function_exists('auragold_stock_history_audit_insert_row')) {
                require_once __DIR__ . '/../includes/stock_history_audit_journal.php';
            }
            $pname = '';
            if ($ow_prod_id > 0) {
                $pnr = @mysqli_query($stConn, 'SELECT name FROM tbl_products WHERE id = ' . $ow_prod_id . ' LIMIT 1');
                if ($pnr && ($pnx = mysqli_fetch_assoc($pnr))) {
                    $pname = trim((string) ($pnx['name'] ?? ''));
                }
                if ($pnr) {
                    mysqli_free_result($pnr);
                }
            }
            $metal_type = '';
            if ($ow_metal_id > 0) {
                $mtqr = @mysqli_query(
                    $stConn,
                    "SELECT TRIM(COALESCE(NULLIF(system_name,''), NULLIF(display_name,''))) AS n FROM tbl_metal WHERE id = " . $ow_metal_id . " LIMIT 1"
                );
                if ($mtqr && ($mtx = mysqli_fetch_assoc($mtqr))) {
                    $metal_type = trim((string) ($mtx['n'] ?? ''));
                }
                if ($mtqr) {
                    mysqli_free_result($mtqr);
                }
            }

            $src_id = (int) $stock_row['id'];

            if (!$crossPhysicalDb) {
                $bc_raw = (string) ($stock_row['barcode'] ?? '');
                if ($bc_raw !== '' && auragold_stock_transfer_dest_has_pending_barcode($stConn, $to_branch, $bc_raw)) {
                    throw new Exception('Barcode already in transit at destination branch: ' . $bc_raw);
                }
                if ($bc_raw !== '' && auragold_stock_transfer_dest_has_active_barcode($stConn, $to_branch, $bc_raw)) {
                    throw new Exception('Duplicate barcode at destination branch: ' . $bc_raw);
                }
            }

            $ow_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
            $ow_vals = "$ow_prod_id, $char_sql, '$ow_barcode_esc', $from_branch, $ow_metal_id, $move_wt, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value, $move_wt, $move_qty, 'outward', '$td_esc', NOW()";
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

            $upd_src = "UPDATE tbl_stock SET current_weight = 0, current_qty = 0, opening_weight = 0, opening_qty = 0, final_weight = 0, value = 0" . ($has_updated_at ? ", updated_at = NOW()" : "") . " WHERE id = $src_id";
            if (!mysqli_query($stConn, $upd_src)) {
                throw new Exception('Source stock update failed: ' . mysqli_error($stConn));
            }

            $barcode_log_esc = mysqli_real_escape_string($stConn, trim((string) ($stock_row['barcode'] ?? '')));
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
                    " . ($barcode_log_esc === '' ? 'NULL' : "'" . $barcode_log_esc . "'") . ",
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
                'barcode' => (string) ($stock_row['barcode'] ?? ''),
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
                'comment' => 'auragold_doc|src=st|doc=' . $transfer_doc_id . '|inv=' . $transfer_invoice_no
                    . '|from=' . $from_branch . '|to=' . $to_branch
                    . ($crossPhysicalDb ? '|dstdb=' . $destDbResolved . '|in_transit=1|' : '|'),
            ]);

            if (!$crossPhysicalDb) {
                $bc_raw = (string) ($stock_row['barcode'] ?? '');
                $barcode_sql = ($bc_raw === '') ? 'NULL' : "'" . $ow_barcode_esc . "'";
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
                        $from_branch, $to_branch, $ow_prod_id, $char_sql, $barcode_sql, $ow_metal_id, $ow_purity,
                        $move_qty, $move_wt, $ow_rate, $ow_value, '$td_esc', $src_id, $outward_id"
                        . ($hasPendDoc ? ', ' . (int) $transfer_doc_id : '') . ", 'pending', NULL, NULL
                    )
                ";
                if (!mysqli_query($stConn, $pending_sql)) {
                    throw new Exception('Transfer pending record failed: ' . mysqli_error($stConn));
                }

                if ($mirrorDbName !== '' && strcasecmp($mirrorDbName, (string) (defined('DB_NAME') ? DB_NAME : '')) !== 0) {
                    $mirror_ops[] = ['pending' => preg_replace('/\s+/', ' ', trim($pending_sql))];
                }
            }

            auragold_stock_transfer_doc_bump_totals($stConn, $transfer_doc_id, $move_qty, $move_wt);
            $processed++;
        }

        // Loose weight lines under the same invoice document.
        if (!empty($loose_items)) {
            require_once __DIR__ . '/../includes/auragold_stock_transfer_loose_process.php';
            foreach ($loose_items as $li) {
                if (!is_array($li)) {
                    continue;
                }
                $n = auragold_stock_transfer_process_loose_item(
                    $stConn,
                    $destConn,
                    $from_branch,
                    $to_branch,
                    $transfer_date,
                    $li,
                    $transfer_doc_id,
                    $transfer_invoice_no,
                    $crossPhysicalDb,
                    $sourceDb,
                    $destDbResolved,
                    $created_by,
                    $has_status_col,
                    $has_sj,
                    $has_reference,
                    $has_updated_at,
                    $destPendingIds
                );
                $processed += (int) $n;
            }
        }

        if ($processed === 0) {
            throw new Exception('No valid stock lines processed.');
        }

        mysqli_commit($stConn);

    } catch (Throwable $e) {
        mysqli_rollback($stConn);
        if ($crossPhysicalDb && $destConn && !empty($destPendingIds)) {
            mysqli_begin_transaction($destConn);
            auragold_stock_transfer_dest_delete_pending_by_ids($destConn, $destPendingIds);
            mysqli_commit($destConn);
        }
        throw $e;
    }

    if ($destConn) {
        mysqli_close($destConn);
        $destConn = null;
    }

    $mirrorWarning = '';
    if ($mirrorDbName !== '' && strcasecmp($mirrorDbName, (string) (defined('DB_NAME') ? DB_NAME : '')) !== 0 && !empty($mirror_ops)) {
        $mconn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $mirrorDbName);
        if ($mconn) {
            mysqli_set_charset($mconn, 'utf8mb4');
            auragold_ensure_stock_transfer_pending_table($mconn);
            mysqli_begin_transaction($mconn);
            try {
                foreach ($mirror_ops as $op) {
                    if (empty($op['pending']) || !mysqli_query($mconn, $op['pending'])) {
                        throw new Exception('tbl_stock_transfer_pending: ' . mysqli_error($mconn));
                    }
                }
                mysqli_commit($mconn);
            } catch (Exception $me) {
                mysqli_rollback($mconn);
                $mirrorWarning = 'Also saved to ' . $mirrorDbName . ' failed: ' . $me->getMessage();
            }
            mysqli_close($mconn);
        } else {
            $mirrorWarning = 'Also saved to ' . $mirrorDbName . ' failed: could not connect (' . mysqli_connect_error() . ')';
        }
    }

    $dbLabel = defined('DB_NAME') ? (string) DB_NAME : '';
    $opDb = auragold_stock_transfer_mysqli_database($stConn);
    if ($opDb === '') {
        $opDb = $dbLabel;
    }

    $msg = ($transfer_invoice_no !== '' ? ('Invoice ' . $transfer_invoice_no . '. ') : '')
        . ($crossPhysicalDb
            ? 'Staged ' . $processed . ' item(s) in transit on database "' . $destDbResolved . '". Source stock deducted; receive at destination to post into stock.'
            : 'Staged ' . $processed . ' item(s) in transit for branch #' . $to_branch . '. Use Stock Receive History to receive into stock.');

    $out = [
        'success'   => true,
        'message'   => $msg,
        'count'     => $processed,
        'invoice_no' => $transfer_invoice_no,
        'transfer_doc_id' => $transfer_doc_id,
        'database'  => $opDb,
        'operational_database' => $opDb,
        'destination_database' => $destDbResolved,
        'cross_database' => $crossPhysicalDb,
    ];
    if ($mirrorDbName !== '' && strcasecmp($mirrorDbName, $dbLabel) !== 0) {
        $out['mirror_database'] = $mirrorDbName;
        $out['mirror_attempted'] = !empty($mirror_ops);
        if ($mirrorWarning !== '') {
            $out['mirror_warning'] = $mirrorWarning;
        }
    }
    echo json_encode($out);
} catch (Throwable $e) {
    if ($destConn instanceof mysqli) {
        @mysqli_close($destConn);
    }
    @mysqli_rollback($stConn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
