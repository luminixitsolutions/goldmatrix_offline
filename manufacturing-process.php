<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_department_schema.php';
auragold_ensure_department_schema($conn);

$mp_manufacturing_mode = isset($mp_manufacturing_mode) ? (string) $mp_manufacturing_mode : 'inhouse';
if ($mp_manufacturing_mode !== 'outsource') {
    $mp_manufacturing_mode = 'inhouse';
}
$mp_page_self = ($mp_manufacturing_mode === 'outsource') ? 'manufacturing-outsource.php' : 'manufacturing-process.php';
$mp_page_heading = ($mp_manufacturing_mode === 'outsource') ? 'Manufacturing Outsource' : 'Manufacturing Process';
$mp_dept_panel_title = ($mp_manufacturing_mode === 'outsource') ? 'Outsource Departments' : 'All Department';
$mp_all_jobs_label = ($mp_manufacturing_mode === 'outsource') ? 'All Outsource' : 'All jobs';
$mp_all_jobs_subtitle = ($mp_manufacturing_mode === 'outsource')
    ? 'All outsource departments · all users'
    : 'All departments · all users';

$departments = [];
$mp_scope_dept_ids = [];
if ($mp_manufacturing_mode === 'outsource') {
    $departments = auragold_get_outsource_departments($conn);
    $mp_scope_dept_ids = auragold_outsource_department_ids($conn);
} else {
    $departments = auragold_get_inhouse_departments($conn);
    $mp_scope_dept_ids = auragold_inhouse_department_ids($conn);
}
if (!is_array($departments)) {
    $departments = [];
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_departments'");
$has_departments_table = ($tbl && mysqli_num_rows($tbl) > 0);
if ($tbl) {
    mysqli_free_result($tbl);
}
if ($departments === [] && $has_departments_table && function_exists('getList')) {
    $dac = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_departments LIKE 'auto_loss'");
    $has_auto_loss_col = ($dac && mysqli_num_rows($dac) > 0);
    if ($dac) {
        mysqli_free_result($dac);
    }
    if ($has_auto_loss_col) {
        $departments = getList('SELECT id, dept_name, auto_loss FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC');
    } else {
        $departments = getList('SELECT id, dept_name, 0 AS auto_loss FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC');
    }
    if (!is_array($departments)) {
        $departments = [];
    }
    if ($mp_scope_dept_ids === []) {
        foreach ($departments as $drow) {
            $did = (int) ($drow['id'] ?? 0);
            if ($did > 0) {
                $mp_scope_dept_ids[] = $did;
            }
        }
    }
}

// Find Job Worker customer type ID
$job_worker_type_id = 0;
$jw_result = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE LOWER(name) = 'job worker' AND status = 1 LIMIT 1");
if ($jw_result && mysqli_num_rows($jw_result) > 0) {
    $jw_row = mysqli_fetch_assoc($jw_result);
    $job_worker_type_id = (int)$jw_row['id'];
}

// Get users for each department from tbl_customers (Job Workers) mapped via tbl_department_user_map
$department_users = [];
foreach ($departments as $dept) {
    $dept_id = (int)$dept['id'];
    $department_users[$dept_id] = [];
    
    // Check if mapping table exists
    $map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
    if ($map_tbl && mysqli_num_rows($map_tbl) > 0) {
        mysqli_free_result($map_tbl);
        
        // Get Job Worker customers assigned to this department
        $users_query = "
            SELECT c.id, c.name 
            FROM tbl_customers c
            INNER JOIN tbl_department_user_map dum ON c.id = dum.user_id AND dum.status = 1
            WHERE dum.department_id = $dept_id 
            AND c.status = 1
            " . ($job_worker_type_id > 0 ? "AND c.customer_type_id = $job_worker_type_id" : "") . "
            ORDER BY c.name ASC
        ";
        $users_result = @mysqli_query($conn, $users_query);
        if ($users_result) {
            while ($user_row = mysqli_fetch_assoc($users_result)) {
                $department_users[$dept_id][] = $user_row;
            }
            mysqli_free_result($users_result);
        }
    }
}

// All saved Job Work Orders (for card grid + department/user filter)
$mp_jobwork_orders = [];
$chk_jwo = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if ($chk_jwo && mysqli_num_rows($chk_jwo) > 0) {
    mysqli_free_result($chk_jwo);
    $jwo_cols = [];
    $colq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders");
    if ($colq) {
        while ($cr = mysqli_fetch_assoc($colq)) {
            $jwo_cols[$cr['Field']] = true;
        }
        mysqli_free_result($colq);
    }
    $sel = 'j.id, j.jobwork_no, j.sale_order_id, j.sale_order_no, j.customer_name, j.order_date, j.due_date, j.grand_total, j.status, j.created_at';
    $join = '';
    if (!empty($jwo_cols['department_id'])) {
        $sel .= ', j.department_id, d.dept_name';
        $join .= ' LEFT JOIN tbl_departments d ON j.department_id = d.id';
    } else {
        $sel .= ', NULL AS department_id, NULL AS dept_name';
    }
    if (!empty($jwo_cols['priority'])) {
        $sel .= ', j.priority';
    } else {
        $sel .= ", 'Medium' AS priority";
    }
    if (!empty($jwo_cols['department_user_id'])) {
        $sel .= ', j.department_user_id, c.name AS worker_name';
        $join .= ' LEFT JOIN tbl_customers c ON j.department_user_id = c.id';
    } else {
        $sel .= ', NULL AS department_user_id, NULL AS worker_name';
    }
    // Linked sale order: hide row when sale order is completed (e.g. after jobwork invoice saved)
    $join .= ' LEFT JOIN tbl_sale_orders so_mp ON so_mp.id = j.sale_order_id';
    if (empty($jwo_cols['manufacturing_time_seconds'])) {
        $al = @mysqli_query($conn, "ALTER TABLE tbl_jobwork_orders ADD COLUMN manufacturing_time_seconds INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cumulative manufacturing time (seconds)'");
        if ($al) {
            $jwo_cols['manufacturing_time_seconds'] = true;
        } elseif (mysqli_errno($conn) === 1060) {
            $jwo_cols['manufacturing_time_seconds'] = true;
        }
    }
    if (!empty($jwo_cols['manufacturing_time_seconds'])) {
        $sel .= ', j.manufacturing_time_seconds';
    } else {
        $sel .= ', 0 AS manufacturing_time_seconds';
    }
    if (empty($jwo_cols['jobwork_queue_no'])) {
        $alj = @mysqli_query($conn, "ALTER TABLE tbl_jobwork_orders ADD COLUMN jobwork_queue_no VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Jobwork Queue No from bill series (Jobwork Queue voucher)' AFTER jobwork_no");
        if ($alj) {
            $jwo_cols['jobwork_queue_no'] = true;
        } elseif (mysqli_errno($conn) === 1060) {
            $jwo_cols['jobwork_queue_no'] = true;
        }
    }
    if (!empty($jwo_cols['jobwork_queue_no'])) {
        $sel .= ', j.jobwork_queue_no';
    } else {
        $sel .= ", '' AS jobwork_queue_no";
    }
    $so_has_branch = false;
    $sbc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'branch_id'");
    if ($sbc && mysqli_num_rows($sbc) > 0) {
        $so_has_branch = true;
    }
    if ($sbc) {
        mysqli_free_result($sbc);
    }
    if ($so_has_branch) {
        $sel .= ', IFNULL(so_mp.branch_id, 0) AS sale_branch_id';
    } else {
        $sel .= ', 0 AS sale_branch_id';
    }
    $jwo_item_cols = [];
    $item_colq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_order_items");
    if ($item_colq) {
        while ($icr = mysqli_fetch_assoc($item_colq)) {
            $jwo_item_cols[$icr['Field']] = true;
        }
        mysqli_free_result($item_colq);
    }
    require_once __DIR__ . '/includes/auragold_mfg_jobwork_queue_line_weights.php';
    $issue_tbl_name = 'tbl_jobwork_queue_diamond_stock_issue';
    $issue_tbl_chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $issue_tbl_name) . "'");
    $has_issue_tbl = ($issue_tbl_chk && mysqli_num_rows($issue_tbl_chk) > 0);
    if ($issue_tbl_chk) {
        mysqli_free_result($issue_tbl_chk);
    }
    $first_item_barcode_expr = "''";
    if (!empty($jwo_item_cols['barcode_no']) && !empty($jwo_item_cols['barcode'])) {
        $first_item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), NULLIF(TRIM(ji.barcode),''), '')";
    } elseif (!empty($jwo_item_cols['barcode_no'])) {
        $first_item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), '')";
    } elseif (!empty($jwo_item_cols['barcode'])) {
        $first_item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode),''), '')";
    }
    $sel .= ', (SELECT ji.product_name FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = j.id ORDER BY ji.id ASC LIMIT 1) AS first_product';
    $sel .= ", (SELECT GROUP_CONCAT(TRIM(ji.product_name) ORDER BY ji.id ASC SEPARATOR ', ') FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = j.id AND TRIM(COALESCE(ji.product_name,'')) <> '') AS all_products";
    $sel .= ", (SELECT {$first_item_barcode_expr} FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = j.id ORDER BY ji.id ASC LIMIT 1) AS first_item_barcode";
    // Card / list aggregate: final_weight when > 0, else metal − loss + diamond per line.
    $mp_ji2_dwt = '0';
    if (!empty($jwo_item_cols['diamond_weight']) && !empty($jwo_item_cols['diamond_wt'])) {
        $mp_ji2_dwt = 'COALESCE(ji2.diamond_weight, ji2.diamond_wt, 0)';
    } elseif (!empty($jwo_item_cols['diamond_weight'])) {
        $mp_ji2_dwt = 'COALESCE(ji2.diamond_weight, 0)';
    } elseif (!empty($jwo_item_cols['diamond_wt'])) {
        $mp_ji2_dwt = 'COALESCE(ji2.diamond_wt, 0)';
    }
    $mp_ji2_loss = '0';
    if (!empty($jwo_item_cols['gold_loss_1']) && !empty($jwo_item_cols['loss_wt'])) {
        $mp_ji2_loss = 'COALESCE(NULLIF(ji2.gold_loss_1, 0), ji2.loss_wt, 0)';
    } elseif (!empty($jwo_item_cols['gold_loss_1'])) {
        $mp_ji2_loss = 'COALESCE(ji2.gold_loss_1, 0)';
    } elseif (!empty($jwo_item_cols['loss_wt'])) {
        $mp_ji2_loss = 'COALESCE(ji2.loss_wt, 0)';
    }
    $mp_ji2_metal = '(CASE WHEN COALESCE(ji2.net_weight,0) > 0.0000001 THEN ji2.net_weight ELSE COALESCE(ji2.gross_weight, 0) END)';
    $mp_ji2_fb = 'GREATEST(0, (' . $mp_ji2_metal . ') - (' . $mp_ji2_loss . ') + (' . $mp_ji2_dwt . '))';
    if (!empty($jwo_item_cols['final_weight']) || !empty($jwo_item_cols['net_weight']) || !empty($jwo_item_cols['gross_weight'])) {
        if (!empty($jwo_item_cols['final_weight'])) {
            $mp_wt_sum_inner = '(CASE WHEN COALESCE(ji2.final_weight,0) > 0.0000001 THEN ji2.final_weight ELSE (' . $mp_ji2_fb . ') END)';
        } else {
            $mp_wt_sum_inner = $mp_ji2_fb;
        }
        $sel .= ", (SELECT COALESCE(SUM({$mp_wt_sum_inner}), 0) FROM tbl_jobwork_order_items ji2 WHERE ji2.jobwork_order_id = j.id) AS jwo_total_wt_num";
    } else {
        $sel .= ', 0 AS jwo_total_wt_num';
    }
    if ($has_issue_tbl) {
        $sel .= ", (SELECT COALESCE(SUM(ds.weight),0) FROM `$issue_tbl_name` ds WHERE ds.jobwork_order_id = j.id) AS jwo_diamond_issue_wt";
    } else {
        $sel .= ', 0 AS jwo_diamond_issue_wt';
    }
    // Card weight: NA until a dept/user transfer is logged; then show saved line weight + purity.
    $jwo_has_transfer_sql = '0';
    $act_chk_tr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    if ($act_chk_tr && mysqli_num_rows($act_chk_tr) > 0) {
        mysqli_free_result($act_chk_tr);
        $jwo_has_transfer_sql = "(SELECT COUNT(*) FROM tbl_jobwork_queue_activity a WHERE a.jobwork_order_id = j.id AND ("
            . " LOWER(TRIM(IFNULL(a.activity_action,''))) = 'department_transfer'"
            . " OR ("
            . " (a.activity_action IS NULL OR TRIM(IFNULL(a.activity_action,'')) = '')"
            . " AND IFNULL(a.from_dept_id,0) > 0"
            . " AND (IFNULL(a.from_dept_id,0) <> IFNULL(a.to_dept_id,0) OR IFNULL(a.from_user_id,0) <> IFNULL(a.to_user_id,0))"
            . ")))";
    } elseif ($act_chk_tr) {
        mysqli_free_result($act_chk_tr);
    }
    $sel .= ', (' . $jwo_has_transfer_sql . ') AS jwo_has_floor_transfer';
    $jwo_where_mp = " WHERE LOWER(TRIM(COALESCE(j.status,''))) <> 'completed' "
        . "AND (so_mp.id IS NULL OR LOWER(TRIM(COALESCE(so_mp.status,''))) <> 'completed')";
    // Outsource: also keep Stock-in (invoice) cards so Material Receive can finish remaining metal balance.
    if ($mp_manufacturing_mode === 'outsource') {
        $jwi_chk_mp = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_invoices'");
        if ($jwi_chk_mp && mysqli_num_rows($jwi_chk_mp) > 0) {
            $jwo_where_mp = ' WHERE ('
                . "(LOWER(TRIM(COALESCE(j.status,''))) <> 'completed' "
                . "AND (so_mp.id IS NULL OR LOWER(TRIM(COALESCE(so_mp.status,''))) <> 'completed'))"
                . ' OR EXISTS (SELECT 1 FROM tbl_jobwork_invoices jwi_mp WHERE jwi_mp.jobwork_order_id = j.id)'
                . ')';
        }
        if ($jwi_chk_mp) {
            mysqli_free_result($jwi_chk_mp);
        }
    }
    // Manufacturing floor: only jobwork orders where a department was chosen (unassigned orders use job-work-order-manufacturing.php).
    if (!empty($jwo_cols['department_id'])) {
        $jwo_where_mp .= ' AND IFNULL(j.department_id, 0) > 0 ';
        if ($mp_scope_dept_ids !== []) {
            $jwo_where_mp .= ' AND j.department_id IN (' . auragold_department_sql_in_ids($mp_scope_dept_ids) . ') ';
        } elseif ($mp_manufacturing_mode === 'outsource') {
            $jwo_where_mp .= ' AND 1=0 ';
        }
    }
    $sql_jwo = 'SELECT ' . $sel . ' FROM tbl_jobwork_orders j ' . $join . $jwo_where_mp . ' ORDER BY j.id DESC';
    $mp_jobwork_orders = function_exists('getList') ? @getList($sql_jwo) : [];
    if (!is_array($mp_jobwork_orders)) {
        $mp_jobwork_orders = [];
    }
    if (!empty($jwo_cols['jobwork_queue_no']) && function_exists('ensureJobworkQueueNoForOrder')) {
        foreach ($mp_jobwork_orders as &$jwo) {
            $jid = (int)($jwo['id'] ?? 0);
            if ($jid > 0) {
                $qn = ensureJobworkQueueNoForOrder($conn, $jid);
                if ($qn !== null && $qn !== '') {
                    $jwo['jobwork_queue_no'] = $qn;
                }
            }
        }
        unset($jwo);
    }
    // Render manufacturing cards item-wise: one card per jobwork item with its own barcode.
    $mp_itemwise_cards = [];
    $item_barcode_expr = "''";
    if (!empty($jwo_item_cols['barcode_no']) && !empty($jwo_item_cols['barcode'])) {
        $item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), NULLIF(TRIM(ji.barcode),''), '')";
    } elseif (!empty($jwo_item_cols['barcode_no'])) {
        $item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode_no),''), '')";
    } elseif (!empty($jwo_item_cols['barcode'])) {
        $item_barcode_expr = "COALESCE(NULLIF(TRIM(ji.barcode),''), '')";
    }
    $extra_item_cols = '';
    if (!empty($jwo_item_cols['product_id'])) {
        $extra_item_cols .= ', ji.product_id AS line_product_id';
    } else {
        $extra_item_cols .= ', NULL AS line_product_id';
    }
    if (!empty($jwo_item_cols['product_characteristic_id']) && !empty($jwo_item_cols['product_id'])) {
        $extra_item_cols .= ', (SELECT pc.metal_id FROM tbl_product_characteristics pc WHERE pc.id = ji.product_characteristic_id AND pc.product_id = ji.product_id AND pc.status = 1 LIMIT 1) AS line_metal_id';
    } elseif (!empty($jwo_item_cols['product_id'])) {
        $extra_item_cols .= ', (SELECT MIN(pc.metal_id) FROM tbl_product_characteristics pc WHERE pc.product_id = ji.product_id AND pc.status = 1) AS line_metal_id';
    } else {
        $extra_item_cols .= ', NULL AS line_metal_id';
    }
    if (!empty($jwo_item_cols['final_weight'])) {
        $extra_item_cols .= ', ji.final_weight AS line_final_wt';
    } else {
        $extra_item_cols .= ', NULL AS line_final_wt';
    }
    if (!empty($jwo_item_cols['net_weight'])) {
        $extra_item_cols .= ', ji.net_weight AS line_net_wt';
    } else {
        $extra_item_cols .= ', NULL AS line_net_wt';
    }
    if (!empty($jwo_item_cols['gross_weight'])) {
        $extra_item_cols .= ', ji.gross_weight AS line_gross_wt';
    } else {
        $extra_item_cols .= ', NULL AS line_gross_wt';
    }
    if (!empty($jwo_item_cols['diamond_weight']) && !empty($jwo_item_cols['diamond_wt'])) {
        $extra_item_cols .= ', COALESCE(ji.diamond_weight, ji.diamond_wt, 0) AS line_diamond_wt';
    } elseif (!empty($jwo_item_cols['diamond_weight'])) {
        $extra_item_cols .= ', COALESCE(ji.diamond_weight, 0) AS line_diamond_wt';
    } elseif (!empty($jwo_item_cols['diamond_wt'])) {
        $extra_item_cols .= ', COALESCE(ji.diamond_wt, 0) AS line_diamond_wt';
    } else {
        $extra_item_cols .= ', 0 AS line_diamond_wt';
    }
    if (!empty($jwo_item_cols['gold_loss_1']) && !empty($jwo_item_cols['loss_wt'])) {
        $extra_item_cols .= ', COALESCE(NULLIF(ji.gold_loss_1, 0), ji.loss_wt, 0) AS line_saved_loss_wt';
    } elseif (!empty($jwo_item_cols['gold_loss_1'])) {
        $extra_item_cols .= ', COALESCE(ji.gold_loss_1, 0) AS line_saved_loss_wt';
    } elseif (!empty($jwo_item_cols['loss_wt'])) {
        $extra_item_cols .= ', COALESCE(ji.loss_wt, 0) AS line_saved_loss_wt';
    } else {
        $extra_item_cols .= ', 0 AS line_saved_loss_wt';
    }
    if (!empty($jwo_item_cols['purity'])) {
        $extra_item_cols .= ', ji.purity AS line_purity';
    } else {
        $extra_item_cols .= ', NULL AS line_purity';
    }
    if (!empty($jwo_item_cols['carat'])) {
        $extra_item_cols .= ', ji.carat AS line_carat';
    } else {
        $extra_item_cols .= ', NULL AS line_carat';
    }
    $soi_has_images = false;
    $soi_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_items LIKE 'images'");
    if ($soi_chk && mysqli_num_rows($soi_chk) > 0) {
        $soi_has_images = true;
    }
    if ($soi_chk) {
        mysqli_free_result($soi_chk);
    }

    foreach ($mp_jobwork_orders as $jwo_card) {
        $jid = (int)($jwo_card['id'] ?? 0);
        $so_id_card = (int)($jwo_card['sale_order_id'] ?? 0);
        $item_rows = [];
        if ($jid > 0) {
            $soi_sel = '';
            if ($soi_has_images) {
                if ($so_id_card > 0) {
                    // Prefer product_id (avoids wrong line when barcodes are empty / duplicate). Barcode match only when barcode is non-empty.
                    if (!empty($jwo_item_cols['product_id'])) {
                        $soi_sel = ", (SELECT soi.images FROM tbl_sale_order_items soi WHERE soi.order_id = {$so_id_card} AND (
                            (ji.product_id IS NOT NULL AND ji.product_id > 0 AND soi.product_id = ji.product_id)
                            OR (
                                (ji.product_id IS NULL OR ji.product_id = 0)
                                AND LENGTH(TRIM(IFNULL(soi.barcode,''))) > 0
                                AND TRIM(IFNULL(soi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$item_barcode_expr},'')) COLLATE utf8mb4_unicode_ci
                            )
                        ) ORDER BY soi.id ASC LIMIT 1) AS sale_item_images";
                    } else {
                        $soi_sel = ", (SELECT soi.images FROM tbl_sale_order_items soi WHERE soi.order_id = {$so_id_card} AND LENGTH(TRIM(IFNULL(soi.barcode,''))) > 0 AND TRIM(IFNULL(soi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$item_barcode_expr},'')) COLLATE utf8mb4_unicode_ci LIMIT 1) AS sale_item_images";
                    }
                } else {
                    $soi_sel = ', NULL AS sale_item_images';
                }
            }
            $sql_items = "SELECT TRIM(COALESCE(ji.product_name,'')) AS product_name, {$item_barcode_expr} AS item_barcode{$extra_item_cols}{$soi_sel} FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = {$jid} ORDER BY ji.id ASC";
            $item_rows = function_exists('getList') ? @getList($sql_items) : [];
            if (!is_array($item_rows)) {
                $item_rows = [];
            }
        }
        if (empty($item_rows)) {
            $item_rows[] = [
                'product_name' => (string)($jwo_card['first_product'] ?? ''),
                'item_barcode' => (string)($jwo_card['first_item_barcode'] ?? ''),
                'line_product_id' => 0,
                'line_metal_id' => 0,
                'line_final_wt' => null,
                'line_net_wt' => null,
                'line_gross_wt' => null,
                'line_diamond_wt' => 0,
                'line_saved_loss_wt' => 0,
                'line_purity' => null,
                'line_carat' => null,
            ];
        }
        foreach ($item_rows as $ir) {
            $row = $jwo_card;
            $row['first_product'] = trim((string)($ir['product_name'] ?? ''));
            $row['first_item_barcode'] = trim((string)($ir['item_barcode'] ?? ''));
            $row['line_product_id'] = (int)($ir['line_product_id'] ?? 0);
            $row['line_metal_id'] = (int)($ir['line_metal_id'] ?? 0);
            $row['line_final_wt'] = $ir['line_final_wt'] ?? null;
            $row['line_net_wt'] = $ir['line_net_wt'] ?? null;
            $row['line_gross_wt'] = $ir['line_gross_wt'] ?? null;
            $row['line_purity'] = $ir['line_purity'] ?? null;
            $row['line_carat'] = $ir['line_carat'] ?? null;
            $itemSynth = [
                'final_weight' => $ir['line_final_wt'] ?? null,
                'net_weight' => $ir['line_net_wt'] ?? null,
                'gross_weight' => $ir['line_gross_wt'] ?? null,
                'diamond_weight' => $ir['line_diamond_wt'] ?? 0,
                'diamond_wt' => $ir['line_diamond_wt'] ?? 0,
                'gold_loss_1' => $ir['line_saved_loss_wt'] ?? 0,
                'loss_wt' => $ir['line_saved_loss_wt'] ?? 0,
            ];
            $row['jwo_total_wt_num'] = auragold_mfg_jobwork_line_calculated_total_wt($itemSynth, $jwo_item_cols);
            $row['item_image_url'] = '';
            $img_raw = isset($ir['sale_item_images']) ? trim((string)$ir['sale_item_images']) : '';
            if ($img_raw !== '') {
                $dec = @json_decode($img_raw, true);
                if ($dec && !empty($dec['primary'])) {
                    $p = trim((string)$dec['primary']);
                    if ($p !== '') {
                        if (preg_match('#^https?://#i', $p)) {
                            $row['item_image_url'] = $p;
                        } else {
                            $row['item_image_url'] = auragold_uploads_public_url($p);
                        }
                    }
                }
            }
            $mp_itemwise_cards[] = $row;
        }
    }
    $mp_jobwork_orders = $mp_itemwise_cards;

    // Outsource Stock-in: keep cards with remaining metal ME balance; lock floor icons except Material Receive.
    if ($mp_manufacturing_mode === 'outsource' && $mp_jobwork_orders !== []) {
        require_once __DIR__ . '/includes/auragold_sale_order_jobwork_lock.php';
        require_once __DIR__ . '/includes/auragold_material_issue_rows_for_sale_order.php';
        $mp_me_bal_cache = [];
        $mp_filtered_cards = [];
        foreach ($mp_jobwork_orders as $mp_card_row) {
            if (!is_array($mp_card_row)) {
                continue;
            }
            $mp_cid = (int) ($mp_card_row['id'] ?? 0);
            $mp_soid = (int) ($mp_card_row['sale_order_id'] ?? 0);
            $mp_stock_in = $mp_cid > 0 && function_exists('auragold_jobwork_order_has_invoice')
                ? auragold_jobwork_order_has_invoice($conn, $mp_cid)
                : false;
            $mp_me_bal = 0.0;
            if ($mp_soid > 0 && function_exists('auragold_material_issue_list_metal_exchange_rows_for_sale_order')) {
                if (!array_key_exists($mp_soid, $mp_me_bal_cache)) {
                    $mp_me_sum = 0.0;
                    $mp_me_rows = auragold_material_issue_list_metal_exchange_rows_for_sale_order($conn, $mp_soid, '', false);
                    if (is_array($mp_me_rows)) {
                        foreach ($mp_me_rows as $mp_me_r) {
                            if (!is_array($mp_me_r)) {
                                continue;
                            }
                            $mp_me_sum += (float) ($mp_me_r['balance_gross'] ?? $mp_me_r['balance_weight'] ?? 0);
                        }
                    }
                    $mp_me_bal_cache[$mp_soid] = $mp_me_sum;
                }
                $mp_me_bal = (float) $mp_me_bal_cache[$mp_soid];
            }
            // Fully settled after Stock-in (no metal left to receive) — hide from floor.
            if ($mp_stock_in && $mp_me_bal <= 0.0000001) {
                continue;
            }
            $mp_card_row['mp_stock_in_done'] = $mp_stock_in ? 1 : 0;
            $mp_card_row['mp_me_metal_balance'] = $mp_me_bal;
            $mp_filtered_cards[] = $mp_card_row;
        }
        $mp_jobwork_orders = $mp_filtered_cards;
    }
} elseif ($chk_jwo) {
    mysqli_free_result($chk_jwo);
}

$metals = function_exists('getList') ? @getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC, id ASC") : [];
if (!is_array($metals)) {
    $metals = [];
}
$filter_branches = function_exists('getListMaster') ? @getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC") : [];
if (!is_array($filter_branches)) {
    $filter_branches = [];
}
$filter_products = function_exists('getList') ? @getList("SELECT id, name FROM tbl_products WHERE status = 1 AND TRIM(IFNULL(name,'')) != '' ORDER BY name ASC LIMIT 800") : [];
if (!is_array($filter_products)) {
    $filter_products = [];
}
$filter_priorities_distinct = function_exists('getList') ? @getList("SELECT DISTINCT TRIM(priority) AS p FROM tbl_jobwork_orders WHERE TRIM(IFNULL(priority,'')) != '' ORDER BY p ASC") : [];
if (!is_array($filter_priorities_distinct)) {
    $filter_priorities_distinct = [];
}
$filter_priority_options = [];
foreach ($filter_priorities_distinct as $pr) {
    $pv = trim((string)($pr['p'] ?? ''));
    if ($pv !== '') {
        $filter_priority_options[] = $pv;
    }
}
foreach (['High', 'Medium', 'Low', 'Hold', 'Processing', 'Draft'] as $defp) {
    if (!in_array($defp, $filter_priority_options, true)) {
        $filter_priority_options[] = $defp;
    }
}
$filter_priority_options = array_values(array_unique($filter_priority_options));

$bank_accounts_raw = function_exists('getList') ? @getList("SELECT id, name FROM tbl_customers WHERE sundry_debtors_id = 29 AND status = 1 AND TRIM(IFNULL(name,'')) != '' ORDER BY name ASC") : [];
$bank_accounts = [];
$exclude_bank_names = ['phonepe', 'phonepay', 'gpay', 'google pay', 'paytm', 'upi', '0.00', '0'];
if (is_array($bank_accounts_raw)) {
    foreach ($bank_accounts_raw as $b) {
        $n = trim(strtolower($b['name'] ?? ''));
        if ($n === '' || in_array($n, $exclude_bank_names) || preg_match('/^[0-9.]+$/', $n)) {
            continue;
        }
        $bank_accounts[] = $b;
    }
}

/** Inward / Outward split stock grids — keys must match ajax/mp-manufacturing-queue-table.php */
$stock_columns = [
    ['key' => 'queue_no', 'label' => 'Queue No'],
    ['key' => 'comment', 'label' => 'Comment'],
    ['key' => 'product_name', 'label' => 'Product Name'],
    ['key' => 'active', 'label' => 'Status'],
    ['key' => 'against_queue', 'label' => 'Against Queue'],
    ['key' => 'against_invoice', 'label' => 'Against Invoice'],
    ['key' => 'metal', 'label' => 'Metal'],
    ['key' => 'description', 'label' => 'Description'],
    ['key' => 'dust_wastage_wt', 'label' => 'Dust / Wastage Wt'],
    ['key' => 'loss_wt', 'label' => 'Loss Wt'],
    ['key' => 'profit_wt', 'label' => 'Profit Wt'],
    ['key' => 'tag_no', 'label' => 'Tag No.'],
    ['key' => 'total_wt', 'label' => 'Total Wt'],
    ['key' => 'metal_wt', 'label' => 'Metal Wt'],
    ['key' => 'diamond_wt', 'label' => 'Diamond Wt'],
    ['key' => 'purity_wt', 'label' => 'Purity Wt'],
    ['key' => 'carat_name', 'label' => 'Carat Name'],
    ['key' => 'total_quantity', 'label' => 'Total Quantity'],
    ['key' => 'date_time', 'label' => 'Date & Time'],
    ['key' => 'branch_name', 'label' => 'Branch Name'],
    ['key' => 'design_no', 'label' => 'DesignNo'],
    ['key' => 'department_name', 'label' => 'Department Name'],
    ['key' => 'user_name', 'label' => 'User Name'],
    ['key' => 'action', 'label' => 'Action'],
];
/** Internal stock-grid keys never shown as columns (kept for row payload / JS only). */
$stock_columns_always_hidden = ['image_urls'];

$mfg_queue_columns = require __DIR__ . '/includes/mp-manufacturing-queue-columns.php';
if (!is_array($mfg_queue_columns)) {
    $mfg_queue_columns = [];
}

/** Inward Stock popup (folder icon) — column list only; narrower UI than main stock grid */
$jwq_inward_stock_modal_columns = [
    ['key' => 'queue_no', 'label' => 'Queue No'],
    ['key' => 'comment', 'label' => 'Comment'],
    ['key' => 'product_name', 'label' => 'Product Name'],
    ['key' => 'active', 'label' => 'Status'],
    ['key' => 'against_queue', 'label' => 'Against Queue'],
    ['key' => 'against_invoice', 'label' => 'Against Invoice'],
    ['key' => 'metal', 'label' => 'Metal'],
    ['key' => 'description', 'label' => 'Description'],
    ['key' => 'dust_wastage_wt', 'label' => 'Dust / Wastage Wt'],
    ['key' => 'loss_wt', 'label' => 'Loss Wt'],
    ['key' => 'total_wt', 'label' => 'Total Wt'],
    ['key' => 'metal_wt', 'label' => 'Metal Wt'],
    ['key' => 'diamond_wt', 'label' => 'Diamond Wt'],
    ['key' => 'purity_wt', 'label' => 'Purity Wt'],
    ['key' => 'carat_name', 'label' => 'Carat Name'],
    ['key' => 'profit_wt', 'label' => 'Profit Wt'],
    ['key' => 'tag_no', 'label' => 'Tag No.'],
    ['key' => 'total_quantity', 'label' => 'Total Quantity'],
    ['key' => 'date_time', 'label' => 'Date & Time'],
];

/** Jobwork Queue modal — order lines grid (show/hide columns) */
$jwq_order_line_columns = [
    ['key' => 'design_no', 'label' => 'Design No'],
    ['key' => 'tag_no', 'label' => 'Tag No'],
    ['key' => 'description', 'label' => 'Description'],
    ['key' => 'order_no', 'label' => 'Order No'],
    ['key' => 'total_wt', 'label' => 'Total Wt'],
    ['key' => 'metal_wt', 'label' => 'Metal Wt'],
    ['key' => 'diamond_wt', 'label' => 'Diamond Wt'],
    ['key' => 'total_purity', 'label' => 'Total Purity'],
    ['key' => 'karat', 'label' => 'Karat'],
    ['key' => 'total_qty', 'label' => 'Total Qty'],
    ['key' => 'price', 'label' => 'Price'],
    ['key' => 'dust_wastage_wt', 'label' => 'Dust / Wastage Wt'],
    ['key' => 'loss', 'label' => 'Loss'],
    ['key' => 'profit', 'label' => 'Profit'],
    ['key' => 'expected_wt', 'label' => 'Expected Wt'],
    ['key' => 'product', 'label' => 'Product'],
    ['key' => 'requested_wt', 'label' => 'Requested Wt.'],
    ['key' => 'requested_purity', 'label' => 'Requested Purity'],
    ['key' => 'alloy_wt', 'label' => 'Alloy Wt.'],
    ['key' => 'damage_qty', 'label' => 'Damage Quantity'],
    ['key' => 'damage_wt', 'label' => 'Damage Weight'],
];

$mp_json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$mp_departments_json = json_encode($departments, $mp_json_flags);
if ($mp_departments_json === false) {
    $mp_departments_json = '[]';
}
$mp_department_users_json = json_encode($department_users, $mp_json_flags);
if ($mp_department_users_json === false) {
    $mp_department_users_json = '{}';
}

$carat_options = [];
$chk_carat = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_carat'");
if ($chk_carat && mysqli_num_rows($chk_carat) > 0) {
    mysqli_free_result($chk_carat);
    $carat_options = function_exists('getList')
        ? @getList("SELECT id, name, purity FROM tbl_carat WHERE status = 1 ORDER BY id ASC")
        : [];
    if (!is_array($carat_options)) {
        $carat_options = [];
    }
} elseif ($chk_carat) {
    mysqli_free_result($chk_carat);
}
$mp_carat_json = json_encode($carat_options, $mp_json_flags);
if ($mp_carat_json === false) {
    $mp_carat_json = '[]';
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($mp_page_heading, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
</head>

<style>
/* GoldMatrix brand: navy + gold (logo-aligned) */
:root {
    --gm-navy: #11294b;
    --gm-navy-dark: #0a1f36;
    --gm-navy-mid: #1a3a5c;
    --gm-navy-light: #2d4a6e;
    --gm-gold: #c9a24a;
    --gm-gold-light: #e8d48a;
    --gm-gold-pale: #faf6eb;
    --gm-gold-deep: #8b6914;
    --gm-surface: #f4f6f9;
}

html, body {
    height: 100%;
    max-height: 100vh;
    max-height: 100dvh;
    overflow-x: hidden !important;
    overflow-y: hidden !important;
    background: var(--gm-surface);
     /* font-family: "Segoe UI", Arial, sans-serif; */
}

/* Fill remaining viewport under company-header + top nav (avoids clipping Grand Total) */
body.manufacturing-process-page,
body.mfg-page.manufacturing-process-page {
    display: flex;
    flex-direction: column;
}
body.manufacturing-process-page > .company-header,
body.manufacturing-process-page > .auragold-top-nav-wrap {
    flex: 0 0 auto;
}
body.manufacturing-process-page > .auragold-nav-backdrop {
    flex: 0 0 0;
}

.layout-content {
    flex: 1 1 auto;
    min-height: 0;
    height: auto;
    overflow: hidden;
}

.container-fluid {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 8px 10px 10px !important;
}

.process-shell {
    height: 100%;
    min-height: 0;
    border: 1px solid #d6dbea;
    border-radius: 12px;
    background: #f7f8fc;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.process-header {
    height: 34px;
    background: linear-gradient(180deg, #fff 0%, #f5f7fa 100%);
    border-bottom: 1px solid #d6dbea;
    position: relative;
}

.title-chip {
    position: absolute;
    right: 10px;
    top: 5px;
    border: 1px solid rgba(201, 162, 74, 0.45);
    color: var(--gm-navy);
    background: linear-gradient(135deg, var(--gm-gold-pale) 0%, #fff 100%);
    border-radius: 0 16px 16px 0;
    padding: 3px 14px;
    font-size: 13px;
    font-weight: 700;
}

.process-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 10px;
    border-bottom: 1px solid #d6dbea;
}

.mp-toolbar-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

/* Transfer + List + Grid (Jewelsteps-style grouped segment) */
.mp-toolbar-view-group {
    display: inline-flex;
    align-items: stretch;
    border: 1px solid #cfd6e7;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(31, 41, 55, 0.06);
}

.mp-toolbar-seg-btn {
    width: 32px;
    min-height: 28px;
    border: 0;
    border-right: 1px solid #e8ecf2;
    background: #fff;
    color: var(--gm-navy-mid);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
}

.mp-toolbar-seg-btn:last-child {
    border-right: 0;
}

.mp-toolbar-seg-btn:hover {
    background: var(--gm-gold-pale);
    color: var(--gm-navy);
}

.mp-toolbar-view-group .mp-view-toggle.is-active {
    background: var(--gm-navy);
    color: var(--gm-gold-light);
}

.mp-toolbar-view-group .mp-view-toggle.is-active:hover {
    background: var(--gm-navy-mid);
    color: #fff;
}

.mp-icon-transfer {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 4px;
    line-height: 1;
}

.mp-icon-transfer .mp-tr-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.mp-icon-transfer .mp-tr-col i {
    font-size: 11px;
    width: 12px;
    height: 12px;
}

.mp-toolbar-seg-btn i.feather {
    font-size: 14px;
}

.btn-mini {
    height: 28px;
    border: 1px solid rgba(17, 41, 75, 0.25);
    color: var(--gm-navy);
    background: var(--gm-gold-pale);
    border-radius: 6px;
    padding: 0 10px;
    font-size: 13px;
    font-weight: 700;
}

.btn-mini.btn-pink {
    color: var(--gm-navy);
    border-color: var(--gm-gold);
    background: linear-gradient(180deg, #fdf8e8 0%, var(--gm-gold-pale) 100%);
}

.mp-export-dd {
    position: relative;
    display: inline-block;
}

.mp-export-dd .mp-export-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    min-width: 168px;
    background: #fff;
    border: 1px solid rgba(17, 41, 75, 0.18);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14);
    z-index: 1200;
    padding: 4px 0;
}

.mp-export-dd .mp-export-menu.show {
    display: block;
}

.mp-export-menu-item {
    display: block;
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    color: #11294b;
    cursor: pointer;
}

.mp-export-menu-item:hover {
    background: #f1f5f9;
}

/* Closing modal — navy + gold (same palette as top nav / logo) */
#mpClosingModalOverlay.filter-modal-overlay {
    z-index: 1410;
}

.mp-closing-modal {
    width: min(440px, calc(100vw - 32px));
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border: 1px solid var(--gm-navy);
    border-radius: 12px;
    box-shadow: 0 14px 34px rgba(17, 41, 75, 0.28);
}

.mp-closing-modal-head {
    position: relative;
    min-height: 44px;
    padding: 10px 40px 10px 16px;
    border-bottom: 1px solid var(--gm-gold);
    background: linear-gradient(90deg, var(--gm-navy-dark) 0%, var(--gm-navy) 50%, var(--gm-navy-mid) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.mp-closing-modal-head span {
    color: var(--gm-gold-light);
    font-size: 17px;
    font-weight: 700;
}

.mp-closing-modal-close {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: var(--gm-gold-light);
    width: 32px;
    height: 32px;
    border-radius: 6px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.mp-closing-modal-close:hover {
    background: rgba(201, 162, 74, 0.2);
    color: #fff;
}

.mp-closing-modal-body {
    padding: 14px 16px 12px;
    background: linear-gradient(180deg, #fff 0%, var(--gm-gold-pale) 100%);
}

.mp-closing-field {
    display: grid;
    grid-template-columns: 130px 1fr;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.mp-closing-field label {
    margin: 0;
    color: var(--gm-navy);
    font-weight: 600;
    font-size: 13px;
}

.mp-closing-field input {
    width: 100%;
    height: 34px;
    border: 1px solid rgba(17, 41, 75, 0.28);
    border-radius: 8px;
    padding: 0 10px;
    font-size: 13px;
    color: var(--gm-navy);
    background: #fff;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.mp-closing-field input:focus {
    outline: none;
    border-color: var(--gm-gold);
    box-shadow: 0 0 0 2px rgba(201, 162, 74, 0.35);
}

.mp-closing-field input:disabled,
.mp-closing-field input[readonly]:not([type="date"]) {
    background: var(--gm-gold-pale);
    color: var(--gm-navy);
    border-color: rgba(201, 162, 74, 0.45);
    cursor: default;
}

.mp-closing-date-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}

.mp-closing-date-wrap input[type="date"] {
    flex: 1;
    min-width: 0;
}

.mp-closing-date-reset {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border: 1px solid var(--gm-navy);
    border-radius: 8px;
    background: var(--gm-gold-pale);
    color: var(--gm-gold-deep);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
}

.mp-closing-date-reset:hover {
    background: linear-gradient(180deg, #fdf8e8 0%, var(--gm-gold-pale) 100%);
    color: var(--gm-navy);
}

.mp-closing-modal-foot {
    padding: 10px 16px 14px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid rgba(17, 41, 75, 0.12);
    background: #fff;
}

.mp-closing-btn-secondary,
.mp-closing-btn-primary {
    min-width: 88px;
    height: 32px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.mp-closing-btn-secondary {
    border: 1px solid var(--gm-navy);
    background: #fff;
    color: var(--gm-navy);
}

.mp-closing-btn-secondary:hover {
    background: var(--gm-gold-pale);
    border-color: var(--gm-gold-deep);
}

.mp-closing-btn-primary {
    border: 1px solid var(--gm-navy-dark);
    background: linear-gradient(180deg, var(--gm-navy-mid) 0%, var(--gm-navy) 55%, var(--gm-navy-dark) 100%);
    color: var(--gm-gold-light);
}

.mp-closing-btn-primary:hover {
    background: var(--gm-navy);
    color: #fff;
}

.btn-icon-mini {
    width: 28px;
    height: 28px;
    border: 1px solid rgba(17, 41, 75, 0.25);
    background: var(--gm-gold-pale);
    color: var(--gm-navy);
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.process-layout {
    display: grid;
    grid-template-columns: 252px 1fr;
    flex: 1;
    min-height: 0;
}

.dept-panel {
    border-right: 1px solid #d6dbea;
    background: #f7f8fc;
    padding: 8px 8px 0;
}

.dept-list-box {
    border: 1px solid #d6dbea;
    border-radius: 10px;
    overflow: hidden;
    background: #f8f9fd;
    max-height: calc(100vh - 200px);
    display: flex;
    flex-direction: column;
}

.dept-title {
    background: linear-gradient(90deg, var(--gm-navy-dark) 0%, var(--gm-navy) 55%, var(--gm-navy-mid) 100%);
    color: var(--gm-gold-light);
    height: 32px;
    padding: 0 10px;
    display: flex;
    align-items: center;
    font-size: 13px;
    font-weight: 700;
}

.dept-list {
    list-style: none;
    margin: 0;
    padding: 4px 0 8px;
    overflow-y: auto;
    flex: 1;
}

.dept-list li a {
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-decoration: none;
    color: #4c5b7b;
    padding: 5px 8px;
    font-size: 13px;
    font-weight: 600;
}

.dept-list li a:hover {
    background: var(--gm-gold-pale);
}

.dept-list li a .arrow {
    color: #9ca8c3;
    font-size: 12px;
    transition: transform 0.2s ease;
}

.dept-list li.expanded > a .arrow {
    transform: rotate(90deg);
}

.dept-list li a.active {
    background: rgba(201, 162, 74, 0.18);
    color: var(--gm-navy);
}

.dept-user-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: none;
    background: #f0f2fa;
    border-top: 1px solid #e2e6f0;
}

.dept-list li.expanded .dept-user-list {
    display: block;
}

.dept-user-list li a {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    padding: 6px 12px 6px 24px;
    font-size: 12px;
    font-weight: 500;
    color: #5a6a8a;
    text-decoration: none;
    border-bottom: 1px solid #e8ebf2;
}

.dept-user-list li a:hover {
    background: rgba(201, 162, 74, 0.12);
    color: var(--gm-navy);
}

.dept-user-list li a.active {
    background: rgba(17, 41, 75, 0.1);
    color: var(--gm-navy);
    font-weight: 600;
}

.dept-user-list li a i {
    font-size: 14px;
    color: #8a9ab8;
}

.dept-user-list li a:hover i,
.dept-user-list li a.active i {
    color: var(--gm-gold-deep);
}

.no-users-msg {
    padding: 6px 12px 6px 24px;
    font-size: 11px;
    color: #9aa5b8;
    font-style: italic;
}

.grid-area {
    padding: 8px 8px 8px 10px;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

#mpStockSplitWrap {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    width: 100%;
}

#mpStockTableWrap {
    display: none;
    flex: 1;
    min-height: 0;
    flex-direction: column;
    width: 100%;
}

#mpStockTableWrap.is-visible {
    display: flex;
}

.stock-box.stock-box--full {
    flex: 1;
    min-height: 0;
    width: 100%;
}

.stock-split {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 6px;
    height: 100%;
    min-height: 0;
    width: 100%;
    flex: 1;
}

.stock-box {
    border: 1px solid #cfd6e7;
    background: #f8f9fd;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    height: 100%;
}

.stock-head {
    min-height: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 8px;
    border-bottom: 1px solid #d6dbea;
    font-size: 13px;
    font-weight: 700;
    color: var(--gm-navy);
    background: linear-gradient(180deg, var(--gm-gold-pale) 0%, #f0f4f8 100%);
}

.stock-head .mp-stock-balance {
    font-size: 0.85rem;
    font-weight: 600;
    color: #5c6b7a;
    margin-left: 0.35rem;
    white-space: nowrap;
}

.stock-head .mp-view-stock-link {
    margin-left: 0.55rem;
    font-size: 12px;
    font-weight: 600;
    color: var(--gm-navy-mid);
    text-decoration: underline;
    text-underline-offset: 2px;
    cursor: pointer;
    white-space: nowrap;
}

.stock-head .mp-view-stock-link:hover {
    color: var(--gm-gold-deep);
}

.mp-stock-balance-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

#mpViewStockModalOverlay.filter-modal-overlay {
    z-index: 1410;
}

.mp-view-stock-modal {
    width: min(420px, calc(100vw - 32px));
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border: 1px solid var(--gm-navy);
    border-radius: 12px;
    box-shadow: 0 14px 34px rgba(17, 41, 75, 0.28);
}

.mp-view-stock-modal .mp-closing-modal-body {
    padding: 16px 18px 18px;
}

.mp-view-stock-details {
    width: 100%;
    border-collapse: collapse;
}

.mp-view-stock-details tr + tr td {
    border-top: 1px solid rgba(17, 41, 75, 0.1);
}

.mp-view-stock-details td {
    padding: 10px 4px;
    font-size: 13px;
    color: var(--gm-navy);
    vertical-align: middle;
}

.mp-view-stock-details td:first-child {
    font-weight: 600;
    padding-right: 12px;
}

.mp-view-stock-details td:last-child {
    text-align: right;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--gm-navy-dark);
}

.mini-gear {
    color: var(--gm-navy-mid);
    font-size: 12px;
}

.head-setting-btn {
    border: 0;
    background: transparent;
    color: var(--gm-navy-mid);
    padding: 0;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.table-wrap {
    position: relative;
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    min-width: 0;
    min-height: 0;
}

/* Inward / Outward: table scrolls H+V; grand total pinned on stock-box (always reserved, never clipped) */
.stock-box .table-wrap.mp-stock-table-panel {
    flex: 1 1 auto;
    min-height: 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.stock-box .mp-stock-table-scroll {
    flex: 1 1 auto;
    min-height: 0;
    min-width: 0;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
.stock-box > .mp-stock-grand-total-bar {
    flex: 0 0 auto;
    display: none;
    border-top: 2px solid #b8c4d8;
    background: linear-gradient(180deg, #eef2f8 0%, #e4eaf3 100%);
    z-index: 3;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    min-height: 36px;
    box-sizing: border-box;
}
.stock-box > .mp-stock-grand-total-bar.is-visible {
    display: block;
    flex: 0 0 auto;
}
.stock-box > .mp-stock-grand-total-bar::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}
.stock-box .mp-stock-grand-total-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    table-layout: fixed;
    margin: 0;
    font-size: 12px;
}
.stock-box .mp-stock-table-scroll > .table {
    width: max-content;
    min-width: 100%;
}
.stock-box .mp-stock-table-scroll > .table.mp-stock-cols-synced {
    table-layout: fixed;
}
.stock-box .mp-stock-table-scroll > .table td {
    padding: 8px 10px;
    white-space: nowrap;
    box-sizing: border-box;
}
.stock-box .mp-stock-grand-total-table td {
    padding: 8px 10px;
    white-space: nowrap;
    border-right: 1px solid #e8ecf4;
    color: var(--gm-navy);
    background: transparent;
    box-sizing: border-box;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stock-box .mp-stock-grand-total-table td[data-col="queue_no"] {
    font-weight: 700;
}
.stock-box .mp-stock-grand-total-table td:last-child {
    border-right: 0;
}
.stock-box .mp-stock-grand-total-table td.num {
    text-align: right;
}

.table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    margin: 0;
    font-size: 12px;
}

.table thead th {
    background: var(--gm-navy);
    border-right: 1px solid var(--gm-navy-dark);
    border-bottom: 1px solid var(--gm-navy-dark);
    color: var(--gm-gold-light);
    font-weight: 700;
    padding: 8px 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}

.table thead th:last-child {
    border-right: 0;
}

.table th.col-hidden,
.table td.col-hidden {
    display: none !important;
}

.jwq-table th.col-hidden,
.jwq-table td.col-hidden {
    display: none !important;
}

.jwq-modal .columns-panel {
    z-index: 1600;
}
.jwq-modal .columns-panel.jwq-columns-inline {
    z-index: auto;
}
/* Jobwork Queue line columns: compact popover like queue/table settings */
#jwqColumnsPanel.columns-panel {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    margin-top: 6px;
    width: min(320px, calc(100vw - 48px));
    min-width: 260px;
    max-height: min(72vh, 430px);
    z-index: 2060;
    box-sizing: border-box;
}
#jwqColumnsPanel.columns-panel.show {
    display: flex;
    flex-direction: column;
}
#jwqColumnsPanel .columns-search,
#jwqColumnsPanel .columns-panel-header {
    flex-shrink: 0;
}
#jwqColumnsPanel .columns-list {
    max-height: min(56vh, 330px);
    min-height: 150px;
    overflow-x: hidden;
    overflow-y: auto;
    flex: 1 1 auto;
    -webkit-overflow-scrolling: touch;
}

#jwqInwardStockModal .modal-dialog {
    min-width: 700px;
    max-width: min(96vw, 960px);
    width: auto;
    margin: 1.5rem auto;
}

/* Allow column picker to extend outside modal body (Bootstrap scrollable/hidden overflow clips it) */
#jwqInwardStockModal .jwq-inward-stock-modal-content {
    border-radius: 8px;
    overflow: visible;
}

#jwqInwardStockModal .modal-body {
    overflow: visible;
}

#jwqInwardStockModal .modal-dialog {
    overflow: visible;
}

#jwqInwardStockModal #jwqInwardStockColumnsPanel.columns-panel {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    margin-top: 6px;
    width: min(320px, calc(100vw - 48px));
    min-width: 260px;
    max-height: min(75vh, 480px);
    z-index: 2060;
    box-sizing: border-box;
}

#jwqInwardStockModal #jwqInwardStockColumnsPanel.columns-panel.show {
    display: flex;
    flex-direction: column;
}

#jwqInwardStockModal #jwqInwardStockColumnsPanel .columns-search {
    flex-shrink: 0;
}

#jwqInwardStockModal #jwqInwardStockColumnsPanel .columns-panel-header {
    flex-shrink: 0;
}

#jwqInwardStockModal #jwqInwardStockColumnsPanel .columns-list {
    max-height: min(58vh, 380px);
    min-height: 160px;
    overflow-x: hidden;
    overflow-y: auto;
    flex: 1 1 auto;
    -webkit-overflow-scrolling: touch;
}

#jwqInwardStockModal .columns-panel {
    z-index: 2060;
}

.jwq-inward-stock-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
    position: relative;
    z-index: 5;
}

.jwq-inward-stock-tool {
    border: none;
    border-radius: 6px;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.jwq-inward-stock-tool:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(17, 41, 75, 0.2);
}

.jwq-inward-stock-tool--excel {
    background: #217346;
    color: #fff;
}

.jwq-inward-stock-tool--pdf {
    background: #c62828;
    color: #fff;
}

.jwq-inward-stock-tool--columns {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.jwq-inward-stock-tool--columns .mini-gear {
    font-size: 16px;
}

.jwq-lines-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-bottom: 6px;
    position: relative;
    overflow: visible;
}

.empty-center {
    position: absolute;
    inset: 42px 0 0 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8a94a8;
    font-size: 22px;
    font-weight: 600;
}

.mp-user-workspace {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    gap: 8px;
}

.dept-list li.mp-all-row > a {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: var(--gm-navy);
}

.dept-list li.mp-all-row > a.active {
    background: rgba(201, 162, 74, 0.2);
    color: var(--gm-navy);
}

.mp-user-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 6px 4px 12px;
    border-bottom: 1px solid #e4e8f2;
    width: 100%;
    box-sizing: border-box;
}

.mp-user-strip-main {
    flex: 0 1 auto;
    min-width: 0;
}

.mp-user-strip h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: var(--gm-gold);
    letter-spacing: 0.02em;
    text-transform: uppercase;
    line-height: 1.15;
    text-shadow: 0 1px 0 rgba(17, 41, 75, 0.12);
}

.mp-user-strip-sub {
    display: block;
    margin: 4px 0 0;
    font-size: 13px;
    color: #5c6b8a;
    font-weight: 600;
    line-height: 1.3;
}

.mp-tag-search {
    height: 30px;
    border: 1px solid #c8d0e2;
    border-radius: 8px;
    padding: 0 12px;
    min-width: 200px;
    font-size: 13px;
    color: #2f3d5b;
    background: #fff;
}

.mp-view-panel {
    display: none;
    flex: 1;
    min-height: 0;
    flex-direction: column;
}

.mp-view-panel.is-active {
    display: flex;
}

.mp-job-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 4px 2px 16px;
    align-content: start;
}

/* One wrapper per cell: avoids grid+flex fragmentation where the image area and body render as separate grid items in some browsers. */
.mp-job-cards > .mp-job-card-grid-item {
    align-self: start;
    width: 100%;
    min-width: 0;
}

.mp-job-card {
    border: 1px solid #cfd6e7;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(31, 41, 55, 0.08);
    display: flex;
    flex-direction: column;
    width: 100%;
    min-height: auto;
    box-sizing: border-box;
    overflow: hidden;
}

.mp-job-card .status-pill {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(255, 255, 255, 0.96);
    color: var(--gm-navy);
    border: 1px solid var(--gm-gold);
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    z-index: 2;
    letter-spacing: 0.02em;
}

.mp-job-card-select-wrap {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 3;
    margin: 0;
    cursor: pointer;
}
.mp-job-card-select-wrap input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--gm-gold);
}
.mp-job-card.is-bulk-selected {
    border-color: var(--gm-gold);
    box-shadow: 0 0 0 2px rgba(197, 168, 100, 0.35);
}
.mp-bulk-select-all-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gm-navy);
    margin-right: 4px;
    cursor: pointer;
    user-select: none;
}
.mp-bulk-select-all-wrap input { accent-color: var(--gm-gold); cursor: pointer; }
#mpBulkTransferBtn:disabled { opacity: 0.55; cursor: not-allowed; }
.mp-bulk-transfer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    z-index: 10950;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.mp-bulk-transfer-overlay.is-open { display: flex; }
.mp-bulk-transfer-dialog {
    background: #fff;
    border-radius: 10px;
    width: min(520px, 96vw);
    max-height: 88vh;
    overflow: auto;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    padding: 20px;
}
.mp-bulk-transfer-dialog h3 {
    margin: 0 0 8px;
    font-size: 1.05rem;
    color: #11294b;
    font-weight: 700;
}
.mp-bulk-transfer-summary {
    margin: 0 0 12px;
    font-size: 0.88rem;
    color: #64748b;
}
.mp-bulk-transfer-list {
    list-style: none;
    margin: 0 0 16px;
    padding: 0;
    max-height: 180px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}
.mp-bulk-transfer-list li {
    padding: 8px 12px;
    font-size: 0.82rem;
    color: #1e293b;
    border-bottom: 1px solid #e2e8f0;
}
.mp-bulk-transfer-list li:last-child { border-bottom: none; }
.mp-bulk-transfer-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
.mp-bulk-transfer-fields label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}
.mp-bulk-transfer-fields select {
    width: 100%;
    font-size: 0.85rem;
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}
.mp-bulk-transfer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.mp-job-card .card-visual-wrap {
    position: relative;
    background: #eef1f7;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #e4e8f2;
    border-radius: 12px 12px 0 0;
    overflow: hidden;
    aspect-ratio: 1 / 1;
    min-height: 168px;
    max-height: 220px;
}

.mp-job-card .card-visual-wrap .ph-inner {
    text-align: center;
    padding: 12px;
}

.mp-job-card .card-visual-wrap .ph-inner.ph-inner--photo {
    padding: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mp-job-card .card-visual-wrap .mp-jwo-card-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
}

.mp-job-card .card-visual-wrap .ph-icon {
    font-size: 48px;
    color: #b0b8cc;
    line-height: 1;
}

.mp-job-card .card-visual-wrap .ph-text {
    margin-top: 10px;
    font-size: 12px;
    font-weight: 600;
    color: #8a94a8;
    line-height: 1.35;
}

.mp-job-card .card-body-pad {
    padding: 11px 12px 18px;
    flex: 0 0 auto;
    flex-shrink: 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
    font-size: 13px;
    line-height: 1.4;
}

.mp-job-card .ref-line {
    font-size: 13px;
    margin-bottom: 8px;
    line-height: 1.35;
}

.mp-job-card .ref-line .ref-barcode {
    color: #4f46e5;
    font-weight: 700;
    margin-right: 8px;
    cursor: pointer;
    text-decoration: none;
    border: 0;
    background: transparent;
    padding: 0;
    font: inherit;
    text-align: left;
}
.mp-job-card .ref-line .ref-barcode:hover {
    text-decoration: underline;
}

.mp-job-card .ref-line .ref-blue {
    color: var(--gm-navy);
    font-weight: 700;
}

.mp-job-card .ref-line .ref-muted {
    color: #263238;
    font-weight: 600;
}

.mp-job-card .names .n1 {
    font-weight: 700;
    color: #212121;
    font-size: 14px;
    line-height: 1.35;
}

.mp-job-card .names .mp-job-wt-row {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #212121;
    line-height: 1.35;
}

.mp-job-card .names .mp-job-wt-row .mp-total-wt-num {
    flex: 1;
    min-width: 0;
}

.mp-job-card .names .mp-job-wt-row .mp-wt-secondary {
    flex-shrink: 0;
    font-weight: 700;
}

.mp-job-card .names .mp-name-meta {
    font-size: 13px;
    color: #263238;
    margin-top: 4px;
    font-weight: 600;
}

.mp-job-card .names .mp-name-meta .mp-na-purple {
    color: var(--gm-gold-deep);
    margin-left: 6px;
}

.mp-job-card .names .n2 {
    font-size: 12px;
    color: #5c6b7a;
    margin-top: 4px;
    font-weight: 500;
}

.mp-dept-banner.mp-timer-bar {
    margin-top: 11px;
    background: linear-gradient(180deg, var(--gm-navy-mid) 0%, var(--gm-navy) 45%, var(--gm-navy-dark) 100%);
    color: #fff;
    border-radius: 6px;
    padding: 10px 12px;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-sizing: border-box;
    min-height: 56px;
    border: 1px solid rgba(201, 162, 74, 0.35);
    box-shadow: inset 0 1px 0 rgba(232, 212, 138, 0.12);
}

.mp-timer-left {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 5px;
    min-width: 0;
    flex: 1;
}

.mp-dept-banner-name {
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.1em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
    line-height: 1.2;
}

.mp-timer-display {
    font-weight: 700;
    font-size: 15px;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.06em;
    line-height: 1.15;
    color: #fff;
    white-space: nowrap;
}

.mp-timer-toggle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 0;
    background: #fff;
    color: var(--gm-navy);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.mp-timer-toggle:hover {
    background: var(--gm-gold-pale);
}

.mp-timer-toggle i {
    font-size: 14px;
}

.mp-job-meta {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0;
    margin-top: 12px;
    font-size: 12px;
    flex-shrink: 0;
    min-width: 0;
    border-top: 1px solid #e8ecf2;
}

.mp-job-meta > div {
    padding: 10px 8px;
    border-left: 1px solid #e0e4eb;
    text-align: center;
}

.mp-job-meta > div:first-child {
    border-left: 0;
    padding-left: 2px;
}

.mp-job-meta .lbl {
    color: #7a8699;
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 600;
}

.mp-job-meta .val {
    color: #263238;
    font-weight: 700;
    font-size: 13px;
}

.mp-job-meta .val.priority-high {
    color: var(--gm-gold-deep);
    font-weight: 800;
}

.mp-job-actions {
    margin-top: 12px;
    padding-top: 10px;
    padding-bottom: 4px;
    border-top: 1px solid #eef0f6;
    flex-shrink: 0;
}

.mp-job-actions-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 5px;
    align-items: center;
}

.mp-job-actions-row button,
.mp-job-actions-row a.mp-act-outline,
.mp-job-actions-row a.mp-act-blue,
.mp-job-actions-row a.mp-act-red {
    width: 30px;
    height: 30px;
    border-radius: 5px;
    background: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    flex-shrink: 0;
    box-sizing: border-box;
}

.mp-job-actions-row .mp-act-locked,
.mp-job-actions-row a.mp-act-locked,
.mp-job-actions-row button.mp-act-locked {
    opacity: 0.4;
    cursor: not-allowed !important;
    pointer-events: none;
    filter: grayscale(0.35);
}

.mp-job-actions-row button i,
.mp-job-actions-row a i {
    font-size: 14px;
}

.mp-act-outline {
    border: 1px solid rgba(17, 41, 75, 0.35);
    color: var(--gm-navy);
}

.mp-act-outline:hover {
    background: rgba(201, 162, 74, 0.12);
}

.mp-act-blue {
    border: 1px solid var(--gm-gold);
    color: var(--gm-gold-deep);
}

.mp-act-blue:hover {
    background: rgba(201, 162, 74, 0.15);
}

.mp-act-red {
    border: 1px solid #e57373;
    color: #c62828;
}

.mp-act-red:hover {
    background: rgba(229, 115, 115, 0.08);
}

.mp-action-with-tip {
    position: relative;
    display: inline-flex;
    vertical-align: top;
}

.mp-tip-bubble {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    padding: 5px 10px;
    background: var(--gm-gold-pale);
    color: var(--gm-navy);
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.15s ease, visibility 0.15s ease;
    z-index: 20;
    box-shadow: 0 2px 6px rgba(17, 41, 75, 0.12);
    border: 1px solid rgba(201, 162, 74, 0.4);
}

.mp-tip-bubble::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -6px;
    border: 6px solid transparent;
    border-top-color: var(--gm-gold-pale);
}

.mp-action-with-tip:hover .mp-tip-bubble {
    opacity: 1;
    visibility: visible;
}

.mp-tip-bubble--start {
    left: 0;
    transform: none;
}

.mp-tip-bubble--start::after {
    left: 14px;
    margin-left: 0;
}

.mp-tip-bubble--end {
    left: auto;
    right: 0;
    transform: none;
}

.mp-tip-bubble--end::after {
    left: auto;
    right: 14px;
    margin-left: 0;
}

.mp-job-actions-row > .mp-action-with-tip {
    flex-shrink: 0;
}

.mp-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 4px 0;
    font-size: 12px;
    color: #6a7690;
}

.mp-pagination-bar select {
    height: 28px;
    border-radius: 6px;
    border: 1px solid #c8d0e2;
    font-size: 12px;
    padding: 0 8px;
}

.btn-icon-mini.is-active {
    background: var(--gm-navy);
    color: var(--gm-gold-light);
    border-color: var(--gm-navy);
}

.table-footer-bar {
    height: 20px;
    border-top: 1px solid #d6dbea;
    background: var(--gm-gold-pale);
    position: relative;
}

.scroll-mock {
    position: absolute;
    left: 22px;
    right: 28px;
    top: 4px;
    height: 12px;
    border-radius: 8px;
    background: linear-gradient(90deg, rgba(201, 162, 74, 0.45) 0%, rgba(17, 41, 75, 0.25) 100%);
}

.columns-panel {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    background: linear-gradient(180deg, #fff 0%, var(--gm-gold-pale) 100%);
    border: 1px solid rgba(17, 41, 75, 0.15);
    border-radius: 6px;
    z-index: 1200;
    display: none;
    box-shadow: 0 6px 20px rgba(31, 41, 55, 0.18);
}

.columns-panel.show {
    display: block;
}

.columns-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    border-bottom: 1px solid #ccd4e4;
    font-size: 12px;
    font-weight: 700;
    color: #4c5a7a;
}

.columns-panel-header .icons {
    display: inline-flex;
    gap: 5px;
    align-items: center;
}

.columns-panel-header .icons .tag {
    font-size: 10px;
    border: 1px solid #c7d1e5;
    background: #fff;
    padding: 1px 4px;
    border-radius: 3px;
}

.columns-panel-close {
    border: 0;
    background: transparent;
    color: #7786a8;
    font-size: 16px;
    line-height: 1;
    padding: 0 2px;
}

.columns-search {
    padding: 6px 8px 4px;
}

.columns-search input {
    width: 100%;
    height: 24px;
    border: 1px solid #c8d0e2;
    border-radius: 5px;
    padding: 0 8px;
    font-size: 12px;
}

.columns-list {
    max-height: 220px;
    overflow: auto;
    padding: 2px 8px 8px;
}

.columns-list label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
    padding: 3px 0;
    font-size: 13px;
    color: #2f3d5b;
    font-weight: 500;
}

.columns-list input[type="checkbox"] {
    width: 14px;
    height: 14px;
}

.columns-panel.jwq-columns-inline {
    position: static;
    left: auto;
    top: auto;
    width: 100%;
    max-width: none;
    z-index: auto;
    box-shadow: none;
    margin-bottom: 10px;
    box-sizing: border-box;
}
.columns-panel.jwq-columns-inline .columns-list.jwq-columns-list--table {
    max-height: min(50vh, 320px);
    overflow: auto;
    padding: 0;
}
.columns-panel.jwq-columns-inline .jwq-column-pref-table {
    font-size: 13px;
    margin: 0;
    background: #fff;
}
.columns-panel.jwq-columns-inline .jwq-column-pref-table thead th {
    background: #eef2f8;
    color: #334155;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 1;
}

/* Advance Filter + .mp-ms: assets/css/advance-filter-global.css (loaded in header-script.php) */

/* Jobwork Queue modal (Add / Update on job card) */
.jwq-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(27, 35, 48, 0.45);
    z-index: 1500;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 12px;
}
.jwq-modal-overlay.show {
    display: flex;
}
.jwq-modal {
    width: min(1180px, calc(100vw - 24px));
    max-height: calc(100vh - 24px);
    display: flex;
    flex-direction: column;
    background: #f4f6fb;
    border: 1px solid #c5ccdb;
    border-radius: 10px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
    overflow: hidden;
}
.jwq-modal-head {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: linear-gradient(180deg, #fff 0%, #f0f3fa 100%);
    border-bottom: 1px solid #d6dbea;
}
.jwq-modal-title-wrap {
    font-size: 15px;
    font-weight: 700;
    color: #1e2b4a;
}
.jwq-modal-title-wrap strong {
    color: #11294b;
}
.jwq-modal-head-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.jwq-btn-text {
    border: 0;
    background: transparent;
    color: var(--gm-navy-mid);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 8px;
    cursor: pointer;
}
.jwq-btn-text:hover {
    text-decoration: underline;
    color: var(--gm-gold-deep);
}
.jwq-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--gm-navy);
    background: var(--gm-navy);
    color: var(--gm-gold-light);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
}
.jwq-btn-save:hover {
    opacity: 0.94;
}
.jwq-modal-close {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 22px;
    line-height: 1;
    padding: 4px 8px;
    cursor: pointer;
    border-radius: 6px;
}
.jwq-modal-close:hover {
    background: #e8ecf4;
    color: #0f172a;
}
.jwq-modal-body {
    padding: 12px 14px 14px;
    overflow: auto;
    flex: 1;
    min-height: 0;
}
.jwq-transfer-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto;
    gap: 10px 12px;
    align-items: end;
    margin-bottom: 12px;
}
@media (max-width: 992px) {
    .jwq-transfer-row {
        grid-template-columns: 1fr;
    }
    .jwq-arrows {
        flex-direction: row;
        justify-content: center;
        padding: 4px 0;
    }
}
.jwq-from-block,
.jwq-to-block {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 10px;
    background: #fff;
    border: 1px solid #d8deeb;
    border-radius: 8px;
    padding: 10px;
}
.jwq-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.jwq-field label {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.jwq-field select,
.jwq-field input {
    height: 32px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    padding: 0 8px;
    font-size: 13px;
    color: #1e293b;
    background: #fff;
}
.jwq-user-with-icons {
    display: flex;
    align-items: center;
    gap: 4px;
}
.jwq-user-with-icons select {
    flex: 1;
    min-width: 0;
}
.jwq-icon-btn {
    width: 30px;
    height: 30px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    background: #f8fafc;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    cursor: pointer;
}
.jwq-icon-btn:hover {
    background: #eef2f8;
    color: #11294b;
}
.jwq-arrows {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #94a3b8;
    padding: 0 4px;
}
.jwq-arrows i {
    font-size: 22px;
}
.jwq-datetime-block {
    background: #fff;
    border: 1px solid #d8deeb;
    border-radius: 8px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 160px;
}
.jwq-datetime-block .jwq-field {
    flex-direction: row;
    align-items: center;
    gap: 8px;
}
.jwq-datetime-block .jwq-field label {
    min-width: 38px;
}
.jwq-time-spent {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    padding: 6px 8px;
    background: #f1f5f9;
    border-radius: 6px;
    border: 1px dashed #cbd5e1;
}
.jwq-tag-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.jwq-tag-row input[type="text"] {
    flex: 1;
    min-width: 160px;
    height: 34px;
    border: 1px solid #c8d0e2;
    border-radius: 8px;
    padding: 0 10px 0 34px;
    font-size: 13px;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M3 7V5a2 2 0 012-2h2'/%3E%3Cpath d='M17 3h2a2 2 0 012 2v2'/%3E%3Cpath d='M21 17v2a2 2 0 01-2 2h-2'/%3E%3Cpath d='M7 21H5a2 2 0 01-2-2v-2'/%3E%3C/svg%3E") no-repeat 10px center;
}
.jwq-tag-row .jwq-pill-btn {
    height: 32px;
    padding: 0 14px;
    border-radius: 6px;
    border: 1px solid #11294b;
    background: #fff;
    color: #11294b;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.jwq-tag-row .jwq-pill-btn:hover {
    background: #11294b;
    color: #fff;
}
.jwq-table-wrap {
    border: 1px solid #d8deeb;
    border-radius: 8px;
    overflow: auto;
    max-height: 200px;
    background: #fff;
    margin-bottom: 10px;
}
.jwq-order-lines-total-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    margin-bottom: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #334155;
}
.jwq-order-lines-total-bar strong {
    color: #11294b;
    font-size: 14px;
}
.jwq-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.jwq-table th {
    position: sticky;
    top: 0;
    background: #e8ecf4;
    color: #334155;
    font-weight: 700;
    text-align: left;
    padding: 8px 10px 8px 10px;
    padding-right: 18px;
    border-bottom: 1px solid #d8deeb;
    border-right: 1px solid #c8d4e8;
    white-space: nowrap;
}
#jwqOrderLinesTable.acr-col-table thead th .acr-th-drag {
    color: #64748b;
}
#jwqOrderLinesTable.acr-col-table thead th .acr-th-drag:hover {
    color: #c9a962;
}

.jwq-table th[data-col="tag_no"],
.jwq-table td[data-col="tag_no"] {
    min-width: 130px;
    width: 130px;
}

.jwq-table th[data-col="description"],
.jwq-table td[data-col="description"] {
    min-width: 220px;
    width: 220px;
}
.jwq-table th:last-child {
    border-right: 0;
}
.jwq-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #eef1f7;
    border-right: 1px solid #e8ecf4;
    color: #1e293b;
}

.jwq-table td .jwq-cell-input {
    width: 100%;
    min-width: 88px;
    max-width: 140px;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
    line-height: 1.25;
    background: #fff;
    color: #0f172a;
    box-sizing: border-box;
}
.jwq-table td .jwq-cell-input--decimal {
    min-width: 96px;
    max-width: 150px;
}
.jwq-table td[data-col="total_wt"],
.jwq-table td[data-col="metal_wt"],
.jwq-table td[data-col="diamond_wt"],
.jwq-table td[data-col="total_purity"],
.jwq-table td[data-col="total_qty"],
.jwq-table td[data-col="loss"],
.jwq-table td[data-col="price"] {
    min-width: 108px;
}

.jwq-table td .jwq-cell-input:focus {
    outline: none;
    border-color: #7aa2ff;
    box-shadow: 0 0 0 2px rgba(122, 162, 255, 0.18);
}

.jwq-table td .jwq-cell-input--readonly,
.jwq-table td .jwq-cell-input[readonly] {
    background: #f1f5f9;
    color: #334155;
    cursor: default;
    border-color: #e2e8f0;
}
.jwq-table td .jwq-cell-input--readonly:focus,
.jwq-table td .jwq-cell-input[readonly]:focus {
    border-color: #e2e8f0;
    box-shadow: none;
}
.jwq-table td:last-child {
    border-right: 0;
}
.jwq-table tr:last-child td {
    border-bottom: 0;
}
.jwq-bottom-split {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 12px;
    align-items: start;
}
@media (max-width: 900px) {
    .jwq-bottom-split {
        grid-template-columns: 1fr;
    }
}
.jwq-material-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
/* Sale-invoice style payment icons (Jobwork Queue) */
.jwq-payment-icons-wrap {
    width: 100%;
}
.jwq-payment-icons-wrap .payment-icons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0;
    margin-bottom: 0;
}
.jwq-payment-icons-wrap .payment-icons .payment-icon {
    margin-right: 0.3rem;
    margin-bottom: 0.25rem;
}
.jwq-payment-icons-wrap .payment-icon {
    width: 45px;
    height: 45px;
    border: 1.5px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    color: #11294b;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}
.jwq-payment-icons-wrap .payment-icon::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.3) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.jwq-payment-icons-wrap .payment-icon:hover::before {
    opacity: 1;
}
.jwq-payment-icons-wrap .payment-exchange:hover,
.jwq-payment-icons-wrap .payment-jewelry:hover,
.jwq-payment-icons-wrap .payment-diamond:hover,
.jwq-payment-icons-wrap .payment-stone:hover,
.jwq-payment-icons-wrap .payment-other:hover {
    background: #11294b;
    border-color: #c5a864;
    color: white;
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 4px 12px #c5a864;
}

/* Bootstrap payment modals above Jobwork Queue overlay (1500) */
.manufacturing-process-page .modal {
    z-index: 2000 !important;
}

#jwqInwardStockModal.modal {
    overflow: visible;
}
.manufacturing-process-page .modal-backdrop {
    z-index: 1990 !important;
}
.jwq-mat-table-wrap {
    border: 1px solid #d8deeb;
    border-radius: 8px;
    overflow: auto;
    background: #fff;
    max-height: 160px;
}
.jwq-mat-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}
.jwq-mat-table th {
    background: #f1f5f9;
    padding: 6px 8px;
    text-align: left;
    font-weight: 700;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}
.jwq-mat-table td {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
}
.jwq-mat-empty {
    text-align: center;
    color: #94a3b8;
    padding: 20px;
    font-size: 13px;
}
.jwq-payment-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
}
.jwq-payment-row input {
    flex: 1;
    height: 32px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    padding: 0 8px;
    font-size: 13px;
}
.jwq-comment-row {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}
.jwq-comment-row input {
    flex: 1;
    height: 34px;
    border: 1px solid #c8d0e2;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 13px;
}
.jwq-comment-row button {
    width: 36px;
    height: 34px;
    border-radius: 6px;
    border: 1px solid #11294b;
    background: #11294b;
    color: #fff;
    cursor: pointer;
}
.jwq-images-box {
    border: 2px dashed #c8d0e2;
    border-radius: 10px;
    min-height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #fafbfe;
    color: #64748b;
    cursor: pointer;
    padding: 16px;
}
.jwq-images-box:hover {
    border-color: #11294b;
    color: #11294b;
    background: #f0f4ff;
}
.jwq-images-box i {
    font-size: 42px;
    opacity: 0.7;
}
.jwq-images-box span {
    font-size: 13px;
    font-weight: 600;
}

@media (max-width: 1200px) {
    .process-layout {
        grid-template-columns: 1fr;
    }
    .dept-panel {
        border-right: 0;
        border-bottom: 1px solid #d6dbea;
    }
    .stock-split {
        grid-template-columns: 1fr;
    }
}

/* Job card print — right drawer */
.mp-jcp-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.38);
    z-index: 1055;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}
.mp-jcp-backdrop.show {
    opacity: 1;
    visibility: visible;
}
.mp-jcp-drawer {
    position: fixed;
    top: 0;
    right: 0;
    width: min(1080px, 100vw);
    height: 100vh;
    max-height: 100vh;
    background: #f8fafc;
    z-index: 1060;
    box-shadow: -12px 0 40px rgba(15, 23, 42, 0.18);
    transform: translateX(100%);
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    font-size: 13px;
    color: #1e293b;
}
.mp-jcp-drawer.open {
    transform: translateX(0);
}
.mp-jcp-drawer-head {
    flex: 0 0 auto;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 18px 12px;
}
.mp-jcp-drawer-head-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.mp-jcp-drawer-head-top h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}
.mp-jcp-drawer-close {
    border: none;
    background: #f1f5f9;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
    color: #475569;
}
.mp-jcp-drawer-close:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.mp-jcp-drawer-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.mp-jcp-drawer-toolbar label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #334155;
    margin: 0;
}
.mp-jcp-drawer-toolbar input[type="text"] {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 10px;
    min-width: 140px;
    font-size: 13px;
}
.mp-jcp-btn-print {
    background: linear-gradient(180deg, #4f46e5 0%, #4338ca 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 18px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
}
.mp-jcp-btn-print:hover {
    filter: brightness(1.05);
}
.mp-jcp-btn-export {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 14px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
    color: #334155;
}
.mp-jcp-btn-export:hover {
    background: #f8fafc;
}
.mp-jcp-drawer-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    padding: 16px 18px 24px;
    display: flex;
    flex-direction: column;
}
.mp-jcp-print-grid {
    flex: 1 1 auto;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
    gap: 18px;
    align-items: stretch;
}
@media (max-width: 900px) {
    .mp-jcp-print-grid {
        grid-template-columns: 1fr;
    }
}
.mp-jcp-side {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    min-height: 0;
    overflow-y: auto;
}
.mp-jcp-side h3 {
    margin: 0 0 12px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
}
.mp-jcp-side-dl {
    margin: 0 0 14px;
    font-size: 13px;
}
.mp-jcp-side-dl div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px dashed #e2e8f0;
}
.mp-jcp-side-dl span:first-child {
    color: #64748b;
    font-weight: 600;
}
.mp-jcp-side-dl span:last-child {
    font-weight: 600;
    color: #0f172a;
    text-align: right;
}
.mp-jcp-barcode-wrap {
    text-align: center;
    margin: 12px 0;
    padding: 10px 8px;
    background: #fafafa;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.mp-jcp-barcode-wrap .mp-jcp-barcode-label {
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.02em;
    margin-bottom: 6px;
    color: #312e81;
}
.mp-jcp-barcode-wrap svg {
    max-width: 100%;
    height: auto;
}
.mp-jcp-time-box {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    padding: 12px;
    border: 1px solid #fbcfe8;
    border-radius: 8px;
    background: #fdf2f8;
    font-weight: 700;
    color: #9d174d;
}
.mp-jcp-time-box i {
    font-size: 22px;
    opacity: 0.85;
}
.mp-jcp-images {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
}
.mp-jcp-images strong {
    display: block;
    margin-bottom: 8px;
    color: #334155;
}
.mp-jcp-images img {
    max-width: 100%;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.mp-jcp-images .mp-jcp-img-empty {
    color: #94a3b8;
    font-style: italic;
    font-size: 12px;
}
.mp-jcp-main {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.mp-jcp-main h3 {
    margin: 0 0 10px;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}
.mp-jcp-main h3 + .table-responsive {
    margin-bottom: 20px;
}
.mp-jcp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.mp-jcp-table th,
.mp-jcp-table td {
    border: 1px solid #e2e8f0;
    padding: 8px 7px;
    text-align: left;
    vertical-align: middle;
}
.mp-jcp-table th {
    background: #f1f5f9;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
}
.mp-jcp-table td.num,
.mp-jcp-table th.num {
    text-align: right;
}
.mp-jcp-table tbody tr:nth-child(even) {
    background: #fafbfc;
}
.mp-jcp-table tfoot td {
    font-weight: 700;
    background: #eef2ff;
}
.mp-jcp-table .mp-jcp-desc-link {
    color: #2563eb;
    font-weight: 600;
    cursor: default;
}
.mp-jcp-table .mp-jcp-dept-flow-txt {
    color: #4c1d95;
    font-weight: 600;
    white-space: nowrap;
}
.mp-jcp-summary-table tfoot td {
    background: #ecfdf5;
}
.mp-jcp-table th.col-hidden,
.mp-jcp-table td.col-hidden {
    display: none;
}
.mp-jcp-table-block {
    position: relative;
    margin-bottom: 0;
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#mpJobCardPrintDrawer .mp-jcp-main > .mp-jcp-table-block:first-of-type {
    flex: 3 1 0;
}
#mpJobCardPrintDrawer .mp-jcp-main > .mp-jcp-table-block:last-of-type {
    flex: 2 1 0;
}
.mp-jcp-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
    flex: 0 0 auto;
    position: relative;
    z-index: 4;
}
.mp-jcp-section-head h3 {
    margin: 0;
}
.mp-jcp-table-scroll {
    flex: 1 1 auto;
    min-height: 0;
    max-height: none;
    overflow: auto;
    overscroll-behavior: contain;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    -webkit-overflow-scrolling: touch;
}
.mp-jcp-table-scroll--summary {
    flex: 1 1 0;
    min-height: 120px;
}
#mpJobCardPrintDrawer .table-responsive.mp-jcp-table-scroll {
    overflow: auto !important;
}
.mp-jcp-table-scroll .mp-jcp-table {
    width: max-content;
    min-width: 100%;
    margin-bottom: 0;
    table-layout: auto;
}
.mp-jcp-table-scroll .mp-jcp-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    box-shadow: 0 1px 0 #e2e8f0;
}
/* Job card print: drawer uses transform; overflow:hidden ancestors clip fixed popovers — use absolute + overflow:visible chain */
#mpJobCardPrintDrawer .mp-jcp-drawer-body,
#mpJobCardPrintDrawer .mp-jcp-print-grid,
#mpJobCardPrintDrawer .mp-jcp-main,
#mpJobCardPrintDrawer .mp-jcp-table-block,
#mpJobCardPrintDrawer .mp-jcp-section-head {
    overflow: visible;
}
/* Job card print: compact column picker under gear icon (anchored in .mp-jcp-section-head) */
#mpJobCardPrintDrawer .columns-panel.mp-jcp-columns-popover {
    width: min(280px, calc(100vw - 24px));
    max-width: 280px;
    border-radius: 8px;
    z-index: 1080;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.18);
}
#mpJobCardPrintDrawer .columns-panel.mp-jcp-columns-popover .columns-list {
    max-height: min(280px, 42vh);
    overflow-x: hidden;
    overflow-y: auto;
}
/* tfoot not sticky: sticky bottom pinned totals mid-scroll; keep totals after all tbody rows */
/* Formal job card — screen: hidden; only used for window.print() */
.mp-jcp-print-sheet {
    display: none;
    position: absolute;
    left: -99999px;
    top: 0;
    width: 210mm;
    max-width: 100%;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #000;
    background: #fff;
}
.mp-jcp-print-sheet .jcp-doc {
    padding: 0;
}
.mp-jcp-print-sheet .jcp-h1 {
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    margin: 0 0 10px;
    letter-spacing: 0.02em;
}
.mp-jcp-print-sheet table.jcp-head {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 0;
}
.mp-jcp-print-sheet table.jcp-head td,
.mp-jcp-print-sheet table.jcp-data td,
.mp-jcp-print-sheet table.jcp-data th {
    border: 1px solid #000;
    padding: 6px 8px;
    vertical-align: middle;
}
.mp-jcp-print-sheet table.jcp-head .jcp-photo {
    width: 22%;
    height: 110px;
    text-align: center;
    vertical-align: middle;
    background: #fafafa;
}
.mp-jcp-print-sheet table.jcp-head .jcp-photo img {
    max-width: 100%;
    max-height: 100px;
    object-fit: contain;
}
.mp-jcp-print-sheet table.jcp-head .jcp-lbl {
    width: 14%;
    font-weight: 700;
    background: #f8fafc;
}
.mp-jcp-print-sheet table.jcp-head .jcp-bc {
    width: 24%;
    text-align: center;
    vertical-align: middle;
}
.mp-jcp-print-sheet table.jcp-head .jcp-tag-num {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 6px;
}
.mp-jcp-print-sheet table.jcp-head .jcp-bc svg {
    max-width: 100%;
    height:44px;
}
.mp-jcp-print-sheet .jcp-desc-bar td {
    font-weight: 600;
}
.mp-jcp-print-sheet .jcp-thumbs {
    display: flex;
    gap: 8px;
    margin: 10px 0 12px;
}
.mp-jcp-print-sheet .jcp-thumbs .jcp-thumb {
    flex: 1;
    min-height: 72px;
    border: 1px solid #000;
    background: #fff;
}
.mp-jcp-print-sheet table.jcp-data {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
}
.mp-jcp-print-sheet table.jcp-data thead th {
    background: #d9edf7;
    font-weight: 700;
    text-align: left;
    font-size: 10px;
}
.mp-jcp-print-sheet table.jcp-data .num {
    text-align: right;
}
.mp-jcp-print-sheet table.jcp-data td.jcp-flow {
    color: #4c1d95;
    font-weight: 600;
    white-space: normal;
    word-break: break-word;
    max-width: 220px;
}
.mp-jcp-print-sheet .jcp-sigs {
    display: table;
    width: 100%;
    margin-top: 20px;
    table-layout: fixed;
}
.mp-jcp-print-sheet .jcp-sigs > div {
    display: table-cell;
    text-align: center;
    padding: 8px 12px;
    font-size: 11px;
}
.mp-jcp-print-sheet .jcp-sigs .jcp-line {
    border-bottom: 1px solid #000;
    margin: 24px 8px 4px;
    min-height: 1px;
}
@media print {
    @page {
        margin: 10mm;
        size: A4;
    }
    body * {
        visibility: hidden !important;
    }
    #mpJcpPrintSheet,
    #mpJcpPrintSheet * {
        visibility: visible !important;
    }
    #mpJcpPrintSheet {
        display: block !important;
        position: absolute;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        background: #fff !important;
    }
    .mp-jcp-backdrop,
    .mp-jcp-drawer,
    .layout-content,
    .dept-panel,
    .sidebar,
    .sidenav,
    .layout-navbar,
    .layout-footer {
        display: none !important;
    }
}
</style>

<body class="mfg-page manufacturing-process-page<?php echo $mp_manufacturing_mode === 'outsource' ? ' manufacturing-outsource-page' : ''; ?>">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
        <div class="process-shell">
            <div class="process-layout">
                <aside class="dept-panel">
                    <div class="dept-list-box">
                        <div class="dept-title"><?php echo htmlspecialchars($mp_dept_panel_title, ENT_QUOTES, 'UTF-8'); ?></div>
                        <ul class="dept-list">
                            <li class="mp-all-row">
                                <a href="javascript:void(0);" class="mp-all-link active" onclick="showAllManufacturing(event, this)">
                                    <i class="feather icon-grid"></i>
                                    <span><?php echo htmlspecialchars($mp_all_jobs_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                            <?php if ($departments === []): ?>
                            <li class="no-users-msg px-2 py-1">
                                <?php echo $mp_manufacturing_mode === 'outsource'
                                    ? 'No Manufacturing Outsource departments. Set Process to Manufacturing Outsource in Department master.'
                                    : 'No departments found.'; ?>
                            </li>
                            <?php endif; ?>
                            <?php foreach ($departments as $d): 
                                $dept_id = (int)$d['id'];
                                $users = isset($department_users[$dept_id]) ? $department_users[$dept_id] : [];
                            ?>
                                <li data-dept-id="<?php echo $dept_id; ?>">
                                    <a href="javascript:void(0);" onclick="toggleDepartment(this, <?php echo $dept_id; ?>)">
                                        <span><?php echo htmlspecialchars($d['dept_name']); ?></span>
                                        <span class="arrow">&#8250;</span>
                                    </a>
                                    <ul class="dept-user-list">
                                        <?php if (!empty($users)): ?>
                                            <?php foreach ($users as $user): ?>
                                                <li>
                                                    <a href="javascript:void(0);" onclick="selectUser(event, this, <?php echo (int)$user['id']; ?>, <?php echo $dept_id; ?>)" data-user-id="<?php echo (int)$user['id']; ?>" data-dept-name="<?php echo htmlspecialchars($d['dept_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <i class="feather icon-user"></i>
                                                        <span><?php echo htmlspecialchars($user['name']); ?></span>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="no-users-msg">No users assigned</li>
                                        <?php endif; ?>
                                    </ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </aside>

                <section class="grid-area">
                    <div class="mp-user-workspace" id="mpUserWorkspace">
                        <div class="mp-user-strip">
                            <div class="mp-user-strip-main">
                                <h2 id="mpSelectedUserTitle">All</h2>
                                <span class="mp-user-strip-sub" id="mpSelectedDeptLabel"><?php echo htmlspecialchars($mp_all_jobs_subtitle, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="mp-toolbar-actions">
                                <button type="button" class="btn-mini btn-pink" id="mpToolbarJobworkQueueBtn">JobWork Queue</button>
                                <label class="mp-bulk-select-all-wrap" title="Select all visible job cards">
                                    <input type="checkbox" id="mpBulkSelectAllVisible" aria-label="Select all visible">
                                    <span>Select</span>
                                </label>
                                <button type="button" class="btn-mini" id="mpBulkTransferBtn" disabled title="Open Jobwork Queue for selected orders">Jobwork Queue (<span id="mpBulkSelCount">0</span>)</button>
                                <input type="search" class="mp-tag-search" id="mpTagSearch" placeholder="Tag No" title="Search by tag number" autocomplete="off">
                                <button type="button" class="btn-icon-mini" title="Filter" id="processFilterBtn"><i class="feather icon-filter"></i></button>
                                <button type="button" class="btn-icon-mini" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
                                <div class="mp-export-dd" id="mpExportDropdown">
                                    <button type="button" class="btn-mini" id="mpExportBtn" aria-haspopup="true" aria-expanded="false">Export <i class="feather icon-chevron-down"></i></button>
                                    <div class="mp-export-menu" id="mpExportMenu" role="menu">
                                        <button type="button" class="mp-export-menu-item" id="mpExportExcelBtn" role="menuitem">Export to Excel</button>
                                        <button type="button" class="mp-export-menu-item" id="mpExportPdfBtn" role="menuitem">Export to PDF</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-mini" id="mpClosingBtn" title="Department closing entry">Closing</button>
                                <div class="mp-toolbar-view-group" role="group" aria-label="View and transfer">
                                    <button type="button" class="mp-toolbar-seg-btn mp-view-toggle<?php echo empty($mp_jobwork_orders) ? ' is-active' : ''; ?>" id="mpTransferBtn" title="Inward / Outward split" data-mp-view="stock-split">
                                        <span class="mp-icon-transfer" aria-hidden="true">
                                            <span class="mp-tr-col"><i class="feather icon-arrow-up"></i></span>
                                            <span class="mp-tr-col"><i class="feather icon-arrow-down"></i></span>
                                        </span>
                                    </button>
                                    <button type="button" class="mp-toolbar-seg-btn mp-view-toggle" title="Full table view" data-mp-view="stock-table" id="mpViewStockBtn"><i class="feather icon-list"></i></button>
                                    <button type="button" class="mp-toolbar-seg-btn mp-view-toggle<?php echo !empty($mp_jobwork_orders) ? ' is-active' : ''; ?>" title="Job cards grid" data-mp-view="cards" id="mpViewCardsBtn"><i class="feather icon-grid"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="mp-view-panel<?php echo !empty($mp_jobwork_orders) ? ' is-active' : ''; ?>" id="mpPanelCards" data-panel="cards">
                            <div class="mp-job-cards" id="mpJobCardsGrid">
                                <?php if (empty($mp_jobwork_orders)): ?>
                                <div class="mp-jwo-empty" style="grid-column:1/-1;padding:32px 16px;text-align:center;color:#8a94a8;font-size:14px;font-weight:600;">
                                    No job work orders saved yet. Create one from a sale order (Job Work Order).
                                </div>
                                <?php else: ?>
                                <?php foreach ($mp_jobwork_orders as $jwo):
                                    $jid = (int)($jwo['id'] ?? 0);
                                    $jq_no = trim((string)($jwo['jobwork_queue_no'] ?? ''));
                                    $dept_id_attr = (int)($jwo['department_id'] ?? 0);
                                    $du_id_attr = (int)($jwo['department_user_id'] ?? 0);
                                    $st_raw = trim((string)($jwo['status'] ?? ''));
                                    $st_disp = $st_raw !== '' ? ucfirst(strtolower($st_raw)) : 'Processing';
                                    $pri = trim((string)($jwo['priority'] ?? 'Medium'));
                                    $pri_class = (stripos($pri, 'high') !== false) ? 'priority-high' : '';
                                    $dept_nm = trim((string)($jwo['dept_name'] ?? ''));
                                    $dept_banner = $dept_nm !== '' ? strtoupper($dept_nm) : 'NO DEPT';
                                    $worker = trim((string)($jwo['worker_name'] ?? ''));
                                    $cust = trim((string)($jwo['customer_name'] ?? ''));
                                    $first_p = trim((string)($jwo['first_product'] ?? ''));
                                    $first_item_barcode = trim((string)($jwo['first_item_barcode'] ?? ''));
                                    $jw_no = (string)($jwo['jobwork_no'] ?? '');
                                    $so_no = (string)($jwo['sale_order_no'] ?? '');
                                    $so_trim = trim($so_no);
                                    $so_display = '';
                                    if ($so_trim !== '') {
                                        $so_display = preg_match('/^SO\s*/i', $so_trim) ? $so_trim : ('SO ' . $so_trim);
                                    }
                                    $od = $jwo['order_date'] ?? '';
                                    $dd = $jwo['due_date'] ?? '';
                                    $od_disp = ($od && strtotime($od)) ? date('n/j/Y', strtotime($od)) : '—';
                                    $dd_disp = ($dd && strtotime($dd)) ? date('n/j/Y', strtotime($dd)) : '—';
                                    $jwo_href = 'jobwork-order.php?id=' . $jid;
                                    $so_id_for_print = (int)($jwo['sale_order_id'] ?? 0);
                                    $mfg_sec = (int)($jwo['manufacturing_time_seconds'] ?? 0);
                                    if ($mfg_sec < 0) {
                                        $mfg_sec = 0;
                                    }
                                    $mfg_h = (int)floor($mfg_sec / 3600);
                                    $mfg_m = (int)floor(($mfg_sec % 3600) / 60);
                                    $mfg_s = (int)($mfg_sec % 60);
                                    $mfg_disp = sprintf('%02d:%02d:%02d', $mfg_h, $mfg_m, $mfg_s);
                                    $card_img_url = trim((string)($jwo['item_image_url'] ?? ''));
                                    $line_pid = (int)($jwo['line_product_id'] ?? 0);
                                    $line_mid = (int)($jwo['line_metal_id'] ?? 0);
                                    $jwo_total_wt_val = 0.0;
                                    $jwo_has_floor_transfer = 0;
                                    foreach ($jwo as $__k => $__v) {
                                        if (strcasecmp((string)$__k, 'jwo_total_wt_num') === 0) {
                                            $jwo_total_wt_val = (float)$__v;
                                        } elseif (strcasecmp((string)$__k, 'jwo_has_floor_transfer') === 0) {
                                            $jwo_has_floor_transfer = (int)$__v;
                                        }
                                    }
                                    $mp_wt_left = 'NA';
                                    if ($jwo_has_floor_transfer > 0 && $jwo_total_wt_val > 0) {
                                        $mp_wt_left = rtrim(rtrim(number_format($jwo_total_wt_val, 3, '.', ''), '0'), '.');
                                        if ($mp_wt_left === '') {
                                            $mp_wt_left = '0';
                                        }
                                    }
                                    $mp_wt_right = 'NA';
                                    if ($jwo_has_floor_transfer > 0) {
                                        $lp = isset($jwo['line_purity']) ? (float)$jwo['line_purity'] : 0.0;
                                        if ($lp > 0) {
                                            $mp_wt_right = rtrim(rtrim(number_format($lp, 2, '.', ''), '0'), '.');
                                        } else {
                                            $lc = trim((string)($jwo['line_carat'] ?? ''));
                                            if ($lc !== '') {
                                                $mp_wt_right = $lc;
                                            }
                                        }
                                    }
                                    $sale_bid = (int)($jwo['sale_branch_id'] ?? 0);
                                    $od_iso = ($od && strtotime($od)) ? date('Y-m-d', strtotime($od)) : '';
                                    $dd_iso = ($dd && strtotime($dd)) ? date('Y-m-d', strtotime($dd)) : '';
                                    $jw_no_cmp = strtoupper(preg_replace('/\s+/', '', (string)$jw_no));
                                    $so_cmp = strtoupper(preg_replace('/\s+/', '', $so_trim));
                                    $pri_cmp = strtolower(trim((string)$pri));
                                    $mp_stock_in_done = !empty($jwo['mp_stock_in_done']);
                                    $mp_me_metal_balance = (float) ($jwo['mp_me_metal_balance'] ?? 0);
                                    $mp_act_lock = $mp_stock_in_done ? ' mp-act-locked' : '';
                                    $mp_act_lock_attr = $mp_stock_in_done ? ' aria-disabled="true" tabindex="-1"' : '';
                                    $mp_act_lock_btn = $mp_stock_in_done ? ' disabled' : '';
                                    // Material Receive stays enabled after Stock-in when metal balance remains.
                                    $mp_mr_lock = ($mp_stock_in_done && $mp_me_metal_balance <= 0.0000001) ? ' mp-act-locked' : '';
                                    $mp_mr_lock_attr = ($mp_mr_lock !== '') ? ' aria-disabled="true" tabindex="-1"' : '';
                                ?>
                                <div class="mp-job-card-grid-item">
                                <article class="mp-job-card" data-jwo-id="<?php echo $jid; ?>" data-dept-id="<?php echo $dept_id_attr; ?>" data-user-id="<?php echo $du_id_attr; ?>" data-floor-transfer="<?php echo $jwo_has_floor_transfer > 0 ? '1' : '0'; ?>" data-stock-in="<?php echo $mp_stock_in_done ? '1' : '0'; ?>" data-me-balance="<?php echo htmlspecialchars(number_format($mp_me_metal_balance, 3, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" data-manufacturing-seconds="<?php echo $mfg_sec; ?>" data-tag-no="<?php echo htmlspecialchars($first_item_barcode, ENT_QUOTES, 'UTF-8'); ?>" data-order-date="<?php echo htmlspecialchars($od_iso, ENT_QUOTES, 'UTF-8'); ?>" data-due-date="<?php echo htmlspecialchars($dd_iso, ENT_QUOTES, 'UTF-8'); ?>" data-jobwork-no="<?php echo htmlspecialchars($jw_no_cmp, ENT_QUOTES, 'UTF-8'); ?>" data-sale-order-no="<?php echo htmlspecialchars($so_cmp, ENT_QUOTES, 'UTF-8'); ?>" data-priority="<?php echo htmlspecialchars($pri_cmp, ENT_QUOTES, 'UTF-8'); ?>" data-product-id="<?php echo $line_pid; ?>" data-metal-id="<?php echo $line_mid; ?>" data-branch-id="<?php echo $sale_bid; ?>" data-customer-name="<?php echo htmlspecialchars($cust, ENT_QUOTES, 'UTF-8'); ?>" data-line-desc="<?php echo htmlspecialchars($first_p, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="card-visual-wrap">
                                        <label class="mp-job-card-select-wrap" title="Select for bulk transfer">
                                            <input type="checkbox" class="mp-job-card-select" data-jwo-id="<?php echo $jid; ?>" aria-label="Select <?php echo htmlspecialchars($jw_no !== '' ? $jw_no : 'JWO #' . $jid, ENT_QUOTES, 'UTF-8'); ?>">
                                        </label>
                                        <span class="status-pill"><?php echo htmlspecialchars($st_disp); ?></span>
                                        <div class="ph-inner<?php echo $card_img_url !== '' ? ' ph-inner--photo' : ''; ?>">
                                            <?php if ($card_img_url !== ''): ?>
                                            <img class="mp-jwo-card-img" src="<?php echo htmlspecialchars($card_img_url); ?>" alt="" loading="lazy" decoding="async" />
                                            <?php else: ?>
                                            <div class="ph-icon"><i class="feather icon-briefcase"></i></div>
                                            <div class="ph-text">Job Work Order</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body-pad">
                                        <div class="ref-line">
                                            <?php if ($first_item_barcode !== ''): ?>
                                            <button type="button" class="ref-barcode" title="Job card print"><?php echo htmlspecialchars($first_item_barcode); ?></button>
                                            <?php endif; ?>
                                            <a class="ref-blue" href="<?php echo htmlspecialchars($jwo_href); ?>" style="text-decoration:none;color:inherit;"><?php echo htmlspecialchars($jw_no !== '' ? $jw_no : 'JWO #' . $jid); ?></a>
                                            <span class="ref-muted"><?php echo $so_display !== '' ? ' &nbsp;|&nbsp; ' . htmlspecialchars($so_display) : ''; ?></span>
                                        </div>
                                        <div class="names">
                                            <div class="n1"><?php echo htmlspecialchars($cust !== '' ? $cust : '—'); ?></div>
                                            <div class="mp-job-wt-row">
                                                <span class="mp-total-wt-num"><?php echo htmlspecialchars($mp_wt_left); ?></span>
                                                <span class="mp-wt-secondary mp-na-purple"><?php echo htmlspecialchars($mp_wt_right); ?></span>
                                            </div>
                                            <div class="mp-name-meta"><span><?php echo htmlspecialchars($worker !== '' ? $worker : '—'); ?></span><?php if ($worker !== ''): ?> <span class="mp-na-purple">Worker</span><?php endif; ?></div>
                                            <div class="n2"><?php echo htmlspecialchars($first_p !== '' ? $first_p : '—'); ?></div>
                                        </div>
                                        <div class="mp-dept-banner mp-timer-bar">
                                            <div class="mp-timer-left">
                                                <span class="mp-dept-banner-name"><?php echo htmlspecialchars($dept_banner); ?></span>
                                                <span class="mp-timer-display" aria-live="polite"><?php echo htmlspecialchars($mfg_disp); ?></span>
                                            </div>
                                            <button type="button" class="mp-timer-toggle" title="Start timer" aria-label="Start timer"><i class="feather icon-play"></i></button>
                                        </div>
                                        <div class="mp-job-meta">
                                            <div>
                                                <span class="lbl">Order Dt</span>
                                                <span class="val"><?php echo htmlspecialchars($od_disp); ?></span>
                                            </div>
                                            <div>
                                                <span class="lbl">Priority</span>
                                                <span class="val <?php echo $pri_class; ?>"><?php echo htmlspecialchars($pri); ?></span>
                                            </div>
                                            <div>
                                                <span class="lbl">Due Dt</span>
                                                <span class="val"><?php echo htmlspecialchars($dd_disp); ?></span>
                                            </div>
                                        </div>
                                        <div class="mp-job-actions">
                                            <div class="mp-job-actions-row">
                                                <a href="<?php echo $mp_stock_in_done ? 'javascript:void(0)' : htmlspecialchars($jwo_href); ?>" class="mp-act-outline<?php echo $mp_act_lock; ?>" title="Open Job Work Order"<?php echo $mp_act_lock_attr; ?> target="_blank" rel="noopener" style="text-decoration:none;"><i class="feather icon-external-link"></i></a>
                                                <a href="<?php echo $mp_stock_in_done ? 'javascript:void(0)' : htmlspecialchars('jobwork-invoice.php?Jobwork_order_id=' . $jid); ?>" class="mp-act-outline<?php echo $mp_act_lock; ?>" title="Stock in"<?php echo $mp_act_lock_attr; ?> style="text-decoration:none;"><i class="feather icon-package"></i></a>
                                                <button type="button" class="mp-act-outline mp-jwq-open-btn<?php echo $mp_act_lock; ?>" title="Add / Update"<?php echo $mp_act_lock_btn; ?>
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                    data-jobwork-queue-no="<?php echo htmlspecialchars($jq_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-jobwork-no="<?php echo htmlspecialchars($jw_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-sale-order-no="<?php echo htmlspecialchars($so_trim, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-item-barcode="<?php echo htmlspecialchars($first_item_barcode, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-dept-id="<?php echo (int)$dept_id_attr; ?>"
                                                    data-dept-name="<?php echo htmlspecialchars($dept_nm, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-id="<?php echo (int)$du_id_attr; ?>"
                                                    data-worker-name="<?php echo htmlspecialchars($worker, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-customer="<?php echo htmlspecialchars($cust, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-first-product="<?php echo htmlspecialchars($first_p, ENT_QUOTES, 'UTF-8'); ?>"
                                                ><i class="feather icon-plus"></i></button>
                                                <button type="button" class="mp-act-outline mp-attach-image-btn<?php echo $mp_act_lock; ?>" title="Attach images" aria-label="Attach images"<?php echo $mp_act_lock_btn; ?>><i class="feather icon-image"></i></button>
                                                <button type="button" class="mp-act-blue mp-weight-btn<?php echo $mp_act_lock; ?>" title="Add Weight" aria-label="Add Weight"<?php echo $mp_act_lock_btn; ?>
                                                    data-weight-mode="add"
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                    data-jobwork-queue-no="<?php echo htmlspecialchars($jq_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-jobwork-no="<?php echo htmlspecialchars($jw_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-sale-order-no="<?php echo htmlspecialchars($so_trim, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-dept-id="<?php echo (int)$dept_id_attr; ?>"
                                                    data-dept-name="<?php echo htmlspecialchars($dept_nm, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-id="<?php echo (int)$du_id_attr; ?>"
                                                    data-worker-name="<?php echo htmlspecialchars($worker, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-customer="<?php echo htmlspecialchars($cust, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-first-product="<?php echo htmlspecialchars($first_p, ENT_QUOTES, 'UTF-8'); ?>"
                                                ><i class="feather icon-trending-up"></i></button>
                                                <button type="button" class="mp-act-red mp-weight-btn<?php echo $mp_act_lock; ?>" title="Reduce Weight" aria-label="Reduce Weight"<?php echo $mp_act_lock_btn; ?>
                                                    data-weight-mode="reduce"
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                    data-jobwork-queue-no="<?php echo htmlspecialchars($jq_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-jobwork-no="<?php echo htmlspecialchars($jw_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-sale-order-no="<?php echo htmlspecialchars($so_trim, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-dept-id="<?php echo (int)$dept_id_attr; ?>"
                                                    data-dept-name="<?php echo htmlspecialchars($dept_nm, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-id="<?php echo (int)$du_id_attr; ?>"
                                                    data-worker-name="<?php echo htmlspecialchars($worker, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-customer="<?php echo htmlspecialchars($cust, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-first-product="<?php echo htmlspecialchars($first_p, ENT_QUOTES, 'UTF-8'); ?>"
                                                ><i class="feather icon-trending-down"></i></button>
                                                <button type="button" class="mp-act-outline mp-comment-btn<?php echo $mp_act_lock; ?>" title="Comment" aria-label="Comment"<?php echo $mp_act_lock_btn; ?>
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                ><i class="feather icon-message-circle"></i></button>
                                                <button type="button" class="mp-act-outline mp-print-slip-btn" title="Print" aria-label="Print"
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                    data-sale-order-id="<?php echo (int)$so_id_for_print; ?>"
                                                ><i class="feather icon-printer"></i></button>
                                                <button type="button" class="mp-act-outline mp-order-tracking-btn" title="Order Tracking" aria-label="Order Tracking"
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                ><i class="feather icon-file-text"></i></button>
                                                <button type="button" class="mp-act-red mp-job-delete-btn<?php echo $mp_act_lock; ?>" title="Remove" aria-label="Remove from department"<?php echo $mp_act_lock_btn; ?>
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                ><i class="feather icon-trash-2"></i></button>
                                                <button type="button" class="mp-act-blue mp-jwq-open-btn<?php echo $mp_act_lock; ?>" title="Transfer" aria-label="Transfer"<?php echo $mp_act_lock_btn; ?>
                                                    data-jwo-id="<?php echo (int)$jid; ?>"
                                                    data-jobwork-queue-no="<?php echo htmlspecialchars($jq_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-jobwork-no="<?php echo htmlspecialchars($jw_no, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-sale-order-no="<?php echo htmlspecialchars($so_trim, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-item-barcode="<?php echo htmlspecialchars($first_item_barcode, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-dept-id="<?php echo (int)$dept_id_attr; ?>"
                                                    data-dept-name="<?php echo htmlspecialchars($dept_nm, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-id="<?php echo (int)$du_id_attr; ?>"
                                                    data-worker-name="<?php echo htmlspecialchars($worker, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-customer="<?php echo htmlspecialchars($cust, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-first-product="<?php echo htmlspecialchars($first_p, ENT_QUOTES, 'UTF-8'); ?>"
                                                ><i class="feather icon-arrow-right"></i></button>
                                                <?php if ($mp_manufacturing_mode === 'outsource'):
                                                    $mp_mi_href = $so_id_for_print > 0
                                                        ? ('material-issue.php?sale_order_id=' . $so_id_for_print)
                                                        : ('material-issue.php?id=' . $jid);
                                                    $mp_mr_href = $so_id_for_print > 0
                                                        ? ('material-receive.php?sale_order_id=' . $so_id_for_print)
                                                        : ('material-receive.php?id=' . $jid);
                                                    $mp_mi_title = function_exists('auragold_t') ? auragold_t('trans.material_issue') : 'Material Issue';
                                                    $mp_mr_title = function_exists('auragold_t') ? auragold_t('trans.material_receive') : 'Material Receive';
                                                    if ($mp_mi_title === 'trans.material_issue') {
                                                        $mp_mi_title = 'Material Issue';
                                                    }
                                                    if ($mp_mr_title === 'trans.material_receive') {
                                                        $mp_mr_title = 'Material Receive';
                                                    }
                                                    if ($mp_mr_lock === '' && $mp_stock_in_done && $mp_me_metal_balance > 0.0000001) {
                                                        $mp_mr_title .= ' (metal balance ' . number_format($mp_me_metal_balance, 3, '.', '') . ')';
                                                    }
                                                ?>
                                                <a href="<?php echo $mp_stock_in_done ? 'javascript:void(0)' : htmlspecialchars($mp_mi_href, ENT_QUOTES, 'UTF-8'); ?>" class="mp-act-blue mp-mat-issue-btn<?php echo $mp_act_lock; ?>" title="<?php echo htmlspecialchars($mp_mi_title, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($mp_mi_title, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $mp_act_lock_attr; ?> target="_blank" rel="noopener" style="text-decoration:none;"><i class="feather icon-share-2"></i></a>
                                                <a href="<?php echo ($mp_mr_lock !== '') ? 'javascript:void(0)' : htmlspecialchars($mp_mr_href, ENT_QUOTES, 'UTF-8'); ?>" class="mp-act-blue mp-mat-receive-btn<?php echo $mp_mr_lock; ?>" title="<?php echo htmlspecialchars($mp_mr_title, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($mp_mr_title, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $mp_mr_lock_attr; ?> target="_blank" rel="noopener" style="text-decoration:none;"><i class="feather icon-inbox"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="mp-pagination-bar">
                                <span id="mpCardsPaginationText"><?php
                                    $nj = count($mp_jobwork_orders);
                                    echo $nj === 0 ? 'No entries' : ('Showing ' . $nj . ' job work order' . ($nj === 1 ? '' : 's'));
                                ?></span>
                                <label>Show All Items <select id="mpCardsPageSize"><option>10</option><option>25</option><option>50</option><option>100</option></select></label>
                            </div>
                        </div>

                        <div class="mp-view-panel<?php echo empty($mp_jobwork_orders) ? ' is-active' : ''; ?>" id="mpPanelStock" data-panel="stock">
                    <div id="mpStockSplitWrap" class="mp-stock-mode mp-stock-mode--split">
                    <div class="stock-split">
                        <div class="stock-box">
                            <div class="stock-head">
                                <span>
                                    Inward Stock
                                    <a href="#" class="mp-view-stock-link" id="mpViewStockLink" role="button">View Stock</a>
                                    <span class="mp-stock-balance mp-stock-balance-sr" id="mpInwardBalanceSummary" aria-hidden="true">(Balance Stock — Total Metal Wt.: 0.000, Total Diamond/Stone Wt.: 0.000, Metal Qty.: 0.00, Diamond/Stone Qty.: 0.00)</span>
                                </span>
                                <button type="button" class="head-setting-btn inward-settings-toggle" title="Columns">
                                    <i class="feather icon-settings mini-gear"></i>
                                </button>
                            </div>
                            <div class="table-wrap mp-stock-table-panel">
                                <div class="columns-panel" id="inwardColumnsPanel">
                                    <div class="columns-panel-header">
                                        <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                        <button type="button" class="columns-panel-close" data-close-panel="inwardColumnsPanel">&times;</button>
                                    </div>
                                    <div class="columns-search">
                                        <input type="text" id="inwardColumnsSearch" placeholder="Search">
                                    </div>
                                    <div class="columns-list" id="inwardColumnsList">
                                        <?php foreach ($stock_columns as $col): ?>
                                            <label data-label="<?php echo htmlspecialchars(strtolower($col['label'])); ?>">
                                                <input type="checkbox" class="inward-column-checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                                                <span><?php echo htmlspecialchars($col['label']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="mp-stock-table-scroll" data-stock-scroll-for="inwardTable">
                                <table class="table" id="inwardTable">
                                    <thead>
                                        <tr>
                                            <?php foreach ($stock_columns as $col): ?>
                                                <th data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo htmlspecialchars($col['label']); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                </table>
                                <div class="empty-center">No Rows To Show</div>
                                </div>
                            </div>
                            <div class="mp-stock-grand-total-bar" id="inwardGrandTotalBar" aria-label="Inward stock grand total">
                                <table class="table mp-stock-grand-total-table" id="inwardGrandTotalTable">
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="stock-box">
                            <div class="stock-head">
                                <span>Outward Stock</span>
                                <button type="button" class="head-setting-btn outward-settings-toggle" title="Columns">
                                    <i class="feather icon-settings mini-gear"></i>
                                </button>
                            </div>
                            <div class="table-wrap mp-stock-table-panel">
                                <div class="columns-panel" id="outwardColumnsPanel">
                                    <div class="columns-panel-header">
                                        <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                        <button type="button" class="columns-panel-close" data-close-panel="outwardColumnsPanel">&times;</button>
                                    </div>
                                    <div class="columns-search">
                                        <input type="text" id="outwardColumnsSearch" placeholder="Search">
                                    </div>
                                    <div class="columns-list" id="outwardColumnsList">
                                        <?php foreach ($stock_columns as $col): ?>
                                            <label data-label="<?php echo htmlspecialchars(strtolower($col['label'])); ?>">
                                                <input type="checkbox" class="outward-column-checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                                                <span><?php echo htmlspecialchars($col['label']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="mp-stock-table-scroll" data-stock-scroll-for="outwardTable">
                                <table class="table" id="outwardTable">
                                    <thead>
                                        <tr>
                                            <?php foreach ($stock_columns as $col): ?>
                                                <th data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo htmlspecialchars($col['label']); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                </table>
                                <div class="empty-center">No Rows To Show</div>
                                </div>
                            </div>
                            <div class="mp-stock-grand-total-bar" id="outwardGrandTotalBar" aria-label="Outward stock grand total">
                                <table class="table mp-stock-grand-total-table" id="outwardGrandTotalTable">
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div id="mpStockTableWrap" class="mp-stock-mode mp-stock-mode--table">
                        <div class="stock-box stock-box--full">
                            <div class="stock-head" style="position:relative;">
                                <span>Manufacturing queue</span>
                                <button type="button" class="head-setting-btn mp-mfg-queue-settings-toggle" id="mpMfgQueueColumnsToggle" title="Columns" aria-expanded="false" aria-controls="mpMfgQueueColumnsPanel">
                                    <i class="feather icon-settings mini-gear"></i>
                                </button>
                            </div>
                            <div class="table-wrap" style="position:relative;">
                                <div class="columns-panel" id="mpMfgQueueColumnsPanel" role="dialog" aria-label="Manufacturing queue columns">
                                    <div class="columns-panel-header">
                                        <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                        <button type="button" class="columns-panel-close" data-close-panel="mpMfgQueueColumnsPanel" aria-label="Close">&times;</button>
                                    </div>
                                    <div class="columns-search">
                                        <input type="text" id="mpMfgQueueColumnsSearch" placeholder="Search" autocomplete="off">
                                    </div>
                                    <div class="columns-list" id="mpMfgQueueColumnsList">
                                        <?php
                                        $mfg_panel_cols = array_merge(
                                            [
                                                ['key' => '_rowchk', 'label' => 'Checkbox'],
                                                ['key' => '_img', 'label' => 'Image'],
                                            ],
                                            $mfg_queue_columns
                                        );
                                        foreach ($mfg_panel_cols as $col):
                                            $lk = strtolower($col['label']);
                                        ?>
                                        <label data-label="<?php echo htmlspecialchars($lk); ?>">
                                            <input type="checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                                            <span><?php echo htmlspecialchars($col['label']); ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <table class="table" id="fullStockTable">
                                    <thead>
                                        <tr>
                                            <th data-col="_rowchk" style="width:40px;text-align:center;"><input type="checkbox" disabled aria-label="Select row"></th>
                                            <th data-col="_img">Image</th>
                                            <?php foreach ($mfg_queue_columns as $col): ?>
                                                <th data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo htmlspecialchars($col['label']); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                </table>
                                <div class="empty-center">No Rows To Show</div>
                            </div>
                            <div class="table-footer-bar"><div class="scroll-mock"></div></div>
                        </div>
                    </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<div class="filter-modal-overlay" id="processFilterModalOverlay">
    <div class="filter-modal" role="dialog" aria-modal="true" aria-labelledby="processFilterTitle">
        <div class="filter-modal-head">
            <span id="processFilterTitle">Advance Filter</span>
            <button type="button" class="filter-modal-close" id="processFilterCloseBtn" aria-label="Close">&times;</button>
        </div>
        <form class="filter-modal-body" id="processFilterForm">
            <div class="filter-grid">
                <div class="filter-field">
                    <label for="filterDateFrom">Due Date</label>
                    <div class="date-range-inputs">
                        <input type="date" id="filterDateFrom" name="due_from">
                        <span class="date-range-sep">to</span>
                        <input type="date" id="filterDateTo" name="due_to">
                    </div>
                </div>
                <div class="filter-field">
                    <label for="filterAdvTagNo">Tag No.</label>
                    <input type="text" id="filterAdvTagNo" name="tag_no" placeholder="Tag / barcode" autocomplete="off">
                </div>

                <div class="filter-field filter-field-full">
                    <label>Branch</label>
                    <div class="mp-ms" data-mp-ms data-mp-label="Select Branch">
                        <button type="button" class="mp-ms-btn" aria-expanded="false">Select Branch</button>
                        <div class="mp-ms-panel">
                            <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                            <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                            <div class="mp-ms-list">
                                <?php foreach ($filter_branches as $br): ?>
                                <label class="mp-ms-opt"><input type="checkbox" name="filter_branch[]" value="<?php echo (int)$br['id']; ?>"><span><?php echo htmlspecialchars($br['name'] ?? ''); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-field filter-field-full">
                    <label>Metal</label>
                    <div class="mp-ms" data-mp-ms data-mp-label="Select Metal">
                        <button type="button" class="mp-ms-btn" aria-expanded="false">Select Metal</button>
                        <div class="mp-ms-panel">
                            <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                            <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                            <div class="mp-ms-list">
                                <?php foreach ($metals as $m): $ml = trim((string)($m['display_name'] ?? '')); if ($ml === '') { $ml = trim((string)($m['system_name'] ?? '')); } ?>
                                <label class="mp-ms-opt"><input type="checkbox" name="filter_metal[]" value="<?php echo (int)$m['id']; ?>"><span><?php echo htmlspecialchars($ml); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-field filter-field-full">
                    <label>Product</label>
                    <div class="mp-ms" data-mp-ms data-mp-label="Select Product">
                        <button type="button" class="mp-ms-btn" aria-expanded="false">Select Product</button>
                        <div class="mp-ms-panel">
                            <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                            <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                            <div class="mp-ms-list">
                                <?php foreach ($filter_products as $fp): ?>
                                <label class="mp-ms-opt"><input type="checkbox" name="filter_product[]" value="<?php echo (int)$fp['id']; ?>"><span><?php echo htmlspecialchars($fp['name'] ?? ''); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-field filter-field-full">
                    <label>Priority</label>
                    <div class="mp-ms" data-mp-ms data-mp-label="Select Priority">
                        <button type="button" class="mp-ms-btn" aria-expanded="false">Select Priority</button>
                        <div class="mp-ms-panel">
                            <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                            <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                            <div class="mp-ms-list">
                                <?php foreach ($filter_priority_options as $po): ?>
                                <label class="mp-ms-opt"><input type="checkbox" name="filter_priority[]" value="<?php echo htmlspecialchars($po, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($po); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-field">
                    <label for="filterOrderNo">Order No</label>
                    <input type="text" id="filterOrderNo" name="order_no" placeholder="Sale order no." autocomplete="off">
                </div>
                <div class="filter-field">
                    <label for="filterJwoNo">JWO No</label>
                    <input type="text" id="filterJwoNo" name="jwo_no" placeholder="Job work no." autocomplete="off">
                </div>
            </div>

            <div class="filter-modal-foot">
                <button type="submit" class="btn-filter-apply">Apply Filter</button>
                <button type="reset" class="btn-filter-clear">Clear Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="filter-modal-overlay" id="mpClosingModalOverlay" aria-hidden="true">
    <div class="mp-closing-modal" role="dialog" aria-modal="true" aria-labelledby="mpClosingTitle">
        <div class="mp-closing-modal-head">
            <span id="mpClosingTitle">Closing</span>
            <button type="button" class="mp-closing-modal-close" id="mpClosingModalCloseBtn" aria-label="Close">&times;</button>
        </div>
        <form class="mp-closing-modal-body" id="mpClosingForm" novalidate>
            <div class="mp-closing-field">
                <label for="mpClosingLossWt">Loss Wt</label>
                <input type="text" id="mpClosingLossWt" name="loss_wt" inputmode="decimal" autocomplete="off" readonly placeholder="—">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingWorkDoneKg">Work Done(KG)</label>
                <input type="text" id="mpClosingWorkDoneKg" name="work_done_kg" inputmode="decimal" autocomplete="off" placeholder="0">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingAvgLossPerKg">Avg Loss/ KG</label>
                <input type="text" id="mpClosingAvgLossPerKg" name="avg_loss_per_kg" readonly tabindex="-1" placeholder="0">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingPurityPer">Purity Per</label>
                <input type="text" id="mpClosingPurityPer" name="purity_per" inputmode="decimal" autocomplete="off" placeholder="0">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingPurityWt">Purity Wt</label>
                <input type="text" id="mpClosingPurityWt" name="purity_wt" readonly tabindex="-1" placeholder="0">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingGoldRate">Gold Rate</label>
                <input type="text" id="mpClosingGoldRate" name="gold_rate" inputmode="decimal" autocomplete="off" placeholder="0">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingGoldLossValue">Gold Loss Value</label>
                <input type="text" id="mpClosingGoldLossValue" name="gold_loss_value" inputmode="decimal" autocomplete="off" placeholder="0">
            </div>
            <div class="mp-closing-field">
                <label for="mpClosingDate">Closing Date</label>
                <div class="mp-closing-date-wrap">
                    <input type="date" id="mpClosingDate" name="closing_date" required>
                    <button type="button" class="mp-closing-date-reset" id="mpClosingDateResetBtn" title="Set to today" aria-label="Set closing date to today">
                        <i class="feather icon-refresh-cw" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </div>
        </form>
        <div class="mp-closing-modal-foot">
            <button type="button" class="mp-closing-btn-secondary" id="mpClosingFooterCloseBtn">Close</button>
            <button type="submit" class="mp-closing-btn-primary" id="mpClosingSaveBtn" form="mpClosingForm">Save</button>
        </div>
    </div>
</div>

<div class="filter-modal-overlay" id="mpViewStockModalOverlay" aria-hidden="true">
    <div class="mp-view-stock-modal mp-closing-modal" role="dialog" aria-modal="true" aria-labelledby="mpViewStockTitle">
        <div class="mp-closing-modal-head">
            <span id="mpViewStockTitle">Balance Stock</span>
            <button type="button" class="mp-closing-modal-close" id="mpViewStockModalCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="mp-closing-modal-body">
            <table class="mp-view-stock-details">
                <tbody>
                    <tr>
                        <td>Total Metal Wt.</td>
                        <td id="mpViewStockMetalWt">0.000</td>
                    </tr>
                    <tr>
                        <td>Metal Qty.</td>
                        <td id="mpViewStockMetalQty">0.00</td>
                    </tr>
                    <tr>
                        <td>Total Diamond/Stone Wt.</td>
                        <td id="mpViewStockDiamondWt">0.000</td>
                    </tr>
                    <tr>
                        <td>Diamond/Stone Qty.</td>
                        <td id="mpViewStockDiamondQty">0.00</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mp-closing-modal-foot">
            <button type="button" class="mp-closing-btn-secondary" id="mpViewStockFooterCloseBtn">Close</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/jwq-queue-modal.php'; ?>
<?php include __DIR__ . '/includes/mp-add-image-modal.php'; ?>

<div id="mpBulkTransferOverlay" class="mp-bulk-transfer-overlay" aria-hidden="true">
    <div class="mp-bulk-transfer-dialog" role="dialog" aria-modal="true" aria-labelledby="mpBulkTransferTitle">
        <h3 id="mpBulkTransferTitle">Bulk department transfer</h3>
        <p class="mp-bulk-transfer-summary" id="mpBulkTransferSummary">0 orders selected</p>
        <ul class="mp-bulk-transfer-list" id="mpBulkTransferList"></ul>
        <div class="mp-bulk-transfer-fields">
            <div>
                <label for="mpBulkToDept">To Dept (destination)</label>
                <select id="mpBulkToDept"></select>
            </div>
            <div>
                <label for="mpBulkToUser">To User (destination)</label>
                <select id="mpBulkToUser"></select>
            </div>
        </div>
        <div class="mp-bulk-transfer-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="mpBulkTransferCancel">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="mpBulkTransferConfirm" style="background:#11294b;border-color:#11294b;">Transfer all</button>
        </div>
    </div>
</div>

<!-- Inward Stock details (folder icon on From/To user rows): compact modal + toolbar -->
<div class="modal fade" id="jwqInwardStockModal" tabindex="-1" role="dialog" aria-labelledby="jwqInwardStockModalTitle" aria-hidden="true" data-backdrop="true">
    <div class="modal-dialog jwq-inward-stock-modal-dialog" role="document">
        <div class="modal-content jwq-inward-stock-modal-content">
            <div class="modal-header" style="background:#11294b;color:#fff;border:none;">
                <h5 class="modal-title mb-0" id="jwqInwardStockModalTitle">Inward Stock <span id="jwqInwardStockModalContext" style="font-size:0.85rem;opacity:0.85;font-weight:500;"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1;text-shadow:none;"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding:12px;background:#f8fafc;">
                <div class="jwq-inward-stock-toolbar">
                    <button type="button" class="jwq-inward-stock-tool jwq-inward-stock-tool--excel" id="jwqInwardStockBtnExcel" title="Export Excel">
                        <i class="feather icon-download" style="width:18px;height:18px;"></i>
                    </button>
                    <button type="button" class="jwq-inward-stock-tool jwq-inward-stock-tool--pdf" id="jwqInwardStockBtnPdf" title="Print / PDF">
                        <i class="feather icon-file-text" style="width:18px;height:18px;"></i>
                    </button>
                    <button type="button" class="jwq-inward-stock-tool jwq-inward-stock-tool--columns head-setting-btn jwq-inward-stock-columns-toggle" id="jwqInwardStockBtnColumns" title="Columns">
                        <i class="feather icon-settings mini-gear"></i>
                    </button>
                    <div class="columns-panel" id="jwqInwardStockColumnsPanel">
                        <div class="columns-panel-header">
                            <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                            <button type="button" class="columns-panel-close" data-close-panel="jwqInwardStockColumnsPanel">&times;</button>
                        </div>
                        <div class="columns-search">
                            <input type="text" id="jwqInwardStockColumnsSearch" placeholder="Search">
                        </div>
                        <div class="columns-list" id="jwqInwardStockColumnsList">
                            <?php foreach ($jwq_inward_stock_modal_columns as $col): ?>
                            <label data-label="<?php echo htmlspecialchars(strtolower($col['label'])); ?>">
                                <input type="checkbox" class="jwq-inward-stock-column-checkbox" data-col="<?php echo htmlspecialchars($col['key']); ?>" checked>
                                <span><?php echo htmlspecialchars($col['label']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:55vh;border:1px solid #e2e8f0;border-radius:6px;background:#fff;">
                    <table class="table table-bordered table-sm mb-0" id="jwqInwardStockTable" style="font-size:12px;">
                        <thead style="background:#eef2f8;color:#334155;">
                            <tr>
                                <?php foreach ($jwq_inward_stock_modal_columns as $col): ?>
                                    <th data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo htmlspecialchars($col['label']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="jwqInwardStockBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mp-jcp-backdrop" id="mpJobCardPrintBackdrop" aria-hidden="true"></div>
<div class="mp-jcp-drawer" id="mpJobCardPrintDrawer" role="dialog" aria-modal="true" aria-labelledby="mpJcpTitle" aria-hidden="true">
    <div class="mp-jcp-drawer-head mp-jcp-no-print">
        <div class="mp-jcp-drawer-head-top">
            <h2 id="mpJcpTitle">Job Card Print</h2>
            <button type="button" class="mp-jcp-drawer-close" id="mpJobCardPrintCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="mp-jcp-drawer-toolbar">
            <label>Tag No <input type="text" id="mpJcpTagInput" autocomplete="off" readonly></label>
            <button type="button" class="mp-jcp-btn-print" id="mpJcpPrintBtn">Print</button>
            <button type="button" class="mp-jcp-btn-export" id="mpJcpExportBtn">Export Excel</button>
        </div>
    </div>
    <div class="mp-jcp-drawer-body" id="mpJcpPrintRoot">
        <div class="mp-jcp-print-grid">
            <div class="mp-jcp-side">
                <h3 id="mpJcpCustomerName">—</h3>
                <div class="mp-jcp-side-dl">
                    <div><span>Date</span><span id="mpJcpOrderDate">—</span></div>
                    <div><span>Due Date</span><span id="mpJcpDueDate">—</span></div>
                    <div><span>Reference No.</span><span id="mpJcpRefNo">—</span></div>
                </div>
                <div class="mp-jcp-barcode-wrap" id="mpJcpBarcodeBlock">
                    <div class="mp-jcp-barcode-label" id="mpJcpBarcodeText">—</div>
                    <svg id="mpJcpBarcodeSvg" xmlns="http://www.w3.org/2000/svg"></svg>
                </div>
                <div class="mp-jcp-time-box">
                    <i class="feather icon-clock"></i>
                    <div><div style="font-size:11px;font-weight:600;opacity:.9;">Total Time Spent</div><div id="mpJcpTimeSpent">0H 00M 00S</div></div>
                </div>
                <div class="mp-jcp-images">
                    <strong>Images</strong>
                    <div id="mpJcpImagesMount"><span class="mp-jcp-img-empty">No Images To Display!</span></div>
                </div>
            </div>
            <div class="mp-jcp-main">
                <div class="mp-jcp-table-block">
                    <div class="mp-jcp-section-head">
                        <h3>Job Queue History</h3>
                        <button type="button" class="head-setting-btn mp-jcp-no-print mp-jcp-history-cols-toggle" id="mpJcpHistoryColsToggle" title="Columns" aria-expanded="false" aria-controls="mpJcpHistoryColumnsPanel">
                            <i class="feather icon-settings mini-gear"></i>
                        </button>
                        <div class="columns-panel mp-jcp-columns-popover" id="mpJcpHistoryColumnsPanel" role="dialog" aria-label="Job queue history columns">
                            <div class="columns-panel-header">
                                <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                <button type="button" class="columns-panel-close" data-close-panel="mpJcpHistoryColumnsPanel" aria-label="Close">&times;</button>
                            </div>
                            <div class="columns-search">
                                <input type="text" id="mpJcpHistoryColumnsSearch" placeholder="Search" autocomplete="off">
                            </div>
                            <div class="columns-list" id="mpJcpHistoryColumnsList"></div>
                        </div>
                    </div>
                    <div class="table-responsive mp-jcp-table-scroll">
                        <table class="mp-jcp-table" id="mpJcpHistoryTable">
                            <thead id="mpJcpHistoryHead"></thead>
                            <tbody id="mpJcpHistoryBody"></tbody>
                            <tfoot id="mpJcpHistoryFoot"></tfoot>
                        </table>
                    </div>
                </div>
                <div class="mp-jcp-table-block">
                    <div class="mp-jcp-section-head">
                        <h3>Summary</h3>
                        <button type="button" class="head-setting-btn mp-jcp-no-print mp-jcp-summary-cols-toggle" id="mpJcpSummaryColsToggle" title="Columns" aria-expanded="false" aria-controls="mpJcpSummaryColumnsPanel">
                            <i class="feather icon-settings mini-gear"></i>
                        </button>
                        <div class="columns-panel mp-jcp-columns-popover" id="mpJcpSummaryColumnsPanel" role="dialog" aria-label="Summary columns">
                            <div class="columns-panel-header">
                                <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                <button type="button" class="columns-panel-close" data-close-panel="mpJcpSummaryColumnsPanel" aria-label="Close">&times;</button>
                            </div>
                            <div class="columns-search">
                                <input type="text" id="mpJcpSummaryColumnsSearch" placeholder="Search" autocomplete="off">
                            </div>
                            <div class="columns-list" id="mpJcpSummaryColumnsList"></div>
                        </div>
                    </div>
                    <div class="table-responsive mp-jcp-table-scroll mp-jcp-table-scroll--summary">
                        <table class="mp-jcp-table mp-jcp-summary-table" id="mpJcpSummaryTable">
                            <thead id="mpJcpSummaryHead"></thead>
                            <tbody id="mpJcpSummaryBody"></tbody>
                            <tfoot id="mpJcpSummaryFoot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="mpJcpPrintSheet" class="mp-jcp-print-sheet" aria-hidden="true"></div>

<?php include 'includes/mp-weight-adjust-modal.php'; ?>
<?php include 'includes/mp-job-comments-modal.php'; ?>
<?php include 'includes/jwq-payment-modals.php'; ?>

<script>
window.mpDepartments = <?php echo $mp_departments_json; ?>;
window.mpDepartmentUsers = <?php echo $mp_department_users_json; ?>;
window.mpManufacturingPageHeading = <?php echo json_encode($mp_page_heading, JSON_UNESCAPED_UNICODE); ?>;
window.mpAllJobsSubtitle = <?php echo json_encode($mp_all_jobs_subtitle, JSON_UNESCAPED_UNICODE); ?>;
window.mpManufacturingPageSelf = <?php echo json_encode($mp_page_self, JSON_UNESCAPED_UNICODE); ?>;
window.JWQ_ORDER_LINE_COL_KEYS = <?php echo json_encode(array_column($jwq_order_line_columns, 'key')); ?>;
window.MP_STOCK_COLUMN_KEYS = <?php echo json_encode(array_column($stock_columns, 'key')); ?>;
window.MP_STOCK_COLUMN_LABELS = <?php
$mp_stock_labels = [];
foreach ($stock_columns as $col) {
    $mp_stock_labels[$col['key']] = $col['label'];
}
echo json_encode($mp_stock_labels);
?>;
window.MP_STOCK_COLUMNS_ALWAYS_HIDDEN = <?php echo json_encode(isset($stock_columns_always_hidden) && is_array($stock_columns_always_hidden) ? array_values($stock_columns_always_hidden) : ['image_urls']); ?>;
window.MFG_QUEUE_COLUMN_KEYS = <?php echo json_encode(array_column($mfg_queue_columns, 'key')); ?>;
window.MFG_QUEUE_COLUMN_LABELS = <?php
$mp_mfg_labels = ['_rowchk' => 'Checkbox', '_img' => 'Image'];
foreach ($mfg_queue_columns as $col) {
    $mp_mfg_labels[$col['key']] = $col['label'];
}
echo json_encode($mp_mfg_labels);
?>;
window.JWQ_INWARD_STOCK_MODAL_KEYS = <?php echo json_encode(array_column($jwq_inward_stock_modal_columns, 'key')); ?>;
window.MP_JCP_HISTORY_COLUMNS = [
    { key: 'active', label: 'active' },
    { key: 'date', label: 'Date' },
    { key: 'sr_no', label: 'Sr No' },
    { key: 'description', label: 'Description' },
    { key: 'qty', label: 'Qty' },
    { key: 'gross_wt', label: 'Gross Wt' },
    { key: 'other_wt', label: 'Other Wt' },
    { key: 'loss_wt', label: 'Loss Wt' },
    { key: 'profit_wt', label: 'Profit Wt' },
    { key: 'gold_wt', label: 'Gold Wt' },
    { key: 'diamond_wt', label: 'Diamond Wt' },
    { key: 'spent_time', label: 'Spent Time' },
    { key: 'price', label: 'Price' },
    { key: 'metal_wt', label: 'Metal Wt' },
    { key: 'dept_flow', label: 'Department Flow' },
    { key: 'changed_wt', label: 'Changed Weight' },
    { key: 'is_add_weight', label: 'Is Add Weight' },
    { key: 'is_return_weight', label: 'Is Return Weight' }
];
window.MP_JCP_SUMMARY_COLUMNS = [
    { key: 'department', label: 'Department' },
    { key: 'issue_wt', label: 'Issue Weight' },
    { key: 'return_wt', label: 'Return Weight' },
    { key: 'actual_loss', label: 'Actual Loss' },
    { key: 'spent_time', label: 'Spent Time' }
];
</script>

<?php include 'footer-script.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="assets/js/auragold-col-reorder.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<?php include 'includes/mp-job-delete-swal.php'; ?>
<?php include 'includes/mp-mfg-queue-reverse-swal.php'; ?>
<?php include 'includes/mp-manufacturing-print-swal.php'; ?>
<?php include 'includes/mp-order-tracking-modal.php'; ?>
<script>
/** Same behaviour as sale-invoice.php: open Bootstrap payment modals (amount defaults to 0.00 or Balance Amt if present). */
function openPaymentModal(type) {
    var modalMap = {
        'cash': '#cashPaymentModal',
        'bank': '#bankPaymentModal',
        'cheque': '#chequePaymentModal',
        'upi': '#upiPaymentModal',
        'card': '#cardPaymentModal',
        'metal-exchange': '#metalExchangeModal',
        'scrap': '#scrapPaymentModal',
        'diamond': '#jwqDiamondModal'
    };
    var modalId = modalMap[type];
    if (!modalId || typeof window.jQuery === 'undefined' || !window.jQuery.fn.modal) {
        return;
    }
    var summaryBalanceAmtEl = document.getElementById('summaryBalanceAmt');
    var balanceAmt = summaryBalanceAmtEl ? parseFloat(summaryBalanceAmtEl.textContent.replace(/,/g, '')) || 0 : 0;
    var amountToShow = balanceAmt > 0 ? balanceAmt.toFixed(2) : '0.00';
    if (type === 'cash') {
        var cashAmountEl = document.getElementById('cashAmount');
        if (cashAmountEl) cashAmountEl.value = amountToShow;
    } else if (type === 'bank') {
        var bankAmountEl = document.getElementById('bankAmount');
        if (bankAmountEl) bankAmountEl.value = amountToShow;
    } else if (type === 'cheque') {
        var chequeAmountEl = document.getElementById('chequeAmount');
        if (chequeAmountEl) chequeAmountEl.value = amountToShow;
    } else if (type === 'upi') {
        var upiAmountEl = document.getElementById('upiAmount');
        if (upiAmountEl) upiAmountEl.value = amountToShow;
    } else if (type === 'card') {
        var cardAmountEl = document.getElementById('cardAmount');
        if (cardAmountEl) cardAmountEl.value = amountToShow;
    } else if (type === 'metal-exchange') {
        var metalExchangeAmountEl = document.getElementById('metalExchangeAmount');
        if (metalExchangeAmountEl) metalExchangeAmountEl.value = amountToShow;
    } else if (type === 'scrap') {
        var scrapAmountEl = document.getElementById('scrapAmount');
        if (scrapAmountEl) scrapAmountEl.value = amountToShow;
    }
    window.jQuery(modalId).modal('show');
}

function savePayment(type) {
    if (typeof window.jwqSaveMetalExchangePayment === 'function' && window.jwqSaveMetalExchangePayment(type)) {
        return;
    }
    if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.modal) {
        return;
    }
    var modalMap = {
        'cash': '#cashPaymentModal',
        'bank': '#bankPaymentModal',
        'cheque': '#chequePaymentModal',
        'upi': '#upiPaymentModal',
        'card': '#cardPaymentModal',
        'metal-exchange': '#metalExchangeModal',
        'scrap': '#scrapPaymentModal',
        'diamond': '#jwqDiamondModal'
    };
    var sel = modalMap[type];
    if (sel) {
        window.jQuery(sel).modal('hide');
    }
}

function jwqPaymentTypeFromIcon(el) {
    if (!el || !el.classList) return 'cash';
    if (el.classList.contains('payment-cash')) return 'cash';
    if (el.classList.contains('payment-bank')) return 'bank';
    if (el.classList.contains('payment-cheque')) return 'cheque';
    if (el.classList.contains('payment-mobile')) return 'upi';
    if (el.classList.contains('payment-card')) return 'card';
    if (el.classList.contains('payment-exchange')) return 'metal-exchange';
    if (el.classList.contains('payment-jewelry')) return 'scrap';
    if (el.classList.contains('payment-diamond')) return 'diamond';
    if (el.classList.contains('payment-stone')) return 'metal-exchange';
    if (el.classList.contains('payment-other')) return 'cash';
    return 'cash';
}

function initJwqPaymentIcons() {
    var wrap = document.getElementById('jwqPaymentIcons');
    if (!wrap) return;
    wrap.addEventListener('click', function (e) {
        var icon = e.target.closest('.payment-icon');
        if (!icon || !wrap.contains(icon)) return;
        e.preventDefault();
        openPaymentModal(jwqPaymentTypeFromIcon(icon));
    });
}

window.mpAdvanceFilter = { active: false };

function mpMsUpdateLabel(wrap) {
    var btn = wrap.querySelector('.mp-ms-btn');
    var list = wrap.querySelector('.mp-ms-list');
    var ph = wrap.getAttribute('data-mp-label') || 'Select';
    if (!btn || !list) return;
    var opts = list.querySelectorAll('input[type="checkbox"]');
    var checked = list.querySelectorAll('input[type="checkbox"]:checked');
    var n = checked.length;
    var total = opts.length;
    if (n === 0) {
        btn.textContent = ph;
    } else if (total && n === total) {
        btn.textContent = ph + ' (all)';
    } else {
        btn.textContent = ph + ' (' + n + ')';
    }
}

function initMpMultiSelectDropdowns(root) {
    root = root || document;
    root.querySelectorAll('[data-mp-ms]').forEach(function (wrap) {
        if (wrap._mpMsInit) return;
        wrap._mpMsInit = true;
        var btn = wrap.querySelector('.mp-ms-btn');
        var panel = wrap.querySelector('.mp-ms-panel');
        var search = wrap.querySelector('.mp-ms-search');
        var list = wrap.querySelector('.mp-ms-list');
        var allCb = wrap.querySelector('.mp-ms-check-all');

        function syncAll() {
            var opts = list.querySelectorAll('input[type="checkbox"]');
            var checked = list.querySelectorAll('input[type="checkbox"]:checked');
            if (allCb) {
                allCb.indeterminate = checked.length > 0 && checked.length < opts.length;
                allCb.checked = opts.length > 0 && checked.length === opts.length;
            }
            mpMsUpdateLabel(wrap);
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = panel.classList.contains('is-open');
            document.querySelectorAll('.mp-ms-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('.mp-ms-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (!wasOpen) {
                panel.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });

        if (allCb) {
            allCb.addEventListener('change', function () {
                var v = allCb.checked;
                list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                    if (lab.style.display === 'none') return;
                    var cb = lab.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = v;
                });
                syncAll();
            });
        }
        list.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'checkbox' && e.target !== allCb) syncAll();
        });

        if (search) {
            search.addEventListener('input', function () {
                var q = (search.value || '').toLowerCase().trim();
                list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                    var t = (lab.textContent || '').toLowerCase();
                    lab.style.display = !q || t.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }
        syncAll();
    });

    if (!document._mpMsDocClick) {
        document._mpMsDocClick = true;
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('.mp-ms')) return;
            document.querySelectorAll('.mp-ms-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('.mp-ms-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
        });
    }
}

function collectAdvanceFilterFromForm(form) {
    var dueFrom = (form.querySelector('#filterDateFrom') || {}).value || '';
    var dueTo = (form.querySelector('#filterDateTo') || {}).value || '';
    var tag = (form.querySelector('#filterAdvTagNo') || {}).value || '';
    var orderNo = (form.querySelector('#filterOrderNo') || {}).value || '';
    var jwoNo = (form.querySelector('#filterJwoNo') || {}).value || '';
    function checkedVals(name) {
        var a = [];
        form.querySelectorAll('input[name="' + name + '"]:checked').forEach(function (cb) {
            a.push(cb.value);
        });
        return a;
    }
    return {
        active: true,
        dueFrom: dueFrom,
        dueTo: dueTo,
        tag: tag.trim(),
        orderNo: orderNo.trim(),
        jwoNo: jwoNo.trim(),
        branches: checkedVals('filter_branch[]'),
        metals: checkedVals('filter_metal[]'),
        products: checkedVals('filter_product[]'),
        priorities: checkedVals('filter_priority[]').map(function (p) {
            return String(p).toLowerCase();
        })
    };
}

function clearAdvanceFilterState(form) {
    window.mpAdvanceFilter = { active: false };
    if (!form) return;
    form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        cb.checked = false;
        cb.indeterminate = false;
    });
    ['#filterDateFrom', '#filterDateTo', '#filterAdvTagNo', '#filterOrderNo', '#filterJwoNo'].forEach(function (sel) {
        var el = form.querySelector(sel);
        if (el) el.value = '';
    });
    form.querySelectorAll('.mp-ms-search').forEach(function (s) {
        s.value = '';
    });
    form.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
        lab.style.display = '';
    });
    form.querySelectorAll('[data-mp-ms]').forEach(function (w) {
        mpMsUpdateLabel(w);
    });
}

function initFilterModal() {
    var openBtn = document.getElementById('processFilterBtn');
    var overlay = document.getElementById('processFilterModalOverlay');
    var closeBtn = document.getElementById('processFilterCloseBtn');
    var form = document.getElementById('processFilterForm');
    if (!openBtn || !overlay || !closeBtn || !form) return;

    initMpMultiSelectDropdowns(form);

    function openModal() {
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        document.querySelectorAll('.mp-ms-panel.is-open').forEach(function (p) {
            p.classList.remove('is-open');
        });
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        window.mpAdvanceFilter = collectAdvanceFilterFromForm(form);
        var tagEl = document.getElementById('mpTagSearch');
        if (tagEl && window.mpAdvanceFilter.tag) {
            tagEl.value = window.mpAdvanceFilter.tag;
        }
        closeModal();
        if (typeof filterByDepartmentAndUser === 'function') {
            filterByDepartmentAndUser();
        }
    });

    form.addEventListener('reset', function () {
        setTimeout(function () {
            clearAdvanceFilterState(form);
            var tagEl = document.getElementById('mpTagSearch');
            if (tagEl) tagEl.value = '';
            if (typeof filterByDepartmentAndUser === 'function') {
                filterByDepartmentAndUser();
            }
        }, 0);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('show')) closeModal();
    });
}

function mpClosingParseFloat(v) {
    if (v === undefined || v === null) {
        return null;
    }
    var s = String(v).replace(/,/g, '').trim();
    if (s === '') {
        return null;
    }
    var n = parseFloat(s);
    return isFinite(n) ? n : null;
}

/** Net inward weight (grams) from Balance Stock (Metal Wt preferred). */
function mpClosingNetWtFromBalance() {
    var bal = window.__mpBalanceStock;
    if (bal && isFinite(bal.metalWt)) {
        return bal.metalWt;
    }
    var el = document.getElementById('mpInwardBalanceSummary');
    if (!el) {
        return null;
    }
    var txt = el.textContent || '';
    var m = /Metal\s*Wt\.:\s*([\d.+\-]+)/i.exec(txt) || /Wt\.:\s*([\d.+\-]+)/i.exec(txt);
    if (!m) {
        return null;
    }
    var n = parseFloat(m[1]);
    return isFinite(n) ? n : null;
}

function initClosingModal() {
    var overlay = document.getElementById('mpClosingModalOverlay');
    var openBtn = document.getElementById('mpClosingBtn');
    var form = document.getElementById('mpClosingForm');
    var closeHead = document.getElementById('mpClosingModalCloseBtn');
    var closeFoot = document.getElementById('mpClosingFooterCloseBtn');
    var dateReset = document.getElementById('mpClosingDateResetBtn');
    if (!overlay || !openBtn || !form) {
        return;
    }

    var lossEl = document.getElementById('mpClosingLossWt');
    var workEl = document.getElementById('mpClosingWorkDoneKg');
    var avgEl = document.getElementById('mpClosingAvgLossPerKg');
    var purPEl = document.getElementById('mpClosingPurityPer');
    var purWtEl = document.getElementById('mpClosingPurityWt');
    var rateEl = document.getElementById('mpClosingGoldRate');
    var glvEl = document.getElementById('mpClosingGoldLossValue');
    var dateEl = document.getElementById('mpClosingDate');
    var goldLossTouched = false;

    function isoToday() {
        var t = new Date();
        var mm = t.getMonth() + 1;
        var dd = t.getDate();
        return t.getFullYear() + '-' + (mm < 10 ? '0' : '') + mm + '-' + (dd < 10 ? '0' : '') + dd;
    }

    function recalcClosing() {
        var lossG = mpClosingParseFloat(lossEl ? lossEl.value : '');
        var workKg = mpClosingParseFloat(workEl ? workEl.value : '');
        workKg = workKg != null && workKg >= 0 ? workKg : 0;
        var purP = mpClosingParseFloat(purPEl ? purPEl.value : '');
        purP = purP != null && purP >= 0 ? purP : 0;
        var rate = mpClosingParseFloat(rateEl ? rateEl.value : '');
        rate = rate != null && rate >= 0 ? rate : 0;

        if (avgEl) {
            if (lossG != null && workKg > 0) {
                avgEl.value = (lossG / workKg).toFixed(4);
            } else {
                avgEl.value = '';
            }
        }
        if (purWtEl) {
            if (lossG != null && lossG >= 0 && purP > 0) {
                var purityFrac = purP > 1 ? purP / 100 : purP;
                if (purityFrac > 1) {
                    purityFrac = purP / 100;
                }
                var pw = lossG * purityFrac;
                purWtEl.value = pw.toFixed(3);
            } else if (lossG != null && lossG >= 0) {
                purWtEl.value = '0.000';
            } else {
                purWtEl.value = '';
            }
        }
        if (glvEl && !goldLossTouched) {
            if (lossG != null && rate > 0) {
                glvEl.value = (lossG * rate).toFixed(2);
            } else if (lossG != null && rate === 0) {
                glvEl.value = '0.00';
            } else {
                glvEl.value = '';
            }
        }
    }

    function openModal() {
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        goldLossTouched = false;
        var nw = mpClosingNetWtFromBalance();
        if (lossEl) {
            lossEl.value = nw != null ? nw.toFixed(3) : '';
        }
        if (workEl) {
            workEl.value = '';
        }
        if (avgEl) {
            avgEl.value = '';
        }
        if (purPEl) {
            purPEl.value = '';
        }
        if (purWtEl) {
            purWtEl.value = '';
        }
        if (rateEl) {
            rateEl.value = '';
        }
        if (glvEl) {
            glvEl.value = '';
        }
        if (dateEl) {
            dateEl.value = isoToday();
        }
        recalcClosing();
        if (workEl) {
            try {
                workEl.focus();
            } catch (e1) {}
        }
    }

    function closeModal() {
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    openBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
    });
    if (closeHead) {
        closeHead.addEventListener('click', closeModal);
    }
    if (closeFoot) {
        closeFoot.addEventListener('click', closeModal);
    }
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });
    if (dateReset && dateEl) {
        dateReset.addEventListener('click', function () {
            dateEl.value = isoToday();
        });
    }

    [workEl, purPEl, rateEl].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('input', recalcClosing);
        el.addEventListener('change', recalcClosing);
    });
    if (glvEl) {
        glvEl.addEventListener('input', function () {
            goldLossTouched = true;
        });
        glvEl.addEventListener('change', function () {
            goldLossTouched = true;
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var d = typeof selectedDeptId !== 'undefined' && selectedDeptId != null ? parseInt(selectedDeptId, 10) : 0;
        var u = typeof selectedUserId !== 'undefined' && selectedUserId != null ? parseInt(selectedUserId, 10) : 0;
        if (d < 1 || u < 1) {
            alert('Select a department and worker from the left sidebar before saving (e.g. open this page with ?department_id=…&user_id=… or pick a user under a department).');
            return;
        }
        var m = typeof mpClosingComputeMetricsSnapshot === 'function' ? mpClosingComputeMetricsSnapshot() : {};
        var fd = new FormData();
        fd.append('department_id', String(d));
        fd.append('department_user_id', String(u));
        fd.append('branch_id', '0');
        ['loss_wt', 'work_done_kg', 'avg_loss_per_kg', 'purity_per', 'purity_wt', 'gold_rate', 'gold_loss_value', 'closing_date'].forEach(function (k) {
            var el = document.getElementById(
                k === 'loss_wt' ? 'mpClosingLossWt' :
                k === 'work_done_kg' ? 'mpClosingWorkDoneKg' :
                k === 'avg_loss_per_kg' ? 'mpClosingAvgLossPerKg' :
                k === 'purity_per' ? 'mpClosingPurityPer' :
                k === 'purity_wt' ? 'mpClosingPurityWt' :
                k === 'gold_rate' ? 'mpClosingGoldRate' :
                k === 'gold_loss_value' ? 'mpClosingGoldLossValue' :
                'mpClosingDate'
            );
            fd.append(k, el ? String(el.value || '') : '');
        });
        fd.append('inward_wt', String(m.inward_wt != null ? m.inward_wt : ''));
        fd.append('outward_wt', String(m.outward_wt != null ? m.outward_wt : ''));
        fd.append('recovery_wt', String(m.recovery_wt != null ? m.recovery_wt : '0'));
        fd.append('closing_wt', String(m.closing_wt != null ? m.closing_wt : ''));
        fd.append('production_wt', String(m.production_wt != null ? m.production_wt : ''));
        fd.append('metal_weight', String(m.metal_weight != null ? m.metal_weight : ''));
        fd.append('difference_loss', String(m.difference_loss != null ? m.difference_loss : ''));
        fd.append('final_loss', String(m.final_loss != null ? m.final_loss : ''));
        fd.append('loss_percent', String(m.loss_percent != null ? m.loss_percent : ''));
        fd.append('closed_jobs', String(m.closed_jobs != null ? m.closed_jobs : '0'));
        fd.append('processed_jobs', String(m.processed_jobs != null ? m.processed_jobs : '0'));
        fd.append('total_jobs', String(m.total_jobs != null ? m.total_jobs : '0'));

        var saveBtn = document.getElementById('mpClosingSaveBtn');
        var prevText = saveBtn ? saveBtn.textContent : '';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
        }

        fetch('ajax/mp-save-manufacturing-closing.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    try {
                        localStorage.setItem(
                            'manufacturing_closing_last_' + d + '_' + u,
                            JSON.stringify({ savedAt: new Date().toISOString(), id: data.id })
                        );
                    } catch (e2) {}
                    closeModal();
                    if (typeof mpReloadManufacturingQueueTable === 'function') {
                        mpReloadManufacturingQueueTable();
                    } else if (typeof filterByDepartmentAndUser === 'function') {
                        filterByDepartmentAndUser();
                    }
                    alert(data.message || 'Closing saved.');
                } else {
                    alert((data && data.message) ? data.message : 'Could not save closing.');
                }
            })
            .catch(function () {
                alert('Network error while saving closing.');
            })
            .then(function () {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = prevText || 'Save';
                }
            });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !overlay.classList.contains('show')) {
            return;
        }
        closeModal();
    });
}

function initColumnManager(config) {
    var table = document.getElementById(config.tableId);
    var panel = document.getElementById(config.panelId);
    var toggleBtn = document.querySelector(config.toggleSelector);
    var searchInput = document.getElementById(config.searchId);
    var listContainer = document.getElementById(config.listId);
    if (!table || !panel || !toggleBtn || !listContainer) return;

    function applyHiddenColumns(hiddenCols) {
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hiddenCols.indexOf(col) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
        if (config.tableId === 'inwardTable' || config.tableId === 'outwardTable') {
            if (typeof mpApplyInwardOutwardStockHidden === 'function') {
                mpApplyInwardOutwardStockHidden();
            }
        }
    }

    function readHiddenFromStorage() {
        try {
            var raw = localStorage.getItem(config.storageKey);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveHiddenToStorage(hiddenCols) {
        try {
            localStorage.setItem(config.storageKey, JSON.stringify(hiddenCols));
        } catch (e) {}
    }

    function collectHiddenFromCheckboxes() {
        var hidden = [];
        listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            if (!cb.checked) hidden.push(cb.getAttribute('data-col'));
        });
        return hidden;
    }

    function syncCheckboxesFromHidden(hiddenCols) {
        listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            var col = cb.getAttribute('data-col');
            cb.checked = hiddenCols.indexOf(col) === -1;
        });
    }

    function positionPanel() {
        if (config.panelLayout === 'inline') {
            panel.style.position = 'static';
            panel.style.left = '';
            panel.style.top = '';
            panel.style.width = '100%';
            panel.style.maxWidth = '';
            return;
        }
        /* Job card print: column picker as bottom sheet aligned to drawer width */
        if (config.panelPosition === 'bottom') {
            panel.style.position = 'fixed';
            panel.style.top = 'auto';
            panel.style.bottom = '0';
            panel.style.marginTop = '0';
            panel.style.marginBottom = '0';
            panel.style.borderRadius = '12px 12px 0 0';
            panel.style.maxHeight = 'min(55vh, 440px)';
            var drawer = document.getElementById('mpJobCardPrintDrawer');
            if (drawer && drawer.classList.contains('open')) {
                var r = drawer.getBoundingClientRect();
                panel.style.left = Math.round(r.left) + 'px';
                panel.style.width = Math.round(r.width) + 'px';
                panel.style.right = 'auto';
            } else {
                panel.style.left = '0';
                panel.style.width = '100%';
                panel.style.right = '0';
            }
            return;
        }
        /* Anchor inside modal toolbar — avoids broken fixed positioning when .modal-dialog uses transform */
        if (config.panelPosition === 'absolute') {
            panel.style.position = 'absolute';
            panel.style.left = 'auto';
            panel.style.right = '0';
            panel.style.top = '100%';
            panel.style.bottom = 'auto';
            panel.style.marginTop = '6px';
            return;
        }
        panel.style.position = 'fixed';
        var btnRect = toggleBtn.getBoundingClientRect();
        var panelWidth = panel.offsetWidth || 250;
        var panelHeight = panel.offsetHeight || 280;
        var gap = 6;
        var left = btnRect.right - panelWidth;
        var top = btnRect.bottom + gap;

        if (left < 8) left = 8;
        if (left + panelWidth > window.innerWidth - 8) {
            left = window.innerWidth - panelWidth - 8;
        }

        if (top + panelHeight > window.innerHeight - 8) {
            top = btnRect.top - panelHeight - gap;
            if (top < 8) top = 8;
        }

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    var initialHidden = readHiddenFromStorage();
    syncCheckboxesFromHidden(initialHidden);
    applyHiddenColumns(initialHidden);

    listContainer.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var hidden = collectHiddenFromCheckboxes();
            saveHiddenToStorage(hidden);
            applyHiddenColumns(hidden);
        });
    });

    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        document.querySelectorAll('.columns-panel.show').forEach(function (p) {
            if (p.id !== config.panelId) p.classList.remove('show');
        });
        var willShow = !panel.classList.contains('show');
        panel.classList.toggle('show');
        if (willShow) positionPanel();
    });

    var closeBtn = panel.querySelector('[data-close-panel]');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            panel.classList.remove('show');
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var term = (searchInput.value || '').toLowerCase().trim();
            listContainer.querySelectorAll('[data-label]').forEach(function (row) {
                var labelText = row.getAttribute('data-label') || '';
                row.style.display = labelText.indexOf(term) >= 0 ? '' : 'none';
            });
        });
    }

    window.addEventListener('resize', function () {
        if (panel.classList.contains('show') && config.panelLayout !== 'inline') positionPanel();
    });
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.columns-panel') && !e.target.closest('.head-setting-btn')) {
        document.querySelectorAll('.columns-panel.show').forEach(function (p) {
            p.classList.remove('show');
        });
    }
});

function alignMpTipBubble(wrap) {
    var bubble = wrap.querySelector('.mp-tip-bubble');
    if (!bubble) return;
    bubble.classList.remove('mp-tip-bubble--start', 'mp-tip-bubble--end');
    requestAnimationFrame(function () {
        var br = bubble.getBoundingClientRect();
        var bounds = wrap.closest('.mp-job-card');
        bounds = bounds ? bounds.getBoundingClientRect() : { left: 8, right: window.innerWidth - 8 };
        if (br.left < bounds.left + 4) {
            bubble.classList.add('mp-tip-bubble--start');
        } else if (br.right > bounds.right - 4) {
            bubble.classList.add('mp-tip-bubble--end');
        }
    });
}

function initMpJobActionTooltips(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.mp-job-actions-row > [title]').forEach(function (el) {
        if (el.closest('.mp-action-with-tip')) return;
        var tip = el.getAttribute('title');
        if (!tip) return;
        el.removeAttribute('title');

        var wrap = document.createElement('span');
        wrap.className = 'mp-action-with-tip';
        el.parentNode.insertBefore(wrap, el);
        wrap.appendChild(el);

        var bubble = document.createElement('span');
        bubble.className = 'mp-tip-bubble';
        bubble.setAttribute('role', 'tooltip');
        bubble.textContent = tip;
        wrap.appendChild(bubble);

        wrap.addEventListener('mouseenter', function () {
            alignMpTipBubble(wrap);
        });
        wrap.addEventListener('mouseleave', function () {
            bubble.classList.remove('mp-tip-bubble--start', 'mp-tip-bubble--end');
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initMpJobActionTooltips();
    initFilterModal();
    initClosingModal();
    initViewStockModal();

    initColumnManager({
        tableId: 'inwardTable',
        panelId: 'inwardColumnsPanel',
        toggleSelector: '.inward-settings-toggle',
        searchId: 'inwardColumnsSearch',
        listId: 'inwardColumnsList',
        storageKey: 'manufacturing_process_inward_hidden_columns'
    });

    initColumnManager({
        tableId: 'outwardTable',
        panelId: 'outwardColumnsPanel',
        toggleSelector: '.outward-settings-toggle',
        searchId: 'outwardColumnsSearch',
        listId: 'outwardColumnsList',
        storageKey: 'manufacturing_process_outward_hidden_columns'
    });

    initColumnManager({
        tableId: 'jwqOrderLinesTable',
        panelId: 'jwqColumnsPanel',
        toggleSelector: '.jwq-settings-toggle',
        searchId: 'jwqColumnsSearch',
        listId: 'jwqColumnsList',
        storageKey: 'manufacturing_process_jwq_order_lines_hidden_columns',
        panelPosition: 'absolute'
    });

    if (typeof AuragoldColReorder !== 'undefined') {
        AuragoldColReorder.init('#jwqOrderLinesTable', {
            storageKey: 'manufacturing_process_jwq_order_lines_column_order',
            widthsStorageKey: 'manufacturing_process_jwq_order_lines_column_widths',
            minWidth: 72
        });
    }

    initColumnManager({
        tableId: 'jwqInwardStockTable',
        panelId: 'jwqInwardStockColumnsPanel',
        toggleSelector: '#jwqInwardStockBtnColumns',
        searchId: 'jwqInwardStockColumnsSearch',
        listId: 'jwqInwardStockColumnsList',
        storageKey: 'manufacturing_process_jwq_inward_modal_hidden_columns',
        panelPosition: 'absolute'
    });

    initColumnManager({
        tableId: 'fullStockTable',
        panelId: 'mpMfgQueueColumnsPanel',
        toggleSelector: '#mpMfgQueueColumnsToggle',
        searchId: 'mpMfgQueueColumnsSearch',
        listId: 'mpMfgQueueColumnsList',
        storageKey: 'manufacturing_process_mfg_queue_hidden_columns'
    });

    var jwqInEx = document.getElementById('jwqInwardStockBtnExcel');
    var jwqInPdf = document.getElementById('jwqInwardStockBtnPdf');
    if (jwqInEx) jwqInEx.addEventListener('click', function (e) { e.preventDefault(); jwqInwardStockExportExcel(); });
    if (jwqInPdf) jwqInPdf.addEventListener('click', function (e) { e.preventDefault(); jwqInwardStockExportPdf(); });

    document.querySelectorAll('.mp-view-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-mp-view');
            if (mode) setMpViewMode(mode);
        });
    });

    initManufacturingJobCardTimers();
    initMpAttachImageButtons();
    if (typeof initMpJwqAddImageModal === 'function') {
        initMpJwqAddImageModal();
    }
    initMpTagSearchFilter();
    initMpExportDropdown();
    initJwqDecimalInputGuards();
    initJobworkQueueModal();
    initMpBulkTransfer();
    mpInitStockGrandTotalScrollSync();
    if (typeof mpSyncAllStockGrandTotalWidths === 'function') {
        window.addEventListener('resize', mpSyncAllStockGrandTotalWidths);
    }
    mpJcpPopulateColumnPanelLists();
    initColumnManager({
        tableId: 'mpJcpHistoryTable',
        panelId: 'mpJcpHistoryColumnsPanel',
        toggleSelector: '#mpJobCardPrintDrawer .mp-jcp-history-cols-toggle',
        searchId: 'mpJcpHistoryColumnsSearch',
        listId: 'mpJcpHistoryColumnsList',
        storageKey: 'manufacturing_process_jcp_history_hidden_columns',
        panelPosition: 'absolute'
    });
    initColumnManager({
        tableId: 'mpJcpSummaryTable',
        panelId: 'mpJcpSummaryColumnsPanel',
        toggleSelector: '#mpJobCardPrintDrawer .mp-jcp-summary-cols-toggle',
        searchId: 'mpJcpSummaryColumnsSearch',
        listId: 'mpJcpSummaryColumnsList',
        storageKey: 'manufacturing_process_jcp_summary_hidden_columns',
        panelPosition: 'absolute'
    });
    initMpJobCardPrintDrawer();
    var jwqLinesTbody = document.getElementById('jwqOrderLinesBody');
    if (jwqLinesTbody && !jwqLinesTbody._jwqAutoLossBound) {
        jwqLinesTbody._jwqAutoLossBound = true;
        jwqLinesTbody.addEventListener('input', jwqHandleOrderLineWeightInput);
        jwqLinesTbody.addEventListener('change', jwqHandleOrderLineWeightInput);
    }
    if (jwqLinesTbody && !jwqLinesTbody._jwqDiamondMaterialLineBound) {
        jwqLinesTbody._jwqDiamondMaterialLineBound = true;
        jwqLinesTbody.addEventListener('focusin', function (e) {
            var tr = e.target && e.target.closest ? e.target.closest('tr[data-item-id]') : null;
            if (!tr || !jwqLinesTbody.contains(tr)) {
                return;
            }
            var iid = parseInt(tr.getAttribute('data-item-id') || '0', 10) || 0;
            if (iid > 0) {
                window.__jwqDiamondMaterialItemId = iid;
            }
        });
    }
    initJwqPaymentIcons();
    if (typeof mpInitManufacturingQueueTableActions === 'function') {
        mpInitManufacturingQueueTableActions();
    }
    if (typeof mpReloadManufacturingQueueTable === 'function') {
        mpReloadManufacturingQueueTable();
    }
    if (typeof filterByDepartmentAndUser === 'function') {
        filterByDepartmentAndUser();
    }
});

// Department and User selection — selectedUserId === null means show ALL
var selectedDeptId = null;
var selectedUserId = null;
/** Raw rows from ajax/mp-manufacturing-queue-table.php (re-filter when sidebar changes without refetch). */
window.__mpMfgQueueRowsRaw = null;

function setAllJobsNavActive(active) {
    var link = document.querySelector('.mp-all-link');
    if (link) link.classList.toggle('active', !!active);
}

function applyAllManufacturingContext() {
    var titleEl = document.getElementById('mpSelectedUserTitle');
    var deptLbl = document.getElementById('mpSelectedDeptLabel');
    if (titleEl) titleEl.textContent = 'All';
    if (deptLbl) deptLbl.textContent = window.mpAllJobsSubtitle || 'All departments · all users';
}

function showAllManufacturing(e, anchor) {
    if (e && e.stopPropagation) e.stopPropagation();
    selectedUserId = null;
    selectedDeptId = null;

    document.querySelectorAll('.dept-user-list li a').forEach(function (a) {
        a.classList.remove('active');
    });
    document.querySelectorAll('.dept-list > li[data-dept-id]').forEach(function (item) {
        item.classList.remove('expanded');
        var ha = item.querySelector(':scope > a');
        if (ha) ha.classList.remove('active');
    });

    setAllJobsNavActive(true);
    applyAllManufacturingContext();
    filterByDepartmentAndUser();
}

function setMpViewMode(mode) {
    var cardsPanel = document.getElementById('mpPanelCards');
    var stockPanel = document.getElementById('mpPanelStock');
    var splitWrap = document.getElementById('mpStockSplitWrap');
    var tableWrap = document.getElementById('mpStockTableWrap');
    var btnSplit = document.getElementById('mpTransferBtn');
    var btnTable = document.getElementById('mpViewStockBtn');
    var btnCards = document.getElementById('mpViewCardsBtn');
    if (!cardsPanel || !stockPanel) return;

    [btnSplit, btnTable, btnCards].forEach(function (b) {
        if (b) b.classList.remove('is-active');
    });

    if (mode === 'cards') {
        cardsPanel.classList.add('is-active');
        stockPanel.classList.remove('is-active');
        if (btnCards) btnCards.classList.add('is-active');
        return;
    }

    cardsPanel.classList.remove('is-active');
    stockPanel.classList.add('is-active');

    if (mode === 'stock-split') {
        if (splitWrap) splitWrap.style.display = '';
        if (tableWrap) {
            tableWrap.style.display = 'none';
            tableWrap.classList.remove('is-visible');
        }
        if (btnSplit) btnSplit.classList.add('is-active');
        if (typeof mpSyncAllStockGrandTotalWidths === 'function') {
            requestAnimationFrame(mpSyncAllStockGrandTotalWidths);
        }
    } else if (mode === 'stock-table') {
        if (splitWrap) splitWrap.style.display = 'none';
        if (tableWrap) {
            tableWrap.style.display = 'flex';
            tableWrap.classList.add('is-visible');
        }
        if (btnTable) btnTable.classList.add('is-active');
        if (typeof mpReloadManufacturingQueueTable === 'function') {
            mpReloadManufacturingQueueTable();
        }
    }
}

function applyUserContextToDemoCard(anchor) {
    var deptName = (anchor.getAttribute('data-dept-name') || '').toUpperCase();
    var nameSpan = anchor.querySelector('span');
    var userName = nameSpan ? nameSpan.textContent.trim() : '';
    var titleEl = document.getElementById('mpSelectedUserTitle');
    var deptLbl = document.getElementById('mpSelectedDeptLabel');
    if (titleEl) titleEl.textContent = userName || '—';
    if (deptLbl) deptLbl.textContent = deptName ? ('Department: ' + deptName + ' · ' + (userName || 'User')) : '';
}

function toggleDepartment(anchor, deptId) {
    var li = anchor.parentElement;
    var wasExpanded = li.classList.contains('expanded');

    document.querySelectorAll('.dept-list > li.expanded').forEach(function(item) {
        if (item !== li) {
            item.classList.remove('expanded');
            var a = item.querySelector(':scope > a');
            if (a) a.classList.remove('active');
        }
    });

    if (wasExpanded) {
        li.classList.remove('expanded');
        anchor.classList.remove('active');
        selectedDeptId = null;
    } else {
        li.classList.add('expanded');
        anchor.classList.add('active');
        selectedDeptId = deptId;
    }

    document.querySelectorAll('.dept-user-list li a').forEach(function(a) {
        if (a.classList.contains('active')) a.classList.remove('active');
    });
    selectedUserId = null;

    // Only highlight "All jobs" when no department filter is active (was always true before, so All looked selected while list was department-filtered).
    setAllJobsNavActive(selectedDeptId == null);
    if (selectedDeptId != null) {
        var dn = anchor.querySelector('span');
        var dtxt = dn ? dn.textContent.replace(/\s*›\s*$/, '').trim() : '';
        var titleEl = document.getElementById('mpSelectedUserTitle');
        var deptLbl = document.getElementById('mpSelectedDeptLabel');
        if (titleEl) titleEl.textContent = dtxt || 'Department';
        if (deptLbl) deptLbl.textContent = 'Department filter · all workers in this department';
    } else {
        applyAllManufacturingContext();
    }
    filterByDepartmentAndUser();
    /* Keep current view tab unchanged when switching department. */
}

function selectUser(e, anchor, userId, deptId) {
    if (e && e.stopPropagation) e.stopPropagation();

    setAllJobsNavActive(false);

    document.querySelectorAll('.dept-user-list li a').forEach(function(a) {
        a.classList.remove('active');
    });
    anchor.classList.add('active');
    selectedUserId = userId;
    selectedDeptId = deptId;

    var deptLi = anchor.closest('.dept-list > li');
    if (deptLi) {
        document.querySelectorAll('.dept-list > li').forEach(function(item) {
            var isThis = item === deptLi;
            item.classList.toggle('expanded', isThis);
            var ha = item.querySelector(':scope > a');
            if (ha) ha.classList.toggle('active', isThis);
        });
    }

    applyUserContextToDemoCard(anchor);
    filterByDepartmentAndUser();
}

function getMpTagSearchQuery() {
    var el = document.getElementById('mpTagSearch');
    if (!el) return '';
    return String(el.value || '').trim().toLowerCase();
}

/** Hide target is .mp-job-card-grid-item when present (grid wrapper), else the card. */
function mpSetJobCardFilteredVisible(card, show) {
    var wrap = card.closest('.mp-job-card-grid-item');
    if (wrap) {
        wrap.style.display = show ? '' : 'none';
        card.style.display = '';
    } else {
        card.style.display = show ? '' : 'none';
    }
}

function mpJobCardIsFilteredHidden(card) {
    var wrap = card.closest('.mp-job-card-grid-item');
    if (wrap && wrap.style.display === 'none') return true;
    return card.style.display === 'none';
}

function filterByDepartmentAndUser() {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid) return;
    var cards = grid.querySelectorAll('.mp-job-card[data-jwo-id]');
    var total = cards.length;
    var visible = 0;
    var tagQ = getMpTagSearchQuery();
    var adv = window.mpAdvanceFilter && window.mpAdvanceFilter.active ? window.mpAdvanceFilter : null;
    if (adv && adv.tag) {
        tagQ = adv.tag.toLowerCase();
    }
    cards.forEach(function (card) {
        var dAttr = card.getAttribute('data-dept-id');
        var uAttr = card.getAttribute('data-user-id');
        var d = dAttr ? parseInt(dAttr, 10) : 0;
        var u = uAttr ? parseInt(uAttr, 10) : 0;
        var show = true;
        if (selectedUserId != null && selectedUserId > 0) {
            show = show && (u === parseInt(selectedUserId, 10));
            // User is chosen under a department: also match current JWO department (was user-only, so Polish jobs appeared under Casting).
            if (selectedDeptId != null && selectedDeptId > 0) {
                show = show && (d === parseInt(selectedDeptId, 10));
            }
        } else if (selectedDeptId != null && selectedDeptId > 0) {
            show = show && (d === parseInt(selectedDeptId, 10));
        }
        if (tagQ) {
            var tn = (card.getAttribute('data-tag-no') || '').trim().toLowerCase();
            show = show && tn.indexOf(tagQ) !== -1;
        }
        if (adv && show) {
            if (adv.dueFrom) {
                var dd = (card.getAttribute('data-due-date') || '').trim();
                if (!dd || dd < adv.dueFrom) show = false;
            }
            if (adv.dueTo && show) {
                var dd2 = (card.getAttribute('data-due-date') || '').trim();
                if (!dd2 || dd2 > adv.dueTo) show = false;
            }
            if (adv.branches && adv.branches.length && show) {
                var bid = parseInt(card.getAttribute('data-branch-id') || '0', 10);
                if (bid > 0) {
                    show = adv.branches.indexOf(String(bid)) >= 0;
                }
                /* Cards without branch on sale order stay visible when branch filter is on */
            }
            if (adv.metals && adv.metals.length && show) {
                var mid = parseInt(card.getAttribute('data-metal-id') || '0', 10);
                show = mid > 0 && adv.metals.indexOf(String(mid)) >= 0;
            }
            if (adv.products && adv.products.length && show) {
                var pid = parseInt(card.getAttribute('data-product-id') || '0', 10);
                show = pid > 0 && adv.products.indexOf(String(pid)) >= 0;
            }
            if (adv.priorities && adv.priorities.length && show) {
                var pr = (card.getAttribute('data-priority') || '').toLowerCase().trim();
                show = adv.priorities.indexOf(pr) >= 0;
            }
            if (adv.orderNo && show) {
                var so = (card.getAttribute('data-sale-order-no') || '').toUpperCase().replace(/\s+/g, '');
                var want = adv.orderNo.toUpperCase().replace(/\s+/g, '');
                show = want === '' || so.indexOf(want) !== -1 || want.indexOf(so) !== -1;
            }
            if (adv.jwoNo && show) {
                var jn = (card.getAttribute('data-jobwork-no') || '').toUpperCase().replace(/\s+/g, '');
                var jw = adv.jwoNo.toUpperCase().replace(/\s+/g, '');
                show = jw === '' || jn.indexOf(jw) !== -1 || jw.indexOf(jn) !== -1;
            }
        }
        mpSetJobCardFilteredVisible(card, show);
        if (show) visible++;
    });
    var pag = document.getElementById('mpCardsPaginationText');
    if (pag && total > 0) {
        var hasTag = tagQ.length > 0;
        var hasDept = selectedDeptId != null && selectedDeptId > 0;
        var hasUser = selectedUserId != null && selectedUserId > 0;
        var hasAdv = adv && (
            adv.dueFrom || adv.dueTo || adv.tag ||
            (adv.branches && adv.branches.length) ||
            (adv.metals && adv.metals.length) || (adv.products && adv.products.length) ||
            (adv.priorities && adv.priorities.length) || adv.orderNo || adv.jwoNo
        );
        if (!hasTag && !hasDept && !hasUser && !hasAdv) {
            pag.textContent = 'Showing ' + total + ' job work order' + (total === 1 ? '' : 's');
        } else {
            var bits = [];
            if (hasAdv) bits.push('advance');
            if (hasTag) bits.push('tag');
            if (hasDept) bits.push('department');
            if (hasUser) bits.push('worker');
            pag.textContent = 'Showing ' + visible + ' of ' + total + ' job work orders (' + bits.join(' + ') + ' filter)';
        }
    }
    /* Keep currently selected view tab; only refresh filtered content. */
    if (typeof mpRenderManufacturingQueueTablesFromCache === 'function' && window.__mpMfgQueueRowsRaw != null && Array.isArray(window.__mpMfgQueueRowsRaw)) {
        mpRenderManufacturingQueueTablesFromCache();
    }
    if (typeof mpUpdateBulkSelectAllState === 'function') {
        mpUpdateBulkSelectAllState();
    }
}

function initMpTagSearchFilter() {
    var inp = document.getElementById('mpTagSearch');
    if (!inp || inp._mpTagBound) return;
    inp._mpTagBound = true;
    inp.addEventListener('input', function () {
        if (typeof filterByDepartmentAndUser === 'function') {
            filterByDepartmentAndUser();
        }
    });
    inp.addEventListener('search', function () {
        if (typeof filterByDepartmentAndUser === 'function') {
            filterByDepartmentAndUser();
        }
    });
}

function formatMpTimerHMS(totalSeconds) {
    var h = Math.floor(totalSeconds / 3600);
    var m = Math.floor((totalSeconds % 3600) / 60);
    var s = totalSeconds % 60;
    function z(n) { return (n < 10 ? '0' : '') + n; }
    return z(h) + ':' + z(m) + ':' + z(s);
}

function getMpCardTimerState(card) {
    if (!card._mpTimer) card._mpTimer = { seconds: 0, intervalId: null };
    return card._mpTimer;
}

function mpSaveManufacturingTimer(card, seconds) {
    var id = parseInt(card.getAttribute('data-jwo-id'), 10);
    if (!id || id < 1) return;
    var sec = parseInt(seconds, 10);
    if (isNaN(sec) || sec < 0) sec = 0;
    if (sec > 999999999) sec = 999999999;
    var fd = new FormData();
    fd.append('jobwork_order_id', String(id));
    fd.append('seconds', String(sec));
    fetch('ajax/mp-save-manufacturing-timer.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.ok && typeof data.seconds === 'number') {
                card.setAttribute('data-manufacturing-seconds', String(data.seconds));
                var st = getMpCardTimerState(card);
                st.seconds = data.seconds;
                renderMpCardTimer(card, st.seconds);
            }
        })
        .catch(function () {});
}

function setMpTimerButtonUi(btn, running) {
    var icon = btn.querySelector('i');
    var label = running ? 'Pause timer' : 'Start timer';
    btn.setAttribute('title', label);
    btn.setAttribute('aria-label', label);
    if (icon) icon.className = running ? 'feather icon-pause' : 'feather icon-play';
}

function renderMpCardTimer(card, seconds) {
    var el = card.querySelector('.mp-timer-display');
    if (el) el.textContent = formatMpTimerHMS(seconds);
}

function initManufacturingJobCardTimers() {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid || grid._mpTimerBound) return;
    grid._mpTimerBound = true;
    grid.querySelectorAll('.mp-job-card').forEach(function (card) {
        var raw = card.getAttribute('data-manufacturing-seconds');
        var sec = parseInt(raw, 10);
        if (isNaN(sec) || sec < 0) sec = 0;
        var st = getMpCardTimerState(card);
        st.seconds = sec;
        renderMpCardTimer(card, st.seconds);
    });
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.mp-timer-toggle');
        if (!btn || !grid.contains(btn)) return;
        e.preventDefault();
        var card = btn.closest('.mp-job-card');
        if (!card) return;
        var st = getMpCardTimerState(card);
        if (st.intervalId) {
            clearInterval(st.intervalId);
            st.intervalId = null;
            setMpTimerButtonUi(btn, false);
            mpSaveManufacturingTimer(card, st.seconds);
        } else {
            st.intervalId = setInterval(function () {
                st.seconds += 1;
                renderMpCardTimer(card, st.seconds);
            }, 1000);
            setMpTimerButtonUi(btn, true);
        }
    });
}

function initMpAttachImageButtons() {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid || grid._mpAttachBound) return;
    grid._mpAttachBound = true;
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.mp-attach-image-btn');
        if (!btn || !grid.contains(btn)) return;
        e.preventDefault();
        var card = btn.closest('.mp-job-card');
        if (!card) return;
        var input = card.querySelector('.mp-attach-image-input');
        if (input) input.click();
    });
    grid.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.classList || !input.classList.contains('mp-attach-image-input')) return;
        if (input.files && input.files.length) {
            /* Upload or preview hook — file is input.files[0] */
        }
    });
}

function jwqEsc(s) {
    if (s == null || s === '') return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

function jwqAttrEsc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

/** Open Jobwork Queue modal from a manufacturing-queue table row (same data as job card Transfer). */
function mpOpenJwqFromQueueTr(tr) {
    if (!tr || typeof jwqOpenModal !== 'function') {
        return;
    }
    var jid = parseInt(tr.getAttribute('data-jwo-id') || '0', 10);
    if (jid < 1) {
        return;
    }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-jwo-id', String(jid));
    btn.setAttribute('data-jobwork-queue-no', tr.getAttribute('data-jobwork-queue-no') || '');
    btn.setAttribute('data-dept-id', tr.getAttribute('data-dept-id') || '');
    btn.setAttribute('data-user-id', tr.getAttribute('data-user-id') || '');
    btn.setAttribute('data-jobwork-no', tr.getAttribute('data-jobwork-no') || '');
    btn.setAttribute('data-sale-order-no', tr.getAttribute('data-sale-order-no') || '');
    btn.setAttribute('data-first-product', tr.getAttribute('data-first-product') || '');
    btn.setAttribute('data-item-barcode', tr.getAttribute('data-item-barcode') || tr.getAttribute('data-tag-no') || '');
    btn.setAttribute('data-floor-transfer', (tr.getAttribute('data-floor-transfer') || '0').trim() || '0');
    btn.setAttribute('title', 'Edit');
    btn.setAttribute('aria-label', 'Edit');
    var mfg = tr.getAttribute('data-manufacturing-seconds');
    if (mfg !== null && mfg !== '') {
        btn.setAttribute('data-manufacturing-seconds', mfg);
    }
    jwqOpenModal(btn, { actionLabel: 'Edit' });
}

function mpInitManufacturingQueueTableActions() {
    ['fullStockTable', 'inwardTable', 'outwardTable'].forEach(function (tableId) {
        var table = document.getElementById(tableId);
        if (!table || table._mpMfgQueueActBound) {
            return;
        }
        table._mpMfgQueueActBound = true;
        table.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.mp-mfg-queue-edit');
            if (editBtn && table.contains(editBtn)) {
                e.preventDefault();
                mpOpenJwqFromQueueTr(editBtn.closest('tr'));
                return;
            }
        });
    });
}

function mpFetchManufacturingQueueRows(jobworkOrderId) {
    var url = 'ajax/mp-manufacturing-queue-table.php';
    var jid = jobworkOrderId != null ? parseInt(jobworkOrderId, 10) : 0;
    if (jid > 0) {
        url += '?jobwork_order_id=' + encodeURIComponent(String(jid));
    }
    return fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!(jid > 0)) {
                window.__mpJobworkLocationTotals = (data && data.jobwork_location_totals) ? data.jobwork_location_totals : [];
            }
            return (data && data.ok && data.rows) ? data.rows : [];
        });
}

function formatMpJobCardHMS(totalSeconds) {
    var sec = parseInt(totalSeconds, 10);
    if (isNaN(sec) || sec < 0) {
        sec = 0;
    }
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    function z(n) { return (n < 10 ? '0' : '') + n; }
    return h + 'H ' + z(m) + 'M ' + z(s) + 'S';
}

window.__mpJcpSortedRows = [];
window.__mpJcpMfgSeconds = 0;

function mpJcpHistoryNumericKeys() {
    return ['qty', 'gross_wt', 'other_wt', 'loss_wt', 'profit_wt', 'gold_wt', 'diamond_wt', 'price', 'metal_wt', 'changed_wt'];
}

function mpJcpHistoryColIsNumeric(key) {
    return mpJcpHistoryNumericKeys().indexOf(key) >= 0;
}

function mpJcpDeptFlowText(row) {
    row = row || {};
    var df = row.department_flow != null ? String(row.department_flow).trim() : '';
    if (df !== '') {
        return df;
    }
    var parts = [];
    var st = String(row.stock_flow_type || '').trim();
    if (st) {
        parts.push(st);
    }
    var dn = String(row.department_name || '').trim();
    if (dn) {
        parts.push(dn);
    }
    var un = String(row.user_name || '').trim();
    if (un) {
        parts.push(un);
    }
    var cm = String(row.comment || '').trim();
    if (cm && parts.indexOf(cm) < 0) {
        parts.push(cm);
    }
    return parts.length ? parts.join(' · ') : '—';
}

function mpJcpHistoryCellRaw(row, key, mfgFallback) {
    row = row || {};
    var ev = String(row.weight_event || '').trim();
    var rec = mpParseStockNumeric(row.receive_wt);
    switch (key) {
        case 'active':
            return row.active != null ? String(row.active) : '—';
        case 'date':
            return row.date_time != null ? String(row.date_time) : '—';
        case 'sr_no':
            return row.queue_no != null ? String(row.queue_no) : '—';
        case 'description':
            return row.description != null ? String(row.description) : '—';
        case 'qty':
            return row.total_quantity != null ? String(row.total_quantity) : '—';
        case 'gross_wt':
            return row.total_wt != null ? String(row.total_wt) : '—';
        case 'other_wt':
            return row.dust_wastage_wt != null ? String(row.dust_wastage_wt) : '—';
        case 'loss_wt':
            return row.loss_wt != null ? String(row.loss_wt) : '—';
        case 'profit_wt':
            return row.profit_wt != null ? String(row.profit_wt) : '—';
        case 'gold_wt':
            return row.metal_wt != null ? String(row.metal_wt) : '—';
        case 'diamond_wt': {
            if (row.diamond_wt != null && String(row.diamond_wt).trim() !== '' && String(row.diamond_wt).trim() !== '—') {
                return String(row.diamond_wt);
            }
            if (row.diamond_weight != null && String(row.diamond_weight).trim() !== '' && String(row.diamond_weight).trim() !== '—') {
                return String(row.diamond_weight);
            }
            return '—';
        }
        case 'spent_time': {
            var ms = row.manufacturing_seconds != null ? parseInt(row.manufacturing_seconds, 10) : 0;
            if (!isFinite(ms) || ms < 1) {
                ms = parseInt(mfgFallback, 10) || 0;
            }
            return formatMpJobCardHMS(ms);
        }
        case 'price':
            if (row.price != null && String(row.price).trim() !== '') {
                return String(row.price);
            }
            return '—';
        case 'metal_wt':
            return row.purity_wt != null ? String(row.purity_wt) : '—';
        case 'dept_flow':
            return mpJcpDeptFlowText(row);
        case 'changed_wt':
            return row.total_wt != null ? String(row.total_wt) : '—';
        case 'is_add_weight':
            return ev === 'add' ? 'Yes' : 'No';
        case 'is_return_weight':
            return (rec !== null && rec > 0.000001) ? 'Yes' : 'No';
        default:
            return '—';
    }
}

function mpJcpParseRowDate(s) {
    if (s == null || s === '' || s === '—') {
        return 0;
    }
    var str = String(s);
    var m = str.match(/^(\d{2})-(\d{2})-(\d{4})\s+(\d{2}):(\d{2}):(\d{2})/);
    if (m) {
        return new Date(+m[3], +m[2] - 1, +m[1], +m[4], +m[5], +m[6]).getTime();
    }
    var t = Date.parse(str);
    return isNaN(t) ? 0 : t;
}

function mpJcpFmtIsoSlash(iso) {
    var raw = String(iso || '').trim();
    if (!raw) {
        return '—';
    }
    var p = raw.split('-');
    if (p.length === 3) {
        return p[2] + '/' + p[1] + '/' + p[0];
    }
    return raw;
}

function mpJcpRenderBarcode(tag) {
    var svg = document.getElementById('mpJcpBarcodeSvg');
    var blk = document.getElementById('mpJcpBarcodeBlock');
    var lbl = document.getElementById('mpJcpBarcodeText');
    if (lbl) {
        lbl.textContent = tag || '—';
    }
    if (!svg) {
        return;
    }
    while (svg.firstChild) {
        svg.removeChild(svg.firstChild);
    }
    var t = String(tag || '').trim();
    if (!t || t === '—') {
        if (blk) {
            blk.style.display = 'none';
        }
        return;
    }
    if (blk) {
        blk.style.display = '';
    }
    if (typeof JsBarcode === 'function') {
        try {
            JsBarcode(svg, t, { format: 'code128', displayValue: false, height: 44, margin: 2, width: 1.6 });
        } catch (e1) {
            svg.innerHTML = '';
        }
    }
}

function mpJcpBuildSummaryRows(historyRows, mfgSeconds) {
    var byDept = {};
    (historyRows || []).forEach(function (row) {
        var flowSrc = String(row.flow_source != null ? row.flow_source : '').trim();
        if (flowSrc === 'department_transfer') {
            var srcDept = String(row.src_department_name != null ? row.src_department_name : '').trim();
            var lossN = mpParseStockNumeric(row.loss_wt);
            if (srcDept && srcDept !== '—' && lossN !== null && lossN > 0.000001) {
                if (!byDept[srcDept]) {
                    byDept[srcDept] = { issue: 0, ret: 0 };
                }
                byDept[srcDept].issue += lossN;
            }
            return;
        }
        var dname = String(row.department_name != null ? row.department_name : '').trim() || '—';
        if (!byDept[dname]) {
            byDept[dname] = { issue: 0, ret: 0 };
        }
        var iw = mpParseStockNumeric(row.issue_wt);
        var rw = mpParseStockNumeric(row.receive_wt);
        if (iw !== null) {
            byDept[dname].issue += iw;
        }
        if (rw !== null) {
            byDept[dname].ret += rw;
        }
    });
    var ms = parseInt(mfgSeconds, 10) || 0;
    var timeDisp = formatMpJobCardHMS(ms);
    return Object.keys(byDept).sort().map(function (k) {
        var o = byDept[k];
        return {
            department: k,
            issue_wt: o.issue,
            return_wt: o.ret,
            actual_loss: o.issue - o.ret,
            spent_time: timeDisp
        };
    });
}

function mpJcpReadHiddenCols(storageKey) {
    try {
        var raw = localStorage.getItem(storageKey);
        var h = raw ? JSON.parse(raw) : [];
        return Array.isArray(h) ? h : [];
    } catch (e2) {
        return [];
    }
}

function mpJcpApplyHistoryColVisibility() {
    var table = document.getElementById('mpJcpHistoryTable');
    if (!table) {
        return;
    }
    var hidden = mpJcpReadHiddenCols('manufacturing_process_jcp_history_hidden_columns');
    table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
        var col = el.getAttribute('data-col');
        if (hidden.indexOf(col) >= 0) {
            el.classList.add('col-hidden');
        } else {
            el.classList.remove('col-hidden');
        }
    });
}

function mpJcpApplySummaryColVisibility() {
    var table = document.getElementById('mpJcpSummaryTable');
    if (!table) {
        return;
    }
    var hidden = mpJcpReadHiddenCols('manufacturing_process_jcp_summary_hidden_columns');
    table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
        var col = el.getAttribute('data-col');
        if (hidden.indexOf(col) >= 0) {
            el.classList.add('col-hidden');
        } else {
            el.classList.remove('col-hidden');
        }
    });
}

function mpJcpUpdateRowSelClass(tr, on) {
    if (!tr) {
        return;
    }
    tr.classList.toggle('mp-jcp-unsel', !on);
}

function mpJcpSyncHistorySelectAll() {
    var table = document.getElementById('mpJcpHistoryTable');
    var headCb = document.getElementById('mpJcpHistorySelectAll');
    if (!table || !headCb) {
        return;
    }
    var boxes = table.querySelectorAll('.mp-jcp-row-chk');
    var n = boxes.length;
    var c = 0;
    boxes.forEach(function (b) {
        if (b.checked) {
            c++;
        }
    });
    headCb.checked = n > 0 && c === n;
    headCb.indeterminate = c > 0 && c < n;
}

function mpJcpRecalcHistoryFooter(sorted, mfgFallback) {
    var table = document.getElementById('mpJcpHistoryTable');
    var tfoot = document.getElementById('mpJcpHistoryFoot');
    if (!table || !tfoot) {
        return;
    }
    tfoot.innerHTML = '';
    var cols = window.MP_JCP_HISTORY_COLUMNS || [];
    if (!sorted.length) {
        return;
    }
    var hidden = mpJcpReadHiddenCols('manufacturing_process_jcp_history_hidden_columns');
    var tr = document.createElement('tr');
    var td0 = document.createElement('td');
    td0.setAttribute('data-col', '_sel');
    td0.innerHTML = '<strong>Total (selected)</strong>';
    tr.appendChild(td0);
    cols.forEach(function (c) {
        var td = document.createElement('td');
        td.setAttribute('data-col', c.key);
        if (hidden.indexOf(c.key) >= 0) {
            tr.appendChild(td);
            return;
        }
        if (mpJcpHistoryColIsNumeric(c.key)) {
            var sum = 0;
            var any = false;
            table.querySelectorAll('#mpJcpHistoryBody tr').forEach(function (r) {
                if (r.classList.contains('mp-jcp-unsel')) {
                    return;
                }
                var drow = r._mpJcpDataRow;
                if (!drow) {
                    return;
                }
                var raw = mpJcpHistoryCellRaw(drow, c.key, mfgFallback);
                var n = mpParseStockNumeric(raw);
                if (n !== null) {
                    sum += n;
                    any = true;
                }
            });
            td.className = 'num';
            td.innerHTML = '<strong>' + jwqEsc(any ? sum.toFixed(3) : '—') + '</strong>';
        }
        tr.appendChild(td);
    });
    tfoot.appendChild(tr);
    mpJcpApplyHistoryColVisibility();
}

function mpJcpFillTables(historyRows, mfgSeconds) {
    var thead = document.getElementById('mpJcpHistoryHead');
    var tbody = document.getElementById('mpJcpHistoryBody');
    var tfoot = document.getElementById('mpJcpHistoryFoot');
    var shead = document.getElementById('mpJcpSummaryHead');
    var sbody = document.getElementById('mpJcpSummaryBody');
    var sfoot = document.getElementById('mpJcpSummaryFoot');
    var mfgFb = parseInt(mfgSeconds, 10) || 0;
    window.__mpJcpMfgSeconds = mfgFb;
    if (tbody) {
        tbody.innerHTML = '';
    }
    if (tfoot) {
        tfoot.innerHTML = '';
    }
    if (sbody) {
        sbody.innerHTML = '';
    }
    if (sfoot) {
        sfoot.innerHTML = '';
    }
    var cols = window.MP_JCP_HISTORY_COLUMNS || [];
    var scols = window.MP_JCP_SUMMARY_COLUMNS || [];
    if (thead) {
        var hr = document.createElement('tr');
        var th0 = document.createElement('th');
        th0.setAttribute('data-col', '_sel');
        th0.style.width = '40px';
        th0.style.textAlign = 'center';
        th0.innerHTML = '<input type="checkbox" id="mpJcpHistorySelectAll" checked title="Select all">';
        hr.appendChild(th0);
        cols.forEach(function (c) {
            var th = document.createElement('th');
            th.setAttribute('data-col', c.key);
            th.textContent = c.label;
            if (mpJcpHistoryColIsNumeric(c.key)) {
                th.className = 'num';
            }
            hr.appendChild(th);
        });
        thead.innerHTML = '';
        thead.appendChild(hr);
    }
    if (shead) {
        var sr = document.createElement('tr');
        scols.forEach(function (c) {
            var th = document.createElement('th');
            th.setAttribute('data-col', c.key);
            th.textContent = c.label;
            if (c.key !== 'department') {
                th.className = 'num';
            }
            sr.appendChild(th);
        });
        shead.innerHTML = '';
        shead.appendChild(sr);
    }
    var sorted = (historyRows || []).slice().sort(function (a, b) {
        return mpJcpParseRowDate(a.date_time) - mpJcpParseRowDate(b.date_time);
    });
    window.__mpJcpSortedRows = sorted;
    var colCount = 1 + cols.length;
    if (!sorted.length && tbody) {
        var emptyTr = document.createElement('tr');
        emptyTr.innerHTML = '<td colspan="' + colCount + '" style="text-align:center;color:#64748b;padding:20px;">No job queue history for this order yet.</td>';
        tbody.appendChild(emptyTr);
        mpJcpApplyHistoryColVisibility();
        mpJcpApplySummaryColVisibility();
        return;
    }
    sorted.forEach(function (row) {
        var tr = document.createElement('tr');
        tr._mpJcpDataRow = row;
        var tds = [];
        var tdChk = document.createElement('td');
        tdChk.setAttribute('data-col', '_sel');
        tdChk.style.textAlign = 'center';
        tdChk.innerHTML = '<input type="checkbox" class="mp-jcp-row-chk" checked aria-label="Select row">';
        tr.appendChild(tdChk);
        cols.forEach(function (c) {
            var td = document.createElement('td');
            td.setAttribute('data-col', c.key);
            var raw = mpJcpHistoryCellRaw(row, c.key, mfgFb);
            if (c.key === 'description') {
                td.innerHTML = '<span class="mp-jcp-desc-link">' + jwqEsc(raw) + '</span>';
            } else if (c.key === 'dept_flow') {
                td.innerHTML = '<span class="mp-jcp-dept-flow-txt">' + jwqEsc(raw) + '</span>';
            } else {
                td.textContent = raw;
            }
            if (mpJcpHistoryColIsNumeric(c.key)) {
                td.className = 'num';
            }
            tr.appendChild(td);
        });
        if (tbody) {
            tbody.appendChild(tr);
        }
    });
    mpJcpRecalcHistoryFooter(sorted, mfgFb);
    var summaries = mpJcpBuildSummaryRows(sorted, mfgFb);
    var tIss = 0;
    var tRet = 0;
    var tLoss = 0;
    summaries.forEach(function (s) {
        var tr = document.createElement('tr');
        scols.forEach(function (c) {
            var td = document.createElement('td');
            td.setAttribute('data-col', c.key);
            var val = s[c.key];
            if (c.key === 'department') {
                td.textContent = val != null ? String(val) : '—';
            } else if (c.key === 'spent_time') {
                td.textContent = val != null ? String(val) : '—';
                td.className = 'num';
            } else {
                td.className = 'num';
                td.textContent = typeof val === 'number' ? val.toFixed(3) : '—';
            }
            tr.appendChild(td);
        });
        if (sbody) {
            sbody.appendChild(tr);
        }
        tIss += s.issue_wt;
        tRet += s.return_wt;
        tLoss += s.actual_loss;
    });
    if (sfoot && summaries.length) {
        var sf = document.createElement('tr');
        scols.forEach(function (c, idx) {
            var td = document.createElement('td');
            td.setAttribute('data-col', c.key);
            if (c.key === 'department') {
                td.innerHTML = '<strong>Total</strong>';
            } else if (c.key === 'spent_time') {
                td.className = 'num';
                td.innerHTML = '<strong>' + jwqEsc(formatMpJobCardHMS(mfgFb)) + '</strong>';
            } else {
                td.className = 'num';
                var tot = c.key === 'issue_wt' ? tIss : (c.key === 'return_wt' ? tRet : tLoss);
                td.innerHTML = '<strong>' + jwqEsc(tot.toFixed(3)) + '</strong>';
            }
            sf.appendChild(td);
        });
        sfoot.appendChild(sf);
    }
    mpJcpApplyHistoryColVisibility();
    mpJcpApplySummaryColVisibility();
    mpJcpSyncHistorySelectAll();
}

window.__mpJcpLastRows = null;

function mpJcpApplyCardToDrawer(card, historyRows) {
    window.__mpJcpLastCard = card || null;
    window.__mpJcpLastRows = historyRows || [];
    var cust = (card.getAttribute('data-customer-name') || '').trim();
    if (!cust) {
        var n1 = card.querySelector('.names .n1');
        cust = n1 ? n1.textContent.trim() : '—';
    }
    var elCust = document.getElementById('mpJcpCustomerName');
    if (elCust) {
        elCust.textContent = cust || '—';
    }
    var od = document.getElementById('mpJcpOrderDate');
    var dd = document.getElementById('mpJcpDueDate');
    if (od) {
        od.textContent = mpJcpFmtIsoSlash(card.getAttribute('data-order-date'));
    }
    if (dd) {
        dd.textContent = mpJcpFmtIsoSlash(card.getAttribute('data-due-date'));
    }
    var jn = (card.getAttribute('data-jobwork-no') || '').trim();
    var sn = (card.getAttribute('data-sale-order-no') || '').trim();
    var refParts = [];
    if (jn) {
        refParts.push(jn);
    }
    if (sn) {
        refParts.push(sn);
    }
    var refEl = document.getElementById('mpJcpRefNo');
    if (refEl) {
        refEl.textContent = refParts.length ? refParts.join(', ') : '—';
    }
    var tag = (card.getAttribute('data-tag-no') || '').trim();
    var tagInp = document.getElementById('mpJcpTagInput');
    if (tagInp) {
        tagInp.value = tag;
    }
    mpJcpRenderBarcode(tag);
    var mfg = parseInt(card.getAttribute('data-manufacturing-seconds') || '0', 10);
    var ts = document.getElementById('mpJcpTimeSpent');
    if (ts) {
        ts.textContent = formatMpJobCardHMS(mfg);
    }
    var imgMount = document.getElementById('mpJcpImagesMount');
    if (imgMount) {
        var im = card.querySelector('.mp-jwo-card-img');
        if (im && im.getAttribute('src')) {
            imgMount.innerHTML = '<img src="' + jwqInputEsc(im.getAttribute('src')) + '" alt="">';
        } else {
            imgMount.innerHTML = '<span class="mp-jcp-img-empty">No Images To Display!</span>';
        }
    }
    mpJcpFillTables(historyRows, mfg);
}

function mpCloseJobCardPrintDrawer() {
    var d = document.getElementById('mpJobCardPrintDrawer');
    var b = document.getElementById('mpJobCardPrintBackdrop');
    if (d) {
        d.classList.remove('open');
        d.setAttribute('aria-hidden', 'true');
    }
    if (b) {
        b.classList.remove('show');
        b.setAttribute('aria-hidden', 'true');
    }
}

function mpOpenJobCardPrintFromCard(card) {
    if (!card) {
        return;
    }
    var jid = parseInt(card.getAttribute('data-jwo-id'), 10) || 0;
    if (jid < 1) {
        return;
    }
    var d = document.getElementById('mpJobCardPrintDrawer');
    var bk = document.getElementById('mpJobCardPrintBackdrop');
    if (d) {
        d.classList.add('open');
        d.setAttribute('aria-hidden', 'false');
    }
    if (bk) {
        bk.classList.add('show');
        bk.setAttribute('aria-hidden', 'false');
    }
    var tbody = document.getElementById('mpJcpHistoryBody');
    var loadCols = (window.MP_JCP_HISTORY_COLUMNS || []).length + 1;
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="' + loadCols + '" style="text-align:center;color:#64748b;padding:16px;">Loading…</td></tr>';
    }
    mpFetchManufacturingQueueRows(jid)
        .then(function (rows) {
            mpJcpApplyCardToDrawer(card, rows);
        })
        .catch(function () {
            mpJcpApplyCardToDrawer(card, []);
        });
}

function mpJcpPopulateColumnPanelLists() {
    var hList = document.getElementById('mpJcpHistoryColumnsList');
    var sList = document.getElementById('mpJcpSummaryColumnsList');
    if (hList && !hList.getAttribute('data-populated')) {
        hList.setAttribute('data-populated', '1');
        (window.MP_JCP_HISTORY_COLUMNS || []).forEach(function (c) {
            var lab = document.createElement('label');
            lab.setAttribute('data-label', String(c.label || '').toLowerCase());
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.setAttribute('data-col', c.key);
            cb.checked = true;
            var sp = document.createElement('span');
            sp.textContent = c.label;
            lab.appendChild(cb);
            lab.appendChild(sp);
            hList.appendChild(lab);
        });
    }
    if (sList && !sList.getAttribute('data-populated')) {
        sList.setAttribute('data-populated', '1');
        (window.MP_JCP_SUMMARY_COLUMNS || []).forEach(function (c) {
            var lab = document.createElement('label');
            lab.setAttribute('data-label', String(c.label || '').toLowerCase());
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.setAttribute('data-col', c.key);
            cb.checked = true;
            var sp = document.createElement('span');
            sp.textContent = c.label;
            lab.appendChild(cb);
            lab.appendChild(sp);
            sList.appendChild(lab);
        });
    }
}

function mpJcpGetVisibleHistoryColumns() {
    var hidden = mpJcpReadHiddenCols('manufacturing_process_jcp_history_hidden_columns');
    return (window.MP_JCP_HISTORY_COLUMNS || []).filter(function (c) {
        return hidden.indexOf(c.key) < 0;
    });
}

function mpJcpCollectSelectedDataRows() {
    var out = [];
    document.querySelectorAll('#mpJcpHistoryBody tr').forEach(function (tr) {
        if (tr.classList.contains('mp-jcp-unsel')) {
            return;
        }
        if (tr._mpJcpDataRow) {
            out.push(tr._mpJcpDataRow);
        }
    });
    return out;
}

function mpJcpFmtPrintHeaderDate(iso) {
    var p = String(iso || '').trim().split('-');
    if (p.length !== 3) {
        return '—';
    }
    var mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var mi = parseInt(p[1], 10) - 1;
    if (mi < 0 || mi > 11) {
        return '—';
    }
    var day = parseInt(p[2], 10);
    return (day < 10 ? '0' : '') + day + '-' + mo[mi] + '-' + p[0];
}

function mpJcpFmtPrintRowDateTime(s) {
    var m = String(s || '').match(/^(\d{2})-(\d{2})-(\d{4})\s+(\d{2}):(\d{2})/);
    if (!m) {
        return s || '—';
    }
    var mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var h = parseInt(m[4], 10);
    var ampm = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12;
    if (h12 === 0) {
        h12 = 12;
    }
    return m[1] + ' ' + mo[parseInt(m[2], 10) - 1] + ' ' + String(m[3]).slice(-2) + ' / ' + h12 + ':' + m[5] + ' ' + ampm;
}

function mpJcpRenderPrintSheet() {
    var shell = document.getElementById('mpJcpPrintSheet');
    if (!shell) {
        return;
    }
    var card = window.__mpJcpLastCard;
    var selected = mpJcpCollectSelectedDataRows();
    if (!selected.length && window.__mpJcpSortedRows && window.__mpJcpSortedRows.length) {
        selected = window.__mpJcpSortedRows.slice();
    }
    var mfg = window.__mpJcpMfgSeconds || 0;
    var cust = '—';
    var odIso = '';
    var ddIso = '';
    var tag = '';
    var refs = '—';
    var jn = '';
    var sn = '';
    var desc = '—';
    var designNo = '—';
    var imgSrc = '';
    if (card) {
        cust = (card.getAttribute('data-customer-name') || '').trim();
        if (!cust) {
            var n1c = card.querySelector('.names .n1');
            cust = n1c ? n1c.textContent.trim() : '—';
        }
        odIso = (card.getAttribute('data-order-date') || '').trim();
        ddIso = (card.getAttribute('data-due-date') || '').trim();
        tag = (card.getAttribute('data-tag-no') || '').trim();
        jn = (card.getAttribute('data-jobwork-no') || '').trim();
        sn = (card.getAttribute('data-sale-order-no') || '').trim();
        desc = (card.getAttribute('data-line-desc') || '').trim();
        var imc = card.querySelector('.mp-jwo-card-img');
        if (imc && imc.getAttribute('src')) {
            imgSrc = imc.getAttribute('src');
        }
    }
    var refEl = document.getElementById('mpJcpRefNo');
    if (refEl && refEl.textContent.trim() && refEl.textContent.trim() !== '—') {
        refs = refEl.textContent.trim().replace(/\s*,\s*/g, ' | ');
    } else if (jn || sn) {
        refs = [jn, sn].filter(Boolean).join(' | ');
    }
    selected = selected.slice().sort(function (a, b) {
        return mpJcpParseRowDate(a.date_time) - mpJcpParseRowDate(b.date_time);
    });
    selected.forEach(function (r) {
        var d = r.design_no != null ? String(r.design_no).trim() : '';
        if (d !== '' && d !== '—' && designNo === '—') {
            designNo = d;
        }
    });
    if (!desc || desc === '—') {
        var fr = selected[0];
        if (fr && fr.description) {
            desc = String(fr.description);
        }
    }
    var odPr = mpJcpFmtPrintHeaderDate(odIso);
    var ddPr = mpJcpFmtPrintHeaderDate(ddIso);
    var photoInner = imgSrc ? '<img src="' + jwqInputEsc(imgSrc) + '" alt="">' : '';
    var histRows = selected.map(function (row) {
        var flow = row.department_flow != null ? String(row.department_flow) : mpJcpDeptFlowText(row);
        return '<tr>'
            + '<td>' + jwqEsc(mpJcpFmtPrintRowDateTime(row.date_time)) + '</td>'
            + '<td>' + jwqEsc(row.description != null ? row.description : '—') + '</td>'
            + '<td class="jcp-flow">' + jwqEsc(flow) + '</td>'
            + '<td class="num">' + jwqEsc(row.total_quantity != null ? row.total_quantity : '—') + '</td>'
            + '<td class="num">' + jwqEsc(row.total_wt != null ? row.total_wt : '—') + '</td>'
            + '<td class="num">' + jwqEsc(row.dust_wastage_wt != null ? row.dust_wastage_wt : '—') + '</td>'
            + '<td class="num">' + jwqEsc(row.loss_wt != null ? row.loss_wt : '—') + '</td>'
            + '<td class="num">' + jwqEsc(row.profit_wt != null ? row.profit_wt : '—') + '</td>'
            + '</tr>';
    }).join('');
    if (!histRows) {
        histRows = '<tr><td colspan="8" style="text-align:center">—</td></tr>';
    }
    var summaries = mpJcpBuildSummaryRows(selected, mfg);
    var tIss = 0;
    var tRet = 0;
    var tLoss = 0;
    var sumRows = summaries.map(function (s) {
        tIss += s.issue_wt;
        tRet += s.return_wt;
        tLoss += s.actual_loss;
        return '<tr>'
            + '<td>' + jwqEsc(s.department) + '</td>'
            + '<td class="num">' + jwqEsc(s.issue_wt.toFixed(3)) + '</td>'
            + '<td class="num">' + jwqEsc(s.return_wt.toFixed(3)) + '</td>'
            + '<td class="num">' + jwqEsc(s.actual_loss.toFixed(3)) + '</td>'
            + '<td class="num">' + jwqEsc(s.spent_time != null ? s.spent_time : formatMpJobCardHMS(mfg)) + '</td>'
            + '</tr>';
    }).join('');
    var sumFoot = summaries.length
        ? '<tr><td><strong>Total</strong></td>'
            + '<td class="num"><strong>' + jwqEsc(tIss.toFixed(3)) + '</strong></td>'
            + '<td class="num"><strong>' + jwqEsc(tRet.toFixed(3)) + '</strong></td>'
            + '<td class="num"><strong>' + jwqEsc(tLoss.toFixed(3)) + '</strong></td>'
            + '<td class="num"><strong>' + jwqEsc(formatMpJobCardHMS(mfg)) + '</strong></td></tr>'
        : '';
    var tagDisp = tag ? ('#' + tag) : '—';
    shell.innerHTML = '<div class="jcp-doc">'
        + '<div class="jcp-h1">Job Card</div>'
        + '<table class="jcp-head">'
        + '<tr>'
        + '<td rowspan="3" class="jcp-photo">' + photoInner + '</td>'
        + '<td class="jcp-lbl">Customer</td>'
        + '<td colspan="3">' + jwqEsc(cust) + '</td>'
        + '<td rowspan="3" class="jcp-bc"><div class="jcp-tag-num">' + jwqEsc(tagDisp) + '</div><svg id="mpJcpPrintBarcodeSvg" xmlns="http://www.w3.org/2000/svg"></svg></td>'
        + '</tr>'
        + '<tr><td class="jcp-lbl">Date</td><td>' + jwqEsc(odPr) + '</td><td class="jcp-lbl">Due Date</td><td>' + jwqEsc(ddPr) + '</td></tr>'
        + '<tr><td class="jcp-lbl">References</td><td>' + jwqEsc(refs) + '</td><td class="jcp-lbl">Design No</td><td>' + jwqEsc(designNo) + '</td></tr>'
        + '<tr class="jcp-desc-bar"><td colspan="6"><strong>Description:</strong> ' + jwqEsc(desc) + '</td></tr>'
        + '</table>'
        + '<div class="jcp-thumbs"><div class="jcp-thumb"></div><div class="jcp-thumb"></div><div class="jcp-thumb"></div><div class="jcp-thumb"></div></div>'
        + '<table class="jcp-data"><thead><tr>'
        + '<th>Date Time</th><th>Description</th><th>Department Flow</th><th class="num">Qty</th><th class="num">Gross Wt</th><th class="num">Other Wt</th><th class="num">Diff Wt.</th><th class="num">Ceramic/Profit</th>'
        + '</tr></thead><tbody>' + histRows + '</tbody></table>'
        + '<table class="jcp-data"><thead><tr>'
        + '<th>Department</th><th class="num">Issue Weight</th><th class="num">Return Weight</th><th class="num">Actual Loss</th><th class="num">Spent Time</th>'
        + '</tr></thead><tbody>' + sumRows + '</tbody><tfoot>' + sumFoot + '</tfoot></table>'
        + '<div class="jcp-sigs">'
        + '<div><div>Quality Check By</div><div class="jcp-line"></div></div>'
        + '<div><div>Supervised By</div><div class="jcp-line"></div></div>'
        + '<div><div>Approved By</div><div class="jcp-line"></div></div>'
        + '</div></div>';
    var svg = document.getElementById('mpJcpPrintBarcodeSvg');
    if (svg && tag && typeof JsBarcode === 'function') {
        try {
            while (svg.firstChild) {
                svg.removeChild(svg.firstChild);
            }
            JsBarcode(svg, tag, { format: 'code128', displayValue: false, height: 44, margin: 2, width: 1.5 });
        } catch (ePb) {}
    }
}

function initMpJobCardPrintDrawer() {
    var histTable = document.getElementById('mpJcpHistoryTable');
    if (histTable && !histTable._mpJcpDelegated) {
        histTable._mpJcpDelegated = true;
        histTable.addEventListener('change', function (e) {
            var t = e.target;
            if (t.id === 'mpJcpHistorySelectAll') {
                histTable.querySelectorAll('.mp-jcp-row-chk').forEach(function (c) {
                    c.checked = t.checked;
                    mpJcpUpdateRowSelClass(c.closest('tr'), c.checked);
                });
                mpJcpRecalcHistoryFooter(window.__mpJcpSortedRows || [], window.__mpJcpMfgSeconds || 0);
                t.indeterminate = false;
                return;
            }
            if (t.classList.contains('mp-jcp-row-chk')) {
                mpJcpUpdateRowSelClass(t.closest('tr'), t.checked);
                mpJcpSyncHistorySelectAll();
                mpJcpRecalcHistoryFooter(window.__mpJcpSortedRows || [], window.__mpJcpMfgSeconds || 0);
            }
        });
    }
    var histColList = document.getElementById('mpJcpHistoryColumnsList');
    if (histColList && !histColList._mpJcpFooterHook) {
        histColList._mpJcpFooterHook = true;
        histColList.addEventListener('change', function () {
            setTimeout(function () {
                mpJcpRecalcHistoryFooter(window.__mpJcpSortedRows || [], window.__mpJcpMfgSeconds || 0);
            }, 0);
        });
    }
    var grid = document.getElementById('mpJobCardsGrid');
    var closeBtn = document.getElementById('mpJobCardPrintCloseBtn');
    var backdrop = document.getElementById('mpJobCardPrintBackdrop');
    var printBtn = document.getElementById('mpJcpPrintBtn');
    var exportBtn = document.getElementById('mpJcpExportBtn');
    if (grid && !grid._mpJcpBound) {
        grid._mpJcpBound = true;
        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('.ref-barcode');
            if (!btn || !grid.contains(btn)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var card = btn.closest('.mp-job-card');
            if (card) {
                mpOpenJobCardPrintFromCard(card);
            }
        });
    }
    if (closeBtn && !closeBtn._mpJcpBound) {
        closeBtn._mpJcpBound = true;
        closeBtn.addEventListener('click', function () {
            mpCloseJobCardPrintDrawer();
        });
    }
    if (backdrop && !backdrop._mpJcpBound) {
        backdrop._mpJcpBound = true;
        backdrop.addEventListener('click', function () {
            mpCloseJobCardPrintDrawer();
        });
    }
    if (printBtn && !printBtn._mpJcpBound) {
        printBtn._mpJcpBound = true;
        printBtn.addEventListener('click', function () {
            if (typeof mpJcpRenderPrintSheet === 'function') {
                mpJcpRenderPrintSheet();
            }
            setTimeout(function () {
                window.print();
            }, 200);
        });
    }
    if (!document.documentElement._mpJcpEscBound) {
        document.documentElement._mpJcpEscBound = true;
        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') {
                return;
            }
            var d = document.getElementById('mpJobCardPrintDrawer');
            if (d && d.classList.contains('open')) {
                mpCloseJobCardPrintDrawer();
            }
        });
    }
    if (exportBtn && !exportBtn._mpJcpBound) {
        exportBtn._mpJcpBound = true;
        exportBtn.addEventListener('click', function () {
            var selected = mpJcpCollectSelectedDataRows();
            if (!selected.length) {
                alert('Select at least one row to export.');
                return;
            }
            var vis = mpJcpGetVisibleHistoryColumns();
            if (!vis.length) {
                alert('Show at least one column in Job Queue History.');
                return;
            }
            var mfg = window.__mpJcpMfgSeconds || 0;
            function escCsv(v) {
                var s = String(v != null ? v : '');
                if (/[",\n]/.test(s)) {
                    return '"' + s.replace(/"/g, '""') + '"';
                }
                return s;
            }
            var header = vis.map(function (c) {
                return escCsv(c.label);
            });
            var lines = [header.join(',')];
            selected.forEach(function (row) {
                var cells = vis.map(function (c) {
                    return escCsv(mpJcpHistoryCellRaw(row, c.key, mfg));
                });
                lines.push(cells.join(','));
            });
            var blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            var tag = document.getElementById('mpJcpTagInput');
            var fname = 'job-queue-' + (tag && tag.value ? tag.value.replace(/\s+/g, '_') : 'export') + '.csv';
            a.href = URL.createObjectURL(blob);
            a.download = fname;
            a.click();
            URL.revokeObjectURL(a.href);
        });
    }
}

/** Rows include stock_flow_type inward|outward when set (preferred). Else weight: receive→inward, issue→outward; activity: activity_side in/out. */
/** Stock rows filter by department_id / department_user_id = sidebar (inward/outward lists are pre-split by flow type). */
function mpFilterStockRowsByDeptUser(rows) {
    if (!rows || !rows.length) {
        return [];
    }
    var sd = selectedDeptId != null ? parseInt(selectedDeptId, 10) : 0;
    var su = selectedUserId != null ? parseInt(selectedUserId, 10) : 0;
    if (sd < 1 && su < 1) {
        return rows.slice();
    }
    return rows.filter(function (row) {
        var d = row.department_id != null ? parseInt(row.department_id, 10) : 0;
        var u = row.department_user_id != null ? parseInt(row.department_user_id, 10) : 0;
        if (su > 0) {
            return u === su && (sd < 1 || d === sd);
        }
        if (sd > 0) {
            return d === sd;
        }
        return true;
    });
}

function mpSplitInwardOutwardRows(allRows) {
    var inward = [];
    var outward = [];
    (allRows || []).forEach(function (row) {
        var ft = String(row.stock_flow_type || '').trim().toLowerCase();
        if (ft === 'inward') {
            inward.push(row);
            return;
        }
        if (ft === 'outward') {
            outward.push(row);
            return;
        }
        var kind = String(row.row_kind || '').trim();
        if (kind === 'weight') {
            if (row.receive_wt && row.receive_wt !== '—') {
                inward.push(row);
            }
            if (row.issue_wt && row.issue_wt !== '—') {
                outward.push(row);
            }
        } else if (kind === 'activity') {
            var side = String(row.activity_side || '').trim().toLowerCase();
            if (side === 'in') {
                inward.push(row);
            } else if (side === 'out') {
                outward.push(row);
            } else {
                inward.push(row);
                outward.push(row);
            }
        }
    });
    return { inward: inward, outward: outward };
}

/** Snapshot metrics for closing save (matches inward/outward grids + jobs). */
function mpClosingComputeMetricsSnapshot() {
    var raw = window.__mpMfgQueueRowsRaw;
    if (!raw || !Array.isArray(raw)) {
        raw = [];
    }
    var filtered = typeof mpFilterStockRowsByDeptUser === 'function' ? mpFilterStockRowsByDeptUser(raw) : raw.slice();
    var split = typeof mpSplitInwardOutwardRows === 'function' ? mpSplitInwardOutwardRows(filtered) : { inward: [], outward: [] };

    function sumWt(rows, outwardSide) {
        var t = 0;
        (rows || []).forEach(function (row) {
            var w = typeof mpParseStockNumeric === 'function' ? mpParseStockNumeric(row.total_wt) : null;
            if (w !== null && isFinite(w)) {
                t += outwardSide ? Math.abs(w) : w;
            }
        });
        return t;
    }

    function sumMetal(rows) {
        var t = 0;
        (rows || []).forEach(function (row) {
            var w = typeof mpParseStockNumeric === 'function' ? mpParseStockNumeric(row.metal_wt) : null;
            if (w !== null && isFinite(w)) {
                t += w;
            }
        });
        return t;
    }

    var inward_wt = sumWt(split.inward, false);
    var outward_wt = sumWt(split.outward, true);
    var jids = {};
    var closed = 0;
    filtered.forEach(function (row) {
        var jid = parseInt(row.jobwork_order_id, 10) || 0;
        if (jid > 0) {
            jids[String(jid)] = true;
        }
        var st = String(row.active || '').toLowerCase();
        if (st && /complete|closed|delivered|done|finish/.test(st)) {
            closed++;
        }
    });
    var total_jobs = Object.keys(jids).length;
    var processed_jobs = total_jobs;
    var closed_jobs = closed > 0 ? Math.min(closed, total_jobs) : 0;

    var metal_weight = sumMetal(filtered);
    var netBal = typeof mpClosingNetWtFromBalance === 'function' ? mpClosingNetWtFromBalance() : null;
    var closing_wt = netBal != null ? netBal : inward_wt - outward_wt;
    var lossEl = document.getElementById('mpClosingLossWt');
    var purWtEl = document.getElementById('mpClosingPurityWt');
    var workEl = document.getElementById('mpClosingWorkDoneKg');
    var final_loss = typeof mpClosingParseFloat === 'function' ? mpClosingParseFloat(lossEl ? lossEl.value : '') : null;
    if (final_loss === null) {
        final_loss = 0;
    }
    var difference_loss = (inward_wt - outward_wt) - final_loss;
    var loss_percent = inward_wt > 0.000001 ? (final_loss / inward_wt) * 100 : null;
    var pw = typeof mpClosingParseFloat === 'function' ? mpClosingParseFloat(purWtEl ? purWtEl.value : '') : null;
    var wkg = typeof mpClosingParseFloat === 'function' ? mpClosingParseFloat(workEl ? workEl.value : '') : null;
    var production_wt = pw != null ? pw : (wkg != null && wkg > 0 ? wkg * 1000 : null);

    return {
        inward_wt: inward_wt,
        outward_wt: outward_wt,
        recovery_wt: 0,
        closing_wt: closing_wt,
        production_wt: production_wt,
        metal_weight: metal_weight,
        total_jobs: total_jobs,
        closed_jobs: closed_jobs,
        processed_jobs: processed_jobs,
        difference_loss: difference_loss,
        final_loss: final_loss,
        loss_percent: loss_percent
    };
}

function mpParseStockNumeric(val) {
    if (val == null || val === '' || val === '—') {
        return null;
    }
    var s = String(val).replace(/,/g, '').trim();
    var n = parseFloat(s);
    return isNaN(n) ? null : n;
}

/** Parse Balance Stock metal wt (prefers live __mpBalanceStock). */
function mpReadInwardBalanceWt() {
    var bal = window.__mpBalanceStock;
    if (bal && isFinite(bal.metalWt)) {
        return bal.metalWt;
    }
    var el = document.getElementById('mpInwardBalanceSummary');
    if (!el) {
        return null;
    }
    var txt = String(el.textContent || '');
    var m = txt.match(/Metal\s*Wt\.:\s*([\d,.+\-]+)/i) || txt.match(/Wt\.:\s*([\d,.+\-]+)/i);
    if (!m) {
        return null;
    }
    var n = parseFloat(String(m[1]).replace(/,/g, ''));
    return isFinite(n) ? n : null;
}

/** Sum jobwork orders physically in the selected dept / user (tbl_jobwork_orders), independent of queue log rows. */
function mpSumJobworkLocationTotalsForFilter() {
    var totals = window.__mpJobworkLocationTotals || [];
    var sd = selectedDeptId != null ? parseInt(selectedDeptId, 10) : 0;
    var su = selectedUserId != null ? parseInt(selectedUserId, 10) : 0;
    var wt = 0;
    var qty = 0;
    totals.forEach(function (t) {
        var d = t.department_id != null ? parseInt(t.department_id, 10) : 0;
        var u = t.department_user_id != null ? parseInt(t.department_user_id, 10) : 0;
        var tw = parseFloat(t.total_wt);
        var tq = parseFloat(t.total_qty);
        if (!isFinite(tw)) {
            tw = 0;
        }
        if (!isFinite(tq)) {
            tq = 0;
        }
        if (su > 0) {
            if (u === su && (sd < 1 || d === sd)) {
                wt += tw;
                qty += tq;
            }
        } else if (sd > 0) {
            if (d === sd) {
                wt += tw;
                qty += tq;
            }
        } else {
            wt += tw;
            qty += tq;
        }
    });
    return { wt: wt, qty: qty };
}

/** Inward header: net metal/diamond wt + metal vs diamond/stone qty from stock log rows. */
function mpUpdateStockBalanceSummaries(inwardRows, outwardRows) {
    function isDiamondQtyRow(row) {
        var kind = String(row.row_kind || '').toLowerCase();
        var flow = String(row.flow_source || '').toLowerCase();
        var we = String(row.weight_event || '').toLowerCase();
        if (kind === 'diamond_issue' || flow === 'diamond_issue' || we === 'diamond_issue') {
            return true;
        }
        var m = mpParseStockNumeric(row.metal_wt);
        var d = mpParseStockNumeric(row.diamond_wt);
        return m === null && d !== null;
    }
    function sumMetalDiamond(rows, outwardSide) {
        var metal = 0;
        var diamond = 0;
        var metalQty = 0;
        var diamondQty = 0;
        (rows || []).forEach(function (row) {
            var m = mpParseStockNumeric(row.metal_wt);
            if (m !== null) {
                metal += outwardSide ? Math.abs(m) : m;
            }
            var d = mpParseStockNumeric(row.diamond_wt);
            if (d !== null) {
                diamond += outwardSide ? Math.abs(d) : d;
            }
            var q = mpParseStockNumeric(row.total_quantity);
            if (q !== null) {
                var qAbs = outwardSide ? Math.abs(q) : q;
                if (isDiamondQtyRow(row)) {
                    diamondQty += qAbs;
                } else {
                    metalQty += qAbs;
                }
            }
        });
        return { metal: metal, diamond: diamond, metalQty: metalQty, diamondQty: diamondQty };
    }
    var si = sumMetalDiamond(inwardRows, false);
    var so = sumMetalDiamond(outwardRows, true);
    var netMetal = si.metal - so.metal;
    var netDiamond = si.diamond - so.diamond;
    var netMetalQty = si.metalQty - so.metalQty;
    var netDiamondQty = si.diamondQty - so.diamondQty;
    window.__mpBalanceStock = {
        metalWt: netMetal,
        diamondWt: netDiamond,
        metalQty: netMetalQty,
        diamondQty: netDiamondQty
    };
    var elIn = document.getElementById('mpInwardBalanceSummary');
    if (elIn) {
        elIn.textContent = '(Balance Stock — Total Metal Wt.: ' + netMetal.toFixed(3)
            + ', Total Diamond/Stone Wt.: ' + netDiamond.toFixed(3)
            + ', Metal Qty.: ' + netMetalQty.toFixed(2)
            + ', Diamond/Stone Qty.: ' + netDiamondQty.toFixed(2) + ')';
    }
    var setTxt = function (id, val) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = val;
        }
    };
    setTxt('mpViewStockMetalWt', netMetal.toFixed(3));
    setTxt('mpViewStockDiamondWt', netDiamond.toFixed(3));
    setTxt('mpViewStockMetalQty', netMetalQty.toFixed(2));
    setTxt('mpViewStockDiamondQty', netDiamondQty.toFixed(2));
}

function initViewStockModal() {
    var overlay = document.getElementById('mpViewStockModalOverlay');
    var openBtn = document.getElementById('mpViewStockLink');
    var closeHead = document.getElementById('mpViewStockModalCloseBtn');
    var closeFoot = document.getElementById('mpViewStockFooterCloseBtn');
    if (!overlay || !openBtn) {
        return;
    }
    function openModal(e) {
        if (e) {
            e.preventDefault();
        }
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    openBtn.addEventListener('click', openModal);
    if (closeHead) {
        closeHead.addEventListener('click', closeModal);
    }
    if (closeFoot) {
        closeFoot.addEventListener('click', closeModal);
    }
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && overlay.classList.contains('show')) {
            closeModal();
        }
    });
}

function mpStockGrandTotalNumericKeys() {
    return ['dust_wastage_wt', 'loss_wt', 'profit_wt', 'total_wt', 'metal_wt', 'diamond_wt', 'purity_wt', 'total_quantity'];
}

function mpFmtStockGrandTotal(key, sum) {
    if (key === 'total_quantity') {
        return sum.toFixed(2);
    }
    return sum.toFixed(3);
}

function mpSumStockRowsForKey(rows, key) {
    var sum = 0;
    var any = false;
    (rows || []).forEach(function (row) {
        var n = mpParseStockNumeric(row[key]);
        if (n !== null) {
            sum += n;
            any = true;
        }
    });
    return any ? sum : null;
}

function mpStockGrandTotalBarId(tableId) {
    if (tableId === 'inwardTable') {
        return 'inwardGrandTotalBar';
    }
    if (tableId === 'outwardTable') {
        return 'outwardGrandTotalBar';
    }
    return '';
}

function mpStockGrandTotalTableId(tableId) {
    if (tableId === 'inwardTable') {
        return 'inwardGrandTotalTable';
    }
    if (tableId === 'outwardTable') {
        return 'outwardGrandTotalTable';
    }
    return '';
}

/** Build matching colgroups so main grid + grand total share exact column widths. */
function mpEnsureTableColgroup(table) {
    var colgroup = table.querySelector('colgroup');
    if (!colgroup) {
        colgroup = document.createElement('colgroup');
        table.insertBefore(colgroup, table.firstChild);
    }
    return colgroup;
}

function mpCellOuterWidth(el) {
    if (!el || el.classList.contains('col-hidden')) {
        return 0;
    }
    var w = el.offsetWidth;
    if (w < 1) {
        w = el.getBoundingClientRect().width;
    }
    // Prefer content width so empty squeezed tables do not collapse headers into overlap.
    var sw = el.scrollWidth;
    if (sw > w) {
        w = sw;
    }
    return Math.round(w);
}

function mpMeasureStockColumnWidths(mainTable) {
    var headRow = mainTable.querySelector('thead tr');
    var bodyRow = mainTable.querySelector('tbody tr');
    var keysRow = bodyRow || headRow;
    if (!keysRow) {
        return [];
    }
    var cols = [];
    keysRow.querySelectorAll('th[data-col], td[data-col]').forEach(function (srcCell) {
        var col = srcCell.getAttribute('data-col');
        if (!col) {
            return;
        }
        var headCell = headRow ? headRow.querySelector('[data-col="' + col + '"]') : null;
        var bodyCell = bodyRow ? bodyRow.querySelector('[data-col="' + col + '"]') : null;
        var hidden = (headCell && headCell.classList.contains('col-hidden'))
            || (bodyCell && bodyCell.classList.contains('col-hidden'))
            || srcCell.classList.contains('col-hidden');
        var w = 0;
        if (!hidden) {
            w = Math.max(mpCellOuterWidth(headCell), mpCellOuterWidth(bodyCell), mpCellOuterWidth(srcCell));
            // Empty outward/inward tables: keep readable header width (avoids gold text smear).
            if (!bodyRow && w < 96) {
                w = 96;
            }
            if (col === 'queue_no' && w < 110) {
                w = 110;
            }
            if (col === 'action' && w < 90) {
                w = 90;
            }
        }
        cols.push({ key: col, hidden: !!hidden, width: w });
    });
    return cols;
}

function mpApplyStockColumnWidths(table, cols, isGrandTotal) {
    if (!table || !cols || !cols.length) {
        return 0;
    }
    var colgroup = mpEnsureTableColgroup(table);
    colgroup.innerHTML = '';
    var totalW = 0;
    var gtRow = isGrandTotal ? table.querySelector('tbody tr.mp-stock-grand-total-row') : null;
    cols.forEach(function (c) {
        var cell = null;
        if (gtRow) {
            cell = gtRow.querySelector('td[data-col="' + c.key + '"]');
        } else {
            cell = table.querySelector('th[data-col="' + c.key + '"], td[data-col="' + c.key + '"]');
        }
        if (cell) {
            if (c.hidden) {
                cell.classList.add('col-hidden');
                cell.style.width = '';
                cell.style.minWidth = '';
                cell.style.maxWidth = '';
                return;
            }
            cell.classList.remove('col-hidden');
        }
        if (c.hidden || c.width < 1) {
            return;
        }
        var colEl = document.createElement('col');
        colEl.setAttribute('data-col', c.key);
        colEl.style.width = c.width + 'px';
        colgroup.appendChild(colEl);
        if (cell) {
            cell.style.width = c.width + 'px';
            cell.style.minWidth = c.width + 'px';
            cell.style.maxWidth = c.width + 'px';
        }
        totalW += c.width;
    });
    if (totalW > 0) {
        table.style.width = totalW + 'px';
        table.style.minWidth = totalW + 'px';
    }
    return totalW;
}

/** Match fixed footer column widths to the main inward/outward grid. */
function mpSyncStockGrandTotalColumnWidths(mainTable, gtTable) {
    if (!mainTable || !gtTable) {
        return;
    }
    var gtRow = gtTable.querySelector('tbody tr.mp-stock-grand-total-row');
    if (!gtRow) {
        return;
    }
    // Measure natural auto-layout widths (unconstrained), then lock both tables to the same col widths.
    mainTable.classList.remove('mp-stock-cols-synced');
    var mainCg = mainTable.querySelector('colgroup');
    if (mainCg) {
        mainCg.innerHTML = '';
    }
    mainTable.style.width = 'max-content';
    mainTable.style.minWidth = 'max-content';
    mainTable.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
        el.style.width = '';
        el.style.minWidth = '';
        el.style.maxWidth = '';
    });
    // Force layout at natural content width so columns are not measured while squeezed.
    void mainTable.offsetWidth;
    var cols = mpMeasureStockColumnWidths(mainTable);
    if (!cols.length) {
        return;
    }
    // Ensure Queue No is wide enough for the "Grand Total" label.
    cols.forEach(function (c) {
        if (c.key === 'queue_no' && !c.hidden && c.width < 110) {
            c.width = 110;
        }
    });
    var totalW = mpApplyStockColumnWidths(mainTable, cols, false);
    mpApplyStockColumnWidths(gtTable, cols, true);
    if (totalW > 0) {
        gtTable.style.width = totalW + 'px';
        gtTable.style.minWidth = totalW + 'px';
        mainTable.style.width = totalW + 'px';
        mainTable.style.minWidth = totalW + 'px';
        mainTable.classList.add('mp-stock-cols-synced');
    }
    // Keep footer scroll position aligned after width changes.
    // Pad footer by the body's vertical scrollbar width so columns stay aligned.
    var scrollEl = mainTable.closest('.mp-stock-table-scroll');
    var bar = gtTable.closest('.mp-stock-grand-total-bar');
    if (scrollEl && bar) {
        var sbW = Math.max(0, scrollEl.offsetWidth - scrollEl.clientWidth);
        bar.style.paddingRight = sbW > 0 ? sbW + 'px' : '';
        bar.scrollLeft = scrollEl.scrollLeft;
    }
}

function mpSyncAllStockGrandTotalWidths() {
    requestAnimationFrame(function () {
        mpSyncStockGrandTotalColumnWidths(
            document.getElementById('inwardTable'),
            document.getElementById('inwardGrandTotalTable')
        );
        mpSyncStockGrandTotalColumnWidths(
            document.getElementById('outwardTable'),
            document.getElementById('outwardGrandTotalTable')
        );
    });
}
window.mpSyncAllStockGrandTotalWidths = mpSyncAllStockGrandTotalWidths;

/** Body horizontal scrollbar drives grand total scrollLeft (footer scrollbar is hidden). */
function mpInitStockGrandTotalScrollSync() {
    [
        ['inwardTable', 'inwardGrandTotalBar'],
        ['outwardTable', 'outwardGrandTotalBar']
    ].forEach(function (pair) {
        var scrollEl = document.querySelector('[data-stock-scroll-for="' + pair[0] + '"]');
        var bar = document.getElementById(pair[1]);
        if (!scrollEl || !bar || scrollEl._mpGrandTotalSync) {
            return;
        }
        scrollEl._mpGrandTotalSync = true;
        var syncing = false;
        scrollEl.addEventListener('scroll', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            bar.scrollLeft = scrollEl.scrollLeft;
            syncing = false;
        });
        bar.addEventListener('scroll', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            scrollEl.scrollLeft = bar.scrollLeft;
            syncing = false;
        });
    });
}

function mpResetStockTableColumnWidths(table) {
    if (!table) {
        return;
    }
    table.classList.remove('mp-stock-cols-synced');
    var cg = table.querySelector('colgroup');
    if (cg) {
        cg.innerHTML = '';
    }
    table.style.width = 'max-content';
    table.style.minWidth = 'max-content';
    table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
        el.style.width = '';
        el.style.minWidth = '';
        el.style.maxWidth = '';
    });
}

function mpUpdateInwardOutwardStockFooter(table, rows, keys) {
    if (!table) {
        return;
    }
    var tableId = table.id || '';
    var bar = document.getElementById(mpStockGrandTotalBarId(tableId));
    var gtTable = document.getElementById(mpStockGrandTotalTableId(tableId));
    if (!bar || !gtTable) {
        return;
    }
    var tbody = gtTable.querySelector('tbody');
    if (!tbody) {
        tbody = document.createElement('tbody');
        gtTable.appendChild(tbody);
    }
    tbody.innerHTML = '';
    if (!rows || !rows.length) {
        bar.classList.remove('is-visible');
        // Empty grid: drop locked col widths so header labels do not overlap/smear.
        mpResetStockTableColumnWidths(table);
        mpResetStockTableColumnWidths(gtTable);
        return;
    }
    bar.classList.add('is-visible');
    var numericKeys = mpStockGrandTotalNumericKeys();
    var tr = document.createElement('tr');
    tr.className = 'mp-stock-grand-total-row';
    (keys || []).forEach(function (k) {
        var td = document.createElement('td');
        td.setAttribute('data-col', k);
        if (k === 'queue_no') {
            td.innerHTML = '<strong>Grand Total</strong>';
        } else if (numericKeys.indexOf(k) >= 0) {
            var sum = mpSumStockRowsForKey(rows, k);
            if (sum !== null) {
                td.className = 'num';
                td.innerHTML = '<strong>' + jwqEsc(mpFmtStockGrandTotal(k, sum)) + '</strong>';
            } else {
                td.innerHTML = '<strong>—</strong>';
            }
        }
        tr.appendChild(td);
    });
    tbody.appendChild(tr);
    if (typeof mpApplyInwardOutwardStockHidden === 'function') {
        mpApplyInwardOutwardStockHidden();
    }
    if (typeof mpSyncStockGrandTotalColumnWidths === 'function') {
        mpSyncStockGrandTotalColumnWidths(table, gtTable);
        requestAnimationFrame(function () {
            mpSyncStockGrandTotalColumnWidths(table, gtTable);
        });
    }
}

function mpBuildMfgQueueActionCellHtml(row, jid, soId) {
    row = row || {};
    jid = parseInt(jid, 10) || 0;
    soId = soId != null ? String(parseInt(soId, 10) || 0) : '0';
    var html = '<button type="button" class="mp-mfg-queue-edit mp-act-outline" title="Edit" aria-label="Edit" style="border:none;background:transparent;padding:2px 6px;cursor:pointer;vertical-align:middle;"><i class="feather icon-edit-2" style="color:#11294b;font-size:16px;"></i></button>'
        + '<button type="button" class="mp-mfg-queue-print" title="Print slip" aria-label="Print" data-jwo-id="' + jid + '" data-sale-order-id="' + soId + '" style="border:none;background:transparent;padding:2px 6px;cursor:pointer;vertical-align:middle;margin-left:2px;"><i class="feather icon-printer" style="color:#6d28d9;font-size:16px;"></i></button>';
    var canReverse = String(row.row_kind || '') === 'activity' && String(row.flow_source || '') === 'department_transfer';
    if (canReverse) {
        var actId = parseInt(row.source_id, 10) || 0;
        if (actId > 0) {
            html += '<button type="button" class="mp-mfg-queue-reverse mp-act-outline" title="Delete / Reverse Transfer" aria-label="Delete / Reverse Transfer" data-jwo-id="' + jid + '" data-activity-id="' + actId + '" style="border:none;background:transparent;padding:2px 6px;cursor:pointer;vertical-align:middle;margin-left:2px;"><i class="feather icon-trash-2" style="color:#dc2626;font-size:16px;"></i></button>';
        }
    }
    return html;
}

function mpFillInwardOutwardStockTable(tableId, rows, keys) {
    var table = document.getElementById(tableId);
    if (!table) {
        return;
    }
    var wrap = table.closest('.table-wrap');
    var empty = wrap ? wrap.querySelector('.empty-center') : null;
    var tbody = table.querySelector('tbody');
    if (!tbody) {
        tbody = document.createElement('tbody');
        table.appendChild(tbody);
    }
    tbody.innerHTML = '';
    (rows || []).forEach(function (row) {
        var tr = document.createElement('tr');
        var jid = parseInt(row.jobwork_order_id, 10) || 0;
        tr.setAttribute('data-jwo-id', String(jid));
        tr.setAttribute('data-row-kind', row.row_kind || '');
        tr.setAttribute('data-source-id', String(row.source_id != null ? row.source_id : ''));
        tr.setAttribute('data-jobwork-queue-no', String(row.jobwork_queue_no_attr != null ? row.jobwork_queue_no_attr : '').trim());
        tr.setAttribute('data-dept-id', String(row.department_id != null ? row.department_id : ''));
        tr.setAttribute('data-user-id', String(row.department_user_id != null ? row.department_user_id : ''));
        tr.setAttribute('data-jobwork-no', String(row.jobwork_no != null ? row.jobwork_no : '').trim());
        tr.setAttribute('data-sale-order-no', String(row.sale_order_no != null ? row.sale_order_no : '').trim());
        tr.setAttribute('data-first-product', String(row.first_product != null ? row.first_product : '').trim());
        tr.setAttribute('data-tag-no', String(row.tag_no != null ? row.tag_no : '').trim());
        tr.setAttribute('data-item-barcode', String(row.tag_no != null ? row.tag_no : '').trim());
        tr.setAttribute('data-floor-transfer', (parseInt(row.jwo_has_floor_transfer, 10) || 0) > 0 ? '1' : '0');
        tr.setAttribute('data-flow-source', String(row.flow_source != null ? row.flow_source : '').trim());
        if (row.manufacturing_seconds != null && row.manufacturing_seconds !== '') {
            tr.setAttribute('data-manufacturing-seconds', String(row.manufacturing_seconds));
        }
        keys.forEach(function (k) {
            var td = document.createElement('td');
            td.setAttribute('data-col', k);
            if (k === 'action') {
                var soId = row.sale_order_id != null ? String(parseInt(row.sale_order_id, 10) || 0) : '0';
                td.innerHTML = mpBuildMfgQueueActionCellHtml(row, jid, soId);
            } else {
                td.innerHTML = jwqEsc(row[k] != null ? row[k] : '—');
            }
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    mpUpdateInwardOutwardStockFooter(table, rows, keys);
    if (empty) {
        empty.style.display = rows.length ? 'none' : '';
    }
}

function mpGetActiveMpViewMode() {
    var cards = document.getElementById('mpPanelCards');
    if (cards && cards.classList.contains('is-active')) {
        return 'cards';
    }
    var tableWrap = document.getElementById('mpStockTableWrap');
    if (tableWrap && tableWrap.classList.contains('is-visible')) {
        return 'stock-table';
    }
    return 'stock-split';
}

function mpGetVisibleStockColumnThs(table) {
    if (!table) {
        return [];
    }
    var headRow = table.querySelector('thead tr');
    if (!headRow) {
        return [];
    }
    return Array.prototype.slice.call(headRow.querySelectorAll('th[data-col]')).filter(function (th) {
        return !th.classList.contains('col-hidden');
    });
}

function mpCsvEscape(s) {
    s = s == null ? '' : String(s);
    if (/[",\n\r]/.test(s)) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

function mpTableToCsvLines(table, includeGrandTotal) {
    var ths = mpGetVisibleStockColumnThs(table);
    if (!ths.length) {
        return [];
    }
    var lines = [];
    lines.push(ths.map(function (th) {
        return mpCsvEscape(String(th.textContent || '').replace(/\s+/g, ' ').trim());
    }).join(','));
    var tbody = table.querySelector('tbody');
    if (tbody) {
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var row = ths.map(function (th) {
                var col = th.getAttribute('data-col');
                var td = tr.querySelector('td[data-col="' + col + '"]');
                var txt = td ? String(td.textContent || '').replace(/\s+/g, ' ').trim() : '';
                return mpCsvEscape(txt);
            });
            lines.push(row.join(','));
        });
    }
    if (includeGrandTotal && table.id) {
        var gtTable = document.getElementById(mpStockGrandTotalTableId(table.id));
        if (gtTable) {
            var gtRow = gtTable.querySelector('tbody tr');
            if (gtRow) {
                lines.push(ths.map(function (th) {
                    var col = th.getAttribute('data-col');
                    var td = gtRow.querySelector('td[data-col="' + col + '"]');
                    var txt = td ? String(td.textContent || '').replace(/\s+/g, ' ').trim() : '';
                    return mpCsvEscape(txt);
                }).join(','));
            }
        }
    }
    return lines;
}

function mpTableToHtml(table, title, includeGrandTotal) {
    var ths = mpGetVisibleStockColumnThs(table);
    if (!ths.length) {
        return '';
    }
    var html = '';
    if (title) {
        html += '<h3 style="font-size:14px;margin:18px 0 8px;color:#11294b;">' + jwqEsc(title) + '</h3>';
    }
    html += '<table><thead><tr>';
    ths.forEach(function (th) {
        html += '<th>' + jwqEsc(String(th.textContent || '').trim()) + '</th>';
    });
    html += '</tr></thead><tbody>';
    var tbody = table.querySelector('tbody');
    if (tbody) {
        tbody.querySelectorAll('tr').forEach(function (tr) {
            html += '<tr>';
            ths.forEach(function (th) {
                var col = th.getAttribute('data-col');
                var td = tr.querySelector('td[data-col="' + col + '"]');
                html += '<td>' + jwqEsc(td ? String(td.textContent || '').replace(/\s+/g, ' ').trim() : '') + '</td>';
            });
            html += '</tr>';
        });
    }
    if (includeGrandTotal && table.id) {
        var gtTable = document.getElementById(mpStockGrandTotalTableId(table.id));
        var gtRow = gtTable ? gtTable.querySelector('tbody tr') : null;
        if (gtRow) {
            html += '<tr style="font-weight:700;background:#f8fafc;">';
            ths.forEach(function (th) {
                var col = th.getAttribute('data-col');
                var td = gtRow.querySelector('td[data-col="' + col + '"]');
                html += '<td>' + jwqEsc(td ? String(td.textContent || '').replace(/\s+/g, ' ').trim() : '') + '</td>';
            });
            html += '</tr>';
        }
    }
    html += '</tbody></table>';
    return html;
}

function mpExportCacheRowsToCsvLines(rows, keys, labels, sectionTitle) {
    keys = keys || [];
    labels = labels || {};
    rows = rows || [];
    if (!rows.length || !keys.length) {
        return [];
    }
    var exportKeys = keys.filter(function (k) { return k !== 'action'; });
    if (!exportKeys.length) {
        return [];
    }
    var lines = [];
    if (sectionTitle) {
        lines.push(mpCsvEscape(sectionTitle));
    }
    lines.push(exportKeys.map(function (k) {
        return mpCsvEscape(labels[k] || k);
    }).join(','));
    rows.forEach(function (row) {
        lines.push(exportKeys.map(function (k) {
            var v = row[k];
            return mpCsvEscape(v != null && String(v).trim() !== '' ? String(v) : '—');
        }).join(','));
    });
    return lines;
}

function mpExportStockExcel() {
    var mode = mpGetActiveMpViewMode();
    var lines = [];
    var deptLabel = (document.getElementById('mpSelectedDeptLabel') || {}).textContent || 'All departments';
    lines.push(mpCsvEscape((window.mpManufacturingPageHeading || 'Manufacturing Process') + ' — ' + String(deptLabel).trim()));
    lines.push('');

    if (mode === 'stock-split') {
        var inTable = document.getElementById('inwardTable');
        var outTable = document.getElementById('outwardTable');
        var inFromDom = mpTableToCsvLines(inTable, true);
        var outFromDom = mpTableToCsvLines(outTable, true);
        if (inFromDom.length || outFromDom.length) {
            if (inFromDom.length) {
                lines.push(mpCsvEscape('Inward Stock'));
                lines = lines.concat(inFromDom);
                lines.push('');
            }
            if (outFromDom.length) {
                lines.push(mpCsvEscape('Outward Stock'));
                lines = lines.concat(outFromDom);
            }
        } else {
            var split = mpSplitInwardOutwardRows(mpFilterStockRowsByDeptUser(window.__mpMfgQueueRowsRaw || []));
            var inCache = mpExportCacheRowsToCsvLines(split.inward, window.MP_STOCK_COLUMN_KEYS || [], window.MP_STOCK_COLUMN_LABELS || {}, 'Inward Stock');
            var outCache = mpExportCacheRowsToCsvLines(split.outward, window.MP_STOCK_COLUMN_KEYS || [], window.MP_STOCK_COLUMN_LABELS || {}, 'Outward Stock');
            if (!inCache.length && !outCache.length) {
                alert('No stock rows to export.');
                return;
            }
            if (inCache.length) {
                lines = lines.concat(inCache);
                lines.push('');
            }
            if (outCache.length) {
                lines = lines.concat(outCache);
            }
        }
    } else if (mode === 'stock-table') {
        var fullTable = document.getElementById('fullStockTable');
        var fullLines = mpTableToCsvLines(fullTable, false);
        if (!fullLines.length) {
            fullLines = mpExportCacheRowsToCsvLines(
                mpFilterStockRowsByDeptUser(window.__mpMfgQueueRowsRaw || []),
                window.MFG_QUEUE_COLUMN_KEYS || [],
                window.MFG_QUEUE_COLUMN_LABELS || {},
                'Manufacturing Queue'
            );
            if (fullLines.length > 1 && fullLines[0].indexOf('Manufacturing') >= 0) {
                lines.push(fullLines.shift());
            }
            lines = lines.concat(fullLines);
        } else {
            lines.push(mpCsvEscape('Manufacturing Queue'));
            lines = lines.concat(fullLines);
        }
    } else {
        var cardLines = mpExportCacheRowsToCsvLines(
            mpFilterStockRowsByDeptUser(window.__mpMfgQueueRowsRaw || []),
            window.MP_STOCK_COLUMN_KEYS || [],
            window.MP_STOCK_COLUMN_LABELS || {},
            'Manufacturing Queue'
        );
        if (!cardLines.length) {
            alert('No rows to export.');
            return;
        }
        lines = lines.concat(cardLines);
    }

    var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'manufacturing-stock-' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

function mpExportStockPdf() {
    var mode = mpGetActiveMpViewMode();
    var deptLabel = (document.getElementById('mpSelectedDeptLabel') || {}).textContent || 'All departments';
    var title = (window.mpManufacturingPageHeading || 'Manufacturing Process') + ' — ' + String(deptLabel).trim();
    var bodyHtml = '';

    if (mode === 'stock-split') {
        var inTable = document.getElementById('inwardTable');
        var outTable = document.getElementById('outwardTable');
        var inHtml = mpTableToHtml(inTable, 'Inward Stock', true);
        var outHtml = mpTableToHtml(outTable, 'Outward Stock', true);
        if (!inHtml && !outHtml) {
            alert('No stock rows to export.');
            return;
        }
        bodyHtml = inHtml + outHtml;
    } else if (mode === 'stock-table') {
        var fullTable = document.getElementById('fullStockTable');
        bodyHtml = mpTableToHtml(fullTable, 'Manufacturing Queue', false);
        if (!bodyHtml) {
            alert('No rows to export.');
            return;
        }
    } else {
        var cacheRows = mpFilterStockRowsByDeptUser(window.__mpMfgQueueRowsRaw || []);
        if (cacheRows.length) {
            var keys = window.MP_STOCK_COLUMN_KEYS || [];
            var labels = window.MP_STOCK_COLUMN_LABELS || {};
            bodyHtml += '<h3 style="font-size:14px;margin:18px 0 8px;color:#11294b;">Manufacturing Queue</h3><table><thead><tr>';
            keys.filter(function (k) { return k !== 'action'; }).forEach(function (k) {
                bodyHtml += '<th>' + jwqEsc(labels[k] || k) + '</th>';
            });
            bodyHtml += '</tr></thead><tbody>';
            cacheRows.forEach(function (row) {
                bodyHtml += '<tr>';
                keys.filter(function (k) { return k !== 'action'; }).forEach(function (k) {
                    var v = row[k];
                    bodyHtml += '<td>' + jwqEsc(v != null && String(v).trim() !== '' ? String(v) : '—') + '</td>';
                });
                bodyHtml += '</tr>';
            });
            bodyHtml += '</tbody></table>';
        }
        if (!bodyHtml) {
            alert('No rows to export. Wait for data to load.');
            return;
        }
    }

    var w = window.open('', '_blank');
    if (!w) {
        alert('Please allow pop-ups to export PDF.');
        return;
    }
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + jwqEsc(title) + '</title>');
    w.document.write('<style>body{font-family:Segoe UI,Arial,sans-serif;padding:16px;color:#11294b;} h2{font-size:18px;margin:0 0 14px;} table{border-collapse:collapse;width:100%;font-size:11px;margin-bottom:12px;} th,td{border:1px solid #cbd5e1;padding:5px 7px;text-align:left;} th{background:#11294b;color:#fff;}</style>');
    w.document.write('</head><body><h2>' + jwqEsc(title) + '</h2>' + bodyHtml + '</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function () { w.print(); }, 300);
}

function initMpExportDropdown() {
    var wrap = document.getElementById('mpExportDropdown');
    var btn = document.getElementById('mpExportBtn');
    var menu = document.getElementById('mpExportMenu');
    var xls = document.getElementById('mpExportExcelBtn');
    var pdf = document.getElementById('mpExportPdfBtn');
    if (!wrap || !btn || !menu || wrap._mpExportBound) {
        return;
    }
    wrap._mpExportBound = true;

    function closeMenu() {
        menu.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var open = menu.classList.toggle('show');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) {
            closeMenu();
        }
    });

    if (xls) {
        xls.addEventListener('click', function (e) {
            e.preventDefault();
            closeMenu();
            mpExportStockExcel();
        });
    }
    if (pdf) {
        pdf.addEventListener('click', function (e) {
            e.preventDefault();
            closeMenu();
            mpExportStockPdf();
        });
    }
}

/** Rebuild full manufacturing queue + inward/outward from cached API rows and current sidebar filter. */
function mpRenderManufacturingQueueTablesFromCache() {
    var fullTable = document.getElementById('fullStockTable');
    var wrap = fullTable ? fullTable.closest('.table-wrap') : null;
    var emptyFull = wrap ? wrap.querySelector('.empty-center') : null;
    var tbody = fullTable ? fullTable.querySelector('tbody') : null;
    if (fullTable && !tbody) {
        tbody = document.createElement('tbody');
        fullTable.appendChild(tbody);
    }
    var mfgKeys = window.MFG_QUEUE_COLUMN_KEYS || window.MP_STOCK_COLUMN_KEYS || [];
    var splitKeys = window.MP_STOCK_COLUMN_KEYS || [];
    var raw = window.__mpMfgQueueRowsRaw || [];
    var rows = mpFilterStockRowsByDeptUser(raw);

    if (fullTable && tbody) {
        tbody.innerHTML = '';
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            var jid = parseInt(row.jobwork_order_id, 10) || 0;
            tr.setAttribute('data-jwo-id', String(jid));
            tr.setAttribute('data-row-kind', row.row_kind || '');
            tr.setAttribute('data-source-id', String(row.source_id != null ? row.source_id : ''));
            tr.setAttribute('data-jobwork-queue-no', String(row.jobwork_queue_no_attr != null ? row.jobwork_queue_no_attr : '').trim());
            tr.setAttribute('data-dept-id', String(row.department_id != null ? row.department_id : ''));
            tr.setAttribute('data-user-id', String(row.department_user_id != null ? row.department_user_id : ''));
            tr.setAttribute('data-jobwork-no', String(row.jobwork_no != null ? row.jobwork_no : '').trim());
            tr.setAttribute('data-sale-order-no', String(row.sale_order_no != null ? row.sale_order_no : '').trim());
            tr.setAttribute('data-first-product', String(row.first_product != null ? row.first_product : '').trim());
            tr.setAttribute('data-tag-no', String(row.tag_no != null ? row.tag_no : '').trim());
            tr.setAttribute('data-item-barcode', String(row.tag_no != null ? row.tag_no : '').trim());
            tr.setAttribute('data-floor-transfer', (parseInt(row.jwo_has_floor_transfer, 10) || 0) > 0 ? '1' : '0');
            if (row.manufacturing_seconds != null && row.manufacturing_seconds !== '') {
                tr.setAttribute('data-manufacturing-seconds', String(row.manufacturing_seconds));
            }
            var td0 = document.createElement('td');
            td0.setAttribute('data-col', '_rowchk');
            td0.innerHTML = '<input type="checkbox" disabled aria-hidden="true">';
            tr.appendChild(td0);
            var tdImg = document.createElement('td');
            tdImg.setAttribute('data-col', '_img');
            tdImg.innerHTML = '<span style="color:#94a3b8;font-size:12px;">—</span>';
            tr.appendChild(tdImg);
            mfgKeys.forEach(function (k) {
                var td = document.createElement('td');
                td.setAttribute('data-col', k);
                if (k === 'action') {
                    var soId = row.sale_order_id != null ? String(parseInt(row.sale_order_id, 10) || 0) : '0';
                    td.innerHTML = mpBuildMfgQueueActionCellHtml(row, jid, soId);
                } else {
                    td.innerHTML = jwqEsc(row[k] != null ? row[k] : '—');
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        if (emptyFull) {
            emptyFull.style.display = rows.length ? 'none' : '';
        }
        if (typeof mpApplyMfgQueueHiddenColumns === 'function') {
            mpApplyMfgQueueHiddenColumns();
        }
    }

    var split = mpSplitInwardOutwardRows(rows);
    mpFillInwardOutwardStockTable('inwardTable', split.inward, splitKeys);
    mpFillInwardOutwardStockTable('outwardTable', split.outward, splitKeys);
    mpUpdateStockBalanceSummaries(split.inward, split.outward);
    if (typeof mpApplyInwardOutwardStockHidden === 'function') {
        mpApplyInwardOutwardStockHidden();
    }
    if (typeof mpSyncAllStockGrandTotalWidths === 'function') {
        mpSyncAllStockGrandTotalWidths();
    }
}
window.mpRenderManufacturingQueueTablesFromCache = mpRenderManufacturingQueueTablesFromCache;

/** Manufacturing queue full table + inward/outward split — one fetch, then department filter. */
function mpReloadManufacturingQueueTable() {
    var fullTable = document.getElementById('fullStockTable');
    var wrap = fullTable ? fullTable.closest('.table-wrap') : null;
    var emptyFull = wrap ? wrap.querySelector('.empty-center') : null;

    mpFetchManufacturingQueueRows()
        .then(function (rows) {
            window.__mpMfgQueueRowsRaw = rows || [];
            mpRenderManufacturingQueueTablesFromCache();
        })
        .catch(function () {
            window.__mpMfgQueueRowsRaw = null;
            window.__mpJobworkLocationTotals = [];
            if (emptyFull) {
                emptyFull.style.display = '';
            }
            var sk = window.MP_STOCK_COLUMN_KEYS || [];
            mpFillInwardOutwardStockTable('inwardTable', [], sk);
            mpFillInwardOutwardStockTable('outwardTable', [], sk);
            mpUpdateStockBalanceSummaries([], []);
        });
}

/** Re-apply column visibility on inward/outward after tbody refresh. */
function mpApplyInwardOutwardStockHidden() {
    var alwaysHidden = Array.isArray(window.MP_STOCK_COLUMNS_ALWAYS_HIDDEN)
        ? window.MP_STOCK_COLUMNS_ALWAYS_HIDDEN
        : ['image_urls'];
    [
        ['inwardTable', 'inwardGrandTotalTable', 'manufacturing_process_inward_hidden_columns'],
        ['outwardTable', 'outwardGrandTotalTable', 'manufacturing_process_outward_hidden_columns']
    ].forEach(function (pair) {
        var tables = [document.getElementById(pair[0]), document.getElementById(pair[1])];
        var hidden = [];
        try {
            hidden = JSON.parse(localStorage.getItem(pair[2]) || '[]');
        } catch (e1) {
            hidden = [];
        }
        if (!Array.isArray(hidden)) {
            hidden = [];
        }
        alwaysHidden.forEach(function (k) {
            if (hidden.indexOf(k) < 0) {
                hidden.push(k);
            }
        });
        tables.forEach(function (table) {
            if (!table) {
                return;
            }
            table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
                var col = el.getAttribute('data-col');
                if (hidden.indexOf(col) >= 0) {
                    el.classList.add('col-hidden');
                } else {
                    el.classList.remove('col-hidden');
                }
            });
        });
    });
    if (typeof mpSyncAllStockGrandTotalWidths === 'function') {
        mpSyncAllStockGrandTotalWidths();
    }
}
window.mpApplyInwardOutwardStockHidden = mpApplyInwardOutwardStockHidden;

/** Re-apply column visibility after Manufacturing queue tbody refresh (same storage as column panel). */
function mpApplyMfgQueueHiddenColumns() {
    var table = document.getElementById('fullStockTable');
    if (!table) {
        return;
    }
    var hidden = [];
    try {
        var raw = localStorage.getItem('manufacturing_process_mfg_queue_hidden_columns');
        hidden = raw ? JSON.parse(raw) : [];
    } catch (e1) {
        hidden = [];
    }
    if (!Array.isArray(hidden)) {
        hidden = [];
    }
    table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
        var col = el.getAttribute('data-col');
        if (hidden.indexOf(col) >= 0) {
            el.classList.add('col-hidden');
        } else {
            el.classList.remove('col-hidden');
        }
    });
}
window.mpApplyMfgQueueHiddenColumns = mpApplyMfgQueueHiddenColumns;

window.mpReloadManufacturingQueueTable = mpReloadManufacturingQueueTable;

function jwqNum3(v) {
    var n = parseFloat(v);
    if (isNaN(n)) return '0.000';
    return n.toFixed(3);
}

function jwqNumOptDash3(v) {
    if (v == null || v === '') return '—';
    var n = parseFloat(v);
    if (isNaN(n)) return '—';
    return n.toFixed(3);
}

function jwqNumOptDash2(v) {
    if (v == null || v === '') return '—';
    var n = parseFloat(v);
    if (isNaN(n)) return '—';
    return n.toFixed(2);
}

function jwqInputEsc(v) {
    return String(v == null ? '' : v)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/** Prefer stable data-field; fall back to legacy data-col-input. */
function jwqLineFieldEl(tr, field) {
    if (!tr || !field) {
        return null;
    }
    var el = tr.querySelector('[data-field="' + field + '"]');
    if (el) {
        return el;
    }
    return tr.querySelector('[data-col-input="' + field + '"]');
}

function jwqLineFieldNum(tr, field) {
    var el = jwqLineFieldEl(tr, field);
    if (!el || el.value == null || el.value === '') {
        return NaN;
    }
    var raw = String(el.value).replace(/,/g, '').trim();
    if (raw === '—' || raw === '-' || raw === '--') {
        return 0;
    }
    var n = parseFloat(raw);
    return isFinite(n) ? n : NaN;
}

window.mpCaratOptions = <?php echo $mp_carat_json; ?>;

function jwqKaratSelectHtml(raw) {
    var sel = String(raw == null ? '' : raw).trim();
    var opts = window.mpCaratOptions || [];
    if (!Array.isArray(opts)) opts = [];
    var parts = ['<select class="jwq-cell-input" data-col-input="karat" data-field="karat">'];
    parts.push('<option value="">' + jwqEsc('-- Select --') + '</option>');
    var found = false;
    opts.forEach(function (c) {
        if (!c) return;
        var name = c.name != null ? String(c.name).trim() : '';
        if (name === '') return;
        var isSel = sel !== '' && name === sel;
        if (isSel) found = true;
        parts.push('<option value="' + jwqInputEsc(name) + '"' + (isSel ? ' selected' : '') + '>' + jwqEsc(name) + '</option>');
    });
    if (sel !== '' && !found) {
        parts.push('<option value="' + jwqInputEsc(sel) + '" selected>' + jwqEsc(sel) + '</option>');
    }
    parts.push('</select>');
    return parts.join('');
}

/**
 * Fallback when queue_display_total_wt is absent: final_weight if saved, else metal − loss + diamond.
 */
function jwqLineQueueDisplayTotalWt(it) {
    it = it || {};
    if (it.queue_display_total_wt != null && String(it.queue_display_total_wt).trim() !== '') {
        var qn0 = parseFloat(String(it.queue_display_total_wt).replace(/,/g, ''));
        if (isFinite(qn0)) {
            return qn0;
        }
    }
    var f = parseFloat(it.final_weight);
    if (isFinite(f) && f > 0.0000001) {
        return f;
    }
    var n = parseFloat(it.net_weight);
    var g = parseFloat(it.gross_weight);
    var lo = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
    if (!isFinite(lo) || lo < 0) {
        lo = 0;
    }
    var d = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        d = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    } else if (it.diamond_weight != null && it.diamond_weight !== '') {
        d = parseFloat(String(it.diamond_weight).replace(/,/g, ''));
    }
    if (!isFinite(d) || d < 0) {
        d = 0;
    }
    var metal = 0;
    if (isFinite(n) && n > 0.0000001) {
        metal = n;
    } else if (isFinite(g) && g > 0.0000001) {
        metal = g;
    }
    return Math.max(0, metal - lo + d);
}

/** metal_wt − loss + diamond_wt (same as PHP fallback when final_weight is not authoritative). */
function jwqComputedTotalMetalLossDiamondFromItem(it) {
    it = it || {};
    var n = parseFloat(it.net_weight);
    var g = parseFloat(it.gross_weight);
    var lo = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
    if (!isFinite(lo) || lo < 0) {
        lo = 0;
    }
    var d = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        d = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    } else if (it.diamond_weight != null && it.diamond_weight !== '') {
        d = parseFloat(String(it.diamond_weight).replace(/,/g, ''));
    }
    if (!isFinite(d) || d < 0) {
        d = 0;
    }
    var metal = 0;
    if (isFinite(n) && n > 0.0000001) {
        metal = n;
    } else if (isFinite(g) && g > 0.0000001) {
        metal = g;
    }
    return Math.max(0, metal - lo + d);
}

/** Saved final_weight differs from metal−loss+diamond ⇒ user locked display total (e.g. 8 vs 9). */
function jwqItemTotalManuallyLockedFromServer(it) {
    it = it || {};
    var f = parseFloat(it.final_weight);
    if (!isFinite(f) || f <= 0.0000001) {
        return false;
    }
    var comp = jwqComputedTotalMetalLossDiamondFromItem(it);
    if (!isFinite(comp)) {
        return false;
    }
    return Math.abs(f - comp) > 0.05;
}

/** Guard: programmatic weight updates must not re-enter live handlers. */
window.__jwqIsRecalculatingWeight = false;

/**
 * Live line weights — always: Total = Metal − Loss + Diamond.
 * - Edit total → Metal = Total − Diamond; Loss reconciled (≥ 0)
 * - Edit metal / loss / diamond → Total = Metal − Loss + Diamond
 */
function jwqLineFormulaTotal(mw, lw, dw) {
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    var t = mw - lw + dw;
    return isFinite(t) && t >= 0 ? t : 0;
}

function jwqLineStoredTotalMismatch(tr, dwOptional) {
    var tw = jwqLineFieldNum(tr, 'total_wt');
    var mw = jwqLineFieldNum(tr, 'metal_wt');
    var lw = jwqLineFieldNum(tr, 'loss');
    var dw = isFinite(dwOptional) ? dwOptional : jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    var expected = jwqLineFormulaTotal(mw, lw, dw);
    return !isFinite(tw) || Math.abs(tw - expected) > 0.05;
}

function jwqItemFormulaTotalWt(it) {
    it = it || {};
    var mw = jwqLineOrigMetalWt(it);
    var lo = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
    if (!isFinite(lo) || lo < 0) {
        lo = 0;
    }
    var dw = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        dw = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    }
    if (!isFinite(dw) || dw <= 0) {
        dw = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    return jwqLineFormulaTotal(mw, lo, dw);
}

function jwqLineLiveRecalcWeights(tr, sourceField) {
    if (!tr || window.__jwqIsRecalculatingWeight) {
        return;
    }
    if (tr.getAttribute('data-jwq-freeze-weights') === '1' && !jwqLineStoredTotalMismatch(tr)) {
        var addExtraFreeze = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
        if (!addExtraFreeze) {
            return;
        }
    }
    var mInp = jwqLineFieldEl(tr, 'metal_wt');
    var dInp = jwqLineFieldEl(tr, 'diamond_wt');
    var lInp = jwqLineFieldEl(tr, 'loss');
    var tInp = jwqLineFieldEl(tr, 'total_wt');
    if (!mInp || !tInp) {
        return;
    }
    var mw = jwqLineFieldNum(tr, 'metal_wt');
    var dw = dInp ? jwqLineFieldNum(tr, 'diamond_wt') : 0;
    var lw = lInp ? jwqLineFieldNum(tr, 'loss') : 0;
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    window.__jwqIsRecalculatingWeight = true;
    try {
        if (sourceField === 'total_wt') {
            var tRead = jwqLineFieldEl(tr, 'total_wt');
            var twRaw = tRead ? parseFloat(String(tRead.value || '').replace(/,/g, '')) : NaN;
            if (!isFinite(twRaw)) {
                return;
            }
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
            if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
                if (typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                    window.jwqApplyReduceWeightManualMetalPreview(tr);
                }
                return;
            }
            var tw = twRaw < 0 ? 0 : twRaw;
            var fromDeptEl = document.getElementById('jwqFromDept');
            var fdAuto = fromDeptEl ? parseInt(fromDeptEl.value || '0', 10) : 0;
            if (jwqDeptHasAutoLoss(fdAuto)) {
                var baselineTw = jwqAutoLossBaselineForRow(tr);
                var lossAuto = baselineTw - tw;
                if (!isFinite(lossAuto) || lossAuto < 0) {
                    lossAuto = 0;
                }
                if (lInp) {
                    lInp.value = jwqNumOptDash3(lossAuto);
                }
                tr.setAttribute('data-jwq-total-manual', '1');
            } else {
                var newMetal = tw - dw;
                if (!isFinite(newMetal) || newMetal < 0) {
                    newMetal = 0;
                }
                mInp.value = jwqNum3(newMetal);
                tr.setAttribute('data-base-metal-wt', String(newMetal));
                if (lInp) {
                    var lossVal = newMetal + dw - tw;
                    if (!isFinite(lossVal) || lossVal < 0) {
                        lossVal = 0;
                    }
                    lInp.value = jwqNumOptDash3(lossVal);
                }
                tr.setAttribute('data-jwq-total-manual', '1');
            }
        } else {
            if (sourceField === 'metal_wt') {
                tr.setAttribute('data-base-metal-wt', String(mw));
            }
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode() && sourceField === 'metal_wt') {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
            if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode() && sourceField === 'metal_wt') {
                if (typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                    window.jwqApplyReduceWeightManualMetalPreview(tr);
                }
                return;
            }
            if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                if (sourceField === 'diamond_wt') {
                    tr.setAttribute('data-jwq-diamond-manual', '1');
                    if (typeof jwqApplyTransferLineCarryingTotalWt === 'function') {
                        jwqApplyTransferLineCarryingTotalWt(tr);
                    }
                    return;
                }
                /* Metal / loss edits: keep user input and recompute Total Wt (do not snap metal back to carrying total). */
                if (sourceField === 'metal_wt' || sourceField === 'loss' || sourceField === 'expected_wt') {
                    var xferTotal = jwqLineFormulaTotal(mw, lw, dw);
                    tInp.value = jwqNum3(xferTotal);
                    tr.setAttribute('data-jwq-total-manual', '1');
                    tr.removeAttribute('data-jwq-freeze-weights');
                    return;
                }
                if (typeof jwqApplyTransferLineCarryingTotalWt === 'function') {
                    jwqApplyTransferLineCarryingTotalWt(tr);
                }
                return;
            }
            var newTotal = mw - lw + dw;
            if (!isFinite(newTotal) || newTotal < 0) {
                newTotal = 0;
            }
            tInp.value = jwqNum3(newTotal);
            tr.removeAttribute('data-jwq-total-manual');
        }
        var tw2 = jwqLineFieldNum(tr, 'total_wt');
        var lw2 = lInp ? jwqLineFieldNum(tr, 'loss') : 0;
        var mw2 = jwqLineFieldNum(tr, 'metal_wt');
        var dw2 = dInp ? jwqLineFieldNum(tr, 'diamond_wt') : 0;
        if (!isFinite(lw2) || lw2 < 0) {
            lw2 = 0;
        }
        if (!isFinite(dw2) || dw2 < 0) {
            dw2 = 0;
        }
        if (!isFinite(mw2) || mw2 < 0) {
            mw2 = 0;
        }
        if (isFinite(tw2) && isFinite(mw2) && isFinite(lw2) && isFinite(dw2)) {
            var expected = mw2 - lw2 + dw2;
            if (isFinite(expected) && Math.abs(tw2 - expected) < 0.05) {
                tr.removeAttribute('data-jwq-total-manual');
            }
        }
        if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
            jwqRefreshOrderLinesTotalSummary();
        }
    } finally {
        window.__jwqIsRecalculatingWeight = false;
    }
}
window.jwqLineLiveRecalcWeights = jwqLineLiveRecalcWeights;

function jwqHandleOrderLineWeightInput(e) {
    if (window.__jwqIsRecalculatingWeight) {
        return;
    }
    var tbody = e.currentTarget;
    if (!tbody) {
        return;
    }
    var inp = e.target.closest(
        '.jwq-cell-input[data-field="metal_wt"], .jwq-cell-input[data-field="loss"], .jwq-cell-input[data-field="expected_wt"], .jwq-cell-input[data-col-input="metal_wt"], .jwq-cell-input[data-col-input="loss"], .jwq-cell-input[data-col-input="expected_wt"]'
    );
    if (inp && tbody.contains(inp)) {
        var tr = inp.closest('tr');
        if (tr) {
            tr.removeAttribute('data-jwq-freeze-weights');
        }
        var f = inp.getAttribute('data-field') || inp.getAttribute('data-col-input');
        if (tr && f === 'expected_wt') {
            tr.removeAttribute('data-jwq-saved-loss-grams');
            if (typeof jwqMaybeApplyAutoLoss === 'function') {
                jwqMaybeApplyAutoLoss(tr, 'expected_wt');
            }
            return;
        }
        if (tr && f === 'metal_wt') {
            tr.removeAttribute('data-jwq-saved-loss-grams');
        }
        if (tr && typeof jwqLineLiveRecalcWeights === 'function' && f) {
            jwqLineLiveRecalcWeights(tr, f);
        }
        if (tr && typeof jwqMaybeApplyAutoLoss === 'function') {
            jwqMaybeApplyAutoLoss(tr, f);
        }
        if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
            jwqRefreshOrderLinesTotalSummary();
        }
        return;
    }
    var inD = e.target.closest('.jwq-cell-input[data-field="diamond_wt"], .jwq-cell-input[data-col-input="diamond_wt"]');
    if (inD && tbody.contains(inD)) {
        var trd = inD.closest('tr');
        if (trd) {
            trd.removeAttribute('data-jwq-freeze-weights');
            /* User typed the diamond weight — later syncs must not resurrect it from the BOM pool gap. */
            trd.setAttribute('data-jwq-diamond-manual', '1');
        }
        if (trd && typeof jwqRefreshLineDiamondBaseFromUi === 'function') {
            jwqRefreshLineDiamondBaseFromUi(trd);
        }
        if (trd && typeof jwqLineLiveRecalcWeights === 'function') {
            jwqLineLiveRecalcWeights(trd, 'diamond_wt');
        }
        if (trd && typeof jwqMaybeApplyAutoLoss === 'function') {
            jwqMaybeApplyAutoLoss(trd);
        }
        if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
            jwqRefreshOrderLinesTotalSummary();
        }
    }
}
window.jwqHandleOrderLineWeightInput = jwqHandleOrderLineWeightInput;

/** When Total Wt is not manually locked: set total = metal − loss + diamond from current line inputs. */
function jwqRefreshComputedTotalWt(tr) {
    jwqLineLiveRecalcWeights(tr, 'metal_wt');
}
window.jwqRefreshComputedTotalWt = jwqRefreshComputedTotalWt;

/** Transfer modal: line weights come from tbl_jobwork_order_items (mp-jobwork-order-items.php). data-orig-total-wt is auto-loss baseline (max of metal vs carrying total), not raw final-only. */
function jwqCellRawByKey(key, it, orderRef) {
    it = it || {};
    switch (key) {
        case 'design_no':
            return it.design_no != null ? String(it.design_no) : '';
        case 'tag_no':
            return it.barcode != null ? String(it.barcode) : '';
        case 'description':
            return it.product_name != null ? String(it.product_name) : '';
        case 'order_no':
            return orderRef != null ? String(orderRef) : '';
        case 'total_wt':
            return jwqNum3(jwqLineQueueCarryingTotalWt(it));
        case 'metal_wt': {
            if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                var carryM = jwqLineQueueCarryingTotalWt(it);
                if (!isFinite(carryM) || carryM < 0) {
                    carryM = 0;
                }
                return jwqNum3(carryM);
            }
            return jwqNum3(jwqLineOrigMetalWt(it));
        }
        case 'diamond_wt': {
            if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                return jwqNum3(0);
            }
            var dStored = NaN;
            if (it.diamond_wt != null && it.diamond_wt !== '') {
                dStored = parseFloat(it.diamond_wt);
            }
            if (!isFinite(dStored) || dStored <= 0) {
                dStored = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
            }
            if (isFinite(dStored) && dStored > 0) {
                return jwqNum3(dStored);
            }
            var tw0 = jwqLineOrigTotalWt(it);
            var mw0 = jwqLineOrigMetalWt(it);
            if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
                return jwqNum3(tw0 - mw0);
            }
            return jwqNum3(0);
        }
        case 'total_purity':
            return jwqNum3(it.purity);
        case 'karat':
            return it.carat != null ? String(it.carat) : '';
        case 'total_qty':
            var jqq = parseFloat(it.quantity);
            if (isFinite(jqq) && jqq > 0) {
                return jwqNum3(jqq);
            }
            return jwqNum3(jqq);
        case 'price':
            return jwqNumOptDash2(it.rate);
        case 'dust_wastage_wt':
            if (it.wastage_wt != null && it.wastage_wt !== '') {
                return jwqNum3(it.wastage_wt);
            }
            return jwqNum3(0);
        case 'loss': {
            if (Object.prototype.hasOwnProperty.call(it, 'queue_display_loss_wt')) {
                var qlossS = it.queue_display_loss_wt != null ? String(it.queue_display_loss_wt).replace(/,/g, '') : '';
                var qxStrict = parseFloat(qlossS);
                if (isFinite(qxStrict) && qxStrict >= 0) {
                    return jwqNumOptDash3(qxStrict);
                }
            }
            var gRaw = it.gold_loss_1;
            var lRaw = it.loss_wt;
            var g = NaN;
            if (gRaw !== null && gRaw !== undefined && String(gRaw).trim() !== '') {
                g = parseFloat(String(gRaw).replace(/,/g, ''));
            }
            if (isFinite(g) && Math.abs(g) > 0.00001) {
                return jwqNumOptDash3(g);
            }
            if (lRaw !== null && lRaw !== undefined && String(lRaw).trim() !== '') {
                var l = parseFloat(String(lRaw).replace(/,/g, ''));
                if (isFinite(l)) {
                    return jwqNumOptDash3(l);
                }
            }
            if (isFinite(g)) {
                return jwqNumOptDash3(g);
            }
            return jwqNumOptDash3(0);
        }
        case 'profit':
            return jwqNumOptDash2(it.profit != null ? it.profit : it.net_amount);
        case 'expected_wt':
            return jwqNum3(it.expected_wt != null ? it.expected_wt : it.gross_weight);
        case 'product':
            return it.product_name != null ? String(it.product_name) : '';
        case 'requested_wt':
            return jwqNumOptDash3(it.requested_wt != null ? it.requested_wt : it.requested);
        case 'requested_purity':
            return jwqNumOptDash3(it.requested_purity);
        case 'alloy_wt':
            return jwqNumOptDash3(it.alloy_wt);
        case 'damage_qty':
            return jwqNumOptDash3(it.damage_qty != null ? it.damage_qty : it.damage_quantity);
        case 'damage_wt':
            return jwqNumOptDash3(it.damage_wt != null ? it.damage_wt : it.damage_weight);
        default:
            return '';
    }
}

function jwqIsNumericCol(key) {
    var numeric = ['total_wt', 'metal_wt', 'diamond_wt', 'total_purity', 'total_qty', 'dust_wastage_wt', 'loss', 'expected_wt', 'requested_wt', 'requested_purity', 'alloy_wt', 'damage_qty', 'damage_wt', 'price', 'profit'];
    return numeric.indexOf(key) >= 0;
}

function jwqSanitizeDecimalInputValue(raw) {
    if (raw == null) {
        return '';
    }
    var s = String(raw).replace(/,/g, '');
    if (s === '') {
        return '';
    }
    var out = '';
    var dot = false;
    for (var i = 0; i < s.length; i++) {
        var c = s.charAt(i);
        if (c >= '0' && c <= '9') {
            out += c;
        } else if (c === '.' && !dot) {
            dot = true;
            out += c;
        }
    }
    return out;
}

function jwqDecimalInputGuardKeydown(e) {
    var inp = e.target;
    if (!inp || !inp.classList || !inp.classList.contains('jwq-cell-input--decimal')) {
        return;
    }
    var k = e.key;
    if (!k || k.length !== 1) {
        return;
    }
    if (e.ctrlKey || e.metaKey || e.altKey) {
        return;
    }
    if (k >= '0' && k <= '9') {
        return;
    }
    if (k === '.' && String(inp.value).indexOf('.') < 0) {
        return;
    }
    e.preventDefault();
}

function jwqDecimalInputGuardInput(e) {
    var inp = e.target;
    if (!inp || !inp.classList || !inp.classList.contains('jwq-cell-input--decimal')) {
        return;
    }
    var clean = jwqSanitizeDecimalInputValue(inp.value);
    if (clean !== inp.value) {
        inp.value = clean;
    }
}

function jwqDecimalInputGuardPaste(e) {
    var inp = e.target;
    if (!inp || !inp.classList || !inp.classList.contains('jwq-cell-input--decimal')) {
        return;
    }
    e.preventDefault();
    var clip = (e.clipboardData || window.clipboardData).getData('text');
    var clean = jwqSanitizeDecimalInputValue(clip);
    if (clean === '') {
        return;
    }
    var start = typeof inp.selectionStart === 'number' ? inp.selectionStart : inp.value.length;
    var end = typeof inp.selectionEnd === 'number' ? inp.selectionEnd : start;
    var merged = jwqSanitizeDecimalInputValue(String(inp.value).slice(0, start) + clean + String(inp.value).slice(end));
    inp.value = merged;
    inp.dispatchEvent(new Event('input', { bubbles: true }));
}

function initJwqDecimalInputGuards() {
    var root = document.getElementById('jwqModalOverlay');
    if (!root || root._jwqDecimalGuardBound) {
        return;
    }
    root._jwqDecimalGuardBound = true;
    root.addEventListener('keydown', jwqDecimalInputGuardKeydown);
    root.addEventListener('input', jwqDecimalInputGuardInput);
    root.addEventListener('paste', jwqDecimalInputGuardPaste);
}

function jwqShouldPrefillLineWeights() {
    return window.__jwqPrefillLineWeights === true;
}

/** Sum Total Wt for bulk Jobwork Queue (multi-order) selection. */
function jwqRefreshOrderLinesTotalSummary() {
    var bar = document.getElementById('jwqOrderLinesTotalBar');
    var totEl = document.getElementById('jwqOrderLinesTotalWt');
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!bar || !totEl) {
        return;
    }
    if (!window.__jwqBulkMode) {
        bar.style.display = 'none';
        return;
    }
    var sum = 0;
    if (tbody) {
        tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
            var w = typeof jwqLineFieldNum === 'function' ? jwqLineFieldNum(tr, 'total_wt') : NaN;
            if (!isFinite(w) || w < 0) {
                w = 0;
            }
            if (w <= 0.0000001 && typeof jwqLineFormulaTotal === 'function' && typeof jwqLineFieldNum === 'function') {
                var mw = jwqLineFieldNum(tr, 'metal_wt');
                var lw = jwqLineFieldNum(tr, 'loss');
                var dw = jwqLineFieldNum(tr, 'diamond_wt');
                w = jwqLineFormulaTotal(mw, lw, dw);
            }
            if (isFinite(w) && w > 0) {
                sum += w;
            }
        });
    }
    totEl.textContent = sum.toFixed(3);
    bar.style.display = '';
}
window.jwqRefreshOrderLinesTotalSummary = jwqRefreshOrderLinesTotalSummary;

function jwqShouldSnapAddWeightOrig() {
    return jwqShouldPrefillLineWeights()
        || (typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi())
        || (typeof window.jwqIsReduceWeightExtraMetalUi === 'function' && window.jwqIsReduceWeightExtraMetalUi());
}

function jwqCellByKey(key, it, orderRef) {
    var raw = jwqCellRawByKey(key, it, orderRef);
    var weightExtra = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
    if (weightExtra) {
        if (key === 'metal_wt' || key === 'total_wt' || key === 'diamond_wt') {
            raw = jwqNum3(0);
        }
    } else if (!jwqShouldPrefillLineWeights()) {
        if (key === 'total_wt' || key === 'metal_wt' || key === 'expected_wt') {
            raw = jwqNum3(0);
        } else if (key === 'loss') {
            raw = jwqNumOptDash3(0);
        } else if (key === 'price') {
            raw = jwqNumOptDash2(0);
        }
    }
    if (key === 'order_no') {
        return jwqEsc(raw);
    }
    if (key === 'tag_no' || key === 'description' || key === 'total_wt') {
        return '<input class="jwq-cell-input jwq-cell-input--readonly" data-col-input="' + jwqEsc(key) + '" data-field="' + jwqEsc(key) + '" type="text" value="' + jwqInputEsc(raw) + '" readonly tabindex="-1">';
    }
    if (key === 'karat') {
        return jwqKaratSelectHtml(raw);
    }
    var cls = 'jwq-cell-input';
    var attrs = ' type="text"';
    if (jwqIsNumericCol(key)) {
        cls += ' jwq-cell-input--decimal';
        attrs += ' inputmode="decimal" autocomplete="off"';
    }
    return '<input class="' + cls + '" data-col-input="' + jwqEsc(key) + '" data-field="' + jwqEsc(key) + '"' + attrs + ' value="' + jwqInputEsc(raw) + '">';
}

function jwqLineOrigTotalWt(it) {
    it = it || {};
    var fw = parseFloat(it.final_weight);
    if (isFinite(fw) && fw > 0) {
        return fw;
    }
    var nw = parseFloat(it.net_weight);
    if (isFinite(nw) && nw > 0) {
        return nw;
    }
    var gw = parseFloat(it.gross_weight);
    if (isFinite(gw) && gw > 0) {
        return gw;
    }
    return 0;
}

/** Metal column: prefer net when set; otherwise same basis as total (gross after transfer often mirrors total). */
function jwqLineOrigMetalWt(it) {
    it = it || {};
    var nw = parseFloat(it.net_weight);
    if (isFinite(nw) && nw > 0) {
        return nw;
    }
    return jwqLineOrigTotalWt(it);
}

function jwqLineOrigDiamondWtFromItem(it) {
    it = it || {};
    var dStored = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        dStored = parseFloat(String(it.diamond_wt).replace(/,/g, ''));
    }
    if (!isFinite(dStored) || dStored <= 0) {
        dStored = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
    }
    if (isFinite(dStored) && dStored > 0) {
        return dStored;
    }
    var tw0 = jwqLineOrigTotalWt(it);
    var mw0 = jwqLineOrigMetalWt(it);
    if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
        return tw0 - mw0;
    }
    return 0;
}

/**
 * Carrying total for cards/modal — prefers queue_display_total_wt from API (matches job card 25).
 */
function jwqLineQueueCarryingTotalWt(it) {
    it = it || {};
    var formulaTot = jwqItemFormulaTotalWt(it);
    if (Object.prototype.hasOwnProperty.call(it, 'queue_display_total_wt')) {
        var qnS = it.queue_display_total_wt != null ? String(it.queue_display_total_wt).replace(/,/g, '') : '';
        var qnStrict = parseFloat(qnS);
        if (isFinite(qnStrict)) {
            if (formulaTot > qnStrict + 0.0001) {
                return formulaTot;
            }
            return qnStrict;
        }
    }
    var disp = jwqLineQueueDisplayTotalWt(it);
    if (formulaTot > disp + 0.0001) {
        return formulaTot;
    }
    return disp;
}

/**
 * Baseline weight for auto-loss (orig − current total): use max(metal, carrying total).
 */
function jwqLineAutoLossBaselineWt(it) {
    var m = jwqLineOrigMetalWt(it);
    var t = jwqLineQueueCarryingTotalWt(it);
    if (isFinite(m) && isFinite(t)) {
        return Math.max(m, t);
    }
    if (isFinite(m) && m > 0) {
        return m;
    }
    if (isFinite(t) && t > 0) {
        return t;
    }
    return 0;
}

function jwqOrderDiamondPoolWtFromItem(it) {
    it = it || {};
    var dStored = NaN;
    if (it.diamond_wt != null && it.diamond_wt !== '') {
        dStored = parseFloat(it.diamond_wt);
    }
    if (!isFinite(dStored) || dStored <= 0) {
        dStored = parseFloat(it.diamond_weight != null ? it.diamond_weight : NaN);
    }
    if (isFinite(dStored) && dStored > 0) {
        return dStored;
    }
    var tw0 = jwqLineOrigTotalWt(it);
    var mw0 = jwqLineOrigMetalWt(it);
    if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
        return tw0 - mw0;
    }
    return 0;
}

/** Grams saved on the jobwork line for loss (non-zero gold_loss_1 else loss_wt) — matches line_saved_loss_wt SQL. */
function jwqLineServerLossGrams(it) {
    it = it || {};
    if (it.queue_display_loss_wt != null && String(it.queue_display_loss_wt).trim() !== '') {
        var qx = parseFloat(String(it.queue_display_loss_wt).replace(/,/g, ''));
        if (isFinite(qx) && qx > 0.00001) {
            return qx;
        }
    }
    var gRaw = it.gold_loss_1;
    var lRaw = it.loss_wt;
    var g = NaN;
    if (gRaw !== null && gRaw !== undefined && String(gRaw).trim() !== '') {
        g = parseFloat(String(gRaw).replace(/,/g, ''));
    }
    if (isFinite(g) && Math.abs(g) > 0.00001) {
        return g;
    }
    if (lRaw !== null && lRaw !== undefined && String(lRaw).trim() !== '') {
        var l = parseFloat(String(lRaw).replace(/,/g, ''));
        if (isFinite(l)) {
            return l;
        }
    }
    return isFinite(g) ? g : 0;
}

/** If Loss input is still zero after load, restore server loss or infer metal + diamond − total (before diamond sync). */
function jwqReconcileLineLossFromWeights() {
    if (!jwqShouldPrefillLineWeights()) {
        return;
    }
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var lossEl = jwqLineFieldEl(tr, 'loss');
        if (!lossEl) {
            return;
        }
        var mw = jwqLineFieldNum(tr, 'metal_wt');
        var tw = jwqLineFieldNum(tr, 'total_wt');
        var dw = jwqLineFieldNum(tr, 'diamond_wt');
        if (!isFinite(dw) || dw < 0) {
            dw = 0;
        }
        var carrying = parseFloat(String(tr.getAttribute('data-orig-total-wt') || '').replace(/,/g, ''));
        if (isFinite(carrying) && isFinite(tw) && carrying > tw + 0.0001) {
            var tInp = jwqLineFieldEl(tr, 'total_wt');
            if (tInp) {
                tInp.value = jwqNum3(carrying);
            }
            tw = carrying;
        }
        if (typeof jwqClearStaleTransferInferredLoss === 'function') {
            jwqClearStaleTransferInferredLoss(tr, mw, dw, carrying);
        }
        var cur = jwqLineFieldNum(tr, 'loss');
        if (isFinite(cur) && cur > 0.00001) {
            return;
        }
        var saved = parseFloat(String(tr.getAttribute('data-jwq-saved-loss-grams') || '').replace(/,/g, ''));
        if (isFinite(saved) && saved > 0.00001) {
            lossEl.value = jwqNumOptDash3(saved);
            return;
        }
        if (!isFinite(mw) || !isFinite(tw)) {
            return;
        }
        var implied = mw + dw - tw;
        if (implied > 0.00001 && implied < 500000) {
            if (isFinite(carrying) && carrying > tw + 0.0001 && implied > carrying - mw - dw + 0.05) {
                /* Do not infer loss that would drop total below the job carrying weight. */
            } else {
                lossEl.value = jwqNumOptDash3(implied);
            }
        }
    });
}
window.jwqReconcileLineLossFromWeights = jwqReconcileLineLossFromWeights;

/** Clear inferred (non-saved) loss on a transfer line when it conflicts with carrying total. */
function jwqClearStaleTransferInferredLoss(tr, mw, dw, carrying) {
    if (!tr || typeof jwqIsNormalTransferQueueMode !== 'function' || !jwqIsNormalTransferQueueMode()) {
        return 0;
    }
    var savedLoss = parseFloat(String(tr.getAttribute('data-jwq-saved-loss-grams') || '').replace(/,/g, ''));
    if (isFinite(savedLoss) && savedLoss > 0.00001) {
        return isFinite(savedLoss) ? savedLoss : 0;
    }
    var lossEl = jwqLineFieldEl(tr, 'loss');
    var lw = lossEl ? jwqLineFieldNum(tr, 'loss') : 0;
    if (!isFinite(lw) || lw < 0) {
        lw = 0;
    }
    if (lw <= 0.00001) {
        return 0;
    }
    if (!isFinite(mw) || mw < 0) {
        mw = 0;
    }
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    var formula = mw - lw + dw;
    var stale = formula < mw - 0.0001
        || (isFinite(carrying) && carrying > formula + 0.0001);
    if (stale) {
        if (lossEl) {
            lossEl.value = jwqNumOptDash3(0);
        }
        return 0;
    }
    return lw;
}
window.jwqClearStaleTransferInferredLoss = jwqClearStaleTransferInferredLoss;

function jwqResolveTransferCarryingTotal(tr) {
    if (!tr) {
        return 0;
    }
    var carrying = parseFloat(String(tr.getAttribute('data-orig-total-wt') || '').replace(/,/g, ''));
    if (!isFinite(carrying) || carrying <= 0.0000001) {
        carrying = jwqLineFieldNum(tr, 'total_wt');
    }
    return isFinite(carrying) && carrying > 0 ? carrying : 0;
}
window.jwqResolveTransferCarryingTotal = jwqResolveTransferCarryingTotal;

/** Transfer: Total Wt = job carrying total (fixed); Metal Wt = Total − Diamond. */
function jwqApplyTransferLineCarryingTotalWt(tr) {
    if (!tr || !tr.getAttribute('data-item-id')) {
        return;
    }
    if (tr.getAttribute('data-jwq-na-floor') === '1') {
        return;
    }
    if (tr.getAttribute('data-jwq-total-manual') === '1') {
        return;
    }
    if (typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi()) {
        return;
    }
    if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
        return;
    }
    if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
        return;
    }
    var tInp = jwqLineFieldEl(tr, 'total_wt');
    var mInp = jwqLineFieldEl(tr, 'metal_wt');
    if (!tInp || !mInp) {
        return;
    }
    var totalFix = jwqResolveTransferCarryingTotal(tr);
    if (totalFix <= 0.0000001) {
        return;
    }
    var dw = jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(dw) || dw < 0) {
        dw = 0;
    }
    if (dw > totalFix + 0.0001) {
        dw = totalFix;
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (dInp) {
            dInp.value = jwqNum3(dw);
        }
    }
    var mw = Math.max(0, totalFix - dw);
    if (typeof jwqClearStaleTransferInferredLoss === 'function') {
        jwqClearStaleTransferInferredLoss(tr, mw, dw, totalFix);
    }
    mInp.value = jwqNum3(mw);
    tInp.value = jwqNum3(totalFix);
    tr.setAttribute('data-base-metal-wt', String(mw));
    tr.removeAttribute('data-jwq-freeze-weights');
    tr.removeAttribute('data-jwq-total-manual');
}
window.jwqApplyTransferLineCarryingTotalWt = jwqApplyTransferLineCarryingTotalWt;

function jwqApplyTransferLineCarryingTotalAll() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        jwqApplyTransferLineCarryingTotalWt(tr);
    });
    if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
        jwqRefreshOrderLinesTotalSummary();
    }
}
window.jwqApplyTransferLineCarryingTotalAll = jwqApplyTransferLineCarryingTotalAll;

function jwqDeptHasAutoLoss(deptId) {
    var id = parseInt(deptId, 10) || 0;
    if (id < 1) {
        return false;
    }
    var list = window.mpDepartments || [];
    for (var i = 0; i < list.length; i++) {
        var d = list[i];
        if (parseInt(d.id, 10) === id) {
            var al = d.auto_loss;
            if (al === true || al === 1 || al === '1') {
                return true;
            }
            if (typeof al === 'string' && al.toLowerCase() === 'on') {
                return true;
            }
            return parseInt(al, 10) === 1;
        }
    }
    return false;
}

function jwqAutoLossBaselineForRow(tr) {
    if (!tr) {
        return 0;
    }
    var orig = parseFloat(String(tr.getAttribute('data-orig-total-wt') || '').replace(/,/g, ''));
    if (isFinite(orig) && orig > 0.0000001) {
        return orig;
    }
    var origM = parseFloat(String(tr.getAttribute('data-jwq-orig-metal-wt') || '').replace(/,/g, ''));
    var dwCur = jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(dwCur) || dwCur < 0) {
        dwCur = 0;
    }
    if (isFinite(origM) && origM > 0.0000001) {
        return origM + dwCur;
    }
    var mwCur = jwqLineFieldNum(tr, 'metal_wt');
    if (isFinite(mwCur) && mwCur > 0.0000001) {
        return mwCur + dwCur;
    }
    return 0;
}

function jwqAutoLossResolveTarget(tr, sourceField, baseline) {
    var ewEl = jwqLineFieldEl(tr, 'expected_wt');
    var twEl = jwqLineFieldEl(tr, 'total_wt');
    var mwEl = jwqLineFieldEl(tr, 'metal_wt');
    var ew = ewEl ? parseFloat(String(ewEl.value || '').replace(/,/g, '')) : NaN;
    var tw = twEl ? parseFloat(String(twEl.value || '').replace(/,/g, '')) : NaN;
    var mw = mwEl ? parseFloat(String(mwEl.value || '').replace(/,/g, '')) : NaN;
    var eps = 0.0000001;
    if (sourceField === 'expected_wt' && isFinite(ew) && ew > eps) {
        return { target: ew, mode: 'expected' };
    }
    if (sourceField === 'metal_wt' && isFinite(mw) && mw > eps) {
        return { target: mw, mode: 'metal' };
    }
    if (sourceField === 'refresh' || sourceField === '') {
        if (isFinite(mw) && mw > eps && mw < baseline - eps) {
            return { target: mw, mode: 'metal' };
        }
        if (isFinite(ew) && ew > eps && ew < baseline - eps) {
            return { target: ew, mode: 'expected' };
        }
        if (isFinite(tw) && tw > eps && tw < baseline - eps) {
            return { target: tw, mode: 'total' };
        }
        return null;
    }
    if (isFinite(mw) && mw > eps && mw < baseline - eps) {
        return { target: mw, mode: 'metal' };
    }
    if (isFinite(ew) && ew > eps && ew < baseline - eps) {
        return { target: ew, mode: 'expected' };
    }
    if (isFinite(tw) && tw > eps && tw < baseline - eps) {
        return { target: tw, mode: 'total' };
    }
    return null;
}

function jwqMaybeApplyAutoLoss(tr, sourceField) {
    sourceField = sourceField || '';
    if (!tr || !tr.getAttribute('data-item-id')) {
        return;
    }
    if (typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi()) {
        return;
    }
    if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
        return;
    }
    if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
        return;
    }
    var fromDept = document.getElementById('jwqFromDept');
    var fd = fromDept ? parseInt(fromDept.value || '0', 10) : 0;
    if (!jwqDeptHasAutoLoss(fd)) {
        return;
    }
    var baseline = jwqAutoLossBaselineForRow(tr);
    if (!isFinite(baseline) || baseline <= 0.0000001) {
        return;
    }
    var resolved = jwqAutoLossResolveTarget(tr, sourceField, baseline);
    if (!resolved || !isFinite(resolved.target) || resolved.target >= baseline - 0.0000001) {
        return;
    }
    if (sourceField !== 'expected_wt' && sourceField !== 'refresh' && sourceField !== 'metal_wt') {
        var savedLossGrams = parseFloat(String(tr.getAttribute('data-jwq-saved-loss-grams') || '').replace(/,/g, ''));
        if (isFinite(savedLossGrams) && savedLossGrams > 0.00001) {
            return;
        }
    }
    var loss = baseline - resolved.target;
    if (loss < 0) {
        loss = 0;
    }
    var lossEl = jwqLineFieldEl(tr, 'loss');
    var twEl = jwqLineFieldEl(tr, 'total_wt');
    var mwEl = jwqLineFieldEl(tr, 'metal_wt');
    if (lossEl) {
        lossEl.value = loss > 0.0000001 ? jwqNumOptDash3(loss) : jwqNumOptDash3(0);
    }
    if (!twEl) {
        return;
    }
    var dwSync = jwqLineFieldNum(tr, 'diamond_wt');
    if (!isFinite(dwSync) || dwSync < 0) {
        dwSync = 0;
    }
    window.__jwqIsRecalculatingWeight = true;
    try {
        if (resolved.mode === 'expected') {
            var mwOrig = parseFloat(String(tr.getAttribute('data-jwq-orig-metal-wt') || '').replace(/,/g, ''));
            if (!isFinite(mwOrig) || mwOrig <= 0.0000001) {
                mwOrig = baseline;
            }
            if (mwEl) {
                mwEl.value = jwqNum3(mwOrig);
            }
            twEl.value = jwqNum3(jwqLineFormulaTotal(mwOrig, loss, dwSync));
        } else {
            twEl.value = jwqNum3(resolved.target);
        }
        tr.removeAttribute('data-jwq-total-manual');
        tr.removeAttribute('data-jwq-freeze-weights');
    } finally {
        window.__jwqIsRecalculatingWeight = false;
    }
    if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
        jwqRefreshOrderLinesTotalSummary();
    }
}

function jwqRefreshAutoLossAllRows() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        jwqMaybeApplyAutoLoss(tr, 'refresh');
    });
}

/** Roll orphan item id 0 and stale item ids into first visible order line (same for plus and used-pick maps). */
function jwqRollMaterialDiamondOrphanIntoFirst(byItem, tbody) {
    if (!byItem || !tbody) {
        return;
    }
    var firstRowId = null;
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var rowItemId = parseInt(tr.getAttribute('data-item-id') || '0', 10);
        if (firstRowId === null && rowItemId > 0) {
            firstRowId = rowItemId;
        }
    });
    if (firstRowId !== null && byItem[0] > 0) {
        byItem[firstRowId] = (byItem[firstRowId] || 0) + byItem[0];
        delete byItem[0];
    }
    /* Transfer can recreate line item ids; if saved diamonds target old ids, map all unmatched weight to first visible line. */
    if (firstRowId !== null) {
        var visibleIds = {};
        tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
            var rid = parseInt(tr.getAttribute('data-item-id') || '0', 10);
            if (rid > 0) {
                visibleIds[rid] = true;
            }
        });
        var unmatched = 0;
        Object.keys(byItem).forEach(function (k) {
            var kid = parseInt(k || '0', 10);
            if (kid > 0 && !visibleIds[kid]) {
                unmatched += byItem[kid] || 0;
                delete byItem[k];
            }
        });
        if (unmatched > 0) {
            byItem[firstRowId] = (byItem[firstRowId] || 0) + unmatched;
        }
    }
}

/** Prefer the material tbody under #jwqMatTableWrap when present (standalone jobwork-queue page). */
function jwqGetJobworkMaterialBody() {
    var wrap = document.getElementById('jwqMatTableWrap');
    if (wrap) {
        var tb = wrap.querySelector('#jwqMaterialBody');
        if (tb) {
            return tb;
        }
    }
    var overlay = document.getElementById('jwqModalOverlay');
    if (overlay) {
        var tb2 = overlay.querySelector('#jwqMaterialBody');
        if (tb2) {
            return tb2;
        }
    }
    return document.getElementById('jwqMaterialBody');
}

/** Last-known issue weight for a stock line (from mp-get-jobwork-queue-diamonds). */
function jwqDiamondIssueWeightFromServerByStockId(stockId) {
    var sid = parseInt(String(stockId != null ? stockId : '0'), 10) || 0;
    if (sid < 1) {
        return 0;
    }
    var srv = window.__jwqLastDiamondServerItems || [];
    var i;
    var r;
    var iw;
    for (i = 0; i < srv.length; i++) {
        r = srv[i];
        if (!r) {
            continue;
        }
        if ((parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0) !== sid) {
            continue;
        }
        iw = parseFloat(String((r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : '')) || '').replace(/,/g, '')) || 0;
        if (iw > 0.0000001) {
            return iw;
        }
    }
    return 0;
}

/**
 * Display weight on the material row; for picks from "(i) Diamonds used" fall back to data-weight then server issue
 * so reduce-to-zero on the grid still releases stock and syncs Diamond Wt.
 */
function jwqMaterialDiamondRowEffectiveWeight(tr) {
    if (!tr) {
        return 0;
    }
    var wtTd = tr.querySelector('.jwq-mat-wt');
    var tds = tr.querySelectorAll('td');
    var wtTxt = wtTd ? wtTd.textContent : (tds[2] ? tds[2].textContent : '');
    var wt = parseFloat(String(wtTxt || '').replace(/,/g, '')) || 0;
    if (wt <= 0.0000001 && tr.dataset && tr.dataset.weight) {
        wt = parseFloat(String(tr.dataset.weight).replace(/,/g, '')) || 0;
    }
    if (wt <= 0.0000001 && tr.getAttribute('data-jwq-from-used-modal') === '1') {
        var sid = parseInt(tr.getAttribute('data-stock-id') || (tr.dataset && tr.dataset.stockId) || '0', 10) || 0;
        wt = jwqDiamondIssueWeightFromServerByStockId(sid);
    }
    return wt;
}

/**
 * Per jobwork line item: diamond material weights split for line sync.
 * plus = rows from stock / server grid; usedPick = rows tagged from "Diamonds used" (already on job — reduces header pool).
 */
function jwqMaterialDiamondWeightByItemSplit(matBody, tbody) {
    var plus = {};
    var usedPick = {};
    if (!matBody || !tbody) {
        return { plus: plus, usedPick: usedPick };
    }
    matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
        if (tr.getAttribute('data-jwq-exclude-from-line-sync') === '1') {
            return;
        }
        var itemId = parseInt(tr.getAttribute('data-jobwork-item-id') || '0', 10);
        if (itemId < 1) {
            itemId = 0;
        }
        var wt = jwqMaterialDiamondRowEffectiveWeight(tr);
        if (wt <= 0) {
            return;
        }
        var bucket = tr.getAttribute('data-jwq-from-used-modal') === '1' ? usedPick : plus;
        bucket[itemId] = (bucket[itemId] || 0) + wt;
    });
    jwqRollMaterialDiamondOrphanIntoFirst(plus, tbody);
    jwqRollMaterialDiamondOrphanIntoFirst(usedPick, tbody);
    return { plus: plus, usedPick: usedPick };
}

function jwqStockIdsOnMaterialDiamondGrid(matBody) {
    var sids = {};
    var barcodes = {};
    if (!matBody) {
        return { sids: sids, barcodes: barcodes };
    }
    matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
        var sid = parseInt(tr.getAttribute('data-stock-id') || tr.dataset.stockId || '0', 10) || 0;
        if (sid > 0) {
            sids[sid] = true;
        }
        var bc = String(tr.getAttribute('data-barcode') || '').trim().toUpperCase();
        if (!bc) {
            var tds = tr.querySelectorAll('td');
            var t1 = tds[1] ? String(tds[1].textContent || '').trim() : '';
            var sepIdx = t1.indexOf(' — ');
            if (sepIdx === -1) {
                sepIdx = t1.indexOf('\u2014');
            }
            if (sepIdx === -1) {
                sepIdx = t1.indexOf(' - ');
            }
            bc = (sepIdx > 0 ? t1.slice(0, sepIdx).trim() : t1).toUpperCase();
        }
        if (bc) {
            barcodes[bc] = true;
        }
    });
    return { sids: sids, barcodes: barcodes };
}

function jwqIssueStockSidSetFromServer(serverRows) {
    var s = {};
    if (!Array.isArray(serverRows)) {
        return s;
    }
    serverRows.forEach(function (r) {
        if (!r || String(r.row_source || '') === 'line_fallback' || jwqDiamondUsedRowIsSummary(r)) {
            return;
        }
        var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var bc = String(r.barcode || '').trim();
        if (sid < 1 || bc === '' || bc === '—') {
            return;
        }
        s[sid] = true;
    });
    return s;
}

function jwqMaterialDiamondPlusIssuedByItem(matBody, tbody, issueSidSet) {
    var by = {};
    if (!matBody || !tbody) {
        return by;
    }
    if (!issueSidSet || typeof issueSidSet !== 'object') {
        return by;
    }
    matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
        if (tr.getAttribute('data-jwq-exclude-from-line-sync') === '1') {
            return;
        }
        if (tr.getAttribute('data-jwq-from-used-modal') === '1') {
            return;
        }
        var sid = parseInt(tr.getAttribute('data-stock-id') || tr.dataset.stockId || '0', 10) || 0;
        if (!issueSidSet[sid]) {
            return;
        }
        var itemId = parseInt(tr.getAttribute('data-jobwork-item-id') || '0', 10) || 0;
        if (itemId < 1) {
            itemId = 0;
        }
        var wt = jwqMaterialDiamondRowEffectiveWeight(tr);
        if (wt <= 0) {
            return;
        }
        by[itemId] = (by[itemId] || 0) + wt;
    });
    jwqRollMaterialDiamondOrphanIntoFirst(by, tbody);
    return by;
}

function jwqRecomputeOpenIssueDiamondWtByItem(serverRows) {
    var matBody = jwqGetJobworkMaterialBody();
    var tbody = document.getElementById('jwqOrderLinesBody');
    var by = {};
    if (!tbody) {
        window.__jwqOpenIssueDiamondWtByItem = by;
        return;
    }
    if (!Array.isArray(serverRows)) {
        serverRows = [];
    }
    var onGrid = jwqStockIdsOnMaterialDiamondGrid(matBody);
    serverRows.forEach(function (r) {
        if (!r || String(r.row_source || '') === 'line_fallback' || jwqDiamondUsedRowIsSummary(r)) {
            return;
        }
        var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var bc = String(r.barcode || '').trim();
        var bcU = bc.toUpperCase();
        if (sid < 1 || bc === '' || bc === '—') {
            return;
        }
        if (onGrid.sids[sid]) {
            return;
        }
        if (bcU && onGrid.barcodes[bcU]) {
            return;
        }
        var wRaw = r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : '');
        var wNum = parseFloat(String(wRaw).replace(/,/g, '')) || 0;
        if (wNum <= 0.0000001) {
            return;
        }
        var itemId = parseInt(String(r.jobwork_order_item_id != null ? r.jobwork_order_item_id : '0'), 10) || 0;
        by[itemId] = (by[itemId] || 0) + wNum;
    });
    jwqRollMaterialDiamondOrphanIntoFirst(by, tbody);
    window.__jwqOpenIssueDiamondWtByItem = by;
}

/** Diamond on line not covered by material grid rows; line diamond = base + material sum. */
function jwqInitLineBaseDiamondWt(tr, dInp, tInp, mInp, sumMat) {
    var curD = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
    var inferred = 0;
    if (tInp && mInp) {
        var tw0 = parseFloat(tInp.value);
        var mw0 = parseFloat(mInp.value);
        if (isFinite(tw0) && isFinite(mw0) && tw0 > mw0 + 0.0000001) {
            inferred = tw0 - mw0;
        }
    }
    var baseDiamond = 0;
    if (curD > 0.0000001) {
        if (sumMat <= curD + 0.0000001) {
            baseDiamond = curD - sumMat;
        } else {
            /* e.g. line still shows old diamond before grid picked up new rows — keep line as base, add material on top */
            baseDiamond = curD;
        }
    } else if (inferred > 0.0000001 && sumMat <= 0.0000001) {
        baseDiamond = inferred;
    }
    if (baseDiamond < 0) {
        baseDiamond = 0;
    }
    tr.setAttribute('data-jwq-base-diamond-wt', jwqNum3(baseDiamond));
    return baseDiamond;
}

function jwqRefreshLineDiamondBaseFromUi(tr) {
    var matBody = jwqGetJobworkMaterialBody();
    var tbody = document.getElementById('jwqOrderLinesBody');
    var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'diamond_wt') : null;
    if (!matBody || !tbody || !dInp) {
        return;
    }
    var split = jwqMaterialDiamondWeightByItemSplit(matBody, tbody);
    var itemId = parseInt(tr.getAttribute('data-item-id') || '0', 10);
    var sumPlus = split.plus[itemId] || 0;
    var D = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
    tr.setAttribute('data-jwq-base-diamond-wt', jwqNum3(Math.max(0, D - sumPlus)));
}

/** Refresh __jwqLastDiamondServerItems from DB (no material grid rebuild), then callback — keeps open-issue weights in sync before save / sync. */
function jwqFetchDiamondServerItemsThen(callback) {
    var jwo = document.getElementById('jwqCurrentJwoId');
    var jwoId = jwo ? (parseInt(jwo.value || '0', 10) || 0) : 0;
    if (jwoId < 1) {
        if (typeof callback === 'function') {
            callback();
        }
        return;
    }
    fetch('ajax/mp-get-jobwork-queue-diamonds.php?jobwork_order_id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) {
            return r.json();
        })
        .then(function (d) {
            var rows = (d && d.ok && Array.isArray(d.items)) ? d.items : [];
            window.__jwqLastDiamondServerItems = rows.filter(function (row) {
                return typeof jwqIsServerDiamondRowRemovedFromUi !== 'function' || !jwqIsServerDiamondRowRemovedFromUi(row);
            });
            if (typeof callback === 'function') {
                callback();
            }
        })
        .catch(function () {
            if (typeof callback === 'function') {
                callback();
            }
        });
}
window.jwqFetchDiamondServerItemsThen = jwqFetchDiamondServerItemsThen;

/** Transfer modal (not add/reduce weight): diamond on line follows material grid + issued stock only. */
function jwqIsNormalTransferQueueMode() {
    if (typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi()) {
        return false;
    }
    if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
        return false;
    }
    if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()) {
        return false;
    }
    return true;
}
window.jwqIsNormalTransferQueueMode = jwqIsNormalTransferQueueMode;

function jwqSyncOrderLineDiamondWtFromMaterialTable() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    var matBody = jwqGetJobworkMaterialBody();
    if (!tbody || !matBody) {
        return;
    }
    jwqRecomputeOpenIssueDiamondWtByItem(window.__jwqLastDiamondServerItems || []);
    var split = jwqMaterialDiamondWeightByItemSplit(matBody, tbody);
    var byPlus = split.plus;
    var byUsedPick = split.usedPick;
    var issueSidSet = jwqIssueStockSidSetFromServer(window.__jwqLastDiamondServerItems || []);
    var byPlusIssued = jwqMaterialDiamondPlusIssuedByItem(matBody, tbody, issueSidSet);
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var addExtraSyncEarly = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
        if (addExtraSyncEarly) {
            var dInpEarly = jwqLineFieldEl(tr, 'diamond_wt');
            if (dInpEarly) {
                dInpEarly.value = jwqNum3(0);
            }
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()
                && typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                window.jwqApplyAddWeightManualMetalPreview(tr);
            } else if (typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode()
                && typeof window.jwqApplyReduceWeightManualMetalPreview === 'function') {
                window.jwqApplyReduceWeightManualMetalPreview(tr);
            }
            return;
        }
        var itemId = parseInt(tr.getAttribute('data-item-id') || '0', 10);
        var dInp = jwqLineFieldEl(tr, 'diamond_wt');
        if (!dInp) {
            return;
        }
        var sumPlus = byPlus[itemId] || 0;
        var sumUsedPick = byUsedPick[itemId] || 0;
        var sumOpenIssued = 0;
        var oiMap = window.__jwqOpenIssueDiamondWtByItem;
        if (oiMap && typeof oiMap === 'object') {
            sumOpenIssued = oiMap[itemId] || 0;
        }
        var gridDiamondWt = sumPlus + sumUsedPick;
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var mInp = jwqLineFieldEl(tr, 'metal_wt');
        var orderPool = parseFloat(tr.getAttribute('data-jwq-order-diamond-wt'));
        if (!isFinite(orderPool) || orderPool < 0) {
            orderPool = 0;
        }
        if (tr.getAttribute('data-jwq-diamond-manual') === '1') {
            /* User typed Diamond Wt: only real issued/grid diamonds may change it, not the BOM pool gap. */
            orderPool = 0;
        }
        if (orderPool < 0.0000001) {
            var twInf = jwqLineFieldNum(tr, 'total_wt');
            var mwInf = jwqLineFieldNum(tr, 'metal_wt');
            var gap = (isFinite(twInf) && isFinite(mwInf) && twInf > mwInf + 0.0000001) ? (twInf - mwInf) : 0;
            var onJobPre = sumOpenIssued + gridDiamondWt;
            if (gap > 0.0000001 && onJobPre > 0.0000001 && Math.abs(gap - onJobPre) < 0.05) {
                /* total − metal already equals issued + grid; do not treat as a separate BOM pool (avoids double-count). */
                orderPool = 0;
            } else if (gap > 0.0000001) {
                orderPool = gap;
            }
        }
        if (orderPool < 0.0000001 && sumUsedPick > 0.0000001) {
            var dCurBoot = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
            var pIssBoot = byPlusIssued[itemId] || 0;
            orderPool = sumUsedPick + dCurBoot - sumPlus + pIssBoot;
            if (orderPool < sumUsedPick - 0.00001) {
                orderPool = sumUsedPick;
            }
            /* Do not persist heuristic pool on the row (avoids feedback loop with total_wt). */
        }
        var sumPlusIssued = byPlusIssued[itemId] || 0;
        var twLine = jwqLineFieldNum(tr, 'total_wt');
        var mwLine = jwqLineFieldNum(tr, 'metal_wt');
        var onJobDiamond = sumOpenIssued + gridDiamondWt;
        var twExplained =
            isFinite(twLine) &&
            isFinite(mwLine) &&
            onJobDiamond > 0.0000001 &&
            Math.abs(twLine - mwLine - onJobDiamond) < 0.05;
        var twMetalEqual =
            isFinite(twLine) && isFinite(mwLine) && Math.abs(twLine - mwLine) < 0.0000001;
        var poolNegligible = orderPool < 0.0000001;
        var noBomDiamondPool = poolNegligible && twMetalEqual;
        var dWt;
        if (twExplained) {
            /* total_wt already matches metal + diamonds on this job — use that, not pool − open (avoids duplicate grams). */
            dWt = onJobDiamond;
        } else if (noBomDiamondPool && (sumOpenIssued > 0.0000001 || gridDiamondWt > 0.0000001)) {
            dWt = onJobDiamond;
        } else {
            dWt = orderPool - sumOpenIssued - sumUsedPick - sumPlusIssued + sumPlus;
        }
        if (!isFinite(dWt) || dWt < 0) {
            dWt = 0;
        }
        /* total_wt − metal_wt can lag behind the grid after adding a row; do not undercut grid + open issue sum. */
        var lineGap = (isFinite(twLine) && isFinite(mwLine) && twLine > mwLine + 0.0000001) ? (twLine - mwLine) : 0;
        if (onJobDiamond > dWt + 0.0001 && onJobDiamond > lineGap + 0.0001) {
            dWt = onJobDiamond;
        }
        var matDiamondActive = gridDiamondWt > 0.0000001 || sumPlusIssued > 0.0000001;
        if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()
            && tr.getAttribute('data-jwq-diamond-manual') !== '1') {
            if (!matDiamondActive) {
                /* No diamonds on material grid — do not subtract open-issue/BOM pool from metal on transfer. */
                dWt = 0;
            } else if (onJobDiamond < 0.0000001) {
                dWt = 0;
            }
        }
        var prevDWt = jwqLineFieldNum(tr, 'diamond_wt');
        if (!isFinite(prevDWt) || prevDWt < 0) {
            prevDWt = 0;
        }
        dInp.value = jwqNum3(dWt);
        var diamondChanged = Math.abs(prevDWt - dWt) > 0.0001;

        var mismatch = jwqLineStoredTotalMismatch(tr, dWt);
        if (tr.getAttribute('data-jwq-freeze-weights') === '1' && !mismatch && !diamondChanged) {
            return;
        }
        if (tr.getAttribute('data-jwq-total-manual') === '1' && !diamondChanged && !mismatch) {
            return;
        }
        if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
            var addExtraSync = typeof window.jwqIsAddWeightExtraMetalUi === 'function' && window.jwqIsAddWeightExtraMetalUi();
            if (addExtraSync) {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
            var addManual = parseFloat(tr.getAttribute('data-jwq-manual-add-grams') || '');
            var addOrigT = parseFloat(tr.getAttribute('data-jwq-orig-total-before-add') || tr.getAttribute('data-orig-total-wt') || '');
            if ((isFinite(addManual) && addManual > 0.0001)
                || (isFinite(twLine) && isFinite(addOrigT) && twLine > addOrigT + 0.0001)) {
                if (typeof window.jwqApplyAddWeightManualMetalPreview === 'function') {
                    window.jwqApplyAddWeightManualMetalPreview(tr);
                }
                return;
            }
        }

        if (tInp && mInp) {
            window.__jwqIsRecalculatingWeight = true;
            try {
                if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()) {
                    var totalFix = jwqResolveTransferCarryingTotal(tr);
                    if (totalFix <= 0.0000001) {
                        totalFix = jwqLineFieldNum(tr, 'total_wt');
                    }
                    if (!isFinite(dWt) || dWt < 0) {
                        dWt = 0;
                    }
                    if (isFinite(totalFix) && dWt > totalFix + 0.0001) {
                        dWt = totalFix;
                        dInp.value = jwqNum3(dWt);
                    }
                    var userMetalKeep = jwqLineFieldNum(tr, 'metal_wt');
                    var preserveUserMetal = tr.getAttribute('data-jwq-total-manual') === '1'
                        || tr.getAttribute('data-jwq-diamond-manual') === '1'
                        || (!matDiamondActive && isFinite(userMetalKeep) && userMetalKeep > 0.0000001);
                    if (preserveUserMetal) {
                        if (!matDiamondActive) {
                            dWt = 0;
                        }
                        dInp.value = jwqNum3(dWt);
                        if (isFinite(totalFix) && totalFix > 0.0000001) {
                            tInp.value = jwqNum3(totalFix);
                        }
                        tr.setAttribute('data-base-metal-wt', String(userMetalKeep));
                        return;
                    }
                    var newMetal = Math.max(0, (isFinite(totalFix) ? totalFix : 0) - dWt);
                    if (typeof jwqClearStaleTransferInferredLoss === 'function') {
                        jwqClearStaleTransferInferredLoss(tr, newMetal, dWt, totalFix);
                    }
                    mInp.value = jwqNum3(newMetal);
                    if (isFinite(totalFix) && totalFix > 0.0000001) {
                        tInp.value = jwqNum3(totalFix);
                    }
                    tr.setAttribute('data-base-metal-wt', String(newMetal));
                    tr.removeAttribute('data-jwq-freeze-weights');
                } else {
                var baseMetal = jwqLineFieldNum(tr, 'metal_wt');
                if (!isFinite(baseMetal) || baseMetal < 0) {
                    baseMetal = parseFloat(tr.getAttribute('data-base-metal-wt') || '');
                }
                if (baseMetal < 0.0000001 && dWt > 0.0000001) {
                    var origM = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
                    if (isFinite(origM) && origM > 0.0000001) {
                        baseMetal = origM;
                    }
                }
                if (!isFinite(baseMetal) || baseMetal < 0) {
                    baseMetal = 0;
                }
                tr.setAttribute('data-base-metal-wt', String(baseMetal));
                var lossNum = jwqLineFieldNum(tr, 'loss');
                if (!isFinite(lossNum) || lossNum < 0) {
                    lossNum = 0;
                }
                var carryingOrig = parseFloat(tr.getAttribute('data-orig-total-wt') || '');
                if (typeof jwqClearStaleTransferInferredLoss === 'function') {
                    lossNum = jwqClearStaleTransferInferredLoss(tr, baseMetal, dWt, carryingOrig);
                }
                var newTotal = baseMetal - lossNum + dWt;
                if (typeof jwqIsNormalTransferQueueMode === 'function' && jwqIsNormalTransferQueueMode()
                    && isFinite(carryingOrig) && carryingOrig > newTotal + 0.0001) {
                    newTotal = carryingOrig;
                }
                if (!isFinite(newTotal) || newTotal < 0) {
                    newTotal = 0;
                }
                mInp.value = jwqNum3(baseMetal);
                tInp.value = jwqNum3(newTotal);
                tr.removeAttribute('data-jwq-total-manual');
                tr.removeAttribute('data-jwq-freeze-weights');
                }
            } finally {
                window.__jwqIsRecalculatingWeight = false;
            }
        }
    });
    if (!window.__jwqSavingQueue) {
        if (typeof jwqRefreshAutoLossAllRows === 'function') {
            jwqRefreshAutoLossAllRows();
        }
        if (typeof jwqApplyTransferLineCarryingTotalAll === 'function' && !window.__jwqBulkMode) {
            jwqApplyTransferLineCarryingTotalAll();
        }
    }
}
window.jwqSyncOrderLineDiamondWtFromMaterialTable = jwqSyncOrderLineDiamondWtFromMaterialTable;

function jwqDebugLogQueueLinesBeforeSave() {
    if (!window.console || typeof console.log !== 'function') {
        return;
    }
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        var id = parseInt(tr.getAttribute('data-item-id'), 10) || 0;
        var og = tr.getAttribute('data-jwq-orig-gross');
        var on = tr.getAttribute('data-jwq-orig-net');
        var of = tr.getAttribute('data-jwq-orig-final');
        var mw = jwqLineFieldNum(tr, 'metal_wt');
        var dw = jwqLineFieldNum(tr, 'diamond_wt');
        var lw = jwqLineFieldNum(tr, 'loss');
        var tw = jwqLineFieldNum(tr, 'total_wt');
        var calc = (isFinite(mw) ? mw : 0) + (isFinite(dw) ? dw : 0) - (isFinite(lw) ? lw : 0);
        if (!isFinite(calc) || calc < 0) {
            calc = 0;
        }
        console.log('JWQ_SAVE_DEBUG_ROW', {
            item_id: id,
            gross_weight: og,
            net_weight: on,
            final_weight: of,
            metal_wt: mw,
            diamond_wt: dw,
            loss_wt: lw,
            total_wt_input: tw,
            calculated_total_wt: calc
        });
    });
}

function jwqCollectQueueLinePayload(jwoIdFilter) {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return [];
    }
    var filterJwo = parseInt(jwoIdFilter != null ? jwoIdFilter : 0, 10) || 0;
    var out = [];
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        if (filterJwo > 0) {
            var rowJwo = parseInt(tr.getAttribute('data-jwo-id') || '0', 10) || 0;
            if (rowJwo !== filterJwo) {
                return;
            }
        }
        var id = parseInt(tr.getAttribute('data-item-id'), 10);
        if (!id) {
            return;
        }
        var total_wt = jwqLineFieldNum(tr, 'total_wt');
        var metal_wt = jwqLineFieldNum(tr, 'metal_wt');
        var diamond_wt = jwqLineFieldNum(tr, 'diamond_wt');
        var dust_wastage_wt = jwqLineFieldNum(tr, 'dust_wastage_wt');
        var loss = jwqLineFieldNum(tr, 'loss');
        if (!isFinite(loss) || loss < 0) {
            loss = 0;
        }
        if (!isFinite(total_wt)) {
            return;
        }
        if (!isFinite(metal_wt)) {
            metal_wt = total_wt;
        }
        if (!isFinite(diamond_wt) || diamond_wt < 0) {
            diamond_wt = 0;
        }
        var line = { item_id: id, total_wt: total_wt, metal_wt: metal_wt, diamond_wt: diamond_wt, loss: loss };
        if (isFinite(dust_wastage_wt) && dust_wastage_wt >= 0) {
            line.dust_wastage_wt = dust_wastage_wt;
        }
        out.push(line);
    });
    return out;
}

function jwqResolveMatRowStockId(tr) {
    var attrs = ['data-stock-id', 'data-id', 'data-diamond-stock-id'];
    var i, n, v;
    for (i = 0; i < attrs.length; i++) {
        v = tr.getAttribute(attrs[i]);
        if (v) {
            n = parseInt(v, 10);
            if (n > 0) {
                return n;
            }
        }
    }
    var inps = tr.querySelectorAll('input[name="stock_id"],input[name*="stock_id"],input[name*="[stock_id]"],input[name="material_stock_id"]');
    for (i = 0; i < inps.length; i++) {
        n = parseInt(String(inps[i].value || '').trim(), 10);
        if (n > 0) {
            return n;
        }
    }
    var cbs = tr.querySelectorAll('input[type="checkbox"]');
    for (i = 0; i < cbs.length; i++) {
        v = String(cbs[i].value || '').trim();
        if (v) {
            n = parseInt(v, 10);
            if (n > 0) {
                return n;
            }
        }
    }
    return 0;
}

function jwqResolveMatRowBarcode(tr, tds) {
    var b = (tr.getAttribute('data-barcode') || '').trim();
    if (b) {
        return b;
    }
    var tdBc = tr.querySelector('td[data-col="barcode_no"],td[data-col="barcode"]');
    if (tdBc) {
        b = String(tdBc.textContent || '').trim();
        if (b) {
            return b;
        }
    }
    var inp = tr.querySelector('input[name*="barcode"],input[name*="Barcode"],input.jwq-mat-barcode');
    if (inp && inp.value) {
        return String(inp.value).trim();
    }
    if (tds && tds.length > 1) {
        var t1 = String(tds[1].textContent || '').trim();
        var sepIdx = t1.indexOf(' — ');
        if (sepIdx === -1) {
            sepIdx = t1.indexOf('\u2014');
        }
        if (sepIdx === -1) {
            sepIdx = t1.indexOf(' - ');
        }
        if (sepIdx > 0) {
            var firstPart = t1.slice(0, sepIdx).trim();
            if (firstPart && /^[A-Za-z0-9\-_.]{2,}$/.test(firstPart)) {
                return firstPart;
            }
        }
        if (/^[A-Za-z0-9\-_.]{3,}$/.test(t1) && t1.length < 80) {
            return t1;
        }
    }
    return '';
}

function jwqMaterialTableLooksLikeItHasDiamondRows() {
    var body = jwqGetJobworkMaterialBody();
    if (!body) {
        return false;
    }
    if (body.querySelector('.jwq-material-diamond-row')) {
        return true;
    }
    var trs = body.querySelectorAll('tr');
    var k;
    for (k = 0; k < trs.length; k++) {
        var tr = trs[k];
        if (tr.classList.contains('jwq-mat-empty')) {
            continue;
        }
        if (tr.querySelector('td[colspan]')) {
            continue;
        }
        var tds = tr.querySelectorAll('td');
        if (tds.length < 2) {
            continue;
        }
        if (tr.getAttribute('data-jwq-mat-from-diamond') === '1') {
            return true;
        }
        if (jwqResolveMatRowStockId(tr) > 0) {
            return true;
        }
        var cat = String(tds[0].textContent || '').trim().toLowerCase();
        if (cat.indexOf('diamond') !== -1) {
            return true;
        }
        var wtEl = tr.querySelector('.jwq-mat-wt');
        var w = wtEl ? (parseFloat(String(wtEl.textContent || '').replace(/,/g, '')) || 0) : 0;
        if (w > 0.0000001) {
            return true;
        }
    }
    return false;
}

function jwqMaterialBodyHasVisibleDataRows() {
    var body = jwqGetJobworkMaterialBody();
    if (!body) {
        return false;
    }
    var trs = body.querySelectorAll('tr');
    var k;
    for (k = 0; k < trs.length; k++) {
        var tr = trs[k];
        if (tr.classList.contains('jwq-mat-empty')) {
            continue;
        }
        if (tr.querySelector('td[colspan]')) {
            continue;
        }
        if (tr.querySelectorAll('td').length >= 2) {
            return true;
        }
    }
    return false;
}

window.jwqCollectQueueLinePayload = jwqCollectQueueLinePayload;

/** Lock edited line weights before save so diamond sync cannot overwrite metal/total. */
function jwqLockTransferLineWeightsForSave() {
    var tbody = document.getElementById('jwqOrderLinesBody');
    if (!tbody) {
        return;
    }
    tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
        tr.setAttribute('data-jwq-total-manual', '1');
        var mw = jwqLineFieldNum(tr, 'metal_wt');
        var lw = jwqLineFieldNum(tr, 'loss');
        var dw = jwqLineFieldNum(tr, 'diamond_wt');
        if (!isFinite(mw) || mw < 0) {
            mw = 0;
        }
        if (!isFinite(lw) || lw < 0) {
            lw = 0;
        }
        if (!isFinite(dw) || dw < 0) {
            dw = 0;
        }
        var tInp = jwqLineFieldEl(tr, 'total_wt');
        var curT = jwqLineFieldNum(tr, 'total_wt');
        if (tInp && (!isFinite(curT) || curT < 0)) {
            tInp.value = jwqNum3(Math.max(0, mw - lw + dw));
        }
    });
}
window.jwqLockTransferLineWeightsForSave = jwqLockTransferLineWeightsForSave;

function jwqCollectMaterialDiamondStockForSave() {
    var out = [];
    var fallbackItemId = 0;
    var firstLineTr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
    if (firstLineTr) {
        fallbackItemId = parseInt(firstLineTr.getAttribute('data-item-id') || '0', 10) || 0;
    }
    if (fallbackItemId < 1) {
        var qLines = typeof jwqCollectQueueLinePayload === 'function' ? jwqCollectQueueLinePayload() : [];
        if (Array.isArray(qLines) && qLines.length > 0) {
            fallbackItemId = parseInt(qLines[0].item_id || '0', 10) || 0;
        }
    }
    var body = jwqGetJobworkMaterialBody();
    if (!body) {
        return out;
    }
    var seen = new Set();
    body.querySelectorAll('.jwq-material-diamond-row').forEach(function (row) {
        var stockId = parseInt(row.dataset.stockId || '0', 10) || 0;
        var barcode = String(row.dataset.barcode || '').trim();
        if (!stockId || !barcode) {
            return;
        }
        if (seen.has(stockId)) {
            return;
        }
        seen.add(stockId);
        var weight = jwqMaterialDiamondRowEffectiveWeight(row);
        var qty = parseFloat(row.dataset.qty || '0') || 0;
        if (qty <= 0) {
            var tds = row.querySelectorAll('td');
            qty = parseFloat(String(tds[4] ? tds[4].textContent : '').replace(/,/g, '')) || 0;
        }
        if (weight <= 0) {
            return;
        }
        var itemId = parseInt(row.getAttribute('data-jobwork-item-id') || '0', 10) || 0;
        if (itemId < 1) {
            itemId = fallbackItemId;
        }
        var addedByDept = parseInt(row.getAttribute('data-added-by-dept-id') || '0', 10) || 0;
        var addedByUser = parseInt(row.getAttribute('data-added-by-user-id') || '0', 10) || 0;
        if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
            addedByDept = 0;
            addedByUser = 0;
        }
        var payload = {
            stock_id: stockId,
            jobwork_order_item_id: itemId > 0 ? itemId : 0,
            barcode: barcode,
            product_name: String(row.dataset.productName || '').trim(),
            weight: weight,
            qty: qty > 0 ? qty : 0,
            added_by_dept_id: addedByDept,
            added_by_user_id: addedByUser,
            diamond_category: String(row.getAttribute('data-diamond-category') || 'Diamond').trim() || 'Diamond'
        };
        if (row.getAttribute('data-jwq-from-used-modal') === '1') {
            payload.from_used_diamond_modal = true;
        }
        if (window.console && typeof console.log === 'function') {
            console.log('DIAMOND SAVE ROW', payload);
        }
        out.push(payload);
    });
    if (out.length < 1 && jwqMaterialTableLooksLikeItHasDiamondRows()) {
        if (window.console && typeof console.error === 'function') {
            console.error('JWQ_COLLECT_DIAMOND: add diamonds via Existing stock so rows get class jwq-material-diamond-row and data-stock-id / data-barcode.');
        }
    }
    return out;
}

function jwqRefreshServerLoadedMaterialDiamonds() {
    var matBody = jwqGetJobworkMaterialBody();
    if (!matBody) {
        return;
    }
    matBody.querySelectorAll('.jwq-material-diamond-row[data-jwq-mat-server-loaded="1"]').forEach(function (tr) {
        tr.remove();
    });
    var jwoId = parseInt(String(window.__jwqCurrentJwoId != null ? window.__jwqCurrentJwoId : '0'), 10) || 0;
    if (jwoId < 1) {
        var hid = document.getElementById('jwqCurrentJwoId');
        jwoId = hid ? (parseInt(hid.value || '0', 10) || 0) : 0;
    }
    if (jwoId > 0 && typeof jwqLoadSavedDiamondRowsForModal === 'function') {
        jwqLoadSavedDiamondRowsForModal(jwoId);
    }
}

/**
 * Refresh diamond issue snapshot from DB for sync / save. Does not put issued stones on #jwqMaterialBody —
 * that list is only for diamonds the user adds via Existing / Add Diamond; full ledger stays on (i) Diamonds used.
 */
function jwqLoadSavedDiamondRowsForModal(jobworkOrderId) {
    var jwoId = parseInt(jobworkOrderId || '0', 10);
    if (jwoId < 1) return;
    var matBody = jwqGetJobworkMaterialBody();
    if (!matBody) return;
    fetch('ajax/mp-get-jobwork-queue-diamonds.php?jobwork_order_id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var rows = (d && d.ok && Array.isArray(d.items)) ? d.items : [];
            window.__jwqLastDiamondServerItems = rows.slice();
            matBody.querySelectorAll('.jwq-material-diamond-row[data-jwq-mat-server-loaded="1"]').forEach(function (tr) {
                tr.remove();
            });
            if (!matBody.querySelector('.jwq-material-diamond-row') && !matBody.querySelector('.jwq-material-metal-row')) {
                if (!matBody.querySelector('.jwq-mat-empty')) {
                    matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
                }
            } else {
                var emptyRow = matBody.querySelector('.jwq-mat-empty');
                if (emptyRow) {
                    emptyRow.remove();
                }
            }
            if (typeof window.jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                window.jwqSyncOrderLineDiamondWtFromMaterialTable();
            }
            if (typeof jwqRefreshMatDiamondTotalFooter === 'function') {
                jwqRefreshMatDiamondTotalFooter();
            }
        })
        .catch(function () {});
}

function jwqBuildLineRowHtml(it, orderRef, jwoId, card) {
    var keys = window.JWQ_ORDER_LINE_COL_KEYS || [];
    var trAttrs = '';
    var iid = 0;
    if (it) {
        iid = parseInt(it.id != null ? it.id : (it.item_id != null ? it.item_id : 0), 10) || 0;
    }
    var jwoNum = parseInt(jwoId != null ? jwoId : 0, 10) || 0;
    if (jwoNum > 0) {
        trAttrs += ' data-jwo-id="' + jwoNum + '"';
    }
    if (card && !jwqCardHasFloorTransfer(card)) {
        trAttrs += ' data-jwq-na-floor="1"';
    }
    if (iid > 0) {
        var prefillWt = jwqShouldPrefillLineWeights();
        var weightExtraRow = typeof window.jwqIsWeightExtraMetalUi === 'function' && window.jwqIsWeightExtraMetalUi();
        var snapOrigWt = prefillWt || weightExtraRow;
        var owAuto = jwqLineQueueCarryingTotalWt(it);
        if (!isFinite(owAuto) || owAuto <= 0.0000001) {
            owAuto = jwqLineAutoLossBaselineWt(it);
        }
        var omAuto = jwqLineOrigMetalWt(it);
        var odAuto = jwqLineOrigDiamondWtFromItem(it);
        var poolW = jwqOrderDiamondPoolWtFromItem(it);
        var slg = typeof jwqLineServerLossGrams === 'function' ? jwqLineServerLossGrams(it) : 0;
        trAttrs =
            trAttrs +
            ' data-item-id="' +
            iid +
            '"' +
            (snapOrigWt && owAuto > 0.0000001 ? ' data-orig-total-wt="' + String(owAuto) + '"' : '') +
            (snapOrigWt && omAuto > 0.0000001 ? ' data-jwq-orig-metal-wt="' + String(omAuto) + '"' : '') +
            (snapOrigWt && isFinite(odAuto) ? ' data-jwq-orig-diamond-wt="' + String(odAuto) + '"' : '') +
            ' data-jwq-saved-loss-grams="' +
            String(snapOrigWt && isFinite(slg) ? slg : 0) +
            '"' +
            ' data-jwq-order-diamond-wt="' +
            jwqNum3(Math.max(0, isFinite(poolW) ? poolW : 0)) +
            '"' +
            (prefillWt &&
            !weightExtraRow &&
            it &&
            Object.prototype.hasOwnProperty.call(it, 'queue_display_total_wt') &&
            (function () {
                var qn = parseFloat(String(it.queue_display_total_wt).replace(/,/g, ''));
                var fx = jwqItemFormulaTotalWt(it);
                return isFinite(qn) && isFinite(fx) && Math.abs(qn - fx) < 0.05;
            })()
                ? ' data-jwq-freeze-weights="1"'
                : '') +
            (it && typeof jwqItemTotalManuallyLockedFromServer === 'function' && jwqItemTotalManuallyLockedFromServer(it)
                ? ' data-jwq-total-manual="1"'
                : '') +
            (it && it.gross_weight != null && String(it.gross_weight).trim() !== ''
                ? ' data-jwq-orig-gross="' + jwqEsc(String(it.gross_weight)) + '"'
                : '') +
            (it && it.net_weight != null && String(it.net_weight).trim() !== ''
                ? ' data-jwq-orig-net="' + jwqEsc(String(it.net_weight)) + '"'
                : '') +
            (it && it.final_weight != null && String(it.final_weight).trim() !== ''
                ? ' data-jwq-orig-final="' + jwqEsc(String(it.final_weight)) + '"'
                : '');
    }
    var tds = keys.map(function (k) {
        return '<td data-col="' + k + '">' + jwqCellByKey(k, it, orderRef) + '</td>';
    });
    return '<tr' + trAttrs + '>' + tds.join('') + '</tr>';
}

function jwqApplyStoredLineColumnVisibility() {
    try {
        var raw = localStorage.getItem('manufacturing_process_jwq_order_lines_hidden_columns');
        var hidden = raw ? JSON.parse(raw) : [];
        var table = document.getElementById('jwqOrderLinesTable');
        if (!table) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hidden.indexOf(col) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
        if (typeof AuragoldColReorder !== 'undefined' && typeof AuragoldColReorder.refresh === 'function') {
            AuragoldColReorder.refresh(table);
        }
    } catch (e) {}
}

function jwqFillDeptSelect(sel, placeholder) {
    if (!sel) return;
    sel.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = placeholder || '-- Select --';
    sel.appendChild(o);
    (window.mpDepartments || []).forEach(function (d) {
        var op = document.createElement('option');
        op.value = String(d.id);
        op.textContent = d.dept_name || d.name || '';
        sel.appendChild(op);
    });
}

function jwqFillUserSelectForDept(sel, deptId) {
    if (!sel) return;
    sel.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = '-- Select --';
    sel.appendChild(o);
    var users = (window.mpDepartmentUsers && window.mpDepartmentUsers[deptId]) ? window.mpDepartmentUsers[deptId] : [];
    if (!Array.isArray(users)) users = [];
    users.forEach(function (u) {
        var op = document.createElement('option');
        op.value = String(u.id);
        op.textContent = u.name || '';
        sel.appendChild(op);
    });
}

function jwqSetNowDateTime() {
    var now = new Date();
    var dEl = document.getElementById('jwqDate');
    var tEl = document.getElementById('jwqTime');
    if (dEl) {
        var y = now.getFullYear();
        function pad2(n) { return (n < 10 ? '0' : '') + n; }
        dEl.value = y + '-' + pad2(now.getMonth() + 1) + '-' + pad2(now.getDate());
    }
    if (tEl) {
        var hh = now.getHours();
        var mm = now.getMinutes();
        var ss = now.getSeconds();
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        tEl.value = pad(hh) + ':' + pad(mm) + ':' + pad(ss);
    }
}

function jwqResolveModalActionLabel(btn, opts) {
    opts = opts || {};
    if (opts.skipActionLabel) {
        return '';
    }
    if (opts.actionLabel) {
        return String(opts.actionLabel).trim();
    }
    if (opts.forWeight) {
        return (opts.weightMode === 'add') ? 'Add Weight' : 'Reduce Weight';
    }
    if (btn && btn.classList && btn.classList.contains('mp-jwq-open-btn')) {
        return (btn.getAttribute('title') || btn.getAttribute('aria-label') || '').trim();
    }
    return '';
}

function jwqSetModalTitle(queueDisplayText) {
    var titleWrap = document.getElementById('jwqModalTitle');
    if (!titleWrap) {
        return;
    }
    var q = (queueDisplayText != null && String(queueDisplayText).trim() !== '') ? String(queueDisplayText).trim() : '—';
    var action = (window.__jwqModalActionLabel || '').trim();
    titleWrap.innerHTML = 'Jobwork Queue No. : <strong id="jwqModalQueueNo">' + jwqAttrEsc(q) + '</strong>';
    if (action) {
        var span = document.createElement('span');
        span.className = 'jwq-modal-action-label';
        span.textContent = ' (' + action + ')';
        titleWrap.appendChild(span);
    }
}

function jwqClearBulkModalState() {
    window.__jwqBulkMode = false;
    window.__jwqBulkJwoIds = null;
    window.__jwqModalActionLabel = '';
    window.__jwqAddWeightExtraMetalUi = false;
    window.__jwqReduceWeightExtraMetalUi = false;
    window.__jwqPrefillLineWeights = false;
    if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
        jwqRefreshOrderLinesTotalSummary();
    }
}

/** Job card shows NA weight until a manufacturing floor transfer is logged (data-floor-transfer="1"). */
function jwqCardHasFloorTransfer(card) {
    if (!card) {
        return false;
    }
    return String(card.getAttribute('data-floor-transfer') || '0').trim() === '1';
}

/** NA cards: queue modal line weights start at 0 (matches card NA display). */
function jwqPrepareOrderItemForCard(it, card) {
    it = it || {};
    if (jwqCardHasFloorTransfer(card)) {
        return it;
    }
    var z = Object.assign({}, it);
    z.final_weight = 0;
    z.net_weight = 0;
    z.gross_weight = 0;
    z.diamond_weight = 0;
    z.diamond_wt = 0;
    z.less_weight = 0;
    z.wastage_wt = 0;
    z.gold_loss_1 = 0;
    z.loss_wt = 0;
    z.queue_display_total_wt = 0;
    z.queue_display_loss_wt = 0;
    z.purity = 0;
    return z;
}

function jwqFilterOrderItemsForCard(items, card) {
    items = items || [];
    if (!card || !items.length) {
        return items;
    }
    var selectedBarcode = (card.getAttribute('data-tag-no') || '').trim().toLowerCase();
    var selectedProduct = (card.getAttribute('data-line-desc') || '').trim().toLowerCase();
    if (selectedBarcode === '' && selectedProduct === '') {
        return items;
    }
    return items.filter(function (it) {
        var rowBarcode = String(it && it.barcode != null ? it.barcode : '').trim().toLowerCase();
        var rowProduct = String(it && it.product_name != null ? it.product_name : '').trim().toLowerCase();
        if (selectedBarcode !== '' && rowBarcode !== '' && rowBarcode === selectedBarcode) return true;
        if (selectedProduct !== '' && rowProduct !== '' && rowProduct === selectedProduct) return true;
        return false;
    });
}

function mpCreateJwqTriggerFromJwoId(jwoId) {
    var card = mpGetFirstCardForJwo(jwoId);
    if (!card) return null;
    return card.querySelector('.mp-jwq-open-btn') || card;
}

function jwqOpenModalForMultipleOrders(jwoIds) {
    jwoIds = (jwoIds || []).map(function (id) { return parseInt(id, 10) || 0; }).filter(function (id) { return id > 0; });
    if (!jwoIds.length) {
        alert('Select one or more job work orders first.');
        return;
    }
    if (typeof setMpViewMode === 'function') {
        setMpViewMode('cards');
    }
    if (jwoIds.length === 1) {
        jwqClearBulkModalState();
        var singleBtn = mpCreateJwqTriggerFromJwoId(jwoIds[0]);
        if (singleBtn) {
            jwqOpenModal(singleBtn, { skipActionLabel: true });
        }
        return;
    }

    var deptSet = {};
    jwoIds.forEach(function (id) {
        var c = mpGetFirstCardForJwo(id);
        var d = c ? parseInt(c.getAttribute('data-dept-id') || '0', 10) : 0;
        deptSet[d] = true;
    });
    if (Object.keys(deptSet).length > 1) {
        if (!confirm('Selected orders are from different departments. From Dept will start from the first order — you can adjust before Save. Continue?')) {
            return;
        }
    }

    var firstBtn = mpCreateJwqTriggerFromJwoId(jwoIds[0]);
    if (!firstBtn) {
        alert('Could not open Jobwork Queue for selected orders.');
        return;
    }

    window.__jwqBulkMode = true;
    window.__jwqBulkJwoIds = jwoIds.slice();
    window.__jwqModalActionLabel = '';

    var overlay = document.getElementById('jwqModalOverlay');
    if (!overlay) return;

    var jwoId = jwoIds[0];
    window.__jwqCurrentJwoId = jwoId;
    window.__jwqPrefillLineWeights = true;
    window.jwqRemovedDiamondIssues = [];

    var titleWrap = document.getElementById('jwqModalTitle');
    var queueEl = document.getElementById('jwqModalQueueNo');
    var queueHid = document.getElementById('jwqJobworkQueueNo');
    if (titleWrap) {
        titleWrap.innerHTML = 'Jobwork Queue — <strong id="jwqModalQueueNo">' + jwoIds.length + ' orders</strong>';
        queueEl = document.getElementById('jwqModalQueueNo');
    }
    if (queueEl) queueEl.textContent = jwoIds.length + ' orders';
    if (queueHid) queueHid.value = '';

    var hid = document.getElementById('jwqCurrentJwoId');
    if (hid) hid.value = String(jwoId);

    var currentDept = parseInt(firstBtn.getAttribute('data-dept-id') || '0', 10);
    var currentUser = parseInt(firstBtn.getAttribute('data-user-id') || '0', 10);
    var fromDept = document.getElementById('jwqFromDept');
    var fromUser = document.getElementById('jwqFromUser');
    var toDeptSel = document.getElementById('jwqToDept');
    var toUserSel = document.getElementById('jwqToUser');
    jwqFillDeptSelect(fromDept);
    jwqFillDeptSelect(toDeptSel);
    if (fromDept) fromDept.value = currentDept > 0 ? String(currentDept) : '';
    jwqFillUserSelectForDept(fromUser, currentDept);
    if (fromUser) fromUser.value = currentUser > 0 ? String(currentUser) : '';
    if (toDeptSel) toDeptSel.value = '';
    jwqFillUserSelectForDept(toUserSel, 0);
    if (toUserSel) toUserSel.value = '';

    jwqSetNowDateTime();
    var timerDisp = document.getElementById('jwqTotalTimeDisplay');
    if (timerDisp) timerDisp.textContent = '00:00:00';

    var tbody = document.getElementById('jwqOrderLinesBody');
    var colCount = (window.JWQ_ORDER_LINE_COL_KEYS && window.JWQ_ORDER_LINE_COL_KEYS.length) ? window.JWQ_ORDER_LINE_COL_KEYS.length : 21;
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center;color:#94a3b8;padding:16px;">Loading ' + jwoIds.length + ' orders…</td></tr>';
    }

    var matBody = jwqGetJobworkMaterialBody();
    if (matBody) matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
    var matTot = document.getElementById('jwqMatTotalWt');
    if (matTot) matTot.textContent = '0.00';

    if (typeof window.jwqToggleWeightStrip === 'function') {
        window.jwqToggleWeightStrip(false);
    }

    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    Promise.all(jwoIds.map(function (id) {
        return fetch('ajax/mp-jobwork-order-items.php?id=' + encodeURIComponent(String(id)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var card = mpGetFirstCardForJwo(id);
                var orderRef = card ? (card.getAttribute('data-jobwork-no') || ('JWO-' + id)) : ('JWO-' + id);
                var rows = (data && data.ok && data.items) ? data.items : [];
                rows = jwqFilterOrderItemsForCard(rows, card);
                return { jwoId: id, orderRef: orderRef, rows: rows, card: card };
            })
            .catch(function () {
                return { jwoId: id, orderRef: 'JWO-' + id, rows: [], card: null };
            });
    })).then(function (results) {
        if (!tbody) return;
        tbody.innerHTML = '';
        var totalLines = 0;
        results.forEach(function (res) {
            if (res.rows.length) {
                res.rows.forEach(function (it) {
                    tbody.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(jwqPrepareOrderItemForCard(it, res.card), res.orderRef, res.jwoId, res.card));
                    totalLines++;
                });
            } else {
                var firstProduct = res.card ? (res.card.getAttribute('data-line-desc') || '—') : '—';
                var ph = {
                    design_no: '—',
                    barcode: res.card ? (res.card.getAttribute('data-tag-no') || '—') : '—',
                    product_name: firstProduct,
                    carat: '—',
                    final_weight: 0,
                    net_weight: 0,
                    purity: 0,
                    quantity: 0,
                    rate: null,
                    less_weight: 0,
                    diamond_weight: 0,
                    gross_weight: 0
                };
                tbody.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(jwqPrepareOrderItemForCard(ph, res.card), res.orderRef, res.jwoId, res.card));
                totalLines++;
            }
        });
        if (!totalLines) {
            tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center;color:#dc2626;padding:12px;">No lines loaded for selected orders</td></tr>';
        } else {
            jwqApplyStoredLineColumnVisibility();
            if (typeof jwqReconcileLineLossFromWeights === 'function') {
                jwqReconcileLineLossFromWeights();
            }
            jwqRefreshAutoLossAllRows();
            if (typeof jwqApplyTransferLineCarryingTotalAll === 'function') {
                jwqApplyTransferLineCarryingTotalAll();
            }
            if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
                jwqRefreshOrderLinesTotalSummary();
            }
        }
    });
}

function jwqSaveBulkJobworkQueue() {
    var jwoIds = window.__jwqBulkJwoIds ? window.__jwqBulkJwoIds.slice() : [];
    if (!jwoIds.length) {
        alert('No job work orders selected.');
        return;
    }
    var toD = document.getElementById('jwqToDept');
    var toU = document.getElementById('jwqToUser');
    var toDeptId = toD ? parseInt(toD.value || '0', 10) : 0;
    if (toDeptId < 1) {
        alert('Please select destination department (To Dept.). All selected orders will move to that department after save.');
        return;
    }
    var toUserId = toU && toU.value ? parseInt(toU.value, 10) : 0;
    var fromD = document.getElementById('jwqFromDept');
    var fromU = document.getElementById('jwqFromUser');
    var fromDeptId = fromD ? parseInt(fromD.value || '0', 10) : 0;
    var fromUserId = fromU && fromU.value ? parseInt(fromU.value, 10) : 0;

    var btnSave = document.getElementById('jwqBtnSave');
    if (btnSave) {
        btnSave.disabled = true;
    }

    var ok = 0;
    var failed = [];

    function saveOne(jwoId) {
        if (typeof jwqLockTransferLineWeightsForSave === 'function') {
            jwqLockTransferLineWeightsForSave();
        }
        var lines = typeof jwqCollectQueueLinePayload === 'function' ? jwqCollectQueueLinePayload(jwoId) : [];
        if (!lines.length) {
            return Promise.reject(new Error('No line weights found for JWO #' + jwoId + '. Reload the queue and try again.'));
        }
        var fd = new FormData();
        fd.append('jobwork_order_id', String(jwoId));
        fd.append('to_dept_id', String(toDeptId));
        if (toUserId > 0) fd.append('to_user_id', String(toUserId));
        if (fromDeptId > 0) fd.append('from_dept_id', String(fromDeptId));
        if (fromUserId > 0) fd.append('from_user_id', String(fromUserId));
        fd.append('queue_lines', JSON.stringify(lines));
        fd.append('jwq_diamond_stock_lines', '[]');
        return fetch('ajax/mp-save-jobwork-queue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (txt) {
                    var data = null;
                    try { data = JSON.parse(txt || '{}'); } catch (e) { data = null; }
                    if (!data || !data.ok) {
                        throw new Error((data && data.message) || 'Save failed');
                    }
                    if (typeof mpUpdateJobCardAfterTransfer === 'function') {
                        mpUpdateJobCardAfterTransfer(jwoId, data);
                    }
                    return data;
                });
            });
    }

    function next(i) {
        if (i >= jwoIds.length) {
            if (btnSave) btnSave.disabled = false;
            jwqClearBulkModalState();
            jwoIds.forEach(function (id) {
                if (typeof mpSyncBulkSelectCheckboxesForJwo === 'function') {
                    mpSyncBulkSelectCheckboxesForJwo(id, false);
                }
            });
            if (typeof mpUpdateBulkSelectAllState === 'function') {
                mpUpdateBulkSelectAllState();
            }
            if (typeof mpReloadManufacturingQueueTable === 'function') {
                mpReloadManufacturingQueueTable();
            }
            jwqCloseModal();
            if (failed.length) {
                alert('Saved ' + ok + ' of ' + jwoIds.length + ' order(s) to Jobwork Queue.\n\nFailed:\n' + failed.join('\n'));
            } else {
                alert('Successfully saved ' + ok + ' order' + (ok === 1 ? '' : 's') + ' to Jobwork Queue.');
            }
            return;
        }
        if (btnSave) {
            btnSave.innerHTML = '<i class="feather icon-check"></i> Saving ' + (i + 1) + '/' + jwoIds.length + '…';
        }
        saveOne(jwoIds[i]).then(function () {
            ok++;
            next(i + 1);
        }).catch(function (err) {
            var card = mpGetFirstCardForJwo(jwoIds[i]);
            var label = card ? (card.getAttribute('data-jobwork-no') || ('JWO #' + jwoIds[i])) : ('JWO #' + jwoIds[i]);
            failed.push(label + ': ' + (err && err.message ? err.message : 'Error'));
            next(i + 1);
        });
    }
    next(0);
}

function jwqOpenModal(btn, opts) {
    opts = opts || {};
    jwqClearBulkModalState();
    window.__jwqModalActionLabel = jwqResolveModalActionLabel(btn, opts);
    var overlay = document.getElementById('jwqModalOverlay');
    if (!overlay) return;
    var jwoId = parseInt(btn.getAttribute('data-jwo-id') || '0', 10);
    window.__jwqCurrentJwoId = jwoId > 0 ? jwoId : 0;
    var cardEl = btn.closest ? btn.closest('.mp-job-card') : null;
    var floorXfer = (btn.getAttribute('data-floor-transfer') || '').trim();
    if (floorXfer !== '1' && cardEl) {
        floorXfer = (cardEl.getAttribute('data-floor-transfer') || '').trim();
    }
    window.__jwqAddWeightExtraMetalUi = !!(opts.forWeight && opts.weightMode === 'add');
    window.__jwqReduceWeightExtraMetalUi = !!(opts.forWeight && opts.weightMode === 'reduce');
    window.__jwqPrefillLineWeights = !window.__jwqAddWeightExtraMetalUi && !window.__jwqReduceWeightExtraMetalUi;
    var queueFromSeries = (btn.getAttribute('data-jobwork-queue-no') || '').trim();
    var queueHid = document.getElementById('jwqJobworkQueueNo');
    jwqSetModalTitle(queueFromSeries || '—');
    if (queueHid) queueHid.value = queueFromSeries;
    if (jwoId > 0 && !queueFromSeries) {
        fetch('ajax/mp-get-jobwork-queue-no.php?jobwork_order_id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;
                var q = (data.jobwork_queue_no || '').trim();
                if (!q) return;
                jwqSetModalTitle(q);
                if (queueHid) queueHid.value = q;
                if (btn && btn.setAttribute) btn.setAttribute('data-jobwork-queue-no', q);
                document.querySelectorAll('.mp-jwq-open-btn[data-jwo-id="' + jwoId + '"]').forEach(function (b) {
                    b.setAttribute('data-jobwork-queue-no', q);
                });
            })
            .catch(function () {});
    }
    var hid = document.getElementById('jwqCurrentJwoId');
    if (hid) hid.value = jwoId > 0 ? String(jwoId) : '';

    var prevOpenJwoRm = parseInt(String(window.__jwqOpenModalLastJwoIdForRemoved || '0'), 10) || 0;
    jwqEnsureRemovedDiamondIssuesArray();
    if (jwoId > 0 && prevOpenJwoRm !== jwoId) {
        window.jwqRemovedDiamondIssues = [];
    }
    window.__jwqOpenModalLastJwoIdForRemoved = jwoId > 0 ? jwoId : prevOpenJwoRm;

    /* Card department/user = where the job is now → From Dept / From User */
    var currentDept = parseInt(btn.getAttribute('data-dept-id') || '0', 10);
    var currentUser = parseInt(btn.getAttribute('data-user-id') || '0', 10);

    var fromDept = document.getElementById('jwqFromDept');
    var fromUser = document.getElementById('jwqFromUser');
    var toDeptSel = document.getElementById('jwqToDept');
    var toUserSel = document.getElementById('jwqToUser');

    jwqFillDeptSelect(fromDept);
    jwqFillDeptSelect(toDeptSel);
    if (opts.forWeight && opts.weightMode === 'add') {
        /* Add Weight: weight comes INTO the current department → To Dept = existing dept, From Dept = user chooses source */
        if (fromDept) fromDept.value = '';
        jwqFillUserSelectForDept(fromUser, 0);
        if (fromUser) fromUser.value = '';
        if (toDeptSel) toDeptSel.value = currentDept > 0 ? String(currentDept) : '';
        jwqFillUserSelectForDept(toUserSel, currentDept);
        if (toUserSel) toUserSel.value = currentUser > 0 ? String(currentUser) : '';
    } else if (opts.forWeight && opts.weightMode === 'reduce') {
        /* Reduce Weight: weight leaves current department → From Dept = existing dept, To Dept = user chooses destination */
        if (fromDept) fromDept.value = currentDept > 0 ? String(currentDept) : '';
        jwqFillUserSelectForDept(fromUser, currentDept);
        if (fromUser) fromUser.value = currentUser > 0 ? String(currentUser) : '';
        if (toDeptSel) toDeptSel.value = '';
        jwqFillUserSelectForDept(toUserSel, 0);
        if (toUserSel) toUserSel.value = '';
    } else {
        if (fromDept) fromDept.value = currentDept > 0 ? String(currentDept) : '';
        jwqFillUserSelectForDept(fromUser, currentDept);
        if (fromUser) fromUser.value = currentUser > 0 ? String(currentUser) : '';

        /* To Dept / To User: destination — user chooses */
        if (toDeptSel) toDeptSel.value = '';
        jwqFillUserSelectForDept(toUserSel, 0);
        if (toUserSel) toUserSel.value = '';
    }

    jwqSetNowDateTime();
    var timerDisp = document.getElementById('jwqTotalTimeDisplay');
    if (timerDisp) {
        var card = btn.closest('.mp-job-card');
        var tshow = '00:00:00';
        if (card) {
            var td = card.querySelector('.mp-timer-display');
            if (td && td.textContent) tshow = td.textContent.trim();
        } else {
            var secAttr = btn.getAttribute('data-manufacturing-seconds');
            if (secAttr !== null && secAttr !== '') {
                var ts = parseInt(secAttr, 10);
                if (!isNaN(ts) && ts >= 0) {
                    var hh = Math.floor(ts / 3600);
                    var mm = Math.floor((ts % 3600) / 60);
                    var ss = ts % 60;
                    function z(n) { return (n < 10 ? '0' : '') + n; }
                    tshow = z(hh) + ':' + z(mm) + ':' + z(ss);
                }
            }
        }
        timerDisp.textContent = tshow;
    }

    var jobNo = btn.getAttribute('data-jobwork-no') || '';
    var saleNo = btn.getAttribute('data-sale-order-no') || '';
    var orderRef = (jobNo || saleNo || '').trim() || '—';
    var firstProduct = btn.getAttribute('data-first-product') || '';
    var selectedBarcode = (btn.getAttribute('data-item-barcode') || '').trim().toLowerCase();
    var selectedProduct = firstProduct.trim().toLowerCase();

    var tbody = document.getElementById('jwqOrderLinesBody');
    var colCount = (window.JWQ_ORDER_LINE_COL_KEYS && window.JWQ_ORDER_LINE_COL_KEYS.length) ? window.JWQ_ORDER_LINE_COL_KEYS.length : 21;
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center;color:#94a3b8;padding:16px;">Loading…</td></tr>';
    }

    fetch('ajax/mp-jobwork-order-items.php?id=' + encodeURIComponent(String(jwoId)), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!tbody) return;
            tbody.innerHTML = '';
            var rows = (data && data.ok && data.items) ? data.items : [];
            if (rows.length && (selectedBarcode !== '' || selectedProduct !== '')) {
                rows = rows.filter(function (it) {
                    var rowBarcode = String(it && it.barcode != null ? it.barcode : '').trim().toLowerCase();
                    var rowProduct = String(it && it.product_name != null ? it.product_name : '').trim().toLowerCase();
                    if (selectedBarcode !== '' && rowBarcode !== '' && rowBarcode === selectedBarcode) return true;
                    if (selectedProduct !== '' && rowProduct !== '' && rowProduct === selectedProduct) return true;
                    return false;
                });
            }
            if (rows.length) {
                rows.forEach(function (it) {
                    tbody.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(jwqPrepareOrderItemForCard(it, cardEl), orderRef, jwoId, cardEl));
                });
            } else {
                var ph = {
                    design_no: '—',
                    barcode: '—',
                    product_name: firstProduct || '—',
                    carat: '—',
                    final_weight: 0,
                    net_weight: 0,
                    purity: 0,
                    quantity: 0,
                    rate: null,
                    less_weight: 0,
                    diamond_weight: 0,
                    gross_weight: 0
                };
                tbody.insertAdjacentHTML('beforeend', jwqBuildLineRowHtml(jwqPrepareOrderItemForCard(ph, cardEl), orderRef, jwoId, cardEl));
            }
            if (typeof jwqApplyTransferLineCarryingTotalAll === 'function') {
                jwqApplyTransferLineCarryingTotalAll();
            }
            jwqApplyStoredLineColumnVisibility();
            if (typeof jwqReconcileLineLossFromWeights === 'function') {
                jwqReconcileLineLossFromWeights();
            }
            if (!opts.forWeight) {
                jwqRefreshAutoLossAllRows();
            }
            if (opts.forWeight && tbody) {
                tbody.querySelectorAll('tr[data-item-id]').forEach(function (tr) {
                    tr.removeAttribute('data-jwq-orig-total-before-add');
                    tr.removeAttribute('data-jwq-orig-metal-before-add');
                    tr.removeAttribute('data-jwq-orig-total-before-reduce');
                    tr.removeAttribute('data-jwq-orig-metal-before-reduce');
                    tr.removeAttribute('data-jwq-add-orig-snapshotted');
                    tr.removeAttribute('data-jwq-reduce-orig-snapshotted');
                    tr.removeAttribute('data-jwq-manual-add-grams');
                    tr.removeAttribute('data-jwq-manual-reduce-grams');
                });
            }
            if (typeof window.jwqSnapshotAddWeightOrigTotals === 'function') {
                window.jwqSnapshotAddWeightOrigTotals(true);
            } else if (typeof window.jwqInitAddWeightOrigTotals === 'function') {
                window.jwqInitAddWeightOrigTotals();
            }
            if (typeof window.jwqSnapshotReduceWeightOrigTotals === 'function') {
                window.jwqSnapshotReduceWeightOrigTotals(true);
            } else if (typeof window.jwqInitReduceWeightOrigTotals === 'function') {
                window.jwqInitReduceWeightOrigTotals();
            }
            if (typeof window.jwqInitAddWeightExtraMetalUi === 'function') {
                window.jwqInitAddWeightExtraMetalUi();
            }
            if (typeof window.jwqInitReduceWeightExtraMetalUi === 'function') {
                window.jwqInitReduceWeightExtraMetalUi();
            }
            if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                window.jwqSyncOrderLineDiamondWtFromMaterialTable();
            }
            if (typeof jwqRefreshOrderLinesTotalSummary === 'function') {
                jwqRefreshOrderLinesTotalSummary();
            }
            /* Re-apply saved diamond issues after line rows exist (transfer target dept view). */
            jwqLoadSavedDiamondRowsForModal(jwoId);
        })
        .catch(function () {
            if (tbody) {
                tbody.innerHTML = '';
                tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center;color:#dc2626;padding:12px;">Could not load lines</td></tr>';
            }
        });

    var matBody = jwqGetJobworkMaterialBody();
    if (matBody) {
        matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
    }
    var matTot = document.getElementById('jwqMatTotalWt');
    if (matTot) matTot.textContent = '0.00';

    if (typeof window.jwqToggleWeightStrip === 'function') {
        if (opts.forWeight) {
            window.jwqToggleWeightStrip(true, opts.weightMode || 'reduce');
        } else {
            window.jwqToggleWeightStrip(false);
        }
    }

    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function jwqCloseModal() {
    var overlay = document.getElementById('jwqModalOverlay');
    if (!overlay) return;
    jwqClearBulkModalState();
    var btnSave = document.getElementById('jwqBtnSave');
    if (btnSave) {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="feather icon-check"></i> Save';
    }
    window.__jwqModalActionLabel = '';
    jwqSetModalTitle('JWQ-');
    if (typeof window.jwqToggleWeightStrip === 'function') {
        window.jwqToggleWeightStrip(false);
    }
    if (typeof window.jwqToggleJwqTransferFields === 'function') {
        window.jwqToggleJwqTransferFields(true);
    }
    var jwqPanel = document.getElementById('jwqColumnsPanel');
    if (jwqPanel) jwqPanel.classList.remove('show');
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function jwqInwardModalDateTimeStr() {
    var now = new Date();
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    return pad(now.getDate()) + '-' + pad(now.getMonth() + 1) + '-' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
}

function jwqApplyStoredInwardModalColumnVisibility() {
    try {
        var raw = localStorage.getItem('manufacturing_process_jwq_inward_modal_hidden_columns');
        var hidden = raw ? JSON.parse(raw) : [];
        var table = document.getElementById('jwqInwardStockTable');
        if (!table) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var col = el.getAttribute('data-col');
            if (hidden.indexOf(col) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
    } catch (e) {}
}

function jwqInwardStockGetVisibleColumnThs() {
    var table = document.getElementById('jwqInwardStockTable');
    if (!table) return [];
    var tr = table.querySelector('thead tr');
    if (!tr) return [];
    return Array.prototype.slice.call(tr.querySelectorAll('th[data-col]')).filter(function (th) {
        return !th.classList.contains('col-hidden');
    });
}

function jwqInwardStockExportExcel() {
    var ths = jwqInwardStockGetVisibleColumnThs();
    if (!ths.length) return;
    var lines = [];
    lines.push(ths.map(function (th) {
        return '"' + String(th.textContent || '').replace(/"/g, '""') + '"';
    }).join(','));
    var table = document.getElementById('jwqInwardStockTable');
    if (!table) return;
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        var row = ths.map(function (th) {
            var col = th.getAttribute('data-col');
            var td = tr.querySelector('td[data-col="' + col + '"]');
            var txt = td ? String(td.textContent || '') : '';
            return '"' + txt.replace(/"/g, '""') + '"';
        });
        lines.push(row.join(','));
    });
    var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'inward-stock.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

function jwqInwardStockExportPdf() {
    var ths = jwqInwardStockGetVisibleColumnThs();
    if (!ths.length) return;
    var table = document.getElementById('jwqInwardStockTable');
    if (!table) return;
    var theadHtml = '<tr>' + ths.map(function (th) {
        return '<th>' + jwqEsc(th.textContent || '') + '</th>';
    }).join('') + '</tr>';
    var bodyHtml = '';
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        bodyHtml += '<tr>';
        ths.forEach(function (th) {
            var col = th.getAttribute('data-col');
            var td = tr.querySelector('td[data-col="' + col + '"]');
            bodyHtml += '<td>' + (td ? jwqEsc(td.textContent || '') : '') + '</td>';
        });
        bodyHtml += '</tr>';
    });
    var title = (document.getElementById('jwqInwardStockModalTitle') || {}).textContent || 'Inward Stock';
    var w = window.open('', '_blank');
    if (!w) return;
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + jwqEsc(title) + '</title>');
    w.document.write('<style>body{font-family:Segoe UI,Arial,sans-serif;padding:16px;} table{border-collapse:collapse;width:100%;font-size:12px;} th,td{border:1px solid #ccc;padding:6px;} th{background:#eef2f8;}</style>');
    w.document.write('</head><body><h2 style="font-size:16px;margin:0 0 12px;">' + jwqEsc(title) + '</h2>');
    w.document.write('<table><thead>' + theadHtml + '</thead><tbody>' + bodyHtml + '</tbody></table></body></html>');
    w.document.close();
    w.focus();
    w.print();
    w.close();
}

function jwqFillInwardStockDetailsModal(which) {
    which = which || 'from';
    var keys = window.JWQ_INWARD_STOCK_MODAL_KEYS || [];
    var ctx = document.getElementById('jwqInwardStockModalContext');
    if (ctx) ctx.textContent = which === 'to' ? '(To)' : '(From)';

    var jwoId = (document.getElementById('jwqCurrentJwoId') || {}).value || '';
    var qHidden = document.getElementById('jwqJobworkQueueNo');
    var qTitle = document.getElementById('jwqModalQueueNo');
    var queueNo = (qHidden && qHidden.value) ? String(qHidden.value).trim() : '';
    if (!queueNo && qTitle && qTitle.textContent) {
        var qt = String(qTitle.textContent).trim();
        if (qt && qt !== '—') queueNo = qt;
    }
    if (!queueNo) {
        queueNo = '—';
    }

    var jwqMap = {};
    var tr = null;
    document.querySelectorAll('#jwqOrderLinesBody tr').forEach(function (r) {
        if (!tr && r.querySelector('td[data-col]')) tr = r;
    });
    if (tr) {
        tr.querySelectorAll('td[data-col]').forEach(function (td) {
            var k = td.getAttribute('data-col');
            if (!k) return;
            var inp = td.querySelector('[data-field="' + k + '"], [data-col-input="' + k + '"]');
            if (inp && inp.value != null) jwqMap[k] = String(inp.value).trim();
            else jwqMap[k] = (td.textContent || '').trim();
        });
    }

    var descCombined = ((jwqMap.description || '') + ' ' + (jwqMap.product || '')).trim();
    var metal = '—';
    if (/gold/i.test(descCombined)) metal = 'Gold';
    else if (/silver/i.test(descCombined)) metal = 'Silver';
    else if (/platinum/i.test(descCombined)) metal = 'Platinum';

    var cellByKey = {};
    keys.forEach(function (k) { cellByKey[k] = '—'; });

    cellByKey.queue_no = queueNo;
    cellByKey.comment = '';
    cellByKey.product_name = jwqMap.product || jwqMap.description || '—';
    cellByKey.active = '';
    cellByKey.image_urls = '';
    cellByKey.against_queue = '';
    cellByKey.against_invoice = jwqMap.order_no || '—';
    cellByKey.metal = metal;
    cellByKey.description = jwqMap.description || '—';
    cellByKey.dust_wastage_wt = jwqMap.dust_wastage_wt && jwqMap.dust_wastage_wt !== '—' ? jwqMap.dust_wastage_wt : '0';
    cellByKey.loss_wt = jwqMap.loss && jwqMap.loss !== '—' ? jwqMap.loss : '0';
    cellByKey.total_wt = jwqMap.total_wt || '—';
    cellByKey.metal_wt = jwqMap.metal_wt || '—';
    cellByKey.diamond_wt = jwqMap.diamond_wt || '—';
    cellByKey.purity_wt = jwqMap.total_purity || '—';
    cellByKey.carat_name = jwqMap.karat || '—';
    cellByKey.profit_wt = jwqMap.profit && jwqMap.profit !== '—' ? jwqMap.profit : '0';
    cellByKey.tag_no = jwqMap.tag_no || '—';
    cellByKey.total_quantity = jwqMap.total_qty || '—';
    cellByKey.date_time = jwqInwardModalDateTimeStr();

    var html = '<tr>';
    keys.forEach(function (k) {
        var v = cellByKey[k] != null ? String(cellByKey[k]) : '—';
        html += '<td data-col="' + k + '">' + jwqEsc(v) + '</td>';
    });
    html += '</tr>';

    var tbody = document.getElementById('jwqInwardStockBody');
    if (tbody) tbody.innerHTML = html;

    jwqApplyStoredInwardModalColumnVisibility();

    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
        window.jQuery('#jwqInwardStockModal').modal('show');
    }
}

/** After Jobwork Queue save: update card dept/user/weights from API response (any save, not only transfers). */
function mpUpdateJobCardAfterTransfer(jwoId, data) {
    if (!jwoId || !data) return;
    var deptId = parseInt(data.department_id, 10) || 0;
    var userId = parseInt(data.department_user_id, 10) || 0;
    var cards = document.querySelectorAll('.mp-job-card[data-jwo-id="' + jwoId + '"]');
    if (!cards.length) return;
    cards.forEach(function (card) {
        card.setAttribute('data-dept-id', deptId > 0 ? String(deptId) : '0');
        card.setAttribute('data-user-id', userId > 0 ? String(userId) : '0');
        var bn = card.querySelector('.mp-dept-banner-name');
        if (bn) {
            var dn = (data.dept_name || '').trim();
            bn.textContent = dn !== '' ? dn.toUpperCase() : 'NO DEPT';
        }
        if (data.jwo_has_floor_transfer !== undefined) {
            card.setAttribute('data-floor-transfer', data.jwo_has_floor_transfer ? '1' : '0');
        }
        if (data.jwo_total_wt !== undefined) {
            var wn = card.querySelector('.mp-total-wt-num');
            if (wn) {
                var wtTxt = data.jwo_total_wt != null ? String(data.jwo_total_wt).trim() : '';
                wn.textContent = wtTxt !== '' ? wtTxt : 'NA';
            }
        }
        if (data.jwo_card_secondary !== undefined) {
            var ws = card.querySelector('.mp-wt-secondary');
            if (ws) {
                var secTxt = data.jwo_card_secondary != null ? String(data.jwo_card_secondary).trim() : '';
                ws.textContent = secTxt !== '' ? secTxt : 'NA';
            }
        }
        var meta = card.querySelector('.mp-name-meta');
        if (meta && data.worker_name !== undefined) {
            var sp = meta.querySelector('span');
            if (sp) sp.textContent = (data.worker_name || '').trim() !== '' ? String(data.worker_name).trim() : '—';
        }
        card.querySelectorAll('.mp-jwq-open-btn').forEach(function (b) {
            if (deptId > 0) b.setAttribute('data-dept-id', String(deptId));
            else b.setAttribute('data-dept-id', '0');
            if (userId > 0) b.setAttribute('data-user-id', String(userId));
            else b.setAttribute('data-user-id', '0');
            if (data.dept_name !== undefined) b.setAttribute('data-dept-name', String(data.dept_name || ''));
            if (data.worker_name !== undefined) b.setAttribute('data-worker-name', String(data.worker_name || ''));
        });
    });
    if (typeof filterByDepartmentAndUser === 'function') {
        filterByDepartmentAndUser();
    }
}

/** After Jobwork Queue Save: optional print — jobwork slip only */
function mpJwqPromptPrintAfterSave(jwoId) {
    var idStr = String(jwoId || '');
    var slipUrl = 'manufacturing-jobwork-slip-print.php?id=' + encodeURIComponent(idStr) + '&autoprint=1';

    function openPrints() {
        window.open(slipUrl, '_blank', 'noopener,noreferrer');
    }

    function finish() {
        jwqCloseModal();
    }

    if (typeof swal === 'function') {
        swal({
            title: 'Print bill',
            text: 'Do you want to print invoice?',
            type: 'info',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonClass: 'confirm',
            cancelButtonClass: 'cancel',
            customClass: 'mp-print-bill-swal'
        }, function (isConfirm) {
            if (isConfirm) {
                openPrints();
            }
            finish();
        });
    } else {
        if (confirm('Do you want to print invoice?')) {
            openPrints();
        }
        finish();
    }
}

function jwqDiamondUsedRowIsSummary(r) {
    var p = String(r && r.product_name != null ? r.product_name : '').trim();
    return p.indexOf('Diamond total on line') >= 0 || p.indexOf('no per-piece') >= 0 || p.indexOf('no barcoded') >= 0 || p.indexOf('Diamond on line') >= 0;
}

/** Full issue ledger for (i) modal — includes barcodes also on the material grid so the list matches all diamonds issued on the job. Collapse duplicate barcode issue rows (keep latest id). */
function jwqFilterDiamondUsedModalRows(all) {
    var list = Array.isArray(all) ? all.slice() : [];
    var rest = [];
    var pick = [];
    list.forEach(function (r) {
        if (!r) {
            return;
        }
        if (String(r.row_source || '') === 'line_fallback' || jwqDiamondUsedRowIsSummary(r)) {
            rest.push(r);
            return;
        }
        var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var bc = String(r.barcode || '').trim();
        if (sid > 0 && bc !== '' && bc !== '—') {
            pick.push(r);
        } else {
            rest.push(r);
        }
    });
    var byBc = {};
    pick.forEach(function (r) {
        var bc = String(r.barcode || '').trim().toUpperCase();
        var idn = parseInt(String(r.id != null ? r.id : '0'), 10) || 0;
        var prev = byBc[bc];
        var pid = prev ? (parseInt(String(prev.id != null ? prev.id : '0'), 10) || 0) : -1;
        if (!prev || idn >= pid) {
            byBc[bc] = r;
        }
    });
    var vals = Object.keys(byBc).map(function (k) {
        return byBc[k];
    });
    vals.sort(function (a, b) {
        return (parseInt(String(b.id != null ? b.id : '0'), 10) || 0) - (parseInt(String(a.id != null ? a.id : '0'), 10) || 0);
    });
    return rest.concat(vals);
}

function jwqEnsureRemovedDiamondIssuesArray() {
    if (!Array.isArray(window.jwqRemovedDiamondIssues)) {
        window.jwqRemovedDiamondIssues = [];
    }
    return window.jwqRemovedDiamondIssues;
}

function jwqIsServerDiamondRowRemovedFromUi(r) {
    if (!r) {
        return false;
    }
    var list = jwqEnsureRemovedDiamondIssuesArray();
    var rid = parseInt(String(r.id != null ? r.id : '0'), 10) || 0;
    var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
    var bc = String(r.barcode || '').trim().toUpperCase();
    var i;
    for (i = 0; i < list.length; i++) {
        var x = list[i];
        if (!x || typeof x !== 'object') {
            continue;
        }
        var xid = parseInt(String(x.issue_id != null ? x.issue_id : '0'), 10) || 0;
        var xsid = parseInt(String(x.stock_id != null ? x.stock_id : '0'), 10) || 0;
        var xbc = String(x.barcode || '').trim().toUpperCase();
        if (xid > 0 && rid > 0 && xid === rid) {
            return true;
        }
        if (xsid > 0 && sid > 0 && xsid === sid && (xbc === '' || bc === '' || xbc === bc)) {
            return true;
        }
    }
    return false;
}

function jwqRefreshMatDiamondTotalFooter() {
    var matBody = jwqGetJobworkMaterialBody();
    if (!matBody) {
        return;
    }
    var wrap = matBody.closest('#jwqMatTableWrap') || matBody.closest('.jwq-mat-table-wrap') || matBody.closest('#jwqModalOverlay');
    var tot = wrap ? wrap.querySelector('#jwqMatTotalWt') : document.getElementById('jwqMatTotalWt');
    if (!tot) {
        return;
    }
    var sum = 0;
    matBody.querySelectorAll('tr.jwq-material-diamond-row').forEach(function (xtr) {
        var tdwt = xtr.querySelector('.jwq-mat-wt');
        sum += parseFloat(String(tdwt ? tdwt.textContent : '').replace(/,/g, '')) || 0;
    });
    tot.textContent = sum.toFixed(2);
}

function jwqRemoveUsedDiamondFromUi(btn) {
    if (!btn) {
        return;
    }
    var trModal = btn.closest('tr');
    var issueId = parseInt(btn.getAttribute('data-issue-id') || '0', 10) || 0;
    var stockId = parseInt(btn.getAttribute('data-stock-id') || '0', 10) || 0;
    var barcode = String(btn.getAttribute('data-barcode') || '').trim();
    var wRem = parseFloat(String(btn.getAttribute('data-weight') || '0').replace(/,/g, '')) || 0;
    var qRem = parseFloat(String(btn.getAttribute('data-qty') || '0').replace(/,/g, '')) || 0;
    if (wRem <= 0.0000001) {
        return;
    }
    var itemId = parseInt(btn.getAttribute('data-jobwork-order-item-id') || '0', 10) || 0;
    var list = jwqEnsureRemovedDiamondIssuesArray();
    var dup = false;
    var j;
    for (j = 0; j < list.length; j++) {
        var ex = list[j];
        if (!ex) {
            continue;
        }
        if (issueId > 0 && (parseInt(String(ex.issue_id || '0'), 10) || 0) === issueId) {
            dup = true;
            break;
        }
        if (stockId > 0 && (parseInt(String(ex.stock_id || '0'), 10) || 0) === stockId
            && String(ex.barcode || '').trim().toUpperCase() === barcode.toUpperCase()) {
            dup = true;
            break;
        }
    }
    if (!dup) {
        list.push({
            issue_id: issueId,
            stock_id: stockId,
            barcode: barcode,
            weight: wRem,
            qty: qRem > 0 ? qRem : 1
        });
    }
    if (trModal && trModal.parentNode) {
        trModal.parentNode.removeChild(trModal);
    }
    var matBody = jwqGetJobworkMaterialBody();
    if (matBody && (stockId > 0 || barcode)) {
        var bcu = barcode.toUpperCase();
        matBody.querySelectorAll('.jwq-material-diamond-row').forEach(function (mtr) {
            var msid = parseInt(mtr.getAttribute('data-stock-id') || mtr.dataset.stockId || '0', 10) || 0;
            var mbc = String(mtr.getAttribute('data-barcode') || '').trim().toUpperCase();
            if ((stockId > 0 && msid === stockId) || (bcu && mbc === bcu)) {
                mtr.remove();
            }
        });
        if (!matBody.querySelector('.jwq-material-diamond-row') && !matBody.querySelector('.jwq-material-metal-row')) {
            matBody.innerHTML = '<tr><td colspan="8" class="jwq-mat-empty">No Rows To Show</td></tr>';
        }
    }
    var tbody = document.getElementById('jwqOrderLinesBody');
    var trLine = null;
    if (itemId > 0 && tbody) {
        trLine = tbody.querySelector('tr[data-item-id="' + String(itemId) + '"]');
    }
    if (!trLine && tbody) {
        trLine = tbody.querySelector('tr[data-item-id]');
    }
    if (trLine) {
        var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(trLine, 'diamond_wt') : null;
        var tInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(trLine, 'total_wt') : null;
        var dw = dInp ? (parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0) : 0;
        var tw = tInp ? (parseFloat(String(tInp.value || '').replace(/,/g, '')) || 0) : 0;
        dw = Math.max(0, dw - wRem);
        tw = Math.max(0, tw - wRem);
        if (dInp) {
            dInp.value = typeof jwqNum3 === 'function' ? jwqNum3(dw) : String(dw);
        }
        if (tInp) {
            tInp.value = typeof jwqNum3 === 'function' ? jwqNum3(tw) : String(tw);
        }
    }
    jwqRefreshMatDiamondTotalFooter();
    var srv = window.__jwqLastDiamondServerItems;
    if (Array.isArray(srv)) {
        window.__jwqLastDiamondServerItems = srv.filter(function (row) {
            return !jwqIsServerDiamondRowRemovedFromUi(row);
        });
    }
    if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
        jwqSyncOrderLineDiamondWtFromMaterialTable();
    }
}
window.jwqRemoveUsedDiamondFromUi = jwqRemoveUsedDiamondFromUi;

function jwqApplyDiamondReduceDeltaToOrderLine(itemId, deltaW) {
    deltaW = parseFloat(deltaW) || 0;
    if (deltaW <= 0.0000001) {
        return;
    }
    var tbody = document.getElementById('jwqOrderLinesBody');
    var trLine = null;
    if (itemId > 0 && tbody) {
        trLine = tbody.querySelector('tr[data-item-id="' + String(itemId) + '"]');
    }
    if (!trLine && tbody) {
        trLine = tbody.querySelector('tr[data-item-id]');
    }
    if (!trLine) {
        return;
    }
    var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(trLine, 'diamond_wt') : null;
    var tInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(trLine, 'total_wt') : null;
    var dw = dInp ? (parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0) : 0;
    var tw = tInp ? (parseFloat(String(tInp.value || '').replace(/,/g, '')) || 0) : 0;
    dw = Math.max(0, dw - deltaW);
    tw = Math.max(0, tw - deltaW);
    if (dInp) {
        dInp.value = typeof jwqNum3 === 'function' ? jwqNum3(dw) : String(dw);
    }
    if (tInp) {
        tInp.value = typeof jwqNum3 === 'function' ? jwqNum3(tw) : String(tw);
    }
    if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
        jwqSyncOrderLineDiamondWtFromMaterialTable();
    }
}

function jwqCancelEditUsedDiamondRow(tr) {
    if (!tr || tr.getAttribute('data-jwq-editing') !== '1') {
        return;
    }
    if (typeof jwqOpenDiamondUsedModal === 'function') {
        jwqOpenDiamondUsedModal();
    }
}

function jwqStartEditUsedDiamondRow(btn) {
    if (!btn) {
        return;
    }
    var tr = btn.closest('tr');
    if (!tr || tr.getAttribute('data-jwq-editing') === '1') {
        return;
    }
    document.querySelectorAll('#jwqDiamondUsedModalBody tr[data-jwq-editing="1"]').forEach(function (other) {
        if (other !== tr) {
            jwqCancelEditUsedDiamondRow(other);
        }
    });
    var oldW = parseFloat(String(btn.getAttribute('data-weight') || tr.getAttribute('data-jwq-used-weight') || '0').replace(/,/g, '')) || 0;
    var oldQ = parseFloat(String(btn.getAttribute('data-qty') || tr.getAttribute('data-jwq-used-qty') || '0').replace(/,/g, '')) || 0;
    if (oldQ <= 0) {
        oldQ = 1;
    }
    var wtCell = tr.querySelector('.jwq-used-wt-cell');
    var qtyCell = tr.querySelector('.jwq-used-qty-cell');
    if (wtCell) {
        wtCell.innerHTML = '<input type="number" class="form-control form-control-sm jwq-used-edit-wt" min="0.001" step="0.001" max="' + String(oldW) + '" value="' + String(oldW) + '" style="max-width:90px;margin-left:auto;">';
    }
    if (qtyCell) {
        qtyCell.innerHTML = '<input type="number" class="form-control form-control-sm jwq-used-edit-qty" min="0" step="0.001" max="' + String(oldQ) + '" value="' + String(oldQ) + '" style="max-width:70px;margin-left:auto;">';
    }
    var actCell = tr.querySelector('.jwq-used-diamond-actions');
    if (actCell) {
        actCell.innerHTML = '<button type="button" class="btn btn-link btn-sm p-0 text-success jwq-save-used-diamond" title="Save reduction" aria-label="Save"><i class="feather icon-check"></i></button>'
            + ' <button type="button" class="btn btn-link btn-sm p-0 text-muted jwq-cancel-used-diamond" title="Cancel" aria-label="Cancel"><i class="feather icon-x"></i></button>';
    }
    tr.setAttribute('data-jwq-editing', '1');
    tr.setAttribute('data-jwq-edit-issue-id', btn.getAttribute('data-issue-id') || '');
    tr.setAttribute('data-jwq-edit-item-id', btn.getAttribute('data-jobwork-order-item-id') || '');
    tr.setAttribute('data-jwq-edit-old-weight', String(oldW));
    tr.setAttribute('data-jwq-edit-old-qty', String(oldQ));
}

function jwqSaveEditedUsedDiamondRow(btn) {
    if (!btn) {
        return;
    }
    var tr = btn.closest('tr');
    if (!tr) {
        return;
    }
    var issueId = parseInt(tr.getAttribute('data-jwq-edit-issue-id') || '0', 10) || 0;
    var itemId = parseInt(tr.getAttribute('data-jwq-edit-item-id') || '0', 10) || 0;
    var oldW = parseFloat(String(tr.getAttribute('data-jwq-edit-old-weight') || '0').replace(/,/g, '')) || 0;
    var oldQ = parseFloat(String(tr.getAttribute('data-jwq-edit-old-qty') || '0').replace(/,/g, '')) || 0;
    var wInp = tr.querySelector('.jwq-used-edit-wt');
    var qInp = tr.querySelector('.jwq-used-edit-qty');
    var newW = wInp ? parseFloat(String(wInp.value || '').replace(/,/g, '')) : NaN;
    var newQ = qInp ? parseFloat(String(qInp.value || '').replace(/,/g, '')) : NaN;
    var jwoEl = document.getElementById('jwqCurrentJwoId');
    var jwoId = jwoEl ? parseInt(jwoEl.value || '0', 10) : 0;
    if (jwoId < 1 || issueId < 1) {
        alert('Invalid job or diamond issue.');
        return;
    }
    if (!isFinite(newW) || newW <= 0) {
        alert('Enter a valid weight.');
        return;
    }
    if (newW >= oldW - 0.0000001) {
        alert('New weight must be less than current weight (' + (typeof jwqNum3 === 'function' ? jwqNum3(oldW) : oldW) + ' g).');
        return;
    }
    if (!isFinite(newQ) || newQ < 0) {
        alert('Enter a valid quantity.');
        return;
    }
    if (oldQ <= 0) {
        oldQ = 1;
    }
    if (newQ > oldQ + 0.0000001) {
        alert('New quantity cannot exceed current quantity.');
        return;
    }
    btn.disabled = true;
    var fd = new FormData();
    fd.append('jobwork_order_id', String(jwoId));
    fd.append('issue_id', String(issueId));
    fd.append('new_weight', String(newW));
    fd.append('new_qty', String(newQ));
    fetch('ajax/mp-save-jobwork-diamond-reduce.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (!data || !data.ok) {
                alert(data && data.message ? data.message : 'Save failed');
                return;
            }
            var returnedW = parseFloat(data.returned_weight) || (oldW - newW);
            jwqApplyDiamondReduceDeltaToOrderLine(itemId, returnedW);
            if (typeof window.mpReloadManufacturingQueueTable === 'function') {
                window.mpReloadManufacturingQueueTable();
            }
            alert(data.message || 'Saved.');
            if (typeof jwqOpenDiamondUsedModal === 'function') {
                jwqOpenDiamondUsedModal();
            }
        })
        .catch(function () {
            btn.disabled = false;
            alert('Save failed');
        });
}

function jwqRenderDiamondUsedModalBody(rows) {
    var tb = document.getElementById('jwqDiamondUsedModalBody');
    if (!tb) return;
    var list = Array.isArray(rows) ? rows : [];
    if (!list.length) {
        tb.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">No diamonds used yet.</td></tr>';
        return;
    }
    tb.innerHTML = list.map(function (r) {
        var pRaw = String(r && r.product_name != null ? r.product_name : '').trim();
        var sidNum = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
        var issueIdNum = parseInt(String(r && r.id != null ? r.id : '0'), 10) || 0;
        var itemIdNum = parseInt(String(r && r.jobwork_order_item_id != null ? r.jobwork_order_item_id : '0'), 10) || 0;
        var bcRaw = String(r && r.barcode != null ? r.barcode : '').trim();
        var isLineFb = String(r.row_source || '') === 'line_fallback';
        var isSummary = pRaw.indexOf('Diamond total on line') >= 0 || pRaw.indexOf('no per-piece') >= 0 || pRaw.indexOf('no barcoded') >= 0;
        var wRaw = r && r.weight_out != null ? r.weight_out : (r && r.weight != null ? r.weight : '');
        var wNum = parseFloat(String(wRaw).replace(/,/g, ''));
        var pickable = !isLineFb && !isSummary && sidNum > 0 && bcRaw !== '' && bcRaw !== '—' && isFinite(wNum) && wNum > 0.0000001;
        var barcode = jwqEsc(r && r.barcode != null ? r.barcode : '');
        var product = jwqEsc(r && r.product_name != null ? r.product_name : '');
        var weight = (typeof jwqNum3 === 'function' && isFinite(wNum)) ? jwqEsc(jwqNum3(wNum)) : jwqEsc(wRaw);
        var qRaw = r && r.qty_out != null ? r.qty_out : (r && r.qty != null ? r.qty : '');
        var qNum = parseFloat(String(qRaw).replace(/,/g, ''));
        var qty = (typeof jwqNum3 === 'function' && isFinite(qNum)) ? jwqEsc(jwqNum3(qNum)) : jwqEsc(qRaw);
        var addDeptNm = String(r && r.added_by_dept_name != null ? r.added_by_dept_name : '').trim();
        var addUserNm = String(r && r.added_by_user_name != null ? r.added_by_user_name : '').trim();
        var addDeptCell = addDeptNm ? jwqEsc(addDeptNm) : (function () {
            var id = parseInt(String(r && r.added_by_dept_id != null ? r.added_by_dept_id : '0'), 10) || 0;
            return id > 0 ? jwqEsc('Dept #' + id) : '—';
        }());
        var addUserCell = addUserNm ? jwqEsc(addUserNm) : (function () {
            var id = parseInt(String(r && r.added_by_user_id != null ? r.added_by_user_id : '0'), 10) || 0;
            return id > 0 ? jwqEsc('User #' + id) : '—';
        }());
        var issued = jwqEsc(r && r.created_at != null ? r.created_at : '(pending save)');
        var qStore = isFinite(qNum) && qNum > 0 ? qNum : 1;
        var wStore = isFinite(wNum) && wNum > 0 ? wNum : 0;
        var trCls = pickable ? ' jwq-diamond-used-pickable' : '';
        var trStyle = pickable ? 'cursor:pointer;' : '';
        var titlePick = pickable ? ' title="Click row to add to diamond material list (reduces line Diamond Wt; save to update stock)"' : '';
        var removeBtn = '';
        var showEdit = typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode() && pickable && issueIdNum > 0;
        if (pickable && (issueIdNum > 0 || sidNum > 0)) {
            var actionBtns = '';
            if (showEdit) {
                actionBtns += '<button type="button" class="btn btn-link btn-sm p-0 jwq-edit-used-diamond" style="color:#11294b;"'
                    + ' data-issue-id="' + String(issueIdNum) + '"'
                    + ' data-stock-id="' + String(sidNum) + '"'
                    + ' data-barcode="' + jwqAttrEsc(bcRaw) + '"'
                    + ' data-weight="' + String(wStore) + '"'
                    + ' data-qty="' + String(qStore) + '"'
                    + ' data-jobwork-order-item-id="' + String(itemIdNum > 0 ? itemIdNum : '') + '"'
                    + ' title="Reduce weight/qty — balance returns to stock" aria-label="Edit used diamond">'
                    + '<i class="feather icon-edit-2"></i></button> ';
            }
            actionBtns += '<button type="button" class="btn btn-link btn-sm text-danger p-0 jwq-remove-used-diamond"'
                + ' data-issue-id="' + String(issueIdNum > 0 ? issueIdNum : '') + '"'
                + ' data-stock-id="' + String(sidNum) + '"'
                + ' data-barcode="' + jwqAttrEsc(bcRaw) + '"'
                + ' data-weight="' + String(wStore) + '"'
                + ' data-qty="' + String(qStore) + '"'
                + ' data-jobwork-order-item-id="' + String(itemIdNum > 0 ? itemIdNum : '') + '"'
                + ' title="Remove from job and return stock (save to persist)" aria-label="Remove used diamond">'
                + '<i class="feather icon-trash-2"></i></button>';
            removeBtn = '<td class="text-center jwq-used-diamond-actions jwq-remove-used-diamond-wrap" style="width:56px;">' + actionBtns + '</td>';
        } else {
            removeBtn = '<td class="text-muted text-center">—</td>';
        }
        return '<tr class="' + trCls.trim() + '" style="' + trStyle + '"' + titlePick
            + ' data-jwq-used-issue-id="' + (issueIdNum > 0 ? String(issueIdNum) : '') + '"'
            + ' data-jwq-used-stock-id="' + (pickable ? String(sidNum) : '') + '" data-jwq-used-barcode="' + jwqAttrEsc(bcRaw) + '" data-jwq-used-weight="' + String(wStore) + '" data-jwq-used-qty="' + String(qStore) + '" data-jwq-used-product="' + jwqAttrEsc(pRaw) + '">'
            + '<td>' + (barcode || '—') + '</td>'
            + '<td>' + (product || '—') + '</td>'
            + '<td class="text-right jwq-used-wt-cell">' + (weight || '0') + '</td>'
            + '<td class="text-right jwq-used-qty-cell">' + (qty || '0') + '</td>'
            + '<td>' + addDeptCell + '</td>'
            + '<td>' + addUserCell + '</td>'
            + '<td>' + (issued || '—') + '</td>'
            + removeBtn
            + '</tr>';
    }).join('');
}

function jwqOpenDiamondUsedModal() {
    var jwoId = parseInt(window.__jwqCurrentJwoId || '0', 10) || 0;
    if (jwoId < 1) {
        var hidJwo = document.getElementById('jwqCurrentJwoId');
        jwoId = hidJwo ? (parseInt(hidJwo.value || '0', 10) || 0) : 0;
    }
    var tb = document.getElementById('jwqDiamondUsedModalBody');
    if (tb) {
        tb.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-3">Loading…</td></tr>';
    }
    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
        window.jQuery('#jwqDiamondUsedModal').modal('show');
    }

    function mergeAndRender(serverRows) {
        var srvRaw = Array.isArray(serverRows) ? serverRows : [];
        var srv = srvRaw.filter(function (row) {
            return !jwqIsServerDiamondRowRemovedFromUi(row);
        });
        window.__jwqLastDiamondServerItems = srv.slice();
        var serverHasIssueRows = srv.some(function (r) {
            if (!r || String(r.row_source || '') === 'line_fallback') {
                return false;
            }
            var sid = parseInt(String(r.stock_id != null ? r.stock_id : '0'), 10) || 0;
            var bc = String(r.barcode || '').trim();
            return sid > 0 && bc !== '';
        });
        var seen = {};
        var all = [];

        function srvDedupeKey(r) {
            var issueId = (r && r.id != null) ? parseInt(r.id, 10) : 0;
            if (issueId > 0) {
                return 'issue:' + issueId;
            }
            var sid = String(r.stock_id != null ? r.stock_id : '0');
            var bc = String(r.barcode || '').trim();
            var w = String(r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : ''));
            var iid = String((r && r.jobwork_order_item_id != null) ? r.jobwork_order_item_id : '');
            return 'row:' + sid + ':' + bc + ':' + w + ':item:' + iid;
        }

        srv.forEach(function (r) {
            var k = srvDedupeKey(r);
            if (seen[k]) {
                return;
            }
            seen[k] = true;
            all.push(r);
        });

        function serverCoversMatRow(sidNum, barcode, wn) {
            if (sidNum < 1) {
                return false;
            }
            var bb = String(barcode || '').trim();
            var i;
            for (i = 0; i < srv.length; i++) {
                var r = srv[i];
                var rsid = parseInt(String(r && r.stock_id != null ? r.stock_id : '0'), 10) || 0;
                if (rsid !== sidNum) {
                    continue;
                }
                var rb = String(r.barcode || '').trim();
                if (rb && bb && rb !== bb) {
                    continue;
                }
                var rw = parseFloat(r.weight_out != null ? r.weight_out : (r.weight != null ? r.weight : '')) || 0;
                if (Math.abs(rw - wn) < 0.0005) {
                    return true;
                }
            }
            return false;
        }

        var matGridRef = jwqGetJobworkMaterialBody();
        if (matGridRef) {
            matGridRef.querySelectorAll('.jwq-material-diamond-row').forEach(function (tr) {
            var tds = tr.querySelectorAll('td');
            if (tds.length < 2) {
                return;
            }
            var sidNum = parseInt(tr.getAttribute('data-stock-id') || tr.dataset.stockId || '0', 10) || 0;
            var wn = jwqMaterialDiamondRowEffectiveWeight(tr);
            if (wn <= 0) {
                return;
            }
            var barcode = String(tr.getAttribute('data-barcode') || tr.dataset.barcode || '').trim();
            if (serverCoversMatRow(sidNum, barcode, wn)) {
                return;
            }
            var woutStr = typeof jwqNum3 === 'function' ? jwqNum3(wn) : String(wn);
            var sid = sidNum > 0 ? String(sidNum) : '';
            var dedupe = sid !== '' ? ('mat:sid:' + sid + ':' + barcode + ':' + woutStr) : ('mat:pending:' + barcode + ':' + woutStr);
            if (seen[dedupe]) {
                return;
            }
            seen[dedupe] = true;
            all.push({
                stock_id: sid || '0',
                barcode: barcode,
                product_name: String(tr.dataset.productName || '').trim() || (tds[1] ? String(tds[1].textContent || '').trim() : ''),
                weight_out: woutStr,
                qty_out: tds[4] ? String(tds[4].textContent || '').trim() : '',
                added_by_dept_id: parseInt(String(tr.dataset.addedByDeptId || tr.getAttribute('data-added-by-dept-id') || '0'), 10) || 0,
                added_by_user_id: parseInt(String(tr.dataset.addedByUserId || tr.getAttribute('data-added-by-user-id') || '0'), 10) || 0,
                added_by_dept_name: String(tr.dataset.addedByDeptName || '').trim(),
                added_by_user_name: String(tr.dataset.addedByUserName || '').trim(),
                created_at: '(pending — save transfer to record in stock)'
            });
        });
        }

        if (all.length < 1 && !serverHasIssueRows) {
            document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
                var dInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'diamond_wt') : tr.querySelector('[data-field="diamond_wt"],[data-col-input="diamond_wt"]');
                if (!dInp) {
                    return;
                }
                var dw = parseFloat(String(dInp.value || '').replace(/,/g, '')) || 0;
                if (dw <= 0.0000001) {
                    return;
                }
                var tagInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'tag_no') : tr.querySelector('[data-field="tag_no"],[data-col-input="tag_no"]');
                var tagNo = tagInp ? String(tagInp.value || '').trim() : '';
                var descInp = typeof jwqLineFieldEl === 'function' ? jwqLineFieldEl(tr, 'description') : tr.querySelector('[data-field="description"],[data-col-input="description"]');
                var desc = descInp ? String(descInp.value || '').trim() : '';
                var lbl = (tagNo || desc || ('Item #' + (tr.getAttribute('data-item-id') || '?'))).trim();
                all.push({
                    stock_id: 0,
                    barcode: '—',
                    product_name: 'Diamond total on line (no per-piece rows yet): ' + lbl,
                    weight_out: jwqNum3(dw),
                    qty_out: '—',
                    added_by_dept_id: 0,
                    added_by_user_id: 0,
                    added_by_dept_name: '',
                    added_by_user_name: '',
                    created_at: 'Add diamonds in the material grid and save to log each barcode in stock.'
                });
            });
        }

        all = jwqFilterDiamondUsedModalRows(all);

        jwqRenderDiamondUsedModalBody(all);
        /* Do not sync line weights when opening "(i)" — read-only modal; sync caused runaway totals. */
    }

    if (jwoId < 1) {
        mergeAndRender([]);
        return;
    }
    var url = 'ajax/mp-get-jobwork-queue-diamonds.php?jobwork_order_id=' + encodeURIComponent(String(jwoId));
    fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            mergeAndRender((d && d.ok && Array.isArray(d.items)) ? d.items : []);
        })
        .catch(function () {
            mergeAndRender([]);
        });
}

function mpGetFirstVisibleJobCard() {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid) return null;
    var cards = grid.querySelectorAll('.mp-job-card[data-jwo-id]');
    var i;
    for (i = 0; i < cards.length; i++) {
        var c = cards[i];
        if (mpJobCardIsFilteredHidden(c)) continue;
        return c;
    }
    return null;
}

function mpGetVisibleJobCards() {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid) return [];
    var out = [];
    grid.querySelectorAll('.mp-job-card[data-jwo-id]').forEach(function (card) {
        if (!mpJobCardIsFilteredHidden(card)) out.push(card);
    });
    return out;
}

function mpSyncBulkSelectCheckboxesForJwo(jwoId, checked) {
    if (!jwoId) return;
    document.querySelectorAll('.mp-job-card-select[data-jwo-id="' + jwoId + '"]').forEach(function (cb) {
        cb.checked = !!checked;
    });
    document.querySelectorAll('.mp-job-card[data-jwo-id="' + jwoId + '"]').forEach(function (card) {
        card.classList.toggle('is-bulk-selected', !!checked);
    });
}

function mpGetSelectedBulkJwoIds() {
    var seen = {};
    document.querySelectorAll('#mpJobCardsGrid .mp-job-card-select:checked').forEach(function (cb) {
        var id = parseInt(cb.getAttribute('data-jwo-id') || '0', 10);
        if (id > 0) seen[id] = true;
    });
    return Object.keys(seen).map(function (k) { return parseInt(k, 10); });
}

function mpGetFirstCardForJwo(jwoId) {
    var cards = document.querySelectorAll('.mp-job-card[data-jwo-id="' + jwoId + '"]');
    var i;
    for (i = 0; i < cards.length; i++) {
        if (!mpJobCardIsFilteredHidden(cards[i])) return cards[i];
    }
    return cards.length ? cards[0] : null;
}

function mpUpdateBulkTransferToolbar() {
    var ids = mpGetSelectedBulkJwoIds();
    var cntEl = document.getElementById('mpBulkSelCount');
    var btn = document.getElementById('mpBulkTransferBtn');
    if (cntEl) cntEl.textContent = String(ids.length);
    if (btn) btn.disabled = ids.length === 0;
}

function mpUpdateBulkSelectAllState() {
    var selectAll = document.getElementById('mpBulkSelectAllVisible');
    if (!selectAll) return;
    var visibleJwo = {};
    mpGetVisibleJobCards().forEach(function (card) {
        var id = parseInt(card.getAttribute('data-jwo-id') || '0', 10);
        if (id > 0) visibleJwo[id] = true;
    });
    var visibleIds = Object.keys(visibleJwo);
    if (!visibleIds.length) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        mpUpdateBulkTransferToolbar();
        return;
    }
    var selected = mpGetSelectedBulkJwoIds();
    var selVisible = selected.filter(function (id) { return visibleJwo[id]; });
    selectAll.checked = selVisible.length === visibleIds.length;
    selectAll.indeterminate = selVisible.length > 0 && selVisible.length < visibleIds.length;
    mpUpdateBulkTransferToolbar();
}

function mpBulkTransferCloseModal() {
    var overlay = document.getElementById('mpBulkTransferOverlay');
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
}

function mpBulkTransferOpenModal() {
    jwqOpenModalForMultipleOrders(mpGetSelectedBulkJwoIds());
}

function mpBulkTransferSaveOne(jwoId, toDeptId, toUserId) {
    var card = mpGetFirstCardForJwo(jwoId);
    var fromDeptId = card ? parseInt(card.getAttribute('data-dept-id') || '0', 10) : 0;
    var fromUserId = card ? parseInt(card.getAttribute('data-user-id') || '0', 10) : 0;
    var fd = new FormData();
    fd.append('jobwork_order_id', String(jwoId));
    fd.append('to_dept_id', String(toDeptId));
    if (toUserId > 0) fd.append('to_user_id', String(toUserId));
    if (fromDeptId > 0) fd.append('from_dept_id', String(fromDeptId));
    if (fromUserId > 0) fd.append('from_user_id', String(fromUserId));
    fd.append('queue_lines', '[]');
    fd.append('jwq_diamond_stock_lines', '[]');
    return fetch('ajax/mp-save-jobwork-queue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) {
            return r.text().then(function (txt) {
                var data = null;
                try { data = JSON.parse(txt || '{}'); } catch (e) { data = null; }
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Transfer failed for order #' + jwoId);
                }
                if (typeof mpUpdateJobCardAfterTransfer === 'function') {
                    mpUpdateJobCardAfterTransfer(jwoId, data);
                }
                return data;
            });
        });
}

function mpBulkTransferRun() {
    var ids = mpGetSelectedBulkJwoIds();
    if (!ids.length) {
        alert('No orders selected.');
        return;
    }
    var toDeptEl = document.getElementById('mpBulkToDept');
    var toUserEl = document.getElementById('mpBulkToUser');
    var confirmBtn = document.getElementById('mpBulkTransferConfirm');
    var toDeptId = toDeptEl ? parseInt(toDeptEl.value || '0', 10) : 0;
    if (toDeptId < 1) {
        alert('Please select destination department (To Dept).');
        return;
    }
    var toUserId = toUserEl ? parseInt(toUserEl.value || '0', 10) : 0;

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Transferring…';
    }

    var ok = 0;
    var failed = [];

    function next(i) {
        if (i >= ids.length) {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Transfer all';
            }
            mpBulkTransferCloseModal();
            ids.forEach(function (id) { mpSyncBulkSelectCheckboxesForJwo(id, false); });
            mpUpdateBulkSelectAllState();
            if (typeof mpReloadManufacturingQueueTable === 'function') {
                mpReloadManufacturingQueueTable();
            }
            if (failed.length) {
                alert('Transferred ' + ok + ' of ' + ids.length + ' order(s).\n\nFailed:\n' + failed.join('\n'));
            } else {
                alert('Successfully transferred ' + ok + ' order' + (ok === 1 ? '' : 's') + '.');
            }
            return;
        }
        if (confirmBtn) {
            confirmBtn.textContent = 'Transferring ' + (i + 1) + '/' + ids.length + '…';
        }
        mpBulkTransferSaveOne(ids[i], toDeptId, toUserId).then(function () {
            ok++;
            next(i + 1);
        }).catch(function (err) {
            var card = mpGetFirstCardForJwo(ids[i]);
            var label = card ? (card.getAttribute('data-jobwork-no') || ('JWO #' + ids[i])) : ('JWO #' + ids[i]);
            failed.push(label + ': ' + (err && err.message ? err.message : 'Error'));
            next(i + 1);
        });
    }
    next(0);
}

function initMpBulkTransfer() {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid || grid._mpBulkBound) return;
    grid._mpBulkBound = true;

    grid.addEventListener('change', function (e) {
        var cb = e.target && e.target.classList && e.target.classList.contains('mp-job-card-select') ? e.target : null;
        if (!cb || !grid.contains(cb)) return;
        var jwoId = parseInt(cb.getAttribute('data-jwo-id') || '0', 10);
        mpSyncBulkSelectCheckboxesForJwo(jwoId, cb.checked);
        mpUpdateBulkSelectAllState();
    });

    grid.addEventListener('click', function (e) {
        if (e.target.closest('.mp-job-card-select-wrap')) {
            e.stopPropagation();
        }
    });

    var selectAll = document.getElementById('mpBulkSelectAllVisible');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var checked = !!selectAll.checked;
            var visibleJwo = {};
            mpGetVisibleJobCards().forEach(function (card) {
                var id = parseInt(card.getAttribute('data-jwo-id') || '0', 10);
                if (id > 0) visibleJwo[id] = true;
            });
            Object.keys(visibleJwo).forEach(function (k) {
                mpSyncBulkSelectCheckboxesForJwo(parseInt(k, 10), checked);
            });
            mpUpdateBulkSelectAllState();
        });
    }

    var transferBtn = document.getElementById('mpBulkTransferBtn');
    if (transferBtn) {
        transferBtn.addEventListener('click', function (e) {
            e.preventDefault();
            mpBulkTransferOpenModal();
        });
    }

    var overlay = document.getElementById('mpBulkTransferOverlay');
    if (overlay && !overlay._mpBulkBound) {
        overlay._mpBulkBound = true;
        var cancelBtn = document.getElementById('mpBulkTransferCancel');
        if (cancelBtn) cancelBtn.addEventListener('click', mpBulkTransferCloseModal);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) mpBulkTransferCloseModal();
        });
        var toDept = document.getElementById('mpBulkToDept');
        var toUser = document.getElementById('mpBulkToUser');
        if (toDept && toUser) {
            toDept.addEventListener('change', function () {
                var id = parseInt(toDept.value || '0', 10);
                jwqFillUserSelectForDept(toUser, id);
            });
        }
        var confirmBtn = document.getElementById('mpBulkTransferConfirm');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function (e) {
                e.preventDefault();
                mpBulkTransferRun();
            });
        }
    }

    mpUpdateBulkSelectAllState();
}

function initJobworkQueueModal() {
    var grid = document.getElementById('mpJobCardsGrid');
    var overlay = document.getElementById('jwqModalOverlay');
    if (!grid || !overlay || overlay._jwqBound) return;
    overlay._jwqBound = true;
    initJwqDecimalInputGuards();

    document.addEventListener('click', function (e) {
        var infoBtn = e.target && e.target.closest ? e.target.closest('.jwq-diamond-used-info-btn') : null;
        if (!infoBtn || !overlay.contains(infoBtn)) return;
        e.preventDefault();
        jwqOpenDiamondUsedModal();
    });

    document.addEventListener('click', function (e) {
        var editBtn = e.target && e.target.closest ? e.target.closest('.jwq-edit-used-diamond') : null;
        if (editBtn && document.getElementById('jwqDiamondUsedModal') && document.getElementById('jwqDiamondUsedModal').contains(editBtn)) {
            e.preventDefault();
            e.stopPropagation();
            jwqStartEditUsedDiamondRow(editBtn);
            return;
        }
        var saveEditBtn = e.target && e.target.closest ? e.target.closest('.jwq-save-used-diamond') : null;
        if (saveEditBtn && document.getElementById('jwqDiamondUsedModal') && document.getElementById('jwqDiamondUsedModal').contains(saveEditBtn)) {
            e.preventDefault();
            e.stopPropagation();
            jwqSaveEditedUsedDiamondRow(saveEditBtn);
            return;
        }
        var cancelEditBtn = e.target && e.target.closest ? e.target.closest('.jwq-cancel-used-diamond') : null;
        if (cancelEditBtn) {
            e.preventDefault();
            e.stopPropagation();
            jwqCancelEditUsedDiamondRow(cancelEditBtn.closest('tr'));
            return;
        }
    });

    var toolbarJwq = document.getElementById('mpToolbarJobworkQueueBtn');
    if (toolbarJwq) {
        toolbarJwq.addEventListener('click', function (e) {
            e.preventDefault();
            var selected = typeof mpGetSelectedBulkJwoIds === 'function' ? mpGetSelectedBulkJwoIds() : [];
            if (selected.length > 0) {
                jwqOpenModalForMultipleOrders(selected);
                return;
            }
            if (typeof setMpViewMode === 'function') {
                setMpViewMode('cards');
            }
            var card = mpGetFirstVisibleJobCard();
            if (!card) {
                alert('No job work order is visible. Select orders with checkboxes, or clear the department filter.');
                return;
            }
            var btn = card.querySelector('.mp-jwq-open-btn');
            if (!btn) {
                alert('Could not open Jobwork Queue.');
                return;
            }
            jwqOpenModal(btn, { skipActionLabel: true });
        });
    }

    overlay.addEventListener('click', function (e) {
        var folderBtn = e.target.closest('.jwq-inward-folder-btn');
        if (folderBtn && overlay.contains(folderBtn)) {
            e.preventDefault();
            jwqFillInwardStockDetailsModal(folderBtn.getAttribute('data-which') || 'from');
        }
    });

    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.mp-jwq-open-btn');
        if (!btn || !grid.contains(btn)) return;
        e.preventDefault();
        jwqOpenModal(btn);
    });

    var closeBtn = document.getElementById('jwqModalClose');
    if (closeBtn) closeBtn.addEventListener('click', jwqCloseModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) jwqCloseModal();
    });

    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
        window.jQuery('#jwqDiamondUsedModal').off('hidden.bs.modal.jwqDiamondSync').on('hidden.bs.modal.jwqDiamondSync', function () {
            if (typeof jwqFetchDiamondServerItemsThen === 'function') {
                jwqFetchDiamondServerItemsThen(function () {
                    if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                        jwqSyncOrderLineDiamondWtFromMaterialTable();
                    }
                });
            }
        });
    }

    var fromDept = document.getElementById('jwqFromDept');
    var fromUser = document.getElementById('jwqFromUser');
    var toDept = document.getElementById('jwqToDept');
    var toUser = document.getElementById('jwqToUser');
    if (fromDept && fromUser) {
        fromDept.addEventListener('change', function () {
            var id = parseInt(fromDept.value || '0', 10);
            jwqFillUserSelectForDept(fromUser, id);
            if (typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode()) {
                if (typeof window.jwqRefreshAddWeightLinePreviews === 'function') {
                    window.jwqRefreshAddWeightLinePreviews();
                }
            } else if (typeof jwqRefreshAutoLossAllRows === 'function') {
                jwqRefreshAutoLossAllRows();
            }
            if (typeof jwqRefreshServerLoadedMaterialDiamonds === 'function') {
                jwqRefreshServerLoadedMaterialDiamonds();
            }
        });
    }
    if (toDept && toUser) {
        toDept.addEventListener('change', function () {
            var id = parseInt(toDept.value || '0', 10);
            jwqFillUserSelectForDept(toUser, id);
        });
    }

    var btnSave = document.getElementById('jwqBtnSave');
    if (btnSave) {
        btnSave.addEventListener('click', function () {
            if (window.__jwqBulkMode && window.__jwqBulkJwoIds && window.__jwqBulkJwoIds.length > 1) {
                jwqSaveBulkJobworkQueue();
                return;
            }
            var jwo = document.getElementById('jwqCurrentJwoId');
            var id = jwo ? parseInt(jwo.value || '0', 10) : 0;
            if (id < 1) {
                alert('No job work order selected.');
                return;
            }
            var toD = document.getElementById('jwqToDept');
            var toU = document.getElementById('jwqToUser');
            var toDeptId = toD ? parseInt(toD.value || '0', 10) : 0;
            var fromD = document.getElementById('jwqFromDept');
            var fromU = document.getElementById('jwqFromUser');
            var reduceOnly = typeof window.jwqIsReduceWeightMode === 'function' && window.jwqIsReduceWeightMode();
            var addOnly = typeof window.jwqIsAddWeightMode === 'function' && window.jwqIsAddWeightMode();
            var toUserVal = toU && toU.value ? toU.value : '';
            if (toDeptId < 1) {
                alert(reduceOnly
                    ? 'Please select To Dept to receive the reduced weight.'
                    : 'Please select destination department (To Dept.). The job will move to that department after save.');
                return;
            }
            var fd = new FormData();
            fd.append('jobwork_order_id', String(id));
            fd.append('to_dept_id', String(toDeptId));
            if (toUserVal) {
                fd.append('to_user_id', toUserVal);
            }
            if (reduceOnly) {
                fd.append('reduce_only', '1');
            }
            if (addOnly) {
                fd.append('add_only', '1');
            }
            if (fromD && fromD.value) {
                fd.append('from_dept_id', String(fromD.value));
            }
            if (fromU && fromU.value) {
                fd.append('from_user_id', String(fromU.value));
            }
            if (reduceOnly) {
                var metalReduceLines = typeof window.jwqCollectMaterialMetalReduceForSave === 'function'
                    ? window.jwqCollectMaterialMetalReduceForSave()
                    : [];
                var manualReduceWt = typeof window.jwqComputeManualReduceWeightGrams === 'function'
                    ? window.jwqComputeManualReduceWeightGrams()
                    : 0;
                if ((!metalReduceLines || metalReduceLines.length === 0) && manualReduceWt <= 0) {
                    alert('Enter reduce weight in Metal Wt (e.g. 3 g reduces from current total), or add M. Exch. rows.');
                    return;
                }
                var sumReduceWt = typeof window.jwqSumMaterialMetalReduceWt === 'function' ? window.jwqSumMaterialMetalReduceWt() : 0;
                var totalReduceCheck = sumReduceWt + manualReduceWt;
                var firstLineTr = document.querySelector('#jwqOrderLinesBody tr[data-item-id]');
                if (firstLineTr && totalReduceCheck > 0) {
                    var origLineT = parseFloat(firstLineTr.getAttribute('data-jwq-orig-total-before-reduce') || firstLineTr.getAttribute('data-orig-total-wt') || '');
                    if (!isFinite(origLineT) && typeof jwqLineFieldNum === 'function') {
                        origLineT = jwqLineFieldNum(firstLineTr, 'total_wt');
                    }
                    if (isFinite(origLineT) && totalReduceCheck > origLineT + 0.0001) {
                        alert('Total reduce weight (' + totalReduceCheck.toFixed(3) + ' g) cannot exceed line total weight (' + origLineT.toFixed(3) + ' g).');
                        return;
                    }
                }
            }
            if (addOnly) {
                var metalAddLines = typeof window.jwqCollectMaterialMetalAddForSave === 'function'
                    ? window.jwqCollectMaterialMetalAddForSave()
                    : [];
                var manualAddWt = typeof window.jwqComputeManualAddWeightGrams === 'function'
                    ? window.jwqComputeManualAddWeightGrams()
                    : 0;
                if ((!metalAddLines || metalAddLines.length === 0) && manualAddWt <= 0) {
                    alert('Enter extra metal weight in Metal Wt (e.g. 3 g adds to current total), or add M. Exch. rows.');
                    return;
                }
                if (manualAddWt > 0) {
                    var fromDeptIdAdd = fromD ? parseInt(fromD.value || '0', 10) : 0;
                    if (fromDeptIdAdd < 1) {
                        alert('Select From Dept to deduct manual add weight.');
                        if (fromD) {
                            fromD.focus();
                        }
                        return;
                    }
                    var fromUserIdAdd = fromU ? parseInt(fromU.value || '0', 10) : 0;
                    var selDept = selectedDeptId != null ? parseInt(selectedDeptId, 10) : 0;
                    var selUser = selectedUserId != null ? parseInt(selectedUserId, 10) : 0;
                    var balanceFromSameDept = (selDept < 1 || selDept === fromDeptIdAdd)
                        && (selUser < 1 || fromUserIdAdd < 1 || selUser === fromUserIdAdd);
                    if (balanceFromSameDept && typeof mpReadInwardBalanceWt === 'function') {
                        var deptBalance = mpReadInwardBalanceWt();
                        if (deptBalance !== null && manualAddWt > deptBalance + 0.0001) {
                            alert('Insufficient balance in From Dept (available ' + deptBalance.toFixed(3) + ' g, need ' + manualAddWt.toFixed(3) + ' g).');
                            return;
                        }
                    }
                }
            }
            var runSave = function () {
                window.__jwqSavingQueue = true;
                try {
                var manualAddGramsForSave = 0;
                var manualReduceGramsForSave = 0;
                if (addOnly && typeof window.jwqComputeManualAddWeightGrams === 'function') {
                    manualAddGramsForSave = window.jwqComputeManualAddWeightGrams();
                }
                if (reduceOnly && typeof window.jwqComputeManualReduceWeightGrams === 'function') {
                    manualReduceGramsForSave = window.jwqComputeManualReduceWeightGrams();
                }
                if (reduceOnly && typeof window.jwqSyncOrderLineTotalWtFromMetalReduceTable === 'function') {
                    window.jwqSyncOrderLineTotalWtFromMetalReduceTable();
                }
                if (addOnly && typeof window.jwqFinalizeAddWeightLineTotalsBeforeSave === 'function') {
                    window.jwqFinalizeAddWeightLineTotalsBeforeSave();
                }
                if (reduceOnly && typeof window.jwqFinalizeReduceWeightLineTotalsBeforeSave === 'function') {
                    window.jwqFinalizeReduceWeightLineTotalsBeforeSave();
                }
                if (typeof jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
                    jwqSyncOrderLineDiamondWtFromMaterialTable();
                }
                if (typeof jwqLockTransferLineWeightsForSave === 'function') {
                    jwqLockTransferLineWeightsForSave();
                }
                if (typeof jwqDebugLogQueueLinesBeforeSave === 'function') {
                    jwqDebugLogQueueLinesBeforeSave();
                }
                var lines = typeof jwqCollectQueueLinePayload === 'function' ? jwqCollectQueueLinePayload() : [];
                fd.append('queue_lines', JSON.stringify(lines));
                var dstock = typeof jwqCollectMaterialDiamondStockForSave === 'function' ? jwqCollectMaterialDiamondStockForSave() : [];
                var metalReduceSave = reduceOnly && typeof window.jwqCollectMaterialMetalReduceForSave === 'function'
                    ? window.jwqCollectMaterialMetalReduceForSave()
                    : [];
                var metalAddSave = addOnly && typeof window.jwqCollectMaterialMetalAddForSave === 'function'
                    ? window.jwqCollectMaterialMetalAddForSave()
                    : [];
                if (window.console && typeof console.log === 'function') {
                    console.log('JWQ_SAVE_LINES', lines);
                    console.log('JWQ_SAVE_DIAMONDS', dstock);
                    if (reduceOnly) {
                        console.log('JWQ_SAVE_METAL_REDUCE', metalReduceSave);
                    }
                    if (addOnly) {
                        console.log('JWQ_SAVE_METAL_ADD', metalAddSave);
                    }
                }
                if ((!dstock || dstock.length === 0)
                    && (!metalReduceSave || metalReduceSave.length === 0)
                    && (!metalAddSave || metalAddSave.length === 0)
                    && typeof jwqMaterialBodyHasVisibleDataRows === 'function'
                    && jwqMaterialBodyHasVisibleDataRows()) {
                    alert('Jobwork Queue: diamond save payload is empty but the material table has rows. Check the console (JWQ_SAVE_DIAMONDS).');
                }
                // Per-piece diamond lines from .jwq-material-diamond-row only (see jwqCollectMaterialDiamondStockForSave).
                fd.append('jwq_diamond_stock_lines', JSON.stringify(dstock));
                if (reduceOnly && metalReduceSave && metalReduceSave.length > 0) {
                    fd.append('metal_exchange_reduce_lines', JSON.stringify(metalReduceSave));
                }
                if (addOnly && metalAddSave && metalAddSave.length > 0) {
                    fd.append('metal_exchange_add_lines', JSON.stringify(metalAddSave));
                }
                if (addOnly) {
                    fd.append('manual_add_weight_grams', String(manualAddGramsForSave));
                }
                if (reduceOnly) {
                    fd.append('manual_reduce_weight_grams', String(manualReduceGramsForSave));
                }
                if (typeof jwqEnsureRemovedDiamondIssuesArray === 'function' && jwqEnsureRemovedDiamondIssuesArray().length > 0) {
                    fd.append('removed_diamond_issues', JSON.stringify(window.jwqRemovedDiamondIssues));
                }
                } finally {
                    window.__jwqSavingQueue = false;
                }
                fetch('ajax/mp-save-jobwork-queue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.text().then(function (txt) {
                            return { status: r.status, text: txt };
                        });
                    })
                    .then(function (resp) {
                        var data = null;
                        try {
                            data = JSON.parse(resp.text || '{}');
                        } catch (e) {
                            var raw = String(resp.text || '').trim();
                            alert('Save failed (invalid server response): ' + (raw ? raw.slice(0, 320) : ('HTTP ' + resp.status)));
                            return;
                        }
                        if (!data.ok) {
                            alert(data.message || 'Save failed');
                            return;
                        }
                        window.jwqRemovedDiamondIssues = [];
                        if (data.jobwork_queue_no) {
                            var qh = document.getElementById('jwqJobworkQueueNo');
                            jwqSetModalTitle(data.jobwork_queue_no);
                            if (qh) {
                                qh.value = data.jobwork_queue_no;
                            }
                            document.querySelectorAll('.mp-jwq-open-btn[data-jwo-id="' + id + '"]').forEach(function (b) {
                                b.setAttribute('data-jobwork-queue-no', data.jobwork_queue_no);
                            });
                        }
                        /* Refresh job card total weight / dept after any successful save (not only transfers). */
                        if (typeof mpUpdateJobCardAfterTransfer === 'function') {
                            mpUpdateJobCardAfterTransfer(id, data);
                        }
                        if (typeof mpReloadManufacturingQueueTable === 'function') {
                            mpReloadManufacturingQueueTable();
                        }
                        if (typeof mpJwqPromptPrintAfterSave === 'function') {
                            mpJwqPromptPrintAfterSave(id);
                        } else {
                            jwqCloseModal();
                        }
                    })
                    .catch(function () {
                        alert('Save failed');
                    });
            };
            if (typeof jwqFetchDiamondServerItemsThen === 'function') {
                jwqFetchDiamondServerItemsThen(runSave);
            } else {
                runSave();
            }
        });
    }

    var btnCat = document.getElementById('jwqBtnCatalogue');
    if (btnCat) {
        btnCat.addEventListener('click', function () {
            alert('Create Catalogue — link this to your catalogue flow when ready.');
        });
    }

    var btnBom = document.getElementById('jwqBtnBom');
    var btnOrd = document.getElementById('jwqBtnOrder');
    if (btnBom) btnBom.addEventListener('click', function () { alert('BOM — connect to bill of materials.'); });
    if (btnOrd) btnOrd.addEventListener('click', function () { alert('Order — connect to order lookup.'); });

    var imgBox = document.getElementById('jwqImagesBox');
    if (imgBox) {
        imgBox.addEventListener('click', function (e) {
            e.preventDefault();
            var jid = (window.__jwqCurrentJwoId && window.__jwqCurrentJwoId > 0) ? window.__jwqCurrentJwoId : 0;
            if (jid < 1) {
                var hid = document.getElementById('jwqCurrentJwoId');
                jid = hid ? parseInt(hid.value || '0', 10) : 0;
            }
            if (jid < 1) {
                alert('No job work order selected.');
                return;
            }
            if (typeof mpJwqOpenAddImageModal === 'function') {
                mpJwqOpenAddImageModal(jid);
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var aim = document.getElementById('addImageModal');
        if (aim && aim.classList.contains('show')) return;
        if (overlay.classList.contains('show')) jwqCloseModal();
    });
}

// Open from Job Work Order save: ?department_id= & optional user_id= (Job Worker customer id)
document.addEventListener('DOMContentLoaded', function () {
    try {
        var params = new URLSearchParams(window.location.search);
        var did = params.get('department_id') || params.get('dept_id');
        if (!did) return;
        var deptId = parseInt(did, 10);
        if (isNaN(deptId) || deptId < 1) return;
        var deptLi = document.querySelector('.dept-list > li[data-dept-id="' + deptId + '"]');
        if (!deptLi) return;
        var uid = params.get('user_id');
        var uidNum = uid ? parseInt(uid, 10) : 0;
        if (uidNum > 0) {
            var anchor = deptLi.querySelector('.dept-user-list a[data-user-id="' + uidNum + '"]');
            if (anchor && typeof selectUser === 'function') {
                selectUser({ stopPropagation: function () {} }, anchor, uidNum, deptId);
                return;
            }
        }
        var deptAnchor = deptLi.querySelector(':scope > a');
        if (deptAnchor && typeof toggleDepartment === 'function') {
            toggleDepartment(deptAnchor, deptId);
        }
    } catch (err) {
        console.warn('Manufacturing URL department filter', err);
    }
});
</script>
</body>
</html>
