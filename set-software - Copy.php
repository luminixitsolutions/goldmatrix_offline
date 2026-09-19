<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';

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
    'metal_type' => ''
];
$bs_metal = $bs_load_metal !== '' ? $bs_load_metal : (isset($bs['metal_type']) ? trim((string) $bs['metal_type']) : '');
/** Presets shown in Label Size dropdown — keep in sync with ajax/save-barcode-settings.php $valid_presets */
$bs_label_preset_ui = ['100x18', '100x25', '100x48', '100x80', '64x25', '81x12', '120x50', '82x38_2box', '250x120', 'custom'];
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
$bs_82x38_layout = auragold_82x38_2box_layout($bs_design_layout_decoded);
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
$render_settings_preview = [
    'label_width_mm'  => (float)($bs['label_width_mm'] ?? 100),
    'label_height_mm' => (float)($bs['label_height_mm'] ?? 18),
    'font_size'       => (int)($bs['font_size'] ?? 12),
    'design_layout'   => $bs_design_layout_decoded,
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
    padding-left: 0;
    padding-right: 0;
    opacity: 0;
    pointer-events: none;
    border: none;
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
    position: absolute;
    left: calc(240px - 28px);
    top: 50%;
    transform: translateY(-50%);
    z-index: 25;
    width: 28px;
    height: 60px;
    margin: 0;
    padding: 0;
    border: none;
    background: #11294b;
    border-radius: 0 6px 6px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    cursor: pointer;
    font-size: 12px;
    box-shadow: 2px 0 6px rgba(0,0,0,0.1);
    transition: left 0.25s ease, background 0.2s ease;
}

.set-software-wrapper.set-software-sidebar-collapsed .set-software-collapse-tab {
    left: 0;
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

/* Drop zone layer - receives dropped toolbox fields */
.barcode-canvas-drops {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    border-radius: 8px;
    z-index: 5;
}

.barcode-canvas-drops.drag-over {
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
/* Single-label sizes (100×18, etc.): show preview on canvas — never for 82×38 dual sticker */
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) .barcode-dual-sticker-shell {
    display: block;
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 200px;
}
.barcode-canvas:not(.dual-barcode-layout):not(.barcode-82x38-dual-layout) #barcodeLabelsContainer {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
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
    cursor: move;
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
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable {
    position: absolute !important;
    pointer-events: auto !important;
    overflow: visible !important;
    z-index: 50 !important;
    box-sizing: border-box;
    cursor: move;
}
.barcode-canvas.barcode-82x38-dual-layout #barcode1,
.barcode-canvas.barcode-82x38-dual-layout #barcode2 {
    z-index: 50 !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable svg {
    width: 100% !important;
    height: 100% !important;
    display: block;
    pointer-events: none !important;
}
.barcode-canvas.barcode-82x38-dual-layout .barcode-inner-draggable .barcode-text {
    position: absolute;
    left: 0;
    top: 100%;
    pointer-events: none;
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
.barcode-canvas.barcode-82x38-dual-layout .barcode-print-wrap svg,
.barcode-canvas.barcode-82x38-dual-layout svg.barcode-svg-box1,
.barcode-canvas.barcode-82x38-dual-layout svg.barcode-svg-box2 {
    width: 100% !important;
    height: 100% !important;
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
    min-height: 20px;
    display: block;
    box-sizing: border-box;
    overflow: visible;
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
    outline: 2px solid #5a3b8c;
    outline-offset: 1px;
}
.barcode-resize-handle {
    position: absolute;
    right: -3px;
    bottom: -3px;
    width: 12px;
    height: 12px;
    cursor: ew-resize;
    background: #5a3b8c;
    border: 1px solid #fff;
    border-radius: 2px;
    z-index: 6;
    box-sizing: border-box;
}
.barcode-resize-handle:hover {
    background: #7c3aed;
}
.barcode-resize-handle.barcode-resize-handle--82x38 {
    cursor: nwse-resize;
}
.barcode-print-wrap:hover {
    outline: 1px dashed rgba(90, 59, 140, 0.5);
    outline-offset: 1px;
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

.toolbox-field-item.selected {
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
            <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
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
                            <option value="100x25" <?php echo $bs_label_preset_for_select === '100x25' ? 'selected' : ''; ?>>100mm x 25mm</option>
                            <option value="100x48" <?php echo $bs_label_preset_for_select === '100x48' ? 'selected' : ''; ?>>100mm x 48mm</option>
                            <option value="100x80" <?php echo $bs_label_preset_for_select === '100x80' ? 'selected' : ''; ?>>100mm x 80mm</option>
                            <option value="64x25" <?php echo $bs_label_preset_for_select === '64x25' ? 'selected' : ''; ?>>64mm x 25mm</option>
                            <option value="81x12" <?php echo $bs_label_preset_for_select === '81x12' ? 'selected' : ''; ?>>81mm x 12mm</option>
                            <option value="120x50" <?php echo $bs_label_preset_for_select === '120x50' ? 'selected' : ''; ?>>120mm x 50mm</option>
                            <option value="82x38_2box" <?php echo $bs_label_preset_for_select === '82x38_2box' ? 'selected' : ''; ?>>82mm x 38mm - 2 Box</option>
                            <option value="250x120" <?php echo $bs_label_preset_for_select === '250x120' ? 'selected' : ''; ?>>250mm x 120mm</option>
                            <option value="custom" <?php echo $bs_label_preset_for_select === 'custom' ? 'selected' : ''; ?>>Custom</option>
                        </select>
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
                <div class="<?php echo htmlspecialchars($bs_canvas_class, ENT_QUOTES, 'UTF-8'); ?>" id="barcodeCanvas">
                    <div class="barcode-canvas-drops" id="barcodeCanvasDrops"></div>
                    <div class="barcode-82x38-preview-wrapper" id="barcode82x38PreviewWrapper"<?php echo $bs_is_82x38_preset ? '' : ' aria-hidden="true"'; ?>>
                    <div class="<?php echo htmlspecialchars($bs_shell_class, ENT_QUOTES, 'UTF-8'); ?>" id="barcodeDualStickerShell" aria-label="<?php echo $bs_is_82x38_preset ? '82mm x 38mm dual box sticker' : '120mm x 50mm dual barcode sticker'; ?>">
                    <div class="barcode-82x38-sticker-guide" id="barcode82x38StickerGuide" aria-hidden="true" title="Horizontal separation line"></div>
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
                                                <svg class="barcode-svg barcode-svg-box1" id="barcodeSvgBox1" data-barcode="00002" aria-hidden="true"></svg>
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
                                                <svg class="barcode-svg barcode-svg-box2" id="barcodeSvgBox2" data-barcode="00003" aria-hidden="true"></svg>
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
                    ['id' => 'toolboxFieldsGold', 'class' => 'toolbox-fields-gold', 'divider' => 'Gold related columns', 'display' => 'flex'],
                    ['id' => 'toolboxFieldsSilver', 'class' => 'toolbox-fields-silver', 'divider' => 'Silver related columns', 'display' => 'none'],
                    ['id' => 'toolboxFieldsPlatinum', 'class' => 'toolbox-fields-platinum', 'divider' => 'Platinum related columns', 'display' => 'none'],
                    ['id' => 'toolboxFieldsDiamond', 'class' => 'toolbox-fields-diamond', 'divider' => 'Diamond & Stones related columns', 'display' => 'none'],
                    ['id' => 'toolboxFieldsImitation', 'class' => 'toolbox-fields-imitation', 'divider' => 'Imitation/Watches related columns', 'display' => 'none'],
                    ['id' => 'toolboxFieldsOther', 'class' => 'toolbox-fields-other', 'divider' => 'Other/Services related columns', 'display' => 'none'],
                ];
                foreach ($barcode_toolbox_tabs as $bt) {
                    $barcode_toolbox_divider = $bt['divider'];
                ?>
                <div class="toolbox-fields <?php echo htmlspecialchars($bt['class'], ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($bt['id'], ENT_QUOTES, 'UTF-8'); ?>" style="display:<?php echo htmlspecialchars($bt['display'], ENT_QUOTES, 'UTF-8'); ?>;">
                <?php include __DIR__ . '/includes/barcode-toolbox-field-chips.php'; ?>
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
                        <small class="prop-hint" style="display:block;margin-top:2px;color:#64748b;">Label padding applies to the whole printed label on barcode print; preview here does not change.</small>
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
                        <small class="prop-hint">Or use arrow keys</small>
                    </div>
                    <div class="prop-row prop-82x38-box-settings" id="propRow82x38Box" style="display: <?php echo $bs_label_preset_for_select === '82x38_2box' ? 'block' : 'none'; ?>;">
                        <label>82×38 sticker layout (mm) — drag boxes; barcode inside box is resizable</label>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 1 Left (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1']['left'] ?? 2); ?>" min="0" max="80" step="0.1" id="propBox1LeftMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 1 Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1']['top'] ?? 13); ?>" min="0" max="35" step="0.1" id="propBox1TopMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 2 Left (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_left_mm'] ?? $bs_82x38_layout['box2']['left'] ?? 58); ?>" min="0" max="80" step="0.1" id="propBox2LeftMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 2 Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2']['top'] ?? 2); ?>" min="0" max="35" step="0.1" id="propBox2TopMm">
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
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 1 Barcode Width (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_width_mm'] ?? $bs_82x38_layout['barcode_width_mm'] ?? 18); ?>" min="4" max="40" step="0.1" id="propBox1BarcodeWidthMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 1 Barcode Height (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_height_mm'] ?? $bs_82x38_layout['barcode_height_mm'] ?? 7); ?>" min="3" max="30" step="0.1" id="propBox1BarcodeHeightMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 2 Barcode Width (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_width_mm'] ?? $bs_82x38_layout['barcode_width_mm'] ?? 18); ?>" min="4" max="40" step="0.1" id="propBox2BarcodeWidthMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 2 Barcode Height (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_height_mm'] ?? $bs_82x38_layout['barcode_height_mm'] ?? 7); ?>" min="3" max="30" step="0.1" id="propBox2BarcodeHeightMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 1 Barcode Left (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_left_mm'] ?? $bs_82x38_layout['barcode_left_mm'] ?? 2); ?>" min="0" max="20" step="0.1" id="propBox1BarcodeLeftMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 1 Barcode Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box1_barcode_top_mm'] ?? $bs_82x38_layout['barcode_top_mm'] ?? 3); ?>" min="0" max="20" step="0.1" id="propBox1BarcodeTopMm">
                            </div>
                        </div>
                        <div class="prop-row prop-row-cols">
                            <div class="prop-field">
                                <label>Box 2 Barcode Left (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_left_mm'] ?? $bs_82x38_layout['barcode_left_mm'] ?? 2); ?>" min="0" max="20" step="0.1" id="propBox2BarcodeLeftMm">
                            </div>
                            <div class="prop-field">
                                <label>Box 2 Barcode Top (mm)</label>
                                <input type="number" value="<?php echo (float)($bs_82x38_layout['box2_barcode_top_mm'] ?? $bs_82x38_layout['barcode_top_mm'] ?? 3); ?>" min="0" max="20" step="0.1" id="propBox2BarcodeTopMm">
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
                        <small class="prop-hint">Inner boxes are fixed 20×25 mm (2×2.5 cm). Drag boxes to move; resize barcode image inside each box only.</small>
                    </div>
                    <div class="prop-row prop-row-barcode-size">
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
                            <small class="prop-hint" style="display:block;margin-top:6px;color:#64748b;">Click barcode on label, then drag the corner handle or use −/+.</small>
                        </div>
                    </div>
                    <div class="prop-row prop-row-cols prop-row-qr-size" id="propRowQrSize" style="display: none;">
                        <div class="prop-field">
                            <label>QR width (px)</label>
                            <input type="number" value="<?php echo (int)($bs['qr_width'] ?? 60); ?>" min="30" max="200" id="propQrWidth" title="QR code width (30–200)">
                        </div>
                        <div class="prop-field">
                            <label>QR height (px)</label>
                            <input type="number" value="<?php echo (int)($bs['qr_height'] ?? 60); ?>" min="30" max="200" id="propQrHeight" title="QR code height (30–200)">
                        </div>
                    </div>
                    <small class="prop-hint prop-hint-barcode" id="propHintBarcode" style="margin-top: 2px;">Line thickness = each black bar width (1 = thinnest). Barcode width = total size on label. Drag the barcode to move; drag the purple corner handle to change width.</small>
                    <small class="prop-hint prop-hint-qr" id="propHintQr" style="margin-top: 2px; display: none;">QR width/height = size of QR code.</small>
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
            default_print_code_type: (dpcEl && dpcEl.value === 'qr') ? 'qr' : 'barcode',
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
            design_layout_qr: persistedLayoutQr || '{}'
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
        return String(snapStorage).toLowerCase() === String(reqStorage).toLowerCase();
    }

    function applyMetalSettingsSnapshot(snap, requestedLp) {
        document.querySelectorAll('.canvas-dropped-item').forEach(function(el) { el.remove(); });
        if (!requestedLp) requestedLp = getCurrentLabelPresetUi();
        if (!snap) {
            persistedLayoutBarcode = '{}';
            persistedLayoutQr = '{}';
            savedDesignLayout = '{}';
            applyRequestedLabelPresetUi(requestedLp);
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
            if (typeof restoreSavedLayout === 'function') restoreSavedLayout();
            if (typeof updateBarcodeQrDisplay === 'function') updateBarcodeQrDisplay();
            if (typeof refreshCodeGraphicAfterLayoutRestore === 'function') refreshCodeGraphicAfterLayoutRestore();
            if (is82x38TwoBoxPreset()) {
                layout82x38DualCanvases();
                render82x38PreviewPipeline({ skipBoxLayout: true });
                syncDualCanvasHeight();
                return;
            }
            if (isDualCanvasLayoutPreset()) {
                ensureDualBarcodesRendered({ preservePositions: !!snap });
                if (!snap) {
                    positionDefaultBarcodesInEachLabel();
                }
                clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
                syncDualCanvasHeight();
            } else {
                clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
            }
        }, 80);
    }

    function onBarcodeMetalTypeChange() {
        var metalSelectEl = document.getElementById('barcodeMetalType');
        if (!metalSelectEl) return;
        var newMetal = metalSelectEl.value || '';
        try { if (newMetal) sessionStorage.setItem('barcode_setting_metal', newMetal); } catch (e) {}
        var lp = getCurrentLabelPresetUi();
        loadBarcodeSettingsForMetalAndLabel(newMetal, lp.ui, lp.w, lp.h);
    }

    function onBarcodeLabelSizeChange() {
        var lp = getCurrentLabelPresetUi();
        applyRequestedLabelPresetUi(lp);
        resizeBarcodePreviewForCurrentLabelSize();
        if (!currentBarcodeMetalKey) {
            document.querySelectorAll('.canvas-dropped-item').forEach(function(el) { el.remove(); });
            setLastLoadedBarcodeContext('', lp.ui, lp.w, lp.h);
            if (isDualCanvasLayoutPreset(lp.ui)) {
                setTimeout(function() {
                    if (lp.ui === STICKER_82X38_PRESET) {
                        render82x38DualEditor();
                    } else {
                        ensureDualBarcodesRendered({ preservePositions: false });
                    }
                }, 120);
            } else if (typeof refreshStandardBarcodeAfterLabelSizeChange === 'function') {
                setTimeout(refreshStandardBarcodeAfterLabelSizeChange, 50);
            }
            return;
        }
        loadBarcodeSettingsForMetalAndLabel(currentBarcodeMetalKey, lp.ui, lp.w, lp.h);
    }

    function filterToolboxBySearch() {
        var searchInput = document.getElementById('toolboxColumnSearch');
        var q = (searchInput && searchInput.value) ? searchInput.value.trim().toLowerCase() : '';
        var activeCat = document.querySelector('.toolbox-tab.active');
        var cat = activeCat ? activeCat.getAttribute('data-category') : 'gold';
        var panel = fieldsByCategory[cat];
        if (!panel) return;
        panel.querySelectorAll('.toolbox-field-item').forEach(function(item) {
            var field = (item.getAttribute('data-field') || '').toLowerCase();
            var labelEl = item.querySelector('.toolbox-field-label');
            var label = (labelEl ? labelEl.textContent : item.textContent || '').trim().toLowerCase();
            if (q === '') {
                item.classList.remove('toolbox-search-hidden');
            } else {
                var match = field.indexOf(q) >= 0 || label.indexOf(q) >= 0;
                if (match) item.classList.remove('toolbox-search-hidden');
                else item.classList.add('toolbox-search-hidden');
            }
        });
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

    function getQrSize() {
        var w = parseInt(document.getElementById('propQrWidth') && document.getElementById('propQrWidth').value, 10);
        var h = parseInt(document.getElementById('propQrHeight') && document.getElementById('propQrHeight').value, 10);
        w = (isNaN(w) || w < 30) ? 60 : Math.min(200, w);
        h = (isNaN(h) || h < 30) ? 60 : Math.min(200, h);
        return { width: w, height: h };
    }
    function updatePropertiesPanelForCodeType() {
        var isBarcode = (currentCodeType === 'barcode');
        var barRow = document.getElementById('propRowBarcodeBar');
        var qrRow = document.getElementById('propRowQrSize');
        var hintBar = document.getElementById('propHintBarcode');
        var hintQr = document.getElementById('propHintQr');
        if (barRow) barRow.style.display = isBarcode ? '' : 'none';
        if (qrRow) qrRow.style.display = isBarcode ? 'none' : '';
        if (hintBar) hintBar.style.display = isBarcode ? '' : 'none';
        if (hintQr) hintQr.style.display = isBarcode ? 'none' : '';
    }
    document.querySelectorAll('.barcode-qr-toggle .toggle-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.barcode-qr-toggle .toggle-option').forEach(function(o) { o.classList.remove('active'); });
            this.classList.add('active');
            var nextType = this.getAttribute('data-type');
            if (nextType === currentCodeType) return;
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
            updateBarcodeQrDisplay();
        });
    });

    function updateBarcodeQrDisplay() {
        var qrSize = getQrSize();
        var printWraps = document.querySelectorAll('.barcode-default-inner .barcode-print-wrap');
        printWraps.forEach(function(printWrap, index) {
            var barcodeStripes = printWrap.querySelector('.barcode-stripes');
            var qrCodeEl = printWrap.querySelector('.qr-code-preview');
            if (currentCodeType === 'qr') {
                if (barcodeStripes) { barcodeStripes.style.display = 'none'; barcodeStripes.style.visibility = 'hidden'; }
                if (!qrCodeEl) {
                    qrCodeEl = document.createElement('div');
                    qrCodeEl.className = 'qr-code-preview';
                }
                qrCodeEl.style.cssText = 'width:' + qrSize.width + 'px;height:' + qrSize.height + 'px;background:#fff;display:flex;align-items:center;justify-content:center;visibility:visible;';
                var qrCanvas = qrCodeEl.querySelector('.qr-preview-canvas');
                if (!qrCanvas) {
                    qrCanvas = document.createElement('canvas');
                    qrCanvas.className = 'qr-preview-canvas';
                    qrCodeEl.appendChild(qrCanvas);
                }
                var drawSize = Math.min(qrSize.width, qrSize.height, 150);
                qrCanvas.width = drawSize;
                qrCanvas.height = drawSize;
                qrCodeEl.style.width = qrSize.width + 'px';
                qrCodeEl.style.height = qrSize.height + 'px';
                if (!printWrap.contains(qrCodeEl)) printWrap.appendChild(qrCodeEl);
                qrCodeEl.style.display = 'flex';
                drawQRCode(qrCanvas, '00002');
            } else {
                if (barcodeStripes) { barcodeStripes.style.display = ''; barcodeStripes.style.visibility = ''; }
                if (qrCodeEl) { qrCodeEl.style.display = 'none'; qrCodeEl.style.visibility = 'hidden'; }
            }
        });
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
                    [lp1, lp2].forEach(function(cont) {
                        if (!cont) return;
                        var el = cont.querySelector('.canvas-dropped-item[data-field="' + fieldName + '"]');
                        if (el) el.remove();
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
    var BOX_82X38_WIDTH_MM = 20;
    var BOX_82X38_HEIGHT_MM = 25;
    function get82x38FixedBoxSizeMm() {
        return { width_mm: BOX_82X38_WIDTH_MM, height_mm: BOX_82X38_HEIGHT_MM };
    }
    function force82x38BoxSizeInputs() {
        var wEl = document.getElementById('propBoxWidthMm');
        var hEl = document.getElementById('propBoxHeightMm');
        if (wEl) wEl.value = String(BOX_82X38_WIDTH_MM);
        if (hEl) hEl.value = String(BOX_82X38_HEIGHT_MM);
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
        force82x38BoxSizeInputs();
        function num(id, fallback) {
            var el = document.getElementById(id);
            var n = el ? parseFloat(el.value) : NaN;
            return (isNaN(n)) ? fallback : n;
        }
        var stickerW = 82;
        var boxW = BOX_82X38_WIDTH_MM;
        var boxH = BOX_82X38_HEIGHT_MM;
        var box2Left = num('propBox2LeftMm', 58);
        box2Left = Math.max(0, Math.min(stickerW - boxW, box2Left));
        var box2Right = Math.max(0, Math.round((stickerW - box2Left - boxW) * 10) / 10);
        return {
            box1_left_mm: num('propBox1LeftMm', 2),
            box1_top_mm: num('propBox1TopMm', 13),
            box2_left_mm: box2Left,
            box2_right_mm: box2Right,
            box2_top_mm: num('propBox2TopMm', 2),
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
            barcode_no_font_size: num('propBoxBarcodeNoFontSize', 7)
        };
    }
    function write82x38BoxLayoutToInputs(layout) {
        function set(id, val) {
            var el = document.getElementById(id);
            if (el && val != null && !isNaN(parseFloat(val))) {
                el.value = Math.round(parseFloat(val) * 10) / 10;
            }
        }
        if (!layout) return;
        set('propBox1LeftMm', layout.box1_left_mm);
        set('propBox1TopMm', layout.box1_top_mm);
        set('propBox2LeftMm', layout.box2_left_mm);
        set('propBox2TopMm', layout.box2_top_mm);
        force82x38BoxSizeInputs();
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
    function get82x38JsBarcodeOpts() {
        var w = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
        return {
            format: 'CODE128',
            width: (isNaN(w) || w < 1) ? 1 : Math.min(10, w),
            height: 30,
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
        wrap.style.lineHeight = '0';
        wrap.style.overflow = 'visible';
        wrap.style.boxSizing = 'border-box';
        wrap.style.pointerEvents = 'auto';
        wrap.style.cursor = 'move';
        wrap.classList.add('barcode-82x38-barcode', 'barcode-inner-draggable');
        var svg = wrap.querySelector('svg.barcode-svg-box1, svg.barcode-svg-box2, svg');
        if (svg) {
            svg.style.width = '100%';
            svg.style.height = '100%';
            svg.style.maxWidth = '100%';
            svg.style.maxHeight = '100%';
            svg.style.display = 'block';
            svg.style.margin = '0';
            svg.style.padding = '0';
            svg.removeAttribute('height');
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
    }
    function mmFrom82x38BarcodeInBox(wrap) {
        var box = get82x38BoxElForBarcode(wrap);
        if (!box || !wrap) return null;
        var leftMm = parseFloat(String(wrap.style.left || '').replace('mm', ''));
        var topMm = parseFloat(String(wrap.style.top || '').replace('mm', ''));
        var widthMm = parseFloat(String(wrap.style.width || '').replace('mm', ''));
        var heightMm = parseFloat(String(wrap.style.height || '').replace('mm', ''));
        if (!isNaN(leftMm) && !isNaN(topMm) && String(wrap.style.left || '').indexOf('mm') >= 0) {
            return {
                left_mm: Math.round(leftMm * 10) / 10,
                top_mm: Math.round(topMm * 10) / 10,
                width_mm: (!isNaN(widthMm) && widthMm > 0) ? Math.round(widthMm * 10) / 10 : null,
                height_mm: (!isNaN(heightMm) && heightMm > 0) ? Math.round(heightMm * 10) / 10 : null
            };
        }
        var boxRect = box.getBoundingClientRect();
        var svg = wrap.querySelector('svg');
        var measureEl = svg || wrap;
        var barcodeRect = measureEl.getBoundingClientRect();
        if (boxRect.width <= 0 || boxRect.height <= 0) return null;
        var scaleX = boxRect.width / BOX_82X38_WIDTH_MM;
        var scaleY = boxRect.height / BOX_82X38_HEIGHT_MM;
        var leftPx = barcodeRect.left - boxRect.left;
        var topPx = barcodeRect.top - boxRect.top;
        return {
            left_mm: Math.round((leftPx / scaleX) * 10) / 10,
            top_mm: Math.round((topPx / scaleY) * 10) / 10,
            width_mm: Math.round((barcodeRect.width / scaleX) * 10) / 10,
            height_mm: Math.round((barcodeRect.height / scaleY) * 10) / 10
        };
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
        apply82x38BarcodeLayoutMm(document.getElementById('barcode1'), bl.box1_barcode_left_mm, bl.box1_barcode_top_mm, bl.box1_barcode_width_mm, bl.box1_barcode_height_mm);
        apply82x38BarcodeLayoutMm(document.getElementById('barcode2'), bl.box2_barcode_left_mm, bl.box2_barcode_top_mm, bl.box2_barcode_width_mm, bl.box2_barcode_height_mm);
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
        var outer = get82x38OuterStickerEl();
        if (!outer) return;
        var outerRect = outer.getBoundingClientRect();
        if (!(outerRect.width > 0) || !(outerRect.height > 0)) return;
        function blockToInputs(block, leftId, topId) {
            if (!block) return;
            var pos = mmFrom82x38DomPosition(block);
            if (!pos) return;
            var leftEl = document.getElementById(leftId);
            var topEl = document.getElementById(topId);
            if (leftEl) leftEl.value = pos.left_mm;
            if (topEl) topEl.value = pos.top_mm;
        }
        force82x38BoxSizeInputs();
        blockToInputs(document.getElementById('box1'), 'propBox1LeftMm', 'propBox1TopMm');
        blockToInputs(document.getElementById('box2'), 'propBox2LeftMm', 'propBox2TopMm');
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
            wrap.insertBefore(svg, wrap.firstChild);
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
            persistedLayoutBarcode = JSON.stringify(parsed);
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
                var newW = startW + (ev.clientX - startX);
                var newH = startH + (ev.clientY - startY);
                var maxW = box.clientWidth - startLeftInBox;
                var maxH = box.clientHeight - startTopInBox;
                newW = Math.max(20, Math.min(newW, maxW));
                newH = Math.max(10, Math.min(newH, maxH));
                wrap.style.setProperty('width', newW + 'px', 'important');
                wrap.style.setProperty('height', newH + 'px', 'important');
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
                wMm = Math.max(4, Math.min(BOX_82X38_WIDTH_MM - (pos ? pos.left_mm : 0), wMm));
                hMm = Math.max(3, Math.min(BOX_82X38_HEIGHT_MM - (pos ? pos.top_mm : 0), hMm));
                var leftMm = pos ? pos.left_mm : 2;
                var topMm = pos ? pos.top_mm : 2;
                wrap.style.removeProperty('width');
                wrap.style.removeProperty('height');
                apply82x38BarcodeLayoutMm(wrap, leftMm, topMm, wMm, hMm);
                set82x38BarcodePropInputs(get82x38BarcodeIndex(wrap), leftMm, topMm, wMm, hMm);
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
        var leftPx = e.clientX - boxRect.left - st.offsetX;
        var topPx = e.clientY - boxRect.top - st.offsetY;
        leftPx = Math.max(0, Math.min(boxRect.width - wrapRect.width, leftPx));
        topPx = Math.max(0, Math.min(boxRect.height - wrapRect.height, topPx));
        var leftMm = Math.round((leftPx / scale.x) * 10) / 10;
        var topMm = Math.round((topPx / scale.y) * 10) / 10;
        apply82x38BarcodeLayoutMm(st.wrap, leftMm, topMm, st.widthMm, st.heightMm);
        set82x38BarcodePropInputs(st.index, leftMm, topMm, st.widthMm, st.heightMm);
    }
    function bind82x38BarcodeWrapDrag(wrap, box, index) {
        if (!wrap || !box || wrap._82x38DragBound) return;
        wrap._82x38DragBound = true;
        wrap.addEventListener('mousedown', function(e) {
            if (!is82x38TwoBoxPreset()) return;
            if (_82x38BarcodeResizing) return;
            if (e.target.closest('.resize-handle')) return;
            e.preventDefault();
            e.stopPropagation();
            selectBarcodePrintWrap(wrap);
            var wrapRect = wrap.getBoundingClientRect();
            var leftMm = parseFloat(String(wrap.style.left || '').replace('mm', ''));
            var topMm = parseFloat(String(wrap.style.top || '').replace('mm', ''));
            var widthMm = parseFloat(String(wrap.style.width || '').replace('mm', ''));
            var heightMm = parseFloat(String(wrap.style.height || '').replace('mm', ''));
            if (isNaN(leftMm)) leftMm = 2;
            if (isNaN(topMm)) topMm = 2;
            if (isNaN(widthMm)) widthMm = 16;
            if (isNaN(heightMm)) heightMm = 7;
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
                sync82x38BarcodePositionsFromDom({ syncSize: true });
                save82x38CurrentLayoutToHiddenJson();
                _82x38BarcodeDragState = null;
                document.body.style.cursor = '';
            });
        }
    }
    function layout82x38DualCanvases() {
        if (!is82x38TwoBoxPreset()) return;
        var bl = read82x38BoxLayoutFromInputs();
        var block1 = document.getElementById('box1');
        var block2 = document.getElementById('box2');
        apply82x38BoxLayoutMm(block1, bl.box1_left_mm, bl.box1_top_mm, bl.box_width_mm, bl.box_height_mm);
        apply82x38BoxLayoutMm(block2, bl.box2_left_mm, bl.box2_top_mm, bl.box_width_mm, bl.box_height_mm);
        ensure82x38BoxHorizontalLines();
        remove82x38OuterCmRuler();
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
        wrap.style.overflow = 'visible';
        wrap.style.width = 'auto';
        wrap.style.maxWidth = '';
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
                labelsContainer.style.position = '';
            }
            if (block1) { block1.style.left = ''; block1.style.top = ''; block1.style.width = ''; block1.style.height = ''; }
            if (block2) { block2.style.left = ''; block2.style.top = ''; block2.style.width = ''; block2.style.height = ''; }
            setTimeout(function() {
                if (typeof refreshStandardBarcodeAfterLabelSizeChange === 'function') {
                    refreshStandardBarcodeAfterLabelSizeChange();
                }
                if (typeof positionBarcodeBlocks === 'function') positionBarcodeBlocks();
                if (typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
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

    /** Single-barcode sizes: reset position/size and re-draw after leaving 120×50 / 250×120 dual mode. */
    function refreshStandardBarcodeAfterLabelSizeChange() {
        if (isDualCanvasLayoutPreset()) return;
        var bc1 = document.getElementById('barcode1');
        var bc2 = document.getElementById('barcode2');
        var c1 = labelCanvas1 || document.getElementById('labelCanvas1');
        if (bc2) bc2.style.display = 'none';
        if (!bc1 || !c1) return;
        bc1.style.display = '';
        bc1.style.position = 'absolute';
        bc1.style.margin = '0';
        bc1.style.boxSizing = '';
        bc1.style.width = '';
        bc1.style.overflow = '';
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
        if (typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
        if (typeof clampBarcodeBlockIntoCanvas === 'function') clampBarcodeBlockIntoCanvas(c1, bc1);
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
                if (typeof renderCanvasBarcode === 'function') renderCanvasBarcode();
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
                moving.style.left = (left - 10) + 'px';
                moving.style.top = (top - 10) + 'px';
            }
            return;
        }

        var fieldName = e.dataTransfer.getData('text/plain');
        if (fieldName) {
            container.querySelectorAll('.canvas-dropped-item[data-field="' + fieldName + '"]').forEach(function(el, i) {
                if (i > 0) el.remove();
            });
            var existingDrop = container.querySelector('.canvas-dropped-item[data-field="' + fieldName + '"]');
            if (existingDrop) {
                existingDrop.style.left = (left - 10) + 'px';
                existingDrop.style.top = (top - 10) + 'px';
                syncPropertiesPanelFromDroppedItem(existingDrop);
            } else {
                var newEl = createDroppedItem(fieldName, left, top, container);
                syncPropertiesPanelFromDroppedItem(newEl);
                addFieldToOtherBlock(fieldName, container, newEl.getAttribute('data-prefix'), newEl.getAttribute('data-suffix'), newEl.getAttribute('data-font'), newEl.getAttribute('data-font-size'), newEl.getAttribute('data-pad-top'), newEl.getAttribute('data-pad-right'), newEl.getAttribute('data-pad-bottom'), newEl.getAttribute('data-pad-left'));
            }
            syncToolboxHighlight();
        }
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
        [labelPreview1, labelPreview2].forEach(function(cont) {
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
    
    function createDroppedItem(fieldName, left, top, container, opts) {
        opts = opts || {};
        container = container || labelCanvas1 || labelPreview1;
        var id = 'canvas-item-' + (++dropIdCounter);
        var el = document.createElement('div');
        el.className = 'canvas-dropped-item';
        el.setAttribute('data-field', fieldName);
        el.setAttribute('data-id', id);
        var prefix = opts.prefix !== undefined ? opts.prefix : fieldName;
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
        el.innerHTML = '<span class="canvas-item-text">' + (prefix + (suffix ? ' ' + suffix : '')).trim() + '</span><span class="canvas-item-remove" title="Remove">' + trashSvg + '</span>';
        if (opts.restore && (opts.left !== undefined || opts.top !== undefined)) {
            el.style.left = (opts.left !== undefined ? opts.left : (left - 10)) + 'px';
            el.style.top = (opts.top !== undefined ? opts.top : (top - 10)) + 'px';
        } else {
            el.style.left = (left - 10) + 'px';
            el.style.top = (top - 10) + 'px';
        }
        container.appendChild(el);
        updateDroppedItemDisplay(el);
        applyDroppedItemFont(el);

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
            if (it.type === 'barcode_image') return true;
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
            if (it.type === 'barcode_image') {
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
        var bw = barcodeEl.offsetWidth;
        var bh = barcodeEl.offsetHeight;
        if (bw <= 0 || bh <= 0) return;
        var l = parseInt(barcodeEl.style.left, 10);
        var t = parseInt(barcodeEl.style.top, 10);
        if (isNaN(l)) l = barcodeEl.offsetLeft;
        if (isNaN(t)) t = barcodeEl.offsetTop;
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

    function selectBarcodePrintWrap(wrap) {
        document.querySelectorAll('.barcode-print-wrap, .barcode-inner-draggable').forEach(function(w) {
            w.classList.remove('barcode-selected');
        });
        document.querySelectorAll('.canvas-dropped-item').forEach(function(i) {
            i.classList.remove('selected');
        });
        if (wrap) {
            wrap.classList.add('barcode-selected');
            syncBarcodeDisplayWidthPropFromWrap(wrap);
        }
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

    function ensureBarcodeResizeHandle(wrap) {
        if (!wrap || wrap.classList.contains('barcode-inner-draggable')) return;
        if (wrap.querySelector('.barcode-resize-handle')) return;
        var handle = document.createElement('span');
        var is82Resize = is82x38TwoBoxPreset() && (wrap.id === 'barcode1' || wrap.id === 'barcode2');
        handle.className = 'barcode-resize-handle' + (is82Resize ? ' barcode-resize-handle--82x38' : '');
        handle.setAttribute('aria-label', is82Resize ? 'Resize barcode width and height' : 'Resize barcode width');
        handle.title = is82Resize ? 'Drag to change barcode width and height' : 'Drag to change barcode width';
        wrap.appendChild(handle);
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
            startW = wrap.offsetWidth || parseInt(wrap.getAttribute('data-display-width'), 10) || 80;
            startH = wrap.offsetHeight || parseInt(wrap.getAttribute('data-display-height'), 10) || 24;
            startLeft = parseInt(wrap.style.left, 10);
            if (isNaN(startLeft)) startLeft = wrap.offsetLeft || 0;
            document.body.style.cursor = is82Resize ? 'nwse-resize' : 'ew-resize';
        });
        document.addEventListener('mousemove', function(e) {
            if (!resizing) return;
            var canvasEl = wrap.closest('.barcode-label-canvas');
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
            var maxW = getBarcodeMaxDisplayWidthPx(canvasEl, wrap);
            var newW = Math.max(24, Math.min(maxW, startW + (e.clientX - startX)));
            wrap.setAttribute('data-display-width', String(Math.round(newW)));
            wrap.style.left = startLeft + 'px';
            var prop = document.getElementById('propBarcodeDisplayWidth');
            if (prop && wrap.classList.contains('barcode-selected')) prop.value = String(Math.round(newW));
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

    function initBarcodePrintWrapInteractions(wrap) {
        if (!wrap || wrap._barcodeUiBound) return;
        wrap._barcodeUiBound = true;
        ensureBarcodeResizeHandle(wrap);
        wrap.addEventListener('mousedown', function(e) {
            if (e.target.closest('.barcode-resize-handle')) return;
            selectBarcodePrintWrap(wrap);
        });
    }

    function getBarcodeBarOptions() {
        var w = parseInt(document.getElementById('propBarcodeBarWidth') && document.getElementById('propBarcodeBarWidth').value, 10);
        var h = parseInt(document.getElementById('propBarcodeBarHeight') && document.getElementById('propBarcodeBarHeight').value, 10);
        var c1 = labelCanvas1 || document.getElementById('labelCanvas1');
        var ch = (c1 && c1.clientHeight > 0) ? c1.clientHeight : Math.round((labelHeightMm || 18) * MM_TO_PX);
        var isShortLabel = (labelHeightMm || 18) <= 20;
        var maxBarH = Math.max(8, ch - (isShortLabel ? 8 : 12));
        var minBarH = isShortLabel ? 8 : 10;
        var barH = (isNaN(h) || h < minBarH) ? (isShortLabel ? 10 : 28) : Math.min(200, h);
        barH = Math.min(barH, maxBarH);
        var barW = (isNaN(w) || w < 1) ? (isShortLabel ? 1 : 2) : Math.min(10, w);
        return { width: barW, height: barH, minBarH: minBarH };
    }
    /** 82×38: boxes → JsBarcode → apply saved mm (no auto-align after). */
    function render82x38PreviewPipeline(opts) {
        opts = opts || {};
        if (!is82x38TwoBoxPreset() || typeof JsBarcode === 'undefined') return;
        if (!opts.skipBoxLayout) {
            layout82x38DualCanvases();
        }
        var bl = read82x38BoxLayoutFromInputs();
        var jsOpts = get82x38JsBarcodeOpts();
        var pairs = [
            { svg: document.getElementById('barcodeSvgBox1'), wrap: document.getElementById('barcode1'), code: String(sampleBarcodeNumber || '00002').trim() || '00002', text: document.getElementById('barcodeText1'), barW: bl.box1_barcode_width_mm, barH: bl.box1_barcode_height_mm, barL: bl.box1_barcode_left_mm, barT: bl.box1_barcode_top_mm },
            { svg: document.getElementById('barcodeSvgBox2'), wrap: document.getElementById('barcode2'), code: String(sampleBarcodeNumber2 || '00003').trim() || '00003', text: document.getElementById('barcodeText2'), barW: bl.box2_barcode_width_mm, barH: bl.box2_barcode_height_mm, barL: bl.box2_barcode_left_mm, barT: bl.box2_barcode_top_mm }
        ];
        pairs.forEach(function(p) {
            if (!p.svg || !p.wrap) return;
            p.wrap.style.display = 'block';
            p.wrap.style.visibility = 'visible';
            p.svg.style.display = 'block';
            p.svg.setAttribute('data-barcode', p.code);
            if (!is82x38TwoBoxPreset() || !p.wrap.classList.contains('barcode-inner-draggable')) {
                initBarcodePrintWrapInteractions(p.wrap);
            }
            try {
                p.svg.innerHTML = '';
                JsBarcode(p.svg, p.code, jsOpts);
            } catch (e) {
                p.svg.innerHTML = '';
            }
            if (p.text) p.text.textContent = p.code;
        });
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                pairs.forEach(function(p) {
                    if (!p.wrap) return;
                    apply82x38BarcodeLayoutMm(p.wrap, p.barL, p.barT, p.barW, p.barH);
                });
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
        if (is82x38TwoBoxPreset()) {
            render82x38PreviewPipeline();
            return;
        }
        var opts = getBarcodeBarOptions();
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
            var maxBarH = Math.max(8, chCanvas - 18);
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
        });
        var bt1 = document.getElementById('barcodeText1');
        var bt2 = document.getElementById('barcodeText2');
        if (bt1) bt1.textContent = code;
        if (bt2) bt2.textContent = code2;
        if (typeof toggleBarcodeNumber === 'function') toggleBarcodeNumber();
        if (barcode1El && !document.querySelector('.barcode-print-wrap.barcode-selected')) {
            selectBarcodePrintWrap(barcode1El);
        }
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
                if (qw) qw.value = Math.max(30, Math.min(200, parseInt(parsed.qr_width, 10) || 60));
            }
            if (parsed.qr_height != null) {
                var qh = document.getElementById('propQrHeight');
                if (qh) qh.value = Math.max(30, Math.min(200, parseInt(parsed.qr_height, 10) || 60));
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
            var skipMmBarcode1Position = hasPinnedBarcode1Px || hasLegacyBarcodePos;
            var is82Restore = (parsed.layout_type === '82x38_2box' || parsed.sticker_82x38_2box || parsed.box1_left_mm != null);
            if (is82Restore) skipMmBarcode1Position = true;
            var isDualRestore = isDualCanvasLayoutPreset();
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
                            }
                            if (!isDualRestore && barcodePrintWrap && barcodeStripes) {
                                var wPx = Math.round(wMm * mmToPxX);
                                var hPx = Math.round(hMm * mmToPxY);
                                var maxBarPx = Math.max(10, ch - 4);
                                hPx = Math.min(hPx, maxBarPx);
                                wPx = Math.min(wPx, Math.max(40, cw - 4));
                                barcodePrintWrap.style.boxSizing = 'border-box';
                                barcodePrintWrap.style.width = wPx + 'px';
                                barcodePrintWrap.style.overflow = 'visible';
                                barcodeStripes.style.width = '100%';
                                barcodeStripes.style.height = hPx + 'px';
                                barcodeStripes.style.minHeight = hPx + 'px';
                                barcodeStripes.style.overflow = 'hidden';
                            }
                        }
                        return;
                    }
                    if (it.field) {
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
            }
            if (!Array.isArray(parsed) && (parsed.layout_type === '82x38_2box' || parsed.sticker_82x38_2box || parsed.box1_left_mm != null)) {
                function setBoxInput(id, val) {
                    var el = document.getElementById(id);
                    if (el && val != null && !isNaN(parseFloat(val))) el.value = parseFloat(val);
                }
                var b1Left = parsed.box1_left_mm;
                var b1Top = parsed.box1_top_mm;
                var b2Left = parsed.box2_left_mm;
                var b2Top = parsed.box2_top_mm;
                if (parsed.box1 && parsed.box1.left_mm != null) b1Left = parsed.box1.left_mm;
                if (parsed.box1 && parsed.box1.top_mm != null) b1Top = parsed.box1.top_mm;
                if (parsed.box2 && parsed.box2.left_mm != null) b2Left = parsed.box2.left_mm;
                if (parsed.box2 && parsed.box2.top_mm != null) b2Top = parsed.box2.top_mm;
                if (b2Left == null && parsed.box2_right_mm != null && (parsed.box2_left_mm == null && (!parsed.box2 || parsed.box2.left_mm == null))) {
                    b2Left = Math.max(0, 82 - parseFloat(parsed.box2_right_mm) - BOX_82X38_WIDTH_MM);
                }
                setBoxInput('propBox1LeftMm', b1Left);
                setBoxInput('propBox1TopMm', b1Top);
                setBoxInput('propBox2LeftMm', b2Left);
                setBoxInput('propBox2TopMm', b2Top);
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
                setBoxInput('propBox1BarcodeLeftMm', b1BarLeft);
                setBoxInput('propBox1BarcodeTopMm', b1BarTop);
                setBoxInput('propBox2BarcodeLeftMm', b2BarLeft);
                setBoxInput('propBox2BarcodeTopMm', b2BarTop);
                setBoxInput('propBoxBarcodeNoMarginTopMm', parsed.barcode_no_margin_top_mm || parsed.barcode_number_gap_mm);
                setBoxInput('propBoxBarcodeNoFontSize', parsed.barcode_no_font_size || parsed.barcode_number_font_pt);
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
                                barcodePrintWrap2.style.boxSizing = 'border-box';
                                barcodePrintWrap2.style.width = wPx2 + 'px';
                                barcodePrintWrap2.style.overflow = 'visible';
                                barcodeStripes2.style.width = '100%';
                                barcodeStripes2.style.height = hPx2 + 'px';
                                barcodeStripes2.style.minHeight = hPx2 + 'px';
                                barcodeStripes2.style.overflow = 'hidden';
                            }
                        }
                        return;
                    }
                    if (it.field) {
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
            syncToolboxHighlight();
            dedupeCanvasDomFields();
            var rootAfterRestore = labelCanvas1 || labelPreview1;
            syncPropertiesPanelAfterLayoutRestore(rootAfterRestore);
            restoreBarcodePosition(parsed);
            refreshCodeGraphicAfterLayoutRestore();
            restoreBarcodePosition(parsed);
            clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
            clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
            setTimeout(function() {
                refreshCodeGraphicAfterLayoutRestore();
                setTimeout(function() {
                    restoreBarcodePosition(parsed);
                    if (isDualCanvasLayoutPreset() && labelCanvas2) {
                        var cw2Late = labelCanvas2.offsetWidth || 270;
                        var ch2Late = labelCanvas2.offsetHeight || 54;
                        restoreBarcode2Position(parsed, cw2Late / getDualTagPrintBoxWidthMm(), ch2Late / getDualTagPrintBoxHeightMm());
                    }
                    clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                    clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
                    if (labelPreview1 && labelCanvas1) adjustDroppedTextBelowBarcode(labelPreview1, labelCanvas1, labelWidthMm, labelHeightMm, 'barcode1');
                    if (labelPreview2 && labelCanvas2) adjustDroppedTextBelowBarcode(labelPreview2, labelCanvas2, labelWidthMm, labelHeightMm, 'barcode2');
                }, 60);
            }, 80);
        } catch (e) { console.warn('Could not restore barcode design', e); }
    }

    /** Apply physical label box size first so mm→px restore uses correct preview dimensions; restore after layout. */
    function initBarcodeDesignerLayoutFromSaved() {
        updatePropertiesPanelForCodeType();
        if (is82x38TwoBoxPreset()) {
            render82x38DualEditor();
        } else {
            applyLabelSizeToBox();
        }
        setTimeout(function() {
            restoreSavedLayout();
            if (is82x38TwoBoxPreset()) {
                setTimeout(function() {
                    layout82x38DualCanvases();
                    render82x38PreviewPipeline({ skipBoxLayout: true });
                    syncDualCanvasHeight();
                }, 130);
            } else if (isDualCanvasLayoutPreset()) {
                setTimeout(function() {
                    ensureDualBarcodesRendered({ preservePositions: true });
                    try {
                        var pDual = JSON.parse(savedDesignLayout);
                        var c2Init = labelCanvas2 || document.getElementById('labelCanvas2');
                        if (pDual && c2Init) {
                            var cwInit = c2Init.offsetWidth || 270;
                            var chInit = c2Init.offsetHeight || 54;
                            restoreBarcode2Position(pDual, cwInit / getDualTagPrintBoxWidthMm(), chInit / getDualTagPrintBoxHeightMm());
                        }
                    } catch (e) {}
                    clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                    clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
                    syncDualCanvasHeight();
                }, 130);
            } else {
                setTimeout(refreshStandardBarcodeAfterLabelSizeChange, 150);
            }
        }, 50);
    }
    function scheduleBarcodeRenderAfterDomReady() {
        function run() {
            if (is82x38TwoBoxPreset()) return;
            setTimeout(function() {
                refreshCodeGraphicAfterLayoutRestore();
                restoreBarcodePosition();
                if (isDualCanvasLayoutPreset()) {
                    try {
                        var pLate = JSON.parse(savedDesignLayout);
                        var c2Late = labelCanvas2 || document.getElementById('labelCanvas2');
                        if (pLate && c2Late) {
                            restoreBarcode2Position(pLate, c2Late.offsetWidth / getDualTagPrintBoxWidthMm(), c2Late.offsetHeight / getDualTagPrintBoxHeightMm());
                        }
                    } catch (e) {}
                }
                clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
            }, 100);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
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
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (is82x38TwoBoxPreset()) return;
                restoreBarcodePosition();
                refreshCodeGraphicAfterLayoutRestore();
                clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
                clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
            }, 150);
        });
    } else {
        initBarcodeDesignerLayoutFromSaved();
        syncToolboxToMetalDropdownOnLoad();
        attach82x38BarcodeResizeDelegation();
        setTimeout(function() {
            if (is82x38TwoBoxPreset()) return;
            restoreBarcodePosition();
            refreshCodeGraphicAfterLayoutRestore();
            clampBarcodeBlockIntoCanvas(labelCanvas1, document.getElementById('barcode1'));
            clampBarcodeBlockIntoCanvas(labelCanvas2, document.getElementById('barcode2'));
        }, 150);
    }
    scheduleBarcodeRenderAfterDomReady();

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
        var canvas = labelCanvas1;
        if (!barcodeBox || !canvas) return;
        if (!barcodeBox.classList.contains('barcode-inner-draggable')) {
            initBarcodePrintWrapInteractions(barcodeBox);
        }
        var isDragging = false, offsetX, offsetY;
        barcodeBox.addEventListener('mousedown', function(e) {
            if (is82x38TwoBoxPreset()) return;
            if (e.target.closest('.barcode-resize-handle')) return;
            selectBarcodePrintWrap(barcodeBox);
            offsetX = e.offsetX;
            offsetY = e.offsetY;
            e.preventDefault();
            e.stopPropagation();
            isDragging = true;
        });
        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            var rect = canvas.getBoundingClientRect();
            var w = barcodeBox.offsetWidth || 120;
            var h = barcodeBox.offsetHeight || 30;
            var left = e.clientX - rect.left - offsetX;
            var top = e.clientY - rect.top - offsetY;
            var maxLeft = rect.width - w;
            var maxTop = rect.height - h;
            left = maxLeft < 0 ? (rect.width - w) / 2 : Math.max(0, Math.min(maxLeft, left));
            top = maxTop < 0 ? (rect.height - h) / 2 : Math.max(0, Math.min(maxTop, top));
            barcodeBox.style.left = Math.round(left) + 'px';
            barcodeBox.style.top = Math.round(top) + 'px';
        });
        document.addEventListener('mouseup', function() {
            if (!isDragging) return;
            isDragging = false;
            if (is82x38TwoBoxPreset()) {
                sync82x38BarcodePositionsFromDom({ syncSize: false });
                reapply82x38BarcodePositionsFromInputs();
            } else {
                clampBarcodeBlockIntoCanvas(canvas, barcodeBox);
            }
        });
    })();
    (function initBarcode2Drag() {
        var barcodeBox = barcode2El;
        if (!barcodeBox) return;
        var isDragging = false, offsetX, offsetY;
        barcodeBox.addEventListener('mousedown', function(e) {
            if (is82x38TwoBoxPreset()) return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            isDragging = true;
            offsetX = e.offsetX;
            offsetY = e.offsetY;
        });
        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            var canvas = barcodeBox.closest('.barcode-label-canvas') || labelCanvas2;
            if (!canvas) return;
            var rect = canvas.getBoundingClientRect();
            var w = barcodeBox.offsetWidth || 120;
            var h = barcodeBox.offsetHeight || 30;
            var left = e.clientX - rect.left - offsetX;
            var top = e.clientY - rect.top - offsetY;
            var maxLeft = rect.width - w;
            var maxTop = rect.height - h;
            left = maxLeft < 0 ? (rect.width - w) / 2 : Math.max(0, Math.min(maxLeft, left));
            top = maxTop < 0 ? (rect.height - h) / 2 : Math.max(0, Math.min(maxTop, top));
            barcodeBox.style.left = Math.round(left) + 'px';
            barcodeBox.style.top = Math.round(top) + 'px';
        });
        document.addEventListener('mouseup', function() {
            if (!isDragging) return;
            isDragging = false;
            if (is82x38TwoBoxPreset()) {
                sync82x38BarcodePositionsFromDom({ syncSize: false });
                reapply82x38BarcodePositionsFromInputs();
            } else {
                var canvasUp = barcodeBox.closest('.barcode-label-canvas') || labelCanvas2;
                clampBarcodeBlockIntoCanvas(canvasUp, barcodeBox);
            }
        });
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

    if (whiteDropZone) {
        document.addEventListener('dragstart', function() { whiteDropZone.classList.add('dragging-active'); });
        document.addEventListener('dragend', function() { whiteDropZone.classList.remove('dragging-active'); });
        document.addEventListener('drop', function() { whiteDropZone.classList.remove('dragging-active'); });
    }
    if (whiteDropZone2) {
        document.addEventListener('dragstart', function() { whiteDropZone2.classList.add('dragging-active'); });
        document.addEventListener('dragend', function() { whiteDropZone2.classList.remove('dragging-active'); });
        document.addEventListener('drop', function() { whiteDropZone2.classList.remove('dragging-active'); });
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

    // Move selected item: buttons + arrow keys
    var MOVE_STEP = 8;
    function moveSelected(dx, dy) {
        var sel = document.querySelector('.canvas-dropped-item.selected');
        if (!sel) return;
        var l = parseInt(sel.style.left, 10) || 0;
        var t = parseInt(sel.style.top, 10) || 0;
        sel.style.left = (l + dx) + 'px';
        sel.style.top = (t + dy) + 'px';
        syncToSecondLabel();
    }
    document.getElementById('btnMoveUp').addEventListener('click', function() { moveSelected(0, -MOVE_STEP); });
    document.getElementById('btnMoveDown').addEventListener('click', function() { moveSelected(0, MOVE_STEP); });
    document.getElementById('btnMoveLeft').addEventListener('click', function() { moveSelected(-MOVE_STEP, 0); });
    document.getElementById('btnMoveRight').addEventListener('click', function() { moveSelected(MOVE_STEP, 0); });
    document.addEventListener('keydown', function(e) {
        var sel = document.querySelector('.canvas-dropped-item.selected');
        if (!sel) return;
        if (e.key === 'ArrowUp') { e.preventDefault(); moveSelected(0, -MOVE_STEP); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); moveSelected(0, MOVE_STEP); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); moveSelected(-MOVE_STEP, 0); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); moveSelected(MOVE_STEP, 0); }
    });

    // Barcode blocks: both draggable independently (mouse drag)
    var barcodeBlock1 = document.getElementById('box1');
    var barcodeBlock2 = document.getElementById('box2');
    var canvasRect = canvas.getBoundingClientRect();
    
    function positionBarcodeBlocks() {
        if (isDualLabelLayoutPreset()) return;
        var r = canvas.getBoundingClientRect();
        var label2 = document.getElementById('box2');
        var showTwo = label2 && label2.style.display !== 'none';
        if (barcodeBlock1) {
            var w1 = barcodeBlock1.offsetWidth;
            var h1 = barcodeBlock1.offsetHeight;
            if (showTwo) {
                barcodeBlock1.style.left = (r.width / 4 - w1 / 2) + 'px';
            } else {
                barcodeBlock1.style.left = (r.width / 2 - w1 / 2) + 'px';
            }
            barcodeBlock1.style.top = (r.height / 2 - h1 / 2) + 'px';
        }

        if (barcodeBlock2 && showTwo) {
            var w2 = barcodeBlock2.offsetWidth;
            var h2 = barcodeBlock2.offsetHeight;
            barcodeBlock2.style.left = (r.width * 3 / 4 - w2 / 2) + 'px';
            barcodeBlock2.style.top = (r.height / 2 - h2 / 2) + 'px';
        }
    }
    setTimeout(positionBarcodeBlocks, 50);
    window.addEventListener('resize', function() {
        positionBarcodeBlocks();
        syncDualCanvasHeight();
    });
    
    function makeBarcodeBlockDraggable(block) {
        if (!block) return;
        var dragging = false, startX, startY, startLeft, startTop;
        
        block.addEventListener('mousedown', function(e) {
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
                startLeft = parseInt(block.style.left, 10);
                if (isNaN(startLeft)) startLeft = block.offsetLeft || 0;
                startTop = parseInt(block.style.top, 10);
                if (isNaN(startTop)) startTop = block.offsetTop || 0;
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
        var c1 = labelCanvas1 || document.getElementById('labelCanvas1');
        var ch = (c1 && c1.clientHeight > 0) ? c1.clientHeight : Math.round((labelHeightMm || 18) * MM_TO_PX);
        var maxH = Math.max(minH, ch - (isShortLabelSize() ? 8 : 12));
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
    ['propBox1LeftMm', 'propBox1TopMm', 'propBox2LeftMm', 'propBox2TopMm', 'propBox1BarcodeWidthMm', 'propBox1BarcodeHeightMm', 'propBox2BarcodeWidthMm', 'propBox2BarcodeHeightMm', 'propBox1BarcodeLeftMm', 'propBox1BarcodeTopMm', 'propBox2BarcodeLeftMm', 'propBox2BarcodeTopMm', 'propBoxBarcodeNoMarginTopMm', 'propBoxBarcodeNoFontSize'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        function onBoxSettingChange() {
            if (!is82x38TwoBoxPreset()) return;
            if (id.indexOf('BarcodeLeft') >= 0 || id.indexOf('BarcodeTop') >= 0 ||
                id.indexOf('BarcodeWidth') >= 0 || id.indexOf('BarcodeHeight') >= 0) {
                render82x38PreviewPipeline();
                return;
            }
            layout82x38DualCanvases();
            remove82x38OuterCmRuler();
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
    function onQrSizeChange() { updateBarcodeQrDisplay(); }
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
            metal_type: metalSelect ? (metalSelect.value || '') : ''
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
                barcode1LeftMm = clampMmGlobal(leftPx1 * pxToMmX1, labelW);
                barcode1TopMm = clampMmGlobal(topPx1 * pxToMmY1, labelH);
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
            labelPreview1El.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
                var left = parseInt(item.style.left, 10);
                var top = parseInt(item.style.top, 10);
                if (isNaN(left)) left = 0;
                if (isNaN(top)) top = 0;
                var leftMm = clampMmGlobal(left * pxToMmX1, labelW);
                var topMm = clampMmGlobal(top * pxToMmY1, labelH);
                if (barcodeBottomMm > 0) {
                    topMm = resolveTextTopMmBelowBarcodeIfOverlap(leftMm, topMm, barcodeLeftMm, barcodeRightMm, barcodeTopMm, barcodeBottomMm, labelW, labelH, gapMm);
                }
                // Keep on-screen preview in sync with what we save (mm → px)
                var newTopPx = Math.round(topMm / pxToMmY1);
                item.style.top = newTopPx + 'px';
                designItems.push({
                    type: 'text',
                    field: item.getAttribute('data-field') || '',
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
            labelPreview2El.querySelectorAll('.canvas-dropped-item').forEach(function(item) {
                var left = parseInt(item.style.left, 10);
                var top = parseInt(item.style.top, 10);
                if (isNaN(left)) left = 0;
                if (isNaN(top)) top = 0;
                var leftMm = clampMmGlobal(left * pxToMmX2, labelWHalf);
                var topMm = clampMmGlobal(top * pxToMmY2, labelHSave);
                var gap2 = 1.5;
                if (barcodeBottomMm2 > 0) {
                    topMm = resolveTextTopMmBelowBarcodeIfOverlap(leftMm, topMm, barcodeLeftMm2, barcodeRightMm2, barcodeTopMm2, barcodeBottomMm2, labelW, labelH, gap2);
                }
                // Keep on-screen preview in sync with what we save (mm → px)
                var newTopPx2 = Math.round(topMm / pxToMmY2);
                item.style.top = newTopPx2 + 'px';
                designItems2.push({
                    type: 'text',
                    field: item.getAttribute('data-field') || '',
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
            var box1ExtraItems = designItems.filter(function(it) { return it && it.type !== 'barcode_image'; });
            var box2ExtraItems = designItems2.filter(function(it) { return it && it.type !== 'barcode_image'; });
            layoutPayload.box1 = {
                left_mm: boxLayout.box1_left_mm,
                top_mm: boxLayout.box1_top_mm,
                width_mm: boxLayout.box_width_mm,
                height_mm: boxLayout.box_height_mm,
                items: [box1BarcodeItem].concat(box1ExtraItems)
            };
            layoutPayload.box2 = {
                left_mm: boxLayout.box2_left_mm,
                top_mm: boxLayout.box2_top_mm,
                width_mm: boxLayout.box_width_mm,
                height_mm: boxLayout.box_height_mm,
                items: [box2BarcodeItem].concat(box2ExtraItems)
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
            var qw = parseInt(document.getElementById('propQrWidth') && document.getElementById('propQrWidth').value, 10);
            var qh = parseInt(document.getElementById('propQrHeight') && document.getElementById('propQrHeight').value, 10);
            layoutPayload.qr_width = (isNaN(qw) || qw < 30) ? 60 : Math.min(200, qw);
            layoutPayload.qr_height = (isNaN(qh) || qh < 30) ? 60 : Math.min(200, qh);
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
        layoutPayload.layout_variant = (typeof currentCodeType !== 'undefined' && currentCodeType === 'qr') ? 'qr' : 'barcode';
        if (typeof currentCodeType !== 'undefined' && currentCodeType === 'barcode') {
            persistedLayoutBarcode = JSON.stringify(layoutPayload);
        } else {
            persistedLayoutQr = JSON.stringify(layoutPayload);
        }
        payload.design_layout = persistedLayoutBarcode;
        payload.design_layout_qr = persistedLayoutQr;
        var dpcEl = document.getElementById('defaultPrintCodeType');
        payload.default_print_code_type = (dpcEl && dpcEl.value === 'qr') ? 'qr' : 'barcode';
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
                        alert(data.message || 'Barcode settings saved.');
                        var metalEl = document.getElementById('barcodeMetalType');
                        var labelEl = document.getElementById('barcodeLabelSize');
                        var metal = metalEl ? (metalEl.value || '') : (payload.metal_type || '');
                        var label = labelEl ? (labelEl.value || '') : (payload.label_size_preset || '100x18');
                        window.location.href = 'set-software.php?barcode_metal=' + encodeURIComponent(metal) + '&barcode_label_size=' + encodeURIComponent(label);
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

