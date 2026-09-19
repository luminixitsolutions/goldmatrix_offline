<?php
/**
 * Process one loose (weight) transfer line under an existing transfer document.
 * Used by ajax/stock-transfer-save.php when loose_items are included in the same Save.
 *
 * @param array<string,mixed> $li product_id, metal_id, characteristic_id, transfer_wt / move_wt
 * @param list<int> $destPendingIds
 * @return int number of outward lines created
 */
function auragold_stock_transfer_process_loose_item(
    mysqli $stConn,
    $destConn,
    int $from_branch,
    int $to_branch,
    string $transfer_date,
    array $li,
    int $transfer_doc_id,
    string $transfer_invoice_no,
    bool $crossPhysicalDb,
    string $sourceDb,
    string $destDbResolved,
    int $created_by,
    bool $has_status_col,
    bool $has_sj,
    bool $has_reference,
    bool $has_updated_at,
    array &$destPendingIds
): int {
    require_once __DIR__ . '/auragold_metal_exchange_stock.php';
    require_once __DIR__ . '/stock_transfer_doc_schema.php';
    if (!function_exists('auragold_stock_history_audit_insert_row')) {
        require_once __DIR__ . '/stock_history_audit_journal.php';
    }

    $product_id = (int) ($li['product_id'] ?? 0);
    $metal_id = (int) ($li['metal_id'] ?? 0);
    $characteristic_id = (int) ($li['characteristic_id'] ?? $li['product_characteristic_id'] ?? 0);
    $transfer_wt = (float) ($li['transfer_wt'] ?? $li['move_wt'] ?? 0);
    $transfer_qty = (float) ($li['transfer_qty'] ?? $li['move_qty'] ?? $li['qty'] ?? 0);
    if ($product_id <= 0 || $metal_id <= 0 || $transfer_wt <= 0.00001) {
        throw new Exception('Invalid loose item (product, metal, transfer wt required).');
    }

    $stock_in_types = function_exists('auragold_metal_exchange_inventory_stock_in_types_sql')
        ? auragold_metal_exchange_inventory_stock_in_types_sql()
        : "'opening','purchase','stock_journal','balance','sale_return','inward'";

    $lot_avail_wt = static function (array $row): float {
        if (function_exists('auragold_inventory_stock_available_weight')) {
            return (float) auragold_inventory_stock_available_weight($row);
        }
        $cw = (float) ($row['current_weight'] ?? 0);
        return $cw > 1e-8 ? $cw : (float) ($row['opening_weight'] ?? 0);
    };

    $status_sql = $has_status_col ? ' AND status = 1' : '';
    $char_sql_f = $characteristic_id > 0
        ? ' AND (product_characteristic_id = ' . $characteristic_id . ' OR product_characteristic_id IS NULL OR product_characteristic_id = 0)'
        : '';
    $sql = "
        SELECT * FROM tbl_stock
        WHERE branch_id = " . (int) $from_branch . "
          AND product_id = " . (int) $product_id . "
          AND metal_id = " . (int) $metal_id . "
          AND stock_type IN ($stock_in_types)
          AND (barcode IS NULL OR TRIM(barcode) = '')
          AND (COALESCE(current_weight,0) > 0 OR COALESCE(opening_weight,0) > 0 OR COALESCE(current_qty,0) > 0 OR COALESCE(opening_qty,0) > 0)
          $status_sql $char_sql_f
        ORDER BY CASE WHEN COALESCE(current_weight,0) > 0 THEN 0 ELSE 1 END, id ASC
    ";
    $lots = [];
    $q = mysqli_query($stConn, $sql);
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            if (function_exists('auragold_stock_row_is_inventory_for_issue') && !auragold_stock_row_is_inventory_for_issue($row)) {
                continue;
            }
            $lots[] = $row;
        }
        mysqli_free_result($q);
    }
    if (empty($lots)) {
        $sql2 = str_replace("AND (barcode IS NULL OR TRIM(barcode) = '')", '', $sql);
        $q2 = mysqli_query($stConn, $sql2);
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
    $avail = 0.0;
    foreach ($lots as $lot) {
        $avail += $lot_avail_wt($lot);
    }
    if ($avail + 0.0001 < $transfer_wt) {
        throw new Exception('Loose transfer: insufficient stock for product #' . $product_id . ' (need ' . number_format($transfer_wt, 3, '.', '') . ', have ' . number_format($avail, 3, '.', '') . ').');
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
    $mtqr = @mysqli_query($stConn, "SELECT TRIM(COALESCE(NULLIF(system_name,''), NULLIF(display_name,''))) AS n FROM tbl_metal WHERE id = $metal_id LIMIT 1");
    if ($mtqr && ($mtx = mysqli_fetch_assoc($mtqr))) {
        $metal_type = trim((string) ($mtx['n'] ?? ''));
    }
    if ($mtqr) {
        mysqli_free_result($mtqr);
    }

    $hasLogDoc = false;
    $ldc = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock_cross_transfer_log LIKE 'transfer_doc_id'");
    if ($ldc && mysqli_num_rows($ldc) > 0) {
        $hasLogDoc = true;
    }
    if ($ldc) {
        mysqli_free_result($ldc);
    }
    $hasPendDoc = false;
    $pdc = @mysqli_query($stConn, "SHOW COLUMNS FROM tbl_stock_transfer_pending LIKE 'transfer_doc_id'");
    if ($pdc && mysqli_num_rows($pdc) > 0) {
        $hasPendDoc = true;
    }
    if ($pdc) {
        mysqli_free_result($pdc);
    }

    $remaining = round($transfer_wt, 3);
    $created = 0;

    foreach ($lots as $lotPreview) {
        if ($remaining <= 0.00001) {
            break;
        }
        $stock_id = (int) ($lotPreview['id'] ?? 0);
        if ($stock_id <= 0) {
            continue;
        }
        $lock_q = mysqli_query($stConn, 'SELECT * FROM tbl_stock WHERE id = ' . $stock_id . ($has_status_col ? ' AND status = 1' : '') . ' FOR UPDATE');
        $stock_row = ($lock_q && mysqli_num_rows($lock_q) > 0) ? mysqli_fetch_assoc($lock_q) : null;
        if ($lock_q) {
            mysqli_free_result($lock_q);
        }
        if (!$stock_row || in_array($stock_row['stock_type'] ?? '', ['outward'], true)) {
            continue;
        }
        $lot_wt = $lot_avail_wt($stock_row);
        if ($lot_wt <= 0.00001) {
            continue;
        }
        $move_wt = round(min($remaining, $lot_wt), 3);
        if ($move_wt <= 0.00001) {
            continue;
        }

        $prev_cq = (float) ($stock_row['current_qty'] ?? 0);
        $prev_cw = (float) ($stock_row['current_weight'] ?? 0);
        $op_q = (float) ($stock_row['opening_qty'] ?? 0);
        $base_qty = $prev_cq > 0 ? $prev_cq : $op_q;
        $base_wt = $prev_cw > 0 ? $prev_cw : (float) ($stock_row['opening_weight'] ?? 0);
        $move_qty = ($base_wt > 0 && $base_qty > 0) ? round($base_qty * ($move_wt / $base_wt), 3) : 0.0;
        if ($transfer_qty > 0 && $transfer_wt > 0) {
            $move_qty = round($transfer_qty * ($move_wt / $transfer_wt), 3);
        }
        if ($move_qty <= 0 && $move_wt > 0) {
            $move_qty = ($move_wt + 0.00001 >= $lot_wt) ? max(1.0, $base_qty) : 0.0;
        }

        $ow_prod_id = (int) $stock_row['product_id'];
        $ow_char_id = (isset($stock_row['product_characteristic_id']) && $stock_row['product_characteristic_id'] !== '' && $stock_row['product_characteristic_id'] !== null)
            ? (int) $stock_row['product_characteristic_id'] : ($characteristic_id > 0 ? $characteristic_id : null);
        $ow_metal_id = (int) ($stock_row['metal_id'] ?? $metal_id);
        if ($ow_metal_id <= 0) {
            $ow_metal_id = $metal_id;
        }
        $ow_purity = (float) ($stock_row['opening_purity'] ?? 100);
        if ($ow_purity <= 0) {
            $ow_purity = 100.0;
        }
        $ow_rate = (float) ($stock_row['rate'] ?? 0);
        $ow_value = $ow_rate > 0 ? $ow_rate * $move_wt : (float) ($stock_row['value'] ?? 0);
        $td_esc = mysqli_real_escape_string($stConn, $transfer_date);
        $char_sql = $ow_char_id !== null ? (string) $ow_char_id : 'NULL';
        $src_id = (int) $stock_row['id'];

        $ow_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
        $ow_vals = "$ow_prod_id, $char_sql, NULL, $from_branch, $ow_metal_id, $move_wt, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value, $move_wt, $move_qty, 'outward', '$td_esc', NOW()";
        if ($has_status_col) {
            $ow_cols .= ', status';
            $ow_vals .= ', 1';
        }
        if ($has_sj) {
            $ow_cols .= ', stock_journal_id';
            $ow_vals .= ', NULL';
        }
        if ($has_reference) {
            $ow_cols .= ', reference_id, reference_type';
            $ow_vals .= ', ' . (int) $transfer_doc_id . ", 'stock_transfer'";
        }
        if (!mysqli_query($stConn, "INSERT INTO tbl_stock ($ow_cols) VALUES ($ow_vals)")) {
            throw new Exception('Loose outward insert failed: ' . mysqli_error($stConn));
        }
        $outward_id = (int) mysqli_insert_id($stConn);

        // Partial deduct
        $stock_rate_val = (float) ($stock_row['rate'] ?? 0);
        $op_w = (float) ($stock_row['opening_weight'] ?? 0);
        $ua = $has_updated_at ? ', updated_at = NOW()' : '';
        if ($prev_cq > 0 || $prev_cw > 0) {
            $sold_q = $move_qty;
            if ($sold_q <= 0 && $prev_cw > 0 && $prev_cq > 0) {
                $sold_q = $prev_cq * ($move_wt / $prev_cw);
            }
            $balance_weight = $prev_cw - $move_wt;
            $new_cq = max(0.0, $prev_cq - $sold_q);
            if ($balance_weight <= 0.00001) {
                mysqli_query($stConn, "UPDATE tbl_stock SET current_weight = 0, current_qty = 0, value = 0$ua WHERE id = $src_id");
            } else {
                $new_val = $stock_rate_val * $balance_weight;
                mysqli_query($stConn, "UPDATE tbl_stock SET current_weight = $balance_weight, current_qty = $new_cq, final_weight = $balance_weight, value = $new_val$ua WHERE id = $src_id");
            }
        } else {
            $new_op_q = max(0.0, $op_q - $move_qty);
            $new_op_w = max(0.0, $op_w - $move_wt);
            if ($new_op_w <= 0.00001 && $new_op_q <= 0.00001) {
                mysqli_query($stConn, "UPDATE tbl_stock SET opening_qty = 0, opening_weight = 0, final_weight = 0, value = 0$ua WHERE id = $src_id");
            } else {
                $new_val = $stock_rate_val * $new_op_w;
                mysqli_query($stConn, "UPDATE tbl_stock SET opening_qty = $new_op_q, opening_weight = $new_op_w, final_weight = $new_op_w, value = $new_val$ua WHERE id = $src_id");
            }
        }

        $src_db_esc = mysqli_real_escape_string($stConn, $sourceDb);
        $dst_db_esc = mysqli_real_escape_string($stConn, $destDbResolved);
        $log_sql = "
            INSERT INTO tbl_stock_cross_transfer_log (
                source_branch_id, destination_branch_id, source_db, destination_db,
                barcode, stock_id, outward_stock_id" . ($hasLogDoc ? ', transfer_doc_id' : '') . ", destination_stock_id, move_qty, move_wt, transfer_date, created_by, status
            ) VALUES (
                $from_branch, $to_branch, '$src_db_esc', '$dst_db_esc',
                NULL, $src_id, $outward_id" . ($hasLogDoc ? ', ' . (int) $transfer_doc_id : '') . ", NULL,
                $move_qty, $move_wt, '$td_esc', " . ($created_by > 0 ? (string) $created_by : 'NULL') . ", 'completed'
            )
        ";
        if (!mysqli_query($stConn, $log_sql)) {
            throw new Exception('Loose transfer log failed: ' . mysqli_error($stConn));
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
                . '|from=' . $from_branch . '|to=' . $to_branch . '|',
        ]);

        if ($crossPhysicalDb && $destConn instanceof mysqli) {
            $pendingId = auragold_stock_transfer_insert_pending_in_transit(
                $destConn, $from_branch, $to_branch, $ow_prod_id, $ow_char_id, '',
                $ow_metal_id, $ow_purity, $move_qty, $move_wt, $ow_rate, $ow_value,
                $transfer_date, $outward_id, $src_id, $transfer_doc_id
            );
            $destPendingIds[] = $pendingId;
        } else {
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
                throw new Exception('Loose pending insert failed: ' . mysqli_error($stConn));
            }
        }

        auragold_stock_transfer_doc_bump_totals($stConn, $transfer_doc_id, $move_qty, $move_wt);
        $remaining = round($remaining - $move_wt, 3);
        $created++;
    }

    if ($created <= 0 || $remaining > 0.001) {
        throw new Exception('Could not complete loose transfer for product #' . $product_id . '.');
    }

    return $created;
}
