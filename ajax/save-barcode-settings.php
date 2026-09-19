<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => 0, 'message' => 'Invalid request method']);
    exit;
}

// Validation helpers
$label_size_preset = isset($_POST['label_size_preset']) ? trim($_POST['label_size_preset']) : '100x18';
$valid_presets = ['100x18', '82x38_2box'];
if (!in_array($label_size_preset, $valid_presets)) {
    $label_size_preset = '100x18';
}

$label_width_mm = isset($_POST['label_width_mm']) ? (float)$_POST['label_width_mm'] : 100;
$label_height_mm = isset($_POST['label_height_mm']) ? (float)$_POST['label_height_mm'] : 18;
/** One DB row per metal + label size (custom sizes stored as custom_WxH). */
$label_size_preset = auragold_barcode_label_storage_preset($label_size_preset, $label_width_mm, $label_height_mm);
[$label_width_mm, $label_height_mm] = auragold_barcode_label_mm_from_storage_preset($label_size_preset, $label_width_mm, $label_height_mm);
$label_width_mm = max(10, min(500, $label_width_mm));
$label_height_mm = max(10, min(300, $label_height_mm));

$font_size = isset($_POST['font_size']) ? (int)$_POST['font_size'] : 12;
$font_size = max(6, min(72, $font_size));

$dpt_save = isset($_POST['default_print_code_type']) ? strtolower(trim((string)$_POST['default_print_code_type'])) : 'barcode';
if ($dpt_save !== 'qr') {
    $dpt_save = 'barcode';
}

$legacy_pn_post = isset($_POST['show_product_name']) ? (((int) $_POST['show_product_name'] === 1) ? 1 : 0) : 1;
$legacy_pr_post = isset($_POST['show_price']) ? (((int) $_POST['show_price'] === 1) ? 1 : 0) : 1;
$legacy_bn_post = isset($_POST['show_barcode_number']) ? (((int) $_POST['show_barcode_number'] === 1) ? 1 : 0) : 1;

$parse_post_flag = static function ($key, $default = 1) {
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }
    return ((int) $_POST[$key] === 1) ? 1 : 0;
};

$has_split_show = array_key_exists('show_product_name_barcode', $_POST) && array_key_exists('show_product_name_qr', $_POST);
if ($has_split_show) {
    $show_product_name_barcode = $parse_post_flag('show_product_name_barcode', 1);
    $show_product_name_qr = $parse_post_flag('show_product_name_qr', 1);
    $show_price_barcode = $parse_post_flag('show_price_barcode', 1);
    $show_price_qr = $parse_post_flag('show_price_qr', 1);
    $show_barcode_number_barcode = $parse_post_flag('show_barcode_number_barcode', 1);
    $show_barcode_number_qr = $parse_post_flag('show_barcode_number_qr', 1);
} else {
    $show_product_name_barcode = $legacy_pn_post;
    $show_product_name_qr = $legacy_pn_post;
    $show_price_barcode = $legacy_pr_post;
    $show_price_qr = $legacy_pr_post;
    $show_barcode_number_barcode = $legacy_bn_post;
    $show_barcode_number_qr = $legacy_bn_post;
}

$show_product_name = ($dpt_save === 'qr') ? $show_product_name_qr : $show_product_name_barcode;
$show_price = ($dpt_save === 'qr') ? $show_price_qr : $show_price_barcode;
$show_barcode_number = ($dpt_save === 'qr') ? $show_barcode_number_qr : $show_barcode_number_barcode;

$print_copies = isset($_POST['print_copies']) ? (int)$_POST['print_copies'] : 1;
$print_copies = max(1, min(100, $print_copies));

$is_default_print = isset($_POST['is_default_print']) ? (((int) $_POST['is_default_print'] === 1) ? 1 : 0) : 0;

$metal_type = isset($_POST['metal_type']) ? trim($_POST['metal_type']) : '';
if ($metal_type !== '' && strlen($metal_type) > 50) {
    $metal_type = substr($metal_type, 0, 50);
}

$design_layout = isset($_POST['design_layout']) ? trim($_POST['design_layout']) : '';
if ($design_layout !== '' && strlen($design_layout) > 65535) {
    $design_layout = substr($design_layout, 0, 65535);
}
$design_layout_val = ($design_layout === '' ? null : $design_layout);

/* When saving QR mode with an empty barcode layout payload, keep the previous barcode design. */
if (($design_layout_val === null || $design_layout_val === '' || $design_layout_val === '{}') && $dpt_save === 'qr') {
    // Will resolve against $existing after lookup below.
    $preserve_barcode_layout_if_empty = true;
} else {
    $preserve_barcode_layout_if_empty = false;
}

// Preview image: base64 data URL from html2canvas, save to uploads/barcode_settings/preview_{timestamp}.png
$preview_image_path = null;
if (!empty($_POST['preview_image']) && preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/i', trim($_POST['preview_image']), $m)) {
    $ext = strtolower($m[1]);
    if ($ext === 'jpg') $ext = 'jpeg';
    $data = base64_decode($m[2], true);
    if ($data !== false && strlen($data) > 0) {
        $upload_dir = dirname(__DIR__) . '/uploads/barcode_settings';
        if (!is_dir($upload_dir)) {
            @mkdir(dirname(__DIR__) . '/uploads', 0755, true);
            @mkdir($upload_dir, 0755, true);
        }
        if (is_dir($upload_dir) && is_writable($upload_dir)) {
            $filename = 'preview_' . time() . '.png';
            $filepath = $upload_dir . '/' . $filename;
            if ($ext === 'png') {
                $ok_file = @file_put_contents($filepath, $data);
            } else {
                $img = @imagecreatefromstring($data);
                $ok_file = $img && @imagepng($img, $filepath) && @imagedestroy($img);
            }
            if (!empty($ok_file) && file_exists($filepath)) {
                $preview_image_path = 'uploads/barcode_settings/' . $filename;
            }
        }
    }
}

// Check if table exists
$table = 'tbl_barcode_settings';
$chk = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) mysqli_free_result($chk);
    echo json_encode(['success' => 0, 'message' => 'Barcode settings table not found. Please run sql/create_tbl_barcode_settings.sql first.']);
    exit;
}
mysqli_free_result($chk);

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_bid = auragold_settings_branch_id();
if (function_exists('auragold_ensure_barcode_settings_qr_columns')) {
    auragold_ensure_barcode_settings_qr_columns($conn);
}
if (isset($_POST['settings_branch_id']) && (int) $_POST['settings_branch_id'] > 0) {
    $post_bid = (int) $_POST['settings_branch_id'];
    if (function_exists('auragold_settings_branch_id_valid') && auragold_settings_branch_id_valid($post_bid)) {
        $settings_bid = $post_bid;
    }
}
$has_branch_col = auragold_tbl_has_column($conn, $table, 'branch_id');
$branch_where = ($has_branch_col && $settings_bid > 0) ? (' AND branch_id = ' . (int) $settings_bid) : '';
if ($metal_type !== '') {
    $mt_esc = mysqli_real_escape_string($conn, $metal_type);
    $ps_esc = mysqli_real_escape_string($conn, $label_size_preset);
    $existing = getRecord("SELECT id FROM $table WHERE 1=1 $branch_where AND metal_type = '$mt_esc' AND label_size_preset = '$ps_esc' ORDER BY id DESC LIMIT 1");
    if (!$existing && strpos($label_size_preset, 'custom_') === 0) {
        $existing = getRecord("SELECT id FROM $table WHERE 1=1 $branch_where AND metal_type = '$mt_esc' AND label_size_preset = 'custom' ORDER BY id DESC LIMIT 1");
    }
    if (!$existing && strpos($label_size_preset, 'custom_') !== 0 && $label_size_preset !== 'custom') {
        $existing = getRecord(
            "SELECT id FROM $table WHERE 1=1 $branch_where AND metal_type = '$mt_esc' AND label_size_preset = 'custom'"
            . ' AND label_width_mm = ' . (float) $label_width_mm
            . ' AND label_height_mm = ' . (float) $label_height_mm
            . ' ORDER BY id DESC LIMIT 1'
        );
    }
} else {
    $existing = getRecord("SELECT id FROM $table WHERE 1=1 $branch_where AND (metal_type IS NULL OR metal_type = '') ORDER BY id DESC LIMIT 1");
    if (!$existing) {
        $existing = getRecord("SELECT id FROM $table WHERE 1=1 $branch_where ORDER BY id DESC LIMIT 1");
    }
}
if (!empty($preserve_barcode_layout_if_empty) && $existing && !empty($existing['id'])) {
    $prevBcRow = getRecord('SELECT design_layout FROM `' . $table . '` WHERE id = ' . (int) $existing['id'] . ' LIMIT 1');
    $prevBcVal = isset($prevBcRow['design_layout']) ? trim((string) $prevBcRow['design_layout']) : '';
    if ($prevBcVal !== '' && $prevBcVal !== '{}') {
        $design_layout = $prevBcVal;
        $design_layout_val = $prevBcVal;
    }
}
$ok = false;

// Check if design_layout column exists (for older DBs)
$has_design = false;
$cols = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'design_layout'");
if ($cols && mysqli_num_rows($cols) > 0) {
    $has_design = true;
}
if ($cols) mysqli_free_result($cols);

// Check if preview_image column exists
$has_preview_image = false;
$cols = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'preview_image'");
if ($cols && mysqli_num_rows($cols) > 0) {
    $has_preview_image = true;
}
if ($cols) mysqli_free_result($cols);

// Optional columns: mirror JsBarcode dimensions from design_layout JSON onto the row
$colsBd = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'barcode_bar_width'");
$has_barcode_dims = ($colsBd && mysqli_num_rows($colsBd) > 0);
if ($colsBd) {
    mysqli_free_result($colsBd);
}

$preview_image_val = ($preview_image_path !== null ? $preview_image_path : null);
if ($existing && !empty($existing['id']) && $preview_image_path === null) {
    $row = getRecord("SELECT preview_image FROM $table WHERE id = " . (int)$existing['id']);
    if ($row !== null) {
        $preview_image_val = $row['preview_image'];
    }
}
if ($existing && !empty($existing['id'])) {
    $id = (int)$existing['id'];
    if ($has_design && $has_preview_image) {
        $stmt = mysqli_prepare($conn, "UPDATE $table SET label_size_preset=?, label_width_mm=?, label_height_mm=?, font_size=?, show_product_name=?, show_price=?, show_barcode_number=?, print_copies=?, metal_type=?, design_layout=?, preview_image=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) {
            echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        $metal_type_val = ($metal_type === '' ? null : $metal_type);
        $prev_val = $preview_image_val;
        mysqli_stmt_bind_param($stmt, 'sddiiiiisssi', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $design_layout_val, $prev_val, $id);
    } elseif ($has_design) {
        $stmt = mysqli_prepare($conn, "UPDATE $table SET label_size_preset=?, label_width_mm=?, label_height_mm=?, font_size=?, show_product_name=?, show_price=?, show_barcode_number=?, print_copies=?, metal_type=?, design_layout=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) {
            echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        $metal_type_val = ($metal_type === '' ? null : $metal_type);
        mysqli_stmt_bind_param($stmt, 'sddiiiiissi', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $design_layout_val, $id);
    } elseif ($has_preview_image) {
        $stmt = mysqli_prepare($conn, "UPDATE $table SET label_size_preset=?, label_width_mm=?, label_height_mm=?, font_size=?, show_product_name=?, show_price=?, show_barcode_number=?, print_copies=?, metal_type=?, preview_image=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) {
            echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        $metal_type_val = ($metal_type === '' ? null : $metal_type);
        $prev_val = $preview_image_val;
        mysqli_stmt_bind_param($stmt, 'sddiiiiissi', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $prev_val, $id);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE $table SET label_size_preset=?, label_width_mm=?, label_height_mm=?, font_size=?, show_product_name=?, show_price=?, show_barcode_number=?, print_copies=?, metal_type=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) {
            echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        $metal_type_val = ($metal_type === '' ? null : $metal_type);
        mysqli_stmt_bind_param($stmt, 'sddiiiiisi', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $id);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    $branch_ins = ($has_branch_col && $settings_bid > 0) ? (int) $settings_bid : 0;
    if ($has_design && $has_preview_image) {
        if ($branch_ins > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (branch_id, label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, design_layout, preview_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            $prev_val = $preview_image_val;
            mysqli_stmt_bind_param($stmt, 'isddiiiiisss', $branch_ins, $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $design_layout_val, $prev_val);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, design_layout, preview_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            $prev_val = $preview_image_val;
            mysqli_stmt_bind_param($stmt, 'sddiiiiisss', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $design_layout_val, $prev_val);
        }
    } elseif ($has_design) {
        if ($branch_ins > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (branch_id, label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, design_layout, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            mysqli_stmt_bind_param($stmt, 'isddiiiiiss', $branch_ins, $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $design_layout_val);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, design_layout, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            mysqli_stmt_bind_param($stmt, 'sddiiiiiss', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $design_layout_val);
        }
    } elseif ($has_preview_image) {
        if ($branch_ins > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (branch_id, label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, preview_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            $prev_val = $preview_image_val;
            mysqli_stmt_bind_param($stmt, 'isddiiiiiss', $branch_ins, $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $prev_val);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, preview_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            $prev_val = $preview_image_val;
            mysqli_stmt_bind_param($stmt, 'sddiiiiiss', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val, $prev_val);
        }
    } else {
        if ($branch_ins > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (branch_id, label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            mysqli_stmt_bind_param($stmt, 'isddiiiiis', $branch_ins, $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO $table (label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies, metal_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                echo json_encode(['success' => 0, 'message' => 'Database error: ' . mysqli_error($conn)]);
                exit;
            }
            $metal_type_val = ($metal_type === '' ? null : $metal_type);
            mysqli_stmt_bind_param($stmt, 'sddiiiiis', $label_size_preset, $label_width_mm, $label_height_mm, $font_size, $show_product_name, $show_price, $show_barcode_number, $print_copies, $metal_type_val);
        }
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($ok && $has_barcode_dims && $design_layout_val !== null && $design_layout_val !== '') {
    $decodedDim = json_decode($design_layout_val, true);
    if (is_array($decodedDim)) {
        $bw = isset($decodedDim['barcode_bar_width']) ? max(1, min(10, (int)$decodedDim['barcode_bar_width'])) : 2;
        $bh = isset($decodedDim['barcode_bar_height']) ? max(10, min(200, (int)$decodedDim['barcode_bar_height'])) : 28;
        $dimId = ($existing && !empty($existing['id'])) ? (int)$existing['id'] : (int)mysqli_insert_id($conn);
        if ($dimId > 0) {
            $stDim = mysqli_prepare($conn, "UPDATE $table SET barcode_bar_width=?, barcode_bar_height=? WHERE id=?");
            if ($stDim) {
                mysqli_stmt_bind_param($stDim, 'iii', $bw, $bh, $dimId);
                mysqli_stmt_execute($stDim);
                mysqli_stmt_close($stDim);
            }
        }
    }
}

$saved_row_id = ($existing && !empty($existing['id'])) ? (int) $existing['id'] : (int) mysqli_insert_id($conn);
if ($ok && $saved_row_id > 0) {
    if (function_exists('auragold_ensure_barcode_settings_qr_columns')) {
        auragold_ensure_barcode_settings_qr_columns($conn);
    }
    $has_qr_col = function_exists('auragold_tbl_has_column')
        ? auragold_tbl_has_column($conn, $table, 'design_layout_qr')
        : false;
    $has_dpt_col = function_exists('auragold_tbl_has_column')
        ? auragold_tbl_has_column($conn, $table, 'default_print_code_type')
        : false;
    if ($has_qr_col || $has_dpt_col) {
        $rid = $saved_row_id;
        $design_layout_qr = isset($_POST['design_layout_qr']) ? trim((string) $_POST['design_layout_qr']) : '';
        if ($design_layout_qr !== '' && strlen($design_layout_qr) > 16777215) {
            $design_layout_qr = substr($design_layout_qr, 0, 16777215);
        }
        /* Keep previous QR layout when client sends empty placeholder while saving barcode mode. */
        if (($design_layout_qr === '' || $design_layout_qr === '{}') && $has_qr_col) {
            $prevQr = getRecord("SELECT design_layout_qr FROM `$table` WHERE id = $rid LIMIT 1");
            $prevQrVal = isset($prevQr['design_layout_qr']) ? trim((string) $prevQr['design_layout_qr']) : '';
            if ($prevQrVal !== '' && $prevQrVal !== '{}') {
                $design_layout_qr = $prevQrVal;
            }
        }
        $dpt = isset($_POST['default_print_code_type']) ? strtolower(trim((string) $_POST['default_print_code_type'])) : $dpt_save;
        if ($dpt !== 'qr') {
            $dpt = 'barcode';
        }
        if ($has_qr_col && $has_dpt_col) {
            $stQr = mysqli_prepare($conn, "UPDATE `$table` SET `design_layout_qr`=?, `default_print_code_type`=? WHERE `id`=?");
            if ($stQr) {
                mysqli_stmt_bind_param($stQr, 'ssi', $design_layout_qr, $dpt, $rid);
                if (!mysqli_stmt_execute($stQr)) {
                    $ok = false;
                }
                mysqli_stmt_close($stQr);
            } else {
                $ok = false;
            }
        } elseif ($has_dpt_col) {
            $stDpt = mysqli_prepare($conn, "UPDATE `$table` SET `default_print_code_type`=? WHERE `id`=?");
            if ($stDpt) {
                mysqli_stmt_bind_param($stDpt, 'si', $dpt, $rid);
                mysqli_stmt_execute($stDpt);
                mysqli_stmt_close($stDpt);
            }
        } elseif ($has_qr_col) {
            $stOnlyQr = mysqli_prepare($conn, "UPDATE `$table` SET `design_layout_qr`=? WHERE `id`=?");
            if ($stOnlyQr) {
                mysqli_stmt_bind_param($stOnlyQr, 'si', $design_layout_qr, $rid);
                mysqli_stmt_execute($stOnlyQr);
                mysqli_stmt_close($stOnlyQr);
            }
        }
    }
}

if ($ok && $saved_row_id > 0 && auragold_tbl_has_column($conn, $table, 'show_product_name_barcode')) {
    $stSm = mysqli_prepare($conn, "UPDATE `$table` SET `show_product_name_barcode`=?, `show_product_name_qr`=?, `show_price_barcode`=?, `show_price_qr`=?, `show_barcode_number_barcode`=?, `show_barcode_number_qr`=? WHERE `id`=?");
    if ($stSm) {
        mysqli_stmt_bind_param($stSm, 'iiiiiii', $show_product_name_barcode, $show_product_name_qr, $show_price_barcode, $show_price_qr, $show_barcode_number_barcode, $show_barcode_number_qr, $saved_row_id);
        mysqli_stmt_execute($stSm);
        mysqli_stmt_close($stSm);
    }
}

auragold_ensure_barcode_settings_is_default_print_column($conn);
if ($ok && $saved_row_id > 0) {
    auragold_barcode_settings_apply_default_print_flag($conn, $saved_row_id, $metal_type, $is_default_print, $settings_bid);
}

if ($ok) {
    $verify_dpt = 'barcode';
    if ($saved_row_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'default_print_code_type')) {
        $vr = getRecord('SELECT default_print_code_type FROM `' . $table . '` WHERE id = ' . (int) $saved_row_id . ' LIMIT 1');
        if ($vr && isset($vr['default_print_code_type']) && $vr['default_print_code_type'] === 'qr') {
            $verify_dpt = 'qr';
        }
    }
    echo json_encode([
        'success' => 1,
        'message' => 'Barcode settings saved successfully.',
        'id' => $saved_row_id,
        'metal_type' => $metal_type,
        'label_size_preset' => $label_size_preset,
        'is_default_print' => $is_default_print,
        'default_print_code_type' => $verify_dpt,
    ]);
} else {
    $db_err = mysqli_error($conn);
    echo json_encode(['success' => 0, 'message' => 'Failed to save settings.' . ($db_err ? ' ' . $db_err : '')]);
}
