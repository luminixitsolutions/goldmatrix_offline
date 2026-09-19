<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
$barcode = preg_replace('/[\x00-\x1F\x7F]/', '', $barcode);

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit;
}

require_once __DIR__ . '/../includes/auragold_barcode_prefix_settings.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_map.php';
$scanned_barcode_raw = $barcode;
if (!empty($conn) && $conn instanceof mysqli) {
    $branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if (preg_match('/^\d{1,4}$/', $barcode) && function_exists('auragold_resolve_four_digit_scan_to_full_barcode')) {
        $padded = str_pad(preg_replace('/\D/', '', $barcode), 4, '0', STR_PAD_LEFT);
        $fromScan = auragold_resolve_four_digit_scan_to_full_barcode($conn, $padded, $branch_id, 0);
        if ($fromScan !== null && $fromScan !== '') {
            $barcode = $fromScan;
        }
    } elseif (function_exists('auragold_resolve_scanned_barcode')) {
        $resolved = auragold_resolve_scanned_barcode($conn, $barcode, ['branch_id' => $branch_id]);
        if ($resolved !== '' && $resolved !== $barcode) {
            $barcode = $resolved;
        }
    }
}

// Fetch barcode details from tbl_stock_journal
$query = "
    SELECT 
        sj.*,
        p.name as product_name,
        p.article,
        m.display_name as metal_name,
        m.id as metal_id,
        pc.hsn,
        pc.sku_code,
        pc.making_on,
        pc.diamond_category,
        pc.carat
    FROM tbl_stock_journal sj
    LEFT JOIN tbl_products p ON sj.product_id = p.id
    LEFT JOIN tbl_metal m ON sj.metal_id = m.id
    LEFT JOIN tbl_product_characteristics pc ON sj.product_characteristic_id = pc.id
    WHERE sj.barcode = '" . esc($barcode) . "'
    AND sj.status = 'active'
    ORDER BY sj.id DESC
    LIMIT 1
";

$result = getRecord($query);

if ($result) {
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    exit;
}

// Fallback: barcode may exist in tbl_product_characteristics (product master) but not yet in stock journal
$pc_query = "
    SELECT 
        pc.id,
        pc.product_id,
        pc.metal_id,
        pc.barcode,
        pc.hsn,
        pc.sku_code,
        pc.making_on,
        pc.diamond_category,
        pc.carat,
        pc.opening_purity AS purity,
        pc.rate,
        pc.value AS amount,
        p.name AS product_name,
        p.article,
        m.display_name AS metal_name
    FROM tbl_product_characteristics pc
    LEFT JOIN tbl_products p ON pc.product_id = p.id
    LEFT JOIN tbl_metal m ON pc.metal_id = m.id
    WHERE pc.barcode = '" . esc($barcode) . "'
    AND pc.status = 1
    ORDER BY pc.id DESC
    LIMIT 1
";
$pc_result = getRecord($pc_query);

if ($pc_result) {
    // Build same shape as stock journal row so addRowFromStockJournal can use it (weights/amounts from product master or 0)
    $data = [
        'id' => 0,
        'product_id' => $pc_result['product_id'],
        'product_characteristic_id' => $pc_result['id'],
        'metal_id' => $pc_result['metal_id'],
        'metal_name' => $pc_result['metal_name'] ?? '',
        'product_name' => $pc_result['product_name'] ?? '',
        'article' => $pc_result['article'] ?? '',
        'barcode' => $pc_result['barcode'] ?? $barcode,
        'hsn' => $pc_result['hsn'] ?? '',
        'sku_code' => $pc_result['sku_code'] ?? '',
        'making_on' => $pc_result['making_on'] ?? '',
        'diamond_category' => $pc_result['diamond_category'] ?? '',
        'carat' => $pc_result['carat'] ?? '',
        'gross_weight' => 0,
        'less_weight' => 0,
        'net_weight' => 0,
        'purity' => $pc_result['purity'] ?? 0,
        'purity_weight' => 0,
        'pure_weight' => 0,
        'final_weight' => 0,
        'rate' => $pc_result['rate'] ?? 0,
        'amount' => $pc_result['amount'] ?? 0,
        'making_amount' => 0,
        'tax_amount' => 0,
        'net_amount' => 0,
        'net_amt_with_tax' => 0,
        'quantity' => 1,
    ];
    echo json_encode([
        'success' => true,
        'data' => $data,
        'source' => 'product_characteristics'
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Barcode not found in stock journal or product master.'
]);
?>
