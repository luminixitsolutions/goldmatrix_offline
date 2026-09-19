<?php
/**
 * Helpers for ajax/stock-transfer-save.php (cross-branch DB via tbl_branches).
 */
require_once __DIR__ . '/branch_credentials.php';
require_once __DIR__ . '/stock_transfer_pending_schema.php';

/**
 * Current schema name for a mysqli link (not necessarily DB_NAME constant).
 */
function auragold_stock_transfer_mysqli_database(mysqli $conn): string {
    $r = @mysqli_query($conn, 'SELECT DATABASE() AS d');
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        mysqli_free_result($r);
        return trim((string) ($row['d'] ?? ''));
    }
    return '';
}

/**
 * tbl_branches row: prefer operational $conn, else registry.
 *
 * @return array<string,mixed>|null
 */
function auragold_stock_transfer_branch_row_by_id(mysqli $opConn, int $branchId): ?array {
    $branchId = (int) $branchId;
    if ($branchId <= 0) {
        return null;
    }
    $res = @mysqli_query($opConn, 'SELECT * FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1');
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
        return is_array($row) ? $row : null;
    }
    if ($res) {
        mysqli_free_result($res);
    }
    if (function_exists('auragold_registry_tbl_branches_row_by_id')) {
        $r2 = auragold_registry_tbl_branches_row_by_id($branchId);
        return is_array($r2) ? $r2 : null;
    }
    return null;
}

/**
 * Open mysqli to a branch operational database (tbl_branches db_name / credentials).
 * Tries branch db_user first, then app DB_USER/DB_PASS (local/dev often lacks per-branch MySQL users).
 *
 * @param array<string,mixed> $branchRow
 */
function auragold_stock_transfer_mysqli_to_branch_db(array $branchRow): mysqli {
    $cr = auragold_branch_row_db_credentials($branchRow);
    $db = trim((string) ($cr['db_name'] ?? ''));
    if ($db === '') {
        throw new RuntimeException('Branch database name (db_name) is empty for branch #' . (int) ($branchRow['id'] ?? 0) . '.');
    }
    $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
    $branchUser = trim((string) ($cr['db_user'] ?? ''));
    $branchPass = (string) ($cr['db_pass'] ?? '');
    $appUser = defined('DB_USER') ? (string) DB_USER : 'root';
    $appPass = defined('DB_PASS') ? (string) DB_PASS : '';

    $attempts = [];
    if ($branchUser !== '') {
        $attempts[] = [$branchUser, $branchPass];
    }
    if ($appUser !== '' && ($branchUser === '' || strcasecmp($branchUser, $appUser) !== 0)) {
        $attempts[] = [$appUser, $appPass];
    }
    if (empty($attempts)) {
        $attempts[] = ['root', ''];
    }

    $lastErr = '';
    foreach ($attempts as $cred) {
        $m = @mysqli_connect($host, $cred[0], $cred[1], $db);
        if ($m) {
            mysqli_set_charset($m, 'utf8mb4');
            return $m;
        }
        $lastErr = (string) mysqli_connect_error();
    }

    throw new RuntimeException(
        'Could not connect to destination branch database "' . $db . '"'
        . ($branchUser !== '' ? ' (user: ' . $branchUser . ')' : '')
        . '. ' . $lastErr
        . '. Check tbl_branches db_name / db_users / db_password, or ensure the database exists on this server.'
    );
}

/**
 * True if destination already has on-hand stock for this barcode at branch.
 */
function auragold_stock_transfer_dest_has_active_barcode(mysqli $destConn, int $toBranchId, string $barcode): bool {
    $bc = trim($barcode);
    if ($bc === '') {
        return false;
    }
    $bid = (int) $toBranchId;
    $bcEsc = mysqli_real_escape_string($destConn, $bc);
    $hasStatus = false;
    $chk = @mysqli_query($destConn, "SHOW COLUMNS FROM tbl_stock LIKE 'status'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $hasStatus = true;
    }
    if ($chk) {
        mysqli_free_result($chk);
    }
    $st = $hasStatus ? " AND s.status = 1 " : '';
    $sql = "SELECT s.id FROM tbl_stock s WHERE s.branch_id = $bid $st
        AND BINARY IFNULL(s.barcode,'') = BINARY '" . $bcEsc . "'
        AND (COALESCE(s.current_weight,0) > 0 OR COALESCE(s.current_qty,0) > 0
             OR COALESCE(s.opening_weight,0) > 0 OR COALESCE(s.opening_qty,0) > 0)
        LIMIT 1";
    $q = @mysqli_query($destConn, $sql);
    if ($q && mysqli_num_rows($q) > 0) {
        mysqli_free_result($q);
        return true;
    }
    if ($q) {
        mysqli_free_result($q);
    }
    return false;
}

/**
 * Sale / POS: block barcodes that are in transit or already transferred out.
 * Returns an error message, or null when the barcode may be sold.
 *
 * Loose transfers use empty barcode and are out of scope for barcode scan sales.
 */
function auragold_sale_barcode_stock_transfer_block_message(mysqli $conn, string $barcode, int $branchId = 0): ?string {
    $bc = trim($barcode);
    if ($bc === '') {
        return null;
    }
    $bcEsc = mysqli_real_escape_string($conn, $bc);

    if (function_exists('auragold_ensure_stock_transfer_pending_table')) {
        @auragold_ensure_stock_transfer_pending_table($conn);
    }

    $pendTbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_transfer_pending'");
    $hasPend = ($pendTbl && mysqli_num_rows($pendTbl) > 0);
    if ($pendTbl) {
        mysqli_free_result($pendTbl);
    }
    if ($hasPend) {
        $pq = @mysqli_query(
            $conn,
            "SELECT id, from_branch_id, to_branch_id FROM tbl_stock_transfer_pending
             WHERE status = 'pending'
               AND BINARY IFNULL(barcode,'') = BINARY '" . $bcEsc . "'
             LIMIT 1"
        );
        if ($pq && mysqli_num_rows($pq) > 0) {
            $prow = mysqli_fetch_assoc($pq);
            mysqli_free_result($pq);
            $fromB = (int) ($prow['from_branch_id'] ?? 0);
            $toB = (int) ($prow['to_branch_id'] ?? 0);
            return 'This barcode is in transit (stock transfer'
                . ($fromB > 0 || $toB > 0 ? ' from branch #' . $fromB . ' to #' . $toB : '')
                . '). Sale invoice is not allowed until it is received at the destination branch.';
        }
        if ($pq) {
            mysqli_free_result($pq);
        }
    }

    $hasRef = false;
    $refChk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_type'");
    if ($refChk && mysqli_num_rows($refChk) > 0) {
        $hasRef = true;
    }
    if ($refChk) {
        mysqli_free_result($refChk);
    }

    $branchSql = '';
    if ($branchId > 0) {
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && (int) $branchId === $main) {
            $branchSql = ' AND (branch_id = ' . (int) $branchId . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $branchSql = ' AND COALESCE(branch_id, 0) = ' . (int) $branchId;
        }
    }

    $inTypes = "'opening','purchase','stock_journal','balance','sale_return','inward'";
    $onHandSql = "
        SELECT id FROM tbl_stock
        WHERE barcode = '" . $bcEsc . "' AND status = 1
          AND stock_type IN ($inTypes)
          AND (
                COALESCE(current_weight, 0) > 0
             OR COALESCE(current_qty, 0) > 0
             OR COALESCE(opening_weight, 0) > 0
             OR COALESCE(opening_qty, 0) > 0
          )
          $branchSql
        LIMIT 1
    ";
    $onHand = @mysqli_query($conn, $onHandSql);
    if ($onHand && mysqli_num_rows($onHand) > 0) {
        mysqli_free_result($onHand);
        return null;
    }
    if ($onHand) {
        mysqli_free_result($onHand);
    }

    // Without reference_type, pending check above is the reliable signal (avoid blocking normal sale outwards).
    if (!$hasRef) {
        return null;
    }

    $xferSql = "
        SELECT id FROM tbl_stock
        WHERE barcode = '" . $bcEsc . "' AND status = 1
          AND stock_type = 'outward'
          AND reference_type = 'stock_transfer'
          $branchSql
        ORDER BY id DESC
        LIMIT 1
    ";
    $xfer = @mysqli_query($conn, $xferSql);
    if ($xfer && mysqli_num_rows($xfer) > 0) {
        mysqli_free_result($xfer);
        return 'This barcode was transferred (stock transfer). Sale invoice is not allowed against a transferred barcode.';
    }
    if ($xfer) {
        mysqli_free_result($xfer);
    }

    // Any-branch: stock_transfer outward exists and this branch has no on-hand for the tag.
    if ($branchId > 0) {
        $xferAny = @mysqli_query(
            $conn,
            "SELECT id FROM tbl_stock
             WHERE barcode = '" . $bcEsc . "' AND status = 1 AND stock_type = 'outward'
               AND reference_type = 'stock_transfer'
             ORDER BY id DESC LIMIT 1"
        );
        if ($xferAny && mysqli_num_rows($xferAny) > 0) {
            mysqli_free_result($xferAny);
            return 'This barcode was transferred (stock transfer). Sale invoice is not allowed against a transferred barcode.';
        }
        if ($xferAny) {
            mysqli_free_result($xferAny);
        }
    }

    return null;
}

/**
 * True if destination already has an in-transit (pending) staging row for this barcode at branch.
 */
function auragold_stock_transfer_dest_has_pending_barcode(mysqli $conn, int $toBranchId, string $barcode): bool {
    $bc = trim($barcode);
    if ($bc === '') {
        return false;
    }
    $bid = (int) $toBranchId;
    $bcEsc = mysqli_real_escape_string($conn, $bc);
    $sql = "SELECT id FROM tbl_stock_transfer_pending WHERE to_branch_id = $bid AND status = 'pending'
        AND BINARY IFNULL(barcode,'') = BINARY '" . $bcEsc . "' LIMIT 1";
    $q = @mysqli_query($conn, $sql);
    if ($q && mysqli_num_rows($q) > 0) {
        mysqli_free_result($q);
        return true;
    }
    if ($q) {
        mysqli_free_result($q);
    }
    return false;
}

/**
 * Insert destination purchase line + optional tbl_inward_stock (same shape as stock-transfer-receive.php).
 *
 * @return int new tbl_stock.id
 */
function auragold_stock_transfer_insert_destination_line(
    mysqli $destConn,
    int $toBranchId,
    int $ow_prod_id,
    ?int $ow_char_id,
    string $barcodeRaw,
    int $ow_metal_id,
    float $ow_purity,
    float $ow_rate,
    float $move_wt,
    float $move_qty,
    float $ow_value,
    string $transfer_dateYmd,
    bool $has_sj,
    bool $has_reference,
    bool $has_status_col
): int {
    $ow_barcode_esc = mysqli_real_escape_string($destConn, $barcodeRaw);
    $char_sql = $ow_char_id !== null ? (string) $ow_char_id : 'NULL';
    $td_esc = mysqli_real_escape_string($destConn, $transfer_dateYmd);
    if ($move_qty <= 0 && $move_wt > 0) {
        $move_qty = 1.0;
    }

    $in_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
    $in_vals = "$ow_prod_id, $char_sql, '" . $ow_barcode_esc . "', " . (int) $toBranchId . ", $ow_metal_id, $move_wt, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value, $move_wt, $move_qty, 'purchase', '$td_esc', NOW()";
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
    if (!mysqli_query($destConn, "INSERT INTO tbl_stock ($in_cols) VALUES ($in_vals)")) {
        throw new RuntimeException('Destination tbl_stock insert failed: ' . mysqli_error($destConn));
    }
    $newId = (int) mysqli_insert_id($destConn);

    $tbl_inward = @mysqli_query($destConn, "SHOW TABLES LIKE 'tbl_inward_stock'");
    if ($tbl_inward && mysqli_num_rows($tbl_inward) > 0) {
        mysqli_free_result($tbl_inward);
        $barcode_inward = ($barcodeRaw === '') ? 'NULL' : "'" . $ow_barcode_esc . "'";
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
                " . (int) $toBranchId . ",
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
        if (!mysqli_query($destConn, $inward_sql)) {
            throw new RuntimeException('Destination tbl_inward_stock insert failed: ' . mysqli_error($destConn));
        }
    } elseif ($tbl_inward) {
        mysqli_free_result($tbl_inward);
    }

    return $newId;
}

/**
 * Insert staging row (in transit). tbl_stock is created later by stock-transfer-receive.php.
 *
 * @param ?int $outward_stock_id Outward line on source DB (same physical DB as $conn); NULL for cross-DB staging on destination only.
 * @param ?int $source_stock_id  Original inward tbl_stock.id on source before it was cleared.
 *
 * @return int new tbl_stock_transfer_pending.id
 *
 * @throws RuntimeException
 */
function auragold_stock_transfer_insert_pending_in_transit(
    mysqli $conn,
    int $from_branch_id,
    int $to_branch_id,
    int $product_id,
    ?int $char_id,
    string $barcodeRaw,
    int $metal_id,
    float $opening_purity,
    float $move_qty,
    float $move_wt,
    float $rate,
    float $value,
    string $transfer_dateYmd,
    ?int $outward_stock_id,
    ?int $source_stock_id,
    ?int $transfer_doc_id = null
): int {
    if (!auragold_ensure_stock_transfer_pending_table($conn)) {
        throw new RuntimeException('tbl_stock_transfer_pending: ' . mysqli_error($conn));
    }
    if (function_exists('auragold_ensure_stock_transfer_doc_table')) {
        auragold_ensure_stock_transfer_doc_table($conn);
    }
    $td_esc = mysqli_real_escape_string($conn, $transfer_dateYmd);
    $char_sql = $char_id !== null ? (string) $char_id : 'NULL';
    $barcode_esc = mysqli_real_escape_string($conn, $barcodeRaw);
    $barcode_sql = ($barcodeRaw === '') ? 'NULL' : "'" . $barcode_esc . "'";
    $mid = $metal_id > 0 ? (string) $metal_id : 'NULL';
    $owSql = ($outward_stock_id !== null && $outward_stock_id > 0) ? (string) (int) $outward_stock_id : 'NULL';
    $srcSql = ($source_stock_id !== null && $source_stock_id > 0) ? (string) (int) $source_stock_id : 'NULL';
    $docCol = '';
    $docVal = '';
    $docChk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_transfer_pending LIKE 'transfer_doc_id'");
    if ($docChk && mysqli_num_rows($docChk) > 0 && $transfer_doc_id !== null && (int) $transfer_doc_id > 0) {
        $docCol = ', transfer_doc_id';
        $docVal = ', ' . (int) $transfer_doc_id;
    }
    if ($docChk) {
        mysqli_free_result($docChk);
    }
    $sql = "
        INSERT INTO tbl_stock_transfer_pending (
            from_branch_id, to_branch_id, product_id, product_characteristic_id, barcode, metal_id, opening_purity,
            move_qty, move_wt, rate, value, transfer_date, source_stock_id, outward_stock_id{$docCol}, status, received_stock_id, received_at
        ) VALUES (
            " . (int) $from_branch_id . ', ' . (int) $to_branch_id . ", $product_id, $char_sql, $barcode_sql, $mid, $opening_purity,
            $move_qty, $move_wt, $rate, $value, '$td_esc', $srcSql, $owSql{$docVal}, 'pending', NULL, NULL)
    ";
    if (!mysqli_query($conn, $sql)) {
        throw new RuntimeException('tbl_stock_transfer_pending insert failed: ' . mysqli_error($conn));
    }
    $newId = (int) mysqli_insert_id($conn);
    if ($newId <= 0) {
        throw new RuntimeException('tbl_stock_transfer_pending insert returned no id.');
    }
    return $newId;
}

/**
 * Delete destination staging rows by pending id (compensation after failed source commit).
 *
 * @param list<int> $pendingIds
 */
function auragold_stock_transfer_dest_delete_pending_by_ids(mysqli $destConn, array $pendingIds): void {
    $pendingIds = array_values(array_filter(array_map('intval', $pendingIds), static function ($x) {
        return $x > 0;
    }));
    if (empty($pendingIds)) {
        return;
    }
    $in = implode(',', $pendingIds);
    mysqli_query($destConn, 'DELETE FROM tbl_stock_transfer_pending WHERE id IN (' . $in . ')');
}
