<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/invoice_item_unique_barcode.php';
require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';

header('Content-Type: application/json');

$tbl_ro = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_orders'");
if (!$tbl_ro || mysqli_num_rows($tbl_ro) === 0) {
    if ($tbl_ro) {
        mysqli_free_result($tbl_ro);
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Repair order tables not found. Please run admin/sql/create_tbl_repair_orders.sql first.'
    ]);
    exit;
}
mysqli_free_result($tbl_ro);

/**
 * Save repair order item images (data URLs) to uploads/repair-order/{order_id}/ and return JSON paths.
 */
function save_repair_order_item_images($group_image, $order_id, $item_id) {
    if (empty($group_image) || $order_id <= 0 || $item_id <= 0) {
        return '';
    }
    $data_urls = [];
    $primary_index = 0;
    if (is_string($group_image) && trim($group_image) !== '') {
        $trimmed = trim($group_image);
        if ($trimmed[0] === '{') {
            $dec = @json_decode($trimmed, true);
            if ($dec && !empty($dec['images']) && is_array($dec['images'])) {
                $data_urls = $dec['images'];
                $p = isset($dec['primary']) ? $dec['primary'] : ($data_urls[0] ?? '');
                $idx = array_search($p, $data_urls, true);
                $primary_index = ($idx !== false) ? $idx : 0;
            } elseif ($dec && !empty($dec['primary'])) {
                $data_urls = [$dec['primary']];
            }
        } else {
            if (preg_match('/^data:image\//', $trimmed)) {
                $data_urls = [$trimmed];
            }
        }
    }
    if (empty($data_urls)) {
        return '';
    }

    $base_dir = dirname(__DIR__) . '/uploads/repair-order/' . (int)$order_id;
    if (!is_dir($base_dir)) {
        if (!@mkdir($base_dir, 0755, true)) {
            return '';
        }
    }
    $paths = [];
    $primary_path = '';
    foreach ($data_urls as $i => $data_url) {
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/s', trim($data_url), $m)) {
            continue;
        }
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $safe_ext = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']) ? $ext : 'png';
        $filename = 'item_' . $item_id . '_' . $i . '.' . $safe_ext;
        $full_path = $base_dir . '/' . $filename;
        $b64 = preg_replace('/\s+/', '', $m[2]);
        $bin = @base64_decode($b64, true);
        if ($bin === false || @file_put_contents($full_path, $bin) === false) {
            continue;
        }
        $relative = 'uploads/repair-order/' . (int)$order_id . '/' . $filename;
        $paths[] = $relative;
        if ($i === $primary_index) {
            $primary_path = $relative;
        }
    }
    if (empty($paths)) {
        return '';
    }
    if ($primary_path === '') {
        $primary_path = $paths[0];
    }
    return json_encode(['primary' => $primary_path, 'images' => $paths]);
}

/**
 * When re-saving, group_image may be full URLs to existing uploads — normalize to DB JSON paths.
 */
function repair_order_group_image_urls_to_db_json($group_image) {
    $trimmed = trim((string)$group_image);
    if ($trimmed === '' || $trimmed[0] !== '{') {
        return '';
    }
    $dec = @json_decode($trimmed, true);
    if (!$dec || empty($dec['images']) || !is_array($dec['images'])) {
        return '';
    }
    $paths = [];
    foreach ($dec['images'] as $img) {
        $img = trim((string)$img);
        if ($img === '' || preg_match('/^data:image\//', $img)) {
            continue;
        }
        if (preg_match('#/(?:admin/)?(uploads/.+)$#i', $img, $m)) {
            $paths[] = $m[1];
        } elseif (preg_match('#^(uploads/.+)$#i', $img, $m)) {
            $paths[] = $m[1];
        }
    }
    if (empty($paths)) {
        return '';
    }
    $primary_path = $paths[0];
    if (!empty($dec['primary'])) {
        $pr = trim((string)$dec['primary']);
        if (preg_match('#/(?:admin/)?(uploads/.+)$#i', $pr, $m)) {
            $primary_path = $m[1];
        } elseif (preg_match('#^(uploads/.+)$#i', $pr, $m)) {
            $primary_path = $m[1];
        }
    }
    return json_encode(['primary' => $primary_path, 'images' => $paths]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    
    // Get order data
    $order_no = esc($_POST['order_no'] ?? '');
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customer_name = esc($_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'AED');
    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $order_date = esc($_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $group_name = esc($_POST['group_name'] ?? '');
    $comment = esc($_POST['comment'] ?? '');
    $department_id = (isset($_POST['department_id']) && $_POST['department_id'] !== '') ? (int)$_POST['department_id'] : null;
    $payment_comments_raw = isset($_POST['payment_comments']) ? $_POST['payment_comments'] : '[]';
    $payment_comments = is_string($payment_comments_raw) ? $payment_comments_raw : json_encode($payment_comments_raw);
    $payment_comments_esc = mysqli_real_escape_string($conn, $payment_comments);
    
    // Summary values
    $previous_balance = (float)($_POST['previous_balance'] ?? 0);
    $previous_gold = (float)($_POST['previous_gold'] ?? 0);
    $previous_silver = (float)($_POST['previous_silver'] ?? 0);
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $additional_amt = (float)($_POST['additional_amt'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $reward_points = (float)($_POST['reward_points'] ?? 0);
    $coupon_code = esc($_POST['coupon_code'] ?? '');
    $coupon_discount = (float)($_POST['coupon_discount'] ?? 0);
    $discount_amt = (float)($_POST['discount_amt'] ?? 0);
    $discount_percent = (float)($_POST['discount_percent'] ?? 0);
    $redeem_points = (float)($_POST['redeem_points'] ?? 0);
    $use_previous_balance = isset($_POST['use_previous_balance']) ? (int)$_POST['use_previous_balance'] : 0;
    $previous_balance_used_amt = (float)($_POST['previous_balance_used_amt'] ?? 0);
    $adjusted_balance_used = (float)($_POST['adjusted_balance_used'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $metal_amt = (float)($_POST['metal_amt'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);
    
    // Check if update or insert
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $is_update = ($order_id > 0);
    
    // Validation
    if (empty($customer_name)) {
        throw new Exception("Customer name is required");
    }
    
    if (empty($order_no)) {
        // Bill Series: voucher "Repair Order" in tbl_bill_series + tbl_voucher_types; else legacy RO-1
        $order_no = function_exists('getNextRepairOrderNo') ? esc(getNextRepairOrderNo($conn)) : 'RO-1';
    }

    // New orders: if order_no already exists, bump until unique (same series pattern)
    if (!$is_update) {
        $cfg = function_exists('getRepairOrderBillSeriesConfig')
            ? getRepairOrderBillSeriesConfig($conn)
            : ['prefix' => 'RO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
        $existing_order = getRecord("SELECT id FROM tbl_repair_orders WHERE order_no = '$order_no'");
        $guard = 0;
        while ($existing_order && $guard < 5000) {
            if (function_exists('bumpRepairOrderNo')) {
                $order_no = esc(bumpRepairOrderNo($conn, $order_no, $cfg));
            } else {
                $order_no = esc(function_exists('getNextRepairOrderNo') ? getNextRepairOrderNo($conn) : ($order_no . '-1'));
            }
            $existing_order = getRecord("SELECT id FROM tbl_repair_orders WHERE order_no = '$order_no'");
            $guard++;
        }
    }
    
    // Ensure department_id on tbl_repair_orders (optional migration)
    $cd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'department_id'");
    if ($cd && mysqli_num_rows($cd) === 0) {
        mysqli_free_result($cd);
        @mysqli_query($conn, "ALTER TABLE `tbl_repair_orders` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`");
    } elseif ($cd) {
        mysqli_free_result($cd);
    }

    // Check optional columns on tbl_repair_orders
    $has_payment_comments = false;
    $has_discount_percent = false;
    $has_use_previous_balance = false;
    $has_previous_balance_used_amt = false;
    $has_adjusted_balance_used = false;
    $has_department_id = false;
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'department_id'");
    if ($c && mysqli_num_rows($c) > 0) {
        $has_department_id = true;
    }
    if ($c) {
        mysqli_free_result($c);
    }
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'payment_comments'");
    if ($c && mysqli_num_rows($c) > 0) { $has_payment_comments = true; } if ($c) mysqli_free_result($c);
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'discount_percent'");
    if ($c && mysqli_num_rows($c) > 0) { $has_discount_percent = true; } if ($c) mysqli_free_result($c);
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'use_previous_balance'");
    if ($c && mysqli_num_rows($c) > 0) { $has_use_previous_balance = true; } if ($c) mysqli_free_result($c);
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'previous_balance_used_amt'");
    if ($c && mysqli_num_rows($c) > 0) { $has_previous_balance_used_amt = true; } if ($c) mysqli_free_result($c);
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_orders LIKE 'adjusted_balance_used'");
    if ($c && mysqli_num_rows($c) > 0) { $has_adjusted_balance_used = true; } if ($c) mysqli_free_result($c);
    
    if ($is_update) {
        // Update existing order
        $extra_update = "";
        if ($has_payment_comments) $extra_update .= ", payment_comments = '$payment_comments_esc'";
        if ($has_discount_percent) $extra_update .= ", discount_percent = $discount_percent";
        if ($has_use_previous_balance) $extra_update .= ", use_previous_balance = $use_previous_balance";
        if ($has_previous_balance_used_amt) $extra_update .= ", previous_balance_used_amt = $previous_balance_used_amt";
        if ($has_adjusted_balance_used) $extra_update .= ", adjusted_balance_used = $adjusted_balance_used";
        if ($has_department_id) {
            $extra_update .= ", department_id = " . (($department_id !== null && $department_id > 0) ? (int)$department_id : "NULL");
        }
        $sql = "
            UPDATE tbl_repair_orders SET
                order_no = '$order_no',
                customer_id = " . ($customer_id > 0 ? $customer_id : "NULL") . ",
                customer_name = '$customer_name',
                against_of = " . ($against_of ? "'$against_of'" : "NULL") . ",
                currency = '$currency',
                ref_no = " . ($ref_no ? "'$ref_no'" : "NULL") . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : "NULL") . ",
                order_date = '$order_date',
                due_date = " . ($due_date ? "'$due_date'" : "NULL") . ",
                layaways_id = " . ($layaways ? (int)$layaways : "NULL") . ",
                fixing_type = '$fixing_type',
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                subtotal = $subtotal,
                additional_amt = $additional_amt,
                net_total = $net_total,
                reward_points = $reward_points,
                coupon_code = " . ($coupon_code ? "'$coupon_code'" : "NULL") . ",
                coupon_discount = $coupon_discount,
                discount_amt = $discount_amt,
                redeem_points = $redeem_points,
                grand_total = $grand_total,
                advance_payment = $advance_payment,
                metal_amt = $metal_amt,
                round_off = $round_off,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                group_name = " . ($group_name ? "'$group_name'" : "NULL") . ",
                comment = " . ($comment ? "'$comment'" : "NULL") . "
                " . ($extra_update ? $extra_update . ", " : ", ") . "
                updated_at = NOW()
            WHERE id = $order_id
        ";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Order update failed: " . mysqli_error($conn));
        }
        
        // Delete existing items and payments
        mysqli_query($conn, "DELETE FROM tbl_repair_order_items WHERE order_id = $order_id");
        mysqli_query($conn, "DELETE FROM tbl_repair_order_payments WHERE order_id = $order_id");
    } else {
        // Insert new order
        $ins_f = "order_no, customer_id, customer_name, against_of, currency, ref_no, sales_person, order_date, due_date, layaways_id, fixing_type, previous_balance, previous_gold, previous_silver, subtotal, additional_amt, net_total, reward_points, coupon_code, coupon_discount, discount_amt, redeem_points, grand_total, advance_payment, metal_amt, round_off, paid_amt, balance_amt, group_name, comment";
        $ins_v = "'$order_no', " . ($customer_id > 0 ? $customer_id : "NULL") . ", '$customer_name', " . ($against_of ? "'$against_of'" : "NULL") . ", '$currency', " . ($ref_no ? "'$ref_no'" : "NULL") . ", " . ($sales_person ? "'$sales_person'" : "NULL") . ", '$order_date', " . ($due_date ? "'$due_date'" : "NULL") . ", " . ($layaways ? (int)$layaways : "NULL") . ", '$fixing_type', $previous_balance, $previous_gold, $previous_silver, $subtotal, $additional_amt, $net_total, $reward_points, " . ($coupon_code ? "'$coupon_code'" : "NULL") . ", $coupon_discount, $discount_amt, $redeem_points, $grand_total, $advance_payment, $metal_amt, $round_off, $paid_amt, $balance_amt, " . ($group_name ? "'$group_name'" : "NULL") . ", " . ($comment ? "'$comment'" : "NULL");
        if ($has_payment_comments) { $ins_f .= ", payment_comments"; $ins_v .= ", '$payment_comments_esc'"; }
        if ($has_discount_percent) { $ins_f .= ", discount_percent"; $ins_v .= ", $discount_percent"; }
        if ($has_use_previous_balance) { $ins_f .= ", use_previous_balance"; $ins_v .= ", $use_previous_balance"; }
        if ($has_previous_balance_used_amt) { $ins_f .= ", previous_balance_used_amt"; $ins_v .= ", $previous_balance_used_amt"; }
        if ($has_adjusted_balance_used) { $ins_f .= ", adjusted_balance_used"; $ins_v .= ", $adjusted_balance_used"; }
        if ($has_department_id) {
            $ins_f .= ", department_id";
            $ins_v .= ", " . (($department_id !== null && $department_id > 0) ? (int)$department_id : "NULL");
        }
        $sql = "INSERT INTO tbl_repair_orders ($ins_f, status, created_by, created_at) VALUES ($ins_v, 'draft', $user_id, NOW())";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Order insert failed: " . mysqli_error($conn));
        }
        
        $order_id = mysqli_insert_id($conn);
    }
    
    // Save order items
    $items = [];
    if (isset($_POST['items'])) {
        if (is_string($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
        } else if (is_array($_POST['items'])) {
            $items = $_POST['items'];
        }
    }
    
    // Optional columns on tbl_repair_order_items (diamond, payment, extra fields)
    $oi_diamond_category = false;
    $oi_metal_rate = false;
    $oi_calculation_type = false;
    $oi_stone_weight = false;
    $oi_metal_value = false;
    $oi_stone_amount = false;
    $oi_diamond_amount = false;
    $oi_location_id = false;
    $oi_metal_qty = false;
    $oi_metal_weight = false;
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'diamond_category'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_diamond_category = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'metal_rate'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_metal_rate = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'calculation_type'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_calculation_type = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'stone_weight'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_stone_weight = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'metal_value'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_metal_value = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'stone_amount'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_stone_amount = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'diamond_amount'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_diamond_amount = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'location_id'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_location_id = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'metal_qty'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_metal_qty = true; } if ($oc) mysqli_free_result($oc);
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'metal_weight'"); if ($oc && mysqli_num_rows($oc) > 0) { $oi_metal_weight = true; } if ($oc) mysqli_free_result($oc);
    $oi_images = false;
    $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'images'");
    if ($oc && mysqli_num_rows($oc) > 0) {
        $oi_images = true;
    }
    if ($oc) {
        mysqli_free_result($oc);
    }
    if (!$oi_images) {
        @mysqli_query($conn, "ALTER TABLE tbl_repair_order_items ADD COLUMN images TEXT NULL DEFAULT NULL COMMENT 'JSON: primary + image paths (repair order line photos)'");
        $oc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_repair_order_items LIKE 'images'");
        if ($oc && mysqli_num_rows($oc) > 0) {
            $oi_images = true;
        }
        if ($oc) {
            mysqli_free_result($oc);
        }
    }

    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';

    if (!empty($items) && is_array($items)) {
        $request_used_barcodes = [];
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $characteristic_id = isset($item['characteristic_id']) ? (int)$item['characteristic_id'] : NULL;
            $barcode = esc($item['barcode'] ?? '');
            $product_name = esc($item['product_name'] ?? '');
            $carat = esc($item['carat'] ?? '');
            $quantity = (float)($item['quantity'] ?? 1);
            $gross_weight = (float)($item['gross_weight'] ?? 0);
            $less_weight = (float)($item['less_weight'] ?? 0);
            $purity = (float)($item['purity'] ?? 0);
            $purity_weight = (float)($item['purity_weight'] ?? 0);
            $final_weight = (float)($item['final_weight'] ?? 0);
            $net_weight = (float)($item['net_weight'] ?? 0);
            $pure_weight = (float)($item['pure_weight'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $making = (float)($item['making'] ?? 0);
            $making_amount = (float)($item['making_amount'] ?? 0);
            $design_no = esc($item['design_no'] ?? '');
            $tax = (float)($item['tax'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
            $net_amount = (float)($item['net_amount'] ?? 0);
            $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
            $stone_charges = (float)($item['stone_charges'] ?? 0);
            $stone_amount = (float)($item['stone_amount'] ?? 0);
            $other_charges = (float)($item['other_charges'] ?? 0);
            $other_amount = (float)($item['other_amount'] ?? 0);
            $diamond_value = (float)($item['diamond_value'] ?? 0);
            $diamond_amount = (float)($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
            $diamond_category = esc($item['category'] ?? $item['diamond_category'] ?? '');
            $calculation_type = esc($item['calculation_type'] ?? $item['calculation'] ?? '');
            $metal_rate = (float)($item['metal_rate'] ?? $item['rate'] ?? 0);
            $stone_weight = (float)($item['stone_weight'] ?? 0);
            $metal_value = (float)($item['metal_value'] ?? 0);
            $location_id = isset($item['location_id']) ? (int)$item['location_id'] : NULL;
            $metal_qty = (float)($item['metal_qty'] ?? 1);
            $metal_weight = (float)($item['metal_weight'] ?? 0);
            $gemstone_value = (float)($item['gemstone_value'] ?? 0);
            $discount = (float)($item['discount'] ?? 0);
            $purchase_amount = (float)($item['purchase_amount'] ?? 0);
            $sale_amount = (float)($item['sale_amount'] ?? 0);
            $sale_amount_with = (float)($item['sale_amount_with'] ?? 0);
            $reverse = (float)($item['reverse'] ?? 0);
            
            if ($product_id > 0) {
                $barcode = esc(auragold_resolve_unique_invoice_item_barcode($conn, $item, $request_used_barcodes));
                $item_cols = "order_id, product_id, product_characteristic_id, barcode, product_name, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, design_no, status, created_at";
                $item_vals = "$order_id, $product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ", " . ($barcode ? "'$barcode'" : "NULL") . ", '$product_name', " . ($carat ? "'$carat'" : "NULL") . ", $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax, $net_amount, $net_amt_with_tax, " . ($design_no ? "'$design_no'" : "NULL") . ", 1, NOW()";
                if ($oi_diamond_category) { $item_cols .= ", diamond_category"; $item_vals .= ", " . ($diamond_category ? "'" . mysqli_real_escape_string($conn, $diamond_category) . "'" : "NULL"); }
                if ($oi_metal_rate) { $item_cols .= ", metal_rate"; $item_vals .= ", $metal_rate"; }
                if ($oi_calculation_type) { $item_cols .= ", calculation_type"; $item_vals .= ", " . ($calculation_type ? "'" . mysqli_real_escape_string($conn, $calculation_type) . "'" : "NULL"); }
                if ($oi_stone_weight) { $item_cols .= ", stone_weight"; $item_vals .= ", $stone_weight"; }
                if ($oi_metal_value) { $item_cols .= ", metal_value"; $item_vals .= ", $metal_value"; }
                if ($oi_stone_amount) { $item_cols .= ", stone_amount"; $item_vals .= ", $stone_amount"; }
                if ($oi_diamond_amount) { $item_cols .= ", diamond_amount"; $item_vals .= ", $diamond_amount"; }
                if ($oi_location_id) { $item_cols .= ", location_id"; $item_vals .= ", " . ($location_id ? $location_id : "NULL"); }
                if ($oi_metal_qty) { $item_cols .= ", metal_qty"; $item_vals .= ", $metal_qty"; }
                if ($oi_metal_weight) { $item_cols .= ", metal_weight"; $item_vals .= ", $metal_weight"; }
                $item_sql = "INSERT INTO tbl_repair_order_items ($item_cols) VALUES ($item_vals)";
                
                if (!mysqli_query($conn, $item_sql)) {
                    throw new Exception("Item insert failed: " . mysqli_error($conn));
                }
                $item_row_id = mysqli_insert_id($conn);
                $group_image_raw = isset($item['group_image']) ? $item['group_image'] : '';
                if ($oi_images && $item_row_id > 0 && $group_image_raw !== '') {
                    $images_json = save_repair_order_item_images($group_image_raw, $order_id, $item_row_id);
                    if ($images_json === '') {
                        $images_json = repair_order_group_image_urls_to_db_json($group_image_raw);
                    }
                    if ($images_json !== '') {
                        $images_esc = mysqli_real_escape_string($conn, $images_json);
                        mysqli_query($conn, "UPDATE tbl_repair_order_items SET images = '$images_esc' WHERE id = $item_row_id");
                    }
                }

                $metal_id_ro = 0;
                if ($characteristic_id) {
                    $ch_ro = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = " . (int) $characteristic_id . " AND status = 1");
                    if ($ch_ro) {
                        $metal_id_ro = (int) ($ch_ro['metal_id'] ?? 0);
                    }
                }
                if ($metal_id_ro <= 0) {
                    $dm_ro = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 ORDER BY id DESC LIMIT 1");
                    if ($dm_ro) {
                        $metal_id_ro = (int) ($dm_ro['metal_id'] ?? 0);
                    }
                }
                $sj_no_ro = 'RO' . (int) $order_id . 'I' . (int) $item_row_id;
                if (strlen($sj_no_ro) > 48) {
                    $sj_no_ro = 'R' . (int) $order_id . 'x' . (int) $item_row_id;
                }
                auragold_stock_history_audit_insert_row($conn, [
                    'sj_invoice_no' => $sj_no_ro,
                    'item_id' => 0,
                    'invoice_id' => 0,
                    'invoice_no' => $order_no,
                    'sj_date' => $order_date,
                    'barcode' => $barcode,
                    'product_id' => $product_id,
                    'product_characteristic_id' => $characteristic_id ? (int) $characteristic_id : 0,
                    'product_name' => $product_name,
                    'metal_id' => $metal_id_ro,
                    'metal_type' => auragold_stock_history_metal_type($conn, $metal_id_ro),
                    'quantity' => $quantity,
                    'gross_weight' => $gross_weight,
                    'less_weight' => $less_weight,
                    'net_weight' => $net_weight,
                    'purity' => $purity,
                    'purity_weight' => $purity_weight,
                    'pure_weight' => $pure_weight,
                    'final_weight' => $final_weight,
                    'rate' => $rate,
                    'amount' => $amount,
                    'making_amount' => $making_amount,
                    'tax_amount' => $tax,
                    'net_amount' => $net_amount,
                    'net_amt_with_tax' => $net_amt_with_tax,
                    'rfid_code' => '',
                    'voucher_type' => 'Repair Order',
                    'design_no' => $design_no,
                    'category' => $diamond_category,
                    'comment' => 'auragold_doc|src=ro|oid=' . (int) $order_id . '|roi=' . (int) $item_row_id . '|',
                ]);
            }
        }
    }
    
    // Save payments
    $payments = [];
    if (isset($_POST['payments'])) {
        if (is_string($_POST['payments'])) {
            $payments = json_decode($_POST['payments'], true);
        } else if (is_array($_POST['payments'])) {
            $payments = $_POST['payments'];
        }
    }
    
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $payment) {
            $payment_type = esc($payment['payment_type'] ?? '');
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $transaction_no = esc($payment['transaction_no'] ?? '');
            $cheque_date = isset($payment['cheque_date']) && $payment['cheque_date'] ? esc($payment['cheque_date']) : NULL;
            $purity_carat = esc($payment['purity_carat'] ?? '');
            $amount = (float)($payment['amount'] ?? 0); // Total amount (current order + previous balance)
            $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
            $current_order_amount = (float)($payment['current_order_amount'] ?? ($amount - $previous_balance_amount));
            $diamond_category = esc($payment['diamond_category'] ?? '');
            $quantity = (float)($payment['quantity'] ?? 0);
            
            if ($amount > 0) {
                // Try to insert with previous_balance_amount column (if it exists)
                $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
                
                $payment_sql = "
                    INSERT INTO tbl_repair_order_payments (
                        order_id, payment_type, deposit_into, transaction_no,
                        cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity,
                        status, created_at
                    ) VALUES (
                        $order_id, '$payment_type',
                        " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                        " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                        " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                        " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                        $amount,
                        $previous_balance_amount,
                        " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                        $quantity,
                        1, NOW()
                    )
                ";
                
                // If insert fails due to missing column, try without previous_balance_amount
                if (!mysqli_query($conn, $payment_sql)) {
                    $error = mysqli_error($conn);
                    if (strpos($error, 'previous_balance_amount') !== false) {
                        // Column doesn't exist, insert without it (will need to add column to table)
                        $payment_sql = "
                            INSERT INTO tbl_repair_order_payments (
                                order_id, payment_type, deposit_into, transaction_no,
                                cheque_date, purity_carat, amount, diamond_category, quantity,
                                status, created_at
                            ) VALUES (
                                $order_id, '$payment_type',
                                " . ($deposit_into ? "'$deposit_into'" : "NULL") . ",
                                " . ($transaction_no ? "'$transaction_no'" : "NULL") . ",
                                " . ($cheque_date ? "'$cheque_date'" : "NULL") . ",
                                " . ($purity_carat ? "'$purity_carat'" : "NULL") . ",
                                $amount,
                                " . ($diamond_category ? "'$diamond_category'" : "NULL") . ",
                                $quantity,
                                1, NOW()
                            )
                        ";
                        if (!mysqli_query($conn, $payment_sql)) {
                            throw new Exception("Payment insert failed: " . mysqli_error($conn));
                        }
                    } else {
                        throw new Exception("Payment insert failed: " . $error);
                    }
                }
            }
        }
    }
    
    // ================== CUSTOMER LEDGER UPDATE ==================
    // Only update ledger for new orders (not updates) to prevent duplicate entries
    // For repair orders: customer owes us money
    // Debit = grand_total (what customer owes)
    // Credit = paid_amt (what customer paid)
    
    if (!$is_update) {
    
    // Get previous balance from customer ledger
    $previous_balance_record = null;
    if ($customer_id > 0) {
        $previous_balance_record = getRecord("
            SELECT balance_amount, balance_gold, balance_silver 
            FROM tbl_customer_ledger 
            WHERE customer_id = $customer_id 
            AND status = 1 
            ORDER BY transaction_date DESC, id DESC 
            LIMIT 1
        ");
        
        // If not found in ledger, try balance table
        if (!$previous_balance_record) {
            $previous_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_balance 
                WHERE customer_id = $customer_id 
                LIMIT 1
            ");
        }
    } else if (!empty($customer_name)) {
        $previous_balance_record = getRecord("
            SELECT balance_amount, balance_gold, balance_silver 
            FROM tbl_customer_ledger 
            WHERE customer_name = '$customer_name' 
            AND status = 1 
            ORDER BY transaction_date DESC, id DESC 
            LIMIT 1
        ");
        
        // If not found in ledger, try balance table
        if (!$previous_balance_record) {
            $previous_balance_record = getRecord("
                SELECT balance_amount, balance_gold, balance_silver 
                FROM tbl_customer_balance 
                WHERE customer_name = '$customer_name' 
                LIMIT 1
            ");
        }
    }
    
    // Calculate previous balance (default to 0 if not found)
    $prev_balance_amount = (float)($previous_balance_record['balance_amount'] ?? $previous_balance);
    $prev_balance_gold = (float)($previous_balance_record['balance_gold'] ?? $previous_gold);
    $prev_balance_silver = (float)($previous_balance_record['balance_silver'] ?? $previous_silver);
    
    // For repair orders: customer owes us (debit = what customer owes, credit = 0 for repair order entry)
    // Payments will be separate credit entries
    $ledger_debit_amount = $grand_total; // Customer owes this amount
    $ledger_credit_amount = 0;           // Repair order entry has no credit (payments are separate entries)
    
    // Calculate new running balance for repair order entry
    // Balance = Previous Balance + Order Amount (no payment deduction here, payments are separate entries)
    $new_balance_amount = $prev_balance_amount + $ledger_debit_amount;
    
    // Calculate gold and silver weights from items
    $total_gold_weight = 0;
    $total_silver_weight = 0;
    if (!empty($items) && is_array($items)) {
        foreach ($items as $item) {
            $item_pure_weight = (float)($item['pure_weight'] ?? 0);
            $item_purity = (float)($item['purity'] ?? 0);
            
            // Determine if gold or silver based on purity (typically gold is 916, 750, etc., silver is 925, etc.)
            if ($item_purity >= 750) {
                // Gold (750, 916, 999, etc.)
                $total_gold_weight += $item_pure_weight;
            } else if ($item_purity >= 500) {
                // Silver (500, 925, etc.)
                $total_silver_weight += $item_pure_weight;
            }
        }
    }
    
    $new_balance_gold = $prev_balance_gold + $total_gold_weight;
    $new_balance_silver = $prev_balance_silver + $total_silver_weight;
    
    // Insert ledger entry for repair order
    $ledger_sql = "
        INSERT INTO tbl_customer_ledger (
            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount, 
            debit_gold, credit_gold, debit_silver, credit_silver,
            balance_amount, balance_gold, balance_silver,
            description, reference_no, against_ledger, against_invoice_no,
            status, created_by, created_at
        ) VALUES (
            " . ($customer_id > 0 ? $customer_id : 0) . ",
            '$customer_name',
            'repair_order',
            $order_id,
            '$order_no',
            '$order_date',
            $ledger_debit_amount,
            $ledger_credit_amount,
            $total_gold_weight,
            0.000,
            $total_silver_weight,
            0.000,
            $new_balance_amount,
            $new_balance_gold,
            $new_balance_silver,
            'Repair Order: $order_no',
            " . ($ref_no ? "'$ref_no'" : "NULL") . ",
            " . ($against_of ? "'$against_of'" : "NULL") . ",
            '$order_no',
            1,
            $user_id,
            NOW()
        )
    ";
    
    if (!mysqli_query($conn, $ledger_sql)) {
        throw new Exception("Ledger entry failed: " . mysqli_error($conn));
    }
    
    // If there are payments, create separate ledger entries for each payment
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $payment) {
            $total_payment_amount = (float)($payment['amount'] ?? 0);
            $previous_balance_amount = (float)($payment['previous_balance_amount'] ?? 0);
            $current_order_amount = (float)($payment['current_order_amount'] ?? ($total_payment_amount - $previous_balance_amount));
            $pay_type_raw = strtolower(trim((string) ($payment['payment_type'] ?? '')));
            $deposit_into = esc($payment['deposit_into'] ?? '');
            $payment_type = esc($payment['payment_type'] ?? '');
            $is_cheque_payment = auragold_payment_is_cheque_type($pay_type_raw);
            
            // Process previous balance payment first (if any)
            if ($previous_balance_amount > 0) {
                // Get the balance before the repair order (previous balance)
                $prev_balance_before_order = getRecord("
                    SELECT balance_amount, balance_gold, balance_silver 
                    FROM tbl_customer_ledger 
                    WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . "
                    AND customer_name = '$customer_name' 
                    AND status = 1 
                    AND transaction_type != 'repair_order'
                    ORDER BY transaction_date DESC, id DESC 
                    LIMIT 1
                ");
                $prev_bal_amt = (float)($prev_balance_before_order['balance_amount'] ?? $prev_balance_amount);
                $prev_bal_gold = (float)($prev_balance_before_order['balance_gold'] ?? $prev_balance_gold);
                $prev_bal_silver = (float)($prev_balance_before_order['balance_silver'] ?? $prev_balance_silver);
                
                $new_prev_balance = $prev_bal_amt - $previous_balance_amount;
                
                // Create ledger entry for previous balance payment
                $prev_balance_payment_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, against_ledger, against_invoice_no,
                        status, created_by, created_at
                    ) VALUES (
                        " . ($customer_id > 0 ? $customer_id : 0) . ",
                        '$customer_name',
                        'payment',
                        $order_id,
                        '$order_no',
                        '$order_date',
                        0.00,
                        $previous_balance_amount,
                        $new_prev_balance,
                        $prev_bal_gold,
                        $prev_bal_silver,
                        'Payment for Previous Balance - Repair Order: $order_no',
                        '" . ($is_cheque_payment ? mysqli_real_escape_string($conn, auragold_cheque_payment_against_label($previous_balance_amount, 'receivable')) : $deposit_into) . "',
                        'Previous Balance',
                        1,
                        $user_id,
                        NOW()
                    )
                ";
                
                if (!mysqli_query($conn, $prev_balance_payment_sql)) {
                    throw new Exception("Previous balance payment ledger entry failed: " . mysqli_error($conn));
                }
            }
            
            // Process current order payment
            if ($current_order_amount > 0) {
                // Get the last balance from database (should be from the repair order entry we just inserted)
                $last_balance_record = getRecord("
                    SELECT balance_amount, balance_gold, balance_silver 
                    FROM tbl_customer_ledger 
                    WHERE customer_id = " . ($customer_id > 0 ? $customer_id : 0) . "
                    AND customer_name = '$customer_name' 
                    AND status = 1 
                    ORDER BY transaction_date DESC, id DESC 
                    LIMIT 1
                ");
                $last_balance_amount = (float)($last_balance_record['balance_amount'] ?? $new_balance_amount);
                $last_balance_gold = (float)($last_balance_record['balance_gold'] ?? $new_balance_gold);
                $last_balance_silver = (float)($last_balance_record['balance_silver'] ?? $new_balance_silver);
                
                // Recalculate balance after each payment (for current order only)
                $new_balance_amount = $last_balance_amount - $current_order_amount;
                $new_balance_gold = $last_balance_gold;
                $new_balance_silver = $last_balance_silver;
                
                // 1. Create customer ledger entry (credit entry for customer payment)
                $payment_ledger_sql = "
                    INSERT INTO tbl_customer_ledger (
                        customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                        transaction_date, debit_amount, credit_amount,
                        balance_amount, balance_gold, balance_silver,
                        description, against_ledger, against_invoice_no,
                        status, created_by, created_at
                    ) VALUES (
                        " . ($customer_id > 0 ? $customer_id : 0) . ",
                        '$customer_name',
                        'payment',
                        $order_id,
                        '$order_no',
                        '$order_date',
                        0.00,
                        $current_order_amount,
                        $new_balance_amount,
                        $last_balance_gold,
                        $last_balance_silver,
                        'Payment for Repair Order: $order_no',
                        '" . ($is_cheque_payment ? mysqli_real_escape_string($conn, auragold_cheque_payment_against_label($current_order_amount, 'receivable')) : $deposit_into) . "',
                        '$order_no',
                        1,
                        $user_id,
                        NOW()
                    )
                ";
                
                if (!mysqli_query($conn, $payment_ledger_sql)) {
                    throw new Exception("Payment ledger entry failed: " . mysqli_error($conn));
                }
                
                // 2. Create Cash/Payment Account ledger entry (debit entry for Cash/Bank)
                if (!empty($deposit_into) && auragold_payment_should_post_deposit_ledger($pay_type_raw)) {
                    // Get Cash/Bank account balance
                    $cash_balance_record = getRecord("
                        SELECT balance_amount 
                        FROM tbl_customer_ledger 
                        WHERE customer_name = '$deposit_into' 
                        AND status = 1 
                        ORDER BY transaction_date DESC, id DESC 
                        LIMIT 1
                    ");
                    $cash_prev_balance = (float)($cash_balance_record['balance_amount'] ?? 0);
                    $cash_new_balance = $cash_prev_balance + $total_payment_amount; // Cash increases when customer pays (total = current + previous)
                    
                    $cash_ledger_sql = "
                        INSERT INTO tbl_customer_ledger (
                            customer_id, customer_name, transaction_type, transaction_id, transaction_no,
                            transaction_date, debit_amount, credit_amount,
                            balance_amount, description, against_ledger, against_invoice_no,
                            status, created_by, created_at
                        ) VALUES (
                            0,
                            '$deposit_into',
                            'payment',
                            $order_id,
                            '$order_no',
                            '$order_date',
                            $total_payment_amount,
                            0.00,
                            $cash_new_balance,
                            'Payment from $customer_name for Repair Order: $order_no',
                            '$customer_name',
                            '$order_no',
                            1,
                            $user_id,
                            NOW()
                        )
                    ";
                    
                    if (!mysqli_query($conn, $cash_ledger_sql)) {
                        throw new Exception("Cash ledger entry failed: " . mysqli_error($conn));
                    }
                }
            }
        }
    }
    
    // Update customer balance summary with final running balance
    $existing_balance = null;
    if ($customer_id > 0) {
        $existing_balance = getRecord("
            SELECT id FROM tbl_customer_balance 
            WHERE customer_id = $customer_id 
            LIMIT 1
        ");
    } else if (!empty($customer_name)) {
        $existing_balance = getRecord("
            SELECT id FROM tbl_customer_balance 
            WHERE customer_name = '$customer_name' 
            LIMIT 1
        ");
    }
    
    if ($existing_balance) {
        // Update existing record
        if ($customer_id > 0) {
            $balance_update_sql = "
                UPDATE tbl_customer_balance SET
                    balance_amount = $new_balance_amount,
                    balance_gold = $new_balance_gold,
                    balance_silver = $new_balance_silver,
                    last_transaction_date = '$order_date',
                    last_updated = NOW()
                WHERE customer_id = $customer_id
            ";
        } else {
            $balance_update_sql = "
                UPDATE tbl_customer_balance SET
                    balance_amount = $new_balance_amount,
                    balance_gold = $new_balance_gold,
                    balance_silver = $new_balance_silver,
                    last_transaction_date = '$order_date',
                    last_updated = NOW()
                WHERE customer_name = '$customer_name'
            ";
        }
        
        if (!mysqli_query($conn, $balance_update_sql)) {
            throw new Exception("Balance update failed: " . mysqli_error($conn));
        }
    } else {
        // Insert new record
        if ($customer_id > 0) {
            $balance_insert_sql = "
                INSERT INTO tbl_customer_balance (
                    customer_id, customer_name, balance_amount, balance_gold, balance_silver,
                    last_transaction_date
                ) VALUES (
                    $customer_id, '$customer_name', $new_balance_amount, 
                    $new_balance_gold, $new_balance_silver,
                    '$order_date'
                )
            ";
        } else {
            $balance_insert_sql = "
                INSERT INTO tbl_customer_balance (
                    customer_id, customer_name, balance_amount, balance_gold, balance_silver,
                    last_transaction_date
                ) VALUES (
                    0, '$customer_name', $new_balance_amount, 
                    $new_balance_gold, $new_balance_silver,
                    '$order_date'
                )
            ";
        }
        
        if (!mysqli_query($conn, $balance_insert_sql)) {
            throw new Exception("Balance insert failed: " . mysqli_error($conn));
        }
    }
    // ================== END CUSTOMER LEDGER UPDATE ==================
    }

    require_once __DIR__ . '/../includes/auragold_voucher_cheque_entry_sync.php';
    if (function_exists('auragold_sync_voucher_cheque_entries')) {
        auragold_sync_voucher_cheque_entries($conn, [
            'voucher_no' => $order_no,
            'voucher_type' => 'Repair Order',
            'voucher_date' => $order_date,
            'account_ledger' => $customer_name,
            'transaction_id' => (int) $order_id,
            'user_id' => (int) $user_id,
            'payments' => isset($payments) && is_array($payments) ? $payments : [],
            'pdc_direction' => 'receivable',
        ]);
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Order saved successfully',
        'order_id' => $order_id,
        'invoice_id' => $order_id,
        'order_no' => $order_no
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

