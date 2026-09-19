<?php
/**
 * Jewellery Catalogue — create / edit (from sale order process or standalone).
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/jewelry_catalog_stock_include.php';
require_once __DIR__ . '/includes/jewelry_catalogue_create_include.php';

if (!function_exists('auragold_nav_show_php_href')) {
    require_once __DIR__ . '/includes/auragold_sidebar_nav_permissions.php';
}

auragold_ensure_jewelry_catalogue_table($conn);

$jcc_title = function_exists('auragold_t')
    ? auragold_t('inv.jewellery_catalogue')
    : 'Jewellery Catalogue';
$page_heading = 'Jewellery Catalogue';

$catalogue_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$order_kind = isset($_GET['order_kind']) ? trim((string) $_GET['order_kind']) : 'sale';
$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;

if ($catalogue_id > 0) {
    $jcc_row = auragold_jewelry_catalogue_load_by_id($conn, $catalogue_id);
} elseif ($order_id > 0 && $item_id > 0) {
    $jcc_row = auragold_jewelry_catalogue_prefill_from_order($conn, $order_kind, $order_id, $item_id);
} else {
    $jcc_row = auragold_jewelry_catalogue_blank_row();
}

if (!$jcc_row) {
    $jcc_row = auragold_jewelry_catalogue_blank_row();
}

$catalogue_id_loaded = (int) ($jcc_row['id'] ?? 0);
if ($catalogue_id_loaded <= 0 && trim((string) ($jcc_row['design_no'] ?? '')) === '') {
    $jcc_row['design_no'] = auragold_next_jewelry_catalogue_design_no($conn);
}

$jcc_masters = auragold_jewelry_catalog_filter_masters($conn);
$jcc_metals = $jcc_masters['metals'] ?? [];
$jcc_products = $jcc_masters['products'] ?? [];
$jcc_categories = $jcc_masters['categories'] ?? [];

$jcc_row_json = json_encode($jcc_row, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($jcc_row_json === false) {
    $jcc_row_json = '{}';
}

$back_url = 'jewelry-catalogue.php';
if (!empty($_GET['return'])) {
    $ret = trim((string) $_GET['return']);
    if ($ret !== '' && strpos($ret, '://') === false && strpos($ret, '..') === false) {
        $back_url = $ret;
    }
} elseif ($order_id > 0) {
    $back_url = 'sale-order-process.php';
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_heading, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        :root {
            --jcc-navy: #11294b;
            --jcc-pink: #e83e8c;
            --jcc-pink-dark: #d62d7c;
        }
        /* Scroll: page grows naturally + large footer gap */
        html:has(body.jewelry-catalogue-create-page),
        body.jewelry-catalogue-create-page {
            overflow-x: hidden !important;
            overflow-y: auto !important;
            height: auto !important;
            min-height: 100%;
        }
        body.jewelry-catalogue-create-page .layout-wrapper,
        body.jewelry-catalogue-create-page .layout-container,
        body.jewelry-catalogue-create-page .layout-inner {
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
        }
        body.jewelry-catalogue-create-page .layout-content {
            height: auto !important;
            min-height: calc(100vh - 58px);
            max-height: none !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            padding-bottom: 0 !important;
        }
        .jcc-wrap {
            padding: 6px 12px 0;
            max-width: 100%;
            box-sizing: border-box;
        }
        .jcc-top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }
        .jcc-top h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--jcc-navy);
        }
        .jcc-top-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .jcc-btn-icon {
            width: 36px; height: 36px; border-radius: 6px;
            border: 1px solid #cbd5e1; background: #fff; color: var(--jcc-navy);
            display: inline-flex; align-items: center; justify-content: center;
        }
        .jcc-btn-new {
            background: var(--jcc-pink); color: #fff; border: none;
            border-radius: 6px; font-weight: 600; padding: 0.4rem 1rem;
        }
        .jcc-btn-new:hover { background: var(--jcc-pink-dark); color: #fff; }
        .jcc-btn-save {
            background: #2563eb; color: #fff; border: none;
            border-radius: 6px; font-weight: 600; padding: 0.4rem 1.1rem;
        }
        .jcc-btn-save:hover { background: #1d4ed8; color: #fff; }
        .jcc-btn-close {
            background: #fff; color: var(--jcc-navy); border: 1px solid #cbd5e1;
            border-radius: 6px; width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .jcc-layout { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; }
        .jcc-main { flex: 1 1 480px; min-width: 0; }
        .jcc-side {
            flex: 0 0 200px; max-width: 100%;
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: 8px 10px;
        }
        .jcc-side h3 { font-size: 0.85rem; font-weight: 700; color: var(--jcc-navy); margin: 0 0 6px; }
        .jcc-upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 8px;
            min-height: 110px; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: #94a3b8; cursor: pointer; background: #fff;
            position: relative; overflow: hidden;
            font-size: 0.8rem;
        }
        .jcc-upload-zone:hover { border-color: var(--jcc-pink); color: var(--jcc-pink); }
        .jcc-upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }
        .jcc-img-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .jcc-img-thumb {
            width: 72px; height: 72px; object-fit: cover;
            border-radius: 6px; border: 1px solid #e2e8f0;
        }
        .jcc-img-wrap { position: relative; }
        .jcc-img-remove {
            position: absolute; top: -6px; right: -6px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #dc2626; color: #fff; border: none;
            font-size: 12px; line-height: 1; cursor: pointer;
        }
        .jcc-card {
            background: #fff; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: 8px 10px; margin-bottom: 8px;
        }
        .jcc-label { font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 2px; display: block; }
        .jcc-label .req { color: #dc2626; }
        #jccForm.jcc-compact .form-group { margin-bottom: 0.35rem; }
        #jccForm.jcc-compact .row { margin-left: -5px; margin-right: -5px; }
        #jccForm.jcc-compact .row > [class*="col-"] { padding-left: 5px; padding-right: 5px; }
        #jccForm.jcc-compact .form-control-sm { padding: 0.2rem 0.45rem; font-size: 0.8125rem; height: calc(1.5em + 0.45rem); }
        #jccForm.jcc-compact textarea.form-control-sm { height: auto; min-height: calc(1.5em + 0.45rem); }
        #jccDesignNo.is-invalid { border-color: #dc2626; }
        .jcc-design-no-error {
            display: none;
            font-size: 0.7rem;
            color: #dc2626;
            margin-top: 2px;
            line-height: 1.2;
        }
        .jcc-bom-head {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 6px;
        }
        .jcc-bom-head h3 { margin: 0; font-size: 0.9rem; font-weight: 700; color: var(--jcc-navy); }
        .jcc-bom-actions {
            display: flex; align-items: center; justify-content: center; gap: 2px;
            white-space: nowrap;
        }
        .jcc-bom-act {
            border: none; background: transparent; padding: 2px 4px;
            line-height: 1; cursor: pointer; border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .jcc-bom-act .feather { width: 15px; height: 15px; }
        .jcc-bom-edit { color: #2563eb; }
        .jcc-bom-edit:hover { background: #eff6ff; color: #1d4ed8; }
        .jcc-bom-del { color: #dc2626; }
        .jcc-bom-del:hover { background: #fef2f2; color: #b91c1c; }
        .jcc-bom-edit-loader {
            position: fixed;
            inset: 0;
            z-index: 10060;
            background: rgba(17, 41, 75, 0.42);
            display: none;
            align-items: center;
            justify-content: center;
        }
        .jcc-bom-edit-loader.show { display: flex; }
        .jcc-bom-edit-loader-box {
            background: #fff;
            border-radius: 12px;
            padding: 22px 28px;
            text-align: center;
            box-shadow: 0 12px 40px rgba(17, 41, 75, 0.25);
            min-width: 160px;
        }
        .jcc-bom-edit-loader-box .spinner-border {
            width: 2rem;
            height: 2rem;
            color: var(--jcc-pink);
            margin-bottom: 10px;
        }
        .jcc-bom-edit-loader-box span {
            display: block;
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--jcc-navy);
        }
        .jcc-add-item {
            display: block; width: 100%; border: 2px dashed var(--jcc-pink);
            background: #fff5f9; color: var(--jcc-pink); border-radius: 8px;
            padding: 8px; text-align: center; font-weight: 600; font-size: 0.8125rem;
            cursor: pointer; margin-bottom: 8px;
        }
        .jcc-add-item:hover { background: #ffe8f3; }
        .jcc-bom-table-wrap { overflow-x: auto; margin-bottom: 20px; padding-bottom: 8px; }
        .jcc-bom-table { width: 100%; font-size: 0.8125rem; border-collapse: collapse; }
        .jcc-bom-table th {
            background: #f1f5f9; padding: 8px 6px; white-space: nowrap;
            border-bottom: 1px solid #e2e8f0; font-weight: 600;
        }
        .jcc-bom-table td {
            padding: 6px; border-bottom: 1px solid #f1f5f9; vertical-align: middle;
        }
        .jcc-bom-val {
            display: block;
            min-width: 70px;
            max-width: 180px;
            font-size: 0.8125rem;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .jcc-bom-val.jcc-bom-merged-desc {
            font-weight: 600;
            color: var(--jcc-navy);
        }
        .jcc-bom-empty { text-align: center; color: #94a3b8; padding: 24px; }
        .jcc-cat-plus {
            border: none; background: #2563eb; color: #fff;
            width: 32px; height: 32px; border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .jcc-page-footer-spacer {
            height: 1px;
            flex-shrink: 0;
            pointer-events: none;
        }
        .jcc-page-footer-scroll {
            display: block;
            height: max(180px, 24vh);
            min-height: 180px;
            line-height: 0;
            font-size: 0;
            pointer-events: none;
            user-select: none;
        }
        @media (max-width: 991px) {
            .jcc-side { flex: 1 1 100%; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/jewelry-catalogue-product-modal.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/jewelry-catalogue-product-modal.css'); ?>">
</head>
<body class="jewelry-catalogue-create-page">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="jcc-wrap container-fluid">
        <div class="jcc-top">
            <h1><?php echo htmlspecialchars($page_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="jcc-top-actions">
                <button type="button" class="jcc-btn-icon" id="jccBtnPrint" title="Print"><i class="feather icon-file-text"></i></button>
                <button type="button" class="jcc-btn-new" id="jccBtnNew">New</button>
                <button type="button" class="jcc-btn-save" id="jccBtnSave">Save</button>
                <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="jcc-btn-close" title="Close"><i class="feather icon-x"></i></a>
            </div>
        </div>

        <form id="jccForm" class="jcc-compact" autocomplete="off">
            <input type="hidden" id="jccId" value="<?php echo (int) ($jcc_row['id'] ?? 0); ?>">
            <input type="hidden" id="jccSaleOrderId" value="<?php echo (int) ($jcc_row['sale_order_id'] ?? 0); ?>">
            <input type="hidden" id="jccSaleItemId" value="<?php echo (int) ($jcc_row['sale_order_item_id'] ?? 0); ?>">
            <input type="hidden" id="jccRepairOrderId" value="<?php echo (int) ($jcc_row['repair_order_id'] ?? 0); ?>">
            <input type="hidden" id="jccRepairItemId" value="<?php echo (int) ($jcc_row['repair_order_item_id'] ?? 0); ?>">

            <div class="jcc-layout">
                <div class="jcc-main">
                    <div class="jcc-card">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="jcc-label" for="jccMetal">Metal</label>
                                <select class="form-control form-control-sm" id="jccMetal">
                                    <option value="">Select</option>
                                    <?php foreach ($jcc_metals as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"<?php echo (int) ($jcc_row['metal_id'] ?? 0) === (int) $m['id'] ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="jcc-label" for="jccProduct">Product</label>
                                <select class="form-control form-control-sm" id="jccProduct">
                                    <option value="">Select</option>
                                    <?php foreach ($jcc_products as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>"<?php echo (int) ($jcc_row['product_id'] ?? 0) === (int) $p['id'] ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="jcc-label" for="jccCategory">Categories</label>
                                <div class="d-flex" style="gap:6px;">
                                    <select class="form-control form-control-sm" id="jccCategory">
                                        <option value="">Select</option>
                                        <?php foreach ($jcc_categories as $c): ?>
                                        <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) ($jcc_row['category_id'] ?? 0) === (int) $c['id'] ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="accounting-masters.php" class="jcc-cat-plus" title="Masters" target="_blank"><i class="feather icon-plus"></i></a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="jcc-label" for="jccTitle">Title <span class="req">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="jccTitle" value="<?php echo htmlspecialchars($jcc_row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="jcc-label" for="jccShortDesc">Short Desc. <span class="req">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="jccShortDesc" value="<?php echo htmlspecialchars($jcc_row['short_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-2 col-md-4 col-6 form-group">
                                <label class="jcc-label" for="jccBarcode">Barcode</label>
                                <input type="text" class="form-control form-control-sm" id="jccBarcode" value="<?php echo htmlspecialchars($jcc_row['barcode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 form-group">
                                <label class="jcc-label" for="jccDesignNo">Design No.</label>
                                <input type="text" class="form-control form-control-sm" id="jccDesignNo"
                                    value="<?php echo htmlspecialchars($jcc_row['design_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="JC-1"
                                    title="From Bill Series master for Jewellery Catalogue voucher type">
                                <div class="jcc-design-no-error" id="jccDesignNoError" role="alert"></div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 form-group">
                                <label class="jcc-label" for="jccSku">SKU</label>
                                <input type="text" class="form-control form-control-sm" id="jccSku" value="<?php echo htmlspecialchars($jcc_row['sku'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 form-group">
                                <label class="jcc-label" for="jccWeight">Weight <span class="req">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="jccWeight" inputmode="decimal" value="<?php echo htmlspecialchars($jcc_row['weight'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 form-group">
                                <label class="jcc-label" for="jccAmount">Amount <span class="req">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="jccAmount" inputmode="decimal" value="<?php echo htmlspecialchars($jcc_row['amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6 form-group">
                                <label class="jcc-label" for="jccPublishWebsite">Publish on Website</label>
                                <select class="form-control form-control-sm" id="jccPublishWebsite">
                                    <option value="1"<?php echo !empty($jcc_row['publish_on_website']) ? ' selected' : ''; ?>>Yes</option>
                                    <option value="0"<?php echo empty($jcc_row['publish_on_website']) ? ' selected' : ''; ?>>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 form-group mb-0">
                                <label class="jcc-label" for="jccFullDesc">Full Desc.</label>
                                <textarea class="form-control form-control-sm" id="jccFullDesc" rows="2"><?php echo htmlspecialchars($jcc_row['full_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="jcc-card">
                        <div class="jcc-bom-head">
                            <h3>Bill of Material</h3>
                            <label class="mb-0" style="font-size:0.8125rem;">
                                <input type="checkbox" id="jccFillDmd"<?php echo !empty($jcc_row['fill_dmd_gms_rate']) ? ' checked' : ''; ?>>
                                Fill DMD/GMS Rate
                            </label>
                        </div>
                        <button type="button" class="jcc-add-item" id="jccAddBomRow">
                            <i class="feather icon-gift"></i> Add Item (Shift + Q)
                        </button>
                        <div class="jcc-bom-table-wrap">
                            <table class="jcc-bom-table" id="jccBomTable">
                                <thead>
                                    <tr>
                                        <th style="width:64px;"></th>
                                        <th>Variants</th>
                                        <th>Barcode</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Gross Wt.</th>
                                        <th>Final Wt.</th>
                                        <th>Net Wt.</th>
                                        <th>Pure Wt</th>
                                        <th>Making</th>
                                        <th>Design No.</th>
                                        <th>Tax</th>
                                    </tr>
                                </thead>
                                <tbody id="jccBomBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="jcc-side">
                    <h3>Images</h3>
                    <div class="jcc-upload-zone" id="jccUploadZone">
                        <input type="file" id="jccImageInput" accept="image/*" multiple>
                        <i class="feather icon-upload" style="font-size:2rem;"></i>
                        <span>Upload</span>
                    </div>
                    <div class="jcc-img-preview" id="jccImgPreview"></div>
                </div>
            </div>
        </form>
        <div class="jcc-page-footer-spacer" aria-hidden="true"></div>
        <div class="jcc-page-footer-scroll" aria-hidden="true"><br><br><br><br><br><br><br><br><br><br></div>
    </div>
</div>

<div id="jccBomEditLoader" class="jcc-bom-edit-loader" aria-hidden="true" aria-live="polite">
    <div class="jcc-bom-edit-loader-box">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <span>Please wait…</span>
    </div>
</div>

<script>
window.JCC_INITIAL = <?php echo $jcc_row_json; ?>;
window.JCC_RETURN_URL = <?php echo json_encode($back_url, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
(function () {
    var state = {
        id: 0,
        images: [],
        bom: [],
        sale_order_id: 0,
        sale_order_item_id: 0,
        repair_order_id: 0,
        repair_order_item_id: 0
    };
    var designNoState = { duplicate: false, checkTimer: null };

    function getDesignNoEl() {
        return document.getElementById('jccDesignNo');
    }

    function setDesignNoError(msg) {
        var el = getDesignNoEl();
        var err = document.getElementById('jccDesignNoError');
        if (msg) {
            if (el) el.classList.add('is-invalid');
            if (err) {
                err.textContent = msg;
                err.style.display = 'block';
            }
            designNoState.duplicate = true;
        } else {
            if (el) el.classList.remove('is-invalid');
            if (err) {
                err.textContent = '';
                err.style.display = 'none';
            }
            designNoState.duplicate = false;
        }
    }

    function checkDesignNoRemote(designNo) {
        return fetch(
            'ajax/check-jewelry-catalogue-design-no.php?design_no='
                + encodeURIComponent(designNo)
                + '&exclude_id='
                + (state.id || 0),
            { credentials: 'same-origin' }
        )
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.exists) {
                    setDesignNoError(data.message || 'Design No. already exists.');
                    return false;
                }
                setDesignNoError('');
                return true;
            })
            .catch(function () { return true; });
    }

    function ensureDesignNoBeforeSave() {
        var dnEl = getDesignNoEl();
        var val = dnEl ? dnEl.value.trim() : '';
        if (val !== '') {
            return checkDesignNoRemote(val);
        }
        return fetch(
            'ajax/get-next-jewelry-catalogue-design-no.php?exclude_id=' + (state.id || 0),
            { credentials: 'same-origin' }
        )
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.design_no && dnEl) {
                    dnEl.value = data.design_no;
                    setDesignNoError('');
                    return true;
                }
                alert('Could not generate Design No.');
                return false;
            })
            .catch(function () {
                alert('Could not generate Design No.');
                return false;
            });
    }

    function bindDesignNoValidation() {
        var dnEl = getDesignNoEl();
        if (!dnEl) return;
        dnEl.addEventListener('blur', function () {
            var val = dnEl.value.trim();
            if (val === '') {
                setDesignNoError('');
                return;
            }
            checkDesignNoRemote(val);
        });
        dnEl.addEventListener('input', function () {
            if (designNoState.checkTimer) clearTimeout(designNoState.checkTimer);
            var val = dnEl.value.trim();
            if (val === '') {
                setDesignNoError('');
                return;
            }
            designNoState.checkTimer = setTimeout(function () {
                checkDesignNoRemote(val);
            }, 400);
        });
    }

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showJccBomEditLoader(show) {
        var el = document.getElementById('jccBomEditLoader');
        if (!el) return;
        el.classList.toggle('show', !!show);
        el.setAttribute('aria-hidden', show ? 'false' : 'true');
    }
    window.jccShowBomEditLoader = showJccBomEditLoader;

    function initFromServer() {
        var init = window.JCC_INITIAL || {};
        state.id = parseInt(init.id, 10) || 0;
        state.images = Array.isArray(init.images) ? init.images.slice() : [];
        state.bom = normalizeBomOnLoad(Array.isArray(init.bom) ? init.bom.slice() : []);
        state.sale_order_id = parseInt(init.sale_order_id, 10) || 0;
        state.sale_order_item_id = parseInt(init.sale_order_item_id, 10) || 0;
        state.repair_order_id = parseInt(init.repair_order_id, 10) || 0;
        state.repair_order_item_id = parseInt(init.repair_order_item_id, 10) || 0;
        document.getElementById('jccId').value = state.id;
        document.getElementById('jccSaleOrderId').value = state.sale_order_id;
        document.getElementById('jccSaleItemId').value = state.sale_order_item_id;
        document.getElementById('jccRepairOrderId').value = state.repair_order_id;
        document.getElementById('jccRepairItemId').value = state.repair_order_item_id;
        if (init.design_no) {
            var dnEl = document.getElementById('jccDesignNo');
            if (dnEl && !dnEl.value.trim()) {
                dnEl.value = init.design_no;
            }
        }
        renderImages();
        renderBom();
    }

    function renderImages() {
        var box = document.getElementById('jccImgPreview');
        if (!box) return;
        box.innerHTML = '';
        state.images.forEach(function (img, idx) {
            var url = img.url || img.path || '';
            if (!url) return;
            var wrap = document.createElement('div');
            wrap.className = 'jcc-img-wrap';
            wrap.innerHTML = '<img src="' + esc(url) + '" alt="" class="jcc-img-thumb">'
                + '<button type="button" class="jcc-img-remove" data-idx="' + idx + '" aria-label="Remove">&times;</button>';
            box.appendChild(wrap);
        });
        box.querySelectorAll('.jcc-img-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.getAttribute('data-idx'), 10);
                if (!isNaN(i)) {
                    state.images.splice(i, 1);
                    renderImages();
                }
            });
        });
    }

    function parseBomNum(v) {
        var n = parseFloat(String(v == null ? '' : v).replace(/,/g, ''));
        return isFinite(n) ? n : 0;
    }

    function fmtBomNum(n, dec) {
        dec = dec == null ? 3 : dec;
        if (!isFinite(n) || Math.abs(n) < 0.0000001) return '';
        return String(parseFloat(n.toFixed(dec)));
    }

    function mergeBomItems(items) {
        if (!items || !items.length) return null;
        if (items.length === 1) {
            var one = Object.assign({}, items[0]);
            if (!one.group_items && (one._modal || one.description)) {
                one.group_items = items.slice();
            }
            return one;
        }
        var descriptions = items.map(function (it) {
            return String(it.description || '').trim();
        }).filter(Boolean);
        var barcodes = items.map(function (it) {
            return String(it.barcode || '').trim();
        }).filter(Boolean);
        var variants = items.map(function (it) {
            return String(it.variants || '').trim();
        }).filter(Boolean);
        var first = items[0] || {};
        var merged = {
            variants: variants.length ? variants.join(' | ') : (first.variants || ''),
            barcode: barcodes.length > 1 ? barcodes.join(', ') : (barcodes[0] || first.barcode || ''),
            description: descriptions.length ? descriptions.join(' + ') : (items.length + ' items'),
            quantity: '0',
            gross_wt: '0',
            final_wt: '0',
            net_wt: '0',
            pure_wt: '0',
            making: '0',
            design_no: first.design_no || '',
            tax: '0',
            group_items: items.slice()
        };
        items.forEach(function (it) {
            merged.quantity = fmtBomNum(parseBomNum(merged.quantity) + parseBomNum(it.quantity), 2);
            merged.gross_wt = fmtBomNum(parseBomNum(merged.gross_wt) + parseBomNum(it.gross_wt));
            merged.final_wt = fmtBomNum(parseBomNum(merged.final_wt) + parseBomNum(it.final_wt));
            merged.net_wt = fmtBomNum(parseBomNum(merged.net_wt) + parseBomNum(it.net_wt));
            merged.pure_wt = fmtBomNum(parseBomNum(merged.pure_wt) + parseBomNum(it.pure_wt));
            merged.making = fmtBomNum(parseBomNum(merged.making) + parseBomNum(it.making), 2);
            merged.tax = fmtBomNum(parseBomNum(merged.tax) + parseBomNum(it.tax), 2);
        });
        return merged;
    }

    function normalizeBomOnLoad(bom) {
        if (!Array.isArray(bom) || bom.length <= 1) return bom || [];
        var hasMerged = bom.some(function (r) {
            return Array.isArray(r.group_items) && r.group_items.length > 0;
        });
        if (hasMerged) return bom;
        return [mergeBomItems(bom)];
    }

    function syncHeaderFromBom() {
        var totalWt = 0;
        var totalAmt = 0;
        state.bom.forEach(function (row) {
            totalWt += parseBomNum(row.final_wt || row.net_wt || row.gross_wt);
            var rowAmt = parseBomNum(row.making);
            if (rowAmt > 0) {
                totalAmt += rowAmt;
                return;
            }
            if (Array.isArray(row.group_items)) {
                row.group_items.forEach(function (gi) {
                    if (gi._modal && gi._modal.net_amt_tax != null) {
                        totalAmt += parseBomNum(gi._modal.net_amt_tax);
                    } else if (gi._modal && gi._modal.amount != null) {
                        totalAmt += parseBomNum(gi._modal.amount);
                    }
                });
            } else if (row._modal && row._modal.net_amt_tax != null) {
                totalAmt += parseBomNum(row._modal.net_amt_tax);
            } else if (row._modal && row._modal.amount != null) {
                totalAmt += parseBomNum(row._modal.amount);
            }
        });
        var wtEl = document.getElementById('jccWeight');
        var amtEl = document.getElementById('jccAmount');
        if (wtEl && totalWt > 0) {
            wtEl.value = fmtBomNum(totalWt);
        }
        if (amtEl && totalAmt > 0) {
            amtEl.value = fmtBomNum(totalAmt, 2);
        }
    }

    function bomCell(row, key) {
        var v = row[key] != null ? String(row[key]) : '';
        var isMergedDesc = key === 'description' && Array.isArray(row.group_items) && row.group_items.length > 1;
        var cls = 'jcc-bom-val' + (isMergedDesc ? ' jcc-bom-merged-desc' : '');
        var title = v !== '' ? ' title="' + esc(v) + '"' : '';
        return '<span class="' + cls + '"' + title + '>' + esc(v) + '</span>';
    }

    function renderBom() {
        var tbody = document.getElementById('jccBomBody');
        if (!tbody) return;
        if (!state.bom.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="jcc-bom-empty">No Rows To Show</td></tr>';
            return;
        }
        var html = '';
        state.bom.forEach(function (row, idx) {
            html += '<tr data-idx="' + idx + '">'
                + '<td class="jcc-bom-actions">'
                + '<button type="button" class="jcc-bom-act jcc-bom-edit" data-idx="' + idx + '" title="Edit item"><i class="feather icon-edit-2"></i></button>'
                + '<button type="button" class="jcc-bom-act jcc-bom-del" data-idx="' + idx + '" title="Delete item"><i class="feather icon-trash-2"></i></button>'
                + '</td>'
                + '<td>' + bomCell(row, 'variants') + '</td>'
                + '<td>' + bomCell(row, 'barcode') + '</td>'
                + '<td>' + bomCell(row, 'description') + '</td>'
                + '<td>' + bomCell(row, 'quantity') + '</td>'
                + '<td>' + bomCell(row, 'gross_wt') + '</td>'
                + '<td>' + bomCell(row, 'final_wt') + '</td>'
                + '<td>' + bomCell(row, 'net_wt') + '</td>'
                + '<td>' + bomCell(row, 'pure_wt') + '</td>'
                + '<td>' + bomCell(row, 'making') + '</td>'
                + '<td>' + bomCell(row, 'design_no') + '</td>'
                + '<td>' + bomCell(row, 'tax') + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
        if (typeof feather !== 'undefined' && feather.replace) {
            feather.replace({ scope: tbody });
        }
        tbody.querySelectorAll('.jcc-bom-edit').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var i = parseInt(btn.getAttribute('data-idx'), 10);
                if (isNaN(i)) return;
                if (!state.bom[i]) return;
                showJccBomEditLoader(true);
                setTimeout(function () {
                    try {
                        if (typeof window.jccOpenBomProductModalForEdit === 'function') {
                            window.jccOpenBomProductModalForEdit(state.bom[i], i);
                        } else {
                            openBomProductModal();
                        }
                    } finally {
                        showJccBomEditLoader(false);
                    }
                }, 40);
            });
        });
        tbody.querySelectorAll('.jcc-bom-del').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var i = parseInt(btn.getAttribute('data-idx'), 10);
                if (!isNaN(i)) {
                    state.bom.splice(i, 1);
                    renderBom();
                    syncHeaderFromBom();
                }
            });
        });
    }

    function syncBomFromDom() {
        /* BOM rows are read-only in the table; state.bom is updated via modal add/edit only. */
    }

    function bomItemFromModalBatch(it) {
        if (!it) return null;
        var row = {
            variants: it.variants || '',
            barcode: it.barcode || '',
            description: it.description || '',
            quantity: it.quantity != null ? String(it.quantity) : '',
            gross_wt: it.gross_wt != null ? String(it.gross_wt) : '',
            final_wt: it.final_wt != null ? String(it.final_wt) : '',
            net_wt: it.net_wt != null ? String(it.net_wt) : '',
            pure_wt: it.pure_wt != null ? String(it.pure_wt) : '',
            making: it.making != null ? String(it.making) : '',
            design_no: it.design_no || '',
            tax: it.tax != null ? String(it.tax) : '',
            group_items: Array.isArray(it.group_items) ? it.group_items : (it._modal ? [it] : [])
        };
        if (it._modal) row._modal = it._modal;
        return row;
    }

    function applyBomBatch(items, replaceIndex) {
        var batch = (items || []).filter(Boolean);
        if (batch.length > 1) {
            batch = [mergeBomItems(batch)];
        } else if (batch.length === 1) {
            batch = [mergeBomItems(batch)];
        }
        batch.forEach(function (it) {
            var row = bomItemFromModalBatch(it);
            if (!row) return;
            if (typeof replaceIndex === 'number' && replaceIndex >= 0 && replaceIndex < state.bom.length) {
                state.bom[replaceIndex] = row;
                replaceIndex = -1;
            } else {
                state.bom.push(row);
            }
        });
        renderBom();
        syncHeaderFromBom();
        if (batch.length && batch[0].description) {
            var shortEl = document.getElementById('jccShortDesc');
            var titleEl = document.getElementById('jccTitle');
            if (shortEl && !shortEl.value.trim()) shortEl.value = batch[0].description;
            if (titleEl && !titleEl.value.trim()) {
                titleEl.value = String(batch[0].description).split(' + ')[0].trim();
            }
        }
    }

    function appendBomItems(items) {
        applyBomBatch(items, -1);
    }

    function replaceBomItemsAt(index, items) {
        if (typeof index !== 'number' || index < 0) {
            appendBomItems(items);
            return;
        }
        applyBomBatch(items, index);
    }

    window.JCC_BOM_BRIDGE = {
        appendBomItems: appendBomItems,
        mergeBomItems: mergeBomItems,
        replaceBomItemsAt: replaceBomItemsAt
    };

    function openBomProductModal() {
        if (typeof window.jccOpenBomProductModal === 'function') {
            window.jccOpenBomProductModal();
        } else if (typeof window.openProductModal === 'function') {
            window.openProductModal();
        } else {
            alert('Product modal is not available. Please refresh the page.');
        }
    }

    function uploadImages(files) {
        if (!files || !files.length) return;
        Array.prototype.forEach.call(files, function (file) {
            var fd = new FormData();
            fd.append('image', file);
            fetch('ajax/upload-jewelry-catalogue-image.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        state.images.push({ path: data.path, url: data.url });
                        renderImages();
                    } else {
                        alert((data && data.message) || 'Upload failed.');
                    }
                })
                .catch(function () { alert('Upload failed.'); });
        });
    }

    function collectPayload() {
        syncBomFromDom();
        var imagePaths = state.images.map(function (img) {
            return { path: img.path || '', url: img.url || '' };
        });
        return {
            id: parseInt(document.getElementById('jccId').value, 10) || 0,
            metal_id: parseInt(document.getElementById('jccMetal').value, 10) || 0,
            product_id: parseInt(document.getElementById('jccProduct').value, 10) || 0,
            category_id: parseInt(document.getElementById('jccCategory').value, 10) || 0,
            title: document.getElementById('jccTitle').value.trim(),
            short_desc: document.getElementById('jccShortDesc').value.trim(),
            full_desc: document.getElementById('jccFullDesc').value,
            barcode: document.getElementById('jccBarcode').value.trim(),
            design_no: document.getElementById('jccDesignNo').value.trim(),
            sku: document.getElementById('jccSku').value.trim(),
            weight: document.getElementById('jccWeight').value.trim(),
            amount: document.getElementById('jccAmount').value.trim(),
            publish_on_website: parseInt(document.getElementById('jccPublishWebsite').value, 10) === 1 ? 1 : 0,
            fill_dmd_gms_rate: document.getElementById('jccFillDmd').checked ? 1 : 0,
            images: imagePaths,
            bom: state.bom,
            sale_order_id: parseInt(document.getElementById('jccSaleOrderId').value, 10) || 0,
            sale_order_item_id: parseInt(document.getElementById('jccSaleItemId').value, 10) || 0,
            repair_order_id: parseInt(document.getElementById('jccRepairOrderId').value, 10) || 0,
            repair_order_item_id: parseInt(document.getElementById('jccRepairItemId').value, 10) || 0
        };
    }

    function saveCatalogue() {
        ensureDesignNoBeforeSave().then(function (ok) {
            if (!ok || designNoState.duplicate) {
                var dn = getDesignNoEl();
                if (dn) dn.focus();
                return;
            }
            var payload = collectPayload();
            var wasNew = (parseInt(payload.id, 10) || 0) <= 0;
            var btn = document.getElementById('jccBtnSave');
            if (btn) btn.disabled = true;
            fetch('ajax/save-jewelry-catalogue.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (btn) btn.disabled = false;
                    if (!data || !data.success) {
                        var msg = (data && data.message) || 'Save failed.';
                        if (/design no/i.test(msg)) {
                            setDesignNoError(msg);
                            var dnEl = getDesignNoEl();
                            if (dnEl) dnEl.focus();
                        } else {
                            alert(msg);
                        }
                        return;
                    }
                    setDesignNoError('');
                    if (wasNew && data.id) {
                        var ret = window.JCC_RETURN_URL || 'jewelry-catalogue.php';
                        var sep = ret.indexOf('?') >= 0 ? '&' : '?';
                        window.location.href = ret + sep + 'catalogue_id=' + encodeURIComponent(String(data.id));
                        return;
                    }
                    alert(data.message || 'Saved.');
                    if (data.design_no) {
                        var dn = getDesignNoEl();
                        if (dn) dn.value = data.design_no;
                    }
                    setDesignNoError('');
                    if (data.id) {
                        document.getElementById('jccId').value = data.id;
                        state.id = data.id;
                        if (window.history && window.history.replaceState) {
                            var u = new URL(window.location.href);
                            u.searchParams.set('id', String(data.id));
                            u.searchParams.delete('order_id');
                            u.searchParams.delete('item_id');
                            u.searchParams.delete('order_kind');
                            window.history.replaceState({}, '', u.toString());
                        }
                    }
                })
                .catch(function () {
                    if (btn) btn.disabled = false;
                    alert('Save failed.');
                });
        });
    }

    document.getElementById('jccBtnSave').addEventListener('click', function (e) {
        e.preventDefault();
        saveCatalogue();
    });
    document.getElementById('jccBtnNew').addEventListener('click', function () {
        var ret = window.JCC_RETURN_URL || 'jewelry-catalogue.php';
        window.location.href = 'jewelry-catalogue-create.php?return=' + encodeURIComponent(ret);
    });
    document.getElementById('jccAddBomRow').addEventListener('click', function (e) {
        e.preventDefault();
        openBomProductModal();
    });
    document.getElementById('jccImageInput').addEventListener('change', function () {
        uploadImages(this.files);
        this.value = '';
    });
    document.addEventListener('keydown', function (e) {
        if (e.shiftKey && (e.key === 'Q' || e.key === 'q')) {
            var modal = document.getElementById('productSelectionModal');
            if (modal && modal.classList.contains('show')) return;
            e.preventDefault();
            openBomProductModal();
        }
    });

    var titleEl = document.getElementById('jccTitle');
    var shortEl = document.getElementById('jccShortDesc');
    var productEl = document.getElementById('jccProduct');
    if (productEl && titleEl) {
        productEl.addEventListener('change', function () {
            var opt = productEl.options[productEl.selectedIndex];
            if (!opt || !opt.text) return;
            if (!titleEl.value.trim()) titleEl.value = opt.text;
            if (shortEl && !shortEl.value.trim()) shortEl.value = opt.text;
        });
    }

    initFromServer();
    bindDesignNoValidation();
})();
</script>
<?php include __DIR__ . '/includes/jewelry_catalogue_product_modal_inc.php'; ?>
</body>
</html>
