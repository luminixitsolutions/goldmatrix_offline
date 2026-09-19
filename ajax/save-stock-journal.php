<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once dirname(__DIR__) . '/includes/auragold_extra_fields_item_values.php';

$sj_save_effective_branch = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@error_reporting(E_ALL);

if (!defined('AURAGOLD_STOCK_JOURNAL_INTERNAL_SAVE')) {
    header('Content-Type: application/json');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// Create tbl_stock_journal_images and upload dir if needed
$create_images_table = "CREATE TABLE IF NOT EXISTS `tbl_stock_journal_images` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_id` INT(11) NOT NULL DEFAULT 0,
  `barcode_no` VARCHAR(100) NOT NULL DEFAULT '',
  `image_path` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_barcode` (`item_id`, `barcode_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn instanceof mysqli) {
    @mysqli_query($conn, $create_images_table);
}

$upload_base = dirname(__DIR__) . '/uploads/stock_journal';
$upload_dir_stock = dirname(__DIR__) . '/uploads/stock';
if (!is_dir($upload_base)) {
    @mkdir(dirname(__DIR__) . '/uploads', 0755, true);
    @mkdir($upload_base, 0755, true);
}
if (!is_dir($upload_dir_stock)) {
    @mkdir($upload_dir_stock, 0775, true);
}

/**
 * Upload stock journal images for a row. Returns array of saved relative paths.
 * @param array $files Array of $_FILES item (single file) or array of such
 * @param int $item_id
 * @param string $barcode_no
 * @param bool $delete_temp_excel_sources If true, remove source file after copy when it lives under uploads/temp_excel/ (Excel import preview).
 * @return array Paths saved (relative to web root, e.g. uploads/stock_journal/...)
 */
function uploadStockImages($files, $item_id, $barcode_no, $delete_temp_excel_sources = false) {
    global $conn, $upload_base;
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $max_size = (defined('AURAGOLD_STOCK_JOURNAL_INTERNAL_SAVE') && AURAGOLD_STOCK_JOURNAL_INTERNAL_SAVE) ? (8 * 1024 * 1024) : (2 * 1024 * 1024);
    $ext_ok = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $saved = [];
    if (!is_array($files) || empty($files)) {
        return $saved;
    }
    // Normalize: may be array of single-file arrays (from multiple input names)
    $list = [];
    foreach ($files as $f) {
        if (isset($f['tmp_name']) && is_string($f['tmp_name']) && $f['tmp_name'] !== '') {
            $list[] = $f;
        } elseif (isset($f['tmp_name']) && is_array($f['tmp_name'])) {
            foreach ($f['tmp_name'] as $i => $tmp) {
                if ($tmp !== '') {
                    $list[] = [
                        'tmp_name' => $tmp,
                        'name' => $f['name'][$i] ?? '',
                        'type' => $f['type'][$i] ?? '',
                        'error' => $f['error'][$i] ?? 0,
                        'size' => $f['size'][$i] ?? 0
                    ];
                }
            }
        }
    }
    foreach ($list as $file) {
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) continue;
        $size = (int)($file['size'] ?? 0);
        if ($size > $max_size) continue;
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || $tmp === '' || !is_readable($tmp)) {
            continue;
        }
        $name = $file['name'] ?? 'image.jpg';
        $type = strtolower((string) ($file['type'] ?? ''));
        if ($type === '' || $type === 'application/octet-stream') {
            if (function_exists('mime_content_type')) {
                $mt = @mime_content_type($tmp);
                if (is_string($mt) && $mt !== '') {
                    $type = strtolower($mt);
                }
            }
        }
        if (!in_array($type, $allowed, true)) {
            $extHint = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $extToMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
            if ($extHint !== '' && isset($extToMime[$extHint])) {
                $type = $extToMime[$extHint];
            }
        }
        if (!in_array($type, $allowed, true)) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $ext_ok, true)) {
            $mimeToExt = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $ext = $mimeToExt[$type] ?? 'jpg';
        }
        if (!in_array($ext, $ext_ok, true)) {
            continue;
        }
        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($name, PATHINFO_FILENAME) . '.' . $ext);
        $unique = date('YmdHis') . '_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8) . '_' . $safe_name;
        $dest = $upload_base . '/' . $unique;
        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : false;
        if (!$moved && is_string($tmp) && $tmp !== '' && is_readable($tmp)) {
            $moved = @copy($tmp, $dest);
        }
        if (!$moved) {
            continue;
        }
        $relative = 'uploads/stock_journal/' . $unique;
        $saved[] = $relative;
        $item_id = (int)$item_id;
        if (!($conn instanceof mysqli)) {
            continue;
        }
        $barcode_esc = mysqli_real_escape_string($conn, $barcode_no);
        $path_esc = mysqli_real_escape_string($conn, $relative);
        mysqli_query($conn, "INSERT INTO tbl_stock_journal_images (item_id, barcode_no, image_path, created_at) VALUES ($item_id, '$barcode_esc', '$path_esc', NOW())");
        if ($delete_temp_excel_sources) {
            $treal = is_string($tmp) ? @realpath($tmp) : false;
            $tbase = @realpath(dirname(__DIR__) . '/uploads/temp_excel');
            if ($treal && $tbase && strpos($treal, $tbase) === 0 && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
    return $saved;
}

try {
    if (!($conn instanceof mysqli)) {
        throw new Exception('Database connection not available');
    }
    mysqli_begin_transaction($conn);

    if (function_exists('auragold_ensure_stock_journal_audit_columns')) {
        auragold_ensure_stock_journal_audit_columns($conn);
    }

    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;
    
    $data = null;
    $row_images = []; // row_index => array of uploaded file info for that row
    
    if (!empty($_POST['data']) && is_string($_POST['data'])) {
        // FormData submit (with images)
        $data = json_decode($_POST['data'], true);
        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $f) {
                if (preg_match('/^images_(\d+)_(\d+)$/', $key, $m)) {
                    $row_index = (int)$m[1];
                    if (!isset($row_images[$row_index])) $row_images[$row_index] = [];
                    if (isset($f['tmp_name']) && $f['tmp_name'] !== '') {
                        $row_images[$row_index][] = $f;
                    } elseif (isset($f['tmp_name']) && is_array($f['tmp_name'])) {
                        foreach ($f['tmp_name'] as $i => $tmp) {
                            if ($tmp !== '') {
                                $row_images[$row_index][] = [
                                    'tmp_name' => $tmp,
                                    'name' => $f['name'][$i] ?? '',
                                    'type' => $f['type'][$i] ?? '',
                                    'error' => $f['error'][$i] ?? 0,
                                    'size' => $f['size'][$i] ?? 0
                                ];
                            }
                        }
                    }
                }
            }
        }
    } else {
        // JSON submit (no files)
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
    }
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $journal_date = esc($data['date'] ?? date('Y-m-d'));
    $item_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;
    $group_name = esc($data['group_name'] ?? '');
    $comment = esc($data['comment'] ?? '');
    $products = $data['products'] ?? [];
    $is_edit = isset($data['edit']) && ($data['edit'] === true || $data['edit'] === 'true');
    $selected_barcodes = isset($data['selected_barcodes']) && is_array($data['selected_barcodes']) ? $data['selected_barcodes'] : [];
    $selected_barcodes = array_values(array_filter(array_map('trim', $selected_barcodes)));
    $opening_barcode = isset($data['opening_barcode']) ? trim($data['opening_barcode']) : '';
    $merge_product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
    $merge_characteristic_id = isset($data['characteristic_id']) ? (int)$data['characteristic_id'] : 0;

    if (is_array($products) && !empty($products)) {
        $batch_barcodes = [];
        foreach ($products as $prow) {
            $b = trim((string) ($prow['barcode'] ?? ''));
            if ($b === '') {
                continue;
            }
            if (isset($batch_barcodes[$b])) {
                throw new Exception('Duplicate barcode in this save: ' . $b);
            }
            $batch_barcodes[$b] = true;
        }
    }

    if (!empty($selected_barcodes) && $opening_barcode !== '' && $merge_product_id > 0) {
        $in_list = implode(', ', array_map(function ($b) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $b) . "'";
        }, $selected_barcodes));
        $ref_barcodes_str = implode(',', $selected_barcodes);
        $ref_esc = mysqli_real_escape_string($conn, $ref_barcodes_str);
        $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_barcodes'");
        if (!$rc || mysqli_num_rows($rc) === 0) {
            if ($rc) mysqli_free_result($rc);
            @mysqli_query($conn, "ALTER TABLE tbl_stock ADD COLUMN reference_barcodes VARCHAR(500) NULL DEFAULT NULL");
        } elseif ($rc) mysqli_free_result($rc);
        $sum = getRecord("SELECT SUM(current_qty) AS total_qty, SUM(current_weight) AS total_weight, SUM(value) AS total_value, MAX(branch_id) AS branch_id, MAX(metal_id) AS metal_id, MAX(product_characteristic_id) AS product_characteristic_id, MAX(opening_purity) AS opening_purity, MAX(rate) AS rate FROM tbl_stock WHERE barcode IN ($in_list) AND stock_type IN ('purchase','opening') AND status = 1");
        if (!$sum || ((float)($sum['total_qty'] ?? 0) == 0 && (float)($sum['total_weight'] ?? 0) == 0)) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'No matching inward records or already zero']);
            exit;
        }
        $total_qty = (float)($sum['total_qty'] ?? 0);
        $total_weight = (float)($sum['total_weight'] ?? 0);
        $total_value = (float)($sum['total_value'] ?? 0);
        $branch_id = (int)($sum['branch_id'] ?? 0);
        $metal_id = (int)($sum['metal_id'] ?? 0);
        $char_id = (int)($sum['product_characteristic_id'] ?? 0);
        if ($merge_characteristic_id > 0) $char_id = $merge_characteristic_id;
        $opening_purity = (float)($sum['opening_purity'] ?? 0);
        $rate = (float)($sum['rate'] ?? 0);
        $primary_row = getRecord("SELECT barcode FROM tbl_stock WHERE product_id = $merge_product_id AND branch_id = $branch_id AND stock_type = 'opening' ORDER BY id ASC LIMIT 1");
        $primary_barcode = $primary_row && !empty(trim($primary_row['barcode'] ?? '')) ? trim($primary_row['barcode']) : '';
        if ($primary_barcode === '') {
            $primary_row = getRecord("SELECT barcode FROM tbl_stock WHERE product_id = $merge_product_id AND stock_type = 'opening' ORDER BY id ASC LIMIT 1");
            $primary_barcode = $primary_row && !empty(trim($primary_row['barcode'] ?? '')) ? trim($primary_row['barcode']) : '';
        }
        $primary_esc = $primary_barcode !== '' ? "'" . mysqli_real_escape_string($conn, $primary_barcode) . "'" : "NULL";
        $has_ref_col = false;
        $rcc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_barcodes'");
        if ($rcc && mysqli_num_rows($rcc) > 0) $has_ref_col = true;
        if ($rcc) mysqli_free_result($rcc);
        $has_ref_id = false;
        $rid = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        if ($rid && mysqli_num_rows($rid) >= 2) $has_ref_id = true;
        if ($rid) mysqli_free_result($rid);
        $ins_cols = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at, status";
        $ins_vals = "$merge_product_id, " . ($char_id ? $char_id : "NULL") . ", $primary_esc, $branch_id, $metal_id, $total_weight, $opening_purity, $total_qty, $total_weight, $rate, $total_value, $total_weight, $total_qty, 'outward', CURDATE(), NOW(), 1";
        if ($has_ref_col) {
            $ins_cols .= ", reference_barcodes";
            $ins_vals .= ", '$ref_esc'";
        }
        if ($has_ref_id) {
            $ins_cols .= ", reference_id, reference_type";
            $ins_vals .= ", NULL, NULL";
        }
        if (!mysqli_query($conn, "INSERT INTO tbl_stock ($ins_cols) VALUES ($ins_vals)")) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Outward insert failed: ' . mysqli_error($conn)]);
            exit;
        }
        if (!mysqli_query($conn, "UPDATE tbl_stock SET current_qty = 0, current_weight = 0 WHERE barcode IN ($in_list) AND stock_type IN ('purchase','opening') AND status = 1")) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Update inward failed: ' . mysqli_error($conn)]);
            exit;
        }
        mysqli_commit($conn);
        if (isset($_SESSION['excel_import_data']) && is_array($_SESSION['excel_import_data'])) {
            unset($_SESSION['excel_import_data']);
        }
        echo json_encode(['status' => 'success', 'message' => 'Outward created', 'saved_count' => 1]);
        exit;
    }

    if (empty($products)) {
        throw new Exception('No products to save');
    }
    
    $has_reference_cols = false;
    $ref_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
    if ($ref_cols && mysqli_num_rows($ref_cols) >= 2) {
        $has_reference_cols = true;
    }
    if ($ref_cols) mysqli_free_result($ref_cols);

    $has_stock_journal_id = false;
    $sjid_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
    if ($sjid_col && mysqli_num_rows($sjid_col) > 0) $has_stock_journal_id = true;
    if ($sjid_col) mysqli_free_result($sjid_col);

    // ---------- EDIT MODE: Delete previous inward/outward by reference, then reinsert (prevent double deduction) ----------
    if ($is_edit) {
        if ($item_id <= 0) {
            throw new Exception('Edit mode requires item_id');
        }
        $updated_count = 0;
        foreach ($products as $product) {
            $barcode = esc($product['barcode'] ?? '');
            if (empty($barcode)) continue;
            $quantity = (float)($product['quantity'] ?? 0);
            $gross_weight = (float)($product['gross_weight'] ?? 0);
            $final_weight = (float)($product['final_weight'] ?? 0);
            $net_weight = (float)($product['net_weight'] ?? 0);
            // Use user-entered gross weight first so inward/outward show what user entered (e.g. 10 not 7.5)
            $stock_weight = $gross_weight > 0 ? $gross_weight : ($final_weight > 0 ? $final_weight : $net_weight);
            
            $sj = getRecord("SELECT id, product_id, product_characteristic_id FROM tbl_stock_journal WHERE item_id = $item_id AND barcode COLLATE utf8mb4_unicode_ci = '$barcode' AND status = 'active' LIMIT 1");
            if (!$sj) continue;
            $journal_id = (int)$sj['id'];
            $char_id = isset($sj['product_characteristic_id']) ? (int)$sj['product_characteristic_id'] : 0;
            $product_id = (int)($sj['product_id'] ?? 0);
            $characteristic_id = $char_id;
            
            if ($has_reference_cols) {
                mysqli_query($conn, "DELETE FROM tbl_stock WHERE reference_id = $journal_id AND reference_type = 'stock_journal'");
            }
            
            // Get branch_id, metal_id, purity for reinsert
            $branch_id = 0;
            $metal_id = 0;
            $stock_purity = 0;
            if ($characteristic_id > 0) {
                $char_details = getRecord("SELECT branch_id, metal_id, opening_purity FROM tbl_product_characteristics WHERE id = $characteristic_id AND status = 1");
                if ($char_details) {
                    $branch_id = (int)$char_details['branch_id'];
                    $metal_id = (int)$char_details['metal_id'];
                    $stock_purity = (float)$char_details['opening_purity'];
                }
            }
            // Stock follows login / working branch when set
            if ($sj_save_effective_branch > 0) {
                $branch_id = $sj_save_effective_branch;
            } elseif ($branch_id <= 0) {
                $branch_id = 1;
            }
            if ($metal_id <= 0) {
                $metal_id = 1;
            }
            
            $stock_value = 0;
            $rate = 0;
            $net_amt_tax = (float)($product['net_amt_tax'] ?? 0);
            $net_amount = (float)($product['net_amount'] ?? 0);
            $amount = (float)($product['amount'] ?? 0);
            $stock_value = $net_amt_tax > 0 ? $net_amt_tax : ($net_amount > 0 ? $net_amount : $amount);
            $rate = (float)($product['rate'] ?? 0);
            $barcode_sql = $barcode ? "'" . mysqli_real_escape_string($conn, $barcode) . "'" : "NULL";
            $ref_cols_sql = $has_reference_cols ? ", reference_id, reference_type" : "";
            $ref_vals_sql = $has_reference_cols ? ", $journal_id, 'stock_journal'" : "";
            $sjid_col_sql = $has_stock_journal_id ? ", stock_journal_id" : "";
            $sjid_val_sql = $has_stock_journal_id ? ", $item_id" : "";

            // Reinsert inward (purchase)
            $inward_sql = "INSERT INTO tbl_stock (product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at$ref_cols_sql$sjid_col_sql) VALUES ($product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ", $barcode_sql, $branch_id, $metal_id, $stock_weight, $stock_purity, $quantity, " . ($final_weight > 0 ? $final_weight : $stock_weight) . ", $rate, $stock_value, $stock_weight, $quantity, 'purchase', CURDATE(), NOW()$ref_vals_sql$sjid_val_sql)";
            if (!mysqli_query($conn, $inward_sql)) {
                throw new Exception("Reinsert inward stock failed: " . mysqli_error($conn));
            }

            // Reinsert outward (one combined record for this journal row)
            $outward_sql = "INSERT INTO tbl_stock (product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at$ref_cols_sql$sjid_col_sql) VALUES ($product_id, " . ($characteristic_id ? $characteristic_id : "NULL") . ", NULL, $branch_id, $metal_id, $stock_weight, $stock_purity, $quantity, " . ($final_weight > 0 ? $final_weight : $stock_weight) . ", $rate, $stock_value, $stock_weight, $quantity, 'outward', CURDATE(), NOW()$ref_vals_sql$sjid_val_sql)";
            if (!mysqli_query($conn, $outward_sql)) {
                throw new Exception("Reinsert outward stock failed: " . mysqli_error($conn));
            }
            
            $sj_mod_audit = '';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'modified_by')) {
                $mun = function_exists('auragold_stock_journal_session_username') ? mysqli_real_escape_string($conn, auragold_stock_journal_session_username()) : '';
                $sj_mod_audit = ", modified_by = $user_id";
                if (auragold_tbl_has_column($conn, 'tbl_stock_journal', 'modified_by_username')) {
                    $sj_mod_audit .= ", modified_by_username = '$mun'";
                }
                $sj_mod_audit .= ', updated_at = NOW()';
            }
            $sj_ef_sql = '';
            if (auragold_ensure_extra_fields_json_column($conn, 'tbl_stock_journal')) {
                $ef_json = auragold_extra_fields_json_from_item($product);
                $sj_ef_sql = $ef_json !== null
                    ? ", extra_fields_json = '" . mysqli_real_escape_string($conn, $ef_json) . "'"
                    : ", extra_fields_json = NULL";
            }
            mysqli_query($conn, "UPDATE tbl_stock_journal SET quantity = $quantity, gross_weight = $stock_weight$sj_ef_sql$sj_mod_audit WHERE id = $journal_id");
            $updated_count++;
        }
        mysqli_commit($conn);
        if (isset($_SESSION['excel_import_data']) && is_array($_SESSION['excel_import_data'])) {
            unset($_SESSION['excel_import_data']);
        }
        echo json_encode(['status' => true, 'message' => 'Stock updated successfully']);
        exit;
    }
    
    // If item_id is provided, get invoice_id and invoice_no from the purchase invoice item
    $invoice_id = 0;
    $invoice_no = '';
    if ($item_id > 0) {
        $invoice_item = getRecord("
            SELECT invoice_id, invoice_no 
            FROM tbl_purchase_invoice_items pii
            LEFT JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
            WHERE pii.id = $item_id
        ");
        if ($invoice_item) {
            $invoice_id = (int)$invoice_item['invoice_id'];
            $invoice_no = esc($invoice_item['invoice_no'] ?? '');
        }
    }
    
    // If item_id is 0, we need to use a default value that exists
    // Since the column is NOT NULL and has a FK constraint, we'll use 0 but need to handle it
    // For now, if item_id is 0, we'll try to find a related item or use a placeholder
    if ($item_id <= 0) {
        // Try to get item_id from the first product if available
        // Or we can create a dummy entry, but for now let's use 0 and see if FK allows it
        // If FK constraint fails, we'll need to either make it nullable or provide a valid item_id
        $item_id = 0;
    }
    
    // Generate base stock journal invoice number (SJ-{n} or SJ-{n}-{line} for multi-line saves).
    // Must use MAX over existing SJ-* numbers: the previous logic only looked at the latest row by id;
    // if that row was a non-SJ sj_invoice_no (other modules), it fell back to SJ-1 and collided with UNIQUE(sj_invoice_no).
    $max_row = getRecord("
        SELECT COALESCE(MAX(
            CAST(SUBSTRING_INDEX(REPLACE(TRIM(sj_invoice_no), 'SJ-', ''), '-', 1) AS UNSIGNED)
        ), 0) AS max_n
        FROM tbl_stock_journal
        WHERE sj_invoice_no LIKE 'SJ-%'
        AND sj_invoice_no REGEXP '^SJ-[0-9]+'
    ");
    $next_base = isset($max_row['max_n']) ? (int)$max_row['max_n'] + 1 : 1;
    if ($next_base < 1) {
        $next_base = 1;
    }
    $base_journal_no = 'SJ-' . $next_base;

    $sj_ins_created_uname_col = '';
    $sj_ins_created_uname_val = '';
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'created_by_username')) {
        $sj_ins_created_uname_col = ",
                created_by_username";
        $sj_ins_created_uname_val = ",
                '" . mysqli_real_escape_string($conn, function_exists('auragold_stock_journal_session_username') ? auragold_stock_journal_session_username() : '') . "'";
    }
    
    static $sj_has_purchase_rate_col = null;
    if ($sj_has_purchase_rate_col === null) {
        $sj_has_purchase_rate_col = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'purchase_rate');
        if (!$sj_has_purchase_rate_col) {
            @mysqli_query($conn, "ALTER TABLE tbl_stock_journal ADD COLUMN purchase_rate DECIMAL(15,4) NULL DEFAULT NULL AFTER metal_value");
            $sj_has_purchase_rate_col = function_exists('auragold_tbl_has_column')
                && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'purchase_rate');
        }
    }

    // Process each product - each product is a separate row in tbl_stock_journal
    $saved_count = 0;
    $sequence = 1;
    $used_barcodes_stock_journal = [];
    $product_index = 0;
    // One consolidated outward per save: sum qty/weight from these lines; barcode = product opening (primary)
    $sj_inward_row_ids = [];
    $sj_agg_qty = 0.0;
    $sj_agg_weight = 0.0;
    $sj_agg_value = 0.0;
    $sj_agg_first = null;
    $sj_first_journal_id = 0;
    $sj_line_barcodes = [];
    foreach ($products as $product) {
        $product_id = (int)($product['product_id'] ?? 0);
        $characteristic_id = isset($product['characteristic_id']) && $product['characteristic_id'] ? (int)$product['characteristic_id'] : 0;
        $barcode = esc($product['barcode'] ?? '');
        $code = esc($product['code'] ?? '');
        $product_name = esc($product['product_name'] ?? '');
        $quantity = (float)($product['quantity'] ?? 0);
        // Karat: column is decimal(10,2). Dropdown may send id (numeric) or display text (e.g. "24k"). Normalize to decimal.
        $karat_raw = $product['karat'] ?? '';
        $karat = null;
        if ($karat_raw !== '' && $karat_raw !== null) {
            if (is_numeric($karat_raw)) {
                $karat_val = (float)$karat_raw;
                // If it looks like tbl_carat id (small int), get actual karat from name (e.g. 24k -> 24)
                if ($karat_val > 0 && $karat_val < 1000 && floor($karat_val) == $karat_val) {
                    $carat_row = getRecord("SELECT name FROM tbl_carat WHERE id = " . (int)$karat_val . " AND status = 1");
                    if ($carat_row && !empty($carat_row['name'])) {
                        $num = preg_replace('/[^0-9.]/', '', $carat_row['name']);
                        if ($num !== '') $karat = (float)$num;
                    }
                    if ($karat === null) $karat = $karat_val;
                } else {
                    $karat = $karat_val;
                }
            } else {
                $num = preg_replace('/[^0-9.]/', '', (string)$karat_raw);
                if ($num !== '') $karat = (float)$num;
            }
        }
        $gross_weight = (float)($product['gross_weight'] ?? 0);
        $less_weight = (float)($product['less_weight'] ?? 0);
        $purity = (float)($product['purity'] ?? 0);
        $final_weight = (float)($product['final_weight'] ?? 0);
        $net_weight = (float)($product['net_weight'] ?? 0);
        $pure_weight = (float)($product['pure_weight'] ?? 0);
        $rate = (float)($product['rate'] ?? 0);
        $amount = (float)($product['amount'] ?? 0);
        $net_amount = (float)($product['net_amount'] ?? 0);
        $net_amt_tax = (float)($product['net_amt_tax'] ?? 0);
        $making_amount = (float)($product['making_amount'] ?? 0);
        $tax_amount = (float)($product['tax_amount'] ?? 0);
        $design_no = esc($product['design_no'] ?? '');
        $rfid_code = esc($product['rfid_code'] ?? $product['code'] ?? '');
        $voucher_type = esc($product['voucher_type'] ?? '');
        // Product opening / stock journal without purchase invoice: tag voucher for reports (stock-history, etc.)
        if ($item_id <= 0 && trim((string)$voucher_type) === '') {
            $voucher_type = 'product_opening';
        } elseif ($item_id > 0 && trim((string)$voucher_type) === '') {
            $voucher_type = 'purchase_invoice';
        }
        $huid_no = esc($product['huid_no'] ?? '');
        $category = esc($product['category'] ?? '');
        $calculation = esc($product['calculation'] ?? '');
        $location = esc($product['location'] ?? '');
        
        // Resolve category ID to name (create page may send id; update page expects name)
        if (empty($category) && !empty($product['category_id'])) {
            $cid = (int)$product['category_id'];
            $cat_row = getRecord("SELECT name FROM tbl_categories WHERE id = $cid AND status = 1");
            if ($cat_row && !empty($cat_row['name'])) $category = esc($cat_row['name']);
        } elseif (!empty(trim($category)) && is_numeric(trim($category))) {
            $cid = (int)$category;
            $cat_row = getRecord("SELECT name FROM tbl_categories WHERE id = $cid AND status = 1");
            if ($cat_row && !empty($cat_row['name'])) $category = esc($cat_row['name']);
        }
        // Resolve location ID to name
        if (empty($location) && !empty($product['location_id'])) {
            $lid = (int)$product['location_id'];
            $loc_row = getRecord("SELECT name FROM tbl_location WHERE id = $lid AND status = 1");
            if ($loc_row && !empty($loc_row['name'])) $location = esc($loc_row['name']);
        } elseif (!empty(trim($location)) && is_numeric(trim($location))) {
            $lid = (int)$location;
            $loc_row = getRecord("SELECT name FROM tbl_location WHERE id = $lid AND status = 1");
            if ($loc_row && !empty($loc_row['name'])) $location = esc($loc_row['name']);
        }
        
        // Additional fields that might be in the product data
        $stone_charges = (float)($product['stone_charges'] ?? 0);
        $other_charges = (float)($product['other_charges'] ?? 0);
        $diamond_value = (float)($product['diamond_value'] ?? 0);
        $gemstone_value = (float)($product['gemstone_value'] ?? 0);
        $metal_value = (float)($product['metal_value'] ?? 0);
        $purchase_rate = isset($product['purchase_rate']) ? (float)$product['purchase_rate'] : null;
        if ($purchase_rate === null || $purchase_rate === 0.0) {
            if (isset($product['metal_rate']) && (float)$product['metal_rate'] > 0) {
                $purchase_rate = (float)$product['metal_rate'];
            } elseif ($rate > 0) {
                $purchase_rate = $rate;
            }
        }
        $discount = (float)($product['discount'] ?? 0);
        $stone_amount = (float)($product['stone_amount'] ?? 0);
        $other_amount = (float)($product['other_amount'] ?? 0);
        $diamond_amount = (float)($product['diamond_amount'] ?? 0);
        $purchase_amount = (float)($product['purchase_amount'] ?? 0);
        $sale_amount = (float)($product['sale_amount'] ?? 0);
        $sale_amount_with = (float)($product['sale_amount_with'] ?? 0);
        $reverse = (float)($product['reverse'] ?? 0);
        $pkt_wt = isset($product['pkt_wt']) ? (float)$product['pkt_wt'] : null;
        $pkt_less_wt = isset($product['pkt_less_wt']) ? (float)$product['pkt_less_wt'] : null;
        $requested_purity = isset($product['requested_purity']) ? (float)$product['requested_purity'] : null;
        $requested = isset($product['requested']) ? (float)$product['requested'] : null;
        $gold_loss_1 = isset($product['gold_loss_1']) ? (float)$product['gold_loss_1'] : null;
        $gold_loss_2 = isset($product['gold_loss_2']) ? (float)$product['gold_loss_2'] : null;
        $setting_charge = isset($product['setting_charge']) ? (float)$product['setting_charge'] : null;
        $wastage_per = isset($product['wastage_per']) ? (float)$product['wastage_per'] : null;
        $wastage_wt = isset($product['wastage_wt']) ? (float)$product['wastage_wt'] : null;
        $alloy_wt = isset($product['alloy_wt']) ? (float)$product['alloy_wt'] : null;
        $metal_cost = isset($product['metal_cost']) ? (float)$product['metal_cost'] : null;
        $discount_type = esc($product['discount_type'] ?? '');
        $discount_per = isset($product['discount_per']) ? (float)$product['discount_per'] : null;
        $discount_amount = isset($product['discount_amount']) ? (float)$product['discount_amount'] : null;
        $making_type = esc($product['making_type'] ?? '');
        $making_rate = isset($product['making_rate']) ? (float)$product['making_rate'] : null;
        $making_cost = isset($product['making_cost']) ? (float)$product['making_cost'] : null;
        $minimum_price = isset($product['minimum_price']) ? (float)$product['minimum_price'] : null;
        $stone_charge_type = esc($product['stone_charge_type'] ?? '');
        $stone_weight = isset($product['stone_weight']) ? (float)$product['stone_weight'] : null;
        $stone_rate = isset($product['stone_rate']) ? (float)$product['stone_rate'] : null;
        $stone_cost = isset($product['stone_cost']) ? (float)$product['stone_cost'] : null;
        $other_charge_type = esc($product['other_charge_type'] ?? '');
        $other_weight = isset($product['other_weight']) ? (float)$product['other_weight'] : null;
        $other_rate = isset($product['other_rate']) ? (float)$product['other_rate'] : null;
        $other_info = esc($product['other_info'] ?? '');
        $hallmark_amount = isset($product['hallmark_amount']) ? (float)$product['hallmark_amount'] : null;
        $hallmark_rate = isset($product['hallmark_rate']) ? (float)$product['hallmark_rate'] : null;
        
        // Skip if no product ID or no quantity
        if ($product_id <= 0 || $quantity == 0) {
            continue;
        }

        $barcode_allowed = true;
        if (function_exists('auragold_product_branch_is_barcode_enabled')) {
            require_once __DIR__ . '/../includes/auragold_product_branch_local_schema.php';
            $barcode_allowed = auragold_product_branch_is_barcode_enabled($conn, $product_id, $sj_save_effective_branch);
        }
        if (!$barcode_allowed) {
            $prod_name = '';
            $pn = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $product_id . ' LIMIT 1');
            if ($pn && !empty($pn['name'])) {
                $prod_name = trim((string) $pn['name']);
            }
            $label = $prod_name !== '' ? $prod_name : ('Product #' . $product_id);
            throw new Exception("Stock journal cannot use barcodes for \"$label\" because Barcode is disabled on Product Opening.");
        }
        
        // Generate barcode: use unique RN + 5-digit sequence (same as purchase invoice) so no duplicate across product opening and PI
        if (empty(trim($barcode))) {
            if (function_exists('auragold_barcode_default_prefix_digit')) {
                $bd = auragold_barcode_default_prefix_digit($conn, $sj_save_effective_branch);
                $barcode = generateBarcode($conn, $bd['prefix'], $bd['digit'], $used_barcodes_stock_journal);
            } else {
                $barcode = getNextRNBarcode($conn, $used_barcodes_stock_journal);
            }
        }
        $barcode_for_used = trim((string) ($product['barcode'] ?? ''));
        if ($barcode_for_used === '' && !empty(trim($barcode))) {
            $barcode_for_used = trim((string) $barcode);
        }
        if ($barcode_for_used !== '' && !in_array($barcode_for_used, $used_barcodes_stock_journal, true)) {
            $used_barcodes_stock_journal[] = $barcode_for_used;
        }
        
        // Get metal and branch from product characteristics
        $metal_id = 0;
        $metal_type = '';
        $branch_id = 0;
        $stock_purity = $purity;
        $char_opening_purity = null;
        // Use the item_id from request (don't reset it)
        $current_item_id = $item_id;
        $current_invoice_id = $invoice_id;
        $current_invoice_no = $invoice_no;
        
        if ($characteristic_id > 0) {
            $char_details = getRecord("
                SELECT branch_id, metal_id, opening_purity 
                FROM tbl_product_characteristics 
                WHERE id = $characteristic_id AND status = 1
            ");
            
            if ($char_details) {
                $branch_id = (int)$char_details['branch_id'];
                $metal_id = (int)$char_details['metal_id'];
                if (isset($char_details['opening_purity']) && $char_details['opening_purity'] !== '' && $char_details['opening_purity'] !== null) {
                    $char_opening_purity = (float) $char_details['opening_purity'];
                }
                if ($stock_purity <= 0 && $char_opening_purity !== null) {
                    $stock_purity = $char_opening_purity;
                }
            }
        }
        
        // If still no metal_id, try to get from product
        if ($metal_id <= 0 && $product_id > 0) {
            $default_metal = getRecord("
                SELECT metal_id FROM tbl_product_characteristics 
                WHERE product_id = $product_id AND status = 1 
                ORDER BY id DESC LIMIT 1
            ");
            $metal_id = $default_metal ? (int)$default_metal['metal_id'] : 1;
        }
        
        // Get metal type from tbl_metal; do not save placeholder/junk values (e.g. "ewew")
        if ($metal_id > 0) {
            $metal_info = getRecord("SELECT system_name, display_name FROM tbl_metal WHERE id = $metal_id");
            if ($metal_info) {
                $raw = $metal_info['system_name'] ?? strtolower($metal_info['display_name'] ?? '');
                $raw = trim((string)$raw);
                $placeholders = ['ewew', 'test', 'xxx', 'abc', 'asdf', 'qwerty'];
                if ($raw !== '' && !in_array(strtolower($raw), $placeholders, true)) {
                    $metal_type = $raw;
                }
            }
        }
        
        // Stock follows login / working branch when set (not only characteristic.branch_id)
        if ($sj_save_effective_branch > 0) {
            $branch_id = $sj_save_effective_branch;
        } elseif ($branch_id <= 0) {
            $branch_id = 1;
        }
        if ($metal_id <= 0) {
            $metal_id = 1; // Default metal
        }
        // stock-journal-create.php defaults the Purity % cell to "1" when opening_purity is missing; that was saved as 1% here (net×1/100 → e.g. 0.050 for 5g). Prefer master opening_purity when it clearly differs.
        if ($item_id <= 0 && $characteristic_id > 0 && $char_opening_purity !== null) {
            if (abs((float) $purity - 1.0) < 0.0001 && abs($char_opening_purity - 1.0) > 0.0001) {
                $stock_purity = $char_opening_purity;
            }
        }
        if ($stock_purity <= 0) {
            $stock_purity = 100.0;
        }
        // Match stock-journal-create.js: values > 1 are percent (91.6 → 0.916); fineness decimals (e.g. 0.916) are used as-is. Never divide by 100 twice.
        $purity_for_calc = (float) $stock_purity;
        if ($purity_for_calc > 1) {
            $purity_for_calc /= 100.0;
        }
        $purity_weight = $net_weight * $purity_for_calc;
        $purity = (float) $stock_purity;
        
        // Link to a purchase invoice item only when this save was opened from a purchase invoice (item_id in URL).
        // Product opening voucher uses item_id = 0 — do not attach a random PI or stock history shows "Purchase".
        if ($item_id > 0 && $current_item_id <= 0 && $product_id > 0) {
            // Try to find a purchase invoice item for this product
            $related_item = getRecord("
                SELECT id FROM tbl_purchase_invoice_items 
                WHERE product_id = $product_id 
                ORDER BY id DESC LIMIT 1
            ");
            if ($related_item) {
                $current_item_id = (int)$related_item['id'];
                // Also get invoice info
                if ($current_invoice_id <= 0) {
                    $inv_info = getRecord("
                        SELECT invoice_id, invoice_no 
                        FROM tbl_purchase_invoice_items pii
                        LEFT JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
                        WHERE pii.id = $current_item_id
                    ");
                    if ($inv_info) {
                        $current_invoice_id = (int)$inv_info['invoice_id'];
                        $current_invoice_no = esc($inv_info['invoice_no'] ?? '');
                    }
                }
            }
        }
        
        // If still no item_id, we must use 0 (FK constraint may fail, but we'll try)
        if ($current_item_id <= 0) {
            $current_item_id = 0;
        }
        
        // Generate unique journal number for this product (append sequence if multiple products)
        $product_journal_no = $base_journal_no;
        if (count($products) > 1) {
            $product_journal_no = $base_journal_no . '-' . $sequence;
        }
        
        // Use NULL for item_id/invoice_id when product opening (no purchase invoice)
        $item_id_sql = $current_item_id > 0 ? (string)$current_item_id : "NULL";
        $invoice_id_sql = $current_invoice_id > 0 ? (string)$current_invoice_id : "NULL";
        $sj_ef_col = '';
        $sj_ef_val = '';
        if (auragold_ensure_extra_fields_json_column($conn, 'tbl_stock_journal')) {
            $ef_json = auragold_extra_fields_json_from_item($product);
            $sj_ef_col = ', extra_fields_json';
            $sj_ef_val = $ef_json !== null
                ? ", '" . mysqli_real_escape_string($conn, $ef_json) . "'"
                : ', NULL';
        }
        if ($current_item_id > 0 && auragold_ensure_extra_fields_json_column($conn, 'tbl_purchase_invoice_items')) {
            $ef_json_pi = auragold_extra_fields_json_from_item($product);
            if ($ef_json_pi !== null) {
                @mysqli_query(
                    $conn,
                    "UPDATE tbl_purchase_invoice_items SET extra_fields_json = '" . mysqli_real_escape_string($conn, $ef_json_pi) . "' WHERE id = " . (int) $current_item_id . " LIMIT 1"
                );
            }
        }
        $journal_sql = "
            INSERT INTO tbl_stock_journal (
                sj_invoice_no,
                item_id,
                invoice_id,
                invoice_no,
                sj_date,
                barcode,
                code,
                product_id,
                product_characteristic_id,
                product_name,
                metal_id,
                metal_type,
                quantity,
                karat,
                gross_weight,
                less_weight,
                net_weight,
                purity,
                purity_weight,
                pure_weight,
                final_weight,
                rate,
                amount,
                making_amount,
                tax_amount,
                net_amount,
                net_amt_with_tax,
                rfid_code,
                voucher_type,
                design_no,
                huid_no,
                category,
                calculation,
                location,
                pkt_wt,
                pkt_less_wt,
                requested_purity,
                requested,
                gold_loss_1,
                gold_loss_2,
                setting_charge,
                wastage_per,
                wastage_wt,
                alloy_wt,
                metal_value,
                " . (!empty($sj_has_purchase_rate_col) ? "purchase_rate,\n                " : "") . "metal_cost,
                discount_type,
                discount_per,
                discount_amount,
                discount,
                making_type,
                making_rate,
                making_cost,
                minimum_price,
                stone_charge_type,
                stone_weight,
                stone_rate,
                stone_amount,
                stone_cost,
                diamond_amount,
                purchase_amount,
                sale_amount,
                other_charge_type,
                other_weight,
                other_rate,
                other_info,
                other_amount,
                hallmark_amount,
                hallmark_rate,
                reverse,
                group_name,
                comment,
                status,
                created_by
                $sj_ins_created_uname_col
                $sj_ef_col,
                created_at
            ) VALUES (
                '$product_journal_no',
                $item_id_sql,
                $invoice_id_sql,
                " . ($current_invoice_no ? "'$current_invoice_no'" : "NULL") . ",
                '$journal_date',
                " . ($barcode ? "'$barcode'" : "NULL") . ",
                " . ($code ? "'$code'" : "NULL") . ",
                $product_id,
                " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                '$product_name',
                " . ($metal_id ? $metal_id : "NULL") . ",
                " . ($metal_type ? "'$metal_type'" : "NULL") . ",
                $quantity,
                " . ($karat !== null ? $karat : "NULL") . ",
                $gross_weight,
                $less_weight,
                $net_weight,
                $purity,
                $purity_weight,
                $pure_weight,
                $final_weight,
                $rate,
                $amount,
                $making_amount,
                $tax_amount,
                $net_amount,
                $net_amt_tax,
                " . ($rfid_code ? "'" . mysqli_real_escape_string($conn, $rfid_code) . "'" : "NULL") . ",
                " . ($voucher_type ? "'" . mysqli_real_escape_string($conn, $voucher_type) . "'" : "NULL") . ",
                " . ($design_no ? "'" . mysqli_real_escape_string($conn, $design_no) . "'" : "NULL") . ",
                " . ($huid_no ? "'" . mysqli_real_escape_string($conn, $huid_no) . "'" : "NULL") . ",
                " . ($category ? "'" . mysqli_real_escape_string($conn, $category) . "'" : "NULL") . ",
                " . ($calculation ? "'" . mysqli_real_escape_string($conn, $calculation) . "'" : "NULL") . ",
                " . ($location ? "'" . mysqli_real_escape_string($conn, $location) . "'" : "NULL") . ",
                " . ($pkt_wt !== null ? $pkt_wt : "NULL") . ",
                " . ($pkt_less_wt !== null ? $pkt_less_wt : "NULL") . ",
                " . ($requested_purity !== null ? $requested_purity : "NULL") . ",
                " . ($requested !== null ? $requested : "NULL") . ",
                " . ($gold_loss_1 !== null ? $gold_loss_1 : "NULL") . ",
                " . ($gold_loss_2 !== null ? $gold_loss_2 : "NULL") . ",
                " . ($setting_charge !== null ? $setting_charge : "NULL") . ",
                " . ($wastage_per !== null ? $wastage_per : "NULL") . ",
                " . ($wastage_wt !== null ? $wastage_wt : "NULL") . ",
                " . ($alloy_wt !== null ? $alloy_wt : "NULL") . ",
                " . ($metal_value !== null ? $metal_value : "NULL") . ",
                " . (!empty($sj_has_purchase_rate_col) ? ($purchase_rate !== null ? $purchase_rate : "NULL") . ",\n                " : "") . ($metal_cost !== null ? $metal_cost : "NULL") . ",
                " . ($discount_type ? "'" . mysqli_real_escape_string($conn, $discount_type) . "'" : "NULL") . ",
                " . ($discount_per !== null ? $discount_per : "NULL") . ",
                " . ($discount_amount !== null ? $discount_amount : "NULL") . ",
                " . ($discount !== null ? $discount : "NULL") . ",
                " . ($making_type ? "'" . mysqli_real_escape_string($conn, $making_type) . "'" : "NULL") . ",
                " . ($making_rate !== null ? $making_rate : "NULL") . ",
                " . ($making_cost !== null ? $making_cost : "NULL") . ",
                " . ($minimum_price !== null ? $minimum_price : "NULL") . ",
                " . ($stone_charge_type ? "'" . mysqli_real_escape_string($conn, $stone_charge_type) . "'" : "NULL") . ",
                " . ($stone_weight !== null ? $stone_weight : "NULL") . ",
                " . ($stone_rate !== null ? $stone_rate : "NULL") . ",
                " . ($stone_amount !== null ? $stone_amount : "NULL") . ",
                " . ($stone_cost !== null ? $stone_cost : "NULL") . ",
                " . ($diamond_amount !== null ? $diamond_amount : "NULL") . ",
                " . ($purchase_amount !== null ? $purchase_amount : "NULL") . ",
                " . ($sale_amount !== null ? $sale_amount : "NULL") . ",
                " . ($other_charge_type ? "'" . mysqli_real_escape_string($conn, $other_charge_type) . "'" : "NULL") . ",
                " . ($other_weight !== null ? $other_weight : "NULL") . ",
                " . ($other_rate !== null ? $other_rate : "NULL") . ",
                " . ($other_info ? "'" . mysqli_real_escape_string($conn, $other_info) . "'" : "NULL") . ",
                " . ($other_amount !== null ? $other_amount : "NULL") . ",
                " . ($hallmark_amount !== null ? $hallmark_amount : "NULL") . ",
                " . ($hallmark_rate !== null ? $hallmark_rate : "NULL") . ",
                " . ($reverse !== null ? $reverse : "NULL") . ",
                " . ($group_name ? "'$group_name'" : "NULL") . ",
                " . ($comment ? "'$comment'" : "NULL") . ",
                'active',
                $user_id
                $sj_ins_created_uname_val
                $sj_ef_val,
                NOW()
            )
        ";
        
        if (!mysqli_query($conn, $journal_sql)) {
            throw new Exception("Stock journal insert failed: " . mysqli_error($conn) . " | SQL: " . $journal_sql);
        }
        
        $journal_id = mysqli_insert_id($conn);
        $saved_count++;
        $sequence++;
        
        // Excel preview: image paths in JSON (uploads/temp_excel) — same row index as FormData file uploads
        if (!empty($product['temp_image_paths']) && is_array($product['temp_image_paths'])) {
            if (!isset($row_images[$product_index])) {
                $row_images[$product_index] = [];
            }
            $adminBase = realpath(dirname(__DIR__));
            $tempDirReal = $adminBase ? @realpath($adminBase . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_excel') : false;
            foreach ($product['temp_image_paths'] as $relp) {
                $relp = trim((string) $relp);
                if ($relp === '' || strpos($relp, '..') !== false) {
                    continue;
                }
                if (stripos($relp, 'uploads/temp_excel/') !== 0 && stripos($relp, 'uploads\\temp_excel\\') !== 0) {
                    continue;
                }
                $relpN = str_replace(['\\', '//'], ['/', '/'], $relp);
                $abs = $adminBase . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relpN, '/\\'));
                if (!is_file($abs) || !is_readable($abs) || $tempDirReal === false) {
                    continue;
                }
                $rr = @realpath($abs);
                if ($rr === false || strpos($rr, $tempDirReal) !== 0) {
                    continue;
                }
                $size = (int) @filesize($rr);
                if ($size <= 0) {
                    continue;
                }
                $mime = 'application/octet-stream';
                if (function_exists('mime_content_type')) {
                    $mt = @mime_content_type($rr);
                    if (is_string($mt) && $mt !== '') {
                        $mime = $mt;
                    }
                }
                $row_images[$product_index][] = [
                    'name' => basename($rr),
                    'type' => $mime,
                    'tmp_name' => $rr,
                    'error' => UPLOAD_ERR_OK,
                    'size' => $size,
                ];
            }
        }
        
        // Upload images for this row (item_id from request, barcode from product)
        if (!empty($row_images[$product_index])) {
            uploadStockImages($row_images[$product_index], $item_id, $barcode, true);
        }
        $product_index++;
        
        // Use user-entered gross weight first so inward/outward show what user entered (e.g. 10 not 7.5)
        $stock_weight = $gross_weight > 0 ? $gross_weight : ($final_weight > 0 ? $final_weight : $net_weight);
        
        // Calculate stock value
        $stock_value = $net_amt_tax > 0 ? $net_amt_tax : ($net_amount > 0 ? $net_amount : $amount);

        if ($sj_agg_first === null) {
            $sj_agg_first = [
                'product_id' => $product_id,
                'characteristic_id' => $characteristic_id,
                'branch_id' => $branch_id,
                'metal_id' => $metal_id,
                'stock_purity' => $stock_purity,
                'rate' => $rate,
            ];
        }
        if ($sj_first_journal_id <= 0) {
            $sj_first_journal_id = $journal_id;
        }
        $bc_raw = trim((string) ($product['barcode'] ?? ''));
        if ($bc_raw !== '' && !in_array($bc_raw, $sj_line_barcodes, true)) {
            $sj_line_barcodes[] = $bc_raw;
        }
        
        // Step 1: Add inward stock (purchase); include reference_id/reference_type and stock_journal_id when columns exist
        $barcode_sql = $barcode ? "'" . mysqli_real_escape_string($conn, $barcode) . "'" : "NULL";
        $ref_cols_sql = $has_reference_cols ? ", reference_id, reference_type" : "";
        $ref_vals_sql = $has_reference_cols ? ", $journal_id, 'stock_journal'" : "";
        $sjid_col_sql = $has_stock_journal_id ? ", stock_journal_id" : "";
        $sjid_val_sql = $has_stock_journal_id ? ", " . ($current_item_id > 0 ? $current_item_id : "NULL") : "";
        $inward_stock_sql = "
            INSERT INTO tbl_stock (
                product_id,
                product_characteristic_id,
                barcode,
                branch_id,
                metal_id,
                opening_weight,
                opening_purity,
                opening_qty,
                final_weight,
                rate,
                value,
                current_weight,
                current_qty,
                stock_type,
                transaction_date,
                created_at
                $ref_cols_sql
                $sjid_col_sql
            ) VALUES (
                $product_id,
                " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                $barcode_sql,
                $branch_id,
                $metal_id,
                $stock_weight,
                $stock_purity,
                $quantity,
                " . ($final_weight > 0 ? $final_weight : $stock_weight) . ",
                $rate,
                $stock_value,
                $stock_weight,
                $quantity,
                'purchase',
                '$journal_date',
                NOW()
                $ref_vals_sql
                $sjid_val_sql
            )
        ";
        
        if (!mysqli_query($conn, $inward_stock_sql)) {
            throw new Exception("Inward stock insert failed: " . mysqli_error($conn));
        }

        $inward_stock_row_id = (int) mysqli_insert_id($conn);
        if ($inward_stock_row_id > 0) {
            $sj_inward_row_ids[] = $inward_stock_row_id;
        }
        $sj_agg_qty += $quantity;
        $sj_agg_weight += $stock_weight;
        $sj_agg_value += $stock_value;
        
        // Insert into tbl_inward_stock (one record per stock journal entry)
        $tbl_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
        if ($tbl_inward && mysqli_num_rows($tbl_inward) > 0) {
            mysqli_free_result($tbl_inward);
            $inward_val_sql = "
                INSERT INTO tbl_inward_stock (
                    stock_journal_id, product_id, product_characteristic_id, barcode_no,
                    branch_id, metal_id, qty, weight, rate, value, stock_type, transaction_date, created_at
                ) VALUES (
                    $journal_id,
                    $product_id,
                    " . ($characteristic_id ? $characteristic_id : "NULL") . ",
                    " . ($barcode ? "'$barcode'" : "NULL") . ",
                    $branch_id,
                    " . ($metal_id ? $metal_id : "NULL") . ",
                    $quantity,
                    $stock_weight,
                    $rate,
                    $stock_value,
                    'purchase',
                    '$journal_date',
                    NOW()
                )
            ";
            if (!mysqli_query($conn, $inward_val_sql)) {
                throw new Exception("tbl_inward_stock insert failed: " . mysqli_error($conn));
            }
        }
        
        // Update master opening on tbl_product_characteristics only for Product Opening voucher (item_id = 0):
        // inward SJ rows deduct from opening_qty / opening_weight as barcodes are split off opening stock.
        // Purchase-invoice stock journal (item_id > 0) must NOT change opening_* — those columns are the
        // Product Opening screen values; adding PI SJ quantities here incorrectly inflated them (e.g. 100 + 6 = 106).
        if ($characteristic_id > 0 && $item_id <= 0) {
            $has_qty = false;
            $has_wt = false;
            $upd_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics WHERE Field IN ('opening_qty','opening_weight')");
            if ($upd_cols) {
                while ($c = mysqli_fetch_assoc($upd_cols)) {
                    if (($c['Field'] ?? '') === 'opening_qty') $has_qty = true;
                    if (($c['Field'] ?? '') === 'opening_weight') $has_wt = true;
                }
                mysqli_free_result($upd_cols);
            }
            if ($has_qty || $has_wt) {
                $set_parts = [];
                if ($has_qty) {
                    $set_parts[] = "opening_qty = GREATEST(0, COALESCE(opening_qty, 0) - $quantity)";
                }
                if ($has_wt) {
                    $set_parts[] = "opening_weight = GREATEST(0, COALESCE(opening_weight, 0) - $stock_weight)";
                }
                $upd_sql = "UPDATE tbl_product_characteristics SET " . implode(', ', $set_parts) . " WHERE id = $characteristic_id";
                if (!mysqli_query($conn, $upd_sql)) {
                    throw new Exception("Update product characteristic totals failed: " . mysqli_error($conn));
                }
            }
        }
        
    }

    if ($saved_count == 0) {
        throw new Exception('No valid products were saved');
    }

    // Single outward row for this voucher: total qty / total weight; barcode = product opening master (not line barcodes).
    // Only the inward lines created above are cleared (avoids SUM(current_qty) across unrelated stock rows).
    if ($sj_agg_first !== null && !empty($sj_inward_row_ids) && ($sj_agg_qty > 0 || $sj_agg_weight > 0)) {
        $oproduct_id = (int) $sj_agg_first['product_id'];
        $ochar = (int) $sj_agg_first['characteristic_id'];
        $obranch = (int) $sj_agg_first['branch_id'];
        $ometal = (int) $sj_agg_first['metal_id'];
        $opurity = (float) $sj_agg_first['stock_purity'];
        $orate = (float) $sj_agg_first['rate'];
        if ($oproduct_id <= 0) {
            $oproduct_id = (int) $merge_product_id;
        }
        if ($oproduct_id <= 0) {
            $oproduct_id = 1;
        }
        if ($obranch <= 0) {
            $obranch = 1;
        }
        if ($ometal <= 0) {
            $ometal = 1;
        }
        // Purchase-invoice SJ: outward row uses the PI line barcode. Product opening (item_id 0): use opening stock barcode.
        $primary_barcode = '';
        if ($item_id > 0) {
            $pi_bc_row = getRecord("SELECT barcode, barcode_no FROM tbl_purchase_invoice_items WHERE id = " . (int) $item_id . " LIMIT 1");
            if ($pi_bc_row) {
                $b1 = trim((string) ($pi_bc_row['barcode'] ?? ''));
                $b2 = trim((string) ($pi_bc_row['barcode_no'] ?? ''));
                $primary_barcode = ($b1 !== '') ? $b1 : $b2;
            }
        }
        if ($primary_barcode === '') {
            $primary_row = getRecord("SELECT barcode FROM tbl_stock WHERE product_id = $oproduct_id AND branch_id = $obranch AND stock_type = 'opening' ORDER BY id ASC LIMIT 1");
            $primary_barcode = $primary_row && !empty(trim($primary_row['barcode'] ?? '')) ? trim($primary_row['barcode']) : '';
        }
        if ($primary_barcode === '') {
            $primary_row = getRecord("SELECT barcode FROM tbl_stock WHERE product_id = $oproduct_id AND stock_type = 'opening' ORDER BY id ASC LIMIT 1");
            $primary_barcode = $primary_row && !empty(trim($primary_row['barcode'] ?? '')) ? trim($primary_row['barcode']) : '';
        }
        $primary_esc = $primary_barcode !== '' ? "'" . mysqli_real_escape_string($conn, $primary_barcode) . "'" : "NULL";
        $agg_qty = (float) $sj_agg_qty;
        $agg_wt = (float) $sj_agg_weight;
        $agg_val = (float) $sj_agg_value;
        $eff_rate = ($agg_wt > 0.0000001) ? ($agg_val / $agg_wt) : $orate;
        $tail_ref_cols = $has_reference_cols ? ", reference_id, reference_type" : "";
        $tail_ref_vals = $has_reference_cols ? ", " . (int) $sj_first_journal_id . ", 'stock_journal'" : "";
        $tail_sjid_sql = $has_stock_journal_id ? ", stock_journal_id" : "";
        $tail_sjid_val = $has_stock_journal_id ? ", " . ($item_id > 0 ? (int) $item_id : "NULL") : "";
        $rcc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_barcodes'");
        $has_ref_bc = ($rcc && mysqli_num_rows($rcc) > 0);
        if ($rcc) {
            mysqli_free_result($rcc);
        }
        $ins_tail = "product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at";
        $val_tail = "$oproduct_id, " . ($ochar ? $ochar : "NULL") . ", $primary_esc, $obranch, $ometal, $agg_wt, $opurity, $agg_qty, $agg_wt, $eff_rate, $agg_val, $agg_wt, $agg_qty, 'outward', '$journal_date', NOW()";
        if ($has_ref_bc && !empty($sj_line_barcodes)) {
            $ins_tail .= ", reference_barcodes";
            $val_tail .= ", '" . mysqli_real_escape_string($conn, implode(',', $sj_line_barcodes)) . "'";
        }
        $ins_tail .= $tail_ref_cols . $tail_sjid_sql;
        $val_tail .= $tail_ref_vals . $tail_sjid_val;
        if (!mysqli_query($conn, "INSERT INTO tbl_stock ($ins_tail) VALUES ($val_tail)")) {
            throw new Exception("Consolidated outward stock insert failed: " . mysqli_error($conn));
        }
        $ids_sql = implode(',', array_map('intval', $sj_inward_row_ids));
        if ($ids_sql !== '') {
            mysqli_query($conn, "UPDATE tbl_stock SET current_qty = 0, current_weight = 0 WHERE id IN ($ids_sql) AND stock_type = 'purchase'");
        }
    }
    
    mysqli_commit($conn);
    
    if (isset($_SESSION['excel_import_data']) && is_array($_SESSION['excel_import_data'])) {
        unset($_SESSION['excel_import_data']);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Stock Journal saved successfully',
        'journal_no' => $base_journal_no,
        'saved_count' => $saved_count
    ]);
    
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @mysqli_rollback($conn);
    }
    error_log("Stock Journal Save Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
