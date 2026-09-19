<?php
/**
 * Optional: set $common_modal_include_product_selection_only = true before including this file
 * (e.g. stock journal) to output only the product selection modal and skip Add Image + Add Product blocks below
 * (those pages may define their own modals to avoid duplicate element IDs).
 */
require __DIR__ . '/common-modal-product-selection.php';
if (!empty($common_modal_include_product_selection_only)) {
    return;
}
?>

<!-- Add Image Modal (multiple images + one primary, same flow as reference) -->
<div class="modal fade" id="addImageModal" tabindex="-1" role="dialog" aria-labelledby="addImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #e2e8f0;">
                <h5 class="modal-title" id="addImageModalLabel">Add Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="addImageModalClose">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.25rem;">
                <div class="d-flex align-items-stretch" style="gap: 0.75rem;">
                    <!-- Primary image (large preview); click thumbnail to set as primary -->
                    <div id="addImagePreviewWrap" style="flex: 1; min-height: 180px; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                        <div id="addImagePreviewPlaceholder" class="text-center text-muted" style="padding: 1rem;">
                            <i class="feather icon-image" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.8rem;">NO PREVIEW AVAILABLE</span>
                        </div>
                        <img id="addImagePreviewImg" src="" alt="Primary" style="max-width: 100%; max-height: 200px; object-fit: contain; display: none; border-radius: 6px; cursor: default;">
                    </div>
                    <!-- Thumbnails: first slot = add, then one per image with X to remove -->
                    <div class="d-flex flex-column" style="gap: 0.5rem;">
                        <div id="addImageThumbnailsWrap" class="d-flex flex-wrap" style="gap: 0.5rem; max-width: 120px;">
                            <div id="addImageUploadZone" style="width: 70px; height: 70px; border: 2px dashed #94a3b8; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; cursor: pointer; transition: background 0.2s; flex-shrink: 0;">
                                <input type="file" id="addImageModalFileInput" accept="image/*" multiple style="display: none;">
                                <i class="feather icon-upload" style="font-size: 1.5rem; color: #64748b;"></i>
                            </div>
                            <!-- Thumbnail slots appended by JS (addImageRenderThumbnails) -->
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-2 mb-0">Click the upload area or use the camera below to add images. Click a thumbnail to set as primary.</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 0.75rem 1.25rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addImageModalCameraBtn" title="Select image(s)">
                    <i class="feather icon-camera" style="font-size: 1.1rem;"></i>
                </button>
                <div class="ml-auto">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-purple btn-sm" id="addImageModalSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Stack above #productSelectionModal (10600) so Add Image opens in front when camera is used from product selection */
#addImageModal.modal {
    z-index: 10700 !important;
}
body.modal-open:has(#addImageModal.show) .modal-backdrop {
    z-index: 10650 !important;
}
</style>

<?php
// Add Product modal: same Tax / Metal / Unit / Location / branch defaults as product-opening.php
require_once __DIR__ . '/auragold_product_opening_field_helpers.php';
require_once __DIR__ . '/auragold_product_add_form_shared_data.php';

$pc_default_hidden_cols = [
    'barcode', 'unit', 'sku', 'making', 'location', 'discount',
    'purity-group', 'purity-sale', 'purity-purchase',
    'wastage-group', 'wastage-sale', 'wastage-purchase',
    'wt-per-piece', 'serialized',
    'styles', 'cut', 'shape', 'color', 'clarity', 'sieve', 'size', 'stylecode',
];
$pc_col_hidden_attr = static function (string $colKey) use ($pc_default_hidden_cols): string {
    return in_array($colKey, $pc_default_hidden_cols, true) ? ' class="pc-col-always-hidden" style="display:none;"' : '';
};

$po_css = __DIR__ . '/../assets/css/product-add-form-po.css';
$po_js = __DIR__ . '/../assets/js/product-add-form-pc-tabs.js';
?>
<link rel="stylesheet" href="assets/css/product-add-form-po.css?v=<?php echo @filemtime($po_css) ?: time(); ?>">
<style>
#customerCreationModal.modal {
    z-index: 10900 !important;
}
body.modal-open:has(#productCreationModal.show):has(#customerCreationModal.show) .modal-backdrop:last-of-type {
    z-index: 10850 !important;
}
</style>
<!-- Right Side Product Creation Modal -->
<div class="modal fade right" id="productCreationModal" tabindex="-1" role="dialog" aria-labelledby="productCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-right modal-xl" role="document" style="max-width: 90%; width: 90%; margin: 0; height: 100vh;">
        <div class="modal-content" style="height: 100vh; border-radius: 0; border: none;">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none; padding: 1rem 1.5rem;">
                <h5 class="modal-title" id="productCreationModalLabel">Add Product</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: calc(100vh - 60px); overflow-y: auto;">
                <form id="productCreationForm" method="post" action="product-save.php" style="height: 100%;">
                    <div class="po-product-form-scroll">
                        <div class="po-modal-right-top">
                            <!-- Product Information -->
                            <div class="card-box product-details-form po-section-card">
                                <div class="po-section-head">
                                    <div class="po-section-head-left">
                                        <span class="po-section-icon"><i class="feather icon-box"></i></span>
                                        <div>
                                            <h3 class="po-section-title">Product Information</h3>
                                            <p class="po-section-sub">Add new product details and specifications</p>
                                        </div>
                                    </div>
                                    <div class="product-details-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearProductForm()">Clear</button>
                                        <button type="button" class="btn btn-primary btn-sm" id="productCreationSaveBtn" onclick="saveProduct(event)">Save</button>
                                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                                    </div>
                                </div>

                                <div class="form-row-custom form-row-product-top">
                                    <div class="form-group">
                                        <label>Product Name *</label>
                                        <input name="name" id="productName" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Vendor Name</label>
                                        <div class="select-with-add-btn">
                                            <select name="vendor_id" id="productVendor" class="form-control">
                                                <option value="">Select Vendor</option>
                                                <?php
                                                if (!empty($vendors) && is_array($vendors)) {
                                                    foreach ($vendors as $vendor) {
                                                        echo '<option value="' . (int) $vendor['id'] . '">' . htmlspecialchars($vendor['name']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <button type="button" class="po-field-add-btn cm-add-vendor" title="Add Vendor" aria-label="Add Vendor">
                                                <i class="feather icon-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row-custom form-row-product-second">
                                    <div class="form-group">
                                        <label>Article</label>
                                        <input name="article" id="productArticle" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Alternate Name</label>
                                        <input name="alternate_name" id="productAlternateName" class="form-control">
                                    </div>
                                </div>

                                <div class="form-row-custom">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <div class="select-with-add">
                                            <select name="category_id" id="productCategory" class="form-control">
                                                <option value="">Select Category</option>
                                                <?php
                                                if (!empty($categories) && is_array($categories)) {
                                                    foreach ($categories as $cat) {
                                                        echo '<option value="' . (int) $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <i class="feather icon-plus add-icon add-product-category-icon" title="Add Category"></i>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Branch <span style="color: red;">*</span></label>
                                        <div class="select-with-add">
                                            <div class="branch-tags" id="branchTagsContainer" data-required="true">
                                                <?php
                                                if (!empty($branches) && !empty($auragold_new_product_default_branch_id)) {
                                                    foreach ($branches as $branch) {
                                                        if ((int) $branch['id'] === (int) $auragold_new_product_default_branch_id) {
                                                            $branch_name = htmlspecialchars($branch['name']);
                                                            echo '<span class="branch-tag" data-branch-id="' . (int) $branch['id'] . '">' . $branch_name . ' <span class="remove-tag">×</span></span>';
                                                            echo '<input type="hidden" name="branch_ids[]" value="' . (int) $branch['id'] . '">';
                                                            break;
                                                        }
                                                    }
                                                } elseif (empty($branches)) {
                                                    echo '<span class="text-muted" style="font-size: 0.8rem;">No branches available</span>';
                                                }
                                                ?>
                                                <span class="add-branch-btn" title="Add branch"><i class="feather icon-plus"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="po-toggle-row">
                                    <div class="po-toggle-card">
                                        <div class="po-toggle-label">
                                            <strong>Show In Stock</strong>
                                            <span>Display product in stock management</span>
                                        </div>
                                        <label class="po-switch mb-0">
                                            <input type="checkbox" name="is_stock_item" id="productShowInStock" value="1" checked>
                                            <span class="po-switch-slider"></span>
                                        </label>
                                    </div>
                                    <div class="po-toggle-card">
                                        <div class="po-toggle-label">
                                            <strong>Barcode</strong>
                                            <span>Enable barcode for this product</span>
                                        </div>
                                        <label class="po-switch mb-0">
                                            <input type="checkbox" name="is_barcode" id="productIsBarcode" value="1" checked>
                                            <span class="po-switch-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Tax Configuration -->
                            <div class="card-box tax-table-wrapper po-section-card po-tax-card">
                                <div class="po-section-head">
                                    <div class="po-section-head-left">
                                        <span class="po-section-icon"><i class="feather icon-percent"></i></span>
                                        <div>
                                            <h3 class="po-section-title">Tax Configuration</h3>
                                            <p class="po-section-sub">Set applicable taxes for this product</p>
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tax</th>
                                            <th>Value</th>
                                            <th>Calculation Mo...</th>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php
if (!empty($tax_master_list)) {
    foreach ($tax_master_list as $t) {
        $val = $t['default_value'] ?? '';
        $mode = $t['default_calculation_mode'] ?? 'Product Amount';
?>
                                        <tr>
                                            <td><input type="checkbox" name="tax_enabled[<?= (int)$t['id'] ?>]" value="1"> <?= htmlspecialchars($t['name']) ?></td>
                                            <td><input type="number" name="tax_value[<?= (int)$t['id'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($val) ?>" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="tax_calculation_mode[<?= (int)$t['id'] ?>]" class="form-control form-control-sm" style="font-size: 0.75rem;">
<?php foreach ($calculation_modes as $mode_row) {
        $sel = ($mode_row['name'] == $mode) ? 'selected' : '';
        echo '<option value="'.htmlspecialchars($mode_row['name']).'" '.$sel.'>'.htmlspecialchars($mode_row['name']).'</option>';
} ?>
                                                </select>
                                            </td>
                                        </tr>
<?php
    }
} else {
?>
                                        <tr>
                                            <td><input type="checkbox" name="vat" id="productVAT" value="1"> VAT</td>
                                            <td><input type="number" name="vat_value" class="form-control form-control-sm" value="5" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="vat_calculation_mode" class="form-control form-control-sm" style="font-size: 0.75rem;">
                                                    <?php foreach ($calculation_modes as $mode) { $selected = ($mode['name'] == 'Product Amount') ? 'selected' : ''; echo '<option value="'.htmlspecialchars($mode['name']).'" '.$selected.'>'.htmlspecialchars($mode['name']).'</option>'; } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" name="tax_bah" id="productTAXBAH" value="1"> TAX BAH</td>
                                            <td><input type="number" name="tax_bah_value" class="form-control form-control-sm" value="10" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="tax_bah_calculation_mode" class="form-control form-control-sm" style="font-size: 0.75rem;">
                                                    <?php foreach ($calculation_modes as $mode) { $selected = ($mode['name'] == 'Product Amount') ? 'selected' : ''; echo '<option value="'.htmlspecialchars($mode['name']).'" '.$selected.'>'.htmlspecialchars($mode['name']).'</option>'; } ?>
                                                </select>
                                            </td>
                                        </tr>
<?php } ?>
                                    </tbody>
                                </table>
                                <div class="po-tax-total">
                                    <span>Total Tax (if all selected)</span>
                                    <strong id="productModalTotalTaxPct">0.00%</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Product Characteristics with metal tabs -->
                        <div class="card-box po-section-card po-characteristics-card">
                            <div class="pc-table-header-actions po-section-head">
                                <div class="po-section-head-left">
                                    <span class="po-pc-step-badge">2</span>
                                    <div>
                                        <h3 class="po-section-title mb-0">Product Characteristics</h3>
                                        <p class="po-section-sub mb-0">Select Product Type / Metal</p>
                                    </div>
                                </div>
                                <div style="position: relative; flex-shrink:0;">
                                    <i class="feather icon-settings gear-icon" id="productModalColumnSettingsBtn" title="Column Settings"></i>
                                    <div class="columns-dropdown" id="productModalColumnsDropdown">
                                        <div class="columns-dropdown-header">Columns</div>
                                        <div class="columns-dropdown-search">
                                            <input type="text" id="productModalColumnSearch" placeholder="Search columns...">
                                        </div>
                                        <div class="columns-dropdown-list" id="productModalColumnsList">
                                            <!-- Will be populated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="po-pc-tabs-shell">
                                <p class="po-pc-tabs-label d-none d-md-block">Choose a metal tab to enter barcode, HSN, karat and other details.</p>
                                <div class="po-pc-tabs-nav" id="poPcTabsNav"></div>
                                <div class="po-pc-table-host pc-wrapper pc-tabs-mode">
                                    <div class="po-pc-details-box">
                                        <div class="pc-scroll">
                                            <table class="table table-bordered pc-table">
                                                <thead id="pcTableHead">
                                                    <tr id="headerRow1">
                                                        <th rowspan="2" class="draggable pc-col-no-drag" data-col="check">✔</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="metal">Metal</th>
                                                        <th colspan="2" class="text-center pc-col-drag" data-col="barcode-group">Barcode</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="hsn">HSN</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="unit"<?= $pc_col_hidden_attr('unit') ?>>Unit</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="sku"<?= $pc_col_hidden_attr('sku') ?>>SKU</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="making"<?= $pc_col_hidden_attr('making') ?>>Making On</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="diamond">Diamond Category</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="location"<?= $pc_col_hidden_attr('location') ?>>Location</th>
                                                        <th rowspan="2" class="draggable carat-header pc-col-drag" data-col="carat">Karat</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="discount"<?= $pc_col_hidden_attr('discount') ?>>Discount</th>
                                                        <th colspan="2" class="text-center pc-col-drag" data-col="purity-group"<?= $pc_col_hidden_attr('purity-group') ?>>Purity</th>
                                                        <th colspan="2" class="text-center pc-col-drag" data-col="wastage-group"<?= $pc_col_hidden_attr('wastage-group') ?>>Wastage</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="wt-per-piece"<?= $pc_col_hidden_attr('wt-per-piece') ?>>Wt / Piece</th>
                                                        <th colspan="6" class="text-center d-none pc-col-drag" data-col="opening">Opening</th>
                                                        <th rowspan="2" class="draggable pc-col-drag" data-col="serialized"<?= $pc_col_hidden_attr('serialized') ?>>Serialized</th>
                                                        <th colspan="7" class="text-center pc-col-drag" data-col="styles"<?= $pc_col_hidden_attr('styles') ?>>Basic Styles</th>
                                                    </tr>
                                                    <tr id="headerRow2">
                                                        <th data-col="barcode-digits">Digits</th>
                                                        <th data-col="barcode-prefix">Prefix</th>
                                                        <th data-col="barcode"<?= $pc_col_hidden_attr('barcode') ?>>Barcode</th>
                                                        <th data-col="purity-sale"<?= $pc_col_hidden_attr('purity-sale') ?>>Sale</th>
                                                        <th data-col="purity-purchase"<?= $pc_col_hidden_attr('purity-purchase') ?>>Purchase</th>
                                                        <th data-col="wastage-sale"<?= $pc_col_hidden_attr('wastage-sale') ?>>Sale</th>
                                                        <th data-col="wastage-purchase"<?= $pc_col_hidden_attr('wastage-purchase') ?>>Purchase</th>
                                                        <th class="d-none" data-col="opening-weight">Weight</th>
                                                        <th class="d-none" data-col="opening-purity">Purity</th>
                                                        <th class="d-none" data-col="opening-qty">Qty</th>
                                                        <th class="d-none" data-col="opening-finalwt">Final Wt</th>
                                                        <th class="d-none" data-col="opening-rate">Rate</th>
                                                        <th class="d-none" data-col="opening-value">Value</th>
                                                        <th data-col="cut"<?= $pc_col_hidden_attr('cut') ?>>Cut</th>
                                                        <th data-col="shape"<?= $pc_col_hidden_attr('shape') ?>>Shape</th>
                                                        <th data-col="color"<?= $pc_col_hidden_attr('color') ?>>Color</th>
                                                        <th data-col="clarity"<?= $pc_col_hidden_attr('clarity') ?>>Clarity</th>
                                                        <th data-col="sieve"<?= $pc_col_hidden_attr('sieve') ?>>Sieve</th>
                                                        <th data-col="size"<?= $pc_col_hidden_attr('size') ?>>Size</th>
                                                        <th data-col="stylecode"<?= $pc_col_hidden_attr('stylecode') ?>>Style Code</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="productCharacteristicsBody">
                                                    <?php require __DIR__ . '/product-open-modal-characteristics-tbody.php'; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$ab_json = !empty($branches) && is_array($branches) ? $branches : [];
?>
<script>
window.auragoldDefaultBranchId = <?php echo isset($auragold_new_product_default_branch_id) ? (int) $auragold_new_product_default_branch_id : 0; ?>;
window.auragoldDefaultBranchName = <?php echo json_encode((string)($auragold_new_product_default_branch_name ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.auragoldProductModalBranches = <?php echo json_encode($ab_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.auragoldProductModalAlwaysHiddenCols = <?php echo json_encode(array_values($pc_default_hidden_cols), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/product-add-form-pc-tabs.js?v=<?php echo @filemtime($po_js) ?: time(); ?>"></script>

<?php $GLOBALS['auragold_common_modal_payments_included'] = true; ?>
<?php
if (!isset($bank_accounts) || !is_array($bank_accounts)) {
    $bank_accounts = [];
}
if (!isset($credit_cards) || !is_array($credit_cards)) {
    $credit_cards = [];
    if (isset($conn) && $conn instanceof mysqli) {
        require_once __DIR__ . '/auragold_credit_card_schema.php';
        if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
            auragold_ensure_branch_id_on_settings_tables($conn);
        }
        $cc_branch_id = 0;
        if (function_exists('auragold_settings_branch_id')) {
            $cc_branch_id = (int) auragold_settings_branch_id();
        }
        if ($cc_branch_id <= 0 && !empty($auragold_working_branch_id)) {
            $cc_branch_id = (int) $auragold_working_branch_id;
        }
        if (function_exists('auragold_get_credit_cards')) {
            $credit_cards = auragold_get_credit_cards($conn, $cc_branch_id);
        }
    }
}
?>
<!-- Payment Modals -->
<!-- Cash Payment Modal -->
<div class="modal fade" id="cashPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Cash Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="cashDepositInto">
                        <option value="Cash">Cash</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="text" class="form-control auragold-payment-amount-input" id="cashAmount" value="0.00" inputmode="decimal" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cash')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Bank Payment Modal -->
<div class="modal fade" id="bankPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Bank Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into <span class="text-danger" title="Required">*</span></label>
                    <select class="form-control" id="bankDepositInto" name="bank_deposit_into" required aria-required="true">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="bankTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="text" class="form-control auragold-payment-amount-input" id="bankAmount" value="0.00" inputmode="decimal" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('bank')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Cheque Payment Modal -->
<div class="modal fade" id="chequePaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Cheque Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="chequeDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="chequeTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="text" class="form-control auragold-payment-amount-input" id="chequeAmount" value="0.00" inputmode="decimal" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Cheque Dt.</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="chequeDate" value="<?php echo date('Y-m-d'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-sm" type="button" onclick="document.getElementById('chequeDate').value = '<?php echo date('Y-m-d'); ?>'" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cheque')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- UPI Payment Modal -->
<div class="modal fade" id="upiPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">UPI Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="upiDepositInto">
                        <option value="">Select Bank</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['name']); ?>"><?php echo htmlspecialchars($bank['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="upiTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="text" class="form-control auragold-payment-amount-input" id="upiAmount" value="0.00" inputmode="decimal" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('upi')">Save</button>
            </div>
        </div>
    </div>
</div>

<?php if (empty($auragold_skip_customer_ledger_modal)) { include __DIR__ . '/customer-ledger-modal.php'; } ?>

<!-- Category Creation Modal -->
<div class="modal fade" id="categoryCreationModal" tabindex="-1" role="dialog" aria-labelledby="categoryCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title" id="categoryCreationModalLabel">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <form id="categoryCreationForm">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Short Code</label>
                        <input type="text" class="form-control" id="categoryShortCode" name="short_code" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Parent Category</label>
                        <select class="form-control" id="categoryParentId" name="parent_id">
                            <option value="0">None</option>
                            <?php
                            if (!empty($categories)) {
                                foreach ($categories as $cat) {
                                    echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min. Qty.</label>
                                <input type="text" class="form-control" id="categoryMinQty" name="min_qty" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max. Qty.</label>
                                <input type="text" class="form-control" id="categoryMaxQty" name="max_qty" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min. Wt.</label>
                                <input type="text" class="form-control" id="categoryMinWt" name="min_wt" step="0.001" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max. Wt.</label>
                                <input type="text" class="form-control" id="categoryMaxWt" name="max_wt" step="0.001" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="categoryIsActive" name="is_active" checked>
                            <label class="form-check-label" for="categoryIsActive">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveCategory()" style="background: #11294b; border: none;">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Card Payment Modal -->
<div class="modal fade" id="cardPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Card Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Deposit Into</label>
                    <select class="form-control" id="cardDepositInto">
                        <option value="">Select Account</option>
                        <?php foreach ($credit_cards as $card) :
                            $card_name = trim((string) ($card['name'] ?? ''));
                            if ($card_name === '') {
                                continue;
                            }
                            $is_default = !empty($card['is_default']);
                            ?>
                        <option value="<?php echo htmlspecialchars($card_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_default ? ' selected' : ''; ?>><?php echo htmlspecialchars($card_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trans No.</label>
                    <input type="text" class="form-control" id="cardTransNo" placeholder="Transaction Number">
                </div>
                <div class="form-group">
                    <label>Card No.</label>
                    <input type="text" class="form-control" id="cardNumber" placeholder="Card Number">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="text" class="form-control auragold-payment-amount-input" id="cardAmount" value="0.00" inputmode="decimal" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('card')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Metal Exchange Payment Modal -->
<div class="modal fade" id="metalExchangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">M. Exch. Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Metal</label>
                            <select class="form-control" id="metalExchangeMetal">
                                <option value="">Select Metal</option>
                                <?php foreach($metals as $metal): ?>
                                <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2" style="position: relative;">
                            <label>Product</label>
                            <input type="text" class="form-control" id="metalExchangeProductInput" placeholder="Type product name to search..." autocomplete="off">
                            <input type="hidden" id="metalExchangeProductId" value="">
                            <div id="metalExchangeProductList" style="display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 1055; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Gross Wt</label>
                            <input type="text" class="form-control" id="metalExchangeGrossWt" value="0" step="0.001">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Purity / Karat</label>
                            <input type="text" class="form-control" id="metalExchangePurity" value="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Purity Wt.</label>
                            <input type="text" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Quantity</label>
                            <input type="text" class="form-control" id="metalExchangeQty" value="1" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Rate</label>
                            <input type="text" class="form-control" id="metalExchangeRate" value="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Amount</label>
                            <input type="text" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="form-group mb-2">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="metalExchangeItemCode" placeholder="Item Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('metal-exchange')">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Scrap Payment Modal -->
<div class="modal fade" id="scrapPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title">Scrap Payment</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Metal Type</label>
                            <select class="form-control" id="scrapMetal">
                                <option value="">Select Metal</option>
                                <?php if (!empty($metals) && is_array($metals)) { foreach ($metals as $m) { ?>
                                <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['display_name'] ?? $m['system_name'] ?? ''); ?></option>
                                <?php } } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gross Wt</label>
                            <input type="text" class="form-control" id="scrapGrossWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Net Wt.</label>
                            <input type="text" class="form-control" id="scrapNetWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Rate</label>
                            <input type="text" class="form-control" id="scrapRate" value="0" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" style="position: relative;">
                            <label>Product</label>
                            <input type="text" class="form-control" id="scrapProductInput" placeholder="Type product name to search..." autocomplete="off">
                            <input type="hidden" id="scrapProductId" value="">
                            <div id="scrapProductList" style="display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 1000; max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                        </div>
                        <div class="form-group">
                            <label>Less Wt.</label>
                            <input type="text" class="form-control" id="scrapLessWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Purity / Karat</label>
                            <input type="text" class="form-control" id="scrapPurity" value="1" step="0.01" placeholder="From product when selected">
                        </div>
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="text" class="form-control" id="scrapAmount" value="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="text" class="form-control" id="scrapQty" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Stone Wt.</label>
                            <input type="text" class="form-control" id="scrapStoneWt" value="0" step="0.001" placeholder="Deduct from weight">
                        </div>
                        <div class="form-group">
                            <label>Purity Wt.</label>
                            <input type="text" class="form-control" id="scrapPurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="scrapItemCode" placeholder="Item Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">CLEAR</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('scrap')">SAVE</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Payment modals above company-header (1300) and product-selection backdrop (10550) */
#cashPaymentModal.modal,
#bankPaymentModal.modal,
#chequePaymentModal.modal,
#upiPaymentModal.modal,
#cardPaymentModal.modal,
#metalExchangeModal.modal,
#scrapPaymentModal.modal {
    z-index: 10800 !important;
}
body.modal-open:has(#cashPaymentModal.show) .modal-backdrop,
body.modal-open:has(#bankPaymentModal.show) .modal-backdrop,
body.modal-open:has(#chequePaymentModal.show) .modal-backdrop,
body.modal-open:has(#upiPaymentModal.show) .modal-backdrop,
body.modal-open:has(#cardPaymentModal.show) .modal-backdrop,
body.modal-open:has(#metalExchangeModal.show) .modal-backdrop,
body.modal-open:has(#scrapPaymentModal.show) .modal-backdrop {
    z-index: 10750 !important;
}
#cashPaymentModal .auragold-payment-amount-input,
#bankPaymentModal .auragold-payment-amount-input,
#chequePaymentModal .auragold-payment-amount-input,
#upiPaymentModal .auragold-payment-amount-input,
#cardPaymentModal .auragold-payment-amount-input,
#metalExchangeModal #metalExchangeAmount,
#scrapPaymentModal #scrapAmount {
    pointer-events: auto !important;
    background-color: #fff !important;
    border: 1px solid #ced4da !important;
    cursor: text;
}
</style>

<script>
(function () {
    if (window.__auragoldPaymentModalFocusInited) return;
    window.__auragoldPaymentModalFocusInited = true;
    var amountFieldByModal = {
        cashPaymentModal: 'cashAmount',
        bankPaymentModal: 'bankAmount',
        chequePaymentModal: 'chequeAmount',
        upiPaymentModal: 'upiAmount',
        cardPaymentModal: 'cardAmount',
        metalExchangeModal: 'metalExchangeAmount',
        scrapPaymentModal: 'scrapAmount'
    };
    function focusPaymentAmount(modalId) {
        var fieldId = amountFieldByModal[modalId];
        if (!fieldId) return;
        var el = document.getElementById(fieldId);
        if (!el) return;
        el.removeAttribute('readonly');
        el.disabled = false;
        setTimeout(function () {
            try {
                el.focus();
                if (typeof el.select === 'function') el.select();
            } catch (e) {}
        }, 80);
    }
    if (typeof jQuery !== 'undefined' && jQuery.fn.on) {
        jQuery(document).on(
            'shown.bs.modal',
            '#cashPaymentModal,#bankPaymentModal,#chequePaymentModal,#upiPaymentModal,#cardPaymentModal,#metalExchangeModal,#scrapPaymentModal',
            function () { focusPaymentAmount(this.id); }
        );
    }
})();
(function () {
    if (window.__metalExchangeProductSearchInited) return;
    window.__metalExchangeProductSearchInited = true;
    var _meSync = false;
    function mePurityFactor(purity) {
        var p = parseFloat(purity) || 0;
        return (p > 0 && p <= 1) ? p : (p / 100);
    }
    /** Recompute purity wt from gross × purity only. */
    function meRefreshPurityWt() {
        var grossEl = document.getElementById('metalExchangeGrossWt');
        var purEl = document.getElementById('metalExchangePurity');
        var pwEl = document.getElementById('metalExchangePurityWt');
        if (!grossEl || !pwEl) return 0;
        var gross = parseFloat(grossEl.value) || 0;
        var purityWt = gross * mePurityFactor(purEl && purEl.value);
        pwEl.value = purityWt.toFixed(3);
        return purityWt;
    }
    /** Rate (or weight/qty) changed → Amount = purityWt × rate × qty */
    function meSyncAmountFromRate() {
        if (_meSync) return;
        _meSync = true;
        try {
            var pw = meRefreshPurityWt();
            var rateEl = document.getElementById('metalExchangeRate');
            var qtyEl = document.getElementById('metalExchangeQty');
            var amtEl = document.getElementById('metalExchangeAmount');
            if (!amtEl) return;
            var rate = parseFloat(rateEl && rateEl.value) || 0;
            var qty = parseFloat(qtyEl && qtyEl.value) || 0;
            var q = qty > 0 ? qty : 1;
            amtEl.value = (pw * rate * q).toFixed(2);
        } finally {
            _meSync = false;
        }
    }
    /** Amount changed → Rate = amount / (purityWt × qty) */
    function meSyncRateFromAmount() {
        if (_meSync) return;
        _meSync = true;
        try {
            var pw = meRefreshPurityWt();
            var qtyEl = document.getElementById('metalExchangeQty');
            var rateEl = document.getElementById('metalExchangeRate');
            var amtEl = document.getElementById('metalExchangeAmount');
            if (!rateEl || !amtEl) return;
            var amt = parseFloat(amtEl.value) || 0;
            var qty = parseFloat(qtyEl && qtyEl.value) || 0;
            var q = qty > 0 ? qty : 1;
            var base = pw * q;
            if (base > 0) {
                rateEl.value = (amt / base).toFixed(6);
            }
        } finally {
            _meSync = false;
        }
    }
    function updateMetalExchangeCalculations() {
        meSyncAmountFromRate();
    }
    window.updateMetalExchangeCalculations = updateMetalExchangeCalculations;
    function initMetalExchangeProductSearch() {
        var metalEl = document.getElementById('metalExchangeMetal');
        var inputEl = document.getElementById('metalExchangeProductInput');
        var idEl = document.getElementById('metalExchangeProductId');
        var listEl = document.getElementById('metalExchangeProductList');
        if (!metalEl || !inputEl || !idEl || !listEl) return;
        var tmr;
        var rateEl = document.getElementById('metalExchangeRate');
        var purEl = document.getElementById('metalExchangePurity');
        function showList(products) {
            listEl.innerHTML = '';
            listEl.style.display = 'block';
            if (!products || !products.length) {
                listEl.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                return;
            }
            products.forEach(function (p) {
                var div = document.createElement('div');
                div.className = 'p-2 border-bottom';
                div.style.cursor = 'pointer';
                div.style.fontSize = '0.9rem';
                div.onmouseover = function () { this.style.background = '#f1f5f9'; };
                div.onmouseout = function () { this.style.background = ''; };
                div.textContent = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                div.addEventListener('click', function () {
                    inputEl.value = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                    idEl.value = (p.characteristic_id || p.id) || '';
                    if (rateEl && p.rate != null && p.rate !== '') rateEl.value = p.rate;
                    if (purEl && p.opening_purity != null && p.opening_purity !== '') purEl.value = p.opening_purity;
                    var sku = (p.sku_code || p.barcode || '');
                    var ic = document.getElementById('metalExchangeItemCode');
                    if (ic && sku) ic.value = sku;
                    listEl.style.display = 'none';
                    listEl.innerHTML = '';
                    meSyncAmountFromRate();
                });
                listEl.appendChild(div);
            });
        }
        function search() {
            var mid = parseInt(metalEl.value, 10) || 0;
            var q = (inputEl.value || '').trim();
            if (!mid) {
                listEl.innerHTML = '<div class="p-2 text-muted small">Select metal first</div>';
                listEl.style.display = 'block';
                return;
            }
            listEl.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            listEl.style.display = 'block';
            var url = 'ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(mid) + (q ? '&search=' + encodeURIComponent(q) : '');
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    showList(data.success && data.products ? data.products : []);
                })
                .catch(function () {
                    listEl.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                });
        }
        inputEl.addEventListener('input', function () {
            clearTimeout(tmr);
            idEl.value = '';
            tmr = setTimeout(search, 300);
        });
        inputEl.addEventListener('focus', function () {
            if (metalEl.value) search();
        });
        metalEl.addEventListener('change', function () {
            inputEl.value = '';
            idEl.value = '';
            listEl.style.display = 'none';
            listEl.innerHTML = '';
        });
        document.addEventListener('click', function (e) {
            if (listEl.style.display === 'block' && !listEl.contains(e.target) && e.target !== inputEl) {
                listEl.style.display = 'none';
            }
        });
        ['metalExchangeGrossWt', 'metalExchangePurity', 'metalExchangeRate', 'metalExchangeQty'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', meSyncAmountFromRate);
                el.addEventListener('change', meSyncAmountFromRate);
            }
        });
        var amtIn = document.getElementById('metalExchangeAmount');
        if (amtIn) {
            amtIn.addEventListener('input', meSyncRateFromAmount);
            amtIn.addEventListener('change', meSyncRateFromAmount);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMetalExchangeProductSearch);
    } else {
        initMetalExchangeProductSearch();
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="assets/js/product-creation-vendor.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/product-creation-vendor.js'); ?>"></script>
<script src="assets/js/product-name-barcode-prefix.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/product-name-barcode-prefix.js'); ?>"></script>
<script src="assets/js/product-characteristics-column-drag.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/product-characteristics-column-drag.js'); ?>"></script>
<?php if (empty($auragold_skip_customer_ledger_modal)) { ?>
<script src="js/customer-ledger-address.js"></script>
<?php } ?>
