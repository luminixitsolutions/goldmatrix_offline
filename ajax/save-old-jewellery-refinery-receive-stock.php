<?php
/**
 * Stock-in from Old Jewellery → Refine → Received: one JWO line into tbl_old_jewelry_stock
 * and mirrored into tbl_stock (main inventory), same rules as scrap stock-in.
 * Barcode must be unique in tbl_old_jewelry_stock (trim match).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

$tst = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
if (!$tst || mysqli_num_rows($tst) === 0) {
    if ($tst) {
        mysqli_free_result($tst);
    }
    echo json_encode(['ok' => false, 'message' => 'Old Jewellery stock table is not installed.']);
    exit;
}
mysqli_free_result($tst);

$refine_needle = 'Job work / refinery from Old Jewellery scrap';

$joi_id = isset($_POST['jobwork_order_item_id']) ? (int) $_POST['jobwork_order_item_id'] : 0;
$jwo_id = isset($_POST['jobwork_order_id']) ? (int) $_POST['jobwork_order_id'] : 0;
$barcode = isset($_POST['barcode']) ? trim((string) $_POST['barcode']) : '';

if ($joi_id <= 0 || $jwo_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid job work line.']);
    exit;
}
if ($barcode === '') {
    echo json_encode(['ok' => false, 'message' => 'Barcode is required.']);
    exit;
}

$bc_esc = mysqli_real_escape_string($conn, $barcode);
$dup = getRecord("SELECT id FROM tbl_old_jewelry_stock WHERE TRIM(barcode) = '" . $bc_esc . "' LIMIT 1");
if ($dup && !empty($dup['id'])) {
    echo json_encode(['ok' => false, 'message' => 'This barcode already exists in Old Jewellery stock. Use a different number.']);
    exit;
}

$t_main_stock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
if ($t_main_stock && mysqli_num_rows($t_main_stock) > 0) {
    mysqli_free_result($t_main_stock);
    $dup_main = getRecord("SELECT id FROM tbl_stock WHERE TRIM(barcode) = '" . $bc_esc . "' LIMIT 1");
    if ($dup_main && !empty($dup_main['id'])) {
        echo json_encode(['ok' => false, 'message' => 'This barcode already exists in main stock (tbl_stock). Use a different number.']);
        exit;
    }
} elseif ($t_main_stock) {
    mysqli_free_result($t_main_stock);
}

$jwi = getRecord('SELECT id, invoice_no, sale_order_id FROM tbl_jobwork_invoices WHERE jobwork_order_id = ' . $jwo_id . ' LIMIT 1');
if (!$jwi || empty($jwi['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Save the jobwork invoice first before receiving to stock.']);
    exit;
}

$sql_ji = 'SELECT ji.*, j.sale_order_id, j.jobwork_no, j.customer_name '
    . 'FROM tbl_jobwork_order_items ji '
    . 'INNER JOIN tbl_jobwork_orders j ON j.id = ji.jobwork_order_id '
    . 'WHERE ji.id = ' . $joi_id . ' AND ji.jobwork_order_id = ' . $jwo_id . ' LIMIT 1';
$ji = getRecord($sql_ji);
if (!$ji) {
    echo json_encode(['ok' => false, 'message' => 'Job work order line not found.']);
    exit;
}

$sale_order_id = (int) ($ji['sale_order_id'] ?? 0);
if ($sale_order_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Sale order link missing for this job work order.']);
    exit;
}

$so = getRecord('SELECT id, comment, against_of FROM tbl_sale_orders WHERE id = ' . $sale_order_id . ' LIMIT 1');
if (!$so || stripos((string) ($so['comment'] ?? ''), $refine_needle) === false) {
    echo json_encode(['ok' => false, 'message' => 'This order is not an Old Jewellery refinery job work.']);
    exit;
}

$needle_esc = mysqli_real_escape_string($conn, 'auragold_oj_refine|joi_id=' . $joi_id . '|');
$already = getRecord("SELECT id FROM tbl_old_jewelry_stock WHERE comment LIKE '%" . $needle_esc . "%' LIMIT 1");
if ($already && !empty($already['id'])) {
    echo json_encode(['ok' => false, 'message' => 'This line is already received into Old Jewellery stock.']);
    exit;
}

$jwi_id = (int) ($jwi['id'] ?? 0);
$invoice_no = trim((string) ($jwi['invoice_no'] ?? ''));
if ($invoice_no === '') {
    $invoice_no = 'JWI-' . $jwi_id;
}
$inv_esc = mysqli_real_escape_string($conn, $invoice_no);

$against_of = trim((string) ($so['against_of'] ?? ''));
$against_sql = $against_of !== '' ? "'" . mysqli_real_escape_string($conn, $against_of) . "'" : 'NULL';

$product_name = trim((string) ($ji['product_name'] ?? ''));
$product_esc = $product_name !== '' ? "'" . mysqli_real_escape_string($conn, $product_name) . "'" : 'NULL';

$gross = (float) ($ji['gross_weight'] ?? 0);
$final = (float) ($ji['final_weight'] ?? 0);
$net = (float) ($ji['net_weight'] ?? 0);
$less = (float) ($ji['less_weight'] ?? 0);
$purity = (float) ($ji['purity'] ?? 0);
$qty = (float) ($ji['quantity'] ?? 1);
if ($qty <= 0) {
    $qty = 1;
}
$rate = (float) ($ji['rate'] ?? 0);
$amount = (float) ($ji['net_amount'] ?? 0);
if ($amount <= 0) {
    $amount = (float) ($ji['amount'] ?? 0);
}

$metal_disp = '';
$branch_stock = 0;
$metal_id_stock = 0;
$char_id = isset($ji['product_characteristic_id']) ? (int) $ji['product_characteristic_id'] : 0;
if ($char_id > 0) {
    $ch = getRecord('SELECT branch_id, metal_id FROM tbl_product_characteristics WHERE id = ' . $char_id . ' AND status = 1 LIMIT 1');
    if ($ch) {
        $branch_stock = (int) ($ch['branch_id'] ?? 0);
        $metal_id_stock = (int) ($ch['metal_id'] ?? 0);
        if ($metal_id_stock > 0) {
            $mr = getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . $metal_id_stock . ' LIMIT 1');
            if ($mr && !empty($mr['display_name'])) {
                $metal_disp = mysqli_real_escape_string($conn, trim((string) $mr['display_name']));
            }
        }
    }
}

$against_voucher_esc = mysqli_real_escape_string($conn, 'Jobwork Invoice');
$comment = 'auragold_oj_refine|jwi_id=' . $jwi_id . '|joi_id=' . $joi_id . '|';
$comment_esc = mysqli_real_escape_string($conn, $comment);

$metal_sql = $metal_disp !== '' ? "'" . $metal_disp . "'" : "''";
$ins = "INSERT INTO tbl_old_jewelry_stock (
    source_invoice_id, source_item_id, barcode, invoice_no, voucher_type, metal, product, location,
    final_wt, gross_wt, purity, branch_id, less_wt, net_wt, amount, category, against_invoice_no, against_voucher,
    group_name, comment, quantity, rate
) VALUES (
    $sale_order_id, $joi_id, '$bc_esc', '$inv_esc', 'Refinery - Jobwork', $metal_sql, $product_esc, NULL,
    $final, $gross, $purity, " . ($branch_stock > 0 ? $branch_stock : 'NULL') . ", $less, $net, $amount, NULL, $against_sql, '$against_voucher_esc',
    '', '$comment_esc', $qty, $rate
)";

require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_mirror_inventory.php';

$product_id_ji = (int) ($ji['product_id'] ?? 0);
$item_fallback = ['description' => $product_name];

mysqli_begin_transaction($conn);
if (!mysqli_query($conn, $ins)) {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => 'Could not save stock row: ' . mysqli_error($conn)]);
    exit;
}
$oj_stock_new_id = (int) mysqli_insert_id($conn);

$mirrored = auragold_oj_scrap_mirror_tbl_stock_line(
    $conn,
    $product_id_ji,
    $char_id,
    $metal_id_stock,
    $branch_stock,
    $product_name,
    $item_fallback,
    $barcode,
    $gross,
    $net,
    $final,
    $purity,
    $qty,
    $rate,
    $amount
);
if (!$mirrored) {
    mysqli_rollback($conn);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not add to main inventory (tbl_stock). Set Product on the job work line (or add an Items master row matching the product name), then try again.',
    ]);
    exit;
}

mysqli_commit($conn);

echo json_encode([
    'ok' => true,
    'message' => 'Added to Old Jewellery stock and main inventory.',
    'stock_id' => $oj_stock_new_id,
]);
