<?php
session_start();
require_once 'config.php';

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$characteristic_id = isset($_GET['characteristic_id']) ? (int)$_GET['characteristic_id'] : 0;
$voucher = isset($_GET['voucher']) ? trim($_GET['voucher']) : '';
$is_product_opening = ($voucher === 'product_opening' && $characteristic_id > 0);

if (!$is_product_opening && $item_id <= 0) {
    header('Location: stock-journal.php');
    exit;
}

$product_summary = [];
$stock_journal_items = [];
$page_heading_id = 0;
$used_qty = 0;
$used_gross_wt = 0;
$balance_qty = 0;
$balance_gross_wt = 0;

/** Set to false to show Details columns (Group Name, Comment, SJ Inv No, …) again */
$sj_hide_details_table_columns = true;

if ($is_product_opening) {
    $page_heading_id = $characteristic_id;
    $product_summary = getRecord("
        SELECT pc.id, p.name as product_name, m.display_name as metal_name
        FROM tbl_product_characteristics pc
        INNER JOIN tbl_products p ON pc.product_id = p.id
        LEFT JOIN tbl_metal m ON pc.metal_id = m.id
        WHERE pc.id = $characteristic_id
    ");
    if (!$product_summary) {
        header('Location: stock-journal.php?voucher=product_opening');
        exit;
    }
    $stock_journal_items = getList("
        SELECT sj.*,
               p.name as product_name,
               m.display_name as metal_name
        FROM tbl_stock_journal sj
        LEFT JOIN tbl_products p ON sj.product_id = p.id
        LEFT JOIN tbl_metal m ON sj.metal_id = m.id
        WHERE sj.status = 'active'
            AND (sj.item_id IS NULL OR sj.item_id = 0)
            AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')
            AND (
                sj.product_characteristic_id = $characteristic_id
                OR (
                    sj.product_id = (SELECT product_id FROM tbl_product_characteristics WHERE id = $characteristic_id LIMIT 1)
                    AND sj.metal_id = (SELECT metal_id FROM tbl_product_characteristics WHERE id = $characteristic_id LIMIT 1)
                )
            )
        ORDER BY sj.id ASC
    ");
    foreach ($stock_journal_items as $sj) {
        $used_qty += (float)($sj['quantity'] ?? 0);
        $used_gross_wt += (float)($sj['gross_weight'] ?? 0);
    }
    $balance_qty = $used_qty;
    $balance_gross_wt = $used_gross_wt;
} else {
    $page_heading_id = $item_id;
    $product_summary = getRecord("
        SELECT pii.id,
               COALESCE(pii.metal_qty, pii.quantity, 0) as total_quantity,
               COALESCE(pii.gross_weight, 0) as total_gross_weight,
               pi.invoice_no, pi.supplier_name, pii.product_name
        FROM tbl_purchase_invoice_items pii
        LEFT JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE pii.id = $item_id
    ");
    if (!$product_summary) {
        header('Location: stock-journal.php');
        exit;
    }
    $stock_journal_items = getList("
        SELECT sj.*,
               p.name as product_name,
               m.display_name as metal_name
        FROM tbl_stock_journal sj
        LEFT JOIN tbl_products p ON sj.product_id = p.id
        LEFT JOIN tbl_metal m ON sj.metal_id = m.id
        WHERE sj.item_id = $item_id AND sj.status = 'active'
        ORDER BY sj.id ASC
    ");
    foreach ($stock_journal_items as $sj) {
        $used_qty += (float)($sj['quantity'] ?? 0);
        $used_gross_wt += (float)($sj['gross_weight'] ?? 0);
    }
    $balance_qty = max(0, (float)($product_summary['total_quantity'] ?? 0) - $used_qty);
    $balance_gross_wt = max(0, (float)($product_summary['total_gross_weight'] ?? 0) - $used_gross_wt);
}

// Master data for dropdown columns (match stock-journal-create)
$locations = getList("SELECT id, name FROM tbl_location WHERE status = 1 ORDER BY name ASC");
$carats = getList("SELECT id, name, purity, description FROM tbl_carat WHERE status = 1 ORDER BY id ASC");
// Categories from tbl_categories (id, name, status, created_at) - active only
$categories = getList("SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
$calculation_modes = getList("SELECT id, name, code FROM tbl_calculation_modes WHERE status = 1 ORDER BY sort_order ASC, name ASC");

// Load images per row (item_id, barcode_no) from tbl_stock_journal_images
$row_images = [];
foreach ($stock_journal_items as $sj) {
    $img_item_id = (int)($sj['item_id'] ?? 0);
    $barcode_no = isset($sj['barcode']) ? mysqli_real_escape_string($conn, $sj['barcode']) : '';
    $sj_id = (int)($sj['id'] ?? 0);
    $row_images[$sj_id] = [];
    if ($barcode_no !== '') {
        $rows = getList("SELECT id, image_path FROM tbl_stock_journal_images WHERE item_id = $img_item_id AND barcode_no = '$barcode_no' ORDER BY id ASC");
        if ($rows) {
            $row_images[$sj_id] = $rows;
        }
    }
}

// All editable columns (label, field_name, type, decimals). Name and Barcode are read-only. 'print' = Print Barcode icon.
$update_columns = [
    ['Id', 'id', 'readonly', 0],
    ['Print Barcode', 'print_barcode', 'print', 0],
    ['Name', 'product_name', 'readonly', 0],
    ['Short Code', 'code', 'text', 0],
    ['RFIDCode', 'rfid_code', 'text', 0],
    ['Voucher Type', 'voucher_type', 'text', 0],
    ['Barcode', 'barcode', 'readonly', 0],
    ['Design No', 'design_no', 'text', 0],
    ['HUID No', 'huid_no', 'text', 0],
    ['Category', 'category', 'text', 0],
    ['Calculation', 'calculation', 'text', 0],
    ['Location', 'location', 'text', 0],
    ['Quantity', 'quantity', 'number', 2],
    ['Karat', 'karat', 'number', 2],
    ['Pkt. Wt.', 'pkt_wt', 'number', 3],
    ['Pkt. Less Wt.', 'pkt_less_wt', 'number', 3],
    ['Requested Purity', 'requested_purity', 'number', 2],
    ['Requested', 'requested', 'number', 3],
    ['Gross Wt.', 'gross_weight', 'number', 3],
    ['Less Wt.', 'less_weight', 'number', 3],
    ['Gold Loss 1', 'gold_loss_1', 'number', 3],
    ['Gold Loss 2', 'gold_loss_2', 'number', 3],
    ['Setting Charge', 'setting_charge', 'number', 2],
    ['Net Wt.', 'net_weight', 'number', 3],
    ['Purity', 'purity', 'number', 2],
    ['Purity Wt.', 'purity_weight', 'number', 3],
    ['Pure Wt.', 'pure_weight', 'number', 3],
    ['Wastage Per.', 'wastage_per', 'number', 2],
    ['Wastage Wt.', 'wastage_wt', 'number', 3],
    ['Final Wt.', 'final_weight', 'number', 3],
    ['Alloy Wt.', 'alloy_wt', 'number', 3],
    ['Rate', 'rate', 'number', 2],
    ['Metal Value', 'metal_value', 'number', 2],
    ['Metal Cost', 'metal_cost', 'number', 2],
    ['Amount', 'amount', 'number', 2],
    ['Discount Type', 'discount_type', 'text', 0],
    ['Discount Per.', 'discount_per', 'number', 2],
    ['Discount Amount', 'discount_amount', 'number', 2],
    ['Discount', 'discount', 'number', 2],
    ['Making Type', 'making_type', 'text', 0],
    ['Making Rate', 'making_rate', 'number', 2],
    ['Making Amount', 'making_amount', 'number', 2],
    ['Making Cost', 'making_cost', 'number', 2],
    ['Minimum Price', 'minimum_price', 'number', 2],
    ['Stone Charge Type', 'stone_charge_type', 'text', 0],
    ['Stone Weight', 'stone_weight', 'number', 3],
    ['Stone Rate', 'stone_rate', 'number', 2],
    ['Stone Amount', 'stone_amount', 'number', 2],
    ['Stone Cost', 'stone_cost', 'number', 2],
    ['Diamond Amount', 'diamond_amount', 'number', 2],
    ['Purchase Amount', 'purchase_amount', 'number', 2],
    ['Sale Amount', 'sale_amount', 'number', 2],
    ['Net Amt', 'net_amount', 'number', 2],
    ['Tax', 'tax_amount', 'number', 2],
    ['Other Charge Type', 'other_charge_type', 'text', 0],
    ['Other Weight', 'other_weight', 'number', 3],
    ['Other Rate', 'other_rate', 'number', 2],
    ['Other Info', 'other_info', 'text', 0],
    ['Other Amount', 'other_amount', 'number', 2],
    ['Hallmark Amount', 'hallmark_amount', 'number', 2],
    ['Hallmark Rate', 'hallmark_rate', 'number', 2],
    ['Net Amt+Tax', 'net_amt_with_tax', 'number', 2],
    ['Reverse', 'reverse', 'number', 2],
];

require_once __DIR__ . '/includes/auragold_extra_fields_schema.php';
require_once __DIR__ . '/includes/auragold_extra_fields_item_values.php';

$sj_extra_field_defs = [];
$sj_branch_id = function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
auragold_ensure_tbl_extra_fields($conn);
auragold_ensure_extra_fields_json_column($conn, 'tbl_stock_journal');

$pi_extra_fields_fallback = '';
if (!$is_product_opening && $item_id > 0 && auragold_ensure_extra_fields_json_column($conn, 'tbl_purchase_invoice_items')) {
    $pi_ef_row = getRecord("SELECT extra_fields_json FROM tbl_purchase_invoice_items WHERE id = " . (int) $item_id . " LIMIT 1");
    if ($pi_ef_row && !empty($pi_ef_row['extra_fields_json'])) {
        $pi_extra_fields_fallback = (string) $pi_ef_row['extra_fields_json'];
    }
}

$sj_metal_names = [];
foreach ($stock_journal_items as $_sj_m) {
    $mn = trim((string) ($_sj_m['metal_name'] ?? $_sj_m['metal_type'] ?? ''));
    if ($mn !== '') {
        $sj_metal_names[$mn] = true;
    }
}
if ($sj_metal_names === [] && $characteristic_id > 0) {
    $pc_metal = getRecord("
        SELECT m.display_name AS metal_name
        FROM tbl_product_characteristics pc
        LEFT JOIN tbl_metal m ON pc.metal_id = m.id
        WHERE pc.id = " . (int) $characteristic_id . " LIMIT 1
    ");
    if ($pc_metal && trim((string) ($pc_metal['metal_name'] ?? '')) !== '') {
        $sj_metal_names[trim((string) $pc_metal['metal_name'])] = true;
    }
}
$seen_extra_field_ids = [];
foreach (array_keys($sj_metal_names) as $_metal_name) {
    foreach (auragold_get_extra_fields($conn, $sj_branch_id, $_metal_name) as $_ef) {
        if ((int) ($_ef['status'] ?? 0) !== 1) {
            continue;
        }
        $_ef_id = (int) ($_ef['id'] ?? 0);
        if ($_ef_id <= 0 || isset($seen_extra_field_ids[$_ef_id])) {
            continue;
        }
        $seen_extra_field_ids[$_ef_id] = true;
        $sj_extra_field_defs[] = $_ef;
    }
}

$sj_extra_update_cols = [];
foreach ($sj_extra_field_defs as $_ef) {
    $_ef_id = (int) ($_ef['id'] ?? 0);
    if ($_ef_id <= 0) {
        continue;
    }
    $sj_extra_update_cols[] = [
        (string) ($_ef['display_name'] ?? ('Extra Field ' . $_ef_id)),
        'extra-field-' . $_ef_id,
        'extra_field',
        0,
        $_ef_id,
        auragold_extra_field_normalize_type((string) ($_ef['field_type'] ?? 'text')),
        (string) ($_ef['dropdown_options_json'] ?? ''),
    ];
}
if ($sj_extra_update_cols !== []) {
    $insert_at = null;
    foreach ($update_columns as $_idx => $_col) {
        if (($_col[1] ?? '') === 'net_amt_with_tax') {
            $insert_at = $_idx;
            break;
        }
    }
    if ($insert_at !== null) {
        array_splice($update_columns, $insert_at, 0, $sj_extra_update_cols);
    } else {
        $update_columns = array_merge($update_columns, $sj_extra_update_cols);
    }
}
$sj_extra_field_count = count($sj_extra_update_cols);

// Column-to-group mapping for dynamic colspan when hiding columns (each column has data-parent="group")
$column_group_defs = [
    ['basic', 16],
    ['weight', 15],
    ['metal', 4],
    ['discount', 4],
    ['making', 5],
    ['stone', 6],
    ['sale', 4],
    ['other', 5],
    ['hallmark', 2],
];
if ($sj_extra_field_count > 0) {
    $column_group_defs[] = ['extra', $sj_extra_field_count];
}
$column_group_defs = array_merge($column_group_defs, [
    ['financial', 2],
    ['details', 9],
    ['images', 2],
]);
$column_parent_list = [];
foreach ($column_group_defs as $g) {
    for ($i = 0; $i < $g[1]; $i++) {
        $column_parent_list[] = $g[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Update Items - Stock Journal</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php';?>
    <style>
        /* Extra bottom space so content clears footer / taskbar when scrolling */
        .layout-content { padding: 16px; padding-bottom: max(12rem, 28vh); }
        /* Reference: navy #1a2b4b, gold #a68b4d, update btn #a389f4 */
        .page-header-bar { background: #1a2b4b; color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .back-link { color: #93c5fd; text-decoration: none; font-size: 13px; }
        .back-link:hover { color: #fff; }
        .balance-info-card { background: linear-gradient(135deg, #1a2b4b 0%, #243d5c 100%); color: #fff; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
        .balance-info-card .label { font-size: 10px; text-transform: uppercase; opacity: 0.8; }
        .balance-info-card .value { font-size: 15px; font-weight: 700; }
        .update-table { width: max-content; table-layout: auto; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 8px; font-size: 11px; border: 1px solid #e2e8f0; }
        .update-table th { background: #1a2b4b; color: #fff; padding: 6px 8px; text-align: center; vertical-align: middle; font-size: 10px; font-weight: 700; white-space: nowrap; border-right: 1px solid rgba(255,255,255,0.2); border-bottom: 1px solid rgba(255,255,255,0.15); }
        .update-table-group-row th { background: #1a2b4b !important; color: #fff; font-weight: 700; padding: 8px 10px; text-align: center; vertical-align: middle; white-space: nowrap; border-bottom: 1px solid rgba(255,255,255,0.25); border-right: 1px solid rgba(255,255,255,0.2); }
        .update-table-group-row th.sj-group-header-gold { background: #a68b4d !important; color: #fff; border-right: 1px solid rgba(255,255,255,0.25); }
        .update-table-group-row th:last-child { border-right: none; }
        /* Align "Images & Action" group header with the two sticky columns below */
        #productListTable .update-table-group-row th:last-child { position: sticky; right: 0; width: 190px; min-width: 190px; max-width: 190px; box-sizing: border-box; z-index: 3; box-shadow: -2px 0 6px rgba(0,0,0,0.08); }
        .update-table thead tr:not(.update-table-group-row) th:nth-child(73) { border-right: 2px solid rgba(255,255,255,0.3); }
        .update-table td { padding: 6px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; font-size: 11px; text-align: center; vertical-align: middle; white-space: nowrap; }
        .update-table input[type="number"], .update-table input[type="text"], .update-table select { width: 100%; max-width: 90px; padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px; box-sizing: border-box; }
        .update-table .col-readonly { background: #f8fafc; color: #64748b; }
        /* Column show/hide: header as source of truth */
        #productListTable th[data-column].hidden,
        #productListTable td[data-column].hidden { display: none !important; }
        /* Table settings button and dropdown (match create page) */
        .table-settings-wrapper { position: relative; z-index: 10000; }
        .table-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem; }
        .table-settings-btn { background: #1a2b4b; border: none; border-radius: 6px; padding: 0.45rem 0.85rem; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #fff; font-weight: 600; }
        .table-settings-btn:hover { background: #243d5c; color: #fff; }
        .table-settings-btn i { font-size: 12px; }
        .table-settings-dropdown { position: absolute; right: 0; top: 100%; margin-top: 0.5rem; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.15); padding: 1rem; min-width: 240px; max-width: 300px; max-height: 450px; overflow-y: auto; overflow-x: hidden; z-index: 10001; display: none; }
        .table-settings-dropdown.show { display: block; }
        .table-settings-dropdown h6 { margin: 0 0 0.75rem 0; font-size: 0.85rem; font-weight: 700; color: #1a2b4b; text-transform: uppercase; letter-spacing: 0.05em; }
        .table-settings-search { width: 100%; padding: 6px 10px; margin-bottom: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; }
        .table-settings-search:focus { outline: none; border-color: #1a2b4b; }
        .table-settings-search::placeholder { color: #94a3b8; }
        .table-settings-list { max-height: 380px; overflow-y: auto; }
        .table-settings-item { display: flex; align-items: center; cursor: pointer; transition: all 0.2s ease; }
        .table-settings-item.filter-hidden { display: none !important; }
        .table-settings-item:hover { background: rgba(17, 41, 75, 0.05); padding-left: 0.5rem; }
        .table-settings-item input[type="checkbox"] { margin-right: 0.75rem; cursor: pointer; }
        .table-settings-item label { margin: 0; cursor: pointer; font-size: 0.85rem; color: #000; font-weight: 500; flex: 1; }
        .card-body:has(.table-settings-wrapper) { overflow: visible !important; }
        .table-responsive { position: relative; overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .table-responsive table { display: inline-table; width: max-content; }
        /* Sticky right columns: Images & Action stay visible on horizontal scroll */
        #productListTable th[data-column="images"],
        #productListTable th[data-column="action"],
        #productListTable td[data-column="images"],
        #productListTable td[data-column="action"] { position: sticky; right: 0; z-index: 2; box-shadow: -2px 0 6px rgba(0,0,0,0.06); }
        #productListTable thead th[data-column="images"],
        #productListTable thead th[data-column="action"] { background: #a68b4d !important; color: #fff; border-right: 1px solid rgba(255,255,255,0.25); }
        #productListTable thead tr:not(.update-table-group-row) th[data-column="net_amt_with_tax"],
        #productListTable thead tr:not(.update-table-group-row) th[data-column="reverse"] { background: #a68b4d !important; color: #fff; border-right: 1px solid rgba(255,255,255,0.25); }
        #productListTable td[data-column="images"],
        #productListTable td[data-column="action"] { background: #fff; }
        #productListTable th[data-column="images"],
        #productListTable td[data-column="images"] { right: 82px; min-width: 100px; }
        #productListTable th[data-column="action"],
        #productListTable td[data-column="action"] { right: 0; }
        /* #productListTable: single table, shrink to visible columns (no extra white space) */
        #productListTable { table-layout: auto; border-collapse: collapse; width: max-content; }
        #productListTable tr { display: table-row; height: 35px; }
        #productListTable tbody tr { height: 80px; }
        #productListTable td { display: table-cell; height: 80px; max-height: 80px; vertical-align: middle; box-sizing: border-box; white-space: nowrap; overflow: hidden; border-bottom: 1px solid #e5e5e5; border-right: 1px solid #e2e8f0; padding: 6px; text-align: center; }
        /* CRITICAL: inner wrapper fills full height so column aligns with row */
        #productListTable td[data-column="images"],
        #productListTable td[data-column="action"] { padding: 0; overflow: hidden; }
        #productListTable td[data-column="images"] > div,
        #productListTable td[data-column="action"] > div { height: 100%; min-height: 80px; display: flex; align-items: center; }
        /* Images column: fixed box so image never overflows or disturbs layout */
        #productListTable .images-wrapper { display: flex; align-items: center; gap: 8px; width: 100%; height: 100%; padding: 0 8px; margin: 0; overflow: hidden; flex-wrap: nowrap; }
        #productListTable .img-box { width: 45px; height: 45px; min-width: 45px; min-height: 45px; max-width: 45px; max-height: 45px; flex-shrink: 0; border-radius: 6px; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center; position: relative; }
        #productListTable .img-box img { width: 100% !important; height: 100% !important; max-width: 100% !important; max-height: 100% !important; object-fit: cover; object-position: center; display: block; margin: 0; }
        #productListTable .remove-img { position: absolute; top: -6px; right: -6px; background: red; color: #fff; font-size: 10px; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer; margin: 0; line-height: 1; }
        #productListTable .upload-btn { min-width: 52px; height: 30px; padding: 0 8px; margin: 0; border: 1px solid #60a5fa; border-radius: 6px; background: #fff; color: #2563eb; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; font-size: 11px; font-weight: 600; flex-shrink: 0; }
        #productListTable .upload-btn .upload-btn-label { font-size: 10px; letter-spacing: 0.02em; }
        #productListTable .upload-btn i.feather { width: 14px; height: 14px; stroke: currentColor; }
        #productListTable .upload-btn:hover { background: #eff6ff; color: #1d4ed8; border-color: #3b82f6; }
        #productListTable .sj-images-thumbs { display: none !important; }
        .update-table .sj-img-input { display: none; }
        /* Action column: center PERFECTLY */
        #productListTable .action-wrapper { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; margin: 0; padding: 0; }
        #productListTable .update-btn { margin: 0; }
        #productListTable thead th.group-end,
        #productListTable tbody td.group-end { border-right: 2px solid #c9a44c; }
        /* Carousel modal */
        #sjCarouselModal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.85); align-items: center; justify-content: center; padding: 20px; }
        #sjCarouselModal.show { display: flex; }
        #sjCarouselModal .sj-carousel-box { max-width: 90vw; max-height: 90vh; position: relative; background: #1e293b; border-radius: 8px; padding: 12px; }
        #sjCarouselModal .sj-carousel-box img { max-width: 90vw; max-height: 85vh; object-fit: contain; display: block; }
        #sjCarouselModal .sj-carousel-close { position: absolute; top: -36px; right: 0; background: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; line-height: 1; color: #334155; }
        #sjCarouselModal .sj-carousel-close:hover { background: #f1f5f9; }
        #sjCarouselModal .sj-carousel-prev, #sjCarouselModal .sj-carousel-next { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 20px; color: #11294b; }
        #sjCarouselModal .sj-carousel-prev { left: 8px; }
        #sjCarouselModal .sj-carousel-next { right: 8px; }
        #sjCarouselModal .sj-carousel-prev:hover, #sjCarouselModal .sj-carousel-next:hover { background: #fff; }
        #sjCarouselModal .sj-carousel-counter { text-align: center; color: #fff; margin-top: 10px; font-size: 14px; }
        #sjCarouselModal .sj-carousel-delete { display: block; margin: 10px auto 0; padding: 8px 20px; border: none; border-radius: 6px; background: #dc2626; color: #fff; font-size: 13px; cursor: pointer; }
        #sjCarouselModal .sj-carousel-delete:hover { background: #b91c1c; }
        .btn-update-row { background: #a389f4; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
        .btn-update-row:hover { background: #9178e8; }
        .btn-update-row:disabled { background: #cbd5e1; cursor: not-allowed; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px; }
        .card-body { padding: 1.25rem; }
        .product-selection-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 0; letter-spacing: -0.01em; }
        .sj-table-section-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; margin-top: 8px; }
        .sj-table-section-head .product-selection-title { margin: 0; }
        .search-bar-wrap { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px 20px; margin-bottom: 16px; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
        .search-bar-wrap .search-field { display: flex; flex-direction: column; gap: 6px; }
        .search-bar-wrap .search-field label { font-weight: 600; color: #475569; font-size: 12px; margin: 0; white-space: nowrap; }
        .search-bar-wrap .search-field input[type="text"], .search-bar-wrap .search-field input[type="number"] { padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; min-width: 100px; width: 100%; box-sizing: border-box; }
        .search-bar-wrap .search-field input::placeholder { color: #94a3b8; }
        .search-bar-wrap .search-field.search-field-wide input { min-width: 200px; }
        .search-bar-wrap .search-field .btn-search-clear { padding: 6px 14px; border-radius: 6px; font-size: 12px; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #475569; align-self: flex-start; }
        .search-bar-wrap .search-field .btn-search-clear:hover { background: #f1f5f9; }
        .search-bar-wrap .search-field .search-result-hint { font-size: 12px; color: #64748b; padding: 6px 0; }
        .search-bar-wrap .search-field.search-field-spacer label { visibility: hidden; }
        .search-bar-wrap .search-no-match { color: #dc2626; font-size: 12px; margin-top: 8px; width: 100%; }
        /* Add Image modal (multiple upload) */
        #addImageModal { display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 20px; }
        #addImageModal.show { display: flex; }
        #addImageModal .add-image-modal-box { background: #fff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 560px; overflow: hidden; }
        #addImageModal .add-image-modal-header { background: #1a2b4b; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
        #addImageModal .add-image-modal-header h5 { margin: 0; font-size: 1.1rem; font-weight: 600; }
        #addImageModal .add-image-modal-close { background: transparent; border: none; color: #fff; font-size: 24px; line-height: 1; cursor: pointer; padding: 0 4px; opacity: 0.9; }
        #addImageModal .add-image-modal-close:hover { opacity: 1; }
        #addImageModal .add-image-modal-body { padding: 20px; }
        #addImageModal .add-image-drop-zone { border: 2px dashed #cbd5e1; border-radius: 8px; min-height: 200px; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; padding: 16px; background: #f8fafc; cursor: pointer; }
        #addImageModal .add-image-drop-zone .no-preview { color: #94a3b8; font-size: 13px; text-align: center; }
        #addImageModal .add-image-drop-zone .no-preview-icon { font-size: 48px; color: #c4b896; margin-bottom: 8px; }
        #addImageModal .add-image-thumbs { display: flex; flex-wrap: wrap; gap: 8px; }
        #addImageModal .add-image-thumb-wrap { position: relative; width: 64px; height: 64px; flex-shrink: 0; }
        #addImageModal .add-image-thumb-wrap img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: block; }
        #addImageModal .add-image-thumb-remove { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border: none; border-radius: 50%; background: #dc2626; color: #fff; font-size: 14px; line-height: 1; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; }
        #addImageModal .add-image-thumb-remove:hover { background: #b91c1c; }
        #addImageModal .add-image-browse-wrap { margin-top: 12px; display: flex; justify-content: flex-end; }
        #addImageModal .add-image-browse { width: 56px; height: 56px; border: 2px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; font-size: 24px; }
        #addImageModal .add-image-browse:hover { border-color: #1a2b4b; color: #1a2b4b; background: #e0e7ff; }
        #addImageModal .add-image-instruction { font-size: 12px; color: #64748b; font-style: italic; margin-top: 12px; }
        #addImageModal .add-image-modal-footer { padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        #addImageModal .add-image-camera-btn { width: 44px; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #475569; font-size: 20px; }
        #addImageModal .add-image-camera-btn:hover { background: #f1f5f9; }
        #addImageModal .add-image-footer-btns { display: flex; gap: 10px; }
        #addImageModal .add-image-btn-cancel, #addImageModal .add-image-btn-save { padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        #addImageModal .add-image-btn-cancel { background: #c4b896; color: #fff; }
        #addImageModal .add-image-btn-cancel:hover { background: #b3a385; }
        #addImageModal .add-image-btn-save { background: #c4b896; color: #fff; }
        #addImageModal .add-image-btn-save:hover { background: #b3a385; }
        #addImageModal .add-image-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>
<body>
    <?php include 'sidebar.php';?>
    <div class="layout-content">
        <div class="container-fluid">
            <div class="page-header-bar">
                <div>
                    <strong>Update Items</strong> — <?php echo $is_product_opening ? 'Characteristic ID: ' . (int)$characteristic_id : 'Item ID: ' . (int)$item_id; ?>
                </div>
                <a href="stock-journal.php<?php echo $is_product_opening ? '?voucher=product_opening' : ''; ?>" class="back-link"><i class="feather icon-arrow-left"></i> Back to Stock Journal</a>
            </div>

            <!-- Product selection box -->
            <div class="card">
                <div class="card-body">
                    <div class="product-selection-title">Product (<?php echo $is_product_opening ? 'Characteristic ID: ' . (int)$characteristic_id : 'Item ID: ' . (int)$item_id; ?>)</div>
                    <div class="balance-info-card">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px;">
                            <?php if ($is_product_opening): ?>
                            <div style="background: rgba(255,255,255,0.15); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Voucher</div>
                                <div class="value">Product Opening</div>
                            </div>
                            <?php else: ?>
                            <div style="background: rgba(255,255,255,0.15); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Purchase Invoice</div>
                                <div class="value"><?php echo htmlspecialchars($product_summary['invoice_no'] ?? 'N/A'); ?></div>
                            </div>
                            <?php endif; ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Product</div>
                                <div class="value"><?php echo htmlspecialchars($product_summary['product_name'] ?? 'N/A'); ?></div>
                            </div>
                            <?php if (!$is_product_opening): ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Total Qty</div>
                                <div class="value"><?php echo number_format($product_summary['total_quantity'] ?? 0, 2); ?></div>
                            </div>
                            <?php endif; ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Used Qty</div>
                                <div class="value"><?php echo number_format($used_qty, 2); ?></div>
                            </div>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Balance Qty</div>
                                <div class="value"><?php echo number_format($balance_qty, 2); ?></div>
                            </div>
                            <?php if (!$is_product_opening): ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Total Gross Wt</div>
                                <div class="value"><?php echo number_format($product_summary['total_gross_weight'] ?? 0, 3); ?></div>
                            </div>
                            <?php endif; ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Used Gross Wt</div>
                                <div class="value"><?php echo number_format($used_gross_wt, 3); ?></div>
                            </div>
                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px;">
                                <div class="label">Balance Gross Wt</div>
                                <div class="value"><?php echo number_format($balance_gross_wt, 3); ?></div>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small mb-2" style="font-size: 12px;">Edit row values and click <strong>Update</strong> to save. Use <strong>Show/Hide Columns</strong> to customize the grid.</p>

                    <?php if (!empty($stock_journal_items)): ?>
                    <!-- Search bar: label on 1st line, textbox on 2nd line for each field -->
                    <div class="search-bar-wrap" id="stockJournalSearchBar">
                        <div class="search-field search-field-wide">
                            <label for="searchGlobal">Search</label>
                            <input type="text" id="searchGlobal" placeholder="Barcode, name, code, qty, weight, purity, rate..." autocomplete="off">
                        </div>
                        <div class="search-field">
                            <label for="searchBarcode">Barcode</label>
                            <input type="text" id="searchBarcode" placeholder="Barcode no" autocomplete="off">
                        </div>
                        <div class="search-field">
                            <label for="searchQty">Qty</label>
                            <input type="number" id="searchQty" placeholder="Quantity" step="0.01" min="0">
                        </div>
                        <div class="search-field">
                            <label for="searchGrossWt">Gross Wt</label>
                            <input type="number" id="searchGrossWt" placeholder="Gross weight" step="0.001" min="0">
                        </div>
                        <div class="search-field">
                            <label for="searchPurity">Purity</label>
                            <input type="number" id="searchPurity" placeholder="Purity" step="0.01" min="0">
                        </div>
                        <div class="search-field">
                            <label for="searchRate">Rate</label>
                            <input type="number" id="searchRate" placeholder="Rate" step="0.01" min="0">
                        </div>
                        <div class="search-field search-field-spacer">
                            <label>&nbsp;</label>
                            <button type="button" class="btn-search-clear" id="btnSearchClear">Clear</button>
                        </div>
                        <div class="search-field search-field-spacer">
                            <label>&nbsp;</label>
                            <span class="search-result-hint" id="searchResultHint"></span>
                        </div>
                    </div>
                    <div class="search-no-match" id="searchNoMatch" style="display: none;">No matching items. Try different search criteria.</div>
                    <?php endif; ?>

                    <?php if (empty($stock_journal_items)): ?>
                        <p class="text-muted">No stock journal entries for this item. Use
                            <?php if ($is_product_opening): ?>
                            <a href="stock-journal-create.php?characteristic_id=<?php echo (int)$characteristic_id; ?>&voucher=product_opening">Create</a> or <a href="stock-journal-create.php?characteristic_id=<?php echo (int)$characteristic_id; ?>&voucher=product_opening&mode=add">Add Items</a>
                            <?php else: ?>
                            <a href="stock-journal-create.php?item_id=<?php echo (int)$item_id; ?>">Create</a> or <a href="stock-journal-create.php?item_id=<?php echo (int)$item_id; ?>&mode=add">Add Items</a>
                            <?php endif; ?>
                            to add products.</p>
                    <?php else: ?>
                        <div class="table-header-row sj-table-section-head">
                            <span class="product-selection-title">Product Selection</span>
                            <div class="table-settings-wrapper">
                                <button type="button" class="table-settings-btn" id="updateTableSettingsBtn" title="Show/Hide columns">
                                    <i class="feather icon-settings"></i> Show/Hide Columns
                                </button>
                                <div class="table-settings-dropdown" id="updateTableSettingsDropdown">
                                    <h6>Show/Hide Columns</h6>
                                    <input type="text" class="table-settings-search" id="updateTableSettingsSearch" placeholder="Search columns..." autocomplete="off">
                                    <div class="table-settings-list" id="updateTableSettingsList">
                                    <?php foreach ($update_columns as $col): ?>
                                    <div class="table-settings-item">
                                        <input type="checkbox" id="update-col-<?php echo htmlspecialchars($col[1]); ?>" data-column="<?php echo htmlspecialchars($col[1]); ?>" checked>
                                        <label for="update-col-<?php echo htmlspecialchars($col[1]); ?>"><?php echo htmlspecialchars($col[0]); ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (!$sj_hide_details_table_columns): ?>
                                    <div class="table-settings-item">
                                        <input type="checkbox" id="update-col-group_name" data-column="group_name" checked>
                                        <label for="update-col-group_name">Group Name</label>
                                    </div>
                                    <div class="table-settings-item">
                                        <input type="checkbox" id="update-col-comment" data-column="comment" checked>
                                        <label for="update-col-comment">Comment</label>
                                    </div>
                                    <?php endif; ?>
                                    <div class="table-settings-item">
                                        <input type="checkbox" id="update-col-images" data-column="images" checked>
                                        <label for="update-col-images">Images</label>
                                    </div>
                                    <div class="table-settings-item">
                                        <input type="checkbox" id="update-col-action" data-column="action" checked>
                                        <label for="update-col-action">Action</label>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="update-table" id="productListTable">
                                <colgroup>
                                    <?php for ($i = 0, $sj_col_total = count($update_columns) + 11; $i < $sj_col_total; $i++) echo '<col>'; ?>
                                </colgroup>
                                <thead>
                                    <tr class="update-table-group-row">
                                        <th data-group="basic" colspan="16" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Basic Information</span></th>
                                        <th data-group="weight" colspan="15" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Weight / Purity</span></th>
                                        <th data-group="metal" colspan="4" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Metal</span></th>
                                        <th data-group="discount" colspan="4" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Discount</span></th>
                                        <th data-group="making" colspan="5" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Making</span></th>
                                        <th data-group="stone" colspan="6" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Stone</span></th>
                                        <th data-group="sale" colspan="4" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Sale / Amounts</span></th>
                                        <th data-group="other" colspan="5" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Other</span></th>
                                        <th data-group="hallmark" colspan="2" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Hallmark</span></th>
                                        <?php if ($sj_extra_field_count > 0): ?>
                                        <th data-group="extra" colspan="<?php echo (int) $sj_extra_field_count; ?>" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Extra Fields</span></th>
                                        <?php endif; ?>
                                        <th data-group="financial" colspan="2" class="product-modal-group-header-th sj-group-header-gold" style="text-align: center;"><span class="product-modal-group-label">Net Amt+Tax &amp; Reverse</span></th>
                                        <th data-group="details" colspan="9" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-label">Details</span></th>
                                        <th data-group="images" data-group-locked="1" colspan="2" class="sj-group-header-gold" style="text-align: center;"><span class="product-modal-group-label">Images &amp; Action</span></th>
                                    </tr>
                                    <tr>
                                    <?php $col_idx = 0; foreach ($update_columns as $col): ?>
                                        <th data-column="<?php echo htmlspecialchars($col[1]); ?>" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'basic'); ?>"><?php echo htmlspecialchars($col[0]); ?></th>
                                    <?php endforeach; ?>
                                        <th data-column="group_name" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Group Name</th>
                                        <th data-column="comment" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Comment</th>
                                        <th data-column="sj_invoice_no" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">SJ Inv No</th>
                                        <th data-column="invoice_no" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Invoice No</th>
                                        <th data-column="sj_date" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">SJ Date</th>
                                        <th data-column="product_id" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Product Id</th>
                                        <th data-column="product_characteristic_id" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Char. Id</th>
                                        <th data-column="metal_id" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Metal Id</th>
                                        <th data-column="metal_type" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'details'); ?>">Metal Type</th>
                                        <th data-column="images" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'images'); ?>">Images</th>
                                        <th data-column="action" data-parent="<?php echo htmlspecialchars($column_parent_list[$col_idx++] ?? 'images'); ?>">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_journal_items as $sj): 
                                        $sj_id = (int)($sj['id'] ?? 0);
                                        $img_item_id = (int)($sj['item_id'] ?? 0);
                                        $sj_barcode = $sj['barcode'] ?? '';
                                        $images_list = isset($row_images[$sj_id]) ? $row_images[$sj_id] : [];
                                        $n = function($key, $dec = 2) use ($sj) {
                                            $v = isset($sj[$key]) ? (float)$sj[$key] : 0;
                                            return number_format($v, $dec, '.', '');
                                        };
                                        $s = function($key) use ($sj) {
                                            return htmlspecialchars($sj[$key] ?? '');
                                        };
                                        $sj_ef_values = [];
                                        if (!empty($sj['extra_fields_json'])) {
                                            $sj_ef_dec = json_decode((string) $sj['extra_fields_json'], true);
                                            if (is_array($sj_ef_dec)) {
                                                $sj_ef_values = $sj_ef_dec;
                                            }
                                        }
                                        if ($sj_ef_values === [] && $pi_extra_fields_fallback !== '') {
                                            $sj_ef_dec = json_decode($pi_extra_fields_fallback, true);
                                            if (is_array($sj_ef_dec)) {
                                                $sj_ef_values = $sj_ef_dec;
                                            }
                                        }
                                    ?>
                                    <tr data-sj-id="<?php echo $sj_id; ?>" data-item-id="<?php echo $img_item_id; ?>" data-barcode="<?php echo htmlspecialchars($sj_barcode); ?>" data-product-name="<?php echo htmlspecialchars($sj['product_name'] ?? ''); ?>">
                                        <?php foreach ($update_columns as $col):
                                            $f = $col[1];
                                            $dec = (int)$col[3];
                                            if ($col[2] === 'readonly'):
                                                $val = $f === 'id' ? $sj_id : $s($f);
                                                if ($f === 'id') $val = (int)$val;
                                        ?>
                                        <td class="col-readonly" data-column="<?php echo htmlspecialchars($f); ?>"<?php echo ($f === 'product_name' || $f === 'barcode') ? ' title="Read-only"' : ''; ?>><?php echo $val; ?></td>
                                        <?php elseif ($col[2] === 'print'): ?>
                                        <td class="col-readonly" data-column="<?php echo htmlspecialchars($f); ?>" style="text-align: center;">
                                            <i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcodeFromRowUpdate(this)" title="Print Barcode"></i>
                                        </td>
                                        <?php elseif ($col[2] === 'extra_field'):
                                            $ef_id = (int) ($col[4] ?? 0);
                                            $ef_type = (string) ($col[5] ?? 'text');
                                            $ef_col_key = (string) ($col[1] ?? ('extra-field-' . $ef_id));
                                            $ef_val = '';
                                            if (isset($sj_ef_values[(string) $ef_id])) {
                                                $ef_val = (string) $sj_ef_values[(string) $ef_id];
                                            } elseif (isset($sj_ef_values[$ef_id])) {
                                                $ef_val = (string) $sj_ef_values[$ef_id];
                                            }
                                            $ef_opts = [];
                                            if ($ef_type === 'dropdown') {
                                                $ef_opts_raw = json_decode((string) ($col[6] ?? '[]'), true);
                                                if (is_array($ef_opts_raw)) {
                                                    $ef_opts = $ef_opts_raw;
                                                }
                                            }
                                        ?>
                                        <td data-column="<?php echo htmlspecialchars($ef_col_key); ?>" data-parent="extra" data-extra-field-id="<?php echo $ef_id; ?>">
                                            <?php if ($ef_type === 'dropdown' && $ef_opts !== []): ?>
                                            <select class="form-control form-control-sm" data-extra-field-id="<?php echo $ef_id; ?>">
                                                <option value=""></option>
                                                <?php foreach ($ef_opts as $_opt):
                                                    $_opt_s = trim((string) $_opt);
                                                    if ($_opt_s === '') continue;
                                                ?>
                                                <option value="<?php echo htmlspecialchars($_opt_s, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($ef_val === $_opt_s) ? ' selected' : ''; ?>><?php echo htmlspecialchars($_opt_s); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php else: ?>
                                            <input type="text" class="form-control form-control-sm" data-extra-field-id="<?php echo $ef_id; ?>" value="<?php echo htmlspecialchars($ef_val, ENT_QUOTES, 'UTF-8'); ?>" maxlength="255">
                                            <?php endif; ?>
                                        </td>
                                        <?php elseif ($col[2] === 'text'): ?>
                                        <?php if ($f === 'making_type'): 
                                            $making_type_val = $s($f);
                                            $making_types = ['Fix', 'Per Gram', 'Per Piece', 'Per Kilogram', 'Per Percent', 'MRP', 'M.KT'];
                                        ?>
                                        <td data-column="making_type">
                                            <select class="form-control form-control-sm" data-field="making_type">
                                                <?php foreach ($making_types as $mt): ?>
                                                <option value="<?php echo htmlspecialchars($mt); ?>"<?php echo ($making_type_val === $mt) ? ' selected' : ''; ?>><?php echo htmlspecialchars($mt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <?php elseif ($f === 'discount_type'): 
                                            $disc_val = $s($f);
                                        ?>
                                        <td data-column="discount_type">
                                            <select class="form-control form-control-sm" data-field="discount_type">
                                                <?php
                                                $disc_types = ['Fix', 'On Amount', 'On Making Amount', 'On Diamond Amount', 'On Stone Amount', 'On Net Amount', 'On Percentage'];
                                                foreach ($disc_types as $dt):
                                                    $sel = ($disc_val === $dt) || ($dt === 'Fix' && ($disc_val === '' || $disc_val === null));
                                                ?>
                                                <option value="<?php echo htmlspecialchars($dt); ?>"<?php echo $sel ? ' selected' : ''; ?>><?php echo htmlspecialchars($dt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <?php elseif ($f === 'location' && !empty($locations)): 
                                            $loc_val = $s($f);
                                        ?>
                                        <td data-column="location">
                                            <select class="form-control form-control-sm" data-field="location">
                                                <option value="">--</option>
                                                <?php foreach ($locations as $loc): 
                                                    $loc_sel = ($loc_val !== '' && $loc_val !== null && ($loc_val === ($loc['name'] ?? '') || $loc_val === (string)($loc['id'] ?? '')));
                                                ?>
                                                <option value="<?php echo htmlspecialchars($loc['name']); ?>"<?php echo $loc_sel ? ' selected' : ''; ?>><?php echo htmlspecialchars($loc['name'] ?? ''); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <?php elseif ($f === 'category' && !empty($categories)): 
                                            $cat_val = $s($f);
                                        ?>
                                        <td data-column="category">
                                            <select class="form-control form-control-sm" data-field="category">
                                                <option value="">--</option>
                                                <?php foreach ($categories as $cat): 
                                                    $cat_id = (int)($cat['id'] ?? 0);
                                                    $cat_sel = ($cat_val !== '' && $cat_val !== null && ($cat_val === (string)$cat_id || $cat_val === ($cat['name'] ?? '')));
                                                ?>
                                                <option value="<?php echo $cat_id; ?>"<?php echo $cat_sel ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['name'] ?? ''); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <?php elseif ($f === 'calculation'): 
                                            $calc_val = $s($f);
                                            $calculation_options = ['Carat X Rate', 'Rate X Gross Wt', 'Rate X Purity Wt', 'Rate X Net Wt', 'Rate X Final Wt', 'Fix', 'Stone Charge', 'Attach Image Type'];
                                        ?>
                                        <td data-column="calculation">
                                            <select class="form-control form-control-sm" data-field="calculation" style="width: 120px; font-size: 0.7rem;">
                                                <option value="">--</option>
                                                <?php foreach ($calculation_options as $opt): 
                                                    $calc_sel = ($calc_val !== '' && $calc_val !== null && $calc_val === $opt);
                                                ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>"<?php echo $calc_sel ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <?php elseif ($f === 'stone_charge_type' || $f === 'other_charge_type'): ?>
                                        <td data-column="<?php echo htmlspecialchars($f); ?>"><input type="text" class="form-control form-control-sm" data-field="<?php echo htmlspecialchars($f); ?>" value="<?php echo $s($f); ?>" maxlength="255"></td>
                                        <?php else: ?>
                                        <td data-column="<?php echo htmlspecialchars($f); ?>"><input type="text" class="form-control form-control-sm" data-field="<?php echo htmlspecialchars($f); ?>" value="<?php echo $s($f); ?>" maxlength="255"></td>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <?php if ($f === 'karat' && !empty($carats)): 
                                            $karat_val = (float)($sj['karat'] ?? 0);
                                        ?>
                                        <td data-column="karat">
                                            <select class="form-control form-control-sm karat-select-update" data-field="karat" style="min-width: 80px;">
                                                <option value="">Select Karat</option>
                                                <?php foreach ($carats as $c): 
                                                    $purity = (float)($c['purity'] ?? $c['id'] ?? 0);
                                                    $cname = $c['name'] ?? '';
                                                    $match = ($karat_val > 0 && abs($karat_val - $purity) < 0.02);
                                                    if (!$match && $karat_val > 0 && $cname !== '' && preg_match('/^(\d+)/', $cname, $m) && abs((float)$m[1] - $karat_val) < 0.01) $match = true;
                                                    $sel = $match ? ' selected' : '';
                                                ?>
                                                <option value="<?php echo $purity; ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($cname); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <?php else: ?>
                                        <td data-column="<?php echo htmlspecialchars($f); ?>"><input type="number" class="form-control form-control-sm" data-field="<?php echo htmlspecialchars($f); ?>" value="<?php echo $n($f, $dec); ?>" step="<?php echo $dec >= 3 ? '0.001' : '0.01'; ?>" min="0"></td>
                                        <?php endif; ?>
                                        <?php endif; endforeach; ?>
                                        <td data-column="group_name"><input type="text" class="form-control form-control-sm" data-field="group_name" value="<?php echo $s('group_name'); ?>" maxlength="255"></td>
                                        <td data-column="comment"><input type="text" class="form-control form-control-sm" data-field="comment" value="<?php echo $s('comment'); ?>"></td>
                                        <td class="col-readonly" data-column="sj_invoice_no"><?php echo $s('sj_invoice_no'); ?></td>
                                        <td class="col-readonly" data-column="invoice_no"><?php echo $s('invoice_no'); ?></td>
                                        <td class="col-readonly" data-column="sj_date"><?php echo $s('sj_date'); ?></td>
                                        <td class="col-readonly" data-column="product_id"><?php echo (int)($sj['product_id'] ?? 0); ?></td>
                                        <td class="col-readonly" data-column="product_characteristic_id"><?php echo (int)($sj['product_characteristic_id'] ?? 0); ?></td>
                                        <td class="col-readonly" data-column="metal_id"><?php echo (int)($sj['metal_id'] ?? 0); ?></td>
                                        <td class="col-readonly" data-column="metal_type"><?php echo $s('metal_type'); ?></td>
                                        <td class="sj-images-cell" data-column="images">
                                            <div class="images-wrapper">
                                                <div class="img-box">
                                                    <?php
                                                    $first_img = isset($images_list[0]) ? $images_list[0] : null;
                                                    if ($first_img && !empty($first_img['image_path'])):
                                                        $first_url = htmlspecialchars($first_img['image_path']);
                                                        $first_id = (int)($first_img['id'] ?? 0);
                                                    ?>
                                                    <img src="<?php echo $first_url; ?>" alt="" title="Click to view all images" data-img-id="<?php echo $first_id; ?>" data-img-src="<?php echo $first_url; ?>">
                                                    <span class="remove-img" data-img-id="<?php echo $first_id; ?>" onclick="deleteStockJournalImage(this)" title="Delete image">×</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="sj-images-thumbs" data-sj-id="<?php echo $sj_id; ?>" aria-hidden="true">
                                                    <?php foreach ($images_list as $img): 
                                                        $img_id = (int)($img['id'] ?? 0);
                                                        $img_path = $img['image_path'] ?? '';
                                                        if ($img_path !== ''): 
                                                            $img_url = htmlspecialchars($img_path);
                                                    ?>
                                                    <div class="sj-thumb-wrap" data-img-id="<?php echo $img_id; ?>" data-img-src="<?php echo $img_url; ?>"></div>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                                <input type="file" class="sj-img-input" accept=".jpg,.jpeg,.png,.webp" multiple data-sj-id="<?php echo $sj_id; ?>">
                                                <button type="button" class="upload-btn" onclick="openAddImageModal(this)" title="Add images"><i class="feather icon-upload"></i><span class="upload-btn-label">ADD</span></button>
                                            </div>
                                        </td>
                                        <td data-column="action">
                                            <div class="action-wrapper">
                                                <button type="button" class="update-btn btn-update-row" onclick="updateRow(this)">Update</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Carousel modal for viewing images -->
    <div id="sjCarouselModal" class="sj-carousel-modal" onclick="if(event.target===this) closeSjCarousel()">
        <div class="sj-carousel-box" onclick="event.stopPropagation()">
            <button type="button" class="sj-carousel-close" onclick="closeSjCarousel()" title="Close">&times;</button>
            <button type="button" class="sj-carousel-prev" onclick="sjCarouselPrev()" title="Previous">&lsaquo;</button>
            <img id="sjCarouselImg" src="" alt="">
            <button type="button" class="sj-carousel-next" onclick="sjCarouselNext()" title="Next">&rsaquo;</button>
            <div class="sj-carousel-counter" id="sjCarouselCounter"></div>
            <button type="button" class="sj-carousel-delete" id="sjCarouselDeleteBtn" onclick="deleteCurrentCarouselImage()" title="Delete this image">Delete image</button>
        </div>
    </div>

    <!-- Add Image modal: multiple upload -->
    <div id="addImageModal" class="add-image-modal" onclick="if(event.target===this) closeAddImageModal()">
        <div class="add-image-modal-box" onclick="event.stopPropagation()">
            <div class="add-image-modal-header">
                <h5>Add Image</h5>
                <button type="button" class="add-image-modal-close" onclick="closeAddImageModal()" title="Close">&times;</button>
            </div>
            <div class="add-image-modal-body">
                <div class="add-image-drop-zone" id="addImageDropZone" title="Click to add images">
                    <div class="add-image-preview-inner" id="addImagePreviewInner">
                        <div class="no-preview" id="addImageNoPreview">
                            <div class="no-preview-icon"><i class="feather icon-image"></i></div>
                            <div>NO PREVIEW AVAILABLE</div>
                        </div>
                        <div class="add-image-thumbs" id="addImageThumbs" style="display: none;"></div>
                    </div>
                </div>
                <div class="add-image-browse-wrap">
                    <div class="add-image-browse" id="addImageBrowse" title="Browse for images"><i class="feather icon-upload"></i></div>
                </div>
                <input type="file" id="addImageModalFileInput" accept=".jpg,.jpeg,.png,.webp" multiple style="display: none;">
                <p class="add-image-instruction">Click the upload area or use the camera below to add images. Click a thumbnail to set as primary.</p>
            </div>
            <div class="add-image-modal-footer">
                <button type="button" class="add-image-camera-btn" id="addImageCameraBtn" title="Capture from camera"><i class="feather icon-camera"></i></button>
                <div class="add-image-footer-btns">
                    <button type="button" class="add-image-btn-cancel" onclick="closeAddImageModal()">CANCEL</button>
                    <button type="button" class="add-image-btn-save" id="addImageBtnSave">SAVE</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/product-modal-add-item-common.js"></script>
    <script>
    function printBarcodeFromRowUpdate(element) {
        var row = element.closest('tr');
        if (!row) return;
        var barcode = row.getAttribute('data-barcode') || '';
        if (!barcode) {
            alert('No barcode found for this row');
            return;
        }
        var url = 'barcode-print.php?barcode=' + encodeURIComponent(barcode);
        function addParam(key, val) {
            if (val == null) return;
            var s = String(val).trim();
            if (s === '') return;
            url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(s);
        }
        var karatSel = row.querySelector('[data-field="karat"]');
        if (karatSel && karatSel.value) {
            addParam('karat', karatSel.value);
            var opt = karatSel.options[karatSel.selectedIndex];
            if (opt && opt.text) addParam('carat_name', opt.text.trim());
        }
        addParam('gross_wt', getField(row, 'gross_weight'));
        addParam('net_wt', getField(row, 'net_weight'));
        addParam('less_wt', getField(row, 'less_weight'));
        addParam('purity', getField(row, 'purity'));
        addParam('final_wt', getField(row, 'final_weight'));
        addParam('quantity', getField(row, 'quantity'));
        addParam('product_name', row.getAttribute('data-product-name') || '');
        window.open(url, '_blank', 'width=800,height=600');
    }
    function getField(row, name) {
        var el = row.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }
    function getNum(row, name) {
        return parseFloat(getField(row, name)) || 0;
    }
    function recalcMakingAmountUpdate(row) {
        var type = (getField(row, 'making_type') || 'Fix').trim();
        var rate = getNum(row, 'making_rate');
        var netWt = getNum(row, 'net_weight');
        var qty = getNum(row, 'quantity');
        var metalValue = getNum(row, 'metal_value');
        var makingAmount = 0;
        if (type === 'Fix' || type === 'MRP' || type === 'M.KT') {
            makingAmount = rate;
        } else if (type === 'Per Gram') {
            makingAmount = netWt * rate;
        } else if (type === 'Per Piece') {
            makingAmount = qty * rate;
        } else if (type === 'Per Kilogram') {
            makingAmount = (netWt / 1000) * rate;
        } else if (type === 'Per Percent') {
            makingAmount = metalValue * (rate / 100);
        } else {
            makingAmount = rate;
        }
        var makingAmountEl = row.querySelector('[data-field="making_amount"]');
        if (makingAmountEl) {
            makingAmountEl.value = typeof makingAmount === 'number' && !isNaN(makingAmount) ? makingAmount.toFixed(2) : '0.00';
        }
    }
    function updateRow(btn) {
        var row = btn.closest('tr');
        if (!row) return;
        var id = row.getAttribute('data-sj-id');
        if (!id) return;

        btn.disabled = true;
        btn.textContent = 'Saving...';

        var formData = new FormData();
        formData.append('id', id);
        row.querySelectorAll('[data-field]').forEach(function(inp) {
            var name = inp.getAttribute('data-field');
            if (name && name !== 'product_name' && name !== 'barcode') {
                var val = inp.value;
                formData.append(name, val === '' ? (inp.type === 'number' ? '0' : '') : val);
            }
        });
        var extraFields = {};
        row.querySelectorAll('[data-extra-field-id]').forEach(function(inp) {
            var efId = inp.getAttribute('data-extra-field-id');
            if (!efId) return;
            extraFields[efId] = inp.value || '';
        });
        formData.append('extra_fields', JSON.stringify(extraFields));

        fetch('ajax/update-stock-journal-item.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Update';
            if (data.status === 'success') {
                alert('Updated successfully.');
            } else {
                alert('Error: ' + (data.message || 'Update failed'));
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = 'Update';
            alert('Error: ' + (err.message || 'Request failed'));
        });
    }

    // Carousel modal state (sources = image URLs, sjCarouselIds = image DB ids for delete)
    var sjCarouselSources = [];
    var sjCarouselIds = [];
    var sjCarouselIndex = 0;
    var sjCarouselContainer = null;

    function openSjCarousel(sources, index, ids, container) {
        sjCarouselSources = sources || [];
        sjCarouselIds = ids || [];
        sjCarouselContainer = container || null;
        sjCarouselIndex = Math.max(0, Math.min(index || 0, sjCarouselSources.length - 1));
        var modal = document.getElementById('sjCarouselModal');
        var imgEl = document.getElementById('sjCarouselImg');
        var counterEl = document.getElementById('sjCarouselCounter');
        var delBtn = document.getElementById('sjCarouselDeleteBtn');
        if (sjCarouselSources.length === 0) return;
        modal.classList.add('show');
        imgEl.src = sjCarouselSources[sjCarouselIndex];
        counterEl.textContent = (sjCarouselIndex + 1) + ' / ' + sjCarouselSources.length;
        document.querySelector('.sj-carousel-prev').style.display = sjCarouselSources.length > 1 ? '' : 'none';
        document.querySelector('.sj-carousel-next').style.display = sjCarouselSources.length > 1 ? '' : 'none';
        if (delBtn) delBtn.style.display = sjCarouselIds.length ? '' : 'none';
    }

    function closeSjCarousel() {
        document.getElementById('sjCarouselModal').classList.remove('show');
        sjCarouselContainer = null;
    }

    function deleteCurrentCarouselImage() {
        if (sjCarouselIds.length === 0 || sjCarouselIndex < 0 || sjCarouselIndex >= sjCarouselIds.length) return;
        var id = sjCarouselIds[sjCarouselIndex];
        if (!id || !confirm('Delete this image?')) return;
        var formData = new FormData();
        formData.append('id', id);
        fetch('ajax/delete-stock-journal-image.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status !== 'success') {
                    alert(data.message || 'Delete failed');
                    return;
                }
                var wrap = sjCarouselContainer && sjCarouselContainer.querySelector('.sj-thumb-wrap[data-img-id="' + id + '"]');
                if (wrap) wrap.remove();
                var cell = sjCarouselContainer && sjCarouselContainer.closest('td[data-column="images"]');
                var imgBox = cell && cell.querySelector('.img-box');
                if (imgBox) {
                    var img = imgBox.querySelector('img[data-img-id="' + id + '"]');
                    var span = imgBox.querySelector('.remove-img[data-img-id="' + id + '"]');
                    if (img) img.remove();
                    if (span) span.remove();
                    var nextWrap = sjCarouselContainer && sjCarouselContainer.querySelector('.sj-thumb-wrap');
                    if (nextWrap && !imgBox.querySelector('img')) {
                        var src = nextWrap.getAttribute('data-img-src');
                        var nextId = nextWrap.getAttribute('data-img-id');
                        if (src && nextId) {
                            var newImg = document.createElement('img');
                            newImg.src = src;
                            newImg.alt = '';
                            newImg.title = 'Click to view all images';
                            newImg.setAttribute('data-img-id', nextId);
                            newImg.setAttribute('data-img-src', src);
                            var newSpan = document.createElement('span');
                            newSpan.className = 'remove-img';
                            newSpan.setAttribute('data-img-id', nextId);
                            newSpan.onclick = function() { deleteStockJournalImage(this); };
                            newSpan.title = 'Delete image';
                            newSpan.innerHTML = '×';
                            imgBox.appendChild(newImg);
                            imgBox.appendChild(newSpan);
                        }
                    }
                }
                sjCarouselSources.splice(sjCarouselIndex, 1);
                sjCarouselIds.splice(sjCarouselIndex, 1);
                if (sjCarouselSources.length === 0) {
                    closeSjCarousel();
                    return;
                }
                sjCarouselIndex = Math.min(sjCarouselIndex, sjCarouselSources.length - 1);
                document.getElementById('sjCarouselImg').src = sjCarouselSources[sjCarouselIndex];
                document.getElementById('sjCarouselCounter').textContent = (sjCarouselIndex + 1) + ' / ' + sjCarouselSources.length;
                document.querySelector('.sj-carousel-prev').style.display = sjCarouselSources.length > 1 ? '' : 'none';
                document.querySelector('.sj-carousel-next').style.display = sjCarouselSources.length > 1 ? '' : 'none';
            })
            .catch(function(err) {
                alert('Error: ' + (err.message || 'Delete failed'));
            });
    }

    function sjCarouselPrev() {
        if (sjCarouselSources.length <= 1) return;
        sjCarouselIndex = (sjCarouselIndex - 1 + sjCarouselSources.length) % sjCarouselSources.length;
        document.getElementById('sjCarouselImg').src = sjCarouselSources[sjCarouselIndex];
        document.getElementById('sjCarouselCounter').textContent = (sjCarouselIndex + 1) + ' / ' + sjCarouselSources.length;
    }

    function sjCarouselNext() {
        if (sjCarouselSources.length <= 1) return;
        sjCarouselIndex = (sjCarouselIndex + 1) % sjCarouselSources.length;
        document.getElementById('sjCarouselImg').src = sjCarouselSources[sjCarouselIndex];
        document.getElementById('sjCarouselCounter').textContent = (sjCarouselIndex + 1) + ' / ' + sjCarouselSources.length;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (addImageModalEl && addImageModalEl.classList.contains('show')) closeAddImageModal();
            else if (document.getElementById('sjCarouselModal').classList.contains('show')) closeSjCarousel();
            return;
        }
        if (!document.getElementById('sjCarouselModal').classList.contains('show')) return;
        if (e.key === 'ArrowLeft') sjCarouselPrev();
        if (e.key === 'ArrowRight') sjCarouselNext();
    });

    // Click on image -> open carousel with all images (from hidden .sj-images-thumbs or .img-box img)
    var tableScroll = document.querySelector('.table-responsive');
    if (tableScroll) {
        tableScroll.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-img')) return;
        var container = null;
        var index = 0;
        var imgEl = e.target.closest('.img-box img');
        if (imgEl) {
            var cell = imgEl.closest('td[data-column="images"]');
            if (!cell) return;
            container = cell.querySelector('.sj-images-thumbs');
            if (!container) return;
            var currentId = imgEl.getAttribute('data-img-id');
            var wraps = container.querySelectorAll('.sj-thumb-wrap');
            wraps.forEach(function(w, i) {
                if (w.getAttribute('data-img-id') === currentId) index = i;
            });
        } else {
            var wrap = e.target.closest('.sj-thumb-wrap');
            if (!wrap) return;
            container = wrap.closest('.sj-images-thumbs');
            if (!container) return;
            var wraps = container.querySelectorAll('.sj-thumb-wrap');
            wraps.forEach(function(w, i) { if (w === wrap) index = i; });
        }
        if (!container) return;
        var wraps = container.querySelectorAll('.sj-thumb-wrap');
        var sources = [];
        var ids = [];
        wraps.forEach(function(w) {
            var src = w.getAttribute('data-img-src');
            var imgId = w.getAttribute('data-img-id');
            if (src) { sources.push(src); ids.push(imgId || ''); }
        });
        if (sources.length) openSjCarousel(sources, index, ids, container);
        });
    }

    function deleteStockJournalImage(btn) {
        if (!confirm('Delete this image?')) return;
        var id = btn.getAttribute('data-img-id');
        if (!id) {
            var wrap = btn.closest('.sj-thumb-wrap');
            if (wrap) id = wrap.getAttribute('data-img-id');
        }
        if (!id) return;
        var formData = new FormData();
        formData.append('id', id);
        fetch('ajax/delete-stock-journal-image.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status !== 'success') {
                    alert(data.message || 'Delete failed');
                    return;
                }
                var cell = btn.closest('td[data-column="images"]');
                if (!cell) return;
                var imgBox = cell.querySelector('.img-box');
                var thumbs = cell.querySelector('.sj-images-thumbs');
                var removedWrap = thumbs && thumbs.querySelector('.sj-thumb-wrap[data-img-id="' + id + '"]');
                if (removedWrap) removedWrap.remove();
                if (imgBox) {
                    var img = imgBox.querySelector('img[data-img-id="' + id + '"]');
                    var span = imgBox.querySelector('.remove-img[data-img-id="' + id + '"]');
                    if (img) img.remove();
                    if (span) span.remove();
                    var nextWrap = thumbs && thumbs.querySelector('.sj-thumb-wrap');
                    if (nextWrap && !imgBox.querySelector('img')) {
                        var src = nextWrap.getAttribute('data-img-src');
                        var nextId = nextWrap.getAttribute('data-img-id');
                        if (src && nextId) {
                            var newImg = document.createElement('img');
                            newImg.src = src;
                            newImg.alt = '';
                            newImg.title = 'Click to view all images';
                            newImg.setAttribute('data-img-id', nextId);
                            newImg.setAttribute('data-img-src', src);
                            var newSpan = document.createElement('span');
                            newSpan.className = 'remove-img';
                            newSpan.setAttribute('data-img-id', nextId);
                            newSpan.onclick = function() { deleteStockJournalImage(this); };
                            newSpan.title = 'Delete image';
                            newSpan.innerHTML = '×';
                            imgBox.appendChild(newImg);
                            imgBox.appendChild(newSpan);
                        }
                    }
                }
            })
            .catch(function(err) {
                alert('Error: ' + (err.message || 'Delete failed'));
            });
    }

    // --- Search / filter rows by barcode, weight, qty, purity, rate, etc. ---
    var searchGlobal = document.getElementById('searchGlobal');
    var searchBarcode = document.getElementById('searchBarcode');
    var searchQty = document.getElementById('searchQty');
    var searchGrossWt = document.getElementById('searchGrossWt');
    var searchPurity = document.getElementById('searchPurity');
    var searchRate = document.getElementById('searchRate');
    var btnSearchClear = document.getElementById('btnSearchClear');
    var searchResultHint = document.getElementById('searchResultHint');
    var searchNoMatch = document.getElementById('searchNoMatch');

    function getRowSearchText(mainRow) {
        var parts = [];
        parts.push((mainRow.getAttribute('data-barcode') || '').toLowerCase());
        parts.push((mainRow.getAttribute('data-product-name') || '').toLowerCase());
        mainRow.querySelectorAll('input[data-field]').forEach(function(inp) {
            var v = (inp.value || '').toString().trim();
            if (v) parts.push(v.toLowerCase());
        });
        return parts.join(' ');
    }

    function applyStockJournalSearch() {
        if (!document.getElementById('productListTable')) return;
        var globalTerm = (searchGlobal && searchGlobal.value || '').toString().trim().toLowerCase();
        var barcodeTerm = (searchBarcode && searchBarcode.value || '').toString().trim().toLowerCase();
        var qtyVal = searchQty && searchQty.value.trim() !== '' ? parseFloat(searchQty.value) : null;
        var grossWtVal = searchGrossWt && searchGrossWt.value.trim() !== '' ? parseFloat(searchGrossWt.value) : null;
        var purityVal = searchPurity && searchPurity.value.trim() !== '' ? parseFloat(searchPurity.value) : null;
        var rateVal = searchRate && searchRate.value.trim() !== '' ? parseFloat(searchRate.value) : null;

        var table = document.getElementById('productListTable');
        var rows = table ? table.querySelectorAll('tbody tr') : [];
        var visibleCount = 0;

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var show = true;

            if (globalTerm) {
                var rowText = getRowSearchText(row);
                if (rowText.indexOf(globalTerm) === -1) show = false;
            }
            if (show && barcodeTerm) {
                var b = (row.getAttribute('data-barcode') || '').toLowerCase();
                if (b.indexOf(barcodeTerm) === -1) show = false;
            }
            if (show && qtyVal !== null) {
                var q = getNum(row, 'quantity');
                if (Math.abs(q - qtyVal) > 0.0001) show = false;
            }
            if (show && grossWtVal !== null) {
                var gw = getNum(row, 'gross_weight');
                if (Math.abs(gw - grossWtVal) > 0.0001) show = false;
            }
            if (show && purityVal !== null) {
                var pu = getNum(row, 'purity');
                if (Math.abs(pu - purityVal) > 0.0001) show = false;
            }
            if (show && rateVal !== null) {
                var rt = getNum(row, 'rate');
                if (Math.abs(rt - rateVal) > 0.0001) show = false;
            }

            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        }

        if (searchResultHint) searchResultHint.textContent = visibleCount + ' of ' + rows.length + ' shown';
        if (searchNoMatch) searchNoMatch.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }

    var updateTable = document.querySelector('.update-table tbody');
    if (updateTable) {
        updateTable.addEventListener('change', function(e) {
            var el = e.target;
            if (el.getAttribute('data-field') === 'making_type') {
                var row = el.closest('tr');
                if (row) recalcMakingAmountUpdate(row);
            }
        });
        updateTable.addEventListener('input', function(e) {
            var el = e.target;
            if (el.getAttribute('data-field') === 'making_rate') {
                var row = el.closest('tr');
                if (row) recalcMakingAmountUpdate(row);
            }
        });
    }

    // --- Column Show/Hide (localStorage key: stock_journal_columns_update) ---
    var STORAGE_KEY_UPDATE_COLUMNS = 'stock_journal_columns_update';
    var SJ_DETAILS_COLUMNS_FORCE_HIDDEN = <?php echo $sj_hide_details_table_columns
        ? "['group_name','comment','sj_invoice_no','invoice_no','sj_date','product_id','product_characteristic_id','metal_id','metal_type']"
        : '[]'; ?>;

    function updateGroupColspans() {
        var table = document.getElementById('productListTable');
        if (!table) return;
        var groupRow = table.querySelector('thead tr.update-table-group-row');
        var headerRow = table.querySelector('thead tr:not(.update-table-group-row)');
        if (!groupRow || !headerRow) return;
        var groupThs = groupRow.querySelectorAll('th[data-group]');
        groupThs.forEach(function(groupTh) {
            var groupName = groupTh.getAttribute('data-group');
            if (!groupName) return;
            var childCols = headerRow.querySelectorAll('th[data-parent="' + groupName + '"]');
            var visibleCount = 0;
            childCols.forEach(function(th) {
                if (!th.classList.contains('hidden')) visibleCount++;
            });
            if (visibleCount === 0) {
                groupTh.style.display = 'none';
            } else {
                groupTh.style.display = '';
                groupTh.setAttribute('colspan', visibleCount);
            }
        });
    }

    function applyColumnVisibilityUpdate() {
        var table = document.getElementById('productListTable');
        if (!table) return;
        var config = {};
        try {
            var raw = localStorage.getItem(STORAGE_KEY_UPDATE_COLUMNS);
            if (raw) config = JSON.parse(raw);
        } catch (e) {}
        var headerRow = table.querySelector('thead tr:not(.update-table-group-row)');
        if (!headerRow) return;
        var ths = headerRow.querySelectorAll('th[data-column]');
        ths.forEach(function(th) {
            var col = th.getAttribute('data-column');
            if (!col) return;
            var forceHidden = SJ_DETAILS_COLUMNS_FORCE_HIDDEN.indexOf(col) !== -1;
            var visible = forceHidden ? false : (config[col] !== false);
            var cells = table.querySelectorAll('tbody td[data-column="' + col + '"]');
            if (visible) {
                th.classList.remove('hidden');
                cells.forEach(function(cell) { cell.classList.remove('hidden'); });
            } else {
                th.classList.add('hidden');
                cells.forEach(function(cell) { cell.classList.add('hidden'); });
            }
        });
        updateGroupColspans();
    }

    function saveColumnVisibilityUpdate() {
        var dropdown = document.getElementById('updateTableSettingsDropdown');
        if (!dropdown) return;
        var config = {};
        dropdown.querySelectorAll('input[type="checkbox"][data-column]').forEach(function(cb) {
            var col = cb.getAttribute('data-column');
            if (col) config[col] = cb.checked;
        });
        try {
            localStorage.setItem(STORAGE_KEY_UPDATE_COLUMNS, JSON.stringify(config));
        } catch (e) {}
        applyColumnVisibilityUpdate();
    }

    (function initColumnVisibilityUpdate() {
        var settingsBtn = document.getElementById('updateTableSettingsBtn');
        var settingsDropdown = document.getElementById('updateTableSettingsDropdown');
        if (!settingsBtn || !settingsDropdown) return;

        try {
            var raw = localStorage.getItem(STORAGE_KEY_UPDATE_COLUMNS);
            if (raw) {
                var config = JSON.parse(raw);
                settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]').forEach(function(cb) {
                    var col = cb.getAttribute('data-column');
                    if (col && config.hasOwnProperty(col)) cb.checked = config[col];
                });
            }
        } catch (e) {}

        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
            if (settingsDropdown.classList.contains('show')) {
                var searchInput = document.getElementById('updateTableSettingsSearch');
                if (searchInput) { searchInput.value = ''; filterColumnSettingsList(''); searchInput.focus(); }
            }
        });
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
            }
        });
        settingsDropdown.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', saveColumnVisibilityUpdate);
        });

        function filterColumnSettingsList(term) {
            var list = document.getElementById('updateTableSettingsList');
            if (!list) return;
            term = (term || '').toLowerCase().trim();
            list.querySelectorAll('.table-settings-item').forEach(function(item) {
                var label = item.querySelector('label');
                var text = label ? label.textContent : '';
                if (term === '' || text.toLowerCase().indexOf(term) !== -1) {
                    item.classList.remove('filter-hidden');
                } else {
                    item.classList.add('filter-hidden');
                }
            });
        }
        var columnSearchInput = document.getElementById('updateTableSettingsSearch');
        if (columnSearchInput) {
            columnSearchInput.addEventListener('input', function() { filterColumnSettingsList(this.value); });
            columnSearchInput.addEventListener('keydown', function(e) { e.stopPropagation(); });
        }

        applyColumnVisibilityUpdate();
    })();

    if (searchGlobal) searchGlobal.addEventListener('input', applyStockJournalSearch);
    if (searchBarcode) searchBarcode.addEventListener('input', applyStockJournalSearch);
    if (searchQty) searchQty.addEventListener('input', applyStockJournalSearch);
    if (searchGrossWt) searchGrossWt.addEventListener('input', applyStockJournalSearch);
    if (searchPurity) searchPurity.addEventListener('input', applyStockJournalSearch);
    if (searchRate) searchRate.addEventListener('input', applyStockJournalSearch);
    if (btnSearchClear) {
        btnSearchClear.addEventListener('click', function() {
            if (searchGlobal) searchGlobal.value = '';
            if (searchBarcode) searchBarcode.value = '';
            if (searchQty) searchQty.value = '';
            if (searchGrossWt) searchGrossWt.value = '';
            if (searchPurity) searchPurity.value = '';
            if (searchRate) searchRate.value = '';
            applyStockJournalSearch();
        });
    }
    applyStockJournalSearch();

    // --- Add Image modal: multiple upload ---
    var addImageModalFiles = [];
    var addImageModalRow = null;
    var addImageModalEl = document.getElementById('addImageModal');
    var addImageDropZone = document.getElementById('addImageDropZone');
    var addImageNoPreview = document.getElementById('addImageNoPreview');
    var addImageThumbs = document.getElementById('addImageThumbs');
    var addImageModalFileInput = document.getElementById('addImageModalFileInput');
    var addImageBrowse = document.getElementById('addImageBrowse');
    var addImageBtnSave = document.getElementById('addImageBtnSave');

    function openAddImageModal(btn) {
        var row = btn && btn.closest ? btn.closest('tr') : null;
        if (!row) return;
        addImageModalRow = row;
        addImageModalFiles = [];
        renderAddImageModalPreview();
        if (addImageModalEl) addImageModalEl.classList.add('show');
        if (addImageModalFileInput) addImageModalFileInput.value = '';
    }

    function closeAddImageModal() {
        addImageModalRow = null;
        addImageModalFiles = [];
        if (addImageModalEl) addImageModalEl.classList.remove('show');
        if (addImageModalFileInput) addImageModalFileInput.value = '';
    }

    function renderAddImageModalPreview() {
        if (!addImageNoPreview || !addImageThumbs) return;
        addImageThumbs.innerHTML = '';
        if (addImageModalFiles.length === 0) {
            addImageNoPreview.style.display = '';
            addImageThumbs.style.display = 'none';
            return;
        }
        addImageNoPreview.style.display = 'none';
        addImageThumbs.style.display = 'flex';
        addImageModalFiles.forEach(function(file, index) {
            var wrap = document.createElement('div');
            wrap.className = 'add-image-thumb-wrap';
            var img = document.createElement('img');
            img.alt = '';
            var url = (window.URL || window.webkitURL).createObjectURL(file);
            img.src = url;
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'add-image-thumb-remove';
            rm.innerHTML = '&times;';
            rm.title = 'Remove';
            (function(idx) {
                rm.onclick = function() {
                    addImageModalFiles.splice(idx, 1);
                    renderAddImageModalPreview();
                };
            })(index);
            wrap.appendChild(img);
            wrap.appendChild(rm);
            addImageThumbs.appendChild(wrap);
        });
    }

    function addFilesToAddImageModal(files) {
        if (!files || !files.length) return;
        var allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            if (f && f.type && allowed.indexOf(f.type) !== -1) addImageModalFiles.push(f);
        }
        renderAddImageModalPreview();
    }

    if (addImageDropZone) {
        addImageDropZone.addEventListener('click', function(e) {
            if (e.target.closest('.add-image-thumb-remove')) return;
            if (addImageModalFileInput) addImageModalFileInput.click();
        });
    }
    if (addImageBrowse) addImageBrowse.addEventListener('click', function() { if (addImageModalFileInput) addImageModalFileInput.click(); });
    if (addImageModalFileInput) {
        addImageModalFileInput.addEventListener('change', function() {
            if (this.files) addFilesToAddImageModal(this.files);
            this.value = '';
        });
    }
    if (document.getElementById('addImageCameraBtn')) {
        document.getElementById('addImageCameraBtn').addEventListener('click', function() {
            if (addImageModalFileInput) addImageModalFileInput.click();
        });
    }
    if (addImageBtnSave) {
        addImageBtnSave.addEventListener('click', function() {
            if (!addImageModalRow || addImageModalFiles.length === 0) {
                alert('Please add at least one image.');
                return;
            }
            var itemId = addImageModalRow.getAttribute('data-item-id') || '0';
            var barcode = addImageModalRow.getAttribute('data-barcode') || '';
            var formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('barcode_no', barcode);
            for (var i = 0; i < addImageModalFiles.length; i++) {
                formData.append('images[]', addImageModalFiles[i]);
            }
            addImageBtnSave.disabled = true;
            addImageBtnSave.textContent = 'Saving...';
            fetch('ajax/upload-stock-journal-images.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    addImageBtnSave.disabled = false;
                    addImageBtnSave.textContent = 'SAVE';
                    if (data.status === 'success' && data.images && data.images.length) {
                        var cell = addImageModalRow.querySelector('td[data-column="images"]');
                        var thumbs = cell && cell.querySelector('.sj-images-thumbs');
                        var imgBox = cell && cell.querySelector('.img-box');
                        if (thumbs) {
                            data.images.forEach(function(o) {
                                var div = document.createElement('div');
                                div.className = 'sj-thumb-wrap';
                                div.setAttribute('data-img-id', o.id);
                                div.setAttribute('data-img-src', o.path);
                                thumbs.appendChild(div);
                            });
                        }
                        if (imgBox && !imgBox.querySelector('img') && data.images[0]) {
                            var o = data.images[0];
                            var newImg = document.createElement('img');
                            newImg.src = o.path;
                            newImg.alt = '';
                            newImg.title = 'Click to view all images';
                            newImg.setAttribute('data-img-id', o.id);
                            newImg.setAttribute('data-img-src', o.path);
                            var newSpan = document.createElement('span');
                            newSpan.className = 'remove-img';
                            newSpan.setAttribute('data-img-id', o.id);
                            newSpan.onclick = function() { deleteStockJournalImage(this); };
                            newSpan.title = 'Delete image';
                            newSpan.innerHTML = '×';
                            imgBox.appendChild(newImg);
                            imgBox.appendChild(newSpan);
                        }
                        alert(data.message || 'Image(s) added.');
                        closeAddImageModal();
                    } else {
                        alert(data.message || 'Upload failed');
                    }
                })
                .catch(function(err) {
                    addImageBtnSave.disabled = false;
                    addImageBtnSave.textContent = 'SAVE';
                    alert('Error: ' + (err.message || 'Upload failed'));
                });
        });
    }

    // Legacy: direct file input on row (kept for compatibility; Add more now opens modal)
    document.querySelectorAll('.sj-img-input').forEach(function(inp) {
        inp.addEventListener('change', function() {
            var files = this.files;
            if (!files || files.length === 0) return;
            var row = document.getElementById('productListTable') && this.closest ? this.closest('tr') : null;
            if (!row) row = document.querySelector('#productListTable tr[data-sj-id="' + (inp.getAttribute('data-sj-id') || '') + '"]');
            if (!row) return;
            var itemId = row.getAttribute('data-item-id') || '0';
            var barcode = row.getAttribute('data-barcode') || '';
            var formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('barcode_no', barcode);
            for (var i = 0; i < files.length; i++) {
                formData.append('images[]', files[i]);
            }
            var cell = row.querySelector('td[data-column="images"]');
            var thumbs = cell && cell.querySelector('.sj-images-thumbs');
            var imgBox = cell && cell.querySelector('.img-box');
            fetch('ajax/upload-stock-journal-images.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'success' && data.images && data.images.length && thumbs) {
                        data.images.forEach(function(o) {
                            var div = document.createElement('div');
                            div.className = 'sj-thumb-wrap';
                            div.setAttribute('data-img-id', o.id);
                            div.setAttribute('data-img-src', o.path);
                            thumbs.appendChild(div);
                        });
                        if (imgBox && !imgBox.querySelector('img') && data.images[0]) {
                            var o = data.images[0];
                            var newImg = document.createElement('img');
                            newImg.src = o.path;
                            newImg.alt = '';
                            newImg.title = 'Click to view all images';
                            newImg.setAttribute('data-img-id', o.id);
                            newImg.setAttribute('data-img-src', o.path);
                            var newSpan = document.createElement('span');
                            newSpan.className = 'remove-img';
                            newSpan.setAttribute('data-img-id', o.id);
                            newSpan.onclick = function() { deleteStockJournalImage(this); };
                            newSpan.title = 'Delete image';
                            newSpan.innerHTML = '×';
                            imgBox.appendChild(newImg);
                            imgBox.appendChild(newSpan);
                        }
                        alert(data.message || 'Image(s) added.');
                    } else {
                        alert(data.message || 'Upload failed');
                    }
                    inp.value = '';
                })
                .catch(function(err) {
                    alert('Error: ' + (err.message || 'Upload failed'));
                    inp.value = '';
                });
        });
    });

    </script>
</body>
</html>
