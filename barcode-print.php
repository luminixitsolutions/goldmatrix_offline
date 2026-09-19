<?php
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

/** CSS px → mm at 96dpi (1in = 25.4mm, 1in = 96px) — single source for bar/QR size from px. */
$PX_TO_MM = 0.264583;

$barcodes_param = isset($_GET['barcodes']) ? $_GET['barcodes'] : '';
$barcodes = !empty($barcodes_param) ? explode(',', $barcodes_param) : [];
if (empty($barcodes) && isset($_GET['barcode'])) {
    $barcodes = [$_GET['barcode']];
}
$barcodes = array_filter(array_map('trim', $barcodes));

$metal_for_print = '';
if (!empty($_GET['metal_type'])) {
    $metal_for_print = trim((string) $_GET['metal_type']);
} elseif (!empty($_GET['metal'])) {
    $metal_for_print = trim((string) $_GET['metal']);
}
if ($metal_for_print === '' && !empty($barcodes)) {
    $firstPrintRow = getBarcodePrintData($barcodes[0]);
    if (!empty($firstPrintRow['metal_name'])) {
        $metal_for_print = trim((string) $firstPrintRow['metal_name']);
    } elseif (!empty($firstPrintRow['MetalName'])) {
        $metal_for_print = trim((string) $firstPrintRow['MetalName']);
    }
    if ($metal_for_print === '') {
        $metal_for_print = auragold_resolve_metal_name_from_barcode($barcodes[0]);
    }
}

$label_for_print = '';
if (!empty($_GET['label_size_preset'])) {
    $label_for_print = trim((string) $_GET['label_size_preset']);
} elseif (!empty($_GET['barcode_label_size'])) {
    $label_for_print = trim((string) $_GET['barcode_label_size']);
}

$settings = getBarcodeSettingsForPrint(
    $metal_for_print !== '' ? $metal_for_print : null,
    $label_for_print !== '' ? $label_for_print : null
);
$print_code_kind = isset($_GET['code']) ? strtolower(trim((string)$_GET['code'])) : '';
if ($print_code_kind !== 'qr' && $print_code_kind !== 'barcode') {
    $print_code_kind = ($settings && isset($settings['default_print_code_type']) && $settings['default_print_code_type'] === 'qr') ? 'qr' : 'barcode';
}
/* Default stock: 100 mm × 50 mm (4 in × 2 in); match Windows printer custom paper or use 50×100 mm if rotated. */
$label_width_mm  = $settings ? (float)($settings['label_width_mm'] ?? 100) : 100;
$label_height_mm = $settings ? (float)($settings['label_height_mm'] ?? 50) : 50;
$label_size_preset = $settings ? trim((string)($settings['label_size_preset'] ?? '')) : '';
if ($label_for_print !== '') {
    $label_size_preset = auragold_barcode_label_storage_preset($label_for_print, $label_width_mm, $label_height_mm);
    [$label_width_mm, $label_height_mm] = auragold_barcode_label_mm_from_storage_preset(
        $label_size_preset,
        $label_width_mm,
        $label_height_mm
    );
}
$font_size       = $settings ? (int)($settings['font_size'] ?? 12) : 12;
$legacy_pn = $settings ? (int)($settings['show_product_name'] ?? 1) : 1;
$legacy_pr = $settings ? (int)($settings['show_price'] ?? 1) : 1;
$legacy_bn = $settings ? (int)($settings['show_barcode_number'] ?? 1) : 1;
if ($print_code_kind === 'qr') {
    $show_product_name   = $settings ? (int)($settings['show_product_name_qr'] ?? $legacy_pn) : 1;
    $show_price          = $settings ? (int)($settings['show_price_qr'] ?? $legacy_pr) : 1;
    $show_barcode_number = $settings ? ((int)($settings['show_barcode_number_qr'] ?? $legacy_bn) === 1) : true;
} else {
    $show_product_name   = $settings ? (int)($settings['show_product_name_barcode'] ?? $legacy_pn) : 1;
    $show_price          = $settings ? (int)($settings['show_price_barcode'] ?? $legacy_pr) : 1;
    $show_barcode_number = $settings ? ((int)($settings['show_barcode_number_barcode'] ?? $legacy_bn) === 1) : true;
}
$print_copies    = $settings ? max(1, min(100, (int)($settings['print_copies'] ?? 1))) : 1;
$design_layout   = [];
$barcode_bar_height_px = 28;
$barcode_bar_width_px  = 2;
$label_pad_top    = 0;
$label_pad_right  = 0;
$label_pad_bottom = 0;
$label_pad_left   = 0;
$barcode1_left_mm = null;
$barcode1_top_mm  = null;
$decoded_snapshot = null;
$raw_layout = '';
if ($settings) {
    if ($print_code_kind === 'qr') {
        $raw_layout = trim((string)($settings['design_layout_qr'] ?? ''));
    } else {
        $raw_layout = trim((string)($settings['design_layout'] ?? ''));
    }
}
if ($print_code_kind === 'qr' && $raw_layout === '') {
    die('QR layout is empty - not loaded from DB');
}
$is_82x38_2box_early = auragold_is_82x38_2box_sticker($label_size_preset);
if ($print_code_kind === 'barcode' && $raw_layout === '' && !$is_82x38_2box_early) {
    die('Barcode layout is empty - not loaded from DB');
}
if ($settings && $raw_layout !== '') {
    $decoded = json_decode($raw_layout, true);
    if (!is_array($decoded)) {
        $decoded = json_decode(stripslashes($raw_layout), true);
    }
    if (!is_array($decoded)) {
        $snippet = htmlspecialchars(substr($raw_layout, 0, 500), ENT_QUOTES, 'UTF-8');
        $kind = ($print_code_kind === 'qr') ? 'QR' : 'Barcode';
        die('Invalid ' . $kind . ' layout JSON: ' . $snippet);
    }
    if (isset($decoded['label_pad_top'])) {
        $label_pad_top = max(0, min(200, (int)$decoded['label_pad_top']));
    }
    if (isset($decoded['label_pad_right'])) {
        $label_pad_right = max(0, min(200, (int)$decoded['label_pad_right']));
    }
    if (isset($decoded['label_pad_bottom'])) {
        $label_pad_bottom = max(0, min(200, (int)$decoded['label_pad_bottom']));
    }
    if (isset($decoded['label_pad_left'])) {
        $label_pad_left = max(0, min(200, (int)$decoded['label_pad_left']));
    }
    if (isset($decoded['barcode1_left'])) {
        $barcode1_left_mm = max(0.0, (float)$decoded['barcode1_left']);
    }
    if (isset($decoded['barcode1_top'])) {
        $barcode1_top_mm = max(0.0, (float)$decoded['barcode1_top']);
    }
    if (isset($decoded['barcode_bar_height'])) {
        $barcode_bar_height_px = max(10, min(200, (int)$decoded['barcode_bar_height']));
    }
    if (isset($decoded['barcode_bar_width'])) {
        $barcode_bar_width_px = max(1, min(10, (int)$decoded['barcode_bar_width']));
    }
    if (isset($decoded['items']) && is_array($decoded['items'])) {
        $design_layout = $decoded['items'];
    } elseif (isset($decoded['fields']) && is_array($decoded['fields'])) {
        $design_layout = $decoded['fields'];
    } else {
        $design_layout = $decoded;
    }
    $decoded_snapshot = $decoded;
}

$product_names = [];
$prices = [];
if (!empty($_GET['product_names'])) $product_names = array_map('trim', explode(',', $_GET['product_names']));
if (!empty($_GET['prices'])) $prices = array_map('trim', explode(',', $_GET['prices']));

// Optional row data from Product List (before Save) - overrides DB for this barcode
$overrides = [];
$override_keys = ['gross_wt', 'less_wt', 'purity', 'final_wt', 'net_wt', 'pure_wt', 'product_name', 'design_no', 'huid_no', 'huid', 'amount', 'net_amt', 'rate', 'making_amount', 'sale_amount', 'quantity', 'short_code', 'karat', 'carat', 'carat_id', 'carat_name'];
foreach ($override_keys as $k) {
    if (isset($_GET[$k]) && (string)$_GET[$k] !== '') $overrides[$k] = $_GET[$k];
}

$is_82x38_2box = auragold_is_82x38_2box_sticker($label_size_preset);
$is_120x50_double_barcode = ($print_code_kind === 'barcode' && $label_size_preset === '120x50' && !$is_82x38_2box);

$barcode_items = [];
foreach ($barcodes as $i => $barcode) {
    $barcode = trim((string) $barcode);
    $row_data = getBarcodePrintData($barcode);
    $row_data['product_name'] = $row_data['product_name'] ?? ($row_data['ProductName'] ?? '');
    if (isset($product_names[$i]) && $product_names[$i] !== '') $row_data['product_name'] = $product_names[$i];
    $row_data['price'] = $row_data['NetAmount'] ?? $row_data['Amount'] ?? '';
    if (isset($prices[$i]) && $prices[$i] !== '') $row_data['price'] = $prices[$i];
    // Encode and human-readable line must use the same string as the print URL (scan / chosen row), not DB-only formatting.
    $row_data['BarcodeNo'] = $barcode;
    $row_data['Barcode']   = $barcode;
    // Apply overrides from Product List (unsaved row data) so label shows current values
    foreach ($overrides as $k => $v) {
        if ($k === 'product_name') { $row_data['product_name'] = $v; $row_data['ProductName'] = $v; continue; }
        if ($k === 'net_amt') { $row_data['NetAmount'] = $v; $row_data['Amount'] = $v; continue; }
        if ($k === 'amount') { $row_data['Amount'] = $v; $row_data['NetAmount'] = $v; continue; }
        if ($k === 'design_no') { $row_data['DesignNo'] = $v; continue; }
        if ($k === 'huid_no' || $k === 'huid') { $row_data['HUIDNo'] = $v; $row_data['huid_no'] = $v; continue; }
        if ($k === 'rate') { $row_data['Rate'] = $v; continue; }
        if ($k === 'making_amount') { $row_data['MakingAmount'] = $v; continue; }
        if ($k === 'sale_amount') { $row_data['SaleAmount'] = $v; $row_data['sale_amount'] = $v; continue; }
        if ($k === 'quantity') { $row_data['Quantity'] = $v; $row_data['quantity'] = $v; continue; }
        if ($k === 'short_code') { $row_data['ShortCode'] = $v; $row_data['short_code'] = $v; continue; }
        if ($k === 'carat_name') {
            $row_data['carat_name'] = $v;
            $row_data['Carat'] = $v;
            continue;
        }
        if ($k === 'karat' || $k === 'carat' || $k === 'carat_id') {
            $row_data['karat'] = $v;
            $row_data['carat'] = $v;
            $row_data['carat_id'] = $v;
            continue;
        }
        if (in_array($k, ['gross_wt', 'less_wt', 'purity', 'final_wt', 'net_wt', 'pure_wt'], true)) {
            $num = is_numeric($v) ? (float)$v : $v;
            if ($k === 'gross_wt') $row_data['GrossWt'] = $num;
            elseif ($k === 'less_wt') $row_data['LessWt'] = $num;
            elseif ($k === 'purity') { $row_data['Purity'] = $num; $row_data['ActualPurity'] = $num; }
            elseif ($k === 'final_wt') $row_data['FinalWt'] = $num;
            elseif ($k === 'net_wt') $row_data['NetWt'] = $num;
            elseif ($k === 'pure_wt') { $row_data['PureWt'] = $num; $row_data['PurityWt'] = $num; }
            continue;
        }
        $row_data[$k] = $v;
    }
    $row_data = auragold_barcode_enrich_print_data($row_data);
    $barcode_items[] = ['barcode' => $barcode, 'row' => $row_data];
}

/** Map 4-digit label scan → full barcode (0005 → BBR00005). Always register on print. */
$auragold_physical_scan_codes = [];
if ($print_code_kind !== 'qr' && $barcode_items !== []) {
    if (!function_exists('auragold_derive_physical_scan_code_from_barcode')) {
        require_once __DIR__ . '/includes/auragold_barcode_scan_map.php';
    }
    global $conn;
    $scan_db = (!empty($conn) && $conn instanceof mysqli) ? $conn : null;
    if (!isset($_SESSION['auragold_recent_print_scan_map']) || !is_array($_SESSION['auragold_recent_print_scan_map'])) {
        $_SESSION['auragold_recent_print_scan_map'] = [];
    }
    $branch_id = function_exists('auragold_physical_scan_code_branch_id')
        ? auragold_physical_scan_code_branch_id()
        : 0;
    $batch_scan_used = [];
    foreach ($barcode_items as $bi) {
        $bc = trim((string) ($bi['barcode'] ?? ''));
        if ($bc === '') {
            continue;
        }
        $derived = auragold_derive_physical_scan_code_from_barcode($bc);
        if ($derived === null || !auragold_is_valid_physical_scan_code($derived)) {
            continue;
        }
        $scan_code = $derived;
        if (isset($batch_scan_used[$scan_code])) {
            if ($scan_db && function_exists('auragold_next_free_physical_scan_code')) {
                $unique = auragold_next_free_physical_scan_code($scan_db, $branch_id, $batch_scan_used);
                if ($unique !== null && auragold_is_valid_physical_scan_code($unique)) {
                    $scan_code = $unique;
                }
            }
        }
        $batch_scan_used[$scan_code] = $bc;
        $_SESSION['auragold_recent_print_scan_map'][$scan_code] = $bc;
        if ($scan_db && function_exists('auragold_force_assign_scan_code_for_barcode')) {
            auragold_force_assign_scan_code_for_barcode($scan_db, $bc, $scan_code, $branch_id);
        }
        if ($is_82x38_2box) {
            $auragold_physical_scan_codes[$bc] = $scan_code;
        }
    }
}

/** Legacy path kept for non-derived codes on 82×38 only. */
if ($is_82x38_2box && $print_code_kind !== 'qr') {
    if (!function_exists('auragold_resolve_physical_scan_code_for_print')) {
        require_once __DIR__ . '/includes/auragold_barcode_scan_map.php';
    }
    global $conn;
    $scan_db = (!empty($conn) && $conn instanceof mysqli) ? $conn : null;
    foreach ($barcode_items as $bi) {
        $bc = trim((string) ($bi['barcode'] ?? ''));
        if ($bc === '' || isset($auragold_physical_scan_codes[$bc])) {
            continue;
        }
        $sc = auragold_resolve_physical_scan_code_for_print($scan_db, $bc);
        if ($sc !== null) {
            $auragold_physical_scan_codes[$bc] = $sc;
        }
    }
}

$items = [];
if ($is_82x38_2box) {
    /* 82×38 two-box sticker: always fill both boxes.
     * - 1 barcode  → same code on left (box1) and right (box2)
     * - 2+ barcodes → pair sequentially; odd leftover duplicates onto box2
     */
    $pair_count = (int) ceil(count($barcode_items) / 2);
    for ($p = 0; $p < $pair_count; $p++) {
        $first = $barcode_items[$p * 2];
        $second = isset($barcode_items[$p * 2 + 1]) ? $barcode_items[$p * 2 + 1] : $first;
        for ($c = 0; $c < $print_copies; $c++) {
            $items[] = [
                'box1' => $first,
                'box2' => $second,
            ];
        }
    }
} elseif ($is_120x50_double_barcode) {
    $label_width_mm = 120;
    $label_height_mm = 50;
    if (!is_array($decoded_snapshot)) {
        $decoded_snapshot = [];
    }
    if (!isset($decoded_snapshot['dual_quadrant_width_mm'])) {
        $decoded_snapshot['dual_quadrant_width_mm'] = 20.0;
    }
    if (!isset($decoded_snapshot['dual_quadrant_height_mm'])) {
        $decoded_snapshot['dual_quadrant_height_mm'] = 25.0;
    }
    $pair_count = (int) ceil(count($barcode_items) / 2);
    for ($p = 0; $p < $pair_count; $p++) {
        // 1st barcode → right tag center; 2nd → left tag center
        $first = $barcode_items[$p * 2];
        $second = isset($barcode_items[$p * 2 + 1]) ? $barcode_items[$p * 2 + 1] : null;
        for ($c = 0; $c < $print_copies; $c++) {
            $sticker = ['right' => $first];
            if ($second !== null) {
                $sticker['left'] = $second;
            }
            $items[] = $sticker;
        }
    }
} else {
    foreach ($barcode_items as $bi) {
        for ($c = 0; $c < $print_copies; $c++) {
            $items[] = $bi;
        }
    }
}

if (empty($items)) {
    die('No barcodes provided');
}

$item_count = count($items);
$print_total_height_mm = $item_count * $label_height_mm;

$use_design = true;
/* Absolute design fields ignore CSS padding — offsets are applied in renderBarcodeLayout via label_pad_*_mm. */
$label_pad_top_mm = round(max(0, min(200, (int) $label_pad_top)) * $PX_TO_MM, 2);
$label_pad_right_mm = round(max(0, min(200, (int) $label_pad_right)) * $PX_TO_MM, 2);
$label_pad_bottom_mm = round(max(0, min(200, (int) $label_pad_bottom)) * $PX_TO_MM, 2);
$label_pad_left_mm = round(max(0, min(200, (int) $label_pad_left)) * $PX_TO_MM, 2);
$label_inner_pad_style = 'padding:0;';
$label_inner_pad_style_simple = sprintf(
    'padding:%smm %smm %smm %smm;',
    $label_pad_top_mm,
    $label_pad_right_mm,
    $label_pad_bottom_mm,
    $label_pad_left_mm
);
// Bar height from settings (JsBarcode height is in px) → mm for simple template / design barcode block
$barcode_height_mm = round($barcode_bar_height_px * $PX_TO_MM, 2);
$barcode_height_mm = max(5, min(50, $barcode_height_mm));
foreach ($design_layout as $el) {
    if (isset($el['type']) && $el['type'] === 'barcode_image' && isset($el['height'])) {
        $barcode_height_mm = (float)$el['height'];
        break;
    }
}
$simple_barcode_w_mm = min(35, $label_width_mm * 0.35);
$simple_qr_w_px = 60;
$simple_qr_h_px = 60;
if (is_array($decoded_snapshot)) {
    if (isset($decoded_snapshot['qr_width'])) {
        $simple_qr_w_px = max(30, min(200, (int)$decoded_snapshot['qr_width']));
    }
    if (isset($decoded_snapshot['qr_height'])) {
        $simple_qr_h_px = max(30, min(200, (int)$decoded_snapshot['qr_height']));
    }
}
$simple_print_qr = ($print_code_kind === 'qr' && !$use_design);
if ($simple_print_qr) {
    $qw_mm = max(8, min((float)$label_width_mm * 0.95, round($simple_qr_w_px * $PX_TO_MM, 2)));
    $qh_mm = max(5, min(50, round($simple_qr_h_px * $PX_TO_MM, 2)));
    $side_mm = min($qw_mm, $qh_mm);
    $simple_barcode_w_mm = $side_mm;
    $barcode_height_mm    = $side_mm;
}
if ($print_code_kind !== 'qr') {
    $barcode_height_mm = max(5, min($barcode_height_mm, 30));
}
$render_settings = [
    'label_width_mm'     => $label_width_mm,
    'label_height_mm'    => $label_height_mm,
    'label_size_preset'  => $label_size_preset,
    'font_size'          => $font_size,
    'design_layout'      => $design_layout,
    'render_code_as'     => ($print_code_kind === 'qr') ? 'qr' : 'barcode',
    'px_to_mm'           => $PX_TO_MM,
    'barcode_height_mm'  => $barcode_height_mm,
    'design_left_inset_mm' => 0.0,
    'show_barcode_number' => $show_barcode_number,
    'label_pad_top_mm'   => $label_pad_top_mm,
    'label_pad_right_mm' => $label_pad_right_mm,
    'label_pad_bottom_mm'=> $label_pad_bottom_mm,
    'label_pad_left_mm'  => $label_pad_left_mm,
];
if ($is_82x38_2box) {
    $label_width_mm = 82;
    $label_height_mm = 38;
    if (!is_array($decoded_snapshot)) {
        $decoded_snapshot = [];
    }
    $render_settings['physical_scan_codes'] = $auragold_physical_scan_codes;
} elseif ($is_120x50_double_barcode) {
    $label_width_mm = 120;
    $label_height_mm = 50;
    if (!is_array($decoded_snapshot)) {
        $decoded_snapshot = [];
    }
    if (!isset($decoded_snapshot['dual_quadrant_width_mm'])) {
        $decoded_snapshot['dual_quadrant_width_mm'] = 20.0;
    }
    if (!isset($decoded_snapshot['dual_quadrant_height_mm'])) {
        $decoded_snapshot['dual_quadrant_height_mm'] = 25.0;
    }
}
/** CSS reference px/mm (96 dpi) — used for on-screen preview; print still uses mm. */
$css_px_per_mm = 96 / 25.4;
$label_w_px = (int) round($label_width_mm * $css_px_per_mm);
$label_h_px = (int) round($label_height_mm * $css_px_per_mm);
if ($is_120x50_double_barcode) {
    /** On-screen ruler size (landscape): W 8.2 cm × H 3.8 cm. Print stays 120×50 mm. */
    $screen_preview_w_cm = 8.2;
    $screen_preview_h_cm = 3.8;
    $screen_preview_w_mm = $screen_preview_w_cm * 10;
    $screen_preview_h_mm = $screen_preview_h_cm * 10;
    $screen_preview_w_px = (int) round($screen_preview_w_mm * $css_px_per_mm);
    $screen_preview_h_px = (int) round($screen_preview_h_mm * $css_px_per_mm);
    $screen_preview_scale_x = $screen_preview_w_mm / $label_width_mm;
    $screen_preview_scale_y = $screen_preview_h_mm / $label_height_mm;
} else {
    $screen_preview_w_cm = 0;
    $screen_preview_h_cm = 0;
    $screen_preview_w_mm = 0;
    $screen_preview_h_mm = 0;
    $screen_preview_w_px = 0;
    $screen_preview_h_px = 0;
    $screen_preview_scale_x = 1;
    $screen_preview_scale_y = 1;
}
$box_82x38_layout = $is_82x38_2box ? auragold_82x38_2box_layout(is_array($decoded_snapshot) ? $decoded_snapshot : []) : null;
$box_120x50_mm = $is_120x50_double_barcode ? auragold_120x50_quadrant_box_mm(is_array($decoded_snapshot) ? $decoded_snapshot : []) : ['width' => 20, 'height' => 25];
$pocket_left_120 = $is_120x50_double_barcode ? auragold_120x50_pocket_mm('left', is_array($decoded_snapshot) ? $decoded_snapshot : []) : [];
$pocket_right_120 = $is_120x50_double_barcode ? auragold_120x50_pocket_mm('right', is_array($decoded_snapshot) ? $decoded_snapshot : []) : [];
$barcode_120x50_fit_mm = $is_120x50_double_barcode ? auragold_120x50_quadrant_barcode_mm(is_array($decoded_snapshot) ? $decoded_snapshot : []) : ['width' => 32, 'height' => 8];
$render_settings['barcode_bar_width_px'] = max(1, min(10, $barcode_bar_width_px));
$render_settings['barcode_bar_height_px'] = max(10, min(200, $barcode_bar_height_px));

if ($barcode1_left_mm !== null && !$is_82x38_2box) {
    $render_settings['barcode1_left_mm'] = $barcode1_left_mm;
}
if ($barcode1_top_mm !== null && !$is_82x38_2box) {
    $render_settings['barcode1_top_mm'] = $barcode1_top_mm;
}

if (!empty($_GET['debug_qr_layout']) || !empty($_GET['debug_barcode_layout'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<pre>';
    echo 'print_code_kind: ' . htmlspecialchars($print_code_kind, ENT_QUOTES, 'UTF-8') . "\n";
    echo 'raw_layout length: ' . (int) strlen($raw_layout) . "\n\n";
    print_r($design_layout);
    echo '</pre>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcodes</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <?php if ($print_code_kind === 'qr'): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body.barcode-print {
            width: 100%;
            min-width: 100%;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff;
            height: auto !important;
            display: block;
        }
        body.barcode-print--120x50-double {
            width: 100%;
            max-width: none;
        }
        body.barcode-print--120x50-double .barcode-container {
            width: max-content;
            max-width: none;
        }
        .print-controls {
            background: #fff;
            padding: 15px;
            margin: 0 0 12px;
            border-radius: 0;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
        }
        .print-controls .print-controls-actions {
            display: flex;
            gap: 10px;
            flex: 0 0 auto;
        }
        .print-controls .print-meta {
            flex: 1 1 240px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }
        .print-controls .print-tip {
            flex: 1 1 100%;
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-primary { background: #11294b; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .barcode-container {
            display: block !important;
            margin: 0;
            padding: 0;
        }
        .barcode-label,
        .barcode-item {
            width: <?php echo $label_width_mm; ?>mm;
            height: <?php echo $label_height_mm; ?>mm !important;
            min-width: <?php echo $label_width_mm; ?>mm;
            min-height: <?php echo $label_height_mm; ?>mm;
            max-width: <?php echo $label_width_mm; ?>mm;
            max-height: <?php echo $label_height_mm; ?>mm !important;
            position: relative;
            box-sizing: border-box;
            overflow: hidden !important;
            background: #fff;
            margin: 0;
            border: 1px solid #1e293b;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .barcode-label-inner {
            position: relative;
            display: block;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            margin: 0;
            overflow: hidden;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        .barcode-print--simple .barcode-label-inner {
            padding: 0 !important;
        }
        .barcode-svg-wrap {
            position: absolute;
            left: 0;
            margin: 0;
            padding: 0;
            z-index: 2;
            overflow: hidden;
            display: block;
            box-sizing: border-box;
            image-rendering: crisp-edges;
            image-rendering: pixelated;
        }
        /* QR: fill the mm box; host sized in JS to a square that fits (no oversized 48px floor). */
        .barcode-svg-wrap.barcode-svg-wrap--qr {
            display: block;
            position: absolute;
            overflow: hidden;
            line-height: 0;
        }
        .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host {
            position: absolute;
            left: 0;
            top: 0;
            display: block;
            margin: 0;
            padding: 0;
            line-height: 0;
            overflow: hidden;
        }
        .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host canvas,
        .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host img {
            display: block !important;
            width: 100% !important;
            height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }
        .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host table {
            border-collapse: collapse !important;
            border-spacing: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            height: 100% !important;
            table-layout: fixed !important;
        }
        .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host table td {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            line-height: 0 !important;
        }
        /* Fill mm box; JsBarcode intrinsic aspect scales uniformly inside container */
        .barcode-svg-wrap:not(.barcode-svg-wrap--qr) svg,
        .barcode-print-wrap svg {
            width: 100% !important;
            height: 100% !important;
            margin: 0;
            display: block;
            shape-rendering: crispEdges;
            image-rendering: pixelated;
        }
        .barcode-print-preview-label {
            position: relative;
            box-sizing: border-box;
        }
        .barcode-print-wrap {
            position: absolute;
            overflow: hidden;
            box-sizing: border-box;
        }
        /* Simple template: row is left-aligned (flex:1 on the wrap was centering the SVG inside a wide box = fake left gap) */
        .barcode-print--simple .barcode-simple-block {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            box-sizing: border-box;
            width: 100%;
            height: 100%;
        }
        .barcode-print--simple .barcode-simple-main {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 2mm;
            width: 100%;
            box-sizing: border-box;
        }
        .barcode-print--simple .barcode-svg-wrap:not(.barcode-svg-wrap--qr) {
            position: relative !important;
            left: auto !important;
            top: auto !important;
            flex: 0 0 auto;
            max-width: 100%;
            min-width: 0;
        }
        .barcode-print--simple .barcode-svg-wrap.barcode-svg-wrap--qr {
            justify-content: flex-start !important;
        }
        .barcode-print--simple .barcode-price {
            position: relative !important;
            flex: 0 0 auto;
            text-align: left;
        }
        .barcode-print--simple .barcode-text {
            width: 100%;
            text-align: left;
        }
        .barcode-print--simple .barcode-product-name {
            width: 100%;
            text-align: left;
        }
        .design-field {
            position: absolute;
            color: #1e293b;
            white-space: nowrap;
            word-break: break-all;
            z-index: 0;
            font-size: <?php echo (int) $font_size; ?>px;
            box-sizing: border-box;
            background: #fff;
            padding: 0 !important;
            margin: 0 !important;
            line-height: 1 !important;
        }
        .design-field.design-strip-line,
        .sticker-strip-line {
            white-space: normal;
            word-break: normal;
            background: transparent !important;
            font-size: 0 !important;
            line-height: 0 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .design-field.design-white-strip {
            white-space: normal;
            word-break: normal;
            outline: none !important;
            box-shadow: none !important;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .sticker-fixed-half-strip,
        .sticker-fixed-white-strip {
            position: absolute;
            margin: 0;
            padding: 0;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background: #fff !important;
            background-color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            z-index: 4;
            overflow: hidden;
        }
        .sticker-fixed-half-strip .design-field,
        .sticker-fixed-half-strip .design-field--half-strip {
            background: transparent !important;
            background-color: transparent !important;
            max-width: 100%;
            overflow: hidden;
            line-height: 1 !important;
            padding: 0 !important;
            margin: 0 !important;
            z-index: 5;
        }
        @media print {
            .design-field.design-white-strip,
            .sticker-fixed-white-strip,
            .sticker-fixed-half-strip {
                outline: none !important;
                box-shadow: none !important;
                background: #fff !important;
                background-color: #fff !important;
            }
        }
        .barcode-product-name, .barcode-price, .barcode-text { margin: 0; padding: 0; }

        /* 120x50 jewelry sticker: fixed pockets (ignore design drag positions) */
        .barcode-print--120x50-double .full-sticker {
            width: <?php echo $label_width_mm; ?>mm;
            height: <?php echo $label_height_mm; ?>mm;
            position: relative;
            overflow: hidden;
            page-break-after: auto;
            break-after: auto;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            background: #fff;
            border: 1px solid #1e293b;
        }
        .barcode-print--120x50-double .full-sticker:not(:last-child) {
            page-break-after: always;
            break-after: page;
        }
        .barcode-print--120x50-double .full-sticker-inner {
            position: relative;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
        }
        .barcode-print--120x50-double .full-sticker-inner::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 0;
            border-left: 0.3mm dashed rgba(148, 163, 184, 0.45);
            transform: translateX(-50%);
            pointer-events: none;
            z-index: 1;
        }
        .barcode-print--120x50-double .full-sticker-inner::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 0;
            border-top: 0.3mm dashed rgba(148, 163, 184, 0.55);
            pointer-events: none;
            z-index: 1;
        }
        .barcode-print--120x50-double .full-sticker::before,
        .barcode-print--120x50-double .full-sticker::after {
            display: none;
        }
        .barcode-print--120x50-double .full-sticker:last-child,
        .barcode-print--120x50-double .full-sticker:only-child {
            page-break-after: avoid;
            break-after: avoid;
        }
        .barcode-sticker-measure-wrap {
            position: relative;
            display: inline-block;
            vertical-align: top;
            box-sizing: border-box;
            padding: 0 52px 40px 0;
            margin: 0;
        }
        .barcode-dim-ruler {
            position: absolute;
            pointer-events: none;
            z-index: 6;
            color: #16a34a;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }
        .barcode-dim-ruler--width {
            left: 0;
            right: 52px;
            bottom: 6px;
            height: 28px;
        }
        .barcode-dim-ruler--height {
            top: 0;
            right: 6px;
            bottom: 40px;
            width: 44px;
        }
        .barcode-dim-line {
            position: absolute;
            background: #22c55e;
        }
        .barcode-dim-line--h {
            left: 0;
            right: 0;
            top: 0;
            height: 2px;
        }
        .barcode-dim-line--h::before,
        .barcode-dim-line--h::after {
            content: '';
            position: absolute;
            top: -5px;
            width: 2px;
            height: 12px;
            background: #22c55e;
        }
        .barcode-dim-line--h::before { left: 0; }
        .barcode-dim-line--h::after { right: 0; }
        .barcode-dim-line--v {
            top: 0;
            bottom: 0;
            right: 0;
            width: 2px;
        }
        .barcode-dim-line--v::before,
        .barcode-dim-line--v::after {
            content: '';
            position: absolute;
            left: -5px;
            height: 2px;
            width: 12px;
            background: #22c55e;
        }
        .barcode-dim-line--v::before { top: 0; }
        .barcode-dim-line--v::after { bottom: 0; }
        .barcode-dim-width-val {
            position: absolute;
            left: 50%;
            top: 10px;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        .barcode-dim-height-val {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%) rotate(-90deg);
            white-space: nowrap;
        }
        @media screen {
            .barcode-print--120x50-double .barcode-container {
                padding: 12px;
            }
            /* Landscape on screen: W 8.2 cm × H 3.8 cm (82×38 mm); print sheet stays 120×50 mm */
            .barcode-print--120x50-double .full-sticker {
                width: <?php echo $screen_preview_w_px; ?>px !important;
                height: <?php echo $screen_preview_h_px; ?>px !important;
                min-width: <?php echo $screen_preview_w_px; ?>px;
                min-height: <?php echo $screen_preview_h_px; ?>px;
                max-width: none;
                max-height: none;
            }
            .barcode-print--120x50-double .full-sticker-inner {
                position: absolute;
                left: 0;
                top: 0;
                width: <?php echo $label_w_px; ?>px !important;
                height: <?php echo $label_h_px; ?>px !important;
                transform: scale(<?php echo round($screen_preview_scale_x, 6); ?>, <?php echo round($screen_preview_scale_y, 6); ?>);
                transform-origin: top left;
            }
        }
        @media print {
            .barcode-sticker-measure-wrap {
                padding: 0 !important;
            }
            .barcode-dim-ruler {
                display: none !important;
            }
        }
        .barcode-print--120x50-double .barcode-copy-right,
        .barcode-print--120x50-double .barcode-copy-left {
            position: absolute;
            right: auto;
            box-sizing: border-box;
            overflow: hidden;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .barcode-print--120x50-double .barcode-print-box-120x50 {
            border: 0.35mm dashed rgba(34, 197, 94, 0.75);
            background: #fff;
        }
        .barcode-print--120x50-double .barcode-print-box-label {
            position: absolute;
            left: 0.5mm;
            bottom: 0.4mm;
            font-size: 6px;
            line-height: 1;
            color: rgba(22, 163, 74, 0.9);
            background: rgba(255, 255, 255, 0.9);
            padding: 0.2mm 0.6mm;
            border-radius: 0.4mm;
            pointer-events: none;
            z-index: 3;
        }
        .barcode-print--120x50-double .barcode-copy-left {
            left: <?php echo $pocket_left_120['left'] ?? 17.5; ?>mm;
            right: auto;
            top: <?php echo $pocket_left_120['top'] ?? 25; ?>mm;
            width: <?php echo $pocket_left_120['width'] ?? $box_120x50_mm['width']; ?>mm;
            height: <?php echo $pocket_left_120['height'] ?? $box_120x50_mm['height']; ?>mm;
        }
        .barcode-print--120x50-double .barcode-copy-right {
            left: <?php echo isset($pocket_right_120['left']) ? $pocket_right_120['left'] : 78.5; ?>mm;
            right: auto;
            top: <?php echo $pocket_right_120['top'] ?? 25; ?>mm;
            width: <?php echo $pocket_right_120['width'] ?? $box_120x50_mm['width']; ?>mm;
            height: <?php echo $pocket_right_120['height'] ?? $box_120x50_mm['height']; ?>mm;
        }
        .barcode-print--120x50-double .barcode-120x50-graphic {
            overflow: hidden;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .barcode-print--120x50-double .barcode-120x50-graphic svg {
            display: block;
            width: 100%;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            transform: none;
            transform-origin: left center;
        }
        .barcode-print--120x50-double .barcode-copy-right svg,
        .barcode-print--120x50-double .barcode-copy-left svg {
            display: block;
            margin: 0;
            padding: 0;
            transform-origin: left top;
        }
        .barcode-print--120x50-double .barcode-copy-text {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            font-size: 8px;
            font-weight: 600;
            text-align: center;
            width: 100%;
            line-height: 1.1;
            margin: 0;
            padding: 0;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 4.5mm;
        }
        .barcode-print--120x50-double .barcode-120x50-graphic .barcode-print-wrap {
            display: none !important;
        }
        .barcode-print--120x50-double .barcode-120x50-design-inner {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: visible;
            box-sizing: border-box;
        }
        .barcode-print--120x50-double .barcode-120x50-design-inner .design-field {
            display: block !important;
            z-index: 3;
            line-height: 1.1 !important;
            overflow: visible;
        }
        .barcode-print--120x50-double .barcode-120x50-design-inner .design-field.design-field--company-name {
            z-index: 4;
        }
        .barcode-print--120x50-double .barcode-120x50-design-inner .design-field.design-strip-line {
            z-index: 1 !important;
        }

        /* 82×38 mm sticker — exact mm layout from saved design_layout inline styles. */
        .barcode-print--82x38-2box .barcode-container {
            width: 82mm;
            max-width: 82mm;
            margin: 0;
            padding: 0;
        }
        /* Print: exact 82×38 mm page — no preview centering wrapper */
        .barcode-print--82x38-2box .barcode-82x38-preview-wrapper {
            display: block !important;
            width: 82mm !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .barcode-print--82x38-2box .sticker-page {
            width: 82mm;
            height: 38mm;
            position: relative;
            overflow: hidden;
            page-break-after: auto;
            break-after: auto;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .barcode-print--82x38-2box .sticker-page:not(:last-child) {
            page-break-after: always;
            break-after: page;
        }
        @media screen {
            .barcode-print--82x38-2box .sticker-page {
                border: none;
            }
            .barcode-print--82x38-2box .barcode-box {
                border: none;
            }
        }
        .barcode-print--82x38-2box .sticker-page-horizontal-guide {
            display: none !important;
        }
        .barcode-print--82x38-2box .barcode-scale,
        .barcode-print--82x38-2box .scale-line,
        .barcode-print--82x38-2box .ruler,
        .barcode-print--82x38-2box .ruler-tick,
        .barcode-print--82x38-2box .ruler-label,
        .barcode-print--82x38-2box .measurement-line,
        .barcode-print--82x38-2box .measurement-guide,
        .barcode-print--82x38-2box .barcode-guide,
        .barcode-print--82x38-2box .barcode-guide-line,
        .barcode-print--82x38-2box .barcode-guide-text,
        .barcode-print--82x38-2box .barcode-label-center-guide,
        .barcode-print--82x38-2box .barcode-dim-ruler,
        .barcode-print--82x38-2box .barcode-dim-line {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        .barcode-print--82x38-2box .sticker-page:last-child,
        .barcode-print--82x38-2box .sticker-page:only-child {
            page-break-after: avoid;
            break-after: avoid;
        }
        .barcode-print--82x38-2box .barcode-box {
            position: absolute;
            overflow: hidden;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            width: 20mm !important;
            height: 25mm !important;
            min-width: 20mm !important;
            min-height: 25mm !important;
            max-width: 20mm !important;
            max-height: 25mm !important;
        }
        .barcode-print--82x38-2box .barcode-box-inner {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            box-sizing: border-box;
        }
        .barcode-print--82x38-2box .barcode-box-inner .barcode-print-wrap,
        .barcode-print--82x38-2box .barcode-box-inner .design-field {
            box-sizing: border-box;
        }
        .barcode-print--82x38-2box .barcode-box-inner .design-field {
            z-index: 3 !important;
        }
        .barcode-print--82x38-2box .barcode-box-inner .barcode-print-wrap.barcode-82x38-stack {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            box-sizing: border-box;
            overflow: hidden;
        }
        .barcode-print--82x38-2box .barcode-82x38-graphic {
            flex: 0 0 auto;
            overflow: visible !important;
            line-height: 0 !important;
            box-sizing: border-box !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            min-height: 8.5mm !important;
        }
        .barcode-print--82x38-2box .barcode-82x38-graphic svg,
        .barcode-print--82x38-2box .barcode-svg--82x38,
        .barcode-print--82x38-2box .barcode-svg-box1,
        .barcode-print--82x38-2box .barcode-svg-box2 {
            display: block !important;
            margin: 0 auto !important;
            padding: 0 !important;
            transform: none !important;
            image-rendering: pixelated !important;
            image-rendering: crisp-edges !important;
        }
        .barcode-print--82x38-2box .barcode-number,
        .barcode-print--82x38-2box .barcode-text {
            flex-shrink: 0 !important;
            width: 100% !important;
            text-align: center !important;
            line-height: 1.1 !important;
        }
        .barcode-print--82x38-2box .barcode-box-inner .barcode-print-wrap:not(.barcode-82x38-stack) .barcode-svg {
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            display: block;
        }
        .barcode-print--82x38-2box .sticker-page-horizontal-guide {
            display: none !important;
        }
        @media print {
            .barcode-print--82x38-2box .barcode-scale,
            .barcode-print--82x38-2box .scale-line,
            .barcode-print--82x38-2box .ruler,
            .barcode-print--82x38-2box .ruler-tick,
            .barcode-print--82x38-2box .ruler-label,
            .barcode-print--82x38-2box .measurement-line,
            .barcode-print--82x38-2box .measurement-guide,
            .barcode-print--82x38-2box .barcode-guide,
            .barcode-print--82x38-2box .barcode-guide-line,
            .barcode-print--82x38-2box .barcode-guide-text,
            .barcode-print--82x38-2box .barcode-label-center-guide,
            .barcode-print--82x38-2box .sticker-page-horizontal-guide,
            .barcode-print--82x38-2box .barcode-box-horizontal-line,
            .barcode-print--82x38-2box .barcode-82x38-outer-scale {
                display: none !important;
                visibility: hidden !important;
            }
            .barcode-print--82x38-2box .barcode-box,
            .barcode-print--82x38-2box .barcode-box * {
                box-shadow: none !important;
                border: none !important;
                outline: none !important;
            }
            .barcode-print--82x38-2box .sticker-page {
                border: none !important;
            }
        }

        @media print {

            /* Paper = 120 mm wide × 50 mm tall (landscape butterfly sticker). */
            @page {
                margin: 0;
                size: <?php echo $label_width_mm; ?>mm <?php echo $label_height_mm; ?>mm;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                width: <?php echo $label_width_mm; ?>mm !important;
                max-width: <?php echo $label_width_mm; ?>mm !important;
                height: auto !important;
                overflow: hidden !important;
            }

            html {
                min-height: 0 !important;
            }

            body {
                min-height: 0 !important;
                font-family: Arial, sans-serif !important;
                display: block !important;
            }

            * {
                box-sizing: border-box !important;
            }

            .print-controls {
                display: none !important;
            }

            .design-field {
                padding: 0 !important;
                margin: 0 !important;
                line-height: 1 !important;
            }

            .barcode-label-inner {
                position: relative !important;
                display: block !important;
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .barcode-print--design .barcode-label-inner {
                padding: 0 !important;
            }
            .barcode-print--simple .barcode-label-inner {
                padding: 0 !important;
            }

            .barcode-svg-wrap:not(.barcode-svg-wrap--qr),
            .barcode-print--simple .barcode-svg-wrap:not(.barcode-svg-wrap--qr) {
                display: block !important;
                image-rendering: crisp-edges !important;
                image-rendering: pixelated !important;
            }

            .barcode-svg-wrap.barcode-svg-wrap--qr {
                display: block !important;
                overflow: hidden !important;
                line-height: 0 !important;
            }
            .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                transform: none !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                line-height: 0 !important;
            }
            .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host canvas,
            .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host img {
                display: block !important;
                width: 100% !important;
                height: 100% !important;
                max-width: 100% !important;
                max-height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                image-rendering: pixelated !important;
                image-rendering: crisp-edges !important;
            }
            .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host table {
                border-collapse: collapse !important;
                border-spacing: 0 !important;
                width: 100% !important;
                height: 100% !important;
                table-layout: fixed !important;
            }
            .barcode-svg-wrap.barcode-svg-wrap--qr .qr-print-host table td {
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
                line-height: 0 !important;
            }

            .barcode-svg-wrap:not(.barcode-svg-wrap--qr) svg,
            .barcode-print-wrap:not(.barcode-82x38-stack) svg {
                width: auto !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: 100% !important;
                shape-rendering: crispEdges;
                image-rendering: pixelated;
            }
            .barcode-print--82x38-2box .barcode-print-wrap.barcode-82x38-stack {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: flex-start !important;
            }
            .barcode-print--82x38-2box .barcode-82x38-graphic {
                flex: 0 0 auto !important;
                overflow: hidden !important;
                line-height: 0 !important;
                box-sizing: border-box !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                height: 100% !important;
            }
            .barcode-print--82x38-2box .barcode-82x38-graphic svg,
            .barcode-print--82x38-2box .barcode-svg--82x38,
            .barcode-print--82x38-2box .barcode-svg-box1,
            .barcode-print--82x38-2box .barcode-svg-box2 {
                display: block !important;
                margin: 0 auto !important;
                padding: 0 !important;
                transform: none !important;
                position: relative !important;
                shape-rendering: crispEdges;
                image-rendering: pixelated !important;
                image-rendering: crisp-edges !important;
            }
            .barcode-print--82x38-2box .barcode-number,
            .barcode-print--82x38-2box .barcode-text {
                flex-shrink: 0 !important;
                width: 100% !important;
                text-align: center !important;
                line-height: 1.1 !important;
            }

            .barcode-print-preview-label {
                position: relative !important;
                box-sizing: border-box !important;
            }

            .barcode-label,
            .barcode-item {
                width: <?php echo $label_width_mm; ?>mm !important;
                height: <?php echo $label_height_mm; ?>mm !important;
                max-height: <?php echo $label_height_mm; ?>mm !important;
                margin: 0;
                padding: 0;
                overflow: hidden;
                box-sizing: border-box;
                border: none !important;
                outline: none !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                page-break-after: auto !important;
                break-after: auto !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .barcode-container > .barcode-label:not(:last-child),
            .barcode-container > .barcode-item:not(:last-child) {
                page-break-after: always !important;
                break-after: page !important;
            }
            .barcode-container > .barcode-label:last-child,
            .barcode-container > .barcode-item:last-child,
            .barcode-container > .barcode-label:only-child,
            .barcode-container > .barcode-item:only-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            .barcode-print--120x50-double .full-sticker {
                width: <?php echo $label_width_mm; ?>mm !important;
                height: <?php echo $label_height_mm; ?>mm !important;
                max-width: <?php echo $label_width_mm; ?>mm !important;
                max-height: <?php echo $label_height_mm; ?>mm !important;
                min-width: <?php echo $label_width_mm; ?>mm !important;
                min-height: <?php echo $label_height_mm; ?>mm !important;
                position: relative !important;
                overflow: hidden !important;
                page-break-after: auto !important;
                break-after: auto !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                zoom: 1 !important;
                transform: none !important;
            }
            .barcode-print--120x50-double .full-sticker:not(:last-child) {
                page-break-after: always !important;
                break-after: page !important;
            }
            .barcode-print--120x50-double .full-sticker-inner {
                position: relative !important;
                left: auto !important;
                top: auto !important;
                width: 100% !important;
                height: 100% !important;
                transform: none !important;
            }
            .barcode-print--120x50-double .full-sticker-inner::before {
                border-left: 0.3mm dashed rgba(148, 163, 184, 0.45) !important;
            }
            .barcode-print--120x50-double .full-sticker-inner::after {
                border-top: 0.3mm dashed rgba(148, 163, 184, 0.55) !important;
            }
            .barcode-print--120x50-double .full-sticker:last-child,
            .barcode-print--120x50-double .full-sticker:only-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .barcode-print--120x50-double .barcode-copy-left {
                position: absolute !important;
                left: <?php echo $pocket_left_120['left'] ?? 17.5; ?>mm !important;
                right: auto !important;
                top: <?php echo $pocket_left_120['top'] ?? 25; ?>mm !important;
                width: <?php echo $pocket_left_120['width'] ?? $box_120x50_mm['width']; ?>mm !important;
                height: <?php echo $pocket_left_120['height'] ?? $box_120x50_mm['height']; ?>mm !important;
                overflow: hidden !important;
            }
            .barcode-print--120x50-double .barcode-copy-right {
                position: absolute !important;
                left: <?php echo isset($pocket_right_120['left']) ? $pocket_right_120['left'] : 78.5; ?>mm !important;
                right: auto !important;
                top: <?php echo $pocket_right_120['top'] ?? 25; ?>mm !important;
                width: <?php echo $pocket_right_120['width'] ?? $box_120x50_mm['width']; ?>mm !important;
                height: <?php echo $pocket_right_120['height'] ?? $box_120x50_mm['height']; ?>mm !important;
                overflow: hidden !important;
            }
            .barcode-print--120x50-double .barcode-print-box-label {
                display: none !important;
            }
            .barcode-print--120x50-double .barcode-print-box-120x50 {
                border: none !important;
            }
            .barcode-print--120x50-double .barcode-120x50-graphic {
                overflow: hidden !important;
                box-sizing: border-box !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .barcode-print--120x50-double .barcode-120x50-graphic svg {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                max-width: 100% !important;
                max-height: 100% !important;
                transform: none !important;
                transform-origin: left center !important;
            }

            .barcode-print--120x50-double .barcode-container {
                width: <?php echo $label_width_mm; ?>mm !important;
                max-width: <?php echo $label_width_mm; ?>mm !important;
            }

            .barcode-print--82x38-2box .sticker-page {
                width: 82mm !important;
                height: 38mm !important;
                max-width: 82mm !important;
                max-height: 38mm !important;
                min-width: 82mm !important;
                min-height: 38mm !important;
                position: relative !important;
                overflow: hidden !important;
                page-break-after: auto !important;
                break-after: auto !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                zoom: 1 !important;
                transform: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .barcode-print--82x38-2box .sticker-page:not(:last-child) {
                page-break-after: always !important;
                break-after: page !important;
            }
            .barcode-print--82x38-2box .sticker-page:last-child,
            .barcode-print--82x38-2box .sticker-page:only-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .barcode-print--82x38-2box .barcode-box {
                position: absolute !important;
                border: none !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .barcode-print--82x38-2box .barcode-number {
                text-align: center !important;
                font-weight: 600 !important;
                line-height: 1 !important;
            }
            .barcode-print--82x38-2box .barcode-container {
                width: 82mm !important;
                max-width: 82mm !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            html:has(body.barcode-print--82x38-2box),
            body.barcode-print.barcode-print--82x38-2box {
                width: 82mm !important;
                max-width: 82mm !important;
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
                margin: 0 !important;
                padding: 0 !important;
                zoom: 1 !important;
                transform: none !important;
                overflow: hidden !important;
            }
            body.barcode-print.barcode-print--82x38-2box .barcode-container {
                height: auto !important;
                max-height: none !important;
                overflow: hidden !important;
            }
            body.barcode-print.barcode-print--single-page .barcode-container > .sticker-page:only-child,
            body.barcode-print.barcode-print--single-page .barcode-container > .full-sticker:only-child,
            body.barcode-print.barcode-print--single-page .barcode-container > .barcode-label:only-child,
            body.barcode-print.barcode-print--single-page .barcode-container > .barcode-item:only-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }

            .barcode-print--design svg:not(.barcode-svg--120x50):not(.barcode-svg--82x38) {
                transform: rotate(0deg) !important;
            }

            .barcode-print--simple .barcode-svg-wrap svg {
                transform: rotate(0deg) !important;
            }

            .barcode-container {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                width: <?php echo ($is_120x50_double_barcode || $is_82x38_2box) ? $label_width_mm . 'mm' : '100%'; ?> !important;
                height: auto !important;
                overflow: hidden !important;
            }

            .barcode-label {
                display: block !important;
                transform: scale(1) !important;
                zoom: 1 !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

        }
    </style>
</head>
<body class="barcode-print <?php echo $use_design ? 'barcode-print--design' : 'barcode-print--simple'; ?><?php echo $is_120x50_double_barcode ? ' barcode-print--120x50-double' : ''; ?><?php echo $is_82x38_2box ? ' barcode-print--82x38-2box' : ''; ?><?php echo $item_count === 1 ? ' barcode-print--single-page' : ''; ?>">
    <div class="print-controls no-print">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
        <span style="margin-left: auto; color: #64748b;"><?php
            if ($is_120x50_double_barcode) {
                echo count($items) . ' sticker(s) · barcodes centered below fold line · print box 2 cm × 2.5 cm · 120mm × 50mm';
            } else {
                echo count($items) . ' label(s)';
            }
        ?> · Label size: <strong><?php echo $label_width_mm; ?>mm × <?php echo $label_height_mm; ?>mm</strong></span>
        <span class="print-tip" style="font-size: 12px; color: #64748b;">Chrome print: <strong>Margins → None</strong>, <strong>Scale 100%</strong>, turn off <strong>Fit to page</strong>. In the print dialog set <strong>Layout → Landscape</strong> (not Portrait). Windows printer paper must be <strong><?php echo $label_width_mm; ?>×<?php echo $label_height_mm; ?> mm</strong> (<?php echo $label_width_mm; ?> mm wide). If preview looks like a thin vertical strip, the paper size is wrong — create custom paper <?php echo $label_width_mm; ?> mm × <?php echo $label_height_mm; ?> mm on the 4BARCODE driver.<?php if ($is_120x50_double_barcode): ?> On-screen outer box is scaled for ruler check (target <strong><?php echo (int) round($label_width_mm / 10); ?> cm × <?php echo (int) round($label_height_mm / 10); ?> cm</strong>); browser zoom must be <strong>100%</strong>.</span><?php else: ?></span><?php endif; ?>
    </div>
    <div class="barcode-container" id="barcodeContainer">
        <?php
        if ($is_82x38_2box):
            foreach ($items as $pageIdx => $item):
                echo render82x38DoubleStickerLabel(
                    $item,
                    $render_settings,
                    is_array($decoded_snapshot) ? $decoded_snapshot : [],
                    (int) $pageIdx
                );
            endforeach;
        elseif ($is_120x50_double_barcode):
            foreach ($items as $item):
                echo render120x50DoubleStickerLabel(
                    $item,
                    $render_settings,
                    is_array($decoded_snapshot) ? $decoded_snapshot : []
                );
            endforeach;
        else:
            foreach ($items as $item):
                $productData = array_merge($item['row'], ['barcode' => $item['barcode']]);
                if ($use_design):
                    $inner_html = renderBarcodeLayout($productData, $render_settings);
        ?>
            <div class="barcode-label barcode-item" style="width:<?php echo $label_width_mm; ?>mm;height:<?php echo $label_height_mm; ?>mm;">
                <div class="barcode-label-inner barcode-print-preview-label" style="position:relative;width:<?php echo $label_width_mm; ?>mm;height:<?php echo $label_height_mm; ?>mm;box-sizing:border-box;<?php echo htmlspecialchars($label_inner_pad_style, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $inner_html; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="barcode-label barcode-item" style="width:<?php echo $label_width_mm; ?>mm;height:<?php echo $label_height_mm; ?>mm;">
                <div class="barcode-label-inner" style="<?php echo htmlspecialchars($label_inner_pad_style_simple, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="barcode-simple-block" style="<?php echo ($barcode1_top_mm !== null && $barcode1_top_mm > 0) ? 'margin-top:' . round($barcode1_top_mm, 2) . 'mm;' : ''; ?>">
                        <?php if ($show_product_name): ?>
                            <div class="barcode-product-name" style="font-size:<?php echo $font_size; ?>px;"><?php echo htmlspecialchars($item['row']['product_name'] !== '' ? $item['row']['product_name'] : ''); ?></div>
                        <?php endif; ?>
                        <div class="barcode-simple-main">
                            <div class="barcode-svg-wrap barcode-svg-wrap--qr" style="width:<?php echo round($simple_barcode_w_mm, 2); ?>mm;height:<?php echo round($barcode_height_mm, 2); ?>mm;">
                                <?php if ($simple_print_qr): ?>
                                <div class="qr-print-host" data-barcode="<?php echo htmlspecialchars($item['barcode']); ?>" style="width:100%;height:100%;"></div>
                                <?php else: ?>
                                <svg class="barcode-svg" data-barcode="<?php echo htmlspecialchars($item['barcode']); ?>"></svg>
                                <?php endif; ?>
                            </div>
                            <?php if ($show_price): ?>
                                <div class="barcode-price" style="font-size:<?php echo $font_size; ?>px;"><?php echo htmlspecialchars($item['row']['price'] !== '' ? $item['row']['price'] : ''); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($show_barcode_number): ?>
                            <div class="barcode-text" style="font-size:<?php echo $font_size; ?>px;"><?php echo htmlspecialchars($item['row']['BarcodeNo'] ?? $item['barcode']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php
                endif;
            endforeach;
        endif;
        ?>
    </div>
    <script>
        var auragoldPrintBarW = <?php echo (int) max(1, min(10, $barcode_bar_width_px)); ?>;
        var auragoldPrintBarH = <?php echo (int) max(10, min(200, $barcode_bar_height_px)); ?>;
        var auragoldPrintPxToMm = 0.264583;
        var auragoldCssPxPerMm = 96 / 25.4;
        var auragold82x38ScanMapByBarcode = <?php echo json_encode($auragold_physical_scan_codes ?? [], JSON_UNESCAPED_UNICODE); ?>;
        function auragoldDeriveScanCodeFromBarcode(barcode) {
            barcode = String(barcode || '').trim();
            if (!barcode) return '';
            var m = barcode.match(/(\d{4})$/);
            if (m) return m[1];
            m = barcode.match(/(\d+)$/);
            if (m) {
                var n = parseInt(m[1], 10);
                if (n >= 1 && n <= 9999) {
                    return ('0000' + String(n)).slice(-4);
                }
            }
            return '';
        }
        function auragoldShouldEncodeFull82x38Barcode(barcode) {
            barcode = String(barcode || '').trim();
            /* Only short codes (≤7) fit full prefix+number and scan on 19 mm tag. */
            if (!barcode || barcode.length > 7) {
                return false;
            }
            /* ASD123, B0001 — encode full string so scanner returns prefix + number. */
            return /^[A-Za-z][A-Za-z0-9]{0,6}$/.test(barcode);
        }
        function auragold82x38ModuleLimitsForCode(code, useScanCode, useFullBarcode) {
            if (useScanCode) {
                /* 4-digit (0005): thick modules + tall bars = reliable thermal scan. */
                return { base: 2, min: 2, max: 3, margin: 2, barHMul: 0.88, minBarRatio: 0.72 };
            }
            if (useFullBarcode) {
                return { base: 1, min: 1, max: 2, margin: 2, barHMul: 0.80, minBarRatio: 0.68 };
            }
            return { base: 1, min: 1, max: 3, margin: 2, barHMul: 0.78, minBarRatio: 0.65 };
        }
        function auragoldResolve82x38EncodeValue(el) {
            var actualBarcode = String(el.getAttribute('data-barcode') || '').trim();
            /* Print-assigned code wins (unique when suffix 0001 collides: BA00001 vs BBR00001). */
            if (actualBarcode && auragold82x38ScanMapByBarcode && auragold82x38ScanMapByBarcode[actualBarcode]) {
                var assigned = String(auragold82x38ScanMapByBarcode[actualBarcode]).trim();
                if (/^\d{4}$/.test(assigned)) {
                    el.setAttribute('data-scan-code', assigned);
                    return assigned;
                }
            }
            if (auragoldShouldEncodeFull82x38Barcode(actualBarcode)) {
                return actualBarcode;
            }
            /* Long codes (BNG00010): 4-digit suffix only — fits 19 mm thermal tag. */
            var derived = auragoldDeriveScanCodeFromBarcode(actualBarcode);
            if (/^\d{4}$/.test(derived)) {
                el.setAttribute('data-scan-code', derived);
                return derived;
            }
            var scanCode = String(el.getAttribute('data-scan-code') || '').trim();
            if (/^\d{4}$/.test(scanCode)) {
                el.setAttribute('data-scan-code', scanCode);
                return scanCode;
            }
            return actualBarcode;
        }
        function auragoldMeasureBrowserPxPerMm() {
            var probe = document.createElement('div');
            probe.style.cssText = 'position:fixed;left:-9999px;top:0;width:100mm;height:1px;visibility:hidden;pointer-events:none;';
            document.documentElement.appendChild(probe);
            var probeW = probe.getBoundingClientRect().width;
            probe.remove();
            return (probeW > 0) ? (probeW / 100) : auragoldCssPxPerMm;
        }
        /** Scale CSS box so a physical ruler reads 8.2×3.8 cm (Windows display scaling shrinks CSS mm on screen). */
        function auragoldPhysicalScreenScale(axis) {
            var dpr = window.devicePixelRatio || 1;
            if (dpr >= 1.05) return dpr;
            return axis === 'h' ? (3.8 / 3.1) : (8.2 / 6.8);
        }
        function auragoldApply120x50ScreenTrueSize() {
            if (!document.body.classList.contains('barcode-print--120x50-double')) return;
            if (window.matchMedia('print').matches) return;
            var pxPerMm = auragoldMeasureBrowserPxPerMm();
            var scaleW = auragoldPhysicalScreenScale('w');
            var scaleH = auragoldPhysicalScreenScale('h');
            var sheetWMm = <?php echo (int) $label_width_mm; ?>;
            var sheetHMm = <?php echo (int) $label_height_mm; ?>;
            var screenWCm = <?php echo $screen_preview_w_cm; ?>;
            var screenHCm = <?php echo $screen_preview_h_cm; ?>;
            var outerWPx = Math.round(screenWCm * 10 * pxPerMm * scaleW);
            var outerHPx = Math.round(screenHCm * 10 * pxPerMm * scaleH);
            var innerWPx = Math.round(sheetWMm * pxPerMm);
            var innerHPx = Math.round(sheetHMm * pxPerMm);
            var scaleX = (innerWPx > 0) ? (outerWPx / innerWPx) : 1;
            var scaleY = (innerHPx > 0) ? (outerHPx / innerHPx) : 1;
            document.querySelectorAll('.full-sticker').forEach(function(el) {
                el.style.zoom = '1';
                el.style.width = outerWPx + 'px';
                el.style.height = outerHPx + 'px';
                el.style.minWidth = outerWPx + 'px';
                el.style.minHeight = outerHPx + 'px';
                el.setAttribute('data-phys-scale-w', String(scaleW));
                el.setAttribute('data-phys-scale-h', String(scaleH));
                var inner = el.querySelector('.full-sticker-inner');
                if (inner) {
                    inner.style.position = 'absolute';
                    inner.style.left = '0';
                    inner.style.top = '0';
                    inner.style.width = innerWPx + 'px';
                    inner.style.height = innerHPx + 'px';
                    inner.style.transform = 'scale(' + scaleX + ',' + scaleY + ')';
                    inner.style.transformOrigin = 'top left';
                }
            });
            auragoldUpdateStickerDimensionRulers();
            auragoldRenderAll120x50Barcodes();
        }
        function auragoldUpdateStickerDimensionRulers() {
            document.querySelectorAll('.barcode-sticker-measure-wrap').forEach(function(wrap) {
                var sticker = wrap.querySelector('.full-sticker');
                if (!sticker) return;
                var screenWCm = parseFloat(sticker.getAttribute('data-screen-w-cm') || '8.2');
                var screenHCm = parseFloat(sticker.getAttribute('data-screen-h-cm') || '3.8');
                var wVal = wrap.querySelector('.barcode-dim-width-val');
                var hVal = wrap.querySelector('.barcode-dim-height-val');
                if (wVal) wVal.textContent = 'W = ' + screenWCm.toFixed(1) + ' cm';
                if (hVal) hVal.textContent = 'H = ' + screenHCm.toFixed(1) + ' cm';
            });
        }
        function auragoldFillQrPrintHosts() {
            if (typeof QRCode === 'undefined') return;
            document.querySelectorAll('.qr-print-host').forEach(function(host) {
                var barcodeValue = host.getAttribute('data-barcode');
                if (!barcodeValue) return;
                var text = String(barcodeValue).trim();
                if (!text) return;
                host.innerHTML = '';
                var par = host.parentElement;
                var boxW = 0;
                var boxH = 0;
                if (par) {
                    boxW = par.clientWidth || par.offsetWidth || 0;
                    boxH = par.clientHeight || par.offsetHeight || 0;
                }
                /* Fit square QR inside the mm box. Never force 48px — that clipped tiny 82×38 QRs. */
                var size = Math.floor(Math.min(boxW, boxH));
                if (!(size > 0)) {
                    /* Fallback when layout not ready: ~mm box via CSS pixels. */
                    var mmMatch = par && String(par.style.width || '').match(/([\d.]+)mm/);
                    var mmSide = mmMatch ? parseFloat(mmMatch[1]) : 8;
                    size = Math.max(16, Math.round(mmSide * (96 / 25.4)));
                }
                size = Math.max(16, Math.min(400, size));
                host.style.width = size + 'px';
                host.style.height = size + 'px';
                host.style.left = '0';
                host.style.top = '0';
                host.style.transform = 'none';
                host.style.lineHeight = '0';
                host.style.overflow = 'hidden';
                try {
                    var opts = { text: text, width: size, height: size, correctLevel: QRCode.CorrectLevel.M };
                    /* Prefer canvas when available — table QR prints as vertical streaks on small labels. */
                    if (typeof opts.useSVG !== 'undefined') {
                        opts.useSVG = false;
                    }
                    new QRCode(host, opts);
                    var canvas = host.querySelector('canvas');
                    var table = host.querySelector('table');
                    if (canvas) {
                        canvas.style.width = '100%';
                        canvas.style.height = '100%';
                        canvas.style.display = 'block';
                        if (table) table.style.display = 'none';
                    } else if (table) {
                        table.style.width = '100%';
                        table.style.height = '100%';
                    }
                } catch (e) {
                    host.innerHTML = '<span style="font-size:8px;">' + String(text).replace(/</g, '&lt;') + '</span>';
                }
            });
        }
        function auragoldFit120x50SvgToGraphic(el) {
            auragoldFitBoxBarcodeSvgToGraphic(el, '.barcode-120x50-graphic');
        }
        function auragoldMmFromStyle(el, cssProp) {
            if (!el) return 0;
            var raw = el.style[cssProp] || '';
            if (!raw) {
                try { raw = window.getComputedStyle(el)[cssProp] || ''; } catch (e) { raw = ''; }
            }
            var m = String(raw).match(/([\d.]+)\s*mm/i);
            return m ? parseFloat(m[1]) : 0;
        }
        function auragoldIs82x38ScanSvg(el) {
            return el.classList.contains('barcode-svg--82x38')
                || el.classList.contains('barcode-svg-box1')
                || el.classList.contains('barcode-svg-box2');
        }
        function auragoldFitBoxBarcodeSvgToGraphic(el, graphicSelector) {
            var graphic = el.closest('.barcode-82x38-graphic') || el.closest(graphicSelector);
            if (!graphic) return;
            var boxWMm = parseFloat(el.getAttribute('data-barcode-width-mm') || '0');
            var boxHMm = parseFloat(el.getAttribute('data-barcode-graphic-height-mm') || el.getAttribute('data-barcode-height-mm') || '0');
            /* Fixed CSS px/mm for print — do not use clientWidth (box1 was rendering narrower than box2). */
            var pxPerMm = auragoldCssPxPerMm;
            if (!(boxWMm > 0)) {
                boxWMm = auragoldMmFromStyle(graphic.closest('.barcode-print-wrap') || graphic, 'width');
                pxPerMm = auragoldMeasureBrowserPxPerMm();
            }
            if (!(boxHMm > 0)) {
                boxHMm = auragoldMmFromStyle(graphic, 'height');
                if (!(boxWMm > 0)) {
                    pxPerMm = auragoldMeasureBrowserPxPerMm();
                }
            }
            var boxW = boxWMm > 0 ? Math.max(1, Math.round(boxWMm * pxPerMm)) : Math.max(1, graphic.clientWidth);
            var boxH = boxHMm > 0 ? Math.max(1, Math.round(boxHMm * pxPerMm)) : Math.max(1, graphic.clientHeight);
            if (boxW <= 0 || boxH <= 0) return;
            var is82x38Scan = auragoldIs82x38ScanSvg(el);
            var code = is82x38Scan
                ? auragoldResolve82x38EncodeValue(el)
                : String(el.getAttribute('data-barcode') || '').trim();
            if (!code) return;
            var useScanCode = is82x38Scan && /^\d{4}$/.test(code);
            var useFullBarcode = is82x38Scan && !useScanCode && /^[A-Za-z]/.test(code);
            var modLimits = is82x38Scan
                ? auragold82x38ModuleLimitsForCode(code, useScanCode, useFullBarcode)
                : { base: Math.max(2, auragoldPrintBarW), min: 1, max: 12, margin: 3, barHMul: 2.5, minBarRatio: 0.5 };
            var baseModW = modLimits.base;
            var minModW = modLimits.min;
            var maxModW = modLimits.max;
            var barMargin = modLimits.margin;
            var minBarHPx = Math.max(28, Math.round(boxH * (modLimits.minBarRatio || 0.65)));
            var targetBarH = is82x38Scan
                ? Math.max(minBarHPx, Math.round(boxH * modLimits.barHMul))
                : Math.max(24, Math.round(boxH * modLimits.barHMul));
            var natW = 0;
            var natH = 0;
            var modW = baseModW;

            function readNatSize() {
                var nw = 0;
                var nh = 0;
                try {
                    var bb = el.getBBox();
                    nw = bb.width;
                    nh = bb.height;
                } catch (e) {}
                return { w: nw, h: nh };
            }

            function draw(modWIn, barH) {
                el.innerHTML = '';
                JsBarcode(el, code, {
                    format: 'CODE128',
                    renderer: 'svg',
                    width: modWIn,
                    height: barH,
                    displayValue: false,
                    margin: is82x38Scan ? barMargin : 3,
                    marginTop: 0,
                    marginBottom: 0,
                    marginLeft: is82x38Scan ? barMargin : 3,
                    marginRight: is82x38Scan ? barMargin : 3,
                    background: '#fff',
                    lineColor: '#000'
                });
            }

            function widthFitLoop() {
                modW = baseModW;
                draw(modW, targetBarH);
                var nat = readNatSize();
                natW = nat.w;
                natH = nat.h;
                for (var pass = 0; pass < 14 && natW > 0 && natW < boxW * 0.96; pass++) {
                    modW = Math.min(maxModW, Math.max(minModW, modW * (boxW / natW) * 1.006));
                    draw(modW, targetBarH);
                    nat = readNatSize();
                    natW = nat.w;
                    natH = nat.h;
                }
                if (natW > boxW * 1.015) {
                    modW = Math.max(minModW, Math.min(maxModW, modW * (boxW / natW) * 0.998));
                    draw(modW, targetBarH);
                    nat = readNatSize();
                    natW = nat.w;
                    natH = nat.h;
                }
            }

            for (var attempt = 0; attempt < 8; attempt++) {
                widthFitLoop();
                if (!(natW > 0) || !(natH > 0)) {
                    return;
                }
                var scaledH = (natH * boxW) / natW;
                if (scaledH <= boxH || targetBarH <= minBarHPx) {
                    break;
                }
                targetBarH = Math.max(minBarHPx, Math.floor(targetBarH * (boxH / scaledH) * 0.92));
            }
            if (!(natW > 0) || !(natH > 0)) return;

            /* Width-first uniform scale — never use min(w,h) scale (that made left tag narrow). */
            var scale = boxW / natW;
            var drawW = boxW;
            var drawH = Math.max(1, Math.round(natH * scale));
            if (drawH > boxH) {
                scale = boxH / natH;
                drawW = Math.max(1, Math.round(natW * scale));
                drawH = boxH;
            }

            el.setAttribute('viewBox', '0 0 ' + natW + ' ' + natH);
            el.setAttribute('preserveAspectRatio', 'xMinYMin meet');
            el.style.width = drawW + 'px';
            el.style.height = drawH + 'px';
            el.style.maxWidth = drawW + 'px';
            el.style.maxHeight = drawH + 'px';
            el.style.display = 'block';
            el.style.margin = '0 auto';
            el.style.padding = '0';
            el.style.transform = 'none';
            graphic.style.overflow = 'hidden';
            graphic.style.lineHeight = '0';
            graphic.style.display = 'flex';
            graphic.style.alignItems = 'flex-start';
            graphic.style.justifyContent = 'center';
            graphic.style.width = '100%';
            graphic.style.height = '100%';
        }
        function auragoldFitDesignWrapBarcode(el) {
            auragoldFitBoxBarcodeSvgToGraphic(el, '.barcode-print-wrap');
        }
        function auragoldRender82x38Svg(el) {
            var wrap = el.closest('.barcode-print-wrap');
            if (wrap) {
                auragoldFitDesignWrapBarcode(el);
            } else {
                auragoldFitBoxBarcodeSvgToGraphic(el, '.barcode-box-inner');
            }
        }
        function auragoldRenderBarcodeSvg(el, barWidth, barHeight, stretchToBox) {
            var barcodeValue = el.getAttribute('data-barcode');
            if (!barcodeValue) return;
            var code = String(barcodeValue).trim();
            if (!code) return;
            try {
                if (stretchToBox && el.classList.contains('barcode-svg--120x50')) {
                    auragoldFit120x50SvgToGraphic(el);
                    return;
                }
                if (stretchToBox && el.classList.contains('barcode-svg--82x38')) {
                    auragoldRender82x38Svg(el);
                    return;
                }
                JsBarcode(el, code, {
                    format: "CODE128",
                    renderer: "svg",
                    width: Math.max(2, barWidth),
                    height: barHeight,
                    displayValue: false,
                    margin: 2,
                    background: "#fff",
                    lineColor: "#000"
                });
            } catch (e) {
                el.parentElement.innerHTML = '<span style="font-size:10px;">' + (String(barcodeValue).replace(/</g, '&lt;')) + '</span>';
            }
        }
        function auragoldRenderAll120x50Barcodes() {
            document.querySelectorAll('.barcode-svg--120x50').forEach(function(el) {
                if (el.closest('.barcode-120x50-design-inner')) {
                    auragoldFitDesignWrapBarcode(el);
                } else {
                    auragoldFit120x50SvgToGraphic(el);
                }
            });
        }
        function auragoldRenderAll82x38DesignBarcodes() {
            void document.body.offsetHeight;
            var box2Els = document.querySelectorAll('.barcode-svg-box2');
            var box1Els = document.querySelectorAll('.barcode-svg-box1');
            var otherEls = document.querySelectorAll('.barcode-box-inner .barcode-svg--82x38:not(.barcode-svg-box1):not(.barcode-svg-box2)');
            box2Els.forEach(function(el) {
                auragoldFitDesignWrapBarcode(el);
            });
            otherEls.forEach(function(el) {
                auragoldFitDesignWrapBarcode(el);
            });
            box1Els.forEach(function(el) {
                auragoldFitDesignWrapBarcode(el);
            });
        }
        function auragoldRenderAll82x38Barcodes() {
            document.querySelectorAll('.barcode-svg--82x38').forEach(function(el) {
                auragoldRender82x38Svg(el);
            });
        }
        function auragoldInitBarcodePrint() {
            var printQr = <?php echo $print_code_kind === 'qr' ? 'true' : 'false'; ?>;
            if (printQr && typeof QRCode !== 'undefined') {
                auragoldFillQrPrintHosts();
                /* Second pass after mm→px layout so tiny 82×38 hosts get correct pixel size. */
                requestAnimationFrame(function() {
                    auragoldFillQrPrintHosts();
                    setTimeout(auragoldFillQrPrintHosts, 100);
                });
            }
            auragoldApply120x50ScreenTrueSize();
            if (document.body.classList.contains('barcode-print--120x50-double') && !printQr) {
                auragoldRenderAll120x50Barcodes();
                requestAnimationFrame(function() {
                    auragoldRenderAll120x50Barcodes();
                    setTimeout(auragoldRenderAll120x50Barcodes, 120);
                });
            }
            if (document.body.classList.contains('barcode-print--82x38-2box') && !printQr) {
                auragoldRenderAll82x38DesignBarcodes();
                requestAnimationFrame(function() {
                    auragoldRenderAll82x38DesignBarcodes();
                    setTimeout(auragoldRenderAll82x38DesignBarcodes, 120);
                });
            }
            document.querySelectorAll('.barcode-svg:not(.barcode-svg--120x50):not(.barcode-svg--82x38):not(.barcode-svg-box1):not(.barcode-svg-box2)').forEach(function(el) {
                if (el.closest('.barcode-box-inner')) return;
                auragoldRenderBarcodeSvg(el, auragoldPrintBarW, auragoldPrintBarH, false);
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', auragoldInitBarcodePrint);
        } else {
            auragoldInitBarcodePrint();
        }
        window.addEventListener('load', function() {
            if (document.body.classList.contains('barcode-print--82x38-2box') && <?php echo $print_code_kind === 'qr' ? 'false' : 'true'; ?>) {
                auragoldRenderAll82x38DesignBarcodes();
            }
        });
        window.addEventListener('resize', function() {
            auragoldApply120x50ScreenTrueSize();
        });
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('.full-sticker').forEach(function(el) {
                el.style.width = '';
                el.style.height = '';
                el.style.minWidth = '';
                el.style.minHeight = '';
                el.style.zoom = '1';
                var inner = el.querySelector('.full-sticker-inner');
                if (inner) {
                    inner.style.width = '';
                    inner.style.height = '';
                    inner.style.transform = '';
                    inner.style.transformOrigin = '';
                    inner.style.position = '';
                    inner.style.left = '';
                    inner.style.top = '';
                }
            });
            if (<?php echo $print_code_kind === 'qr' ? 'true' : 'false'; ?> && typeof QRCode !== 'undefined') {
                auragoldFillQrPrintHosts();
            }
            if (<?php echo ($print_code_kind === 'barcode' && $is_120x50_double_barcode) ? 'true' : 'false'; ?>) {
                setTimeout(function() {
                    auragoldRenderAll120x50Barcodes();
                }, 50);
            }
            if (<?php echo ($print_code_kind === 'barcode' && $is_82x38_2box) ? 'true' : 'false'; ?>) {
                auragoldRenderAll82x38DesignBarcodes();
                requestAnimationFrame(function() {
                    auragoldRenderAll82x38DesignBarcodes();
                });
                setTimeout(function() {
                    auragoldRenderAll82x38DesignBarcodes();
                }, 80);
            }
        });
        window.addEventListener('afterprint', function() {
            auragoldApply120x50ScreenTrueSize();
        });
    </script>
</body>
</html>
