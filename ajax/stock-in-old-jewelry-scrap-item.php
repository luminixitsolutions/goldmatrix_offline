<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_balance.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$source = isset($_POST['source']) ? trim($_POST['source']) : 'scrap';
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
$barcode = isset($_POST['barcode']) ? trim((string)$_POST['barcode']) : '';
$quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 1;
$product = isset($_POST['product']) ? esc($_POST['product']) : '';
$product_raw = isset($_POST['product']) ? trim((string) $_POST['product']) : '';
$category = isset($_POST['category']) ? esc($_POST['category']) : '';
$group_name = isset($_POST['group_name']) ? esc($_POST['group_name']) : '';
$comment = isset($_POST['comment']) ? esc($_POST['comment']) : '';
$gross_wt = isset($_POST['gross_wt']) ? (float)$_POST['gross_wt'] : 0;
$final_wt = isset($_POST['final_wt']) ? (float)$_POST['final_wt'] : 0;
$net_wt = isset($_POST['net_wt']) ? (float)$_POST['net_wt'] : 0;
$purity = isset($_POST['purity']) ? (float)$_POST['purity'] : 0;
$rate = isset($_POST['rate']) ? (float)$_POST['rate'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$less_wt = isset($_POST['less_wt']) ? (float)$_POST['less_wt'] : 0;
$metal = isset($_POST['metal']) ? esc($_POST['metal']) : '';
$location = isset($_POST['location']) ? esc($_POST['location']) : '';
$against_invoice_no = isset($_POST['against_invoice_no']) ? esc($_POST['against_invoice_no']) : '';
$against_voucher = isset($_POST['against_voucher']) ? esc($_POST['against_voucher']) : '';
$resolved_product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$resolved_pc_id = isset($_POST['product_characteristic_id']) ? (int) $_POST['product_characteristic_id'] : 0;
$resolved_metal_id = isset($_POST['metal_id']) ? (int) $_POST['metal_id'] : 0;

if ($item_id <= 0 || $invoice_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid item or invoice ID']);
    exit;
}

$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
if (!$t || mysqli_num_rows($t) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please run SQL: admin/sql/create_old_jewelry_stock_table.sql']);
    exit;
}

$invoice_no = '';
$against_of = '';
$voucher_type = 'Old Jewelry - Scrap';

if ($source === 'purchase') {
    $row = getRecord("
        SELECT pii.*, pi.invoice_no, pi.against_of
        FROM tbl_purchase_invoice_items pii
        INNER JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE pii.id = $item_id AND pii.invoice_id = $invoice_id AND IFNULL(pii.active, 1) = 1
        LIMIT 1
    ");
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Purchase item not found']);
        exit;
    }
    $scrap_pay = getRecord("
        SELECT id FROM tbl_purchase_invoice_payments
        WHERE invoice_id = $invoice_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1
        LIMIT 1
    ");
    if (!$scrap_pay) {
        echo json_encode(['status' => 'error', 'message' => 'No scrap payment on this purchase invoice']);
        exit;
    }
    $invoice_no = $row['invoice_no'] ?? '';
    $against_of = $row['against_of'] ?? '';
    $item = [
        'barcode' => $row['barcode'] ?? '',
        'description' => $row['product_name'] ?? '',
        'gross_wt' => (float)($row['gross_weight'] ?? 0),
        'final_wt' => (float)($row['final_weight'] ?? 0),
        'net_wt' => (float)($row['net_weight'] ?? 0),
        'pure_wt' => (float)($row['purity_weight'] ?? 0),
        'purity' => (float)($row['purity'] ?? 0),
        'rate' => (float)($row['rate'] ?? 0),
        'amount' => (float)($row['amount'] ?? 0),
        'quantity' => (float)($row['quantity'] ?? 1),
        'less_wt' => (float)($row['less_weight'] ?? 0),
    ];
    $voucher_type = 'Purchase Invoice - Scrap';
    $resolved_product_id = (int) ($row['product_id'] ?? 0);
    $resolved_pc_id = (int) ($row['product_characteristic_id'] ?? 0);
    if ($resolved_metal_id <= 0 && $resolved_pc_id > 0) {
        $pc_m = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = $resolved_pc_id LIMIT 1");
        if ($pc_m) {
            $resolved_metal_id = (int) ($pc_m['metal_id'] ?? 0);
        }
    }
    $invoice_date_for_sj = date('Y-m-d');
    $pi_d = getRecord("SELECT invoice_date FROM tbl_purchase_invoices WHERE id = $invoice_id LIMIT 1");
    if ($pi_d && !empty($pi_d['invoice_date'])) {
        $invoice_date_for_sj = substr((string) $pi_d['invoice_date'], 0, 10);
    }
} else {
    $item = getRecord("SELECT * FROM tbl_old_jewelry_scrap_invoice_items WHERE id = $item_id AND invoice_id = $invoice_id AND status = 1");
    if (!$item) {
        echo json_encode(['status' => 'error', 'message' => 'Item not found']);
        exit;
    }
    $inv = getRecord("SELECT invoice_no, against_of, invoice_date FROM tbl_old_jewelry_scrap_invoices WHERE id = $invoice_id");
    $invoice_no = $inv ? ($inv['invoice_no'] ?? '') : '';
    $against_of = $inv ? ($inv['against_of'] ?? '') : '';
    $invoice_date_for_sj = date('Y-m-d');
    if ($inv && !empty($inv['invoice_date'])) {
        $invoice_date_for_sj = substr((string) $inv['invoice_date'], 0, 10);
    }
    if ($resolved_metal_id <= 0) {
        $resolved_metal_id = (int) ($item['metal_id'] ?? 0);
    }
}

// When modal sends empty body (direct POST), fill from DB row
if ($barcode === '' && $product === '' && $gross_wt == 0 && $final_wt == 0) {
    $barcode = $item['barcode'] ?? '';
    $product = esc($item['description'] ?? '');
    $gross_wt = (float)($item['gross_wt'] ?? 0);
    $final_wt = (float)($item['final_wt'] ?? 0);
    $net_wt = (float)($item['net_wt'] ?? 0);
    $purity = (float)($item['purity'] ?? 0);
    $rate = (float)($item['rate'] ?? 0);
    $amount = (float)($item['amount'] ?? 0);
    $quantity = (float)($item['quantity'] ?? 1);
    $less_wt = (float)($item['less_wt'] ?? 0);
}

if (empty($against_invoice_no)) {
    $against_invoice_no = esc($against_of);
}

/**
 * For scrap lines, net/final/less/amount on the movement must match gross movement vs line gross (POST may send full-line weights).
 */
if ($source !== 'purchase') {
    $orig_g_scrap = (float) ($item['gross_wt'] ?? 0);
    if ($orig_g_scrap > 0.00001 && $gross_wt > 0.00001) {
        $sc_mov = $gross_wt / $orig_g_scrap;
        $final_wt = (float) ($item['final_wt'] ?? 0) * $sc_mov;
        $net_wt = (float) ($item['net_wt'] ?? 0) * $sc_mov;
        $less_wt = (float) ($item['less_wt'] ?? 0) * $sc_mov;
        $net_amt_item = (float) ($item['net_amt'] ?? 0);
        $amt_item = (float) ($item['amount'] ?? 0);
        $amount = ($net_amt_item > 0 ? $net_amt_item : $amt_item) * $sc_mov;
    }
}

require_once __DIR__ . '/../includes/next_product_stock_barcode.php';
require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_mirror_inventory.php';

$invoice_no_esc = esc($invoice_no);
$voucher_esc = esc($voucher_type);

$lines_batch_raw = isset($_POST['lines_json']) ? trim((string) $_POST['lines_json']) : '';
$lines_batch = null;
if ($lines_batch_raw !== '') {
    $lines_batch = json_decode($lines_batch_raw, true);
    if (!is_array($lines_batch)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid lines data']);
        exit;
    }
}

if ($source !== 'purchase' && $lines_batch !== null && count($lines_batch) > 0) {
    $orig_line_gross = (float) ($item['gross_wt'] ?? 0);
    $rem_line = auragold_oj_scrap_remaining_gross($conn, $item_id, $orig_line_gross, $invoice_id);
    $batch_gross_sum = 0;
    foreach ($lines_batch as $__ln) {
        if (is_array($__ln)) {
            $batch_gross_sum += isset($__ln['gross_wt']) ? (float) $__ln['gross_wt'] : 0;
        }
    }
    if (round($batch_gross_sum, 4) > round($rem_line, 4)) {
        echo json_encode(['status' => 'error', 'message' => 'Total gross weight (' . round($batch_gross_sum, 4) . ') cannot exceed remaining scrap balance (' . round($rem_line, 4) . ').']);
        exit;
    }
}

if ($lines_batch !== null && count($lines_batch) > 0) {
    $group_name_b = isset($_POST['group_name']) ? esc($_POST['group_name']) : '';
    $comment_b = isset($_POST['comment']) ? esc($_POST['comment']) : '';
    $branch_last = 0;
    $barcodes_out = [];
    $last_stock_id = 0;
    mysqli_begin_transaction($conn);
    foreach ($lines_batch as $line) {
        if (!is_array($line)) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Invalid line row']);
            exit;
        }
        $product_raw = trim((string) ($line['product'] ?? ''));
        $product = esc($product_raw);
        $category = esc(trim((string) ($line['category'] ?? '')));
        $metal = esc(trim((string) ($line['metal'] ?? '')));
        $location = esc(trim((string) ($line['location'] ?? '')));
        $against_invoice_no = esc(trim((string) ($line['against_invoice_no'] ?? '')));
        $against_voucher = esc(trim((string) ($line['against_voucher'] ?? '')));
        if ($against_invoice_no === '') {
            $against_invoice_no = esc($against_of);
        }
        $gross_wt = isset($line['gross_wt']) ? (float) $line['gross_wt'] : 0;
        $final_wt = isset($line['final_wt']) ? (float) $line['final_wt'] : 0;
        $net_wt = isset($line['net_wt']) ? (float) $line['net_wt'] : 0;
        $less_wt = isset($line['less_wt']) ? (float) $line['less_wt'] : 0;
        $purity = isset($line['purity']) ? (float) $line['purity'] : 0;
        $rate = isset($line['rate']) ? (float) $line['rate'] : 0;
        $amount = isset($line['amount']) ? (float) $line['amount'] : 0;
        $quantity = isset($line['quantity']) ? (float) $line['quantity'] : 1;
        if ($source !== 'purchase') {
            $orig_g_batch = (float) ($item['gross_wt'] ?? 0);
            if ($orig_g_batch > 0.00001 && $gross_wt > 0.00001) {
                $sc_b = $gross_wt / $orig_g_batch;
                $final_wt = (float) ($item['final_wt'] ?? 0) * $sc_b;
                $net_wt = (float) ($item['net_wt'] ?? 0) * $sc_b;
                $less_wt = (float) ($item['less_wt'] ?? 0) * $sc_b;
                $net_amt_b = (float) ($item['net_amt'] ?? 0);
                $amt_b = (float) ($item['amount'] ?? 0);
                $amount = ($net_amt_b > 0 ? $net_amt_b : $amt_b) * $sc_b;
            }
        }
        $branch_id_line = isset($line['branch_id']) ? (int) $line['branch_id'] : 0;
        $branch_last = $branch_id_line;
        $rpid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
        $rpcid = isset($line['product_characteristic_id']) ? (int) $line['product_characteristic_id'] : 0;
        $rmid = isset($line['metal_id']) ? (int) $line['metal_id'] : 0;
        if ($rmid <= 0) {
            $rmid = (int) ($item['metal_id'] ?? 0);
        }
        if ($rmid <= 0 && $source === 'purchase') {
            $rmid = (int) ($resolved_metal_id ?? 0);
        }
        if ($rpid <= 0 && $product_raw !== '') {
            $d_esc = mysqli_real_escape_string($conn, $product_raw);
            $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND (name = '$d_esc' OR alternate_name = '$d_esc') LIMIT 1");
            if (!$pr) {
                $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND name LIKE '%" . mysqli_real_escape_string($conn, $product_raw) . "%' ORDER BY id ASC LIMIT 1");
            }
            if ($pr) {
                $rpid = (int) $pr['id'];
            }
        }
        if ($rpcid <= 0 && $rpid > 0 && $rmid > 0) {
            $bid_pc = $branch_id_line > 0 ? $branch_id_line : 1;
            $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $rpid AND metal_id = $rmid AND branch_id = $bid_pc AND status = 1 ORDER BY id DESC LIMIT 1");
            if (!$pcr) {
                $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $rpid AND metal_id = $rmid AND status = 1 ORDER BY id DESC LIMIT 1");
            }
            if ($pcr) {
                $rpcid = (int) $pcr['id'];
            }
        }
        $bid_bc = $branch_id_line > 0 ? $branch_id_line : 1;
        $nb = auragold_next_product_stock_barcode($conn, $rpid, $rpcid, $rmid, $bid_bc);
        $barcode_line = $nb['barcode'];
        $barcodes_out[] = $barcode_line;
        $barcode_esc_line = esc($barcode_line);
        $sql_line = "INSERT INTO tbl_old_jewelry_stock (
            source_invoice_id, source_item_id, barcode, invoice_no, voucher_type, metal, product, location,
            final_wt, gross_wt, purity, branch_id, less_wt, net_wt, amount, category, against_invoice_no, against_voucher,
            group_name, comment, quantity, rate
        ) VALUES (
            $invoice_id, $item_id, '$barcode_esc_line', '$invoice_no_esc', '$voucher_esc', '$metal', '$product', '$location',
            " . (float) $final_wt . ", " . (float) $gross_wt . ", " . (float) $purity . ", " . ($branch_id_line > 0 ? $branch_id_line : 'NULL') . ", " . (float) $less_wt . ", " . (float) $net_wt . ", " . (float) $amount . ", '$category', '$against_invoice_no', '$against_voucher',
            '$group_name_b', '$comment_b', " . (float) $quantity . ", " . (float) $rate . "
        )";
        if (!mysqli_query($conn, $sql_line)) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save: ' . mysqli_error($conn)]);
            exit;
        }
        $last_stock_id = (int) mysqli_insert_id($conn);
        auragold_oj_scrap_mirror_tbl_stock_line(
            $conn,
            $rpid,
            $rpcid,
            $rmid,
            $branch_id_line,
            $product_raw,
            $item,
            $barcode_line,
            $gross_wt,
            $net_wt,
            $final_wt,
            $purity,
            $quantity,
            $rate,
            $amount
        );
        $pure_wt_line = isset($line['pure_wt']) ? (float) $line['pure_wt'] : (isset($line['purity_wt']) ? (float) $line['purity_wt'] : 0);
        if ($source !== 'purchase') {
            $orig_g_p = (float) ($item['gross_wt'] ?? 0);
            if ($orig_g_p > 0.00001 && $gross_wt > 0.00001) {
                $pure_wt_line = (float) ($item['pure_wt'] ?? 0) * ($gross_wt / $orig_g_p);
            }
        }
        $grp_plain_b = isset($_POST['group_name']) ? trim((string) $_POST['group_name']) : '';
        $cmt_plain_b = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
        $cat_plain_b = trim((string) ($line['category'] ?? ''));
        $loc_plain_b = trim((string) ($line['location'] ?? ''));
        auragold_oj_scrap_insert_stock_history_journal_line(
            $conn,
            $invoice_id,
            $item_id,
            (string) $last_stock_id,
            $invoice_no,
            $invoice_date_for_sj,
            $barcode_line,
            $rpid,
            $rpcid,
            $rmid,
            $product_raw,
            $gross_wt,
            $less_wt,
            $net_wt,
            $final_wt,
            $pure_wt_line,
            $purity,
            $quantity,
            $rate,
            $amount,
            $voucher_type,
            $grp_plain_b,
            $cmt_plain_b,
            $cat_plain_b,
            $loc_plain_b
        );
    }
    if ($source !== 'purchase') {
        auragold_oj_scrap_sync_is_stocked_for_item($conn, $item_id);
    }
    mysqli_commit($conn);
    echo json_encode([
        'status' => 'success',
        'message' => 'Stock In saved. Added to Stocked tab.',
        'item_id' => $item_id,
        'old_jewelry_stock_id' => $last_stock_id,
        'barcodes' => $barcodes_out,
        'batch_count' => count($barcodes_out),
    ]);
    exit;
}

$desc_for_match_early = trim((string) ($product_raw !== '' ? $product_raw : ($item['description'] ?? '')));
if ($resolved_product_id <= 0 && $desc_for_match_early !== '') {
    $d_esc = mysqli_real_escape_string($conn, $desc_for_match_early);
    $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND (name = '$d_esc' OR alternate_name = '$d_esc') LIMIT 1");
    if (!$pr) {
        $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND name LIKE '%" . mysqli_real_escape_string($conn, $desc_for_match_early) . "%' ORDER BY id ASC LIMIT 1");
    }
    if ($pr) {
        $resolved_product_id = (int) $pr['id'];
    }
}
if ($resolved_pc_id <= 0 && $resolved_product_id > 0 && $resolved_metal_id > 0) {
    $bid_early = $branch_id > 0 ? $branch_id : 1;
    $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $resolved_product_id AND metal_id = $resolved_metal_id AND branch_id = $bid_early AND status = 1 ORDER BY id DESC LIMIT 1");
    if (!$pcr) {
        $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $resolved_product_id AND metal_id = $resolved_metal_id AND status = 1 ORDER BY id DESC LIMIT 1");
    }
    if ($pcr) {
        $resolved_pc_id = (int) $pcr['id'];
    }
}

if ($source !== 'purchase') {
    $orig_one = (float) ($item['gross_wt'] ?? 0);
    $rem_one = auragold_oj_scrap_remaining_gross($conn, $item_id, $orig_one, $invoice_id);
    if (round($gross_wt, 4) > round($rem_one, 4)) {
        echo json_encode(['status' => 'error', 'message' => 'Gross weight (' . round($gross_wt, 4) . ') cannot exceed remaining scrap balance (' . round($rem_one, 4) . ').']);
        exit;
    }
}

if ($source === 'scrap' || trim($barcode) === '') {
    $bid_bc = $branch_id > 0 ? $branch_id : 1;
    $nb = auragold_next_product_stock_barcode($conn, $resolved_product_id, $resolved_pc_id, $resolved_metal_id, $bid_bc);
    $barcode = $nb['barcode'];
}
$barcode_esc = esc($barcode);

$sql = "INSERT INTO tbl_old_jewelry_stock (
    source_invoice_id, source_item_id, barcode, invoice_no, voucher_type, metal, product, location,
    final_wt, gross_wt, purity, branch_id, less_wt, net_wt, amount, category, against_invoice_no, against_voucher,
    group_name, comment, quantity, rate
) VALUES (
    $invoice_id, $item_id, '$barcode_esc', '$invoice_no_esc', '$voucher_esc', '$metal', '$product', '$location',
    " . (float)$final_wt . ", " . (float)$gross_wt . ", " . (float)$purity . ", " . ($branch_id > 0 ? $branch_id : 'NULL') . ", " . (float)$less_wt . ", " . (float)$net_wt . ", " . (float)$amount . ", '$category', '$against_invoice_no', '$against_voucher',
    '$group_name', '$comment', " . (float)$quantity . ", " . (float)$rate . "
)";

if (!mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save: ' . mysqli_error($conn)]);
    exit;
}

$old_jewelry_stock_new_id = (int) mysqli_insert_id($conn);

auragold_oj_scrap_mirror_tbl_stock_line(
    $conn,
    $resolved_product_id,
    $resolved_pc_id,
    $resolved_metal_id,
    $branch_id,
    $product_raw,
    $item,
    $barcode,
    $gross_wt,
    $net_wt,
    $final_wt,
    $purity,
    $quantity,
    $rate,
    $amount
);
$pure_wt_single = (float) ($item['pure_wt'] ?? 0);
if ($source !== 'purchase') {
    $og_sj = (float) ($item['gross_wt'] ?? 0);
    if ($og_sj > 0.00001 && $gross_wt > 0.00001) {
        $pure_wt_single = (float) ($item['pure_wt'] ?? 0) * ($gross_wt / $og_sj);
    }
}
$grp_plain_s = isset($_POST['group_name']) ? trim((string) $_POST['group_name']) : '';
$cmt_plain_s = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
$cat_plain_s = trim((string) ($_POST['category'] ?? ''));
$loc_plain_s = trim((string) ($_POST['location'] ?? ''));
auragold_oj_scrap_insert_stock_history_journal_line(
    $conn,
    $invoice_id,
    $item_id,
    (string) $old_jewelry_stock_new_id,
    $invoice_no,
    $invoice_date_for_sj,
    $barcode,
    $resolved_product_id,
    $resolved_pc_id,
    $resolved_metal_id,
    $desc_for_match_early,
    $gross_wt,
    $less_wt,
    $net_wt,
    $final_wt,
    $pure_wt_single,
    $purity,
    $quantity,
    $rate,
    $amount,
    $voucher_type,
    $grp_plain_s,
    $cmt_plain_s,
    $cat_plain_s,
    $loc_plain_s
);

if ($source !== 'purchase') {
    auragold_oj_scrap_sync_is_stocked_for_item($conn, $item_id);
}

echo json_encode([
    'status' => 'success',
    'message' => 'Stock In saved. Added to Stocked tab.',
    'item_id' => $item_id,
    'old_jewelry_stock_id' => isset($old_jewelry_stock_new_id) ? $old_jewelry_stock_new_id : 0,
]);
