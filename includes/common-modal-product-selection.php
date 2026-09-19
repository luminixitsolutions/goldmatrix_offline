<?php
/** Cut / Color / Shape / Clarity / Seive / Size masters for modal spec dropdowns (all voucher pages). */
if (empty($GLOBALS['auragold_common_modal_spec_masters_loaded'])) {
    if (isset($conn) && $conn) {
        require_once __DIR__ . '/auragold_product_modal_spec_masters.php';
        $GLOBALS['auragold_common_modal_spec_masters_loaded'] = true;
    }
}
/**
 * Product selection modal markup. Included from includes/common-modal.php and other screens (e.g. stock journal).
 * Optional flags (all have defaults; set only what you need before include):
 *   $common_modal_show_images_column, $common_modal_show_checkbox_column, $common_modal_show_checkbox_in_settings
 *   $common_modal_product_footer_mode = 'voucher'|'stock_journal'
 *   $common_modal_show_add_product_in_header, $common_modal_show_add_product_icon
 *   $common_modal_add_row_btn_id, $common_modal_add_row_btn_class
 *   $common_modal_table_settings_btn_id, $common_modal_table_settings_dropdown_id, $common_modal_table_settings_dropdown_class
 *   $common_modal_omit_table_settings_search_id, $common_modal_table_settings_search_extra_class
 *   $common_modal_modal_body_attr, $common_modal_pl_table_class_extra, $common_modal_pl_table_style
 *   $common_modal_show_excel_import — show Import dropdown (Import + Sample) beside Add Product (only honored on sale-order.php and purchase-invoice.php)
 *   $common_modal_excel_sample_href — download URL for Sample menu item
 *   $common_modal_excel_import_file_id, $common_modal_excel_import_trigger_class — optional element ids/classes
 *   $common_modal_show_group_single_item_checkbox — show "Group Single Item" beside UnFix (sale invoice Gold/Silver)
 */
if (!isset($common_modal_show_images_column)) {
    $common_modal_show_images_column = false;
}
if (!isset($common_modal_show_checkbox_column)) {
    $common_modal_show_checkbox_column = true;
}
if (!isset($common_modal_show_checkbox_in_settings)) {
    $common_modal_show_checkbox_in_settings = true;
}
if (!isset($common_modal_product_footer_mode)) {
    $common_modal_product_footer_mode = 'voucher';
}
if (!isset($common_modal_show_add_product_in_header)) {
    $common_modal_show_add_product_in_header = true;
}
if (!isset($common_modal_show_add_product_icon)) {
    $common_modal_show_add_product_icon = true;
}
if (!isset($common_modal_add_row_btn_id)) {
    $common_modal_add_row_btn_id = 'addProductRowBtn';
}
if (!isset($common_modal_add_row_btn_class)) {
    $common_modal_add_row_btn_class = 'table-settings-btn';
}
if (!isset($common_modal_table_settings_btn_id)) {
    $common_modal_table_settings_btn_id = 'modalTableSettingsBtn';
}
if (!isset($common_modal_table_settings_dropdown_id)) {
    $common_modal_table_settings_dropdown_id = 'modalTableSettingsDropdown';
}
if (!isset($common_modal_table_settings_dropdown_class)) {
    $common_modal_table_settings_dropdown_class = 'table-settings-dropdown';
}
if (!isset($common_modal_modal_body_attr)) {
    $common_modal_modal_body_attr = '';
}
if (!isset($common_modal_pl_table_class_extra)) {
    $common_modal_pl_table_class_extra = '';
}
if (!isset($common_modal_pl_table_style)) {
    $common_modal_pl_table_style = 'font-size: 0.75rem;';
}
if (!isset($common_modal_omit_table_settings_search_id)) {
    $common_modal_omit_table_settings_search_id = false;
}
if (!isset($common_modal_table_settings_search_extra_class)) {
    $common_modal_table_settings_search_extra_class = '';
}
if (!isset($common_modal_show_excel_import)) {
    $common_modal_show_excel_import = false;
}
/* Excel import UI is only allowed on these screens (even if another page mistakenly sets the flag). */
$__auragoldExcelImportScript = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
$__auragoldExcelImportAllowedPages = ['sale-order.php', 'purchase-invoice.php'];
if (!empty($common_modal_show_excel_import) && !in_array($__auragoldExcelImportScript, $__auragoldExcelImportAllowedPages, true)) {
    $common_modal_show_excel_import = false;
}
unset($__auragoldExcelImportScript, $__auragoldExcelImportAllowedPages);
if (!isset($common_modal_excel_sample_href)) {
    $common_modal_excel_sample_href = 'ajax/download-stock-journal-excel-sample.php?voucher=sale_order';
}
if (!isset($common_modal_excel_import_file_id)) {
    $common_modal_excel_import_file_id = 'productModalExcelImportFile';
}
if (!isset($common_modal_excel_import_trigger_class)) {
    $common_modal_excel_import_trigger_class = 'js-product-modal-excel-import-trigger';
}
if (!isset($common_modal_show_group_single_item_checkbox)) {
    $common_modal_show_group_single_item_checkbox = false;
}
$common_modal_empty_row_colspan = !empty($common_modal_show_images_column) ? 104 : 103;
if (empty($common_modal_show_checkbox_column)) {
    $common_modal_empty_row_colspan--;
}
?>
<!-- Product Selection Modal -->
<div class="modal fade" id="productSelectionModal" tabindex="-1" role="dialog" aria-labelledby="productSelectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl product-selection-modal-dialog" role="document">
        <div class="modal-content">
            <div class="product-modal-mobile-header d-md-none">
                <button type="button" class="close product-modal-mobile-back" aria-label="Back">
                    <i class="feather icon-chevron-left"></i>
                </button>
                <h5 id="productSelectionModalLabel">Add Item</h5>
            </div>
            <?php if (!empty($common_modal_show_excel_import)): ?>
            <div id="productModalExcelImportLoader" class="product-modal-excel-import-loader" aria-hidden="true">
                <div class="product-modal-excel-import-loader__panel">
                    <div class="product-modal-excel-import-loader__spinner" aria-hidden="true"></div>
                    <p class="product-modal-excel-import-loader__text">Uploading file… Please wait.</p>
                </div>
            </div>
            <?php endif; ?>
            <!-- <div class="modal-header" style="background: #ffffff; border-bottom: 2px solid #e2e8f0; padding: 1rem;">
                <div style="width: 100%; position: relative;">
                    <input type="text" class="form-control form-control-lg" id="modalProductSearchInput" placeholder="Enter your item" style="border: 2px solid #c5a864; border-radius: 6px; padding-right: 40px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.5rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div> -->
            <div class="modal-body"<?php echo $common_modal_modal_body_attr; ?>>
                <!-- Category Tabs -->
                <div class="product-category-tabs">
                    <?php 
                    $first_metal = true;
                    foreach($metals as $metal): 
                        $tab_class = $first_metal ? 'active' : '';
                        $tab_id = 'modal-tab-' . strtolower(str_replace([' ', '&'], ['-', ''], $metal['display_name']));
                    ?>
                    <button type="button" class="category-tab-btn <?php echo $tab_class; ?>" data-metal-id="<?php echo $metal['id']; ?>" data-metal-name="<?php echo htmlspecialchars($metal['display_name']); ?>" id="<?php echo $tab_id; ?>">
                        <?php echo htmlspecialchars($metal['display_name']); ?>
                    </button>
                    <?php 
                    $first_metal = false;
                    endforeach; 
                    ?>
                    <!-- <button type="button" class="btn btn-sm btn-purple ml-auto" style="margin-left: auto;">
                        + More >>
                    </button> -->
                </div>
                
                <!-- Diamond Category filter (visible only on Diamond & Stones tab) -->
                <div id="modalDiamondCategoryFilterRow" class="mb-2" style="display: none;">
                    <label class="mr-2" style="font-size: 0.85rem;">Filter by Diamond Category:</label>
                    <select id="modalDiamondCategoryFilter" class="form-control form-control-sm" style="width: auto; display: inline-block; min-width: 180px;">
                        <option value="">All categories</option>
                        <option value="Diamonds">Diamonds</option>
                        <option value="GemStones">GemStones</option>
                        <option value="Jewellery">Jewellery</option>
                    </select>
                </div>
                
                <!-- Item Entry Fields Above Table -->
                <div class="row mb-3 product-modal-entry-row">
                    <div class="col-6 col-md-2">
                        <div class="form-group mb-2">
                            <label>Barcode</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductBarcode" placeholder="Scan or enter" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-sm" id="modalProductBarcodeFetchBtn" title="Fetch product by barcode" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem;">
                                        <i class="feather icon-image"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="form-group mb-2">
                            <label>Code</label>
                            <input type="text" class="form-control form-control-sm" id="modalProductCode" placeholder="Code">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="form-group mb-2">
                            <label for="modalProductDesignNo">Des. No.</label>
                            <select class="form-control form-control-sm" id="modalProductDesignNo" title="Jewellery catalogue design number">
                                <option value="">Select design no</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-md-1">
                        <div class="form-group mb-2">
                            <label>Qty</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductQty" value="" placeholder="Qty" min="1" step="0.01">
                                <div class="input-group-append">
                                    <button class="btn btn-sm" type="button" id="modalProductQtyApplyBtn" title="Apply quantity — duplicate catalogue items with unique barcodes" style="background: #f8fafc; border: 1px solid #e2e8f0;"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                            <input type="checkbox" id="modalCatalogAutoAddToOrder" checked hidden aria-hidden="true" tabindex="-1">
                        </div>
                    </div>
                    <div class="<?php echo !empty($common_modal_show_group_single_item_checkbox) ? 'col-12 col-md-3 product-modal-entry-options-col' : 'col-12 col-md-2 product-modal-entry-options-col'; ?>">
                        <div class="form-group mb-2">
                            <label class="d-none d-md-block">&nbsp;</label>
                            <label class="d-md-none">Options</label>
                            <div class="d-flex flex-wrap product-modal-entry-options">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalMetalUnfix">
                                    <label class="form-check-label" for="modalMetalUnfix" style="font-size: 0.75rem;">Metal Unfix</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalUnfix">
                                    <label class="form-check-label" for="modalUnfix" style="font-size: 0.75rem;">UnFix</label>
                                </div>
                                <?php if (!empty($common_modal_show_group_single_item_checkbox)): ?>
                                <div class="form-check" id="modalGroupSingleItemWrap" style="display: none;">
                                    <input class="form-check-input" type="checkbox" id="modalGroupSingleItem" checked>
                                    <label class="form-check-label" for="modalGroupSingleItem" style="font-size: 0.75rem;">Group Single Item</label>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>&nbsp;</label>
                            <button class="btn btn-sm btn-purple" type="button" style="width: 100%;">
                                + More >>
                            </button>
                        </div>
                    </div> -->
                </div>
                
                <!-- Column Visibility Settings -->
                <div class="d-flex justify-content-between align-items-center mb-2 product-modal-toolbar-row">
                    <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product Selection</h6>
                    <div class="d-flex align-items-center product-modal-toolbar-actions">
                        <?php if (!empty($common_modal_show_excel_import)): ?>
                        <div class="dropdown product-modal-excel-import-wrap">
                            <button type="button" class="btn btn-purple btn-sm dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Import lines from Excel">
                                <i class="feather icon-upload" style="font-size: 0.85rem;"></i> Import
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item <?php echo htmlspecialchars($common_modal_excel_import_trigger_class); ?>" href="#"><i class="feather icon-upload mr-2"></i>Import</a>
                                <a class="dropdown-item js-product-modal-excel-sample-download" href="<?php echo htmlspecialchars($common_modal_excel_sample_href, ENT_QUOTES, 'UTF-8'); ?>" data-sample-base="<?php echo htmlspecialchars($common_modal_excel_sample_href, ENT_QUOTES, 'UTF-8'); ?>"><i class="feather icon-corner-down-left mr-2"></i>Sample</a>
                            </div>
                        </div>
                        <input type="file" id="<?php echo htmlspecialchars($common_modal_excel_import_file_id); ?>" accept=".xlsx,.xls" style="display: none;" tabindex="-1">
                        <?php endif; ?>
                        <?php if (!empty($common_modal_show_add_product_in_header)): ?>
                        <button type="button" class="<?php echo htmlspecialchars($common_modal_add_row_btn_class); ?>" id="<?php echo htmlspecialchars($common_modal_add_row_btn_id); ?>" style="background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%); color: #11294b; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">
                            <i class="feather icon-plus"></i> Add Product
                        </button>
                        <?php endif; ?>
                        <div class="table-settings-wrapper">
                            <button type="button" class="table-settings-btn" id="<?php echo htmlspecialchars($common_modal_table_settings_btn_id); ?>">
                                <i class="feather icon-settings"></i> Show/Hide Columns
                            </button>
                        <div class="<?php echo htmlspecialchars($common_modal_table_settings_dropdown_class); ?>" id="<?php echo htmlspecialchars($common_modal_table_settings_dropdown_id); ?>">
                            <div class="table-settings-dropdown-head">
                            <h6>Show/Hide Columns</h6>
                            <div class="table-settings-search">
                                <input type="text"<?php if (empty($common_modal_omit_table_settings_search_id)) : ?> id="modalTableSettingsSearch"<?php endif; ?> class="form-control form-control-sm<?php echo $common_modal_table_settings_search_extra_class !== '' ? ' ' . htmlspecialchars($common_modal_table_settings_search_extra_class) : ''; ?>"
                                    placeholder="Search columns..." autocomplete="off" aria-label="Search columns">
                            </div>
                            </div>
                            <div class="table-settings-dropdown-body">
                            <?php if (!empty($common_modal_show_checkbox_in_settings)): ?>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-checkbox" data-column="checkbox" checked>
                                <label for="modal-col-checkbox">Select</label>
                            </div>
                            <?php endif; ?>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-id" data-column="id" checked>
                                <label for="modal-col-id">Id</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-rfid" data-column="rfid" checked>
                                <label for="modal-col-rfid">RFIDCode</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-voucher-type" data-column="voucher-type" checked>
                                <label for="modal-col-voucher-type">Voucher Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-photo" data-column="photo" checked>
                                <label for="modal-col-photo">Photo</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-barcode" data-column="barcode" checked>
                                <label for="modal-col-barcode">Barcode</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-design-no" data-column="design-no" checked>
                                <label for="modal-col-design-no">Design No</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-huid" data-column="huid" checked>
                                <label for="modal-col-huid">HUID No</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-item-code" data-column="item-code" checked>
                                <label for="modal-col-item-code">Item Code</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-category" data-column="category" checked>
                                <label for="modal-col-category">Diamond Category</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-product-category" data-column="product-category" checked>
                                <label for="modal-col-product-category">Category (Product)</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-calculation" data-column="calculation" checked>
                                <label for="modal-col-calculation">Calculation</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-product" data-column="product" checked>
                                <label for="modal-col-product">Product</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-location" data-column="location" checked>
                                <label for="modal-col-location">Location</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-pkt-wt" data-column="pkt-wt" checked>
                                <label for="modal-col-pkt-wt">Pkt. Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-pkt-less-wt" data-column="pkt-less-wt" checked>
                                <label for="modal-col-pkt-less-wt">Pkt. Less Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-gross-wt" data-column="gross-wt" checked>
                                <label for="modal-col-gross-wt">Gross Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-weight" data-column="stone-weight" checked>
                                <label for="modal-col-stone-weight">Carat / Stone Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-less-wt" data-column="less-wt" checked>
                                <label for="modal-col-less-wt">Less Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-net-wt" data-column="net-wt" checked>
                                <label for="modal-col-net-wt">Net Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-quantity" data-column="quantity" checked>
                                <label for="modal-col-quantity">Quantity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-rate" data-column="rate" checked>
                                <label for="modal-col-rate">Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-fc-amount" data-column="fc-amount" checked>
                                <label for="modal-col-fc-amount">FC Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-diamond-line-metal" data-column="diamond-line-metal-value" checked>
                                <label for="modal-col-diamond-line-metal">Metal Value (line)</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-rapnet" data-column="rapnet-valuation" checked>
                                <label for="modal-col-rapnet">RapNet Valuation</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-setting-charge" data-column="setting-charge" checked>
                                <label for="modal-col-setting-charge">Setting Charge</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-amount" data-column="stone-amount" checked>
                                <label for="modal-col-stone-amount">Setting Charge Amt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-mark-up-amount" data-column="mark-up-amount" checked>
                                <label for="modal-col-mark-up-amount">Mark Up Amt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-mark-up-per" data-column="mark-up-per" checked>
                                <label for="modal-col-mark-up-per">Mark Up %</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-amount" data-column="amount" checked>
                                <label for="modal-col-amount">Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-qty" data-column="metal-qty" checked>
                                <label for="modal-col-metal-qty">Metal Qty</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-weight" data-column="metal-weight" checked>
                                <label for="modal-col-metal-weight">Weight</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-carat" data-column="carat" checked>
                                <label for="modal-col-carat">Karat</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purity" data-column="purity" checked>
                                <label for="modal-col-purity">Purity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purity-wt" data-column="purity-wt" checked>
                                <label for="modal-col-purity-wt">Purity Wt</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-gold-loss1" data-column="gold-loss1" checked>
                                <label for="modal-col-gold-loss1">Gold Loss 1</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-gold-loss2" data-column="gold-loss2" checked>
                                <label for="modal-col-gold-loss2">Gold Loss 2</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-loss-value" data-column="metal-loss-value" checked>
                                <label for="modal-col-metal-loss-value">Loss Value</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-wastage-per" data-column="wastage-per" checked>
                                <label for="modal-col-wastage-per">Wastage Per.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-wastage-wt" data-column="wastage-wt" checked>
                                <label for="modal-col-wastage-wt">Wastage Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-rate" data-column="metal-rate" checked>
                                <label for="modal-col-metal-rate">Metal Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-value" data-column="metal-value" checked>
                                <label for="modal-col-metal-value">Metal Value</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purchase-rate" data-column="purchase-rate" checked>
                                <label for="modal-col-purchase-rate">Purchase Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-cost" data-column="metal-cost" checked>
                                <label for="modal-col-metal-cost">Metal Cost</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-requested-purity" data-column="requested-purity" checked>
                                <label for="modal-col-requested-purity">Requested Purity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-requested" data-column="requested" checked>
                                <label for="modal-col-requested">Requested</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-final-wt" data-column="final-wt" checked>
                                <label for="modal-col-final-wt">Final Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-alloy-wt" data-column="alloy-wt" checked>
                                <label for="modal-col-alloy-wt">Alloy Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-weight" data-column="platinum-weight" checked>
                                <label for="modal-col-platinum-weight">Pt. Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-karat" data-column="platinum-karat" checked>
                                <label for="modal-col-platinum-karat">Pt. Karat</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-purity" data-column="platinum-purity" checked>
                                <label for="modal-col-platinum-purity">Pt. Purity %</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-purity-wt" data-column="platinum-purity-wt" checked>
                                <label for="modal-col-platinum-purity-wt">Pt. Purity Wt</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-rate" data-column="platinum-rate" checked>
                                <label for="modal-col-platinum-rate">Pt. Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-wastage-per" data-column="platinum-wastage-per" checked>
                                <label for="modal-col-platinum-wastage-per">Pt. Wastg. %</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-wastage-wt" data-column="platinum-wastage-wt" checked>
                                <label for="modal-col-platinum-wastage-wt">Pt. Wastg. Wt</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-platinum-amount" data-column="platinum-amount" checked>
                                <label for="modal-col-platinum-amount">Pt. Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount-type" data-column="discount-type" checked>
                                <label for="modal-col-discount-type">Discount Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount-per" data-column="discount-per" checked>
                                <label for="modal-col-discount-per">Discount Per.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount-amount" data-column="discount-amount" checked>
                                <label for="modal-col-discount-amount">Discount Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount" data-column="discount" checked>
                                <label for="modal-col-discount">Discount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-type" data-column="making-type" checked>
                                <label for="modal-col-making-type">Making Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-rate" data-column="making-rate" checked>
                                <label for="modal-col-making-rate">Making Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-discount-amt" data-column="making-discount-amt" checked>
                                <label for="modal-col-making-discount-amt">Making Discount Amt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-amount" data-column="making-amount" checked>
                                <label for="modal-col-making-amount">Making Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-actual-value" data-column="making-actual-value" checked>
                                <label for="modal-col-making-actual-value">Making Actual Value</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-cost" data-column="making-cost" checked>
                                <label for="modal-col-making-cost">Making Cost</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-min-price" data-column="min-price" checked>
                                <label for="modal-col-min-price">Minimum Price</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-minimum" data-column="minimum" checked>
                                <label for="modal-col-minimum">Minimum Code</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-charge-type" data-column="stone-charge-type" checked>
                                <label for="modal-col-stone-charge-type">Stone Charge Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-rate" data-column="stone-rate" checked>
                                <label for="modal-col-stone-rate">Stone Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-cost" data-column="stone-cost" checked>
                                <label for="modal-col-stone-cost">Stone Cost</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-diamond-amount" data-column="diamond-amount" checked>
                                <label for="modal-col-diamond-amount">Diamond Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purchase-amount" data-column="purchase-amount" checked>
                                <label for="modal-col-purchase-amount">Purchase Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-sale-percent" data-column="sale-percent" checked>
                                <label for="modal-col-sale-percent">Sale Percentages</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-sale-amount" data-column="sale-amount" checked>
                                <label for="modal-col-sale-amount">Sale Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-sale-amount-with" data-column="sale-amount-with" checked>
                                <label for="modal-col-sale-amount-with">Sale Amount With Tax</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-net-amt" data-column="net-amt" checked>
                                <label for="modal-col-net-amt">Net Amt</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-tax-type" data-column="tax-type" checked>
                                <label for="modal-col-tax-type">Tax Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-tax-percent" data-column="tax-percent" checked>
                                <label for="modal-col-tax-percent">Tax %</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-tax" data-column="tax" checked>
                                <label for="modal-col-tax">Tax</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-charge-type" data-column="other-charge-type" checked>
                                <label for="modal-col-other-charge-type">Other Charge Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-weight" data-column="other-weight" checked>
                                <label for="modal-col-other-weight">Other Weight</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-rate" data-column="other-rate" checked>
                                <label for="modal-col-other-rate">Other Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-info" data-column="other-info" checked>
                                <label for="modal-col-other-info">Other Info</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-amount" data-column="other-amount" checked>
                                <label for="modal-col-other-amount">Other Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-certificate-amount" data-column="certificate-amount" checked>
                                <label for="modal-col-certificate-amount">Certificate Amt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-certificate-no" data-column="certificate-no" checked>
                                <label for="modal-col-certificate-no">Certificate No.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-certificate-link" data-column="certificate-link" checked>
                                <label for="modal-col-certificate-link">Certificate Link</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-video-link" data-column="video-link" checked>
                                <label for="modal-col-video-link">Video Link</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-cut" data-column="cut" checked>
                                <label for="modal-col-cut">Cut</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-color" data-column="color" checked>
                                <label for="modal-col-color">Color</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-seive-size" data-column="seive-size" checked>
                                <label for="modal-col-seive-size">Seive Size</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-size" data-column="size" checked>
                                <label for="modal-col-size">Size</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-shape" data-column="shape" checked>
                                <label for="modal-col-shape">Shape</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-clarity" data-column="clarity" checked>
                                <label for="modal-col-clarity">Clarity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-unit-price" data-column="unit-price" checked>
                                <label for="modal-col-unit-price">Unit Price</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-hallmark-amount" data-column="hallmark-amount" checked>
                                <label for="modal-col-hallmark-amount">Hallmark Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-hallmark-rate" data-column="hallmark-rate" checked>
                                <label for="modal-col-hallmark-rate">Hallmark Rate</label>
                            </div>
                            <?php if (!empty($common_modal_show_images_column)): ?>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-images" data-column="images" checked>
                                <label for="modal-col-images">Images</label>
                            </div>
                            <?php endif; ?>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-net-amt-tax" data-column="net-amt-tax" checked>
                                <label for="modal-col-net-amt-tax">Net Amt+Tax</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-reverse" data-column="reverse" checked>
                                <label for="modal-col-reverse">Reverse</label>
                            </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                
                <style>
                /* Stack above .company-header (z-index 1300 in newcss.css) so metal tabs are not hidden under the top bar */
                #productSelectionModal.modal {
                    z-index: 10600 !important;
                }
                /* Only while product selection is open — do not raise every .modal-backdrop (breaks payment modals) */
                body.modal-open:has(#productSelectionModal.show) .modal-backdrop {
                    z-index: 10550 !important;
                }
                /* Product picker from a row inside product selection modal — above #productSelectionModal (10600) */
                #productSearchModal {
                    z-index: 10700 !important;
                }
                /* Add Product / Add Category from product selection — stack above parent modal */
                #productCreationModal.modal,
                #categoryCreationModal.modal {
                    z-index: 10800 !important;
                }
                body.modal-open:has(#productSelectionModal.show):has(#productCreationModal.show) .modal-backdrop:last-of-type,
                body.modal-open:has(#productSelectionModal.show):has(#categoryCreationModal.show) .modal-backdrop:last-of-type {
                    z-index: 10750 !important;
                }
                /* Row Details overlay (10850) — product picker must stack above it */
                body.product-row-detail-modal-open #productSearchModal {
                    z-index: 10900 !important;
                }
                /* Edit Product Row overlay — above #productSelectionModal (10600) */
                #editRowModal {
                    z-index: 10950 !important;
                }
                body.edit-row-modal-open #productSelectionModal.modal {
                    pointer-events: none !important;
                }
                body.edit-row-modal-open .modal-backdrop {
                    pointer-events: none !important;
                }
                #productSelectionModal .modal-dialog {
                    margin-top: 1.25rem !important;
                    margin-bottom: 1rem !important;
                }
                #productSelectionModal .product-category-tabs {
                    position: sticky;
                    top: 0;
                    z-index: 12;
                    background: #fff;
                    padding-top: 0.35rem;
                    margin-top: -0.25rem;
                }
                /* Excel import overlay (sale order / purchase invoice only when flag is on) */
                #productSelectionModal .modal-content {
                    position: relative;
                }
                #productModalExcelImportLoader.product-modal-excel-import-loader {
                    display: none;
                    position: absolute;
                    left: 0;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 1080;
                    align-items: center;
                    justify-content: center;
                    flex-direction: column;
                    background: rgba(15, 23, 42, 0.5);
                    border-radius: 0.3rem;
                }
                #productModalExcelImportLoader.product-modal-excel-import-loader.is-visible {
                    display: flex;
                }
                #productModalExcelImportLoader .product-modal-excel-import-loader__panel {
                    text-align: center;
                    padding: 1.5rem 2rem;
                    background: #1e293b;
                    color: #f8fafc;
                    border-radius: 8px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
                    max-width: 90%;
                }
                #productModalExcelImportLoader .product-modal-excel-import-loader__spinner {
                    width: 2.5rem;
                    height: 2.5rem;
                    margin: 0 auto 1rem;
                    border: 3px solid rgba(248, 250, 252, 0.25);
                    border-top-color: #c5a864;
                    border-radius: 50%;
                    animation: productModalExcelImportSpin 0.7s linear infinite;
                }
                #productModalExcelImportLoader .product-modal-excel-import-loader__text {
                    margin: 0;
                    font-size: 0.95rem;
                    font-weight: 600;
                    color: #f1f5f9;
                }
                @keyframes productModalExcelImportSpin {
                    to { transform: rotate(360deg); }
                }
                #catalogDesignLoadOverlay.catalog-design-load-overlay {
                    display: none;
                    position: absolute;
                    inset: 0;
                    z-index: 80;
                    align-items: center;
                    justify-content: center;
                    flex-direction: column;
                    background: rgba(255, 255, 255, 0.92);
                    border-radius: 6px;
                    min-height: 180px;
                }
                #catalogDesignLoadOverlay.catalog-design-load-overlay.is-visible {
                    display: flex;
                }
                #catalogDesignLoadOverlay .catalog-design-load-overlay__spinner {
                    width: 2.25rem;
                    height: 2.25rem;
                    margin-bottom: 0.65rem;
                    border: 3px solid rgba(17, 41, 75, 0.15);
                    border-top-color: #c5a864;
                    border-radius: 50%;
                    animation: productModalExcelImportSpin 0.7s linear infinite;
                }
                #catalogDesignLoadOverlay .catalog-design-load-overlay__text {
                    margin: 0;
                    font-size: 1rem;
                    font-weight: 700;
                    color: #11294b;
                }
                #catalogDesignLoadOverlay .catalog-design-load-overlay__sub {
                    margin: 0.35rem 0 0;
                    font-size: 0.8125rem;
                    color: #64748b;
                }
                #productSelectionModal #modalProductDesignNo + .select2-container {
                    width: 100% !important;
                    max-width: 100%;
                }
                #productSelectionModal .select2-container--default .select2-selection--single {
                    height: calc(1.5em + 0.5rem + 2px);
                    min-height: calc(1.5em + 0.5rem + 2px);
                    border: 1px solid #ced4da;
                    border-radius: 0.2rem;
                    font-size: 0.875rem;
                }
                #productSelectionModal .select2-container--default .select2-selection--single .select2-selection__rendered {
                    line-height: calc(1.5em + 0.5rem);
                    padding-left: 0.5rem;
                    color: #495057;
                }
                #productSelectionModal .select2-container--default .select2-selection--single .select2-selection__arrow {
                    height: calc(1.5em + 0.5rem + 2px);
                }
                #productSelectionModal .select2-container--default.select2-container--disabled .select2-selection--single {
                    background-color: #e9ecef;
                    opacity: 1;
                }
                #productSelectionModal .select2-container {
                    z-index: 10050;
                }
                #productSelectionModal .select2-dropdown {
                    z-index: 10051;
                    font-size: 0.875rem;
                }
                /* Show/Hide Columns: hidden by default; flex layout + scroll only when .show (see page .table-settings-dropdown). */
                #productSelectionModal .table-settings-dropdown.show {
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    max-height: min(70vh, 500px);
                    overflow: hidden;
                }
                #productSelectionModal .table-settings-dropdown-head {
                    flex-shrink: 0;
                }
                #productSelectionModal .table-settings-dropdown.show .table-settings-dropdown-body {
                    overflow-y: auto;
                    overflow-x: hidden;
                    flex: 1 1 auto;
                    min-height: 0;
                    max-height: 52vh;
                    -webkit-overflow-scrolling: touch;
                }
                /* Group column borders: use default table-bordered (1px) only — no extra thick 2px group dividers. */
                #productSelectionModal .product-modal-group-drag-handle {
                    display: inline-block;
                    padding: 0 4px 0 0;
                    margin-right: 2px;
                    vertical-align: middle;
                    line-height: 1;
                }
                #productSelectionModal .product-modal-group-header-th .feather { width: 14px; height: 14px; }
                /* Group header row: navy + white (matches stock journal product table) */
                #productSelectionModal #productListTable thead tr:nth-child(1) th:not([data-column="checkbox"]) {
                    background: #11294b !important;
                    color: #ffffff !important;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.25);
                }
                #productSelectionModal #productListTable thead tr:nth-child(1) th.product-modal-group-header-th,
                #productSelectionModal #productListTable thead tr:nth-child(1) th .product-modal-group-label,
                #productSelectionModal #productListTable thead tr:nth-child(1) th .feather {
                    color: #ffffff !important;
                }
                #productSelectionModal #productListTable thead tr:nth-child(1) th .product-modal-group-drag-handle {
                    color: rgba(255, 255, 255, 0.92) !important;
                }
                #productSelectionModal #productListTable .product-modal-col-drag-handle {
                    display: inline-block;
                    padding: 0 4px 0 0;
                    margin-right: 2px;
                    vertical-align: middle;
                    line-height: 1;
                }
                #productSelectionModal #productListTable .product-modal-col-drag-handle .feather {
                    width: 12px;
                    height: 12px;
                }
                #productSelectionModal #productListTable .product-modal-col-drag-handle--locked {
                    cursor: default;
                    opacity: 0.45;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) {
                    color: #ffffff !important;
                    background: #11294b !important;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-th-label {
                    color: #ffffff !important;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-col-drag-handle,
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .feather {
                    color: rgba(255, 255, 255, 0.92) !important;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"],
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] {
                    color: #ffffff !important;
                    background: linear-gradient(180deg, #c5a864 0%, #a68a4a 100%) !important;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-th-label,
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .product-modal-th-label {
                    color: #ffffff !important;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-col-drag-handle,
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .product-modal-col-drag-handle {
                    color: rgba(255, 255, 255, 0.92) !important;
                    opacity: 0.95;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .feather,
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .feather {
                    color: rgba(255, 255, 255, 0.95) !important;
                }
                #productSelectionModal #productListTable thead tr:first-child th[data-group="net-reverse"] .product-modal-col-drag-handle--locked,
                #productSelectionModal #productListTable thead tr:first-child th[data-column="actions"] .product-modal-col-drag-handle--locked {
                    color: rgba(255, 255, 255, 0.9);
                    opacity: 0.85;
                }
                /* Small label following cursor while dragging a column */
                .product-modal-col-drag-ghost {
                    max-width: 260px;
                    padding: 6px 10px;
                    border-radius: 6px;
                    background: rgba(17, 41, 75, 0.92);
                    color: #fff;
                    font-size: 0.72rem;
                    font-weight: 600;
                    line-height: 1.25;
                    border: none;
                    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
                    word-break: break-word;
                }
                .product-modal-col-drag-ghost--minimal {
                    letter-spacing: 0.01em;
                }
                /* Softer drag state: keep header color, thin ring only */
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column].modal-col-dragging {
                    background: inherit !important;
                    border-style: solid !important;
                    box-shadow: inset 0 0 0 2px rgba(197, 168, 100, 0.95) !important;
                    opacity: 0.98;
                }
                /* Column resize: do NOT set position:relative on th here — it overrides page rules like sticky (sale-quotations) and causes a white gap between header rows. Sticky th still contains absolute .pm-col-resizer. JS adds position:relative only when computed position is static. */
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="actions"]) {
                    box-sizing: border-box;
                    padding-right: 10px;
                    vertical-align: middle;
                }
                /* Label truncates with ellipsis when column is narrow; expands when resized (width from JS). title on th = full name. */
                #productSelectionModal #productListTable thead tr:nth-child(2) th.product-modal-th-cell .product-modal-th-inner {
                    display: flex;
                    align-items: center;
                    gap: 0.2rem;
                    min-width: 0;
                    width: 100%;
                    max-width: 100%;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th.product-modal-th-cell .product-modal-th-label {
                    flex: 1 1 auto;
                    min-width: min(100%, 2.5rem);
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-th-label,
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .product-modal-th-label {
                    min-width: 3.5em;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th.product-modal-th-cell .product-modal-col-drag-handle {
                    flex-shrink: 0;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th.product-modal-th-cell .product-modal-th-inner > .feather {
                    flex-shrink: 0;
                }
                /* Resize: invisible hit strip (no white icon); gold on hover. Left feather icon-move = reorder only. */
                #productSelectionModal #productListTable .pm-col-resizer {
                    position: absolute;
                    top: 0;
                    right: 0;
                    width: 8px;
                    min-width: 8px;
                    max-width: 8px;
                    margin: 0;
                    padding: 0;
                    cursor: col-resize;
                    user-select: none;
                    -webkit-user-select: none;
                    z-index: 8;
                    height: 100%;
                    min-height: 28px;
                    background: transparent !important;
                    box-shadow: none;
                }
                #productSelectionModal #productListTable .pm-col-resizer:hover {
                    background: rgba(212, 175, 55, 0.4) !important;
                }
                /* Resized columns set td width; inputs keep a browser min width and can spill — clamp controls to the cell. */
                #productSelectionModal #productListTable tbody td[data-column]:not([data-column="checkbox"]):not([data-column="actions"]) {
                    overflow: hidden;
                    min-width: 0;
                }
                #productSelectionModal #productListTable tbody td[data-column] input.form-control,
                #productSelectionModal #productListTable tbody td[data-column] select.form-control,
                #productSelectionModal #productListTable tbody td[data-column] textarea.form-control {
                    width: 100% !important;
                    max-width: 100% !important;
                    min-width: 0 !important;
                    box-sizing: border-box;
                }
                </style>
                <link rel="stylesheet" href="assets/css/product-selection-modal-mobile.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/product-selection-modal-mobile.css'); ?>">
                <!-- Mobile: columns as 2-col form (table hidden via CSS) -->
                <div id="productModalMobileFormWrap" class="product-modal-mobile-form-wrap d-md-none">
                    <div id="productModalMobileFormEmpty" class="product-modal-mobile-form-empty" hidden aria-hidden="true"></div>
                    <div id="productModalMobileForm" class="product-row-detail-modal__form product-modal-mobile-form"></div>
                    <div id="productModalMobileFormFooter" class="product-modal-mobile-form-footer" hidden>
                        <button type="button" class="product-row-detail-modal__btn-clear" data-action="mobile-clear">Clear</button>
                        <button type="button" class="product-row-detail-modal__btn-save" data-action="mobile-save">Save</button>
                    </div>
                </div>
                <!-- Product List Table with All Options - Horizontally Scrollable (scroll wrapper needed for sticky right columns) -->
                <div class="product-list-table-outer product-selection-wrapper" id="productListTableOuter">
                <div id="productListTableScrollWrapper" class="table-responsive" style="overflow-x: auto; overflow-y: auto; max-height: 500px; border: 1px solid #e2e8f0; border-radius: 6px; position: relative;">
                    <div id="catalogDesignLoadOverlay" class="catalog-design-load-overlay" aria-hidden="true" aria-live="polite">
                        <div class="catalog-design-load-overlay__spinner" role="status" aria-label="Loading"></div>
                        <p class="catalog-design-load-overlay__text">Please wait…</p>
                        <p class="catalog-design-load-overlay__sub">Loading catalogue items…</p>
                    </div>
                    <table class="table table-bordered table-sm mb-0<?php echo $common_modal_pl_table_class_extra !== '' ? ' ' . htmlspecialchars($common_modal_pl_table_class_extra) : ''; ?>" id="productListTable" style="<?php echo htmlspecialchars($common_modal_pl_table_style, ENT_QUOTES, 'UTF-8'); ?>" data-modal-images-column="<?php echo !empty($common_modal_show_images_column) ? '1' : '0'; ?>">
                        <thead class="product-modal-thead" style="position: sticky; top: 0; background: #f8fafc; z-index: 6;">
                            <?php
                            $common_modal_drag_icons = '<i class="feather icon-move"></i>';
                            $common_modal_col_drag = '<span class="product-modal-col-drag-handle" title="Drag to reorder within this group (use the move icon on the group title row above to move the whole group).">' . $common_modal_drag_icons . '</span>';
                            $common_modal_col_drag_locked = '<span class="product-modal-col-drag-handle product-modal-col-drag-handle--locked" title="Fixed column order"><i class="feather icon-move"></i></span>';
                            ?>
                            <!-- Group header row: colspans = column counts per group (updated by JS on show/hide) -->
                            <tr style="font-weight: 600;">
                                <?php if (!empty($common_modal_show_checkbox_column)): ?>
                                <th rowspan="2" data-column="checkbox" style="min-width: 50px; background: #e2e8f0; vertical-align: middle;" title="Select all. While dragging a column group (top header row), drop here to place that group first—before Basic Information.">
                                    <input type="checkbox" id="selectAllProducts" title="Select All">
                                </th>
                                <?php endif; ?>
                                <th colspan="13" data-group="basic-information" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Basic Information</span></th>
                                <th colspan="16" data-group="diamond-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Diamond group</span></th>
                                <th colspan="14" data-group="metal-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Metal group</span></th>
                                <th colspan="4" data-group="request-final-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Request &amp; Final Wt.</span></th>
                                <th colspan="8" data-group="platinum-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Platinum (group)</span></th>
                                <th colspan="4" data-group="discount-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Discount (group)</span></th>
                                <th colspan="6" data-group="making-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Making (group)</span></th>
                                <th colspan="2" data-group="minimum-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Minimum</span></th>
                                <th colspan="4" data-group="stone-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Stone group</span></th>
                                <th colspan="8" data-group="amounts" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Amounts</span></th>
                                <th colspan="5" data-group="other-charge-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Other Charge (group)</span></th>
                                <th colspan="11" data-group="cert-spec-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Certificate &amp; spec</span></th>
                                <th colspan="2" data-group="hallmark" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Hallmark</span></th>
                                <th colspan="2" data-group="net-reverse" data-group-locked="1" style="text-align: center;"><?php echo $common_modal_col_drag_locked; ?><span class="product-modal-group-label">Net Amt+Tax / Reverse</span></th>
                                <?php if (!empty($common_modal_show_images_column)): ?>
                                <th rowspan="2" data-column="images" style="text-align: center; vertical-align: middle;"><?php echo $common_modal_col_drag_locked; ?>Images</th>
                                <?php endif; ?>
                                <th rowspan="2" data-column="actions" style="min-width: 80px; width: 80px; text-align: center; vertical-align: middle;"><?php echo $common_modal_col_drag_locked; ?>Action</th>
                            </tr>
                            <!-- Column header row (order must match body cells after checkbox). Full names in title + .product-modal-th-label; ellipsis when narrow. -->
                            <tr>
                                <th data-column="id" class="product-modal-th-cell" style="min-width: 60px;" title="Id"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Id</span></div></th>
                                <th data-column="rfid" class="product-modal-th-cell" style="min-width: 100px;" title="RFIDCode"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">RFIDCode</span></div></th>
                                <th data-column="voucher-type" class="product-modal-th-cell" style="min-width: 120px;" title="Style"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Style</span></div></th>
                                <th data-column="photo" class="product-modal-th-cell" style="min-width: 70px;" title="Photo"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Photo</span></div></th>
                                <th data-column="barcode" class="product-modal-th-cell" style="min-width: 120px;" title="Barcode No."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Barcode No.</span></div></th>
                                <th data-column="design-no" class="product-modal-th-cell" style="min-width: 100px;" title="Design No"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Design No</span></div></th>
                                <th data-column="huid" class="product-modal-th-cell" style="min-width: 100px;" title="HUID No."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">HUID No.</span></div></th>
                                <th data-column="item-code" class="product-modal-th-cell" style="min-width: 100px;" title="Item Code"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Item Code</span></div></th>
                                <th data-column="category" class="product-modal-th-cell" style="min-width: 120px;" title="Diamond Category"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Diamond Category</span><i class="feather icon-plus add-category-icon" style="font-size: 0.7rem; cursor: pointer; flex-shrink: 0;" title="Add New Category"></i></div></th>
                                <th data-column="product-category" class="product-modal-th-cell" style="min-width: 120px;" title="Product category (tbl_categories)"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Product category</span><i class="feather icon-plus add-product-category-icon" style="font-size: 0.7rem; cursor: pointer; flex-shrink: 0;" title="Product category (tbl_categories)"></i></div></th>
                                <th data-column="calculation" class="product-modal-th-cell" style="min-width: 140px;" title="Calculation"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Calculation</span></div></th>
                                <th data-column="product" class="product-modal-th-cell" style="min-width: 120px;" title="Product"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Product*</span><?php if (!empty($common_modal_show_add_product_icon)) : ?><i class="feather icon-plus add-product-icon" style="font-size: 0.7rem; cursor: pointer; flex-shrink: 0;" title="Add New Product"></i><?php endif; ?></div></th>
                                <th data-column="location" class="product-modal-th-cell" style="min-width: 120px;" title="Location"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Location</span><i class="feather icon-plus add-location-icon" style="font-size: 0.7rem; cursor: pointer; flex-shrink: 0;" title="Add Location"></i></div></th>
                                <th data-column="pkt-wt" class="product-modal-th-cell" style="min-width: 80px;" title="Pkt. Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pkt. Wt.</span></div></th>
                                <th data-column="pkt-less-wt" class="product-modal-th-cell" style="min-width: 100px;" title="Pkt. Less Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pkt. Less Wt.</span></div></th>
                                <th data-column="gross-wt" class="product-modal-th-cell" style="min-width: 80px;" title="Gross Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Gross Wt.</span></div></th>
                                <th data-column="stone-weight" class="product-modal-th-cell" style="min-width: 110px;" title="Carat / Stone Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Carat / Stone Wt.</span></div></th>
                                <th data-column="less-wt" class="product-modal-th-cell" style="min-width: 80px;" title="Less Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Less Wt.</span></div></th>
                                <th data-column="net-wt" class="product-modal-th-cell" style="min-width: 80px;" title="Net Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Net Wt.</span></div></th>
                                <th data-column="quantity" class="product-modal-th-cell" style="min-width: 80px;" title="Quantity"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Quantity</span></div></th>
                                <th data-column="rate" class="product-modal-th-cell" style="min-width: 80px;" title="Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Rate</span></div></th>
                                <th data-column="fc-amount" class="product-modal-th-cell" style="min-width: 90px;" title="FC Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">FC Amount</span></div></th>
                                <th data-column="diamond-line-metal-value" class="product-modal-th-cell" style="min-width: 100px;" title="Metal Value (line)"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Metal Value</span></div></th>
                                <th data-column="rapnet-valuation" class="product-modal-th-cell" style="min-width: 100px;" title="RapNet Valuation"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">RapNet Valuation</span></div></th>
                                <th data-column="setting-charge" class="product-modal-th-cell" style="min-width: 110px;" title="Setting Charge"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Setting Charge</span></div></th>
                                <th data-column="stone-amount" class="product-modal-th-cell" style="min-width: 120px;" title="Setting Charge Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Setting Charge Amt.</span></div></th>
                                <th data-column="mark-up-amount" class="product-modal-th-cell" style="min-width: 100px;" title="Mark Up Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Mark Up Amt.</span></div></th>
                                <th data-column="mark-up-per" class="product-modal-th-cell" style="min-width: 80px;" title="Mark Up %"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Mark Up %</span></div></th>
                                <th data-column="amount" class="product-modal-th-cell" style="min-width: 100px;" title="Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Amount</span></div></th>
                                <th data-column="metal-qty" class="product-modal-th-cell" style="min-width: 80px;" title="Metal Qty"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Metal Qty</span></div></th>
                                <th data-column="metal-weight" class="product-modal-th-cell" style="min-width: 80px;" title="Weight"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Weight</span></div></th>
                                <th data-column="carat" class="product-modal-th-cell" style="min-width: 80px;" title="Karat"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Karat</span><i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer; flex-shrink: 0;"></i></div></th>
                                <th data-column="purity" class="product-modal-th-cell" style="min-width: 80px;" title="Purity %"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Purity %</span></div></th>
                                <th data-column="purity-wt" class="product-modal-th-cell" style="min-width: 90px;" title="Purity Wt"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Purity Wt</span></div></th>
                                <th data-column="gold-loss1" class="product-modal-th-cell" style="min-width: 100px;" title="Gold Loss 1"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Gold Loss 1</span></div></th>
                                <th data-column="gold-loss2" class="product-modal-th-cell" style="min-width: 100px;" title="Gold Loss 2"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Gold Loss 2</span></div></th>
                                <th data-column="metal-loss-value" class="product-modal-th-cell" style="min-width: 100px;" title="Loss Value"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Loss Value</span></div></th>
                                <th data-column="wastage-per" class="product-modal-th-cell" style="min-width: 100px;" title="Wastage Per."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Wastage Per.</span></div></th>
                                <th data-column="wastage-wt" class="product-modal-th-cell" style="min-width: 100px;" title="Wastage Wt"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Wastage Wt</span></div></th>
                                <th data-column="metal-rate" class="product-modal-th-cell" style="min-width: 90px;" title="Metal Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Metal Rate</span></div></th>
                                <th data-column="metal-value" class="product-modal-th-cell" style="min-width: 100px;" title="Metal Value"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Metal Value</span></div></th>
                                <th data-column="purchase-rate" class="product-modal-th-cell" style="min-width: 100px;" title="Purchase Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Purchase Rate</span></div></th>
                                <th data-column="metal-cost" class="product-modal-th-cell" style="min-width: 100px;" title="Metal Cost"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Metal Cost</span></div></th>
                                <th data-column="requested-purity" class="product-modal-th-cell" style="min-width: 120px;" title="Requested Purity"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Requested Purity</span></div></th>
                                <th data-column="requested" class="product-modal-th-cell" style="min-width: 100px;" title="Requested"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Requested</span></div></th>
                                <th data-column="final-wt" class="product-modal-th-cell" style="min-width: 80px;" title="Final Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Final Wt.</span></div></th>
                                <th data-column="alloy-wt" class="product-modal-th-cell" style="min-width: 80px;" title="Alloy Wt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Alloy Wt.</span></div></th>
                                <th data-column="platinum-weight" class="product-modal-th-cell" style="min-width: 80px;" title="Platinum Weight"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Wt.</span></div></th>
                                <th data-column="platinum-karat" class="product-modal-th-cell" style="min-width: 80px;" title="Platinum Karat"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Karat</span></div></th>
                                <th data-column="platinum-purity" class="product-modal-th-cell" style="min-width: 80px;" title="Platinum Purity %"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Purity %</span></div></th>
                                <th data-column="platinum-purity-wt" class="product-modal-th-cell" style="min-width: 90px;" title="Platinum Purity Wt"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Purity Wt</span></div></th>
                                <th data-column="platinum-rate" class="product-modal-th-cell" style="min-width: 90px;" title="Platinum Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Rate</span></div></th>
                                <th data-column="platinum-wastage-per" class="product-modal-th-cell" style="min-width: 100px;" title="Platinum Wastage %"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Wastg. %</span></div></th>
                                <th data-column="platinum-wastage-wt" class="product-modal-th-cell" style="min-width: 100px;" title="Platinum Wastage Wt"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Wastg. Wt</span></div></th>
                                <th data-column="platinum-amount" class="product-modal-th-cell" style="min-width: 100px;" title="Platinum Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Pt. Amount</span></div></th>
                                <th data-column="discount-type" class="product-modal-th-cell" style="min-width: 100px;" title="Discount Type"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Discount Type</span></div></th>
                                <th data-column="discount-per" class="product-modal-th-cell" style="min-width: 80px;" title="Discount Per."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Discount Per.</span></div></th>
                                <th data-column="discount-amount" class="product-modal-th-cell" style="min-width: 100px;" title="Discount Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Discount Amount</span></div></th>
                                <th data-column="discount" class="product-modal-th-cell" style="min-width: 100px;" title="Discount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Discount</span></div></th>
                                <th data-column="making-type" class="product-modal-th-cell" style="min-width: 100px;" title="Making Type"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Making Type</span></div></th>
                                <th data-column="making-rate" class="product-modal-th-cell" style="min-width: 100px;" title="Making Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Making Rate</span></div></th>
                                <th data-column="making-discount-amt" class="product-modal-th-cell" style="min-width: 130px;" title="Making Discount Amt."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Making Discount Amt.</span></div></th>
                                <th data-column="making-amount" class="product-modal-th-cell" style="min-width: 100px;" title="Making Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Making Amount</span></div></th>
                                <th data-column="making-actual-value" class="product-modal-th-cell" style="min-width: 120px;" title="Making Actual Value"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Making Actual Value</span></div></th>
                                <th data-column="making-cost" class="product-modal-th-cell" style="min-width: 110px;" title="Making Cost"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Making Cost</span></div></th>
                                <th data-column="min-price" class="product-modal-th-cell" style="min-width: 100px;" title="Minimum Price"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Minimum Price</span></div></th>
                                <th data-column="minimum" class="product-modal-th-cell" style="min-width: 100px;" title="Minimum Code"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Minimum Code</span></div></th>
                                <th data-column="stone-charge-type" class="product-modal-th-cell" style="min-width: 100px;" title="Stone Charge Type"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Stone Charge Type</span></div></th>
                                <th data-column="stone-rate" class="product-modal-th-cell" style="min-width: 100px;" title="Stone Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Stone Rate</span></div></th>
                                <th data-column="stone-cost" class="product-modal-th-cell" style="min-width: 100px;" title="Stone Cost"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Stone Cost</span></div></th>
                                <th data-column="diamond-amount" class="product-modal-th-cell" style="min-width: 120px;" title="Diamond Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Diamond Amount</span></div></th>
                                <th data-column="purchase-amount" class="product-modal-th-cell" style="min-width: 130px;" title="Purchase Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Purchase Amount</span></div></th>
                                <th data-column="sale-percent" class="product-modal-th-cell" style="min-width: 100px;" title="Sale Percentages"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Sale Percentages</span></div></th>
                                <th data-column="sale-amount" class="product-modal-th-cell" style="min-width: 110px;" title="Sale Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Sale Amount</span></div></th>
                                <th data-column="sale-amount-with" class="product-modal-th-cell" style="min-width: 130px;" title="Sale Amount With Tax"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Sale Amount With Tax</span></div></th>
                                <th data-column="net-amt" class="product-modal-th-cell" style="min-width: 110px;" title="Net Amt"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Net Amt</span></div></th>
                                <th data-column="tax-type" class="product-modal-th-cell" style="min-width: 120px;" title="Tax Type"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Tax Type</span></div></th>
                                <th data-column="tax-percent" class="product-modal-th-cell" style="min-width: 70px;" title="Tax %"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Tax %</span></div></th>
                                <th data-column="tax" class="product-modal-th-cell" style="min-width: 80px;" title="Tax"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Tax</span></div></th>
                                <th data-column="other-charge-type" class="product-modal-th-cell" style="min-width: 100px;" title="Other Charge Type"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Other Charge Type</span></div></th>
                                <th data-column="other-weight" class="product-modal-th-cell" style="min-width: 110px;" title="Other Weight"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Other Weight</span></div></th>
                                <th data-column="other-rate" class="product-modal-th-cell" style="min-width: 100px;" title="Other Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Other Rate</span></div></th>
                                <th data-column="other-info" class="product-modal-th-cell" style="min-width: 100px;" title="Other Info"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Other Info</span></div></th>
                                <th data-column="other-amount" class="product-modal-th-cell" style="min-width: 120px;" title="Other Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Other Amount</span></div></th>
                                <th data-column="certificate-amount" class="product-modal-th-cell" style="min-width: 110px;" title="Certificate Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Certificate Amt.</span></div></th>
                                <th data-column="certificate-no" class="product-modal-th-cell" style="min-width: 110px;" title="Certificate No."><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Certificate No.</span></div></th>
                                <th data-column="certificate-link" class="product-modal-th-cell" style="min-width: 120px;" title="Certificate Link"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Certificate Link</span></div></th>
                                <th data-column="video-link" class="product-modal-th-cell" style="min-width: 120px;" title="Video Link"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Video Link</span></div></th>
                                <th data-column="cut" class="product-modal-th-cell" style="min-width: 100px;" title="Cut"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Cut</span></div></th>
                                <th data-column="color" class="product-modal-th-cell" style="min-width: 100px;" title="Color"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Color</span></div></th>
                                <th data-column="seive-size" class="product-modal-th-cell" style="min-width: 90px;" title="Seive Size"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Seive Size</span></div></th>
                                <th data-column="size" class="product-modal-th-cell" style="min-width: 80px;" title="Size"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Size</span></div></th>
                                <th data-column="shape" class="product-modal-th-cell" style="min-width: 100px;" title="Shape"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Shape</span></div></th>
                                <th data-column="clarity" class="product-modal-th-cell" style="min-width: 100px;" title="Clarity"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Clarity</span></div></th>
                                <th data-column="unit-price" class="product-modal-th-cell" style="min-width: 90px;" title="Unit Price"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Unit Price</span></div></th>
                                <th data-column="hallmark-amount" class="product-modal-th-cell" style="min-width: 130px;" title="Hallmark Amount"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Hallmark Amount</span></div></th>
                                <th data-column="hallmark-rate" class="product-modal-th-cell" style="min-width: 120px;" title="Hallmark Rate"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag; ?><span class="product-modal-th-label">Hallmark Rate</span></div></th>
                                <th data-column="net-amt-tax" class="product-modal-th-cell" style="min-width: 130px;" title="Net Amt+Tax"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag_locked; ?><span class="product-modal-th-label">Net Amt+Tax</span></div></th>
                                <th data-column="reverse" class="product-modal-th-cell" style="min-width: 80px;" title="Reverse"><div class="product-modal-th-inner"><?php echo $common_modal_col_drag_locked; ?><span class="product-modal-th-label">Reverse</span></div></th>
                            </tr>
                        </thead>
                        <tbody id="productListBody">
                            <tr>
                                <td colspan="<?php echo (int) $common_modal_empty_row_colspan; ?>" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
                
                <!-- Bottom Section -->
                <div class="row mt-3 product-modal-bottom-row">
                    <div class="col-6 col-md-6">
                        <div class="form-group mb-2">
                            <label>Group Name</label>
                            <input type="text" class="form-control form-control-sm" id="modalGroupName" placeholder="Group Name">
                        </div>
                    </div>
                    <div class="col-6 col-md-6">
                        <div class="form-group mb-2">
                            <label>Comment</label>
                            <input type="text" class="form-control form-control-sm" id="modalComment" placeholder="Comment">
                        </div>
                    </div>
                </div>
                
                <?php if ($common_modal_product_footer_mode === 'stock_journal') : ?>
                <div class="text-right mt-3">
                    <button type="button" class="btn btn-purple btn-sm stock-journal-add-btn" id="modalAddBtn2">
                        <i class="feather icon-plus"></i> Add (Shift + A)
                    </button>
                </div>
                <?php else : ?>
                <div class="text-right mt-3 d-flex align-items-center justify-content-end" style="gap: 0.5rem;">
                    <button type="button" class="btn btn-purple btn-sm" id="modalAddBtn">
                        <i class="feather icon-plus"></i> Add (Shift + A)
                    </button>
                    <input type="file" id="productModalGroupImageInput" accept="image/*" style="display: none;">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="productModalUploadImageBtn" title="Upload image for this group (tab-wise)" style="margin-left: 0.5rem;">
                        <i class="feather icon-camera" style="font-size: 12px;"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
if (function_exists('auragold_echo_product_modal_spec_masters_js')) {
    echo "<script>\n";
    auragold_echo_product_modal_spec_masters_js();
    echo "</script>\n";
}
$GLOBALS['auragold_common_modal_product_selection_included'] = true;
?>
<script>
(function () {
    var modal = document.getElementById('productSelectionModal');
    if (!modal || modal.dataset.mobileTabsScrollBound === '1') {
        return;
    }
    modal.dataset.mobileTabsScrollBound = '1';

    function isMobileTabs() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    function scrollActiveCategoryTab(inline) {
        if (!isMobileTabs()) {
            return;
        }
        var tabs = modal.querySelector('.product-category-tabs');
        var active = tabs && tabs.querySelector('.category-tab-btn.active');
        if (!active || typeof active.scrollIntoView !== 'function') {
            return;
        }
        active.scrollIntoView({
            inline: inline || 'nearest',
            block: 'nearest',
            behavior: 'auto'
        });
    }

    modal.addEventListener('shown.bs.modal', function () {
        scrollActiveCategoryTab('start');
    });

    modal.addEventListener('click', function (e) {
        var btn = e.target.closest('.category-tab-btn');
        if (!btn || !isMobileTabs()) {
            return;
        }
        window.setTimeout(function () {
            btn.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
        }, 0);
    });
})();
</script>
