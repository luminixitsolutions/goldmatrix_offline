<?php
/**
 * ARCHIVE / REFERENCE ONLY — not loaded by the app.
 * Live Product Selection modal: admin/includes/common-modal.php
 * (Same two-row thead markup; current file adds no third header row.)
 *
 * Optional before include: $common_modal_show_images_column = true;
 * Default false: voucher modals (sale/purchase/etc.) use Photo + group image only; row HTML has no images <td>,
 * so a header-only Images column misaligned Action under "Images".
 */
if (!isset($common_modal_show_images_column)) {
    $common_modal_show_images_column = false;
}
?>
<!-- Product Selection Modal -->
<div class="modal fade" id="productSelectionModal" tabindex="-1" role="dialog" aria-labelledby="productSelectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
        <div class="modal-content">
            <!-- <div class="modal-header" style="background: #ffffff; border-bottom: 2px solid #e2e8f0; padding: 1rem;">
                <div style="width: 100%; position: relative;">
                    <input type="text" class="form-control form-control-lg" id="modalProductSearchInput" placeholder="Enter your item" style="border: 2px solid #c5a864; border-radius: 6px; padding-right: 40px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.5rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div> -->
            <div class="modal-body">
                <!-- Category Tabs -->
                <div class="product-category-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
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
                <div class="row mb-3" style="background: transparent; padding: 0px; border-radius: 0px;">
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>Barcode</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductBarcode" placeholder="Scan or enter">
                                <div class="input-group-append">
                                    <span class="input-group-text" style="background: #f8fafc;"><i class="feather icon-image"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>Code</label>
                            <input type="text" class="form-control form-control-sm" id="modalProductCode" placeholder="Code">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>Des. No.</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductDesignNo" placeholder="Design number">
                                <div class="input-group-append">
                                    <span class="input-group-text" style="background: #f8fafc;"><i class="feather icon-chevron-down"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group mb-2">
                            <label>&nbsp;</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductQty" value="1" min="1" step="0.01">
                                <div class="input-group-append">
                                    <button class="btn btn-sm" type="button" style="background: #f8fafc; border: 1px solid #e2e8f0;"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>&nbsp;</label>
                            <div class="d-flex" style="gap: 0.5rem; align-items: center;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalMetalUnfix">
                                    <label class="form-check-label" for="modalMetalUnfix" style="font-size: 0.75rem;">Metal Unfix</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalUnfix">
                                    <label class="form-check-label" for="modalUnfix" style="font-size: 0.75rem;">UnFix</label>
                                </div>
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
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product Selection</h6>
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <button class="table-settings-btn" id="addProductRowBtn" style="background: #c5a864; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="feather icon-plus"></i> Add Product
                        </button>
                        <div class="table-settings-wrapper">
                            <button class="table-settings-btn" id="modalTableSettingsBtn">
                                <i class="feather icon-settings"></i> Show/Hide Columns
                            </button>
                        <div class="table-settings-dropdown" id="modalTableSettingsDropdown">
                            <h6>Show/Hide Columns</h6>
                            <div class="table-settings-search">
                                <input type="text" id="modalTableSettingsSearch" placeholder="Search columns..." autocomplete="off">
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-checkbox" data-column="checkbox" checked>
                                <label for="modal-col-checkbox">Select</label>
                            </div>
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
                                <input type="checkbox" id="modal-col-category" data-column="category" checked>
                                <label for="modal-col-category">Category</label>
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
                                <input type="checkbox" id="modal-col-setting-charge" data-column="setting-charge" checked>
                                <label for="modal-col-setting-charge">Setting Charge</label>
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
                                <input type="checkbox" id="modal-col-stone-amount" data-column="stone-amount" checked>
                                <label for="modal-col-stone-amount">Stone Amount</label>
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
                
                <style>
                /* Product modal: clear vertical line between column groups (matches PRODUCT_MODAL_COLUMN_GROUPS) */
                #productSelectionModal #productListTable thead tr:first-child th[data-column="checkbox"]:not(.hidden) {
                    border-right: 2px solid #8b6914 !important;
                }
                #productSelectionModal #productListTable thead tr:first-child th[data-group]:not(.hidden) {
                    border-right: 2px solid #8b6914 !important;
                }
                /* Group divider: last visible column per data-group in DOM order (see markProductModalGroupEndColumns) */
                #productSelectionModal #productListTable thead tr:nth-child(2) th.group-end:not(.hidden),
                #productSelectionModal #productListTable tbody td.group-end:not(.hidden) {
                    border-right: 2px solid #c9a44c !important;
                }
                /* Hallmark separate from Net Amt+Tax / Reverse (same contract as PRODUCT_MODAL_COLUMN_GROUPS) */
                #productSelectionModal #productListTable thead tr:first-child th[data-group="hallmark"]:not(.hidden) {
                    border-right: 2px solid #475569 !important;
                }
                #productSelectionModal #productListTable thead tr:first-child th[data-group="net-reverse"]:not(.hidden) {
                    border-left: 2px solid rgba(255, 255, 255, 0.65) !important;
                }
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"]:not(.hidden) {
                    border-left: 2px solid #475569 !important;
                }
                #productSelectionModal #productListTable tbody td[data-column="net-amt-tax"]:not(.hidden) {
                    border-left: 2px solid #cbd5e1 !important;
                }
                #productSelectionModal .product-modal-group-drag-handle {
                    display: inline-block;
                    padding: 0 4px 0 0;
                    margin-right: 2px;
                    vertical-align: middle;
                    line-height: 1;
                }
                #productSelectionModal .product-modal-group-header-th .feather { width: 14px; height: 14px; }
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
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-col-drag-handle,
                #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .product-modal-col-drag-handle {
                    color: rgba(255, 255, 255, 0.9);
                    opacity: 0.85;
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
                </style>
                <!-- Product List Table with All Options - Horizontally Scrollable (scroll wrapper needed for sticky right columns) -->
                <div class="product-list-table-outer product-selection-wrapper" id="productListTableOuter">
                <div id="productListTableScrollWrapper" class="table-responsive" style="overflow-x: auto; overflow-y: auto; max-height: 500px; border: 1px solid #e2e8f0; border-radius: 6px; position: relative;">
                    <table class="table table-bordered table-sm mb-0" id="productListTable" style="font-size: 0.75rem;" data-modal-images-column="<?php echo !empty($common_modal_show_images_column) ? '1' : '0'; ?>">
                        <thead class="product-modal-thead" style="position: sticky; top: 0; background: #f8fafc; z-index: 6;">
                            <?php
                            $common_modal_drag_icons = '<i class="feather icon-move"></i>';
                            $common_modal_col_drag = '<span class="product-modal-col-drag-handle" title="Drag to reorder within this group (use the move icon on the group title row above to move the whole group).">' . $common_modal_drag_icons . '</span>';
                            $common_modal_col_drag_locked = '<span class="product-modal-col-drag-handle product-modal-col-drag-handle--locked" title="Fixed column order"><i class="feather icon-move"></i></span>';
                            ?>
                            <!-- Group header row: colspans = column counts per group (updated by JS on show/hide) -->
                            <tr style="background: #e2e8f0; font-weight: 600;">
                                <th rowspan="2" data-column="checkbox" style="min-width: 50px; background: #e2e8f0; vertical-align: middle;" title="Select all. While dragging a column group (top header row), drop here to place that group first—before Basic Information.">
                                    <input type="checkbox" id="selectAllProducts" title="Select All">
                                </th>
                                <th colspan="11" data-group="basic-information" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Basic Information</span></th>
                                <th colspan="9" data-group="diamond-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Diamond group</span></th>
                                <th colspan="13" data-group="metal-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Metal group</span></th>
                                <th colspan="5" data-group="request-final-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Request &amp; Final Wt.</span></th>
                                <th colspan="4" data-group="discount-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Discount (group)</span></th>
                                <th colspan="6" data-group="making-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Making (group)</span></th>
                                <th colspan="2" data-group="minimum-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Minimum</span></th>
                                <th colspan="5" data-group="stone-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Stone group</span></th>
                                <th colspan="7" data-group="amounts" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Amounts</span></th>
                                <th colspan="5" data-group="other-charge-group" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Other Charge (group)</span></th>
                                <th colspan="2" data-group="hallmark" class="product-modal-group-header-th" style="text-align: center; background: #cbd5e1;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $common_modal_drag_icons; ?></span><span class="product-modal-group-label">Hallmark</span></th>
                                <th colspan="2" data-group="net-reverse" data-group-locked="1" style="text-align: center; background: #a68a4a !important; color: #fff;"><?php echo $common_modal_col_drag_locked; ?><span class="product-modal-group-label">Net Amt+Tax / Reverse</span></th>
                                <?php if (!empty($common_modal_show_images_column)): ?>
                                <th rowspan="2" data-column="images" style="vertical-align: middle;"><?php echo $common_modal_col_drag_locked; ?>Images</th>
                                <?php endif; ?>
                                <th rowspan="2" data-column="actions" style="min-width: 80px; width: 80px; text-align: center; background: #a68a4a !important; vertical-align: middle;"><?php echo $common_modal_col_drag_locked; ?>Action</th>
                            </tr>
                            <!-- Column header row (order must match body cells after checkbox) -->
                            <tr>
                                <th data-column="id" style="min-width: 60px;"><?php echo $common_modal_col_drag; ?>Id</th>
                                <th data-column="rfid" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>RFIDCode</th>
                                <th data-column="voucher-type" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>voucherTypeId</th>
                                <th data-column="photo" style="min-width: 70px;"><?php echo $common_modal_col_drag; ?>Photo</th>
                                <th data-column="barcode" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Barcode No.</th>
                                <th data-column="design-no" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Design No</th>
                                <th data-column="huid" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>HUID No.</th>
                                <th data-column="category" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Category <i class="feather icon-plus add-category-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Category"></i></th>
                                <th data-column="calculation" style="min-width: 140px;"><?php echo $common_modal_col_drag; ?>Calculation ...</th>
                                <th data-column="product" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Product* <i class="feather icon-plus add-product-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Product"></i></th>
                                <th data-column="location" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Location <i class="feather icon-plus add-location-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add Location"></i></th>
                                <th data-column="pkt-wt" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Pkt. Wt.</th>
                                <th data-column="pkt-less-wt" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>PKt. Less Wt.</th>
                                <th data-column="gross-wt" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Gross Wt.</th>
                                <th data-column="stone-weight" style="min-width: 110px;"><?php echo $common_modal_col_drag; ?>Carat</th>
                                <th data-column="less-wt" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>D.Weight</th>
                                <th data-column="net-wt" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Net Wt.</th>
                                <th data-column="quantity" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Quantity</th>
                                <th data-column="rate" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Rate</th>
                                <th data-column="amount" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Amount</th>
                                <th data-column="metal-qty" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Metal Qty</th>
                                <th data-column="metal-weight" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Weight</th>
                                <th data-column="carat" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Carat <i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer;"></i></th>
                                <th data-column="purity" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Purity %</th>
                                <th data-column="purity-wt" style="min-width: 90px;"><?php echo $common_modal_col_drag; ?>Purity Wt</th>
                                <th data-column="gold-loss1" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Loss Wt.</th>
                                <th data-column="gold-loss2" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Loss Wt. Per</th>
                                <th data-column="metal-loss-value" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Loss Value</th>
                                <th data-column="wastage-per" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Wastage Per</th>
                                <th data-column="wastage-wt" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Wastage Wt</th>
                                <th data-column="metal-rate" style="min-width: 90px;"><?php echo $common_modal_col_drag; ?>Metal Rate</th>
                                <th data-column="metal-value" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Metal Value</th>
                                <th data-column="metal-cost" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Metal Cost</th>
                                <th data-column="requested-purity" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Requested Pu...</th>
                                <th data-column="requested" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Requested...</th>
                                <th data-column="setting-charge" style="min-width: 110px;"><?php echo $common_modal_col_drag; ?>Setting Ch...</th>
                                <th data-column="final-wt" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Final Wt.</th>
                                <th data-column="alloy-wt" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Alloy Wt.</th>
                                <th data-column="discount-type" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Type</th>
                                <th data-column="discount-per" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Per.</th>
                                <th data-column="discount-amount" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Amount</th>
                                <th data-column="discount" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Discount</th>
                                <th data-column="making-type" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Type</th>
                                <th data-column="making-rate" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Rate</th>
                                <th data-column="making-discount-amt" style="min-width: 130px;"><?php echo $common_modal_col_drag; ?>Discount Amount</th>
                                <th data-column="making-amount" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Amount</th>
                                <th data-column="making-actual-value" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Actual Value</th>
                                <th data-column="making-cost" style="min-width: 110px;"><?php echo $common_modal_col_drag; ?>Making Cost</th>
                                <th data-column="min-price" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Minimum Price</th>
                                <th data-column="minimum" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Minimum ...</th>
                                <th data-column="stone-charge-type" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Type</th>
                                <th data-column="stone-rate" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Stone Rate</th>
                                <th data-column="stone-amount" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Stone Amount</th>
                                <th data-column="stone-cost" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Stone Cost</th>
                                <th data-column="diamond-amount" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Diamond Amount</th>
                                <th data-column="purchase-amount" style="min-width: 130px;"><?php echo $common_modal_col_drag; ?>Purchase Amount</th>
                                <th data-column="sale-amount" style="min-width: 110px;"><?php echo $common_modal_col_drag; ?>Sale Amount</th>
                                <th data-column="sale-amount-with" style="min-width: 130px;"><?php echo $common_modal_col_drag; ?>Sale Amount Wi...</th>
                                <th data-column="net-amt" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Net Amt</th>
                                <th data-column="tax-type" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Tax Type</th>
                                <th data-column="tax-percent" style="min-width: 70px;"><?php echo $common_modal_col_drag; ?>Tax %</th>
                                <th data-column="tax" style="min-width: 80px;"><?php echo $common_modal_col_drag; ?>Tax</th>
                                <th data-column="other-charge-type" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Type</th>
                                <th data-column="other-weight" style="min-width: 110px;"><?php echo $common_modal_col_drag; ?>Other Weight</th>
                                <th data-column="other-rate" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Other Rate</th>
                                <th data-column="other-info" style="min-width: 100px;"><?php echo $common_modal_col_drag; ?>Other Info</th>
                                <th data-column="other-amount" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>Other Amount</th>
                                <th data-column="hallmark-amount" style="min-width: 130px;"><?php echo $common_modal_col_drag; ?>Hallmark A...</th>
                                <th data-column="hallmark-rate" style="min-width: 120px;"><?php echo $common_modal_col_drag; ?>HallMark Rate</th>
                                <th data-column="net-amt-tax" style="min-width: 120px; background: #a68a4a !important;"><?php echo $common_modal_col_drag_locked; ?>Net Amt+Tax</th>
                                <th data-column="reverse" style="min-width: 80px; background: #a68a4a !important;"><?php echo $common_modal_col_drag_locked; ?>Reverse</th>
                            </tr>
                        </thead>
                        <tbody id="productListBody">
                            <tr>
                                <td colspan="<?php echo !empty($common_modal_show_images_column) ? '74' : '73'; ?>" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
                
                <!-- Bottom Section -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <label>Group Name</label>
                            <input type="text" class="form-control form-control-sm" id="modalGroupName" placeholder="Group Name">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <label>Comment</label>
                            <input type="text" class="form-control form-control-sm" id="modalComment" placeholder="Comment">
                        </div>
                    </div>
                </div>
                
                <div class="text-right mt-3 d-flex align-items-center justify-content-end" style="gap: 0.5rem;">
                    <button type="button" class="btn btn-purple btn-sm" id="modalAddBtn">
                        <i class="feather icon-plus"></i> Add (Shift + A)
                    </button>
                    <input type="file" id="productModalGroupImageInput" accept="image/*" style="display: none;">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="productModalUploadImageBtn" title="Upload image for this group (tab-wise)" style="margin-left: 0.5rem;">
                        <i class="feather icon-camera" style="font-size: 12px;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

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

<?php
// Ensure product-opening form data available for Add Product modal (same as product-opening.php)
if (!isset($tax_master_list)) {
    $tax_master_list = [];
    $tax_master_table = @mysqli_query($conn, "SELECT 1 FROM tbl_tax_master LIMIT 1");
    if ($tax_master_table && mysqli_num_rows($tax_master_table) > 0) {
        mysqli_free_result($tax_master_table);
        $tax_master_list = getList("SELECT id, name, default_value, default_calculation_mode FROM tbl_tax_master WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    }
}
if (!isset($units)) {
    $units = getList("SELECT id, name FROM tbl_unit WHERE status = 1 ORDER BY id ASC");
}
if (!isset($locations)) {
    $locations = getList("SELECT id, name FROM tbl_location WHERE status = 1 ORDER BY id ASC");
}
?>
<!-- Right Side Product Creation Modal -->
<div class="modal fade right" id="productCreationModal" tabindex="-1" role="dialog" aria-labelledby="productCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-right modal-xl" role="document" style="max-width: 90%; width: 90%; margin: 0; height: 100vh;">
        <div class="modal-content" style="height: 100vh; border-radius: 0; border: none;">
            <style>
                #productCreationModal .modal-body .sec-title,
                #productCreationModal .modal-body .card-box label { color: #1e293b !important; }
                #productCreationModal .modal-body .form-group label { color: #1e293b !important; }
                #productCreationModal .modal-body .tax-table-wrapper .sec-title { color: #1e293b !important; }
            </style>
            <div class="modal-header" style="background: #11294b; color: #fff; border: none; padding: 1rem 1.5rem;">
                <h5 class="modal-title" id="productCreationModalLabel">Add Product</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: calc(100vh - 60px); overflow-y: auto;">
                <form id="productCreationForm" method="post" action="product-save.php" style="height: 100%;">
                    <div style="display: flex; flex-direction: column; height: 100%;">
                        <!-- Top Section: Product Details + Tax -->
                        <div style="display: flex; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                            <!-- Product Details Form -->
                            <div class="card-box" style="flex: 1; padding: 1rem;">
                                <div class="d-flex justify-content-end mb-2">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearProductForm()" style="margin-right: 0.5rem;">Clear</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveProduct()" style="margin-right: 0.5rem; background: #11294b; border: none;">Save</button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                                </div>
                                <p class="sec-title">Product Details</p>
                                
                                <div class="form-row-custom">
                                    <div class="form-group">
                                        <label>Name *</label>
                                        <input name="name" id="productName" class="form-control" required>
                                    </div>
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
                                            <select name="category_id" id="productCategory" class="form-control" required>
                                                <option value="">Select Category</option>
                                                <?php 
                                                foreach($categories as $cat) {
                                                    echo '<option value="'.$cat['id'].'">'.htmlspecialchars($cat['name']).'</option>';
                                                }
                                                ?>
                                            </select>
                                            <i class="feather icon-plus add-icon" title="Add Category"></i>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Branch * <span style="color: red;">*</span></label>
                                        <div class="select-with-add">
                                            <div class="branch-tags" id="branchTagsContainer" data-required="true">
                                                <?php 
                                                // Same as product-opening: new product = main branch only (exclude Dubai)
                                                if (!empty($branches)) {
                                                    foreach($branches as $branch) {
                                                        $branch_name_lower = strtolower($branch['name']);
                                                        if (strpos($branch_name_lower, 'main') !== false && strpos($branch_name_lower, 'dubai') === false) {
                                                            $branch_name = htmlspecialchars($branch['name']);
                                                            echo '<span class="branch-tag" data-branch-id="'.$branch['id'].'">'.$branch_name.' <span class="remove-tag">×</span></span>';
                                                            echo '<input type="hidden" name="branch_ids[]" value="'.$branch['id'].'">';
                                                        }
                                                    }
                                                }
                                                if (empty($branches)) {
                                                    echo '<span class="text-muted" style="font-size: 0.8rem;">No branches available</span>';
                                                }
                                                ?>
                                                <span class="add-branch-btn"><i class="feather icon-plus"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group checkbox-custom">
                                        <label><input type="checkbox" name="is_stock_item" id="productShowInStock" value="1" checked> Show In Stock</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tax Box (same as product-opening: Tax Master when available, else VAT/TAX BAH) -->
                            <div class="card-box tax-table-wrapper" style="width: 400px; padding: 1rem;">
                                <p class="sec-title">Tax</p>
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
                                            <td><input type="text" name="tax_value[<?= (int)$t['id'] ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($val) ?>" step="0.01" style="width: 47px;"></td>
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
                                            <td><input type="checkbox" name="vat" id="productVAT" value="1"></td>
                                            <td><input type="text" name="vat_value" class="form-control form-control-sm" value="5" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="vat_calculation_mode" class="form-control form-control-sm" style="font-size: 0.75rem;">
                                                    <?php foreach($calculation_modes as $mode) { $selected = ($mode['name'] == 'Product Amount') ? 'selected' : ''; echo '<option value="'.htmlspecialchars($mode['name']).'" '.$selected.'>'.$mode['name'].'</option>'; } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" name="tax_bah" id="productTAXBAH" value="1"></td>
                                            <td><input type="text" name="tax_bah_value" class="form-control form-control-sm" value="10" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="tax_bah_calculation_mode" class="form-control form-control-sm" style="font-size: 0.75rem;">
                                                    <?php foreach($calculation_modes as $mode) { $selected = ($mode['name'] == 'Product Amount') ? 'selected' : ''; echo '<option value="'.htmlspecialchars($mode['name']).'" '.$selected.'>'.$mode['name'].'</option>'; } ?>
                                                </select>
                                            </td>
                                        </tr>
<?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Bottom Section: Product Characteristics Table -->
                        <div class="card-box" style="flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; padding: 1.5rem;">
                            <div class="pc-table-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <p class="sec-title mb-0">Product Characteristics</p>
                                <div style="position: relative;">
                                    <i class="feather icon-settings gear-icon" id="productModalColumnSettingsBtn" title="Column Settings" style="cursor: pointer; font-size: 1.2rem; color: #c5a864;"></i>
                                    <div class="columns-dropdown" id="productModalColumnsDropdown" style="position: absolute; right: 0; top: 100%; margin-top: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; min-width: 250px; max-width: 300px; display: none;">
                                        <div class="columns-dropdown-header" style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #ffffff;">Columns</div>
                                        <div class="columns-dropdown-search" style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">
                                            <input type="text" id="productModalColumnSearch" placeholder="Search columns..." style="width: 100%; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 0.8rem;">
                                        </div>
                                        <div class="columns-dropdown-list" id="productModalColumnsList" style="max-height: 300px; overflow-y: auto; padding: 8px;">
                                            <!-- Will be populated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pc-wrapper" style="flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden;">
                                <div class="pc-scroll" style="flex: 1; min-height: 0; overflow-x: auto !important; overflow-y: auto !important; width: 100%;">
                                    <table class="table table-sm table-bordered pc-table" style="width: max-content; min-width: 100%; white-space: nowrap; margin-bottom: 0;">
                                        <thead id="pcTableHead">
                                            <tr id="headerRow1">
                                                <th rowspan="2" class="draggable" data-col="check">✔</th>
                                                <th rowspan="2" class="draggable" data-col="metal">Metal</th>
                                                <th rowspan="2" class="draggable" data-col="hsn">HSN</th>
                                                <th rowspan="2" class="draggable" data-col="unit">Unit</th>
                                                <th rowspan="2" class="draggable" data-col="sku">SKU</th>
                                                <th rowspan="2" class="draggable" data-col="making">Making On</th>
                                                <th rowspan="2" class="draggable" data-col="diamond">Diamond Category</th>
                                                <th rowspan="2" class="draggable" data-col="location">Location</th>
                                                <th rowspan="2" class="draggable carat-header" data-col="carat">Karat</th>
                                                <th rowspan="2" class="draggable" data-col="discount">Discount</th>
                                                <th colspan="2" class="text-center" data-col="purity-group">Purity</th>
                                                <th colspan="2" class="text-center" data-col="wastage-group">Wastage</th>
                                                <th rowspan="2" class="draggable" data-col="wt-per-piece">Wt / Piece</th>
                                                <th colspan="6" class="text-center" data-col="opening">Opening</th>
                                                <th colspan="3" class="text-center" data-col="barcode-group">Barcode</th>
                                                <th rowspan="2" class="draggable" data-col="serialized">Serialized</th>
                                                <th colspan="7" class="text-center" data-col="styles">Basic Styles</th>
                                            </tr>
                                            <tr id="headerRow2">
                                                <th class="draggable" data-col="purity-sale">Sale</th>
                                                <th class="draggable" data-col="purity-purchase">Purchase</th>
                                                <th class="draggable" data-col="wastage-sale">Sale</th>
                                                <th class="draggable" data-col="wastage-purchase">Purchase</th>
                                                <th class="draggable" data-col="opening-weight">Weight</th>
                                                <th class="draggable" data-col="opening-purity">Purity</th>
                                                <th class="draggable" data-col="opening-qty">Qty</th>
                                                <th class="draggable" data-col="opening-finalwt">Final Wt</th>
                                                <th class="draggable" data-col="opening-rate">Rate</th>
                                                <th class="draggable" data-col="opening-value">Value</th>
                                                <th class="draggable" data-col="barcode-digits">Digits</th>
                                                <th class="draggable" data-col="barcode-prefix">Prefix</th>
                                                <th class="draggable" data-col="barcode">Barcode</th>
                                                <th class="draggable" data-col="cut">Cut</th>
                                                <th class="draggable" data-col="shape">Shape</th>
                                                <th class="draggable" data-col="color">Color</th>
                                                <th class="draggable" data-col="clarity">Clarity</th>
                                                <th class="draggable" data-col="sieve">Sieve</th>
                                                <th class="draggable" data-col="size">Size</th>
                                                <th class="draggable" data-col="stylecode">Style Code</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productCharacteristicsBody">
                                            <?php
                                            $metals_list = getList("SELECT id, display_name, hsn_code FROM tbl_metal WHERE status = 1 ORDER BY id ASC");
                                            $hsn_codes = [];
                                            foreach($metals_list as $m) {
                                                $hsn_codes[$m['display_name']] = $m['hsn_code'] ?: '7113';
                                            }
                                            $default_hsn = ['Gold' => '7113', 'Silver' => '7113', 'Platinum' => '999', 'Diamond & Stones' => '7105', 'Imitation Or Watches' => '7117', 'Other Or Services' => '7113'];
                                            $i = 0;
                                            foreach($metals_list as $metal) {
                                                $metal_name = $metal['display_name'];
                                                $hsn_code = isset($hsn_codes[$metal_name]) ? $hsn_codes[$metal_name] : (isset($default_hsn[$metal_name]) ? $default_hsn[$metal_name] : '7113');
                                                $is_diamond_stones = ($metal_name == "Diamond & Stones");
                                                $diamond_val = $is_diamond_stones ? 'Jewellery' : '';
                                            ?>
                                            <tr>
                                                <td data-col="check"><input type="checkbox" name="row[<?=$i?>][is_selected]" value="1"></td>
                                                <td data-col="metal">
                                                    <input type="hidden" name="row[<?=$i?>][metal]" value="<?= htmlspecialchars($metal_name) ?>">
                                                    <input type="hidden" name="row[<?=$i?>][metal_id]" value="<?= $metal['id'] ?>">
                                                    <?= htmlspecialchars($metal_name) ?>
                                                </td>
                                                <td data-col="hsn"><input name="row[<?=$i?>][hsn]" class="form-control form-control-sm" value="<?= $hsn_code ?>"></td>
                                                <td data-col="unit">
                                                    <select name="row[<?=$i?>][unit_id]" class="form-control form-control-sm">
                                                        <option value="">Select</option>
                                                        <?php if (!empty($units)) { foreach($units as $u) { echo '<option value="'.$u['id'].'">'.htmlspecialchars($u['name']).'</option>'; } } ?>
                                                    </select>
                                                </td>
                                                <td data-col="sku"><input name="row[<?=$i?>][sku_code]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="making"><input name="row[<?=$i?>][making_on]" class="form-control form-control-sm" value="Gross Wt"></td>
                                                <td data-col="diamond"><?php if ($is_diamond_stones) { ?>
                                                    <select name="row[<?=$i?>][diamond_category]" class="form-control form-control-sm">
                                                        <option value="">Select Diamond Category</option>
                                                        <option value="Diamonds">Diamonds</option>
                                                        <option value="GemStones">GemStones</option>
                                                        <option value="Jewellery" selected>Jewellery</option>
                                                    </select>
                                                <?php } else { ?>
                                                    <input type="text" name="row[<?=$i?>][diamond_category]" class="form-control form-control-sm" value="">
                                                <?php } ?></td>
                                                <td data-col="location">
                                                    <select name="row[<?=$i?>][location_id]" class="form-control form-control-sm">
                                                        <option value="">Select</option>
                                                        <?php if (!empty($locations)) { foreach($locations as $l) { echo '<option value="'.$l['id'].'">'.htmlspecialchars($l['name']).'</option>'; } } ?>
                                                    </select>
                                                </td>
                                                <td data-col="carat"><input name="row[<?=$i?>][carat]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="discount"><input name="row[<?=$i?>][discount]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="purity-sale"><input name="row[<?=$i?>][purity_sale]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="purity-purchase"><input type="checkbox" name="row[<?=$i?>][purity_purchase]" value="1"></td>
                                                <td data-col="wastage-sale"><input name="row[<?=$i?>][wastage_sale]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="wastage-purchase"><input name="row[<?=$i?>][wastage_purchase]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="wt-per-piece"><input name="row[<?=$i?>][wt_per_piece]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="opening-weight"><input name="row[<?=$i?>][opening_weight]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="opening-purity"><input name="row[<?=$i?>][opening_purity]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="opening-qty"><input name="row[<?=$i?>][opening_qty]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="opening-finalwt"><input name="row[<?=$i?>][final_weight]" class="form-control form-control-sm" value="" readonly></td>
                                                <td data-col="opening-rate"><input name="row[<?=$i?>][rate]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="opening-value"><input name="row[<?=$i?>][value]" class="form-control form-control-sm" value="" readonly></td>
                                                <td data-col="barcode-digits"><input name="row[<?=$i?>][barcode_digits]" class="form-control form-control-sm" value="5"></td>
                                                <td data-col="barcode-prefix"><input name="row[<?=$i?>][barcode_prefix]" class="form-control form-control-sm" value="RN"></td>
                                                <td data-col="barcode"><input name="row[<?=$i?>][barcode]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="serialized"><input type="checkbox" name="row[<?=$i?>][serialized_barcode]" value="1"></td>
                                                <td data-col="cut"><input name="row[<?=$i?>][cut]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="shape"><input name="row[<?=$i?>][shape]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="color"><input name="row[<?=$i?>][color]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="clarity"><input name="row[<?=$i?>][clarity]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="sieve"><input name="row[<?=$i?>][sieve]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="size"><input name="row[<?=$i?>][size]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="stylecode"><input name="row[<?=$i?>][style_code]" class="form-control form-control-sm" value=""></td>
                                            </tr>
                                            <?php $i++; } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                    <input type="text" class="form-control" id="cashAmount" value="0.00" step="0.01">
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
                    <label>Deposit Into</label>
                    <select class="form-control" id="bankDepositInto">
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
                    <input type="text" class="form-control" id="bankAmount" value="0.00" step="0.01">
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
                    <input type="text" class="form-control" id="chequeAmount" value="0.00" step="0.01">
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
                    <input type="text" class="form-control" id="upiAmount" value="0.00" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('upi')">Save</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/customer-ledger-modal.php'; ?>

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
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card</option>
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
                    <input type="text" class="form-control" id="cardAmount" value="0.00" step="0.01">
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

<script>
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
<script src="js/customer-ledger-address.js"></script>