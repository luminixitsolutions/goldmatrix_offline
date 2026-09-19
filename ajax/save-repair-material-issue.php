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

$tbl_master = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issues'");
$tbl_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issue_items'");
if (!$tbl_master || mysqli_num_rows($tbl_master) === 0 || !$tbl_items || mysqli_num_rows($tbl_items) === 0) {
    if ($tbl_master) {
        mysqli_free_result($tbl_master);
    }
    if ($tbl_items) {
        mysqli_free_result($tbl_items);
    }
    echo json_encode(['status' => 'error', 'message' => 'Repair material issue tables not found. Please run admin/sql/create_tbl_repair_material_issues.sql']);
    exit;
}
mysqli_free_result($tbl_master);
mysqli_free_result($tbl_items);

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

function auragold_repair_mi_no_taken($conn, $no_esc) {
    $a = getRecord("SELECT id FROM tbl_material_issues WHERE material_issue_no = '$no_esc' LIMIT 1");
    if ($a) {
        return true;
    }
    $b = getRecord("SELECT id FROM tbl_repair_material_issues WHERE material_issue_no = '$no_esc' LIMIT 1");
    return (bool)$b;
}

if ($rjwo_id > 0) {
    $rjwo = getRecord("SELECT id, repair_order_id, material_issue_no FROM tbl_repair_material_issues WHERE id = $rjwo_id");
    if (!$rjwo || (int)$rjwo['repair_order_id'] !== $repair_order_id) {
        echo json_encode(['status' => 'error', 'message' => 'Repair material issue not found or repair order mismatch']);
        exit;
    }
    $material_issue_no = $rjwo['material_issue_no'];
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "UPDATE tbl_repair_material_issues SET grand_total = $grand_total, updated_at = NOW() WHERE id = $rjwo_id");
        mysqli_query($conn, "DELETE FROM tbl_repair_material_issue_items WHERE repair_material_issue_id = $rjwo_id");
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
            $ins_item = "INSERT INTO tbl_repair_material_issue_items (repair_material_issue_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, description, status, created_at) VALUES ($rjwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax, $desc_sql, 1, NOW())";
            mysqli_query($conn, $ins_item);
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'message' => 'Repair Material Issue updated successfully.',
        'repair_jwo_id' => $rjwo_id,
        'jobwork_no' => $material_issue_no,
        'material_issue_no' => $material_issue_no,
        'repair_order_id' => $repair_order_id,
        'redirect' => 'repair-order-process.php?saved=1&jobwork_no=' . urlencode($material_issue_no)
    ]);
    exit;
}

$existing = getRecord("SELECT id, material_issue_no FROM tbl_repair_material_issues WHERE repair_order_id = $repair_order_id");
if ($existing) {
    echo json_encode([
        'status' => 'error',
        'message' => 'A Repair Material Issue already exists for this repair order.',
        'repair_jwo_id' => (int)$existing['id'],
        'jobwork_no' => $existing['material_issue_no']
    ]);
    exit;
}

$repair_order_no = mysqli_real_escape_string($conn, $repair_order['order_no']);
$customer_name = mysqli_real_escape_string($conn, $repair_order['customer_name'] ?? '');
$order_date = !empty($repair_order['order_date']) ? mysqli_real_escape_string($conn, $repair_order['order_date']) : 'NULL';
$due_date = !empty($repair_order['due_date']) ? "'" . mysqli_real_escape_string($conn, $repair_order['due_date']) . "'" : 'NULL';

$cfg_mi = function_exists('getMaterialIssueBillSeriesConfig')
    ? getMaterialIssueBillSeriesConfig($conn)
    : ['prefix' => 'MI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
$material_issue_no = function_exists('getNextMaterialIssueNo') ? getNextMaterialIssueNo($conn) : 'MI-1';
$material_issue_no_esc = mysqli_real_escape_string($conn, $material_issue_no);
$guard_no = 0;
while (auragold_repair_mi_no_taken($conn, $material_issue_no_esc) && $guard_no < 5000) {
    $material_issue_no = function_exists('bumpMaterialIssueNo') ? bumpMaterialIssueNo($conn, $material_issue_no, $cfg_mi) : ($material_issue_no . '-1');
    $material_issue_no_esc = mysqli_real_escape_string($conn, $material_issue_no);
    $guard_no++;
}

mysqli_begin_transaction($conn);
try {
    $ins_master = "INSERT INTO tbl_repair_material_issues (material_issue_no, repair_order_id, repair_order_no, customer_name, order_date, due_date, grand_total, status, created_at) VALUES ('$material_issue_no_esc', $repair_order_id, '$repair_order_no', '$customer_name', " . ($order_date !== 'NULL' ? "'$order_date'" : 'NULL') . ", $due_date, $grand_total, 'draft', NOW())";
    if (!mysqli_query($conn, $ins_master)) {
        throw new Exception('Failed to create repair material issue: ' . mysqli_error($conn));
    }
    $new_id = mysqli_insert_id($conn);

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

        $ins_item = "INSERT INTO tbl_repair_material_issue_items (repair_material_issue_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, description, status, created_at) VALUES ($new_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax, $desc_sql, 1, NOW())";
        if (!mysqli_query($conn, $ins_item)) {
            throw new Exception('Failed to insert item: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Repair Material Issue saved successfully.',
    'repair_jwo_id' => $new_id,
    'jobwork_no' => $material_issue_no,
    'material_issue_no' => $material_issue_no,
    'repair_order_id' => $repair_order_id,
    'redirect' => 'repair-order-process.php?saved=1&jobwork_no=' . urlencode($material_issue_no)
]);
