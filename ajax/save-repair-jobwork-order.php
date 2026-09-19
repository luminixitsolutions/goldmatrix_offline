<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$repair_order_id = isset($_POST['repair_order_id']) ? (int)$_POST['repair_order_id'] : 0;
$rjwo_id = isset($_POST['rjwo_id']) ? (int)$_POST['rjwo_id'] : 0;
$items_json = isset($_POST['items']) ? $_POST['items'] : '';
$items = [];
if (is_string($items_json) && $items_json !== '') {
    $items = json_decode($items_json, true);
}
if (!is_array($items)) {
    $items = [];
}

if ($repair_order_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Repair order ID required']);
    exit;
}

$tbl_master = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_jobwork_orders'");
$tbl_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_jobwork_order_items'");
if (!$tbl_master || mysqli_num_rows($tbl_master) === 0 || !$tbl_items || mysqli_num_rows($tbl_items) === 0) {
    if ($tbl_master) mysqli_free_result($tbl_master);
    if ($tbl_items) mysqli_free_result($tbl_items);
    echo json_encode(['status' => 'error', 'message' => 'Repair job work order tables not found. Please run admin/sql/create_tbl_repair_jobwork_orders.sql']);
    exit;
}
mysqli_free_result($tbl_master);
mysqli_free_result($tbl_items);

/**
 * Persist department / job worker (Name) on RJWO — columns added on first save if missing.
 */
function auragold_rjwo_ensure_dept_columns(mysqli $conn): void
{
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_jobwork_orders LIKE 'department_id'");
    if (!$c || mysqli_num_rows($c) === 0) {
        if ($c) {
            mysqli_free_result($c);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_repair_jobwork_orders ADD COLUMN department_id INT NULL DEFAULT NULL AFTER status');
    } elseif ($c) {
        mysqli_free_result($c);
    }
    $c2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_jobwork_orders LIKE 'department_user_id'");
    if (!$c2 || mysqli_num_rows($c2) === 0) {
        if ($c2) {
            mysqli_free_result($c2);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_repair_jobwork_orders ADD COLUMN department_user_id INT NULL DEFAULT NULL AFTER department_id');
    } elseif ($c2) {
        mysqli_free_result($c2);
    }
    $c3 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_jobwork_orders LIKE 'priority'");
    if (!$c3 || mysqli_num_rows($c3) === 0) {
        if ($c3) {
            mysqli_free_result($c3);
        }
        @mysqli_query($conn, "ALTER TABLE tbl_repair_jobwork_orders ADD COLUMN priority VARCHAR(30) NULL DEFAULT NULL AFTER department_user_id");
    } elseif ($c3) {
        mysqli_free_result($c3);
    }
}

/**
 * Keep repair order header status in sync with the Repair Job Work Order master (same idea as save-jobwork-order.php updating tbl_sale_orders).
 */
function auragold_sync_repair_order_header_status_from_rjwo(mysqli $conn, int $repair_order_id, int $rjwo_id): void
{
    $rid = (int) $repair_order_id;
    $jid = (int) $rjwo_id;
    if ($rid < 1 || $jid < 1 || !function_exists('getRecord')) {
        return;
    }
    $row = getRecord('SELECT LOWER(TRIM(IFNULL(status, \'\'))) AS s FROM tbl_repair_jobwork_orders WHERE id = ' . $jid . ' LIMIT 1');
    if (!$row || !isset($row['s'])) {
        return;
    }
    $slug = (string) $row['s'];
    if ($slug === '') {
        return;
    }
    if ($slug === 'not initiate') {
        $slug = 'draft';
    }
    $esc = mysqli_real_escape_string($conn, $slug);
    mysqli_query($conn, 'UPDATE tbl_repair_orders SET status = \'' . $esc . '\' WHERE id = ' . $rid);
}

auragold_rjwo_ensure_dept_columns($conn);

$map_chk_req = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
$has_department_user_map = ($map_chk_req && mysqli_num_rows($map_chk_req) > 0);
if ($map_chk_req) {
    mysqli_free_result($map_chk_req);
}
$department_user_id_provided = isset($_POST['department_user_id']);
$department_user_id = null;
if ($department_user_id_provided) {
    $v_du = trim((string)($_POST['department_user_id'] ?? ''));
    $department_user_id = ($v_du !== '' && (int)$v_du > 0) ? (int)$v_du : null;
}
if ($has_department_user_map) {
    if (!$department_user_id_provided || $department_user_id === null || (int)$department_user_id < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Name is required']);
        exit;
    }
}

$department_id = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : 0;
$priority = isset($_POST['priority']) ? trim((string) $_POST['priority']) : '';
$jwo_status = isset($_POST['jwo_status']) ? trim((string) $_POST['jwo_status']) : 'Processing';

$dept_sql = $department_id > 0 ? (string) $department_id : 'NULL';
$user_sql = ($department_user_id !== null && (int)$department_user_id > 0) ? (string)(int)$department_user_id : 'NULL';
$pri_esc = mysqli_real_escape_string($conn, $priority);
$pri_sql = ($priority !== '') ? "'$pri_esc'" : 'NULL';
$status_sql = '';
if ($jwo_status !== '') {
    $st_esc = mysqli_real_escape_string($conn, $jwo_status);
    $status_sql = ", status = '$st_esc'";
}

$repair_order = getRecord("SELECT id, order_no, customer_name, order_date, due_date, grand_total, status FROM tbl_repair_orders WHERE id = $repair_order_id");
if (!$repair_order) {
    echo json_encode(['status' => 'error', 'message' => 'Repair order not found']);
    exit;
}

$grand_total = 0;
foreach ($items as $item) {
    $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
}
$grand_total = round($grand_total, 2);

// Update existing RJWO (edit mode)
if ($rjwo_id > 0) {
    $rjwo = getRecord("SELECT id, repair_order_id, jobwork_no FROM tbl_repair_jobwork_orders WHERE id = $rjwo_id");
    if (!$rjwo || (int)$rjwo['repair_order_id'] !== $repair_order_id) {
        echo json_encode(['status' => 'error', 'message' => 'Repair job work order not found or repair order mismatch']);
        exit;
    }
    $jobwork_no = $rjwo['jobwork_no'];
    mysqli_begin_transaction($conn);
    try {
        $upd_rjwo = "UPDATE tbl_repair_jobwork_orders SET grand_total = $grand_total, department_user_id = $user_sql, priority = $pri_sql" . $status_sql;
        if (isset($_POST['department_id']) && trim((string)$_POST['department_id']) !== '' && (int)$_POST['department_id'] > 0) {
            $upd_rjwo .= ', department_id = ' . (int)$_POST['department_id'];
        }
        $upd_rjwo .= ', updated_at = NOW() WHERE id = ' . (int) $rjwo_id;
        mysqli_query($conn, $upd_rjwo);
        mysqli_query($conn, "DELETE FROM tbl_repair_jobwork_order_items WHERE repair_jobwork_order_id = $rjwo_id");
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
            $tax_amount = (float)($item['tax'] ?? $item['tax_amount'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
            $description = mysqli_real_escape_string($conn, $item['description'] ?? '');
            $char_sql = $characteristic_id !== null ? $characteristic_id : 'NULL';
            $barcode_sql = $barcode !== '' ? "'$barcode'" : 'NULL';
            $design_sql = $design_no !== '' ? "'$design_no'" : 'NULL';
            $carat_sql = $carat !== '' ? "'$carat'" : 'NULL';
            $desc_sql = $description !== '' ? "'$description'" : 'NULL';
            $ins_item = "INSERT INTO tbl_repair_jobwork_order_items (repair_jobwork_order_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, description, status, created_at) VALUES ($rjwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax, $desc_sql, 1, NOW())";
            mysqli_query($conn, $ins_item);
        }
        mysqli_commit($conn);
        auragold_sync_repair_order_header_status_from_rjwo($conn, $repair_order_id, (int) $rjwo_id);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'message' => 'Repair Job Work Order updated successfully.',
        'repair_jwo_id' => $rjwo_id,
        'jobwork_no' => $jobwork_no,
        'repair_order_id' => $repair_order_id,
        'redirect' => 'repair-order-process.php?saved=1&jobwork_no=' . urlencode($jobwork_no)
    ]);
    exit;
}

// Create new RJWO: do not allow duplicate for same repair_order_id
$existing = getRecord("SELECT id, jobwork_no FROM tbl_repair_jobwork_orders WHERE repair_order_id = $repair_order_id");
if ($existing) {
    echo json_encode([
        'status' => 'error',
        'message' => 'A Repair Job Work Order already exists for this repair order.',
        'repair_jwo_id' => (int)$existing['id'],
        'jobwork_no' => $existing['jobwork_no']
    ]);
    exit;
}

$repair_order_no = mysqli_real_escape_string($conn, $repair_order['order_no']);
$customer_name = mysqli_real_escape_string($conn, $repair_order['customer_name'] ?? '');
$order_date = !empty($repair_order['order_date']) ? mysqli_real_escape_string($conn, $repair_order['order_date']) : 'NULL';
$due_date = !empty($repair_order['due_date']) ? "'" . mysqli_real_escape_string($conn, $repair_order['due_date']) . "'" : 'NULL';

$status_ins_esc = mysqli_real_escape_string($conn, $jwo_status);

mysqli_begin_transaction($conn);
try {
    $ins_master = "INSERT INTO tbl_repair_jobwork_orders (jobwork_no, repair_order_id, repair_order_no, customer_name, order_date, due_date, grand_total, status, department_id, department_user_id, priority, created_at) VALUES ('', $repair_order_id, '$repair_order_no', '$customer_name', " . ($order_date !== 'NULL' ? "'$order_date'" : 'NULL') . ", $due_date, $grand_total, '$status_ins_esc', $dept_sql, $user_sql, $pri_sql, NOW())";
    if (!mysqli_query($conn, $ins_master)) {
        throw new Exception('Failed to create repair job work order: ' . mysqli_error($conn));
    }
    $new_id = mysqli_insert_id($conn);
    $jobwork_no = 'RJWO-' . $new_id;
    if (!mysqli_query($conn, "UPDATE tbl_repair_jobwork_orders SET jobwork_no = '$jobwork_no' WHERE id = $new_id")) {
        throw new Exception('Failed to set jobwork_no: ' . mysqli_error($conn));
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
        $tax_amount = (float)($item['tax'] ?? $item['tax_amount'] ?? 0);
        $net_amount = (float)($item['net_amount'] ?? 0);
        $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
        $description = mysqli_real_escape_string($conn, $item['description'] ?? '');

        $char_sql = $characteristic_id !== null ? $characteristic_id : 'NULL';
        $barcode_sql = $barcode !== '' ? "'$barcode'" : 'NULL';
        $design_sql = $design_no !== '' ? "'$design_no'" : 'NULL';
        $carat_sql = $carat !== '' ? "'$carat'" : 'NULL';
        $desc_sql = $description !== '' ? "'$description'" : 'NULL';

        $ins_item = "INSERT INTO tbl_repair_jobwork_order_items (repair_jobwork_order_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, description, status, created_at) VALUES ($new_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax, $desc_sql, 1, NOW())";
        if (!mysqli_query($conn, $ins_item)) {
            throw new Exception('Failed to insert item: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    auragold_sync_repair_order_header_status_from_rjwo($conn, $repair_order_id, (int) $new_id);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Repair Job Work Order saved successfully.',
    'repair_jwo_id' => $new_id,
    'jobwork_no' => $jobwork_no,
    'repair_order_id' => $repair_order_id,
    'redirect' => 'repair-order-process.php?saved=1&jobwork_no=' . urlencode($jobwork_no)
]);
