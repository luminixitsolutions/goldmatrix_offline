<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/auragold_extra_fields_schema.php';

/** Metals from master (branch-scoped when logged into a branch), for barcode Metal Type dropdown */
$barcode_metals = getList(
    "SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 "
    . auragold_master_list_sql_suffix($conn, 'tbl_metal')
    . " ORDER BY id ASC"
);
if (!is_array($barcode_metals)) {
    $barcode_metals = [];
}

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
if (function_exists('auragold_ensure_barcode_settings_qr_columns')) {
    auragold_ensure_barcode_settings_qr_columns($conn);
}
auragold_ensure_tbl_extra_fields($conn);
$barcode_extra_fields_by_metal = auragold_get_active_extra_fields_by_metal_map($conn, $settings_branch_id);

/** Metal + label size to open after save/reload (?barcode_metal=Gold&barcode_label_size=82x38_2box). */
$bs_load_metal = isset($_GET['barcode_metal']) ? trim((string) $_GET['barcode_metal']) : '';
$bs_load_label = isset($_GET['barcode_label_size']) ? trim((string) $_GET['barcode_label_size']) : '';
if ($bs_load_metal === '' && !empty($barcode_metals)) {
    foreach ($barcode_metals as $_bm_init) {
        $_dn_init = isset($_bm_init['display_name']) ? trim((string) $_bm_init['display_name']) : '';
        if ($_dn_init !== '') {
            $bs_load_metal = $_dn_init;
            break;
        }
    }
}
$barcode_settings = ($bs_load_metal !== '')
    ? getBarcodeSettings($bs_load_metal, $bs_load_label !== '' ? $bs_load_label : null)
    : getBarcodeSettings(null, $bs_load_label !== '' ? $bs_load_label : null);
if (!$barcode_settings && $bs_load_metal !== '') {
    $barcode_settings = null;
}
$barcode_settings_cache_js = getBarcodeSettingsCacheMap();
$bs = $barcode_settings ?: [
    'label_size_preset' => '100x18',
    'label_width_mm' => 100,
    'label_height_mm' => 18,
    'font_size' => 12,
    'show_product_name' => 1,
    'show_price' => 1,
    'show_barcode_number' => 1,
    'show_product_name_barcode' => 1,
    'show_product_name_qr' => 1,
    'show_price_barcode' => 1,
    'show_price_qr' => 1,
    'show_barcode_number_barcode' => 1,
    'show_barcode_number_qr' => 1,
    'print_copies' => 1,
    'metal_type' => '',
    'is_default_print' => 0,
];
$bs_metal = $bs_load_metal !== '' ? $bs_load_metal : (isset($bs['metal_type']) ? trim((string) $bs['metal_type']) : '');
/** Presets shown in Label Size dropdown — keep in sync with ajax/save-barcode-settings.php $valid_presets */
$bs_label_preset_ui = ['100x18', '82x38_2box'];
/** When no DB row exists yet, honor ?barcode_label_size= so the dropdown matches the URL (e.g. 250x120, 120x50). */
if (!$barcode_settings && $bs_load_label !== '') {
    $bs_hint_storage = auragold_barcode_label_storage_preset($bs_load_label);
    [$bs_hint_w, $bs_hint_h] = auragold_barcode_label_mm_from_storage_preset($bs_hint_storage);
    $bs_hint_ui = auragold_barcode_label_ui_preset($bs_hint_storage, $bs_hint_w, $bs_hint_h);
    if ($bs_hint_ui === 'custom') {
        $bs_dim_hint = (int) round($bs_hint_w) . 'x' . (int) round($bs_hint_h);
        if (in_array($bs_dim_hint, $bs_label_preset_ui, true)) {
            $bs_hint_ui = $bs_dim_hint;
        }
    }
    if ($bs_hint_ui !== 'custom' && in_array($bs_hint_ui, $bs_label_preset_ui, true)) {
        $bs['label_size_preset'] = $bs_hint_storage;
        $bs['label_width_mm'] = $bs_hint_w;
        $bs['label_height_mm'] = $bs_hint_h;
    }
}
$bs_label_storage_preset = trim((string) ($bs['label_size_preset'] ?? '100x18'));
if ($bs_label_storage_preset === '') {
    $bs_label_storage_preset = '100x18';
}
$bs_label_preset_for_select = auragold_barcode_label_ui_preset(
    $bs_label_storage_preset,
    $bs['label_width_mm'] ?? 100,
    $bs['label_height_mm'] ?? 18
);
if ($bs_label_preset_for_select === 'custom') {
    $bs_dim_preset = (int) round((float) ($bs['label_width_mm'] ?? 100)) . 'x' . (int) round((float) ($bs['label_height_mm'] ?? 18));
    if (in_array($bs_dim_preset, $bs_label_preset_ui, true)) {
        $bs_label_preset_for_select = $bs_dim_preset;
    }
}
if ($bs_load_label !== '' && in_array($bs_load_label, $bs_label_preset_ui, true)) {
    $bs_label_preset_for_select = $bs_load_label;
    $bs_load_label_storage = auragold_barcode_label_storage_preset($bs_load_label);
    [$bs_url_w, $bs_url_h] = auragold_barcode_label_mm_from_storage_preset($bs_load_label_storage);
    $bs['label_size_preset'] = $bs_load_label_storage;
    $bs['label_width_mm'] = $bs_url_w;
    $bs['label_height_mm'] = $bs_url_h;
} else {
    $bs_load_label_storage = $bs_label_storage_preset;
}
if (!in_array($bs_label_preset_for_select, $bs_label_preset_ui, true)) {
    $bs_label_preset_for_select = '100x18';
    $bs_load_label_storage = '100x18';
    $bs['label_size_preset'] = '100x18';
    [$bs['label_width_mm'], $bs['label_height_mm']] = auragold_barcode_label_mm_from_storage_preset('100x18');
}
$bs_design_layout = isset($bs['design_layout']) && $bs['design_layout'] !== '' && $bs['design_layout'] !== null ? $bs['design_layout'] : '';
$bs_design_layout_decoded = $bs_design_layout ? @json_decode($bs_design_layout, true) : [];
$bs_design_layout_json_error = json_last_error();
if (!is_array($bs_design_layout_decoded)) {
    $bs_design_layout_decoded = [];
}
// Retry decode when first parse failed (e.g. escaped JSON in DB)
if ($bs_design_layout !== '' && $bs_design_layout !== null && $bs_design_layout_json_error !== JSON_ERROR_NONE) {
    $try2 = @json_decode(stripslashes($bs_design_layout), true);
    if (is_array($try2)) {
        $bs_design_layout_decoded = $try2;
    }
}
$bs_design_layout_qr = isset($bs['design_layout_qr']) && is_string($bs['design_layout_qr']) ? $bs['design_layout_qr'] : '';
$bs_default_print_code = (isset($bs['default_print_code_type']) && $bs['default_print_code_type'] === 'qr') ? 'qr' : 'barcode';
$legacy_show_pn = (int)($bs['show_product_name'] ?? 1);
$legacy_show_pr = (int)($bs['show_price'] ?? 1);
$legacy_show_bn = (int)($bs['show_barcode_number'] ?? 1);
$show_product_name_barcode = isset($bs['show_product_name_barcode']) ? (int)$bs['show_product_name_barcode'] : $legacy_show_pn;
$show_product_name_qr      = isset($bs['show_product_name_qr']) ? (int)$bs['show_product_name_qr'] : $legacy_show_pn;
$show_price_barcode        = isset($bs['show_price_barcode']) ? (int)$bs['show_price_barcode'] : $legacy_show_pr;
$show_price_qr             = isset($bs['show_price_qr']) ? (int)$bs['show_price_qr'] : $legacy_show_pr;
$show_barcode_number_barcode = isset($bs['show_barcode_number_barcode']) ? (int)$bs['show_barcode_number_barcode'] : $legacy_show_bn;
$show_barcode_number_qr      = isset($bs['show_barcode_number_qr']) ? (int)$bs['show_barcode_number_qr'] : $legacy_show_bn;
if ($bs_default_print_code === 'qr') {
    $show_product_name   = $show_product_name_qr;
    $show_price          = $show_price_qr;
    $show_barcode_number = $show_barcode_number_qr;
} else {
    $show_product_name   = $show_product_name_barcode;
    $show_price          = $show_price_barcode;
    $show_barcode_number = $show_barcode_number_barcode;
}
$bs_design_layout_qr_decoded = $bs_design_layout_qr ? @json_decode($bs_design_layout_qr, true) : [];
if (!is_array($bs_design_layout_qr_decoded)) {
    $bs_design_layout_qr_decoded = [];
}
if ($bs_design_layout_qr !== '' && $bs_design_layout_qr !== null && empty($bs_design_layout_qr_decoded)) {
    $tryQr = @json_decode(stripslashes($bs_design_layout_qr), true);
    if (is_array($tryQr)) {
        $bs_design_layout_qr_decoded = $tryQr;
    }
}
$bs['barcode_bar_width'] = isset($bs_design_layout_decoded['barcode_bar_width'])
    ? (int)$bs_design_layout_decoded['barcode_bar_width']
    : ($bs['barcode_bar_width'] ?? 2);
$bs['barcode_bar_height'] = isset($bs_design_layout_decoded['barcode_bar_height'])
    ? (int)$bs_design_layout_decoded['barcode_bar_height']
    : ($bs['barcode_bar_height'] ?? 28);
/* QR size: read from design_layout_qr first so saving barcode layout does not overwrite QR width/height in the form */
$bs['qr_width'] = isset($bs_design_layout_qr_decoded['qr_width'])
    ? (int)$bs_design_layout_qr_decoded['qr_width']
    : (isset($bs_design_layout_decoded['qr_width']) ? (int)$bs_design_layout_decoded['qr_width'] : 60);
$bs['qr_height'] = isset($bs_design_layout_qr_decoded['qr_height'])
    ? (int)$bs_design_layout_qr_decoded['qr_height']
    : (isset($bs_design_layout_decoded['qr_height']) ? (int)$bs_design_layout_decoded['qr_height'] : 60);
$pad_src = ($bs_default_print_code === 'qr' && !empty($bs_design_layout_qr_decoded))
    ? $bs_design_layout_qr_decoded
    : $bs_design_layout_decoded;
$bs['label_pad_top']    = isset($pad_src['label_pad_top']) ? max(0, min(200, (int)$pad_src['label_pad_top'])) : 0;
$bs['label_pad_right']  = isset($pad_src['label_pad_right']) ? max(0, min(200, (int)$pad_src['label_pad_right'])) : 0;
$bs['label_pad_bottom'] = isset($pad_src['label_pad_bottom']) ? max(0, min(200, (int)$pad_src['label_pad_bottom'])) : 0;
$bs['label_pad_left']   = isset($pad_src['label_pad_left']) ? max(0, min(200, (int)$pad_src['label_pad_left'])) : 0;
/* When Default print = QR, seed 82×38 box props from design_layout_qr (not barcode layout). */
$bs_82x38_layout_src = ($bs_default_print_code === 'qr' && !empty($bs_design_layout_qr_decoded))
    ? $bs_design_layout_qr_decoded
    : $bs_design_layout_decoded;
$bs_82x38_layout = auragold_82x38_2box_layout(is_array($bs_82x38_layout_src) ? $bs_82x38_layout_src : []);
$bs_sticker_print_offset_top = (float) (is_array($bs_82x38_layout_src) ? ($bs_82x38_layout_src['sticker_print_offset_top_mm'] ?? 0) : 0);
$bs_sticker_print_offset_left = (float) (is_array($bs_82x38_layout_src) ? ($bs_82x38_layout_src['sticker_print_offset_left_mm'] ?? 0) : 0);
$bs_is_82x38_preset = ($bs_label_preset_for_select === '82x38_2box');
$bs_is_dual_jewelry_preset = in_array($bs_label_preset_for_select, ['120x50', '250x120'], true);
$bs_canvas_class = 'barcode-canvas';
if ($bs_is_82x38_preset) {
    $bs_canvas_class .= ' dual-barcode-layout barcode-82x38-dual-layout';
} elseif ($bs_is_dual_jewelry_preset) {
    $bs_canvas_class .= ' dual-barcode-layout';
}
$bs_shell_class = 'barcode-dual-sticker-shell' . ($bs_is_82x38_preset ? ' barcode-82x38-outer' : '');
$bs_box_extra_class = $bs_is_82x38_preset ? ' barcode-82x38-box' : '';
$bs_label2_display = ($bs_is_82x38_preset || $bs_is_dual_jewelry_preset)
    ? ($bs_is_dual_jewelry_preset ? 'flex' : 'block')
    : 'none';
$bs_preview_layout = ($bs_default_print_code === 'qr' && !empty($bs_design_layout_qr_decoded))
    ? $bs_design_layout_qr_decoded
    : $bs_design_layout_decoded;
$render_settings_preview = [
    'label_width_mm'  => (float)($bs['label_width_mm'] ?? 100),
    'label_height_mm' => (float)($bs['label_height_mm'] ?? 18),
    'font_size'       => (int)($bs['font_size'] ?? 12),
    'design_layout'   => $bs_preview_layout,
    'render_code_as'  => ($bs_default_print_code === 'qr') ? 'qr' : 'barcode',
];
$sample_data_preview = [
    'barcode' => '00002',
    'barcode2' => '00003',
    'BarcodeNo' => '00002',
    'BarcodeNo2' => '00003',
    'ActualPurity' => '99.99%',
    'product_name' => 'Sample Product',
    'price' => '1,234.00',
];
/** Same keys as toolbox data-field — used so canvas preview matches print (not duplicate “Barcode” label text). */
$sample_field_preview = $sample_data_preview;
$sample_field_preview['Barcode'] = $sample_data_preview['BarcodeNo'] ?? $sample_data_preview['barcode'] ?? '';
$sample_field_preview['ShortCode'] = 'SC01';
$sample_field_preview['Carat'] = '22K';
$sample_field_preview['DiamondWt'] = '0.350';
$sample_field_preview['TotalDiamond'] = '0.500';
$sample_field_preview['TotalStones'] = '1.200';
foreach ($barcode_extra_fields_by_metal as $_ef_metal_fields) {
    if (!is_array($_ef_metal_fields)) {
        continue;
    }
    foreach ($_ef_metal_fields as $_ef_row) {
        $_ef_id = (int) ($_ef_row['id'] ?? 0);
        if ($_ef_id <= 0) {
            continue;
        }
        $_ef_key = auragold_barcode_extra_field_key($_ef_id);
        $sample_field_preview[$_ef_key] = trim((string) ($_ef_row['display_name'] ?? '')) !== ''
            ? ('Sample ' . trim((string) $_ef_row['display_name']))
            : 'Sample';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style page-set-software-root">
<head>
    <title>Barcode Setting - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
</head>

<style>
html, body {
    overflow-x: hidden !important;
    height: 100vh;
    background: #f4f6fb;
    /* font-family: 'Segoe UI', Arial, sans-serif; */
}

.layout-content {
    height: calc(100vh - 60px);
    overflow: hidden;
    display: flex;
}

/* Set Software: main wrapper (content + right sidebar) */
.set-software-wrapper {
    flex: 1;
    display: flex;
    min-width: 0;
    height: 100%;
    position: relative;
}

/* Left Set Software sidebar (purple) */
.set-software-sidebar {
    width: 240px;
    min-width: 240px;
    background: #11294b;
    color: #fff;
    padding: 16px 0;
    display: flex;
    flex-direction: column;
    position: relative;
    flex-shrink: 0;
    transition: width 0.25s ease, min-width 0.25s ease, opacity 0.2s ease, padding 0.25s ease;
    overflow: hidden;
}

.set-software-wrapper.set-software-sidebar-collapsed .set-software-sidebar {
    width: 0;
    min-width: 0;
    max-width: 0;
    padding-top: 16px;
    padding-left: 0;
    padding-right: 0;
    opacity: 1;
    pointer-events: none;
    overflow: visible;
    background: transparent;
    border: none;
}

.set-software-sidebar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    flex-shrink: 0;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    z-index: 2;
}

.set-software-sidebar-head .set-software-sidebar-title {
    margin-bottom: 0;
    border-bottom: none;
    flex: 1;
    min-width: 0;
}

.set-software-sidebar-title {
    padding: 12px 16px;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255,255,255,0.9);
    border-bottom: 1px solid rgba(255,255,255,0.15);
    margin-bottom: 8px;
}

.set-software-nav-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    font-size: 12px;
    transition: background 0.2s, color 0.2s;
}

.set-software-nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.set-software-nav-item.active {
    background: rgba(255,255,255,0.2);
    color: #fff;
    font-weight: 600;
}

.set-software-nav-item i {
    margin-right: 8px;
    opacity: 0.9;
}

.set-software-collapse-tab {
    position: relative;
    left: auto;
    top: auto;
    transform: none;
    z-index: 25;
    width: 28px;
    height: 40px;
    margin: 0;
    padding: 0;
    flex-shrink: 0;
    background: linear-gradient(180deg, #c5a864 0%, #a68a4a 100%);
    border: 1px solid rgba(17, 41, 75, 0.12);
    border-radius: 0 6px 6px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #11294b;
    cursor: pointer;
    font-size: 12px;
    box-shadow: 2px 0 8px rgba(166, 138, 74, 0.35);
    transition: background 0.2s ease, box-shadow 0.2s ease;
}

.set-software-collapse-tab:hover {
    background: linear-gradient(180deg, #d4b872 0%, #c5a864 100%);
}

.set-software-wrapper.set-software-sidebar-collapsed .set-software-sidebar-menu,
.set-software-wrapper.set-software-sidebar-collapsed .set-software-sidebar-head .set-software-sidebar-title {
    opacity: 0;
    visibility: hidden;
    width: 0;
    height: 0;
    overflow: hidden;
    padding: 0;
    margin: 0;
    border: none;
    pointer-events: none;
}

.set-software-wrapper.set-software-sidebar-collapsed .set-software-sidebar-head {
    border-bottom: none;
    margin-bottom: 0;
    width: 28px;
    pointer-events: auto;
}

.set-software-wrapper.set-software-sidebar-collapsed .set-software-collapse-tab {
    position: absolute;
    left: 0;
    top: 16px;
    pointer-events: auto;
}

/* Main content area (title bar + canvas) */
.set-software-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #fff;
}

/* Barcode page top bar */
.barcode-top-bar {
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.barcode-page-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.barcode-top-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.barcode-control-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.barcode-control-group label {
    font-size: 11px;
    color: #64748b;
    white-space: nowrap;
}

.barcode-control-group input,
.barcode-control-group select {
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 11px;
    min-width: 80px;
}

.barcode-check-wrap label {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    cursor: pointer;
}
.barcode-check-wrap input[type="checkbox"] {
    min-width: auto;
    margin: 0;
}

.barcode-top-actions {
    display: flex;
    gap: 10px;
}

.btn-clone-barcode {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
    font-weight: 500;
}

.btn-clone-barcode:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.btn-save-barcode {
    background: #11294b;
    border: none;
    color: #fff;
    padding: 8px 20px;
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
    font-weight: 500;
}

.btn-save-barcode:hover {
    background: #4a2b7c;
}

/* Canvas area */
.barcode-canvas-wrap {
    flex: 1;
    overflow: auto;
    padding: 24px;
    background: #fff;
}

/* Labels container for multiple labels */
.barcode-labels-container {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
}

.barcode-labels-container .barcode-preview-block {
    position: absolute;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 8px;
    cursor: move;
    pointer-events: auto;
}

.barcode-label-2 .barcode-default-inner {
    background: #e8e8e8;
}

/* 120×50 mm: compact jewelry tag — small tail, white area = full print mm size */
.barcode-default-inner.barcode-tag-jewelry {
    align-items: center;
    padding: 4px 6px;
}
.barcode-default-inner.barcode-tag-jewelry .barcode-default-left-strip {
    width: 18px;
    min-width: 18px;
    height: 12px;
    min-height: 12px;
    border-radius: 6px 0 0 6px;
    align-self: center;
    margin-right: 0;
}
.barcode-default-inner.barcode-tag-jewelry .barcode-default-handle {
    width: 10px;
    min-width: 10px;
    height: 60%;
    min-height: 28px;
    align-self: center;
    border-radius: 0 10px 10px 0;
}
/* 120×50: horizontal mid-line only (no vertical split in printable area) */
.barcode-default-inner.barcode-tag-jewelry .barcode-label-center-guide {
    left: 0;
    top: 50%;
    width: 100%;
    height: 0;
    bottom: auto;
    border-left: none;
    border-top: 1px dashed rgba(22, 163, 74, 0.55);
    transform: translateY(-50%);
}

.barcode-canvas {
    min-height: 400px;
    background-image:
        linear-gradient(rgba(0,0,0,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,0,0,0.04) 1px, transparent 1px);
    background-size: 12px 12px;
    background-color: #fafafa;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Drop zone layer - receives dropped toolbox fields; pass clicks through unless dragging. */
.barcode-canvas-drops {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    border-radius: 8px;
    z-index: 4;
    pointer-events: none;
}

.barcode-canvas-drops.drag-over {
    pointer-events: auto;
    background: rgba(90, 59, 140, 0.06);
    outline: 2px dashed #11294b;
    outline-offset: -2px;
}

/* Placed field on barcode white block: simple text only, no background highlighter */
.canvas-dropped-item {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0 2px;
    font-size: 11px;
    font-weight: normal;
    color: #1e293b;
    background: transparent !important;
    border: none !important;
    border-radius: 0;
    cursor: move;
    white-space: nowrap;
    z-index: 10;
    user-select: none;
    outline: none;
}

.canvas-dropped-item:hover {
    color: #334155;
}

.canvas-dropped-item .canvas-item-text {
    border: none;
    background: none;
}

/* No delete icon in barcode block – delete only from toolbox (right side) */
.canvas-dropped-item .canvas-item-remove {
    display: none !important;
}

.canvas-dropped-item .canvas-item-remove:hover {
    opacity: 1;
}

.canvas-dropped-item .canvas-item-remove svg {
    width: 12px;
    height: 12px;
    fill: #64748b;
}

.canvas-dropped-item .canvas-item-remove:hover svg {
    fill: #dc2626;
}

/* Barcode block: position absolute so it can be dragged; cursor move */
.barcode-preview-block {
    position: absolute;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 8px;
    min-height: 80px;
    cursor: move;
    z-index: 6;
    user-select: none;
}

/* Wrapper: single centered container (exact match to reference image) */
.barcode-default-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Outer: grey = tag backing only (not part of mm print size). Width/height set in JS from mm + strips + padding. */
.barcode-default-inner {
    display: flex;
    align-items: stretch;
    background: #e8e8e8;
    padding: 14px 18px;
    border-radius: 14px;
    border: 1px dashed #94a3b8;
    width: auto;
    min-width: 128px;
    min-height: 64px;
    box-sizing: border-box;
}
.barcode-default-inner.barcode-tag-backing {
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.35);
}

/* Left strip: tag backing only (not printable); grey like outer ends */
.barcode-default-left-strip {
    width: 16px;
    min-width: 16px;
    background: #e8e8e8;
    border-radius: 14px 0 0 14px;
    flex-shrink: 0;
    margin-right: -1px;
}

/* Middle: exact mm print face; padding 0 so #labelCanvas matches label_width_mm × label_height_mm */
.barcode-default-white {
    flex: 1;
    min-width: 0;
    background: #fff;
    border-radius: 0;
    padding: 0;
    margin-right: 0;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
}
.barcode-default-white.barcode-print-area-mm {
    flex: 0 0 auto;
    outline: 2px dashed rgba(34, 197, 94, 0.7);
    outline-offset: -1px;
}
.barcode-print-size-badge {
    position: absolute;
    left: 2px;
    bottom: 1px;
    font-size: 8px;
    font-weight: 600;
    color: rgba(22, 163, 74, 0.9);
    line-height: 1.1;
    pointer-events: none;
    z-index: 3;
    background: rgba(255, 255, 255, 0.92);
    padding: 1px 4px;
    border-radius: 2px;
}
/* Free-positioning canvas: must not exceed label height (short 18mm labels are ~54px); old min-height:80px made center guide extend below white label */
.barcode-label-canvas {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 0;
    box-sizing: border-box;
    overflow: hidden;
}
/* Allow resize handle inside canvas; clip barcode so it cannot block drops below the label */
.barcode-canvas:not(.barcode-82x38-dual-layout) .barcode-label-canvas {
    overflow: hidden;
}
.barcode-canvas:not(.barcode-82x38-dual-layout) .barcode-default-white {
    overflow: hidden;
}
.barcode-canvas:not(.barcode-82x38-dual-layout) #barcode1,
.barcode-canvas:not(.barcode-82x38-dual-layout) #barcode2 {
    z-index: 50;
    max-height: 100%;
}
.barcode-label-canvas.is-toolbox-dragging .barcode-print-wrap {
    pointer-events: none;
}
body.page-set-software.is-barcode-dragging .barcode-canvas-drops {
    pointer-events: auto;
}
/* Visual guide: clipped to canvas; does not extend past barcode label box */
.barcode-label-center-guide {
    position: absolute;
    left: 50%;
    top: 0;
    height: 100%;
    bottom: auto;
    width: 0;
    border-left: 1px dashed rgba(22, 163, 74, 0.55);
    transform: translateX(-50%);
    pointer-events: none;
    z-index: 1;
}
/* 120×50 only: two jewelry tags side-by-side inside one 120×50 mm sticker */
.barcode-dual-sticker-shell {
    display: none;
    box-sizing: border-box;
    margin: 0 auto;
}
/* Single-label sizes (100×18, etc.): flex-center preview in canvas. */
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) {
    align-items: stretch;
    justify-content: stretch;
    min-height: 360px;
}
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) .barcode-dual-sticker-shell {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1 1 auto;
    width: 100%;
    min-height: 100%;
    position: relative;
}
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) #barcodeLabelsContainer {
    position: relative;
    left: auto;
    top: auto;
    right: auto;
    bottom: auto;
    width: 100%;
    height: 100%;
    min-height: 360px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 8;
}
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) .barcode-preview-block {
    position: relative;
    left: auto;
    top: auto;
    flex: 0 0 auto;
    pointer-events: auto;
}
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) .barcode-preview-block.barcode-preview-positioned {
    position: absolute;
}
.barcode-canvas.dual-barcode-layout {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    position: relative;
    overflow: visible;
}
.barcode-dual-outer-size-badge {
    position: absolute;
    left: 50%;
    top: 6px;
    transform: translateX(-50%);
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    pointer-events: none;
    z-index: 2;
}
.dual-barcode-layout .barcode-dual-sticker-shell {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
    gap: 6px;
    box-sizing: border-box;
}
.dual-barcode-layout #barcodeLabelsContainer {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 6px;
    position: relative;
    left: auto;
    top: auto;
    right: auto;
    bottom: auto;
    width: auto;
    height: auto;
    padding: 0;
    box-sizing: border-box;
    z-index: 6;
}
.dual-barcode-layout .barcode-preview-block {
    position: relative !important;
    transform: none !important;
    margin: 0;
    min-height: 0;
    padding: 0;
    flex: 0 0 auto;
    pointer-events: auto;
}
.dual-barcode-layout:not(.barcode-82x38-dual-layout) .barcode-preview-block {
    left: auto !important;
    top: auto !important;
}
.dual-barcode-layout:not(.barcode-82x38-dual-layout) #box2 {
    display: flex !important;
}
.dual-barcode-layout .barcode-default-inner.barcode-tag-jewelry {
    padding: 4px 5px;
}
.barcode-canvas.dual-barcode-layout.barcode-82x38-dual-layout {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    width: 100%;
    min-height: 200px;
}
/* Center outer 82×38 sticker in preview canvas; inner boxes stay absolute mm inside shell. */
.barcode-canvas:not(.barcode-82x38-dual-layout) .barcode-82x38-preview-wrapper {
    display: contents;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-preview-wrapper {
    width: 100%;
    flex: 1 1 auto;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 180px;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
/* 82×38 mm — outer sticker + absolutely positioned inner boxes (mm coordinates). */
.barcode-canvas.dual-barcode-layout.barcode-82x38-dual-layout .barcode-dual-sticker-shell.barcode-82x38-outer,
.barcode-canvas.dual-barcode-layout.barcode-82x38-dual-layout .barcode-82x38-outer {
    display: block !important;
    position: relative !important;
    width: 82mm !important;
    height: 38mm !important;
    min-width: 82mm !important;
    min-height: 38mm !important;
    max-width: 82mm !important;
    border: 2px dashed rgba(34, 197, 94, 0.7);
    box-sizing: border-box;
    flex: none !important;
    flex-direction: unset !important;
    align-items: stretch !important;
    justify-content: flex-start !important;
    gap: 0 !important;
    margin: 0 auto !important;
    left: auto !important;
    top: auto !important;
    right: auto !important;
    zoom: 1 !important;
    transform: none !important;
}
/* 82×38 outer cm/mm ruler disabled — preview shows dashed border only */
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-outer-scale,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-outer-scale-top,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-outer-scale-left,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-outer-scale-tick,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-outer-scale-line,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-outer-scale-label {
    display: none !important;
    visibility: hidden !important;
}
.barcode-canvas.barcode-82x38-dual-layout #barcodeLabelsContainer {
    display: block !important;
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100% !important;
    height: 100% !important;
    flex-direction: unset !important;
    align-items: stretch !important;
    justify-content: flex-start !important;
    gap: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    transform: none !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-wrap {
    display: contents !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-preview-block,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box,
.barcode-canvas.barcode-82x38-dual-layout #box1,
.barcode-canvas.barcode-82x38-dual-layout #box2 {
    width: 20mm !important;
    height: 25mm !important;
    min-width: 20mm !important;
    min-height: 25mm !important;
    max-width: 20mm !important;
    max-height: 25mm !important;
    position: absolute !important;
    overflow: visible !important;
    padding: 0 !important;
    margin: 0 !important;
    flex: none !important;
    align-items: stretch !important;
    justify-content: flex-start !important;
    border: 2px dashed rgba(34, 197, 94, 0.7);
    box-sizing: border-box;
    transform: none !important;
    cursor: default !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-preview-block .barcode-default-inner {
    padding: 0 !important;
    margin: 0 !important;
    min-width: 0 !important;
    min-height: 0 !important;
    width: 100% !important;
    height: 100% !important;
    border: none !important;
    background: transparent !important;
    border-radius: 0 !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-wrap.barcode-82x38-barcode,
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable,
.barcode-canvas.barcode-82x38-dual-layout #barcode1,
.barcode-canvas.barcode-82x38-dual-layout #barcode2 {
    position: absolute !important;
    margin: 0 !important;
    display: block !important;
    transform: none !important;
    left: auto;
    top: auto;
    pointer-events: auto !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-label-canvas {
    pointer-events: auto !important;
    position: relative !important;
    overflow: visible !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-white {
    overflow: visible !important;
}
.barcode-canvas.barcode-82x38-dual-layout #box1,
.barcode-canvas.barcode-82x38-dual-layout #box2 {
    pointer-events: auto !important;
    cursor: default !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable {
    position: absolute !important;
    pointer-events: auto !important;
    overflow: hidden !important;
    z-index: 50 !important;
    box-sizing: border-box;
    cursor: move;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: flex-start !important;
}
.barcode-canvas.barcode-82x38-dual-layout #barcode1,
.barcode-canvas.barcode-82x38-dual-layout #barcode2 {
    z-index: 50 !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-graphic {
    flex: 0 0 auto;
    min-height: 0;
    width: 100%;
    overflow: hidden;
    display: block;
    box-sizing: border-box;
    line-height: 0;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-graphic svg,
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable .barcode-82x38-graphic svg {
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 100% !important;
    display: block !important;
    pointer-events: none !important;
    margin: 0 !important;
    padding: 0 !important;
    vertical-align: top;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable .barcode-text {
    position: static !important;
    flex-shrink: 0;
    width: 100%;
    text-align: center;
    pointer-events: none;
    box-sizing: border-box;
    line-height: 1.1;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable .resize-handle {
    display: block !important;
    position: absolute !important;
    right: -5px !important;
    bottom: -5px !important;
    width: 12px !important;
    height: 12px !important;
    background: #6b46c1 !important;
    border: 1px solid #fff;
    border-radius: 1px;
    cursor: se-resize !important;
    z-index: 99999 !important;
    pointer-events: auto !important;
    box-sizing: border-box;
    box-shadow: 0 0 0 1px rgba(107, 70, 193, 0.35);
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.barcode-selected .resize-handle {
    background: #5a3b8c !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-box-horizontal-line {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    border-top: 2px solid #16a34a;
    transform: translateY(-50%);
    pointer-events: none;
    z-index: 2;
}
/* No ruler/scale inside inner barcode boxes — outer sticker only */
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .ruler,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .ruler-tick,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .ruler-label,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .barcode-scale,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .scale-line,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .measurement-guide,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-box .barcode-82x38-outer-scale,
.barcode-canvas.barcode-82x38-dual-layout .barcode-label-canvas .barcode-82x38-outer-scale,
.barcode-canvas.barcode-82x38-dual-layout .barcode-label-canvas .barcode-box-cm-ruler {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-white.barcode-print-area-mm {
    outline: none !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-left-strip,
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-handle {
    display: none !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-inner.barcode-tag-backing {
    padding: 0 !important;
    background: transparent !important;
    box-sizing: border-box;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-default-white,
.barcode-canvas.barcode-82x38-dual-layout .barcode-label-canvas {
    width: 100% !important;
    height: 100% !important;
    min-width: 0 !important;
    min-height: 0 !important;
    flex: none !important;
}
/* Hide all measurement overlays in 82×38 designer */
.barcode-canvas.barcode-82x38-dual-layout .barcode-scale,
.barcode-canvas.barcode-82x38-dual-layout .scale-line,
.barcode-canvas.barcode-82x38-dual-layout .ruler,
.barcode-canvas.barcode-82x38-dual-layout .ruler-tick,
.barcode-canvas.barcode-82x38-dual-layout .ruler-label,
.barcode-canvas.barcode-82x38-dual-layout .measurement-line,
.barcode-canvas.barcode-82x38-dual-layout .measurement-guide,
.barcode-canvas.barcode-82x38-dual-layout .mm-scale,
.barcode-canvas.barcode-82x38-dual-layout .cm-scale,
.barcode-canvas.barcode-82x38-dual-layout .grid-scale,
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-size-badge,
.barcode-canvas.barcode-82x38-dual-layout .barcode-canvas-ruler,
.barcode-canvas.barcode-82x38-dual-layout .barcode-ruler,
.barcode-canvas.barcode-82x38-dual-layout .barcode-guide,
.barcode-canvas.barcode-82x38-dual-layout .barcode-guide-line,
.barcode-canvas.barcode-82x38-dual-layout .barcode-guide-text,
.barcode-canvas.barcode-82x38-dual-layout .barcode-box-cm-ruler,
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-sticker-guide,
.barcode-canvas.barcode-82x38-dual-layout .barcode-label-center-guide {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
}
.barcode-canvas.barcode-82x38-dual-layout::before,
.barcode-canvas.barcode-82x38-dual-layout::after {
    display: none !important;
}
/* 82×38: dedicated SVG per box (not .barcode-stripes) */
.barcode-svg-box1,
.barcode-svg-box2 {
    display: none;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-stripes {
    display: none !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-svg-box1,
.barcode-canvas.barcode-82x38-dual-layout .barcode-svg-box2 {
    display: block;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-wrap {
    display: block !important;
    visibility: visible !important;
    min-height: 0 !important;
    overflow: hidden !important;
    line-height: 0 !important;
    box-sizing: border-box !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-wrap.barcode-82x38-barcode {
    position: absolute !important;
    left: var(--saved-left-mm, 0mm) !important;
    top: var(--saved-top-mm, 0mm) !important;
    width: var(--saved-width-mm, 15mm) !important;
    height: var(--saved-height-mm, 6mm) !important;
    margin: 0 !important;
    padding: 0 !important;
    transform: none !important;
    display: block !important;
    line-height: 0 !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.barcode-82x38-barcode {
    overflow: visible !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.barcode-selected {
    outline: 2px solid #5a3b8c;
    outline-offset: 1px;
}
/* QR mode: hide placeholder SVG so QR fills the draggable wrap (not pushed below outline) */
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.qr-graphic-mode > svg,
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.qr-graphic-mode > svg.barcode-svg-box1,
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.qr-graphic-mode > svg.barcode-svg-box2 {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    max-width: 0 !important;
    max-height: 0 !important;
    overflow: hidden !important;
    position: absolute !important;
    pointer-events: none !important;
    visibility: hidden !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.qr-graphic-mode {
    overflow: hidden !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.qr-graphic-mode .qr-code-preview {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    height: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    z-index: 2;
    box-sizing: border-box;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable.qr-graphic-mode .qr-preview-canvas {
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: contain;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-wrap svg {
    width: auto !important;
    height: auto !important;
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    max-width: 100% !important;
    max-height: 100% !important;
    box-sizing: border-box !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-wrap .barcode-text {
    position: absolute !important;
    left: 0 !important;
    top: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1 !important;
    pointer-events: none;
    white-space: nowrap;
}
.barcode-canvas.barcode-82x38-dual-layout #box1 {
    z-index: 3;
}
.barcode-canvas.barcode-82x38-dual-layout #box2 {
    z-index: 4;
}
#labelPreview1,
#labelPreview2 {
    position: relative;
}
/* Barcode box: absolute; default 0,0 so block can sit flush left (saved left/top override after load) */
#barcode1,
#barcode2 {
    position: absolute;
    left: 0;
    top: 0;
    z-index: 5;
    margin: 0;
    transform: none !important;
}

/* Drop zone: pass-through by default so barcode and canvas items can be dragged; only receive drops when dragging from toolbox */
.barcode-white-drop-zone {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    border-radius: 0;
    pointer-events: none;
}
.barcode-white-drop-zone.dragging-active {
    pointer-events: auto;
    z-index: 4;
}
.barcode-white-drop-zone.drag-over,
.barcode-white-drop-zone.dragging-active {
    z-index: 4;
}
.barcode-white-drop-zone.drag-over {
    background: rgba(90, 59, 140, 0.06);
    outline: 2px dashed #a78bfa;
    outline-offset: -2px;
}

/* Right handle: tag backing only (not printable) */
.barcode-default-handle {
    width: 32px;
    min-width: 32px;
    background: #e8e8e8;
    border-radius: 0 14px 14px 0;
    flex-shrink: 0;
    margin-left: -1px;
    box-shadow: none;
}

/* Barcode print: absolute for drag; block stack — stripes clip to layout box, text below */
.barcode-print-wrap {
    position: absolute;
    left: 0;
    top: 0;
    margin: 0;
    padding: 0;
    cursor: move;
    min-height: 0;
    height: auto;
    display: block;
    box-sizing: border-box;
    overflow: hidden;
    z-index: 5;
}
.barcode-canvas:not(.barcode-82x38-dual-layout) .barcode-print-wrap {
    max-height: 100%;
}
.barcode-print-wrap.qr-graphic-mode .barcode-stripes,
.barcode-print-wrap.qr-graphic-mode > svg.barcode-svg,
.barcode-print-wrap.qr-graphic-mode > svg.barcode-svg-box1,
.barcode-print-wrap.qr-graphic-mode > svg.barcode-svg-box2 {
    display: none !important;
    visibility: hidden !important;
    width: 0 !important;
    height: 0 !important;
    max-height: 0 !important;
    overflow: hidden !important;
    pointer-events: none !important;
}
/* Standard labels: wrap hugs the QR — avoid leftover barcode width leaving empty gap */
.barcode-canvas:not(.barcode-82x38-dual-layout) .barcode-print-wrap.qr-graphic-mode {
    width: fit-content;
    max-width: 100%;
    min-width: 0;
}
.barcode-canvas:not(.barcode-82x38-dual-layout) .barcode-print-wrap.qr-graphic-mode .qr-code-preview {
    display: flex !important;
    flex-shrink: 0;
}
.barcode-print-wrap:not(.qr-graphic-mode) .qr-code-preview {
    display: none !important;
    visibility: hidden !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    pointer-events: none !important;
}
.barcode-print-wrap .barcode-stripes {
    display: block;
    box-sizing: border-box;
    width: auto;
    max-width: 100%;
    height: auto;
    overflow: hidden;
}
.barcode-print-wrap.barcode-selected {
    outline: none;
    box-shadow: none;
}
.barcode-print-wrap.barcode-selected .barcode-stripes,
.barcode-print-wrap.barcode-selected .qr-code-preview {
    outline: 2px solid #5a3b8c;
    outline-offset: 1px;
}
.barcode-resize-handle {
    display: none !important;
    position: absolute !important;
    right: -5px !important;
    bottom: -5px;
    width: 12px !important;
    height: 12px !important;
    cursor: se-resize !important;
    background: #6b46c1 !important;
    border: 1px solid #fff;
    border-radius: 2px;
    z-index: 99999 !important;
    pointer-events: auto !important;
    box-sizing: border-box;
    box-shadow: 0 0 0 1px rgba(107, 70, 193, 0.35);
}
.barcode-print-wrap.barcode-selected .barcode-resize-handle {
    display: block !important;
}
.barcode-resize-handle:hover {
    background: #5a3b8c !important;
}
.barcode-resize-handle.barcode-resize-handle--82x38 {
    cursor: se-resize !important;
}
.barcode-text {
    margin-top: 2px;
    text-align: left;
    font-size: 9px;
    line-height: 1;
    color: #000;
    width: auto;
    max-width: 100%;
    align-self: flex-start;
    box-sizing: border-box;
}
.barcode-default-white .barcode-stripes {
    display: block;
    min-width: 24px;
    width: auto;
    min-height: 10px;
    margin: 0;
    background: #fff;
    box-sizing: border-box;
    overflow: hidden;
}
/* Fallback: show black lines when barcode area is empty (before JsBarcode runs or if it fails) */
.barcode-default-white .barcode-stripes:empty {
    background: repeating-linear-gradient(90deg, #000 0px, #000 2px, transparent 2px, transparent 4px);
    min-height: 28px;
}
/* JsBarcode SVG: height from container; width auto unless scaled via resize handle */
.barcode-default-white .barcode-stripes svg,
.barcode-default-white .barcode-stripes img {
    height: 100% !important;
    min-height: 0;
    max-height: 100%;
    display: block;
    vertical-align: middle;
}
.barcode-default-white .barcode-stripes:not(.is-scaled-x) svg {
    width: auto !important;
    max-width: 100%;
    object-fit: contain;
    object-position: left center;
}
.barcode-default-white .barcode-stripes.is-scaled-x svg {
    max-width: none;
}

.barcode-preview-block .barcode-label {
    font-size: 12px;
    font-weight: 500;
    color: #1e293b;
    margin-top: 8px;
    text-align: center;
}

/* 100mm x 18mm (and other short labels): flat wide shape, reduced padding, small barcode */
.barcode-default-inner.barcode-label-short {
    padding: 6px 10px;
}
.barcode-default-inner.barcode-label-short .barcode-default-white {
    padding: 0;
}
.barcode-default-inner.barcode-label-short .barcode-print-wrap {
    margin-bottom: 0;
}
.barcode-default-inner.barcode-label-short .barcode-stripes {
    min-height: 8px;
    min-width: 24px;
    width: auto;
}
.barcode-default-inner.barcode-label-short .barcode-default-handle {
    width: 20px;
    min-width: 20px;
}
.barcode-print-preview-label .barcode-svg-wrap { margin: 0; padding: 0; z-index: 0; display: flex; align-items: center; }
.barcode-print-preview-label .barcode-svg-wrap svg { width: 100%; height: 100%; display: block; }
.barcode-print-preview-label .design-field { margin: 0; padding: 0; z-index: 1; line-height: 1.2; color: #1e293b; }
.barcode-default-inner.barcode-label-short .barcode-default-left-strip {
    width: 10px;
    min-width: 10px;
    border-radius: 10px 0 0 10px;
}

/* Right sidebar: Toolbox + Properties */
.barcode-right-sidebar {
    width: 360px;
    min-width: 360px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.barcode-panel {
    border-bottom: 1px solid #e2e8f0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.barcode-panel:last-child {
    border-bottom: none;
}

.barcode-panel-title {
    padding: 12px 16px;
    font-weight: 600;
    font-size: 12px;
    color: #1e293b;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

/* Toolbox metal tabs */
.toolbox-tabs {
    display: flex;
    flex-wrap: wrap;
    padding: 4px 8px;
    gap: 4px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.toolbox-tab {
    padding: 6px 10px;
    font-size: 11px;
    color: #64748b;
    background: transparent;
    border: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    white-space: nowrap;
}

.toolbox-tab:hover {
    color: #11294b;
}

.toolbox-tab.active {
    color: #11294b;
    font-weight: 600;
    border-bottom-color: #11294b;
}

/* QR Code preview styling */
.qr-code-preview {
    width: 60px;
    height: 60px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-code-preview svg {
    width: 50px;
    height: 50px;
}

.barcode-inner-draggable .qr-code-preview {
    box-sizing: border-box;
    overflow: hidden;
    margin: 0;
}

.barcode-inner-draggable .qr-preview-canvas {
    display: block;
    max-width: 100%;
    max-height: 100%;
}

.barcode-qr-toggle {
    padding: 6px 16px 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: #ffffff;
}

.barcode-qr-toggle .toggle-option {
    padding: 4px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.barcode-qr-toggle .toggle-option.active {
    background: #11294b;
    color: #fff;
}

.barcode-qr-toggle .toggle-option:not(.active) {
    background: #f1f5f9;
    color: #64748b;
}

.toolbox-fields {
    padding: 6px 12px 6px 12px;
    overflow-y: auto;
    max-height: 320px;
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    gap: 8px 10px;
}

.toolbox-field-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #11294b;
    background: #ede9fe;
    border: 1px solid #c4b5fd;
    border-radius: 6px;
    cursor: grab;
    white-space: nowrap;
    flex-shrink: 0;
}

.toolbox-field-item.dragging {
    opacity: 0.6;
}

.toolbox-field-item:hover {
    background: #ddd6fe;
    border-color: #a78bfa;
    color: #4c1d95;
}

.toolbox-field-extra {
    background: #ede9fe;
    border-color: #c4b5fd;
}
.toolbox-field-extra:hover {
    background: #ddd6fe;
    border-color: #a78bfa;
}
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

/* Highlight toolbox columns that are already on the label (dropped) */
.toolbox-field-item.on-label {
    background: #c4b5fd;
    border-color: #8b5cf6;
    color: #4c1d95;
}
.toolbox-field-item.on-label:hover {
    background: #a78bfa;
    border-color: #7c3aed;
}

.toolbox-field-item .field-trash {
    display: none;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    padding: 2px;
    cursor: pointer;
    opacity: 0.6;
}

.toolbox-field-item.on-label .field-trash {
    display: inline-flex;
}

.toolbox-field-item .field-trash:hover {
    opacity: 1;
}

.toolbox-field-item .field-trash svg {
    width: 14px;
    height: 14px;
    fill: currentColor;
}

.toolbox-field-item .field-trash:hover svg {
    fill: #dc2626;
}

.toolbox-field-image {
    border-left: 3px solid #11294b;
    padding-left: 10px;
}
.toolbox-field-strip {
    border-left-color: #0f766e;
}
.toolbox-field-white-strip {
    border-left-color: #94a3b8;
    background: linear-gradient(90deg, #fff 0%, #f8fafc 100%);
}
/* White Strip — blank rectangle. Must override .canvas-dropped-item { background: transparent !important } */
.canvas-dropped-item.canvas-white-strip,
.barcode-label-canvas .canvas-white-strip,
.canvas-white-strip {
    position: absolute !important;
    display: block !important;
    padding: 0 !important;
    margin: 0 !important;
    box-sizing: border-box !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
    border: none !important;
    border-radius: 0;
    white-space: normal !important;
    overflow: visible !important;
    min-width: 10px !important;
    min-height: 5px !important;
    cursor: move !important;
    pointer-events: auto !important;
    user-select: none;
    z-index: 5;
    color: transparent !important;
    font-size: 0 !important;
    line-height: 0 !important;
    gap: 0 !important;
}
/* Designer-only helper outline (not printed) */
body.page-set-software .barcode-designer-mode .canvas-white-strip,
body.page-set-software .canvas-white-strip {
    outline: 1px dashed #64748b !important;
    outline-offset: 0;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.15) !important;
}
body.page-set-software .canvas-white-strip.selected {
    outline: 2px solid #7c3aed !important;
    outline-offset: 1px;
    box-shadow: 0 0 0 1px rgba(124, 58, 237, 0.35) !important;
    z-index: 25 !important;
}
.canvas-white-strip .canvas-item-text {
    display: none !important;
}
.canvas-white-strip .canvas-item-remove {
    display: inline-flex !important;
    position: absolute;
    right: -8px;
    top: -8px;
    width: 18px;
    height: 18px;
    align-items: center;
    justify-content: center;
    background: #fff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 3px;
    padding: 1px;
    z-index: 30;
    cursor: pointer;
    pointer-events: auto;
}
.canvas-white-strip .white-strip-resize-handle {
    display: none;
    position: absolute;
    width: 8px;
    height: 8px;
    background: #7c3aed;
    border: 1px solid #fff;
    border-radius: 1px;
    z-index: 30;
    box-sizing: border-box;
    pointer-events: auto;
}
.canvas-white-strip.selected .white-strip-resize-handle {
    display: block;
}
.canvas-white-strip .ws-handle-nw { left: -4px; top: -4px; cursor: nwse-resize; }
.canvas-white-strip .ws-handle-n  { left: 50%; top: -4px; margin-left: -4px; cursor: ns-resize; }
.canvas-white-strip .ws-handle-ne { right: -4px; top: -4px; cursor: nesw-resize; }
.canvas-white-strip .ws-handle-e  { right: -4px; top: 50%; margin-top: -4px; cursor: ew-resize; }
.canvas-white-strip .ws-handle-se { right: -4px; bottom: -4px; cursor: nwse-resize; }
.canvas-white-strip .ws-handle-s  { left: 50%; bottom: -4px; margin-left: -4px; cursor: ns-resize; }
.canvas-white-strip .ws-handle-sw { left: -4px; bottom: -4px; cursor: nesw-resize; }
.canvas-white-strip .ws-handle-w  { left: -4px; top: 50%; margin-top: -4px; cursor: ew-resize; }
.barcode-label-canvas {
    position: relative;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-label-canvas {
    position: relative !important;
}
.prop-row-white-strip {
    display: none;
}
.prop-row-white-strip.is-visible {
    display: block;
}
.prop-row-white-strip.prop-row-cols.is-visible {
    display: grid;
}
.prop-white-strip-layer-btns {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.prop-white-strip-layer-btns button {
    flex: 1;
    min-width: 100px;
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
    color: #334155;
    font-size: 11px;
    cursor: pointer;
}
.prop-white-strip-layer-btns button:hover {
    border-color: #7c3aed;
    color: #7c3aed;
}
.barcode-ws-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: #11294b;
    color: #fff;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    z-index: 99999;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.25);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
.barcode-ws-toast.is-visible {
    opacity: 1;
}
@media print {
    .canvas-white-strip {
        box-shadow: none !important;
        outline: none !important;
    }
    .white-strip-resize-handle,
    .canvas-white-strip .canvas-item-remove {
        display: none !important;
    }
}
/* Fixed half white strips — 82×38 only (thin bands matching red-mark positions) */
.barcode-82x38-fixed-strips {
    display: none;
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 70;
    overflow: visible;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-fixed-strips {
    display: block !important;
}
.barcode-82x38-fixed-half-strip {
    position: absolute !important;
    display: block !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 1px dashed #64748b !important;
    border-radius: 0 !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.15);
    pointer-events: auto !important;
    z-index: 70 !important;
    line-height: normal !important;
    overflow: visible !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
body.page-set-software.is-barcode-dragging .barcode-82x38-fixed-half-strip {
    pointer-events: auto !important;
    z-index: 80 !important;
}
.barcode-82x38-fixed-half-strip.drag-over {
    border-color: #0d9488 !important;
    box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.35);
}
.barcode-82x38-fixed-half-strip .canvas-dropped-item {
    pointer-events: auto !important;
    z-index: 2;
    max-width: 100%;
    white-space: nowrap;
    align-items: flex-start !important;
    padding: 0 !important;
    margin: 0 !important;
    line-height: 1 !important;
}
.barcode-82x38-fixed-half-strip .canvas-dropped-item .canvas-item-text {
    line-height: 1 !important;
    margin: 0 !important;
    padding: 0 !important;
    display: block;
}
/*
 * Half strips (white bg) — 82×38 only:
 *  1) top-left: left edge → box2, flush with top
 *  2) bottom-right: right of box1 → right edge (top 30.6mm)
 */
.barcode-82x38-fixed-half-strip--top-left {
    left: 0 !important;
    top: 5mm !important;
    width: 62mm !important;
    height: 4.5mm !important;
}
.barcode-82x38-fixed-half-strip--bottom-right {
    left: 20mm !important;
    top: 30.6mm !important;
    width: 62mm !important;
    height: 4.5mm !important;
}
@media print {
    .barcode-82x38-fixed-half-strip {
        border: none !important;
        box-shadow: none !important;
        background: #ffffff !important;
        background-color: #ffffff !important;
    }
}
/* Layer for strip lines on the outer 82×38 sticker (not inside the green boxes) */
.barcode-82x38-strip-layer {
    display: none;
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 60;
    overflow: visible;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-strip-layer {
    display: block;
    pointer-events: none;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-82x38-strip-layer .canvas-strip-line {
    pointer-events: auto;
}
.barcode-canvas.barcode-82x38-dual-layout #barcodeLabelsContainer {
    z-index: 5;
    pointer-events: none !important;
}
/* Draggable horizontal strip line on the label canvas / outer sticker */
.canvas-dropped-item.canvas-strip-line {
    display: block;
    padding: 0;
    height: 12px;
    min-height: 12px;
    line-height: 0;
    white-space: normal;
    background: transparent !important;
    border: none !important;
    z-index: 45;
    box-sizing: border-box;
}
.canvas-dropped-item.canvas-strip-line .canvas-strip-line-bar {
    display: block;
    width: 100%;
    height: 0;
    margin-top: 5px;
    border-top: 2px solid #0f172a;
    pointer-events: none;
}
.canvas-dropped-item.canvas-strip-line.selected .canvas-strip-line-bar,
.canvas-dropped-item.canvas-strip-line:hover .canvas-strip-line-bar {
    border-top-color: #0f766e;
    box-shadow: 0 0 0 1px rgba(15, 118, 110, 0.35);
}
.canvas-dropped-item.canvas-strip-line .canvas-item-text {
    display: none !important;
}
.canvas-dropped-item.canvas-strip-line .canvas-item-remove {
    display: inline-flex !important;
    position: absolute;
    right: -2px;
    top: -2px;
    background: #fff;
    border-radius: 3px;
    padding: 1px;
    z-index: 2;
}

.toolbox-search-wrap {
    padding: 8px 16px 6px;
}
.toolbox-column-search {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    outline: none;
}
.toolbox-column-search:focus {
    border-color: #11294b;
    box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.15);
}
.toolbox-column-search::placeholder {
    color: #94a3b8;
}
.toolbox-field-item.toolbox-search-hidden {
    display: none !important;
}
/* Matched chips must stay visible even if another rule set display:none */
.toolbox-fields.is-toolbox-searching .toolbox-field-item:not(.toolbox-search-hidden) {
    display: inline-flex !important;
}
.toolbox-search-empty {
    display: none;
    width: 100%;
    flex-basis: 100%;
    padding: 10px 4px 6px;
    font-size: 12px;
    color: #94a3b8;
}
.toolbox-fields.is-toolbox-searching.has-no-search-matches .toolbox-search-empty {
    display: block;
}
.toolbox-fields.is-toolbox-searching.has-no-search-matches .toolbox-fields-divider {
    display: none;
}

.toolbox-fields-divider {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 6px 0 4px;
    margin: 4px 0 0;
    border-top: 1px solid #e2e8f0;
    width: 100%;
    flex-basis: 100%;
}

/* Properties */
.properties-body {
    padding: 16px;
    overflow-y: auto;
    max-height: 320px;
}

.prop-row {
    margin-bottom: 14px;
}
.prop-row.prop-row-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    align-items: end;
}
.prop-row.prop-row-cols .prop-field { margin-bottom: 0; }

.prop-row label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 4px;
    font-weight: 500;
}

.prop-row input,
.prop-row select {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 11px;
    box-sizing: border-box;
}

.prop-row input[type="number"] {
    width: 100%;
    min-width: 0;
}
.prop-row.prop-row-cols input[type="number"] {
    width: 100%;
}
.prop-row .prop-field { width: 100%; }

.prop-hint { font-size: 11px; color: #94a3b8; display: block; margin-top: 4px; }
.move-buttons, .barcode-size-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-move, .btn-size {
    width: 36px; height: 36px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    font-size: 18px;
    font-weight: 600;
    line-height: 1;
    color: #334155;
    display: flex; align-items: center; justify-content: center;
}
.btn-move:hover, .btn-size:hover { background: #f1f5f9; border-color: #cbd5e1; color: #0f172a; }

/* —— Mobile: stacked layout, single scroll, compact chrome —— */
/* Desktop: canvas center-left, toolbox fixed on the right */
@media (min-width: 992px) {
    body.page-set-software .set-software-wrapper {
        flex-direction: row;
        align-items: stretch;
    }
    body.page-set-software .set-software-main {
        flex: 1 1 auto;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }
    body.page-set-software .barcode-canvas-wrap {
        flex: 1 1 auto;
        min-height: 0;
        overflow: auto;
    }
    body.page-set-software .barcode-right-sidebar {
        flex: 0 0 360px;
        width: 360px;
        min-width: 360px;
        max-height: 100%;
        overflow-y: auto;
        border-left: 1px solid #e2e8f0;
        border-top: none;
    }
}

@media (max-width: 991.98px) {
    html.page-set-software-root,
    body.page-set-software {
        height: auto;
        min-height: 100dvh;
        overflow-x: hidden;
        overflow-y: auto;
    }

    body.page-set-software .layout-content {
        height: auto;
        min-height: calc(100dvh - 56px);
        overflow-x: hidden;
        overflow-y: visible;
        display: block;
    }

    /* Hide identity strip (title, FY, DB) — keep menu / logo / avatar */
    body.page-set-software .company-header .auragold-header-identity__title,
    body.page-set-software .company-header .auragold-header-identity__pill,
    body.page-set-software .company-header .user-info .auragold-header-db-name {
        display: none !important;
    }
    body.page-set-software .company-header {
        row-gap: 0.35rem;
        padding-bottom: 10px;
    }

    body.page-set-software .set-software-wrapper {
        flex-direction: column;
        height: auto;
        min-height: 0;
    }

    body.page-set-software .set-software-main {
        flex: none;
        width: 100%;
        min-width: 0;
        overflow: visible;
    }

    body.page-set-software .auragold-settings-branch-banner {
        margin-bottom: 10px !important;
        padding: 8px 12px !important;
        font-size: 12px !important;
    }
    body.page-set-software .auragold-settings-branch-banner select {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100%;
    }

    body.page-set-software .barcode-top-bar {
        flex-direction: column;
        align-items: stretch;
        padding: 10px 12px;
        gap: 10px;
    }
    body.page-set-software .barcode-page-title {
        font-size: 1rem;
    }
    body.page-set-software .barcode-top-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 10px;
        width: 100%;
    }
    body.page-set-software .barcode-control-group {
        flex-direction: column;
        align-items: stretch;
        gap: 4px;
        min-width: 0;
    }
    body.page-set-software .barcode-control-group label {
        font-size: 10px;
    }
    body.page-set-software .barcode-control-group input,
    body.page-set-software .barcode-control-group select {
        width: 100%;
        min-width: 0;
    }
    body.page-set-software .barcode-check-wrap label {
        font-size: 11px;
    }
    body.page-set-software .barcode-top-actions {
        width: 100%;
        flex-wrap: wrap;
        gap: 8px;
    }
    body.page-set-software .barcode-top-actions .btn-clone-barcode,
    body.page-set-software .barcode-top-actions .btn-save-barcode {
        flex: 1 1 auto;
        min-width: 120px;
        text-align: center;
        justify-content: center;
    }
    body.page-set-software .barcode-top-actions .barcode-control-group {
        flex: 1 1 100%;
    }

    body.page-set-software .barcode-canvas-wrap {
        flex: none;
        padding: 12px;
        min-height: 220px;
        max-height: min(42vh, 360px);
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }
    body.page-set-software .barcode-canvas {
        min-height: 180px;
    }

    body.page-set-software .barcode-right-sidebar {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        border-left: none;
        border-top: 1px solid #e2e8f0;
        flex-shrink: 0;
        overflow: visible;
    }

    body.page-set-software .barcode-panel {
        overflow: visible;
    }

    /* Metal tabs: one horizontal row, swipe to see all */
    body.page-set-software .toolbox-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding: 6px 10px;
        gap: 2px;
    }
    body.page-set-software .toolbox-tabs::-webkit-scrollbar {
        display: none;
    }
    body.page-set-software .toolbox-tab {
        flex: 0 0 auto;
        padding: 8px 12px;
        font-size: 12px;
    }

    body.page-set-software .barcode-qr-toggle {
        color: #64748b;
        padding: 8px 12px;
    }

    body.page-set-software .toolbox-fields {
        max-height: none;
        overflow-y: visible;
        padding: 8px 12px 12px;
    }

    body.page-set-software .properties-body {
        max-height: none;
        overflow-y: visible;
        padding: 12px 14px 16px;
    }

    body.page-set-software .prop-row.prop-row-cols {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    body.page-set-software .toolbox-field-item {
        font-size: 11px;
        padding: 8px 10px;
    }

    body.page-set-software .barcode-panel-title {
        padding: 10px 14px;
        font-size: 13px;
    }
}

@media (max-width: 575.98px) {
    body.page-set-software .barcode-top-controls {
        grid-template-columns: 1fr;
    }
    body.page-set-software .barcode-top-actions .btn-clone-barcode,
    body.page-set-software .barcode-top-actions .btn-save-barcode {
        width: 100%;
        min-width: 0;
    }
}
</style>

<body class="page-set-software">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
    <div class="set-software-wrapper">
        <?php include 'set-software-sidebar.php'; ?>

        <!-- Main content -->
        <div class="set-software-main">
            <?php include __DIR__ . '/includes/set-software-branches-tabs.php'; ?>
            <input type="hidden" name="settings_branch_id" id="settingsBranchId" value="<?php echo (int) $settings_branch_id; ?>">
            <div class="barcode-top-bar">
                <h1 class="barcode-page-title">Barcode Setting</h1>
                <div class="barcode-top-controls">
                    <div class="barcode-control-group">
                        <label>No.of Copies</label>
                        <input type="number" value="<?php echo (int)($bs['print_copies'] ?? 1); ?>" min="1" max="100" id="barcodeCopies">
                    </div>
                    <div class="barcode-control-group">
                        <label>Label Size</label>
                        <select id="barcodeLabelSize" data-saved-preset="<?php echo htmlspecialchars($bs_label_preset_for_select, ENT_QUOTES, 'UTF-8'); ?>">
                            <option value="100x18" <?php echo $bs_label_preset_for_select === '100x18' ? 'selected' : ''; ?>>100mm x 18mm</option>
                            <option value="82x38_2box" <?php echo $bs_label_preset_for_select === '82x38_2box' ? 'selected' : ''; ?>>82mm x 38mm - 2 Box</option>
                        </select>
                    </div>
                    <div class="barcode-control-group barcode-check-wrap">
                        <label title="Use this label size as the default when printing barcodes for the selected metal type"><input type="checkbox" id="barcodeIsDefaultPrint" name="is_default_print" value="1" <?php echo ((int)($bs['is_default_print'] ?? 0) === 1) ? 'checked' : ''; ?>> Print</label>
                    </div>
                    <div class="barcode-control-group barcode-custom-size-wrap" id="barcodeCustomSizeWrap" style="display: none;">
                        <label>Width (mm)</label>
                        <input type="number" id="barcodeCustomWidthMm" value="<?php echo (float)($bs['label_width_mm'] ?? 100); ?>" min="10" max="500" step="1" style="width: 70px;">
                    </div>
                    <div class="barcode-control-group barcode-custom-size-wrap" id="barcodeCustomHeightWrap" style="display: none;">
                        <label>Height (mm)</label>
                        <input type="number" id="barcodeCustomHeightMm" value="<?php echo (float)($bs['label_height_mm'] ?? 18); ?>" min="10" max="300" step="1" style="width: 70px;">
                    </div>
                    <div class="barcode-control-group">
                        <label>Font Size</label>
                        <input type="number" id="barcodeFontSize" value="<?php echo (int)($bs['font_size'] ?? 12); ?>" min="6" max="72" style="width: 60px;">
                    </div>
                    <div class="barcode-control-group barcode-check-wrap">
                        <label><input type="checkbox" id="barcodeShowProductName" <?php echo ((int)$show_product_name === 1) ? 'checked' : ''; ?>> Product name</label>
                    </div>
                    <div class="barcode-control-group barcode-check-wrap">
                        <label><input type="checkbox" id="barcodeShowPrice" <?php echo ((int)$show_price === 1) ? 'checked' : ''; ?>> Price</label>
                    </div>
                    <div class="barcode-control-group barcode-check-wrap">
                        <label><input type="checkbox" id="barcodeShowBarcodeNo" <?php echo ((int)$show_barcode_number === 1) ? 'checked' : ''; ?>> Barcode no.</label>
                    </div>
                    <div class="barcode-control-group">
                        <label>Metal Type</label>
                        <select id="barcodeMetalType">
                            <option value="" <?php echo $bs_metal === '' ? 'selected' : ''; ?>>Select</option>
                            <?php foreach ($barcode_metals as $_bm) {
                                $_dn = isset($_bm['display_name']) ? trim((string) $_bm['display_name']) : '';
                                if ($_dn === '') {
                                    continue;
                                }
                                $_sn = isset($_bm['system_name']) ? trim((string) $_bm['system_name']) : '';
                                $_sel = ($bs_metal !== '' && ($bs_metal === $_dn || ($_sn !== '' && strcasecmp($bs_metal, $_sn) === 0))) ? ' selected' : '';
                                ?>
                            <option value="<?php echo htmlspecialchars($_dn, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $_sel; ?>><?php echo htmlspecialchars($_dn, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="barcode-control-group">
                        <label>User</label>
                        <select id="barcodeUser">
                            <option>All</option>
                        </select>
                    </div>
                </div>
                <div class="barcode-top-actions">
                    <button type="button" class="btn-clone-barcode">Clone Barcode Setting</button>
                    <div class="barcode-control-group">
                        <label>Default print</label>
                        <select id="defaultPrintCodeType" title="Used when product print URL does not include &amp;code=">
                            <option value="barcode" <?php echo $bs_default_print_code === 'barcode' ? 'selected' : ''; ?>>Barcode</option>
                            <option value="qr" <?php echo $bs_default_print_code === 'qr' ? 'selected' : ''; ?>>QR</option>
                        </select>
                    </div>
                    <button type="button" class="btn-save-barcode" id="btnSaveBarcodeSettings">Save</button>
                </div>
            </div>

            <div class="barcode-canvas-wrap">
                <div class="<?php echo htmlspecialchars($bs_canvas_class, ENT_QUOTES, 'UTF-8'); ?> barcode-designer-mode" id="barcodeCanvas">
                    <div class="barcode-canvas-drops" id="barcodeCanvasDrops"></div>
                    <div class="barcode-82x38-preview-wrapper" id="barcode82x38PreviewWrapper"<?php echo $bs_is_82x38_preset ? '' : ' aria-hidden="true"'; ?>>
                    <div class="<?php echo htmlspecialchars($bs_shell_class, ENT_QUOTES, 'UTF-8'); ?>" id="barcodeDualStickerShell" aria-label="<?php echo $bs_is_82x38_preset ? '82mm x 38mm dual box sticker' : '120mm x 50mm dual barcode sticker'; ?>">
                    <div class="barcode-82x38-sticker-guide" id="barcode82x38StickerGuide" aria-hidden="true" title="Horizontal separation line"></div>
                    <?php if ($bs_is_82x38_preset) { ?>
                    <div class="barcode-82x38-fixed-strips" id="barcode82x38FixedStrips" aria-hidden="false" title="Fixed half white strips — drop columns here">
                        <div class="barcode-82x38-fixed-half-strip barcode-82x38-fixed-half-strip--top-left" data-fixed-half-strip="top-left" title="Half white strip (top-left) — drop columns here"></div>
                        <div class="barcode-82x38-fixed-half-strip barcode-82x38-fixed-half-strip--bottom-right" data-fixed-half-strip="bottom-right" title="Half white strip (bottom) — drop columns here"></div>
                    </div>
                    <?php } ?>
                    <div class="barcode-labels-container" id="barcodeLabelsContainer">
                        <div class="barcode-preview-block<?php echo htmlspecialchars($bs_box_extra_class, ENT_QUOTES, 'UTF-8'); ?>" id="box1" data-legacy-id="barcodeLabel1">
                            <div class="barcode-default-wrap barcode-82x38-box-inner">
                                <div class="barcode-default-inner">
                                    <div class="barcode-default-left-strip" title="Left strip"></div>
                                    <div class="barcode-default-white" id="labelPreview1">
                                        <div id="labelCanvas1" class="barcode-label-canvas">
                                            <div class="barcode-label-center-guide" aria-hidden="true" title="Horizontal mid-line (barcode above, number below)"></div>
                                            <?php if ($bs_is_82x38_preset) { ?>
                                            <div class="barcode-inner-draggable barcode-82x38-barcode" id="barcode1" data-barcode-index="1">
                                                <div class="barcode-82x38-graphic">
                                                    <svg class="barcode-svg barcode-svg-box1" id="barcodeSvgBox1" data-barcode="00002" aria-hidden="true"></svg>
                                                </div>
                                                <div class="barcode-text" id="barcodeText1"><?php echo htmlspecialchars(trim((string)($sample_data_preview['BarcodeNo'] ?? $sample_data_preview['barcode'] ?? '00002')) ?: '00002', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <span class="resize-handle" aria-label="Resize barcode"></span>
                                            </div>
                                            <?php } else { ?>
                                            <div class="barcode-print-wrap" id="barcode1">
                                                <span class="barcode-stripes"></span>
                                                <svg class="barcode-svg barcode-svg-box1" id="barcodeSvgBox1" data-barcode="00002" aria-hidden="true"></svg>
                                                <div class="barcode-text" id="barcodeText1"><?php echo htmlspecialchars(trim((string)($sample_data_preview['BarcodeNo'] ?? $sample_data_preview['barcode'] ?? '00002')) ?: '00002', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <?php } ?>
                                            <div class="barcode-white-drop-zone" id="barcodeWhiteDropZone" title="Drop columns here"></div>
                                        </div>
                                    </div>
                                    <div class="barcode-default-handle" title="Handle"></div>
                                </div>
                            </div>
                        </div>
                        <div class="barcode-preview-block barcode-label-2<?php echo htmlspecialchars($bs_box_extra_class, ENT_QUOTES, 'UTF-8'); ?>" id="box2" data-legacy-id="barcodeLabel2" style="display: <?php echo htmlspecialchars($bs_label2_display, ENT_QUOTES, 'UTF-8'); ?>;">
                            <div class="barcode-default-wrap barcode-82x38-box-inner">
                                <div class="barcode-default-inner">
                                    <div class="barcode-default-left-strip" title="Left strip"></div>
                                    <div class="barcode-default-white" id="labelPreview2">
                                        <div id="labelCanvas2" class="barcode-label-canvas">
                                            <div class="barcode-label-center-guide" aria-hidden="true" title="Center of label (half width)"></div>
                                            <?php if ($bs_is_82x38_preset) { ?>
                                            <div class="barcode-inner-draggable barcode-82x38-barcode" id="barcode2" data-barcode-index="2">
                                                <div class="barcode-82x38-graphic">
                                                    <svg class="barcode-svg barcode-svg-box2" id="barcodeSvgBox2" data-barcode="00003" aria-hidden="true"></svg>
                                                </div>
                                                <div class="barcode-text" id="barcodeText2"><?php echo htmlspecialchars(trim((string)($sample_data_preview['BarcodeNo2'] ?? $sample_data_preview['barcode2'] ?? '00003')) ?: '00003', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <span class="resize-handle" aria-label="Resize barcode"></span>
                                            </div>
                                            <?php } else { ?>
                                            <div class="barcode-print-wrap" id="barcode2">
                                                <span class="barcode-stripes"></span>
                                                <svg class="barcode-svg barcode-svg-box2" id="barcodeSvgBox2" data-barcode="00003" aria-hidden="true"></svg>
                                                <div class="barcode-text" id="barcodeText2"><?php echo htmlspecialchars(trim((string)($sample_data_preview['BarcodeNo2'] ?? $sample_data_preview['barcode2'] ?? '00003')) ?: '00003', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <?php } ?>
                                            <div class="barcode-white-drop-zone" id="barcodeWhiteDropZone2" title="Drop columns here"></div>
                                        </div>
                                    </div>
                                    <div class="barcode-default-handle" title="Handle"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="barcode-82x38-strip-layer" id="barcode82x38StripLayer" aria-label="Sticker strip lines"></div>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right sidebar: Toolbox + Properties -->
        <aside class="barcode-right-sidebar">
            <div class="barcode-panel">
                <div class="barcode-panel-title">Toolbox</div>
                <div class="toolbox-tabs">
                    <button type="button" class="toolbox-tab active" data-category="gold">Gold</button>
                    <button type="button" class="toolbox-tab" data-category="silver">Silver</button>
                    <button type="button" class="toolbox-tab" data-category="platinum">Platinum</button>
                    <button type="button" class="toolbox-tab" data-category="diamond">Diamond &amp; Stones</button>
                    <button type="button" class="toolbox-tab" data-category="imitation">Imitation Or Watches</button>
                    <button type="button" class="toolbox-tab" data-category="other">Other Or Services</button>
                </div>
                <div class="barcode-qr-toggle">
                    <span class="toggle-option<?php echo $bs_default_print_code === 'barcode' ? ' active' : ''; ?>" data-type="barcode">Barcode</span>
                    <span class="toggle-option<?php echo $bs_default_print_code === 'qr' ? ' active' : ''; ?>" data-type="qr">QR Code</span>
                </div>
                <div class="toolbox-search-wrap">
                    <input type="text" id="toolboxColumnSearch" class="toolbox-column-search" placeholder="Search columns..." autocomplete="off">
                </div>
                <?php
                $barcode_toolbox_tabs = [
                    ['id' => 'toolboxFieldsGold', 'class' => 'toolbox-fields-gold', 'divider' => 'Gold related columns', 'display' => 'flex', 'metal' => 'Gold'],
                    ['id' => 'toolboxFieldsSilver', 'class' => 'toolbox-fields-silver', 'divider' => 'Silver related columns', 'display' => 'none', 'metal' => 'Silver'],
                    ['id' => 'toolboxFieldsPlatinum', 'class' => 'toolbox-fields-platinum', 'divider' => 'Platinum related columns', 'display' => 'none', 'metal' => 'Platinum'],
                    ['id' => 'toolboxFieldsDiamond', 'class' => 'toolbox-fields-diamond', 'divider' => 'Diamond & Stones related columns', 'display' => 'none', 'metal' => 'Diamond & Stones'],
                    ['id' => 'toolboxFieldsImitation', 'class' => 'toolbox-fields-imitation', 'divider' => 'Imitation/Watches related columns', 'display' => 'none', 'metal' => 'Imitation Or Watches'],
                    ['id' => 'toolboxFieldsOther', 'class' => 'toolbox-fields-other', 'divider' => 'Other/Services related columns', 'display' => 'none', 'metal' => 'Other Or Services'],
                ];
                foreach ($barcode_toolbox_tabs as $bt) {
                    $barcode_toolbox_divider = $bt['divider'];
                    $barcode_extra_fields = $barcode_extra_fields_by_metal[$bt['metal'] ?? ''] ?? [];
                ?>
                <div class="toolbox-fields <?php echo htmlspecialchars($bt['class'], ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($bt['id'], ENT_QUOTES, 'UTF-8'); ?>" style="display:<?php echo htmlspecialchars($bt['display'], ENT_QUOTES, 'UTF-8'); ?>;">
                <?php include __DIR__ . '/includes/barcode-toolbox-field-chips.php'; ?>
                <?php include __DIR__ . '/includes/barcode-toolbox-extra-fields-chips.php'; ?>
                </div>
                <?php } ?>
            </div>
            <div class="barcode-panel">
                <div class="barcode-panel-title">Properties</div>
                <div class="properties-body">
                    <div class="prop-row prop-row-cols">
                        <div class="prop-field">
                            <label>Prefix</label>
                            <input type="text" value="" id="propPrefix" placeholder="">
                        </div>
                        <div class="prop-field">
                            <label>Suffix</label>
                            <input type="text" value="" id="propSuffix" placeholder="">
                        </div>
                    </div>
                    <div class="prop-row">
                        <label>Number Of Decimal</label>
                        <input type="number" value="0" min="0" id="propDecimals">
                    </div>
                    <div class="prop-row prop-row-cols prop-row-white-strip" id="propRowWhiteStripSize">
                        <div class="prop-field">
                            <label>Width (px)</label>
                            <input type="number" value="250" min="10" max="2000" id="propWhiteStripWidth" title="White Strip width">
                        </div>
                        <div class="prop-field">
                            <label>Height (px)</label>
                            <input type="number" value="40" min="5" max="2000" id="propWhiteStripHeight" title="White Strip height">
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-white-strip" id="propRowWhiteStripColors">
                        <div class="prop-field">
                            <label>Background Color</label>
                            <input type="color" value="#ffffff" id="propWhiteStripBg" title="Background">
                        </div>
                        <div class="prop-field">
                            <label>Print Border Enabled</label>
                            <select id="propWhiteStripBorderEnabled">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-white-strip" id="propRowWhiteStripBorder">
                        <div class="prop-field">
                            <label>Print Border Color</label>
                            <input type="color" value="#000000" id="propWhiteStripBorderColor" title="Print border color">
                        </div>
                        <div class="prop-field">
                            <label>Print Border Width (px)</label>
                            <input type="number" value="0" min="0" max="20" step="0.5" id="propWhiteStripBorderWidth">
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-white-strip" id="propRowWhiteStripFx">
                        <div class="prop-field">
                            <label>Border Radius (px)</label>
                            <input type="number" value="0" min="0" max="100" id="propWhiteStripBorderRadius">
                        </div>
                        <div class="prop-field">
                            <label>Opacity</label>
                            <input type="number" value="1" min="0" max="1" step="0.05" id="propWhiteStripOpacity">
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-white-strip" id="propRowWhiteStripRot">
                        <div class="prop-field">
                            <label>Rotation (deg)</label>
                            <input type="number" value="0" min="-180" max="180" step="1" id="propWhiteStripRotation">
                        </div>
                        <div class="prop-field">
                            <label>Z-Index</label>
                            <input type="number" value="5" min="0" max="999" id="propWhiteStripZIndex">
                        </div>
                    </div>
                    <div class="prop-row prop-row-white-strip" id="propRowWhiteStripLayer">
                        <label>Layer Order</label>
                        <div class="prop-white-strip-layer-btns">
                            <button type="button" id="btnWhiteStripBringForward" title="Bring forward">Bring Forward</button>
                            <button type="button" id="btnWhiteStripSendBackward" title="Send backward">Send Backward</button>
                            <button type="button" id="btnWhiteStripBringFront" title="Bring to front">Bring to Front</button>
                            <button type="button" id="btnWhiteStripSendBack" title="Send to back">Send to Back</button>
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols">
                        <div class="prop-field">
                            <label>Font</label>
                            <select id="propFont">
                                <option value="Arial">Arial</option>
                                <option value="Segoe UI">Segoe UI</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Microsoft YaHei UI">Microsoft YaHei UI</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Trebuchet MS">Trebuchet MS</option>
                                <option value="Lucida Sans Unicode">Lucida Sans Unicode</option>
                                <option value="Lucida Console">Lucida Console</option>
                                <option value="Consolas">Consolas</option>
                                <option value="Calibri">Calibri</option>
                                <option value="Cambria">Cambria</option>
                                <option value="Candara">Candara</option>
                                <option value="Constantia">Constantia</option>
                                <option value="Corbel">Corbel</option>
                                <option value="Franklin Gothic Medium">Franklin Gothic Medium</option>
                                <option value="Garamond">Garamond</option>
                                <option value="Impact">Impact</option>
                                <option value="Palatino Linotype">Palatino Linotype</option>
                                <option value="Century Gothic">Century Gothic</option>
                                <option value="Comic Sans MS">Comic Sans MS</option>
                                <option value="Helvetica">Helvetica</option>
                                <option value="Helvetica Neue">Helvetica Neue</option>
                                <option value="Roboto">Roboto</option>
                                <option value="Open Sans">Open Sans</option>
                                <option value="Lato">Lato</option>
                                <option value="Source Sans Pro">Source Sans Pro</option>
                                <option value="PT Sans">PT Sans</option>
                                <option value="Oswald">Oswald</option>
                                <option value="Montserrat">Montserrat</option>
                                <option value="Poppins">Poppins</option>
                                <option value="Ubuntu">Ubuntu</option>
                                <option value="Noto Sans">Noto Sans</option>
                                <option value="sans-serif">sans-serif</option>
                                <option value="serif">serif</option>
                                <option value="monospace">monospace</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Font Size</label>
                            <input type="number" value="10" min="6" max="72" id="propFontSize">
                        </div>
                    </div>
                    <div class="prop-row prop-row-barcode-size" id="propRowBarcodeSize">
                        <label>Barcode size</label>
                        <div class="barcode-size-buttons">
                            <button type="button" class="btn-size" id="btnBarcodeDecrease" title="Smaller: stripe area, bar height, and line thickness" aria-label="Decrease barcode size">&#8722;</button>
                            <button type="button" class="btn-size" id="btnBarcodeIncrease" title="Larger: stripe area, bar height, and line thickness" aria-label="Increase barcode size">+</button>
                            <button type="button" class="btn-size" id="btnBarcodeCenter" title="Center barcode on label" aria-label="Center barcode">&#9675;</button>
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-barcode-bar" id="propRowBarcodeBar">
                        <div class="prop-field">
                            <label>Line thickness (px)</label>
                            <input type="number" value="<?php echo (int)($bs['barcode_bar_width'] ?? 2); ?>" min="1" max="10" id="propBarcodeBarWidth" title="Thickness of each black bar (1 = thinnest lines)">
                        </div>
                        <div class="prop-field">
                            <label>Bar height (px)</label>
                            <input type="number" value="<?php echo (int)($bs['barcode_bar_height'] ?? 28); ?>" min="8" max="200" id="propBarcodeBarHeight" title="Height of barcode lines">
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-barcode-display" id="propRowBarcodeDisplay">
                        <div class="prop-field">
                            <label>Barcode width (px)</label>
                            <input type="number" value="0" min="0" max="500" id="propBarcodeDisplayWidth" title="Overall barcode width on label. 0 = auto. Drag the purple handle on the barcode to resize.">
                        </div>
                        <div class="prop-field">
                            <label>&nbsp;</label>
                            <small class="prop-hint" style="display:block;margin-top:6px;color:#64748b;">Click barcode lines on label, then drag the purple corner handle or use −/+.</small>
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-qr-size" id="propRowQrSize" style="display: none;">
                        <div class="prop-field">
                            <label>QR width (px)</label>
                            <input type="number" value="<?php echo max(12, min(200, (int)($bs['qr_width'] ?? 60))); ?>" min="12" max="200" id="propQrWidth" title="QR code width (12–200)">
                        </div>
                        <div class="prop-field">
                            <label>QR height (px)</label>
                            <input type="number" value="<?php echo max(12, min(200, (int)($bs['qr_height'] ?? 60))); ?>" min="12" max="200" id="propQrHeight" title="QR code height (12–200)">
                        </div>
                    </div>
                    <small class="prop-hint prop-hint-barcode" id="propHintBarcode" style="margin-top: 2px;">Line thickness = each black bar width. Bar height = stripe height. Drag the purple corner handle to change width and height.</small>
                    <small class="prop-hint prop-hint-qr" id="propHintQr" style="margin-top: 2px; display: none;">QR width/height = size of QR code. Click QR on label, then drag the purple corner handle or edit the values above.</small>
                    <div class="prop-row prop-row-cols">
                        <div class="prop-field">
                            <label>Label · Padding Top (px)</label>
                            <input type="number" value="<?php echo (int)$bs['label_pad_top']; ?>" min="0" max="200" step="1" id="labelPadTop">
                        </div>
                        <div class="prop-field">
                            <label>Label · Padding Right (px)</label>
                            <input type="number" value="<?php echo (int)$bs['label_pad_right']; ?>" min="0" max="200" step="1" id="labelPadRight">
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols">
                        <div class="prop-field">
                            <label>Label · Padding Bottom (px)</label>
                            <input type="number" value="<?php echo (int)$bs['label_pad_bottom']; ?>" min="0" max="200" step="1" id="labelPadBottom">
                        </div>
                        <div class="prop-field">
                            <label>Label · Padding Left (px)</label>
                            <input type="number" value="<?php echo (int)$bs['label_pad_left']; ?>" min="0" max="200" step="1" id="labelPadLeft">
                        </div>
                    </div>
                    <div class="prop-row">
                        <small class="prop-hint" style="display:block;margin-top:2px;color:#64748b;">Label padding shifts the whole printed label (including QR/barcode). Preview on this page does not change — check barcode print.</small>
                    </div>
                    <div class="prop-row prop-row-cols">
                        <div class="prop-field">
                            <label>Field · Padding Top (px)</label>
                            <input type="number" value="0" min="0" max="200" step="1" id="propPadTop">
                        </div>
                        <div class="prop-field">
                            <label>Field · Padding Right (px)</label>
                            <input type="number" value="0" min="0" max="200" step="1" id="propPadRight">
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols">
                        <div class="prop-field">
                            <label>Field · Padding Bottom (px)</label>
                            <input type="number" value="0" min="0" max="200" step="1" id="propPadBottom">
                        </div>
                        <div class="prop-field">
                            <label>Field · Padding Left (px)</label>
                            <input type="number" value="0" min="0" max="200" step="1" id="propPadLeft">
                        </div>
                    </div>
                    <div class="prop-row">
                        <small class="prop-hint" style="display:block;margin-top:2px;color:#64748b;">Field padding is for the selected text item only on print.</small>
                    </div>
                    <div class="prop-row prop-row-move">
                        <label>Move selected</label>
                        <div class="move-buttons">
                            <button type="button" class="btn-move" id="btnMoveUp" title="Up">↑</button>
                            <button type="button" class="btn-move" id="btnMoveDown" title="Down">↓</button>
                            <button type="button" class="btn-move" id="btnMoveLeft" title="Left">←</button>
                            <button type="button" class="btn-move" id="btnMoveRight" title="Right">→</button>
                        </div>
                        <small class="prop-hint">Arrow keys / buttons = 1px nudge. Hold Shift for 8px.</small>
                    </div>
                    <div class="prop-row prop-82x38-box-settings" id="propRow82x38Box" style="display: <?php echo $bs_label_preset_for_select === '82x38_2box' ? 'block' : 'none'; ?>;">
                        <label>82×38 sticker layout (mm) — set Box 2 Top for right-tag gap from sticker edge</label>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 1 Left (fixed)</label>
                                <input type="text" value="0 mm (bottom-left)" readonly style="background:#f1f5f9;color:#475569;">
                                <input type="hidden" value="0" id="propBox1LeftMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 1 Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1']['top'] ?? $bs_82x38_layout['box1_top_mm'] ?? 13); ?>" min="0" max="13" step="0.1" id="propBox1TopMm" title="Vertical position of left print box">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 2 Left (fixed)</label>
                                <input type="text" value="62 mm (top-right)" readonly style="background:#f1f5f9;color:#475569;">
                                <input type="hidden" value="62" id="propBox2LeftMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 2 Top (mm) — gap from top</label>
                                <input type="number" value="<?php
                                    echo (float)($bs_82x38_layout['box2']['top'] ?? $bs_82x38_layout['box2_top_mm'] ?? 2);
                                ?>" min="0" max="13" step="0.1" id="propBox2TopMm" title="Move right tag down from sticker top to avoid QR clipping">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box size (fixed)</label>
                                <input type="text" value="20 mm × 25 mm (2 cm × 2.5 cm)" readonly id="propBoxSizeFixedLabel" style="background:#f1f5f9;color:#475569;">
                                <input type="hidden" value="20" id="propBoxWidthMm">
                                <input type="hidden" value="25" id="propBoxHeightMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols prop-82x38-barcode-wh">
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 1 Barcode Width" data-82x38-label-suffix=" (mm)">Box 1 Barcode Width (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_width_mm'] ?? $bs_82x38_layout['barcode_width_mm'] ?? 18); ?>" min="4" max="40" step="0.1" id="propBox1BarcodeWidthMm">
                            </div>
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 1 Barcode Height" data-82x38-label-suffix=" (mm)">Box 1 Barcode Height (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_height_mm'] ?? $bs_82x38_layout['barcode_height_mm'] ?? 7); ?>" min="3" max="30" step="0.1" id="propBox1BarcodeHeightMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols prop-82x38-barcode-wh">
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 2 Barcode Width" data-82x38-label-suffix=" (mm)">Box 2 Barcode Width (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_width_mm'] ?? $bs_82x38_layout['barcode_width_mm'] ?? 18); ?>" min="4" max="40" step="0.1" id="propBox2BarcodeWidthMm">
                            </div>
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 2 Barcode Height" data-82x38-label-suffix=" (mm)">Box 2 Barcode Height (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_height_mm'] ?? $bs_82x38_layout['barcode_height_mm'] ?? 7); ?>" min="3" max="30" step="0.1" id="propBox2BarcodeHeightMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 1 Barcode Left" data-82x38-label-suffix=" (mm)">Box 1 Barcode Left (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_left_mm'] ?? $bs_82x38_layout['barcode_left_mm'] ?? 2); ?>" min="0" max="20" step="0.1" id="propBox1BarcodeLeftMm">
                            </div>
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 1 Barcode Top" data-82x38-label-suffix=" (mm)">Box 1 Barcode Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_top_mm'] ?? $bs_82x38_layout['barcode_top_mm'] ?? 3); ?>" min="0" max="24" step="0.1" id="propBox1BarcodeTopMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 2 Barcode Left" data-82x38-label-suffix=" (mm)">Box 2 Barcode Left (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_left_mm'] ?? $bs_82x38_layout['barcode_left_mm'] ?? 2); ?>" min="0" max="20" step="0.1" id="propBox2BarcodeLeftMm">
                            </div>
                            <div class="prop-field">
                                <label data-82x38-label-barcode="Box 2 Barcode Top" data-82x38-label-suffix=" (mm)">Box 2 Barcode Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_top_mm'] ?? $bs_82x38_layout['barcode_top_mm'] ?? 3); ?>" min="0" max="24" step="0.1" id="propBox2BarcodeTopMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Number margin-top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['barcode_no_margin_top_mm'] ?? 1); ?>" min="0" max="10" step="0.1" id="propBoxBarcodeNoMarginTopMm">
                            </div>
                            <div class="prop-field">
                                <label>Number font size (px)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['barcode_no_font_size'] ?? 7); ?>" min="5" max="24" step="0.5" id="propBoxBarcodeNoFontSize">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Printer offset top (mm)</label>
                                <input type="number" value="<?php echo $bs_sticker_print_offset_top; ?>" min="-12" max="12" step="0.1" id="propStickerPrintOffsetTopMm" title="Negative moves print up (less gap at top). Try -4 to -6 if labels print too low.">
                            </div>
                            <div class="prop-field">
                                <label>Printer offset left (mm)</label>
                                <input type="number" value="<?php echo $bs_sticker_print_offset_left; ?>" min="-12" max="12" step="0.1" id="propStickerPrintOffsetLeftMm" title="Negative moves print left.">
                            </div>
                        </div>
                        <small class="prop-hint">Inner boxes are fixed 20×25 mm: Box 1 bottom-left, Box 2 top-right. If physical print has a gap at the top, lower <strong>Box 1 Top</strong>, raise <strong>Box 2 Top</strong>, set <strong>Box Barcode Top</strong> to 0, or use a negative <strong>Printer offset top</strong> (e.g. -5 mm). Save, then re-print.</small>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php include 'footer-script.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
(function() {
    var savedDesignLayoutBarcodeInit = <?php echo json_encode($bs_design_layout); ?>;
    var savedDesignLayoutQrInit = <?php echo json_encode($bs_design_layout_qr); ?>;
    var persistedLayoutBarcode = savedDesignLayoutBarcodeInit;
    var persistedLayoutQr = (savedDesignLayoutQrInit && String(savedDesignLayoutQrInit).trim()) ? savedDesignLayoutQrInit : '{}';
    var persistedShowProductNameBarcode = <?php echo (int)$show_product_name_barcode; ?>;
    var persistedShowProductNameQr = <?php echo (int)$show_product_name_qr; ?>;
    var persistedShowPriceBarcode = <?php echo (int)$show_price_barcode; ?>;
    var persistedShowPriceQr = <?php echo (int)$show_price_qr; ?>;
    var persistedShowBarcodeNoBarcode = <?php echo (int)$show_barcode_number_barcode; ?>;
    var persistedShowBarcodeNoQr = <?php echo (int)$show_barcode_number_qr; ?>;
    var currentCodeType = <?php echo json_encode($bs_default_print_code === 'qr' ? 'qr' : 'barcode'); ?>;
    var savedDesignLayout = (currentCodeType === 'qr') ? persistedLayoutQr : persistedLayoutBarcode;
    var sampleFieldPreview = <?php echo json_encode($sample_field_preview); ?>;
    var fieldsByCategory = {
        gold: document.getElementById('toolboxFieldsGold'),
        silver: document.getElementById('toolboxFieldsSilver'),
        platinum: document.getElementById('toolboxFieldsPlatinum'),
        diamond: document.getElementById('toolboxFieldsDiamond'),
        imitation: document.getElementById('toolboxFieldsImitation'),
        other: document.getElementById('toolboxFieldsOther')
    };

    /** Per metal + label size ("Gold::100x18", "Silver::100x80", "Gold::custom_100x25", …). */
    var barcodeSettingsCache = <?php echo json_encode($barcode_settings_cache_js, JSON_UNESCAPED_UNICODE); ?> || {};
    var currentBarcodeMetalKey = <?php echo json_encode($bs_metal); ?> || '';
    var currentBarcodeLabelStorageKey = <?php echo json_encode($bs_load_label_storage); ?> || '100x18';
    var lastLoadedBarcodeContext = {
        metal: currentBarcodeMetalKey,
        labelUi: <?php echo json_encode($bs_label_preset_for_select); ?>,
        labelW: <?php echo (float) ($bs['label_width_mm'] ?? 100); ?>,
        labelH: <?php echo (float) ($bs['label_height_mm'] ?? 18); ?>
    };

    function barcodeLabelStoragePreset(preset, w, h) {
        preset = String(preset || '100x18').trim();
        var standard = ['100x18', '100x25', '100x48', '100x80', '64x25', '81x12', '120x50', '82x38_2box', '250x120', 'zebra-zpl'];
        if (preset === '120x50') return '120x50';
        if (preset === '82x38_2box') return '82x38_2box';
        if (preset !== 'custom' && standard.indexOf(preset) >= 0) return preset;
        if (/^custom_\d+x\d+$/i.test(preset)) return preset.toLowerCase();
        w = parseInt(w, 10) || 100;
        h = parseInt(h, 10) || 18;
        return 'custom_' + w + 'x' + h;
    }

    function getLabelMmFromPresetUi(preset, cwVal, chVal) {
        preset = String(preset || '100x18').trim();
        if (preset === '120x50') {
            return { w: 120, h: 50 };
        }
        if (preset === '82x38_2box') {
            return { w: 82, h: 38 };
        }
        if (preset === '250x120') {
            return { w: 250, h: 120 };
        }
        if (preset === 'custom') {
            var defW = 100;
            var defH = 18;
            return { w: parseFloat(cwVal) || defW, h: parseFloat(chVal) || defH };
        }
        if (preset !== 'zebra-zpl' && preset.indexOf('x') > 0) {
            var parts = preset.split('x');
            return { w: parseInt(parts[0], 10) || 100, h: parseInt(parts[1], 10) || 18 };
        }
        return { w: 100, h: 18 };
    }

    function getCurrentLabelPresetUi() {
        var el = document.getElementById('barcodeLabelSize');
        var preset = el ? String(el.value || '').trim() : '100x18';
        if (!preset && el) preset = String(el.getAttribute('data-saved-preset') || '100x18').trim();
        var cw = document.getElementById('barcodeCustomWidthMm');
        var ch = document.getElementById('barcodeCustomHeightMm');
        var mm = getLabelMmFromPresetUi(preset, cw ? cw.value : null, ch ? ch.value : null);
        return { ui: preset || '100x18', w: mm.w, h: mm.h };
    }

    function syncLabelPresetInputsFromUi(lp) {
        if (!lp) lp = getCurrentLabelPresetUi();
        var cw = document.getElementById('barcodeCustomWidthMm');
        var ch = document.getElementById('barcodeCustomHeightMm');
        if (cw) cw.value = lp.w;
        if (ch) ch.value = lp.h;
    }

    /** Resize preview box immediately when Label Size changes (do not wait for AJAX). */
    function resizeBarcodePreviewForCurrentLabelSize() {
        var lp = getCurrentLabelPresetUi();
        syncLabelPresetInputsFromUi(lp);
        if (typeof syncLabelMmFromPreset === 'function') syncLabelMmFromPreset(lp.ui);
        if (lp.ui === '82x38_2box' && typeof render82x38DualEditor === 'function') {
            render82x38DualEditor();
        } else if (typeof applyLabelSizeToBox === 'function') {
            applyLabelSizeToBox();
        } else if (typeof labelWidthMm !== 'undefined') {
            labelWidthMm = lp.w;
            labelHeightMm = lp.h;
        }
    }

    function barcodeSettingsCacheKey(metal, presetUi, w, h) {
        metal = String(metal || '').trim();
        var storage = barcodeLabelStoragePreset(presetUi, w, h);
        return metal + '::' + storage;
    }

    function getCurrentBarcodeCacheKey() {
        var lp = getCurrentLabelPresetUi();
        return barcodeSettingsCacheKey(currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
    }

    function stashBarcodeContextToCache(ctx) {
        if (!ctx || !ctx.metal) return;
        var key = barcodeSettingsCacheKey(ctx.metal, ctx.labelUi, ctx.labelW, ctx.labelH);
        if (!key || key.indexOf('::') <= 0) return;
        barcodeSettingsCache[key] = captureMetalSettingsSnapshot();
    }

    function stashCurrentBarcodeSettingsToCache() {
        stashBarcodeContextToCache(lastLoadedBarcodeContext);
    }

    /** Reload URL: set-software.php?barcode_metal=Gold&barcode_label_size=100x18 (&branch_id if present). */
    function buildBarcodeSettingsPageUrl(metal, labelUi) {
        var parts = [];
        metal = String(metal || '').trim();
        labelUi = String(labelUi || '').trim();
        if (metal) parts.push('barcode_metal=' + encodeURIComponent(metal));
        if (labelUi) parts.push('barcode_label_size=' + encodeURIComponent(labelUi));
        try {
            var qs = new URLSearchParams(window.location.search);
            var branchId = qs.get('branch_id');
            if (branchId) parts.push('branch_id=' + encodeURIComponent(branchId));
        } catch (e) {}
        return 'set-software.php' + (parts.length ? '?' + parts.join('&') : '');
    }

    function navigateToBarcodeSettingsPage(metal, labelUi) {
        stashCurrentBarcodeSettingsToCache();
        window.location.href = buildBarcodeSettingsPageUrl(metal, labelUi);
    }

    function setLastLoadedBarcodeContext(metal, presetUi, w, h) {
        lastLoadedBarcodeContext = {
            metal: String(metal || '').trim(),
            labelUi: presetUi || '100x18',
            labelW: parseFloat(w) || 100,
            labelH: parseFloat(h) || 18
        };
    }

    function finishLoadBarcodeSnapshot(snap, metal, presetUi, w, h) {
        var requestedLp = {
            ui: presetUi || '100x18',
            w: (w !== undefined && w !== null && !isNaN(w)) ? w : getLabelMmFromPresetUi(presetUi || '100x18', null, null).w,
            h: (h !== undefined && h !== null && !isNaN(h)) ? h : getLabelMmFromPresetUi(presetUi || '100x18', null, null).h
        };
        if (snap && !snapshotMatchesRequestedLabelPreset(snap, requestedLp)) {
            snap = null;
        }
        if (snap) {
            applyMetalSettingsSnapshot(snap, requestedLp);
            var ui = snap.label_size_ui_preset || requestedLp.ui;
            var sw = snap.label_width_mm != null ? snap.label_width_mm : requestedLp.w;
            var sh = snap.label_height_mm != null ? snap.label_height_mm : requestedLp.h;
            setLastLoadedBarcodeContext(metal, ui, sw, sh);
        } else {
            applyMetalSettingsSnapshot(null, requestedLp);
            setLastLoadedBarcodeContext(metal, requestedLp.ui, requestedLp.w, requestedLp.h);
        }
    }

    function loadBarcodeSettingsForMetalAndLabel(metal, presetUi, w, h) {
        resetBarcodeLayoutRestoreFlag();
        stashCurrentBarcodeSettingsToCache();
        currentBarcodeMetalKey = String(metal || '').trim();
        var lp = { ui: presetUi || '100x18', w: w, h: h };
        if (lp.w === undefined || lp.w === null || isNaN(lp.w)) {
            var cur = getCurrentLabelPresetUi();
            if (lp.ui === undefined || lp.ui === null) lp.ui = cur.ui;
            lp.w = cur.w;
            lp.h = cur.h;
        }
        currentBarcodeLabelStorageKey = barcodeLabelStoragePreset(lp.ui, lp.w, lp.h);
        if (currentBarcodeMetalKey) {
            activateToolboxCategory(metalDisplayToToolboxCategory(currentBarcodeMetalKey));
        }
        if (!currentBarcodeMetalKey) {
            finishLoadBarcodeSnapshot(null, '', lp.ui, lp.w, lp.h);
            return;
        }
        var cacheKey = barcodeSettingsCacheKey(currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
        if (barcodeSettingsCache[cacheKey]) {
            finishLoadBarcodeSnapshot(barcodeSettingsCache[cacheKey], currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
            return;
        }
        var qs = 'metal_type=' + encodeURIComponent(currentBarcodeMetalKey)
            + '&label_size_preset=' + encodeURIComponent(lp.ui)
            + '&label_width_mm=' + encodeURIComponent(lp.w)
            + '&label_height_mm=' + encodeURIComponent(lp.h);
        fetch('ajax/get-barcode-settings.php?' + qs, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.settings) {
                    barcodeSettingsCache[cacheKey] = data.settings;
                    finishLoadBarcodeSnapshot(data.settings, currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
                } else {
                    finishLoadBarcodeSnapshot(null, currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
                }
            })
            .catch(function() {
                finishLoadBarcodeSnapshot(null, currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
            });
    }

    function metalDisplayToToolboxCategory(metalName) {
        var n = String(metalName || '').toLowerCase().trim();
        if (!n) return 'gold';
        if (n.indexOf('gold') >= 0) return 'gold';
        if (n.indexOf('silver') >= 0) return 'silver';
        if (n.indexOf('platinum') >= 0) return 'platinum';
        if (n.indexOf('diamond') >= 0 || n.indexOf('stone') >= 0) return 'diamond';
        if (n.indexOf('imitation') >= 0 || n.indexOf('watch') >= 0) return 'imitation';
        return 'other';
    }

    function activateToolboxCategory(cat) {
        if (!cat) cat = 'gold';
        var btn = document.querySelector('.toolbox-tab[data-category="' + cat + '"]');
        if (btn) btn.click();
    }

    function captureMetalSettingsSnapshot() {
        if (typeof flushPendingCanvasPropsToDom === 'function') flushPendingCanvasPropsToDom();
        if (typeof flushCheckboxDomToPersisted === 'function') flushCheckboxDomToPersisted();
        var pl = null;
        try {
            pl = buildBarcodeFormPayload();
            var lp = buildBarcodeLayoutPayloadObject(pl);
            lp.layout_variant = (currentCodeType === 'qr') ? 'qr' : 'barcode';
            if (currentCodeType === 'barcode') {
                persistedLayoutBarcode = JSON.stringify(lp);
            } else {
                persistedLayoutQr = JSON.stringify(lp);
            }
        } catch (e) {}
        var labelSizeEl = document.getElementById('barcodeLabelSize');
        var labelPreset = labelSizeEl ? String(labelSizeEl.value || '').trim() : '100x18';
        if (!labelPreset && labelSizeEl) {
            labelPreset = String(labelSizeEl.getAttribute('data-saved-preset') || '100x18').trim();
        }
        var dpcEl = document.getElementById('defaultPrintCodeType');
        var cwEl = document.getElementById('barcodeCustomWidthMm');
        var chEl = document.getElementById('barcodeCustomHeightMm');
        var lw = pl ? pl.label_width_mm : (cwEl ? parseFloat(cwEl.value) || 100 : 100);
        var lh = pl ? pl.label_height_mm : (chEl ? parseFloat(chEl.value) || 18 : 18);
        var storagePreset = barcodeLabelStoragePreset(labelPreset, lw, lh);
        return {
            metal_type: currentBarcodeMetalKey,
            label_size_preset: storagePreset,
            label_size_storage_preset: storagePreset,
            label_size_ui_preset: labelPreset,
            cache_key: barcodeSettingsCacheKey(currentBarcodeMetalKey, labelPreset, lw, lh),
            label_width_mm: lw,
            label_height_mm: lh,
            font_size: parseInt(document.getElementById('barcodeFontSize') && document.getElementById('barcodeFontSize').value, 10) || 12,
            print_copies: parseInt(document.getElementById('barcodeCopies') && document.getElementById('barcodeCopies').value, 10) || 1,
            default_print_code_type: (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr')
                ? 'qr'
                : ((dpcEl && dpcEl.value === 'qr') ? 'qr' : 'barcode'),
            show_product_name_barcode: persistedShowProductNameBarcode,
            show_product_name_qr: persistedShowProductNameQr,
            show_price_barcode: persistedShowPriceBarcode,
            show_price_qr: persistedShowPriceQr,
            show_barcode_number_barcode: persistedShowBarcodeNoBarcode,
            show_barcode_number_qr: persistedShowBarcodeNoQr,
            barcode_bar_width: parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10) || 2,
            barcode_bar_height: parseInt(document.getElementById('propBarcodeBarHeight') && document.getElementById('propBarcodeBarHeight').value, 10) || 28,
            qr_width: parseInt(document.getElementById('propQrWidth') && document.getElementById('propQrWidth').value, 10) || 60,
            qr_height: parseInt(document.getElementById('propQrHeight') && document.getElementById('propQrHeight').value, 10) || 60,
            label_pad_top: parseInt(document.getElementById('labelPadTop') && document.getElementById('labelPadTop').value, 10) || 0,
            label_pad_right: parseInt(document.getElementById('labelPadRight') && document.getElementById('labelPadRight').value, 10) || 0,
            label_pad_bottom: parseInt(document.getElementById('labelPadBottom') && document.getElementById('labelPadBottom').value, 10) || 0,
            label_pad_left: parseInt(document.getElementById('labelPadLeft') && document.getElementById('labelPadLeft').value, 10) || 0,
            design_layout_barcode: persistedLayoutBarcode || '{}',
            design_layout_qr: persistedLayoutQr || '{}',
            is_default_print: (document.getElementById('barcodeIsDefaultPrint') && document.getElementById('barcodeIsDefaultPrint').checked) ? 1 : 0
        };
    }

    function applyRequestedLabelPresetUi(requestedLp) {
        if (!requestedLp) {
            requestedLp = getCurrentLabelPresetUi();
        } else if (typeof requestedLp === 'string') {
            var presetStr = String(requestedLp).trim();
            var mmFromStr = getLabelMmFromPresetUi(presetStr, null, null);
            requestedLp = { ui: presetStr, w: mmFromStr.w, h: mmFromStr.h };
        }
        var labelSizeEl = document.getElementById('barcodeLabelSize');
        if (labelSizeEl && requestedLp && requestedLp.ui) {
            var ui = String(requestedLp.ui).trim();
            if (ui.indexOf('custom_') === 0) ui = 'custom';
            if (labelSizeEl.querySelector('option[value="' + ui + '"]')) {
                labelSizeEl.value = ui;
            }
            labelSizeEl.setAttribute('data-saved-preset', ui);
            currentBarcodeLabelStorageKey = barcodeLabelStoragePreset(ui, requestedLp.w, requestedLp.h);
        }
        syncLabelPresetInputsFromUi(requestedLp);
        if (typeof syncLabelMmFromPreset === 'function') syncLabelMmFromPreset(requestedLp.ui);
        var wrapW = document.getElementById('barcodeCustomSizeWrap');
        var wrapH = document.getElementById('barcodeCustomHeightWrap');
        var showMm = showBarcodeCustomSizeFields();
        if (wrapW) wrapW.style.display = showMm ? 'flex' : 'none';
        if (wrapH) wrapH.style.display = showMm ? 'flex' : 'none';
    }

    function snapshotMatchesRequestedLabelPreset(snap, requestedLp) {
        if (!snap || !requestedLp) return false;
        var reqStorage = barcodeLabelStoragePreset(requestedLp.ui, requestedLp.w, requestedLp.h);
        var snapStorage = snap.label_size_storage_preset || snap.label_size_preset || '';
        if (!snapStorage) {
            snapStorage = barcodeLabelStoragePreset(
                snap.label_size_ui_preset || snap.label_size_preset || '100x18',
                snap.label_width_mm,
                snap.label_height_mm
            );
        }
        if (String(snapStorage).toLowerCase() === String(reqStorage).toLowerCase()) {
            return true;
        }
        if (String(snapStorage).toLowerCase() === 'custom' && requestedLp && requestedLp.ui && requestedLp.ui !== 'custom') {
            var reqMm = getLabelMmFromPresetUi(requestedLp.ui, requestedLp.w, requestedLp.h);
            var snapW = parseFloat(snap.label_width_mm);
            var snapH = parseFloat(snap.label_height_mm);
            if (!isNaN(snapW) && !isNaN(snapH) && Math.abs(snapW - reqMm.w) < 0.01 && Math.abs(snapH - reqMm.h) < 0.01) {
                return true;
            }
        }
        return false;
    }

    function applyMetalSettingsSnapshot(snap, requestedLp) {
        document.querySelectorAll('.canvas-dropped-item').forEach(function(el) { el.remove(); });
        if (!requestedLp) requestedLp = getCurrentLabelPresetUi();
        if (!snap) {
            persistedLayoutBarcode = '{}';
            persistedLayoutQr = '{}';
            savedDesignLayout = '{}';
            applyRequestedLabelPresetUi(requestedLp);
            var dpClear = document.getElementById('barcodeIsDefaultPrint');
            if (dpClear) dpClear.checked = false;
        } else {
            persistedLayoutBarcode = snap.design_layout_barcode || '{}';
            persistedLayoutQr = snap.design_layout_qr || '{}';
            persistedShowProductNameBarcode = snap.show_product_name_barcode ? 1 : 0;
            persistedShowProductNameQr = snap.show_product_name_qr ? 1 : 0;
            persistedShowPriceBarcode = snap.show_price_barcode ? 1 : 0;
            persistedShowPriceQr = snap.show_price_qr ? 1 : 0;
            persistedShowBarcodeNoBarcode = snap.show_barcode_number_barcode ? 1 : 0;
            persistedShowBarcodeNoQr = snap.show_barcode_number_qr ? 1 : 0;
            currentCodeType = (snap.default_print_code_type === 'qr') ? 'qr' : 'barcode';
            savedDesignLayout = (currentCodeType === 'qr') ? persistedLayoutQr : persistedLayoutBarcode;
            if (snapshotMatchesRequestedLabelPreset(snap, requestedLp)) {
                var labelSizeEl = document.getElementById('barcodeLabelSize');
                if (labelSizeEl) {
                    var presetUi = snap.label_size_ui_preset || snap.label_size_preset || requestedLp.ui || '100x18';
                    if (presetUi.indexOf('custom_') === 0) presetUi = 'custom';
                    if (labelSizeEl.querySelector('option[value="' + presetUi + '"]')) {
                        labelSizeEl.value = presetUi;
                    }
                    labelSizeEl.setAttribute('data-saved-preset', presetUi);
                    currentBarcodeLabelStorageKey = snap.label_size_storage_preset || snap.label_size_preset || barcodeLabelStoragePreset(presetUi, snap.label_width_mm, snap.label_height_mm);
                }
                var cwEl = document.getElementById('barcodeCustomWidthMm');
                var chEl = document.getElementById('barcodeCustomHeightMm');
                if (cwEl) cwEl.value = snap.label_width_mm || requestedLp.w || 100;
                if (chEl) chEl.value = snap.label_height_mm || requestedLp.h || 18;
            } else {
                applyRequestedLabelPresetUi(requestedLp);
            }
            var fsEl = document.getElementById('barcodeFontSize');
            if (fsEl) fsEl.value = snap.font_size || 12;
            var cpEl = document.getElementById('barcodeCopies');
            if (cpEl) cpEl.value = snap.print_copies || 1;
            var dpEl = document.getElementById('barcodeIsDefaultPrint');
            if (dpEl) dpEl.checked = !!snap.is_default_print;
            var dpcEl = document.getElementById('defaultPrintCodeType');
            if (dpcEl) dpcEl.value = currentCodeType;
            document.querySelectorAll('.barcode-qr-toggle .toggle-option').forEach(function(o) {
                o.classList.toggle('active', o.getAttribute('data-type') === currentCodeType);
            });
            var pbw = document.getElementById('propBarcodeBarWidth');
            var pbh = document.getElementById('propBarcodeBarHeight');
            if (pbw) pbw.value = snap.barcode_bar_width || 2;
            if (pbh) pbh.value = snap.barcode_bar_height || 28;
            var qrw = document.getElementById('propQrWidth');
            var qrh = document.getElementById('propQrHeight');
            if (qrw) qrw.value = snap.qr_width || 60;
            if (qrh) qrh.value = snap.qr_height || 60;
            var padIds = [['label_pad_top', 'labelPadTop'], ['label_pad_right', 'labelPadRight'], ['label_pad_bottom', 'labelPadBottom'], ['label_pad_left', 'labelPadLeft']];
            padIds.forEach(function(pair) {
                var el = document.getElementById(pair[1]);
                if (el && snap[pair[0]] !== undefined) el.value = snap[pair[0]];
            });
        }
        applyCheckboxPersistToDom();
        updatePropertiesPanelForCodeType();
        if (is82x38TwoBoxPreset()) {
            render82x38DualEditor();
        } else if (typeof applyLabelSizeToBox === 'function') {
            applyLabelSizeToBox();
        }
        setTimeout(function() {
            if (is82x38TwoBoxPreset()) {
                run82x38SavedLayoutRestore();
                return;
            }
            if (typeof updateBarcodeQrDisplay === 'function') updateBarcodeQrDisplay();
            if (isDualCanvasLayoutPreset()) {
                if (typeof restoreSavedLayout === 'function') restoreSavedLayout();
                if (typeof refreshCodeGraphicAfterLayoutRestore === 'function') refreshCodeGraphicAfterLayoutRestore();
                ensureDualBarcodesRendered({ preservePositions: !!snap });
                if (!snap) {
                    positionDefaultBarcodesInEachLabel();
                }
                clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
                syncDualCanvasHeight();
            } else {
                resetBarcodeLayoutRestoreFlag();
                ensureBarcodeLayoutRestoredFromSaved(0);
            }
        }, 80);
    }

    function onBarcodeMetalTypeChange() {
        var metalSelectEl = document.getElementById('barcodeMetalType');
        if (!metalSelectEl) return;
        var newMetal = metalSelectEl.value || '';
        try { if (newMetal) sessionStorage.setItem('barcode_setting_metal', newMetal); } catch (e) {}
        var lp = getCurrentLabelPresetUi();
        navigateToBarcodeSettingsPage(newMetal, lp.ui);
    }

    function onBarcodeLabelSizeChange() {
        var lp = getCurrentLabelPresetUi();
        var labelUi = lp.ui || '100x18';
        var labelSizeEl = document.getElementById('barcodeLabelSize');
        if (labelSizeEl) {
            labelUi = String(labelSizeEl.value || labelUi).trim() || labelUi;
        }
        var metalEl = document.getElementById('barcodeMetalType');
        var metal = metalEl ? (metalEl.value || '') : (currentBarcodeMetalKey || '');
        try { if (metal) sessionStorage.setItem('barcode_setting_metal', metal); } catch (e) {}
        navigateToBarcodeSettingsPage(metal, labelUi);
    }

    function normalizeToolboxSearchText(s) {
        return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    }

    function getActiveToolboxFieldsPanel() {
        var activeCat = document.querySelector('.toolbox-tab.active');
        var cat = (activeCat && activeCat.getAttribute('data-category')) || 'gold';
        /* Re-query by id so a stale Node reference cannot filter a detached panel. */
        var idMap = {
            gold: 'toolboxFieldsGold',
            silver: 'toolboxFieldsSilver',
            platinum: 'toolboxFieldsPlatinum',
            diamond: 'toolboxFieldsDiamond',
            imitation: 'toolboxFieldsImitation',
            other: 'toolboxFieldsOther'
        };
        var el = document.getElementById(idMap[cat] || idMap.gold);
        if (el) fieldsByCategory[cat] = el;
        return el || fieldsByCategory[cat] || fieldsByCategory.gold;
    }

    function filterToolboxBySearch() {
        var searchInput = document.getElementById('toolboxColumnSearch');
        var qRaw = (searchInput && searchInput.value) ? searchInput.value.trim().toLowerCase() : '';
        var qNorm = normalizeToolboxSearchText(qRaw);
        var panel = getActiveToolboxFieldsPanel();
        if (!panel) return;

        if (!panel.querySelector('.toolbox-search-empty')) {
            var emptyHint = document.createElement('div');
            emptyHint.className = 'toolbox-search-empty';
            emptyHint.textContent = 'No columns match your search.';
            panel.appendChild(emptyHint);
        }

        var items = panel.querySelectorAll('.toolbox-field-item');
        /* Pass 1: clear previous hide state so a failed/partial filter cannot leave chips stuck hidden. */
        items.forEach(function(item) {
            item.classList.remove('toolbox-search-hidden');
        });

        if (!qRaw) {
            panel.classList.remove('is-toolbox-searching', 'has-no-search-matches');
            return;
        }

        panel.classList.add('is-toolbox-searching');
        var matchCount = 0;
        items.forEach(function(item) {
            try {
                var field = (item.getAttribute('data-field') || '').toLowerCase();
                var labelEl = item.querySelector('.toolbox-field-label');
                var label = (labelEl ? labelEl.textContent : '').trim().toLowerCase();
                if (!label) {
                    /* Prefer data-field over full textContent (avoids trash SVG title noise). */
                    label = field || (item.textContent || '').trim().toLowerCase();
                }
                var extra = (item.getAttribute('data-search') || '').toLowerCase();
                var haystack = (field + ' ' + label + ' ' + extra).trim();
                var hayNorm = normalizeToolboxSearchText(haystack);
                var match = haystack.indexOf(qRaw) >= 0 || (qNorm && hayNorm.indexOf(qNorm) >= 0);
                if (match) {
                    matchCount += 1;
                    item.classList.remove('toolbox-search-hidden');
                } else {
                    item.classList.add('toolbox-search-hidden');
                }
            } catch (err) {
                /* Keep chip visible if one item errors during filter. */
                item.classList.remove('toolbox-search-hidden');
                matchCount += 1;
            }
        });
        panel.classList.toggle('has-no-search-matches', matchCount === 0);
        try { panel.scrollTop = 0; } catch (eScroll) {}
    }
    document.querySelectorAll('.toolbox-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.toolbox-tab').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var cat = this.getAttribute('data-category');
            Object.keys(fieldsByCategory).forEach(function(k) {
                if (fieldsByCategory[k]) {
                    fieldsByCategory[k].style.display = (k === cat) ? 'flex' : 'none';
                }
            });
            /* Clear search when switching metal tab — otherwise prior filter can hide every chip (e.g. Diamond tab looks empty). */
            var ts = document.getElementById('toolboxColumnSearch');
            if (ts) ts.value = '';
            bindToolboxItems();
            filterToolboxBySearch();
        });
    });
    var searchInputEl = document.getElementById('toolboxColumnSearch');
    if (searchInputEl) {
        searchInputEl.addEventListener('input', filterToolboxBySearch);
        searchInputEl.addEventListener('keyup', filterToolboxBySearch);
    }

    var _suppressQrPropChange = false;
    function getQrSize() {
        var w = parseInt(document.getElementById('propQrWidth') && document.getElementById('propQrWidth').value, 10);
        var h = parseInt(document.getElementById('propQrHeight') && document.getElementById('propQrHeight').value, 10);
        /* Min 12 so tiny 82×38 QRs (~6mm) are not forced up to 60px. */
        w = (isNaN(w) || w < 12) ? 60 : Math.min(200, w);
        h = (isNaN(h) || h < 12) ? 60 : Math.min(200, h);
        return { width: w, height: h };
    }
    function getQrSizeMmFor82x38Wrap(wrap) {
        var qr = getQrSize();
        var box = get82x38BoxElForBarcode(wrap);
        var scale = get82x38ScaleFromBox(box);
        var wMm = Math.round((qr.width / scale.x) * 10) / 10;
        var hMm = Math.round((qr.height / scale.y) * 10) / 10;
        return {
            width_mm: Math.max(4, Math.min(BOX_82X38_WIDTH_MM, wMm)),
            height_mm: Math.max(4, Math.min(BOX_82X38_HEIGHT_MM, hMm))
        };
    }
    /** Read current wrap size in mm (prefer inline style; never jump to shared propQr*). */
    function get82x38WrapSizeMm(wrap) {
        if (!wrap) return { width_mm: 16, height_mm: 7 };
        var widthMm = parseFloat(String(wrap.style.width || '').replace('mm', ''));
        var heightMm = parseFloat(String(wrap.style.height || '').replace('mm', ''));
        if (!isNaN(widthMm) && widthMm > 0 && !isNaN(heightMm) && heightMm > 0
            && String(wrap.style.width || '').indexOf('mm') >= 0) {
            return {
                width_mm: Math.max(4, Math.min(BOX_82X38_WIDTH_MM, widthMm)),
                height_mm: Math.max(3, Math.min(BOX_82X38_HEIGHT_MM, heightMm))
            };
        }
        var box = get82x38BoxElForBarcode(wrap);
        var scale = get82x38ScaleFromBox(box);
        var wPx = wrap.offsetWidth || 0;
        var hPx = wrap.offsetHeight || 0;
        if (wPx > 2 && hPx > 2 && scale.x > 0 && scale.y > 0) {
            return {
                width_mm: Math.max(4, Math.min(BOX_82X38_WIDTH_MM, Math.round((wPx / scale.x) * 10) / 10)),
                height_mm: Math.max(3, Math.min(BOX_82X38_HEIGHT_MM, Math.round((hPx / scale.y) * 10) / 10))
            };
        }
        var idx = get82x38BarcodeIndex(wrap);
        var bl = read82x38BoxLayoutFromInputs();
        return {
            width_mm: idx === 2 ? bl.box2_barcode_width_mm : bl.box1_barcode_width_mm,
            height_mm: idx === 2 ? bl.box2_barcode_height_mm : bl.box1_barcode_height_mm
        };
    }
    function get82x38GraphicDimsForBox(boxNum, bl, wrap) {
        var prefix = boxNum === 2 ? 'box2' : 'box1';
        /* Always use saved per-box mm (QR and barcode). Do not overwrite with shared propQr* —
           that made post-save reload ignore the QR size the user set on each box. */
        return {
            left: bl[prefix + '_barcode_left_mm'],
            top: bl[prefix + '_barcode_top_mm'],
            width: bl[prefix + '_barcode_width_mm'],
            height: bl[prefix + '_barcode_height_mm']
        };
    }
    /** Push QR width/height props into both 82×38 box size inputs (keeps save/reload in sync). */
    function applyQrPxPropsTo82x38BoxMmInputs(onlyBoxNum) {
        if (!is82x38TwoBoxPreset()) return;
        var wrap1 = document.getElementById('barcode1');
        var qrMm = getQrSizeMmFor82x38Wrap(wrap1 || document.getElementById('barcode2'));
        var bl = read82x38BoxLayoutFromInputs();
        function applyBox(n) {
            var left = n === 2 ? bl.box2_barcode_left_mm : bl.box1_barcode_left_mm;
            var top = n === 2 ? bl.box2_barcode_top_mm : bl.box1_barcode_top_mm;
            set82x38BarcodePropInputs(n, left, top, qrMm.width_mm, qrMm.height_mm);
        }
        if (onlyBoxNum === 1 || onlyBoxNum === 2) {
            applyBox(onlyBoxNum);
        } else {
            applyBox(1);
            applyBox(2);
        }
    }
    function syncQrPxFrom82x38WrapMm(wMm, hMm, box) {
        if (typeof currentCodeType === 'undefined' || currentCodeType !== 'qr') return;
        var scale = get82x38ScaleFromBox(box);
        var qwEl = document.getElementById('propQrWidth');
        var qhEl = document.getElementById('propQrHeight');
        _suppressQrPropChange = true;
        if (qwEl) qwEl.value = Math.max(12, Math.min(200, Math.round(wMm * scale.x)));
        if (qhEl) qhEl.value = Math.max(12, Math.min(200, Math.round(hMm * scale.y)));
        _suppressQrPropChange = false;
    }
    function updatePropertiesPanelForCodeType() {
        var isBarcode = (currentCodeType === 'barcode');
        var barRow = document.getElementById('propRowBarcodeBar');
        var barSizeRow = document.getElementById('propRowBarcodeSize');
        var barDisplayRow = document.getElementById('propRowBarcodeDisplay');
        var qrRow = document.getElementById('propRowQrSize');
        var hintBar = document.getElementById('propHintBarcode');
        var hintQr = document.getElementById('propHintQr');
        if (barRow) barRow.style.display = isBarcode ? '' : 'none';
        if (barSizeRow) barSizeRow.style.display = isBarcode ? '' : 'none';
        if (barDisplayRow) barDisplayRow.style.display = isBarcode ? '' : 'none';
        if (qrRow) qrRow.style.display = isBarcode ? 'none' : '';
        if (hintBar) hintBar.style.display = isBarcode ? '' : 'none';
        if (hintQr) hintQr.style.display = isBarcode ? 'none' : '';
        var isQr = !isBarcode;
        document.querySelectorAll('[data-82x38-label-barcode]').forEach(function(el) {
            var barcodeText = el.getAttribute('data-82x38-label-barcode') || 'Barcode';
            var suffix = el.getAttribute('data-82x38-label-suffix') || '';
            el.textContent = (isQr ? barcodeText.replace(/Barcode/g, 'QR') : barcodeText) + suffix;
        });
        document.querySelectorAll('.prop-82x38-barcode-wh').forEach(function(el) {
            el.style.display = isQr ? 'none' : '';
        });
    }
    function codeGraphicSampleForWrap(wrap) {
        if (!wrap) return '00002';
        if (wrap.id === 'barcode2' || wrap.getAttribute('data-barcode-index') === '2') {
            return String(sampleBarcodeNumber2 || '00003').trim() || '00003';
        }
        return String(sampleBarcodeNumber || '00002').trim() || '00002';
    }
    function syncCodeTypeUiControls() {
        var dpcEl = document.getElementById('defaultPrintCodeType');
        if (dpcEl && dpcEl.value !== currentCodeType) dpcEl.value = currentCodeType;
        document.querySelectorAll('.barcode-qr-toggle .toggle-option').forEach(function(o) {
            o.classList.toggle('active', o.getAttribute('data-type') === currentCodeType);
        });
    }
    function applyCodeTypeSwitch(nextType) {
        if (!nextType || nextType === currentCodeType) return;
        flushPendingCanvasPropsToDom();
        flushCheckboxDomToPersisted();
        try {
            var pl = buildBarcodeFormPayload();
            var lp = buildBarcodeLayoutPayloadObject(pl);
            lp.layout_variant = (currentCodeType === 'qr') ? 'qr' : 'barcode';
            if (currentCodeType === 'barcode') {
                persistedLayoutBarcode = JSON.stringify(lp);
            } else {
                persistedLayoutQr = JSON.stringify(lp);
            }
        } catch (e) {}
        currentCodeType = nextType;
        applyCheckboxPersistToDom();
        savedDesignLayout = (currentCodeType === 'qr') ? persistedLayoutQr : persistedLayoutBarcode;
        try {
            restoreSavedLayout();
        } catch (e2) {}
        updatePropertiesPanelForCodeType();
        if (currentCodeType === 'qr') {
            updateBarcodeQrDisplay();
            updateBarcodeResizeHandleLabels();
            if (is82x38TwoBoxPreset() && typeof render82x38PreviewPipeline === 'function') {
                render82x38PreviewPipeline({ skipBoxLayout: true });
            }
        } else {
            updateBarcodeResizeHandleLabels();
            if (is82x38TwoBoxPreset() && typeof render82x38PreviewPipeline === 'function') {
                render82x38PreviewPipeline({ skipBoxLayout: true });
            } else if (typeof renderCanvasBarcode === 'function') {
                renderCanvasBarcode();
            } else {
                updateBarcodeQrDisplay();
            }
        }
        syncCodeTypeUiControls();
    }
    document.querySelectorAll('.barcode-qr-toggle .toggle-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            applyCodeTypeSwitch(this.getAttribute('data-type'));
        });
    });
    var defaultPrintCodeTypeEl = document.getElementById('defaultPrintCodeType');
    if (defaultPrintCodeTypeEl) {
        defaultPrintCodeTypeEl.addEventListener('change', function() {
            applyCodeTypeSwitch(defaultPrintCodeTypeEl.value === 'qr' ? 'qr' : 'barcode');
        });
    }

    function hideBarcodeGraphicsInWrap(printWrap) {
        if (!printWrap) return;
        var stripes = printWrap.querySelector('.barcode-stripes');
        if (stripes) {
            stripes.style.display = 'none';
            stripes.style.visibility = 'hidden';
        }
        printWrap.querySelectorAll('svg.barcode-svg, svg.barcode-svg-box1, svg.barcode-svg-box2').forEach(function(s) {
            s.style.setProperty('display', 'none', 'important');
            s.style.setProperty('visibility', 'hidden', 'important');
            s.style.setProperty('width', '0', 'important');
            s.style.setProperty('height', '0', 'important');
        });
    }
    function showBarcodeGraphicsInWrap(printWrap) {
        if (!printWrap) return;
        var stripes = printWrap.querySelector('.barcode-stripes');
        if (stripes) {
            stripes.style.display = 'block';
            stripes.style.visibility = 'visible';
        }
        /* 82×38 uses direct-child SVG barcodes; other layouts draw into .barcode-stripes and keep placeholder SVGs hidden. */
        var showDirectSvg = printWrap.classList.contains('barcode-inner-draggable')
            || printWrap.classList.contains('barcode-82x38-barcode')
            || (typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset());
        printWrap.querySelectorAll(':scope > svg.barcode-svg, :scope > svg.barcode-svg-box1, :scope > svg.barcode-svg-box2').forEach(function(s) {
            if (showDirectSvg) {
                s.style.setProperty('display', 'block', 'important');
                s.style.setProperty('visibility', 'visible', 'important');
                s.style.setProperty('width', '100%', 'important');
                s.style.setProperty('height', '100%', 'important');
                s.style.removeProperty('max-width');
                s.style.removeProperty('max-height');
            } else {
                s.style.setProperty('display', 'none', 'important');
                s.style.setProperty('visibility', 'hidden', 'important');
            }
        });
        var qr = printWrap.querySelector('.qr-code-preview');
        if (qr) {
            qr.style.display = 'none';
            qr.style.visibility = 'hidden';
        }
    }
    function measure82x38WrapPx(printWrap) {
        var w = printWrap.offsetWidth || printWrap.clientWidth || 0;
        var h = printWrap.offsetHeight || printWrap.clientHeight || 0;
        if (w > 2 && h > 2) {
            return { width: w, height: h };
        }
        var wMm = parseFloat(String(printWrap.style.width || '').replace('mm', ''));
        var hMm = parseFloat(String(printWrap.style.height || '').replace('mm', ''));
        if (!isNaN(wMm) && wMm > 0 && !isNaN(hMm) && hMm > 0) {
            var box = (typeof get82x38BoxElForBarcode === 'function') ? get82x38BoxElForBarcode(printWrap) : null;
            var scale = (typeof get82x38ScaleFromBox === 'function') ? get82x38ScaleFromBox(box) : { x: MM_TO_PX, y: MM_TO_PX };
            return {
                width: Math.max(4, Math.round(wMm * (scale.x || MM_TO_PX))),
                height: Math.max(4, Math.round(hMm * (scale.y || MM_TO_PX)))
            };
        }
        var qrSize = getQrSize();
        return { width: qrSize.width, height: qrSize.height };
    }
    function updateBarcodeQrDisplay() {
        var qrSize = getQrSize();
        var printWraps = document.querySelectorAll('#barcode1, #barcode2, .barcode-default-inner .barcode-print-wrap');
        printWraps.forEach(function(printWrap) {
            var qrCodeEl = printWrap.querySelector('.qr-code-preview');
            var sampleCode = codeGraphicSampleForWrap(printWrap);
            if (currentCodeType === 'qr') {
                printWrap.classList.add('qr-graphic-mode');
                hideBarcodeGraphicsInWrap(printWrap);
                if (!qrCodeEl) {
                    qrCodeEl = document.createElement('div');
                    qrCodeEl.className = 'qr-code-preview';
                }
                var qrW = qrSize.width;
                var qrH = qrSize.height;
                var isInner = printWrap.classList.contains('barcode-inner-draggable');
                if (isInner) {
                    qrCodeEl.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:100%;background:#fff;display:flex;align-items:center;justify-content:center;visibility:visible;box-sizing:border-box;margin:0;padding:0;overflow:hidden;';
                    var measured = measure82x38WrapPx(printWrap);
                    qrW = measured.width;
                    qrH = measured.height;
                } else {
                    /* Standard labels (e.g. 100×18): shrink wrap to QR size.
                       Leaving barcode display-width made a wide empty gap beside the small QR. */
                    qrCodeEl.style.cssText = 'width:' + qrW + 'px;height:' + qrH + 'px;background:#fff;display:flex;align-items:center;justify-content:center;visibility:visible;box-sizing:border-box;margin:0;padding:0;';
                    printWrap.style.width = qrW + 'px';
                    printWrap.style.maxWidth = qrW + 'px';
                    printWrap.style.height = 'auto';
                    printWrap.style.minWidth = '0';
                    printWrap.setAttribute('data-display-width', String(Math.round(qrW)));
                    var stripes = printWrap.querySelector('.barcode-stripes');
                    if (stripes) {
                        stripes.style.width = '0';
                        stripes.style.maxWidth = '0';
                        stripes.style.height = '0';
                    }
                }
                var qrCanvas = qrCodeEl.querySelector('.qr-preview-canvas');
                if (!qrCanvas) {
                    qrCanvas = document.createElement('canvas');
                    qrCanvas.className = 'qr-preview-canvas';
                    qrCodeEl.appendChild(qrCanvas);
                }
                /* Fill the wrap (square QR). No hard 150px cap — that left a tiny QR in a large dashed box. */
                var drawSize = Math.max(8, Math.floor(Math.min(qrW, qrH)));
                qrCanvas.width = drawSize;
                qrCanvas.height = drawSize;
                qrCanvas.style.width = drawSize + 'px';
                qrCanvas.style.height = drawSize + 'px';
                qrCanvas.style.maxWidth = '100%';
                qrCanvas.style.maxHeight = '100%';
                if (!isInner) {
                    qrCodeEl.style.width = qrW + 'px';
                    qrCodeEl.style.height = qrH + 'px';
                }
                if (!printWrap.contains(qrCodeEl)) {
                    printWrap.insertBefore(qrCodeEl, printWrap.firstChild);
                }
                qrCodeEl.style.display = 'flex';
                qrCodeEl.style.visibility = 'visible';
                drawQRCode(qrCanvas, sampleCode);
                if (!isInner && typeof positionStandardBarcodeResizeHandle === 'function') {
                    positionStandardBarcodeResizeHandle(printWrap);
                }
            } else {
                printWrap.classList.remove('qr-graphic-mode');
                showBarcodeGraphicsInWrap(printWrap);
            }
        });
        updateBarcodeResizeHandleLabels();
    }
    updatePropertiesPanelForCodeType();
    updateBarcodeQrDisplay();

    function drawQRCode(canvas, text) {
        var ctx = canvas.getContext('2d');
        var size = canvas.width;
        var moduleCount = 21;
        var moduleSize = size / moduleCount;
        
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.fillStyle = '#000000';
        
        function drawModule(row, col) {
            ctx.fillRect(col * moduleSize, row * moduleSize, moduleSize, moduleSize);
        }
        
        function drawFinderPattern(startRow, startCol) {
            for (var r = 0; r < 7; r++) {
                for (var c = 0; c < 7; c++) {
                    if (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4)) {
                        drawModule(startRow + r, startCol + c);
                    }
                }
            }
        }
        
        drawFinderPattern(0, 0);
        drawFinderPattern(0, 14);
        drawFinderPattern(14, 0);
        
        for (var i = 8; i < 13; i++) {
            if (i % 2 === 0) {
                drawModule(6, i);
                drawModule(i, 6);
            }
        }
        
        var dataPattern = [
            [0,0,0,0,0,0,0,0,1,0,1,1,0,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,0,1,0,0,1,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,1,1,0,1,0,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,0,0,1,0,1,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,1,0,0,1,0,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,0,1,1,0,1,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,1,0,1,0,1,0,0,0,0,0,0,0,0],
            [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
            [1,0,1,0,1,0,1,0,1,1,0,1,0,1,1,0,1,0,1,1,0],
            [0,1,0,1,0,1,0,0,0,0,1,0,1,0,0,1,0,1,0,0,1],
            [1,1,0,0,1,0,1,0,1,0,0,1,0,1,0,1,1,0,1,0,1],
            [0,0,1,1,0,1,0,0,0,1,1,0,1,0,1,0,0,1,0,1,0],
            [1,0,0,0,1,0,1,0,1,0,0,1,0,1,0,1,0,0,1,0,1],
            [0,0,0,0,0,0,0,0,1,1,0,0,1,0,1,0,1,0,0,1,0],
            [0,0,0,0,0,0,0,0,0,0,1,1,0,1,0,1,0,1,1,0,1],
            [0,0,0,0,0,0,0,0,1,0,0,0,1,0,1,0,0,0,0,1,0],
            [0,0,0,0,0,0,0,0,0,1,0,1,0,1,0,1,1,0,1,0,1],
            [0,0,0,0,0,0,0,0,1,0,1,0,1,0,1,0,0,1,0,1,0],
            [0,0,0,0,0,0,0,0,0,0,0,1,0,1,0,1,0,0,1,0,1],
            [0,0,0,0,0,0,0,0,1,1,0,0,1,0,1,0,1,1,0,1,0],
            [0,0,0,0,0,0,0,0,0,0,1,0,0,1,0,1,0,0,1,0,1]
        ];
        
        for (var row = 0; row < moduleCount; row++) {
            for (var col = 0; col < moduleCount; col++) {
                if ((row < 7 && col < 7) || (row < 7 && col >= 14) || (row >= 14 && col < 7)) {
                    continue;
                }
                if (row === 6 || col === 6) {
                    continue;
                }
                if (dataPattern[row] && dataPattern[row][col] === 1) {
                    drawModule(row, col);
                }
            }
        }
    }

    var toolboxTrashSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';

    function clampPadPx(v) {
        var n = parseInt(v, 10);
        if (isNaN(n)) return 0;
        return Math.max(0, Math.min(200, n));
    }
    function loadPaddingInputsFromEl(el) {
        var ids = [['propPadTop', 'data-pad-top'], ['propPadRight', 'data-pad-right'], ['propPadBottom', 'data-pad-bottom'], ['propPadLeft', 'data-pad-left']];
        ids.forEach(function(pair) {
            var inp = document.getElementById(pair[0]);
            if (inp) inp.value = el && el.getAttribute(pair[1]) != null ? String(clampPadPx(el.getAttribute(pair[1]))) : '0';
        });
    }
    function clearPaddingInputs() {
        ['propPadTop', 'propPadRight', 'propPadBottom', 'propPadLeft'].forEach(function(id) {
            var inp = document.getElementById(id);
            if (inp) inp.value = '0';
        });
    }

    /** Copy Properties panel (prefix/suffix/font/padding) onto the selected canvas item before Save so layout JSON matches the UI. */
    function flushPendingCanvasPropsToDom() {
        if (is82x38TwoBoxPreset()) {
            sync82x38BoxPositionsFromDom();
        }
        var sel = document.querySelector('.canvas-dropped-item.selected');
        if (!sel) return;
        var propPrefix = document.getElementById('propPrefix');
        var propSuffix = document.getElementById('propSuffix');
        var propFont = document.getElementById('propFont');
        var propFontSize = document.getElementById('propFontSize');
        if (propPrefix) sel.setAttribute('data-prefix', propPrefix.value || '');
        if (propSuffix) sel.setAttribute('data-suffix', propSuffix.value || '');
        if (propFont) sel.setAttribute('data-font', propFont.value || 'Arial');
        if (propFontSize) sel.setAttribute('data-font-size', propFontSize.value || '10');
        [['propPadTop', 'data-pad-top'], ['propPadRight', 'data-pad-right'], ['propPadBottom', 'data-pad-bottom'], ['propPadLeft', 'data-pad-left']].forEach(function(pair) {
            var inp = document.getElementById(pair[0]);
            if (inp) sel.setAttribute(pair[1], String(clampPadPx(inp.value)));
        });
    }

    /** Fill Properties (prefix/suffix/font/padding) from a canvas text item — used on click and after reload restore. */
    function syncPropertiesPanelFromDroppedItem(el) {
        if (!el || !el.classList || !el.classList.contains('canvas-dropped-item')) return;
        document.querySelectorAll('.canvas-dropped-item').forEach(function(i) { i.classList.remove('selected'); });
        /* Clear barcode graphic selection so arrow keys move this column, not the bars. */
        document.querySelectorAll('.barcode-print-wrap, .barcode-inner-draggable').forEach(function(w) {
            w.classList.remove('barcode-selected');
        });
        if (typeof syncBarcodeResizeHandleVisibility === 'function') syncBarcodeResizeHandleVisibility();
        el.classList.add('selected');
        var pp = document.getElementById('propPrefix');
        var ps = document.getElementById('propSuffix');
        var pf = document.getElementById('propFont');
        var pfs = document.getElementById('propFontSize');
        if (pp) pp.value = el.getAttribute('data-prefix') || '';
        if (ps) ps.value = el.getAttribute('data-suffix') || '';
        if (pf) pf.value = el.getAttribute('data-font') || 'Arial';
        if (pfs) pfs.value = el.getAttribute('data-font-size') || '10';
        loadPaddingInputsFromEl(el);
        var fn = el.getAttribute('data-field');
        if (fn) {
            document.querySelectorAll('.toolbox-field-item').forEach(function(t) {
                t.classList.toggle('selected', t.getAttribute('data-field') === fn);
            });
        }
        if (el.classList.contains('canvas-white-strip')) {
            syncWhiteStripPropsFromEl(el);
        } else {
            setWhiteStripPropsVisibility(false);
        }
    }

    /** After layout restore, open Properties for the Barcode text field if present (matches how users tune barcode label text first). */
    function syncPropertiesPanelAfterLayoutRestore(rootEl) {
        if (!rootEl) return;
        var el = rootEl.querySelector('.canvas-dropped-item[data-field="Barcode"]')
            || rootEl.querySelector('.canvas-dropped-item');
        if (el) syncPropertiesPanelFromDroppedItem(el);
    }

    function bindToolboxItems() {
        document.querySelectorAll('.toolbox-field-item').forEach(function(item) {
            if (item._bound) return;
            item._bound = true;
            if (!item.querySelector('.field-trash')) {
                var label = item.textContent.trim();
                if (!label) {
                    label = (item.getAttribute('data-label') || item.getAttribute('data-field') || '').trim();
                }
                item.textContent = '';
                var labelSpan = document.createElement('span');
                labelSpan.className = 'toolbox-field-label';
                labelSpan.textContent = label;
                item.appendChild(labelSpan);
                var trashSpan = document.createElement('span');
                trashSpan.className = 'field-trash';
                trashSpan.title = 'Remove from label';
                trashSpan.innerHTML = toolboxTrashSvg;
                item.appendChild(trashSpan);
            }
            item.setAttribute('draggable', 'true');
            item.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('text/plain', this.getAttribute('data-field') || '');
                e.dataTransfer.effectAllowed = 'copy';
                this.classList.add('dragging');
            });
            item.addEventListener('dragend', function() {
                this.classList.remove('dragging');
            });
            item.addEventListener('click', function(e) {
                if (e.target.closest('.field-trash')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var fieldName = this.getAttribute('data-field');
                    var lp1 = document.getElementById('labelPreview1');
                    var lp2 = document.getElementById('labelPreview2');
                    var stripHost = get82x38StripHost();
                    var halfStripsHost = document.getElementById('barcode82x38FixedStrips');
                    [lp1, lp2, stripHost, halfStripsHost].forEach(function(cont) {
                        if (!cont) return;
                        cont.querySelectorAll('.canvas-dropped-item[data-field="' + fieldName + '"]').forEach(function(el) {
                            el.remove();
                        });
                    });
                    if (typeof syncToolboxHighlight === 'function') syncToolboxHighlight();
                    return;
                }
                document.querySelectorAll('.toolbox-field-item').forEach(function(i) { i.classList.remove('selected'); });
                this.classList.add('selected');
                document.querySelectorAll('.canvas-dropped-item').forEach(function(i) { i.classList.remove('selected'); });
                var propPrefix = document.getElementById('propPrefix');
                var propSuffix = document.getElementById('propSuffix');
                if (propPrefix) propPrefix.value = '';
                if (propSuffix) propSuffix.value = '';
                clearPaddingInputs();
                setWhiteStripPropsVisibility(false);
                /* Click StripLine → place on 82×38 outer sticker (where red marks are) */
                var clickField = this.getAttribute('data-field') || '';
                if (isStripLineField(clickField) && typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset()) {
                    addStripLineTo82x38Sticker({});
                }
                if (isWhiteStripField(clickField)) {
                    var activeBox = document.querySelector('#box2.barcode-selected, .barcode-preview-block.barcode-selected');
                    var boxNumClick = (activeBox && activeBox.id === 'box2') ? 2 : 1;
                    var wsHost = getWhiteStripPrintCanvas(boxNumClick);
                    if (wsHost) {
                        addWhiteStripToTarget(wsHost, 0, 0, { center: true, box: boxNumClick });
                    }
                }
                /* 82×38: click a column chip to place it on the top half white strip (drag still works too). */
                if (clickField && !isStripLineField(clickField) && !isWhiteStripField(clickField)
                    && typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset()) {
                    ensure82x38FixedHalfWhiteStrips();
                    var clickStrip = document.querySelector('.barcode-82x38-fixed-half-strip--top-left')
                        || document.querySelector('.barcode-82x38-fixed-half-strip');
                    if (clickStrip) {
                        var existingClick = clickStrip.querySelector('.canvas-dropped-item[data-field="' + clickField + '"]');
                        if (!existingClick) {
                            var placed = createDroppedItem(clickField, 12, 0, clickStrip, {
                                font_size: (document.getElementById('propFontSize') && document.getElementById('propFontSize').value) || '8'
                            });
                            if (placed) {
                                placed.setAttribute('data-strip-slot', clickStrip.getAttribute('data-fixed-half-strip') || 'top-left');
                                placed.style.top = '0px';
                                clampDroppedItemInHalfStrip(placed, clickStrip);
                                syncPropertiesPanelFromDroppedItem(placed);
                            }
                        } else {
                            syncPropertiesPanelFromDroppedItem(existingClick);
                        }
                        if (typeof syncToolboxHighlight === 'function') syncToolboxHighlight();
                    }
                }
            });
        });
    }
    bindToolboxItems();

    // Canvas drag and drop
    var canvas = document.getElementById('barcodeCanvas');
    var dropLayer = document.getElementById('barcodeCanvasDrops');
    var dropIdCounter = 0;

    var labelPreview1 = document.getElementById('labelPreview1');
    var labelCanvas1 = document.getElementById('labelCanvas1');
    var labelCanvas2 = document.getElementById('labelCanvas2');
    var whiteDropZone = document.getElementById('barcodeWhiteDropZone');
    var barcodeBox = document.querySelector('.barcode-default-inner');
    var barcodeStripesEl = document.querySelector('.barcode-default-inner .barcode-stripes');
    var labelSizeSelect = document.getElementById('barcodeLabelSize');
    var MM_TO_PX = 96 / 25.4;
    var MAX_DISPLAY_WIDTH_MM = 400;

    function barcodeLabelPreset() {
        return labelSizeSelect ? (labelSizeSelect.value || '').trim() : '';
    }
    /** Dual-tag butterfly stickers: two barcodes per physical label. */
    var DUAL_LABEL_SIZES = ['120x50', '250x120'];
    var STICKER_82X38_PRESET = '82x38_2box';
    var STICKER_82X38_WIDTH_MM = 82;
    var STICKER_82X38_HEIGHT_MM = 38;
    var BOX_82X38_WIDTH_MM = 20;
    var BOX_82X38_HEIGHT_MM = 25;
    var BOX_82X38_BOX1_LEFT_MM = 0;
    var BOX_82X38_BOX1_TOP_MM = STICKER_82X38_HEIGHT_MM - BOX_82X38_HEIGHT_MM;
    var BOX_82X38_BOX2_LEFT_MM = STICKER_82X38_WIDTH_MM - BOX_82X38_WIDTH_MM;
    var BOX_82X38_BOX2_TOP_MM = 2;
    var BOX_82X38_BARCODE_MIN_WIDTH_MM = 4;
    var BOX_82X38_BARCODE_MIN_HEIGHT_MM = 5;
    var BOX_82X38_BARCODE_GRAPHIC_MIN_MM = 3;
    var _82x38ResizeRedrawRaf = null;
    function is82x38BarcodeTextVisible() {
        var chk = document.getElementById('barcodeShowBarcodeNo');
        return chk ? chk.checked : false;
    }
    function get82x38BarcodeTextFontPt() {
        var el = document.getElementById('propBoxBarcodeNoFontSize');
        var pt = el ? parseFloat(el.value) : NaN;
        if (isNaN(pt) || pt <= 0) pt = 7;
        return Math.max(5, Math.min(24, pt));
    }
    /** Reserved mm at bottom of barcode container for human-readable value text. */
    function get82x38BarcodeTextAreaMm(scaleY) {
        if (!is82x38BarcodeTextVisible()) return 0;
        var marginEl = document.getElementById('propBoxBarcodeNoMarginTopMm');
        var marginMm = marginEl ? parseFloat(marginEl.value) : NaN;
        if (isNaN(marginMm) || marginMm < 0) marginMm = 1;
        var fontMm = get82x38BarcodeTextFontPt() * 0.352778;
        return Math.round((Math.max(1.2, fontMm) + Math.min(3, marginMm)) * 10) / 10;
    }
    function get82x38MaxContainerHeightMm(topMm) {
        topMm = (isNaN(topMm)) ? 0 : topMm;
        var maxH = Math.max(0, BOX_82X38_HEIGHT_MM - topMm);
        var halfLine = BOX_82X38_HEIGHT_MM / 2;
        if (topMm < halfLine - 0.05) {
            maxH = Math.min(maxH, Math.max(0, halfLine - topMm));
        }
        return Math.round(maxH * 10) / 10;
    }
    function ensure82x38BarcodeGraphicWrap(wrap) {
        if (!wrap) return null;
        var graphic = wrap.querySelector('.barcode-82x38-graphic');
        if (!graphic) {
            graphic = document.createElement('div');
            graphic.className = 'barcode-82x38-graphic';
            var svg = wrap.querySelector('svg.barcode-svg-box1, svg.barcode-svg-box2, svg');
            wrap.insertBefore(graphic, wrap.firstChild);
            if (svg) graphic.appendChild(svg);
        }
        return graphic;
    }
    function get82x38FixedBoxSizeMm() {
        return { width_mm: BOX_82X38_WIDTH_MM, height_mm: BOX_82X38_HEIGHT_MM };
    }
    function get82x38FixedBoxPositionsMm() {
        var b1TopEl = document.getElementById('propBox1TopMm');
        var b2TopEl = document.getElementById('propBox2TopMm');
        var b1Top = b1TopEl ? parseFloat(b1TopEl.value) : NaN;
        var b2Top = b2TopEl ? parseFloat(b2TopEl.value) : NaN;
        if (isNaN(b1Top)) b1Top = BOX_82X38_BOX1_TOP_MM;
        if (isNaN(b2Top)) b2Top = BOX_82X38_BOX2_TOP_MM;
        b1Top = Math.max(0, Math.min(STICKER_82X38_HEIGHT_MM - BOX_82X38_HEIGHT_MM, b1Top));
        b2Top = Math.max(0, Math.min(STICKER_82X38_HEIGHT_MM - BOX_82X38_HEIGHT_MM, b2Top));
        return {
            box1_left_mm: BOX_82X38_BOX1_LEFT_MM,
            box1_top_mm: Math.round(b1Top * 10) / 10,
            box2_left_mm: BOX_82X38_BOX2_LEFT_MM,
            box2_top_mm: Math.round(b2Top * 10) / 10
        };
    }
    function force82x38BoxSizeInputs() {
        var wEl = document.getElementById('propBoxWidthMm');
        var hEl = document.getElementById('propBoxHeightMm');
        if (wEl) wEl.value = String(BOX_82X38_WIDTH_MM);
        if (hEl) hEl.value = String(BOX_82X38_HEIGHT_MM);
    }
    function force82x38FixedBoxPositions() {
        if (!is82x38TwoBoxPreset()) return;
        force82x38BoxSizeInputs();
        var fixed = get82x38FixedBoxPositionsMm();
        function set(id, val) {
            var el = document.getElementById(id);
            if (el) el.value = String(val);
        }
        /* Left edges stay fixed; tops come from user inputs (gap from top). */
        set('propBox1LeftMm', fixed.box1_left_mm);
        set('propBox2LeftMm', fixed.box2_left_mm);
        set('propBox1TopMm', fixed.box1_top_mm);
        set('propBox2TopMm', fixed.box2_top_mm);
    }
    var DUAL_TAG_GAP_MM = 2;

    function is82x38TwoBoxPreset(val) {
        val = val || barcodeLabelPreset();
        return val === STICKER_82X38_PRESET;
    }
    /** Dual editable canvases: jewelry tags (120×50, 250×120) and 82×38 two-box sticker. */
    function isDualCanvasLayoutPreset(val) {
        val = val || barcodeLabelPreset();
        return isDualLabelLayoutPreset(val) || is82x38TwoBoxPreset(val);
    }
    function read82x38BoxLayoutFromInputs() {
        force82x38FixedBoxPositions();
        function num(id, fallback) {
            var el = document.getElementById(id);
            var n = el ? parseFloat(el.value) : NaN;
            return (isNaN(n)) ? fallback : n;
        }
        var stickerW = STICKER_82X38_WIDTH_MM;
        var boxW = BOX_82X38_WIDTH_MM;
        var boxH = BOX_82X38_HEIGHT_MM;
        var fixed = get82x38FixedBoxPositionsMm();
        var box2Left = fixed.box2_left_mm;
        var box2Right = Math.max(0, Math.round((stickerW - box2Left - boxW) * 10) / 10);
        var out = {
            box1_left_mm: fixed.box1_left_mm,
            box1_top_mm: fixed.box1_top_mm,
            box2_left_mm: box2Left,
            box2_right_mm: box2Right,
            box2_top_mm: fixed.box2_top_mm,
            box_width_mm: boxW,
            box_height_mm: boxH,
            box1_barcode_width_mm: num('propBox1BarcodeWidthMm', 18),
            box1_barcode_height_mm: num('propBox1BarcodeHeightMm', 7),
            box1_barcode_left_mm: num('propBox1BarcodeLeftMm', 2),
            box1_barcode_top_mm: num('propBox1BarcodeTopMm', 3),
            box2_barcode_width_mm: num('propBox2BarcodeWidthMm', 18),
            box2_barcode_height_mm: num('propBox2BarcodeHeightMm', 7),
            box2_barcode_left_mm: num('propBox2BarcodeLeftMm', 2),
            box2_barcode_top_mm: num('propBox2BarcodeTopMm', 3),
            box_barcode_width_mm: num('propBox1BarcodeWidthMm', 18),
            box_barcode_height_mm: num('propBox1BarcodeHeightMm', 7),
            barcode_width_mm: num('propBox1BarcodeWidthMm', 18),
            barcode_height_mm: num('propBox1BarcodeHeightMm', 7),
            barcode_left_mm: num('propBox1BarcodeLeftMm', 2),
            barcode_top_mm: num('propBox1BarcodeTopMm', 3),
            barcode_no_margin_top_mm: num('propBoxBarcodeNoMarginTopMm', 1),
            barcode_no_font_size: num('propBoxBarcodeNoFontSize', 7),
            sticker_print_offset_top_mm: num('propStickerPrintOffsetTopMm', 0),
            sticker_print_offset_left_mm: num('propStickerPrintOffsetLeftMm', 0)
        };
        /* Repair off-canvas QR/barcode coords (e.g. left_mm:-208 from hidden-SVG measure). */
        if (typeof clamp82x38BarcodeGraphicMm === 'function') {
            var c1 = clamp82x38BarcodeGraphicMm(out.box1_barcode_left_mm, out.box1_barcode_top_mm, out.box1_barcode_width_mm, out.box1_barcode_height_mm);
            var c2 = clamp82x38BarcodeGraphicMm(out.box2_barcode_left_mm, out.box2_barcode_top_mm, out.box2_barcode_width_mm, out.box2_barcode_height_mm);
            out.box1_barcode_left_mm = c1.left_mm;
            out.box1_barcode_top_mm = c1.top_mm;
            out.box1_barcode_width_mm = c1.width_mm;
            out.box1_barcode_height_mm = c1.height_mm;
            out.box2_barcode_left_mm = c2.left_mm;
            out.box2_barcode_top_mm = c2.top_mm;
            out.box2_barcode_width_mm = c2.width_mm;
            out.box2_barcode_height_mm = c2.height_mm;
            out.box_barcode_width_mm = c1.width_mm;
            out.box_barcode_height_mm = c1.height_mm;
            out.barcode_width_mm = c1.width_mm;
            out.barcode_height_mm = c1.height_mm;
            out.barcode_left_mm = c1.left_mm;
            out.barcode_top_mm = c1.top_mm;
            if (typeof set82x38BarcodePropInputs === 'function') {
                function needsRepair(id, val) {
                    var el = document.getElementById(id);
                    if (!el) return false;
                    var cur = parseFloat(el.value);
                    return isNaN(cur) || Math.abs(cur - val) > 0.05;
                }
                if (needsRepair('propBox1BarcodeLeftMm', c1.left_mm) || needsRepair('propBox1BarcodeTopMm', c1.top_mm)
                    || needsRepair('propBox1BarcodeWidthMm', c1.width_mm) || needsRepair('propBox1BarcodeHeightMm', c1.height_mm)) {
                    set82x38BarcodePropInputs(1, c1.left_mm, c1.top_mm, c1.width_mm, c1.height_mm);
                }
                if (needsRepair('propBox2BarcodeLeftMm', c2.left_mm) || needsRepair('propBox2BarcodeTopMm', c2.top_mm)
                    || needsRepair('propBox2BarcodeWidthMm', c2.width_mm) || needsRepair('propBox2BarcodeHeightMm', c2.height_mm)) {
                    set82x38BarcodePropInputs(2, c2.left_mm, c2.top_mm, c2.width_mm, c2.height_mm);
                }
            }
        }
        return out;
    }
    function write82x38BoxLayoutToInputs(layout) {
        function set(id, val) {
            var el = document.getElementById(id);
            if (el && val != null && !isNaN(parseFloat(val))) {
                el.value = Math.round(parseFloat(val) * 10) / 10;
            }
        }
        if (!layout) return;
        force82x38BoxSizeInputs();
        set('propBox1LeftMm', BOX_82X38_BOX1_LEFT_MM);
        set('propBox2LeftMm', BOX_82X38_BOX2_LEFT_MM);
        var b1Top = layout.box1_top_mm != null ? layout.box1_top_mm : (layout.box1 && layout.box1.top_mm != null ? layout.box1.top_mm : (layout.box1 && layout.box1.top != null ? layout.box1.top : BOX_82X38_BOX1_TOP_MM));
        var b2Top = layout.box2_top_mm != null ? layout.box2_top_mm : (layout.box2 && layout.box2.top_mm != null ? layout.box2.top_mm : (layout.box2 && layout.box2.top != null ? layout.box2.top : BOX_82X38_BOX2_TOP_MM));
        set('propBox1TopMm', b1Top);
        set('propBox2TopMm', b2Top);
        set('propBox1BarcodeWidthMm', layout.box1_barcode_width_mm || layout.box_barcode_width_mm || layout.barcode_width_mm);
        set('propBox1BarcodeHeightMm', layout.box1_barcode_height_mm || layout.box_barcode_height_mm || layout.barcode_height_mm);
        set('propBox2BarcodeWidthMm', layout.box2_barcode_width_mm || layout.box_barcode_width_mm || layout.barcode_width_mm);
        set('propBox2BarcodeHeightMm', layout.box2_barcode_height_mm || layout.box_barcode_height_mm || layout.barcode_height_mm);
        set('propBox1BarcodeLeftMm', layout.box1_barcode_left_mm != null ? layout.box1_barcode_left_mm : layout.barcode_left_mm);
        set('propBox1BarcodeTopMm', layout.box1_barcode_top_mm != null ? layout.box1_barcode_top_mm : layout.barcode_top_mm);
        set('propBox2BarcodeLeftMm', layout.box2_barcode_left_mm != null ? layout.box2_barcode_left_mm : layout.barcode_left_mm);
        set('propBox2BarcodeTopMm', layout.box2_barcode_top_mm != null ? layout.box2_barcode_top_mm : layout.barcode_top_mm);
        set('propBoxBarcodeNoMarginTopMm', layout.barcode_no_margin_top_mm);
        set('propBoxBarcodeNoFontSize', layout.barcode_no_font_size);
        set('propStickerPrintOffsetTopMm', layout.sticker_print_offset_top_mm != null ? layout.sticker_print_offset_top_mm : 0);
        set('propStickerPrintOffsetLeftMm', layout.sticker_print_offset_left_mm != null ? layout.sticker_print_offset_left_mm : 0);
    }
    function toggle82x38BoxPropRow(show) {
        var row = document.getElementById('propRow82x38Box');
        if (row) row.style.display = show ? 'block' : 'none';
        if (show) force82x38BoxSizeInputs();
    }
    function get82x38OuterStickerEl() {
        return document.getElementById('barcodeDualStickerShell');
    }
    function get82x38OuterRect() {
        var outer = get82x38OuterStickerEl();
        return outer ? outer.getBoundingClientRect() : null;
    }
    function get82x38ScaleFromOuter(outerRect) {
        if (!outerRect || outerRect.width <= 0 || outerRect.height <= 0) {
            return { x: MM_TO_PX, y: MM_TO_PX };
        }
        return { x: outerRect.width / 82, y: outerRect.height / 38 };
    }
    function get82x38BoxElForBarcode(wrap) {
        if (!wrap) return null;
        if (wrap.id === 'barcode2') return document.getElementById('box2');
        return document.getElementById('box1');
    }
    function get82x38JsBarcodeOpts(heightPx, modW) {
        var w = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
        var h = heightPx;
        if (h == null || isNaN(h) || h < 8) {
            h = parseInt(document.getElementById('propBarcodeBarHeight') && document.getElementById('propBarcodeBarHeight').value, 10);
        }
        if (isNaN(h) || h < 8) h = 30;
        return {
            format: 'CODE128',
            width: (modW != null && !isNaN(modW)) ? Math.max(2, Math.min(10, modW)) : ((isNaN(w) || w < 1) ? 2 : Math.min(10, w)),
            height: Math.max(8, Math.min(200, h)),
            displayValue: false,
            margin: 0,
            marginTop: 0,
            marginBottom: 0,
            marginLeft: 0,
            marginRight: 0,
            background: '#ffffff',
            lineColor: '#000000'
        };
    }
    /** Draw CODE128 at full stripe height inside mm box (avoid width:100% + meet crushing bar height). */
    function fit82x38JsBarcodeToPxBox(svgEl, boxWPx, boxHPx, code, baseModW) {
        if (!svgEl || !code || boxWPx <= 0 || boxHPx <= 0 || typeof JsBarcode === 'undefined') return;
        baseModW = Math.max(2, baseModW || 2);
        /* Per-box px height drives stripe height — ignore global propBarcodeBarHeight (standard labels only). */
        var targetBarH = Math.max(8, Math.round(boxHPx));
        function draw(modW) {
            svgEl.innerHTML = '';
            JsBarcode(svgEl, code, get82x38JsBarcodeOpts(targetBarH, modW));
        }
        draw(baseModW);
        var natW = 0;
        var natH = 0;
        try {
            var bb = svgEl.getBBox();
            natW = bb.width;
            natH = bb.height;
        } catch (e) {}
        if (natW > 0 && natW < boxWPx * 0.85) {
            var modW2 = baseModW * (boxWPx / natW);
            modW2 = Math.max(2, Math.min(10, modW2));
            draw(modW2);
            try {
                natW = svgEl.getBBox().width;
                natH = svgEl.getBBox().height;
            } catch (e2) {}
        } else if (natW > boxWPx * 1.05) {
            var modW3 = baseModW * (boxWPx / natW);
            modW3 = Math.max(2, Math.min(10, modW3));
            draw(modW3);
            try {
                natW = svgEl.getBBox().width;
                natH = svgEl.getBBox().height;
            } catch (e3) {}
        }
        natW = Math.ceil(natW > 0 ? natW : boxWPx);
        natH = Math.ceil(natH > 0 ? natH : targetBarH);
        svgEl.setAttribute('preserveAspectRatio', 'none');
        svgEl.style.setProperty('width', '100%', 'important');
        svgEl.style.setProperty('height', '100%', 'important');
        svgEl.style.setProperty('max-width', '100%', 'important');
        svgEl.style.setProperty('max-height', '100%', 'important');
        svgEl.style.setProperty('display', 'block', 'important');
        svgEl.style.margin = '0';
        svgEl.style.padding = '0';
        svgEl.style.transform = 'none';
    }
    /** Split container px into graphic + text; style inner stack; redraw JsBarcode into graphic area only. */
    function layout82x38BarcodeInnerContent(wrap, opts) {
        opts = opts || {};
        if (!wrap) return;
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') return;
        var box = get82x38BoxElForBarcode(wrap);
        var scale = get82x38ScaleFromBox(box);
        var totalWPx = opts.wPx > 0 ? opts.wPx : wrap.offsetWidth;
        var totalHPx = opts.hPx > 0 ? opts.hPx : wrap.offsetHeight;
        if (totalWPx <= 0 || totalHPx <= 0) return;
        ensure82x38BarcodeGraphicWrap(wrap);
        var graphic = wrap.querySelector('.barcode-82x38-graphic');
        var textEl = wrap.querySelector('.barcode-text');
        var svg = graphic ? graphic.querySelector('svg.barcode-svg-box1, svg.barcode-svg-box2, svg') : wrap.querySelector('svg');
        var showText = is82x38BarcodeTextVisible();
        var textPx = 0;
        if (textEl) {
            textEl.style.fontSize = get82x38BarcodeTextFontPt() + 'pt';
            textEl.style.textAlign = 'center';
            textEl.style.width = '100%';
            textEl.style.margin = '0';
            textEl.style.padding = '0';
            var marginMm = parseFloat(document.getElementById('propBoxBarcodeNoMarginTopMm') && document.getElementById('propBoxBarcodeNoMarginTopMm').value);
            if (isNaN(marginMm) || marginMm < 0) marginMm = 1;
            textEl.style.marginTop = showText ? (marginMm * scale.y) + 'px' : '0';
            textEl.style.display = showText ? 'block' : 'none';
            if (showText) {
                textPx = Math.max(Math.ceil(textEl.offsetHeight || 0), Math.ceil(get82x38BarcodeTextAreaMm(scale.y) * scale.y));
            }
        }
        var minGraphicPx = Math.max(8, Math.ceil(BOX_82X38_BARCODE_GRAPHIC_MIN_MM * scale.y));
        var graphicHPx = Math.max(minGraphicPx, totalHPx - textPx);
        if (graphic) {
            graphic.style.flex = '0 0 auto';
            graphic.style.minHeight = '0';
            graphic.style.width = '100%';
            graphic.style.height = graphicHPx + 'px';
            graphic.style.maxHeight = graphicHPx + 'px';
            graphic.style.overflow = 'hidden';
            graphic.style.lineHeight = '0';
            graphic.style.display = 'block';
        }
        if (svg && !opts.skipRedraw && typeof JsBarcode !== 'undefined') {
            var code = codeGraphicSampleForWrap(wrap);
            var baseModW = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
            if (isNaN(baseModW) || baseModW < 1) baseModW = 2;
            fit82x38JsBarcodeToPxBox(svg, totalWPx, graphicHPx, code, baseModW);
        }
    }
    function schedule82x38BarcodeResizeRedraw(wrap, wPx, hPx) {
        if (!wrap) return;
        if (_82x38ResizeRedrawRaf) cancelAnimationFrame(_82x38ResizeRedrawRaf);
        _82x38ResizeRedrawRaf = requestAnimationFrame(function() {
            _82x38ResizeRedrawRaf = null;
            layout82x38BarcodeInnerContent(wrap, { wPx: wPx, hPx: hPx });
        });
    }
    /** Re-draw CODE128 inside one 82×38 draggable wrap at the given container pixel size. */
    function redraw82x38BarcodeWrapGraphic(wrap, wPx, hPx) {
        if (!wrap || wPx <= 0 || hPx <= 0) return;
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') return;
        layout82x38BarcodeInnerContent(wrap, { wPx: wPx, hPx: hPx });
    }
    function log82x38AppliedBarcodes() {
        if (!is82x38TwoBoxPreset()) return;
        var bl = read82x38BoxLayoutFromInputs();
        var barcode1 = {
            left_mm: bl.box1_barcode_left_mm,
            top_mm: bl.box1_barcode_top_mm,
            width_mm: bl.box1_barcode_width_mm,
            height_mm: bl.box1_barcode_height_mm
        };
        var barcode2 = {
            left_mm: bl.box2_barcode_left_mm,
            top_mm: bl.box2_barcode_top_mm,
            width_mm: bl.box2_barcode_width_mm,
            height_mm: bl.box2_barcode_height_mm
        };
        console.log('82x38 applied barcode1', barcode1);
        console.log('82x38 applied barcode2', barcode2);
    }
    function apply82x38BarcodeLayoutMm(wrap, leftMm, topMm, widthMm, heightMm) {
        if (!wrap) return;
        leftMm = Math.round(parseFloat(leftMm) * 10) / 10;
        topMm = Math.round(parseFloat(topMm) * 10) / 10;
        widthMm = Math.round(parseFloat(widthMm) * 10) / 10;
        heightMm = Math.round(parseFloat(heightMm) * 10) / 10;
        wrap.style.setProperty('--saved-left-mm', leftMm + 'mm');
        wrap.style.setProperty('--saved-top-mm', topMm + 'mm');
        wrap.style.setProperty('--saved-width-mm', widthMm + 'mm');
        wrap.style.setProperty('--saved-height-mm', heightMm + 'mm');
        wrap.style.position = 'absolute';
        wrap.style.margin = '0';
        wrap.style.padding = '0';
        wrap.style.display = 'block';
        wrap.style.left = leftMm + 'mm';
        wrap.style.top = topMm + 'mm';
        wrap.style.width = widthMm + 'mm';
        wrap.style.height = heightMm + 'mm';
        wrap.style.right = 'auto';
        wrap.style.bottom = 'auto';
        wrap.style.transform = 'none';
        wrap.style.lineHeight = 'normal';
        wrap.style.overflow = 'hidden';
        wrap.style.boxSizing = 'border-box';
        wrap.style.pointerEvents = 'auto';
        wrap.style.cursor = 'move';
        wrap.style.display = 'flex';
        wrap.style.flexDirection = 'column';
        wrap.style.alignItems = 'center';
        wrap.classList.add('barcode-82x38-barcode', 'barcode-inner-draggable');
        wrap.classList.toggle('qr-graphic-mode', typeof currentCodeType !== 'undefined' && currentCodeType === 'qr');
        ensure82x38BarcodeGraphicWrap(wrap);
        var svg = wrap.querySelector('.barcode-82x38-graphic svg, svg.barcode-svg-box1, svg.barcode-svg-box2, svg');
        if (svg) {
            if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                svg.style.setProperty('display', 'none', 'important');
                svg.style.setProperty('width', '0', 'important');
                svg.style.setProperty('height', '0', 'important');
            } else {
                svg.style.setProperty('display', 'block', 'important');
                svg.style.setProperty('visibility', 'visible', 'important');
                svg.style.removeProperty('width');
                svg.style.removeProperty('height');
                svg.style.maxWidth = '100%';
                svg.style.maxHeight = '100%';
                svg.style.margin = '0';
                svg.style.padding = '0';
                svg.removeAttribute('height');
            }
        }
    }
    function toggle82x38BarcodeTextVisibility() {
        var chk = document.getElementById('barcodeShowBarcodeNo');
        var show = chk ? chk.checked : false;
        document.querySelectorAll('#barcode1 .barcode-text, #barcode2 .barcode-text').forEach(function(el) {
            el.style.display = show ? 'block' : 'none';
            el.style.margin = '0';
            el.style.padding = '0';
        });
        if (is82x38TwoBoxPreset()) {
            [document.getElementById('barcode1'), document.getElementById('barcode2')].forEach(function(wrap) {
                if (wrap) layout82x38BarcodeInnerContent(wrap);
            });
        }
    }
    /** Keep barcode container mm inside the fixed 20×25 mm print box (includes text band when visible). */
    function clamp82x38BarcodeGraphicMm(leftMm, topMm, widthMm, heightMm) {
        var textAreaMm = get82x38BarcodeTextAreaMm();
        var minH = BOX_82X38_BARCODE_GRAPHIC_MIN_MM + textAreaMm;
        var w = (!isNaN(widthMm) && widthMm > 0) ? Math.max(BOX_82X38_BARCODE_MIN_WIDTH_MM, Math.min(BOX_82X38_WIDTH_MM, widthMm)) : 18;
        var h = (!isNaN(heightMm) && heightMm > 0) ? Math.max(minH, Math.min(BOX_82X38_HEIGHT_MM, heightMm)) : (minH + 2);
        var left = (!isNaN(leftMm)) ? leftMm : 2;
        var top = (!isNaN(topMm)) ? topMm : 3;
        /* Off-canvas leftovers from QR-mode SVG measurement bugs → reset to defaults. */
        if (left < -0.5 || left > BOX_82X38_WIDTH_MM || top < -0.5 || top > BOX_82X38_HEIGHT_MM) {
            left = 2;
            top = 3;
        }
        left = Math.max(0, Math.min(BOX_82X38_WIDTH_MM - w, left));
        var maxH = get82x38MaxContainerHeightMm(top);
        h = Math.max(minH, Math.min(maxH, h));
        top = Math.max(0, Math.min(BOX_82X38_HEIGHT_MM - h, top));
        return {
            left_mm: Math.round(left * 10) / 10,
            top_mm: Math.round(top * 10) / 10,
            width_mm: Math.round(w * 10) / 10,
            height_mm: Math.round(h * 10) / 10
        };
    }
    /**
     * QR saved at top≈0 prints flush to the box edge (right ear looks stuck to the top).
     * Vertically center flush-top QR so designer matches print.
     */
    function center82x38QrIfFlushTop(pos) {
        if (!pos) return pos;
        if (typeof currentCodeType === 'undefined' || currentCodeType !== 'qr') return pos;
        if (pos.top_mm > 0.15) return pos;
        var h = pos.height_mm > 0 ? pos.height_mm : 7;
        pos.top_mm = Math.round(((BOX_82X38_HEIGHT_MM - h) / 2) * 10) / 10;
        return pos;
    }
    function mmFrom82x38BarcodeInBox(wrap) {
        var box = get82x38BoxElForBarcode(wrap);
        if (!box || !wrap) return null;
        var leftMm = parseFloat(String(wrap.style.left || '').replace('mm', ''));
        var topMm = parseFloat(String(wrap.style.top || '').replace('mm', ''));
        var widthMm = parseFloat(String(wrap.style.width || '').replace('mm', ''));
        var heightMm = parseFloat(String(wrap.style.height || '').replace('mm', ''));
        if (!isNaN(leftMm) && !isNaN(topMm) && String(wrap.style.left || '').indexOf('mm') >= 0) {
            return clamp82x38BarcodeGraphicMm(
                leftMm,
                topMm,
                (!isNaN(widthMm) && widthMm > 0) ? widthMm : null,
                (!isNaN(heightMm) && heightMm > 0) ? heightMm : null
            );
        }
        var boxRect = box.getBoundingClientRect();
        if (boxRect.width <= 0 || boxRect.height <= 0) return null;
        /* Never measure hidden SVG in QR mode (getBoundingClientRect → 0,0 → huge negative mm). */
        var measureEl = wrap;
        if (wrap.classList.contains('qr-graphic-mode')) {
            var qrEl = wrap.querySelector('.qr-code-preview');
            if (qrEl && qrEl.offsetWidth > 2 && qrEl.offsetHeight > 2) measureEl = qrEl;
        }
        var barcodeRect = measureEl.getBoundingClientRect();
        if (barcodeRect.width < 2 || barcodeRect.height < 2) {
            barcodeRect = wrap.getBoundingClientRect();
        }
        if (barcodeRect.width < 2 || barcodeRect.height < 2) return null;
        var scaleX = boxRect.width / BOX_82X38_WIDTH_MM;
        var scaleY = boxRect.height / BOX_82X38_HEIGHT_MM;
        if (!(scaleX > 0) || !(scaleY > 0)) return null;
        var leftPx = barcodeRect.left - boxRect.left;
        var topPx = barcodeRect.top - boxRect.top;
        return clamp82x38BarcodeGraphicMm(
            leftPx / scaleX,
            topPx / scaleY,
            barcodeRect.width / scaleX,
            barcodeRect.height / scaleY
        );
    }
    function sync82x38BarcodePositionsFromDom(opts) {
        if (!is82x38TwoBoxPreset()) return;
        opts = opts || {};
        var syncSize = !!opts.syncSize;
        function syncOne(wrap, leftId, topId, wId, hId) {
            var pos = mmFrom82x38BarcodeInBox(wrap);
            if (!pos) return;
            var leftEl = document.getElementById(leftId);
            var topEl = document.getElementById(topId);
            if (leftEl) leftEl.value = pos.left_mm;
            if (topEl) topEl.value = pos.top_mm;
            if (syncSize && wId && pos.width_mm > 0) {
                var wEl = document.getElementById(wId);
                if (wEl) wEl.value = pos.width_mm;
            }
            if (syncSize && hId && pos.height_mm > 0) {
                var hEl = document.getElementById(hId);
                if (hEl) hEl.value = pos.height_mm;
            }
        }
        syncOne(document.getElementById('barcode1'), 'propBox1BarcodeLeftMm', 'propBox1BarcodeTopMm', 'propBox1BarcodeWidthMm', 'propBox1BarcodeHeightMm');
        syncOne(document.getElementById('barcode2'), 'propBox2BarcodeLeftMm', 'propBox2BarcodeTopMm', 'propBox2BarcodeWidthMm', 'propBox2BarcodeHeightMm');
    }
    function reapply82x38BarcodePositionsFromInputs() {
        if (!is82x38TwoBoxPreset()) return;
        var bl = read82x38BoxLayoutFromInputs();
        var wrap1 = document.getElementById('barcode1');
        var wrap2 = document.getElementById('barcode2');
        var d1 = get82x38GraphicDimsForBox(1, bl, wrap1);
        var d2 = get82x38GraphicDimsForBox(2, bl, wrap2);
        apply82x38BarcodeLayoutMm(wrap1, d1.left, d1.top, d1.width, d1.height);
        apply82x38BarcodeLayoutMm(wrap2, d2.left, d2.top, d2.width, d2.height);
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            updateBarcodeQrDisplay();
        } else {
            [wrap1, wrap2].forEach(function(w) {
                if (w) layout82x38BarcodeInnerContent(w);
            });
        }
        toggle82x38BarcodeTextVisibility();
        log82x38AppliedBarcodes();
    }
    function mmFrom82x38DomPosition(block) {
        var outerRect = get82x38OuterRect();
        if (!outerRect || !block) return null;
        var boxRect = block.getBoundingClientRect();
        var scale = get82x38ScaleFromOuter(outerRect);
        var leftPx = boxRect.left - outerRect.left;
        var topPx = boxRect.top - outerRect.top;
        return {
            left_mm: Math.round((leftPx / scale.x) * 10) / 10,
            top_mm: Math.round((topPx / scale.y) * 10) / 10,
            width_mm: Math.round((boxRect.width / scale.x) * 10) / 10,
            height_mm: Math.round((boxRect.height / scale.y) * 10) / 10
        };
    }
    function apply82x38BoxLayoutMm(block, leftMm, topMm, widthMm, heightMm) {
        if (!block) return;
        widthMm = BOX_82X38_WIDTH_MM;
        heightMm = BOX_82X38_HEIGHT_MM;
        block.style.position = 'absolute';
        block.style.left = leftMm + 'mm';
        block.style.top = topMm + 'mm';
        block.style.width = widthMm + 'mm';
        block.style.height = heightMm + 'mm';
        block.style.right = 'auto';
        block.style.bottom = 'auto';
        block.style.margin = '0';
        block.style.flex = 'none';
        block.style.transform = 'none';
    }
    function ensure82x38BoxHorizontalLines() {
        if (!is82x38TwoBoxPreset()) return;
        ['labelCanvas1', 'labelCanvas2'].forEach(function(id) {
            var canvas = document.getElementById(id);
            if (!canvas) return;
            if (!canvas.querySelector('.barcode-box-horizontal-line')) {
                var line = document.createElement('div');
                line.className = 'barcode-box-horizontal-line';
                line.setAttribute('aria-hidden', 'true');
                canvas.insertBefore(line, canvas.firstChild);
            }
        });
    }
    function sync82x38BoxPositionsFromDom() {
        if (!is82x38TwoBoxPreset()) return;
        force82x38FixedBoxPositions();
    }
    function get82x38BarcodeIndex(wrap) {
        if (!wrap) return 1;
        var idx = parseInt(wrap.getAttribute('data-barcode-index'), 10);
        if (idx === 2 || wrap.id === 'barcode2') return 2;
        return 1;
    }
    function get82x38BoxForBarcodeIndex(index) {
        return index === 2 ? document.getElementById('box2') : document.getElementById('box1');
    }
    function get82x38ScaleFromBox(box) {
        var boxRect = box ? box.getBoundingClientRect() : null;
        if (!boxRect || boxRect.width <= 0 || boxRect.height <= 0) {
            return { x: MM_TO_PX, y: MM_TO_PX };
        }
        return {
            x: boxRect.width / BOX_82X38_WIDTH_MM,
            y: boxRect.height / BOX_82X38_HEIGHT_MM
        };
    }
    function set82x38BarcodePropInputs(index, leftMm, topMm, widthMm, heightMm) {
        var p = index === 2 ? 'propBox2' : 'propBox1';
        function set(id, val) {
            var el = document.getElementById(id);
            if (el && val != null && !isNaN(val)) el.value = Math.round(parseFloat(val) * 10) / 10;
        }
        set(p + 'BarcodeLeftMm', leftMm);
        set(p + 'BarcodeTopMm', topMm);
        if (widthMm != null) set(p + 'BarcodeWidthMm', widthMm);
        if (heightMm != null) set(p + 'BarcodeHeightMm', heightMm);
    }
    function ensure82x38BarcodeInnerDom(index) {
        var wrapId = index === 2 ? 'barcode2' : 'barcode1';
        var svgId = index === 2 ? 'barcodeSvgBox2' : 'barcodeSvgBox1';
        var textId = index === 2 ? 'barcodeText2' : 'barcodeText1';
        var wrap = document.getElementById(wrapId);
        var canvas = document.getElementById(index === 2 ? 'labelCanvas2' : 'labelCanvas1');
        if (!wrap || !canvas) return null;
        wrap.classList.add('barcode-inner-draggable', 'barcode-82x38-barcode');
        wrap.classList.remove('barcode-print-wrap');
        wrap.setAttribute('data-barcode-index', String(index));
        wrap.style.pointerEvents = 'auto';
        var stripes = wrap.querySelector('.barcode-stripes');
        if (stripes) stripes.remove();
        var svg = document.getElementById(svgId);
        if (!svg) {
            svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'barcode-svg barcode-svg-box' + index);
            svg.id = svgId;
        }
        ensure82x38BarcodeGraphicWrap(wrap);
        var graphic = wrap.querySelector('.barcode-82x38-graphic');
        if (svg && graphic && svg.parentNode !== graphic) {
            graphic.appendChild(svg);
        }
        if (!wrap.querySelector('.barcode-text') && !document.getElementById(textId)) {
            var txt = document.createElement('div');
            txt.className = 'barcode-text';
            txt.id = textId;
            wrap.appendChild(txt);
        } else if (!wrap.querySelector('#' + textId) && document.getElementById(textId)) {
            wrap.appendChild(document.getElementById(textId));
        }
        if (!wrap.querySelector('.resize-handle')) {
            var handle = document.createElement('span');
            handle.className = 'resize-handle';
            handle.setAttribute('aria-label', 'Resize barcode');
            wrap.appendChild(handle);
        } else {
            var existingHandle = wrap.querySelector('.resize-handle');
            if (existingHandle) wrap.appendChild(existingHandle);
        }
        return wrap;
    }
    var _82x38BarcodeDragState = null;
    var _82x38BarcodeResizing = false;

    function save82x38CurrentLayoutToHiddenJson() {
        if (!is82x38TwoBoxPreset()) return;
        sync82x38BoxPositionsFromDom();
        sync82x38BarcodePositionsFromDom({ syncSize: true });
        try {
            var bl = read82x38BoxLayoutFromInputs();
            var parsed = {};
            if (savedDesignLayout && String(savedDesignLayout).trim()) {
                parsed = JSON.parse(savedDesignLayout);
            }
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                parsed = {};
            }
            parsed.layout_type = '82x38_2box';
            parsed.sticker_82x38_2box = true;
            parsed.box1_left_mm = bl.box1_left_mm;
            parsed.box1_top_mm = bl.box1_top_mm;
            parsed.box2_left_mm = bl.box2_left_mm;
            parsed.box2_top_mm = bl.box2_top_mm;
            parsed.box_width_mm = bl.box_width_mm;
            parsed.box_height_mm = bl.box_height_mm;
            parsed.barcode1 = {
                left_mm: bl.box1_barcode_left_mm,
                top_mm: bl.box1_barcode_top_mm,
                width_mm: bl.box1_barcode_width_mm,
                height_mm: bl.box1_barcode_height_mm
            };
            parsed.barcode2 = {
                left_mm: bl.box2_barcode_left_mm,
                top_mm: bl.box2_barcode_top_mm,
                width_mm: bl.box2_barcode_width_mm,
                height_mm: bl.box2_barcode_height_mm
            };
            parsed.barcode_no_font_size = bl.barcode_no_font_size;
            parsed.barcode_no_margin_top_mm = bl.barcode_no_margin_top_mm;
            parsed.sticker_print_offset_top_mm = bl.sticker_print_offset_top_mm;
            parsed.sticker_print_offset_left_mm = bl.sticker_print_offset_left_mm;
            if (typeof collect82x38StickerStripLines === 'function') {
                parsed.sticker_strip_lines = collect82x38StickerStripLines();
            }
            if (typeof collect82x38StickerWhiteStrips === 'function') {
                parsed.sticker_white_strips = collect82x38StickerWhiteStrips();
            }
            if (typeof collect82x38HalfStripFields === 'function') {
                parsed.sticker_half_strip_fields = collect82x38HalfStripFields();
            }
            if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                var qw = parseInt(document.getElementById('propQrWidth') && document.getElementById('propQrWidth').value, 10);
                var qh = parseInt(document.getElementById('propQrHeight') && document.getElementById('propQrHeight').value, 10);
                parsed.qr_width = (isNaN(qw) || qw < 12) ? 60 : Math.min(200, qw);
                parsed.qr_height = (isNaN(qh) || qh < 12) ? 60 : Math.min(200, qh);
                persistedLayoutQr = JSON.stringify(parsed);
            } else {
                persistedLayoutBarcode = JSON.stringify(parsed);
            }
            savedDesignLayout = (currentCodeType === 'qr') ? persistedLayoutQr : persistedLayoutBarcode;
        } catch (e) {}
    }

    function attach82x38BarcodeResizeDelegation() {
        if (document._82x38BarcodeResizeDelegationBound) return;
        document._82x38BarcodeResizeDelegationBound = true;
        document.addEventListener('mousedown', function(e) {
            if (!is82x38TwoBoxPreset()) return;
            var handle = e.target.closest('.resize-handle');
            if (!handle) return;
            var wrap = handle.closest('.barcode-inner-draggable');
            if (!wrap || !(wrap.closest('.barcode-82x38-box') || wrap.closest('#box1, #box2'))) return;

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            var box = wrap.closest('.barcode-82x38-box') || wrap.closest('#box1, #box2');
            if (!box) return;

            _82x38BarcodeResizing = true;
            _82x38BarcodeDragState = null;
            document.body.style.cursor = 'se-resize';
            selectBarcodePrintWrap(wrap);

            var startX = e.clientX;
            var startY = e.clientY;
            var startW = wrap.offsetWidth;
            var startH = wrap.offsetHeight;
            var wrapRect = wrap.getBoundingClientRect();
            var boxRect = box.getBoundingClientRect();
            var startLeftInBox = wrapRect.left - boxRect.left;
            var startTopInBox = wrapRect.top - boxRect.top;

            function onMove(ev) {
                ev.preventDefault();
                var scaleMove = get82x38ScaleFromBox(box);
                var textAreaPx = Math.ceil(get82x38BarcodeTextAreaMm(scaleMove.y) * scaleMove.y);
                var minGraphicPx = Math.max(8, Math.ceil(BOX_82X38_BARCODE_GRAPHIC_MIN_MM * scaleMove.y));
                var minHPx = minGraphicPx + textAreaPx;
                var maxW = box.clientWidth - startLeftInBox;
                var maxH = box.clientHeight - startTopInBox;
                var topMm = startTopInBox / scaleMove.y;
                var maxHMm = get82x38MaxContainerHeightMm(topMm);
                maxH = Math.min(maxH, Math.ceil(maxHMm * scaleMove.y));
                var newW = startW + (ev.clientX - startX);
                var newH = startH + (ev.clientY - startY);
                newW = Math.max(Math.ceil(BOX_82X38_BARCODE_MIN_WIDTH_MM * scaleMove.x), Math.min(newW, maxW));
                newH = Math.max(minHPx, Math.min(newH, maxH));
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                    var side = Math.max(minHPx, Math.min(newW, newH, maxW, maxH));
                    newW = side;
                    newH = side;
                }
                wrap.style.setProperty('width', newW + 'px', 'important');
                wrap.style.setProperty('height', newH + 'px', 'important');
                var wMmMove = Math.round((newW / scaleMove.x) * 10) / 10;
                var hMmMove = Math.round((newH / scaleMove.y) * 10) / 10;
                set82x38BarcodePropInputs(get82x38BarcodeIndex(wrap), null, null, wMmMove, hMmMove);
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                    updateBarcodeQrDisplay();
                } else {
                    schedule82x38BarcodeResizeRedraw(wrap, newW, newH);
                }
            }

            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.body.style.cursor = '';
                _82x38BarcodeResizing = false;
                var scale = get82x38ScaleFromBox(box);
                var pos = mmFrom82x38BarcodeInBox(wrap);
                var wMm = Math.round((wrap.offsetWidth / scale.x) * 10) / 10;
                var hMm = Math.round((wrap.offsetHeight / scale.y) * 10) / 10;
                /* QR stays square from the resize corner — use the smaller side so it never jumps larger. */
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                    var side = Math.max(4, Math.min(wMm, hMm));
                    wMm = side;
                    hMm = side;
                }
                wMm = Math.max(BOX_82X38_BARCODE_MIN_WIDTH_MM, Math.min(BOX_82X38_WIDTH_MM - (pos ? pos.left_mm : 0), wMm));
                var textAreaMm = get82x38BarcodeTextAreaMm(scale.y);
                var minHm = BOX_82X38_BARCODE_GRAPHIC_MIN_MM + textAreaMm;
                var maxHm = get82x38MaxContainerHeightMm(pos ? pos.top_mm : 0);
                hMm = Math.max(minHm, Math.min(maxHm, hMm));
                var leftMm = pos ? pos.left_mm : 2;
                var topMm = pos ? pos.top_mm : 2;
                wrap.style.removeProperty('width');
                wrap.style.removeProperty('height');
                apply82x38BarcodeLayoutMm(wrap, leftMm, topMm, wMm, hMm);
                set82x38BarcodePropInputs(get82x38BarcodeIndex(wrap), leftMm, topMm, wMm, hMm);
                syncQrPxFrom82x38WrapMm(wMm, hMm, box);
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                    updateBarcodeQrDisplay();
                } else {
                    layout82x38BarcodeInnerContent(wrap, { wPx: wMm * scale.x, hPx: hMm * scale.y });
                }
                save82x38CurrentLayoutToHiddenJson();
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        }, true);
    }
    function dragBarcodeInsideBox(e) {
        var st = _82x38BarcodeDragState;
        if (!st || st.mode !== 'drag' || !st.wrap || !st.box) return;
        var boxRect = st.box.getBoundingClientRect();
        var scale = get82x38ScaleFromBox(st.box);
        var wrapRect = st.wrap.getBoundingClientRect();
        var widthMm = st.widthMm;
        var heightMm = st.heightMm;
        /* Keep the wrap's current mm size while dragging — do not jump to shared propQr size. */
        var leftPx = e.clientX - boxRect.left - st.offsetX;
        var topPx = e.clientY - boxRect.top - st.offsetY;
        var maxLeftPx = boxRect.width - (widthMm * scale.x);
        var maxTopPx = boxRect.height - (heightMm * scale.y);
        leftPx = Math.max(0, Math.min(maxLeftPx, leftPx));
        topPx = Math.max(0, Math.min(maxTopPx, topPx));
        var leftMm = Math.round((leftPx / scale.x) * 10) / 10;
        var topMm = Math.round((topPx / scale.y) * 10) / 10;
        leftMm = Math.max(0, Math.min(BOX_82X38_WIDTH_MM - widthMm, leftMm));
        topMm = Math.max(0, Math.min(BOX_82X38_HEIGHT_MM - heightMm, topMm));
        apply82x38BarcodeLayoutMm(st.wrap, leftMm, topMm, widthMm, heightMm);
        set82x38BarcodePropInputs(st.index, leftMm, topMm, widthMm, heightMm);
    }
    function bind82x38BarcodeWrapDrag(wrap, box, index) {
        if (!wrap || !box || wrap._82x38DragBound) return;
        wrap._82x38DragBound = true;
        wrap.addEventListener('mousedown', function(e) {
            if (!is82x38TwoBoxPreset()) return;
            if (_82x38BarcodeResizing) return;
            if (e.target.closest('.resize-handle')) return;
            if (e.target.closest('.qr-code-preview') || e.target.closest('.qr-preview-canvas')) {
                /* Allow drag from QR canvas — use wrap as drag target */
            }
            e.preventDefault();
            e.stopPropagation();
            selectBarcodePrintWrap(wrap);
            var wrapRect = wrap.getBoundingClientRect();
            var leftMm = parseFloat(String(wrap.style.left || '').replace('mm', ''));
            var topMm = parseFloat(String(wrap.style.top || '').replace('mm', ''));
            if (isNaN(leftMm)) leftMm = 2;
            if (isNaN(topMm)) topMm = 2;
            /* Always keep the wrap's current size — do NOT replace with propQr* (that made QR jump larger + left). */
            var sizeMm = get82x38WrapSizeMm(wrap);
            var widthMm = sizeMm.width_mm;
            var heightMm = sizeMm.height_mm;
            _82x38BarcodeDragState = {
                mode: 'drag',
                wrap: wrap,
                box: box,
                index: index,
                offsetX: e.clientX - wrapRect.left,
                offsetY: e.clientY - wrapRect.top,
                widthMm: widthMm,
                heightMm: heightMm
            };
        });
    }
    function init82x38BarcodeDragResize() {
        if (!is82x38TwoBoxPreset()) return;
        attach82x38BarcodeResizeDelegation();
        [1, 2].forEach(function(index) {
            var wrap = ensure82x38BarcodeInnerDom(index);
            var box = get82x38BoxForBarcodeIndex(index);
            if (!wrap || !box) return;
            bind82x38BarcodeWrapDrag(wrap, box, index);
        });
        if (!document._82x38BarcodeDragDocBound) {
            document._82x38BarcodeDragDocBound = true;
            document.addEventListener('mousemove', function(e) {
                if (!_82x38BarcodeDragState) return;
                if (_82x38BarcodeDragState.mode === 'drag') dragBarcodeInsideBox(e);
            });
            document.addEventListener('mouseup', function() {
                if (!_82x38BarcodeDragState) return;
                var dragged = _82x38BarcodeDragState;
                _82x38BarcodeDragState = null;
                document.body.style.cursor = '';
                /* Sync THIS wrap only — avoid re-measuring the other box and shifting it left. */
                sync82x38BarcodePositionsFromDom({ syncSize: true });
                if (dragged.wrap && typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                    var sz = get82x38WrapSizeMm(dragged.wrap);
                    syncQrPxFrom82x38WrapMm(sz.width_mm, sz.height_mm, dragged.box);
                    updateBarcodeQrDisplay();
                }
                save82x38CurrentLayoutToHiddenJson();
            });
        }
    }
    function get82x38StickerShiftMm() {
        function padMm(id) {
            var el = document.getElementById(id);
            if (!el) return 0;
            var px = parseInt(el.value, 10);
            if (isNaN(px) || px < 0) return 0;
            return Math.round((Math.min(200, px) / MM_TO_PX) * 10) / 10;
        }
        function offsetMm(id) {
            var el = document.getElementById(id);
            if (!el) return 0;
            var n = parseFloat(el.value);
            if (isNaN(n)) return 0;
            return Math.round(Math.max(-12, Math.min(12, n)) * 10) / 10;
        }
        return {
            top_mm: Math.round((padMm('labelPadTop') + offsetMm('propStickerPrintOffsetTopMm')) * 10) / 10,
            left_mm: Math.round((padMm('labelPadLeft') + offsetMm('propStickerPrintOffsetLeftMm')) * 10) / 10
        };
    }
    function layout82x38DualCanvases() {
        if (!is82x38TwoBoxPreset()) return;
        force82x38FixedBoxPositions();
        var bl = read82x38BoxLayoutFromInputs();
        var shift = get82x38StickerShiftMm();
        var block1 = document.getElementById('box1');
        var block2 = document.getElementById('box2');
        apply82x38BoxLayoutMm(block1, bl.box1_left_mm + shift.left_mm, bl.box1_top_mm + shift.top_mm, bl.box_width_mm, bl.box_height_mm);
        apply82x38BoxLayoutMm(block2, bl.box2_left_mm + shift.left_mm, bl.box2_top_mm + shift.top_mm, bl.box_width_mm, bl.box_height_mm);
        ensure82x38BoxHorizontalLines();
        ensure82x38FixedHalfWhiteStrips();
        remove82x38OuterCmRuler();
    }
    /** Always show fixed half white strips on 82×38 (top-left + bottom-right). */
    function get82x38HalfStripGeometry(slot) {
        if (slot === 'bottom-right') {
            return { left: 20, top: 30.6, width: 62, height: 4.5 };
        }
        return { left: 0, top: 5, width: 62, height: 4.5 };
    }
    function is82x38HalfStripContainer(el) {
        return !!(el && el.classList && el.classList.contains('barcode-82x38-fixed-half-strip'));
    }
    function get82x38HalfStripAtPoint(clientX, clientY) {
        if (!is82x38TwoBoxPreset()) return null;
        var strips = document.querySelectorAll('.barcode-82x38-fixed-half-strip');
        for (var i = 0; i < strips.length; i++) {
            var r = strips[i].getBoundingClientRect();
            if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
                return strips[i];
            }
        }
        return null;
    }
    function bind82x38HalfStripDrop(stripEl) {
        if (!stripEl || stripEl.getAttribute('data-drop-bound') === '1') return;
        stripEl.setAttribute('data-drop-bound', '1');
        stripEl.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = (e.dataTransfer.types.indexOf('application/x-canvas-item') >= 0) ? 'move' : 'copy';
            stripEl.classList.add('drag-over');
        });
        stripEl.addEventListener('dragleave', function(e) {
            if (!stripEl.contains(e.relatedTarget)) stripEl.classList.remove('drag-over');
        });
        stripEl.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            stripEl.classList.remove('drag-over');
            handleDrop(e, stripEl.getBoundingClientRect(), stripEl);
        });
    }
    function ensure82x38FixedHalfWhiteStrips() {
        if (!is82x38TwoBoxPreset()) {
            var existingOff = document.getElementById('barcode82x38FixedStrips');
            if (existingOff) existingOff.style.display = 'none';
            return;
        }
        var shell = document.getElementById('barcodeDualStickerShell');
        if (!shell) return;
        var host = document.getElementById('barcode82x38FixedStrips');
        if (!host) {
            host = document.createElement('div');
            host.id = 'barcode82x38FixedStrips';
            host.className = 'barcode-82x38-fixed-strips';
            host.setAttribute('aria-hidden', 'true');
            host.title = 'Fixed half white strips';
            var labels = document.getElementById('barcodeLabelsContainer');
            if (labels && labels.parentNode === shell) {
                shell.insertBefore(host, labels);
            } else {
                shell.insertBefore(host, shell.firstChild);
            }
        }
        host.style.display = 'block';
        host.setAttribute('aria-hidden', 'false');
        /* Keep only the two fixed half strips; remove leftovers. */
        host.querySelectorAll('.barcode-82x38-fixed-half-strip').forEach(function(el) {
            var key = el.getAttribute('data-fixed-half-strip');
            if (key !== 'top-left' && key !== 'bottom-right') el.remove();
        });
        function ensureOne(cls, key, title, leftMm, topMm, widthMm, heightMm) {
            var el = host.querySelector('[data-fixed-half-strip="' + key + '"]');
            if (!el) {
                el = document.createElement('div');
                el.className = 'barcode-82x38-fixed-half-strip ' + cls;
                el.setAttribute('data-fixed-half-strip', key);
                el.title = title;
                host.appendChild(el);
            } else {
                el.className = 'barcode-82x38-fixed-half-strip ' + cls;
            }
            el.style.setProperty('left', leftMm + 'mm', 'important');
            el.style.setProperty('top', topMm + 'mm', 'important');
            el.style.setProperty('width', widthMm + 'mm', 'important');
            el.style.setProperty('height', heightMm + 'mm', 'important');
            el.style.setProperty('background', '#ffffff', 'important');
            el.style.setProperty('background-color', '#ffffff', 'important');
            el.style.setProperty('display', 'block', 'important');
            el.style.setProperty('border', '1px dashed #64748b', 'important');
            el.style.setProperty('pointer-events', 'auto', 'important');
            bind82x38HalfStripDrop(el);
        }
        /* Single top strip + single bottom strip (no mid duplicates). */
        var shift = get82x38StickerShiftMm();
        ensureOne('barcode-82x38-fixed-half-strip--top-left', 'top-left', 'Half white strip (top-left) — drop columns here', 0 + shift.left_mm, 5 + shift.top_mm, 62, 4.5);
        ensureOne('barcode-82x38-fixed-half-strip--bottom-right', 'bottom-right', 'Half white strip (bottom) — drop columns here', 20 + shift.left_mm, 30.6 + shift.top_mm, 62, 4.5);
    }
    function remove82x38OuterCmRuler() {
        document.querySelectorAll('.barcode-82x38-outer-scale').forEach(function(el) { el.remove(); });
    }

    function getDualPresetConfig(val) {
        val = val || barcodeLabelPreset();
        if (val === STICKER_82X38_PRESET) {
            return {
                stickerW: 82, stickerH: 38, gap: 0,
                quadW: BOX_82X38_WIDTH_MM, quadH: BOX_82X38_HEIGHT_MM,
                stripPx: 0, handlePx: 0, padH: 0, padV: 0,
                is82x38: true
            };
        }
        if (val === '250x120') {
            return {
                stickerW: 250, stickerH: 120, gap: DUAL_TAG_GAP_MM,
                quadW: 42, quadH: 60,
                stripPx: 18, handlePx: 10, padH: 5, padV: 4
            };
        }
        return {
            stickerW: 120, stickerH: 50, gap: DUAL_TAG_GAP_MM,
            quadW: 20, quadH: 25,
            stripPx: 18, handlePx: 10, padH: 5, padV: 4
        };
    }

    function isDualLabelLayoutPreset(val) {
        val = val || barcodeLabelPreset();
        return DUAL_LABEL_SIZES.indexOf(val) !== -1;
    }
    function isDualSingleLabelPreset(val) {
        return isDualLabelLayoutPreset(val);
    }
    function getDualHalfLabelWidthMm(val) {
        var cfg = getDualPresetConfig(val);
        return (cfg.stickerW - cfg.gap) / 2;
    }
    function getDualTagPrintBoxWidthMm(val) {
        val = val || barcodeLabelPreset();
        if (val === STICKER_82X38_PRESET) {
            return BOX_82X38_WIDTH_MM;
        }
        return getDualPresetConfig(val).quadW;
    }
    function getDualTagPrintBoxHeightMm(val) {
        val = val || barcodeLabelPreset();
        if (val === STICKER_82X38_PRESET) {
            return BOX_82X38_HEIGHT_MM;
        }
        return getDualPresetConfig(val).quadH;
    }
    function getDualTagLayoutPx(val) {
        val = val || barcodeLabelPreset();
        if (val === STICKER_82X38_PRESET) {
            var bl = read82x38BoxLayoutFromInputs();
            var whiteWPx = Math.round(bl.box_width_mm * MM_TO_PX);
            var whiteHPx = Math.round(bl.box_height_mm * MM_TO_PX);
            return {
                stickerWPx: Math.round(82 * MM_TO_PX),
                stickerHPx: Math.round(38 * MM_TO_PX),
                whiteWPx: whiteWPx,
                whiteHPx: whiteHPx,
                innerW: whiteWPx,
                innerH: whiteHPx,
                halfLabelMm: bl.box_width_mm,
                cfg: { stickerW: 82, stickerH: 38, quadW: bl.box_width_mm, quadH: bl.box_height_mm, is82x38: true }
            };
        }
        var cfg = getDualPresetConfig(val);
        var tagOuterWMm = getDualHalfLabelWidthMm(val);
        var whiteWPx = Math.round(cfg.quadW * MM_TO_PX);
        var whiteHPx = Math.round(cfg.quadH * MM_TO_PX);
        var innerW = cfg.padH * 2 + cfg.stripPx + whiteWPx + cfg.handlePx;
        var innerH = cfg.padV * 2 + whiteHPx;
        return {
            stickerWPx: Math.round(cfg.stickerW * MM_TO_PX),
            stickerHPx: Math.round(cfg.stickerH * MM_TO_PX),
            whiteWPx: whiteWPx,
            whiteHPx: whiteHPx,
            innerW: innerW,
            innerH: innerH,
            halfLabelMm: tagOuterWMm,
            cfg: cfg
        };
    }
    /** @deprecated use getDualTagLayoutPx */
    function get120x50DualTagLayoutPx() {
        return getDualTagLayoutPx('120x50');
    }
    function restoreBarcode2ToHomeCanvas() {
        var bc2 = document.getElementById('barcode2');
        if (!bc2 || !bc2._dualHomeParent) return;
        if (bc2.parentElement === bc2._dualHomeParent) return;
        var home = bc2._dualHomeParent;
        var before = bc2._dualHomeBefore;
        if (before && before.parentElement === home) {
            home.insertBefore(bc2, before);
        } else {
            home.appendChild(bc2);
        }
    }
    function rememberBarcode2Home() {
        var bc2 = document.getElementById('barcode2');
        var c2 = labelCanvas2 || document.getElementById('labelCanvas2');
        var dropZ2 = document.getElementById('barcodeWhiteDropZone2');
        if (!bc2 || !c2) return;
        if (!bc2._dualHomeParent) {
            bc2._dualHomeParent = c2;
            bc2._dualHomeBefore = dropZ2;
        }
    }
    function resetBarcodeWrapInlineSize(wrap) {
        if (!wrap) return;
        wrap.style.display = '';
        wrap.style.visibility = 'visible';
        wrap.style.boxSizing = 'border-box';
        wrap.style.overflow = 'hidden';
        wrap.style.width = 'auto';
        wrap.style.maxWidth = '';
        wrap.style.height = 'auto';
        wrap.style.maxHeight = '';
        wrap.removeAttribute('data-display-height');
        var stripes = wrap.querySelector('.barcode-stripes');
        if (stripes) {
            stripes.style.display = 'block';
            stripes.style.visibility = 'visible';
            stripes.style.width = '';
            stripes.style.height = '';
            stripes.style.minHeight = '';
        }
    }
    function positionDefaultBarcodesInEachLabel() {
        var bc1 = document.getElementById('barcode1');
        var bc2 = document.getElementById('barcode2');
        var c1 = labelCanvas1 || document.getElementById('labelCanvas1');
        var c2 = labelCanvas2 || document.getElementById('labelCanvas2');
        var isDual120 = isDualLabelLayoutPreset();
        var is82 = is82x38TwoBoxPreset();
        var pad = isDual120 ? 0 : 6;
        function placeInTagBelowFoldCentered(wrap, canvasEl) {
            if (!wrap || !canvasEl) return;
            resetBarcodeWrapInlineSize(wrap);
            wrap.style.position = 'absolute';
            wrap.style.margin = '0';
            wrap.style.right = 'auto';
            wrap.style.bottom = 'auto';
            var w = Math.max(40, wrap.offsetWidth || 80);
            var h = Math.max(20, wrap.offsetHeight || 24);
            var mid = Math.floor(canvasEl.clientHeight * 0.5);
            wrap.style.left = Math.max(0, Math.round((canvasEl.clientWidth - w) / 2)) + 'px';
            wrap.style.top = Math.max(mid, canvasEl.clientHeight - h - pad) + 'px';
        }
        function placeInTag(wrap, canvasEl, hAlign, vZone) {
            if (!wrap || !canvasEl) return;
            resetBarcodeWrapInlineSize(wrap);
            wrap.style.position = 'absolute';
            wrap.style.margin = '0';
            var w = Math.max(40, wrap.offsetWidth || 80);
            var h = Math.max(20, wrap.offsetHeight || 24);
            var mid = Math.floor(canvasEl.clientHeight * 0.5);
            if (hAlign === 'center') {
                wrap.style.left = Math.max(0, Math.round((canvasEl.clientWidth - w) / 2)) + 'px';
                wrap.style.right = 'auto';
            } else if (hAlign === 'left') {
                wrap.style.left = pad + 'px';
                wrap.style.right = 'auto';
            } else {
                wrap.style.left = Math.max(pad, canvasEl.clientWidth - w - pad) + 'px';
                wrap.style.right = 'auto';
            }
            if (vZone === 'bottom' || vZone === 'below-fold') {
                wrap.style.top = Math.max(mid, canvasEl.clientHeight - h - pad) + 'px';
            } else if (vZone === 'top') {
                wrap.style.top = pad + 'px';
            } else {
                wrap.style.top = pad + 'px';
            }
        }
        if (isDual120) {
            placeInTagBelowFoldCentered(bc1, c1);
            placeInTagBelowFoldCentered(bc2, c2);
        } else if (is82) {
            render82x38PreviewPipeline({ skipBoxLayout: true });
        } else {
            placeInTag(bc1, c1, 'left', null);
            placeInTag(bc2, c2, 'right', null);
        }
        if (typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
        if (!isDual120 && !is82 && typeof clampBarcodeBlockIntoCanvas === 'function') {
            clampBarcodeBlockIntoCanvas(c1, bc1);
            clampBarcodeBlockIntoCanvas(c2, bc2);
        }
        syncDualCanvasHeight();
    }
    function syncDualCanvasHeight() {
        var canvasEl = document.getElementById('barcodeCanvas');
        var shell = document.getElementById('barcodeDualStickerShell');
        if (!canvasEl) return;
        if (!isDualCanvasLayoutPreset() || !shell) {
            canvasEl.style.minHeight = '';
            if (shell) {
                shell.style.width = '';
                shell.style.height = '';
                shell.style.maxWidth = '';
                shell.style.minWidth = '';
                shell.style.minHeight = '';
                shell.style.zoom = '';
                shell.classList.remove('barcode-82x38-outer');
            }
            var outerBadgeClear = document.getElementById('barcodeDualOuterSizeBadge');
            if (outerBadgeClear) outerBadgeClear.textContent = '';
            return;
        }
        if (is82x38TwoBoxPreset()) {
            shell.classList.add('barcode-82x38-outer');
            shell.style.width = '82mm';
            shell.style.height = '38mm';
            shell.style.maxWidth = '82mm';
            shell.style.minWidth = '82mm';
            shell.style.minHeight = '38mm';
            shell.style.zoom = '';
            shell.style.left = '';
            shell.style.top = '';
            shell.style.right = '';
            shell.style.margin = '';
            shell.style.position = '';
            shell.setAttribute('title', '82 mm × 38 mm outer sticker (8.2 cm × 3.8 cm)');
            var outerBadge82 = document.getElementById('barcodeDualOuterSizeBadge');
            if (outerBadge82) {
                outerBadge82.textContent = '';
                outerBadge82.style.display = 'none';
            }
            canvasEl.style.minHeight = Math.max(180, shell.offsetHeight + 16) + 'px';
            layout82x38DualCanvases();
            remove82x38OuterCmRuler();
            return;
        }
        shell.classList.remove('barcode-82x38-outer');
        var val = barcodeLabelPreset();
        var dims = getDualTagLayoutPx();
        var cfg = dims.cfg;
        shell.style.width = dims.stickerWPx + 'px';
        shell.style.height = dims.stickerHPx + 'px';
        shell.style.maxWidth = 'none';
        shell.style.minWidth = dims.stickerWPx + 'px';
        shell.style.minHeight = dims.stickerHPx + 'px';
        shell.setAttribute('title', cfg.stickerW + ' mm × ' + cfg.stickerH + ' mm outer sticker (' + (cfg.stickerW / 10) + ' cm × ' + (cfg.stickerH / 10) + ' cm)');
        var outerBadge = document.getElementById('barcodeDualOuterSizeBadge');
        if (!outerBadge) {
            outerBadge = document.createElement('div');
            outerBadge.id = 'barcodeDualOuterSizeBadge';
            outerBadge.className = 'barcode-dual-outer-size-badge';
            shell.parentElement.insertBefore(outerBadge, shell);
        }
        outerBadge.style.display = '';
        outerBadge.textContent = 'Outer sticker: ' + cfg.stickerW + ' mm × ' + cfg.stickerH + ' mm (' + (cfg.stickerW / 10) + ' cm × ' + (cfg.stickerH / 10) + ' cm)';
        canvasEl.style.minHeight = Math.max(180, dims.stickerHPx + 48) + 'px';
        applyDualShellScreenTrueSize();
    }
    function applyDualShellScreenTrueSize() {
        if (is82x38TwoBoxPreset()) return;
        var shell = document.getElementById('barcodeDualStickerShell');
        if (!shell || !isDualCanvasLayoutPreset()) return;
        var ref = 96 / 25.4;
        var probe = document.createElement('div');
        probe.style.cssText = 'position:fixed;left:-9999px;top:0;width:100mm;height:1px;visibility:hidden;pointer-events:none;';
        document.documentElement.appendChild(probe);
        var probeW = probe.getBoundingClientRect().width;
        probe.remove();
        var cssFix = (probeW > 0) ? ((100 * ref) / probeW) : 1;
        var dpr = window.devicePixelRatio || 1;
        var zoom = cssFix * dpr;
        shell.style.zoom = (zoom > 0.05 && zoom < 8) ? String(zoom) : '1';
    }
    function barcodeWrapNeedsReset(wrap, canvasEl) {
        if (!wrap || !canvasEl) return true;
        var stripes = wrap.querySelector('.barcode-stripes');
        if (!stripes || !stripes.querySelector('svg')) return true;
        if (wrap.offsetWidth > canvasEl.clientWidth + 4) return true;
        if (stripes.style.width === '100%') return true;
        return false;
    }
    function ensureDualBarcodesRendered(opts) {
        opts = opts || {};
        var preservePositions = !!opts.preservePositions;
        if (!isDualCanvasLayoutPreset()) return;
        var bc1 = document.getElementById('barcode1');
        var bc2 = document.getElementById('barcode2');
        var c1 = labelCanvas1 || document.getElementById('labelCanvas1');
        var c2 = labelCanvas2 || document.getElementById('labelCanvas2');
        var needsReset = barcodeWrapNeedsReset(bc1, c1) || barcodeWrapNeedsReset(bc2, c2);
        if (!preservePositions) {
            resetBarcodeWrapInlineSize(bc1);
            resetBarcodeWrapInlineSize(bc2);
        }
        if (needsReset && !preservePositions) {
            positionDefaultBarcodesInEachLabel();
        } else if (is82x38TwoBoxPreset()) {
            render82x38PreviewPipeline({ skipBoxLayout: !!preservePositions });
        } else {
            if (typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
            syncDualCanvasHeight();
        }
    }
    function destroyStandardBarcodePreview() {
        var canvasEl = document.getElementById('barcodeCanvas');
        if (canvasEl) {
            canvasEl.classList.remove('dual-barcode-layout', 'barcode-82x38-dual-layout', 'barcode-82x38-layout');
        }
        var shellEl = document.getElementById('barcodeDualStickerShell');
        if (shellEl) {
            shellEl.classList.remove('barcode-82x38-outer');
            shellEl.style.width = '';
            shellEl.style.height = '';
            shellEl.style.maxWidth = '';
            shellEl.style.minWidth = '';
            shellEl.style.minHeight = '';
            shellEl.style.position = '';
            shellEl.style.margin = '';
        }
        var previewWrapper = document.getElementById('barcode82x38PreviewWrapper');
        if (previewWrapper) previewWrapper.setAttribute('aria-hidden', 'true');
        ['box1', 'box2'].forEach(function(id) {
            var block = document.getElementById(id);
            if (!block) return;
            block.classList.remove('barcode-82x38-box');
            block.style.left = '';
            block.style.top = '';
            block.style.width = '';
            block.style.height = '';
            block.style.position = '';
            var wrap = block.querySelector('.barcode-default-wrap');
            if (wrap) wrap.style.display = '';
            var inner = block.querySelector('.barcode-default-inner');
            if (inner) {
                inner.style.width = '';
                inner.style.height = '';
                inner.style.minWidth = '';
                inner.style.minHeight = '';
                inner.classList.remove('barcode-tag-backing', 'barcode-label-short', 'barcode-tag-jewelry');
            }
        });
        var box2 = document.getElementById('box2');
        if (box2) box2.style.display = 'none';
        var bc1 = document.getElementById('barcode1');
        var bc2 = document.getElementById('barcode2');
        if (bc1) {
            bc1.classList.remove('barcode-82x38-barcode', 'barcode-inner-draggable');
            bc1._82x38DragBound = false;
            var h1 = bc1.querySelector('.resize-handle');
            if (h1) h1._82x38ResizeBound = false;
        }
        if (bc2) {
            bc2.classList.remove('barcode-82x38-barcode', 'barcode-inner-draggable');
            bc2._82x38DragBound = false;
            var h2 = bc2.querySelector('.resize-handle');
            if (h2) h2._82x38ResizeBound = false;
        }
        document.querySelectorAll('.barcode-box-horizontal-line').forEach(function(el) { el.remove(); });
        remove82x38OuterCmRuler();
    }

    function render82x38DualEditor() {
        if (!is82x38TwoBoxPreset()) return;
        force82x38FixedBoxPositions();
        labelWidthMm = 82;
        labelHeightMm = 38;
        syncLabelMmFromPreset(STICKER_82X38_PRESET);
        applyDualBarcodeLayout(STICKER_82X38_PRESET);
        ensure82x38BarcodeInnerDom(1);
        ensure82x38BarcodeInnerDom(2);
        ['box1', 'box2'].forEach(function(id) {
            var block = document.getElementById(id);
            if (!block) return;
            block.classList.add('barcode-82x38-box');
            var wrap = block.querySelector('.barcode-default-wrap');
            if (wrap) wrap.style.display = 'contents';
            var inner = block.querySelector('.barcode-default-inner');
            if (inner) {
                inner.style.width = '';
                inner.style.height = '';
                inner.style.minWidth = '';
                inner.style.minHeight = '';
                inner.classList.remove('barcode-tag-backing', 'barcode-label-short', 'barcode-tag-jewelry');
            }
            var white = block.querySelector('.barcode-default-white');
            if (white) {
                white.style.width = '';
                white.style.height = '';
                white.style.flex = '';
                white.style.minWidth = '';
                white.style.minHeight = '';
                white.classList.remove('barcode-print-area-mm');
            }
        });
        var box2 = document.getElementById('box2');
        if (box2) box2.style.display = 'block';
        var previewWrapper = document.getElementById('barcode82x38PreviewWrapper');
        if (previewWrapper) previewWrapper.setAttribute('aria-hidden', 'false');
        layout82x38DualCanvases();
        render82x38PreviewPipeline({ skipBoxLayout: true });
        init82x38BarcodeDragResize();
        attach82x38BarcodeResizeDelegation();
        syncDualCanvasHeight();
        toggle82x38BoxPropRow(true);
    }

    function applyDualBarcodeLayout(val) {
        val = (val || barcodeLabelPreset()).trim();
        var isDualCanvas = isDualCanvasLayoutPreset(val);
        var is82 = is82x38TwoBoxPreset(val);
        var canvasEl = document.getElementById('barcodeCanvas');
        var shellEl = document.getElementById('barcodeDualStickerShell');
        var previewWrapper = document.getElementById('barcode82x38PreviewWrapper');
        var labelsContainer = document.getElementById('barcodeLabelsContainer');
        var label2 = document.getElementById('box2');
        var block1 = document.getElementById('box1');
        var block2 = document.getElementById('box2');
        var bt2 = document.getElementById('barcodeText2');
        rememberBarcode2Home();
        toggle82x38BoxPropRow(is82);
        if (!is82) {
            document.querySelectorAll('.barcode-box-cm-ruler').forEach(function(el) { el.remove(); });
            remove82x38OuterCmRuler();
        }
        if (canvasEl) {
            canvasEl.classList.remove('barcode-82x38-dual-layout', 'barcode-82x38-layout');
        }
        if (labelsContainer) {
            labelsContainer.classList.remove('barcode-tag-pair-stack', 'two-labels');
        }
        if (isDualCanvas) {
            if (canvasEl) {
                canvasEl.classList.add('dual-barcode-layout');
                if (is82) canvasEl.classList.add('barcode-82x38-dual-layout');
            }
            if (shellEl) {
                shellEl.classList.toggle('barcode-82x38-outer', is82);
                if (is82) {
                    shellEl.style.left = '';
                    shellEl.style.top = '';
                    shellEl.style.right = '';
                    shellEl.style.margin = '';
                    shellEl.style.position = '';
                }
            }
            if (previewWrapper) {
                previewWrapper.setAttribute('aria-hidden', is82 ? 'false' : 'true');
            }
            restoreBarcode2ToHomeCanvas();
            if (label2) label2.style.display = is82 ? 'block' : 'flex';
            var bc1Show = document.getElementById('barcode1');
            var bc2Show = document.getElementById('barcode2');
            if (block1) block1.classList.toggle('barcode-82x38-box', is82);
            if (block2) block2.classList.toggle('barcode-82x38-box', is82);
            if (bc1Show) {
                bc1Show.classList.toggle('barcode-82x38-barcode', is82);
            }
            if (bc2Show) {
                bc2Show.classList.toggle('barcode-82x38-barcode', is82);
            }
            if (!is82 && block1) { block1.style.left = ''; block1.style.top = ''; block1.style.width = ''; block1.style.height = ''; block1.classList.remove('barcode-82x38-box'); }
            if (!is82 && block2) { block2.style.left = ''; block2.style.top = ''; block2.style.width = ''; block2.style.height = ''; block2.classList.remove('barcode-82x38-box'); }
            if (bt2) bt2.style.display = '';
            if (!is82) {
                resetBarcodeWrapInlineSize(bc1Show);
                resetBarcodeWrapInlineSize(bc2Show);
            }
            if (bc1Show) { bc1Show.style.display = ''; bc1Show.style.visibility = 'visible'; }
            if (bc2Show) { bc2Show.style.display = ''; bc2Show.style.visibility = 'visible'; }
            setTimeout(function() {
                syncDualCanvasHeight();
                if (is82) {
                    layout82x38DualCanvases();
                    ensureDualBarcodesRendered({ preservePositions: true });
                } else {
                    ensureDualBarcodesRendered({ preservePositions: false });
                }
            }, 80);
        } else {
            toggle82x38BoxPropRow(false);
            if (canvasEl) canvasEl.style.minHeight = '';
            if (canvasEl) canvasEl.classList.remove('dual-barcode-layout', 'barcode-82x38-dual-layout');
            restoreBarcode2ToHomeCanvas();
            if (label2) label2.style.display = 'none';
            if (shellEl) {
                shellEl.style.display = 'block';
                shellEl.style.width = '';
                shellEl.style.height = '';
                shellEl.style.maxWidth = '';
                shellEl.style.minWidth = '';
                shellEl.style.minHeight = '';
                shellEl.style.zoom = '';
            }
            if (labelsContainer) {
                labelsContainer.style.position = 'absolute';
                labelsContainer.style.left = '0';
                labelsContainer.style.top = '0';
                labelsContainer.style.right = '0';
                labelsContainer.style.bottom = '0';
            }
            if (block1) {
                block1.classList.remove('barcode-preview-positioned');
                block1.style.left = '';
                block1.style.top = '';
                block1.style.width = '';
                block1.style.height = '';
                block1.style.position = '';
            }
            if (block2) { block2.style.left = ''; block2.style.top = ''; block2.style.width = ''; block2.style.height = ''; }
            setTimeout(function() {
                if (typeof positionBarcodeBlocks === 'function') positionBarcodeBlocks();
                if (!hasRestorableBarcodeLayout() && typeof refreshStandardBarcodeAfterLabelSizeChange === 'function') {
                    refreshStandardBarcodeAfterLabelSizeChange(true);
                }
                if (!hasRestorableBarcodeLayout() && typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
            }, 50);
        }
    }
    function clearTagOrientationClasses(innerEl) {
        if (!innerEl) return;
        innerEl.classList.remove('barcode-tag-tail-left', 'barcode-tag-tail-right', 'barcode-tag-jewelry');
    }

    /** Keep labelWidthMm / labelHeightMm in sync when user changes Label Size (PHP defaults are only initial). */
    function syncLabelMmFromPreset(val) {
        if (!val) return;
        var cw = document.getElementById('barcodeCustomWidthMm');
        var ch = document.getElementById('barcodeCustomHeightMm');
        if (val === '120x50') {
            labelWidthMm = 120;
            labelHeightMm = 50;
            if (cw) cw.value = 120;
            if (ch) ch.value = 50;
        } else if (val === '82x38_2box') {
            labelWidthMm = 82;
            labelHeightMm = 38;
            if (cw) cw.value = 82;
            if (ch) ch.value = 38;
        } else if (val === '250x120') {
            labelWidthMm = 250;
            labelHeightMm = 120;
            if (cw) cw.value = 250;
            if (ch) ch.value = 120;
        } else if (val === 'custom') {
            var defW = 100;
            var defH = 18;
            labelWidthMm = (cw && cw.value !== '') ? parseFloat(cw.value) || defW : defW;
            labelHeightMm = (ch && ch.value !== '') ? parseFloat(ch.value) || defH : defH;
        } else if (val !== 'zebra-zpl') {
            var parts = val.split('x');
            if (parts.length >= 2) {
                labelWidthMm = parseInt(parts[0], 10) || 100;
                labelHeightMm = parseInt(parts[1], 10) || 18;
                if (cw) cw.value = labelWidthMm;
                if (ch) ch.value = labelHeightMm;
            }
        }
    }

    function hasRestorableBarcodeLayout() {
        if (!savedDesignLayout || !String(savedDesignLayout).trim()) return false;
        try {
            var p = JSON.parse(savedDesignLayout);
            if (!p || typeof p !== 'object') return false;
            if (p.preview_box1_left != null || p.preview_box1_top != null) return true;
            if (p.layout_type === '82x38_2box' || p.sticker_82x38_2box || p.box1_left_mm != null) return true;
            if (p.barcode1_left != null || p.barcode1_top != null) return true;
            if (p.barcode_left != null || p.barcode_top != null) return true;
            var items = p.fields || p.items || [];
            return Array.isArray(items) && items.length > 0;
        } catch (e) {
            return false;
        }
    }

    var _barcodeLayoutRestoreDone = false;
    function resetBarcodeLayoutRestoreFlag() {
        _barcodeLayoutRestoreDone = false;
    }
    function shouldPreferMmBarcodeRestore(parsed) {
        if (!parsed || is82x38TwoBoxPreset() || isDualCanvasLayoutPreset()) return false;
        return parsed.barcode1_left != null && parsed.barcode1_top != null;
    }
    function restoreInnerBarcodePositionFromSaved() {
        if (is82x38TwoBoxPreset() || !hasRestorableBarcodeLayout()) return;
        try {
            var parsed = JSON.parse(savedDesignLayout);
            if (!parsed || shouldPreferMmBarcodeRestore(parsed)) return;
            restoreBarcodePosition(parsed);
        } catch (e) {}
    }
    function getBarcodeLabelsContainerEl() {
        return document.getElementById('barcodeLabelsContainer');
    }

    function getPreviewBoxPositionFromLayout(parsed) {
        if (!parsed || is82x38TwoBoxPreset() || isDualCanvasLayoutPreset()) return null;
        if (parsed.preview_box1_left != null && parsed.preview_box1_top != null) {
            return {
                left: parseFloat(parsed.preview_box1_left),
                top: parseFloat(parsed.preview_box1_top)
            };
        }
        return null;
    }

    function restorePreviewBoxPosition(parsed) {
        if (!parsed) {
            if (!hasRestorableBarcodeLayout()) return false;
            try { parsed = JSON.parse(savedDesignLayout); } catch (e) { return false; }
        }
        var pos = getPreviewBoxPositionFromLayout(parsed);
        var block1 = document.getElementById('box1');
        var container = getBarcodeLabelsContainerEl();
        if (!pos || !block1 || !container || isNaN(pos.left) || isNaN(pos.top)) return false;
        var cw = container.clientWidth || 0;
        var ch = container.clientHeight || 0;
        if (cw <= 0 || ch <= 0) return false;
        block1.classList.add('barcode-preview-positioned');
        block1.style.position = 'absolute';
        block1.style.left = Math.max(0, Math.min(cw - block1.offsetWidth, Math.round(pos.left))) + 'px';
        block1.style.top = Math.max(0, Math.min(ch - block1.offsetHeight, Math.round(pos.top))) + 'px';
        return true;
    }

    function clearPreviewBoxFlexCenter() {
        var block1 = document.getElementById('box1');
        if (!block1) return;
        block1.classList.remove('barcode-preview-positioned');
        block1.style.position = '';
        block1.style.left = '';
        block1.style.top = '';
    }

    /** One restore pass after label canvas has real px dimensions. */
    function ensureBarcodeLayoutRestoredFromSaved(retry) {
        if (_barcodeLayoutRestoreDone) return;
        retry = retry || 0;
        if (is82x38TwoBoxPreset()) {
            _barcodeLayoutRestoreDone = true;
            return;
        }
        var canvas1 = labelCanvas1 || document.getElementById('labelCanvas1');
        var labelsContainer = getBarcodeLabelsContainerEl();
        var cW = canvas1 ? canvas1.offsetWidth : 0;
        var cH = canvas1 ? canvas1.offsetHeight : 0;
        var lcH = labelsContainer ? labelsContainer.clientHeight : 0;
        if ((cW <= 0 || cH <= 0 || lcH <= 0) && retry < 20) {
            setTimeout(function() { ensureBarcodeLayoutRestoredFromSaved(retry + 1); }, 50);
            return;
        }
        if (typeof positionBarcodeBlocks === 'function') positionBarcodeBlocks();
        if (hasRestorableBarcodeLayout()) {
            if (typeof restoreSavedLayout === 'function') restoreSavedLayout();
        } else if (typeof refreshStandardBarcodeAfterLabelSizeChange === 'function') {
            refreshStandardBarcodeAfterLabelSizeChange(true);
        }
        if (typeof refreshCodeGraphicAfterLayoutRestore === 'function') refreshCodeGraphicAfterLayoutRestore();
        restoreInnerBarcodePositionFromSaved();
        [document.getElementById('barcode1'), document.getElementById('barcode2')].forEach(function(wrap) {
            if (!wrap) return;
            var canvasEl = wrap.closest('.barcode-label-canvas');
            if (canvasEl && typeof enforceStandardBarcodeGraphicBounds === 'function') {
                enforceStandardBarcodeGraphicBounds(wrap, canvasEl);
            }
        });
        clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
        clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
        if (typeof ensureAllBarcodeResizeHandles === 'function') ensureAllBarcodeResizeHandles();
        _barcodeLayoutRestoreDone = true;
    }

    /** Single-barcode sizes: reset position/size and re-draw after leaving 120×50 / 250×120 dual mode. */
    function refreshStandardBarcodeAfterLabelSizeChange(forceReset) {
        if (isDualCanvasLayoutPreset()) return;
        var bc1 = document.getElementById('barcode1');
        var bc2 = document.getElementById('barcode2');
        var c1 = labelCanvas1 || document.getElementById('labelCanvas1');
        if (bc2) bc2.style.display = 'none';
        if (!bc1 || !c1) return;
        var preserveSaved = !forceReset && hasRestorableBarcodeLayout();
        bc1.style.display = '';
        bc1.style.position = 'absolute';
        bc1.style.margin = '0';
        if (!preserveSaved) {
            bc1.style.boxSizing = '';
            bc1.style.width = '';
            bc1.style.height = 'auto';
            bc1.style.maxHeight = '';
            bc1.style.overflow = 'hidden';
            bc1.removeAttribute('data-display-height');
            var stripes = bc1.querySelector('.barcode-stripes');
            if (stripes) {
                stripes.style.width = '';
                stripes.style.height = '';
                stripes.style.minHeight = '';
            }
            var hMm = labelHeightMm || 18;
            if (hMm <= 20) {
                var ph = document.getElementById('propBarcodeBarHeight');
                var pw = document.getElementById('propBarcodeBarWidth');
                if (ph) ph.value = '10';
                if (pw) pw.value = '1';
            }
            bc1.style.left = '2px';
            bc1.style.top = '2px';
        }
        if (typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
        if (typeof clampBarcodeBlockIntoCanvas === 'function') clampBarcodeBlockIntoCanvas(c1, bc1);
        if (typeof ensureAllBarcodeResizeHandles === 'function') ensureAllBarcodeResizeHandles();
    }

    function applyLabelSizeToBox() {
        if (!barcodeBox || !labelSizeSelect) return;
        var val = (labelSizeSelect.value || '').trim();
        syncLabelMmFromPreset(val);
        var labelsContainer = document.getElementById('barcodeLabelsContainer');
        var label2 = document.getElementById('box2');
        var barcodeBox2 = label2 ? label2.querySelector('.barcode-default-inner') : null;
        var barcodeStripes2 = label2 ? label2.querySelector('.barcode-stripes') : null;
        
        function clearMmPrintArea(whiteEl) {
            if (!whiteEl) return;
            whiteEl.style.width = '';
            whiteEl.style.height = '';
            whiteEl.style.flex = '';
            whiteEl.style.minWidth = '';
            whiteEl.style.minHeight = '';
            whiteEl.classList.remove('barcode-print-area-mm');
            whiteEl.removeAttribute('title');
        }
        function clearTagBacking(innerEl) {
            if (!innerEl) return;
            innerEl.style.width = '';
            innerEl.style.minWidth = '';
            innerEl.style.height = '';
            innerEl.style.minHeight = '';
            innerEl.classList.remove('barcode-tag-backing');
            innerEl.removeAttribute('title');
        }
        if (!val) {
            clearTagBacking(barcodeBox);
            if (barcodeBox2) clearTagBacking(barcodeBox2);
            clearMmPrintArea(document.getElementById('labelPreview1'));
            clearMmPrintArea(document.getElementById('labelPreview2'));
            barcodeBox.classList.remove('barcode-label-short');
            if (barcodeStripesEl) {
                barcodeStripesEl.style.width = '';
                barcodeStripesEl.style.minWidth = '';
            }
            if (label2) label2.style.display = 'none';
            if (labelsContainer) {
                labelsContainer.classList.remove('two-labels', 'barcode-tag-pair-stack');
            }
            clearTagOrientationClasses(barcodeBox);
            if (barcodeBox2) clearTagOrientationClasses(barcodeBox2);
            applyDualBarcodeLayout('');
            return;
        }
        var wMm = labelWidthMm || 100;
        var hMm = labelHeightMm || 25;
        if (val === '82x38_2box') {
            wMm = 82;
            hMm = 38;
            labelWidthMm = 82;
            labelHeightMm = 38;
        } else if (val === 'custom' || val === '120x50' || val === '250x120') {
            wMm = labelWidthMm || (val === '250x120' ? 250 : (val === '120x50' ? 120 : 100));
            hMm = labelHeightMm || (val === '250x120' ? 120 : (val === '120x50' ? 50 : 18));
        } else if (val !== 'zebra-zpl') {
            var parts = val.split('x');
            if (parts.length >= 2) {
                wMm = parseInt(parts[0], 10) || 100;
                hMm = parseInt(parts[1], 10) || 25;
                labelWidthMm = wMm;
                labelHeightMm = hMm;
            }
        }
        var displayWMm = wMm > MAX_DISPLAY_WIDTH_MM ? MAX_DISPLAY_WIDTH_MM : wMm;
        var wPx = Math.round(displayWMm * MM_TO_PX);
        var hPx = Math.round(hMm * MM_TO_PX);
        
        clearTagOrientationClasses(barcodeBox);
        if (barcodeBox2) clearTagOrientationClasses(barcodeBox2);
        if (labelsContainer) labelsContainer.classList.remove('barcode-tag-pair-stack');
        var isShort = (hMm <= 20);
        var stripW = isShort ? 10 : 16;
        var handleW = isShort ? 20 : 32;
        var padH = isShort ? 20 : 36;
        var padV = isShort ? 12 : 28;
        var is82x38 = is82x38TwoBoxPreset(val);
        var isDualJewelry = isDualLabelLayoutPreset(val);
        if (isDualJewelry) {
            var dualCfgEarly = getDualPresetConfig(val);
            stripW = dualCfgEarly.stripPx;
            handleW = dualCfgEarly.handlePx;
            padH = dualCfgEarly.padH;
            padV = dualCfgEarly.padV;
            barcodeBox.classList.add('barcode-tag-jewelry');
            if (barcodeBox2) barcodeBox2.classList.add('barcode-tag-jewelry');
        } else if (is82x38) {
            if (barcodeBox) barcodeBox.classList.remove('barcode-tag-jewelry');
            if (barcodeBox2) barcodeBox2.classList.remove('barcode-tag-jewelry');
        }
        var dualTagPx = (isDualJewelry || is82x38) ? getDualTagLayoutPx(val) : null;
        var innerW = dualTagPx ? dualTagPx.innerW : (padH + stripW + wPx + handleW);
        var innerH = dualTagPx ? dualTagPx.innerH : (padV + hPx);
        var whiteWPx = dualTagPx ? dualTagPx.whiteWPx : wPx;
        var whiteHPx = dualTagPx ? dualTagPx.whiteHPx : hPx;
        var halfPrintMm = dualTagPx ? dualTagPx.halfLabelMm : displayWMm;
        function sizeTagBackingBox(innerEl, tagTitle) {
            if (!innerEl) return;
            innerEl.style.width = innerW + 'px';
            innerEl.style.minWidth = innerW + 'px';
            innerEl.style.height = innerH + 'px';
            innerEl.style.minHeight = innerH + 'px';
            innerEl.classList.add('barcode-tag-backing');
            innerEl.setAttribute('title', tagTitle || ('Grey = tag backing only. White center = printable area.'));
        }
        function applyMmToWhite(whiteEl, printTitle) {
            if (!whiteEl) return;
            whiteEl.style.width = whiteWPx + 'px';
            whiteEl.style.height = whiteHPx + 'px';
            whiteEl.style.flex = '0 0 auto';
            whiteEl.style.minWidth = whiteWPx + 'px';
            whiteEl.style.minHeight = whiteHPx + 'px';
            whiteEl.classList.add('barcode-print-area-mm');
            whiteEl.setAttribute('title', printTitle || ('Print area — design inside this white box.'));
        }
        if (isDualJewelry) {
            var dualCfgTitle = getDualPresetConfig(val);
            var tagTitle = 'One jewelry tag on ' + dualCfgTitle.stickerW + '×' + dualCfgTitle.stickerH + ' mm sticker (half width). White ≈ ' + Math.round(halfPrintMm) + '×' + dualCfgTitle.quadH + ' mm printable.';
            sizeTagBackingBox(barcodeBox, tagTitle);
            if (barcodeBox2) sizeTagBackingBox(barcodeBox2, tagTitle);
            applyMmToWhite(document.getElementById('labelPreview1'), 'Left tag printable area');
            applyMmToWhite(document.getElementById('labelPreview2'), 'Right tag printable area');
        } else if (is82x38) {
            clearTagBacking(barcodeBox);
            if (barcodeBox2) clearTagBacking(barcodeBox2);
            clearMmPrintArea(document.getElementById('labelPreview1'));
            clearMmPrintArea(document.getElementById('labelPreview2'));
        } else {
            sizeTagBackingBox(barcodeBox);
            applyMmToWhite(document.getElementById('labelPreview1'));
            clearMmPrintArea(document.getElementById('labelPreview2'));
        }
        if (isShort) {
            barcodeBox.classList.add('barcode-label-short');
            if (barcodeStripesEl) {
                barcodeStripesEl.style.width = '';
                barcodeStripesEl.style.minWidth = '40px';
            }
        } else {
            barcodeBox.classList.remove('barcode-label-short');
            if (barcodeStripesEl) {
                barcodeStripesEl.style.width = '';
                barcodeStripesEl.style.minWidth = '40px';
            }
        }
        
        applyDualBarcodeLayout(val);
        if (is82x38) {
            setTimeout(function() {
                render82x38DualEditor();
            }, 80);
        } else if (isDualCanvasLayoutPreset(val)) {
            setTimeout(function() {
                syncDualCanvasHeight();
                if (typeof ensureDualBarcodesRendered === 'function') {
                    ensureDualBarcodesRendered({ preservePositions: false });
                }
            }, 120);
        } else {
            setTimeout(function() {
                if (typeof positionBarcodeBlocks === 'function') positionBarcodeBlocks();
                if (!hasRestorableBarcodeLayout() && typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
            }, 80);
        }
    }

    function showBarcodeCustomSizeFields() {
        var v = labelSizeSelect ? (labelSizeSelect.value || '').trim() : '';
        return v === 'custom' || v === '120x50' || v === '250x120' || v === '82x38_2box';
    }
    if (labelSizeSelect) {
        var wrapW = document.getElementById('barcodeCustomSizeWrap');
        var wrapH = document.getElementById('barcodeCustomHeightWrap');
        var showMm = showBarcodeCustomSizeFields();
        if (wrapW) wrapW.style.display = showMm ? 'flex' : 'none';
        if (wrapH) wrapH.style.display = showMm ? 'flex' : 'none';
    }

    function getDropLayerRect() {
        return dropLayer.getBoundingClientRect();
    }

    /** Must return labelCanvas1/2 (where .canvas-dropped-item nodes live), not labelPreview — otherwise parent !== drop target and drag logic clones fields. */
    function getLabelContainerAt(x, y) {
        if (!labelPreview1 && !labelPreview2) return labelCanvas1 || labelCanvas2;
        var r1 = labelPreview1 ? labelPreview1.getBoundingClientRect() : null;
        var r2 = labelPreview2 && document.getElementById('box2').style.display !== 'none'
            ? labelPreview2.getBoundingClientRect() : null;
        var in1 = r1 && x >= r1.left && x <= r1.right && y >= r1.top && y <= r1.bottom;
        var in2 = r2 && x >= r2.left && x <= r2.right && y >= r2.top && y <= r2.bottom;
        if (in1 && in2) return (Math.abs(x - (r1.left + r1.width / 2)) <= Math.abs(x - (r2.left + r2.width / 2))) ? labelCanvas1 : labelCanvas2;
        if (in1) return labelCanvas1;
        if (in2) return labelCanvas2;
        if (r1 && r2) {
            var d1 = Math.pow(x - (r1.left + r1.width / 2), 2) + Math.pow(y - (r1.top + r1.height / 2), 2);
            var d2 = Math.pow(x - (r2.left + r2.width / 2), 2) + Math.pow(y - (r2.top + r2.height / 2), 2);
            return d1 <= d2 ? labelCanvas1 : labelCanvas2;
        }
        return labelCanvas1 || labelCanvas2;
    }

    function handleDrop(e, rect, container) {
        e.preventDefault();
        if (!rect || !container) return;
        var left = e.clientX - rect.left;
        var top = e.clientY - rect.top;
        var movingId = e.dataTransfer.getData('application/x-canvas-item');
        var isDropOnCanvas = (container === dropLayer);

        /* 82×38: half white strips win over nearest green-box remap (columns belong on the strips). */
        if (typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset() && !is82x38HalfStripContainer(container)) {
            var hsPrefer = get82x38HalfStripAtPoint(e.clientX, e.clientY);
            if (hsPrefer) {
                container = hsPrefer;
                rect = hsPrefer.getBoundingClientRect();
                left = e.clientX - rect.left;
                top = e.clientY - rect.top;
                isDropOnCanvas = false;
            }
        }

        if (labelPreview1 && isDropOnCanvas) {
            container = getLabelContainerAt(e.clientX, e.clientY);
            rect = container.getBoundingClientRect();
            left = e.clientX - rect.left;
            top = e.clientY - rect.top;
            if (left < 0 || top < 0 || left > rect.width || top > rect.height) {
                left = 25;
                top = 20;
            }
        }

        if (movingId) {
            var moving = document.querySelector('.canvas-dropped-item[data-id="' + movingId + '"]');
            if (moving) {
                var oldContainer = moving.parentElement;
                if (oldContainer !== container) {
                    moving.parentNode.removeChild(moving);
                    container.appendChild(moving);
                    /* Only mirror field onto the other label when two blocks are visible (otherwise this created duplicate "Barcode" on every cross-parent drag). */
                    if (typeof isSecondBlockVisible === 'function' && isSecondBlockVisible()) {
                        var isLabel1 = function(c) { return c === labelCanvas1 || c === labelPreview1; };
                        var isLabel2 = function(c) { return c === labelCanvas2 || c === labelPreview2; };
                        if ((isLabel1(oldContainer) || isLabel2(oldContainer)) && (isLabel1(container) || isLabel2(container))) {
                            var fn = moving.getAttribute('data-field');
                            if (!isStripLineField(fn) && !isWhiteStripField(fn) && !moving.classList.contains('canvas-white-strip')) {
                                var defLeft = 25, defTop = 40;
                                createDroppedItem(fn, defLeft + 10, defTop + 10, oldContainer, {
                                    prefix: moving.getAttribute('data-prefix'),
                                    suffix: moving.getAttribute('data-suffix'),
                                    font: moving.getAttribute('data-font'),
                                    font_size: moving.getAttribute('data-font-size')
                                });
                            }
                        }
                    }
                }
                if (isStripLineField(moving.getAttribute('data-field')) || moving.classList.contains('canvas-strip-line')) {
                    if (moving.getAttribute('data-strip-scope') === 'sticker' || moving.classList.contains('canvas-strip-line-sticker') || is82x38TwoBoxPreset()) {
                        var shellMove = document.getElementById('barcodeDualStickerShell');
                        var hostMove = get82x38StripHost();
                        if (hostMove && moving.parentElement !== hostMove) {
                            hostMove.appendChild(moving);
                        }
                        var scMove = get82x38StickerMmScale();
                        var topMmMove = 6;
                        if (shellMove) {
                            var sr = shellMove.getBoundingClientRect();
                            topMmMove = clampMmGlobal((e.clientY - sr.top) * scMove.pxToMmY, 38);
                        }
                        moving.style.left = '0px';
                        moving.style.top = Math.round(topMmMove * scMove.mmToPxY) + 'px';
                        moving.style.width = Math.round(82 * scMove.mmToPxX) + 'px';
                        moving.classList.add('canvas-strip-line-sticker');
                        moving.setAttribute('data-strip-scope', 'sticker');
                    } else {
                        var moveContW = (container && container.clientWidth > 0) ? container.clientWidth : 120;
                        moving.style.left = '2px';
                        moving.style.top = Math.max(0, top - 5) + 'px';
                        if (!moving.style.width) moving.style.width = Math.max(24, moveContW - 4) + 'px';
                    }
                } else if (moving.classList.contains('canvas-white-strip') || isWhiteStripField(moving.getAttribute('data-field'))) {
                    var wsTarget = resolveWhiteStripDropTarget(e.clientX, e.clientY, container) || container;
                    if (wsTarget && moving.parentElement !== wsTarget
                        && (wsTarget.id === 'labelCanvas1' || wsTarget.id === 'labelCanvas2' || wsTarget.id === 'labelPreview1' || wsTarget.id === 'labelPreview2')) {
                        wsTarget.appendChild(moving);
                        container = wsTarget;
                    }
                    var tr = container.getBoundingClientRect();
                    var nl = Math.round(e.clientX - tr.left - moving.offsetWidth / 2);
                    var nt = Math.round(e.clientY - tr.top - moving.offsetHeight / 2);
                    moving.style.left = Math.max(0, nl) + 'px';
                    moving.style.top = Math.max(0, nt) + 'px';
                    moving.setAttribute('data-box', String(getWhiteStripBoxNumFromContainer(container)));
                    clampWhiteStripInParent(moving);
                    syncWhiteStripPropsFromEl(moving);
                } else if (is82x38HalfStripContainer(container) || (moving.getAttribute('data-strip-slot') && get82x38HalfStripAtPoint(e.clientX, e.clientY))) {
                    var hsMove = is82x38HalfStripContainer(container) ? container : get82x38HalfStripAtPoint(e.clientX, e.clientY);
                    if (hsMove && moving.parentElement !== hsMove) {
                        hsMove.appendChild(moving);
                        container = hsMove;
                    }
                    if (hsMove) {
                        var hsr = hsMove.getBoundingClientRect();
                        moving.style.left = Math.max(0, Math.round(e.clientX - hsr.left - 6)) + 'px';
                        moving.style.top = Math.max(0, Math.round(e.clientY - hsr.top - 6)) + 'px';
                        moving.setAttribute('data-strip-slot', hsMove.getAttribute('data-fixed-half-strip') || 'top-left');
                        clampDroppedItemInHalfStrip(moving, hsMove);
                    }
                } else {
                    moving.style.left = (left - 10) + 'px';
                    moving.style.top = (top - 10) + 'px';
                }
            }
            return;
        }

        var fieldName = e.dataTransfer.getData('text/plain');
        if (fieldName) {
            /* 82×38 half strips: drop columns (CompanyName, Qty, Wt, …) onto the white strip bands */
            var halfStripTarget = is82x38HalfStripContainer(container) ? container : get82x38HalfStripAtPoint(e.clientX, e.clientY);
            if (halfStripTarget && !isStripLineField(fieldName) && !isWhiteStripField(fieldName) && is82x38TwoBoxPreset()) {
                var hsRect = halfStripTarget.getBoundingClientRect();
                var hsLeft = e.clientX - hsRect.left;
                var hsTop = e.clientY - hsRect.top;
                halfStripTarget.querySelectorAll('.canvas-dropped-item[data-field="' + fieldName + '"]').forEach(function(el, i) {
                    if (i > 0) el.remove();
                });
                var existingHs = halfStripTarget.querySelector('.canvas-dropped-item[data-field="' + fieldName + '"]');
                if (existingHs) {
                    existingHs.style.left = Math.max(0, hsLeft - 6) + 'px';
                    existingHs.style.top = '0px';
                    clampDroppedItemInHalfStrip(existingHs, halfStripTarget);
                    syncPropertiesPanelFromDroppedItem(existingHs);
                } else {
                    var newHs = createDroppedItem(fieldName, hsLeft, 0, halfStripTarget, {
                        font_size: (document.getElementById('propFontSize') && document.getElementById('propFontSize').value) || '8'
                    });
                    if (newHs) {
                        newHs.setAttribute('data-strip-slot', halfStripTarget.getAttribute('data-fixed-half-strip') || 'top-left');
                        newHs.style.top = '0px';
                        clampDroppedItemInHalfStrip(newHs, halfStripTarget);
                        syncPropertiesPanelFromDroppedItem(newHs);
                    }
                }
                syncToolboxHighlight();
                clearBarcodeDragDecorations();
                clearBarcodeSelection();
                return;
            }
            /* 82×38: StripLine goes on the outer sticker (red-mark zones), not inside the green boxes */
            if (isStripLineField(fieldName) && is82x38TwoBoxPreset()) {
                var shell = document.getElementById('barcodeDualStickerShell');
                var host = get82x38StripHost();
                var scDrop = get82x38StickerMmScale();
                var topMmDrop = 6;
                if (shell) {
                    var shellRect = shell.getBoundingClientRect();
                    topMmDrop = clampMmGlobal((e.clientY - shellRect.top) * scDrop.pxToMmY, 38);
                } else if (rect && rect.top != null) {
                    topMmDrop = clampMmGlobal((e.clientY - rect.top) * scDrop.pxToMmY, 38);
                }
                addStripLineTo82x38Sticker({ top_mm: topMmDrop, left_mm: 0, width_mm: 82 });
                syncToolboxHighlight();
                clearBarcodeDragDecorations();
                clearBarcodeSelection();
                return;
            }
            /* White Strip: create inside the barcode print box under the pointer */
            if (isWhiteStripField(fieldName)) {
                var wsContainer = resolveWhiteStripDropTarget(e.clientX, e.clientY, container) || getWhiteStripPrintCanvas(1);
                var wsRect = wsContainer.getBoundingClientRect();
                var dropL = e.clientX - wsRect.left;
                var dropT = e.clientY - wsRect.top;
                var newWs = addWhiteStripToTarget(wsContainer, dropL, dropT, {
                    clientX: e.clientX,
                    clientY: e.clientY,
                    box: getWhiteStripBoxNumFromContainer(wsContainer)
                });
                if (newWs) syncPropertiesPanelFromDroppedItem(newWs);
                syncToolboxHighlight();
                clearBarcodeDragDecorations();
                clearBarcodeSelection();
                return;
            }
            container.querySelectorAll('.canvas-dropped-item[data-field="' + fieldName + '"]').forEach(function(el, i) {
                if (i > 0) el.remove();
            });
            var existingDrop = container.querySelector('.canvas-dropped-item[data-field="' + fieldName + '"]');
            if (existingDrop) {
                if (isStripLineField(fieldName) || existingDrop.classList.contains('canvas-strip-line')) {
                    var existContW = (container && container.clientWidth > 0) ? container.clientWidth : 120;
                    existingDrop.style.left = '2px';
                    existingDrop.style.top = Math.max(0, top - 5) + 'px';
                    existingDrop.style.width = Math.max(24, existContW - 4) + 'px';
                } else {
                    existingDrop.style.left = (left - 10) + 'px';
                    existingDrop.style.top = (top - 10) + 'px';
                }
                syncPropertiesPanelFromDroppedItem(existingDrop);
            } else {
                var newEl = createDroppedItem(fieldName, left, top, container);
                syncPropertiesPanelFromDroppedItem(newEl);
                if (!(isStripLineField(fieldName) && is82x38TwoBoxPreset()) && !is82x38HalfStripContainer(container)) {
                    addFieldToOtherBlock(fieldName, container, newEl.getAttribute('data-prefix'), newEl.getAttribute('data-suffix'), newEl.getAttribute('data-font'), newEl.getAttribute('data-font-size'), newEl.getAttribute('data-pad-top'), newEl.getAttribute('data-pad-right'), newEl.getAttribute('data-pad-bottom'), newEl.getAttribute('data-pad-left'));
                }
            }
            syncToolboxHighlight();
        }
        clearBarcodeDragDecorations();
        clearBarcodeSelection();
    }

    function clampDroppedItemInHalfStrip(el, stripEl) {
        if (!el || !stripEl) return;
        var pw = stripEl.clientWidth || 0;
        var ph = stripEl.clientHeight || 0;
        if (pw <= 0 || ph <= 0) return;
        var left = parseInt(el.style.left, 10); if (isNaN(left)) left = 0;
        var top = parseInt(el.style.top, 10); if (isNaN(top)) top = 0;
        var w = el.offsetWidth || 40;
        var h = el.offsetHeight || 12;
        left = Math.max(0, Math.min(left, Math.max(0, pw - Math.min(w, pw))));
        top = Math.max(0, Math.min(top, Math.max(0, ph - Math.min(h, ph))));
        el.style.left = Math.round(left) + 'px';
        el.style.top = Math.round(top) + 'px';
    }
    function collect82x38HalfStripFields() {
        var out = [];
        if (!is82x38TwoBoxPreset()) return out;
        document.querySelectorAll('.barcode-82x38-fixed-half-strip').forEach(function(strip) {
            var slot = strip.getAttribute('data-fixed-half-strip') || 'top-left';
            var geo = get82x38HalfStripGeometry(slot);
            var sw = strip.clientWidth > 0 ? strip.clientWidth : Math.max(1, geo.width * MM_TO_PX);
            var sh = strip.clientHeight > 0 ? strip.clientHeight : Math.max(1, geo.height * MM_TO_PX);
            var pxToMmX = geo.width / sw;
            var pxToMmY = geo.height / sh;
            strip.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
                if (isStripLineField(item.getAttribute('data-field')) || item.classList.contains('canvas-strip-line')) return;
                if (isWhiteStripField(item.getAttribute('data-field')) || item.classList.contains('canvas-white-strip')) return;
                var left = parseInt(item.style.left, 10); if (isNaN(left)) left = 0;
                var top = parseInt(item.style.top, 10); if (isNaN(top)) top = 0;
                out.push({
                    type: 'text',
                    strip: slot,
                    field: item.getAttribute('data-field') || '',
                    left: Math.round(left * pxToMmX * 100) / 100,
                    top: Math.round(top * pxToMmY * 100) / 100,
                    prefix: item.getAttribute('data-prefix') || '',
                    suffix: item.getAttribute('data-suffix') || '',
                    font: item.getAttribute('data-font') || 'Arial',
                    font_size: parseInt(item.getAttribute('data-font-size'), 10) || 8
                });
            });
        });
        return out;
    }
    function restore82x38HalfStripFields(parsed) {
        if (!is82x38TwoBoxPreset()) return;
        ensure82x38FixedHalfWhiteStrips();
        document.querySelectorAll('.barcode-82x38-fixed-half-strip .canvas-dropped-item').forEach(function(el) {
            el.remove();
        });
        var fields = [];
        if (parsed && Array.isArray(parsed.sticker_half_strip_fields)) {
            fields = parsed.sticker_half_strip_fields;
        } else if (parsed && Array.isArray(parsed.half_strip_fields)) {
            fields = parsed.half_strip_fields;
        }
        fields.forEach(function(it) {
            if (!it || !it.field) return;
            if (isStripLineField(it.field) || isWhiteStripField(it.field)) return;
            var slot = it.strip || it.slot || 'top-left';
            if (slot === 'mid-lower') slot = 'bottom-right';
            var strip = document.querySelector('.barcode-82x38-fixed-half-strip[data-fixed-half-strip="' + slot + '"]');
            if (!strip) return;
            var geo = get82x38HalfStripGeometry(slot);
            var sw = strip.clientWidth > 0 ? strip.clientWidth : Math.max(1, geo.width * MM_TO_PX);
            var sh = strip.clientHeight > 0 ? strip.clientHeight : Math.max(1, geo.height * MM_TO_PX);
            var mmToPxX = sw / geo.width;
            var mmToPxY = sh / geo.height;
            var leftPx = Math.round((parseFloat(it.left) || 0) * mmToPxX);
            var topPx = Math.round((parseFloat(it.top) || 0) * mmToPxY);
            var el = createDroppedItem(it.field, leftPx + 10, topPx + 10, strip, {
                restore: true,
                left: leftPx,
                top: topPx,
                prefix: it.prefix,
                suffix: it.suffix,
                font: it.font,
                font_size: it.font_size != null ? it.font_size : 8
            });
            if (el) {
                el.setAttribute('data-strip-slot', slot);
                clampDroppedItemInHalfStrip(el, strip);
            }
        });
    }

    function isBarcodeGraphicClickTarget(el) {
        if (!el) return false;
        return !!(el.closest('.barcode-stripes') || el.closest('.qr-code-preview') || el.closest('.barcode-resize-handle'));
    }

    function clearBarcodeDragDecorations() {
        if (dropLayer) dropLayer.classList.remove('drag-over');
        document.querySelectorAll('.barcode-white-drop-zone').forEach(function(z) {
            z.classList.remove('drag-over', 'dragging-active');
        });
        document.querySelectorAll('.barcode-82x38-fixed-half-strip').forEach(function(z) {
            z.classList.remove('drag-over');
        });
        document.body.classList.remove('is-barcode-dragging');
        [labelCanvas1, labelCanvas2].forEach(function(c) {
            if (c) c.classList.remove('is-toolbox-dragging');
        });
    }

    function syncBarcodeResizeHandleVisibility() {
        document.querySelectorAll('.barcode-print-wrap').forEach(function(w) {
            var h = w.querySelector('.barcode-resize-handle');
            if (h) h.style.display = w.classList.contains('barcode-selected') ? 'block' : 'none';
        });
    }

    function clearBarcodeSelection() {
        document.querySelectorAll('.barcode-print-wrap, .barcode-inner-draggable').forEach(function(w) {
            w.classList.remove('barcode-selected');
        });
        syncBarcodeResizeHandleVisibility();
    }

    /** Preview sample value for a toolbox field (from PHP sample_field_preview). */
    function resolveSampleFieldValue(fieldName) {
        if (!fieldName || !sampleFieldPreview) return null;
        if (Object.prototype.hasOwnProperty.call(sampleFieldPreview, fieldName)) {
            var v = sampleFieldPreview[fieldName];
            if (v !== null && v !== undefined && String(v) !== '') return String(v);
        }
        return null;
    }
    /**
     * Canvas preview: if Prefix and/or Suffix are set, show only that text (what you typed in Properties).
     * If both are empty, show sample data so you can still preview real values. Printed labels still use renderBarcodeLayout() + DB data.
     */
    function updateDroppedItemDisplay(el) {
        var textEl = el && el.querySelector('.canvas-item-text');
        if (!textEl) return;
        var field = (el.getAttribute('data-field') || '').trim();
        var prefix = (el.getAttribute('data-prefix') || '').trim();
        var suffix = (el.getAttribute('data-suffix') || '').trim();
        if (prefix !== '' || suffix !== '') {
            var parts = [];
            if (prefix !== '') parts.push(prefix);
            if (suffix !== '') parts.push(suffix);
            textEl.textContent = parts.join(' ');
            return;
        }
        var val = resolveSampleFieldValue(field);
        if (val === null) val = field || '';
        textEl.textContent = String(val);
    }
    function applyDroppedItemFont(el) {
        var textEl = el && el.querySelector('.canvas-item-text');
        if (!textEl) return;
        var font = el.getAttribute('data-font') || 'Arial';
        var size = el.getAttribute('data-font-size') || '10';
        textEl.style.fontFamily = font;
        textEl.style.fontSize = (size === '' ? 10 : parseInt(size, 10)) + 'px';
    }

    var labelPreview2 = document.getElementById('labelPreview2');
    
    function syncToolboxHighlight() {
        var onLabelFields = {};
        [labelPreview1, labelPreview2, get82x38StripHost(), document.getElementById('barcode82x38FixedStrips')].forEach(function(cont) {
            if (!cont) return;
            cont.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
                var f = item.getAttribute('data-field');
                if (f) onLabelFields[f] = true;
            });
        });
        document.querySelectorAll('.toolbox-field-item').forEach(function(item) {
            var f = item.getAttribute('data-field');
            if (f && onLabelFields[f]) item.classList.add('on-label');
            else item.classList.remove('on-label');
        });
    }
    
    function isSecondBlockVisible() {
        var label2 = document.getElementById('box2');
        return label2 && label2.style.display !== 'none' && labelPreview2;
    }
    
    function addFieldToOtherBlock(fieldName, sourceContainer, prefix, suffix, font, fontSize, padTop, padRight, padBottom, padLeft) {
        if (isStripLineField(fieldName) || isWhiteStripField(fieldName)) return;
        if (!isSecondBlockVisible()) return;
        var otherContainer = (sourceContainer === labelCanvas1 || sourceContainer === labelPreview1) ? (labelCanvas2 || labelPreview2) : (labelCanvas1 || labelPreview1);
        if (otherContainer.querySelector('.canvas-dropped-item[data-field="' + fieldName + '"]')) return;
        var defaultLeft = 25;
        var defaultTop = 40;
        createDroppedItem(fieldName, defaultLeft + 10, defaultTop + 10, otherContainer, {
            prefix: prefix,
            suffix: suffix,
            font: font || 'Arial',
            font_size: fontSize || '10',
            pad_top: padTop !== undefined && padTop !== null && padTop !== '' ? padTop : 0,
            pad_right: padRight !== undefined && padRight !== null && padRight !== '' ? padRight : 0,
            pad_bottom: padBottom !== undefined && padBottom !== null && padBottom !== '' ? padBottom : 0,
            pad_left: padLeft !== undefined && padLeft !== null && padLeft !== '' ? padLeft : 0
        });
    }
    
    function removeFieldFromOtherBlock(fieldName, fromContainer) {
        if (is82x38HalfStripContainer(fromContainer)) return;
        var otherContainer = (fromContainer === labelCanvas1 || fromContainer === labelPreview1) ? (labelCanvas2 || labelPreview2) : (labelCanvas1 || labelPreview1);
        if (!otherContainer) return;
        var other = otherContainer.querySelector('.canvas-dropped-item[data-field="' + fieldName + '"]');
        if (other) other.remove();
    }
    
    function syncToSecondLabel() {
        /* Used for property sync; add/remove sync is done in addFieldToOtherBlock / remove handler */
    }
    
    (function _removedSyncClone() {
        if (!labelPreview2) return;
        var label2 = document.getElementById('box2');
        if (!label2 || label2.style.display === 'none') return;
        var barcodePrintWrap1 = document.getElementById('barcode1');
        var barcodePrintWrap2 = document.getElementById('barcode2');
        if (barcodePrintWrap1 && barcodePrintWrap2) {
            var stripes1 = barcodePrintWrap1.querySelector('.barcode-stripes');
            var stripes2 = barcodePrintWrap2.querySelector('.barcode-stripes');
            if (stripes1 && stripes2) {
                stripes2.style.width = stripes1.style.width;
                stripes2.style.minHeight = stripes1.style.minHeight;
            }
        }
    })();
    
    function isStripLineField(fieldName) {
        return String(fieldName || '').toLowerCase() === 'stripline';
    }
    function get82x38StripHost() {
        return document.getElementById('barcode82x38StripLayer')
            || document.getElementById('barcodeDualStickerShell');
    }
    function get82x38StickerMmScale() {
        var host = get82x38StripHost() || document.getElementById('barcodeDualStickerShell');
        var w = (host && host.clientWidth > 0) ? host.clientWidth : Math.round(82 * MM_TO_PX);
        var h = (host && host.clientHeight > 0) ? host.clientHeight : Math.round(38 * MM_TO_PX);
        return {
            mmW: 82,
            mmH: 38,
            pxToMmX: 82 / w,
            pxToMmY: 38 / h,
            mmToPxX: w / 82,
            mmToPxY: h / 38,
            hostW: w,
            hostH: h
        };
    }
    function next82x38StripTopMm() {
        var host = get82x38StripHost();
        var count = host ? host.querySelectorAll('.canvas-strip-line').length : 0;
        /* Defaults match typical empty zones on 82×38 (top band + mid fold). */
        var defaults = [6, 19, 28];
        return defaults[Math.min(count, defaults.length - 1)];
    }
    function addStripLineTo82x38Sticker(opts) {
        opts = opts || {};
        if (!is82x38TwoBoxPreset()) return null;
        var host = get82x38StripHost();
        if (!host) return null;
        var sc = get82x38StickerMmScale();
        var topMm = opts.top_mm != null ? parseFloat(opts.top_mm) : next82x38StripTopMm();
        if (isNaN(topMm)) topMm = next82x38StripTopMm();
        topMm = Math.max(0, Math.min(36, topMm));
        var leftMm = opts.left_mm != null ? parseFloat(opts.left_mm) : 0;
        if (isNaN(leftMm)) leftMm = 0;
        var widthMm = opts.width_mm != null ? parseFloat(opts.width_mm) : 82;
        if (isNaN(widthMm) || widthMm < 2) widthMm = 82;
        widthMm = Math.max(2, Math.min(82 - leftMm, widthMm));
        var leftPx = Math.round(leftMm * sc.mmToPxX);
        var topPx = Math.round(topMm * sc.mmToPxY);
        var widthPx = Math.round(widthMm * sc.mmToPxX);
        var el = createDroppedItem('StripLine', leftPx, topPx, host, {
            restore: true,
            left: leftPx,
            top: topPx,
            width: widthPx,
            prefix: '',
            suffix: '',
            skipOtherBlock: true
        });
        if (el) {
            el.classList.add('canvas-strip-line-sticker');
            el.setAttribute('data-strip-scope', 'sticker');
            syncPropertiesPanelFromDroppedItem(el);
            syncToolboxHighlight();
        }
        return el;
    }
    function collect82x38StickerStripLines() {
        var host = get82x38StripHost();
        if (!host || !is82x38TwoBoxPreset()) return [];
        var sc = get82x38StickerMmScale();
        var out = [];
        host.querySelectorAll('.canvas-strip-line').forEach(function(item) {
            var left = parseInt(item.style.left, 10);
            var top = parseInt(item.style.top, 10);
            var wPx = parseInt(item.style.width, 10);
            if (isNaN(left)) left = 0;
            if (isNaN(top)) top = 0;
            if (isNaN(wPx) || wPx < 8) wPx = item.offsetWidth || sc.hostW;
            out.push({
                type: 'strip_line',
                field: 'StripLine',
                scope: 'sticker',
                left: clampMmGlobal(left * sc.pxToMmX, 82),
                top: clampMmGlobal(top * sc.pxToMmY, 38),
                width: clampMmGlobal(wPx * sc.pxToMmX, 82)
            });
        });
        return out;
    }
    function restore82x38StickerStripLines(parsed) {
        var host = get82x38StripHost();
        if (!host || !is82x38TwoBoxPreset()) return;
        host.querySelectorAll('.canvas-strip-line').forEach(function(el) { el.remove(); });
        var lines = [];
        if (parsed && Array.isArray(parsed.sticker_strip_lines)) {
            lines = parsed.sticker_strip_lines;
        } else if (parsed && Array.isArray(parsed.strip_lines)) {
            lines = parsed.strip_lines;
        }
        lines.forEach(function(it) {
            if (!it) return;
            addStripLineTo82x38Sticker({
                top_mm: it.top != null ? it.top : it.top_mm,
                left_mm: it.left != null ? it.left : it.left_mm,
                width_mm: it.width != null ? it.width : it.width_mm
            });
        });
    }

    function isWhiteStripField(fieldName) {
        var f = String(fieldName || '').toLowerCase().replace(/\s+/g, '');
        return f === 'whitestrip' || f === 'blankstrip' || f === 'whitestripblank';
    }
    function isWhiteStripType(it) {
        if (!it) return false;
        var t = String(it.type || '').toLowerCase().replace(/\s+/g, '');
        return t === 'white_strip' || t === 'whitestrip' || isWhiteStripField(it.field);
    }
    function showWhiteStripToast(msg) {
        var t = document.getElementById('barcodeWsToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'barcodeWsToast';
            t.className = 'barcode-ws-toast';
            document.body.appendChild(t);
        }
        t.textContent = msg || 'White Strip added';
        t.classList.add('is-visible');
        clearTimeout(showWhiteStripToast._timer);
        showWhiteStripToast._timer = setTimeout(function() { t.classList.remove('is-visible'); }, 1800);
    }
    function getWhiteStripBoxNumFromContainer(container) {
        if (!container) return 1;
        if (container.id === 'labelCanvas2' || container.id === 'labelPreview2' || (container.closest && container.closest('#box2'))) return 2;
        return 1;
    }
    function getWhiteStripPrintCanvas(boxNum) {
        boxNum = boxNum === 2 ? 2 : 1;
        if (boxNum === 2) return labelCanvas2 || document.getElementById('labelCanvas2') || labelPreview2 || document.getElementById('labelPreview2');
        return labelCanvas1 || document.getElementById('labelCanvas1') || labelPreview1 || document.getElementById('labelPreview1');
    }
    function resolveWhiteStripDropTarget(clientX, clientY, fallbackContainer) {
        var el = document.elementFromPoint(clientX, clientY);
        if (el) {
            var c2 = el.closest && (el.closest('#labelCanvas2') || el.closest('#box2') || el.closest('#labelPreview2'));
            if (c2) return getWhiteStripPrintCanvas(2);
            var c1 = el.closest && (el.closest('#labelCanvas1') || el.closest('#box1') || el.closest('#labelPreview1'));
            if (c1) return getWhiteStripPrintCanvas(1);
        }
        var b2 = document.getElementById('box2');
        if (b2 && b2.style.display !== 'none') {
            var r2 = b2.getBoundingClientRect();
            if (clientX >= r2.left && clientX <= r2.right && clientY >= r2.top && clientY <= r2.bottom) {
                return getWhiteStripPrintCanvas(2);
            }
        }
        var b1 = document.getElementById('box1');
        if (b1) {
            var r1 = b1.getBoundingClientRect();
            if (clientX >= r1.left && clientX <= r1.right && clientY >= r1.top && clientY <= r1.bottom) {
                return getWhiteStripPrintCanvas(1);
            }
        }
        if (fallbackContainer && (fallbackContainer.id === 'labelCanvas1' || fallbackContainer.id === 'labelCanvas2'
            || fallbackContainer.id === 'labelPreview1' || fallbackContainer.id === 'labelPreview2')) {
            return fallbackContainer.id.indexOf('2') >= 0 ? getWhiteStripPrintCanvas(2) : getWhiteStripPrintCanvas(1);
        }
        var selectedBox = document.querySelector('.barcode-preview-block.barcode-selected, #box1.barcode-selected, #box2.barcode-selected');
        if (selectedBox && selectedBox.id === 'box2') return getWhiteStripPrintCanvas(2);
        return getWhiteStripPrintCanvas(1);
    }
    function clampWhiteStripInParent(el) {
        if (!el || !el.parentElement) return;
        var parent = el.parentElement;
        var pw = parent.clientWidth || 0;
        var ph = parent.clientHeight || 0;
        if (pw <= 0 || ph <= 0) return;
        var left = parseInt(el.style.left, 10); if (isNaN(left)) left = 0;
        var top = parseInt(el.style.top, 10); if (isNaN(top)) top = 0;
        var w = parseInt(el.style.width, 10); if (isNaN(w) || w < 10) w = Math.min(250, Math.max(10, pw - 4));
        var h = parseInt(el.style.height, 10); if (isNaN(h) || h < 5) h = Math.min(40, Math.max(5, ph - 4));
        w = Math.min(w, pw);
        h = Math.min(h, ph);
        left = Math.max(0, Math.min(left, pw - w));
        top = Math.max(0, Math.min(top, ph - h));
        el.style.left = Math.round(left) + 'px';
        el.style.top = Math.round(top) + 'px';
        el.style.width = Math.round(w) + 'px';
        el.style.height = Math.round(h) + 'px';
    }
    function applyWhiteStripStyle(el) {
        if (!el || !el.classList.contains('canvas-white-strip')) return;
        var bg = el.getAttribute('data-bg') || el.getAttribute('data-background-color') || '#FFFFFF';
        var borderEnabled = el.getAttribute('data-border-enabled') === '1' || el.getAttribute('data-border-enabled') === 'true';
        var bc = el.getAttribute('data-border-color') || '#000000';
        var bw = parseFloat(el.getAttribute('data-border-width'));
        if (isNaN(bw)) bw = 0;
        var br = parseFloat(el.getAttribute('data-border-radius'));
        if (isNaN(br)) br = 0;
        var op = parseFloat(el.getAttribute('data-opacity'));
        if (isNaN(op)) op = 1;
        var rot = parseFloat(el.getAttribute('data-rotation'));
        if (isNaN(rot)) rot = 0;
        var z = parseInt(el.getAttribute('data-z-index'), 10);
        if (isNaN(z)) z = 5;
        el.style.setProperty('background', bg, 'important');
        el.style.setProperty('background-color', bg, 'important');
        /* Print border only when enabled — designer uses CSS outline, not this border */
        if (borderEnabled && bw > 0) {
            el.style.setProperty('border', bw + 'px solid ' + bc, 'important');
        } else {
            el.style.setProperty('border', 'none', 'important');
        }
        el.style.borderRadius = br + 'px';
        el.style.opacity = String(Math.max(0, Math.min(1, op)));
        el.style.transform = rot ? ('rotate(' + rot + 'deg)') : 'none';
        el.style.zIndex = String(z);
        el.setAttribute('data-z-index', String(z));
    }
    function setWhiteStripPropsVisibility(show) {
        document.querySelectorAll('.prop-row-white-strip').forEach(function(row) {
            if (show) row.classList.add('is-visible');
            else row.classList.remove('is-visible');
        });
    }
    function syncWhiteStripPropsFromEl(el) {
        if (!el || !el.classList.contains('canvas-white-strip')) {
            setWhiteStripPropsVisibility(false);
            return;
        }
        setWhiteStripPropsVisibility(true);
        function setVal(id, v) {
            var n = document.getElementById(id);
            if (n) n.value = v;
        }
        setVal('propWhiteStripWidth', parseInt(el.style.width, 10) || el.offsetWidth || 250);
        setVal('propWhiteStripHeight', parseInt(el.style.height, 10) || el.offsetHeight || 40);
        setVal('propWhiteStripBg', el.getAttribute('data-bg') || '#ffffff');
        setVal('propWhiteStripBorderEnabled', el.getAttribute('data-border-enabled') === '1' ? '1' : '0');
        setVal('propWhiteStripBorderColor', el.getAttribute('data-border-color') || '#000000');
        setVal('propWhiteStripBorderWidth', el.getAttribute('data-border-width') || '0');
        setVal('propWhiteStripBorderRadius', el.getAttribute('data-border-radius') || '0');
        setVal('propWhiteStripOpacity', el.getAttribute('data-opacity') || '1');
        setVal('propWhiteStripRotation', el.getAttribute('data-rotation') || '0');
        setVal('propWhiteStripZIndex', el.getAttribute('data-z-index') || '5');
    }
    function applyWhiteStripPropsToSelected() {
        var sel = document.querySelector('.canvas-white-strip.selected');
        if (!sel) return;
        var w = parseInt(document.getElementById('propWhiteStripWidth') && document.getElementById('propWhiteStripWidth').value, 10);
        var h = parseInt(document.getElementById('propWhiteStripHeight') && document.getElementById('propWhiteStripHeight').value, 10);
        if (!isNaN(w) && w >= 10) sel.style.width = w + 'px';
        if (!isNaN(h) && h >= 5) sel.style.height = h + 'px';
        var bg = document.getElementById('propWhiteStripBg');
        var be = document.getElementById('propWhiteStripBorderEnabled');
        var bc = document.getElementById('propWhiteStripBorderColor');
        var bw = document.getElementById('propWhiteStripBorderWidth');
        var br = document.getElementById('propWhiteStripBorderRadius');
        var op = document.getElementById('propWhiteStripOpacity');
        var rot = document.getElementById('propWhiteStripRotation');
        var zEl = document.getElementById('propWhiteStripZIndex');
        if (bg) sel.setAttribute('data-bg', bg.value || '#FFFFFF');
        if (be) sel.setAttribute('data-border-enabled', be.value === '1' ? '1' : '0');
        if (bc) sel.setAttribute('data-border-color', bc.value || '#000000');
        if (bw) sel.setAttribute('data-border-width', String(parseFloat(bw.value) || 0));
        if (br) sel.setAttribute('data-border-radius', String(parseFloat(br.value) || 0));
        if (op) sel.setAttribute('data-opacity', String(Math.max(0, Math.min(1, parseFloat(op.value)))));
        if (rot) sel.setAttribute('data-rotation', String(parseFloat(rot.value) || 0));
        if (zEl) sel.setAttribute('data-z-index', String(parseInt(zEl.value, 10) || 5));
        applyWhiteStripStyle(sel);
        clampWhiteStripInParent(sel);
    }
    function whiteStripMaxZ(container) {
        var maxZ = 0;
        (container || document).querySelectorAll('.canvas-white-strip').forEach(function(el) {
            var z = parseInt(el.getAttribute('data-z-index'), 10);
            if (!isNaN(z) && z > maxZ) maxZ = z;
        });
        return maxZ;
    }
    function whiteStripMinZ(container) {
        var minZ = 9999;
        var found = false;
        (container || document).querySelectorAll('.canvas-white-strip').forEach(function(el) {
            var z = parseInt(el.getAttribute('data-z-index'), 10);
            if (!isNaN(z)) { found = true; if (z < minZ) minZ = z; }
        });
        return found ? minZ : 5;
    }
    function serializeWhiteStripEl(item, leftMm, topMm, widthMm, heightMm) {
        var box = parseInt(item.getAttribute('data-box'), 10);
        if (box !== 2) box = 1;
        var op = parseFloat(item.getAttribute('data-opacity'));
        if (isNaN(op)) op = 1;
        var id = item.getAttribute('data-ws-id') || item.getAttribute('data-id') || ('white-strip-' + Date.now());
        return {
            id: id,
            type: 'WhiteStrip',
            field: 'WhiteStrip',
            box: box,
            x: leftMm,
            y: topMm,
            left: leftMm,
            top: topMm,
            width: widthMm,
            height: heightMm,
            backgroundColor: item.getAttribute('data-bg') || '#FFFFFF',
            background_color: item.getAttribute('data-bg') || '#FFFFFF',
            opacity: op,
            rotation: parseFloat(item.getAttribute('data-rotation')) || 0,
            borderEnabled: item.getAttribute('data-border-enabled') === '1',
            border_enabled: item.getAttribute('data-border-enabled') === '1',
            borderColor: item.getAttribute('data-border-color') || '#000000',
            border_color: item.getAttribute('data-border-color') || '#000000',
            borderWidth: parseFloat(item.getAttribute('data-border-width')) || 0,
            border_width: parseFloat(item.getAttribute('data-border-width')) || 0,
            borderRadius: parseFloat(item.getAttribute('data-border-radius')) || 0,
            border_radius: parseFloat(item.getAttribute('data-border-radius')) || 0,
            zIndex: parseInt(item.getAttribute('data-z-index'), 10) || 5,
            z_index: parseInt(item.getAttribute('data-z-index'), 10) || 5
        };
    }
    function bindWhiteStripResize(el) {
        if (!el || el._wsResizeBound) return;
        el._wsResizeBound = true;
        el.querySelectorAll('.white-strip-resize-handle').forEach(function(handle) {
            handle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var dir = handle.getAttribute('data-dir') || 'se';
                var startX = e.clientX;
                var startY = e.clientY;
                var startL = parseInt(el.style.left, 10) || 0;
                var startT = parseInt(el.style.top, 10) || 0;
                var startW = el.offsetWidth;
                var startH = el.offsetHeight;
                var parent = el.parentElement;
                var pw = parent ? parent.clientWidth : 9999;
                var ph = parent ? parent.clientHeight : 9999;
                function onMove(ev) {
                    var dx = ev.clientX - startX;
                    var dy = ev.clientY - startY;
                    var left = startL, top = startT, w = startW, h = startH;
                    if (dir.indexOf('e') >= 0) w = startW + dx;
                    if (dir.indexOf('s') >= 0) h = startH + dy;
                    if (dir.indexOf('w') >= 0) { w = startW - dx; left = startL + dx; }
                    if (dir.indexOf('n') >= 0) { h = startH - dy; top = startT + dy; }
                    w = Math.max(10, Math.min(w, pw));
                    h = Math.max(5, Math.min(h, ph));
                    left = Math.max(0, Math.min(left, pw - w));
                    top = Math.max(0, Math.min(top, ph - h));
                    el.style.left = Math.round(left) + 'px';
                    el.style.top = Math.round(top) + 'px';
                    el.style.width = Math.round(w) + 'px';
                    el.style.height = Math.round(h) + 'px';
                    syncWhiteStripPropsFromEl(el);
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.cursor = '';
                    clampWhiteStripInParent(el);
                }
                document.body.style.cursor = handle.style.cursor || 'se-resize';
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }
    function createWhiteStripItem(left, top, container, opts) {
        opts = opts || {};
        container = container || getWhiteStripPrintCanvas(opts.box || 1);
        if (!container) return null;
        /* Never place on outer sticker strip layer or grey grid */
        if (container.id === 'barcode82x38StripLayer' || container.id === 'barcodeCanvasDrops' || container.id === 'barcodeDualStickerShell') {
            container = getWhiteStripPrintCanvas(opts.box || 1);
        }
        if (!container) return null;
        var boxNum = opts.box === 2 ? 2 : getWhiteStripBoxNumFromContainer(container);
        var wsId = opts.id || ('white-strip-' + Date.now() + '-' + Math.floor(Math.random() * 10000));
        var id = 'canvas-item-' + (++dropIdCounter);
        var el = document.createElement('div');
        el.className = 'canvas-dropped-item canvas-white-strip';
        el.setAttribute('data-field', 'WhiteStrip');
        el.setAttribute('data-id', id);
        el.setAttribute('data-ws-id', wsId);
        el.setAttribute('data-box', String(boxNum));
        el.setAttribute('data-prefix', '');
        el.setAttribute('data-suffix', '');
        el.setAttribute('data-bg', opts.backgroundColor || opts.background_color || '#FFFFFF');
        el.setAttribute('data-border-enabled', (opts.borderEnabled === true || opts.border_enabled === true || opts.borderEnabled === 1 || opts.borderEnabled === '1') ? '1' : '0');
        el.setAttribute('data-border-color', opts.borderColor || opts.border_color || '#000000');
        el.setAttribute('data-border-width', String(opts.borderWidth != null ? opts.borderWidth : (opts.border_width != null ? opts.border_width : 0)));
        el.setAttribute('data-border-radius', String(opts.borderRadius != null ? opts.borderRadius : (opts.border_radius != null ? opts.border_radius : 0)));
        el.setAttribute('data-opacity', String(opts.opacity != null && !isNaN(parseFloat(opts.opacity)) ? opts.opacity : 1));
        el.setAttribute('data-rotation', String(opts.rotation != null ? opts.rotation : 0));
        el.setAttribute('data-z-index', String(opts.zIndex != null ? opts.zIndex : (opts.z_index != null ? opts.z_index : 5)));
        el.draggable = true;
        var trashSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
        el.innerHTML = '<span class="canvas-item-remove" title="Remove">' + trashSvg + '</span>'
            + '<span class="white-strip-resize-handle ws-handle-nw" data-dir="nw"></span>'
            + '<span class="white-strip-resize-handle ws-handle-n" data-dir="n"></span>'
            + '<span class="white-strip-resize-handle ws-handle-ne" data-dir="ne"></span>'
            + '<span class="white-strip-resize-handle ws-handle-e" data-dir="e"></span>'
            + '<span class="white-strip-resize-handle ws-handle-se" data-dir="se"></span>'
            + '<span class="white-strip-resize-handle ws-handle-s" data-dir="s"></span>'
            + '<span class="white-strip-resize-handle ws-handle-sw" data-dir="sw"></span>'
            + '<span class="white-strip-resize-handle ws-handle-w" data-dir="w"></span>';
        var contW = container.clientWidth > 0 ? container.clientWidth : 120;
        var contH = container.clientHeight > 0 ? container.clientHeight : 80;
        var defW = opts.width != null ? parseInt(opts.width, 10) : Math.min(250, Math.max(40, contW - 8));
        var defH = opts.height != null ? parseInt(opts.height, 10) : Math.min(40, Math.max(16, Math.round(contH * 0.25)));
        if (isNaN(defW) || defW < 10) defW = Math.min(250, Math.max(10, contW - 4));
        if (isNaN(defH) || defH < 5) defH = Math.min(40, Math.max(5, contH - 4));
        var leftPx, topPx;
        if (opts.restore) {
            leftPx = opts.left !== undefined ? opts.left : 0;
            topPx = opts.top !== undefined ? opts.top : 0;
        } else if (opts.center) {
            leftPx = Math.max(0, Math.round((contW - defW) / 2));
            topPx = Math.max(0, Math.round((contH - defH) / 2));
        } else {
            leftPx = Math.max(0, Math.round(left - defW / 2));
            topPx = Math.max(0, Math.round(top - defH / 2));
        }
        el.style.left = leftPx + 'px';
        el.style.top = topPx + 'px';
        el.style.width = defW + 'px';
        el.style.height = defH + 'px';
        applyWhiteStripStyle(el);
        container.appendChild(el);
        clampWhiteStripInParent(el);
        bindWhiteStripResize(el);
        var rm = el.querySelector('.canvas-item-remove');
        if (rm) {
            rm.addEventListener('click', function(ev) {
                ev.stopPropagation();
                el.remove();
                syncToolboxHighlight();
                setWhiteStripPropsVisibility(false);
            });
        }
        el.addEventListener('click', function(ev) {
            if (ev.target.closest('.canvas-item-remove') || ev.target.closest('.white-strip-resize-handle')) return;
            syncPropertiesPanelFromDroppedItem(el);
        });
        el.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('application/x-canvas-item', id);
            e.dataTransfer.effectAllowed = 'move';
            e.stopPropagation();
        });
        el.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
        });
        el.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = el.parentElement;
            var r = parent && parent.getBoundingClientRect ? parent.getBoundingClientRect() : getDropLayerRect();
            handleDrop(e, r, parent || getWhiteStripPrintCanvas(1));
        });
        return el;
    }
    function collectWhiteStripItemsFromCanvas(canvasEl, mmW, mmH, pxToMmX, pxToMmY) {
        var out = [];
        if (!canvasEl) return out;
        canvasEl.querySelectorAll('.canvas-white-strip').forEach(function(item) {
            var left = parseInt(item.style.left, 10);
            var top = parseInt(item.style.top, 10);
            var wPx = parseInt(item.style.width, 10);
            var hPx = parseInt(item.style.height, 10);
            if (isNaN(left)) left = 0;
            if (isNaN(top)) top = 0;
            if (isNaN(wPx) || wPx < 10) wPx = item.offsetWidth || 40;
            if (isNaN(hPx) || hPx < 5) hPx = item.offsetHeight || 20;
            var leftMm = clampMmGlobal(left * pxToMmX, mmW);
            var topMm = clampMmGlobal(top * pxToMmY, mmH);
            var widthMm = clampMmGlobal(wPx * pxToMmX, mmW);
            var heightMm = clampMmGlobal(hPx * pxToMmY, mmH);
            var row = serializeWhiteStripEl(item, leftMm, topMm, widthMm, heightMm);
            if (isNaN(row.opacity)) row.opacity = 1;
            out.push(row);
        });
        return out;
    }
    function restoreWhiteStripItems(items, canvasEl, mmToPxX, mmToPxY) {
        if (!items || !items.length || !canvasEl) return;
        items.forEach(function(it) {
            if (!isWhiteStripType(it)) return;
            var leftMm = typeof it.left === 'number' ? it.left : (typeof it.x === 'number' ? it.x : (parseFloat(it.left || it.x) || 0));
            var topMm = typeof it.top === 'number' ? it.top : (typeof it.y === 'number' ? it.y : (parseFloat(it.top || it.y) || 0));
            var widthMm = typeof it.width === 'number' ? it.width : (parseFloat(it.width) || 0);
            var heightMm = typeof it.height === 'number' ? it.height : (parseFloat(it.height) || 0);
            var leftPx = Math.round(leftMm * mmToPxX);
            var topPx = Math.round(topMm * mmToPxY);
            var widthPx = widthMm > 0 ? Math.round(widthMm * mmToPxX) : Math.min(250, Math.max(40, (canvasEl.clientWidth || 200) - 8));
            var heightPx = heightMm > 0 ? Math.round(heightMm * mmToPxY) : 40;
            var target = canvasEl;
            var boxNum = parseInt(it.box, 10);
            if (boxNum === 1 || boxNum === 2) target = getWhiteStripPrintCanvas(boxNum) || canvasEl;
            createWhiteStripItem(leftPx, topPx, target, {
                restore: true,
                id: it.id,
                box: boxNum === 2 ? 2 : 1,
                left: leftPx,
                top: topPx,
                width: widthPx,
                height: heightPx,
                backgroundColor: it.backgroundColor || it.background_color || '#FFFFFF',
                borderEnabled: it.borderEnabled === true || it.border_enabled === true,
                borderColor: it.borderColor || it.border_color || '#000000',
                borderWidth: it.borderWidth != null ? it.borderWidth : (it.border_width != null ? it.border_width : 0),
                borderRadius: it.borderRadius != null ? it.borderRadius : (it.border_radius != null ? it.border_radius : 0),
                opacity: it.opacity != null && !isNaN(parseFloat(it.opacity)) ? parseFloat(it.opacity) : 1,
                rotation: it.rotation != null ? it.rotation : 0,
                zIndex: it.zIndex != null ? it.zIndex : (it.z_index != null ? it.z_index : 5)
            });
        });
    }
    /** @deprecated sticker-level white strips — kept for loading old saves into box 1 */
    function collect82x38StickerWhiteStrips() {
        return [];
    }
    function restore82x38StickerWhiteStrips(parsed) {
        var host = get82x38StripHost();
        if (host) host.querySelectorAll('.canvas-white-strip').forEach(function(el) { el.remove(); });
        var list = [];
        if (parsed && Array.isArray(parsed.sticker_white_strips)) list = parsed.sticker_white_strips;
        else if (parsed && Array.isArray(parsed.white_strips)) list = parsed.white_strips;
        if (!list.length) return;
        /* Migrate old sticker-level strips into box 1 */
        var canvas1 = getWhiteStripPrintCanvas(1);
        if (!canvas1) return;
        var sc = is82x38TwoBoxPreset() ? get82x38CanvasMmScale(canvas1) : { mmToPxX: MM_TO_PX, mmToPxY: MM_TO_PX };
        list.forEach(function(it) {
            if (!it) return;
            it.box = 1;
            restoreWhiteStripItems([it], canvas1, sc.mmToPxX, sc.mmToPxY);
        });
    }
    function addWhiteStripToTarget(container, leftPx, topPx, opts) {
        opts = opts || {};
        var boxCanvas = resolveWhiteStripDropTarget(
            (opts.clientX != null ? opts.clientX : 0),
            (opts.clientY != null ? opts.clientY : 0),
            container
        ) || container || getWhiteStripPrintCanvas(1);
        if (opts.box === 1 || opts.box === 2) boxCanvas = getWhiteStripPrintCanvas(opts.box);
        var el = createWhiteStripItem(leftPx || 20, topPx || 20, boxCanvas, opts);
        if (el) {
            syncPropertiesPanelFromDroppedItem(el);
            syncToolboxHighlight();
            if (!opts.restore && !opts.silent) showWhiteStripToast('White Strip added');
        }
        return el;
    }

    function createDroppedItem(fieldName, left, top, container, opts) {
        opts = opts || {};
        if (isWhiteStripField(fieldName)) {
            return createWhiteStripItem(left, top, container, opts);
        }
        container = container || labelCanvas1 || labelPreview1;
        var id = 'canvas-item-' + (++dropIdCounter);
        var el = document.createElement('div');
        el.className = 'canvas-dropped-item';
        el.setAttribute('data-field', fieldName);
        el.setAttribute('data-id', id);
        var isStrip = isStripLineField(fieldName);
        var prefix = opts.prefix !== undefined ? opts.prefix : (isStrip ? '' : fieldName);
        var suffix = opts.suffix !== undefined ? opts.suffix : '';
        var font = opts.font !== undefined ? opts.font : (document.getElementById('propFont') && document.getElementById('propFont').value ? document.getElementById('propFont').value : 'Arial');
        var fontSize = opts.font_size !== undefined ? String(opts.font_size) : (document.getElementById('propFontSize') && document.getElementById('propFontSize').value ? document.getElementById('propFontSize').value : '10');
        el.setAttribute('data-prefix', prefix);
        el.setAttribute('data-suffix', suffix);
        el.setAttribute('data-font', font);
        el.setAttribute('data-font-size', fontSize);
        var padTop = opts.pad_top !== undefined ? clampPadPx(opts.pad_top) : 0;
        var padRight = opts.pad_right !== undefined ? clampPadPx(opts.pad_right) : 0;
        var padBottom = opts.pad_bottom !== undefined ? clampPadPx(opts.pad_bottom) : 0;
        var padLeft = opts.pad_left !== undefined ? clampPadPx(opts.pad_left) : 0;
        el.setAttribute('data-pad-top', String(padTop));
        el.setAttribute('data-pad-right', String(padRight));
        el.setAttribute('data-pad-bottom', String(padBottom));
        el.setAttribute('data-pad-left', String(padLeft));
        el.draggable = true;
        var trashSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
        if (isStrip) {
            el.classList.add('canvas-strip-line');
            el.innerHTML = '<span class="canvas-strip-line-bar" aria-hidden="true"></span><span class="canvas-item-remove" title="Remove">' + trashSvg + '</span>';
            var contW = (container && container.clientWidth > 0) ? container.clientWidth : 120;
            var lineW = opts.width !== undefined ? parseInt(opts.width, 10) : Math.max(24, contW - 4);
            if (isNaN(lineW) || lineW < 8) lineW = Math.max(24, contW - 4);
            el.style.width = lineW + 'px';
            if (opts.restore && (opts.left !== undefined || opts.top !== undefined)) {
                el.style.left = (opts.left !== undefined ? opts.left : 2) + 'px';
                el.style.top = (opts.top !== undefined ? opts.top : Math.max(0, top - 5)) + 'px';
            } else {
                el.style.left = '2px';
                el.style.top = Math.max(0, (top - 5)) + 'px';
                el.style.width = Math.max(24, contW - 4) + 'px';
            }
        } else {
            el.innerHTML = '<span class="canvas-item-text">' + (prefix + (suffix ? ' ' + suffix : '')).trim() + '</span><span class="canvas-item-remove" title="Remove">' + trashSvg + '</span>';
            if (opts.restore && (opts.left !== undefined || opts.top !== undefined)) {
                el.style.left = (opts.left !== undefined ? opts.left : (left - 10)) + 'px';
                el.style.top = (opts.top !== undefined ? opts.top : (top - 10)) + 'px';
            } else {
                el.style.left = (left - 10) + 'px';
                el.style.top = (top - 10) + 'px';
            }
        }
        container.appendChild(el);
        if (is82x38HalfStripContainer(container)) {
            el.setAttribute('data-strip-slot', container.getAttribute('data-fixed-half-strip') || 'top-left');
            if (!opts.restore) {
                el.setAttribute('data-font-size', el.getAttribute('data-font-size') || '8');
                /* Keep text flush to top of strip (avoid scrolled-down look). */
                el.style.top = '0px';
                el.style.left = Math.max(0, (parseInt(el.style.left, 10) || 0)) + 'px';
            }
            clampDroppedItemInHalfStrip(el, container);
        }
        if (!isStrip) {
            updateDroppedItemDisplay(el);
            applyDroppedItemFont(el);
        }

        el.querySelector('.canvas-item-remove').addEventListener('click', function(ev) {
            ev.stopPropagation();
            var fieldName = el.getAttribute('data-field');
            var fromContainer = el.parentElement;
            el.remove();
            removeFieldFromOtherBlock(fieldName, fromContainer);
            syncToolboxHighlight();
        });
        el.addEventListener('click', function(ev) {
            if (ev.target.closest('.canvas-item-remove')) return;
            syncPropertiesPanelFromDroppedItem(el);
        });
        el.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('application/x-canvas-item', id);
            e.dataTransfer.effectAllowed = 'move';
            e.stopPropagation();
        });
        el.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
        });
        el.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = el.parentElement;
            var r = parent && parent.getBoundingClientRect ? parent.getBoundingClientRect() : getDropLayerRect();
            handleDrop(e, r, parent || labelCanvas1 || labelPreview1);
        });
        return el;
    }

    if (whiteDropZone && labelPreview1) {
        whiteDropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = (e.dataTransfer.types.indexOf('application/x-canvas-item') >= 0) ? 'move' : 'copy';
            whiteDropZone.classList.add('drag-over');
        });
        whiteDropZone.addEventListener('dragleave', function(e) {
            if (!whiteDropZone.contains(e.relatedTarget)) whiteDropZone.classList.remove('drag-over');
        });
        whiteDropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            whiteDropZone.classList.remove('drag-over');
            var rect = (labelCanvas1 || labelPreview1).getBoundingClientRect();
            handleDrop(e, rect, labelCanvas1 || labelPreview1);
        });
    }
    var whiteDropZone2 = document.getElementById('barcodeWhiteDropZone2');
    if (whiteDropZone2 && labelPreview2) {
        whiteDropZone2.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = (e.dataTransfer.types.indexOf('application/x-canvas-item') >= 0) ? 'move' : 'copy';
            whiteDropZone2.classList.add('drag-over');
        });
        whiteDropZone2.addEventListener('dragleave', function(e) {
            if (!whiteDropZone2.contains(e.relatedTarget)) whiteDropZone2.classList.remove('drag-over');
        });
        whiteDropZone2.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            whiteDropZone2.classList.remove('drag-over');
            var rect = (labelCanvas2 || labelPreview2).getBoundingClientRect();
            handleDrop(e, rect, labelCanvas2 || labelPreview2);
        });
    }

    /** Remove duplicate .canvas-dropped-item with same data-field (fixes bad saves / old drag bug). */
    function dedupeCanvasDomFields() {
        [labelCanvas1, labelCanvas2].forEach(function(canvas) {
            if (!canvas) return;
            var seen = {};
            canvas.querySelectorAll('.canvas-dropped-item').forEach(function(el) {
                var f = el.getAttribute('data-field');
                if (!f) return;
                if (isWhiteStripField(f) || el.classList.contains('canvas-white-strip')) return;
                if (seen[f]) el.remove();
                else seen[f] = true;
            });
        });
    }

    /** One text field per column name (saved JSON or items+items2 could list Barcode twice). */
    function dedupeSavedLayoutItems(items) {
        if (!Array.isArray(items)) return items;
        var seen = {};
        return items.filter(function(it) {
            if (!it) return false;
            if (it.type === 'barcode_image' || it.type === 'white_strip') return true;
            if (isWhiteStripField(it.field)) return true;
            var f = it.field;
            if (!f) return true;
            if (seen[f]) return false;
            seen[f] = true;
            return true;
        });
    }
    /** Same rule when saving layout from the canvas DOM. */
    function dedupeDesignLayoutItems(items) {
        if (!items || !items.length) return items;
        var seen = {};
        var out = [];
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            if (!it) continue;
            if (it.type === 'barcode_image' || it.type === 'qr_image' || it.type === 'white_strip' || it.type === 'WhiteStrip' || isWhiteStripField(it.field)) {
                out.push(it);
                continue;
            }
            if (it.type === 'strip_line' || it.type === 'line' || isStripLineField(it.field)) {
                if (seen['__StripLine__']) continue;
                seen['__StripLine__'] = true;
                out.push(it);
                continue;
            }
            if (it.type === 'text' && it.field) {
                if (seen[it.field]) continue;
                seen[it.field] = true;
            }
            out.push(it);
        }
        return out;
    }

    function clampMmGlobal(val, maxVal) {
        return Math.max(0, Math.min(maxVal, Math.round(val * 100) / 100));
    }
    function get82x38InnerBoxMmDims() {
        return { w: BOX_82X38_WIDTH_MM, h: BOX_82X38_HEIGHT_MM };
    }
    function get82x38CanvasMmScale(canvasEl) {
        var dims = get82x38InnerBoxMmDims();
        var cw = (canvasEl && canvasEl.clientWidth > 0) ? canvasEl.clientWidth : Math.round(dims.w * MM_TO_PX);
        var ch = (canvasEl && canvasEl.clientHeight > 0) ? canvasEl.clientHeight : Math.round(dims.h * MM_TO_PX);
        return {
            mmW: dims.w,
            mmH: dims.h,
            pxToMmX: dims.w / cw,
            pxToMmY: dims.h / ch,
            mmToPxX: cw / dims.w,
            mmToPxY: ch / dims.h
        };
    }
    function pushCanvasDroppedItemsToDesign(itemsArr, canvasEl, mmW, mmH, pxToMmX, pxToMmY, opts) {
        opts = opts || {};
        if (!canvasEl) return;
        canvasEl.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
            var left = parseInt(item.style.left, 10);
            var top = parseInt(item.style.top, 10);
            if (isNaN(left)) left = 0;
            if (isNaN(top)) top = 0;
            var leftMm = clampMmGlobal(left * pxToMmX, mmW);
            var topMm = clampMmGlobal(top * pxToMmY, mmH);
            if (!opts.skipDomSync) {
                item.style.left = Math.round(leftMm / pxToMmX) + 'px';
                item.style.top = Math.round(topMm / pxToMmY) + 'px';
            }
            var fieldName = item.getAttribute('data-field') || '';
            if (isStripLineField(fieldName) || item.classList.contains('canvas-strip-line')) {
                var wPx = parseInt(item.style.width, 10);
                if (isNaN(wPx) || wPx < 8) wPx = item.offsetWidth || Math.round(mmW / pxToMmX);
                var widthMm = clampMmGlobal(wPx * pxToMmX, mmW);
                if (!opts.skipDomSync) {
                    item.style.width = Math.round(widthMm / pxToMmX) + 'px';
                }
                itemsArr.push({
                    type: 'strip_line',
                    field: 'StripLine',
                    left: leftMm,
                    top: topMm,
                    width: widthMm
                });
                return;
            }
            if (isWhiteStripField(fieldName) || item.classList.contains('canvas-white-strip')) {
                var wPxWs = parseInt(item.style.width, 10);
                var hPxWs = parseInt(item.style.height, 10);
                if (isNaN(wPxWs) || wPxWs < 8) wPxWs = item.offsetWidth || 40;
                if (isNaN(hPxWs) || hPxWs < 4) hPxWs = item.offsetHeight || 20;
                var widthMmWs = clampMmGlobal(wPxWs * pxToMmX, mmW);
                var heightMmWs = clampMmGlobal(hPxWs * pxToMmY, mmH);
                if (!opts.skipDomSync) {
                    item.style.width = Math.round(widthMmWs / pxToMmX) + 'px';
                    item.style.height = Math.round(heightMmWs / pxToMmY) + 'px';
                }
                var wsRow = serializeWhiteStripEl(item, leftMm, topMm, widthMmWs, heightMmWs);
                if (isNaN(wsRow.opacity)) wsRow.opacity = 1;
                itemsArr.push(wsRow);
                return;
            }
            itemsArr.push({
                type: 'text',
                field: fieldName,
                left: leftMm,
                top: topMm,
                prefix: item.getAttribute('data-prefix') || '',
                suffix: item.getAttribute('data-suffix') || '',
                font: item.getAttribute('data-font') || 'Arial',
                font_size: item.getAttribute('data-font-size') || '10',
                pad_top: clampPadPx(item.getAttribute('data-pad-top')),
                pad_right: clampPadPx(item.getAttribute('data-pad-right')),
                pad_bottom: clampPadPx(item.getAttribute('data-pad-bottom')),
                pad_left: clampPadPx(item.getAttribute('data-pad-left'))
            });
        });
    }
    function restoreCanvasTextFieldsFromItems(items, canvasEl, mmToPxX, mmToPxY) {
        if (!items || !items.length || !canvasEl) return;
        items.forEach(function(it) {
            if (!it || it.type === 'barcode_image' || it.type === 'qr_image') return;
            var leftMm = typeof it.left === 'number' ? it.left : (typeof it.x === 'number' ? it.x : (parseFloat(it.left || it.x) || 0));
            var topMm = typeof it.top === 'number' ? it.top : (typeof it.y === 'number' ? it.y : (parseFloat(it.top || it.y) || 0));
            var leftPx = Math.round(leftMm * mmToPxX);
            var topPx = Math.round(topMm * mmToPxY);
            if (it.type === 'white_strip' || isWhiteStripType(it)) {
                restoreWhiteStripItems([it], canvasEl, mmToPxX, mmToPxY);
                return;
            }
            if (it.type === 'strip_line' || it.type === 'line' || isStripLineField(it.field)) {
                var widthMm = typeof it.width === 'number' ? it.width : (parseFloat(it.width) || 0);
                var widthPx = widthMm > 0 ? Math.round(widthMm * mmToPxX) : Math.max(24, (canvasEl.clientWidth || 100) - 4);
                createDroppedItem('StripLine', leftPx, topPx, canvasEl, {
                    prefix: '',
                    suffix: '',
                    left: leftPx,
                    top: topPx,
                    width: widthPx,
                    restore: true
                });
                return;
            }
            if (!it.field) return;
            createDroppedItem(it.field, leftPx, topPx, canvasEl, {
                prefix: it.prefix !== undefined ? it.prefix : it.field,
                suffix: it.suffix !== undefined ? it.suffix : '',
                font: it.font !== undefined ? it.font : 'Arial',
                font_size: it.font_size !== undefined ? it.font_size : 10,
                left: leftPx,
                top: topPx,
                restore: true,
                pad_top: it.pad_top !== undefined ? it.pad_top : 0,
                pad_right: it.pad_right !== undefined ? it.pad_right : 0,
                pad_bottom: it.pad_bottom !== undefined ? it.pad_bottom : 0,
                pad_left: it.pad_left !== undefined ? it.pad_left : 0
            });
        });
    }
    function restore82x38BoxTextFields(parsed) {
        if (!parsed || !is82x38TwoBoxPreset()) return;
        var canvas1 = labelCanvas1 || document.getElementById('labelCanvas1');
        var canvas2 = labelCanvas2 || document.getElementById('labelCanvas2');
        if (canvas1) {
            canvas1.querySelectorAll('.canvas-dropped-item').forEach(function(el) { el.remove(); });
        }
        if (canvas2) {
            canvas2.querySelectorAll('.canvas-dropped-item').forEach(function(el) { el.remove(); });
        }
        var arr1 = (parsed.box1 && Array.isArray(parsed.box1.items)) ? parsed.box1.items
            : (parsed.fields || parsed.items || []);
        var arr2 = (parsed.box2 && Array.isArray(parsed.box2.items)) ? parsed.box2.items
            : (parsed.fields2 || parsed.items2 || []);
        arr1 = dedupeSavedLayoutItems(arr1).filter(function(it) {
            return !(it && (it.type === 'strip_line' || it.type === 'line' || isStripLineField(it.field)));
        });
        arr2 = dedupeSavedLayoutItems(arr2).filter(function(it) {
            return !(it && (it.type === 'strip_line' || it.type === 'line' || isStripLineField(it.field)));
        });
        if (canvas1) {
            var sc1 = get82x38CanvasMmScale(canvas1);
            restoreCanvasTextFieldsFromItems(arr1, canvas1, sc1.mmToPxX, sc1.mmToPxY);
        }
        if (canvas2) {
            var sc2 = get82x38CanvasMmScale(canvas2);
            restoreCanvasTextFieldsFromItems(arr2, canvas2, sc2.mmToPxX, sc2.mmToPxY);
        }
        dedupeCanvasDomFields();
        restore82x38StickerStripLines(parsed);
        restore82x38StickerWhiteStrips(parsed);
        restore82x38HalfStripFields(parsed);
    }
    /** Saved print size for 82×38 SVG barcodes — use saved mm box, not SVG intrinsic height. */
    function get82x38BarcodeSaveSizePx(wrap, svgEl) {
        var bl = read82x38BoxLayoutFromInputs();
        var boxNum = get82x38BoxNumFromWrap(wrap);
        var wMm = boxNum === 2 ? bl.box2_barcode_width_mm : bl.box1_barcode_width_mm;
        var hMm = boxNum === 2 ? bl.box2_barcode_height_mm : bl.box1_barcode_height_mm;
        var canvasEl = wrap ? wrap.closest('.barcode-label-canvas') : null;
        if (canvasEl && canvasEl.clientWidth > 0 && canvasEl.clientHeight > 0) {
            var pxX = canvasEl.clientWidth / bl.box_width_mm;
            var pxY = canvasEl.clientHeight / bl.box_height_mm;
            return {
                width: Math.max(20, Math.round(wMm * pxX)),
                height: Math.max(8, Math.round(hMm * pxY))
            };
        }
        return { width: 90, height: 18 };
    }
    function get82x38BoxNumFromWrap(wrap) {
        return (wrap && wrap.id === 'barcode2') ? 2 : 1;
    }
    function update82x38BoxBarcodeMmFromPx(wrap, wPx, hPx, canvasEl) {
        if (!wrap || !canvasEl || canvasEl.clientWidth <= 0 || canvasEl.clientHeight <= 0) return;
        var pxX = canvasEl.clientWidth / BOX_82X38_WIDTH_MM;
        var pxY = canvasEl.clientHeight / BOX_82X38_HEIGHT_MM;
        var wMm = Math.max(4, Math.round((wPx / pxX) * 10) / 10);
        var hMm = Math.max(3, Math.round((hPx / pxY) * 10) / 10);
        var boxNum = get82x38BoxNumFromWrap(wrap);
        var wId = boxNum === 2 ? 'propBox2BarcodeWidthMm' : 'propBox1BarcodeWidthMm';
        var hId = boxNum === 2 ? 'propBox2BarcodeHeightMm' : 'propBox1BarcodeHeightMm';
        var wEl = document.getElementById(wId);
        var hEl = document.getElementById(hId);
        if (wEl) wEl.value = wMm;
        if (hEl) hEl.value = hMm;
    }
    /** Saved print size: prefer designer display width (purple handle), not natural JsBarcode stripe width. */
    function getBarcodeSaveSizePx(wrap, stripesEl) {
        var wPx = 0;
        var hPx = 0;
        if (wrap) {
            var dw = parseInt(wrap.getAttribute('data-display-width'), 10);
            if (dw > 0) wPx = dw;
            if (!wPx && wrap.offsetWidth > 0) wPx = wrap.offsetWidth;
        }
        if (!wPx && stripesEl && stripesEl.offsetWidth > 0) wPx = stripesEl.offsetWidth;
        if (!wPx) wPx = 90;
        if (stripesEl && stripesEl.offsetHeight > 0) {
            hPx = stripesEl.offsetHeight;
        } else if (wrap && wrap.offsetHeight > 0) {
            hPx = wrap.offsetHeight;
        } else {
            hPx = 18;
        }
        return { width: wPx, height: hPx };
    }
    /** Estimated text box size in mm (used only for overlap checks, not for print). */
    function estimateTextBoxMm(lw, lh) {
        var estW = Math.min(50, Math.max(12, lw * 0.45));
        var estH = Math.max(2.5, Math.min(8, lh * 0.5));
        return { w: estW, h: estH };
    }
    /**
     * True if estimated text box overlaps the barcode graphic (not the margin below it).
     * Text starting at or below bB does not overlap — fixes short labels where "just under the bars"
     * was mistaken for overlap and forced to the bottom (clipped by overflow).
     */
    function textOverlapsBarcodeBox(leftMm, topMm, bL, bR, bT, bB, lw, lh) {
        var est = estimateTextBoxMm(lw, lh);
        var horiz = leftMm < bR && (leftMm + est.w) > bL;
        var vert = (topMm + est.h > bT) && (topMm < bB);
        return horiz && vert;
    }
    /**
     * If text overlaps bars, move top to just below barcode — only when it still fits on the label.
     * Otherwise keep the user's position (avoids pushing caption off a 12mm-tall label).
     */
    function resolveTextTopMmBelowBarcodeIfOverlap(leftMm, topMm, bL, bR, bT, bB, labelW, labelH, gapMm) {
        if (bB <= 0 && bT <= 0) return topMm;
        if (!textOverlapsBarcodeBox(leftMm, topMm, bL, bR, bT, bB, labelW, labelH)) return topMm;
        var estH = estimateTextBoxMm(labelW, labelH).h;
        var targetTopMm = bB + gapMm;
        if (targetTopMm + estH <= labelH - 0.25) {
            return clampMmGlobal(targetTopMm, labelH);
        }
        return topMm;
    }
    /** Move dropped field text below barcode when it would draw on top of the bars (preview + after restore). */
    function adjustDroppedTextBelowBarcode(previewEl, canvasEl, labelW, labelH, barcodeId) {
        if (!previewEl || !canvasEl) return;
        var barcodeWrap = document.getElementById(barcodeId);
        if (!barcodeWrap) return;
        var contentW = canvasEl.offsetWidth || 270;
        var contentH = canvasEl.offsetHeight || 54;
        var pxToMmX = labelW / contentW;
        var pxToMmY = labelH / contentH;
        var br = barcodeWrap.getBoundingClientRect();
        var wr = canvasEl.getBoundingClientRect();
        var barcodeLeftMm = clampMmGlobal((br.left - wr.left) * pxToMmX, labelW);
        var barcodeTopMm = clampMmGlobal((br.top - wr.top) * pxToMmY, labelH);
        var stripes = barcodeWrap.querySelector('.barcode-stripes');
        var wPx = stripes ? stripes.offsetWidth : 90;
        var hPx = stripes ? stripes.offsetHeight : 18;
        var barcodeRightMm = barcodeLeftMm + clampMmGlobal(wPx * pxToMmX, labelW);
        var barcodeBottomMm = barcodeTopMm + clampMmGlobal(hPx * pxToMmY, labelH);
        var gapMm = 1.5;
        canvasEl.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
            var fieldName = item.getAttribute('data-field') || '';
            /* CompanyName is placed manually above the strip line — never auto-push on restore. */
            if (fieldName === 'CompanyName') return;
            var left = parseInt(item.style.left, 10);
            var top = parseInt(item.style.top, 10);
            if (isNaN(left)) left = 0;
            if (isNaN(top)) top = 0;
            var leftMm = clampMmGlobal(left * pxToMmX, labelW);
            var topMm = clampMmGlobal(top * pxToMmY, labelH);
            var newTopMm = resolveTextTopMmBelowBarcodeIfOverlap(leftMm, topMm, barcodeLeftMm, barcodeRightMm, barcodeTopMm, barcodeBottomMm, labelW, labelH, gapMm);
            if (Math.abs(newTopMm - topMm) > 0.01) {
                item.style.top = Math.round(newTopMm / pxToMmY) + 'px';
            }
        });
    }

    // Restore saved barcode print design (design_layout: barcode_image + text, all in mm)
    var labelWidthMm = <?php echo json_encode(isset($bs['label_width_mm']) ? (float)$bs['label_width_mm'] : 100); ?>;
    var labelHeightMm = <?php echo json_encode(isset($bs['label_height_mm']) ? (float)$bs['label_height_mm'] : 18); ?>;
    var sampleBarcodeNumber = <?php echo json_encode(trim((string)($sample_data_preview['barcode'] ?? $sample_data_preview['BarcodeNo'] ?? '')) ?: '00002'); ?>;
    var sampleBarcodeNumber2 = <?php echo json_encode(trim((string)($sample_data_preview['barcode2'] ?? $sample_data_preview['BarcodeNo2'] ?? '')) ?: '00003'); ?>;

    /** Offset of el from ancestor (padding edge), or null if ancestor is not in the offsetParent chain. */
    function getElementOffsetInAncestor(el, ancestor) {
        if (!el || !ancestor) return null;
        var l = 0, t = 0, node = el;
        while (node && node !== ancestor) {
            l += node.offsetLeft;
            t += node.offsetTop;
            node = node.offsetParent;
        }
        if (node !== ancestor) return null;
        return { left: l, top: t };
    }

    /**
     * Pin #barcode1 to saved pixel coords. Pass layout object, or omit to parse savedDesignLayout.
     * Uses barcode_left/top when defined (including 0); else legacy barcode_position.
     */
    /** Keep #barcode1/#barcode2 fully inside label canvas (stripes + caption) so nothing paints outside the white label. */
    function clampBarcodeBlockIntoCanvas(canvasEl, barcodeEl) {
        if (!canvasEl || !barcodeEl) return;
        if (is82x38TwoBoxPreset()) return;
        var cw = canvasEl.clientWidth;
        var ch = canvasEl.clientHeight;
        if (cw <= 0 || ch <= 0) return;

        var l = parseInt(barcodeEl.style.left, 10);
        var t = parseInt(barcodeEl.style.top, 10);
        if (isNaN(l)) l = barcodeEl.offsetLeft;
        if (isNaN(t)) t = barcodeEl.offsetTop;

        var stripes = barcodeEl.querySelector('.barcode-stripes');
        var caption = barcodeEl.querySelector('.barcode-text');
        var captionH = 0;
        if (caption && caption.style.display !== 'none') {
            captionH = caption.offsetHeight > 0 ? caption.offsetHeight + 2 : 10;
        }
        if (stripes) {
            var maxStripesH = Math.max(8, ch - t - captionH - 2);
            var sh = parseInt(stripes.style.height, 10);
            if (isNaN(sh) || sh <= 0) sh = stripes.offsetHeight;
            if (sh > maxStripesH) {
                stripes.style.height = maxStripesH + 'px';
                stripes.style.minHeight = maxStripesH + 'px';
                var propH = document.getElementById('propBarcodeBarHeight');
                if (propH && (barcodeEl.classList.contains('barcode-selected') || barcodeEl.id === 'barcode1')) {
                    propH.value = String(Math.max(8, Math.round(maxStripesH)));
                }
            }
        } else if (barcodeEl.classList.contains('qr-graphic-mode')) {
            var qrEl = barcodeEl.querySelector('.qr-code-preview');
            if (qrEl) {
                var maxQrH = Math.max(30, ch - t - captionH - 2);
                var qrH = qrEl.offsetHeight;
                if (qrH > maxQrH) {
                    var qhEl = document.getElementById('propQrHeight');
                    if (qhEl) qhEl.value = String(Math.max(30, Math.round(maxQrH)));
                    if (typeof updateBarcodeQrDisplay === 'function') updateBarcodeQrDisplay();
                }
            }
        }
        barcodeEl.style.height = 'auto';
        barcodeEl.style.maxHeight = '100%';
        barcodeEl.removeAttribute('data-display-height');

        var bw = barcodeEl.offsetWidth;
        var bh = barcodeEl.offsetHeight;
        if (bw <= 0 || bh <= 0) return;
        var maxL = Math.max(0, cw - bw);
        var maxT = Math.max(0, ch - bh);
        barcodeEl.style.left = Math.max(0, Math.min(maxL, l)) + 'px';
        barcodeEl.style.top = Math.max(0, Math.min(maxT, t)) + 'px';
    }

    function restoreBarcodePosition(layout) {
        if (is82x38TwoBoxPreset()) return;
        var data = layout;
        if (!data) {
            if (!savedDesignLayout || !String(savedDesignLayout).trim()) return;
            try {
                data = JSON.parse(savedDesignLayout);
            } catch (e) {
                return;
            }
        }
        if (!data) return;
        var barcode = document.getElementById('barcode1');
        if (!barcode) return;
        barcode.style.position = 'absolute';
        barcode.style.margin = '0';
        if (data.barcode_left !== undefined && data.barcode_left !== null) {
            barcode.style.left = parseInt(data.barcode_left, 10) + 'px';
        } else if (data.barcode_position && typeof data.barcode_position.left === 'number' && !isNaN(data.barcode_position.left)) {
            barcode.style.left = data.barcode_position.left + 'px';
        }
        if (data.barcode_top !== undefined && data.barcode_top !== null) {
            barcode.style.top = parseInt(data.barcode_top, 10) + 'px';
        } else if (data.barcode_position && typeof data.barcode_position.top === 'number' && !isNaN(data.barcode_position.top)) {
            barcode.style.top = data.barcode_position.top + 'px';
        }
        if (data.barcode_bar_width != null) {
            var pw = document.getElementById('propBarcodeBarWidth');
            if (pw) pw.value = Math.max(1, Math.min(10, parseInt(data.barcode_bar_width, 10) || 2));
        }
        if (data.barcode_bar_height != null) {
            var ph = document.getElementById('propBarcodeBarHeight');
            if (ph) ph.value = Math.max(10, Math.min(200, parseInt(data.barcode_bar_height, 10) || 28));
        }
    }

    function restoreBarcode2Position(layout, mmToPxX, mmToPxY) {
        /* 82×38 uses mm layout via apply82x38BarcodeLayoutMm — px restore breaks QR sync. */
        if (typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset()) return;
        var data = layout;
        if (!data) return;
        var barcode2 = document.getElementById('barcode2');
        if (!barcode2) return;
        barcode2.style.position = 'absolute';
        barcode2.style.margin = '0';
        var leftMm = null;
        var topMm = null;
        var lists = [data.items2, data.fields2];
        for (var li = 0; li < lists.length; li++) {
            var arr = lists[li];
            if (!arr || !arr.length) continue;
            for (var i = 0; i < arr.length; i++) {
                var it = arr[i];
                if (it && it.type === 'barcode_image') {
                    leftMm = typeof it.left === 'number' ? it.left : parseFloat(it.left);
                    topMm = typeof it.top === 'number' ? it.top : parseFloat(it.top);
                    break;
                }
            }
            if (leftMm != null && !isNaN(leftMm)) break;
        }
        if ((leftMm == null || isNaN(leftMm)) && data.barcode2_left != null) {
            leftMm = parseFloat(data.barcode2_left);
        }
        if ((topMm == null || isNaN(topMm)) && data.barcode2_top != null) {
            topMm = parseFloat(data.barcode2_top);
        }
        if (leftMm == null || isNaN(leftMm) || topMm == null || isNaN(topMm)) return;
        if (!(mmToPxX > 0) || !(mmToPxY > 0)) return;
        barcode2.style.left = Math.round(leftMm * mmToPxX) + 'px';
        barcode2.style.top = Math.round(topMm * mmToPxY) + 'px';
    }

    function getActiveBarcodePrintWrap() {
        return document.querySelector('.barcode-print-wrap.barcode-selected')
            || document.querySelector('.barcode-inner-draggable.barcode-selected')
            || document.getElementById('barcode1');
    }

    function syncBarcodeDisplayWidthPropFromWrap(wrap) {
        var prop = document.getElementById('propBarcodeDisplayWidth');
        if (!prop || !wrap) return;
        var dw = parseInt(wrap.getAttribute('data-display-width'), 10);
        if (dw > 0) {
            prop.value = String(dw);
        } else if (wrap.offsetWidth > 0) {
            prop.value = String(wrap.offsetWidth);
        }
    }

    function isQrGraphicWrap(wrap) {
        return !!(wrap && typeof currentCodeType !== 'undefined' && currentCodeType === 'qr' && wrap.classList.contains('qr-graphic-mode'));
    }

    function syncQrSizePropsFromWrap(wrap) {
        if (!isQrGraphicWrap(wrap)) return;
        var qw = document.getElementById('propQrWidth');
        var qh = document.getElementById('propQrHeight');
        if (!qw || !qh) return;
        var wPx = 0;
        var hPx = 0;
        if (is82x38TwoBoxPreset()) {
            var sz = get82x38WrapSizeMm(wrap);
            var box = get82x38BoxElForBarcode(wrap);
            var scale = get82x38ScaleFromBox(box);
            wPx = Math.round(sz.width_mm * scale.x);
            hPx = Math.round(sz.height_mm * scale.y);
        } else {
            var qr = wrap.querySelector('.qr-code-preview');
            if (qr && qr.offsetWidth > 0) wPx = Math.round(qr.offsetWidth);
            if (qr && qr.offsetHeight > 0) hPx = Math.round(qr.offsetHeight);
        }
        _suppressQrPropChange = true;
        if (wPx > 0) qw.value = String(Math.max(12, Math.min(200, wPx)));
        if (hPx > 0) qh.value = String(Math.max(12, Math.min(200, hPx)));
        _suppressQrPropChange = false;
    }

    function getQrMaxSizeForCanvas(canvasEl, wrap) {
        var maxW = 200;
        var maxH = 200;
        if (!canvasEl || canvasEl.clientWidth <= 0 || canvasEl.clientHeight <= 0) {
            return { maxW: maxW, maxH: maxH };
        }
        var left = parseInt(wrap.style.left, 10);
        var top = parseInt(wrap.style.top, 10);
        if (isNaN(left)) left = wrap.offsetLeft || 0;
        if (isNaN(top)) top = wrap.offsetTop || 0;
        maxW = Math.min(200, Math.max(12, canvasEl.clientWidth - left - 4));
        maxH = Math.min(200, Math.max(12, canvasEl.clientHeight - top - 4));
        var caption = wrap.querySelector('.barcode-text');
        if (caption && caption.style.display !== 'none' && caption.offsetHeight > 0) {
            maxH = Math.max(12, maxH - caption.offsetHeight - 2);
        }
        return { maxW: maxW, maxH: maxH };
    }

    function updateBarcodeResizeHandleLabels() {
        var isQr = typeof currentCodeType !== 'undefined' && currentCodeType === 'qr';
        document.querySelectorAll('.barcode-print-wrap .barcode-resize-handle').forEach(function(handle) {
            handle.setAttribute('aria-label', isQr ? 'Resize QR width and height' : 'Resize barcode width and height');
            handle.title = isQr ? 'Drag to change QR width and height' : 'Drag to change barcode width and height';
        });
    }

    function getBarcodeCaptionHeight(wrap) {
        if (!wrap) return 0;
        var caption = wrap.querySelector('.barcode-text');
        if (!caption || caption.style.display === 'none') return 0;
        return caption.offsetHeight > 0 ? caption.offsetHeight + 2 : 10;
    }

    function getBarcodeMaxStripeHeightPx(wrap, canvasEl) {
        var ch = (canvasEl && canvasEl.clientHeight > 0) ? canvasEl.clientHeight : Math.round((labelHeightMm || 18) * MM_TO_PX);
        var minBarH = (labelHeightMm || 18) <= 20 ? 8 : 10;
        if (!wrap) return Math.max(minBarH, ch - ((labelHeightMm || 18) <= 20 ? 8 : 12));
        var topPx = parseInt(wrap.style.top, 10);
        if (isNaN(topPx)) topPx = wrap.offsetTop || 0;
        return Math.max(minBarH, ch - topPx - getBarcodeCaptionHeight(wrap) - 2);
    }

    function syncBarcodeBarHeightPropFromWrap(wrap) {
        if (!wrap || isQrGraphicWrap(wrap)) return;
        var stripes = wrap.querySelector('.barcode-stripes');
        var propH = document.getElementById('propBarcodeBarHeight');
        if (!stripes || !propH) return;
        var h = stripes.offsetHeight;
        if (h > 0) propH.value = String(Math.max(8, Math.min(200, Math.round(h))));
    }

    function selectBarcodePrintWrap(wrap) {
        document.querySelectorAll('.barcode-print-wrap, .barcode-inner-draggable').forEach(function(w) {
            w.classList.remove('barcode-selected');
        });
        document.querySelectorAll('.canvas-dropped-item').forEach(function(i) {
            i.classList.remove('selected');
        });
        if (wrap) {
            wrap.classList.add('barcode-selected');
            if (isQrGraphicWrap(wrap)) {
                syncQrSizePropsFromWrap(wrap);
            } else {
                syncBarcodeDisplayWidthPropFromWrap(wrap);
                syncBarcodeBarHeightPropFromWrap(wrap);
            }
            positionStandardBarcodeResizeHandle(wrap);
        }
        syncBarcodeResizeHandleVisibility();
    }

    function getBarcodeMaxDisplayWidthPx(canvasEl, wrap) {
        var cw = (canvasEl && canvasEl.clientWidth > 0) ? canvasEl.clientWidth : 270;
        var maxW = Math.max(24, cw - 4);
        if (!wrap || !canvasEl) return maxW;
        var l = parseInt(wrap.style.left, 10);
        if (isNaN(l)) l = wrap.offsetLeft || 0;
        return Math.max(24, Math.min(maxW, cw - l - 4));
    }

    function getBarcodeTargetDisplayWidthPx(wrap, canvasEl, naturalW) {
        var maxW = getBarcodeMaxDisplayWidthPx(canvasEl, wrap);
        var dataW = wrap ? parseInt(wrap.getAttribute('data-display-width'), 10) : 0;
        if (dataW > 0) {
            return Math.max(24, Math.min(maxW, dataW));
        }
        var propEl = document.getElementById('propBarcodeDisplayWidth');
        var activeWrap = getActiveBarcodePrintWrap();
        var useProp = (!wrap || wrap === activeWrap) && propEl && parseInt(propEl.value, 10) > 0;
        if (useProp) {
            return Math.max(24, Math.min(maxW, parseInt(propEl.value, 10)));
        }
        if (naturalW > 0) {
            var cap = Math.round(maxW * 0.92);
            return Math.max(24, Math.min(Math.ceil(naturalW), cap));
        }
        return Math.max(40, Math.round(maxW * 0.85));
    }

    function applyBarcodeDisplayWidth(stripesEl, wrap, targetW, naturalW, barBoxH) {
        if (!stripesEl || !wrap || targetW <= 0) return;
        stripesEl.style.width = targetW + 'px';
        stripesEl.style.maxWidth = targetW + 'px';
        wrap.style.width = targetW + 'px';
        wrap.style.maxWidth = targetW + 'px';
        wrap.setAttribute('data-display-width', String(Math.round(targetW)));
        var svg = stripesEl.querySelector('svg');
        if (svg && naturalW > 0 && Math.abs(targetW - naturalW) > 0.5) {
            var scale = targetW / naturalW;
            stripesEl.classList.add('is-scaled-x');
            svg.style.width = naturalW + 'px';
            svg.style.height = barBoxH + 'px';
            svg.style.maxWidth = 'none';
            svg.style.transform = 'scaleX(' + scale + ')';
            svg.style.transformOrigin = 'left top';
            stripesEl.style.overflow = 'hidden';
        } else if (svg) {
            stripesEl.classList.remove('is-scaled-x');
            svg.style.transform = '';
            svg.style.width = 'auto';
            svg.style.height = '100%';
        }
    }

    function positionStandardBarcodeResizeHandle(wrap) {
        if (!wrap || wrap.classList.contains('barcode-inner-draggable')) return;
        var handle = wrap.querySelector('.barcode-resize-handle');
        if (!handle) return;
        var stripes = wrap.querySelector('.barcode-stripes');
        if (wrap.classList.contains('qr-graphic-mode')) {
            var qr = wrap.querySelector('.qr-code-preview');
            if (qr && qr.offsetHeight > 0) stripes = qr;
        }
        handle.style.right = '-5px';
        handle.style.bottom = 'auto';
        if (stripes && stripes.offsetHeight > 0) {
            handle.style.top = Math.max(0, stripes.offsetHeight - 6) + 'px';
        } else {
            handle.style.top = '';
            handle.style.bottom = '-5px';
        }
    }
    function ensureAllBarcodeResizeHandles() {
        if (is82x38TwoBoxPreset()) return;
        [document.getElementById('barcode1'), document.getElementById('barcode2')].forEach(function(wrap) {
            if (!wrap || wrap.classList.contains('barcode-inner-draggable')) return;
            initBarcodePrintWrapInteractions(wrap);
            positionStandardBarcodeResizeHandle(wrap);
        });
    }
    function ensureBarcodeResizeHandle(wrap) {
        if (!wrap || wrap.classList.contains('barcode-inner-draggable')) return;
        if (wrap.querySelector('.barcode-resize-handle')) return;
        var handle = document.createElement('span');
        var is82Resize = is82x38TwoBoxPreset() && (wrap.id === 'barcode1' || wrap.id === 'barcode2');
        handle.className = 'barcode-resize-handle' + (is82Resize ? ' barcode-resize-handle--82x38' : '');
        handle.setAttribute('aria-label', 'Resize barcode width and height');
        handle.title = 'Drag to change barcode width and height';
        wrap.appendChild(handle);
        positionStandardBarcodeResizeHandle(wrap);
        var resizing = false;
        var startX = 0;
        var startY = 0;
        var startW = 0;
        var startH = 0;
        var startLeft = 0;
        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            selectBarcodePrintWrap(wrap);
            resizing = true;
            startX = e.clientX;
            startY = e.clientY;
            var isQrResize = isQrGraphicWrap(wrap);
            if (isQrResize) {
                var qrStart = wrap.querySelector('.qr-code-preview');
                var qrSize = getQrSize();
                startW = (qrStart && qrStart.offsetWidth > 0) ? qrStart.offsetWidth : qrSize.width;
                startH = (qrStart && qrStart.offsetHeight > 0) ? qrStart.offsetHeight : qrSize.height;
            } else {
                startW = wrap.offsetWidth || parseInt(wrap.getAttribute('data-display-width'), 10) || 80;
                var stripesStart = wrap.querySelector('.barcode-stripes');
                startH = (stripesStart && stripesStart.offsetHeight > 0) ? stripesStart.offsetHeight : (wrap.offsetHeight || parseInt(wrap.getAttribute('data-display-height'), 10) || 24);
            }
            startLeft = parseInt(wrap.style.left, 10);
            if (isNaN(startLeft)) startLeft = wrap.offsetLeft || 0;
            document.body.style.cursor = 'nwse-resize';
        });
        document.addEventListener('mousemove', function(e) {
            if (!resizing) return;
            var canvasEl = wrap.closest('.barcode-label-canvas');
            var isQrResize = isQrGraphicWrap(wrap);
            if (is82Resize) {
                var bl = read82x38BoxLayoutFromInputs();
                var maxW = canvasEl && canvasEl.clientWidth > 0
                    ? Math.max(24, canvasEl.clientWidth - (parseInt(wrap.style.left, 10) || 0) - 4)
                    : 200;
                var maxH = canvasEl && canvasEl.clientHeight > 0
                    ? Math.max(8, canvasEl.clientHeight - (parseInt(wrap.style.top, 10) || 0) - 4)
                    : 80;
                var newW = Math.max(24, Math.min(maxW, startW + (e.clientX - startX)));
                var newH = Math.max(8, Math.min(maxH, startH + (e.clientY - startY)));
                wrap.setAttribute('data-display-width', String(Math.round(newW)));
                wrap.setAttribute('data-display-height', String(Math.round(newH)));
                wrap.style.width = newW + 'px';
                wrap.style.height = newH + 'px';
                var svg = wrap.querySelector('svg');
                if (svg) {
                    svg.style.width = newW + 'px';
                    svg.style.height = newH + 'px';
                }
                update82x38BoxBarcodeMmFromPx(wrap, newW, newH, canvasEl);
                return;
            }
            if (isQrResize) {
                var qrLimits = getQrMaxSizeForCanvas(canvasEl, wrap);
                var newW = Math.max(12, Math.min(qrLimits.maxW, startW + (e.clientX - startX)));
                var newH = Math.max(12, Math.min(qrLimits.maxH, startH + (e.clientY - startY)));
                var side = Math.max(12, Math.min(newW, newH));
                var qwEl = document.getElementById('propQrWidth');
                var qhEl = document.getElementById('propQrHeight');
                _suppressQrPropChange = true;
                if (qwEl) qwEl.value = String(Math.round(side));
                if (qhEl) qhEl.value = String(Math.round(side));
                _suppressQrPropChange = false;
                updateBarcodeQrDisplay();
                positionStandardBarcodeResizeHandle(wrap);
                return;
            }
            var maxW = getBarcodeMaxDisplayWidthPx(canvasEl, wrap);
            var newW = Math.max(24, Math.min(maxW, startW + (e.clientX - startX)));
            var opts = getBarcodeBarOptions(wrap, canvasEl);
            var maxStripeH = getBarcodeMaxStripeHeightPx(wrap, canvasEl);
            var newH = Math.max(opts.minBarH, Math.min(maxStripeH, startH + (e.clientY - startY)));
            wrap.setAttribute('data-display-width', String(Math.round(newW)));
            wrap.style.width = newW + 'px';
            wrap.style.left = startLeft + 'px';
            var propW = document.getElementById('propBarcodeDisplayWidth');
            if (propW && wrap.classList.contains('barcode-selected')) propW.value = String(Math.round(newW));
            var propH = document.getElementById('propBarcodeBarHeight');
            if (propH && wrap.classList.contains('barcode-selected')) propH.value = String(Math.round(newH));
            positionStandardBarcodeResizeHandle(wrap);
            renderCanvasBarcode();
        });
        document.addEventListener('mouseup', function() {
            if (!resizing) return;
            resizing = false;
            document.body.style.cursor = '';
            var canvasEl = wrap.closest('.barcode-label-canvas');
            if (is82Resize) {
                sync82x38BarcodePositionsFromDom({ syncSize: true });
                render82x38PreviewPipeline({ skipBoxLayout: true });
                return;
            }
            clampBarcodeBlockIntoCanvas(canvasEl, wrap);
        });
    }

    function initStandardBarcodePrintWrapDrag(wrap) {
        if (!wrap || wrap._stdDragBound || is82x38TwoBoxPreset()) return;
        wrap._stdDragBound = true;
        var dragging = false;
        var startX = 0;
        var startY = 0;
        var startL = 0;
        var startT = 0;
        wrap.addEventListener('mousedown', function(e) {
            if (is82x38TwoBoxPreset()) return;
            if (e.target.closest('.barcode-resize-handle')) return;
            if (!isBarcodeGraphicClickTarget(e.target)) return;
            e.preventDefault();
            e.stopPropagation();
            dragging = true;
            startX = e.clientX;
            startY = e.clientY;
            var canvasEl = wrap.closest('.barcode-label-canvas') || labelCanvas1;
            var off = canvasEl ? getElementOffsetInAncestor(wrap, canvasEl) : null;
            startL = off ? off.left : (parseInt(wrap.style.left, 10) || wrap.offsetLeft || 0);
            startT = off ? off.top : (parseInt(wrap.style.top, 10) || wrap.offsetTop || 0);
            wrap.style.position = 'absolute';
            wrap.style.margin = '0';
            wrap.style.left = startL + 'px';
            wrap.style.top = startT + 'px';
            wrap.style.zIndex = '50';
            selectBarcodePrintWrap(wrap);
        });
        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            var canvasEl = wrap.closest('.barcode-label-canvas') || labelCanvas1;
            var nl = startL + (e.clientX - startX);
            var nt = startT + (e.clientY - startY);
            wrap.style.left = nl + 'px';
            wrap.style.top = nt + 'px';
            clampBarcodeBlockIntoCanvas(canvasEl, wrap);
        });
        document.addEventListener('mouseup', function() {
            if (!dragging) return;
            dragging = false;
            wrap.style.zIndex = '';
        });
    }

    function initBarcodePrintWrapInteractions(wrap) {
        if (!wrap || wrap._barcodeUiBound) return;
        wrap._barcodeUiBound = true;
        ensureBarcodeResizeHandle(wrap);
        syncBarcodeResizeHandleVisibility();
        initStandardBarcodePrintWrapDrag(wrap);
    }

    function getBarcodeBarOptions(wrap, canvasEl) {
        var w = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
        var h = parseInt(document.getElementById('propBarcodeBarHeight') && document.getElementById('propBarcodeBarHeight').value, 10);
        var c1 = canvasEl || labelCanvas1 || document.getElementById('labelCanvas1');
        var ch = (c1 && c1.clientHeight > 0) ? c1.clientHeight : Math.round((labelHeightMm || 18) * MM_TO_PX);
        var isShortLabel = (labelHeightMm || 18) <= 20;
        var minBarH = isShortLabel ? 8 : 10;
        var maxBarH = wrap ? getBarcodeMaxStripeHeightPx(wrap, c1) : Math.max(minBarH, ch - (isShortLabel ? 8 : 12));
        var barH = (isNaN(h) || h < minBarH) ? (isShortLabel ? 10 : 28) : Math.min(200, h);
        barH = Math.min(barH, maxBarH);
        var barW = (isNaN(w) || w < 1) ? (isShortLabel ? 1 : 2) : Math.min(10, w);
        return { width: barW, height: barH, minBarH: minBarH };
    }
    /** Keep standard barcode stripes within the label canvas so the block does not block drag/drop. */
    function enforceStandardBarcodeGraphicBounds(wrap, canvasEl) {
        if (!wrap || !canvasEl || is82x38TwoBoxPreset() || wrap.classList.contains('barcode-inner-draggable')) return;
        if (wrap.classList.contains('qr-graphic-mode')) return;
        var stripes = wrap.querySelector('.barcode-stripes');
        if (!stripes) return;
        var opts = getBarcodeBarOptions(wrap, canvasEl);
        var ch = canvasEl.clientHeight > 0 ? canvasEl.clientHeight : Math.round((labelHeightMm || 18) * MM_TO_PX);
        var captionH = getBarcodeCaptionHeight(wrap);
        var topPx = parseInt(wrap.style.top, 10);
        if (isNaN(topPx)) topPx = wrap.offsetTop || 0;
        var maxStripeH = Math.max(8, ch - topPx - captionH - 2);
        var barH = Math.min(opts.height, maxStripeH);
        stripes.style.height = barH + 'px';
        stripes.style.minHeight = barH + 'px';
        stripes.style.maxHeight = barH + 'px';
        wrap.style.height = 'auto';
        wrap.style.maxHeight = Math.max(barH + captionH, 8) + 'px';
        wrap.removeAttribute('data-display-height');
        wrap.querySelectorAll(':scope > svg.barcode-svg').forEach(function(s) {
            s.style.setProperty('display', 'none', 'important');
        });
        syncBarcodeBarHeightPropFromWrap(wrap);
    }
    /** 82×38: boxes → JsBarcode or QR preview → apply saved mm (no auto-align after). */
    function render82x38PreviewPipeline(opts) {
        opts = opts || {};
        if (!is82x38TwoBoxPreset()) return;
        if (!opts.skipBoxLayout) {
            layout82x38DualCanvases();
        }
        var bl = read82x38BoxLayoutFromInputs();
        var wrap1 = document.getElementById('barcode1');
        var wrap2 = document.getElementById('barcode2');
        var dims1 = get82x38GraphicDimsForBox(1, bl, wrap1);
        var dims2 = get82x38GraphicDimsForBox(2, bl, wrap2);
        var pairs = [
            { svg: document.getElementById('barcodeSvgBox1'), wrap: wrap1, code: String(sampleBarcodeNumber || '00002').trim() || '00002', text: document.getElementById('barcodeText1'), barW: dims1.width, barH: dims1.height, barL: dims1.left, barT: dims1.top },
            { svg: document.getElementById('barcodeSvgBox2'), wrap: wrap2, code: String(sampleBarcodeNumber2 || '00003').trim() || '00003', text: document.getElementById('barcodeText2'), barW: dims2.width, barH: dims2.height, barL: dims2.left, barT: dims2.top }
        ];
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            pairs.forEach(function(p) {
                if (!p.wrap) return;
                p.wrap.style.display = 'block';
                p.wrap.style.visibility = 'visible';
                if (p.text) p.text.textContent = p.code;
            });
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    pairs.forEach(function(p, idx) {
                        if (!p.wrap) return;
                        apply82x38BarcodeLayoutMm(p.wrap, p.barL, p.barT, p.barW, p.barH);
                        set82x38BarcodePropInputs(idx + 1, p.barL, p.barT, p.barW, p.barH);
                    });
                    updateBarcodeQrDisplay();
                    /* Second paint after mm→px layout so QR canvas fills the wrap (avoids tiny QR in large box). */
                    requestAnimationFrame(function() {
                        updateBarcodeQrDisplay();
                        toggle82x38BarcodeTextVisibility();
                        init82x38BarcodeDragResize();
                    });
                });
            });
            return;
        }
        if (typeof JsBarcode === 'undefined') return;
        var baseModW = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
        if (isNaN(baseModW) || baseModW < 1) baseModW = 2;
        pairs.forEach(function(p) {
            if (!p.svg || !p.wrap) return;
            p.wrap.style.display = 'block';
            p.wrap.style.visibility = 'visible';
            p.wrap.classList.remove('qr-graphic-mode');
            p.svg.style.setProperty('display', 'block', 'important');
            p.svg.style.setProperty('visibility', 'visible', 'important');
            p.svg.setAttribute('data-barcode', p.code);
            if (!is82x38TwoBoxPreset() || !p.wrap.classList.contains('barcode-inner-draggable')) {
                initBarcodePrintWrapInteractions(p.wrap);
            }
            if (p.text) p.text.textContent = p.code;
        });
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                pairs.forEach(function(p, idx) {
                    if (!p.wrap || !p.svg) return;
                    apply82x38BarcodeLayoutMm(p.wrap, p.barL, p.barT, p.barW, p.barH);
                    var box = get82x38BoxForBarcodeIndex(idx + 1);
                    var scale = get82x38ScaleFromBox(box);
                    layout82x38BarcodeInnerContent(p.wrap, {
                        wPx: Math.max(8, p.barW * scale.x),
                        hPx: Math.max(8, p.barH * scale.y)
                    });
                });
                updateBarcodeQrDisplay();
                toggle82x38BarcodeTextVisibility();
                init82x38BarcodeDragResize();
                log82x38AppliedBarcodes();
            });
        });
    }
    function render82x38DualBarcodes() {
        render82x38PreviewPipeline();
    }
    /** Canvas preview: always reads bar width/height from prop inputs (matches saved design_layout after reload). */
    function renderCanvasBarcode() {
        if (typeof JsBarcode === 'undefined') return;
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            updateBarcodeQrDisplay();
            return;
        }
        if (is82x38TwoBoxPreset()) {
            render82x38PreviewPipeline();
            return;
        }
        var bc1WrapEarly = document.getElementById('barcode1');
        var preservedPos = null;
        if (bc1WrapEarly && (bc1WrapEarly.style.left || bc1WrapEarly.style.top)) {
            preservedPos = { left: bc1WrapEarly.style.left, top: bc1WrapEarly.style.top };
        }
        var opts = getBarcodeBarOptions(bc1Wrap, labelCanvas1 || document.getElementById('labelCanvas1'));
        var code = String(sampleBarcodeNumber || '00002').trim() || '00002';
        var code2 = String(sampleBarcodeNumber2 || '00003').trim() || '00003';
        var jsOpts = {
            format: 'CODE128',
            width: opts.width,
            height: opts.height,
            displayValue: false,
            margin: 0,
            marginTop: 0,
            marginBottom: 0,
            marginLeft: 0,
            marginRight: 0,
            background: '#ffffff',
            lineColor: '#000000'
        };
        var bc1Wrap = document.getElementById('barcode1');
        var bc2ForStripes = document.getElementById('barcode2');
        var isDual = isDualCanvasLayoutPreset();
        if (bc1Wrap) bc1Wrap.style.display = '';
        if (isDual && bc2ForStripes) bc2ForStripes.style.display = '';
        var stripes1 = bc1Wrap ? bc1Wrap.querySelector('.barcode-stripes') : (labelPreview1 ? labelPreview1.querySelector('.barcode-stripes') : null);
        var stripes2 = (isDual && bc2ForStripes) ? bc2ForStripes.querySelector('.barcode-stripes') : null;
        [stripes1, stripes2].forEach(function(stripesEl, idx) {
            if (!stripesEl) return;
            var boxCode = idx === 0 ? code : code2;
            var wrap = stripesEl.parentElement;
            var canvasEl = idx === 0 ? (document.getElementById('labelCanvas1') || labelPreview1) : (document.getElementById('labelCanvas2') || labelPreview2);
            initBarcodePrintWrapInteractions(wrap);
            var cw = (canvasEl && canvasEl.offsetWidth > 0) ? canvasEl.offsetWidth : 270;
            var chCanvas = (canvasEl && canvasEl.offsetHeight > 0) ? canvasEl.offsetHeight : Math.round((labelHeightMm || 18) * MM_TO_PX);
            var maxBarH = getBarcodeMaxStripeHeightPx(wrap, canvasEl);
            stripesEl.innerHTML = '';
            stripesEl.style.display = 'block';
            stripesEl.style.boxSizing = 'border-box';
            stripesEl.style.overflow = 'hidden';
            var barBoxH = Math.min(opts.height, maxBarH);
            stripesEl.style.minHeight = barBoxH + 'px';
            stripesEl.style.height = barBoxH + 'px';
            stripesEl.style.width = 'auto';
            if (wrap) {
                wrap.style.boxSizing = 'border-box';
                wrap.style.width = 'auto';
                wrap.style.maxWidth = Math.max(40, cw - 4) + 'px';
            }
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            svg.setAttribute('preserveAspectRatio', 'xMinYMid meet');
            svg.style.display = 'block';
            svg.style.width = 'auto';
            svg.style.height = '100%';
            stripesEl.appendChild(svg);
            try {
                JsBarcode(svg, boxCode, jsOpts);
                var naturalW = 0;
                try {
                    if (svg.getBBox) naturalW = svg.getBBox().width;
                } catch (bboxErr) {}
                if (!naturalW || naturalW <= 0) {
                    naturalW = parseFloat(svg.getAttribute('width') || '0') || 0;
                }
                var targetW = getBarcodeTargetDisplayWidthPx(wrap, canvasEl, naturalW);
                applyBarcodeDisplayWidth(stripesEl, wrap, targetW, naturalW, barBoxH);
            } catch (e) {
                stripesEl.innerHTML = '';
            }
            if (wrap) {
                enforceStandardBarcodeGraphicBounds(wrap, canvasEl);
                wrap.style.height = 'auto';
                wrap.style.maxHeight = '100%';
                wrap.removeAttribute('data-display-height');
                wrap.querySelectorAll(':scope > svg.barcode-svg').forEach(function(s) {
                    s.style.setProperty('display', 'none', 'important');
                });
                clampBarcodeBlockIntoCanvas(canvasEl, wrap);
                positionStandardBarcodeResizeHandle(wrap);
                syncBarcodeBarHeightPropFromWrap(wrap);
            }
        });
        var bt1 = document.getElementById('barcodeText1');
        var bt2 = document.getElementById('barcodeText2');
        if (bt1) bt1.textContent = code;
        if (bt2) bt2.textContent = code2;
        if (typeof toggleBarcodeNumber === 'function') toggleBarcodeNumber();
        if (preservedPos && bc1WrapEarly) {
            bc1WrapEarly.style.left = preservedPos.left;
            bc1WrapEarly.style.top = preservedPos.top;
        }
        ensureAllBarcodeResizeHandles();
        updateBarcodeQrDisplay();
    }
    /** Re-render canvas barcodes from saved prop inputs (avoids CSS hardcoded stripe width fighting design_layout). */
    function applySavedBarcodeSize() {
        renderCanvasBarcode();
    }
    function renderBarcode() {
        applySavedBarcodeSize();
    }
    /** After layout restore, show either JsBarcode or QR preview matching the active designer mode (not both). */
    function refreshCodeGraphicAfterLayoutRestore() {
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            updateBarcodeQrDisplay();
        } else {
            applySavedBarcodeSize();
        }
    }

    function restoreSavedLayout() {
        if (!savedDesignLayout || !labelPreview1) return;
        try {
            var parsed = JSON.parse(savedDesignLayout);
            if (parsed.layout_type != null && String(parsed.layout_type) !== '' && parsed.layout_type !== currentCodeType && parsed.layout_type !== '82x38_2box') {
                return;
            }
            document.querySelectorAll('.canvas-dropped-item').forEach(function(el) {
                el.remove();
            });
            if (parsed.barcode_bar_width != null) {
                var pw = document.getElementById('propBarcodeBarWidth');
                if (pw) pw.value = Math.max(1, Math.min(10, parseInt(parsed.barcode_bar_width, 10) || 2));
            }
            if (parsed.barcode_bar_height != null) {
                var ph = document.getElementById('propBarcodeBarHeight');
                if (ph) ph.value = Math.max(10, Math.min(200, parseInt(parsed.barcode_bar_height, 10) || 28));
            }
            if (parsed.qr_width != null) {
                var qw = document.getElementById('propQrWidth');
                if (qw) qw.value = Math.max(12, Math.min(200, parseInt(parsed.qr_width, 10) || 60));
            }
            if (parsed.qr_height != null) {
                var qh = document.getElementById('propQrHeight');
                if (qh) qh.value = Math.max(12, Math.min(200, parseInt(parsed.qr_height, 10) || 60));
            }
            var arr = Array.isArray(parsed) ? parsed : (
                (parsed.box1 && Array.isArray(parsed.box1.items)) ? parsed.box1.items
                : (parsed.fields || parsed.items || [])
            );
            var arr2 = Array.isArray(parsed) ? [] : (
                (parsed.box2 && Array.isArray(parsed.box2.items)) ? parsed.box2.items
                : (parsed.fields2 || parsed.items2 || [])
            );
            arr = dedupeSavedLayoutItems(arr);
            arr2 = dedupeSavedLayoutItems(arr2);
            var hasPinnedBarcode1Px = (parsed.barcode_left !== undefined && parsed.barcode_left !== null &&
                parsed.barcode_top !== undefined && parsed.barcode_top !== null);
            var hasLegacyBarcodePos = (parsed.barcode_position &&
                typeof parsed.barcode_position.left === 'number' && !isNaN(parsed.barcode_position.left) &&
                typeof parsed.barcode_position.top === 'number' && !isNaN(parsed.barcode_position.top));
            var hasMmBarcode1Pos = (parsed.barcode1_left != null && parsed.barcode1_top != null);
            var is82Restore = (parsed.layout_type === '82x38_2box' || parsed.sticker_82x38_2box || parsed.box1_left_mm != null);
            var isDualRestore = isDualCanvasLayoutPreset();
            var skipMmBarcode1Position = hasPinnedBarcode1Px || hasLegacyBarcodePos;
            if (is82Restore) skipMmBarcode1Position = true;
            if (!isDualRestore && !is82Restore && hasMmBarcode1Pos) {
                skipMmBarcode1Position = false;
            }
            var usedMmBarcode1Restore = false;
            var labelBoxWMm = isDualRestore ? getDualTagPrintBoxWidthMm() : (labelWidthMm || 100);
            var labelBoxHMm = isDualRestore ? getDualTagPrintBoxHeightMm() : (labelHeightMm || 18);
            /* Must match save math: px/mm uses #labelCanvas dimensions, not #labelPreview (preview includes padding → wider → drift right each reload). */
            var canvas1Restore = labelCanvas1 || document.getElementById('labelCanvas1');
            var cw = (canvas1Restore && canvas1Restore.offsetWidth > 0) ? canvas1Restore.offsetWidth : (labelPreview1.offsetWidth || 270);
            var ch = (canvas1Restore && canvas1Restore.offsetHeight > 0) ? canvas1Restore.offsetHeight : (labelPreview1.offsetHeight || 54);
            var mmToPxX = cw / labelBoxWMm;
            var mmToPxY = ch / labelBoxHMm;
            var barcodePrintWrap = document.getElementById('barcode1');
            var barcodeStripes = labelPreview1.querySelector('.barcode-stripes');
            if (arr.length > 0) {
                arr.forEach(function(it) {
                    if (!it) return;
                    if (it.type === 'barcode_image') {
                        if (barcodePrintWrap) {
                            barcodePrintWrap.style.position = 'absolute';
                            barcodePrintWrap.style.margin = '0';
                            var wMm = typeof it.width === 'number' ? it.width : (parseFloat(it.width) || 33);
                            var hMm = typeof it.height === 'number' ? it.height : (parseFloat(it.height) || 6);
                            if (!skipMmBarcode1Position && !is82Restore) {
                                var leftMm = typeof it.left === 'number' ? it.left : (parseFloat(it.left) || 0);
                                var topMm = typeof it.top === 'number' ? it.top : (parseFloat(it.top) || 0);
                                barcodePrintWrap.style.left = Math.round(leftMm * mmToPxX) + 'px';
                                barcodePrintWrap.style.top = Math.round(topMm * mmToPxY) + 'px';
                                usedMmBarcode1Restore = true;
                            }
                            if (!isDualRestore && barcodePrintWrap && barcodeStripes) {
                                var wPx = Math.round(wMm * mmToPxX);
                                var hPx = Math.round(hMm * mmToPxY);
                                var maxBarPx = Math.max(10, ch - 4);
                                var optsCap = getBarcodeBarOptions();
                                hPx = Math.min(hPx, optsCap.height, maxBarPx);
                                wPx = Math.min(wPx, Math.max(40, cw - 4));
                                barcodePrintWrap.style.boxSizing = 'border-box';
                                barcodePrintWrap.style.width = wPx + 'px';
                                barcodePrintWrap.style.height = 'auto';
                                barcodePrintWrap.style.maxHeight = '100%';
                                barcodePrintWrap.style.overflow = 'hidden';
                                barcodePrintWrap.removeAttribute('data-display-height');
                                barcodeStripes.style.width = '100%';
                                barcodeStripes.style.height = hPx + 'px';
                                barcodeStripes.style.minHeight = hPx + 'px';
                                barcodeStripes.style.overflow = 'hidden';
                            }
                        }
                        return;
                    }
                    if (it.type === 'white_strip' || isWhiteStripType(it)) {
                        if (is82Restore) return;
                        restoreWhiteStripItems([it], labelCanvas1 || labelPreview1, mmToPxX, mmToPxY);
                        return;
                    }
                    if (it.type === 'strip_line' || it.type === 'line' || isStripLineField(it.field)) {
                        if (is82Restore) return;
                        var leftMmS = typeof it.left === 'number' ? it.left : (parseFloat(it.left) || 0);
                        var topMmS = typeof it.top === 'number' ? it.top : (parseFloat(it.top) || 0);
                        var leftPxS = Math.round(leftMmS * mmToPxX);
                        var topPxS = Math.round(topMmS * mmToPxY);
                        var widthMmS = typeof it.width === 'number' ? it.width : (parseFloat(it.width) || 0);
                        var widthPxS = widthMmS > 0 ? Math.round(widthMmS * mmToPxX) : Math.max(24, cw - 4);
                        createDroppedItem('StripLine', leftPxS, topPxS, labelCanvas1 || labelPreview1, {
                            prefix: '',
                            suffix: '',
                            left: leftPxS,
                            top: topPxS,
                            width: widthPxS,
                            restore: true
                        });
                        return;
                    }
                    if (it.field) {
                        if (is82Restore) return;
                        if (isWhiteStripField(it.field)) return;
                        var leftMm = typeof it.left === 'number' ? it.left : (parseFloat(it.left) || 0);
                        var topMm = typeof it.top === 'number' ? it.top : (parseFloat(it.top) || 0);
                        var leftPx = (it.type === 'text' || leftMm <= 200) ? Math.round(leftMm * mmToPxX) : (parseInt(it.left, 10) || 15);
                        var topPx = (it.type === 'text' || topMm <= 100) ? Math.round(topMm * mmToPxY) : (parseInt(it.top, 10) || 20);
                        createDroppedItem(it.field, leftPx, topPx, labelCanvas1 || labelPreview1, {
                            prefix: it.prefix !== undefined ? it.prefix : it.field,
                            suffix: it.suffix !== undefined ? it.suffix : '',
                            font: it.font !== undefined ? it.font : 'Arial',
                            font_size: it.font_size !== undefined ? it.font_size : 10,
                            left: leftPx,
                            top: topPx,
                            restore: true,
                            pad_top: it.pad_top !== undefined ? it.pad_top : 0,
                            pad_right: it.pad_right !== undefined ? it.pad_right : 0,
                            pad_bottom: it.pad_bottom !== undefined ? it.pad_bottom : 0,
                            pad_left: it.pad_left !== undefined ? it.pad_left : 0
                        });
                    }
                });
            }
            if (!skipMmBarcode1Position && !Array.isArray(parsed) && parsed.barcode1_top != null && parsed.barcode1_left != null && barcodePrintWrap) {
                barcodePrintWrap.style.left = Math.round(parseFloat(parsed.barcode1_left) * mmToPxX) + 'px';
                barcodePrintWrap.style.top = Math.round(parseFloat(parsed.barcode1_top) * mmToPxY) + 'px';
                usedMmBarcode1Restore = true;
            }
            if (!Array.isArray(parsed) && (parsed.layout_type === '82x38_2box' || parsed.sticker_82x38_2box || parsed.box1_left_mm != null)) {
                function setBoxInput(id, val) {
                    var el = document.getElementById(id);
                    if (el && val != null && !isNaN(parseFloat(val))) el.value = parseFloat(val);
                }
                force82x38FixedBoxPositions();
                force82x38BoxSizeInputs();
                setBoxInput('propBox1BarcodeWidthMm', parsed.box1_barcode_width_mm || parsed.barcode_width_mm || parsed.box_barcode_width_mm);
                setBoxInput('propBox1BarcodeHeightMm', parsed.box1_barcode_height_mm || parsed.barcode_height_mm || parsed.box_barcode_height_mm);
                setBoxInput('propBox2BarcodeWidthMm', parsed.box2_barcode_width_mm || parsed.barcode_width_mm || parsed.box_barcode_width_mm);
                setBoxInput('propBox2BarcodeHeightMm', parsed.box2_barcode_height_mm || parsed.barcode_height_mm || parsed.box_barcode_height_mm);
                var b1BarLeft = parsed.box1_barcode_left_mm;
                var b1BarTop = parsed.box1_barcode_top_mm;
                var b2BarLeft = parsed.box2_barcode_left_mm;
                var b2BarTop = parsed.box2_barcode_top_mm;
                if (parsed.barcode1 && parsed.barcode1.left_mm != null) b1BarLeft = parsed.barcode1.left_mm;
                if (parsed.barcode1 && parsed.barcode1.top_mm != null) b1BarTop = parsed.barcode1.top_mm;
                if (parsed.barcode2 && parsed.barcode2.left_mm != null) b2BarLeft = parsed.barcode2.left_mm;
                if (parsed.barcode2 && parsed.barcode2.top_mm != null) b2BarTop = parsed.barcode2.top_mm;
                if (b1BarLeft == null) b1BarLeft = parsed.barcode_left_mm;
                if (b1BarTop == null) b1BarTop = parsed.barcode_top_mm;
                if (parsed.barcode1 && parsed.barcode1.width_mm != null) {
                    setBoxInput('propBox1BarcodeWidthMm', parsed.barcode1.width_mm);
                }
                if (parsed.barcode1 && parsed.barcode1.height_mm != null) {
                    setBoxInput('propBox1BarcodeHeightMm', parsed.barcode1.height_mm);
                }
                if (parsed.barcode2 && parsed.barcode2.width_mm != null) {
                    setBoxInput('propBox2BarcodeWidthMm', parsed.barcode2.width_mm);
                }
                if (parsed.barcode2 && parsed.barcode2.height_mm != null) {
                    setBoxInput('propBox2BarcodeHeightMm', parsed.barcode2.height_mm);
                }
                /* Clamp / repair corrupt off-canvas coords (QR mode used to save left_mm:-208). */
                if (typeof clamp82x38BarcodeGraphicMm === 'function') {
                    var b1wR = parseFloat(document.getElementById('propBox1BarcodeWidthMm') && document.getElementById('propBox1BarcodeWidthMm').value);
                    var b1hR = parseFloat(document.getElementById('propBox1BarcodeHeightMm') && document.getElementById('propBox1BarcodeHeightMm').value);
                    var b2wR = parseFloat(document.getElementById('propBox2BarcodeWidthMm') && document.getElementById('propBox2BarcodeWidthMm').value);
                    var b2hR = parseFloat(document.getElementById('propBox2BarcodeHeightMm') && document.getElementById('propBox2BarcodeHeightMm').value);
                    var fix1 = clamp82x38BarcodeGraphicMm(b1BarLeft, b1BarTop, b1wR, b1hR);
                    var fix2 = clamp82x38BarcodeGraphicMm(b2BarLeft, b2BarTop, b2wR, b2hR);
                    /* Flush-top QR (top=0) — vertically center so designer matches print on right ear. */
                    if (typeof center82x38QrIfFlushTop === 'function') {
                        fix1 = center82x38QrIfFlushTop(fix1);
                        fix2 = center82x38QrIfFlushTop(fix2);
                    }
                    b1BarLeft = fix1.left_mm;
                    b1BarTop = fix1.top_mm;
                    b2BarLeft = fix2.left_mm;
                    b2BarTop = fix2.top_mm;
                    setBoxInput('propBox1BarcodeWidthMm', fix1.width_mm);
                    setBoxInput('propBox1BarcodeHeightMm', fix1.height_mm);
                    setBoxInput('propBox2BarcodeWidthMm', fix2.width_mm);
                    setBoxInput('propBox2BarcodeHeightMm', fix2.height_mm);
                }
                setBoxInput('propBox1BarcodeLeftMm', b1BarLeft);
                setBoxInput('propBox1BarcodeTopMm', b1BarTop);
                setBoxInput('propBox2BarcodeLeftMm', b2BarLeft);
                setBoxInput('propBox2BarcodeTopMm', b2BarTop);
                setBoxInput('propBoxBarcodeNoMarginTopMm', parsed.barcode_no_margin_top_mm || parsed.barcode_number_gap_mm);
                setBoxInput('propBoxBarcodeNoFontSize', parsed.barcode_no_font_size || parsed.barcode_number_font_pt);
                /* After restoring mm sizes, sync QR px props from box1 so the Properties panel matches the canvas. */
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
                    var b1w = parseFloat(document.getElementById('propBox1BarcodeWidthMm') && document.getElementById('propBox1BarcodeWidthMm').value);
                    var b1h = parseFloat(document.getElementById('propBox1BarcodeHeightMm') && document.getElementById('propBox1BarcodeHeightMm').value);
                    if (!isNaN(b1w) && b1w > 0 && !isNaN(b1h) && b1h > 0) {
                        syncQrPxFrom82x38WrapMm(b1w, b1h, document.getElementById('box1'));
                    } else if (parsed.qr_width != null || parsed.qr_height != null) {
                        var qwR = document.getElementById('propQrWidth');
                        var qhR = document.getElementById('propQrHeight');
                        if (qwR && parsed.qr_width != null) qwR.value = Math.max(12, Math.min(200, parseInt(parsed.qr_width, 10) || 60));
                        if (qhR && parsed.qr_height != null) qhR.value = Math.max(12, Math.min(200, parseInt(parsed.qr_height, 10) || 60));
                    }
                }
                syncDualCanvasHeight();
                if (typeof layout82x38DualCanvases === 'function') layout82x38DualCanvases();
            }
            if (arr2.length > 0 && labelPreview2) {
                var preview2ForRestore = labelPreview2;
                var canvas2Restore = labelCanvas2 || document.getElementById('labelCanvas2');
                var cw2 = (canvas2Restore && canvas2Restore.offsetWidth > 0) ? canvas2Restore.offsetWidth : (preview2ForRestore.offsetWidth || 270);
                var ch2 = (canvas2Restore && canvas2Restore.offsetHeight > 0) ? canvas2Restore.offsetHeight : (preview2ForRestore.offsetHeight || 54);
                var mmToPxX2 = cw2 / labelBoxWMm;
                var mmToPxY2 = ch2 / labelBoxHMm;
                var barcodePrintWrap2 = document.getElementById('barcode2');
                var barcodeStripes2 = barcodePrintWrap2 ? barcodePrintWrap2.querySelector('.barcode-stripes') : null;
                arr2.forEach(function(it) {
                    if (!it) return;
                    if (it.type === 'barcode_image') {
                        if (barcodePrintWrap2) {
                            barcodePrintWrap2.style.position = 'absolute';
                            barcodePrintWrap2.style.margin = '0';
                            var leftMm = typeof it.left === 'number' ? it.left : (parseFloat(it.left) || 0);
                            var topMm = typeof it.top === 'number' ? it.top : (parseFloat(it.top) || 0);
                            var wMm = typeof it.width === 'number' ? it.width : (parseFloat(it.width) || 33);
                            var hMm = typeof it.height === 'number' ? it.height : (parseFloat(it.height) || 6);
                            if (!is82Restore) {
                                barcodePrintWrap2.style.left = Math.round(leftMm * mmToPxX2) + 'px';
                                barcodePrintWrap2.style.top = Math.round(topMm * mmToPxY2) + 'px';
                            }
                            if (!isDualRestore && barcodePrintWrap2 && barcodeStripes2) {
                                var wPx2 = Math.round(wMm * mmToPxX2);
                                var hPx2 = Math.round(hMm * mmToPxY2);
                                var maxBarPx2 = Math.max(10, ch2 - 4);
                                var optsCap2 = getBarcodeBarOptions();
                                hPx2 = Math.min(hPx2, optsCap2.height, maxBarPx2);
                                barcodePrintWrap2.style.boxSizing = 'border-box';
                                barcodePrintWrap2.style.width = wPx2 + 'px';
                                barcodePrintWrap2.style.height = 'auto';
                                barcodePrintWrap2.style.maxHeight = '100%';
                                barcodePrintWrap2.style.overflow = 'hidden';
                                barcodePrintWrap2.removeAttribute('data-display-height');
                                barcodeStripes2.style.width = '100%';
                                barcodeStripes2.style.height = hPx2 + 'px';
                                barcodeStripes2.style.minHeight = hPx2 + 'px';
                                barcodeStripes2.style.overflow = 'hidden';
                            }
                        }
                        return;
                    }
                    if (it.type === 'white_strip' || isWhiteStripType(it)) {
                        if (is82Restore) return;
                        restoreWhiteStripItems([it], canvas2Restore || preview2ForRestore, mmToPxX2, mmToPxY2);
                        return;
                    }
                    if (it.type === 'strip_line' || it.type === 'line' || isStripLineField(it.field)) {
                        if (is82Restore) return;
                        var leftMmS2 = typeof it.left === 'number' ? it.left : (parseFloat(it.left) || 0);
                        var topMmS2 = typeof it.top === 'number' ? it.top : (parseFloat(it.top) || 0);
                        var leftPxS2 = Math.round(leftMmS2 * mmToPxX2);
                        var topPxS2 = Math.round(topMmS2 * mmToPxY2);
                        var widthMmS2 = typeof it.width === 'number' ? it.width : (parseFloat(it.width) || 0);
                        var c2w = (canvas2Restore || preview2ForRestore);
                        var widthPxS2 = widthMmS2 > 0 ? Math.round(widthMmS2 * mmToPxX2) : Math.max(24, (c2w && c2w.clientWidth ? c2w.clientWidth : 100) - 4);
                        createDroppedItem('StripLine', leftPxS2, topPxS2, canvas2Restore || preview2ForRestore, {
                            prefix: '',
                            suffix: '',
                            left: leftPxS2,
                            top: topPxS2,
                            width: widthPxS2,
                            restore: true
                        });
                        return;
                    }
                    if (it.field) {
                        if (is82Restore) return;
                        if (isWhiteStripField(it.field)) return;
                        var leftMm = typeof it.left === 'number' ? it.left : (parseFloat(it.left) || 0);
                        var topMm = typeof it.top === 'number' ? it.top : (parseFloat(it.top) || 0);
                        var leftPx = Math.round(leftMm * mmToPxX2);
                        var topPx = Math.round(topMm * mmToPxY2);
                        createDroppedItem(it.field, leftPx, topPx, canvas2Restore || preview2ForRestore, {
                            prefix: it.prefix !== undefined ? it.prefix : it.field,
                            suffix: it.suffix !== undefined ? it.suffix : '',
                            font: it.font !== undefined ? it.font : 'Arial',
                            font_size: it.font_size !== undefined ? it.font_size : 10,
                            left: leftPx,
                            top: topPx,
                            restore: true,
                            pad_top: it.pad_top !== undefined ? it.pad_top : 0,
                            pad_right: it.pad_right !== undefined ? it.pad_right : 0,
                            pad_bottom: it.pad_bottom !== undefined ? it.pad_bottom : 0,
                            pad_left: it.pad_left !== undefined ? it.pad_left : 0
                        });
                    }
                });
                if (parsed.barcode2_top != null && parsed.barcode2_left != null && barcodePrintWrap2) {
                    barcodePrintWrap2.style.left = Math.round(parseFloat(parsed.barcode2_left) * mmToPxX2) + 'px';
                    barcodePrintWrap2.style.top = Math.round(parseFloat(parsed.barcode2_top) * mmToPxY2) + 'px';
                }
            }
            if (!Array.isArray(parsed) && parsed.barcode2_top != null && parsed.barcode2_left != null && labelPreview2) {
                var parsedRef = parsed;
                setTimeout(function() {
                    var c2 = document.getElementById('labelCanvas2');
                    var lp2 = document.getElementById('labelPreview2');
                    var bc2 = document.getElementById('barcode2');
                    if (!lp2 || !bc2 || lp2.offsetHeight <= 0) return;
                    var cwLate = (c2 && c2.offsetWidth > 0) ? c2.offsetWidth : (lp2.offsetWidth || 270);
                    var chLate = (c2 && c2.offsetHeight > 0) ? c2.offsetHeight : (lp2.offsetHeight || 54);
                    var boxWMm = getDualTagPrintBoxWidthMm();
                    var boxHMm = getDualTagPrintBoxHeightMm();
                    restoreBarcode2Position(parsedRef, cwLate / boxWMm, chLate / boxHMm);
                    clampBarcodeBlockIntoCanvas(c2 || lp2, bc2);
                }, 150);
            }
            if (is82Restore) {
                restore82x38BoxTextFields(parsed);
            }
            syncToolboxHighlight();
            dedupeCanvasDomFields();
            var rootAfterRestore = labelCanvas1 || labelPreview1;
            syncPropertiesPanelAfterLayoutRestore(rootAfterRestore);
            if (!usedMmBarcode1Restore) {
                restoreBarcodePosition(parsed);
            }
            refreshCodeGraphicAfterLayoutRestore();
            if (!usedMmBarcode1Restore) {
                restoreBarcodePosition(parsed);
            }
            clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
            clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
            setTimeout(function() {
                refreshCodeGraphicAfterLayoutRestore();
                setTimeout(function() {
                    if (!usedMmBarcode1Restore) {
                        restoreBarcodePosition(parsed);
                    }
                    if (isDualCanvasLayoutPreset() && labelCanvas2 && !is82Restore) {
                        var cw2Late = labelCanvas2.offsetWidth || 270;
                        var ch2Late = labelCanvas2.offsetHeight || 54;
                        restoreBarcode2Position(parsed, cw2Late / getDualTagPrintBoxWidthMm(), ch2Late / getDualTagPrintBoxHeightMm());
                    }
                    clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                    clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
                    if (!is82Restore) {
                        var adjWMm = isDualCanvasLayoutPreset() ? getDualTagPrintBoxWidthMm() : labelWidthMm;
                        var adjHMm = isDualCanvasLayoutPreset() ? getDualTagPrintBoxHeightMm() : labelHeightMm;
                        if (labelPreview1 && labelCanvas1) adjustDroppedTextBelowBarcode(labelPreview1, labelCanvas1, adjWMm, adjHMm, 'barcode1');
                        if (labelPreview2 && labelCanvas2) adjustDroppedTextBelowBarcode(labelPreview2, labelCanvas2, adjWMm, adjHMm, 'barcode2');
                    }
                }, 60);
            }, 80);
        } catch (e) { console.warn('Could not restore barcode design', e); }
    }

    /** Restore barcode positions + dropped columns after 82×38 dual editor DOM is sized. */
    function run82x38SavedLayoutRestore() {
        if (!is82x38TwoBoxPreset()) return;
        if (typeof restoreSavedLayout === 'function') restoreSavedLayout();
        if (typeof layout82x38DualCanvases === 'function') layout82x38DualCanvases();
        if (typeof render82x38PreviewPipeline === 'function') render82x38PreviewPipeline({ skipBoxLayout: true });
        if (typeof syncDualCanvasHeight === 'function') syncDualCanvasHeight();
    }

    function schedule82x38SavedLayoutRestore() {
        if (!is82x38TwoBoxPreset()) return;
        setTimeout(run82x38SavedLayoutRestore, 80);
    }

    /** Apply physical label box size first so mm→px restore uses correct preview dimensions; restore after layout. */
    function initBarcodeDesignerLayoutFromSaved() {
        updatePropertiesPanelForCodeType();
        if (is82x38TwoBoxPreset()) {
            render82x38DualEditor();
            schedule82x38SavedLayoutRestore();
        } else {
            applyLabelSizeToBox();
            ensureBarcodeLayoutRestoredFromSaved(0);
        }
    }
    var barcodeMetalTypeEl = document.getElementById('barcodeMetalType');
    if (barcodeMetalTypeEl) {
        barcodeMetalTypeEl.addEventListener('change', onBarcodeMetalTypeChange);
    }
    function syncToolboxToMetalDropdownOnLoad() {
        var ms = document.getElementById('barcodeMetalType');
        if (!ms) return;
        var metal = ms.value || '';
        if (!metal) {
            try { metal = sessionStorage.getItem('barcode_setting_metal') || ''; } catch (e) {}
            if (metal) {
                ms.value = metal;
                currentBarcodeMetalKey = metal;
            }
        } else {
            currentBarcodeMetalKey = metal;
        }
        if (metal) {
            activateToolboxCategory(metalDisplayToToolboxCategory(metal));
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBarcodeDesignerLayoutFromSaved);
        document.addEventListener('DOMContentLoaded', syncToolboxToMetalDropdownOnLoad);
        document.addEventListener('DOMContentLoaded', attach82x38BarcodeResizeDelegation);
    } else {
        initBarcodeDesignerLayoutFromSaved();
        syncToolboxToMetalDropdownOnLoad();
        attach82x38BarcodeResizeDelegation();
    }

    var barcode1El = document.getElementById('barcode1');
    var barcode2El = document.getElementById('barcode2');
    if (labelCanvas1 && barcode1El) {
        barcode1El.style.position = 'absolute';
        barcode1El.style.margin = '0';
    }
    if (labelCanvas2 && barcode2El) {
        barcode2El.style.position = 'absolute';
        barcode2El.style.margin = '0';
    }

    function toggleBarcodeNumber() {
        var chk = document.getElementById('barcodeShowBarcodeNo');
        if (!chk) return;
        if (is82x38TwoBoxPreset()) {
            toggle82x38BarcodeTextVisibility();
            reapply82x38BarcodePositionsFromInputs();
            return;
        }
        var show = chk.checked;
        document.querySelectorAll('.barcode-text').forEach(function(el) {
            el.style.display = show ? 'block' : 'none';
        });
        /* Barcode image/SVG always visible — checkbox controls number text only. */
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            if (typeof updateBarcodeQrDisplay === 'function') updateBarcodeQrDisplay();
            return;
        }
        if (is82x38TwoBoxPreset()) {
            document.querySelectorAll('.barcode-svg-box1, .barcode-svg-box2').forEach(function(el) {
                el.style.display = 'block';
                el.style.visibility = 'visible';
            });
        } else {
            document.querySelectorAll('.barcode-stripes, .barcode-svg:not(.barcode-svg-box1):not(.barcode-svg-box2)').forEach(function(el) {
                el.style.display = '';
                el.style.visibility = 'visible';
            });
        }
    }
    var barcodeShowBarcodeNoEl = document.getElementById('barcodeShowBarcodeNo');
    if (barcodeShowBarcodeNoEl) {
        barcodeShowBarcodeNoEl.addEventListener('change', function() {
            flushCheckboxDomToPersisted();
            toggleBarcodeNumber();
        });
    }
    toggleBarcodeNumber();

    (function initBarcode1Drag() {
        var barcodeBox = barcode1El;
        if (!barcodeBox) return;
        if (!barcodeBox.classList.contains('barcode-inner-draggable')) {
            initBarcodePrintWrapInteractions(barcodeBox);
            ensureAllBarcodeResizeHandles();
        }
    })();
    (function initBarcode2Drag() {
        var barcodeBox = barcode2El;
        if (!barcodeBox) return;
        if (!barcodeBox.classList.contains('barcode-inner-draggable')) {
            initBarcodePrintWrapInteractions(barcodeBox);
            ensureAllBarcodeResizeHandles();
        }
    })();
    function centerBarcode(barcodeBox, canvas) {
        if (!barcodeBox || !canvas) return;
        if (is82x38TwoBoxPreset()) {
            var bl = read82x38BoxLayoutFromInputs();
            var isBox2 = barcodeBox.id === 'barcode2';
            var barW = isBox2 ? bl.box2_barcode_width_mm : bl.box1_barcode_width_mm;
            var barH = isBox2 ? bl.box2_barcode_height_mm : bl.box1_barcode_height_mm;
            var leftMm = Math.max(0, Math.round(((bl.box_width_mm - barW) / 2) * 10) / 10);
            var topMm = Math.max(0, Math.round(((bl.box_height_mm - barH) / 2) * 10) / 10);
            apply82x38BarcodeLayoutMm(barcodeBox, leftMm, topMm, barW, barH);
            if (isBox2) {
                var el2L = document.getElementById('propBox2BarcodeLeftMm');
                var el2T = document.getElementById('propBox2BarcodeTopMm');
                if (el2L) el2L.value = leftMm;
                if (el2T) el2T.value = topMm;
            } else {
                var el1L = document.getElementById('propBox1BarcodeLeftMm');
                var el1T = document.getElementById('propBox1BarcodeTopMm');
                if (el1L) el1L.value = leftMm;
                if (el1T) el1T.value = topMm;
            }
            log82x38AppliedBarcodes();
            return;
        }
        barcodeBox.style.left = Math.max(0, (canvas.offsetWidth - (barcodeBox.offsetWidth || 120)) / 2) + 'px';
        barcodeBox.style.top = Math.max(0, (canvas.offsetHeight - (barcodeBox.offsetHeight || 30)) / 2) + 'px';
    }

    function bindLabelCanvasDropTarget(canvasEl) {
        if (!canvasEl || canvasEl._dropTargetBound) return;
        canvasEl._dropTargetBound = true;
        canvasEl.addEventListener('dragover', function(e) {
            if (e.dataTransfer.types.indexOf('text/plain') < 0 && e.dataTransfer.types.indexOf('application/x-canvas-item') < 0) return;
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = (e.dataTransfer.types.indexOf('application/x-canvas-item') >= 0) ? 'move' : 'copy';
            if (dropLayer) dropLayer.classList.add('drag-over');
        });
        canvasEl.addEventListener('dragleave', function(e) {
            if (!canvasEl.contains(e.relatedTarget) && dropLayer) dropLayer.classList.remove('drag-over');
        });
        canvasEl.addEventListener('drop', function(e) {
            if (e.dataTransfer.types.indexOf('text/plain') < 0 && e.dataTransfer.types.indexOf('application/x-canvas-item') < 0) return;
            e.preventDefault();
            e.stopPropagation();
            if (dropLayer) dropLayer.classList.remove('drag-over');
            var rect = canvasEl.getBoundingClientRect();
            handleDrop(e, rect, canvasEl);
        });
    }
    bindLabelCanvasDropTarget(labelCanvas1);
    bindLabelCanvasDropTarget(labelCanvas2);

    if (whiteDropZone) {
        document.addEventListener('dragstart', function(e) {
            if (!e.target.closest('.toolbox-field-item') && !e.target.closest('.canvas-dropped-item')) return;
            whiteDropZone.classList.add('dragging-active');
            document.body.classList.add('is-barcode-dragging');
            if (labelCanvas1) labelCanvas1.classList.add('is-toolbox-dragging');
            if (labelCanvas2) labelCanvas2.classList.add('is-toolbox-dragging');
            if (dropLayer) dropLayer.classList.add('drag-over');
        });
        function endBarcodeCanvasDragUi() {
            clearBarcodeDragDecorations();
        }
        document.addEventListener('dragend', endBarcodeCanvasDragUi);
        document.addEventListener('drop', endBarcodeCanvasDragUi);
    }
    if (whiteDropZone2) {
        document.addEventListener('dragstart', function(e) {
            if (!e.target.closest('.toolbox-field-item') && !e.target.closest('.canvas-dropped-item')) return;
            whiteDropZone2.classList.add('dragging-active');
        });
        document.addEventListener('dragend', function() {
            if (whiteDropZone2) whiteDropZone2.classList.remove('dragging-active');
        });
        document.addEventListener('drop', function() {
            if (whiteDropZone2) whiteDropZone2.classList.remove('dragging-active');
        });
    }

    dropLayer.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = (e.dataTransfer.types.indexOf('application/x-canvas-item') >= 0) ? 'move' : 'copy';
        dropLayer.classList.add('drag-over');
    });
    dropLayer.addEventListener('dragleave', function(e) {
        if (!dropLayer.contains(e.relatedTarget)) dropLayer.classList.remove('drag-over');
    });
    dropLayer.addEventListener('drop', function(e) {
        e.preventDefault();
        dropLayer.classList.remove('drag-over');
        var rect = getDropLayerRect();
        handleDrop(e, rect, dropLayer);
    });

    canvas.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = (e.dataTransfer.types.indexOf('application/x-canvas-item') >= 0) ? 'move' : 'copy';
    }, true);
    canvas.addEventListener('drop', function(e) {
        if (e.target.closest('#barcodeWhiteDropZone') || e.target.closest('#barcodeWhiteDropZone2') || e.target.closest('.canvas-dropped-item')) return;
        e.preventDefault();
        handleDrop(e, getDropLayerRect(), dropLayer);
    }, true);

    canvas.addEventListener('click', function(e) {
        if (!isBarcodeGraphicClickTarget(e.target)) {
            clearBarcodeSelection();
        }
        if (!e.target.closest('.canvas-dropped-item')) {
            document.querySelectorAll('.canvas-dropped-item').forEach(function(i) { i.classList.remove('selected'); });
            document.querySelectorAll('.toolbox-field-item').forEach(function(i) { i.classList.remove('selected'); });
            var prop = document.getElementById('propPrefix');
            if (prop) prop.value = '';
            var suf = document.getElementById('propSuffix');
            if (suf) suf.value = '';
            clearPaddingInputs();
        }
    });

    // When Prefix, Suffix, Font or Font Size changes in Properties, update the selected item in barcode preview
    (function() {
        var propPrefix = document.getElementById('propPrefix');
        var propSuffix = document.getElementById('propSuffix');
        var propFont = document.getElementById('propFont');
        var propFontSize = document.getElementById('propFontSize');
        function syncPropsToSelected() {
            var sel = document.querySelector('.canvas-dropped-item.selected');
            if (!sel) return;
            sel.setAttribute('data-prefix', propPrefix ? propPrefix.value : '');
            sel.setAttribute('data-suffix', propSuffix ? propSuffix.value : '');
            updateDroppedItemDisplay(sel);
        }
        function syncFontToSelected() {
            var sel = document.querySelector('.canvas-dropped-item.selected');
            if (!sel) return;
            sel.setAttribute('data-font', propFont ? propFont.value : 'Arial');
            sel.setAttribute('data-font-size', propFontSize ? propFontSize.value : '10');
            applyDroppedItemFont(sel);
            syncToSecondLabel();
        }
        if (propPrefix) { 
            propPrefix.addEventListener('input', function() { syncPropsToSelected(); syncToSecondLabel(); }); 
            propPrefix.addEventListener('change', function() { syncPropsToSelected(); syncToSecondLabel(); }); 
        }
        if (propSuffix) { 
            propSuffix.addEventListener('input', function() { syncPropsToSelected(); syncToSecondLabel(); }); 
            propSuffix.addEventListener('change', function() { syncPropsToSelected(); syncToSecondLabel(); }); 
        }
        if (propFont) { propFont.addEventListener('change', syncFontToSelected); }
        if (propFontSize) { propFontSize.addEventListener('input', syncFontToSelected); propFontSize.addEventListener('change', syncFontToSelected); }
    })();

    (function initWhiteStripProperties() {
        var ids = [
            'propWhiteStripWidth', 'propWhiteStripHeight', 'propWhiteStripBg', 'propWhiteStripBorderEnabled',
            'propWhiteStripBorderColor', 'propWhiteStripBorderWidth', 'propWhiteStripBorderRadius',
            'propWhiteStripOpacity', 'propWhiteStripRotation', 'propWhiteStripZIndex'
        ];
        ids.forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', applyWhiteStripPropsToSelected);
            el.addEventListener('change', applyWhiteStripPropsToSelected);
        });
        function selectedWs() {
            return document.querySelector('.canvas-white-strip.selected');
        }
        function setZ(sel, z) {
            sel.setAttribute('data-z-index', String(z));
            applyWhiteStripStyle(sel);
            var zEl = document.getElementById('propWhiteStripZIndex');
            if (zEl) zEl.value = String(z);
        }
        var btnFwd = document.getElementById('btnWhiteStripBringForward');
        var btnBackOne = document.getElementById('btnWhiteStripSendBackward');
        var btnFront = document.getElementById('btnWhiteStripBringFront');
        var btnBack = document.getElementById('btnWhiteStripSendBack');
        if (btnFwd) {
            btnFwd.addEventListener('click', function() {
                var sel = selectedWs(); if (!sel) return;
                setZ(sel, (parseInt(sel.getAttribute('data-z-index'), 10) || 5) + 1);
            });
        }
        if (btnBackOne) {
            btnBackOne.addEventListener('click', function() {
                var sel = selectedWs(); if (!sel) return;
                setZ(sel, Math.max(0, (parseInt(sel.getAttribute('data-z-index'), 10) || 5) - 1));
            });
        }
        if (btnFront) {
            btnFront.addEventListener('click', function() {
                var sel = selectedWs(); if (!sel) return;
                var parent = sel.parentElement || document;
                setZ(sel, whiteStripMaxZ(parent) + 1);
                if (parent && parent.appendChild) parent.appendChild(sel);
            });
        }
        if (btnBack) {
            btnBack.addEventListener('click', function() {
                var sel = selectedWs(); if (!sel) return;
                var parent = sel.parentElement;
                setZ(sel, Math.max(0, whiteStripMinZ(parent) - 1));
                if (parent) parent.insertBefore(sel, parent.firstChild);
            });
        }
        /* Mark designer mode for CSS helpers */
        document.body.classList.add('page-set-software');
        var canvasEl = document.getElementById('barcodeCanvas');
        if (canvasEl) canvasEl.classList.add('barcode-designer-mode');
        /* Track which 82×38 box is active for click-to-add White Strip */
        ['box1', 'box2'].forEach(function(id) {
            var box = document.getElementById(id);
            if (!box || box._wsBoxSelectBound) return;
            box._wsBoxSelectBound = true;
            box.addEventListener('mousedown', function() {
                document.querySelectorAll('#box1, #box2').forEach(function(b) { b.classList.remove('barcode-selected'); });
                box.classList.add('barcode-selected');
            }, true);
        });
    })();

    (function() {
        var padMap = [['propPadTop', 'data-pad-top'], ['propPadRight', 'data-pad-right'], ['propPadBottom', 'data-pad-bottom'], ['propPadLeft', 'data-pad-left']];
        function syncPaddingToSelected() {
            var sel = document.querySelector('.canvas-dropped-item.selected');
            if (!sel) return;
            padMap.forEach(function(pair) {
                var inp = document.getElementById(pair[0]);
                if (inp) sel.setAttribute(pair[1], String(clampPadPx(inp.value)));
            });
            syncToSecondLabel();
        }
        padMap.forEach(function(pair) {
            var inp = document.getElementById(pair[0]);
            if (inp) {
                inp.addEventListener('input', syncPaddingToSelected);
                inp.addEventListener('change', syncPaddingToSelected);
            }
        });
    })();

    // Move selected item: buttons + arrow keys (1px nudge; Shift = 8px)
    var MOVE_STEP = 1;
    var MOVE_STEP_LARGE = 8;
    function getMoveStep(e) {
        return (e && e.shiftKey) ? MOVE_STEP_LARGE : MOVE_STEP;
    }
    function move82x38SelectedGraphic(dx, dy) {
        if (!is82x38TwoBoxPreset()) return false;
        /* Only move barcode bars when a barcode wrap is explicitly selected (not the barcode1 fallback). */
        var barSel = document.querySelector('.barcode-print-wrap.barcode-selected, .barcode-inner-draggable.barcode-selected');
        if (!barSel) return false;
        var index = get82x38BarcodeIndex(barSel);
        var leftMm = parseFloat(String(barSel.style.left || '').replace('mm', ''));
        var topMm = parseFloat(String(barSel.style.top || '').replace('mm', ''));
        var widthMm = parseFloat(String(barSel.style.width || '').replace('mm', ''));
        var heightMm = parseFloat(String(barSel.style.height || '').replace('mm', ''));
        if (isNaN(leftMm)) leftMm = 2;
        if (isNaN(topMm)) topMm = 2;
        if (isNaN(widthMm)) widthMm = 16;
        if (isNaN(heightMm)) heightMm = 7;
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            var qrMm = getQrSizeMmFor82x38Wrap(barSel);
            widthMm = qrMm.width_mm;
            heightMm = qrMm.height_mm;
        }
        var box = get82x38BoxForBarcodeIndex(index);
        var scale = get82x38ScaleFromBox(box);
        var dLeftMm = Math.round((dx / scale.x) * 10) / 10;
        var dTopMm = Math.round((dy / scale.y) * 10) / 10;
        var newLeft = Math.max(0, Math.min(BOX_82X38_WIDTH_MM - widthMm, leftMm + dLeftMm));
        var newTop = Math.max(0, Math.min(BOX_82X38_HEIGHT_MM - heightMm, topMm + dTopMm));
        apply82x38BarcodeLayoutMm(barSel, newLeft, newTop, widthMm, heightMm);
        set82x38BarcodePropInputs(index, newLeft, newTop, widthMm, heightMm);
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            updateBarcodeQrDisplay();
        }
        save82x38CurrentLayoutToHiddenJson();
        return true;
    }
    function clampDroppedItemInParent(el) {
        if (!el || !el.parentElement) return;
        var parent = el.parentElement;
        if (typeof is82x38HalfStripContainer === 'function' && is82x38HalfStripContainer(parent)) {
            clampDroppedItemInHalfStrip(el, parent);
            return;
        }
        if (el.classList.contains('canvas-white-strip')) {
            clampWhiteStripInParent(el);
            return;
        }
        var pw = parent.clientWidth || parent.offsetWidth || 0;
        var ph = parent.clientHeight || parent.offsetHeight || 0;
        if (pw <= 0 || ph <= 0) return;
        var l = parseInt(el.style.left, 10); if (isNaN(l)) l = el.offsetLeft || 0;
        var t = parseInt(el.style.top, 10); if (isNaN(t)) t = el.offsetTop || 0;
        var w = el.offsetWidth || 0;
        var h = el.offsetHeight || 0;
        el.style.left = Math.max(0, Math.min(Math.max(0, pw - Math.max(8, w)), l)) + 'px';
        el.style.top = Math.max(0, Math.min(Math.max(0, ph - Math.max(8, h)), t)) + 'px';
    }
    function moveSelected(dx, dy) {
        /* Prefer selected column/text field over barcode graphic (82×38 was always stealing arrows). */
        var sel = document.querySelector('.canvas-dropped-item.selected');
        if (sel) {
            var l = parseInt(sel.style.left, 10);
            var t = parseInt(sel.style.top, 10);
            if (isNaN(l)) l = sel.offsetLeft || 0;
            if (isNaN(t)) t = sel.offsetTop || 0;
            sel.style.left = (l + dx) + 'px';
            sel.style.top = (t + dy) + 'px';
            clampDroppedItemInParent(sel);
            if (typeof syncToSecondLabel === 'function') syncToSecondLabel();
            if (typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset()
                && typeof save82x38CurrentLayoutToHiddenJson === 'function') {
                save82x38CurrentLayoutToHiddenJson();
            }
            return;
        }
        if (move82x38SelectedGraphic(dx, dy)) return;
    }
    document.getElementById('btnMoveUp').addEventListener('click', function() { moveSelected(0, -MOVE_STEP); });
    document.getElementById('btnMoveDown').addEventListener('click', function() { moveSelected(0, MOVE_STEP); });
    document.getElementById('btnMoveLeft').addEventListener('click', function() { moveSelected(-MOVE_STEP, 0); });
    document.getElementById('btnMoveRight').addEventListener('click', function() { moveSelected(MOVE_STEP, 0); });
    document.addEventListener('keydown', function(e) {
        var tag = e.target && e.target.tagName ? e.target.tagName.toUpperCase() : '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (e.target && e.target.isContentEditable)) return;
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        var sel = document.querySelector('.canvas-dropped-item.selected');
        var barSel = document.querySelector('.barcode-print-wrap.barcode-selected, .barcode-inner-draggable.barcode-selected');
        if (!sel && !(barSel && typeof is82x38TwoBoxPreset === 'function' && is82x38TwoBoxPreset())) return;
        e.preventDefault();
        var step = getMoveStep(e);
        if (e.key === 'ArrowUp') moveSelected(0, -step);
        else if (e.key === 'ArrowDown') moveSelected(0, step);
        else if (e.key === 'ArrowLeft') moveSelected(-step, 0);
        else if (e.key === 'ArrowRight') moveSelected(step, 0);
    });

    // Barcode blocks: both draggable independently (mouse drag)
    var barcodeBlock1 = document.getElementById('box1');
    var barcodeBlock2 = document.getElementById('box2');
    var canvasRect = canvas.getBoundingClientRect();
    
    function positionBarcodeBlocks(opts) {
        opts = opts || {};
        if (isDualLabelLayoutPreset()) return;
        var container = getBarcodeLabelsContainerEl() || canvas;
        var cw = container.clientWidth || container.offsetWidth || 0;
        var ch = container.clientHeight || container.offsetHeight || 0;
        if (cw <= 0 || ch <= 0) return;
        var label2 = document.getElementById('box2');
        var showTwo = label2 && label2.style.display !== 'none';
        if (barcodeBlock1) {
            if (!opts.forceCenter && restorePreviewBoxPosition()) {
                return;
            }
            if (!opts.forceCenter) {
                if (barcodeBlock1.classList.contains('barcode-preview-positioned')
                    && barcodeBlock1.style.left && barcodeBlock1.style.top) {
                    return;
                }
                clearPreviewBoxFlexCenter();
                return;
            }
            var w1 = barcodeBlock1.offsetWidth;
            var h1 = barcodeBlock1.offsetHeight;
            barcodeBlock1.classList.add('barcode-preview-positioned');
            barcodeBlock1.style.position = 'absolute';
            if (showTwo) {
                barcodeBlock1.style.left = Math.max(0, Math.round(cw / 4 - w1 / 2)) + 'px';
            } else {
                barcodeBlock1.style.left = Math.max(0, Math.round(cw / 2 - w1 / 2)) + 'px';
            }
            barcodeBlock1.style.top = Math.max(0, Math.round(ch / 2 - h1 / 2)) + 'px';
        }

        if (barcodeBlock2 && showTwo) {
            var w2 = barcodeBlock2.offsetWidth;
            var h2 = barcodeBlock2.offsetHeight;
            barcodeBlock2.style.position = 'absolute';
            barcodeBlock2.style.left = Math.max(0, Math.round(cw * 3 / 4 - w2 / 2)) + 'px';
            barcodeBlock2.style.top = Math.max(0, Math.round(ch / 2 - h2 / 2)) + 'px';
        }
    }
    window.addEventListener('resize', function() {
        if (!isDualLabelLayoutPreset()) positionBarcodeBlocks();
        syncDualCanvasHeight();
    });
    
    function makeBarcodeBlockDraggable(block) {
        if (!block) return;
        var dragging = false, startX, startY, startLeft, startTop;
        
        block.addEventListener('mousedown', function(e) {
            if (is82x38TwoBoxPreset()) return;
            if (isDualLabelLayoutPreset() && !is82x38TwoBoxPreset()) return;
            if (_82x38BarcodeDragState) return;
            if (_82x38BarcodeResizing) return;
            if (e.target.closest('.resize-handle')) return;
            if (e.target.closest('.barcode-inner-draggable')) return;
            if (e.target.closest('.canvas-dropped-item')) return;
            if (e.target.closest('.barcode-print-wrap')) return;
            if (e.target.closest('.barcode-resize-handle')) return;
            dragging = true;
            startX = e.clientX;
            startY = e.clientY;
            if (is82x38TwoBoxPreset()) {
                var outerRect = get82x38OuterRect();
                var boxRect = block.getBoundingClientRect();
                if (outerRect) {
                    startLeft = boxRect.left - outerRect.left;
                    startTop = boxRect.top - outerRect.top;
                } else {
                    startLeft = 0;
                    startTop = 0;
                }
            } else {
                var container = getBarcodeLabelsContainerEl();
                if (container && !block.classList.contains('barcode-preview-positioned')) {
                    var br = block.getBoundingClientRect();
                    var cr = container.getBoundingClientRect();
                    startLeft = br.left - cr.left;
                    startTop = br.top - cr.top;
                    block.classList.add('barcode-preview-positioned');
                    block.style.position = 'absolute';
                    block.style.left = startLeft + 'px';
                    block.style.top = startTop + 'px';
                } else {
                    startLeft = parseInt(block.style.left, 10);
                    if (isNaN(startLeft)) startLeft = block.offsetLeft || 0;
                    startTop = parseInt(block.style.top, 10);
                    if (isNaN(startTop)) startTop = block.offsetTop || 0;
                    block.style.position = 'absolute';
                }
            }
            block.style.zIndex = '100';
        });
        
        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            var newLeft = startLeft + (e.clientX - startX);
            var newTop = startTop + (e.clientY - startY);
            if (is82x38TwoBoxPreset()) {
                var outer82 = get82x38OuterStickerEl();
                var outerRect82 = outer82 ? outer82.getBoundingClientRect() : null;
                if (outerRect82) {
                    var scale82 = get82x38ScaleFromOuter(outerRect82);
                    var boxWPx = BOX_82X38_WIDTH_MM * scale82.x;
                    var boxHPx = BOX_82X38_HEIGHT_MM * scale82.y;
                    newLeft = Math.max(0, Math.min(outerRect82.width - boxWPx, newLeft));
                    newTop = Math.max(0, Math.min(outerRect82.height - boxHPx, newTop));
                }
                block.style.position = 'absolute';
                block.style.left = newLeft + 'px';
                block.style.right = 'auto';
                block.style.top = newTop + 'px';
                block.style.transform = 'none';
                return;
            }
            block.style.left = newLeft + 'px';
            block.style.top = newTop + 'px';
            block.classList.add('barcode-preview-positioned');
        });
        
        document.addEventListener('mouseup', function() { 
            if (dragging) {
                dragging = false;
                block.style.zIndex = '';
                if (is82x38TwoBoxPreset()) {
                    sync82x38BoxPositionsFromDom();
                    layout82x38DualCanvases();
                }
            }
        });
    }
    
    makeBarcodeBlockDraggable(barcodeBlock1);
    makeBarcodeBlockDraggable(barcodeBlock2);

    // Barcode size: +/− update Bar width / Bar height props then re-render (JsBarcode reads props)
    var BARCODE_HEIGHT_STEP = 8;
    var BARCODE_HEIGHT_STEP_SHORT = 2;
    function isShortLabelSize() {
        return (labelHeightMm || 18) <= 20;
    }
    function getBarcodeHeightStep() {
        return isShortLabelSize() ? BARCODE_HEIGHT_STEP_SHORT : BARCODE_HEIGHT_STEP;
    }
    function bumpPropBarcodeBarHeight(delta) {
        var ph = document.getElementById('propBarcodeBarHeight');
        if (!ph) return;
        var minH = isShortLabelSize() ? 8 : 10;
        var v = parseInt(ph.value, 10);
        if (isNaN(v)) v = isShortLabelSize() ? 10 : 28;
        var wrap = getActiveBarcodePrintWrap() || document.getElementById('barcode1');
        var canvasEl = wrap ? wrap.closest('.barcode-label-canvas') : (labelCanvas1 || document.getElementById('labelCanvas1'));
        var maxH = getBarcodeMaxStripeHeightPx(wrap, canvasEl);
        ph.value = String(Math.max(minH, Math.min(maxH, Math.min(200, v + delta))));
    }
    function bumpPropBarcodeBarWidth(delta) {
        var pw = document.getElementById('propBarcodeBarWidth');
        if (!pw) return;
        var v = parseInt(pw.value, 10);
        if (isNaN(v) || v < 1) v = isShortLabelSize() ? 1 : 2;
        pw.value = String(Math.max(1, Math.min(10, v + delta)));
    }
    function bumpPropBarcodeDisplayWidth(delta) {
        var wrap = getActiveBarcodePrintWrap() || document.getElementById('barcode1');
        var prop = document.getElementById('propBarcodeDisplayWidth');
        var canvasEl = wrap ? wrap.closest('.barcode-label-canvas') : (labelCanvas1 || document.getElementById('labelCanvas1'));
        var maxW = getBarcodeMaxDisplayWidthPx(canvasEl, wrap);
        var cur = parseInt(wrap && wrap.getAttribute('data-display-width'), 10);
        if (!cur || cur <= 0) cur = wrap ? wrap.offsetWidth : 0;
        if (!cur || cur <= 0) cur = parseInt(prop && prop.value, 10) || Math.round(maxW * 0.85);
        var newW = Math.max(24, Math.min(maxW, cur + delta));
        if (wrap) wrap.setAttribute('data-display-width', String(newW));
        if (prop) prop.value = String(newW);
    }
    document.getElementById('btnBarcodeDecrease').addEventListener('click', function() {
        var pw = document.getElementById('propBarcodeBarWidth');
        var pwVal = pw ? parseInt(pw.value, 10) : 2;
        if (pwVal > 1) {
            bumpPropBarcodeBarWidth(-1);
        } else {
            bumpPropBarcodeDisplayWidth(-6);
        }
        bumpPropBarcodeBarHeight(-getBarcodeHeightStep());
        onBarSizeChange();
    });
    document.getElementById('btnBarcodeIncrease').addEventListener('click', function() {
        bumpPropBarcodeBarHeight(getBarcodeHeightStep());
        bumpPropBarcodeDisplayWidth(6);
        onBarSizeChange();
    });
    var btnBarcodeCenter = document.getElementById('btnBarcodeCenter');
    if (btnBarcodeCenter && typeof centerBarcode === 'function') {
        btnBarcodeCenter.addEventListener('click', function() {
            if (is82x38TwoBoxPreset()) {
                centerBarcode(barcode1El, labelCanvas1);
                centerBarcode(barcode2El, labelCanvas2);
                if (typeof save82x38CurrentLayoutToHiddenJson === 'function') {
                    save82x38CurrentLayoutToHiddenJson();
                }
                return;
            }
            centerBarcode(barcode1El, labelCanvas1);
            centerBarcode(barcode2El, labelCanvas2);
            clampBarcodeBlockIntoCanvas(labelCanvas1, barcode1El);
            clampBarcodeBlockIntoCanvas(labelCanvas2, barcode2El);
            syncToSecondLabel();
        });
    }
    var propBarWidth = document.getElementById('propBarcodeBarWidth');
    var propBarHeight = document.getElementById('propBarcodeBarHeight');
    function onBarSizeChange() {
        if (is82x38TwoBoxPreset()) {
            render82x38PreviewPipeline({ skipBoxLayout: true });
            return;
        }
        renderBarcode();
        clampBarcodeBlockIntoCanvas(labelCanvas1, barcode1El);
        clampBarcodeBlockIntoCanvas(labelCanvas2, barcode2El);
        syncToSecondLabel();
    }
    if (propBarWidth) {
        propBarWidth.addEventListener('change', onBarSizeChange);
        propBarWidth.addEventListener('input', onBarSizeChange);
    }
    if (propBarHeight) {
        propBarHeight.addEventListener('change', onBarSizeChange);
        propBarHeight.addEventListener('input', onBarSizeChange);
    }
    ['propBox1TopMm', 'propBox2TopMm', 'propBox1BarcodeWidthMm', 'propBox1BarcodeHeightMm', 'propBox2BarcodeWidthMm', 'propBox2BarcodeHeightMm', 'propBox1BarcodeLeftMm', 'propBox1BarcodeTopMm', 'propBox2BarcodeLeftMm', 'propBox2BarcodeTopMm', 'propBoxBarcodeNoMarginTopMm', 'propBoxBarcodeNoFontSize', 'labelPadTop', 'labelPadLeft', 'propStickerPrintOffsetTopMm', 'propStickerPrintOffsetLeftMm'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        function onBoxSettingChange() {
            if (!is82x38TwoBoxPreset()) return;
            if (id.indexOf('BarcodeLeft') >= 0 || id.indexOf('BarcodeTop') >= 0 ||
                id.indexOf('BarcodeWidth') >= 0 || id.indexOf('BarcodeHeight') >= 0) {
                render82x38PreviewPipeline();
                return;
            }
            if (id.indexOf('BarcodeNo') >= 0) {
                [document.getElementById('barcode1'), document.getElementById('barcode2')].forEach(function(w) {
                    if (w) layout82x38BarcodeInnerContent(w);
                });
                if (typeof save82x38CurrentLayoutToHiddenJson === 'function') {
                    save82x38CurrentLayoutToHiddenJson();
                }
                return;
            }
            if (id === 'labelPadTop' || id === 'labelPadLeft' || id === 'propStickerPrintOffsetTopMm' || id === 'propStickerPrintOffsetLeftMm') {
                layout82x38DualCanvases();
                remove82x38OuterCmRuler();
                return;
            }
            layout82x38DualCanvases();
            remove82x38OuterCmRuler();
            if (typeof save82x38CurrentLayoutToHiddenJson === 'function') {
                save82x38CurrentLayoutToHiddenJson();
            }
        }
        el.addEventListener('change', onBoxSettingChange);
        el.addEventListener('input', onBoxSettingChange);
    });
    var propBarcodeDisplayWidth = document.getElementById('propBarcodeDisplayWidth');
    if (propBarcodeDisplayWidth) {
        function onBarcodeDisplayWidthChange() {
            var wrap = getActiveBarcodePrintWrap() || document.getElementById('barcode1');
            var val = parseInt(propBarcodeDisplayWidth.value, 10);
            if (wrap && val > 0) {
                wrap.setAttribute('data-display-width', String(val));
            } else if (wrap) {
                wrap.removeAttribute('data-display-width');
            }
            onBarSizeChange();
        }
        propBarcodeDisplayWidth.addEventListener('change', onBarcodeDisplayWidthChange);
        propBarcodeDisplayWidth.addEventListener('input', onBarcodeDisplayWidthChange);
    }
    var propQrWidth = document.getElementById('propQrWidth');
    var propQrHeight = document.getElementById('propQrHeight');
    function onQrSizeChange() {
        if (_suppressQrPropChange) return;
        if (is82x38TwoBoxPreset() && typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') {
            /* Prop QR px → box mm inputs, then render from saved mm (not prop overwrite). */
            var active = getActiveBarcodePrintWrap && getActiveBarcodePrintWrap();
            var onlyBox = (active && active.id === 'barcode2') ? 2 : ((active && active.id === 'barcode1') ? 1 : null);
            applyQrPxPropsTo82x38BoxMmInputs(onlyBox);
            render82x38PreviewPipeline({ skipBoxLayout: true });
            return;
        }
        updateBarcodeQrDisplay();
    }
    if (propQrWidth) {
        propQrWidth.addEventListener('change', onQrSizeChange);
        propQrWidth.addEventListener('input', onQrSizeChange);
    }
    if (propQrHeight) {
        propQrHeight.addEventListener('change', onQrSizeChange);
        propQrHeight.addEventListener('input', onQrSizeChange);
    }

    // Label size change: show/hide custom inputs and resize box (applyLabelSizeToBox defined earlier, before restore)
    if (labelSizeSelect) {
        labelSizeSelect.addEventListener('change', function() {
            var val = (labelSizeSelect.value || '').trim();
            var wrapW = document.getElementById('barcodeCustomSizeWrap');
            var wrapH = document.getElementById('barcodeCustomHeightWrap');
            var showMm = showBarcodeCustomSizeFields();
            if (wrapW) wrapW.style.display = showMm ? 'flex' : 'none';
            if (wrapH) wrapH.style.display = showMm ? 'flex' : 'none';
            if (val !== STICKER_82X38_PRESET) {
                destroyStandardBarcodePreview();
            }
            onBarcodeLabelSizeChange();
        });
    }
    var customW = document.getElementById('barcodeCustomWidthMm');
    var customH = document.getElementById('barcodeCustomHeightMm');
    if (customW) customW.addEventListener('change', applyLabelSizeToBox);
    if (customH) customH.addEventListener('change', applyLabelSizeToBox);

    function flushCheckboxDomToPersisted() {
        var pn = document.getElementById('barcodeShowProductName');
        var pr = document.getElementById('barcodeShowPrice');
        var bn = document.getElementById('barcodeShowBarcodeNo');
        if (!pn || !pr || !bn) return;
        var vPn = pn.checked ? 1 : 0;
        var vPr = pr.checked ? 1 : 0;
        var vBn = bn.checked ? 1 : 0;
        if (currentCodeType === 'barcode') {
            persistedShowProductNameBarcode = vPn;
            persistedShowPriceBarcode = vPr;
            persistedShowBarcodeNoBarcode = vBn;
        } else {
            persistedShowProductNameQr = vPn;
            persistedShowPriceQr = vPr;
            persistedShowBarcodeNoQr = vBn;
        }
    }
    function applyCheckboxPersistToDom() {
        var pn = document.getElementById('barcodeShowProductName');
        var pr = document.getElementById('barcodeShowPrice');
        var bn = document.getElementById('barcodeShowBarcodeNo');
        if (!pn || !pr || !bn) return;
        if (currentCodeType === 'barcode') {
            pn.checked = !!persistedShowProductNameBarcode;
            pr.checked = !!persistedShowPriceBarcode;
            bn.checked = !!persistedShowBarcodeNoBarcode;
        } else {
            pn.checked = !!persistedShowProductNameQr;
            pr.checked = !!persistedShowPriceQr;
            bn.checked = !!persistedShowBarcodeNoQr;
        }
        toggleBarcodeNumber();
    }

    function buildBarcodeFormPayload() {
        flushCheckboxDomToPersisted();
        var labelSize = (document.getElementById('barcodeLabelSize').value || '100x18').trim();
        var metalSelect = document.getElementById('barcodeMetalType');
        var payload = {
            label_size_preset: labelSize,
            label_width_mm: showBarcodeCustomSizeFields() ? (parseFloat(document.getElementById('barcodeCustomWidthMm').value) || 100) : (labelSize.split('x')[0] || 100),
            label_height_mm: showBarcodeCustomSizeFields() ? (parseFloat(document.getElementById('barcodeCustomHeightMm').value) || 18) : (labelSize.split('x')[1] || 18),
            font_size: parseInt(document.getElementById('barcodeFontSize').value, 10) || 12,
            show_product_name_barcode: persistedShowProductNameBarcode ? 1 : 0,
            show_product_name_qr: persistedShowProductNameQr ? 1 : 0,
            show_price_barcode: persistedShowPriceBarcode ? 1 : 0,
            show_price_qr: persistedShowPriceQr ? 1 : 0,
            show_barcode_number_barcode: persistedShowBarcodeNoBarcode ? 1 : 0,
            show_barcode_number_qr: persistedShowBarcodeNoQr ? 1 : 0,
            print_copies: parseInt(document.getElementById('barcodeCopies').value, 10) || 1,
            metal_type: metalSelect ? (metalSelect.value || '') : '',
            is_default_print: (document.getElementById('barcodeIsDefaultPrint') && document.getElementById('barcodeIsDefaultPrint').checked) ? 1 : 0
        };
        if (labelSize === STICKER_82X38_PRESET) {
            payload.label_width_mm = 82;
            payload.label_height_mm = 38;
        } else if (labelSize && labelSize !== 'custom' && labelSize !== '120x50') {
            var p = labelSize.split('x');
            if (p.length >= 2) {
                payload.label_width_mm = parseFloat(p[0]) || 100;
                payload.label_height_mm = parseFloat(p[1]) || 18;
            }
        }
        return payload;
    }

    function buildBarcodeLayoutPayloadObject(payload) {
        var designItems = [];
        var designItems2 = [];
        var labelPreview1El = document.getElementById('labelPreview1');
        var labelPreview2El = document.getElementById('labelPreview2');
        var labelCanvas1El = document.getElementById('labelCanvas1');
        var labelCanvas2El = document.getElementById('labelCanvas2');
        var labelW = payload.label_width_mm || 100;
        var labelH = payload.label_height_mm || 18;
        var dualSaveHalf = isDualCanvasLayoutPreset();
        var labelWHalf = dualSaveHalf ? getDualTagPrintBoxWidthMm() : labelW;
        var labelHSave = dualSaveHalf ? getDualTagPrintBoxHeightMm() : labelH;
        var contentW1 = (labelCanvas1El || labelPreview1El) ? ((labelCanvas1El || labelPreview1El).offsetWidth || 270) : 270;
        var contentH1 = (labelCanvas1El || labelPreview1El) ? ((labelCanvas1El || labelPreview1El).offsetHeight || 54) : 54;
        var pxToMmX1 = labelWHalf / contentW1;
        var pxToMmY1 = labelHSave / contentH1;
        var barcodeLeftMm = 0, barcodeRightMm = 0, barcodeTopMm = 0, barcodeBottomMm = 0;
        var barcode1TopMm = 0, barcode1LeftMm = 0, barcode2TopMm = 0, barcode2LeftMm = 0;
        if (labelPreview1El) {
            var barcodeWrap1 = document.getElementById('barcode1');
            var barcodeStripes1 = labelPreview1El.querySelector('.barcode-stripes');
            var rectRef1 = labelCanvas1El || labelPreview1El;
            if (barcodeWrap1 && rectRef1 && (barcodeStripes1 || is82x38TwoBoxPreset())) {
                var posCanvas1 = labelCanvas1El || rectRef1;
                var off1 = getElementOffsetInAncestor(barcodeWrap1, posCanvas1);
                var leftPx1 = off1 ? off1.left : (function() {
                    var br = barcodeWrap1.getBoundingClientRect();
                    var wr = rectRef1.getBoundingClientRect();
                    return br.left - wr.left;
                })();
                var topPx1 = off1 ? off1.top : (function() {
                    var br = barcodeWrap1.getBoundingClientRect();
                    var wr = rectRef1.getBoundingClientRect();
                    return br.top - wr.top;
                })();
                barcode1LeftMm = clampMmGlobal(leftPx1 * pxToMmX1, labelWHalf);
                barcode1TopMm = clampMmGlobal(topPx1 * pxToMmY1, labelHSave);
                var wPx1, hPx1;
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr' && barcodeWrap1) {
                    var qrEl1 = barcodeWrap1.querySelector('.qr-code-preview');
                    if (qrEl1 && qrEl1.offsetWidth > 0 && qrEl1.offsetHeight > 0) {
                        wPx1 = qrEl1.offsetWidth;
                        hPx1 = qrEl1.offsetHeight;
                    } else {
                        var qs1 = getQrSize();
                        wPx1 = qs1.width;
                        hPx1 = qs1.height;
                    }
                } else if (is82x38TwoBoxPreset()) {
                    var svg1Save = barcodeWrap1.querySelector('svg.barcode-svg, svg');
                    var saveSize82_1 = get82x38BarcodeSaveSizePx(barcodeWrap1, svg1Save);
                    wPx1 = saveSize82_1.width;
                    hPx1 = saveSize82_1.height;
                } else {
                    var saveSize1 = getBarcodeSaveSizePx(barcodeWrap1, barcodeStripes1);
                    wPx1 = saveSize1.width;
                    hPx1 = saveSize1.height;
                }
                var barMaxW1 = dualSaveHalf ? labelWHalf : labelW;
                var barLeft = barcode1LeftMm;
                var barTop = barcode1TopMm;
                var barW = clampMmGlobal(wPx1 * pxToMmX1, barMaxW1);
                var barH = clampMmGlobal(hPx1 * pxToMmY1, labelHSave);
                barcodeLeftMm = barLeft;
                barcodeRightMm = barLeft + barW;
                barcodeTopMm = barTop;
                barcodeBottomMm = barTop + barH;
                designItems.push({
                    type: 'barcode_image',
                    left: barLeft,
                    top: barTop,
                    width: barW,
                    height: barH,
                    display_width_mm: barW
                });
            }
            var gapMm = 1.5;
            var dropCanvas1 = labelCanvas1El || labelPreview1El;
            if (is82x38TwoBoxPreset()) {
                var scSave1 = get82x38CanvasMmScale(dropCanvas1);
                pushCanvasDroppedItemsToDesign(designItems, dropCanvas1, scSave1.mmW, scSave1.mmH, scSave1.pxToMmX, scSave1.pxToMmY, { skipDomSync: true });
            } else {
            dropCanvas1.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
                var left = parseInt(item.style.left, 10);
                var top = parseInt(item.style.top, 10);
                if (isNaN(left)) left = 0;
                if (isNaN(top)) top = 0;
                var leftMm = clampMmGlobal(left * pxToMmX1, labelWHalf);
                var topMm = clampMmGlobal(top * pxToMmY1, labelHSave);
                var fieldNameSave = item.getAttribute('data-field') || '';
                if (isStripLineField(fieldNameSave) || item.classList.contains('canvas-strip-line')) {
                    var wPxStrip = parseInt(item.style.width, 10);
                    if (isNaN(wPxStrip) || wPxStrip < 8) wPxStrip = item.offsetWidth || Math.round(labelWHalf / pxToMmX1);
                    var widthMmStrip = clampMmGlobal(wPxStrip * pxToMmX1, labelWHalf);
                    item.style.top = Math.round(topMm / pxToMmY1) + 'px';
                    item.style.width = Math.round(widthMmStrip / pxToMmX1) + 'px';
                    designItems.push({
                        type: 'strip_line',
                        field: 'StripLine',
                        left: leftMm,
                        top: topMm,
                        width: widthMmStrip
                    });
                    return;
                }
                if (isWhiteStripField(fieldNameSave) || item.classList.contains('canvas-white-strip')) {
                    var wPxWs1 = parseInt(item.style.width, 10);
                    var hPxWs1 = parseInt(item.style.height, 10);
                    if (isNaN(wPxWs1) || wPxWs1 < 8) wPxWs1 = item.offsetWidth || 40;
                    if (isNaN(hPxWs1) || hPxWs1 < 4) hPxWs1 = item.offsetHeight || 20;
                    var widthMmWs1 = clampMmGlobal(wPxWs1 * pxToMmX1, labelWHalf);
                    var heightMmWs1 = clampMmGlobal(hPxWs1 * pxToMmY1, labelHSave);
                    item.style.top = Math.round(topMm / pxToMmY1) + 'px';
                    item.style.width = Math.round(widthMmWs1 / pxToMmX1) + 'px';
                    item.style.height = Math.round(heightMmWs1 / pxToMmY1) + 'px';
                    var wsSave1 = serializeWhiteStripEl(item, leftMm, topMm, widthMmWs1, heightMmWs1);
                    if (isNaN(wsSave1.opacity)) wsSave1.opacity = 1;
                    designItems.push(wsSave1);
                    return;
                }
                if (barcodeBottomMm > 0 && fieldNameSave !== 'CompanyName') {
                    topMm = resolveTextTopMmBelowBarcodeIfOverlap(leftMm, topMm, barcodeLeftMm, barcodeRightMm, barcodeTopMm, barcodeBottomMm, labelWHalf, labelHSave, gapMm);
                }
                // Keep on-screen preview in sync with what we save (mm → px)
                var newTopPx = Math.round(topMm / pxToMmY1);
                item.style.top = newTopPx + 'px';
                designItems.push({
                    type: 'text',
                    field: fieldNameSave,
                    left: leftMm,
                    top: topMm,
                    prefix: item.getAttribute('data-prefix') || '',
                    suffix: item.getAttribute('data-suffix') || '',
                    font: item.getAttribute('data-font') || 'Arial',
                    font_size: item.getAttribute('data-font-size') || '10',
                    pad_top: clampPadPx(item.getAttribute('data-pad-top')),
                    pad_right: clampPadPx(item.getAttribute('data-pad-right')),
                    pad_bottom: clampPadPx(item.getAttribute('data-pad-bottom')),
                    pad_left: clampPadPx(item.getAttribute('data-pad-left'))
                });
            });
            }
        }
        var barcodeWrap2Save = document.getElementById('barcode2');
        if (labelPreview2El && isDualCanvasLayoutPreset()) {
            var contentW2 = (labelCanvas2El || labelPreview2El) ? ((labelCanvas2El || labelPreview2El).offsetWidth || 270) : 270;
            var contentH2 = (labelCanvas2El || labelPreview2El) ? ((labelCanvas2El || labelPreview2El).offsetHeight || 54) : 54;
            var pxToMmX2 = labelWHalf / contentW2;
            var pxToMmY2 = labelHSave / contentH2;
            var barcodeWrap2 = barcodeWrap2Save;
            var barcodeStripes2 = barcodeWrap2 ? barcodeWrap2.querySelector('.barcode-stripes') : null;
            var rectRef2 = labelCanvas2El || labelPreview2El;
            if (barcodeWrap2 && rectRef2 && (barcodeStripes2 || is82x38TwoBoxPreset())) {
                var posCanvas2 = labelCanvas2El || rectRef2;
                var off2 = getElementOffsetInAncestor(barcodeWrap2, posCanvas2);
                var leftPx2 = off2 ? off2.left : (function() {
                    var br = barcodeWrap2.getBoundingClientRect();
                    var wr = rectRef2.getBoundingClientRect();
                    return br.left - wr.left;
                })();
                var topPx2 = off2 ? off2.top : (function() {
                    var br = barcodeWrap2.getBoundingClientRect();
                    var wr = rectRef2.getBoundingClientRect();
                    return br.top - wr.top;
                })();
                barcode2LeftMm = clampMmGlobal(leftPx2 * pxToMmX2, labelWHalf);
                barcode2TopMm = clampMmGlobal(topPx2 * pxToMmY2, labelHSave);
                var wPx2, hPx2;
                if (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr' && barcodeWrap2) {
                    var qrEl2 = barcodeWrap2.querySelector('.qr-code-preview');
                    if (qrEl2 && qrEl2.offsetWidth > 0 && qrEl2.offsetHeight > 0) {
                        wPx2 = qrEl2.offsetWidth;
                        hPx2 = qrEl2.offsetHeight;
                    } else {
                        var qs2 = getQrSize();
                        wPx2 = qs2.width;
                        hPx2 = qs2.height;
                    }
                } else if (is82x38TwoBoxPreset()) {
                    var svg2Save = barcodeWrap2.querySelector('svg.barcode-svg, svg');
                    var saveSize82_2 = get82x38BarcodeSaveSizePx(barcodeWrap2, svg2Save);
                    wPx2 = saveSize82_2.width;
                    hPx2 = saveSize82_2.height;
                } else {
                    var saveSize2 = getBarcodeSaveSizePx(barcodeWrap2, barcodeStripes2);
                    wPx2 = saveSize2.width;
                    hPx2 = saveSize2.height;
                }
                designItems2.push({
                    type: 'barcode_image',
                    left: barcode2LeftMm,
                    top: barcode2TopMm,
                    width: clampMmGlobal(wPx2 * pxToMmX2, labelWHalf),
                    height: clampMmGlobal(hPx2 * pxToMmY2, labelHSave),
                    display_width_mm: clampMmGlobal(wPx2 * pxToMmX2, labelWHalf)
                });
            }
            var barcodeLeftMm2 = 0, barcodeRightMm2 = 0, barcodeTopMm2 = 0, barcodeBottomMm2 = 0;
            if (designItems2.length) {
                var bi = designItems2[0];
                barcodeLeftMm2 = bi.left; barcodeRightMm2 = bi.left + bi.width;
                barcodeTopMm2 = bi.top; barcodeBottomMm2 = bi.top + bi.height;
            }
            var dropCanvas2 = labelCanvas2El || labelPreview2El;
            if (is82x38TwoBoxPreset()) {
                var scSave2 = get82x38CanvasMmScale(dropCanvas2);
                pushCanvasDroppedItemsToDesign(designItems2, dropCanvas2, scSave2.mmW, scSave2.mmH, scSave2.pxToMmX, scSave2.pxToMmY, { skipDomSync: true });
            } else {
            dropCanvas2.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
                var left = parseInt(item.style.left, 10);
                var top = parseInt(item.style.top, 10);
                if (isNaN(left)) left = 0;
                if (isNaN(top)) top = 0;
                var leftMm = clampMmGlobal(left * pxToMmX2, labelWHalf);
                var topMm = clampMmGlobal(top * pxToMmY2, labelHSave);
                var fieldNameSave2 = item.getAttribute('data-field') || '';
                if (isStripLineField(fieldNameSave2) || item.classList.contains('canvas-strip-line')) {
                    var wPxStrip2 = parseInt(item.style.width, 10);
                    if (isNaN(wPxStrip2) || wPxStrip2 < 8) wPxStrip2 = item.offsetWidth || Math.round(labelWHalf / pxToMmX2);
                    var widthMmStrip2 = clampMmGlobal(wPxStrip2 * pxToMmX2, labelWHalf);
                    item.style.top = Math.round(topMm / pxToMmY2) + 'px';
                    item.style.width = Math.round(widthMmStrip2 / pxToMmX2) + 'px';
                    designItems2.push({
                        type: 'strip_line',
                        field: 'StripLine',
                        left: leftMm,
                        top: topMm,
                        width: widthMmStrip2
                    });
                    return;
                }
                if (isWhiteStripField(fieldNameSave2) || item.classList.contains('canvas-white-strip')) {
                    var wPxWs2 = parseInt(item.style.width, 10);
                    var hPxWs2 = parseInt(item.style.height, 10);
                    if (isNaN(wPxWs2) || wPxWs2 < 8) wPxWs2 = item.offsetWidth || 40;
                    if (isNaN(hPxWs2) || hPxWs2 < 4) hPxWs2 = item.offsetHeight || 20;
                    var widthMmWs2 = clampMmGlobal(wPxWs2 * pxToMmX2, labelWHalf);
                    var heightMmWs2 = clampMmGlobal(hPxWs2 * pxToMmY2, labelHSave);
                    item.style.top = Math.round(topMm / pxToMmY2) + 'px';
                    item.style.width = Math.round(widthMmWs2 / pxToMmX2) + 'px';
                    item.style.height = Math.round(heightMmWs2 / pxToMmY2) + 'px';
                    var wsSave2 = serializeWhiteStripEl(item, leftMm, topMm, widthMmWs2, heightMmWs2);
                    if (isNaN(wsSave2.opacity)) wsSave2.opacity = 1;
                    designItems2.push(wsSave2);
                    return;
                }
                var gap2 = 1.5;
                if (barcodeBottomMm2 > 0 && fieldNameSave2 !== 'CompanyName') {
                    topMm = resolveTextTopMmBelowBarcodeIfOverlap(leftMm, topMm, barcodeLeftMm2, barcodeRightMm2, barcodeTopMm2, barcodeBottomMm2, labelWHalf, labelHSave, gap2);
                }
                // Keep on-screen preview in sync with what we save (mm → px)
                var newTopPx2 = Math.round(topMm / pxToMmY2);
                item.style.top = newTopPx2 + 'px';
                designItems2.push({
                    type: 'text',
                    field: fieldNameSave2,
                    left: leftMm,
                    top: topMm,
                    prefix: item.getAttribute('data-prefix') || '',
                    suffix: item.getAttribute('data-suffix') || '',
                    font: item.getAttribute('data-font') || 'Arial',
                    font_size: item.getAttribute('data-font-size') || '10',
                    pad_top: clampPadPx(item.getAttribute('data-pad-top')),
                    pad_right: clampPadPx(item.getAttribute('data-pad-right')),
                    pad_bottom: clampPadPx(item.getAttribute('data-pad-bottom')),
                    pad_left: clampPadPx(item.getAttribute('data-pad-left'))
                });
            });
            }
        }
        designItems = dedupeDesignLayoutItems(designItems);
        if (designItems2.length) designItems2 = dedupeDesignLayoutItems(designItems2);
        var layoutPayload = designItems2.length > 0 ? {
            items: designItems,
            items2: designItems2,
            barcode1_top: barcode1TopMm,
            barcode1_left: barcode1LeftMm,
            barcode2_top: barcode2TopMm,
            barcode2_left: barcode2LeftMm
        } : { items: designItems, barcode1_top: barcode1TopMm, barcode1_left: barcode1LeftMm };
        if (isDualLabelLayoutPreset()) {
            layoutPayload.double_barcode_120x50 = true;
            layoutPayload.double_barcode_dual_tag = true;
            layoutPayload.dual_label_preset = barcodeLabelPreset() || '120x50';
            layoutPayload.dual_tag_half_width_mm = getDualHalfLabelWidthMm();
            layoutPayload.dual_tag_gap_mm = DUAL_TAG_GAP_MM;
            layoutPayload.dual_quadrant_width_mm = getDualTagPrintBoxWidthMm();
            layoutPayload.dual_quadrant_height_mm = getDualTagPrintBoxHeightMm();
        }
        if (is82x38TwoBoxPreset()) {
            sync82x38BoxPositionsFromDom();
            sync82x38BarcodePositionsFromDom({ syncSize: true });
            var boxLayout = read82x38BoxLayoutFromInputs();
            layoutPayload.layout_type = '82x38_2box';
            layoutPayload.sticker_82x38_2box = true;
            layoutPayload.barcode1 = {
                left_mm: boxLayout.box1_barcode_left_mm,
                top_mm: boxLayout.box1_barcode_top_mm,
                width_mm: boxLayout.box1_barcode_width_mm,
                height_mm: boxLayout.box1_barcode_height_mm
            };
            layoutPayload.barcode2 = {
                left_mm: boxLayout.box2_barcode_left_mm,
                top_mm: boxLayout.box2_barcode_top_mm,
                width_mm: boxLayout.box2_barcode_width_mm,
                height_mm: boxLayout.box2_barcode_height_mm
            };
            var box1BarcodeItem = {
                type: 'barcode_image',
                left: boxLayout.box1_barcode_left_mm,
                top: boxLayout.box1_barcode_top_mm,
                width: boxLayout.box1_barcode_width_mm,
                height: boxLayout.box1_barcode_height_mm,
                display_width_mm: boxLayout.box1_barcode_width_mm
            };
            var box2BarcodeItem = {
                type: 'barcode_image',
                left: boxLayout.box2_barcode_left_mm,
                top: boxLayout.box2_barcode_top_mm,
                width: boxLayout.box2_barcode_width_mm,
                height: boxLayout.box2_barcode_height_mm,
                display_width_mm: boxLayout.box2_barcode_width_mm
            };
            var box1ExtraItems = designItems.filter(function(it) {
                return it && it.type !== 'barcode_image' && it.type !== 'strip_line' && it.type !== 'line' && !isStripLineField(it.field)
                    && it.type !== 'white_strip' && it.type !== 'WhiteStrip' && !isWhiteStripField(it.field);
            });
            var box2ExtraItems = designItems2.filter(function(it) {
                return it && it.type !== 'barcode_image' && it.type !== 'strip_line' && it.type !== 'line' && !isStripLineField(it.field)
                    && it.type !== 'white_strip' && it.type !== 'WhiteStrip' && !isWhiteStripField(it.field);
            });
            layoutPayload.sticker_strip_lines = collect82x38StickerStripLines();
            layoutPayload.sticker_white_strips = [];
            layoutPayload.sticker_half_strip_fields = collect82x38HalfStripFields();
            /* White strips live inside each print box canvas (box 1 / box 2) */
            var box1Ws = collectWhiteStripItemsFromCanvas(labelCanvas1 || labelPreview1, boxLayout.box_width_mm, boxLayout.box_height_mm,
                (labelCanvas1 && labelCanvas1.clientWidth > 0) ? (boxLayout.box_width_mm / labelCanvas1.clientWidth) : (1 / MM_TO_PX),
                (labelCanvas1 && labelCanvas1.clientHeight > 0) ? (boxLayout.box_height_mm / labelCanvas1.clientHeight) : (1 / MM_TO_PX));
            var box2Ws = collectWhiteStripItemsFromCanvas(labelCanvas2 || labelPreview2, boxLayout.box_width_mm, boxLayout.box_height_mm,
                (labelCanvas2 && labelCanvas2.clientWidth > 0) ? (boxLayout.box_width_mm / labelCanvas2.clientWidth) : (1 / MM_TO_PX),
                (labelCanvas2 && labelCanvas2.clientHeight > 0) ? (boxLayout.box_height_mm / labelCanvas2.clientHeight) : (1 / MM_TO_PX));
            box1Ws.forEach(function(w) { w.box = 1; });
            box2Ws.forEach(function(w) { w.box = 2; });
            layoutPayload.box1 = {
                left_mm: boxLayout.box1_left_mm,
                top_mm: boxLayout.box1_top_mm,
                width_mm: boxLayout.box_width_mm,
                height_mm: boxLayout.box_height_mm,
                items: [box1BarcodeItem].concat(box1ExtraItems).concat(box1Ws)
            };
            layoutPayload.box2 = {
                left_mm: boxLayout.box2_left_mm,
                top_mm: boxLayout.box2_top_mm,
                width_mm: boxLayout.box_width_mm,
                height_mm: boxLayout.box_height_mm,
                items: [box2BarcodeItem].concat(box2ExtraItems).concat(box2Ws)
            };
            layoutPayload.items = layoutPayload.box1.items.slice(0);
            layoutPayload.items2 = layoutPayload.box2.items.slice(0);
            barcode1LeftMm = boxLayout.box1_barcode_left_mm;
            barcode1TopMm = boxLayout.box1_barcode_top_mm;
            barcode2LeftMm = boxLayout.box2_barcode_left_mm;
            barcode2TopMm = boxLayout.box2_barcode_top_mm;
            layoutPayload.box1_left_mm = boxLayout.box1_left_mm;
            layoutPayload.box1_top_mm = boxLayout.box1_top_mm;
            layoutPayload.box1_width_mm = boxLayout.box_width_mm;
            layoutPayload.box1_height_mm = boxLayout.box_height_mm;
            layoutPayload.box2_left_mm = boxLayout.box2_left_mm;
            layoutPayload.box2_top_mm = boxLayout.box2_top_mm;
            layoutPayload.box2_width_mm = boxLayout.box_width_mm;
            layoutPayload.box2_height_mm = boxLayout.box_height_mm;
            layoutPayload.box2_right_mm = boxLayout.box2_right_mm;
            layoutPayload.box_width_mm = boxLayout.box_width_mm;
            layoutPayload.box_height_mm = boxLayout.box_height_mm;
            layoutPayload.box1_barcode_width_mm = boxLayout.box1_barcode_width_mm;
            layoutPayload.box1_barcode_height_mm = boxLayout.box1_barcode_height_mm;
            layoutPayload.box1_barcode_left_mm = boxLayout.box1_barcode_left_mm;
            layoutPayload.box1_barcode_top_mm = boxLayout.box1_barcode_top_mm;
            layoutPayload.box2_barcode_width_mm = boxLayout.box2_barcode_width_mm;
            layoutPayload.box2_barcode_height_mm = boxLayout.box2_barcode_height_mm;
            layoutPayload.box2_barcode_left_mm = boxLayout.box2_barcode_left_mm;
            layoutPayload.box2_barcode_top_mm = boxLayout.box2_barcode_top_mm;
            layoutPayload.box_barcode_width_mm = boxLayout.box1_barcode_width_mm;
            layoutPayload.box_barcode_height_mm = boxLayout.box1_barcode_height_mm;
            layoutPayload.barcode_width_mm = boxLayout.box1_barcode_width_mm;
            layoutPayload.barcode_height_mm = boxLayout.box1_barcode_height_mm;
            layoutPayload.barcode_left_mm = boxLayout.barcode_left_mm;
            layoutPayload.barcode_top_mm = boxLayout.barcode_top_mm;
            layoutPayload.barcode_no_font_size = boxLayout.barcode_no_font_size;
            layoutPayload.barcode_no_margin_top_mm = boxLayout.barcode_no_margin_top_mm;
            layoutPayload.sticker_print_offset_top_mm = boxLayout.sticker_print_offset_top_mm;
            layoutPayload.sticker_print_offset_left_mm = boxLayout.sticker_print_offset_left_mm;
            layoutPayload.dual_quadrant_width_mm = boxLayout.box_width_mm;
            layoutPayload.dual_quadrant_height_mm = boxLayout.box_height_mm;
            console.log('82x38 saved layout', {
                layout_type: '82x38_2box',
                box1: layoutPayload.box1,
                box2: layoutPayload.box2,
                barcode1: layoutPayload.barcode1,
                barcode2: layoutPayload.barcode2
            });
        }
        var isQrSave = (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr');
        if (isQrSave) {
            /* Keep qr_width/height aligned with box1 mm so reload/print match the canvas. */
            if (is82x38TwoBoxPreset()) {
                var blQrSave = read82x38BoxLayoutFromInputs();
                syncQrPxFrom82x38WrapMm(blQrSave.box1_barcode_width_mm, blQrSave.box1_barcode_height_mm, document.getElementById('box1'));
            }
            var qw = parseInt(document.getElementById('propQrWidth') && document.getElementById('propQrWidth').value, 10);
            var qh = parseInt(document.getElementById('propQrHeight') && document.getElementById('propQrHeight').value, 10);
            layoutPayload.qr_width = (isNaN(qw) || qw < 12) ? 60 : Math.min(200, qw);
            layoutPayload.qr_height = (isNaN(qh) || qh < 12) ? 60 : Math.min(200, qh);
        } else {
            var barW = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
            var barH = parseInt(document.getElementById('propBarcodeBarHeight') && document.getElementById('propBarcodeBarHeight').value, 10);
            layoutPayload.barcode_bar_width = (isNaN(barW) || barW < 1) ? 2 : Math.min(10, barW);
            layoutPayload.barcode_bar_height = (isNaN(barH) || barH < 10) ? 28 : Math.min(200, barH);
        }
        function clampLabelPadPx(v) {
            var n = parseInt(v, 10);
            if (isNaN(n) || n < 0) return 0;
            return Math.min(200, n);
        }
        var lpT = document.getElementById('labelPadTop');
        var lpR = document.getElementById('labelPadRight');
        var lpB = document.getElementById('labelPadBottom');
        var lpL = document.getElementById('labelPadLeft');
        layoutPayload.label_pad_top = lpT ? clampLabelPadPx(lpT.value) : 0;
        layoutPayload.label_pad_right = lpR ? clampLabelPadPx(lpR.value) : 0;
        layoutPayload.label_pad_bottom = lpB ? clampLabelPadPx(lpB.value) : 0;
        layoutPayload.label_pad_left = lpL ? clampLabelPadPx(lpL.value) : 0;
        var bc1Save = document.getElementById('barcode1');
        var labelCanvas1Save = document.getElementById('labelCanvas1');
        if (bc1Save && labelCanvas1Save) {
            var pinOff = getElementOffsetInAncestor(bc1Save, labelCanvas1Save);
            if (pinOff) {
                layoutPayload.barcode_left = Math.round(pinOff.left);
                layoutPayload.barcode_top = Math.round(pinOff.top);
            } else {
                layoutPayload.barcode_left = Math.round(bc1Save.offsetLeft);
                layoutPayload.barcode_top = Math.round(bc1Save.offsetTop);
            }
            layoutPayload.barcode_position = { left: layoutPayload.barcode_left, top: layoutPayload.barcode_top };
        }
        layoutPayload.layout_type = is82x38TwoBoxPreset()
            ? '82x38_2box'
            : ((typeof currentCodeType !== 'undefined' && currentCodeType) ? currentCodeType : 'barcode');
        if (!is82x38TwoBoxPreset() && !isDualCanvasLayoutPreset()) {
            var previewBox1 = document.getElementById('box1');
            var labelsContainerSave = getBarcodeLabelsContainerEl();
            if (previewBox1 && labelsContainerSave) {
                var boxOff = getElementOffsetInAncestor(previewBox1, labelsContainerSave);
                if (boxOff) {
                    layoutPayload.preview_box1_left = Math.round(boxOff.left);
                    layoutPayload.preview_box1_top = Math.round(boxOff.top);
                } else {
                    layoutPayload.preview_box1_left = Math.round(parseInt(previewBox1.style.left, 10) || previewBox1.offsetLeft || 0);
                    layoutPayload.preview_box1_top = Math.round(parseInt(previewBox1.style.top, 10) || previewBox1.offsetTop || 0);
                }
            }
        }
        layoutPayload.fields = designItems.slice(0);
        if (designItems2.length > 0) {
            layoutPayload.fields2 = designItems2.slice(0);
        }
        return layoutPayload;
    }

    document.getElementById('btnSaveBarcodeSettings').addEventListener('click', function() {
        var saveBtn = document.getElementById('btnSaveBarcodeSettings');
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Please wait...'; }
        function resetSaveButton() { if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; } }

        setTimeout(function() {
        flushPendingCanvasPropsToDom();
        var payload = buildBarcodeFormPayload();
        if (!payload.metal_type) {
            resetSaveButton();
            alert('Please select a Metal Type before saving barcode settings.');
            return;
        }
        if (!payload.label_size_preset) {
            resetSaveButton();
            alert('Please select a Label Size before saving barcode settings.');
            return;
        }
        var layoutPayload = buildBarcodeLayoutPayloadObject(payload);
        /* currentCodeType is source of truth (toolbox + Default print stay in sync). */
        var saveCodeType = (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') ? 'qr' : 'barcode';
        var dpcEl = document.getElementById('defaultPrintCodeType');
        if (dpcEl) dpcEl.value = saveCodeType;
        layoutPayload.layout_variant = saveCodeType;
        if (saveCodeType === 'barcode') {
            persistedLayoutBarcode = JSON.stringify(layoutPayload);
        } else {
            persistedLayoutQr = JSON.stringify(layoutPayload);
        }
        payload.design_layout = persistedLayoutBarcode;
        payload.design_layout_qr = persistedLayoutQr;
        payload.default_print_code_type = saveCodeType;
        var formData = new FormData();
        Object.keys(payload).forEach(function(k) {
            if (k.indexOf('show_') === 0) {
                formData.append(k, String(payload[k] ? 1 : 0));
            } else {
                formData.append(k, payload[k]);
            }
        });
        var sb = document.getElementById('settingsBranchId');
        if (sb) formData.append('settings_branch_id', sb.value);
        var previewEl = document.querySelector('.barcode-preview-block .barcode-default-inner');
        function doSave() {
            fetch('ajax/save-barcode-settings.php', { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    resetSaveButton();
                    if (data.success) {
                        var savedCode = (data.default_print_code_type === 'qr') ? 'QR' : 'Barcode';
                        alert((data.message || 'Barcode settings saved.') + '\nDefault print: ' + savedCode);
                        var metalEl = document.getElementById('barcodeMetalType');
                        var labelEl = document.getElementById('barcodeLabelSize');
                        var metal = metalEl ? (metalEl.value || '') : (payload.metal_type || '');
                        var label = labelEl ? (labelEl.value || '') : (payload.label_size_preset || '100x18');
                        var storagePreset = barcodeLabelStoragePreset(label, payload.label_width_mm, payload.label_height_mm);
                        try {
                            var snap = captureMetalSettingsSnapshot();
                            if (snap && snap.cache_key) {
                                snap.default_print_code_type = saveCodeType;
                                snap.design_layout_qr = persistedLayoutQr;
                                snap.design_layout_barcode = persistedLayoutBarcode;
                                barcodeSettingsCache[snap.cache_key] = snap;
                            }
                        } catch (cacheErr) {}
                        window.location.href = buildBarcodeSettingsPageUrl(metal, label);
                    } else {
                        alert(data.message || 'Save failed.');
                    }
                })
                .catch(function() {
                    resetSaveButton();
                    alert('Request failed');
                });
        }
        if (previewEl && typeof html2canvas === 'function') {
            html2canvas(previewEl, { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' })
                .then(function(canvas) {
                    var dataUrl = canvas.toDataURL('image/png');
                    formData.append('preview_image', dataUrl);
                    doSave();
                })
                .catch(function() { doSave(); });
        } else {
            doSave();
        }
        }, 0);
    });

    // Init barcode in "Print preview" section (same as Stock Journal print)
    (function initPrintPreviewBarcode() {
        var previewBarcode = document.querySelector('.barcode-print-preview-label .barcode');
        if (!previewBarcode || typeof JsBarcode === 'undefined') return;
        var barcodeValue = previewBarcode.getAttribute('data-barcode') || '00002';
        var barcodeNumber = String(barcodeValue).trim() || barcodeValue;
        var barOpts = getBarcodeBarOptions ? getBarcodeBarOptions() : { width: 2, height: 50 };
        try {
            JsBarcode(previewBarcode, barcodeNumber, {
                format: 'CODE128',
                width: barOpts.width,
                height: barOpts.height,
                displayValue: false,
                margin: 0,
                background: '#ffffff',
                lineColor: '#000000'
            });
        } catch (e) {}
    })();

    /** Label padding inputs: always sync from saved design_layout JSON (runs even if main layout restore throws). */
    (function restoreLabelPadInputsFromSavedLayout() {
        if (!savedDesignLayout || typeof savedDesignLayout !== 'string' || !savedDesignLayout.trim()) return;
        try {
            var p = JSON.parse(savedDesignLayout);
            if (!p || typeof p !== 'object') return;
            var pairs = [
                ['label_pad_top', 'labelPadTop'],
                ['label_pad_right', 'labelPadRight'],
                ['label_pad_bottom', 'labelPadBottom'],
                ['label_pad_left', 'labelPadLeft']
            ];
            pairs.forEach(function(row) {
                var key = row[0], id = row[1];
                if (p[key] === undefined || p[key] === null) return;
                var el = document.getElementById(id);
                if (el) el.value = String(Math.max(0, Math.min(200, parseInt(p[key], 10) || 0)));
            });
        } catch (e) {}
    })();
})();
</script>
</body>
</html>

