<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

// Get only the single product for this purchase invoice item (item_id = one line item).
// pii.product_characteristic_id can be NULL (older rows) — use first characteristic for the product
// if missing. Do not require p/pc "active" status: PI lines must load even for legacy/inactive products.
// Barcode is left empty: the stock-journal line must get a NEW serial via get-next-barcode (client),
// not reuse pii.barcode or pc.barcode (those are often already on tbl_stock from purchase/opening).
$query = "
    SELECT
        p.id,
        p.name,
        p.alternate_name,
        p.article,
        p.category_id,
        pc.id as characteristic_id,
        pc.sku_code,
        '' as barcode,
        TRIM(pii.barcode) as purchase_invoice_item_barcode,
        TRIM(pc.barcode) as characteristic_barcode,
        COALESCE(NULLIF(TRIM(pii.carat), ''), NULLIF(TRIM(pc.carat), '')) as carat,
        pii.carat as purchase_invoice_carat,
        pc.carat as characteristic_carat,
        COALESCE(NULLIF(pii.purity, 0), NULLIF(pc.opening_purity, 0), pc.opening_purity, 1) as opening_purity,
        COALESCE(NULLIF(pii.metal_qty, 0), NULLIF(pii.quantity, 0), 1) as metal_qty,
        COALESCE(NULLIF(pii.metal_weight, 0), NULLIF(pii.gross_weight, 0), NULLIF(pc.opening_weight, 0), 0) as metal_weight,
        pc.opening_purity as characteristic_opening_purity,
        pc.opening_weight,
        pc.final_weight,
        pc.rate,
        COALESCE(NULLIF(pii.metal_rate, 0), NULLIF(pii.rate, 0), pc.rate, 0) as purchase_rate,
        pc.value,
        pc.making_on,
        pc.diamond_category,
        pc.barcode_prefix,
        pc.barcode_digits,
        m.display_name as metal_name,
        m.id as metal_id,
        pii.quantity,
        pii.gross_weight,
        pii.making_type,
        pii.making_rate,
        pii.making_discount_amt,
        pii.making_amount,
        pii.making_actual_value,
        pii.making_cost
    FROM tbl_purchase_invoice_items pii
    INNER JOIN tbl_products p ON pii.product_id = p.id
    INNER JOIN tbl_product_characteristics pc
        ON pc.id = (
            CASE
                WHEN pii.product_characteristic_id IS NOT NULL
                    THEN pii.product_characteristic_id
                ELSE (
                    SELECT t.id
                    FROM tbl_product_characteristics t
                    WHERE t.product_id = pii.product_id
                    ORDER BY (t.status = 1) DESC, t.id ASC
                    LIMIT 1
                )
            END
        )
    INNER JOIN tbl_metal m ON m.id = pc.metal_id
    WHERE pii.id = $item_id
    ORDER BY p.name ASC, pc.id ASC
";

$products = getList($query);

if (empty($products)) {
    echo json_encode(['success' => false, 'message' => 'Purchase invoice item not found']);
    exit;
}

echo json_encode(['success' => true, 'products' => $products]);
?>
