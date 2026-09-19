<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$sale_order_id = isset($_POST['sale_order_id']) ? (int)$_POST['sale_order_id'] : 0;
/** When 1, always INSERT a new tbl_material_receives row (do not reuse existing for same sale order). */
$force_new_jwo = isset($_POST['force_new_jwo']) && ($_POST['force_new_jwo'] === '1' || $_POST['force_new_jwo'] === 1 || $_POST['force_new_jwo'] === true);
$jwo_status = isset($_POST['jwo_status']) ? trim($_POST['jwo_status']) : 'Processing';
$department_id = (isset($_POST['department_id']) && $_POST['department_id'] !== '') ? (int)$_POST['department_id'] : null;
$priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
$jwo_id = isset($_POST['jwo_id']) ? (int)$_POST['jwo_id'] : 0;
$department_user_id_provided = isset($_POST['department_user_id']);
$department_user_id = null;
if ($department_user_id_provided) {
    $v_du = trim((string)$_POST['department_user_id']);
    $department_user_id = ($v_du !== '' && (int)$v_du > 0) ? (int)$v_du : null;
}

$sales_person_post = isset($_POST['sales_person']) ? trim((string)$_POST['sales_person']) : '';

$items_json = isset($_POST['items']) ? $_POST['items'] : '';
$items = [];
if (is_string($items_json) && $items_json !== '') {
    $items = json_decode($items_json, true);
}
if (!is_array($items)) {
    $items = [];
}

$payments_json_mr = isset($_POST['payments']) ? $_POST['payments'] : '';
$me_mr_payments = [];
if (is_string($payments_json_mr) && $payments_json_mr !== '') {
    $me_mr_payments = json_decode($payments_json_mr, true);
}
if (!is_array($me_mr_payments)) {
    $me_mr_payments = [];
}
$metal_exchange_barcodes_out = [];

if ($sale_order_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Sale order ID required']);
    exit;
}

$tbl_master = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_receives'");
$tbl_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_receive_items'");
if (!$tbl_master || mysqli_num_rows($tbl_master) === 0 || !$tbl_items || mysqli_num_rows($tbl_items) === 0) {
    if ($tbl_master) {
        mysqli_free_result($tbl_master);
    }
    if ($tbl_items) {
        mysqli_free_result($tbl_items);
    }
    echo json_encode(['status' => 'error', 'message' => 'Material receive tables not found. Please run admin/sql/create_tbl_material_receives.sql']);
    exit;
}
mysqli_free_result($tbl_master);
mysqli_free_result($tbl_items);

$has_mr_branch = auragold_ensure_table_branch_id_column($conn, 'tbl_material_receives');
$hdr_mr_branch = auragold_transaction_header_branch_id();
$mr_scope_sql = ($has_mr_branch && $hdr_mr_branch > 0) ? (' AND branch_id = ' . (int) $hdr_mr_branch) : '';

$map_chk_req = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
$has_department_user_map = ($map_chk_req && mysqli_num_rows($map_chk_req) > 0);
if ($map_chk_req) {
    mysqli_free_result($map_chk_req);
}

if ($has_department_user_map && $department_id !== null && (int)$department_id > 0) {
    if (!$department_user_id_provided || $department_user_id === null || (int)$department_user_id < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Worker Name is required when Department is selected']);
        exit;
    }
}
if ($department_id !== null && (int)$department_id > 0) {
    $department_id = (int)$department_id;
} else {
    $department_id = null;
}

if ($jwo_id < 1 && !$force_new_jwo) {
    $row_existing = getRecord("SELECT id FROM tbl_material_receives WHERE sale_order_id = $sale_order_id$mr_scope_sql LIMIT 1");
    if ($row_existing && !empty($row_existing['id'])) {
        $jwo_id = (int)$row_existing['id'];
    }
}

$sale_order = getRecord("SELECT id, order_no, customer_name, order_date, due_date, grand_total, status FROM tbl_sale_orders WHERE id = $sale_order_id");
if (!$sale_order) {
    echo json_encode(['status' => 'error', 'message' => 'Sale order not found']);
    exit;
}

if (auragold_tbl_has_column($conn, 'tbl_sale_orders', 'branch_id')) {
    $sob = getRecord('SELECT branch_id FROM tbl_sale_orders WHERE id = ' . (int) $sale_order_id . ' LIMIT 1');
    $sbi = (int) ($sob['branch_id'] ?? 0);
    $hdr = (int) $hdr_mr_branch;
    if ($sbi > 0 && $hdr > 0 && $sbi !== $hdr) {
        echo json_encode(['status' => 'error', 'message' => 'Sale order belongs to another branch.']);
        exit;
    }
}

$cd_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
if ($cd_so && mysqli_num_rows($cd_so) === 0) {
    mysqli_free_result($cd_so);
    @mysqli_query($conn, "ALTER TABLE `tbl_sale_orders` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`");
} elseif ($cd_so) {
    mysqli_free_result($cd_so);
}

function auragold_sync_sale_order_department($conn, $sale_order_id, $department_id) {
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
    if (!$c || mysqli_num_rows($c) === 0) {
        if ($c) {
            mysqli_free_result($c);
        }
        return;
    }
    mysqli_free_result($c);
    $sid = (int)$sale_order_id;
    if ($department_id !== null && (int)$department_id > 0) {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET department_id = " . (int)$department_id . " WHERE id = $sid");
    } else {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET department_id = NULL WHERE id = $sid");
    }
}

function auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person) {
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'sales_person'");
    if (!$c || mysqli_num_rows($c) === 0) {
        if ($c) {
            mysqli_free_result($c);
        }
        return;
    }
    mysqli_free_result($c);
    $sid = (int)$sale_order_id;
    $sp = mysqli_real_escape_string($conn, $sales_person);
    if ($sales_person !== '') {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET sales_person = '$sp' WHERE id = $sid");
    } else {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET sales_person = NULL WHERE id = $sid");
    }
}

function auragold_material_receive_no_taken($conn, $no_esc, $branch_scope_sql = '') {
    $mr_active = auragold_doc_series_active_sql($conn, 'tbl_material_receives');
    $a = getRecord("SELECT id FROM tbl_material_receives WHERE material_receive_no = '$no_esc' $branch_scope_sql" . $mr_active . " LIMIT 1");
    if ($a) {
        return true;
    }
    $tr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_receives'");
    if ($tr && mysqli_num_rows($tr) > 0) {
        mysqli_free_result($tr);
        $rmr_active = auragold_doc_series_active_sql($conn, 'tbl_repair_material_receives');
        $b = getRecord("SELECT id FROM tbl_repair_material_receives WHERE material_receive_no = '$no_esc'" . $rmr_active . " LIMIT 1");
        return (bool)$b;
    }
    if ($tr) {
        mysqli_free_result($tr);
    }
    return false;
}

$sale_order_no = mysqli_real_escape_string($conn, $sale_order['order_no']);
$customer_name = mysqli_real_escape_string($conn, $sale_order['customer_name'] ?? '');
$order_date = !empty($sale_order['order_date']) ? mysqli_real_escape_string($conn, $sale_order['order_date']) : 'NULL';
$due_date = !empty($sale_order['due_date']) ? "'" . mysqli_real_escape_string($conn, $sale_order['due_date']) . "'" : 'NULL';
$grand_total = (float)($sale_order['grand_total'] ?? 0);

if ($jwo_id > 0) {
    $jwo = getRecord("SELECT id, sale_order_id, status, department_id, department_user_id FROM tbl_material_receives WHERE id = $jwo_id");
    if (!$jwo) {
        echo json_encode(['status' => 'error', 'message' => 'Material receive not found']);
        exit;
    }
    try {
        auragold_branch_require_document_access($conn, 'tbl_material_receives', $jwo_id);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    if ((int)$jwo['sale_order_id'] !== $sale_order_id) {
        echo json_encode(['status' => 'error', 'message' => 'Sale order mismatch']);
        exit;
    }
    $new_status = mysqli_real_escape_string($conn, $jwo_status);
    $grand_total = 0;
    foreach ($items as $item) {
        $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_total = round($grand_total, 2);
    $upd = "UPDATE tbl_material_receives SET status = '$new_status', grand_total = $grand_total, updated_at = NOW()";
    if ($department_id !== null && $department_id > 0) {
        $upd .= ", department_id = " . (int)$department_id;
    }
    $priority_esc = mysqli_real_escape_string($conn, $priority);
    $upd .= ", priority = '$priority_esc'";
    if ($department_user_id_provided) {
        if ($department_user_id !== null && $department_user_id > 0) {
            $upd .= ", department_user_id = " . (int)$department_user_id;
        } else {
            $upd .= ", department_user_id = NULL";
        }
    }
    if ($has_mr_branch && $hdr_mr_branch > 0) {
        $rb = getRecord('SELECT branch_id FROM tbl_material_receives WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
        if ($rb && (int) ($rb['branch_id'] ?? 0) <= 0) {
            $upd .= ', branch_id = ' . (int) $hdr_mr_branch;
        }
    }
    $upd .= " WHERE id = $jwo_id";
    mysqli_query($conn, $upd);
    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=mr|hid=" . (int) $jwo_id . "|%'");
    mysqli_query($conn, "DELETE FROM tbl_material_receive_items WHERE material_receive_id = $jwo_id");
    foreach ($items as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $characteristic_id = isset($item['characteristic_id']) && $item['characteristic_id'] !== '' ? (int)$item['characteristic_id'] : null;
        $barcode = mysqli_real_escape_string($conn, $item['barcode'] ?? '');
        $product_name = mysqli_real_escape_string($conn, $item['product_name'] ?? '');
        $design_no = mysqli_real_escape_string($conn, $item['design_no'] ?? '');
        $carat = mysqli_real_escape_string($conn, $item['carat'] ?? '');
        $quantity = (float)($item['quantity'] ?? 1);
        $gross_weight = (float)($item['gross_weight'] ?? 0);
        $less_weight = (float)($item['less_weight'] ?? 0);
        $purity = (float)($item['purity'] ?? 0);
        $purity_weight = (float)($item['purity_weight'] ?? 0);
        $final_weight = (float)($item['final_weight'] ?? 0);
        $net_weight = (float)($item['net_weight'] ?? 0);
        $pure_weight = (float)($item['pure_weight'] ?? 0);
        $rate = (float)($item['rate'] ?? 0);
        $making_amount = (float)($item['making_amount'] ?? 0);
        $amount = (float)($item['amount'] ?? 0);
        $tax_amount = (float)($item['tax'] ?? 0);
        $net_amount = (float)($item['net_amount'] ?? 0);
        $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
        $char_sql = $characteristic_id !== null ? $characteristic_id : 'NULL';
        $barcode_sql = $barcode !== '' ? "'$barcode'" : 'NULL';
        $design_sql = $design_no !== '' ? "'$design_no'" : 'NULL';
        $carat_sql = $carat !== '' ? "'$carat'" : 'NULL';
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_material_receive_items', $item);
        $ins_item = "INSERT INTO tbl_material_receive_items (material_receive_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at) VALUES ($jwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax{$ef_parts['values']}, 1, NOW())";
        mysqli_query($conn, $ins_item);
        $mr_line_id = (int) mysqli_insert_id($conn);
        require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
        $mrh = getRecord("SELECT material_receive_no, order_date FROM tbl_material_receives WHERE id = " . (int) $jwo_id . " LIMIT 1");
        $mr_no = trim((string) ($mrh['material_receive_no'] ?? ''));
        $mr_dt = substr(trim((string) ($mrh['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mr_dt) && !empty($sale_order['order_date'])) {
            $mr_dt = substr(trim((string) $sale_order['order_date']), 0, 10);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mr_dt)) {
            $mr_dt = date('Y-m-d');
        }
        auragold_stock_history_audit_for_document_barcode_line($conn, 'Material Receive', $mr_no, $mr_dt, 'MR', (int) $jwo_id, $mr_line_id, 'mr', array_merge($item, [
            'product_id' => $product_id,
            'product_characteristic_id' => $characteristic_id !== null ? (int) $characteristic_id : 0,
        ]));
    }
    if (strtolower($new_status) === 'completed') {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'completed', updated_at = NOW() WHERE id = $sale_order_id");
    }
    auragold_sync_sale_order_department($conn, $sale_order_id, $department_id);
    auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person_post);
    if (!empty($me_mr_payments)) {
        try {
            foreach ($me_mr_payments as $__pmr) {
                if (!is_array($__pmr)) {
                    continue;
                }
                $__mmr = auragold_metal_exchange_enrich_payment_from_issue_stock(
                    $conn,
                    auragold_payment_merge_stored_details($__pmr)
                );
                if (!auragold_payment_is_metal_exchange_inward($conn, $__mmr)) {
                    continue;
                }
                auragold_validate_metal_exchange_for_stock($conn, $__mmr);
            }
            $rmr_h = @getRecord('SELECT material_receive_no, order_date FROM tbl_material_receives WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
            $__mr_no = trim((string) ($rmr_h['material_receive_no'] ?? ''));
            $__mr_dt = substr(trim((string) ($rmr_h['order_date'] ?? '')), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mr_dt) && !empty($sale_order['order_date'])) {
                $__mr_dt = substr(trim((string) $sale_order['order_date']), 0, 10);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mr_dt)) {
                $__mr_dt = date('Y-m-d');
            }
            $___mr_me_ref = auragold_metal_exchange_document_init($conn, true, (int) $jwo_id, 'material_receive_metal_exchange');
            $me_reserved_bc = [];
            $me_reserved_stock_ids = [];
            foreach ($me_mr_payments as $pay_seq => $payment) {
                if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                    continue;
                }
                $___pm_mr = auragold_metal_exchange_enrich_payment_from_issue_stock(
                    $conn,
                    auragold_payment_merge_stored_details($payment)
                );
                auragold_post_metal_exchange_payment_to_stock(
                    $conn,
                    'material_receive_metal_exchange',
                    (int) $jwo_id,
                    $__mr_no,
                    $__mr_dt,
                    $___pm_mr,
                    auragold_metal_exchange_default_branch_id(),
                    is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                    $___mr_me_ref,
                    'Material Receive — Metal Exchange',
                    'mr_me',
                    'MR-ME-',
                    $metal_exchange_barcodes_out,
                    $me_reserved_bc,
                    $me_reserved_stock_ids
                );
                foreach ($metal_exchange_barcodes_out as $__me_bc_row) {
                    $__me_bc = trim((string) ($__me_bc_row['barcode'] ?? ''));
                    if ($__me_bc !== '' && !in_array($__me_bc, $me_reserved_bc, true)) {
                        $me_reserved_bc[] = $__me_bc;
                    }
                    $__me_sid = (int) ($__me_bc_row['stock_id'] ?? 0);
                    if ($__me_sid > 0 && !in_array($__me_sid, $me_reserved_stock_ids, true)) {
                        $me_reserved_stock_ids[] = $__me_sid;
                    }
                }
            }
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
    require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
    $rmr_apply_upd = @getRecord('SELECT material_receive_no, order_date FROM tbl_material_receives WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
    if ($rmr_apply_upd && (int) $jwo_id > 0) {
        $od_ap = substr(trim((string) ($rmr_apply_upd['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od_ap)) {
            $od_ap = date('Y-m-d');
        }
        try {
            auragold_voucher_apply_pending_diamond_stone_from_post(
                $conn,
                'material_receive',
                (int) $jwo_id,
                trim((string) ($rmr_apply_upd['material_receive_no'] ?? '')),
                $od_ap
            );
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
    require_once __DIR__ . '/../includes/auragold_notifications.php';
    $rmr = @getRecord('SELECT material_receive_no, customer_name, order_date, due_date FROM tbl_material_receives WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
    if (is_array($rmr)) {
        $dd = isset($rmr['due_date']) && $rmr['due_date'] !== null && trim((string) $rmr['due_date']) !== ''
            ? substr(trim((string) $rmr['due_date']), 0, 10) : '';
        $od = substr(trim((string) ($rmr['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
            $od = date('Y-m-d');
        }
        auragold_notify_document_saved($conn, [
            'label' => 'Material Receive',
            'verb' => 'updated',
            'number' => trim((string) ($rmr['material_receive_no'] ?? '')),
            'party' => trim((string) ($rmr['customer_name'] ?? '')),
            'doc_date' => $od,
            'due_date' => $dd,
            'ref_id' => (int) $jwo_id,
        ]);
    }
    echo json_encode(['status' => 'success', 'message' => 'Material receive updated', 'jwo_id' => $jwo_id, 'new_barcodes' => $metal_exchange_barcodes_out]);
    exit;
}

if (count($items) === 1) {
    $grand_total = 0;
    foreach ($items as $item) {
        $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_total = round($grand_total, 2);
}

$cfg_mi = function_exists('getMaterialReceiveBillSeriesConfig')
    ? getMaterialReceiveBillSeriesConfig($conn)
    : ['prefix' => 'MR-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
$material_receive_no = function_exists('getNextMaterialReceiveNo') ? getNextMaterialReceiveNo($conn) : 'MR-1';
$material_receive_no_esc = mysqli_real_escape_string($conn, $material_receive_no);
$guard_no = 0;
while (auragold_material_receive_no_taken($conn, $material_receive_no_esc, $mr_scope_sql) && $guard_no < 5000) {
    $material_receive_no = function_exists('bumpMaterialReceiveNo') ? bumpMaterialReceiveNo($conn, $material_receive_no, $cfg_mi) : ($material_receive_no . '-1');
    $material_receive_no_esc = mysqli_real_escape_string($conn, $material_receive_no);
    $guard_no++;
}
if (function_exists('auragold_release_soft_deleted_document_no')) {
    auragold_release_soft_deleted_document_no($conn, [
        ['tbl_material_receives', 'material_receive_no'],
        ['tbl_repair_material_receives', 'material_receive_no'],
    ], $material_receive_no);
}

$status_esc = mysqli_real_escape_string($conn, $jwo_status);
$ins_master = "INSERT INTO tbl_material_receives (material_receive_no, sale_order_id, sale_order_no, customer_name, order_date, due_date, grand_total, status, "
    . ($has_mr_branch ? 'branch_id, ' : '')
    . "created_at) VALUES ('$material_receive_no_esc', $sale_order_id, '$sale_order_no', '$customer_name', " . ($order_date !== 'NULL' ? "'$order_date'" : 'NULL') . ", $due_date, $grand_total, '$status_esc', "
    . ($has_mr_branch ? ((int) $hdr_mr_branch > 0 ? (int) $hdr_mr_branch : 'NULL') . ', ' : '')
    . 'NOW())';
if (!mysqli_query($conn, $ins_master)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create material receive: ' . mysqli_error($conn)]);
    exit;
}

$new_jwo_id = mysqli_insert_id($conn);

if ($department_id !== null && $department_id > 0) {
    mysqli_query($conn, "UPDATE tbl_material_receives SET department_id = " . (int)$department_id . " WHERE id = $new_jwo_id");
}
$priority_esc = mysqli_real_escape_string($conn, $priority);
mysqli_query($conn, "UPDATE tbl_material_receives SET priority = '$priority_esc' WHERE id = $new_jwo_id");
if ($department_user_id_provided) {
    if ($department_user_id !== null && $department_user_id > 0) {
        mysqli_query($conn, "UPDATE tbl_material_receives SET department_user_id = " . (int)$department_user_id . " WHERE id = $new_jwo_id");
    } else {
        mysqli_query($conn, "UPDATE tbl_material_receives SET department_user_id = NULL WHERE id = $new_jwo_id");
    }
}

foreach ($items as $item) {
    $product_id = (int)($item['product_id'] ?? 0);
    $characteristic_id = isset($item['characteristic_id']) && $item['characteristic_id'] !== '' ? (int)$item['characteristic_id'] : null;
    $barcode = mysqli_real_escape_string($conn, $item['barcode'] ?? '');
    $product_name = mysqli_real_escape_string($conn, $item['product_name'] ?? '');
    $design_no = mysqli_real_escape_string($conn, $item['design_no'] ?? '');
    $carat = mysqli_real_escape_string($conn, $item['carat'] ?? '');
    $quantity = (float)($item['quantity'] ?? 1);
    $gross_weight = (float)($item['gross_weight'] ?? 0);
    $less_weight = (float)($item['less_weight'] ?? 0);
    $purity = (float)($item['purity'] ?? 0);
    $purity_weight = (float)($item['purity_weight'] ?? 0);
    $final_weight = (float)($item['final_weight'] ?? 0);
    $net_weight = (float)($item['net_weight'] ?? 0);
    $pure_weight = (float)($item['pure_weight'] ?? 0);
    $rate = (float)($item['rate'] ?? 0);
    $making_amount = (float)($item['making_amount'] ?? 0);
    $amount = (float)($item['amount'] ?? 0);
    $tax_amount = (float)($item['tax'] ?? 0);
    $net_amount = (float)($item['net_amount'] ?? 0);
    $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);

    $char_sql = $characteristic_id !== null ? $characteristic_id : 'NULL';
    $barcode_sql = $barcode !== '' ? "'$barcode'" : 'NULL';
    $design_sql = $design_no !== '' ? "'$design_no'" : 'NULL';
    $carat_sql = $carat !== '' ? "'$carat'" : 'NULL';

    $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_material_receive_items', $item);
    $ins_item = "INSERT INTO tbl_material_receive_items (material_receive_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at) VALUES ($new_jwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax{$ef_parts['values']}, 1, NOW())";
    mysqli_query($conn, $ins_item);
    $mr_line_id = (int) mysqli_insert_id($conn);
    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
    $mr_dt_n = !empty($sale_order['order_date']) ? substr(trim((string) $sale_order['order_date']), 0, 10) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mr_dt_n)) {
        $mr_dt_n = date('Y-m-d');
    }
    auragold_stock_history_audit_for_document_barcode_line($conn, 'Material Receive', $material_receive_no, $mr_dt_n, 'MR', (int) $new_jwo_id, $mr_line_id, 'mr', array_merge($item, [
        'product_id' => $product_id,
        'product_characteristic_id' => $characteristic_id !== null ? (int) $characteristic_id : 0,
    ]));
}

auragold_sync_sale_order_department($conn, $sale_order_id, $department_id);
auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person_post);
mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'processing' WHERE id = $sale_order_id");

if (!empty($me_mr_payments)) {
    try {
        foreach ($me_mr_payments as $__pmr_n) {
            if (!is_array($__pmr_n)) {
                continue;
            }
            $__mmr_n = auragold_metal_exchange_enrich_payment_from_issue_stock(
                $conn,
                auragold_payment_merge_stored_details($__pmr_n)
            );
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mmr_n)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mmr_n);
        }
        $rmr_n = @getRecord('SELECT material_receive_no, order_date FROM tbl_material_receives WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
        $__mr_no_n = trim((string) ($rmr_n['material_receive_no'] ?? $material_receive_no));
        $__mr_dt_n = substr(trim((string) ($rmr_n['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mr_dt_n) && !empty($sale_order['order_date'])) {
            $__mr_dt_n = substr(trim((string) $sale_order['order_date']), 0, 10);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mr_dt_n)) {
            $__mr_dt_n = date('Y-m-d');
        }
        $___mr_new_me_ref = auragold_metal_exchange_document_init($conn, false, (int) $new_jwo_id, 'material_receive_metal_exchange');
        $me_reserved_bc_n = [];
        $me_reserved_stock_ids_n = [];
        foreach ($me_mr_payments as $pay_seq => $payment) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                continue;
            }
            $___pm_mr_n = auragold_metal_exchange_enrich_payment_from_issue_stock(
                $conn,
                auragold_payment_merge_stored_details($payment)
            );
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'material_receive_metal_exchange',
                (int) $new_jwo_id,
                $__mr_no_n,
                $__mr_dt_n,
                $___pm_mr_n,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___mr_new_me_ref,
                'Material Receive — Metal Exchange',
                'mr_me',
                'MR-ME-',
                $metal_exchange_barcodes_out,
                $me_reserved_bc_n,
                $me_reserved_stock_ids_n
            );
            foreach ($metal_exchange_barcodes_out as $__me_bc_row_n) {
                $__me_bc_n = trim((string) ($__me_bc_row_n['barcode'] ?? ''));
                if ($__me_bc_n !== '' && !in_array($__me_bc_n, $me_reserved_bc_n, true)) {
                    $me_reserved_bc_n[] = $__me_bc_n;
                }
                $__me_sid_n = (int) ($__me_bc_row_n['stock_id'] ?? 0);
                if ($__me_sid_n > 0 && !in_array($__me_sid_n, $me_reserved_stock_ids_n, true)) {
                    $me_reserved_stock_ids_n[] = $__me_sid_n;
                }
            }
        }
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
$rmr_apply_ins = @getRecord('SELECT material_receive_no, order_date FROM tbl_material_receives WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
if ($rmr_apply_ins && (int) $new_jwo_id > 0) {
    $od_ins = substr(trim((string) ($rmr_apply_ins['order_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od_ins)) {
        $od_ins = date('Y-m-d');
    }
    try {
        auragold_voucher_apply_pending_diamond_stone_from_post(
            $conn,
            'material_receive',
            (int) $new_jwo_id,
            trim((string) ($rmr_apply_ins['material_receive_no'] ?? '')),
            $od_ins
        );
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/../includes/auragold_notifications.php';
$rmr_new = @getRecord('SELECT material_receive_no, customer_name, order_date, due_date FROM tbl_material_receives WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
if (is_array($rmr_new)) {
    $dd = isset($rmr_new['due_date']) && $rmr_new['due_date'] !== null && trim((string) $rmr_new['due_date']) !== ''
        ? substr(trim((string) $rmr_new['due_date']), 0, 10) : '';
    $od = substr(trim((string) ($rmr_new['order_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
        $od = date('Y-m-d');
    }
    auragold_notify_document_saved($conn, [
        'label' => 'Material Receive',
        'verb' => 'created',
        'number' => trim((string) ($rmr_new['material_receive_no'] ?? '')),
        'party' => trim((string) ($rmr_new['customer_name'] ?? '')),
        'doc_date' => $od,
        'due_date' => $dd,
        'ref_id' => (int) $new_jwo_id,
    ]);
}

echo json_encode([
    'status' => 'success',
    'message' => 'Material receive created',
    'jwo_id' => $new_jwo_id,
    'job_work_no' => $material_receive_no,
    'jobwork_no' => $material_receive_no,
    'material_receive_no' => $material_receive_no,
    'sale_order_id' => $sale_order_id,
    'new_barcodes' => $metal_exchange_barcodes_out,
]);
