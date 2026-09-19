<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/stock_transfer_pending_schema.php';
require_once __DIR__ . '/../includes/branch_credentials.php';

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

$pending_ids = isset($payload['pending_ids']) && is_array($payload['pending_ids']) ? $payload['pending_ids'] : [];
$pending_ids = array_values(array_filter(array_map('intval', $pending_ids), static function ($x) {
    return $x > 0;
}));
if (empty($pending_ids)) {
    echo json_encode(['success' => false, 'message' => 'No pending lines selected.']);
    exit;
}

try {
    $conn = auragold_stock_transfer_central_mysqli();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

if (!auragold_ensure_stock_transfer_pending_table($conn)) {
    echo json_encode(['success' => false, 'message' => 'tbl_stock_transfer_pending: ' . mysqli_error($conn)]);
    exit;
}

$has_sj = false;
$sj_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
if ($sj_check && mysqli_num_rows($sj_check) > 0) {
    $has_sj = true;
}
if ($sj_check) {
    mysqli_free_result($sj_check);
}

$has_reference = false;
$ref_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
if ($ref_chk && mysqli_num_rows($ref_chk) >= 2) {
    $has_reference = true;
}
if ($ref_chk) {
    mysqli_free_result($ref_chk);
}

$has_status_col = false;
$st_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'status'");
if ($st_chk && mysqli_num_rows($st_chk) > 0) {
    $has_status_col = true;
}
if ($st_chk) {
    mysqli_free_result($st_chk);
}

/**
 * Schema name actually in use by a mysqli link (session branch DB may differ from DB_NAME constant).
 */
function auragold_receive_mysqli_current_database(mysqli $conn) {
    $r = @mysqli_query($conn, 'SELECT DATABASE() AS d');
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        mysqli_free_result($r);
        return trim((string) ($row['d'] ?? ''));
    }
    return '';
}

/**
 * Mark tbl_stock_transfer_pending as received on the source branch database (where the outward row exists).
 * Destination receive updates only the current $conn; pending is also stored on source after transfer save.
 *
 * Important: DB_NAME in config is the bootstrap/main schema and does NOT change when working_db points $conn
 * at another branch. Compare source db_name to mysqli's DATABASE(), not to DB_NAME.
 */
function auragold_receive_sync_pending_on_source_db($conn_master, mysqli $conn, array $pen, $destination_received_stock_id) {
    $from_bid = (int) ($pen['from_branch_id'] ?? 0);
    $owid = (int) ($pen['outward_stock_id'] ?? 0);
    if ($from_bid <= 0 || $owid <= 0) {
        return '';
    }
    $bid_esc = (string) $from_bid;
    $bq = @mysqli_query($conn_master, "SELECT * FROM tbl_branches WHERE id = $bid_esc LIMIT 1");
    if (!$bq || mysqli_num_rows($bq) === 0) {
        if ($bq) {
            mysqli_free_result($bq);
        }
        return '';
    }
    $brow = mysqli_fetch_assoc($bq);
    mysqli_free_result($bq);
    $cr = auragold_branch_row_db_credentials($brow);
    $sdb = trim((string) ($cr['db_name'] ?? ''));
    // Main branch rows often leave database empty; stock lives in bootstrap DB_NAME (see product_opening_save_core).
    if ($sdb === '' && defined('DB_NAME')) {
        $sdb = trim((string) DB_NAME);
    }
    if ($sdb === '') {
        return 'Source branch #' . $from_bid . ': no database name in tbl_branches';
    }
    $currentDb = auragold_receive_mysqli_current_database($conn);
    if ($currentDb !== '' && strcasecmp($sdb, $currentDb) === 0) {
        return '';
    }
    $dbu = trim((string) ($cr['db_user'] ?? ''));
    $dbp = (string) ($cr['db_pass'] ?? '');
    if ($dbu === '') {
        $dbu = DB_USER;
        $dbp = DB_PASS;
    }
    $sconn = @mysqli_connect(DB_HOST, $dbu, $dbp, $sdb);
    if (!$sconn) {
        return 'Source DB ' . $sdb . ': connect failed (' . mysqli_connect_error() . ')';
    }
    mysqli_set_charset($sconn, 'utf8mb4');
    if (!auragold_ensure_stock_transfer_pending_table($sconn)) {
        mysqli_close($sconn);
        return 'Source DB ' . $sdb . ': tbl_stock_transfer_pending unavailable';
    }
    $rid = (int) $destination_received_stock_id;
    $upd = "UPDATE tbl_stock_transfer_pending SET status = 'received', received_stock_id = $rid, received_at = NOW() WHERE outward_stock_id = $owid AND status = 'pending'";
    if (!mysqli_query($sconn, $upd)) {
        $err = mysqli_error($sconn);
        mysqli_close($sconn);
        return 'Source DB ' . $sdb . ': pending update failed: ' . $err;
    }
    mysqli_close($sconn);
    return '';
}

/**
 * After receive on destination DB, set tbl_stock_cross_transfer_log.destination_stock_id on the source branch DB
 * so stock-transfer-history can show In transit vs Received for cross-database transfers.
 *
 * @return string non-empty warning on failure (best-effort; receive already committed)
 */
function auragold_receive_update_source_cross_transfer_log(mysqli $branchRegistryConn, mysqli $destConn, array $pen, int $new_stock_id): string {
    $srcStockId = (int) ($pen['source_stock_id'] ?? 0);
    if ($srcStockId <= 0 || $new_stock_id <= 0) {
        return '';
    }
    $fromBid = (int) ($pen['from_branch_id'] ?? 0);
    $toBid = (int) ($pen['to_branch_id'] ?? 0);
    if ($fromBid <= 0 || $toBid <= 0) {
        return '';
    }
    $bid_esc = (string) $fromBid;
    $bq = @mysqli_query($branchRegistryConn, "SELECT * FROM tbl_branches WHERE id = " . $bid_esc . " LIMIT 1");
    if (!$bq || mysqli_num_rows($bq) === 0) {
        if ($bq) {
            mysqli_free_result($bq);
        }
        return '';
    }
    $brow = mysqli_fetch_assoc($bq);
    mysqli_free_result($bq);
    if (!is_array($brow)) {
        return '';
    }
    $cr = auragold_branch_row_db_credentials($brow);
    $sdb = trim((string) ($cr['db_name'] ?? ''));
    if ($sdb === '' && defined('DB_NAME')) {
        $sdb = trim((string) DB_NAME);
    }
    if ($sdb === '') {
        return '';
    }
    $currentDb = auragold_receive_mysqli_current_database($destConn);
    if ($currentDb !== '' && strcasecmp($sdb, $currentDb) === 0) {
        // Same physical DB as receive: history uses pen row, not cross log id for status.
        return '';
    }
    $dbu = trim((string) ($cr['db_user'] ?? ''));
    $dbp = (string) ($cr['db_pass'] ?? '');
    if ($dbu === '') {
        $dbu = DB_USER;
        $dbp = DB_PASS;
    }
    $sconn = @mysqli_connect(DB_HOST, $dbu, $dbp, $sdb);
    if (!$sconn) {
        return 'cross_log: source DB connect failed';
    }
    mysqli_set_charset($sconn, 'utf8mb4');
    $chk = @mysqli_query($sconn, "SHOW TABLES LIKE 'tbl_stock_cross_transfer_log'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) {
            mysqli_free_result($chk);
        }
        mysqli_close($sconn);
        return '';
    }
    mysqli_free_result($chk);
    $nid = (int) $new_stock_id;
    $upd = "UPDATE tbl_stock_cross_transfer_log SET destination_stock_id = $nid
        WHERE stock_id = $srcStockId AND destination_branch_id = $toBid
        AND (destination_stock_id IS NULL OR destination_stock_id = 0)
        ORDER BY id DESC LIMIT 1";
    if (!mysqli_query($sconn, $upd)) {
        $err = mysqli_error($sconn);
        mysqli_close($sconn);
        return 'cross_log: ' . $err;
    }
    mysqli_close($sconn);
    return '';
}

$mirrorRows = [];

// Mirror receive into a second DB only when env is set; default off (single database, destination = branch_id on tbl_stock).
$mirrorDbRaw = getenv('AURAGOLD_MIRROR_STOCK_DB');
$mirrorDbName = ($mirrorDbRaw === false || $mirrorDbRaw === null) ? '' : trim((string) $mirrorDbRaw);
if ($mirrorDbName === '' || strcasecmp($mirrorDbName, 'none') === 0 || strcasecmp($mirrorDbName, 'off') === 0) {
    $mirrorDbName = '';
}

mysqli_begin_transaction($conn);

try {
    $processed = 0;
    $id_list = implode(',', $pending_ids);
    $q = mysqli_query($conn, "SELECT * FROM tbl_stock_transfer_pending WHERE id IN ($id_list) AND status = 'pending' FOR UPDATE");
    if (!$q) {
        throw new Exception('Lock pending rows failed: ' . mysqli_error($conn));
    }
    $rows = [];
    while ($r = mysqli_fetch_assoc($q)) {
        $rows[(int) $r['id']] = $r;
    }
    mysqli_free_result($q);

    foreach ($pending_ids as $pid) {
        if (!isset($rows[$pid])) {
            continue;
        }
        $pen = $rows[$pid];
        $to_branch = (int) $pen['to_branch_id'];
        $ow_prod_id = (int) $pen['product_id'];
        $ow_char_id = isset($pen['product_characteristic_id']) && $pen['product_characteristic_id'] !== null && $pen['product_characteristic_id'] !== ''
            ? (int) $pen['product_characteristic_id'] : null;
        $ow_barcode_esc = mysqli_real_escape_string($conn, (string) ($pen['barcode'] ?? ''));
        $ow_metal_id = (int) ($pen['metal_id'] ?? 1);
        if ($ow_metal_id <= 0) {
            $ow_metal_id = 1;
        }
        $ow_purity = (float) ($pen['opening_purity'] ?? 100);
        if ($ow_purity <= 0) {
            $ow_purity = 100.0;
        }
        $ow_rate = (float) ($pen['rate'] ?? 0);
        $move_wt = (float) ($pen['move_wt'] ?? 0);
        $move_qty = (float) ($pen['move_qty'] ?? 0);
        if ($move_qty <= 0 && $move_wt > 0) {
            $move_qty = 1;
        }
        $ow_value = (float) ($pen['value'] ?? 0);
        if ($ow_value <= 0 && $ow_rate > 0 && $move_wt > 0) {
            $ow_value = $ow_rate * $move_wt;
        }
        $td = $pen['transfer_date'] ?? date('Y-m-d');
        $td_esc = mysqli_real_escape_string($conn, (string) $td);

        $char_sql = $ow_char_id !== null ? (string) $ow_char_id : 'NULL';

        $in_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
        $in_vals = "$ow_prod_id, $char_sql, '$ow_barcode_esc', $to_branch, $ow_metal_id, $move_wt, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value, $move_wt, $move_qty, 'purchase', '$td_esc', NOW()";
        if ($has_status_col) {
            $in_cols .= ", status";
            $in_vals .= ", 1";
        }
        if ($has_sj) {
            $in_cols .= ", stock_journal_id";
            $in_vals .= ", NULL";
        }
        if ($has_reference) {
            $in_cols .= ", reference_id, reference_type";
            $in_vals .= ", NULL, 'stock_transfer'";
        }
        if (!mysqli_query($conn, "INSERT INTO tbl_stock ($in_cols) VALUES ($in_vals)")) {
            throw new Exception('tbl_stock insert failed: ' . mysqli_error($conn));
        }
        $new_stock_id = (int) mysqli_insert_id($conn);

        $tbl_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
        if ($tbl_inward && mysqli_num_rows($tbl_inward) > 0) {
            mysqli_free_result($tbl_inward);
            $barcode_inward = (($pen['barcode'] ?? '') === '') ? 'NULL' : "'" . $ow_barcode_esc . "'";
            $metal_inward = $ow_metal_id > 0 ? (string) $ow_metal_id : 'NULL';
            $inward_sql = "
                INSERT INTO tbl_inward_stock (
                    stock_journal_id, product_id, product_characteristic_id, barcode_no,
                    branch_id, metal_id, qty, weight, rate, value, stock_type, transaction_date, created_at
                ) VALUES (
                    NULL,
                    $ow_prod_id,
                    $char_sql,
                    $barcode_inward,
                    $to_branch,
                    $metal_inward,
                    $move_qty,
                    $move_wt,
                    $ow_rate,
                    $ow_value,
                    'purchase',
                    '$td_esc',
                    NOW()
                )
            ";
            if (!mysqli_query($conn, $inward_sql)) {
                throw new Exception('tbl_inward_stock: ' . mysqli_error($conn));
            }
        }

        if (!function_exists('auragold_stock_history_audit_insert_row')) {
            require_once __DIR__ . '/../includes/stock_history_audit_journal.php';
        }
        $pname = '';
        if ($ow_prod_id > 0) {
            $pnr = @mysqli_query($conn, 'SELECT name FROM tbl_products WHERE id = ' . $ow_prod_id . ' LIMIT 1');
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
                $conn,
                "SELECT TRIM(COALESCE(NULLIF(system_name,''), NULLIF(display_name,''))) AS n FROM tbl_metal WHERE id = " . $ow_metal_id . " LIMIT 1"
            );
            if ($mtqr && ($mtx = mysqli_fetch_assoc($mtqr))) {
                $metal_type = trim((string) ($mtx['n'] ?? ''));
            }
            if ($mtqr) {
                mysqli_free_result($mtqr);
            }
        }
        $sj_dt = (string) $td;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sj_dt)) {
            $sj_dt = date('Y-m-d');
        }
        $from_b = (int) ($pen['from_branch_id'] ?? 0);
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => 'STIN-' . $new_stock_id,
            'invoice_no' => 'ST-P' . $pid,
            'sj_date' => $sj_dt,
            'barcode' => (string) ($pen['barcode'] ?? ''),
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
            'voucher_type' => 'Stock Transfer (In)',
            'comment' => 'auragold_doc|src=st|pid=' . $pid . '|to=' . $to_branch . '|from=' . $from_b . '|stock=' . $new_stock_id . '|',
        ]);

        $upd = "UPDATE tbl_stock_transfer_pending SET status = 'received', received_stock_id = $new_stock_id, received_at = NOW() WHERE id = $pid AND status = 'pending'";
        if (!mysqli_query($conn, $upd)) {
            throw new Exception('Update pending failed: ' . mysqli_error($conn));
        }
        if (mysqli_affected_rows($conn) === 0) {
            throw new Exception('Pending row not updated: #' . $pid);
        }

        $mirrorRows[] = ['pen' => $pen, 'new_stock_id' => $new_stock_id];
        $processed++;
    }

    if ($processed === 0) {
        throw new Exception('No pending lines were received (wrong ids or already received).');
    }

    mysqli_commit($conn);

    $crossLogWarning = '';
    global $conn_master;
    $registryConn = (isset($conn_master) && $conn_master instanceof mysqli) ? $conn_master : $conn;
    foreach ($mirrorRows as $mr) {
        $w = auragold_receive_update_source_cross_transfer_log($registryConn, $conn, $mr['pen'], $mr['new_stock_id']);
        if ($w !== '') {
            $crossLogWarning = ($crossLogWarning === '') ? $w : ($crossLogWarning . ' ' . $w);
        }
    }

    $sourceSyncWarning = '';
    // Optional: open source branch DB to mark pending received there (legacy multi-DB). Off by default — single main DB only.
    $syncSourcePending = getenv('AURAGOLD_RECEIVE_SYNC_SOURCE_PENDING');
    if ($syncSourcePending !== false && $syncSourcePending !== null && trim((string) $syncSourcePending) === '1'
        && !empty($mirrorRows) && isset($conn_master) && $conn_master) {
        foreach ($mirrorRows as $mr) {
            $w = auragold_receive_sync_pending_on_source_db($conn_master, $conn, $mr['pen'], $mr['new_stock_id']);
            if ($w !== '') {
                $sourceSyncWarning = ($sourceSyncWarning === '') ? $w : ($sourceSyncWarning . ' ' . $w);
            }
        }
    }

    $mirrorWarning = '';
    if ($mirrorDbName !== '' && strcasecmp($mirrorDbName, (string) (defined('DB_NAME') ? DB_NAME : '')) !== 0 && !empty($mirrorRows)) {
        $mconn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $mirrorDbName);
        if ($mconn) {
            mysqli_set_charset($mconn, 'utf8mb4');
            auragold_ensure_stock_transfer_pending_table($mconn);
            mysqli_begin_transaction($mconn);
            try {
                foreach ($mirrorRows as $mr) {
                    $penM = $mr['pen'];
                    $owid = (int) ($penM['outward_stock_id'] ?? 0);
                    if ($owid <= 0) {
                        // Same-DB mirror pending is keyed by outward_stock_id; cross-DB staging has NULL outward on destination.
                        continue;
                    }
                    $to_branch = (int) $penM['to_branch_id'];
                    $ow_prod_id = (int) $penM['product_id'];
                    $ow_char_id = isset($penM['product_characteristic_id']) && $penM['product_characteristic_id'] !== null && $penM['product_characteristic_id'] !== ''
                        ? (int) $penM['product_characteristic_id'] : null;
                    $ow_barcode_esc = mysqli_real_escape_string($mconn, (string) ($penM['barcode'] ?? ''));
                    $ow_metal_id = (int) ($penM['metal_id'] ?? 1);
                    if ($ow_metal_id <= 0) {
                        $ow_metal_id = 1;
                    }
                    $ow_purity = (float) ($penM['opening_purity'] ?? 100);
                    if ($ow_purity <= 0) {
                        $ow_purity = 100.0;
                    }
                    $ow_rate = (float) ($penM['rate'] ?? 0);
                    $move_wt = (float) ($penM['move_wt'] ?? 0);
                    $move_qty = (float) ($penM['move_qty'] ?? 0);
                    if ($move_qty <= 0 && $move_wt > 0) {
                        $move_qty = 1;
                    }
                    $ow_value = (float) ($penM['value'] ?? 0);
                    if ($ow_value <= 0 && $ow_rate > 0 && $move_wt > 0) {
                        $ow_value = $ow_rate * $move_wt;
                    }
                    $td = $penM['transfer_date'] ?? date('Y-m-d');
                    $td_esc = mysqli_real_escape_string($mconn, (string) $td);
                    $char_sql = $ow_char_id !== null ? (string) $ow_char_id : 'NULL';
                    $in_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
                    $in_vals = "$ow_prod_id, $char_sql, '$ow_barcode_esc', $to_branch, $ow_metal_id, $move_wt, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value, $move_wt, $move_qty, 'purchase', '$td_esc', NOW()";
                    if ($has_status_col) {
                        $in_cols .= ", status";
                        $in_vals .= ", 1";
                    }
                    if ($has_sj) {
                        $in_cols .= ", stock_journal_id";
                        $in_vals .= ", NULL";
                    }
                    if ($has_reference) {
                        $in_cols .= ", reference_id, reference_type";
                        $in_vals .= ", NULL, 'stock_transfer'";
                    }
                    if (!mysqli_query($mconn, "INSERT INTO tbl_stock ($in_cols) VALUES ($in_vals)")) {
                        throw new Exception('mirror tbl_stock: ' . mysqli_error($mconn));
                    }
                    $mirror_new_id = (int) mysqli_insert_id($mconn);

                    $tbl_inward_m = @mysqli_query($mconn, "SHOW TABLES LIKE 'tbl_inward_stock'");
                    if ($tbl_inward_m && mysqli_num_rows($tbl_inward_m) > 0) {
                        mysqli_free_result($tbl_inward_m);
                        $barcode_inward = (($penM['barcode'] ?? '') === '') ? 'NULL' : "'" . $ow_barcode_esc . "'";
                        $metal_inward = $ow_metal_id > 0 ? (string) $ow_metal_id : 'NULL';
                        $inward_sql_m = "
                            INSERT INTO tbl_inward_stock (
                                stock_journal_id, product_id, product_characteristic_id, barcode_no,
                                branch_id, metal_id, qty, weight, rate, value, stock_type, transaction_date, created_at
                            ) VALUES (
                                NULL,
                                $ow_prod_id,
                                $char_sql,
                                $barcode_inward,
                                $to_branch,
                                $metal_inward,
                                $move_qty,
                                $move_wt,
                                $ow_rate,
                                $ow_value,
                                'purchase',
                                '$td_esc',
                                NOW()
                            )
                        ";
                        if (!mysqli_query($mconn, $inward_sql_m)) {
                            throw new Exception('mirror tbl_inward_stock: ' . mysqli_error($mconn));
                        }
                    }

                    $upd_m = "UPDATE tbl_stock_transfer_pending SET status = 'received', received_stock_id = $mirror_new_id, received_at = NOW() WHERE outward_stock_id = $owid AND status = 'pending'";
                    if (!mysqli_query($mconn, $upd_m)) {
                        throw new Exception('mirror pending update: ' . mysqli_error($mconn));
                    }
                }
                mysqli_commit($mconn);
            } catch (Exception $me) {
                mysqli_rollback($mconn);
                $mirrorWarning = 'Also received on ' . $mirrorDbName . ' failed: ' . $me->getMessage();
            }
            mysqli_close($mconn);
        } else {
            $mirrorWarning = 'Also received on ' . $mirrorDbName . ' failed: could not connect (' . mysqli_connect_error() . ')';
        }
    }

    $dbLabel = defined('DB_NAME') ? (string) DB_NAME : '';
    $opDb = '';
    if ($ddr = @mysqli_query($conn, 'SELECT DATABASE() AS d')) {
        if ($dr = mysqli_fetch_assoc($ddr)) {
            $opDb = trim((string) ($dr['d'] ?? ''));
        }
        mysqli_free_result($ddr);
    }
    $out = [
        'success'  => true,
        'message'  => 'Received ' . $processed . ' line(s) into tbl_stock at the destination branch. Stock Transfer (In) recorded. Source was cleared when the transfer was saved.',
        'count'    => $processed,
        'database' => $opDb !== '' ? $opDb : $dbLabel,
        'operational_database' => $opDb !== '' ? $opDb : $dbLabel,
    ];
    if ($mirrorDbName !== '' && strcasecmp($mirrorDbName, $dbLabel) !== 0) {
        $out['mirror_database'] = $mirrorDbName;
        $out['mirror_attempted'] = !empty($mirrorRows);
        if ($mirrorWarning !== '') {
            $out['mirror_warning'] = $mirrorWarning;
        }
    }
    if (isset($sourceSyncWarning) && $sourceSyncWarning !== '') {
        $out['source_sync_warning'] = $sourceSyncWarning;
    }
    if (isset($crossLogWarning) && $crossLogWarning !== '') {
        $out['cross_log_warning'] = $crossLogWarning;
    }
    echo json_encode($out);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
