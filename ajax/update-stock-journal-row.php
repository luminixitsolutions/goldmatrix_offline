<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if (!isset($_POST['update_row']) || $_POST['update_row'] != '1') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid update request']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 0;
$gross_weight = isset($_POST['gross_weight']) ? (float)$_POST['gross_weight'] : 0;
$purity = isset($_POST['purity']) ? (float)$_POST['purity'] : 0;
$rate = isset($_POST['rate']) ? (float)$_POST['rate'] : 0;
$value = isset($_POST['value']) ? (float)$_POST['value'] : (isset($_POST['amount']) ? (float)$_POST['amount'] : 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing row id']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $has_updated_at = false;
    $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'updated_at'");
    if ($rc && mysqli_num_rows($rc) > 0) $has_updated_at = true;
    if ($rc) mysqli_free_result($rc);

    if ($has_updated_at) {
        $stmt = mysqli_prepare($conn, "UPDATE tbl_stock SET opening_qty = ?, opening_weight = ?, opening_purity = ?, rate = ?, value = ?, current_qty = ?, current_weight = ?, updated_at = NOW() WHERE id = ? AND stock_type IN ('purchase','opening','outward')");
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE tbl_stock SET opening_qty = ?, opening_weight = ?, opening_purity = ?, rate = ?, value = ?, current_qty = ?, current_weight = ? WHERE id = ? AND stock_type IN ('purchase','opening','outward')");
    }
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'dddddddi', $quantity, $gross_weight, $purity, $rate, $value, $quantity, $gross_weight, $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('Update failed: ' . mysqli_error($conn));
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected === 0) {
        throw new Exception('No matching stock row found');
    }

    $row = getRecord("SELECT barcode, stock_type FROM tbl_stock WHERE id = $id");
    $barcode = $row['barcode'] ?? '';
    $stock_type = $row['stock_type'] ?? '';

    $has_ref = false;
    $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_barcodes'");
    if ($rc && mysqli_num_rows($rc) > 0) $has_ref = true;
    if ($rc) mysqli_free_result($rc);

    if ($has_ref && $barcode !== '' && in_array($stock_type, ['purchase', 'opening'])) {
        $out_rows = getList("SELECT id, reference_barcodes FROM tbl_stock WHERE stock_type = 'outward' AND reference_barcodes IS NOT NULL AND reference_barcodes != ''");
        foreach ($out_rows as $or) {
            $ref_barcodes_raw = $or['reference_barcodes'] ?? '';
            $arr = array_filter(array_map('trim', explode(',', $ref_barcodes_raw)));
            if (empty($arr) || !in_array($barcode, $arr)) continue;
            $in_list = implode(', ', array_map(function ($b) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $b) . "'";
            }, $arr));
            $sum = getRecord("SELECT SUM(current_qty) AS tq, SUM(current_weight) AS tw, SUM(value) AS tv FROM tbl_stock WHERE barcode IN ($in_list) AND stock_type IN ('purchase','opening')");
            $tq = (float)($sum['tq'] ?? 0);
            $tw = (float)($sum['tw'] ?? 0);
            $tv = (float)($sum['tv'] ?? 0);
            $oid = (int)$or['id'];
            $st2 = mysqli_prepare($conn, "UPDATE tbl_stock SET opening_qty = ?, current_qty = ?, opening_weight = ?, current_weight = ?, value = ? WHERE id = ?");
            if ($st2) {
                mysqli_stmt_bind_param($st2, 'dddddi', $tq, $tq, $tw, $tw, $tv, $oid);
                mysqli_stmt_execute($st2);
                mysqli_stmt_close($st2);
            }
        }
    }

    $barcode_esc = mysqli_real_escape_string($conn, $barcode);
    $sj_st = mysqli_prepare($conn, "UPDATE tbl_stock_journal SET quantity = ?, gross_weight = ?, purity = ?, rate = ?, amount = ?, net_amount = ?, net_amt_with_tax = ? WHERE barcode = ? AND status = 'active'");
    if ($sj_st) {
        mysqli_stmt_bind_param($sj_st, 'ddddddds', $quantity, $gross_weight, $purity, $rate, $value, $value, $value, $barcode_esc);
        mysqli_stmt_execute($sj_st);
        mysqli_stmt_close($sj_st);
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Updated']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
