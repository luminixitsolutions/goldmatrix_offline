<?php
/**
 * Shared jobwork weight adjustment helpers (reduce + metal exchange shop stock).
 */

/**
 * @param array<string,mixed> $me
 * @return array<string,mixed>
 */
function mp_jwq_metal_exchange_payment_from_request(array $me): array
{
    return [
        'type' => 'metal-exchange',
        'payment_type' => 'M. Exch.',
        'deposit_into' => 'Metal Exchange',
        'metal_exchange_metal_id' => (int) ($me['metal_exchange_metal_id'] ?? $me['metal_id'] ?? 0),
        'metal_exchange_product_id' => (int) ($me['metal_exchange_product_id'] ?? $me['product_id'] ?? 0),
        'metal_exchange_product_name' => trim((string) ($me['metal_exchange_product_name'] ?? $me['product_name'] ?? '')),
        'metal_exchange_gross_wt' => (string) ($me['metal_exchange_gross_wt'] ?? $me['gross_weight'] ?? '0'),
        'metal_exchange_purity_wt' => (string) ($me['metal_exchange_purity_wt'] ?? $me['purity_weight'] ?? '0'),
        'purity_carat' => (string) ($me['purity_carat'] ?? $me['metal_exchange_purity'] ?? '1'),
        'metal_exchange_item_code' => trim((string) ($me['metal_exchange_item_code'] ?? $me['item_code'] ?? '')),
        'metal_exchange_rate' => (string) ($me['metal_exchange_rate'] ?? $me['rate'] ?? '0'),
        'quantity' => (float) ($me['quantity'] ?? 1),
        'amount' => (float) ($me['amount'] ?? 0),
    ];
}

/**
 * @return array{0:bool,1:int,2:string}
 */
function mp_jwq_insert_weight_adjustment_row(
    mysqli $db,
    int $jwo_id,
    string $type,
    float $grams,
    string $note,
    int $created_by,
    int $dept_id,
    int $user_id
): array {
    $stmt = mysqli_prepare(
        $db,
        'INSERT INTO tbl_jobwork_weight_adjustments'
        . ' (jobwork_order_id, adjustment_type, weight_grams, remark, created_by_user_id, source_department_id, source_user_id)'
        . ' VALUES (?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0))'
    );
    if (!$stmt) {
        return [false, 0, mysqli_error($db)];
    }
    mysqli_stmt_bind_param($stmt, 'isdsiii', $jwo_id, $type, $grams, $note, $created_by, $dept_id, $user_id);
    $inserted = mysqli_stmt_execute($stmt);
    $insert_id = $inserted ? mysqli_insert_id($db) : 0;
    $insert_error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    return [$inserted, $insert_id, $insert_error];
}

/**
 * Post shop inward + department reduce adjustment for one metal exchange line.
 *
 * @param array<string,mixed> $metal_exchange
 * @return array{ok:bool,message:string,weight:float,adjustment_id:int}
 */
function mp_jwq_apply_reduce_metal_exchange_line(
    mysqli $conn,
    int $jobwork_order_id,
    array $metal_exchange,
    int $from_dept_id,
    int $from_user_id,
    int $uid,
    string $remark = '',
    bool $update_item_weights = true
): array {
    $me_payment = mp_jwq_metal_exchange_payment_from_request($metal_exchange);
    $me_resolved = function_exists('auragold_metal_exchange_resolve')
        ? auragold_metal_exchange_resolve($conn, $me_payment)
        : ['gross' => 0.0];
    $weight = (float) ($me_resolved['gross'] ?? 0);
    if ($weight <= 0.0000001) {
        $weight = (float) ($me_payment['metal_exchange_gross_wt'] ?? 0);
    }
    if (!is_finite($weight) || $weight <= 0) {
        return ['ok' => false, 'message' => 'Enter gross weight greater than zero.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    $weight = round($weight, 4);

    if (!function_exists('auragold_payment_is_metal_exchange_inward')
        || !auragold_payment_is_metal_exchange_inward($conn, $me_payment)) {
        return ['ok' => false, 'message' => 'Select metal, product, and gross weight for shop stock return.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    $jwo_meta = function_exists('getRecord')
        ? getRecord('SELECT id, jobwork_no FROM tbl_jobwork_orders WHERE id = ' . (int) $jobwork_order_id . ' LIMIT 1')
        : null;
    $jobwork_no_plain = ($jwo_meta && !empty($jwo_meta['jobwork_no']))
        ? trim((string) $jwo_meta['jobwork_no'])
        : ('JWO-' . (int) $jobwork_order_id);
    $seq_row = function_exists('getRecord')
        ? getRecord(
            "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1 AND stock_type = 'inward'"
            . " AND reference_type = 'jobwork_order' AND reference_id = " . (int) $jobwork_order_id
        )
        : null;
    $pay_seq = ((int) ($seq_row['c'] ?? 0)) + 1;
    try {
        if (function_exists('auragold_post_jobwork_order_metal_inward_to_shop_stock')) {
            auragold_post_jobwork_order_metal_inward_to_shop_stock(
                $conn,
                (int) $jobwork_order_id,
                $jobwork_no_plain,
                date('Y-m-d'),
                $me_payment,
                $pay_seq
            );
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage(), 'weight' => 0.0, 'adjustment_id' => 0];
    }

    $reduce_dept_id = $from_dept_id;
    if ($reduce_dept_id < 1) {
        $jwo = @mysqli_query($conn, 'SELECT department_id, department_user_id FROM tbl_jobwork_orders WHERE id = ' . (int) $jobwork_order_id . ' LIMIT 1');
        if ($jwo && ($jwo_row = mysqli_fetch_assoc($jwo))) {
            $reduce_dept_id = (int) ($jwo_row['department_id'] ?? 0);
            if ($from_user_id < 1) {
                $from_user_id = (int) ($jwo_row['department_user_id'] ?? 0);
            }
        }
        if ($jwo) {
            mysqli_free_result($jwo);
        }
    }

    $prod_note = trim((string) ($me_payment['metal_exchange_product_name'] ?? ''));
    $code_note = trim((string) ($me_payment['metal_exchange_item_code'] ?? ''));
    $remark_db = 'Shop stock inward (reduce weight return)';
    if ($prod_note !== '') {
        $remark_db .= ' · ' . $prod_note;
    }
    if ($code_note !== '') {
        $remark_db .= ' · ' . $code_note;
    }
    if ($remark !== '') {
        $remark_db .= ' · ' . $remark;
    }

    [$ok, $new_id, $err] = mp_jwq_insert_weight_adjustment_row(
        $conn,
        $jobwork_order_id,
        'reduce',
        $weight,
        $remark_db,
        $uid,
        $reduce_dept_id,
        $from_user_id
    );
    if (!$ok) {
        return ['ok' => false, 'message' => $err !== '' ? $err : 'Could not save reduce adjustment.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    if ($update_item_weights) {
        $ji_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
        if ($ji_chk && mysqli_num_rows($ji_chk) > 0) {
            mysqli_free_result($ji_chk);
            $item_row = function_exists('getRecord')
                ? getRecord('SELECT id, net_weight, gross_weight, final_weight FROM tbl_jobwork_order_items WHERE jobwork_order_id = '
                    . (int) $jobwork_order_id . ' ORDER BY id ASC LIMIT 1')
                : null;
            if ($item_row && (int) ($item_row['id'] ?? 0) > 0) {
                $item_id = (int) $item_row['id'];
                $cols_q = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
                $ji_cols = [];
                if ($cols_q) {
                    while ($c = mysqli_fetch_assoc($cols_q)) {
                        $fn = (string) ($c['Field'] ?? '');
                        if ($fn !== '') {
                            $ji_cols[$fn] = true;
                        }
                    }
                    mysqli_free_result($cols_q);
                }
                $sets = [];
                if (!empty($ji_cols['net_weight'])) {
                    $nw = (float) ($item_row['net_weight'] ?? 0);
                    $sets[] = 'net_weight = ' . round(max(0, $nw - $weight), 4);
                }
                if (!empty($ji_cols['gross_weight'])) {
                    $gw = (float) ($item_row['gross_weight'] ?? 0);
                    $sets[] = 'gross_weight = ' . round(max(0, $gw - $weight), 4);
                }
                if (!empty($ji_cols['final_weight'])) {
                    $fw = (float) ($item_row['final_weight'] ?? 0);
                    $base_fw = $fw > 0.0000001 ? $fw : (float) ($item_row['net_weight'] ?? 0);
                    $sets[] = 'final_weight = ' . round(max(0, $base_fw - $weight), 4);
                }
                if ($sets !== []) {
                    @mysqli_query(
                        $conn,
                        'UPDATE tbl_jobwork_order_items SET ' . implode(', ', $sets)
                        . ' WHERE id = ' . $item_id . ' AND jobwork_order_id = ' . (int) $jobwork_order_id . ' LIMIT 1'
                    );
                }
            }
        } elseif ($ji_chk) {
            mysqli_free_result($ji_chk);
        }
    }

    return ['ok' => true, 'message' => 'Reduce weight saved.', 'weight' => $weight, 'adjustment_id' => (int) $new_id];
}

/**
 * Post shop outward + department add adjustment for one metal exchange line.
 *
 * @param array<string,mixed> $metal_exchange
 * @return array{ok:bool,message:string,weight:float,adjustment_id:int}
 */
function mp_jwq_apply_add_metal_exchange_line(
    mysqli $conn,
    int $jobwork_order_id,
    array $metal_exchange,
    int $to_dept_id,
    int $to_user_id,
    int $uid,
    string $remark = '',
    bool $update_item_weights = true
): array {
    $me_payment = mp_jwq_metal_exchange_payment_from_request($metal_exchange);
    $me_resolved = function_exists('auragold_metal_exchange_resolve')
        ? auragold_metal_exchange_resolve($conn, $me_payment)
        : ['gross' => 0.0];
    $weight = (float) ($me_resolved['gross'] ?? 0);
    if ($weight <= 0.0000001) {
        $weight = (float) ($me_payment['metal_exchange_gross_wt'] ?? 0);
    }
    if (!is_finite($weight) || $weight <= 0) {
        return ['ok' => false, 'message' => 'Enter gross weight greater than zero.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    $weight = round($weight, 4);

    if ($to_dept_id < 1) {
        return ['ok' => false, 'message' => 'Please select To Dept.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    if (!function_exists('auragold_payment_is_metal_exchange_inward')
        || !auragold_payment_is_metal_exchange_inward($conn, $me_payment)) {
        return ['ok' => false, 'message' => 'Select metal, product, and gross weight for shop stock issue.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    $inv_src = function_exists('auragold_resolve_inventory_stock_for_metal_exchange')
        ? auragold_resolve_inventory_stock_for_metal_exchange($conn, $me_payment)
        : null;
    if (!is_array($inv_src)) {
        return ['ok' => false, 'message' => 'No shop stock found for the selected metal/product. Check item code or opening stock.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    $jwo_meta = function_exists('getRecord')
        ? getRecord('SELECT id, jobwork_no FROM tbl_jobwork_orders WHERE id = ' . (int) $jobwork_order_id . ' LIMIT 1')
        : null;
    $jobwork_no_plain = ($jwo_meta && !empty($jwo_meta['jobwork_no']))
        ? trim((string) $jwo_meta['jobwork_no'])
        : ('JWO-' . (int) $jobwork_order_id);
    $seq_row = function_exists('getRecord')
        ? getRecord(
            "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1 AND stock_type = 'outward'"
            . " AND reference_type = 'jobwork_order' AND reference_id = " . (int) $jobwork_order_id
        )
        : null;
    $pay_seq = ((int) ($seq_row['c'] ?? 0)) + 1;
    try {
        if (function_exists('auragold_post_jobwork_order_metal_outward_from_inventory')) {
            auragold_post_jobwork_order_metal_outward_from_inventory(
                $conn,
                (int) $jobwork_order_id,
                $jobwork_no_plain,
                date('Y-m-d'),
                $me_payment,
                $pay_seq,
                $inv_src
            );
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage(), 'weight' => 0.0, 'adjustment_id' => 0];
    }

    $prod_note = trim((string) ($me_payment['metal_exchange_product_name'] ?? ''));
    $code_note = trim((string) ($me_payment['metal_exchange_item_code'] ?? ''));
    $remark_db = 'Shop stock outward';
    if ($prod_note !== '') {
        $remark_db .= ' · ' . $prod_note;
    }
    if ($code_note !== '') {
        $remark_db .= ' · ' . $code_note;
    }
    if ($remark !== '') {
        $remark_db .= ' · ' . $remark;
    }
    $in_note = 'Add weight from branch stock to department ' . $to_dept_id . ': ' . $remark_db;

    [$ok, $new_id, $err] = mp_jwq_insert_weight_adjustment_row(
        $conn,
        $jobwork_order_id,
        'add',
        $weight,
        $in_note,
        $uid,
        $to_dept_id,
        $to_user_id
    );
    if (!$ok) {
        return ['ok' => false, 'message' => $err !== '' ? $err : 'Could not save add adjustment.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    if ($update_item_weights) {
        $ji_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
        if ($ji_chk && mysqli_num_rows($ji_chk) > 0) {
            mysqli_free_result($ji_chk);
            $item_row = function_exists('getRecord')
                ? getRecord('SELECT id, net_weight, gross_weight, final_weight FROM tbl_jobwork_order_items WHERE jobwork_order_id = '
                    . (int) $jobwork_order_id . ' ORDER BY id ASC LIMIT 1')
                : null;
            if ($item_row && (int) ($item_row['id'] ?? 0) > 0) {
                $item_id = (int) $item_row['id'];
                $cols_q = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
                $ji_cols = [];
                if ($cols_q) {
                    while ($c = mysqli_fetch_assoc($cols_q)) {
                        $fn = (string) ($c['Field'] ?? '');
                        if ($fn !== '') {
                            $ji_cols[$fn] = true;
                        }
                    }
                    mysqli_free_result($cols_q);
                }
                $sets = [];
                if (!empty($ji_cols['net_weight'])) {
                    $nw = (float) ($item_row['net_weight'] ?? 0);
                    $sets[] = 'net_weight = ' . round(max(0, $nw + $weight), 4);
                }
                if (!empty($ji_cols['gross_weight'])) {
                    $gw = (float) ($item_row['gross_weight'] ?? 0);
                    $sets[] = 'gross_weight = ' . round(max(0, $gw + $weight), 4);
                }
                if (!empty($ji_cols['final_weight'])) {
                    $fw = (float) ($item_row['final_weight'] ?? 0);
                    $base_fw = $fw > 0.0000001 ? $fw : (float) ($item_row['net_weight'] ?? 0);
                    $sets[] = 'final_weight = ' . round(max(0, $base_fw + $weight), 4);
                }
                if ($sets !== []) {
                    @mysqli_query(
                        $conn,
                        'UPDATE tbl_jobwork_order_items SET ' . implode(', ', $sets)
                        . ' WHERE id = ' . $item_id . ' AND jobwork_order_id = ' . (int) $jobwork_order_id . ' LIMIT 1'
                    );
                }
            }
        } elseif ($ji_chk) {
            mysqli_free_result($ji_chk);
        }
    }

    return ['ok' => true, 'message' => 'Add weight saved.', 'weight' => $weight, 'adjustment_id' => (int) $new_id];
}

/**
 * Transfer weight from one department to another (add weight without shop metal exchange).
 *
 * @return array{ok:bool,message:string,weight:float,adjustment_id:int}
 */
function mp_jwq_apply_add_dept_transfer(
    mysqli $conn,
    int $jobwork_order_id,
    float $weight,
    int $from_dept_id,
    int $from_user_id,
    int $to_dept_id,
    int $to_user_id,
    int $uid,
    string $remark = '',
    bool $update_item_weights = true
): array {
    if (!is_finite($weight) || $weight <= 0) {
        return ['ok' => false, 'message' => 'Enter add weight greater than zero.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    if ($from_dept_id < 1) {
        return ['ok' => false, 'message' => 'Please select From Dept.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    if ($to_dept_id < 1) {
        return ['ok' => false, 'message' => 'Please select To Dept.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    if ($from_dept_id === $to_dept_id && $from_user_id === $to_user_id) {
        return ['ok' => false, 'message' => 'From and To department/user cannot be the same.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    $weight = round($weight, 4);
    $note = $remark !== '' ? $remark : '';
    $out_note = 'Add weight transfer outward to department ' . $to_dept_id;
    $in_note = 'Add weight transfer inward from department ' . $from_dept_id;
    if ($note !== '') {
        $out_note .= ': ' . $note;
        $in_note .= ': ' . $note;
    }
    [$ok_out, , $err_out] = mp_jwq_insert_weight_adjustment_row(
        $conn,
        $jobwork_order_id,
        'reduce',
        $weight,
        $out_note,
        $uid,
        $from_dept_id,
        $from_user_id
    );
    if (!$ok_out) {
        return ['ok' => false, 'message' => $err_out !== '' ? $err_out : 'Could not deduct from source department.', 'weight' => 0.0, 'adjustment_id' => 0];
    }
    [$ok, $new_id, $err] = mp_jwq_insert_weight_adjustment_row(
        $conn,
        $jobwork_order_id,
        'add',
        $weight,
        $in_note,
        $uid,
        $to_dept_id,
        $to_user_id
    );
    if (!$ok) {
        return ['ok' => false, 'message' => $err !== '' ? $err : 'Could not add to destination department.', 'weight' => 0.0, 'adjustment_id' => 0];
    }

    if ($update_item_weights) {
        $ji_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
        if ($ji_chk && mysqli_num_rows($ji_chk) > 0) {
            mysqli_free_result($ji_chk);
            $item_row = function_exists('getRecord')
                ? getRecord('SELECT id, net_weight, gross_weight, final_weight FROM tbl_jobwork_order_items WHERE jobwork_order_id = '
                    . (int) $jobwork_order_id . ' ORDER BY id ASC LIMIT 1')
                : null;
            if ($item_row && (int) ($item_row['id'] ?? 0) > 0) {
                $item_id = (int) $item_row['id'];
                $cols_q = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
                $ji_cols = [];
                if ($cols_q) {
                    while ($c = mysqli_fetch_assoc($cols_q)) {
                        $fn = (string) ($c['Field'] ?? '');
                        if ($fn !== '') {
                            $ji_cols[$fn] = true;
                        }
                    }
                    mysqli_free_result($cols_q);
                }
                $sets = [];
                if (!empty($ji_cols['net_weight'])) {
                    $nw = (float) ($item_row['net_weight'] ?? 0);
                    $sets[] = 'net_weight = ' . round(max(0, $nw + $weight), 4);
                }
                if (!empty($ji_cols['gross_weight'])) {
                    $gw = (float) ($item_row['gross_weight'] ?? 0);
                    $sets[] = 'gross_weight = ' . round(max(0, $gw + $weight), 4);
                }
                if (!empty($ji_cols['final_weight'])) {
                    $fw = (float) ($item_row['final_weight'] ?? 0);
                    $base_fw = $fw > 0.0000001 ? $fw : (float) ($item_row['net_weight'] ?? 0);
                    $sets[] = 'final_weight = ' . round(max(0, $base_fw + $weight), 4);
                }
                if ($sets !== []) {
                    @mysqli_query(
                        $conn,
                        'UPDATE tbl_jobwork_order_items SET ' . implode(', ', $sets)
                        . ' WHERE id = ' . $item_id . ' AND jobwork_order_id = ' . (int) $jobwork_order_id . ' LIMIT 1'
                    );
                }
            }
        } elseif ($ji_chk) {
            mysqli_free_result($ji_chk);
        }
    }

    return ['ok' => true, 'message' => 'Add weight transferred between departments.', 'weight' => $weight, 'adjustment_id' => (int) $new_id];
}
